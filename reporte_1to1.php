<?php
$reportes_dir = '/home/aulatuspeaking/www/app/moodle/reportes_1to1/';
$script_path = $reportes_dir . 'generar_reporte_1to1.sh';

$empresas = [
    '' => 'Todas las empresas 1-to-1',
    'Tekia' => 'Tekia',
    'OHmyBox' => 'OHmyBox'
];

$periodos = [
    ['nombre' => '2025.2 (Sep-Dic 2025)', 'inicio' => '2025-09-01', 'fin' => '2025-12-31'],
    ['nombre' => '2026.1 (Ene-Jun 2026)', 'inicio' => '2026-01-01', 'fin' => '2026-06-30'],
];

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $con_tiempo = isset($_POST['con_tiempo']) && $_POST['con_tiempo'] == '1';
    
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $error = 'Por favor, selecciona las fechas de inicio y fin.';
    } elseif ($fecha_inicio > $fecha_fin) {
        $error = 'La fecha de inicio debe ser anterior a la fecha de fin.';
    } else {
        $cmd = "PYTHONPATH=/home/aulatuspeaking/.local/lib/python3.9/site-packages /bin/bash $script_path " . 
               escapeshellarg($fecha_inicio) . " " . 
               escapeshellarg($fecha_fin);
        
        if (!empty($empresa)) {
            $cmd .= " " . escapeshellarg($empresa);
        }
        
        if ($con_tiempo) {
            $cmd .= " --con-tiempo";
        }
        
        $cmd .= " 2>&1";
        
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0) {
            if (!empty($empresa)) {
                $archivo = $reportes_dir . "Reporte_1to1_{$empresa}_{$fecha_inicio}_a_{$fecha_fin}.xlsx";
            } else {
                $archivo = $reportes_dir . "Reporte_1to1_Empresas_{$fecha_inicio}_a_{$fecha_fin}.xlsx";
            }
            
            if (file_exists($archivo)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
                header('Content-Length: ' . filesize($archivo));
                readfile($archivo);
                exit;
            } else {
                $error = 'El archivo no se encontró.';
                $mensaje = implode("\n", $output);
            }
        } else {
            $error = 'Error al generar el reporte.';
            $mensaje = implode("\n", $output);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Asistencia 1-to-1 | TuSpeaking</title>
    <style>
        :root { --ts-primary: #008ba3; --ts-secondary: #00bcd4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: var(--ts-primary); font-size: 1.8rem; margin-bottom: 8px; }
        .header p { color: #666; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        input[type="date"], select { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; }
        input[type="date"]:focus, select:focus { outline: none; border-color: var(--ts-primary); }
        .row { display: flex; gap: 20px; }
        .row .form-group { flex: 1; }
        .periodos { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .periodo-btn { padding: 8px 16px; background: #f0f0f0; border: none; border-radius: 20px; cursor: pointer; font-size: 0.9rem; }
        .periodo-btn:hover { background: var(--ts-secondary); color: white; }
        .checkbox-label { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
        .checkbox-label input { width: 20px; height: 20px; margin-top: 2px; }
        .btn-submit { width: 100%; padding: 14px; background: var(--ts-primary); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #006d80; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: #ffeaea; color: #e74c3c; border: 1px solid #ffcdd2; }
        .leyenda { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .leyenda h3 { font-size: 1rem; margin-bottom: 15px; }
        .leyenda-items { display: flex; gap: 20px; flex-wrap: wrap; }
        .leyenda-item { display: flex; align-items: center; gap: 8px; }
        .color-box { width: 24px; height: 24px; border-radius: 4px; }
        .color-green { background: #C6EFCE; }
        .color-yellow { background: #FFEB9C; }
        .color-red { background: #FFC7CE; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 8px; font-size: 0.85rem; margin-top: 10px; overflow-x: auto; }
        .info-box { background: #e8f5e9; border-left: 4px solid #27ae60; padding: 15px; margin-bottom: 20px; border-radius: 0 8px 8px 0; }
        .info-box h4 { margin-bottom: 10px; color: #2e7d32; }
        .info-box ul { margin-left: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>📊 Reporte Asistencia 1-to-1</h1>
                <p>Genera reportes para empresas con clases individuales</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    <?php if ($mensaje): ?><pre><?= htmlspecialchars($mensaje) ?></pre><?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h4>📋 Datos incluidos:</h4>
                <ul>
                    <li>Clases contratadas vs realizadas</li>
                    <li>Porcentaje de asistencia</li>
                    <li>Progreso en plataforma Moodle</li>
                    <li>Última entrada al curso</li>
                    <li>Estado FUNDAE</li>
                </ul>
            </div>
            
            <form method="POST">
                <label>Períodos sugeridos:</label>
                <div class="periodos">
                    <?php foreach ($periodos as $p): ?>
                        <button type="button" class="periodo-btn" onclick="setFechas('<?= $p['inicio'] ?>', '<?= $p['fin'] ?>')"><?= $p['nombre'] ?></button>
                    <?php endforeach; ?>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label>Fecha Inicio:</label>
                        <input type="date" name="fecha_inicio" value="<?= $_POST['fecha_inicio'] ?? '2025-09-01' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin:</label>
                        <input type="date" name="fecha_fin" value="<?= $_POST['fecha_fin'] ?? date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Empresa:</label>
                    <select name="empresa">
                        <?php foreach ($empresas as $v => $n): ?>
                            <option value="<?= $v ?>" <?= (($_POST['empresa'] ?? '') === $v) ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="con_tiempo" value="1" <?= isset($_POST['con_tiempo']) ? 'checked' : '' ?>>
                        <span>Incluir tiempo de dedicación <small style="color:#666;">(más lento)</small></span>
                    </label>
                </div>
                
                <button type="submit" class="btn-submit">📥 Generar y Descargar Reporte</button>
            </form>
            
            <div class="leyenda">
                <h3>🎨 Código de colores:</h3>
                <div class="leyenda-items">
                    <div class="leyenda-item"><div class="color-box color-green"></div><span>≥75% Bueno</span></div>
                    <div class="leyenda-item"><div class="color-box color-yellow"></div><span>50-74% Regular</span></div>
                    <div class="leyenda-item"><div class="color-box color-red"></div><span>&lt;50% Bajo</span></div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function setFechas(inicio, fin) {
            document.querySelector('input[name="fecha_inicio"]').value = inicio;
            document.querySelector('input[name="fecha_fin"]').value = fin;
        }
    </script>
</body>
</html>
