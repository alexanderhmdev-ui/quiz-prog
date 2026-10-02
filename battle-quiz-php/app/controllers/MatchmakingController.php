<?php
require_once __DIR__ . '/../models/MatchmakingModel.php';

class MatchmakingController
{
    private function requireUser(): array
    {
        if (empty($_SESSION['user']) || (int)($_SESSION['user']['id'] ?? 0) <= 0) {
            if ($this->isApiRequest()) {
                $this->json(['status' => 'error', 'message' => 'Necesitas una sesión conectada a MySQL.'], 401);
                exit;
            }
            header('Location: index.php');
            exit;
        }
        return $_SESSION['user'];
    }

    public function heartbeat(): void
    {
        $user = $this->requireUser();
        try {
            $status = MatchmakingModel::status((int)$user['id']);
            $online = MatchmakingModel::onlineUsers((int)$user['id']);
            $this->json([
                'status' => 'ok',
                'queue' => $status,
                'onlineUsers' => $online,
                'onlineCount' => count($online) + 1,
            ]);
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function joinQueue(): void
    {
        $user = $this->requireUser();
        try {
            $this->json(MatchmakingModel::joinQueue((int)$user['id']));
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function leaveQueue(): void
    {
        $user = $this->requireUser();
        try {
            $this->json(MatchmakingModel::leaveQueue((int)$user['id']));
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function status(): void
    {
        $user = $this->requireUser();
        try {
            $this->json(MatchmakingModel::status((int)$user['id']));
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function pvp(): void
    {
        $user = $this->requireUser();
        $matchId = max(0, (int)($_GET['match'] ?? 0));
        if ($matchId <= 0) {
            header('Location: index.php?action=lobby');
            exit;
        }

        try {
            $initialState = MatchmakingModel::matchState($matchId, (int)$user['id']);
        } catch (Throwable $e) {
            header('Location: index.php?action=lobby');
            exit;
        }
        include __DIR__ . '/../view/game/pvp.php';
    }

    public function pvpState(): void
    {
        $user = $this->requireUser();
        $matchId = max(0, (int)($_GET['match'] ?? 0));
        try {
            $this->json(MatchmakingModel::matchState($matchId, (int)$user['id']));
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function pvpAnswer(): void
    {
        $user = $this->requireUser();
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $matchId = max(0, (int)($payload['matchId'] ?? 0));
        $optionId = max(0, (int)($payload['optionId'] ?? 0));
        $responseMs = max(100, (int)($payload['responseMs'] ?? 1000));

        try {
            $this->json(MatchmakingModel::answer($matchId, (int)$user['id'], $optionId, $responseMs));
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function pvpAbandon(): void
    {
        $user = $this->requireUser();
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $matchId = max(0, (int)($payload['matchId'] ?? 0));
        try {
            $this->json(MatchmakingModel::abandon($matchId, (int)$user['id']));
        } catch (Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    private function isApiRequest(): bool
    {
        $action = $_GET['action'] ?? '';
        return in_array($action, ['heartbeat', 'joinQueue', 'leaveQueue', 'matchmakingStatus', 'pvpState', 'pvpAnswer', 'pvpAbandon'], true);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
