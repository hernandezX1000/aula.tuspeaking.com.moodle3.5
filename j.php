<?php
// _tszoom/j.php — Redirige a la join_url (enlace de alumno) usando el meeting_id
require_once(__DIR__ . '/../config.php');
require_login();

// Parámetros
$mid     = optional_param('mid', 0, PARAM_INT);
$eventid = optional_param('eventid', 0, PARAM_INT);
$urlraw  = optional_param('url', '', PARAM_RAW_TRIMMED);
$debug   = optional_param('debug', 0, PARAM_INT);

$event = null;

// Intentar obtener mid desde url/evento si no viene explícito
if (!$mid && $urlraw && preg_match('~zoom\.us/(?:j|s)/(\d+)~', $urlraw, $m)) {
    $mid = (int)$m[1];
}
if (!$mid && $eventid) {
    $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
    $desc  = (string)($event->description ?? '');
    if (preg_match('~zoom\.us/(?:j|s)/(\d+)~', $desc, $m)) {
        $mid = (int)$m[1];
    }
}
if (!$mid) { print_error('Missing meeting id'); }

// No cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Helpers Zoom (mismo patrón que start.php)
function zoom_token(): string {
    $acc = getenv('ZOOM_ACCOUNT_ID');
    $id  = getenv('ZOOM_CLIENT_ID');
    $sec = getenv('ZOOM_CLIENT_SECRET');
    if (!$acc || !$id || !$sec) throw new moodle_exception('Zoom ENV missing (check ~/.zoom_env.php)');
    $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($acc);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode("$id:$sec"),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200) throw new moodle_exception('Token HTTP ' . $code . ' | ' . substr((string)$r, 0, 200));
    $data = json_decode($r, true);
    return $data['access_token'] ?? '';
}
function zoom_meeting_detail(string $tok, int $mid): ?array {
    $ch = curl_init('https://api.zoom.us/v2/meetings/' . urlencode((string)$mid));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tok],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 404) return null;
    if ($code !== 200)  throw new moodle_exception('Get meeting HTTP ' . $code);
    return json_decode($r, true);
}

try {
    $tok = zoom_token();
    $det = zoom_meeting_detail($tok, $mid);

    // Si Zoom no encuentra la reunión, intenta usar una URL que venga en la descripción o en ?url=
    if (!$det) {
        if ($urlraw && preg_match('~https://[^\s<"]*zoom[^\s<"]*~', $urlraw, $mm)) {
            $join = $mm[0];
            if ($debug) { header('Content-Type:text/plain; charset=utf-8'); echo "mid=$mid\njoin_url=$join\n"; exit; }
            redirect($join);
        }
        if (!empty($event) && preg_match('~https://[^\s<"]*zoom[^\s<"]*~', (string)$event->description, $mm)) {
            redirect($mm[0]);
        }
        print_error('Meeting not found (404)');
    }

    $join = $det['join_url'] ?? null;
    if (!$join) { print_error('No join_url'); }

    if ($debug) {
        header('Content-Type:text/plain; charset=utf-8');
        echo "mid=$mid\njoin_url=$join\n";
        echo "short={$CFG->wwwroot}/_tszoom/j.php?mid={$mid}\n";
        exit;
    }

    redirect($join);

} catch (Throwable $e) {
    // Último recurso: si hay una url zoom en la descripción, úsala
    if (!empty($event) && preg_match('~https://[^\s<"]*zoom[^\s<"]*~', (string)$event->description, $mm)) {
        redirect($mm[0], 'Zoom error; using participant link.', 0, 'notifywarning');
    }
    print_error('Zoom error: ' . $e->getMessage());
}

