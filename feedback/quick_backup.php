<?php
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$acuityid = isset($_GET['a']) ? intval($_GET['a']) : 0;
$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/', '', $_GET['t']) : '';
$es_recordatorio = isset($_GET['r']) && $_GET['r'] == '1';

if (!$acuityid || !$token) {
    die("<div style="text-align:center;padding:50px;font-family:sans-serif;"><h2>Enlace inválido</h2><p style="color:#666;margin:20px 0;">Este enlace requiere un código válido.</p><a href="/app/moodle/feedback/" style="color:#008ba3;">Ir al formulario de feedback</a></div>");
}

$sql = "SELECT e.*, u_student.firstname as student_firstname, u_teacher.firstname as teacher_firstname, u_teacher.lastname as teacher_lastname, a.acuity_type, a.acuity_datetime
        FROM own_feedback_envios e
        INNER JOIN mdl_user u_student ON e.studentid = u_student.id
        INNER JOIN mdl_user u_teacher ON e.teacherid = u_teacher.id
        INNER JOIN mdl_i3code_acuityZoom a ON e.acuityid = a.acuityid
        WHERE e.acuityid = ? AND e.token = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $acuityid, $token);
$stmt->execute();
$envio = $stmt->get_result()->fetch_assoc();

if (!$envio) {
    die("<div style='text-align:center;padding:50px;font-family:sans-serif;'><h2>Enlace no válido</h2></div>");
}

if ($envio['respondido_at']) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;"><span class="material-icons" style="font-size:64px;color:#27ae60;">check_circle</span><h2 style="color:#008ba3;margin:16px 0 8px;">Gracias</h2><p style="color:#666;">Ya has enviado tu valoracion.</p></div></body></html>');
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
    $recibio = isset($_POST['recibio_feedback']) ? $_POST['recibio_feedback'] : '';
    
    if ($valoracion >= 1 && $valoracion <= 10) {
        $type_lower = strtolower($envio['acuity_type']);
        $idioma = 'Ingles';
        if (strpos($type_lower, 'french') !== false || strpos($type_lower, 'frances') !== false) $idioma = 'Frances';
        if (strpos($type_lower, 'german') !== false || strpos($type_lower, 'aleman') !== false) $idioma = 'Aleman';
        
        $profesor = $envio['teacher_firstname'] . ' ' . $envio['teacher_lastname'];
        $sql_ins = "INSERT INTO own_feedback_nps (acuityid, studentid, teacherid, submission_date, idioma, profesor, valoracion, problema_conexion, recibio_feedback, comentarios, email, enviado_auto) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bind_param("iiisssisss", $envio['acuityid'], $envio['studentid'], $envio['teacherid'], $idioma, $profesor, $valoracion, $problema, $recibio, $comentarios, $envio['student_email']);
        
        if ($stmt_ins->execute()) {
            $feedback_id = $conn->insert_id;
            $conn->query("UPDATE own_feedback_envios SET respondido_at = NOW(), feedback_id = $feedback_id WHERE id = " . $envio['id']);
            if ($valoracion <= 3) {
                @mail(($conn->query("SELECT valor FROM own_feedback_config WHERE clave='email_alertas'")->fetch_assoc()['valor'] ?? 'notificaciones@tuspeaking.com'), "Alerta Feedback $valoracion", "Profesor: $profesor\nAlumno: {$envio['student_email']}\nValoracion: $valoracion/10\nComentarios: $comentarios", "From: noreply@tuspeaking.com");
            }
            $enviado = true;
        }
    } else {
        $mensaje = "Selecciona una valoracion del 1 al 10";
    }
}

if ($enviado) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;max-width:400px;"><span class="material-icons" style="font-size:64px;color:#27ae60;">check_circle</span><h2 style="color:#008ba3;margin:16px 0 8px;">Gracias</h2><p style="color:#666;">Tu valoracion ha sido enviada.</p><p style="margin-top:24px;font-size:12px;"><a href="optout.php?t='.$token.'" style="color:#999;">No quiero recibir mas emails de feedback</a></p></div></body></html>');
}

$fecha = date('d/m/Y H:i', strtotime($envio['acuity_datetime']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valora tu clase - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .container{background:#fff;border-radius:16px;padding:30px;max-width:420px;width:100%;box-shadow:0 4px 20px rgba(0,0,0,.2)}
        .header{text-align:center;margin-bottom:24px}
        .logo{color:#008ba3;font-size:24px;font-weight:700}
        .teacher{font-size:20px;color:#333;text-align:center;margin:16px 0 8px;display:flex;align-items:center;justify-content:center;gap:8px}
        .teacher .material-icons{color:#008ba3;font-size:28px}
        .date{text-align:center;color:#888;font-size:13px;margin-bottom:24px;display:flex;align-items:center;justify-content:center;gap:4px}
        .date .material-icons{font-size:16px}
        .rating-btns{display:grid;grid-template-columns:repeat(10,1fr);gap:4px;margin-bottom:16px}
        .rating-btn{width:100%;height:36px;border:2px solid #ddd;border-radius:6px;background:#fff;font-size:14px;font-weight:600;color:#666;cursor:pointer;transition:all .2s;padding:0}
        .rating-btn:hover{border-color:#008ba3;color:#008ba3}
        .rating-btn.selected{background:#27ae60;border-color:#27ae60;color:#fff}
        .rating-btn.selected.low{background:#e74c3c;border-color:#e74c3c}
        .rating-btn.selected.med{background:#f39c12;border-color:#f39c12}
        .scale{display:flex;justify-content:space-between;font-size:11px;color:#999;margin-bottom:20px}
        .toggle{background:none;border:none;color:#008ba3;font-size:13px;cursor:pointer;width:100%;padding:8px;display:flex;align-items:center;justify-content:center;gap:4px}
        .toggle .material-icons{font-size:18px}
        .optional{display:none;margin-top:16px}
        .optional.show{display:block}
        .field{margin-bottom:16px}
        .field label{display:block;font-size:13px;color:#666;margin-bottom:6px}
        textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:14px;min-height:80px;resize:vertical}
        textarea:focus{outline:none;border-color:#008ba3}
        .checkbox{display:flex;gap:20px;margin-top:8px}
        .checkbox label{display:flex;align-items:center;gap:6px;font-size:14px;color:#666;cursor:pointer}
        .checkbox input{width:18px;height:18px;accent-color:#008ba3}
        .submit{width:100%;padding:16px;background:#008ba3;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .2s}
        .submit:hover{background:#007a91}
        .submit:disabled{background:#ccc;cursor:not-allowed}
        .submit .material-icons{font-size:20px}
        .error{background:#ffebee;color:#c62828;padding:12px;border-radius:8px;margin-bottom:16px;text-align:center}
    </style>
</head>
<body>
<div class="container">
    <div class="header"><div class="logo">tuSpeaking</div><small style="color:#666">¿Qué tal ha ido tu clase?</small></div>
    <div class="teacher"><span class="material-icons">school</span> <?= htmlspecialchars($envio['teacher_firstname']) ?></div>
    <div class="date"><span class="material-icons">event</span> <?= $fecha ?></div>
    <?php if($mensaje): ?><div class="error"><?= $mensaje ?></div><?php endif; ?>
    <form method="POST">
        <div class="rating-btns">
            <?php for($i=1;$i<=10;$i++): ?><button type="button" class="rating-btn" data-v="<?=$i?>"><?=$i?></button><?php endfor; ?>
        </div>
        <div class="scale"><span>Muy mal</span><span>Excelente</span></div>
        <input type="hidden" name="valoracion" id="val">
        <button type="button" class="toggle" onclick="document.getElementById('opt').classList.toggle('show')">
            <span class="material-icons">add_comment</span> Añadir comentario (opcional)
        </button>
        <div class="optional" id="opt">
            <div class="field"><label>Comentario</label><textarea name="comentarios" placeholder="Cuéntanos tu experiencia..."></textarea></div>
            <div class="field"><label>¿Problemas de conexión?</label><div class="checkbox"><label><input type="checkbox" name="problema_conexion" value="1"> Sí, tuve problemas</label></div></div>
            <?php if($es_recordatorio): ?>
            <div class="field"><label>¿Recibiste feedback del profesor?</label><div class="checkbox"><label><input type="radio" name="recibio_feedback" value="Si"> Sí</label><label><input type="radio" name="recibio_feedback" value="No"> No</label></div></div>
            <?php endif; ?>
        </div>
        <button type="submit" class="submit" id="btn" disabled><span class="material-icons">send</span> Enviar valoración</button>
    </form>
</div>
<script>
document.querySelectorAll('.rating-btn').forEach(function(b){
    b.addEventListener('click', function(){
        document.querySelectorAll('.rating-btn').forEach(function(x){x.className='rating-btn';});
        var v=parseInt(this.getAttribute('data-v'));
        this.classList.add('selected');
        if(v<=3) this.classList.add('low');
        else if(v<=6) this.classList.add('med');
        document.getElementById('val').value=v;
        document.getElementById('btn').disabled=false;
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
