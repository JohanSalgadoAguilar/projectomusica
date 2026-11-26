<?php
session_start();
// Si ya inició sesión, mandarlo directo al reproductor
if (isset($_SESSION['id_usuario'])) {
    header("Location: reproductor.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | Plataforma Musical</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <div class="card bg-secondary text-white shadow">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">Iniciar Sesión</h3>
                    <form action="auth/login_proceso.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="correo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>

                    <hr class="my-4">

                    <h5 class="text-center mb-3">¿No tienes cuenta? Regístrate</h5>
                    <form action="auth/registro_proceso.php" method="POST">
                        <div class="mb-2">
                            <input type="text" name="nombre_usuario" class="form-control form-control-sm" placeholder="Nombre de usuario" required>
                        </div>
                        <div class="mb-2">
                            <input type="email" name="correo" class="form-control form-control-sm" placeholder="Correo electrónico" required>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Contraseña" required>
                        </div>
                        <button type="submit" class="btn btn-outline-light btn-sm w-100">Crear Cuenta</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>