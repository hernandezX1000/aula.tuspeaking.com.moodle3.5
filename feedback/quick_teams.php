<?php
/**
 * Formulario Feedback Rápido Teams - TuSpeaking
 * Soporta español (es) e inglés (en)
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$grupo_id = intval($_GET['g'] ?? 0);
$alumno_id = intval($_GET['a'] ?? 0);
$token = $_GET['t'] ?? '';
$lang = $_GET['lang'] ?? 'es';
$es_recordatorio = isset($_GET['r']);

// Textos
$txt = [
    'es' => [
        'titulo' => 'Valorar clase',
        'profesor' => 'Profesor',
        'grupo' => 'Grupo',
        'pregunta' => '¿Cómo valorarías la clase?',
        'comentarios' => 'Comentarios (opcional)',
        'feedback_q' => '¿Recibiste feedback del profesor?',
        'si' => 'Sí',
        'no' => 'No',
        'enviar' => 'Enviar valoración',
        'gracias' => 'Gracias',
        'enviado' => 'Tu valoración ha sido enviada.',
        'ya_enviado' => 'Ya has enviado tu valoración.',
        'invalido' => 'Enlace inválido',
        'selecciona' => 'Selecciona una valoración del 1 al 10',
        'optout' => 'No quiero recibir más emails de feedback'
    ],
    'en' => [
        'titulo' => 'Rate class',
        'profesor' => 'Teacher',
        'grupo' => 'Group',
        'pregunta' => 'How would you rate the class?',
        'comentarios' => 'Comments (optional)',
        'feedback_q' => 'Did you receive feedback from the teacher?',
        'si' => 'Yes',
        'no' => 'No',
        'enviar' => 'Submit rating',
        'gracias' => 'Thank you',
        'enviado' => 'Your rating has been submitted.',
        'ya_enviado' => 'You have already submitted your rating.',
        'invalido' => 'Invalid link',
        'selecciona' => 'Select a rating from 1 to 10',
        'optout' => 'I don\'t want to receive more feedback emails'
    ]
];
$t = $txt[$lang] ?? $txt['es'];

// Validar token
$token_esperado = substr(hash('sha256', $grupo_id . '-' . $alumno_id . '-' . date('Y-m-d') . '-tS2026!'), 0, 32);
if (!$grupo_id || !$alumno_id || $token !== $token_esperado) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;"><span class="material-icons" style="font-size:64px;color:#e74c3c;">error</span><h2 style="color:#333;">'.$t['invalido'].'</h2></div></body></html>');
}

// Obtener datos
$grupo = $conn->query("SELECT * FROM own_feedback_teams_grupos WHERE id = $grupo_id")->fetch_assoc();
$alumno = $conn->query("SELECT * FROM own_feedback_teams_alumnos WHERE id = $alumno_id")->fetch_assoc();

if (!$grupo || !$alumno) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;"><span class="material-icons" style="font-size:64px;color:#e74c3c;">error</span><h2 style="color:#333;">'.$t['invalido'].'</h2></div></body></html>');
}

// Verificar si ya respondió
$envio = $conn->query("SELECT * FROM own_feedback_envios WHERE acuityid = -$grupo_id AND student_email = '".$conn->real_escape_string($alumno['email'])."' AND DATE(enviado_at) = CURDATE()")->fetch_assoc();
if ($envio && $envio['respondido_at']) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;"><span class="material-icons" style="font-size:64px;color:#27ae60;">check_circle</span><h2 style="color:#008ba3;">'.$t['gracias'].'</h2><p style="color:#666;">'.$t['ya_enviado'].'</p></div></body></html>');
}

// Marcar apertura
if ($envio) {
    $conn->query("UPDATE own_feedback_envios SET abierto_at = IFNULL(abierto_at, NOW()) WHERE id = " . $envio['id']);
}

$mensaje = '';
$enviado = false;

// Procesar envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valoracion = intval($_POST['valoracion'] ?? 0);
    $comentarios = trim($_POST['comentarios'] ?? '');
    $recibio_feedback = $_POST['recibio_feedback'] ?? '';
    
    if ($valoracion >= 1 && $valoracion <= 10) {
        $stmt = $conn->prepare("INSERT INTO own_feedback_nps (submission_date, idioma, profesor, valoracion, problema_conexion, recibio_feedback, comentarios, email, enviado_auto, acuityid, studentid, teacherid, token) VALUES (NOW(), ?, ?, ?, '', ?, ?, ?, 1, ?, ?, 0, ?)");
        $idioma = $grupo['empresa'];
        $acuityid_teams = -$grupo_id;
        $stmt->bind_param("ssisssiis", $idioma, $grupo['profesor'], $valoracion, $recibio_feedback, $comentarios, $alumno['email'], $acuityid_teams, $alumno_id, $token);
        
        if ($stmt->execute()) {
            $feedback_id = $conn->insert_id;
            if ($envio) {
                $conn->query("UPDATE own_feedback_envios SET respondido_at = NOW(), feedback_id = $feedback_id WHERE id = " . $envio['id']);
            }
            
            // Alerta si valoración baja
            if ($valoracion <= 3) {
                $email_alertas = $conn->query("SELECT valor FROM own_feedback_config WHERE clave = 'email_alertas'")->fetch_assoc()['valor'] ?? 'notificaciones@tuspeaking.com';
                @mail($email_alertas, "Alerta Feedback Teams $valoracion", "Empresa: {$grupo['empresa']}\nGrupo: {$grupo['nombre_grupo']}\nProfesor: {$grupo['profesor']}\nAlumno: {$alumno['email']}\nValoracion: $valoracion/10\nComentarios: $comentarios", "From: noreply@tuspeaking.com");
            }
            $enviado = true;
        }
    } else {
        $mensaje = $t['selecciona'];
    }
}

if ($enviado) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head><body style="margin:0;font-family:sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:40px;border-radius:16px;text-align:center;max-width:400px;"><span class="material-icons" style="font-size:64px;color:#27ae60;">check_circle</span><h2 style="color:#008ba3;margin:16px 0 8px;">'.$t['gracias'].'</h2><p style="color:#666;">'.$t['enviado'].'</p><p style="margin-top:24px;font-size:12px;"><a href="optout.php?t='.$token.'" style="color:#999;">'.$t['optout'].'</a></p></div></body></html>');
}
?>
<!DOCTYPE html>
<html lang="<?=$lang?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$t['titulo']?> - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#008ba3,#00bcd4);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:white;border-radius:16px;max-width:450px;width:100%;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.2)}
        .header{background:#008ba3;color:white;padding:24px;text-align:center}
        .header h1{font-size:20px;margin-bottom:4px;display:flex;align-items:center;justify-content:center;gap:8px}
        .content{padding:24px}
        .info{display:flex;align-items:center;gap:8px;padding:10px;background:#f5f5f5;border-radius:8px;margin-bottom:16px;font-size:14px}
        .info .material-icons{color:#008ba3;font-size:20px}
        .pregunta{font-weight:600;margin-bottom:12px;color:#333}
        .rating{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:20px}
        .rating button{height:44px;border:2px solid #ddd;border-radius:8px;background:white;font-size:16px;font-weight:600;cursor:pointer;transition:all .2s}
        .rating button:hover{border-color:#008ba3;background:#e0f7fa}
        .rating button.selected{background:#008ba3;color:white;border-color:#008ba3}
        .rating button.low{border-color:#ffcdd2}
        .rating button.low.selected{background:#e74c3c;border-color:#e74c3c}
        .rating button.mid{border-color:#fff3cd}
        .rating button.mid.selected{background:#f39c12;border-color:#f39c12}
        .rating button.high{border-color:#c8e6c9}
        .rating button.high.selected{background:#27ae60;border-color:#27ae60}
        textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:none;height:80px;margin-bottom:16px}
        .feedback-q{margin-bottom:16px}
        .feedback-q label{display:block;margin-bottom:8px;font-weight:500;color:#333}
        .feedback-q .options{display:flex;gap:16px}
        .feedback-q input[type="radio"]{margin-right:4px}
        .submit{width:100%;padding:14px;background:#008ba3;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
        .submit:hover{background:#007a91}
        .error{background:#ffebee;color:#e74c3c;padding:12px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1><span class="material-icons">rate_review</span> <?=$t['titulo']?></h1>
        </div>
        <div class="content">
            <div class="info"><span class="material-icons">school</span> <strong><?=$t['profesor']?>:</strong> <?=htmlspecialchars($grupo['profesor'])?></div>
            <div class="info"><span class="material-icons">group</span> <strong><?=$t['grupo']?>:</strong> <?=htmlspecialchars($grupo['nombre_grupo'])?></div>
            
            <?php if($mensaje): ?><div class="error"><span class="material-icons">error</span> <?=$mensaje?></div><?php endif; ?>
            
            <form method="POST">
                <p class="pregunta"><?=$t['pregunta']?></p>
                <div class="rating">
                    <?php for($i=1; $i<=10; $i++): 
                        $class = $i<=3 ? 'low' : ($i<=6 ? 'mid' : 'high');
                    ?>
                    <button type="button" class="<?=$class?>" onclick="selectRating(<?=$i?>)"><?=$i?></button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="valoracion" id="valoracion" value="">
                
                <?php if($es_recordatorio): ?>
                <div class="feedback-q">
                    <label><?=$t['feedback_q']?></label>
                    <div class="options">
                        <label><input type="radio" name="recibio_feedback" value="si"> <?=$t['si']?></label>
                        <label><input type="radio" name="recibio_feedback" value="no"> <?=$t['no']?></label>
                    </div>
                </div>
                <?php endif; ?>
                
                <textarea name="comentarios" placeholder="<?=$t['comentarios']?>"></textarea>
                <button type="submit" class="submit"><span class="material-icons">send</span> <?=$t['enviar']?></button>
            </form>
        </div>
    </div>
    <script>
    function selectRating(val) {
        document.getElementById('valoracion').value = val;
        var btns = document.querySelectorAll('.rating button');
        for(var i=0; i<btns.length; i++) { btns[i].classList.remove('selected'); }
        btns[val-1].classList.add('selected');
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>
