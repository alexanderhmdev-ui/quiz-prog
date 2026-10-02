<?php
session_start();
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/GameController.php';
require_once __DIR__ . '/../app/controllers/MatchmakingController.php';

$action = $_GET['action'] ?? (empty($_SESSION['user']) ? 'loginPage' : 'lobby');
$auth = new AuthController();
$game = new GameController();
$matchmaking = new MatchmakingController();

switch ($action) {
    case 'loginPage': $auth->loginPage(); break;
    case 'login': $auth->login(); break;
    case 'logout': $auth->logout(); break;

    case 'lobby': $game->lobby(); break;
    case 'battle': $game->battle(); break;
    case 'startBattle': $game->startBattle(); break;
    case 'answer': $game->answer(); break;
    case 'stats': $game->stats(); break;

    case 'heartbeat': $matchmaking->heartbeat(); break;
    case 'joinQueue': $matchmaking->joinQueue(); break;
    case 'leaveQueue': $matchmaking->leaveQueue(); break;
    case 'matchmakingStatus': $matchmaking->status(); break;
    case 'pvp': $matchmaking->pvp(); break;
    case 'pvpState': $matchmaking->pvpState(); break;
    case 'pvpAnswer': $matchmaking->pvpAnswer(); break;
    case 'pvpAbandon': $matchmaking->pvpAbandon(); break;

    default:
        http_response_code(404);
        echo 'Ruta no encontrada';
}
