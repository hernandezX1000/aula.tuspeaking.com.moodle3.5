<?php
/**
 * Importador de grupos Teams desde Excel/CSV
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$mensaje = '';
$errores = [];
$importados_grupos = 0;
$importados_alumnos = 0;

// Procesar importación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo']['tmp_name'];
    $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['csv', 'xls', 'xlsx'])) {
        $mensaje = 'error:Formato no válido. Use CSV o Excel.';
    } else {
        // Leer CSV (para Excel, convertir primero)
        if ($extension == 'csv') {
            $handle = fopen($archivo, 'r');
        } else {
            // Para Excel, usar conversión simple
            $mensaje = 'error:Por favor, guarde el archivo como CSV (UTF-8) antes de importar.';
        }
        
        if (isset($handle) && $handle) {
            $fila = 0;
            $grupos_creados = [];
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $fila++;
                if ($fila == 1) continue; // Saltar cabecera
                
                // Columnas: Empresa, Grupo, Profesor, Día, Hora, Idioma, FechaInicio, FechaFin, NombreAlumno, EmailAlumno
                if (count($data) < 10) {
                    $errores[] = "Fila $fila: Faltan columnas";
                    continue;
                }
                
                $empresa = trim($data[0]);
                $grupo = trim($data[1]);
                $profesor = trim($data[2]);
                $dia = strtolower(trim($data[3]));
                $hora = trim($data[4]);
                $idioma = strtolower(trim($data[5])) == 'en' ? 'en' : 'es';
                $fecha_inicio = trim($data[6]);
                $fecha_fin = trim($data[7]);
                $nombre_alumno = trim($data[8]);
                $email_alumno = strtolower(trim($data[9]));
                
                // Validar
                if (empty($empresa) || empty($grupo) || empty($email_alumno)) {
                    $errores[] = "Fila $fila: Faltan datos obligatorios (Empresa, Grupo o Email)";
                    continue;
                }
                
                if (!filter_var($email_alumno, FILTER_VALIDATE_EMAIL)) {
                    $errores[] = "Fila $fila: Email inválido ($email_alumno)";
                    continue;
                }
                
                // Normalizar día
                $dias_map = ['lunes'=>'lunes','monday'=>'lunes','martes'=>'martes','tuesday'=>'martes','miercoles'=>'miercoles','miércoles'=>'miercoles','wednesday'=>'miercoles','jueves'=>'jueves','thursday'=>'jueves','viernes'=>'viernes','friday'=>'viernes'];
                $dia = $dias_map[$dia] ?? $dia;
                
                if (!in_array($dia, ['lunes','martes','miercoles','jueves','viernes'])) {
                    $errores[] = "Fila $fila: Día inválido ($dia)";
                    continue;
                }
                
                // Crear grupo si no existe
                $grupo_key = "$empresa|$grupo|$dia|$hora";
                if (!isset($grupos_creados[$grupo_key])) {
                    // Verificar si ya existe
                    $check = $conn->query("SELECT id FROM own_feedback_teams_grupos WHERE empresa = '".$conn->real_escape_string($empresa)."' AND nombre_grupo = '".$conn->real_escape_string($grupo)."'");
                    
                    if ($check->num_rows > 0) {
                        $grupos_creados[$grupo_key] = $check->fetch_assoc()['id'];
                    } else {
                        $stmt = $conn->prepare("INSERT INTO own_feedback_teams_grupos (empresa, nombre_grupo, profesor, dia_semana, hora_inicio, idioma_formulario, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssss", $empresa, $grupo, $profesor, $dia, $hora, $idioma, $fecha_inicio, $fecha_fin);
                        $stmt->execute();
                        $grupos_creados[$grupo_key] = $conn->insert_id;
                        $importados_grupos++;
                    }
                }
                
                $grupo_id = $grupos_creados[$grupo_key];
                
                // Añadir alumno si no existe
                $check_alumno = $conn->query("SELECT id FROM own_feedback_teams_alumnos WHERE grupo_id = $grupo_id AND email = '".$conn->real_escape_string($email_alumno)."'");
                if ($check_alumno->num_rows == 0) {
                    $stmt = $conn->prepare("INSERT INTO own_feedback_teams_alumnos (grupo_id, nombre, email) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $grupo_id, $nombre_alumno, $email_alumno);
                    $stmt->execute();
                    $importados_alumnos++;
                }
            }
            fclose($handle);
            
            if ($importados_grupos > 0 || $importados_alumnos > 0) {
                $mensaje = "ok:Importación completada: $importados_grupos grupos, $importados_alumnos alumnos";
            } else {
                $mensaje = "warning:No se importaron nuevos registros";
            }
        }
    }
}

// Generar plantilla CSV de ejemplo
if (isset($_GET['plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_teams_feedback.csv');
    echo "\xEF\xBB\xBF"; // BOM para Excel
    echo "Empresa,Grupo,Profesor,Dia,Hora,Idioma,FechaInicio,FechaFin,NombreAlumno,EmailAlumno\n";
    echo "Samsung,Samsung Grupo 1,Kate Klopper,Martes,10:00,EN,2026-01-15,2026-06-30,John Smith,john.smith@samsung.com\n";
    echo "Samsung,Samsung Grupo 1,Kate Klopper,Martes,10:00,EN,2026-01-15,2026-06-30,Jane Doe,jane.doe@samsung.com\n";
    echo "Rubi,Rubi Grupo 1,Steve Marchant,Miercoles,09:00,ES,2026-01-15,2026-06-30,Pedro García,pedro@rubi.com\n";
    echo "Lin3s,Lin3s Grupo 1,Amber Gendron,Jueves,11:00,ES,2026-01-15,2026-06-30,María López,maria@lin3s.com\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Teams - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;padding:20px}
        .container{max-width:800px;margin:0 auto}
        .card{background:white;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        h1{color:#008ba3;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        h3{color:#333;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        h3 .material-icons{color:#008ba3}
        .alert{padding:16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .alert-ok{background:#e8f5e9;color:#27ae60}
        .alert-error{background:#ffebee;color:#e74c3c}
        .alert-warning{background:#fff8e1;color:#f39c12}
        table{width:100%;border-collapse:collapse;margin:16px 0}
        th,td{padding:10px;text-align:left;border-bottom:1px solid #eee;font-size:13px}
        th{background:#f9f9f9;color:#666}
        code{background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none}
        .btn-primary{background:#008ba3;color:white}
        .btn-success{background:#27ae60;color:white}
        .btn-primary:hover{background:#007a91}
        .btn-success:hover{background:#229954}
        input[type="file"]{margin:16px 0;padding:10px;border:2px dashed #ddd;border-radius:8px;width:100%;cursor:pointer}
        .errors{background:#ffebee;padding:16px;border-radius:8px;margin-top:16px}
        .errors li{color:#e74c3c;font-size:13px;margin:4px 0}
        .back{color:#008ba3;text-decoration:none;display:flex;align-items:center;gap:4px;margin-bottom:20px}
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php?s=teams" class="back"><span class="material-icons">arrow_back</span> Volver a Teams</a>
        
        <h1><span class="material-icons">upload_file</span> Importar Grupos Teams</h1>
        
        <?php 
        if ($mensaje):
            list($tipo, $texto) = explode(':', $mensaje, 2);
        ?>
        <div class="alert alert-<?=$tipo?>">
            <span class="material-icons"><?=$tipo=='ok'?'check_circle':($tipo=='error'?'error':'warning')?></span>
            <?=$texto?>
        </div>
        <?php endif; ?>
        
        <?php if (count($errores) > 0): ?>
        <div class="errors">
            <strong>Errores encontrados:</strong>
            <ul><?php foreach($errores as $e): ?><li><?=$e?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h3><span class="material-icons">description</span> Paso 1: Descargar Plantilla</h3>
            <p style="color:#666;margin-bottom:16px;">Descarga la plantilla CSV, complétala con los datos de los grupos y alumnos.</p>
            <a href="?plantilla=1" class="btn btn-success"><span class="material-icons">download</span> Descargar Plantilla CSV</a>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">table_chart</span> Formato de la Plantilla</h3>
            <table>
                <tr><th>Columna</th><th>Obligatorio</th><th>Formato</th><th>Ejemplo</th></tr>
                <tr><td>Empresa</td><td>✅ Sí</td><td>Texto</td><td>Samsung</td></tr>
                <tr><td>Grupo</td><td>✅ Sí</td><td>Texto</td><td>Samsung Grupo 1</td></tr>
                <tr><td>Profesor</td><td>✅ Sí</td><td>Texto</td><td>Kate Klopper</td></tr>
                <tr><td>Dia</td><td>✅ Sí</td><td>Lunes/Martes/Miercoles/Jueves/Viernes</td><td>Martes</td></tr>
                <tr><td>Hora</td><td>✅ Sí</td><td>HH:MM</td><td>10:00</td></tr>
                <tr><td>Idioma</td><td>✅ Sí</td><td>ES / EN</td><td>EN</td></tr>
                <tr><td>FechaInicio</td><td>✅ Sí</td><td>YYYY-MM-DD</td><td>2026-01-15</td></tr>
                <tr><td>FechaFin</td><td>✅ Sí</td><td>YYYY-MM-DD</td><td>2026-06-30</td></tr>
                <tr><td>NombreAlumno</td><td>⚠️ Recomendado</td><td>Texto</td><td>John Smith</td></tr>
                <tr><td>EmailAlumno</td><td>✅ Sí</td><td>email@ejemplo.com</td><td>john@samsung.com</td></tr>
            </table>
            <p style="color:#666;font-size:13px;margin-top:12px;">💡 <strong>Tip:</strong> Cada fila es un alumno. Si un grupo tiene 5 alumnos, habrá 5 filas con los mismos datos de grupo.</p>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">cloud_upload</span> Paso 2: Importar Datos</h3>
            <form method="POST" enctype="multipart/form-data">
                <p style="color:#666;margin-bottom:12px;">Selecciona el archivo CSV completado:</p>
                <input type="file" name="archivo" accept=".csv" required>
                <p style="color:#999;font-size:12px;margin-bottom:16px;">⚠️ El archivo debe estar en formato CSV (UTF-8). Si usas Excel, guarda como "CSV UTF-8".</p>
                <button type="submit" class="btn btn-primary"><span class="material-icons">upload</span> Importar</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
