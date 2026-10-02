<?php
require_once __DIR__ . '/../../services/JavaService.php';
require_once __DIR__ . '/../../services/PythonService.php';
require_once __DIR__ . '/../models/MatchModel.php';
require_once __DIR__ . '/../models/MatchmakingModel.php';

class GameController
{
    private function requireUser(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: index.php');
            exit;
        }
    }

    public function lobby(): void
    {
        $this->requireUser();
        $summary = ['games'=>0,'score'=>0,'accuracy'=>0,'victories'=>0];
        $ranking = [];
        if (!empty($_SESSION['db_available']) && (int)($_SESSION['user']['id'] ?? 0) > 0) {
            try {
                $summary = MatchModel::summary((int)$_SESSION['user']['id']);
                $ranking = MatchModel::ranking();
            } catch (Throwable $e) {}
        }
        try { MatchmakingModel::touchPresence((int)($_SESSION['user']['id'] ?? 0), 'online'); } catch (Throwable $e) {}
        $javaHealth = JavaService::health();
        $pythonHealth = PythonService::health();
        include __DIR__ . '/../view/game/lobby.php';
    }

    public function battle(): void
    {
        $this->requireUser();
        include __DIR__ . '/../view/game/battle.php';
    }

    public function startBattle(): void
    {
        $this->requireUser();
        $requested = strtolower((string)($_GET['difficulty'] ?? 'adaptive'));
        $recommended = (string)($_SESSION['ml']['difficulty'] ?? 'medium');
        $difficulty = $requested === 'adaptive' ? $recommended : $requested;
        if (!in_array($difficulty, ['easy','medium','hard'], true)) $difficulty = 'medium';

        $_SESSION['current_difficulty'] = $difficulty;
        $_SESSION['adaptive_mode'] = ($requested === 'adaptive');
        $_SESSION['saved_game_over'] = false;
        $_SESSION['ml']['correct'] = 0;
        $_SESSION['ml']['total'] = 0;
        $_SESSION['ml']['streak'] = 0;
        $_SESSION['battle_started_at'] = microtime(true);

        $data = JavaService::startBattle($difficulty);
        $data['mlDifficulty'] = $recommended;
        $this->json($data);
    }

    public function answer(): void
    {
        $this->requireUser();
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $answer = isset($payload['answer']) ? (int)$payload['answer'] : -1;
        $responseTime = max(0.2, min(60.0, (float)($payload['responseTime'] ?? 8.0)));

        $data = JavaService::answer($answer);
        if (($data['status'] ?? 'error') !== 'ok') {
            $this->json($data, 502);
            return;
        }

        $ml = $_SESSION['ml'] ?? ['correct'=>0,'total'=>0,'streak'=>0,'avg_time'=>8.0,'difficulty'=>'medium'];
        $ml['total']++;
        if (!empty($data['correct'])) {
            $ml['correct']++;
            $ml['streak']++;
        } else {
            $ml['streak'] = 0;
        }
        $ml['avg_time'] = (($ml['avg_time'] * max(0, $ml['total'] - 1)) + $responseTime) / max(1, $ml['total']);
        $accuracy = $ml['total'] > 0 ? $ml['correct'] / $ml['total'] : 0.0;

        $prediction = PythonService::recommend([
            'accuracy' => round($accuracy, 4),
            'avg_response_time' => round($ml['avg_time'], 2),
            'streak' => (int)$ml['streak'],
            'rounds' => (int)$ml['total'],
            'difficulty' => $_SESSION['current_difficulty'] ?? 'medium',
        ]);
        if (in_array(($prediction['difficulty'] ?? ''), ['easy','medium','hard'], true)) {
            $ml['difficulty'] = $prediction['difficulty'];
        }
        $_SESSION['ml'] = $ml;

        if (!empty($_SESSION['adaptive_mode']) && empty($data['gameOver'])) {
            $adapted = JavaService::adapt($ml['difficulty']);
            if (($adapted['status'] ?? '') === 'ok' && !empty($adapted['question'])) {
                $data['question'] = $adapted['question'];
                $data['difficulty'] = $adapted['difficulty'] ?? $ml['difficulty'];
            }
        }

        $data['ml'] = [
            'difficulty' => $ml['difficulty'],
            'accuracy' => round($accuracy * 100, 1),
            'avgResponseTime' => round($ml['avg_time'], 1),
            'streak' => $ml['streak'],
            'source' => $prediction['source'] ?? ($prediction['status'] ?? 'fallback'),
        ];

        if (!empty($data['gameOver']) && empty($_SESSION['saved_game_over'])) {
            $_SESSION['saved_game_over'] = true;
            if (!empty($_SESSION['db_available']) && (int)($_SESSION['user']['id'] ?? 0) > 0) {
                try {
                    $duration = isset($_SESSION['battle_started_at'])
                        ? max(0, (int)round(microtime(true) - (float)$_SESSION['battle_started_at']))
                        : 0;
                    MatchModel::save(
                        (int)$_SESSION['user']['id'],
                        (int)($data['score'] ?? 0),
                        (int)($data['round'] ?? 0),
                        ($data['winner'] ?? '') === 'player' ? 'victory' : 'defeat',
                        (string)($_SESSION['current_difficulty'] ?? 'medium'),
                        round($accuracy * 100, 2),
                        !empty($_SESSION['adaptive_mode']) ? 'adaptativo' : 'normal',
                        (int)($ml['streak'] ?? 0),
                        $duration
                    );
                } catch (Throwable $e) {}
            }
        }
        if (empty($data['gameOver'])) $_SESSION['saved_game_over'] = false;

        $this->json($data);
    }

    public function stats(): void
    {
        $this->requireUser();
        if (empty($_SESSION['db_available']) || (int)($_SESSION['user']['id'] ?? 0) <= 0) {
            $this->json(['status'=>'ok','recent'=>[],'summary'=>[],'database'=>'offline']);
            return;
        }
        try {
            $this->json([
                'status'=>'ok',
                'recent'=>MatchModel::recent((int)$_SESSION['user']['id']),
                'summary'=>MatchModel::summary((int)$_SESSION['user']['id']),
                'database'=>'online'
            ]);
        } catch (Throwable $e) {
            $this->json(['status'=>'error','message'=>'No se pudieron cargar las estadísticas.'], 500);
        }
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
