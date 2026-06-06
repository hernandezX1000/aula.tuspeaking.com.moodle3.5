<?php
/**
 * Cron Resumen Diario Feedback - TuSpeaking
 * Envía resumen de valoraciones del día anterior
 * Ejecutar: 0 8 * * * php /home/aulatuspeaking/www/app/moodle/feedback/cron_resumen_diario.php
 */
echo date('Y-m-d H:i:s') . " - Inicio resumen diario\n";

$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

// Leer configuración
$config = [];
$res = $conn->query("SELECT clave, valor FROM own_feedback_config");
while ($r = $res->fetch_assoc()) { $config[$r['clave']] = $r['valor']; }

// Verificar si está activo
if (($config['email_resumen_activo'] ?? '0') != '1') {
    echo "Resumen diario desactivado\n";
    exit(0);
}

// Verificar días restantes
$dias = intval($config['dias_resumen_restantes'] ?? 0);
if ($dias <= 0) {
    echo "Periodo de resumen finalizado (0 dias restantes)\n";
    exit(0);
}

// Decrementar contador
$conn->query("UPDATE own_feedback_config SET valor = '".($dias-1)."' WHERE clave = 'dias_resumen_restantes'");
echo "Dias restantes: " . ($dias-1) . "\n";

$email_destino = $config['email_resumen_destino'] ?? 'notificaciones@tuspeaking.com';
$ayer = date('Y-m-d', strtotime('-1 day'));

// Stats del día anterior
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    AVG(valoracion) as media,
    SUM(valoracion <= 3) as alertas,
    SUM(valoracion >= 9) as excelentes
    FROM own_feedback_nps 
    WHERE DATE(submission_date) = '$ayer'")->fetch_assoc();

// Detalle por profesor
$por_profesor = $conn->query("SELECT profesor, COUNT(*) as total, AVG(valoracion) as media 
    FROM own_feedback_nps 
    WHERE DATE(submission_date) = '$ayer' AND profesor != ''
    GROUP BY profesor ORDER BY media DESC");

// Stats de envío
$envios = $conn->query("SELECT 
    COUNT(*) as enviados,
    SUM(abierto_at IS NOT NULL) as abiertos,
    SUM(respondido_at IS NOT NULL) as respondidos
    FROM own_feedback_envios 
    WHERE DATE(enviado_at) = '$ayer'")->fetch_assoc();

$tasa = $envios['enviados'] > 0 ? round($envios['respondidos'] * 100 / $envios['enviados'], 1) : 0;

// Construir email HTML
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:sans-serif;margin:0;padding:20px;background:#f5f5f5;">
<div style="max-width:600px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#008ba3,#00bcd4);padding:24px;text-align:center;">
<h1 style="color:white;margin:0;">tuSpeaking</h1>
<p style="color:rgba(255,255,255,0.9);margin:8px 0 0;">Resumen Feedback - '.$ayer.'</p>
</div>
<div style="padding:24px;">

<h2 style="color:#333;font-size:18px;margin:0 0 16px;">📊 Resumen del Día</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
<tr style="background:#f9f9f9;"><td style="padding:12px;border:1px solid #eee;"><strong>Total feedbacks</strong></td><td style="padding:12px;border:1px solid #eee;text-align:center;font-size:24px;color:#008ba3;"><strong>'.intval($stats['total']).'</strong></td></tr>
<tr><td style="padding:12px;border:1px solid #eee;"><strong>Valoración media</strong></td><td style="padding:12px;border:1px solid #eee;text-align:center;font-size:24px;color:'.($stats['media']>=8?'#27ae60':($stats['media']>=6?'#f39c12':'#e74c3c')).'"><strong>'.number_format($stats['media'],1).'</strong></td></tr>
<tr style="background:#f9f9f9;"><td style="padding:12px;border:1px solid #eee;"><strong>Excelentes (≥9)</strong></td><td style="padding:12px;border:1px solid #eee;text-align:center;color:#27ae60;"><strong>'.intval($stats['excelentes']).'</strong></td></tr>
<tr><td style="padding:12px;border:1px solid #eee;"><strong>Alertas (≤3)</strong></td><td style="padding:12px;border:1px solid #eee;text-align:center;color:#e74c3c;"><strong>'.intval($stats['alertas']).'</strong></td></tr>
</table>

<h2 style="color:#333;font-size:18px;margin:0 0 16px;">📧 Tasa de Conversión</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
<tr><td style="padding:12px;border:1px solid #eee;">Emails enviados</td><td style="padding:12px;border:1px solid #eee;text-align:right;"><strong>'.intval($envios['enviados']).'</strong></td></tr>
<tr><td style="padding:12px;border:1px solid #eee;">Abiertos</td><td style="padding:12px;border:1px solid #eee;text-align:right;"><strong>'.intval($envios['abiertos']).'</strong></td></tr>
<tr><td style="padding:12px;border:1px solid #eee;">Respondidos</td><td style="padding:12px;border:1px solid #eee;text-align:right;"><strong>'.intval($envios['respondidos']).'</strong> <span style="background:#e3f2fd;color:#1976d2;padding:2px 8px;border-radius:10px;font-size:12px;">'.$tasa.'%</span></td></tr>
</table>';

// Detalle por profesor si hay datos
if ($por_profesor->num_rows > 0) {
    $html .= '<h2 style="color:#333;font-size:18px;margin:0 0 16px;">👨‍🏫 Por Profesor</h2>
    <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
    <tr style="background:#f9f9f9;"><th style="padding:10px;border:1px solid #eee;text-align:left;">Profesor</th><th style="padding:10px;border:1px solid #eee;text-align:center;">Feedbacks</th><th style="padding:10px;border:1px solid #eee;text-align:center;">Media</th></tr>';
    while ($p = $por_profesor->fetch_assoc()) {
        $color = $p['media'] >= 8 ? '#27ae60' : ($p['media'] >= 6 ? '#f39c12' : '#e74c3c');
        $html .= '<tr><td style="padding:10px;border:1px solid #eee;">'.htmlspecialchars($p['profesor']).'</td><td style="padding:10px;border:1px solid #eee;text-align:center;">'.$p['total'].'</td><td style="padding:10px;border:1px solid #eee;text-align:center;color:'.$color.';font-weight:bold;">'.number_format($p['media'],1).'</td></tr>';
    }
    $html .= '</table>';
}

$html .= '<p style="color:#999;font-size:12px;text-align:center;margin-top:24px;">Días restantes de resumen: '.($dias-1).'</p>
<p style="text-align:center;margin-top:16px;"><a href="https://aula.tuspeaking.com/app/moodle/feedback/admin.php" style="background:#008ba3;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;">Ver Panel Completo</a></p>
</div></div></body></html>';

// Enviar email
$subject = "=?UTF-8?B?" . base64_encode("📊 Resumen Feedback $ayer - tuSpeaking") . "?=";
$headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: tuSpeaking <noreply@tuspeaking.com>";

if (@mail($email_destino, $subject, $html, $headers)) {
    echo "Resumen enviado a $email_destino\n";
} else {
    echo "Error enviando resumen\n";
}

$conn->close();
?>
