<?php
/**
 * Cron Recordatorio 24h - TuSpeaking
 * Envía segundo email a quienes no respondieron
 * Ejecutar: 0 9 * * * php /home/aulatuspeaking/www/app/moodle/feedback/cron_recordatorio_24h.php
 */
define('BASE_URL', 'https://aula.tuspeaking.com/app/moodle/feedback/quick.php');

echo date('Y-m-d H:i:s') . " - Inicio cron recordatorio 24h\n";

$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) { die("Error BD: " . $conn->connect_error); }

// Buscar envíos de hace 20-28 horas sin respuesta y sin recordatorio previo
$sql = "SELECT e.*, u.firstname as teacher_firstname
        FROM own_feedback_envios e
        INNER JOIN mdl_user u ON e.teacherid = u.id
        WHERE e.respondido_at IS NULL
        AND e.recordatorio_enviado IS NULL
        AND e.enviado_at BETWEEN DATE_SUB(NOW(), INTERVAL 28 HOUR) AND DATE_SUB(NOW(), INTERVAL 20 HOUR)
        AND e.student_email IS NOT NULL";

$result = $conn->query($sql);

if (!$result) {
    echo "Error SQL: " . $conn->error . "\n";
    exit(1);
}

echo "Pendientes de recordatorio: " . $result->num_rows . "\n";

$enviados = 0;
while ($e = $result->fetch_assoc()) {
    $link = BASE_URL . "?a=" . $e['acuityid'] . "&t=" . $e['token'] . "&r=1";
    
    $subject = "=?UTF-8?B?" . base64_encode("¿Nos das tu opinión? Solo 30 segundos ⏱️") . "?=";
    $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;margin:0;padding:0;background:#f5f5f5;">
    <div style="max-width:500px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#008ba3,#00bcd4);padding:30px;text-align:center;border-radius:16px 16px 0 0;">
    <h1 style="color:white;margin:0;">tuSpeaking</h1></div>
    <div style="background:white;padding:30px;border-radius:0 0 16px 16px;">
    <p>Hola <strong>' . htmlspecialchars($e['student_email']) . '</strong>,</p>
    <p style="color:#666;">Vimos que aun no valoraste tu clase con <strong>' . htmlspecialchars($e['teacher_firstname']) . '</strong>.</p>
    <p style="color:#666;">Tu opinion nos ayuda mucho a mejorar. Solo toma 30 segundos:</p>
    <div style="text-align:center;margin:30px 0;">
    <a href="' . $link . '" style="display:inline-block;background:#008ba3;color:white;padding:16px 40px;text-decoration:none;border-radius:8px;font-weight:600;">Valorar ahora</a>
    </div>
    <p style="color:#999;font-size:12px;text-align:center;">Gracias por tu tiempo</p>
    </div></div></body></html>';
    
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: tuSpeaking <noreply@tuspeaking.com>";
    
    if (@mail($e['student_email'], $subject, $body, $headers)) {
        $conn->query("UPDATE own_feedback_envios SET recordatorio_enviado = NOW() WHERE id = " . $e['id']);
        echo "OK: " . $e['student_email'] . "\n";
        $enviados++;
    } else {
        echo "FAIL: " . $e['student_email'] . "\n";
    }
    usleep(100000);
}

echo "Recordatorios enviados: $enviados\n";
$conn->close();
?>
