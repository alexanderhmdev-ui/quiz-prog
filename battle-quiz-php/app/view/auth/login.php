<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Battle Quiz | Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-shell">
    <section class="auth-hero">
        <div class="brand brand-large"><div class="brand-mark">BQ</div><div><strong>Battle Quiz</strong><span>Programming Arena</span></div></div>
        <div class="hero-copy"><span class="eyebrow">JAVA + PHP + PYTHON + MYSQL</span><h1>Convierte tus conocimientos en una batalla.</h1><p>Crea tu usuario, encuentra rivales conectados, compite en PvP y entrena contra la IA con dificultad adaptativa mediante Machine Learning.</p></div>
        <div class="tech-row"><span>Spring Boot</span><span>PHP MVC</span><span>Python ML</span><span>MySQL</span></div>
    </section>
    <section class="auth-card">
        <div><span class="eyebrow">BIENVENIDO</span><h2>Entra a la arena</h2><p>Usa un nickname. Si es nuevo se creará en MySQL; si ya existe, entrarás con ese usuario y podrás buscar rivales conectados.</p></div>
        <?php if (!empty($error)): ?><div class="feedback bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="index.php?action=login" class="auth-form">
            <label>Nombre del jugador<input name="username" maxlength="30" required autocomplete="nickname" placeholder="Ej. Alexander"></label>
            <button class="primary-btn wide" type="submit">Ingresar a Battle Quiz</button>
        </form>
        <small>Proyecto académico con arquitectura de microservicios.</small>
    </section>
</div>
</body></html>
