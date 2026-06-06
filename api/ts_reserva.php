<?php
// ts_reserva.php — Redirector de reservas por curso (Acuity)
// Colocar en: /app/moodle/api/ts_reserva.php
// Detecta el courseid desde ?id=#### o desde el Referer y redirige a la URL mapeada.
// Fallback: agenda general si el curso no está en el mapa.

header('Referrer-Policy: no-referrer-when-downgrade');

/** Devuelve el courseid si está en ?id= o en el Referer (course/view.php?id=####), o null si no hay */
function ts_get_courseid(): ?int {
    // 1) ?id=#### en la URL
    if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
        return (int) $_GET['id'];
    }
    // 2) Intentar desde el Referer (cuando vienes de course/view.php?id=####)
    if (!empty($_SERVER['HTTP_REFERER'])) {
        if (preg_match('/[?&]id=(\d+)/', $_SERVER['HTTP_REFERER'], $m)) {
            return (int) $m[1];
        }
    }
    return null;
}

// ---------- Cargar mapa externo (courseid => URL de Acuity) ----------
$mapFile = __DIR__ . '/ts_reserva_map.php';
$map = file_exists($mapFile) ? (require $mapFile) : [];

// URL por defecto si el courseid no está mapeado
$defaultUrl = 'https://tuspeaking.as.me/schedule.php';

// --------------------------------------------------------------------
$courseId = ts_get_courseid();
$target   = $defaultUrl;

// Si hay courseId y está mapeado, usar su URL
if (!empty($courseId) && isset($map[$courseId])) {
    $target = $map[$courseId];
}

// Añade etiqueta de course para intake/analytics en Acuity (opcional, no rompe si ya existe)
$hasQuery = (parse_url($target, PHP_URL_QUERY) !== null);
$sep = $hasQuery ? '&' : '?';
if (strpos($target, 'course=') === false) {
    $target .= $sep . 'course=' . urlencode($courseId ?: 'unknown');
}

// Redirigir
header('Location: ' . $target, true, 302);
exit;
