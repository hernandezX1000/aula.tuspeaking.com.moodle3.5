<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Stats
$stats = [
    'usuarios' => $db->query("SELECT COUNT(*) FROM mdl_user WHERE deleted=0 AND suspended=0")->fetchColumn(),
    'cursos' => $db->query("SELECT COUNT(*) FROM mdl_course WHERE visible=1 AND id>1")->fetchColumn(),
    'pendientes' => 0,
    'hoy' => 0
];

// Check si existe tabla
try {
    $stats['pendientes'] = $db->query("SELECT COUNT(*) FROM mdl_contenido_asignaciones WHERE estado='pendiente'")->fetchColumn();
    $stats['hoy'] = $db->query("SELECT COUNT(*) FROM mdl_contenido_asignaciones WHERE estado='importado' AND DATE(fecha_importacion)=CURDATE()")->fetchColumn();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=h(APP_NAME)?> - TuSpeaking</title>
    <link rel="stylesheet" href="assets/contenido.css">
</head>
<body>
    <header class="header">
        <h1>📚 <?=h(APP_NAME)?></h1>
        <a href="<?=ADMIN_URL?>?s=dashboard" class="btn btn-outline">← Panel Admin</a>
    </header>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card"><h3><?=number_format($stats['usuarios'])?></h3><p>Usuarios</p></div>
            <div class="stat-card"><h3><?=number_format($stats['cursos'])?></h3><p>Cursos</p></div>
            <div class="stat-card"><h3><?=number_format($stats['pendientes'])?></h3><p>Pendientes</p></div>
            <div class="stat-card"><h3><?=number_format($stats['hoy'])?></h3><p>Importados hoy</p></div>
        </div>
        
        <div class="nav-grid">
            <a href="por_usuario.php" class="nav-card">
                <span class="nav-icon">👤</span>
                <h3>Por Usuario</h3>
                <p>Ver contenido y progreso de cada usuario</p>
            </a>
            <a href="por_curso.php" class="nav-card">
                <span class="nav-icon">📖</span>
                <h3>Por Curso</h3>
                <p>Gestionar contenido y alumnos por curso</p>
            </a>
            <a href="asignar.php" class="nav-card">
                <span class="nav-icon">➕</span>
                <h3>Asignar Contenido</h3>
                <p>Copiar contenido entre cursos</p>
            </a>
        </div>
    </div>
</body>
</html>
