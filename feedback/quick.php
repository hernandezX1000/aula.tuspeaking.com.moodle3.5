<?php
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$acuityid = isset($_GET['a']) ? intval($_GET['a']) : 0;
$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/', '', $_GET['t']) : '';
$es_recordatorio = isset($_GET['r']) && $_GET['r'] == '1';

if (!$acuityid || !$token) {
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h2>Enlace inválido</h2><p style="color:#666;margin:20px 0;">Este enlace requiere un código válido.</p><a href="/app/moodle/feedback/" style="color:#008ba3;">Ir al formulario de feedback</a></div>');
}

// CORREGIDO: Usar own_acuity + mdl_event en lugar de mdl_i3code_acuityZoom
$sql = "SELECT e.*, u_student.firstname as student_firstname, u_teacher.firstname as teacher_firstname, u_teacher.lastname as teacher_lastname, 
        ev.name as acuity_type, FROM_UNIXTIME(ev.timestart) as acuity_datetime
        FROM own_feedback_envios e
        INNER JOIN mdl_user u_student ON e.studentid = u_student.id
        INNER JOIN mdl_user u_teacher ON e.teacherid = u_teacher.id
        LEFT JOIN own_acuity oa ON e.acuityid = oa.acuityid
        LEFT JOIN mdl_event ev ON oa.studenteventid = ev.id
        WHERE e.acuityid = ? AND e.token = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $acuityid, $token);
$stmt->execute();
$envio = $stmt->get_result()->fetch_assoc();

if (!$envio) {
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h2>Enlace no válido</h2><p style="color:#666;">El enlace ha expirado o no es correcto.</p></div>');
}

if ($envio['respondido_at']) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;max-width:400px;"><span class="material-icons" style="font-size:64px;color:#27ae60;">check_circle</span><h2 style="color:#008ba3;margin:16px 0 8px;">¡Gracias!</h2><p style="color:#666;">Ya has enviado tu valoración.</p></div></body></html>');
}

if (!$envio['abierto_at']) {
    $conn->query("UPDATE own_feedback_envios SET abierto_at = NOW() WHERE id = " . $envio['id']);
}

$mensaje = '';
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valoracion = intval($_POST['valoracion']);
    $comentarios = trim($_POST['comentarios'] ?? '');
    $problema = isset($_POST['problema_conexion']) ? 'Si' : 'No';
    $no_asisti = isset($_POST['no_asisti']) ? 'Si (declarado por alumno)' : 'No';
    $recibio = isset($_POST['recibio_feedback']) ? $_POST['recibio_feedback'] : '';
    
    if ($valoracion >= 1 && $valoracion <= 10) {
        $acuity_type = $envio['acuity_type'] ?? '';
        $type_lower = strtolower($acuity_type);
        $idioma = 'Inglés';
        if (strpos($type_lower, 'french') !== false || strpos($type_lower, 'francés') !== false || strpos($type_lower, 'frances') !== false) $idioma = 'Francés';
        if (strpos($type_lower, 'german') !== false || strpos($type_lower, 'alemán') !== false || strpos($type_lower, 'aleman') !== false) $idioma = 'Alemán';
        if (strpos($type_lower, 'portuguese') !== false || strpos($type_lower, 'portugués') !== false) $idioma = 'Portugués';
        if (strpos($type_lower, 'spanish') !== false || strpos($type_lower, 'español') !== false) $idioma = 'Español';
        
        $profesor = $envio['teacher_firstname'] . ' ' . $envio['teacher_lastname'];
        
        $stmt = $conn->prepare("INSERT INTO own_feedback_nps (acuityid, studentid, teacherid, submission_date, idioma, profesor, valoracion, problema_conexion, no_asisti, recibio_feedback, comentarios, email, created_at, enviado_auto, token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, ?)");
        $fecha = date('Y-m-d', strtotime($envio['clase_fecha']));
        $stmt->bind_param("iiisssississs", $envio['acuityid'], $envio['studentid'], $envio['teacherid'], $fecha, $idioma, $profesor, $valoracion, $problema, $no_asisti, $recibio, $comentarios, $envio['student_email'], $token);
        
        if ($stmt->execute()) {
            $feedback_id = $conn->insert_id;
            $conn->query("UPDATE own_feedback_envios SET respondido_at = NOW(), feedback_id = $feedback_id WHERE id = " . $envio['id']);
            
            // Alerta si valoración <= 3
            if ($valoracion <= 3) {
                $config_alertas = $conn->query("SELECT valor FROM own_feedback_config WHERE clave = 'email_alertas'")->fetch_assoc();
                $email_alertas = $config_alertas['valor'] ?? 'notificaciones@tuspeaking.com';
                
                $asunto = "=?UTF-8?B?" . base64_encode("⚠️ Alerta: Valoración baja ($valoracion/10) - " . $envio['student_email']) . "?=";
                $cuerpo = "Alumno: " . $envio['student_firstname'] . " (" . $envio['student_email'] . ")\n";
                $cuerpo .= "Profesor: $profesor\n";
                $cuerpo .= "Valoración: $valoracion/10\n";
                $cuerpo .= "Fecha clase: " . $envio['clase_fecha'] . "\n";
                $cuerpo .= "Comentarios: $comentarios\n";
                @mail($email_alertas, $asunto, $cuerpo, "From: tuSpeaking <noreply@tuspeaking.com>");
            }
            
            $enviado = true;
        } else {
            $mensaje = 'Error al guardar. Inténtalo de nuevo.';
        }
    } else {
        $mensaje = 'Por favor, selecciona una valoración.';
    }
}

$profesor_nombre = $envio['teacher_firstname'];
$fecha_clase = $envio['clase_fecha'] ? date('d/m/Y H:i', strtotime($envio['clase_fecha'])) : date('d/m/Y H:i', strtotime($envio['enviado_at']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Valorar clase - tuSpeaking</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:white;border-radius:20px;max-width:500px;width:100%;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.2)}
.header{background:linear-gradient(135deg,#008ba3,#00bcd4);padding:30px;text-align:center;color:white}
.header h1{font-size:24px;font-weight:300}
.header p{opacity:0.9;margin-top:8px}
.content{padding:30px}
.profesor{text-align:center;margin-bottom:25px}
.profesor-nombre{font-size:20px;color:#333;font-weight:600}
.profesor-fecha{color:#888;font-size:14px;margin-top:5px}
.rating{display:flex;justify-content:center;gap:8px;margin:25px 0;flex-wrap:wrap}
.rating input{display:none}
.rating label{width:42px;height:42px;border-radius:50%;border:2px solid #ddd;display:flex;align-items:center;justify-content:center;cursor:pointer;font-weight:600;color:#666;transition:all 0.2s}
.rating label:hover{border-color:#008ba3;color:#008ba3}
.rating input:checked+label{background:#008ba3;border-color:#008ba3;color:white}
.scale{display:flex;justify-content:space-between;color:#888;font-size:12px;margin-bottom:20px;padding:0 10px}
.checkbox-group{margin:20px 0}
.checkbox-group label{display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px;border-radius:8px;transition:background 0.2s}
.checkbox-group label:hover{background:#f5f5f5}
.checkbox-group input[type="checkbox"]{width:20px;height:20px;accent-color:#008ba3}
textarea{width:100%;padding:15px;border:1px solid #ddd;border-radius:10px;font-size:14px;resize:vertical;min-height:80px;font-family:inherit}
textarea:focus{outline:none;border-color:#008ba3}
.btn{width:100%;padding:16px;background:linear-gradient(135deg,#008ba3,#00bcd4);color:white;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer;margin-top:20px;transition:transform 0.2s}
.btn:hover{transform:scale(1.02)}
.error{background:#fee;color:#c00;padding:12px;border-radius:8px;margin-bottom:15px;text-align:center}
.success{text-align:center;padding:40px}
.success .material-icons{font-size:80px;color:#27ae60}
.success h2{color:#008ba3;margin:20px 0 10px}
.success p{color:#666}
</style>
</head>
<body>
<div class="card">
<?php if($enviado): ?>
<div class="success">
    <span class="material-icons">check_circle</span>
    <h2>¡Gracias!</h2>
    <p>Tu valoración ha sido enviada correctamente.</p>
</div>
<?php else: ?>
<div class="header">
    <h1>tuSpeaking</h1>
    <p>Tu opinión nos ayuda a mejorar</p>
</div>
<div class="content">
    <div class="profesor">
        <div class="profesor-nombre">¿Cómo ha ido tu clase con <?= htmlspecialchars($profesor_nombre) ?>?</div>
        <div class="profesor-fecha"><?= $fecha_clase ?></div>
    </div>
    
    <?php if($mensaje): ?><div class="error"><?= $mensaje ?></div><?php endif; ?>
    
    <form method="POST">
        <div class="rating">
            <?php for($i=1; $i<=10; $i++): ?>
            <input type="radio" name="valoracion" id="v<?=$i?>" value="<?=$i?>">
            <label for="v<?=$i?>"><?=$i?></label>
            <?php endfor; ?>
        </div>
        <div class="scale">
            <span>😞 Muy mal</span>
            <span>😊 Excelente</span>
        </div>
        
        <div class="checkbox-group">
            <label><input type="checkbox" name="no_asisti" value="1" id="no_asisti"> No asistí a la clase</label>
            <label><input type="checkbox" name="problema_conexion" value="1"> ¿El profesor tuvo problemas de conexión?</label>
            <?php if($es_recordatorio): ?><label><input type="checkbox" name="recibio_feedback" value="Si"> Recibí feedback del profesor</label><?php endif; ?>
        </div>
        
        <textarea name="comentarios" placeholder="¿Algún comentario adicional? (opcional)"></textarea>
        
        <button type="submit" class="btn">Enviar valoración</button>
    </form>
</div>
<?php endif; ?>
</div>
</body>
</html>
<?php $conn->close(); ?>
