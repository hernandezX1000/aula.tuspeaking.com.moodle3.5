<?php
/**
 * Alta Prueba de Nivel Multiidioma - tuSpeaking
 * URL: https://aula.tuspeaking.com/app/moodle/empresas/prueba_nivel.php
 * v3 - 2026-02-13: Multiidioma + tests manuales + nivel 0
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

$ROL_ESTUDIANTE = 5;

// Configuración de tests disponibles
$TESTS = [
    'ingles' => [
        'nombre' => 'Inglés',
        'tipo' => 'quiz',
        'curso_id' => 1856,
        'quiz_cmid' => 281964,
        'max_puntos' => 25
    ],
    'ingles_reeval' => [
        'nombre' => 'Inglés (Reevaluación)',
        'tipo' => 'quiz',
        'curso_id' => 2422,
        'quiz_cmid' => 366426,
        'max_puntos' => 25
    ],
    'frances' => [
        'nombre' => 'Francés',
        'tipo' => 'quiz',
        'curso_id' => 1212,
        'quiz_cmid' => 177770,
        'max_puntos' => 35
    ],
    'espanol' => [
        'nombre' => 'Español',
        'tipo' => 'quiz',
        'curso_id' => 1213,
        'quiz_cmid' => 177843,
        'max_puntos' => 35
    ],
    'portugues' => [
        'nombre' => 'Portugués',
        'tipo' => 'quiz',
        'curso_id' => 1214,
        'quiz_cmid' => 177851,
        'max_puntos' => 25
    ],
    'italiano' => [
        'nombre' => 'Italiano',
        'tipo' => 'manual',
        'curso_id' => 2172,
        'tareas' => [
            ['cmid' => 325150, 'nombre' => 'Audio'],
            ['cmid' => 325151, 'nombre' => 'Escritura']
        ]
    ],
    'aleman' => [
        'nombre' => 'Alemán',
        'tipo' => 'manual',
        'curso_id' => 2057,
        'tareas' => [
            ['cmid' => 309058, 'nombre' => 'Audio']
        ]
    ]
];

// Test seleccionado
$test_key = $_GET['test'] ?? $_POST['test_key'] ?? 'ingles';
if (!isset($TESTS[$test_key])) $test_key = 'ingles';
$test = $TESTS[$test_key];

$mensaje = '';
$tipo = '';
$usuario_creado = null;

// Procesar alta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $fecha_limite = $_POST['fecha_limite'] ?? '';
    $enviar_email = isset($_POST['enviar_email']);
    $nivel_cero = isset($_POST['nivel_cero']);
    
    $errores = [];
    
    if (empty($nombre)) $errores[] = 'Nombre obligatorio';
    if (empty($apellidos)) $errores[] = 'Apellidos obligatorios';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido';
    
    if (!$nivel_cero) {
        // Verificar email existente
        $check = $conn->query("SELECT id FROM mdl_user WHERE email='".mysqli_real_escape_string($conn, $email)."' AND deleted=0");
        if ($check->num_rows > 0) {
            $errores[] = 'Ya existe un usuario con este email';
        }
        $check2 = $conn->query("SELECT id FROM mdl_user WHERE username='".mysqli_real_escape_string($conn, $email)."'");
        if ($check2->num_rows > 0) {
            $errores[] = 'Ya existe un usuario con este email como nombre de usuario';
        }
    }
    
    if (empty($errores)) {
        
        if ($nivel_cero) {
            // Registrar nivel 0 sin crear usuario Moodle
            $nombre_esc = mysqli_real_escape_string($conn, $nombre);
            $apellidos_esc = mysqli_real_escape_string($conn, $apellidos);
            $email_esc = mysqli_real_escape_string($conn, $email);
            $test_key_esc = mysqli_real_escape_string($conn, $test_key);
            
            $conn->query("INSERT INTO own_prueba_nivel_cero (nombre, apellidos, email, idioma, fecha_registro) 
                          VALUES ('$nombre_esc', '$apellidos_esc', '$email_esc', '$test_key_esc', NOW())");
            
            $usuario_creado = [
                'username' => $email,
                'password' => '(no aplica)',
                'email' => $email,
                'nombre' => "$nombre $apellidos",
                'fecha_limite' => 'N/A',
                'email_enviado' => false,
                'nivel_cero' => true
            ];
            $tipo = 'success';
            
        } else {
            $username = $email;
            $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
            $password = '';
            for ($i = 0; $i < 8; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $password = ucfirst($password);
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            $nombre_esc = mysqli_real_escape_string($conn, $nombre);
            $apellidos_esc = mysqli_real_escape_string($conn, $apellidos);
            $email_esc = mysqli_real_escape_string($conn, $email);
            
            $conn->query("INSERT INTO mdl_user (username, password, firstname, lastname, email, confirmed, mnethostid, lang, timecreated, timemodified, auth) 
                          VALUES ('$email_esc', '$password_hash', '$nombre_esc', '$apellidos_esc', '$email_esc', 1, 1, 'es', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 'manual')");
            $user_id = $conn->insert_id;
            
            $curso_id = $test['curso_id'];
            $enrol = $conn->query("SELECT id FROM mdl_enrol WHERE courseid=$curso_id AND enrol='manual' AND status=0")->fetch_assoc();
            $enrol_id = $enrol['id'];
            
            $timeend = 0;
            $fecha_limite_texto = 'Sin fecha límite';
            if (!empty($fecha_limite)) {
                $timeend = strtotime($fecha_limite . ' 23:59:59');
                $fecha_limite_texto = date('d/m/Y', $timeend);
            }
            
            $conn->query("INSERT INTO mdl_user_enrolments (enrolid, userid, timestart, timeend, timecreated, timemodified, status) 
                          VALUES ($enrol_id, $user_id, UNIX_TIMESTAMP(), $timeend, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)");
            
            $context = $conn->query("SELECT id FROM mdl_context WHERE contextlevel=50 AND instanceid=$curso_id")->fetch_assoc();
            $context_id = $context['id'];
            $conn->query("INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified) 
                          VALUES ($ROL_ESTUDIANTE, $context_id, $user_id, UNIX_TIMESTAMP())");
            
            // URL del test
            if ($test['tipo'] === 'quiz') {
                $url_test = 'https://aula.tuspeaking.com/app/moodle/mod/quiz/view.php?id=' . $test['quiz_cmid'];
            } else {
                $url_test = 'https://aula.tuspeaking.com/app/moodle/course/view.php?id=' . $curso_id;
            }
            
            $email_enviado = false;
            if ($enviar_email) {
                $idioma_nombre = $test['nombre'];
                $tipo_test = $test['tipo'] === 'manual' ? 'Deberás completar las tareas del curso (audio y/o escritura).' : 'Haz clic en el botón para acceder directamente al test.';
                
                $asunto = "Acceso a tu Prueba de Nivel de $idioma_nombre - tuSpeaking";
                $cuerpo = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
                    <div style='background:#008ba3;color:white;padding:20px;text-align:center'>
                        <h2 style='margin:0'>tuSpeaking</h2>
                        <p style='margin:5px 0 0'>Prueba de Nivel de $idioma_nombre</p>
                    </div>
                    <div style='padding:25px;background:#f9f9f9'>
                        <p>Hola <strong>$nombre</strong>,</p>
                        <p>Te damos la bienvenida a tuSpeaking. Hemos creado tu cuenta para realizar la <strong>Prueba de Nivel de $idioma_nombre</strong>.</p>
                        <p>$tipo_test</p>
                        <div style='background:white;border:1px solid #ddd;padding:20px;margin:20px 0;border-radius:5px'>
                            <h3 style='margin-top:0;color:#008ba3'>Tus credenciales</h3>
                            <p><strong>Email:</strong> $email</p>
                            <p><strong>Contraseña:</strong> $password</p>
                        </div>
                        <div style='text-align:center;margin:25px 0'>
                            <a href='$url_test' style='background:#008ba3;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block'>Acceder a la Prueba de Nivel</a>
                        </div>
                        <p style='font-size:13px;color:#666'>Si el botón no funciona, copia este enlace en tu navegador:<br><a href='$url_test'>$url_test</a></p>
                        ".($fecha_limite_texto != 'Sin fecha límite' ? "
                        <div style='background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:5px;margin-top:20px'>
                            <strong>⏰ Fecha límite:</strong> $fecha_limite_texto
                        </div>" : "")."
                        <p style='margin-top:25px'>¡Mucha suerte!<br>El equipo de tuSpeaking</p>
                    </div>
                </div>";
                
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: tuSpeaking <info@tuspeaking.com>\r\n";
                
                $email_enviado = mail($email, $asunto, $cuerpo, $headers);
            }
            
            $usuario_creado = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'nombre' => "$nombre $apellidos",
                'fecha_limite' => $fecha_limite_texto,
                'email_enviado' => $email_enviado,
                'nivel_cero' => false,
                'url_test' => $url_test
            ];
            $tipo = 'success';
        }
    } else {
        $mensaje = implode('<br>', $errores);
        $tipo = 'error';
    }
}

// Obtener últimas altas del test actual
$curso_id = $test['curso_id'];
$ultimas = $conn->query("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.timecreated,
           ue.timeend as fecha_limite
    FROM mdl_user u
    JOIN mdl_user_enrolments ue ON ue.userid = u.id
    JOIN mdl_enrol e ON e.id = ue.enrolid
    WHERE e.courseid = $curso_id
    ORDER BY u.timecreated DESC
    LIMIT 15
");

// Obtener altas de nivel 0 para este idioma
$nivel_cero_list = [];
$nc_result = $conn->query("SELECT * FROM own_prueba_nivel_cero WHERE idioma='".mysqli_real_escape_string($conn, $test_key)."' ORDER BY fecha_registro DESC LIMIT 10");
if ($nc_result) {
    while ($nc = $nc_result->fetch_assoc()) {
        $nivel_cero_list[] = $nc;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta Prueba de Nivel - tuSpeaking</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #008ba3, #00bcd4); color: white; padding: 20px 25px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .header h1 { margin: 0; font-size: 1.4rem; }
        .header a { color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 5px; font-size: 14px; }
        .card { background: white; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 20px; }
        .card-standalone { border-radius: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #444; }
        input[type="text"], input[type="email"], input[type="date"], select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #008ba3; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
        .btn { padding: 12px 25px; background: #008ba3; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn:hover { background: #007a8f; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-error { background: #fee; border: 1px solid #fcc; color: #c33; }
        .result-box { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .result-box-zero { background: #fff3e0; border: 1px solid #ffcc80; }
        .credentials { background: white; border-radius: 6px; padding: 15px; font-family: monospace; font-size: 13px; }
        .credentials p { margin: 6px 0; }
        .copy-btn { background: #eee; border: 1px solid #ddd; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; margin-left: 8px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; }
        .badge-success { background: #c8e6c9; color: #2e7d32; }
        .badge-warning { background: #fff3e0; color: #e65100; }
        .badge-danger { background: #ffcdd2; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #1565c0; }
        .badge-zero { background: #fff3e0; color: #e65100; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        .section-title { font-size: 1rem; color: #333; margin: 25px 0 10px; padding-bottom: 8px; border-bottom: 2px solid #eee; }
        .help-text { font-size: 11px; color: #888; margin-top: 3px; }
        .test-selector { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .test-btn { padding: 8px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 500; text-decoration: none; color: #333; font-size: 13px; }
        .test-btn:hover { border-color: #008ba3; }
        .test-btn.active { background: #008ba3; color: #fff; border-color: #008ba3; }
        .test-btn .tipo-tag { font-size: 10px; display: block; opacity: 0.7; }
        .nivel-cero-box { background: #fff8e1; border: 2px dashed #ffb300; border-radius: 8px; padding: 15px; margin-top: 15px; }
        .nivel-cero-box label { color: #e65100; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Alta Prueba de Nivel — <?= htmlspecialchars($test['nombre']) ?></h1>
            <div>
                <a href="prueba_nivel_resultados.php?test=<?= $test_key ?>">📊 Resultados</a>
                <a href="admin.php?s=dashboard" style="margin-left:10px">← Panel</a>
            </div>
        </div>
        
        <div class="card">
            <!-- Selector de idioma/test -->
            <div class="test-selector">
                <?php foreach ($TESTS as $key => $t): ?>
                <a href="?test=<?= $key ?>" class="test-btn <?= $key === $test_key ? 'active' : '' ?>">
                    <?= $t['nombre'] ?>
                    <span class="tipo-tag"><?= $t['tipo'] === 'quiz' ? '📝 Auto' : '🎤 Manual' ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($test['tipo'] === 'manual'): ?>
            <div style="background:#e3f2fd;border:1px solid #90caf9;padding:12px;border-radius:6px;margin-bottom:20px;font-size:13px">
                <strong>ℹ️ Test manual:</strong> El alumno deberá completar las siguientes tareas: 
                <?php echo implode(', ', array_map(function($t){ return $t['nombre']; }, $test['tareas'])); ?>.
                Los resultados serán revisados por el equipo de tuSpeaking.
            </div>
            <?php endif; ?>
            
            <?php if ($mensaje && $tipo === 'error'): ?>
                <div class="alert alert-error"><?= $mensaje ?></div>
            <?php endif; ?>
            
            <?php if ($usuario_creado): ?>
                <?php if ($usuario_creado['nivel_cero'] ?? false): ?>
                <div class="result-box result-box-zero">
                    <h3 style="margin:0 0 15px;color:#e65100">⚠ Nivel 0 registrado</h3>
                    <div class="credentials">
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario_creado['nombre']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($usuario_creado['email']) ?></p>
                        <p><strong>Idioma:</strong> <?= htmlspecialchars($test['nombre']) ?></p>
                        <p><strong>Estado:</strong> <span class="badge badge-zero">Nivel 0 — No requiere test</span></p>
                    </div>
                </div>
                <?php else: ?>
                <div class="result-box">
                    <h3 style="margin:0 0 15px;color:#2e7d32">✓ Usuario creado correctamente</h3>
                    <div class="credentials">
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario_creado['nombre']) ?></p>
                        <p><strong>Email/Usuario:</strong> <?= htmlspecialchars($usuario_creado['email']) ?> <button class="copy-btn" onclick="copiar('<?= $usuario_creado['email'] ?>')">Copiar</button></p>
                        <p><strong>Contraseña:</strong> <?= $usuario_creado['password'] ?> <button class="copy-btn" onclick="copiar('<?= $usuario_creado['password'] ?>')">Copiar</button></p>
                        <p><strong>Idioma:</strong> <?= htmlspecialchars($test['nombre']) ?></p>
                        <p><strong>Fecha límite:</strong> <?= $usuario_creado['fecha_limite'] ?></p>
                        <p><strong>Email:</strong> <?= $usuario_creado['email_enviado'] ? '<span class="badge badge-success">✓ Enviado</span>' : '<span class="badge badge-warning">No enviado</span>' ?></p>
                        <p style="margin-top:15px"><strong>URL Test:</strong> <a href="<?= $usuario_creado['url_test'] ?>" target="_blank">Abrir prueba de nivel</a></p>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="test_key" value="<?= $test_key ?>">
                <div class="grid">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required placeholder="Ej: María">
                    </div>
                    <div class="form-group">
                        <label>Apellidos *</label>
                        <input type="text" name="apellidos" required placeholder="Ej: García López">
                    </div>
                    <div class="form-group">
                        <label>Email * <small style="color:#888">(será el usuario de acceso)</small></label>
                        <input type="email" name="email" required placeholder="usuario@empresa.com">
                    </div>
                    <div class="form-group">
                        <label>Fecha límite</label>
                        <input type="date" name="fecha_limite" min="<?= date('Y-m-d') ?>">
                        <p class="help-text">Opcional. Después de esta fecha no podrá acceder al curso.</p>
                    </div>
                </div>
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="enviar_email" name="enviar_email" checked>
                    <label for="enviar_email" style="margin:0;font-weight:normal">Enviar email con credenciales y enlace al test</label>
                </div>
                
                <div class="nivel-cero-box">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="nivel_cero" name="nivel_cero" onchange="toggleNivelCero()">
                        <label for="nivel_cero" style="margin:0">El alumno indica que su nivel es 0 — no necesita hacer la prueba</label>
                    </div>
                    <p class="help-text" style="margin-top:8px">Si marcas esta opción, se registrará al alumno como nivel 0 sin crear cuenta ni enviar test.</p>
                </div>
                
                <div style="margin-top:20px" id="btn-crear">
                    <button type="submit" name="crear_usuario" class="btn">Crear Usuario y Matricular</button>
                </div>
                <div style="margin-top:20px;display:none" id="btn-nivel-cero">
                    <button type="submit" name="crear_usuario" class="btn" style="background:#ff9800">Registrar como Nivel 0</button>
                </div>
            </form>
            
            <h3 class="section-title">Últimas altas — <?= htmlspecialchars($test['nombre']) ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Fecha límite</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $ultimas->fetch_assoc()): 
                        $vencido = $u['fecha_limite'] && $u['fecha_limite'] < time();
                    ?>
                    <tr>
                        <td><?= date('d/m/Y', $u['timecreated']) ?></td>
                        <td><?= htmlspecialchars($u['firstname'] . ' ' . $u['lastname']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['fecha_limite']): ?>
                                <span class="badge <?= $vencido ? 'badge-danger' : 'badge-warning' ?>">
                                    <?= date('d/m/Y', $u['fecha_limite']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#999">Sin límite</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <?php if (!empty($nivel_cero_list)): ?>
            <h3 class="section-title">⚠ Alumnos Nivel 0 — <?= htmlspecialchars($test['nombre']) ?></h3>
            <table>
                <thead><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($nivel_cero_list as $nc): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($nc['fecha_registro'])) ?></td>
                        <td><?= htmlspecialchars($nc['nombre'] . ' ' . $nc['apellidos']) ?></td>
                        <td><?= htmlspecialchars($nc['email']) ?></td>
                        <td><span class="badge badge-zero">Nivel 0</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function copiar(t) { navigator.clipboard.writeText(t).then(() => alert('Copiado: ' + t)); }
        function toggleNivelCero() {
            var checked = document.getElementById('nivel_cero').checked;
            document.getElementById('btn-crear').style.display = checked ? 'none' : 'block';
            document.getElementById('btn-nivel-cero').style.display = checked ? 'block' : 'none';
            document.getElementById('enviar_email').disabled = checked;
        }
    </script>
</body>
</html>
