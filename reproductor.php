<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/db.php';

// Géneros y cantidad de canciones por género (para los filtros)
$generos = $pdo->query("SELECT * FROM generos ORDER BY id_genero ASC")->fetchAll();

$conteos = [];
foreach ($pdo->query("SELECT id_genero, COUNT(*) AS total FROM canciones GROUP BY id_genero")->fetchAll() as $fila) {
    $conteos[(int)$fila['id_genero']] = (int)$fila['total'];
}
$total_general = array_sum($conteos);

// Género seleccionado por GET (0 = todos)
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
    $canciones = $pdo->query("
        SELECT c.*, g.nombre AS genero_nombre
        FROM canciones c
        INNER JOIN generos g ON c.id_genero = g.id_genero
        ORDER BY c.id_cancion ASC
    ")->fetchAll();
}

// URL base del sitio (sirve en localhost y en InfinityFree) para el enlace/QR del audio
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$ruta_base = $protocolo . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';

$inicial = mb_strtoupper(mb_substr($_SESSION['nombre_usuario'], 0, 1));
$es_admin = ($_SESSION['rol'] === 'admin');
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca | Plataforma Musical</title>
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
                <span class="role-tag"><?= htmlspecialchars($_SESSION['rol']) ?></span>
            </div>
            <?php if ($es_admin): ?>
                <a href="admin.php" class="btn btn-sm btn-quiet">Panel de administración</a>
            <?php endif; ?>
            <a href="auth/logout.php" class="btn btn-sm btn-quiet">Cerrar sesión</a>
        </div>
    </div>
</header>

<main class="page">
    <div class="page-head">
        <div>
            <h1>Biblioteca</h1>
            <p><?= count($canciones) ?> <?= count($canciones) === 1 ? 'canción' : 'canciones' ?><?= $genero_actual > 0 ? ' en este género' : '' ?></p>
        </div>
    </div>

    <nav class="pills" aria-label="Filtrar por género">
        <a href="?genero=0" class="pill pill-all <?= $genero_actual === 0 ? 'active' : '' ?>">
            Todos <span class="n"><?= $total_general ?></span>
        </a>
        <?php foreach ($generos as $g): ?>
            <?php $gid = (int)$g['id_genero']; ?>
            <a href="?genero=<?= $gid ?>" class="pill g-<?= $gid ?> <?= $gid === $genero_actual ? 'active' : '' ?>">
                <span class="dot"></span>
                <?= htmlspecialchars($g['nombre']) ?>
                <span class="n"><?= $conteos[$gid] ?? 0 ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tracks">
        <?php if (count($canciones) > 0): ?>
            <div class="track track-head" aria-hidden="true">
                <span></span>
                <span>Título</span>
                <span class="col-genre">Género</span>
                <span class="col-dur">Duración</span>
                <span class="col-rate">Calificación</span>
                <span></span>
            </div>

            <?php foreach ($canciones as $c): ?>
                <?php
                    $gid        = (int)$c['id_genero'];
                    $url_audio  = $ruta_base . ltrim($c['ruta_archivo'], '/');
                    $estrellas  = max(1, min(5, (int)$c['calificacion']));
                    $fecha      = $c['fecha_subida'] ? date('d/m/Y', strtotime($c['fecha_subida'])) : '';
                ?>
                <div class="track g-<?= $gid ?>"
                     data-src="<?= htmlspecialchars($c['ruta_archivo']) ?>"
                     data-title="<?= htmlspecialchars($c['nombre']) ?>"
                     data-genre="<?= htmlspecialchars($c['genero_nombre']) ?>"
                     data-gid="<?= $gid ?>">
                    <button type="button" class="play-btn" aria-label="Reproducir <?= htmlspecialchars($c['nombre']) ?>">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                    </button>

                    <div class="min-w-0">
                        <div class="t-name"><?= htmlspecialchars($c['nombre']) ?></div>
                        <div class="t-meta">
                            <span><?= htmlspecialchars($c['tipo']) ?></span>
                            <?php if ($fecha): ?><span>Subida el <?= $fecha ?></span><?php endif; ?>
                            <span class="only-mobile chip"><?= htmlspecialchars($c['genero_nombre']) ?></span>
                            <span class="only-mobile"><?= htmlspecialchars($c['duracion']) ?></span>
                        </div>
                    </div>

                    <div class="col-genre"><span class="chip"><?= htmlspecialchars($c['genero_nombre']) ?></span></div>
                    <div class="col-dur mono"><?= htmlspecialchars($c['duracion']) ?></div>
                    <div class="col-rate stars" role="img" aria-label="Calificación <?= $estrellas ?> de 5">
                        <?= str_repeat('★', $estrellas) ?><span class="off"><?= str_repeat('★', 5 - $estrellas) ?></span>
                    </div>

                    <div>
                        <button type="button" class="icon-btn qr-btn"
                                data-url="<?= htmlspecialchars($url_audio) ?>"
                                data-title="<?= htmlspecialchars($c['nombre']) ?>"
                                data-bs-toggle="modal" data-bs-target="#qrModal"
                                aria-label="Ver código QR de <?= htmlspecialchars($c['nombre']) ?>" title="Código QR">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h3M20 20h1"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">
                <strong>No hay canciones en esta selección</strong>
                <?php if ($es_admin): ?>
                    Sube la primera desde el <a href="admin.php">panel de administración</a>.
                <?php else: ?>
                    Prueba con otro género o vuelve más tarde.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Reproductor fijo -->
<div class="player" id="player" aria-label="Reproductor">
    <div class="player-inner">
        <div class="now">
            <div class="disc" aria-hidden="true"></div>
            <div style="min-width:0">
                <div class="now-title" id="nowTitle">Elige una canción</div>
                <div class="now-sub" id="nowSub">Pulsa el botón de reproducir en cualquier fila</div>
            </div>
        </div>

        <div class="transport">
            <div class="controls">
                <button type="button" class="ctl" id="btnPrev" aria-label="Canción anterior">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M6 6h2v12H6zM9.5 12 18 18V6z"/></svg>
                </button>
                <button type="button" class="ctl main" id="btnPlay" aria-label="Reproducir">
                    <svg id="icoPlay" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </button>
                <button type="button" class="ctl" id="btnNext" aria-label="Siguiente canción">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M16 6h2v12h-2zM6 18l8.5-6L6 6z"/></svg>
                </button>
            </div>
            <div class="seekrow">
                <span id="tCur">0:00</span>
                <input type="range" class="seek" id="seek" min="0" max="1000" value="0" aria-label="Posición de reproducción">
                <span id="tDur">0:00</span>
            </div>
        </div>

        <div class="vol">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M3 10v4h4l5 4V6L7 10H3zm13.5 2a4.5 4.5 0 0 0-2.5-4v8a4.5 4.5 0 0 0 2.5-4z"/></svg>
            <input type="range" class="seek" id="vol" min="0" max="100" value="80" aria-label="Volumen">
        </div>
    </div>
    <audio id="audio" preload="none"></audio>
</div>

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
(function () {
    const PLAY  = '<path d="M8 5v14l11-7z"/>';
    const PAUSE = '<path d="M6 5h4v14H6zM14 5h4v14h-4z"/>';

    const audio  = document.getElementById('audio');
    const player = document.getElementById('player');
    const rows   = Array.from(document.querySelectorAll('.track[data-src]'));
    const seek   = document.getElementById('seek');
    const vol    = document.getElementById('vol');
    const title  = document.getElementById('nowTitle');
    const sub    = document.getElementById('nowSub');
    const tCur   = document.getElementById('tCur');
    const tDur   = document.getElementById('tDur');
    const icoMain = document.getElementById('icoPlay');
    const btnPlay = document.getElementById('btnPlay');
    let idx = -1;

    function fmt(s) {
        if (!isFinite(s)) return '0:00';
        const m = Math.floor(s / 60), r = Math.floor(s % 60);
        return m + ':' + String(r).padStart(2, '0');
    }
    function setFill(input, pct) { input.style.setProperty('--p', pct + '%'); }

    function refreshIcons() {
        const playing = !audio.paused && !audio.ended;
        player.classList.toggle('playing', playing);
        icoMain.innerHTML = playing ? PAUSE : PLAY;
        btnPlay.setAttribute('aria-label', playing ? 'Pausar' : 'Reproducir');
        rows.forEach(function (r, i) {
            const svg = r.querySelector('.play-btn svg');
            svg.innerHTML = (i === idx && playing) ? PAUSE : PLAY;
        });
    }

    function load(i, autoplay) {
        if (i < 0 || i >= rows.length) return;
        idx = i;
        const r = rows[i];
        audio.src = r.dataset.src;
        title.textContent = r.dataset.title;
        sub.textContent = r.dataset.genre;
        player.classList.remove('g-1', 'g-2', 'g-3', 'g-4');
        player.classList.add('g-' + r.dataset.gid);
        rows.forEach(function (x) { x.classList.toggle('is-playing', x === r); });
        seek.value = 0; setFill(seek, 0); tCur.textContent = '0:00'; tDur.textContent = '0:00';
        if (autoplay) {
            audio.play().catch(function () { sub.textContent = 'No se pudo reproducir este archivo'; });
        }
        refreshIcons();
    }

    function toggle() {
        if (idx === -1) { load(0, true); return; }
        if (audio.paused) { audio.play().catch(function () {}); } else { audio.pause(); }
    }

    rows.forEach(function (r, i) {
        r.querySelector('.play-btn').addEventListener('click', function () {
            if (i === idx) { toggle(); } else { load(i, true); }
        });
    });

    btnPlay.addEventListener('click', toggle);
    document.getElementById('btnNext').addEventListener('click', function () { load(idx === -1 ? 0 : (idx + 1) % rows.length, true); });
    document.getElementById('btnPrev').addEventListener('click', function () {
        if (audio.currentTime > 3) { audio.currentTime = 0; return; }
        load(idx <= 0 ? rows.length - 1 : idx - 1, true);
    });

    audio.addEventListener('play', refreshIcons);
    audio.addEventListener('pause', refreshIcons);
    audio.addEventListener('ended', function () {
        if (idx < rows.length - 1) { load(idx + 1, true); } else { refreshIcons(); }
    });
    audio.addEventListener('loadedmetadata', function () { tDur.textContent = fmt(audio.duration); });
    audio.addEventListener('error', function () { if (idx !== -1) sub.textContent = 'No se pudo cargar el archivo de audio'; });
    audio.addEventListener('timeupdate', function () {
        if (!audio.duration) return;
        const p = (audio.currentTime / audio.duration) * 1000;
        seek.value = p; setFill(seek, p / 10);
        tCur.textContent = fmt(audio.currentTime);
    });

    seek.addEventListener('input', function () {
        setFill(seek, seek.value / 10);
        if (audio.duration) audio.currentTime = (seek.value / 1000) * audio.duration;
    });
    vol.addEventListener('input', function () { audio.volume = vol.value / 100; setFill(vol, vol.value); });
    audio.volume = 0.8; setFill(vol, 80); setFill(seek, 0);

    // Modal de QR
    document.querySelectorAll('.qr-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('qrTitle').textContent = b.dataset.title;
            document.getElementById('qrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=' + encodeURIComponent(b.dataset.url);
            document.getElementById('qrLink').href = b.dataset.url;
        });
    });
})();
</script>

</body>
</html>