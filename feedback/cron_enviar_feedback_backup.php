<?php
/**
 * Cron de Envío de Feedback - TuSpeaking
 * Lee configuración de own_feedback_config
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

// Buscar clases candidatas
$sql = "SELECT a.acuityid, a.studentid, a.teacherid, a.acuity_firstname, a.acuity_email, a.acuity_datetime, a.acuity_endtime,
        u.firstname as teacher_firstname
        FROM mdl_i3code_acuityZoom a
        INNER JOIN mdl_user u ON a.teacherid = u.id
        WHERE a.acuity_datetime >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND a.acuity_email NOT IN (SELECT email FROM own_feedback_optout)
        AND a.acuity_email IS NOT NULL AND a.acuity_email != '' AND a.acuity_email LIKE '%@%'
        AND NOT EXISTS (SELECT 1 FROM own_acuity oa WHERE oa.acuityid = a.acuityid AND oa.iscancelled = 't')
        AND NOT EXISTS (SELECT 1 FROM own_feedback_envios e WHERE e.acuityid = a.acuityid)
        AND STR_TO_DATE(CONCAT(LEFT(a.acuity_datetime,10), ' ', a.acuity_endtime, ':00'), '%Y-%m-%d %H:%i:%s') 
            BETWEEN DATE_SUB(NOW(), INTERVAL $MINUTOS_LIMITE MINUTE) AND DATE_SUB(NOW(), INTERVAL $MINUTOS_ESPERA MINUTE)";

$result = $conn->query($sql);
echo "Clases candidatas: " . $result->num_rows . "\n";

$enviados = 0;
$omitidos = 0;

while ($c = $result->fetch_assoc()) {
    // Verificar límite semanal por alumno
    $check = $conn->query("SELECT COUNT(*) as cnt FROM own_feedback_envios 
                           WHERE student_email = '".$conn->real_escape_string($c['acuity_email'])."' 
                           AND enviado_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $enviados_semana = $check->fetch_assoc()['cnt'];
    
    if ($enviados_semana >= $MAX_SEMANA) {
        echo "SKIP: " . $c['acuity_email'] . " (ya tiene $enviados_semana esta semana)\n";
        $omitidos++;
        continue;
    }
    
    $token = substr(hash('sha256', $c['acuityid'] . '-' . $c['studentid'] . '-tS2026!'), 0, 32);
    $link = $BASE_URL . "?a=" . $c['acuityid'] . "&t=" . $token;
    $fecha = date('Y-m-d H:i:s', strtotime($c['acuity_datetime']));
    
    $stmt = $conn->prepare("INSERT INTO own_feedback_envios (acuityid, studentid, teacherid, student_email, teacher_name, clase_fecha, token, enviado_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiissss", $c['acuityid'], $c['studentid'], $c['teacherid'], $c['acuity_email'], $c['teacher_firstname'], $fecha, $token);
    if (!$stmt->execute()) { echo "Error insert: " . $stmt->error . "\n"; continue; }
    
    $subject = "=?UTF-8?B?" . base64_encode("¿Qué tal tu clase con " . $c['teacher_firstname'] . "? 📚") . "?=";
    $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;margin:0;padding:0;background:#f5f5f5;">
    <div style="max-width:500px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#008ba3,#00bcd4);padding:30px;text-align:center;border-radius:16px 16px 0 0;">
    <h1 style="color:white;margin:0;">tuSpeaking</h1></div>
    <div style="background:white;padding:30px;border-radius:0 0 16px 16px;">
    <p>Hola <strong>' . htmlspecialchars($c['acuity_firstname']) . '</strong>,</p>
    <p style="color:#666;">Como ha ido tu clase con <strong>' . htmlspecialchars($c['teacher_firstname']) . '</strong>?</p>
    <p style="color:#888;font-size:13px;">Fecha: ' . date('d/m/Y H:i', strtotime($c['acuity_datetime'])) . '</p>
    <div style="text-align:center;margin:30px 0;">
    <a href="' . $link . '" style="display:inline-block;background:#008ba3;color:white;padding:16px 40px;text-decoration:none;border-radius:8px;font-weight:600;">Valorar clase (30 seg)</a>
    </div>
    <p style="color:#999;font-size:12px;text-align:center;">Tu opinion nos ayuda a mejorar</p>
    </div></div></body></html>';
    
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: tuSpeaking <noreply@tuspeaking.com>";
    
    if (@mail($c['acuity_email'], $subject, $body, $headers)) {
        echo "OK: " . $c['acuity_email'] . "\n";
        $enviados++;
    } else {
        echo "FAIL: " . $c['acuity_email'] . "\n";
    }
    usleep(100000);
}

echo "Enviados: $enviados, Omitidos por limite: $omitidos\n";
$conn->close();
?>
