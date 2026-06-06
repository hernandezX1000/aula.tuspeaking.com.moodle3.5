<?php
/**
 * Cron de Envío de Feedback - TuSpeaking
 * CORREGIDO: Usa own_acuity + mdl_event en lugar de mdl_i3code_acuityZoom
 */
echo date('Y-m-d H:i:s') . " - Inicio cron feedback\n";

$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) { die("Error BD: " . $conn->connect_error); }

// Leer configuración
$config = [];
$res = $conn->query("SELECT clave, valor FROM own_feedback_config");
while ($r = $res->fetch_assoc()) { $config[$r['clave']] = $r['valor']; }

$MINUTOS_ESPERA = intval($config['minutos_espera_envio'] ?? 30);
$MINUTOS_LIMITE = intval($config['minutos_limite_envio'] ?? 90);
$MAX_SEMANA = intval($config['max_feedbacks_semana'] ?? 2);
$BASE_URL = 'https://aula.tuspeaking.com/app/moodle/feedback/quick.php';

echo "Config: espera=$MINUTOS_ESPERA, limite=$MINUTOS_LIMITE, max_semana=$MAX_SEMANA\n";

// Buscar clases candidatas usando own_acuity + mdl_event
$sql = "SELECT 
    oa.acuityid, 
    oa.studentid, 
    oa.teacherid,
    us.email as student_email,
    us.firstname as student_firstname,
    ut.firstname as teacher_firstname,
    FROM_UNIXTIME(e.timestart) as clase_fecha,
    e.timestart as clase_timestamp
FROM own_acuity oa
INNER JOIN mdl_user us ON oa.studentid = us.id
INNER JOIN mdl_user ut ON oa.teacherid = ut.id
INNER JOIN mdl_event e ON oa.studenteventid = e.id
WHERE oa.iscancelled = 'f'
AND us.email IS NOT NULL 
AND us.email != '' 
AND us.email LIKE '%@%'
AND us.email NOT IN (SELECT email FROM own_feedback_optout)
AND NOT EXISTS (SELECT 1 FROM own_feedback_envios fe WHERE fe.acuityid = oa.acuityid)
AND FROM_UNIXTIME(e.timestart + 1800) BETWEEN DATE_SUB(NOW(), INTERVAL $MINUTOS_LIMITE MINUTE) AND DATE_SUB(NOW(), INTERVAL $MINUTOS_ESPERA MINUTE)
ORDER BY e.timestart DESC";

$result = $conn->query($sql);
if (!$result) {
    echo "Error SQL: " . $conn->error . "\n";
    exit;
}
echo "Clases candidatas: " . $result->num_rows . "\n";

$enviados = 0;
$omitidos = 0;

while ($c = $result->fetch_assoc()) {
    // Verificar límite semanal por alumno
    $email_esc = $conn->real_escape_string($c['student_email']);
    $check = $conn->query("SELECT COUNT(*) as cnt FROM own_feedback_envios 
                           WHERE student_email = '$email_esc' 
                           AND enviado_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $enviados_semana = $check->fetch_assoc()['cnt'];
    
    if ($enviados_semana >= $MAX_SEMANA) {
        echo "SKIP: " . $c['student_email'] . " (ya tiene $enviados_semana esta semana)\n";
        $omitidos++;
        continue;
    }
    
    $token = substr(hash('sha256', $c['acuityid'] . '-' . $c['studentid'] . '-tS2026!'), 0, 32);
    $link = $BASE_URL . "?a=" . $c['acuityid'] . "&t=" . $token;
    
    $stmt = $conn->prepare("INSERT INTO own_feedback_envios (acuityid, studentid, teacherid, student_email, teacher_name, clase_fecha, token, enviado_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiissss", $c['acuityid'], $c['studentid'], $c['teacherid'], $c['student_email'], $c['teacher_firstname'], $c['clase_fecha'], $token);
    if (!$stmt->execute()) { 
        echo "Error insert: " . $stmt->error . "\n"; 
        continue; 
    }
    
    $subject = "=?UTF-8?B?" . base64_encode("¿Qué tal tu clase con " . $c['teacher_firstname'] . "? 📚") . "?=";
    $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;margin:0;padding:0;background:#f5f5f5;">
    <div style="max-width:500px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#008ba3,#00bcd4);padding:30px;text-align:center;border-radius:16px 16px 0 0;">
    <h1 style="color:white;margin:0;">tuSpeaking</h1></div>
    <div style="background:white;padding:30px;border-radius:0 0 16px 16px;">
    <p>Hola <strong>' . htmlspecialchars($c['student_firstname']) . '</strong>,</p>
    <p style="color:#666;">¿Cómo ha ido tu clase con <strong>' . htmlspecialchars($c['teacher_firstname']) . '</strong>?</p>
    <p style="color:#888;font-size:13px;">Fecha: ' . date('d/m/Y H:i', strtotime($c['clase_fecha'])) . '</p>
    <div style="text-align:center;margin:30px 0;">
    <a href="' . $link . '" style="display:inline-block;background:#008ba3;color:white;padding:16px 40px;text-decoration:none;border-radius:8px;font-weight:600;">Valorar clase (30 seg)</a>
    </div>
    <p style="color:#999;font-size:12px;text-align:center;">Tu opinión nos ayuda a mejorar</p>
    </div></div></body></html>';
    
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: tuSpeaking <noreply@tuspeaking.com>";
    
    if (@mail($c['student_email'], $subject, $body, $headers)) {
        echo "OK: " . $c['student_email'] . " - " . $c['teacher_firstname'] . " - " . $c['clase_fecha'] . "\n";
        $enviados++;
    } else {
        echo "FAIL: " . $c['student_email'] . "\n";
    }
    usleep(100000);
}

echo "Enviados: $enviados, Omitidos por limite: $omitidos\n";
$conn->close();
?>
