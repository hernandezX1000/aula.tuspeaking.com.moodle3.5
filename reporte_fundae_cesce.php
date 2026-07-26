<?php
/**
 * Reporte FUNDAE CESCE - Interfaz Web
 */

require_once(__DIR__ . '/config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$mensaje = '';
$error = '';
$archivo_generado = null;

if (isset($_GET['descargar']) && isset($_GET['archivo'])) {
    $archivo = '/var/www/html/app/moodle/reportes_cesce/' . basename($_GET['archivo']);
    if (file_exists($archivo) && pathinfo($archivo, PATHINFO_EXTENSION) === 'xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
        header('Content-Length: ' . filesize($archivo));
        readfile($archivo);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '2025-09-01';
    $fecha_fin = $_POST['fecha_fin'] ?? '2025-12-31';
    
    $script = '/var/www/html/app/moodle/reportes_cesce/reporte_fundae_cesce.py';
    
    if (file_exists($script)) {
        $cmd = "cd /var/www/html/app/moodle/reportes_cesce && PYTHONPATH=/home/aulatuspeaking/.local/lib/python3.9/site-packages /usr/bin/python3 " . escapeshellarg($script) . " " . escapeshellarg($fecha_inicio) . " " . escapeshellarg($fecha_fin) . " 2>&1";
        
        $output = array();
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        $output_text = implode("\n", $output);
        
        if ($return_var === 0) {
            if (preg_match('/Reporte:\s*(.+\.xlsx)/', $output_text, $matches)) {
                $archivo_generado = basename(trim($matches[1]));
                $mensaje = 'Reporte generado correctamente';
            } else {
                $mensaje = 'Proceso completado';
            }
        } else {
            $error = 'Error al generar reporte: ' . $output_text;
        }
    } else {
        $error = 'Script no encontrado';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="https://aula.tuspeaking.com/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
    <title>Reporte FUNDAE CESCE | tuSpeaking</title>
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
        main { max-width: 700px; margin: 30px auto; padding: 0 20px; }
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
        .alert-danger { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .info-box { background: #e3f2fd; border-left: 4px solid var(--tus-secondary); padding: 15px 20px; margin-bottom: 20px; border-radius: 0 6px 6px 0; }
        .periodos { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .periodo { background: #f5f5f5; padding: 10px 15px; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; }
        .periodo:hover { border-color: var(--tus-secondary); background: #e0f7fa; }
        .nav-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .nav-links a { color: var(--tus-primary); margin-right: 20px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">tu<span>Speaking</span></div>
        <h2><i class="fas fa-file-invoice"></i> Reporte FUNDAE CESCE</h2>
        <a href="https://aula.tuspeaking.com/app/moodle"><i class="fas fa-arrow-left"></i> Ir a Moodle</a>
    </div>
</header>
<main>
    <?php if ($mensaje): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
        <?php if ($archivo_generado): ?>
        <br><a href="?descargar=1&archivo=<?php echo urlencode($archivo_generado); ?>" class="btn-download">
            <i class="fas fa-download"></i> Descargar Excel
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-times-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="fas fa-cog"></i> Configuración del Reporte</div>
        <div class="card-body">
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> Reporte FUNDAE</strong><br>
                Genera el reporte de asistencia en formato requerido por FUNDAE para la bonificación de formación.
            </div>

            <form method="post">
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Periodos Rápidos:</label>
                    <div class="periodos">
                        <div class="periodo" onclick="document.getElementById('f1').value='2025-09-01';document.getElementById('f2').value='2025-12-31'">
                            <strong>25.2</strong> Sep-Dic 2025
                        </div>
                        <div class="periodo" onclick="document.getElementById('f1').value='2025-01-01';document.getElementById('f2').value='2025-06-30'">
                            <strong>25.1</strong> Ene-Jun 2025
                        </div>
                        <div class="periodo" onclick="document.getElementById('f1').value='2026-01-01';document.getElementById('f2').value='2026-06-30'">
                            <strong>26.1</strong> Ene-Jun 2026
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-calendar"></i> Fecha Inicio:</label>
                        <input type="date" id="f1" name="fecha_inicio" class="form-control" value="<?php echo $_POST['fecha_inicio'] ?? '2025-09-01'; ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-calendar"></i> Fecha Fin:</label>
                        <input type="date" id="f2" name="fecha_fin" class="form-control" value="<?php echo $_POST['fecha_fin'] ?? '2025-12-31'; ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-generate">
                    <i class="fas fa-play"></i> Generar Reporte FUNDAE
                </button>
            </form>

            <div class="nav-links">
                <a href="reporte_cesce.php"><i class="fas fa-users"></i> Alumnos CESCE</a>
                <a href="reporte_profesores_cesce.php"><i class="fas fa-chalkboard-teacher"></i> Profesores CESCE</a>
                <a href="reporte_empresas.php"><i class="fas fa-building"></i> Empresas</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
