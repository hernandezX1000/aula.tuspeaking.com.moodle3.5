<?php
/**
 * Resultados Prueba de Nivel Multiidioma - tuSpeaking
 * v4 - 2026-02-13: Recordatorios individuales + ampliar plazo + mensaje personalizado
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

$TESTS = [
    'ingles' => ['nombre' => 'Inglés', 'tipo' => 'quiz', 'curso_id' => 1856, 'quiz_cmid' => 281964, 'max_puntos' => 25],
    'ingles_reeval' => ['nombre' => 'Inglés (Reevaluación)', 'tipo' => 'quiz', 'curso_id' => 2422, 'quiz_cmid' => 366426, 'max_puntos' => 25],
    'frances' => ['nombre' => 'Francés', 'tipo' => 'quiz', 'curso_id' => 1212, 'quiz_cmid' => 177770, 'max_puntos' => 35],
    'espanol' => ['nombre' => 'Español', 'tipo' => 'quiz', 'curso_id' => 1213, 'quiz_cmid' => 177843, 'max_puntos' => 35],
    'portugues' => ['nombre' => 'Portugués', 'tipo' => 'quiz', 'curso_id' => 1214, 'quiz_cmid' => 177851, 'max_puntos' => 25],
    'italiano' => ['nombre' => 'Italiano', 'tipo' => 'manual', 'curso_id' => 2172,
        'tareas' => [['cmid' => 325150, 'nombre' => 'Audio', 'assign_id' => 15261], ['cmid' => 325151, 'nombre' => 'Escritura', 'assign_id' => 15262]]],
    'aleman' => ['nombre' => 'Alemán', 'tipo' => 'manual', 'curso_id' => 2057,
        'tareas' => [['cmid' => 309058, 'nombre' => 'Audio', 'assign_id' => 14476]]]
];

$test_key = $_GET['test'] ?? 'ingles';
if (!isset($TESTS[$test_key])) $test_key = 'ingles';
$test = $TESTS[$test_key];

// ============ AJAX: Enviar recordatorio ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_recordatorio'])) {
    header('Content-Type: application/json');
    $email_alumno = trim($_POST['email'] ?? '');
    $nombre_alumno = trim($_POST['nombre'] ?? '');
    $mensaje_extra = trim($_POST['mensaje_extra'] ?? '');
    $nueva_fecha = trim($_POST['nueva_fecha'] ?? '');
    $test_k = $_POST['test_key'] ?? 'ingles';
    if (!isset($TESTS[$test_k])) { echo json_encode(['error' => 'Test no válido']); exit; }
    $t = $TESTS[$test_k];
    
    if (empty($email_alumno)) { echo json_encode(['error' => 'Email obligatorio']); exit; }
    
    // Ampliar fecha límite si se indicó
    if (!empty($nueva_fecha)) {
        $new_timeend = strtotime($nueva_fecha . ' 23:59:59');
        if ($new_timeend) {
            $userid_r = $conn->query("SELECT id FROM mdl_user WHERE email='".mysqli_real_escape_string($conn, $email_alumno)."' AND deleted=0 LIMIT 1");
            if ($userid_r && $u = $userid_r->fetch_assoc()) {
                $uid = $u['id'];
                $curso_id = $t['curso_id'];
                $conn->query("UPDATE mdl_user_enrolments ue 
                    JOIN mdl_enrol e ON e.id = ue.enrolid 
                    SET ue.timeend = $new_timeend, ue.timemodified = UNIX_TIMESTAMP() 
                    WHERE ue.userid = $uid AND e.courseid = $curso_id");
            }
        }
    }
    
    // URL del test
    if ($t['tipo'] === 'quiz') {
        $url_test = 'https://aula.tuspeaking.com/app/moodle/mod/quiz/view.php?id=' . $t['quiz_cmid'];
    } else {
        $url_test = 'https://aula.tuspeaking.com/app/moodle/course/view.php?id=' . $t['curso_id'];
    }
    
    $idioma_nombre = $t['nombre'];
    $fecha_texto = '';
    if (!empty($nueva_fecha)) {
        $fecha_texto = "<div style='background:#e8f5e9;border:1px solid #4caf50;padding:15px;border-radius:5px;margin:15px 0'>
            <strong>📅 Nueva fecha límite:</strong> " . date('d/m/Y', strtotime($nueva_fecha)) . "
        </div>";
    }
    $mensaje_bloque = '';
    if (!empty($mensaje_extra)) {
        $mensaje_bloque = "<div style='background:#f5f5f5;border-left:4px solid #008ba3;padding:15px;margin:15px 0;border-radius:0 5px 5px 0'>
            <strong>Mensaje del equipo:</strong><br>" . nl2br(htmlspecialchars($mensaje_extra)) . "
        </div>";
    }
    
    $asunto = "Recordatorio: Prueba de Nivel de $idioma_nombre pendiente - tuSpeaking";
    $url_recuperar = 'https://aula.tuspeaking.com/app/moodle/login/forgot_password.php';
    $url_login = 'https://aula.tuspeaking.com/app/moodle/login/index.php';
    $cuerpo = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
        <div style='background:#008ba3;color:white;padding:20px;text-align:center'>
            <h2 style='margin:0'>tuSpeaking</h2>
            <p style='margin:5px 0 0'>Recordatorio — Prueba de Nivel de $idioma_nombre</p>
        </div>
        <div style='padding:25px;background:#f9f9f9'>
            <p>Hola <strong>$nombre_alumno</strong>,</p>
            <p>Te recordamos que tienes pendiente completar tu <strong>Prueba de Nivel de $idioma_nombre</strong>.</p>
            $mensaje_bloque
            $fecha_texto
            <div style='background:white;border:1px solid #ddd;padding:20px;margin:20px 0;border-radius:5px'>
                <h3 style='margin-top:0;color:#008ba3'>Tus datos de acceso</h3>
                <p><strong>Usuario:</strong> $email_alumno</p>
                <p><strong>Enlace de acceso:</strong> <a href='$url_login'>$url_login</a></p>
                <p style='font-size:13px;color:#888;margin-top:10px'>¿No recuerdas tu contraseña? <a href='$url_recuperar' style='color:#008ba3;font-weight:bold'>Recupérala aquí</a></p>
            </div>
            <div style='text-align:center;margin:25px 0'>
                <a href='$url_test' style='background:#008ba3;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block'>Acceder a la Prueba de Nivel</a>
            </div>
            <p style='font-size:13px;color:#666'>Si el botón no funciona, copia este enlace:<br><a href='$url_test'>$url_test</a></p>
            <p style='margin-top:25px'>¡Mucho ánimo!<br>El equipo de tuSpeaking</p>
        </div>
    </div>";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: tuSpeaking <info@tuspeaking.com>\r\n";
    
    $enviado = mail($email_alumno, $asunto, $cuerpo, $headers);
    
    // Registrar recordatorio
    if ($enviado) {
        $email_esc = mysqli_real_escape_string($conn, $email_alumno);
        $test_esc = mysqli_real_escape_string($conn, $test_k);
        $msg_esc = mysqli_real_escape_string($conn, $mensaje_extra);
        $fecha_esc = mysqli_real_escape_string($conn, $nueva_fecha);
        $conn->query("INSERT INTO own_prueba_nivel_recordatorios (email, idioma, mensaje, nueva_fecha_limite, fecha_envio) VALUES ('$email_esc', '$test_esc', '$msg_esc', " . ($nueva_fecha ? "'$fecha_esc'" : "NULL") . ", NOW())");
    }
    
    echo json_encode(['ok' => $enviado, 'fecha' => date('d/m/Y H:i')]);
    exit;
}

// Escala de niveles
function getNivelCambridge($p, $max = 25) {
    $p = floatval($p);
    if ($max == 25) {
        if ($p >= 23) return ['nivel' => 'C2 Proficiency', 'color' => '#1a237e', 'bg' => '#e8eaf6'];
        if ($p >= 22) return ['nivel' => 'C1 Advanced / C2', 'color' => '#283593', 'bg' => '#ede7f6'];
        if ($p >= 20) return ['nivel' => 'B2 First / C1', 'color' => '#303f9f', 'bg' => '#e8eaf6'];
        if ($p >= 18) return ['nivel' => 'B2 First', 'color' => '#1976d2', 'bg' => '#e3f2fd'];
        if ($p >= 16) return ['nivel' => 'B1 / B2 First', 'color' => '#0288d1', 'bg' => '#e1f5fe'];
        if ($p >= 13) return ['nivel' => 'B1 Preliminary', 'color' => '#0097a7', 'bg' => '#e0f7fa'];
        if ($p >= 11) return ['nivel' => 'A2 / B1', 'color' => '#00897b', 'bg' => '#e0f2f1'];
        if ($p >= 6)  return ['nivel' => 'A2 Key', 'color' => '#43a047', 'bg' => '#e8f5e9'];
        return ['nivel' => 'Por debajo de A2', 'color' => '#757575', 'bg' => '#fafafa'];
    } else {
        if ($p >= 32) return ['nivel' => 'C2', 'color' => '#1a237e', 'bg' => '#e8eaf6'];
        if ($p >= 31) return ['nivel' => 'C1 / C2', 'color' => '#283593', 'bg' => '#ede7f6'];
        if ($p >= 28) return ['nivel' => 'B2 / C1', 'color' => '#303f9f', 'bg' => '#e8eaf6'];
        if ($p >= 25) return ['nivel' => 'B2', 'color' => '#1976d2', 'bg' => '#e3f2fd'];
        if ($p >= 22) return ['nivel' => 'B1 / B2', 'color' => '#0288d1', 'bg' => '#e1f5fe'];
        if ($p >= 18) return ['nivel' => 'B1', 'color' => '#0097a7', 'bg' => '#e0f7fa'];
        if ($p >= 15) return ['nivel' => 'A2 / B1', 'color' => '#00897b', 'bg' => '#e0f2f1'];
        if ($p >= 8)  return ['nivel' => 'A2', 'color' => '#43a047', 'bg' => '#e8f5e9'];
        return ['nivel' => 'Por debajo de A2', 'color' => '#757575', 'bg' => '#fafafa'];
    }
}

$filtro_buscar = trim($_GET['buscar'] ?? '');
$filtro_nivel = $_GET['nivel'] ?? '';
$orden = $_GET['orden'] ?? 'fecha_desc';

// Cargar recordatorios enviados para este idioma
$recordatorios = [];
$rec_r = $conn->query("SELECT email, MAX(fecha_envio) as ultimo_envio, COUNT(*) as total_envios FROM own_prueba_nivel_recordatorios WHERE idioma='".mysqli_real_escape_string($conn, $test_key)."' GROUP BY email");
if ($rec_r) while ($rc = $rec_r->fetch_assoc()) $recordatorios[$rc['email']] = $rc;

$resultados = [];
$stats = ['total' => 0, 'suma' => 0, 'niveles' => [], 'pendientes_quiz' => 0];

if ($test['tipo'] === 'quiz') {
    $cm = $conn->query("SELECT instance FROM mdl_course_modules WHERE id={$test['quiz_cmid']}")->fetch_assoc();
    $quiz_id = $cm['instance'];
    $max_puntos = $test['max_puntos'];
    
    // Completados
    $sql_where = "WHERE qa.quiz = $quiz_id AND qa.state = 'finished'";
    if (!empty($filtro_buscar)) {
        $buscar = mysqli_real_escape_string($conn, $filtro_buscar);
        $sql_where .= " AND (LOWER(u.firstname) LIKE '%$buscar%' OR LOWER(u.lastname) LIKE '%$buscar%' OR LOWER(u.email) LIKE '%$buscar%')";
    }
    $sql_order = "ORDER BY qa.timefinish DESC";
    if ($orden === 'puntuacion_desc') $sql_order = "ORDER BY qa.sumgrades DESC";
    if ($orden === 'puntuacion_asc') $sql_order = "ORDER BY qa.sumgrades ASC";
    if ($orden === 'nombre') $sql_order = "ORDER BY u.lastname, u.firstname";
    
    $sql = "SELECT qa.id as attempt_id, qa.userid, qa.sumgrades as puntuacion, qa.timefinish,
            u.firstname, u.lastname, u.email
            FROM mdl_quiz_attempts qa JOIN mdl_user u ON u.id = qa.userid
            $sql_where $sql_order";
    $raw = $conn->query($sql);
    $emails_completados = [];
    while ($r = $raw->fetch_assoc()) {
        $r['nivel_info'] = getNivelCambridge($r['puntuacion'], $max_puntos);
        if (!empty($filtro_nivel) && $filtro_nivel !== 'pendiente' && strpos($r['nivel_info']['nivel'], $filtro_nivel) === false) continue;
        if ($filtro_nivel !== 'pendiente') {
            $resultados[] = $r;
        }
        $emails_completados[$r['email']] = true;
        $stats['total']++;
        $stats['suma'] += $r['puntuacion'];
        $n = $r['nivel_info']['nivel'];
        if (!isset($stats['niveles'][$n])) $stats['niveles'][$n] = 0;
        $stats['niveles'][$n]++;
    }
    $stats['promedio'] = $stats['total'] > 0 ? round($stats['suma'] / $stats['total'], 1) : 0;
    
    // Pendientes (matriculados que no han hecho el quiz)
    $curso_id = $test['curso_id'];
    $buscar_sql = '';
    if (!empty($filtro_buscar)) {
        $buscar = mysqli_real_escape_string($conn, $filtro_buscar);
        $buscar_sql = "AND (LOWER(u.firstname) LIKE '%$buscar%' OR LOWER(u.lastname) LIKE '%$buscar%' OR LOWER(u.email) LIKE '%$buscar%')";
    }
    $pend_sql = "SELECT u.id as userid, u.firstname, u.lastname, u.email, u.lastaccess, u.lastlogin, ue.timecreated as fecha_matricula, ue.timeend as fecha_limite
        FROM mdl_user u
        JOIN mdl_user_enrolments ue ON ue.userid = u.id
        JOIN mdl_enrol e ON e.id = ue.enrolid
        WHERE e.courseid = $curso_id AND u.deleted = 0 $buscar_sql
        ORDER BY ue.timecreated DESC";
    $pend_raw = $conn->query($pend_sql);
    $pendientes = [];
    while ($p = $pend_raw->fetch_assoc()) {
        if (!isset($emails_completados[$p['email']])) {
            $p['recordatorio'] = $recordatorios[$p['email']] ?? null;
            $pendientes[] = $p;
            $stats['pendientes_quiz']++;
        }
    }

} else {
    $curso_id = $test['curso_id'];
    $assign_ids = array_column($test['tareas'], 'assign_id');
    
    $sql = "SELECT u.id as userid, u.firstname, u.lastname, u.email, u.lastaccess, u.lastlogin, ue.timecreated as fecha_matricula, ue.timeend as fecha_limite
            FROM mdl_user u
            JOIN mdl_user_enrolments ue ON ue.userid = u.id
            JOIN mdl_enrol e ON e.id = ue.enrolid
            WHERE e.courseid = $curso_id AND u.deleted = 0
            ORDER BY ue.timecreated DESC";
    if (!empty($filtro_buscar)) {
        $buscar = mysqli_real_escape_string($conn, $filtro_buscar);
        $sql = "SELECT u.id as userid, u.firstname, u.lastname, u.email, u.lastaccess, u.lastlogin, ue.timecreated as fecha_matricula, ue.timeend as fecha_limite
                FROM mdl_user u
                JOIN mdl_user_enrolments ue ON ue.userid = u.id
                JOIN mdl_enrol e ON e.id = ue.enrolid
                WHERE e.courseid = $curso_id AND u.deleted = 0
                AND (LOWER(u.firstname) LIKE '%$buscar%' OR LOWER(u.lastname) LIKE '%$buscar%' OR LOWER(u.email) LIKE '%$buscar%')
                ORDER BY ue.timecreated DESC";
    }
    $raw = $conn->query($sql);
    while ($r = $raw->fetch_assoc()) {
        $r['entregas'] = [];
        $todas_entregadas = true;
        foreach ($assign_ids as $aid) {
            $sub = $conn->query("SELECT id, timemodified, status FROM mdl_assign_submission WHERE assignment=$aid AND userid={$r['userid']} AND status='submitted' ORDER BY timemodified DESC LIMIT 1");
            if ($sub && $s = $sub->fetch_assoc()) {
                $r['entregas'][$aid] = $s;
            } else {
                $todas_entregadas = false;
                $r['entregas'][$aid] = null;
            }
        }
        $r['todas_entregadas'] = $todas_entregadas;
        $r['alguna_entregada'] = count(array_filter($r['entregas'])) > 0;
        $r['recordatorio'] = $recordatorios[$r['email']] ?? null;
        if ($filtro_nivel === 'entregado' && !$todas_entregadas) continue;
        if ($filtro_nivel === 'pendiente' && $todas_entregadas) continue;
        $resultados[] = $r;
        $stats['total']++;
    }
}

$nivel_cero_list = [];
$nc_result = $conn->query("SELECT * FROM own_prueba_nivel_cero WHERE idioma='".mysqli_real_escape_string($conn, $test_key)."' ORDER BY fecha_registro DESC");
if ($nc_result) while ($nc = $nc_result->fetch_assoc()) $nivel_cero_list[] = $nc;

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=resultados_' . $test_key . '_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    if ($test['tipo'] === 'quiz') {
        fputcsv($output, ['Fecha', 'Nombre', 'Apellidos', 'Email', 'Puntuación', 'Nivel'], ';');
        foreach ($resultados as $r) {
            fputcsv($output, [date('d/m/Y H:i', $r['timefinish']), $r['firstname'], $r['lastname'], $r['email'], round($r['puntuacion'], 0), $r['nivel_info']['nivel']], ';');
        }
    } else {
        fputcsv($output, ['Fecha Matrícula', 'Nombre', 'Apellidos', 'Email', 'Estado'], ';');
        foreach ($resultados as $r) {
            $estado = $r['todas_entregadas'] ? 'Entregado' : ($r['alguna_entregada'] ? 'Parcial' : 'Pendiente');
            fputcsv($output, [date('d/m/Y', $r['fecha_matricula']), $r['firstname'], $r['lastname'], $r['email'], $estado], ';');
        }
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados Prueba de Nivel — <?= htmlspecialchars($test['nombre']) ?> - tuSpeaking</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #008ba3, #00bcd4); color: white; padding: 20px 25px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .header h1 { margin: 0; font-size: 1.4rem; }
        .header a { color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 5px; font-size: 14px; margin-left: 10px; }
        .card { background: white; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; padding: 20px; border-bottom: 1px solid #eee; }
        .stat-box { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .stat-box .number { font-size: 2rem; font-weight: 700; color: #008ba3; }
        .stat-box .label { font-size: 0.85rem; color: #666; }
        .filters { padding: 20px; border-bottom: 1px solid #eee; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filters input, .filters select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .filters input[type="text"] { width: 250px; }
        .btn { padding: 10px 20px; background: #008ba3; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #007a8f; }
        .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 4px; }
        .btn-secondary { background: #6c757d; }
        .btn-warning { background: #ff9800; }
        .btn-export { background: #28a745; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.85rem; }
        tr:hover { background: #f8f9fa; }
        .nivel-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .puntuacion { font-size: 1.3rem; font-weight: 700; }
        .empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .test-selector { display: flex; gap: 8px; flex-wrap: wrap; padding: 20px; border-bottom: 1px solid #eee; }
        .test-btn { padding: 8px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 500; text-decoration: none; color: #333; font-size: 13px; }
        .test-btn:hover { border-color: #008ba3; }
        .test-btn.active { background: #008ba3; color: #fff; border-color: #008ba3; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; }
        .badge-success { background: #c8e6c9; color: #2e7d32; }
        .badge-warning { background: #fff3e0; color: #e65100; }
        .badge-danger { background: #ffcdd2; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #1565c0; }
        .badge-zero { background: #fff3e0; color: #e65100; font-weight: 600; }
        .section-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-top: 20px; }
        .escala-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .escala-item { padding: 10px 15px; border-radius: 6px; display: flex; justify-content: space-between; }
        /* Modal */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center; }
        .modal-overlay.active { display:flex; }
        .modal { background:white; border-radius:12px; padding:25px; width:90%; max-width:500px; box-shadow:0 10px 40px rgba(0,0,0,0.3); }
        .modal h3 { margin:0 0 20px; color:#333; }
        .modal .form-group { margin-bottom:15px; }
        .modal label { display:block; margin-bottom:5px; font-weight:500; color:#444; font-size:14px; }
        .modal input, .modal textarea { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        .modal textarea { resize:vertical; }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
        .rec-info { font-size:11px; color:#888; }
        .rec-sent { font-size:11px; color:#2e7d32; font-weight:500; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Resultados — <?= htmlspecialchars($test['nombre']) ?></h1>
            <div>
                <a href="prueba_nivel.php?test=<?= $test_key ?>">+ Nueva Alta</a>
                <a href="?test=<?= $test_key ?>&export=csv&<?= http_build_query(array_diff_key($_GET, ['export'=>1])) ?>">📥 CSV</a>
                <a href="admin.php?s=dashboard">← Panel</a>
            </div>
        </div>
        
        <div class="card">
            <div class="test-selector">
                <?php foreach ($TESTS as $key => $t): ?>
                <a href="?test=<?= $key ?>" class="test-btn <?= $key === $test_key ? 'active' : '' ?>">
                    <?= $t['nombre'] ?>
                    <small style="opacity:0.7;display:block;font-size:10px"><?= $t['tipo'] === 'quiz' ? '📝 Auto' : '🎤 Manual' ?></small>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div class="stats">
                <?php if ($test['tipo'] === 'quiz'): ?>
                <div class="stat-box">
                    <div class="number"><?= $stats['total'] ?></div>
                    <div class="label">Completadas</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color:#e65100"><?= $stats['pendientes_quiz'] ?></div>
                    <div class="label">Pendientes</div>
                </div>
                <div class="stat-box">
                    <div class="number"><?= $stats['promedio'] ?></div>
                    <div class="label">Puntuación media</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="font-size:1rem"><?= getNivelCambridge($stats['promedio'], $test['max_puntos'])['nivel'] ?></div>
                    <div class="label">Nivel medio</div>
                </div>
                <?php else: ?>
                <div class="stat-box">
                    <div class="number"><?= $stats['total'] ?></div>
                    <div class="label">Matriculados</div>
                </div>
                <div class="stat-box">
                    <div class="number"><?= count(array_filter($resultados, function($r){ return $r['todas_entregadas']; })) ?></div>
                    <div class="label">Entregados</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color:#e65100"><?= count(array_filter($resultados, function($r){ return !$r['todas_entregadas']; })) ?></div>
                    <div class="label">Pendientes</div>
                </div>
                <?php endif; ?>
            </div>
            
            <form class="filters" method="GET">
                <input type="hidden" name="test" value="<?= $test_key ?>">
                <input type="text" name="buscar" placeholder="🔍 Buscar nombre o email..." value="<?= htmlspecialchars($filtro_buscar) ?>">
                <?php if ($test['tipo'] === 'quiz'): ?>
                <select name="nivel">
                    <option value="">Todos los niveles</option>
                    <option value="pendiente" <?= $filtro_nivel === 'pendiente' ? 'selected' : '' ?>>⏳ Solo pendientes</option>
                    <option value="A2" <?= $filtro_nivel === 'A2' ? 'selected' : '' ?>>A2</option>
                    <option value="B1" <?= $filtro_nivel === 'B1' ? 'selected' : '' ?>>B1</option>
                    <option value="B2" <?= $filtro_nivel === 'B2' ? 'selected' : '' ?>>B2</option>
                    <option value="C1" <?= $filtro_nivel === 'C1' ? 'selected' : '' ?>>C1</option>
                    <option value="C2" <?= $filtro_nivel === 'C2' ? 'selected' : '' ?>>C2</option>
                </select>
                <select name="orden">
                    <option value="fecha_desc" <?= $orden === 'fecha_desc' ? 'selected' : '' ?>>Más recientes</option>
                    <option value="puntuacion_desc" <?= $orden === 'puntuacion_desc' ? 'selected' : '' ?>>Mayor puntuación</option>
                    <option value="puntuacion_asc" <?= $orden === 'puntuacion_asc' ? 'selected' : '' ?>>Menor puntuación</option>
                    <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre A-Z</option>
                </select>
                <?php else: ?>
                <select name="nivel">
                    <option value="">Todos</option>
                    <option value="entregado" <?= $filtro_nivel === 'entregado' ? 'selected' : '' ?>>✅ Entregados</option>
                    <option value="pendiente" <?= $filtro_nivel === 'pendiente' ? 'selected' : '' ?>>⏳ Pendientes</option>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn">Filtrar</button>
                <?php if (!empty($filtro_buscar) || !empty($filtro_nivel)): ?>
                    <a href="?test=<?= $test_key ?>" class="btn btn-secondary">Limpiar</a>
                <?php endif; ?>
            </form>
            
            <?php if ($test['tipo'] === 'quiz' && $filtro_nivel !== 'pendiente'): ?>
                <?php if (empty($resultados)): ?>
                    <div class="empty-state"><p style="font-size:3rem">🔭</p><p><strong>No hay resultados</strong></p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th style="text-align:center">Puntuación</th><th>Nivel</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($resultados as $r): ?>
                        <tr>
                            <td><?= date('d/m/Y', $r['timefinish']) ?><br><small style="color:#999"><?= date('H:i', $r['timefinish']) ?></small></td>
                            <td><strong><?= htmlspecialchars($r['firstname'] . ' ' . $r['lastname']) ?></strong></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td style="text-align:center"><span class="puntuacion"><?= round($r['puntuacion'], 0) ?></span><span style="color:#999">/<?= $test['max_puntos'] ?></span></td>
                            <td><span class="nivel-badge" style="background:<?= $r['nivel_info']['bg'] ?>;color:<?= $r['nivel_info']['color'] ?>"><?= $r['nivel_info']['nivel'] ?></span></td>
                            <td><a href="https://aula.tuspeaking.com/app/moodle/mod/quiz/review.php?attempt=<?= $r['attempt_id'] ?>" target="_blank" style="color:#008ba3">Ver detalle</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php elseif ($test['tipo'] === 'quiz' && $filtro_nivel === 'pendiente'): ?>
                <!-- Solo mostrar pendientes -->
            <?php elseif ($test['tipo'] === 'manual'): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th><th>Nombre</th><th>Email</th><th>Último acceso</th>
                            <?php foreach ($test['tareas'] as $tarea): ?>
                            <th style="text-align:center"><?= $tarea['nombre'] ?></th>
                            <?php endforeach; ?>
                            <th>Estado</th><th>Recordatorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $r): 
                            $nunca_entro = empty($r['lastaccess']) || $r['lastaccess'] == 0;
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', $r['fecha_matricula']) ?></td>
                            <td><strong><?= htmlspecialchars($r['firstname'] . ' ' . $r['lastname']) ?></strong></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td>
                                <?php if ($nunca_entro): ?>
                                    <span class="badge badge-danger">❌ Nunca</span>
                                <?php else: ?>
                                    <span class="badge badge-info">🕐 <?= date('d/m/Y', $r['lastaccess']) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($test['tareas'] as $tarea): 
                                $entrega = $r['entregas'][$tarea['assign_id']] ?? null;
                            ?>
                            <td style="text-align:center">
                                <?php if ($entrega): ?>
                                    <span class="badge badge-success">✅ <?= date('d/m', $entrega['timemodified']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-danger">⏳</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($r['todas_entregadas']): ?>
                                    <span class="badge badge-success">Pendiente revisión</span>
                                <?php elseif ($r['alguna_entregada']): ?>
                                    <span class="badge badge-warning">Parcial</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">No entregado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$r['todas_entregadas']): ?>
                                    <button class="btn btn-warning btn-sm" onclick="abrirModal('<?= htmlspecialchars($r['email']) ?>', '<?= htmlspecialchars($r['firstname'] . ' ' . $r['lastname']) ?>', '<?= $r['fecha_limite'] ? date('Y-m-d', $r['fecha_limite']) : '' ?>', <?= $nunca_entro ? 'true' : 'false' ?>)">📧 Recordar</button>
                                    <?php if ($r['recordatorio']): ?>
                                    <br><span class="rec-sent">✓ Enviado <?= date('d/m', strtotime($r['recordatorio']['ultimo_envio'])) ?> (<?= $r['recordatorio']['total_envios'] ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#999;font-size:12px">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php if ($test['tipo'] === 'quiz' && !empty($pendientes) && ($filtro_nivel === '' || $filtro_nivel === 'pendiente')): ?>
            <div style="padding:20px;border-top:2px solid #ff9800">
                <h3 style="color:#e65100;margin:0 0 15px">⏳ Pendientes de completar (<?= count($pendientes) ?>)</h3>
                <table>
                    <thead><tr><th>Matriculado</th><th>Nombre</th><th>Email</th><th>Último acceso</th><th>Fecha límite</th><th>Recordatorio</th></tr></thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): 
                            $vencido = $p['fecha_limite'] && $p['fecha_limite'] < time();
                            $nunca_entro = empty($p['lastaccess']) || $p['lastaccess'] == 0;
                        ?>
                        <tr<?= $vencido ? ' style="background:#fff5f5"' : '' ?>>
                            <td><?= date('d/m/Y', $p['fecha_matricula']) ?></td>
                            <td><strong><?= htmlspecialchars($p['firstname'] . ' ' . $p['lastname']) ?></strong></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td>
                                <?php if ($nunca_entro): ?>
                                    <span class="badge badge-danger">❌ Nunca ha entrado</span>
                                <?php else: ?>
                                    <span class="badge badge-info">🕐 <?= date('d/m/Y H:i', $p['lastaccess']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['fecha_limite'] && $p['fecha_limite'] > 0): ?>
                                    <span class="badge <?= $vencido ? 'badge-danger' : 'badge-warning' ?>"><?= date('d/m/Y', $p['fecha_limite']) ?></span>
                                <?php else: ?>
                                    <span style="color:#999">Sin límite</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="abrirModal('<?= htmlspecialchars($p['email']) ?>', '<?= htmlspecialchars($p['firstname'] . ' ' . $p['lastname']) ?>', '<?= $p['fecha_limite'] && $p['fecha_limite'] > 0 ? date('Y-m-d', $p['fecha_limite']) : '' ?>', <?= $nunca_entro ? 'true' : 'false' ?>)">📧 Recordar</button>
                                <?php if ($p['recordatorio']): ?>
                                <br><span class="rec-sent">✓ <?= date('d/m H:i', strtotime($p['recordatorio']['ultimo_envio'])) ?> (<?= $p['recordatorio']['total_envios'] ?>x)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($nivel_cero_list)): ?>
        <div class="section-card">
            <h3>⚠ Alumnos Nivel 0 — <?= htmlspecialchars($test['nombre']) ?> (<?= count($nivel_cero_list) ?>)</h3>
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
        </div>
        <?php endif; ?>
        
        <?php if ($test['tipo'] === 'quiz'): ?>
        <div class="section-card">
            <h3>📋 Escala de Niveles — <?= htmlspecialchars($test['nombre']) ?> (0-<?= $test['max_puntos'] ?>)</h3>
            <div class="escala-grid">
                <?php if ($test['max_puntos'] == 25): ?>
                <div class="escala-item" style="background:#e8f5e9"><span>6 - 10</span><span>A2</span></div>
                <div class="escala-item" style="background:#e0f2f1"><span>11 - 12</span><span>A2 / B1</span></div>
                <div class="escala-item" style="background:#e0f7fa"><span>13 - 15</span><span>B1</span></div>
                <div class="escala-item" style="background:#e1f5fe"><span>16 - 17</span><span>B1 / B2</span></div>
                <div class="escala-item" style="background:#e3f2fd"><span>18 - 19</span><span>B2</span></div>
                <div class="escala-item" style="background:#e8eaf6"><span>20 - 21</span><span>B2 / C1</span></div>
                <div class="escala-item" style="background:#ede7f6"><span>22</span><span>C1 / C2</span></div>
                <div class="escala-item" style="background:#f3e5f5"><span>23 - 25</span><span>C2</span></div>
                <?php else: ?>
                <div class="escala-item" style="background:#e8f5e9"><span>8 - 14</span><span>A2</span></div>
                <div class="escala-item" style="background:#e0f2f1"><span>15 - 17</span><span>A2 / B1</span></div>
                <div class="escala-item" style="background:#e0f7fa"><span>18 - 21</span><span>B1</span></div>
                <div class="escala-item" style="background:#e1f5fe"><span>22 - 24</span><span>B1 / B2</span></div>
                <div class="escala-item" style="background:#e3f2fd"><span>25 - 27</span><span>B2</span></div>
                <div class="escala-item" style="background:#e8eaf6"><span>28 - 30</span><span>B2 / C1</span></div>
                <div class="escala-item" style="background:#ede7f6"><span>31</span><span>C1 / C2</span></div>
                <div class="escala-item" style="background:#f3e5f5"><span>32 - 35</span><span>C2</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de recordatorio -->
    <div class="modal-overlay" id="modalRecordatorio">
        <div class="modal">
            <h3>📧 Enviar recordatorio</h3>
            <div class="form-group">
                <label>Alumno</label>
                <input type="text" id="modal_nombre" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="text" id="modal_email" readonly style="background:#f5f5f5">
            </div>
            <div id="modal_aviso_acceso" style="display:none;background:#ffebee;border:1px solid #ef9a9a;padding:12px;border-radius:6px;margin-bottom:15px;font-size:13px">
                <strong>⚠️ Este alumno nunca ha entrado a la plataforma.</strong> El recordatorio incluirá sus datos de acceso y enlace de recuperación de contraseña.
            </div>
            <div class="form-group">
                <label>Ampliar fecha límite (opcional)</label>
                <input type="date" id="modal_fecha" min="<?= date('Y-m-d') ?>">
                <p style="font-size:12px;color:#888;margin:4px 0 0" id="modal_fecha_actual"></p>
            </div>
            <div class="form-group">
                <label>Mensaje personalizado (opcional)</label>
                <textarea id="modal_mensaje" rows="3" placeholder="Ej: Te ampliamos el plazo hasta el viernes. ¡Ánimo!"></textarea>
            </div>
            <div id="modal_status" style="display:none;padding:10px;border-radius:6px;margin-bottom:10px"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button class="btn btn-warning" id="btn_enviar" onclick="enviarRecordatorio()">📧 Enviar recordatorio</button>
            </div>
        </div>
    </div>

    <script>
    function abrirModal(email, nombre, fechaLimiteActual, nuncaEntro) {
        document.getElementById('modal_email').value = email;
        document.getElementById('modal_nombre').value = nombre;
        document.getElementById('modal_fecha').value = '';
        document.getElementById('modal_fecha_actual').textContent = fechaLimiteActual ? 'Actual: ' + new Date(fechaLimiteActual).toLocaleDateString('es-ES') : 'Sin fecha límite';
        document.getElementById('modal_mensaje').value = '';
        document.getElementById('modal_status').style.display = 'none';
        document.getElementById('btn_enviar').disabled = false;
        document.getElementById('btn_enviar').textContent = '📧 Enviar recordatorio';
        var aviso = document.getElementById('modal_aviso_acceso');
        if (nuncaEntro) {
            aviso.style.display = 'block';
        } else {
            aviso.style.display = 'none';
        }
        document.getElementById('modalRecordatorio').classList.add('active');
    }
    function cerrarModal() {
        document.getElementById('modalRecordatorio').classList.remove('active');
    }
    function enviarRecordatorio() {
        var btn = document.getElementById('btn_enviar');
        var status = document.getElementById('modal_status');
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        status.style.display = 'none';
        
        var fd = new FormData();
        fd.append('enviar_recordatorio', '1');
        fd.append('email', document.getElementById('modal_email').value);
        fd.append('nombre', document.getElementById('modal_nombre').value);
        fd.append('mensaje_extra', document.getElementById('modal_mensaje').value);
        fd.append('nueva_fecha', document.getElementById('modal_fecha').value);
        fd.append('test_key', '<?= $test_key ?>');
        
        fetch(window.location.pathname + '?test=<?= $test_key ?>', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                status.style.display = 'block';
                status.style.background = '#e8f5e9';
                status.style.color = '#2e7d32';
                status.innerHTML = '✅ Recordatorio enviado correctamente — ' + data.fecha;
                btn.textContent = '✓ Enviado';
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                status.style.display = 'block';
                status.style.background = '#ffebee';
                status.style.color = '#c62828';
                status.innerHTML = '❌ Error: ' + (data.error || 'No se pudo enviar');
                btn.disabled = false;
                btn.textContent = '📧 Enviar recordatorio';
            }
        })
        .catch(function() {
            status.style.display = 'block';
            status.style.background = '#ffebee';
            status.style.color = '#c62828';
            status.innerHTML = '❌ Error de conexión';
            btn.disabled = false;
            btn.textContent = '📧 Enviar recordatorio';
        });
    }
    document.getElementById('modalRecordatorio').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });
    </script>
</body>
</html>
