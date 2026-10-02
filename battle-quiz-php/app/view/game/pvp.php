<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Battle Quiz | PvP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body data-match-id="<?= (int)$matchId ?>">
<div class="app-shell">
<aside class="sidebar">
    <div class="brand"><div class="brand-mark">BQ</div><div><strong>Battle Quiz</strong><span>Programming Arena</span></div></div>
    <nav class="menu">
        <a class="menu-item" href="index.php?action=lobby">⌂ Dashboard</a>
        <a class="menu-item active" href="#">◉ PvP Online</a>
        <a class="menu-item" href="index.php?action=battle">⚔ vs IA</a>
    </nav>
    <div class="sidebar-card">
        <span class="eyebrow">SALA ONLINE</span>
        <strong>#<?= (int)$matchId ?></strong>
        <p><span class="mini-online-dot"></span> Sincronizada mediante PHP + MySQL.</p>
    </div>
</aside>

<main class="main-content">
<header class="topbar">
    <div><p class="eyebrow">DUELO MULTIJUGADOR</p><h1>Battle Arena PvP</h1></div>
    <button id="abandonButton" class="ghost-danger">Abandonar duelo</button>
</header>

<section class="arena-card pvp-arena">
    <div class="arena-header">
        <div><span class="eyebrow">PARTIDA #<?= (int)$matchId ?></span><h2>Duelo de conocimiento en tiempo real</h2></div>
        <div class="match-stats">
            <div><span>PREGUNTA</span><strong id="roundValue">-/-</strong></div>
            <div><span>TUS PUNTOS</span><strong id="scoreValue">0</strong></div>
            <div><span>RIVAL</span><strong id="opponentScoreValue">0</strong></div>
        </div>
    </div>

    <div class="fighters">
        <article class="fighter-card player-card">
            <div id="selfAvatar" class="avatar player-avatar">YO</div>
            <div class="fighter-info">
                <div class="fighter-title"><span id="selfName"><?= htmlspecialchars($initialState['self']['nickname'] ?? $_SESSION['user']['username']) ?></span><strong><span id="playerHp">100</span> HP</strong></div>
                <div class="hp-track"><div id="playerHpBar" class="hp-fill"></div></div>
                <small class="fighter-progress"><span id="selfAnswered">0</span> preguntas respondidas</small>
            </div>
        </article>
        <div class="versus live-versus"><span>VS</span><small>LIVE</small></div>
        <article class="fighter-card enemy-card">
            <div id="opponentAvatar" class="avatar enemy-avatar">??</div>
            <div class="fighter-info">
                <div class="fighter-title"><span id="opponentName"><?= htmlspecialchars($initialState['opponent']['nickname'] ?? 'Rival') ?></span><strong><span id="enemyHp">100</span> HP</strong></div>
                <div class="hp-track"><div id="enemyHpBar" class="hp-fill enemy-fill"></div></div>
                <small class="fighter-progress"><span id="opponentAnswered">0</span> preguntas respondidas</small>
            </div>
        </article>
    </div>

    <section id="questionPanel" class="question-panel">
        <div class="question-meta">
            <div><span id="categoryBadge" class="category-badge">...</span><span id="difficultyBadge" class="difficulty-badge">...</span></div>
            <div class="live-meta"><span class="mini-online-dot"></span><span id="timerLabel">0.0 s</span></div>
        </div>
        <h3 id="questionText">Cargando pregunta compartida...</h3>
        <div id="answersGrid" class="answers-grid"></div>
    </section>

    <div id="waitingPanel" class="pvp-waiting hidden">
        <div class="search-loader"><span></span><span></span><span></span></div>
        <h3>Ya terminaste tus preguntas</h3>
        <p>Esperando a que <b id="waitingOpponentName">tu rival</b> termine. El marcador se actualiza automáticamente.</p>
    </div>

    <div id="feedback" class="feedback neutral">Sincronizando la sala...</div>

    <div id="endPanel" class="end-panel hidden">
        <div id="endIcon" class="end-pvp-icon">🏆</div>
        <h3 id="endTitle">Duelo finalizado</h3>
        <p id="endText"></p>
        <div class="pvp-result-grid">
            <div><span>Tus puntos</span><strong id="endSelfScore">0</strong></div>
            <div><span>Tu HP</span><strong id="endSelfHp">0</strong></div>
            <div><span>Rival</span><strong id="endOpponentScore">0</strong></div>
        </div>
        <a class="primary-btn link-btn" href="index.php?action=lobby">Volver al lobby</a>
    </div>
</section>

<section class="info-grid">
    <article class="info-card"><span class="info-icon">PvP</span><div><strong>Mismas preguntas</strong><p>Ambos jugadores reciben el mismo conjunto de preguntas.</p></div></article>
    <article class="info-card"><span class="info-icon">DMG</span><div><strong>Daño por acierto</strong><p>Fácil: 10 · Medio: 15 · Difícil: 20 puntos de HP.</p></div></article>
    <article class="info-card"><span class="info-icon">DB</span><div><strong>Persistencia real</strong><p>Resultados, puntos y respuestas quedan registrados en MySQL.</p></div></article>
</section>
</main>
</div>
<script>window.BQ_INITIAL_PVP_STATE = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="pvp.js"></script>
</body>
</html>
