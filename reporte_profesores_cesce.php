<?php
/**
 * Reporte de Asistencia Profesores CESCE - Interfaz Web
 */

require_once(__DIR__ . '/config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$mensaje = '';
$archivo_generado = null;

if (isset($_GET['descargar']) && isset($_GET['archivo'])) {
    $archivo = '/home/aulatuspeaking/www/app/moodle/reportes_cesce/' . basename($_GET['archivo']);
    if (file_exists($archivo) && pathinfo($archivo, PATHINFO_EXTENSION) === 'xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
        header('Content-Length: ' . filesize($archivo));
        readfile($archivo);
        exit;
    }
}

if (isset($_POST['generar'])) {
    $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '2025-09-01';
    $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-d');
    
    $script = '/home/aulatuspeaking/www/app/moodle/reportes_cesce/generar_reporte_profesores.sh';
    
    if (file_exists($script)) {
        $cmd = "PYTHONPATH=/home/aulatuspeaking/.local/lib/python3.9/site-packages /bin/bash " . escapeshellarg($script) . " " . escapeshellarg($fecha_inicio) . " " . escapeshellarg($fecha_fin) . " 2>&1";
        
        $output = array();
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        $output_text = implode("\n", $output);
        
        if ($return_var === 0) {
            $archivo_esperado = "/home/aulatuspeaking/www/app/moodle/reportes_cesce/Reporte_Profesores_CESCE_{$fecha_inicio}_a_{$fecha_fin}_formato.xlsx";
            if (file_exists($archivo_esperado)) {
                $archivo_generado = $archivo_esperado;
                $mensaje = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Reporte generado correctamente</div>';
            } else {
                $mensaje = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Proceso completado pero no se encontró archivo</div>';
            }
        } else {
            $mensaje = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error al generar reporte</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Script no encontrado</div>';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="https://aula.tuspeaking.com/app/moodle/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
    <title>Reporte Profesores CESCE | tuSpeaking</title>
    <style>
        :root {
            --tus-primary: #008ba3;
            --tus-secondary: #00bcd4;
            --tus-dark: #454545;
        }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: var(--tus-dark); margin: 0; }
        header { background: linear-gradient(135deg, var(--tus-primary), var(--tus-secondary)); padding: 15px 30px; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; }
        .logo { font-size: 26px; color: white; font-weight: 300; }
        .logo span { font-weight: 600; }
        header h2 { color: white; font-size: 18px; font-weight: 400; margin: 0; }
        header a { color: white; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 4px; text-decoration: none; }
        header a:hover { background: rgba(255,255,255,0.3); color: white; }
        main { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-header { background: var(--tus-secondary); color: white; padding: 15px 20px; font-size: 16px; font-weight: 500; border-radius: 8px 8px 0 0; }
        .card-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 600; margin-bottom: 8px; display: block; }
        .form-control { border: 1px solid #ddd; border-radius: 4px; padding: 10px 12px; width: 100%; }
        .form-control:focus { border-color: var(--tus-secondary); outline: none; box-shadow: 0 0 0 2px rgba(0,188,212,0.2); }
        .btn-generate { background: var(--tus-primary); color: white; border: none; padding: 12px 30px; font-size: 16px; border-radius: 6px; cursor: pointer; width: 100%; }
        .btn-generate:hover { background: var(--tus-secondary); }
        .btn-download { background: #4CAF50; color: white; padding: 15px 30px; border-radius: 6px; text-decoration: none; display: inline-block; font-size: 16px; margin-top: 15px; }
        .btn-download:hover { background: #43a047; color: white; text-decoration: none; }
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
        .alert-warning { background: #fff3e0; color: #ef6c00; border-left: 4px solid #ff9800; }
        .alert-danger { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .info-box { background: #e3f2fd; border-left: 4px solid var(--tus-secondary); padding: 15px 20px; margin-bottom: 20px; border-radius: 0 6px 6px 0; }
        .info-box ul { margin: 10px 0 0 0; padding-left: 20px; }
        .info-box li { margin: 5px 0; }
        .leyenda { display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap; }
        .leyenda span { padding: 5px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; }
        .verde { background: #C6EFCE; color: #006100; }
        .amarillo { background: #FFEB9C; color: #9C5700; }
        .azul { background: #BDD7EE; color: #1F4E79; }
        .rojo { background: #FFC7CE; color: #9C0006; }
        .periodos { background: #f5f5f5; padding: 15px; border-radius: 6px; margin-top: 20px; }
        .periodos p { margin: 5px 0; font-size: 14px; }
        .nav-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .nav-links a { color: var(--tus-primary); margin-right: 20px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">tu<span>Speaking</span></div>
        <h2><i class="fas fa-chalkboard-teacher"></i> Reporte Asistencia CESCE - Profesores</h2>
        <a href="https://aula.tuspeaking.com/app/moodle"><i class="fas fa-arrow-left"></i> Ir a Moodle</a>
    </div>
</header>
<main>
    <?php echo $mensaje; ?>
    
    <?php if ($archivo_generado && file_exists($archivo_generado)): ?>
    <div class="card">
        <div class="card-header"><i class="fas fa-file-excel"></i> Reporte Generado</div>
        <div class="card-body" style="text-align: center;">
            <p><strong><?php echo basename($archivo_generado); ?></strong></p>
            <a href="?descargar=1&archivo=<?php echo urlencode(basename($archivo_generado)); ?>" class="btn-download">
                <i class="fas fa-download"></i> Descargar Excel
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="fas fa-cog"></i> Configuración del Reporte</div>
        <div class="card-body">
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> Contenido del reporte (8 hojas):</strong>
                <ul>
                    <li><strong>Resumen mensual (4 hojas):</strong> Sep, Oct, Nov, Dic - Profesores por grupo</li>
                    <li><strong>Detalle Zoom (4 hojas):</strong> Registros individuales por mes</li>
                </ul>
                <div class="leyenda">
                    <span class="verde">✓ Profesor asignado</span>
                    <span class="amarillo">⚠ Sustituto</span>
                    <span class="azul">👥 Co-profesor</span>
                    <span class="rojo">✗ Sin profesor</span>
                </div>
            </div>

            <form method="post">
                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-calendar"></i> Fecha Inicio:</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '2025-09-01'; ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-calendar"></i> Fecha Fin:</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <button type="submit" name="generar" class="btn-generate">
                    <i class="fas fa-play"></i> Generar Reporte
                </button>
            </form>

            <div class="periodos">
                <strong><i class="fas fa-clock"></i> Periodos sugeridos:</strong>
                <p>• Edición 25.1: 2025-01-01 a 2025-06-30</p>
                <p>• Edición 25.2: 2025-09-01 a 2025-12-19</p>
                <p>• Edición 26.1: 2026-01-01 a 2026-06-30</p>
            </div>

            <div class="nav-links">
                <a href="reporte_cesce.php"><i class="fas fa-users"></i> Alumnos CESCE</a>
                <a href="reporte_empresas.php"><i class="fas fa-building"></i> Reporte Empresas</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
