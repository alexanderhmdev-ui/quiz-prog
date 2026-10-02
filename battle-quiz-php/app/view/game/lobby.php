<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Battle Quiz | Lobby</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
    <div class="brand"><div class="brand-mark">BQ</div><div><strong>Battle Quiz</strong><span>Programming Arena</span></div></div>
    <nav class="menu">
        <a class="menu-item active" href="index.php?action=lobby">⌂ Dashboard</a>
        <a class="menu-item" href="#matchmaking">◉ Emparejamiento</a>
        <a class="menu-item" href="index.php?action=battle">⚔ vs IA</a>
        <a class="menu-item" href="#ranking">🏆 Ranking</a>
    </nav>
    <div class="sidebar-card">
        <span class="eyebrow">JUGADOR CONECTADO</span>
        <strong><?= htmlspecialchars($_SESSION['user']['username']) ?></strong>
        <p><span id="presenceDot" class="mini-online-dot"></span> En línea · listo para PvP</p>
    </div>
    <a class="logout-link" href="index.php?action=logout">Cerrar sesión</a>
</aside>

<main class="main-content">
<header class="topbar">
    <div><p class="eyebrow">CENTRO DE MANDO</p><h1>Hola, <?= htmlspecialchars($_SESSION['user']['username']) ?></h1></div>
    <a class="ghost-link" href="index.php?action=battle">Entrenar vs IA</a>
</header>

<section class="dashboard-hero pvp-hero">
    <div>
        <span class="eyebrow">BATTLE QUIZ ONLINE</span>
        <h2>Busca un rival conectado y compite en tiempo real.</h2>
        <p>El sistema detecta usuarios activos, crea una sala compartida y entrega las mismas preguntas a ambos jugadores. Los puntos y el daño se guardan en MySQL.</p>
        <div class="hero-actions">
            <button id="quickMatchHero" class="primary-btn" <?= empty($_SESSION['db_available']) ? 'disabled' : '' ?>>Buscar rival</button>
            <a class="secondary-btn" href="index.php?action=battle">Jugar contra IA</a>
        </div>
    </div>
    <div class="hero-orb hero-versus"><span>PvP</span><small>ONLINE</small></div>
</section>

<section class="stats-grid">
    <div class="metric-card"><span>Partidas</span><strong><?= (int)($summary['games'] ?? 0) ?></strong><small>registradas</small></div>
    <div class="metric-card"><span>Victorias</span><strong><?= (int)($summary['victories'] ?? 0) ?></strong><small>acumuladas</small></div>
    <div class="metric-card"><span>Precisión</span><strong><?= number_format((float)($summary['accuracy'] ?? 0), 1) ?>%</strong><small>promedio</small></div>
    <div class="metric-card"><span>Puntos</span><strong><?= (int)($summary['score'] ?? 0) ?></strong><small>históricos</small></div>
</section>

<section id="matchmaking" class="matchmaking-grid">
    <article class="matchmaking-card">
        <div class="section-title-row">
            <div><span class="eyebrow">EMPAREJAMIENTO</span><h2>Duelo contra jugadores</h2></div>
            <div class="online-pill"><span class="mini-online-dot"></span><b id="onlineCount">1</b> online</div>
        </div>
        <div id="matchmakingIdle" class="matchmaking-state">
            <div class="radar-icon"><span></span></div>
            <h3>Encuentra un rival</h3>
            <p>Al buscar, entrarás a una cola. En cuanto otro usuario conectado busque partida, Battle Quiz los emparejará automáticamente.</p>
            <button id="queueButton" class="primary-btn" <?= empty($_SESSION['db_available']) ? 'disabled' : '' ?>>Buscar partida</button>
        </div>
        <div id="matchmakingSearching" class="matchmaking-state hidden">
            <div class="search-loader"><span></span><span></span><span></span></div>
            <h3>Buscando oponente...</h3>
            <p id="queueMessage">Esperando a otro jugador conectado.</p>
            <button id="cancelQueueButton" class="secondary-btn danger-soft">Cancelar búsqueda</button>
        </div>
        <div id="matchmakingFound" class="matchmaking-state hidden">
            <div class="found-icon">VS</div>
            <h3>¡Rival encontrado!</h3>
            <p id="foundOpponent">Preparando la arena...</p>
            <small>Entrando al duelo automáticamente.</small>
        </div>
        <?php if (empty($_SESSION['db_available'])): ?>
            <div class="feedback bad">El PvP necesita MySQL. Verifica la conexión a <b>127.0.0.1:8000</b> y la base <b>battle_quiz</b>.</div>
        <?php endif; ?>
    </article>

    <article class="online-users-card">
        <div class="section-title-row"><div><span class="eyebrow">PRESENCIA</span><h2>Jugadores conectados</h2></div><button id="refreshPlayers" class="icon-btn" title="Actualizar">↻</button></div>
        <div id="onlineUsersList" class="online-users-list">
            <div class="empty-online"><span class="pulse-dot"></span><p>Buscando jugadores activos...</p></div>
        </div>
        <p class="presence-note">Un usuario aparece como conectado mientras mantiene abierto el lobby o una batalla.</p>
    </article>
</section>

<section class="service-grid">
    <div class="service-card"><span class="service-dot <?= ($javaHealth['status'] ?? '') === 'ok' ? 'ok' : 'off' ?>"></span><div><b>Java API</b><p><?= ($javaHealth['status'] ?? '') === 'ok' ? 'Spring Boot conectado · :8081' : 'Sin conexión · inicia Spring Boot' ?></p></div></div>
    <div class="service-card"><span class="service-dot <?= ($pythonHealth['status'] ?? '') === 'ok' ? 'ok' : 'off' ?>"></span><div><b>Python ML</b><p><?= ($pythonHealth['status'] ?? '') === 'ok' ? 'Modelo activo · :5000' : 'Sin conexión · ejecuta python app.py' ?></p></div></div>
    <div class="service-card"><span class="service-dot <?= !empty($_SESSION['db_available']) ? 'ok' : 'off' ?>"></span><div><b>MySQL</b><p><?= !empty($_SESSION['db_available']) ? 'Usuarios, PvP e historial activos · :8000' : 'Sin conexión a la base de datos' ?></p></div></div>
</section>

<section id="ranking" class="ranking-card">
    <div class="section-title"><div><span class="eyebrow">CLASIFICACIÓN</span><h2>Ranking global</h2></div></div>
    <?php if (!$ranking): ?>
        <p class="muted">Aún no hay datos o MySQL no está conectado.</p>
    <?php else: ?>
        <div class="ranking-list">
            <?php foreach($ranking as $i=>$r): ?>
                <div class="ranking-row"><span class="rank-num">#<?= $i+1 ?></span><strong><?= htmlspecialchars($r['username']) ?></strong><b><?= (int)$r['total_score'] ?> pts</b></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
</main>
</div>
<script>window.BQ_DB_AVAILABLE = <?= !empty($_SESSION['db_available']) ? 'true' : 'false' ?>;</script>
<script src="lobby.js"></script>
</body>
</html>
