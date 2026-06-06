<?php
/**
 * Página de Opt-out Feedback - TuSpeaking
 * Solo accesible después de dar feedback (requiere token válido)
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$token = $_GET['t'] ?? '';
$mensaje = '';
$exito = false;
$puede_optout = false;
$datos = null;

// Verificar token - debe ser de alguien que ya respondió
if ($token) {
    $stmt = $conn->prepare("SELECT e.*, n.id as feedback_id FROM own_feedback_envios e 
                            LEFT JOIN own_feedback_nps n ON e.feedback_id = n.id 
                            WHERE e.token = ? AND e.respondido_at IS NOT NULL");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $datos = $result->fetch_assoc();
        $puede_optout = true;
        
        // Verificar si ya está en opt-out
        $check = $conn->query("SELECT 1 FROM own_feedback_optout WHERE email = '".$conn->real_escape_string($datos['student_email'])."'");
        if ($check->num_rows > 0) {
            $mensaje = "Ya estás dado de baja de los emails de feedback.";
            $puede_optout = false;
            $exito = true;
        }
    }
}

// Procesar opt-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_optout && $datos) {
    $motivo = $_POST['motivo'] ?? '';
    $nombre = $datos['student_email']; // Usar email como nombre si no tenemos otro
    
    // Buscar nombre real
    $user = $conn->query("SELECT firstname, lastname FROM mdl_user WHERE email = '".$conn->real_escape_string($datos['student_email'])."' LIMIT 1");
    if ($user->num_rows > 0) {
        $u = $user->fetch_assoc();
        $nombre = $u['firstname'] . ' ' . $u['lastname'];
    }
    
    $stmt = $conn->prepare("INSERT INTO own_feedback_optout (email, nombre, motivo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $datos['student_email'], $nombre, $motivo);
    
    if ($stmt->execute()) {
        $exito = true;
        $mensaje = "Te has dado de baja correctamente. No recibirás más emails de feedback.";
        $puede_optout = false;
        
        // Log
        $conn->query("INSERT INTO own_feedback_logs (tipo, mensaje, email) VALUES ('optout', 'Opt-out solicitado: $motivo', '".$conn->real_escape_string($datos['student_email'])."')");
    } else {
        $mensaje = "Error al procesar la solicitud. Inténtalo de nuevo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Darse de baja - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:white;border-radius:16px;max-width:450px;width:100%;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)}
        .header{background:linear-gradient(135deg,#008ba3,#00bcd4);padding:30px;text-align:center;color:white}
        .header h1{font-size:24px;margin-bottom:8px}
        .content{padding:30px}
        .mensaje{padding:16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .mensaje.exito{background:#e8f5e9;color:#27ae60}
        .mensaje.error{background:#ffebee;color:#e74c3c}
        .mensaje.info{background:#e3f2fd;color:#1976d2}
        label{display:block;margin-bottom:6px;color:#666;font-size:14px}
        textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;min-height:100px}
        button{width:100%;padding:14px;background:#e74c3c;color:white;border:none;border-radius:8px;font-size:16px;cursor:pointer;margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px}
        button:hover{background:#c0392b}
        .note{color:#999;font-size:12px;text-align:center;margin-top:16px}
        a{color:#008ba3;text-decoration:none}
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>tuSpeaking</h1>
            <p>Preferencias de comunicación</p>
        </div>
        <div class="content">
            <?php if (!$token || (!$puede_optout && !$exito)): ?>
                <div class="mensaje error">
                    <span class="material-icons">error</span>
                    <span>Enlace inválido o ya no tienes feedbacks pendientes.</span>
                </div>
                <p style="text-align:center;color:#666;">Para darte de baja, primero completa un feedback cuando lo recibas.</p>
            <?php elseif ($exito): ?>
                <div class="mensaje exito">
                    <span class="material-icons">check_circle</span>
                    <span><?=$mensaje?></span>
                </div>
                <p class="note">Gracias por tu tiempo. Si cambias de opinión, contacta con nosotros.</p>
            <?php else: ?>
                <div class="mensaje info">
                    <span class="material-icons">info</span>
                    <span>Si no deseas recibir más solicitudes de feedback, puedes darte de baja aquí.</span>
                </div>
                <form method="POST">
                    <label>¿Por qué deseas darte de baja? (opcional)</label>
                    <textarea name="motivo" placeholder="Tu feedback nos ayuda a mejorar..."></textarea>
                    <button type="submit">
                        <span class="material-icons">unsubscribe</span>
                        Darme de baja
                    </button>
                </form>
                <p class="note">Nota: Seguirás recibiendo comunicaciones importantes sobre tus clases.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
