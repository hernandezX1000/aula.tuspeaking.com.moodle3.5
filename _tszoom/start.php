<?php
// _tszoom/start.php — Lanza Zoom como HOST (profes) con redirect 302, sin guardar tokens
require_once(__DIR__ . '/../config.php'); // <- OJO: un nivel arriba
require_login();

// ===== CREDENCIALES =====
$envfile = (getenv('HOME') ?: '/home/aulatuspeaking') . '/.zoom_env.php';
@include $envfile;

// ===== PARÁMETROS =====
$mid     = optional_param('mid', 0, PARAM_INT);
$eventid = optional_param('eventid', 0, PARAM_INT);
$urlraw  = optional_param('url', '', PARAM_RAW_TRIMMED);
$debug   = optional_param('debug', 0, PARAM_INT);
$allowme = optional_param('allowme', 0, PARAM_INT);

$event = null;

// 1) Sacar meeting_id desde URL si llega
if (!$mid && $urlraw && preg_match('~zoom\.us/(?:j|s)/(\d+)~', $urlraw, $m)) {
    $mid = (int)$m[1];
}
// 2) Sacar meeting_id desde eventid si llega
if (!$mid && $eventid) {
    $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
    $desc  = (string)($event->description ?? '');
    if (preg_match('~zoom\.us/(?:j|s)/(\d+)~', $desc, $m)) {
        $mid = (int)$m[1];
    }
}
// 3) Validación
if (!$mid) { print_error('Missing meeting id'); }

// ===== CONTEXTO =====
$context = context_system::instance();
if (!empty($event) && !empty($event->courseid)) {
    $context = context_course::instance($event->courseid);
}

// ===== ZOOM =====
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

// ===== NO CACHE =====
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $tok = zoom_token();
    $det = zoom_meeting_detail($tok, $mid);
    if (!$det) {
        if (!empty($event) && preg_match('~https://[^\s<"]+zoom[^\s<"]+~', (string)$event->description, $mm)) {
            redirect($mm[0], 'Meeting not found (404). Using participant link.', 0, 'notifywarning');
        }
        print_error('Meeting not found (404)');
    }

    $start = $det['start_url'] ?? null;
    $join  = $det['join_url']  ?? null;
    $host  = strtolower($det['host_email'] ?? '');
    $me    = strtolower((string)$USER->email);
    $using = $start ? 'START' : ($join ? 'JOIN' : 'NONE');

    // Permisos: profesor o host. Override de prueba (solo debug) para meetings de prueba
    $is_teacher = has_capability('moodle/course:update', $context, $USER);
    $is_host    = $host && ($me === $host);
    $override_whitelist = [88913881099, 88368328616];
    $can_override = $debug && $allowme && in_array($mid, $override_whitelist, true);

    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "debug=1\n";
        echo "mid={$mid}\n";
        echo "user_email={$me}\n";
        echo "host_email={$host}\n";
        echo "using={$using}\n";
        echo "have_start=" . ($start ? 'yes' : 'no') . "\n";
        echo "have_join="  . ($join  ? 'yes' : 'no') . "\n";
        echo "is_teacher=" . ($is_teacher ? 'yes' : 'no') . "\n";
        echo "is_host="    . ($is_host ? 'yes' : 'no') . "\n";
        echo "can_override=" . ($can_override ? 'yes' : 'no') . "\n";
        exit;
    }

    if (!$is_teacher && !$is_host && !$can_override) {
        if ($join) {
            redirect($join, 'Only participants can join. Using participant link.', 0, 'notifywarning');
        }
        if (!empty($event) && preg_match('~https://[^\s<"]+zoom[^\s<"]+~', (string)$event->description, $mm)) {
            redirect($mm[0], 'Only participants can join. Using participant link.', 0, 'notifywarning');
        }
        print_error('Only teachers can launch the host link');
    }

    if ($start) {
        redirect($start);
    } elseif ($join) {
        redirect($join, 'Using participant link (no start_url).', 0, 'notifywarning');
    } else {
        print_error('No suitable Zoom URL');
    }

} catch (Throwable $e) {
    if (!empty($event) && preg_match('~https://[^\s<"]+zoom[^\s<"]+~', (string)$event->description, $mm)) {
        redirect($mm[0], 'Zoom error; using participant link.', 0, 'notifywarning');
    }
    print_error('Zoom error: ' . $e->getMessage());
}
