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
$pestana = 'canciones';

$carpetas_genero = [
    1 => 'uploads/rock/',
    2 => 'uploads/pop/',
    3 => 'uploads/electronica/',
    4 => 'uploads/urbano/'
];

$codigos_error_subida = [
    UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize del servidor.',
    UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
    UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente.',
    UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor.',
    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
    UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si el envío supera post_max_size, PHP vacía $_POST y $_FILES
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = "El envío supera post_max_size del servidor (" . ini_get('post_max_size') . "). Prueba con un MP3 más ligero.";
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'agregar_usuario' || $accion === 'eliminar_usuario') {
            $pestana = 'usuarios';
        }

        try {
            if ($accion === 'agregar_cancion') {
                $id_genero    = (int)($_POST['id_genero'] ?? 0);
                $nombre       = trim($_POST['nombre'] ?? '');
                $tipo         = trim($_POST['tipo'] ?? 'MP3') ?: 'MP3';
                $duracion     = trim($_POST['duracion'] ?? '03:30') ?: '03:30';
                $calificacion = max(1, min(5, (int)($_POST['calificacion'] ?? 5)));

                if (!isset($carpetas_genero[$id_genero])) {
                    $error = "Género inválido.";
                } elseif (!isset($_FILES['archivo_mp3'])) {
                    $error = "No se recibió ningún archivo (puede superar el límite de subida del servidor).";
                } elseif ($_FILES['archivo_mp3']['error'] !== UPLOAD_ERR_OK) {
                    $cod = (int)$_FILES['archivo_mp3']['error'];
                    $error = "Error al subir el archivo (código $cod): " . ($codigos_error_subida[$cod] ?? 'Error desconocido.');
                } else {
                    $ext = strtolower(pathinfo($_FILES['archivo_mp3']['name'], PATHINFO_EXTENSION));
                    if ($ext !== 'mp3') {
                        $error = "Solo se permiten archivos .mp3.";
                    } else {
                        $carpeta_relativa = $carpetas_genero[$id_genero];
                        $carpeta_absoluta = __DIR__ . '/' . $carpeta_relativa;

                        if (!is_dir($carpeta_absoluta) && !mkdir($carpeta_absoluta, 0755, true) && !is_dir($carpeta_absoluta)) {
                            $error = "No se pudo crear la carpeta en el servidor: " . $carpeta_relativa . " (créala manualmente desde el administrador de archivos).";
                        } else {
                            $nombre_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['archivo_mp3']['name'], PATHINFO_FILENAME));
                            if ($nombre_limpio === '') {
                                $nombre_limpio = 'audio';
                            }
                            $nombre_final  = $nombre_limpio . '_' . time() . '.mp3';
                            $ruta_relativa = $carpeta_relativa . $nombre_final;   // se guarda en la BD
                            $ruta_absoluta = $carpeta_absoluta . $nombre_final;   // ruta real en disco

                            if (move_uploaded_file($_FILES['archivo_mp3']['tmp_name'], $ruta_absoluta)) {
                                try {
                                    $stmt = $pdo->prepare("INSERT INTO canciones (id_genero, nombre, tipo, duracion, calificacion, ruta_archivo) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([$id_genero, $nombre, $tipo, $duracion, $calificacion, $ruta_relativa]);
                                    $mensaje = "Canción añadida correctamente.";
                                } catch (PDOException $e) {
                                    @unlink($ruta_absoluta);
                                    $error = "Error en la base de datos: " . $e->getMessage();
                                }
                            } else {
                                $error = "No se pudo guardar el archivo. Revisa los permisos de la carpeta: " . $carpeta_relativa;
                            }
                        }
                    }
                }
            }

            if ($accion === 'eliminar_cancion') {
                $id_cancion = (int)($_POST['id_cancion'] ?? 0);
                $stmt = $pdo->prepare("SELECT ruta_archivo FROM canciones WHERE id_cancion = ?");
                $stmt->execute([$id_cancion]);
                $cancion = $stmt->fetch();

                if ($cancion) {
                    $ruta_disco = __DIR__ . '/' . ltrim($cancion['ruta_archivo'] ?? '', '/');
                    if (!empty($cancion['ruta_archivo']) && is_file($ruta_disco)) {
                        @unlink($ruta_disco);
                    }
                    $del = $pdo->prepare("DELETE FROM canciones WHERE id_cancion = ?");
                    $del->execute([$id_cancion]);
                    $mensaje = "Canción eliminada.";
                }
            }

            if ($accion === 'agregar_usuario') {
                $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
                $password       = $_POST['password'] ?? '';
                $rol            = (($_POST['rol'] ?? 'consultor') === 'admin') ? 'admin' : 'consultor';

                try {
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password, rol) VALUES (?, ?, ?)");
                    $stmt->execute([$nombre_usuario, $password, $rol]);
                    $mensaje = "Usuario creado con rol '$rol'.";
                } catch (PDOException $e) {
                    $error = "Ese nombre de usuario ya existe.";
                }
            }

            if ($accion === 'eliminar_usuario') {
                $id_eliminar = (int)($_POST['id_usuario'] ?? 0);
                if ($id_eliminar === (int)$_SESSION['id_usuario']) {
                    $error = "No puedes eliminar tu propia cuenta activa.";
                } else {
                    $del = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
                    $del->execute([$id_eliminar]);
                    $mensaje = "Usuario eliminado.";
                }
            }
        } catch (Throwable $e) {
            $error = "Error inesperado: " . $e->getMessage();
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

$inicial = mb_strtoupper(mb_substr($_SESSION['nombre_usuario'], 0, 1));
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración | Plataforma Musical</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="reproductor.php">
            <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">
                <circle cx="16" cy="16" r="15" fill="#171b23" stroke="#2a303c"/>
                <circle cx="16" cy="16" r="9" fill="none" stroke="#2a303c" stroke-width="1.5"/>
                <circle cx="16" cy="16" r="4.5" fill="#f4b942"/>
                <circle cx="16" cy="16" r="1.3" fill="#101319"/>
            </svg>
            <span class="brand-name">Plataforma Musical</span>
        </a>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="user-chip">
                <span class="avatar" aria-hidden="true"><?= htmlspecialchars($inicial) ?></span>
                <span><?= htmlspecialchars($_SESSION['nombre_usuario']) ?></span>
                <span class="role-tag">admin</span>
            </div>
            <a href="reproductor.php" class="btn btn-sm btn-quiet">Ir a la biblioteca</a>
            <a href="auth/logout.php" class="btn btn-sm btn-quiet">Cerrar sesión</a>
        </div>
    </div>
</header>

<main class="page" style="padding-bottom:3rem">
    <div class="page-head">
        <div>
            <h1>Administración</h1>
            <p>Gestiona las canciones de la biblioteca y las cuentas de usuario.</p>
        </div>
        <div class="seg" role="tablist" style="min-width:300px">
            <button type="button" role="tab" data-target="pane-canciones" class="<?= $pestana === 'canciones' ? 'active' : '' ?>" aria-selected="<?= $pestana === 'canciones' ? 'true' : 'false' ?>">Canciones<span class="count"><?= count($canciones) ?></span></button>
            <button type="button" role="tab" data-target="pane-usuarios" class="<?= $pestana === 'usuarios' ? 'active' : '' ?>" aria-selected="<?= $pestana === 'usuarios' ? 'true' : 'false' ?>">Usuarios<span class="count"><?= count($usuarios) ?></span></button>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="notice ok mb-3" role="status"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice err mb-3" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ===================== CANCIONES ===================== -->
    <section id="pane-canciones" role="tabpanel" <?= $pestana === 'canciones' ? '' : 'hidden' ?>>
        <div class="admin-grid">
            <div class="panel sticky-form">
                <h2 class="h5 mb-3">Añadir canción</h2>
                <form method="POST" enctype="multipart/form-data" id="formCancion">
                    <input type="hidden" name="accion" value="agregar_cancion">

                    <div class="mb-3">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="archivo_mp3">Archivo MP3</label>
                        <div class="dropzone" id="dropzone">
                            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                            <span class="file-name" id="fileName">Arrastra un MP3 aquí o haz clic para elegirlo</span>
                            <input type="file" id="archivo_mp3" name="archivo_mp3" accept=".mp3,audio/mpeg" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select">
                                <option value="MP3">MP3</option>
                                <option value="Sencillo">Sencillo</option>
                                <option value="Álbum">Álbum</option>
                                <option value="En Vivo">En Vivo</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="duracion">Duración</label>
                            <input type="text" id="duracion" name="duracion" class="form-control" placeholder="03:30" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-7">
                            <label class="form-label" for="id_genero">Género</label>
                            <select id="id_genero" name="id_genero" class="form-select" required>
                                <?php foreach ($generos as $g): ?>
                                    <option value="<?= (int)$g['id_genero'] ?>"><?= htmlspecialchars($g['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="calificacion">Calificación</label>
                            <select id="calificacion" name="calificacion" class="form-select" required>
                                <option value="5">5 ★</option>
                                <option value="4">4 ★</option>
                                <option value="3">3 ★</option>
                                <option value="2">2 ★</option>
                                <option value="1">1 ★</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-amber w-100 py-2" id="btnGuardar">Guardar canción</button>
                </form>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Canción</th>
                            <th>Género</th>
                            <th>Duración</th>
                            <th>Calificación</th>
                            <th>Subida</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($canciones) > 0): ?>
                            <?php foreach ($canciones as $c): ?>
                                <?php
                                    $url_cancion = $ruta_base . ltrim($c['ruta_archivo'], '/');
                                    $estrellas   = max(1, min(5, (int)$c['calificacion']));
                                    $fecha       = $c['fecha_subida'] ? date('d/m/Y', strtotime($c['fecha_subida'])) : '';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($c['nombre']) ?></div>
                                        <div class="text-muted-2 small"><?= htmlspecialchars($c['tipo']) ?></div>
                                    </td>
                                    <td><span class="chip g-<?= (int)$c['id_genero'] ?>"><?= htmlspecialchars($c['genero_nombre']) ?></span></td>
                                    <td class="mono"><?= htmlspecialchars($c['duracion']) ?></td>
                                    <td class="stars" role="img" aria-label="Calificación <?= $estrellas ?> de 5">
                                        <?= str_repeat('★', $estrellas) ?><span class="off"><?= str_repeat('★', 5 - $estrellas) ?></span>
                                    </td>
                                    <td class="text-muted-2 small"><?= $fecha ?></td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" class="icon-btn qr-btn"
                                                    data-url="<?= htmlspecialchars($url_cancion) ?>"
                                                    data-title="<?= htmlspecialchars($c['nombre']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#qrModal"
                                                    aria-label="Ver código QR de <?= htmlspecialchars($c['nombre']) ?>" title="Código QR">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                                                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                                    <rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h3M20 20h1"/>
                                                </svg>
                                            </button>
                                            <form method="POST" class="d-inline" data-confirm="¿Eliminar «<?= htmlspecialchars($c['nombre']) ?>»? También se borrará el archivo de audio.">
                                                <input type="hidden" name="accion" value="eliminar_cancion">
                                                <input type="hidden" name="id_cancion" value="<?= (int)$c['id_cancion'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger-quiet">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty">
                                        <strong>Aún no hay canciones</strong>
                                        Sube tu primer MP3 con el formulario de la izquierda.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ===================== USUARIOS ===================== -->
    <section id="pane-usuarios" role="tabpanel" <?= $pestana === 'usuarios' ? '' : 'hidden' ?>>
        <div class="admin-grid">
            <div class="panel sticky-form">
                <h2 class="h5 mb-3">Añadir usuario</h2>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_usuario">
                    <div class="mb-3">
                        <label class="form-label" for="u_nombre">Nombre de usuario</label>
                        <input type="text" id="u_nombre" name="nombre_usuario" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="u_pass">Contraseña</label>
                        <input type="password" id="u_pass" name="password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="u_rol">Rol</label>
                        <select id="u_rol" name="rol" class="form-select">
                            <option value="consultor">consultor</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-amber w-100 py-2">Crear usuario</button>
                </form>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Contraseña</th>
                            <th>Rol</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($u['nombre_usuario']) ?></span>
                                    <?php if ((int)$u['id_usuario'] === (int)$_SESSION['id_usuario']): ?>
                                        <span class="role-tag ms-2">tú</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="secret mono" data-real="<?= htmlspecialchars($u['password']) ?>">••••••••</span>
                                    <button type="button" class="btn btn-sm btn-quiet ms-2 toggle-secret">Mostrar</button>
                                </td>
                                <td><span class="role-tag"><?= htmlspecialchars($u['rol']) ?></span></td>
                                <td>
                                    <div class="actions">
                                        <?php if ((int)$u['id_usuario'] !== (int)$_SESSION['id_usuario']): ?>
                                            <form method="POST" class="d-inline" data-confirm="¿Eliminar al usuario «<?= htmlspecialchars($u['nombre_usuario']) ?>»?">
                                                <input type="hidden" name="accion" value="eliminar_usuario">
                                                <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger-quiet">Eliminar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted-2 small">Sesión actual</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<!-- Modal QR -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h5 class="modal-title fs-6" id="qrTitle">Código QR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="qr-box"><img id="qrImg" src="" alt="Código QR del audio"></div>
                <p class="text-muted-2 small mt-3 mb-0">Escanéalo para abrir el audio en otro dispositivo.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <a id="qrLink" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-quiet">Abrir enlace directo</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pestañas Canciones / Usuarios
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

// Confirmación antes de eliminar
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!confirm(f.dataset.confirm)) e.preventDefault();
    });
});

// Mostrar / ocultar contraseñas
document.querySelectorAll('.toggle-secret').forEach(function (b) {
    b.addEventListener('click', function () {
        const s = b.previousElementSibling;
        const visible = b.dataset.visible === '1';
        s.textContent = visible ? '••••••••' : s.dataset.real;
        s.classList.toggle('secret', visible);
        b.textContent = visible ? 'Mostrar' : 'Ocultar';
        b.dataset.visible = visible ? '0' : '1';
    });
});

// Modal QR
document.querySelectorAll('.qr-btn').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('qrTitle').textContent = b.dataset.title;
        document.getElementById('qrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=' + encodeURIComponent(b.dataset.url);
        document.getElementById('qrLink').href = b.dataset.url;
    });
});

// Selector de archivo: muestra el nombre y calcula la duración automáticamente
const inputMp3 = document.getElementById('archivo_mp3');
inputMp3.addEventListener('change', function () {
    const file = this.files && this.files[0];
    const zona = document.getElementById('dropzone');
    if (!file) {
        zona.classList.remove('has-file');
        document.getElementById('fileName').textContent = 'Arrastra un MP3 aquí o haz clic para elegirlo';
        return;
    }
    zona.classList.add('has-file');
    document.getElementById('fileName').textContent = file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)';

    const nombre = document.getElementById('nombre');
    if (!nombre.value) nombre.value = file.name.replace(/\.mp3$/i, '').replace(/[_]+/g, ' ');

    const url = URL.createObjectURL(file);
    const audio = new Audio();
    audio.preload = 'metadata';
    audio.onloadedmetadata = function () {
        URL.revokeObjectURL(url);
        if (!isNaN(audio.duration) && isFinite(audio.duration)) {
            const total = Math.round(audio.duration);
            const mins = String(Math.floor(total / 60)).padStart(2, '0');
            const segs = String(total % 60).padStart(2, '0');
            document.getElementById('duracion').value = mins + ':' + segs;
        }
    };
    audio.onerror = function () { URL.revokeObjectURL(url); };
    audio.src = url;
});

// Evita doble envío mientras sube
document.getElementById('formCancion').addEventListener('submit', function () {
    const b = document.getElementById('btnGuardar');
    b.disabled = true;
    b.textContent = 'Subiendo…';
});
</script>

</body>
</html>