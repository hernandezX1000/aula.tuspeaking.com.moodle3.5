<?php
// _tszoom/canlaunch.php — Devuelve JSON {ok:true/false, reason, host_email}
require_once(__DIR__ . '/../config.php'); // <- OJO: un nivel arriba
require_login();

// Cargar creds
$envfile = (getenv('HOME') ?: '/home/aulatuspeaking') . '/.zoom_env.php';
@include $envfile;

// Params
$mid     = optional_param('mid', 0, PARAM_INT);
$eventid = optional_param('eventid', 0, PARAM_INT);
$urlraw  = optional_param('url', '', PARAM_RAW_TRIMMED);

$event = null;

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
if (!$mid) {
    header('Content-Type: application/json'); echo json_encode(['ok'=>false,'reason'=>'missing_mid']); exit;
}

$context = context_system::instance();
if (!empty($event) && !empty($event->courseid)) {
    $context = context_course::instance($event->courseid);
}

// ---- Zoom helpers ----
function ts_zoom_token(): string {
    $acc = getenv('ZOOM_ACCOUNT_ID'); $id = getenv('ZOOM_CLIENT_ID'); $sec = getenv('ZOOM_CLIENT_SECRET');
    if (!$acc || !$id || !$sec) throw new moodle_exception('zoom_env_missing');
    $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($acc);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>'',
        CURLOPT_HTTPHEADER=>[
            'Authorization: Basic ' . base64_encode("$id:$sec"),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT=>15,
    ]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200) throw new moodle_exception('token_http_'.$code);
    $data=json_decode($r,true);
    return $data['access_token'] ?? '';
}
function ts_zoom_meeting(string $tok,int $mid):?array{
    $ch=curl_init('https://api.zoom.us/v2/meetings/'.urlencode((string)$mid));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok], CURLOPT_TIMEOUT=>15]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code===404) return null; if($code!==200) throw new moodle_exception('meeting_http_'.$code);
    return json_decode($r,true);
}

// ---- Respuesta ----
header('Content-Type: application/json; charset=utf-8');
try {
    $tok = ts_zoom_token();
    $det = ts_zoom_meeting($tok, $mid);
    if (!$det) { echo json_encode(['ok'=>false,'reason'=>'meeting_404']); exit; }

    $host  = strtolower($det['host_email'] ?? '');
    $me    = strtolower((string)$USER->email);
    $is_teacher = has_capability('moodle/course:update', $context, $USER);
    $is_host    = $host && ($me === $host);

    echo json_encode(['ok'=> (bool)($is_teacher || $is_host), 'host_email'=>$host]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'reason'=>'exception','msg'=>$e->getMessage()]);
}
