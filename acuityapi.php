<?php
/**
 * acuityapi.php — llamadas a la API de Acuity desde el aula.
 *
 * SEC-12 (25-oct-2026, T-279). Antes este fichero estaba fuera de git con la clave escrita y respondía a cualquiera:
 * un POST url=… sin sesión usaba la cuenta de Acuity del aula (leer todas las citas con nombre, correo y teléfono,
 * crear o cancelar citas). Ahora:
 *   · usuario y clave salen de config.php ($CFG->ts_acuity_userid, $CFG->ts_acuity_key), no del código;
 *   · por POST directo (lo que usan acuityrecursive.js y own_PrintZoom.js) hace falta sesión y solo se permite lo que
 *     usan esas páginas: disponibilidad, crear la cita del propio alumno en un curso en el que está matriculado y leer
 *     una cita (sin correo ni teléfono). Los administradores del sitio, todo;
 *   · incluido desde PHP (cancelbymail.php, acuityrecursive.php) solo define callAPI() y putAPI(), como antes.
 */
if (!defined('MOODLE_INTERNAL')) {
    require_once(__DIR__ . '/config.php');
}
global $CFG;
$userID = isset($CFG->ts_acuity_userid) ? $CFG->ts_acuity_userid : '';
$apiKey = isset($CFG->ts_acuity_key) ? $CFG->ts_acuity_key : '';
$baseUrl = "https://acuityscheduling.com/api/v1/";

function callAPI($url, $data){
    global $userID, $apiKey, $baseUrl;
    $url = $baseUrl . $url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$userID:$apiKey");
    if ($data != ""){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $result = curl_exec($ch);
    curl_close($ch);
    $jsonresult = json_decode($result, true);
    return $jsonresult;
}

function putAPI($url, $data){
    global $userID, $apiKey, $baseUrl;
    $url = $baseUrl . $url;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERPWD, "$userID:$apiKey");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $result = curl_exec($curl);
    curl_close($curl);
    $jsonResult = json_decode($result, true);
    return $jsonResult;
}

/**
 * SEC-12: ¿puede el usuario de la sesión hacer esta llamada por POST directo?
 * $filtrar = true → de la respuesta solo se devuelven los campos que pinta own_PrintZoom.js.
 */
function acuityapi_permitida($url, $data, &$filtrar) {
    global $USER;
    $filtrar = false;
    if (is_siteadmin()) {
        return true;
    }
    // Disponibilidad (acuityrecursive.js): lectura, la misma que enseña el formulario público de Acuity.
    if ($data === '' && preg_match('#^availability/(dates|times)\?[A-Za-z0-9_=&%:.+/-]*$#', $url)) {
        return true;
    }
    if ($url === 'availability/check-times') {
        return true;
    }
    // Una cita por su número (own_PrintZoom.js): sin correo ni teléfono.
    if ($data === '' && preg_match('#^appointments/[0-9]+$#', $url)) {
        $filtrar = true;
        return true;
    }
    // Reservas recurrentes (acuityrecursive.js): solo para uno mismo y en un curso en el que esté matriculado.
    if ($url === 'appointments?noEmail=true' && $data !== '') {
        $d = json_decode($data, true);
        if (!is_array($d) || strcasecmp((string)($d['email'] ?? ''), (string)$USER->email) !== 0) {
            return false;
        }
        $alumno = null;
        $curso = null;
        foreach ((array)($d['fields'] ?? array()) as $f) {
            if ((string)($f['id'] ?? '') === '5837866') { $alumno = (string)($f['value'] ?? ''); }
            if ((string)($f['id'] ?? '') === '5915926') { $curso = (string)($f['value'] ?? ''); }
        }
        if ($alumno !== (string)$USER->id || !ctype_digit((string)$curso)) {
            return false;
        }
        $ctx = context_course::instance((int)$curso, IGNORE_MISSING);
        return $ctx && is_enrolled($ctx, $USER);
    }
    return false;
}

// Llamada directa por POST (no cuando otro PHP incluye este fichero).
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ && isset($_POST['url'])) {
    require_login();
    $url = (string)$_POST['url'];
    $data = (string)($_POST['data'] ?? '');
    $filtrar = false;
    if (!acuityapi_permitida($url, $data, $filtrar)) {
        http_response_code(403);
        error_log('acuityapi SEC-12: rechazada para el usuario ' . $USER->id . ': ' . substr($url, 0, 80));
        echo json_encode(array('error' => 'no permitido'));
        exit;
    }
    $r = callAPI($url, $data);
    if ($filtrar && is_array($r)) {
        $r = array_intersect_key($r, array_flip(array('id', 'datetime', 'type', 'duration', 'calendar', 'calendarID',
            'appointmentTypeID', 'firstName', 'lastName', 'canceled', 'location')));
    }
    echo json_encode($r);
    exit;
}
