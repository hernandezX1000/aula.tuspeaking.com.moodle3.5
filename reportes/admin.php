<?php
/**
 * Panel de Reportes - Dashboard
 * tuSpeaking 2026
 */

session_start();

$db_host = 'localhost';
$db_name = 'aulatuspeaking35';
$db_user = 'moodle35';
$db_pass = 'TuspeakingFix2025!';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$section = $_GET['s'] ?? 'dashboard';

$stats = ['empresas_activas' => 0];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM mdl_empresas WHERE activo = 1");
    $stats['empresas_activas'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $stats['empresas_activas'] = '?';
}

$tipos_reportes = [
    ['id' => 'estado_finalizacion', 'nombre' => 'Estado de Finalización', 'descripcion' => 'Progreso de actividades en plataforma por alumno', 'icon' => '✅', 'fuentes' => ['csv', 'moodle']],
    ['id' => 'clases_zoom', 'nombre' => 'Clases Zoom', 'descripcion' => 'Asistencia a clases de conversación', 'icon' => '📹', 'fuentes' => ['csv', 'zoom_api']],
    ['id' => 'progreso_combinado', 'nombre' => 'Progreso Combinado', 'descripcion' => 'Plataforma + Clases + % Global + Bonificación', 'icon' => '📊', 'fuentes' => ['csv', 'bd']],
    ['id' => 'dedicacion', 'nombre' => 'Dedicación Total', 'descripcion' => 'Tiempo en plataforma + tiempo en Zoom', 'icon' => '⏱️', 'fuentes' => ['csv', 'bd']],
    ['id' => 'resumen_empresa', 'nombre' => 'Resumen Empresa', 'descripcion' => 'Consolidado de todos los alumnos de una empresa', 'icon' => '🏢', 'fuentes' => ['csv', 'bd']],
    ['id' => 'asistencia_profesores', 'nombre' => 'Asistencia Profesores', 'descripcion' => 'Control de asistencia de profesores a clases', 'icon' => '👨‍🏫', 'fuentes' => ['csv', 'bd']],
    ['id' => 'fundae', 'nombre' => 'Reporte FUNDAE', 'descripcion' => 'Formato específico para bonificación FUNDAE', 'icon' => '📋', 'fuentes' => ['bd']]
];

$reportes_legacy = [
    ['nombre' => 'Asistencia Alumnos CESCE', 'url' => '/app/moodle/reporte_cesce.php', 'estado' => 'activo'],
    ['nombre' => 'Asistencia Profesores CESCE', 'url' => '/app/moodle/reporte_profesores_cesce.php', 'estado' => 'activo'],
    ['nombre' => 'FUNDAE CESCE', 'url' => '/app/moodle/reporte_fundae_cesce.php', 'estado' => 'activo'],
    ['nombre' => 'Reporte 1 a 1', 'url' => '/app/moodle/reporte_1to1.php', 'estado' => 'activo'],
    ['nombre' => 'Reporte Empresas', 'url' => '/app/moodle/reporte_empresas.php', 'estado' => 'activo'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Reportes - tuSpeaking</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #008ba3; --primary-dark: #006d80; --secondary: #00bcd4;
            --success: #27ae60; --warning: #f39c12; --danger: #e74c3c;
            --light: #f8f9fa; --dark: #2c3e50; --gray: #6c757d; --border: #dee2e6;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--light); min-height: 100vh; display: flex; }
        .sidebar { width: 220px; background: var(--primary); color: white; min-height: 100vh; position: fixed; left: 0; top: 0; }
        .sidebar-header { padding: 20px; font-size: 1.3rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { list-style: none; padding: 10px 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: rgba(255,255,255,0.85); text-decoration: none; transition: all 0.2s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu li a i { width: 20px; text-align: center; }
        .menu-divider { border-top: 1px solid rgba(255,255,255,0.1); margin: 10px 0; }
        .menu-label { padding: 10px 20px 5px; font-size: 0.75rem; text-transform: uppercase; color: rgba(255,255,255,0.5); letter-spacing: 1px; }
        .main-content { margin-left: 220px; flex: 1; padding: 30px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 1.5rem; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 25px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-value { font-size: 2.5rem; font-weight: bold; color: var(--primary); }
        .stat-value.warning { color: var(--warning); }
        .stat-value.success { color: var(--success); }
        .stat-value.danger { color: var(--danger); }
        .stat-label { color: var(--gray); font-size: 0.9rem; margin-top: 5px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-header { padding: 15px 20px; border-bottom: 1px solid var(--border); font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 20px; }
        .reportes-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .reporte-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 2px solid transparent; transition: all 0.2s; cursor: pointer; }
        .reporte-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .reporte-icon { font-size: 2rem; margin-bottom: 10px; }
        .reporte-nombre { font-weight: 600; color: var(--dark); margin-bottom: 5px; }
        .reporte-desc { font-size: 0.85rem; color: var(--gray); margin-bottom: 10px; }
        .reporte-fuentes { display: flex; gap: 5px; }
        .fuente-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 10px; background: var(--light); color: var(--gray); }
        .fuente-badge.csv { background: #e3f2fd; color: #1976d2; }
        .fuente-badge.bd { background: #e8f5e9; color: #388e3c; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        .table th { background: var(--light); font-weight: 600; color: var(--dark); }
        .table tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #d4edda; color: #155724; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 6px; font-size: 0.9rem; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: white; border: 1px solid var(--border); color: var(--dark); }
        .btn-outline:hover { background: var(--light); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .section-title { font-size: 1.1rem; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .two-columns { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 1200px) { .reportes-grid { grid-template-columns: repeat(2, 1fr); } .two-columns { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .sidebar { width: 60px; } .main-content { margin-left: 60px; } .stats-row { grid-template-columns: repeat(2, 1fr); } .reportes-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header"><i class="fas fa-chart-bar"></i> <span>Reportes</span></div>
        <ul class="sidebar-menu">
            <li><a href="?s=dashboard" class="<?= $section == 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
            <div class="menu-label">Generación</div>
            <li><a href="?s=generar" class="<?= $section == 'generar' ? 'active' : '' ?>"><i class="fas fa-plus-circle"></i> <span>Generar Reporte</span></a></li>
            <li><a href="?s=historico" class="<?= $section == 'historico' ? 'active' : '' ?>"><i class="fas fa-history"></i> <span>Histórico</span></a></li>
            <div class="menu-label">Validación</div>
            <li><a href="?s=auditoria" class="<?= $section == 'auditoria' ? 'active' : '' ?>"><i class="fas fa-search"></i> <span>Auditoría</span></a></li>
            <div class="menu-label">Legacy</div>
            <li><a href="?s=legacy" class="<?= $section == 'legacy' ? 'active' : '' ?>"><i class="fas fa-archive"></i> <span>Reportes Antiguos</span></a></li>
            <div class="menu-divider"></div>
            <li><a href="/app/moodle/empresas/admin.php"><i class="fas fa-building"></i> <span>Panel Empresas</span></a></li>
            <li><a href="/app/moodle/feedback/admin.php"><i class="fas fa-star"></i> <span>Panel Feedback</span></a></li>
            <li><a href="?s=config" class="<?= $section == 'config' ? 'active' : '' ?>"><i class="fas fa-cog"></i> <span>Configuración</span></a></li>
        </ul>
    </nav>
    
    <main class="main-content">
        <?php if ($section == 'dashboard'): ?>
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-th-large"></i> Dashboard</h1>
            <a href="?s=generar" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Reporte</a>
        </div>
        <div class="stats-row">
            <div class="stat-card"><div class="stat-value"><?= $stats['empresas_activas'] ?></div><div class="stat-label">Empresas Activas</div></div>
            <div class="stat-card"><div class="stat-value"><?= count($tipos_reportes) ?></div><div class="stat-label">Tipos de Reporte</div></div>
            <div class="stat-card"><div class="stat-value warning">0</div><div class="stat-label">Reportes este mes</div></div>
            <div class="stat-card"><div class="stat-value danger">0</div><div class="stat-label">Pendientes Auditoría</div></div>
        </div>
        <div class="two-columns">
            <div>
                <h3 class="section-title"><i class="fas fa-file-alt"></i> Tipos de Reportes</h3>
                <div class="reportes-grid">
                    <?php foreach ($tipos_reportes as $reporte): ?>
                    <div class="reporte-card" onclick="location.href='?s=generar&tipo=<?= $reporte['id'] ?>'">
                        <div class="reporte-icon"><?= $reporte['icon'] ?></div>
                        <div class="reporte-nombre"><?= $reporte['nombre'] ?></div>
                        <div class="reporte-desc"><?= $reporte['descripcion'] ?></div>
                        <div class="reporte-fuentes">
                            <?php foreach ($reporte['fuentes'] as $fuente): ?>
                            <span class="fuente-badge <?= $fuente == 'csv' ? 'csv' : 'bd' ?>"><?= strtoupper($fuente) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h3 class="section-title"><i class="fas fa-clock"></i> Últimos Reportes</h3>
                <div class="card"><div class="card-body"><p style="color: var(--gray); text-align: center; padding: 30px 0;"><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>No hay reportes generados aún</p></div></div>
            </div>
        </div>

        <?php elseif ($section == 'legacy'): ?>
        <div class="page-header"><h1 class="page-title"><i class="fas fa-archive"></i> Reportes Antiguos</h1></div>
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> Reportes existentes</div>
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Nombre</th><th>URL</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($reportes_legacy as $r): ?>
                        <tr>
                            <td><strong><?= $r['nombre'] ?></strong></td>
                            <td><code><?= $r['url'] ?></code></td>
                            <td><span class="badge badge-success">Activo</span></td>
                            <td><a href="<?= $r['url'] ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link-alt"></i> Abrir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section == 'generar'): ?>
        <div class="page-header"><h1 class="page-title"><i class="fas fa-plus-circle"></i> Generar Reporte</h1></div>
        <div class="card">
            <div class="card-header"><i class="fas fa-cog"></i> Configuración del Reporte</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Empresa</label>
                            <select name="empresa" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                                <option value="">Seleccionar empresa...</option>
                                <option value="hyatt">Hyatt</option>
                                <option value="cesce">CESCE</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Tipo de Reporte</label>
                            <select name="tipo" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                                <option value="">Seleccionar tipo...</option>
                                <?php foreach ($tipos_reportes as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= $r['icon'] ?> <?= $r['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Fuente de Datos</label>
                        <div style="display: flex; gap: 20px;">
                            <label style="cursor: pointer;"><input type="radio" name="fuente" value="csv" checked> 📤 Importar CSV</label>
                            <label style="cursor: pointer;"><input type="radio" name="fuente" value="bd"> 🗄️ Base de Datos</label>
                        </div>
                    </div>
                    <div style="margin-top: 20px; padding: 20px; border: 2px dashed var(--border); border-radius: 10px; text-align: center;">
                        <p>Arrastra archivos CSV o haz clic para seleccionar</p>
                        <input type="file" name="csv_files[]" multiple accept=".csv" style="margin-top: 10px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;"><i class="fas fa-play"></i> Generar Reporte</button>
                </form>
            </div>
        </div>

        <?php elseif ($section == 'config'): ?>
        <div class="page-header"><h1 class="page-title"><i class="fas fa-cog"></i> Configuración</h1></div>
        <div class="card">
            <div class="card-header"><i class="fas fa-sliders-h"></i> Parámetros</div>
            <div class="card-body">
                <div style="max-width: 400px;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Umbral Bonificación (%)</label>
                        <input type="number" value="75" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Duración mínima clase Zoom (min)</label>
                        <input type="number" value="20" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    </div>
                </div>
                <button class="btn btn-primary" style="margin-top: 10px;"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </div>

        <?php else: ?>
        <div class="card"><div class="card-body" style="text-align: center; padding: 50px;">
            <i class="fas fa-hard-hat" style="font-size: 3rem; color: var(--warning);"></i>
            <h3 style="margin-top: 15px;">Sección en desarrollo</h3>
            <a href="?s=dashboard" class="btn btn-primary" style="margin-top: 20px;">Volver al Dashboard</a>
        </div></div>
        <?php endif; ?>
    </main>
</body>
</html>
