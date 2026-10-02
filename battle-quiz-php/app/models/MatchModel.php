<?php
require_once __DIR__ . '/../core/Database.php';

class MatchModel
{
    public static function save(
        int $userId,
        int $score,
        int $rounds,
        string $result,
        string $difficulty,
        float $accuracy,
        string $mode = 'normal',
        int $bestStreak = 0,
        int $durationSeconds = 0
    ): void {
        $pdo = Database::connection();
        $difficulty = self::difficultyToDb($difficulty);
        $result = $result === 'victory' ? 'victoria' : 'derrota';
        $mode = $mode === 'adaptativo' ? 'adaptativo' : 'normal';
        $correct = (int)round(($accuracy / 100) * max(0, $rounds));
        $incorrect = max(0, $rounds - $correct);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO partidas
                    (usuario_id, modo, dificultad_final, puntos, respuestas_correctas, respuestas_incorrectas,
                     precision_porcentaje, mejor_racha, resultado, duracion_segundos)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId, $mode, $difficulty, $score, $correct, $incorrect,
                round($accuracy, 2), max(0, $bestStreak), $result, max(0, $durationSeconds)
            ]);

            $victory = $result === 'victoria' ? 1 : 0;
            $defeat = $result === 'derrota' ? 1 : 0;
            $stmt = $pdo->prepare(
                'UPDATE usuarios
                 SET puntos_totales = puntos_totales + ?,
                     partidas_jugadas = partidas_jugadas + 1,
                     victorias = victorias + ?,
                     derrotas = derrotas + ?,
                     mejor_racha = GREATEST(mejor_racha, ?)
                 WHERE id = ?'
            );
            $stmt->execute([$score, $victory, $defeat, max(0, $bestStreak), $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function recent(int $userId, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT puntos AS score, (respuestas_correctas + respuestas_incorrectas) AS rounds,
                    CASE resultado WHEN 'victoria' THEN 'victory' WHEN 'empate' THEN 'draw' ELSE 'defeat' END AS result,
                    dificultad_final AS difficulty, precision_porcentaje AS accuracy, modo, jugada_en AS played_at
             FROM partidas WHERE usuario_id = ? ORDER BY id DESC LIMIT {$limit}"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function ranking(int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));
        return Database::connection()->query(
            "SELECT nickname AS username, puntos_totales AS total_score, victorias, partidas_jugadas
             FROM usuarios
             ORDER BY puntos_totales DESC, victorias DESC, nickname ASC
             LIMIT {$limit}"
        )->fetchAll();
    }

    public static function summary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) games,
                    COALESCE(SUM(puntos), 0) score,
                    COALESCE(AVG(precision_porcentaje), 0) accuracy,
                    SUM(resultado = 'victoria') victories
             FROM partidas WHERE usuario_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: ['games' => 0, 'score' => 0, 'accuracy' => 0, 'victories' => 0];
    }

    private static function difficultyToDb(string $difficulty): string
    {
        return match (strtolower($difficulty)) {
            'easy', 'facil' => 'facil',
            'hard', 'dificil' => 'dificil',
            default => 'medio',
        };
    }
}
