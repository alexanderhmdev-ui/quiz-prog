<!DOCTYPE html>
<html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Battle Quiz | Arena</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="style.css">
</head><body>
<div class="app-shell">
<aside class="sidebar"><div class="brand"><div class="brand-mark">BQ</div><div><strong>Battle Quiz</strong><span>Programming Arena</span></div></div>
<nav class="menu"><a class="menu-item" href="index.php?action=lobby">⌂ Dashboard</a><a class="menu-item active" href="index.php?action=battle">⚔ Batalla</a></nav>
<div class="sidebar-card"><span class="eyebrow">ML EN TIEMPO REAL</span><strong id="mlDifficultySide">Adaptativo</strong><p>El modelo analiza precisión, tiempo de respuesta y racha para sugerir el siguiente nivel.</p></div></aside>
<main class="main-content">
<header class="topbar"><div><p class="eyebrow">MODO INDIVIDUAL</p><h1>Battle Arena</h1></div><a class="ghost-link" href="index.php?action=lobby">← Dashboard</a></header>
<section class="arena-card">
<div class="arena-header"><div><span class="eyebrow">PARTIDA ACTUAL</span><h2>Duelo de conocimiento</h2></div><div class="match-stats"><div><span>RONDA</span><strong id="roundValue">1</strong></div><div><span>PUNTOS</span><strong id="scoreValue">0</strong></div><div><span>PRECISIÓN</span><strong id="accuracyValue">0%</strong></div></div></div>
<div class="fighters"><article class="fighter-card player-card"><div class="avatar player-avatar"><?= strtoupper(substr($_SESSION['user']['username'],0,2)) ?></div><div class="fighter-info"><div class="fighter-title"><span><?= htmlspecialchars($_SESSION['user']['username']) ?></span><strong><span id="playerHp">100</span> HP</strong></div><div class="hp-track"><div id="playerHpBar" class="hp-fill"></div></div></div></article><div class="versus">VS</div><article class="fighter-card enemy-card"><div class="avatar enemy-avatar">AI</div><div class="fighter-info"><div class="fighter-title"><span>Rival IA</span><strong><span id="enemyHp">100</span> HP</strong></div><div class="hp-track"><div id="enemyHpBar" class="hp-fill enemy-fill"></div></div></div></article></div>
<div id="startPanel" class="start-panel"><div class="start-icon">⚡</div><h3>Configura el duelo</h3><p>En adaptativo, Python usa Machine Learning para sugerir la dificultad según tu rendimiento.</p><div class="difficulty-picker"><button data-difficulty="adaptive" class="difficulty-btn active">Adaptativo <small>ML</small></button><button data-difficulty="easy" class="difficulty-btn">Fácil</button><button data-difficulty="medium" class="difficulty-btn">Medio</button><button data-difficulty="hard" class="difficulty-btn">Difícil</button></div><button id="startButton" class="primary-btn">Iniciar batalla</button></div>
<section id="questionPanel" class="question-panel hidden"><div class="question-meta"><div><span id="categoryBadge" class="category-badge">HTML</span><span id="difficultyBadge" class="difficulty-badge">medium</span></div><span id="timerLabel">00.0 s</span></div><h3 id="questionText">Pregunta</h3><div id="answersGrid" class="answers-grid"></div></section>
<div id="feedback" class="feedback neutral">Esperando para comenzar...</div>
<div id="endPanel" class="end-panel hidden"><h3 id="endTitle">Partida finalizada</h3><p id="endText"></p><div id="mlSummary" class="ml-summary"></div><button id="restartButton" class="primary-btn">Jugar otra vez</button></div>
</section>
<section class="info-grid"><article class="info-card"><span class="info-icon">✓</span><div><strong>Acierto</strong><p>Suma puntos, aumenta la racha y causa daño.</p></div></article><article class="info-card"><span class="info-icon">AI</span><div><strong>Machine Learning</strong><p>Analiza tu precisión, velocidad y racha.</p></div></article><article class="info-card"><span class="info-icon">DB</span><div><strong>Historial</strong><p>MySQL registra resultado, rondas y dificultad.</p></div></article></section>
</main></div><script src="app.js"></script></body></html>
