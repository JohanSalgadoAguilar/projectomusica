<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header("Location: reproductor.php");
    exit;
}

require_once 'config/db.php';

$mensaje = "";
$error = "";

$carpetas_genero = [
    1 => 'uploads/rock/',
    2 => 'uploads/pop/',
    3 => 'uploads/electronica/',
    4 => 'uploads/urbano/'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_cancion') {
        $id_genero    = (int)$_POST['id_genero'];
        $nombre       = trim($_POST['nombre']);
        $tipo         = trim($_POST['tipo']) ?: 'MP3';
        $duracion     = trim($_POST['duracion']) ?: '03:30';
        $calificacion = max(1, min(5, (int)$_POST['calificacion']));

        if (!isset($carpetas_genero[$id_genero])) {
            $error = "Género inválido.";
        } elseif (!isset($_FILES['archivo_mp3']) || $_FILES['archivo_mp3']['error'] !== UPLOAD_ERR_OK) {
            $error = "Debes seleccionar un archivo MP3 válido.";
        } else {
            $ext = strtolower(pathinfo($_FILES['archivo_mp3']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'mp3') {
                $error = "Solo se permiten archivos .mp3.";
            } else {
                $carpeta_destino = $carpetas_genero[$id_genero];
                if (!is_dir($carpeta_destino)) {
                    mkdir($carpeta_destino, 0755, true);
                }

                $nombre_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['archivo_mp3']['name'], PATHINFO_FILENAME));
                $nombre_final  = $nombre_limpio . '_' . time() . '.mp3';
                $ruta_relativa = $carpeta_destino . $nombre_final;

                if (move_uploaded_file($_FILES['archivo_mp3']['tmp_name'], $ruta_relativa)) {
                    $stmt = $pdo->prepare("INSERT INTO canciones (id_genero, nombre, tipo, duracion, calificacion, ruta_archivo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_genero, $nombre, $tipo, $duracion, $calificacion, $ruta_relativa]);
                    $mensaje = "Canción añadida correctamente.";
                } else {
                    $error = "No se pudo mover el archivo a la carpeta de destino.";
                }
            }
        }
    }

    if ($accion === 'eliminar_cancion') {
        $id_cancion = (int)$_POST['id_cancion'];
        $stmt = $pdo->prepare("SELECT ruta_archivo FROM canciones WHERE id_cancion = ?");
        $stmt->execute([$id_cancion]);
        $cancion = $stmt->fetch();

        if ($cancion) {
            if (!empty($cancion['ruta_archivo']) && file_exists($cancion['ruta_archivo'])) {
                unlink($cancion['ruta_archivo']);
            }
            $del = $pdo->prepare("DELETE FROM canciones WHERE id_cancion = ?");
            $del->execute([$id_cancion]);
            $mensaje = "Canción eliminada.";
        }
    }

    if ($accion === 'agregar_usuario') {
        $nombre_usuario = trim($_POST['nombre_usuario']);
        $password       = $_POST['password'];
        $rol            = ($_POST['rol'] === 'admin') ? 'admin' : 'consultor';

        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password, rol) VALUES (?, ?, ?)");
            $stmt->execute([$nombre_usuario, $password, $rol]);
            $mensaje = "Usuario creado con rol '$rol'.";
        } catch (PDOException $e) {
            $error = "Ese nombre de usuario ya existe.";
        }
    }

    if ($accion === 'eliminar_usuario') {
        $id_eliminar = (int)$_POST['id_usuario'];
        if ($id_eliminar === (int)$_SESSION['id_usuario']) {
            $error = "No puedes eliminar tu propia cuenta activa.";
        } else {
            $del = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $del->execute([$id_eliminar]);
            $mensaje = "Usuario eliminado.";
        }
    }
}

$generos = $pdo->query("SELECT * FROM generos ORDER BY id_genero ASC")->fetchAll();
$canciones = $pdo->query("
    SELECT c.*, g.nombre AS genero_nombre
    FROM canciones c
    INNER JOIN generos g ON c.id_genero = g.id_genero
    ORDER BY c.id_cancion DESC
")->fetchAll();
$usuarios = $pdo->query("SELECT id_usuario, nombre_usuario, password, rol FROM usuarios ORDER BY id_usuario ASC")->fetchAll();

$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$ruta_base = $protocolo . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">

<nav class="navbar navbar-dark bg-black px-4 mb-4">
    <span class="navbar-brand">⚙️ Panel Admin</span>
    <div class="d-flex align-items-center gap-2">
        <span class="me-2">Admin: <strong><?= htmlspecialchars($_SESSION['nombre_usuario']) ?></strong></span>
        <a href="reproductor.php" class="btn btn-sm btn-outline-light">Ir al Reproductor</a>
        <a href="auth/logout.php" class="btn btn-sm btn-danger">Cerrar Sesión</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h3 class="mb-3">🎵 Gestión de Música</h3>
    <div class="row g-4 mb-5">
        <div class="col-lg-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title mb-3">Añadir Canción</h5>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="agregar_cancion">

                        <div class="mb-2">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="MP3">MP3</option>
                                    <option value="Sencillo">Sencillo</option>
                                    <option value="Álbum">Álbum</option>
                                    <option value="En Vivo">En Vivo</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Duración</label>
                                <input type="text" name="duracion" class="form-control" placeholder="03:30" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-7">
                                <label class="form-label">Género</label>
                                <select name="id_genero" class="form-select" required>
                                    <?php foreach ($generos as $g): ?>
                                        <option value="<?= (int)$g['id_genero'] ?>"><?= htmlspecialchars($g['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label">Calificación</label>
                                <select name="calificacion" class="form-select" required>
                                    <option value="5">5 ★★★★★</option>
                                    <option value="4">4 ★★★★</option>
                                    <option value="3">3 ★★★</option>
                                    <option value="2">2 ★★</option>
                                    <option value="1">1 ★</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Archivo MP3</label>
                            <input type="file" name="archivo_mp3" accept=".mp3,audio/mpeg" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Guardar Canción</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-dark table-bordered table-hover align-middle text-center">
                    <thead class="table-secondary text-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Duración</th>
                            <th>Género</th>
                            <th>Calificación</th>
                            <th>Fecha</th>
                            <th>QR</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($canciones) > 0): ?>
                            <?php foreach ($canciones as $c): ?>
                                <?php
                                    $url_cancion = $ruta_base . ltrim($c['ruta_archivo'], '/');
                                    $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=" . urlencode($url_cancion);
                                    $estrellas = max(1, min(5, (int)$c['calificacion']));
                                ?>
                                <tr>
                                    <td class="text-start fw-semibold"><?= htmlspecialchars($c['nombre']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($c['tipo']) ?></span></td>
                                    <td><?= htmlspecialchars($c['duracion']) ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($c['genero_nombre']) ?></span></td>
                                    <td class="text-warning fs-5">
                                        <?= str_repeat('★', $estrellas) . str_repeat('☆', 5 - $estrellas) ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($c['fecha_subida'])) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($url_cancion) ?>" target="_blank" title="Abrir audio">
                                            <img src="<?= htmlspecialchars($qr_api) ?>" alt="QR" width="55" height="55" class="bg-white p-1 rounded">
                                        </a>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('¿Eliminar esta canción?');" class="d-inline">
                                            <input type="hidden" name="accion" value="eliminar_cancion">
                                            <input type="hidden" name="id_cancion" value="<?= (int)$c['id_cancion'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-muted py-4">No hay canciones registradas aún.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <h3 class="mb-3">👥 Gestión de Usuarios</h3>
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title mb-3">Añadir Usuario</h5>
                    <form method="POST">
                        <input type="hidden" name="accion" value="agregar_usuario">
                        <div class="mb-2">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="nombre_usuario" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Contraseña</label>
                            <input type="text" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="rol" class="form-select">
                                <option value="consultor">consultor</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Crear Usuario</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="table-responsive">
                <table class="table table-dark table-striped table-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Contraseña</th>
                            <th>Rol</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?= (int)$u['id_usuario'] ?></td>
                                <td><?= htmlspecialchars($u['nombre_usuario']) ?></td>
                                <td><?= htmlspecialchars($u['password']) ?></td>
                                <td>
                                    <span class="badge <?= ($u['rol'] === 'admin') ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                        <?= htmlspecialchars($u['rol']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$u['id_usuario'] !== (int)$_SESSION['id_usuario']): ?>
                                        <form method="POST" onsubmit="return confirm('¿Eliminar este usuario?');" class="d-inline">
                                            <input type="hidden" name="accion" value="eliminar_usuario">
                                            <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    <?php else: ?>
                                        <small class="text-muted">Sesión actual</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>