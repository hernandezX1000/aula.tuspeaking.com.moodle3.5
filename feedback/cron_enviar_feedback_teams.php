<?php
/**
 * Cron de Envío de Feedback para Teams - TuSpeaking
 * Basado en programación de grupos (no Acuity)
 * Ejecutar: cada 30 min (cada 30 min)
 */
echo date('Y-m-d H:i:s') . " - Inicio cron feedback Teams\n";

$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

// Leer configuración
$config = [];
$res = $conn->query("SELECT clave, valor FROM own_feedback_config");
while ($r = $res->fetch_assoc()) { $config[$r['clave']] = $r['valor']; }

$MAX_SEMANA = intval($config['max_feedbacks_semana'] ?? 2);
$BASE_URL = 'https://aula.tuspeaking.com/app/moodle/feedback/quick_teams.php';

// Día actual en español
$dias = ['sunday'=>'domingo','monday'=>'lunes','tuesday'=>'martes','wednesday'=>'miercoles','thursday'=>'jueves','friday'=>'viernes','saturday'=>'sabado'];
$dia_hoy = $dias[strtolower(date('l'))];
$hora_actual = date('H:i:s');

echo "Día: $dia_hoy, Hora: $hora_actual\n";

// Buscar grupos que terminaron clase hace 30-90 min
$sql = "SELECT g.*, 
        TIME_FORMAT(ADDTIME(g.hora_inicio, '01:00:00'), '%H:%i:%s') as hora_fin_calc
        FROM own_feedback_teams_grupos g
        WHERE g.dia_semana = '$dia_hoy'
        AND g.activo = 1
        AND ADDTIME(g.hora_inicio, '00:30:00') <= '$hora_actual'
        AND ADDTIME(g.hora_inicio, '01:30:00') >= '$hora_actual'";

$grupos = $conn->query($sql);
echo "Grupos candidatos: " . $grupos->num_rows . "\n";

$enviados = 0;
$omitidos = 0;

while ($g = $grupos->fetch_assoc()) {
    echo "Procesando grupo: " . $g['nombre_grupo'] . "\n";
    
    // Obtener alumnos del grupo
    $alumnos = $conn->query("SELECT * FROM own_feedback_teams_alumnos WHERE grupo_id = " . $g['id'] . " AND activo = 1");
    
    while ($a = $alumnos->fetch_assoc()) {
        // Verificar opt-out
        $optout = $conn->query("SELECT 1 FROM own_feedback_optout WHERE email = '".$conn->real_escape_string($a['email'])."'");
        if ($optout->num_rows > 0) {
            echo "  SKIP (opt-out): " . $a['email'] . "\n";
            continue;
        }
        
        // Verificar límite semanal
        $check = $conn->query("SELECT COUNT(*) as cnt FROM own_feedback_envios 
                               WHERE student_email = '".$conn->real_escape_string($a['email'])."' 
                               AND enviado_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        if ($check->fetch_assoc()['cnt'] >= $MAX_SEMANA) {
            echo "  SKIP (limite): " . $a['email'] . "\n";
            $omitidos++;
            continue;
        }
        
        // Verificar si ya enviamos hoy para este grupo
        $hoy = date('Y-m-d');
        $ya_enviado = $conn->query("SELECT 1 FROM own_feedback_envios 
                                    WHERE student_email = '".$conn->real_escape_string($a['email'])."' 
                                    AND DATE(enviado_at) = '$hoy'
                                    AND acuityid = -" . $g['id']); // Usamos acuityid negativo para Teams
        if ($ya_enviado->num_rows > 0) {
            echo "  SKIP (ya enviado hoy): " . $a['email'] . "\n";
            continue;
        }
        
        // Generar token
        $token = substr(hash('sha256', $g['id'] . '-' . $a['id'] . '-' . $hoy . '-tS2026!'), 0, 32);
        $link = $BASE_URL . "?g=" . $g['id'] . "&a=" . $a['id'] . "&t=" . $token . "&lang=" . $g['idioma_formulario'];
        
        // Insertar en envios (acuityid negativo indica Teams)
        $stmt = $conn->prepare("INSERT INTO own_feedback_envios (acuityid, studentid, teacherid, student_email, teacher_name, clase_fecha, token, enviado_at) VALUES (?, ?, 0, ?, ?, NOW(), ?, NOW())");
        $acuityid_teams = -$g['id']; // Negativo para distinguir de Acuity
        $stmt->bind_param("iisss", $acuityid_teams, $a['id'], $a['email'], $g['profesor'], $token);
        $stmt->execute();
        
        // Email
        $lang = $g['idioma_formulario'];
        $subject_txt = $lang == 'en' ? "How was your class with " . $g['profesor'] . "? 📚" : "¿Qué tal tu clase con " . $g['profesor'] . "? 📚";
        $subject = "=?UTF-8?B?" . base64_encode($subject_txt) . "?=";
        
        $saludo = $lang == 'en' ? "Hi" : "Hola";
        $pregunta = $lang == 'en' ? "How was your class with" : "¿Cómo ha ido tu clase con";
        $btn_txt = $lang == 'en' ? "Rate class (30 sec)" : "Valorar clase (30 seg)";
        $footer = $lang == 'en' ? "Your feedback helps us improve" : "Tu opinión nos ayuda a mejorar";
        
        $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;margin:0;padding:0;background:#f5f5f5;">
        <div style="max-width:500px;margin:0 auto;padding:20px;">
        <div style="background:linear-gradient(135deg,#008ba3,#00bcd4);padding:30px;text-align:center;border-radius:16px 16px 0 0;">
        <h1 style="color:white;margin:0;">tuSpeaking</h1></div>
        <div style="background:white;padding:30px;border-radius:0 0 16px 16px;">
        <p>'.$saludo.' <strong>' . htmlspecialchars($a['nombre'] ?? $a['email']) . '</strong>,</p>
        <p style="color:#666;">'.$pregunta.' <strong>' . htmlspecialchars($g['profesor']) . '</strong>?</p>
        <p style="color:#888;font-size:13px;">' . $g['empresa'] . ' - ' . $g['nombre_grupo'] . '</p>
        <div style="text-align:center;margin:30px 0;">
        <a href="' . $link . '" style="display:inline-block;background:#008ba3;color:white;padding:16px 40px;text-decoration:none;border-radius:8px;font-weight:600;">'.$btn_txt.'</a>
        </div>
        <p style="color:#999;font-size:12px;text-align:center;">'.$footer.'</p>
        </div></div></body></html>';
        
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: tuSpeaking <noreply@tuspeaking.com>";
        
        if (@mail($a['email'], $subject, $body, $headers)) {
            echo "  OK: " . $a['email'] . "\n";
            $enviados++;
        } else {
            echo "  FAIL: " . $a['email'] . "\n";
        }
        usleep(100000);
    }
}

echo "Enviados: $enviados, Omitidos: $omitidos\n";
$conn->close();
?>
