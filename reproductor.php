<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/db.php';

// Obtener los 4 géneros
$generos = $pdo->query("SELECT * FROM generos ORDER BY id_genero ASC")->fetchAll();

// Género seleccionado por GET (0 = Todos, o el ID de alguno de los 4 géneros; por defecto 0 para ver todo o 1 para el primero)
$genero_actual = isset($_GET['genero']) ? (int)$_GET['genero'] : 0;

if ($genero_actual > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, g.nombre AS genero_nombre
        FROM canciones c
        INNER JOIN generos g ON c.id_genero = g.id_genero
        WHERE c.id_genero = ?
        ORDER BY c.id_cancion ASC
    ");
    $stmt->execute([$genero_actual]);
    $canciones = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("
        SELECT c.*, g.nombre AS genero_nombre
        FROM canciones c
        INNER JOIN generos g ON c.id_genero = g.id_genero
        ORDER BY c.id_cancion ASC
    ");
    $canciones = $stmt->fetchAll();
}

// URL base del sitio (funciona tanto en localhost como en InfinityFree) para generar el QR del audio
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$ruta_base = $protocolo . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reproductor Musical</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">

<nav class="navbar navbar-dark bg-black px-4 mb-4">
    <span class="navbar-brand">🎵 Mi Música</span>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="me-2">
            Hola, <strong><?= htmlspecialchars($_SESSION['nombre_usuario']) ?></strong>
            <span class="badge bg-info text-dark"><?= htmlspecialchars($_SESSION['rol']) ?></span>
        </span>
        <?php if ($_SESSION['rol'] === 'admin'): ?>
            <a href="admin.php" class="btn btn-sm btn-warning">Panel Admin</a>
        <?php endif; ?>
        <a href="auth/logout.php" class="btn btn-sm btn-danger">Cerrar Sesión</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">
    <!-- Botones de filtro por los 4 Géneros -->
    <div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
        <a href="?genero=0" 
           class="btn <?= ($genero_actual === 0) ? 'btn-primary' : 'btn-outline-light' ?>">
            Todos
        </a>
        <?php foreach ($generos as $g): ?>
            <a href="?genero=<?= (int)$g['id_genero'] ?>" 
               class="btn <?= ((int)$g['id_genero'] === $genero_actual) ? 'btn-primary' : 'btn-outline-light' ?>">
                <?= htmlspecialchars($g['nombre']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tabla con las columnas del pizarrón + Reproductor -->
    <div class="table-responsive">
        <table class="table table-dark table-bordered table-hover align-middle text-center">
            <thead class="table-secondary text-dark">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Duración</th>
                    <th>Género</th>
                    <th>Calificación</th>
                    <th>Fecha</th>
                    <th>QR</th>
                    <th>Reproducir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($canciones) > 0): ?>
                    <?php foreach ($canciones as $index => $c): ?>
                        <?php
                            $url_audio = $ruta_base . ltrim($c['ruta_archivo'], '/');
                            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=" . urlencode($url_audio);
                            $estrellas = max(1, min(5, (int)$c['calificacion']));
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td class="text-start fw-semibold"><?= htmlspecialchars($c['nombre']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($c['tipo']) ?></span></td>
                            <td><?= htmlspecialchars($c['duracion']) ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($c['genero_nombre']) ?></span></td>
                            <td class="text-warning fs-5">
                                <?= str_repeat('★', $estrellas) . str_repeat('☆', 5 - $estrellas) ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($c['fecha_subida'])) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($url_audio) ?>" target="_blank" title="Abrir enlace directo del audio">
                                    <img src="<?= htmlspecialchars($qr_url) ?>" alt="QR <?= htmlspecialchars($c['nombre']) ?>" width="55" height="55" class="bg-white p-1 rounded">
                                </a>
                            </td>
                            <td>
                                <audio controls preload="none" style="height: 36px; max-width: 240px;">
                                    <source src="<?= htmlspecialchars($c['ruta_archivo']) ?>" type="audio/mpeg">
                                    Tu navegador no soporta audio.
                                </audio>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-muted py-4">No hay canciones registradas en esta selección.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>