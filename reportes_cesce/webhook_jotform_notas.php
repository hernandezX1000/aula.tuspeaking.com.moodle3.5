<?php
/**
 * ============================================================
 * CODING - Webhook JotForm Calificaciones CESCE
 * ============================================================
 * Archivo: webhook_jotform_notas.php
 * Ruta: /home/aulatuspeaking/www/app/moodle/reportes_cesce/
 * URL: https://aula.tuspeaking.com/app/moodle/reportes_cesce/webhook_jotform_notas.php
 * Fecha: 2025-12-28
 * ============================================================
 */

$dbhost = 'localhost';
$dbname = 'aulatuspeaking35';
$dbuser = 'moodle35';
$dbpass = 'TuspeakingFix2025!';

$log_dir = __DIR__ . '/logs';
$log_file = $log_dir . '/jotform_' . date('Y-m-d') . '.log';

if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

function logear($msg, $datos = null) {
    global $log_file;
    $ts = date('Y-m-d H:i:s');
    $log = "[$ts] $msg";
    if ($datos !== null) $log .= "\n" . print_r($datos, true);
    $log .= "\n" . str_repeat("-", 60) . "\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

function extraer_email($txt) {
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $txt, $m)) return strtolower($m[0]);
    return null;
}

function extraer_nota($txt) {
    $txt = str_replace(',', '.', $txt);
    if (preg_match('/(\d+\.?\d*)/', $txt, $m)) {
        $n = floatval($m[1]);
        return ($n >= 0 && $n <= 10) ? $n : null;
    }
    return null;
}

function detectar_nivel($txt) {
    $txt = strtoupper($txt);
    foreach (['C1','B2','B1.2','B1','A2','A1'] as $n) if (strpos($txt, $n) !== false) return $n;
    return null;
}

logear("=== WEBHOOK RECIBIDO ===");
logear("IP: " . $_SERVER['REMOTE_ADDR']);

$raw = file_get_contents('php://input');
logear("RAW INPUT", $raw);

$datos = [];
if (isset($_POST['rawRequest'])) {
    $datos = json_decode($_POST['rawRequest'], true);
} elseif ($raw) {
    $datos = json_decode($raw, true);
}
if (empty($datos)) $datos = $_POST;

logear("DATOS PARSEADOS", $datos);

if (empty($datos)) {
    logear("ERROR: Sin datos");
    http_response_code(400);
    die(json_encode(['error' => 'No data']));
}

$reg = ['email'=>null,'grupo'=>'Sin asignar','nivel'=>'B1','profesor'=>null,
        'nota_oral'=>null,'nota_plataforma'=>null,'nota_clase'=>null,
        'nota_final'=>null,'pass_yn'=>null,'comentario'=>null,'edicion'=>date('y').'.'.(date('n')<=6?'1':'2')];

foreach ($datos as $k => $v) {
    $kl = strtolower($k);
    $vs = is_string($v) ? $v : json_encode($v);
    
    if (!$reg['email'] && (strpos($kl,'email')!==false || strpos($kl,'correo')!==false)) 
        $reg['email'] = extraer_email($vs);
    if (!$reg['nota_oral'] && (strpos($kl,'oral')!==false || strpos($kl,'speaking')!==false)) 
        $reg['nota_oral'] = extraer_nota($vs);
    if (!$reg['nota_plataforma'] && (strpos($kl,'plataforma')!==false || strpos($kl,'online')!==false)) 
        $reg['nota_plataforma'] = extraer_nota($vs);
    if (!$reg['nota_clase'] && (strpos($kl,'participacion')!==false || strpos($kl,'clase')!==false)) 
        $reg['nota_clase'] = extraer_nota($vs);
    if (!$reg['nivel']) $reg['nivel'] = detectar_nivel($vs) ?: 'B1';
    if (strpos($kl,'grupo')!==false || strpos($kl,'class')!==false) $reg['grupo'] = $vs;
    if (strpos($kl,'profesor')!==false || strpos($kl,'teacher')!==false) $reg['profesor'] = $vs;
    if (strpos($kl,'comentario')!==false || strpos($kl,'comment')!==false) $reg['comentario'] = $vs;
}

if (!$reg['email']) {
    foreach ($datos as $v) if (is_string($v) && ($e = extraer_email($v))) { $reg['email'] = $e; break; }
}

if ($reg['nota_oral']!==null || $reg['nota_plataforma']!==null || $reg['nota_clase']!==null) {
    $reg['nota_final'] = round((($reg['nota_oral']??0)*0.6) + (($reg['nota_plataforma']??0)*0.2) + (($reg['nota_clase']??0)*0.2), 2);
    $reg['pass_yn'] = $reg['nota_final'] >= 5 ? 'Y' : 'N';
}

logear("REGISTRO MAPEADO", $reg);

if (!$reg['email']) {
    logear("ERROR: Email no encontrado");
    http_response_code(400);
    die(json_encode(['error' => 'Email required']));
}

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $stmt = $pdo->prepare("SELECT id FROM mdl_cesce_calificaciones WHERE empleado_email=? AND edicion=?");
    $stmt->execute([$reg['email'], $reg['edicion']]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        $sql = "UPDATE mdl_cesce_calificaciones SET grupo=COALESCE(?,grupo), profesor=COALESCE(?,profesor), 
                nivel=COALESCE(?,nivel), nota_oral=COALESCE(?,nota_oral), nota_plataforma=COALESCE(?,nota_plataforma),
                nota_clase=COALESCE(?,nota_clase), nota_final=COALESCE(?,nota_final), pass_yn=COALESCE(?,pass_yn),
                comentario_profesor=COALESCE(?,comentario_profesor), fecha_evaluacion=CURDATE() WHERE id=?";
        $pdo->prepare($sql)->execute([$reg['grupo'],$reg['profesor'],$reg['nivel'],$reg['nota_oral'],
            $reg['nota_plataforma'],$reg['nota_clase'],$reg['nota_final'],$reg['pass_yn'],$reg['comentario'],$existe['id']]);
        logear("ACTUALIZADO ID: " . $existe['id']);
    } else {
        $sql = "INSERT INTO mdl_cesce_calificaciones (edicion,empleado_email,grupo,profesor,nivel,nota_oral,
                nota_plataforma,nota_clase,nota_final,pass_yn,comentario_profesor,fecha_evaluacion) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE())";
        $pdo->prepare($sql)->execute([$reg['edicion'],$reg['email'],$reg['grupo'],$reg['profesor'],$reg['nivel'],
            $reg['nota_oral'],$reg['nota_plataforma'],$reg['nota_clase'],$reg['nota_final'],$reg['pass_yn'],$reg['comentario']]);
        logear("INSERTADO ID: " . $pdo->lastInsertId());
    }
    
    echo json_encode(['success'=>true, 'email'=>$reg['email'], 'nota'=>$reg['nota_final']]);
} catch (PDOException $e) {
    logear("ERROR DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
?>
