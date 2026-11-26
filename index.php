<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
    header("Location: reproductor.php");
    exit;
}

// Si el error viene del registro, se abre esa pestaña
$pestana = (isset($_GET['error']) && strpos($_GET['error'], 'en uso') !== false) ? 'registro' : 'login';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | Plataforma Musical</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<main class="auth">
    <section class="auth-art" aria-hidden="false">
        <a class="brand" href="index.php">
            <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">
                <circle cx="16" cy="16" r="15" fill="#171b23" stroke="#2a303c"/>
                <circle cx="16" cy="16" r="9" fill="none" stroke="#2a303c" stroke-width="1.5"/>
                <circle cx="16" cy="16" r="4.5" fill="#f4b942"/>
                <circle cx="16" cy="16" r="1.3" fill="#101319"/>
            </svg>
            <span class="brand-name">Plataforma Musical</span>
        </a>

        <div class="copy">
            <h1>Tu música, ordenada por género.</h1>
            <p>Rock, pop, electrónica y urbano en una sola biblioteca, con enlace directo y código QR para cada canción.</p>
        </div>

        <div class="vinyl" aria-hidden="true"></div>
    </section>

    <section class="auth-side">
        <div class="auth-card">
            <a class="brand d-lg-none mb-4" href="index.php">
                <svg width="28" height="28" viewBox="0 0 32 32" aria-hidden="true">
                    <circle cx="16" cy="16" r="15" fill="#171b23" stroke="#2a303c"/>
                    <circle cx="16" cy="16" r="4.5" fill="#f4b942"/>
                </svg>
                <span class="brand-name">Plataforma Musical</span>
            </a>

            <?php if (isset($_GET['error'])): ?>
                <div class="notice err mb-3" role="alert"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg'])): ?>
                <div class="notice ok mb-3" role="status"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <div class="seg mb-4" role="tablist">
                <button type="button" role="tab" id="tab-login" data-target="panel-login" class="<?= $pestana === 'login' ? 'active' : '' ?>" aria-selected="<?= $pestana === 'login' ? 'true' : 'false' ?>">Iniciar sesión</button>
                <button type="button" role="tab" id="tab-registro" data-target="panel-registro" class="<?= $pestana === 'registro' ? 'active' : '' ?>" aria-selected="<?= $pestana === 'registro' ? 'true' : 'false' ?>">Crear cuenta</button>
            </div>

            <div id="panel-login" role="tabpanel" <?= $pestana === 'login' ? '' : 'hidden' ?>>
                <h2>Bienvenido de nuevo</h2>
                <p class="text-muted-2 mb-4">Entra para escuchar tu biblioteca.</p>
                <form action="auth/login_proceso.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label" for="login-usuario">Usuario</label>
                        <input type="text" id="login-usuario" name="nombre_usuario" class="form-control" autocomplete="username" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="login-pass">Contraseña</label>
                        <input type="password" id="login-pass" name="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-amber w-100 py-2">Entrar</button>
                </form>
            </div>

            <div id="panel-registro" role="tabpanel" <?= $pestana === 'registro' ? '' : 'hidden' ?>>
                <h2>Crea tu cuenta</h2>
                <p class="text-muted-2 mb-4">Las cuentas nuevas pueden escuchar y consultar la biblioteca.</p>
                <form action="auth/registro_proceso.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label" for="reg-usuario">Nombre de usuario</label>
                        <input type="text" id="reg-usuario" name="nombre_usuario" class="form-control" autocomplete="username" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="reg-pass">Contraseña</label>
                        <input type="password" id="reg-pass" name="password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-amber w-100 py-2">Crear cuenta</button>
                </form>
            </div>
        </div>
    </section>
</main>

<script>
document.querySelectorAll('.seg button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.seg button').forEach(function (b) {
            const activo = b === btn;
            b.classList.toggle('active', activo);
            b.setAttribute('aria-selected', activo ? 'true' : 'false');
            document.getElementById(b.dataset.target).hidden = !activo;
        });
    });
});
</script>

</body>
</html>