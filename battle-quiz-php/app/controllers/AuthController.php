<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/MatchmakingModel.php';

class AuthController
{
    public function loginPage(?string $error = null): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: index.php?action=lobby');
            exit;
        }
        include __DIR__ . '/../view/auth/login.php';
    }

    public function login(): void
    {
        $username = trim((string)($_POST['username'] ?? ''));
        if ($username === '') {
            $this->loginPage('Ingresa un nombre para entrar a la arena.');
            return;
        }

        try {
            $user = User::findOrCreate($username);
            $_SESSION['db_available'] = true;
            $_SESSION['user'] = $user;
            MatchmakingModel::touchPresence((int)$user['id'], 'online');
        } catch (Throwable $e) {
            $user = ['id' => 0, 'username' => mb_substr($username, 0, 50), 'total_score' => 0];
            $_SESSION['db_available'] = false;
            $_SESSION['user'] = $user;
        }

        $_SESSION['ml'] = [
            'correct' => 0,
            'total' => 0,
            'streak' => 0,
            'avg_time' => 8.0,
            'difficulty' => 'medium'
        ];
        header('Location: index.php?action=lobby');
        exit;
    }

    public function logout(): void
    {
        if (!empty($_SESSION['user']['id']) && !empty($_SESSION['db_available'])) {
            try {
                MatchmakingModel::disconnect((int)$_SESSION['user']['id']);
            } catch (Throwable $e) {
                // El cierre de sesión debe continuar aunque MySQL no responda.
            }
        }
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
