<?php
/**
 * Panel de Importación de Datos Zoom
 * URL: https://aula.tuspeaking.com/app/moodle/coding_zoom_import.php
 */
require('config.php');

$admins = get_admins();
$isadmin = false;
foreach($admins as $admin){
    if ($admin->id == $USER->id){ $isadmin = true; break; }
}
if (!$isadmin){ header("Location: /app/moodle"); die(); }

$upload_dir = '/home/aulatuspeaking/scripts/coding_tuspeaking/data/zoom/';
$processed_dir = '/home/aulatuspeaking/scripts/coding_tuspeaking/data/processed/';

if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
if (!file_exists($processed_dir)) mkdir($processed_dir, 0777, true);

$msg = '';
$import_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === 0) {
    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, ['csv'])) {
        $original_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file['name']);
        $fn = 'zoom_'.date('Ymd_His').'_'.$original_name;
        $fp = $upload_dir.$fn;
        
        if (move_uploaded_file($file['tmp_name'], $fp)) {
            $import_result = process_zoom_csv($fp, $USER->username);
            
            if ($import_result['success']) {
                rename($fp, $processed_dir.$fn);
                $msg = 'success';
            } else {
                $msg = 'error:'.$import_result['error'];
            }
        } else {
            $msg = 'error:Error al subir el archivo';
        }
    } else {
        $msg = 'warning:Formato no válido. Use .csv';
    }
}

function process_zoom_csv($filepath, $imported_by) {
    global $DB;
    
    $filename = basename($filepath);
    $result = ['success' => false, 'import_id' => null, 'meetings' => 0, 'participants' => 0, 'error' => null];
    
    try {
        if (preg_match('/(\d{4}_\d{2}_\d{2})_(\d{4}_\d{2}_\d{2})/', $filename, $m)) {
            $date_from = str_replace('_', '-', $m[1]);
            $date_to = str_replace('_', '-', $m[2]);
        } else {
            $date_from = date('Y-m-d');
            $date_to = $date_from;
        }
        
        $import = new stdClass();
        $import->filename = $filename;
        $import->filepath = $filepath;
        $import->date_from = $date_from;
        $import->date_to = $date_to;
        $import->imported_by = $imported_by;
        $import->imported_at = date('Y-m-d H:i:s');
        $import->notes = 'Importación desde panel web';
        
        $import_id = $DB->insert_record('coding_zoom_imports', $import);
        $result['import_id'] = $import_id;
        
        $handle = fopen($filepath, 'r');
        if (!$handle) throw new Exception("No se puede abrir el archivo");
        
        $first_line = fgets($handle);
        rewind($handle);
        $delimiter = (strpos($first_line, ';') !== false) ? ';' : ',';
        
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) throw new Exception("No se puede leer la cabecera");
        
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        
        $col_map = [];
        foreach ($header as $i => $col) $col_map[trim($col)] = $i;
        
        $meetings_inserted = [];
        $meetings_count = 0;
        $participants_count = 0;
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $meeting_id_raw = $row[$col_map['ID']] ?? '';
            if (empty(trim($meeting_id_raw))) continue;
            
            $meeting_id = preg_replace('/\s+/', '', $meeting_id_raw);
            if (empty($meeting_id) || !is_numeric($meeting_id)) continue;
            
            $meeting_key = $import_id.'_'.$meeting_id;
            if (!isset($meetings_inserted[$meeting_key])) {
                $meeting = new stdClass();
                $meeting->import_id = $import_id;
                $meeting->zoom_meetingid = $meeting_id;
                $meeting->topic = substr($row[$col_map['Tema']] ?? '', 0, 500);
                $meeting->meeting_type = $row[$col_map['Tipo']] ?? '';
                $meeting->host_name = $row[$col_map['Nombre del anfitrión']] ?? '';
                $meeting->host_email = $row[$col_map['Correo electrónico del anfitrión']] ?? '';
                $meeting->start_time = parse_zoom_datetime($row[$col_map['Hora de inicio']] ?? '');
                $meeting->end_time = parse_zoom_datetime($row[$col_map['Hora de finalización']] ?? '');
                $meeting->duration_minutes = intval($row[$col_map['Duración (minutos)']] ?? 0);
                $meeting->participants_count = intval($row[$col_map['Participantes']] ?? 0);
                $meeting->total_participant_minutes = intval($row[$col_map['Total de minutos de los participantes']] ?? 0);
                $meeting->department = $row[$col_map['Departamento']] ?? '';
                $meeting->source = $row[$col_map['Fuente']] ?? '';
                
                try {
                    $DB->insert_record('coding_zoom_meetings', $meeting);
                    $meetings_count++;
                } catch (Exception $e) {}
                $meetings_inserted[$meeting_key] = true;
            }
            
            $participant_name = $row[$col_map['Nombre (nombre original)']] ?? '';
            if (!empty($participant_name)) {
                $participant = new stdClass();
                $participant->import_id = $import_id;
                $participant->zoom_meetingid = $meeting_id;
                $participant->participant_name = substr($participant_name, 0, 200);
                $participant->participant_email = substr($row[$col_map['Correo electrónico']] ?? '', 0, 200);
                $participant->join_time = parse_zoom_datetime($row[$col_map['Hora de entrada']] ?? '');
                $participant->leave_time = parse_zoom_datetime($row[$col_map['Hora de salida']] ?? '');
                
                $dur_idx = ($col_map['Hora de salida'] ?? 0) + 1;
                $participant->duration_minutes = isset($row[$dur_idx]) ? intval($row[$dur_idx]) : 0;
                $participant->is_guest = $row[$col_map['Invitado']] ?? '';
                $participant->in_waiting_room = $row[$col_map['En sala de espera']] ?? '';
                
                $DB->insert_record('coding_zoom_participants', $participant);
                $participants_count++;
            }
        }
        
        fclose($handle);
        
        $DB->execute("UPDATE {coding_zoom_imports} SET total_records = ?, total_meetings = ?, total_participants = ? WHERE id = ?",
            [$participants_count, $meetings_count, $participants_count, $import_id]);
        
        $result['success'] = true;
        $result['meetings'] = $meetings_count;
        $result['participants'] = $participants_count;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

function parse_zoom_datetime($date_str) {
    if (empty($date_str) || $date_str === '-') return null;
    $dt = DateTime::createFromFormat('m/d/Y h:i:s A', trim($date_str));
    if ($dt) return $dt->format('Y-m-d H:i:s');
    $dt = DateTime::createFromFormat('n/j/y H:i', trim($date_str));
    if ($dt) return $dt->format('Y-m-d H:i:s');
    return null;
}

$imports = $DB->get_records_sql("SELECT * FROM {coding_zoom_imports} ORDER BY imported_at DESC LIMIT 20");
$total_imports = $DB->count_records('coding_zoom_imports');
$total_participants = $DB->count_records('coding_zoom_participants');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="https://aula.tuspeaking.com/app/moodle/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
    <title>Importar Datos Zoom | tuSpeaking</title>
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
        header a.back-link { color: white; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 4px; text-decoration: none; }
        header a.back-link:hover { background: rgba(255,255,255,0.3); color: white; }
        main { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-header { background: var(--tus-secondary); color: white; padding: 15px 20px; font-size: 15px; font-weight: 500; border-radius: 8px 8px 0 0; }
        .card-header.success { background: #4CAF50; }
        .card-header.warning { background: #ff9800; }
        .card-header.info { background: var(--tus-primary); }
        .card-body { padding: 20px; }
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
        .alert-danger { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .alert-warning { background: #fff3e0; color: #ef6c00; border-left: 4px solid #ff9800; }
        .upload-zone { border: 2px dashed var(--tus-secondary); border-radius: 10px; padding: 40px 30px; text-align: center; background: #e0f7fa; cursor: pointer; transition: all 0.2s; }
        .upload-zone:hover { background: #b2ebf2; border-color: var(--tus-primary); }
        .upload-zone i { color: var(--tus-primary); }
        .btn-tus { background: var(--tus-primary); border: none; color: white; padding: 12px 25px; border-radius: 6px; font-size: 15px; cursor: pointer; }
        .btn-tus:hover { background: var(--tus-secondary); color: white; }
        .btn-outline { background: white; border: 2px solid var(--tus-secondary); color: var(--tus-primary); padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; }
        .btn-outline:hover { background: var(--tus-secondary); color: white; text-decoration: none; }
        .stat-box { text-align: center; padding: 20px; background: linear-gradient(135deg, var(--tus-primary), var(--tus-secondary)); border-radius: 8px; color: white; }
        .stat-box h3 { margin: 0; font-size: 2.5em; font-weight: 600; }
        .stat-box small { opacity: 0.9; }
        .stat-box.green { background: linear-gradient(135deg, #43a047, #66bb6a); }
        .stat-box.orange { background: linear-gradient(135deg, #ff9800, #ffb74d); }
        .import-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        .import-row:last-child { border-bottom: none; }
        .import-row:hover { background: #fafafa; }
        .badge-id { background: var(--tus-secondary); color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
        .instructions ol { margin: 0; padding-left: 20px; }
        .instructions li { margin: 8px 0; }
        .nav-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; }
        .nav-links a { color: var(--tus-primary); margin: 0 15px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">tu<span>Speaking</span></div>
        <h2><i class="fas fa-video"></i> Importar Datos Zoom</h2>
        <a href="https://aula.tuspeaking.com/app/moodle" class="back-link"><i class="fas fa-arrow-left"></i> Ir a Moodle</a>
    </div>
</header>
<main>
    <?php if ($msg === 'success'): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <strong>Importación completada correctamente</strong></div>
    <?php elseif (strpos($msg, 'error:') === 0): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <strong>Error:</strong> <?php echo htmlspecialchars(substr($msg, 6)); ?></div>
    <?php elseif (strpos($msg, 'warning:') === 0): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars(substr($msg, 8)); ?></div>
    <?php endif; ?>

    <?php if ($import_result && $import_result['success']): ?>
    <div class="card">
        <div class="card-header success"><i class="fas fa-check-circle"></i> Resultado de la Importación</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><div class="stat-box green"><h3><?php echo $import_result['meetings']; ?></h3><small>Meetings</small></div></div>
                <div class="col-md-4"><div class="stat-box"><h3><?php echo $import_result['participants']; ?></h3><small>Participantes</small></div></div>
                <div class="col-md-4"><div class="stat-box orange"><h3>#<?php echo $import_result['import_id']; ?></h3><small>ID Importación</small></div></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-upload"></i> Subir Archivo CSV</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-zone" onclick="document.getElementById('archivo').click()">
                            <i class="fas fa-cloud-upload-alt fa-3x"></i>
                            <p class="mt-3 mb-1"><strong>Clic para seleccionar archivo CSV</strong></p>
                            <small class="text-muted">Exportado desde Zoom Reports</small>
                            <p id="fileName" style="color: var(--tus-primary); margin-top: 15px; font-weight: 500;"></p>
                        </div>
                        <input type="file" name="archivo" id="archivo" accept=".csv" style="display:none" required 
                               onchange="document.getElementById('fileName').textContent=this.files[0]?'📄 '+this.files[0].name:'';if(this.files[0])document.getElementById('submitBtn').style.display='block';">
                        <button type="submit" id="submitBtn" class="btn-tus btn-block mt-3" style="display:none">
                            <i class="fas fa-upload"></i> Importar Archivo
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header info"><i class="fas fa-info-circle"></i> Instrucciones</div>
                <div class="card-body instructions">
                    <ol>
                        <li>Ir a <strong>Zoom → Reports → Usage Reports → Meeting</strong></li>
                        <li>Seleccionar el rango de fechas deseado</li>
                        <li>Click en <strong>Export as CSV</strong></li>
                        <li>Subir el archivo aquí</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header warning"><i class="fas fa-chart-bar"></i> Estadísticas</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6"><div class="stat-box"><h3><?php echo $total_imports; ?></h3><small>Importaciones</small></div></div>
                        <div class="col-6"><div class="stat-box green"><h3><?php echo number_format($total_participants); ?></h3><small>Participantes</small></div></div>
                    </div>
                    <a href="coding_zoom_audit.php" class="btn-outline btn-block mt-3 text-center">
                        <i class="fas fa-search"></i> Ver Auditoría Zoom
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history"></i> Últimas Importaciones
                    <button onclick="location.reload()" style="float:right; background:rgba(255,255,255,0.2); border:none; color:white; padding:2px 8px; border-radius:4px; cursor:pointer;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body" style="padding: 0; max-height: 300px; overflow-y: auto;">
                    <?php if(empty($imports)): ?>
                    <p class="text-muted p-3 mb-0 text-center">No hay importaciones</p>
                    <?php else: ?>
                    <?php foreach($imports as $imp): ?>
                    <div class="import-row">
                        <div>
                            <i class="fas fa-file-csv" style="color: #4CAF50; margin-right: 10px;"></i>
                            <span style="font-size: 13px;"><?php echo htmlspecialchars($imp->filename); ?></span><br>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($imp->imported_at)); ?> · 
                                <?php echo $imp->total_meetings; ?> meetings · 
                                <?php echo $imp->total_participants; ?> part.
                            </small>
                        </div>
                        <span class="badge-id">#<?php echo $imp->id; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="nav-links">
        <a href="coding_zoom_audit.php"><i class="fas fa-search"></i> Auditoría Zoom</a>
        <a href="reporte_empresas.php"><i class="fas fa-building"></i> Reporte Empresas</a>
        <a href="reporte_cesce.php"><i class="fas fa-users"></i> Reporte CESCE</a>
    </div>
</main>
</body>
</html>
