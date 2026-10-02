<?php
require_once __DIR__ . '/../core/Database.php';

class MatchmakingModel
{
    private const PRESENCE_TTL_SECONDS = 35;
    private const QUEUE_TTL_SECONDS = 50;
    private const QUESTIONS_PER_MATCH = 10;

    public static function touchPresence(int $userId, string $state = 'online'): void
    {
        if ($userId <= 0) {
            return;
        }

        $allowed = ['online', 'queue', 'battle', 'offline'];
        if (!in_array($state, $allowed, true)) {
            $state = 'online';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO presencia_usuarios (usuario_id, session_key, estado, ultimo_ping)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                session_key = VALUES(session_key),
                estado = VALUES(estado),
                ultimo_ping = NOW()"
        );
        $stmt->execute([$userId, session_id(), $state]);
    }

    public static function disconnect(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM cola_emparejamiento WHERE usuario_id = ? AND estado = 'waiting'");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare(
                "INSERT INTO presencia_usuarios (usuario_id, session_key, estado, ultimo_ping)
                 VALUES (?, ?, 'offline', NOW())
                 ON DUPLICATE KEY UPDATE estado = 'offline', ultimo_ping = NOW()"
            );
            $stmt->execute([$userId, session_id()]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function onlineUsers(int $currentUserId): array
    {
        $pdo = Database::connection();
        self::cleanupStale($pdo);

        $seconds = self::PRESENCE_TTL_SECONDS;
        $stmt = $pdo->prepare(
            "SELECT u.id, u.nickname, u.puntos_totales,
                    p.estado,
                    TIMESTAMPDIFF(SECOND, p.ultimo_ping, NOW()) AS seconds_ago
             FROM presencia_usuarios p
             INNER JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.usuario_id <> ?
               AND p.estado <> 'offline'
               AND p.ultimo_ping >= DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)
             ORDER BY FIELD(p.estado, 'queue', 'battle', 'online'), u.puntos_totales DESC, u.nickname ASC
             LIMIT 30"
        );
        $stmt->execute([$currentUserId]);
        return $stmt->fetchAll();
    }

    public static function joinQueue(int $userId): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            self::cleanupStale($pdo, false);

            $existing = $pdo->prepare('SELECT * FROM cola_emparejamiento WHERE usuario_id = ? FOR UPDATE');
            $existing->execute([$userId]);
            $row = $existing->fetch();

            if ($row && $row['estado'] === 'matched' && !empty($row['duelo_id'])) {
                $pdo->commit();
                return self::status($userId);
            }

            if ($row && $row['estado'] === 'waiting') {
                $pdo->prepare('UPDATE cola_emparejamiento SET updated_at = NOW() WHERE usuario_id = ?')->execute([$userId]);
                self::upsertPresence($pdo, $userId, 'queue');
                $pdo->commit();
                return ['status' => 'ok', 'queueStatus' => 'waiting'];
            }

            $ttl = self::QUEUE_TTL_SECONDS;
            $opponentStmt = $pdo->prepare(
                "SELECT q.usuario_id
                 FROM cola_emparejamiento q
                 WHERE q.estado = 'waiting'
                   AND q.usuario_id <> ?
                   AND q.updated_at >= DATE_SUB(NOW(), INTERVAL {$ttl} SECOND)
                 ORDER BY q.joined_at ASC
                 LIMIT 1
                 FOR UPDATE"
            );
            $opponentStmt->execute([$userId]);
            $opponentId = (int)($opponentStmt->fetchColumn() ?: 0);

            if ($opponentId > 0) {
                $questionStmt = $pdo->query(
                    'SELECT id FROM preguntas WHERE activa = 1 ORDER BY RAND() LIMIT ' . self::QUESTIONS_PER_MATCH
                );
                $questionIds = array_map('intval', $questionStmt->fetchAll(PDO::FETCH_COLUMN));

                if (count($questionIds) < 4) {
                    throw new RuntimeException('Se necesitan al menos 4 preguntas activas para iniciar un duelo PvP.');
                }

                $insertMatch = $pdo->prepare(
                    "INSERT INTO duelos_pvp
                        (jugador1_id, jugador2_id, estado, started_at)
                     VALUES (?, ?, 'playing', NOW())"
                );
                $insertMatch->execute([$opponentId, $userId]);
                $matchId = (int)$pdo->lastInsertId();

                $insertQuestion = $pdo->prepare(
                    'INSERT INTO duelo_preguntas (duelo_id, posicion, pregunta_id) VALUES (?, ?, ?)'
                );
                foreach ($questionIds as $index => $questionId) {
                    $insertQuestion->execute([$matchId, $index + 1, $questionId]);
                }

                $pdo->prepare(
                    "UPDATE cola_emparejamiento
                     SET estado = 'matched', duelo_id = ?, updated_at = NOW()
                     WHERE usuario_id = ?"
                )->execute([$matchId, $opponentId]);

                $pdo->prepare(
                    "INSERT INTO cola_emparejamiento
                        (usuario_id, session_key, estado, duelo_id, joined_at, updated_at)
                     VALUES (?, ?, 'matched', ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                        session_key = VALUES(session_key), estado = 'matched', duelo_id = VALUES(duelo_id), updated_at = NOW()"
                )->execute([$userId, session_id(), $matchId]);

                self::upsertPresence($pdo, $opponentId, 'battle');
                self::upsertPresence($pdo, $userId, 'battle');
                $pdo->commit();

                return self::status($userId);
            }

            $pdo->prepare(
                "INSERT INTO cola_emparejamiento
                    (usuario_id, session_key, estado, duelo_id, joined_at, updated_at)
                 VALUES (?, ?, 'waiting', NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    session_key = VALUES(session_key), estado = 'waiting', duelo_id = NULL, joined_at = NOW(), updated_at = NOW()"
            )->execute([$userId, session_id()]);
            self::upsertPresence($pdo, $userId, 'queue');
            $pdo->commit();

            return ['status' => 'ok', 'queueStatus' => 'waiting'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function leaveQueue(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("DELETE FROM cola_emparejamiento WHERE usuario_id = ? AND estado = 'waiting'");
        $stmt->execute([$userId]);
        self::touchPresence($userId, 'online');
        return ['status' => 'ok', 'queueStatus' => 'idle'];
    }

    public static function status(int $userId): array
    {
        $pdo = Database::connection();
        self::cleanupStale($pdo);

        $stmt = $pdo->prepare(
            "SELECT q.estado, q.duelo_id,
                    CASE WHEN d.jugador1_id = ? THEN d.jugador2_id ELSE d.jugador1_id END AS rival_id,
                    d.estado AS duelo_estado
             FROM cola_emparejamiento q
             LEFT JOIN duelos_pvp d ON d.id = q.duelo_id
             WHERE q.usuario_id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId, $userId]);
        $queue = $stmt->fetch();

        if (!$queue) {
            self::touchPresence($userId, 'online');
            return ['status' => 'ok', 'queueStatus' => 'idle'];
        }

        if ($queue['estado'] === 'waiting') {
            $pdo->prepare('UPDATE cola_emparejamiento SET updated_at = NOW() WHERE usuario_id = ?')->execute([$userId]);
            self::touchPresence($userId, 'queue');
            return ['status' => 'ok', 'queueStatus' => 'waiting'];
        }

        if ($queue['estado'] === 'matched' && !empty($queue['duelo_id'])) {
            $oppStmt = $pdo->prepare('SELECT id, nickname, puntos_totales FROM usuarios WHERE id = ? LIMIT 1');
            $oppStmt->execute([(int)$queue['rival_id']]);
            $opponent = $oppStmt->fetch() ?: null;
            self::touchPresence($userId, 'battle');
            return [
                'status' => 'ok',
                'queueStatus' => 'matched',
                'matchId' => (int)$queue['duelo_id'],
                'opponent' => $opponent,
                'matchStatus' => $queue['duelo_estado'] ?: 'playing',
            ];
        }

        return ['status' => 'ok', 'queueStatus' => 'idle'];
    }

    public static function matchState(int $matchId, int $userId): array
    {
        $pdo = Database::connection();

        $match = self::loadMatch($pdo, $matchId, $userId);
        if (!$match) {
            throw new RuntimeException('No perteneces a este duelo o la partida no existe.');
        }
        self::touchPresence($userId, $match['estado'] === 'playing' ? 'battle' : 'online');

        $isPlayer1 = (int)$match['jugador1_id'] === $userId;
        $selfId = $userId;
        $opponentId = $isPlayer1 ? (int)$match['jugador2_id'] : (int)$match['jugador1_id'];
        $selfHp = $isPlayer1 ? (int)$match['jugador1_hp'] : (int)$match['jugador2_hp'];
        $oppHp = $isPlayer1 ? (int)$match['jugador2_hp'] : (int)$match['jugador1_hp'];
        $selfScore = $isPlayer1 ? (int)$match['jugador1_puntos'] : (int)$match['jugador2_puntos'];
        $oppScore = $isPlayer1 ? (int)$match['jugador2_puntos'] : (int)$match['jugador1_puntos'];

        $userStmt = $pdo->prepare('SELECT id, nickname, puntos_totales FROM usuarios WHERE id IN (?, ?) ORDER BY id');
        $userStmt->execute([$selfId, $opponentId]);
        $users = [];
        foreach ($userStmt->fetchAll() as $user) {
            $users[(int)$user['id']] = $user;
        }

        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM duelo_preguntas WHERE duelo_id = ?');
        $totalStmt->execute([$matchId]);
        $totalQuestions = (int)$totalStmt->fetchColumn();

        $countStmt = $pdo->prepare(
            'SELECT usuario_id, COUNT(*) answered FROM duelo_respuestas WHERE duelo_id = ? GROUP BY usuario_id'
        );
        $countStmt->execute([$matchId]);
        $answered = [$selfId => 0, $opponentId => 0];
        foreach ($countStmt->fetchAll() as $row) {
            $answered[(int)$row['usuario_id']] = (int)$row['answered'];
        }

        $question = null;
        if ($match['estado'] === 'playing' && $selfHp > 0 && $oppHp > 0 && ($answered[$selfId] ?? 0) < $totalQuestions) {
            $qStmt = $pdo->prepare(
                "SELECT dp.posicion, p.id, p.enunciado, p.dificultad, p.puntos, p.explicacion, c.nombre AS categoria
                 FROM duelo_preguntas dp
                 INNER JOIN preguntas p ON p.id = dp.pregunta_id
                 INNER JOIN categorias c ON c.id = p.categoria_id
                 LEFT JOIN duelo_respuestas dr
                    ON dr.duelo_id = dp.duelo_id
                   AND dr.pregunta_id = dp.pregunta_id
                   AND dr.usuario_id = ?
                 WHERE dp.duelo_id = ? AND dr.id IS NULL
                 ORDER BY dp.posicion ASC
                 LIMIT 1"
            );
            $qStmt->execute([$selfId, $matchId]);
            $q = $qStmt->fetch();
            if ($q) {
                $optStmt = $pdo->prepare(
                    'SELECT id, texto FROM opciones WHERE pregunta_id = ? ORDER BY orden_opcion ASC, id ASC'
                );
                $optStmt->execute([(int)$q['id']]);
                $options = array_map(
                    fn(array $o): array => ['id' => (int)$o['id'], 'text' => $o['texto']],
                    $optStmt->fetchAll()
                );

                $question = [
                    'id' => (int)$q['id'],
                    'position' => (int)$q['posicion'],
                    'text' => $q['enunciado'],
                    'category' => $q['categoria'],
                    'difficulty' => $q['dificultad'],
                    'points' => (int)$q['puntos'],
                    'options' => $options,
                ];
            }
        }

        $winner = null;
        if ($match['estado'] === 'finished') {
            if ((int)$match['empate'] === 1 || empty($match['ganador_id'])) {
                $winner = 'draw';
            } else {
                $winner = (int)$match['ganador_id'] === $selfId ? 'self' : 'opponent';
            }
        }

        return [
            'status' => 'ok',
            'match' => [
                'id' => (int)$match['id'],
                'status' => $match['estado'],
                'winner' => $winner,
                'createdAt' => $match['created_at'],
            ],
            'self' => [
                'id' => $selfId,
                'nickname' => $users[$selfId]['nickname'] ?? 'Jugador',
                'hp' => $selfHp,
                'score' => $selfScore,
                'answered' => $answered[$selfId] ?? 0,
            ],
            'opponent' => [
                'id' => $opponentId,
                'nickname' => $users[$opponentId]['nickname'] ?? 'Rival',
                'hp' => $oppHp,
                'score' => $oppScore,
                'answered' => $answered[$opponentId] ?? 0,
            ],
            'totalQuestions' => $totalQuestions,
            'question' => $question,
            'waitingOpponent' => $match['estado'] === 'playing' && $question === null,
            'finished' => $match['estado'] === 'finished',
        ];
    }

    public static function answer(int $matchId, int $userId, int $optionId, int $responseMs): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $matchStmt = $pdo->prepare('SELECT * FROM duelos_pvp WHERE id = ? FOR UPDATE');
            $matchStmt->execute([$matchId]);
            $match = $matchStmt->fetch();
            if (!$match || ((int)$match['jugador1_id'] !== $userId && (int)$match['jugador2_id'] !== $userId)) {
                throw new RuntimeException('No perteneces a este duelo.');
            }
            if ($match['estado'] !== 'playing') {
                $pdo->commit();
                return self::matchState($matchId, $userId);
            }

            $isPlayer1 = (int)$match['jugador1_id'] === $userId;
            $opponentId = $isPlayer1 ? (int)$match['jugador2_id'] : (int)$match['jugador1_id'];

            $qStmt = $pdo->prepare(
                "SELECT dp.posicion, p.id, p.dificultad, p.puntos, p.explicacion
                 FROM duelo_preguntas dp
                 INNER JOIN preguntas p ON p.id = dp.pregunta_id
                 LEFT JOIN duelo_respuestas dr
                    ON dr.duelo_id = dp.duelo_id
                   AND dr.pregunta_id = dp.pregunta_id
                   AND dr.usuario_id = ?
                 WHERE dp.duelo_id = ? AND dr.id IS NULL
                 ORDER BY dp.posicion ASC
                 LIMIT 1
                 FOR UPDATE"
            );
            $qStmt->execute([$userId, $matchId]);
            $question = $qStmt->fetch();
            if (!$question) {
                $pdo->commit();
                return self::matchState($matchId, $userId);
            }

            $optStmt = $pdo->prepare(
                'SELECT id, es_correcta FROM opciones WHERE id = ? AND pregunta_id = ? LIMIT 1'
            );
            $optStmt->execute([$optionId, (int)$question['id']]);
            $option = $optStmt->fetch();
            if (!$option) {
                throw new InvalidArgumentException('La opción seleccionada no pertenece a la pregunta actual.');
            }

            $correct = (int)$option['es_correcta'] === 1;
            $points = $correct ? (int)$question['puntos'] : 0;
            $damageMap = ['facil' => 10, 'medio' => 15, 'dificil' => 20];
            $damage = $correct ? ($damageMap[$question['dificultad']] ?? 15) : 0;
            $responseMs = max(100, min(120000, $responseMs));

            $insertAnswer = $pdo->prepare(
                "INSERT INTO duelo_respuestas
                    (duelo_id, usuario_id, pregunta_id, opcion_id, correcta, puntos, dano, tiempo_ms)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertAnswer->execute([
                $matchId,
                $userId,
                (int)$question['id'],
                $optionId,
                $correct ? 1 : 0,
                $points,
                $damage,
                $responseMs,
            ]);

            if ($isPlayer1) {
                $pdo->prepare(
                    'UPDATE duelos_pvp SET jugador1_puntos = jugador1_puntos + ?, jugador2_hp = GREATEST(0, jugador2_hp - ?) WHERE id = ?'
                )->execute([$points, $damage, $matchId]);
            } else {
                $pdo->prepare(
                    'UPDATE duelos_pvp SET jugador2_puntos = jugador2_puntos + ?, jugador1_hp = GREATEST(0, jugador1_hp - ?) WHERE id = ?'
                )->execute([$points, $damage, $matchId]);
            }

            self::finalizeIfNeeded($pdo, $matchId);
            $pdo->commit();

            $state = self::matchState($matchId, $userId);
            $state['answerFeedback'] = [
                'correct' => $correct,
                'points' => $points,
                'damage' => $damage,
                'explanation' => $question['explicacion'] ?: '',
            ];
            return $state;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function abandon(int $matchId, int $userId): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM duelos_pvp WHERE id = ? FOR UPDATE');
            $stmt->execute([$matchId]);
            $match = $stmt->fetch();
            if (!$match || ((int)$match['jugador1_id'] !== $userId && (int)$match['jugador2_id'] !== $userId)) {
                throw new RuntimeException('No perteneces a este duelo.');
            }

            if ($match['estado'] === 'playing') {
                $winnerId = (int)$match['jugador1_id'] === $userId
                    ? (int)$match['jugador2_id']
                    : (int)$match['jugador1_id'];
                $pdo->prepare(
                    "UPDATE duelos_pvp
                     SET estado = 'finished', ganador_id = ?, empate = 0, finished_at = NOW()
                     WHERE id = ?"
                )->execute([$winnerId, $matchId]);
                self::applyStats($pdo, $matchId);
            }
            $pdo->commit();
            return self::matchState($matchId, $userId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function finalizeIfNeeded(PDO $pdo, int $matchId): void
    {
        $stmt = $pdo->prepare('SELECT * FROM duelos_pvp WHERE id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        if (!$match || $match['estado'] !== 'playing') {
            return;
        }

        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM duelo_preguntas WHERE duelo_id = ?');
        $totalStmt->execute([$matchId]);
        $total = (int)$totalStmt->fetchColumn();

        $countStmt = $pdo->prepare(
            'SELECT usuario_id, COUNT(*) qty FROM duelo_respuestas WHERE duelo_id = ? GROUP BY usuario_id'
        );
        $countStmt->execute([$matchId]);
        $counts = [];
        foreach ($countStmt->fetchAll() as $row) {
            $counts[(int)$row['usuario_id']] = (int)$row['qty'];
        }

        $p1 = (int)$match['jugador1_id'];
        $p2 = (int)$match['jugador2_id'];
        $p1Done = ($counts[$p1] ?? 0) >= $total;
        $p2Done = ($counts[$p2] ?? 0) >= $total;
        $hpEnded = (int)$match['jugador1_hp'] <= 0 || (int)$match['jugador2_hp'] <= 0;

        if (!$hpEnded && !($p1Done && $p2Done)) {
            return;
        }

        $winnerId = null;
        $draw = 0;
        $p1Hp = (int)$match['jugador1_hp'];
        $p2Hp = (int)$match['jugador2_hp'];
        $p1Score = (int)$match['jugador1_puntos'];
        $p2Score = (int)$match['jugador2_puntos'];

        if ($p1Hp <= 0 && $p2Hp > 0) {
            $winnerId = $p2;
        } elseif ($p2Hp <= 0 && $p1Hp > 0) {
            $winnerId = $p1;
        } elseif ($p1Score !== $p2Score) {
            $winnerId = $p1Score > $p2Score ? $p1 : $p2;
        } elseif ($p1Hp !== $p2Hp) {
            $winnerId = $p1Hp > $p2Hp ? $p1 : $p2;
        } else {
            $draw = 1;
        }

        $pdo->prepare(
            "UPDATE duelos_pvp
             SET estado = 'finished', ganador_id = ?, empate = ?, finished_at = NOW()
             WHERE id = ?"
        )->execute([$winnerId, $draw, $matchId]);

        self::applyStats($pdo, $matchId);
    }

    private static function applyStats(PDO $pdo, int $matchId): void
    {
        $stmt = $pdo->prepare('SELECT * FROM duelos_pvp WHERE id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        if (!$match || (int)$match['stats_applied'] === 1) {
            return;
        }

        $players = [
            (int)$match['jugador1_id'] => (int)$match['jugador1_puntos'],
            (int)$match['jugador2_id'] => (int)$match['jugador2_puntos'],
        ];
        $winnerId = $match['ganador_id'] !== null ? (int)$match['ganador_id'] : null;
        $isDraw = (int)$match['empate'] === 1;

        $statsStmt = $pdo->prepare(
            "SELECT usuario_id, COUNT(*) total, COALESCE(SUM(correcta = 1), 0) correctas
             FROM duelo_respuestas
             WHERE duelo_id = ?
             GROUP BY usuario_id"
        );
        $statsStmt->execute([$matchId]);
        $answerStats = [];
        foreach ($statsStmt->fetchAll() as $row) {
            $answerStats[(int)$row['usuario_id']] = [
                'total' => (int)$row['total'],
                'correct' => (int)$row['correctas'],
            ];
        }

        foreach ($players as $playerId => $score) {
            $victory = !$isDraw && $winnerId === $playerId ? 1 : 0;
            $defeat = !$isDraw && $winnerId !== null && $winnerId !== $playerId ? 1 : 0;

            $pdo->prepare(
                "UPDATE usuarios
                 SET puntos_totales = puntos_totales + ?,
                     partidas_jugadas = partidas_jugadas + 1,
                     victorias = victorias + ?,
                     derrotas = derrotas + ?
                 WHERE id = ?"
            )->execute([$score, $victory, $defeat, $playerId]);

            $total = $answerStats[$playerId]['total'] ?? 0;
            $correct = $answerStats[$playerId]['correct'] ?? 0;
            $incorrect = max(0, $total - $correct);
            $accuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0.0;
            $result = $isDraw ? 'empate' : ($victory ? 'victoria' : 'derrota');

            $pdo->prepare(
                "INSERT INTO partidas
                    (usuario_id, modo, dificultad_final, puntos, respuestas_correctas, respuestas_incorrectas,
                     precision_porcentaje, mejor_racha, resultado, duracion_segundos)
                 VALUES (?, 'pvp', 'medio', ?, ?, ?, ?, 0, ?, GREATEST(0, TIMESTAMPDIFF(SECOND, ?, NOW())))"
            )->execute([
                $playerId,
                $score,
                $correct,
                $incorrect,
                $accuracy,
                $result,
                $match['started_at'] ?: $match['created_at'],
            ]);
        }

        $pdo->prepare('UPDATE duelos_pvp SET stats_applied = 1 WHERE id = ?')->execute([$matchId]);
        $pdo->prepare('DELETE FROM cola_emparejamiento WHERE usuario_id IN (?, ?)')->execute([
            (int)$match['jugador1_id'],
            (int)$match['jugador2_id'],
        ]);
        self::upsertPresence($pdo, (int)$match['jugador1_id'], 'online');
        self::upsertPresence($pdo, (int)$match['jugador2_id'], 'online');
    }

    private static function loadMatch(PDO $pdo, int $matchId, int $userId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM duelos_pvp WHERE id = ? AND (jugador1_id = ? OR jugador2_id = ?) LIMIT 1'
        );
        $stmt->execute([$matchId, $userId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function cleanupStale(PDO $pdo, bool $allowCommit = true): void
    {
        $ttl = self::QUEUE_TTL_SECONDS;
        $pdo->exec(
            "DELETE FROM cola_emparejamiento
             WHERE estado = 'waiting'
               AND updated_at < DATE_SUB(NOW(), INTERVAL {$ttl} SECOND)"
        );

        $presenceTtl = self::PRESENCE_TTL_SECONDS;
        $pdo->exec(
            "UPDATE presencia_usuarios
             SET estado = 'offline'
             WHERE estado <> 'offline'
               AND ultimo_ping < DATE_SUB(NOW(), INTERVAL {$presenceTtl} SECOND)"
        );
    }

    private static function upsertPresence(PDO $pdo, int $userId, string $state): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO presencia_usuarios (usuario_id, session_key, estado, ultimo_ping)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), ultimo_ping = NOW()"
        );
        $stmt->execute([$userId, session_id(), $state]);
    }
}
