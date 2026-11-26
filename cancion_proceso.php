<?php
// Subida y borrado de canciones para usuarios con sesión (admin y consultor).
// - Cualquier usuario con sesión puede subir.
// - Un consultor solo puede borrar las canciones que subió él; el admin puede borrar cualquiera.
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: reproductor.php");
    exit;
}

require_once 'config/db.php';

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

$id_sesion = (int)$_SESSION['id_usuario'];
$es_admin  = ($_SESSION['rol'] === 'admin');
$genero_vuelta = (int)($_POST['volver_genero'] ?? 0);

function volver(string $tipo, string $texto, int $genero): void
{
    $url = "reproductor.php?";
    if ($genero > 0) {
        $url .= "genero=" . $genero . "&";
    }
    $url .= $tipo . "=" . urlencode($texto);
    header("Location: " . $url);
    exit;
}

// Si el envío supera post_max_size, PHP vacía $_POST y $_FILES
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    volver('error', "El envío supera el límite del servidor (" . ini_get('post_max_size') . "). Prueba con un MP3 más ligero.", 0);
}

$accion = $_POST['accion'] ?? '';

try {
    if ($accion === 'agregar_cancion') {
        $id_genero    = (int)($_POST['id_genero'] ?? 0);
        $nombre       = trim($_POST['nombre'] ?? '');
        $tipo         = trim($_POST['tipo'] ?? 'MP3') ?: 'MP3';
        $duracion     = trim($_POST['duracion'] ?? '03:30') ?: '03:30';
        $calificacion = max(1, min(5, (int)($_POST['calificacion'] ?? 5)));

        if ($nombre === '') {
            volver('error', "Escribe un nombre para la canción.", $genero_vuelta);
        }
        if (!isset($carpetas_genero[$id_genero])) {
            volver('error', "Género inválido.", $genero_vuelta);
        }
        if (!isset($_FILES['archivo_mp3'])) {
            volver('error', "No se recibió ningún archivo.", $genero_vuelta);
        }
        if ($_FILES['archivo_mp3']['error'] !== UPLOAD_ERR_OK) {
            $cod = (int)$_FILES['archivo_mp3']['error'];
            volver('error', "Error al subir el archivo (código $cod): " . ($codigos_error_subida[$cod] ?? 'Error desconocido.'), $genero_vuelta);
        }

        $ext = strtolower(pathinfo($_FILES['archivo_mp3']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mp3') {
            volver('error', "Solo se permiten archivos .mp3.", $genero_vuelta);
        }

        $carpeta_relativa = $carpetas_genero[$id_genero];
        $carpeta_absoluta = __DIR__ . '/' . $carpeta_relativa;
        if (!is_dir($carpeta_absoluta) && !mkdir($carpeta_absoluta, 0755, true) && !is_dir($carpeta_absoluta)) {
            volver('error', "No se pudo crear la carpeta " . $carpeta_relativa . " en el servidor.", $genero_vuelta);
        }

        $nombre_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['archivo_mp3']['name'], PATHINFO_FILENAME));
        if ($nombre_limpio === '') {
            $nombre_limpio = 'audio';
        }
        $nombre_final  = $nombre_limpio . '_' . time() . '.mp3';
        $ruta_relativa = $carpeta_relativa . $nombre_final;
        $ruta_absoluta = $carpeta_absoluta . $nombre_final;

        if (!move_uploaded_file($_FILES['archivo_mp3']['tmp_name'], $ruta_absoluta)) {
            volver('error', "No se pudo guardar el archivo. Revisa los permisos de " . $carpeta_relativa, $genero_vuelta);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO canciones (id_genero, nombre, tipo, duracion, calificacion, ruta_archivo, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_genero, $nombre, $tipo, $duracion, $calificacion, $ruta_relativa, $id_sesion]);
        } catch (PDOException $e) {
            @unlink($ruta_absoluta);
            volver('error', "Error en la base de datos: " . $e->getMessage(), $genero_vuelta);
        }

        volver('msg', "Canción añadida correctamente.", $genero_vuelta);
    }

    if ($accion === 'eliminar_cancion') {
        $id_cancion = (int)($_POST['id_cancion'] ?? 0);
        $stmt = $pdo->prepare("SELECT ruta_archivo, id_usuario FROM canciones WHERE id_cancion = ?");
        $stmt->execute([$id_cancion]);
        $cancion = $stmt->fetch();

        if (!$cancion) {
            volver('error', "La canción ya no existe.", $genero_vuelta);
        }

        $es_dueno = ($cancion['id_usuario'] !== null && (int)$cancion['id_usuario'] === $id_sesion);
        if (!$es_admin && !$es_dueno) {
            volver('error', "Solo puedes eliminar las canciones que subiste tú.", $genero_vuelta);
        }

        $ruta_disco = __DIR__ . '/' . ltrim($cancion['ruta_archivo'] ?? '', '/');
        if (!empty($cancion['ruta_archivo']) && is_file($ruta_disco)) {
            @unlink($ruta_disco);
        }
        $del = $pdo->prepare("DELETE FROM canciones WHERE id_cancion = ?");
        $del->execute([$id_cancion]);
        volver('msg', "Canción eliminada.", $genero_vuelta);
    }
} catch (Throwable $e) {
    volver('error', "Error inesperado: " . $e->getMessage(), $genero_vuelta);
}

volver('error', "Acción no válida.", $genero_vuelta);
