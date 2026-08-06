<?php
require('../config.php');
require_once($CFG->dirroot.'/secrets.php');   // Credenciales Acuity externalizadas (fuera de git)
require_once($CFG->libdir.'/adminlib.php');

// Verificar admin
$admins = get_admins();
$isadmin = false;
foreach($admins as $admin){
    if ($admin->id == $USER->id){ $isadmin = true; break; }
}
if (!$isadmin){
    header("Location: /app/moodle");
    die();
}

// Credenciales API Acuity (externalizadas en secrets.php, fuera de git)
$acuityUserID = ACUITY_USER_ID;
$acuityApiKey = ACUITY_API_KEY;

// Obtener tipos de Acuity desde BD
$conn = new PDO("mysql:host={$CFG->dbhost};dbname={$CFG->dbname};charset=utf8", $CFG->dbuser, $CFG->dbpass);
$acuityTypes = $conn->query("SELECT acuityid, acuitytype FROM own_acuitytypes ORDER BY acuitytype DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

// Si se solicita consulta
$appointments = [];
$selectedType = $_GET['type'] ?? '';
$error = '';

if ($selectedType) {
    $url = "https://acuityscheduling.com/api/v1/appointments?appointmentTypeID=" . intval($selectedType);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$acuityUserID:$acuityApiKey");
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($result, true);
        if (is_array($data)) {
            usort($data, function($a, $b) {
                return strcmp($a['datetime'] ?? '', $b['datetime'] ?? '');
            });
            
            $seen = [];
            foreach ($data as $apt) {
                $location = $apt['location'] ?? '';
                $url = '';
                if (preg_match('/(https:\/\/[^\s]+zoom\.us[^\s]+)/', $location, $matches)) {
                    $url = $matches[1];
                }
                if ($url && !isset($seen[$url])) {
                    $seen[$url] = true;
                    $fecha = substr($apt['datetime'] ?? '', 0, 10);
                    $hora = $apt['time'] ?? '';
                    $dia = date('l', strtotime($fecha));
                    $appointments[] = [
                        'fecha' => $fecha,
                        'dia' => $dia,
                        'hora' => $hora,
                        'url' => $url
                    ];
                }
            }
        }
    } else {
        $error = "Error al consultar Acuity (HTTP $httpCode)";
    }
}

if (isset($_GET['export']) && $selectedType && count($appointments) > 0) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zoom_urls_' . $selectedType . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Fecha', 'Dia', 'Hora', 'Zoom_URL']);
    foreach ($appointments as $apt) {
        fputcsv($output, [$apt['fecha'], $apt['dia'], $apt['hora'], $apt['url']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URLs Zoom - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root{--primary:#008ba3;--secondary:#00bcd4;--dark:#454545;--light:#f5f5f5;--success:#27ae60;--warning:#f39c12}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:var(--light);min-height:100vh}
        .header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;padding:20px 30px;display:flex;justify-content:space-between;align-items:center}
        .header h1{font-size:22px;font-weight:500;display:flex;align-items:center;gap:10px}
        .header a{color:white;text-decoration:none;display:flex;align-items:center;gap:5px;opacity:0.9}
        .header a:hover{opacity:1}
        .container{max-width:1200px;margin:30px auto;padding:0 20px}
        .card{background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:25px;margin-bottom:20px}
        .form-row{display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap}
        .form-group{flex:1;min-width:250px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:var(--dark)}
        .form-group select,.form-group input{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px}
        .btn{padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
        .btn-primary{background:var(--primary);color:white}
        .btn-primary:hover{background:var(--secondary)}
        .btn-success{background:var(--success);color:white}
        .btn-warning{background:var(--warning);color:white}
        .btn-sm{padding:6px 12px;font-size:12px}
        .results{margin-top:20px}
        .results-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
        .results-header h3{color:var(--dark)}
        .results-actions{display:flex;gap:10px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee}
        th{background:var(--light);font-weight:600;color:var(--dark)}
        tr:hover{background:#f9f9f9}
        .url-cell{font-family:monospace;font-size:12px;max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .url-cell a{color:var(--primary)}
        .copy-btn{background:none;border:1px solid #ddd;padding:4px 8px;border-radius:3px;cursor:pointer;font-size:11px}
        .copy-btn:hover{background:var(--light)}
        .badge{display:inline-block;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:500}
        .badge-tue{background:#e3f2fd;color:#1565c0}
        .badge-thu{background:#f3e5f5;color:#7b1fa2}
        .stats{display:flex;gap:20px;margin-bottom:15px}
        .stat{background:var(--light);padding:10px 15px;border-radius:4px}
        .stat-value{font-size:24px;font-weight:600;color:var(--primary)}
        .stat-label{font-size:12px;color:#666}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-content{background:white;border-radius:8px;width:90%;max-width:800px;max-height:80vh;overflow:hidden;display:flex;flex-direction:column}
        .modal-header{background:var(--primary);color:white;padding:15px 20px;display:flex;justify-content:space-between;align-items:center}
        .modal-header h3{font-weight:500}
        .modal-close{background:none;border:none;color:white;font-size:24px;cursor:pointer}
        .modal-body{padding:20px;overflow-y:auto;flex:1}
        .modal-body textarea{width:100%;height:300px;font-family:monospace;font-size:12px;padding:10px;border:1px solid #ddd;border-radius:4px}
        .modal-footer{padding:15px 20px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:10px}
        .alert{padding:12px 15px;border-radius:4px;margin-bottom:15px}
        .alert-error{background:#ffebee;color:#c62828;border-left:4px solid #f44336}
        .alert-info{background:#e3f2fd;color:#1565c0;border-left:4px solid #2196f3}
    </style>
</head>
<body>
    <div class="header">
        <h1><span class="material-icons">video_call</span> URLs Zoom desde Acuity</h1>
        <a href="admin.php"><span class="material-icons">arrow_back</span> Volver al Panel</a>
    </div>
    
    <div class="container">
        <div class="card">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label>Seleccionar Appointment Type</label>
                        <select name="type" id="typeSelect">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach($acuityTypes as $t): ?>
                            <option value="<?=$t['acuityid']?>" <?=$selectedType==$t['acuityid']?'selected':''?>><?=htmlspecialchars($t['acuitytype'])?> (<?=$t['acuityid']?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0">
                        <label>&nbsp;</label>
                        <input type="text" name="type_manual" placeholder="O introduce ID manual" style="width:150px" value="<?=$selectedType && !in_array($selectedType, array_column($acuityTypes, 'acuityid')) ? $selectedType : ''?>">
                    </div>
                    <div style="flex:0">
                        <button type="submit" class="btn btn-primary"><span class="material-icons">search</span> Consultar</button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?=$error?></div>
        <?php endif; ?>
        
        <?php if($selectedType && count($appointments) > 0): ?>
        <div class="card results">
            <div class="stats">
                <div class="stat">
                    <div class="stat-value"><?=count($appointments)?></div>
                    <div class="stat-label">Clases únicas</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?=count(array_filter($appointments, function($a) { return $a['dia']=='Tuesday'; }))?></div>
                    <div class="stat-label">Martes</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?=count(array_filter($appointments, function($a) { return $a['dia']=='Thursday'; }))?></div>
                    <div class="stat-label">Jueves</div>
                </div>
            </div>
            
            <div class="results-header">
                <h3>URLs de Zoom</h3>
                <div class="results-actions">
                    <button class="btn btn-warning" onclick="document.getElementById('modal-copy').classList.add('active')"><span class="material-icons">content_copy</span> Copiar Todo</button>
                    <a href="?type=<?=$selectedType?>&export=1" class="btn btn-success"><span class="material-icons">download</span> Exportar CSV</a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Hora</th>
                        <th>URL Zoom</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach($appointments as $apt): ?>
                    <tr>
                        <td><?=$i++?></td>
                        <td><?=$apt['fecha']?></td>
                        <td><span class="badge <?=$apt['dia']=='Tuesday'?'badge-tue':'badge-thu'?>"><?=$apt['dia']?></span></td>
                        <td><?=$apt['hora']?></td>
                        <td class="url-cell"><a href="<?=$apt['url']?>" target="_blank"><?=$apt['url']?></a></td>
                        <td><button class="copy-btn" onclick="copyUrl(this, '<?=$apt['url']?>')">Copiar</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="modal" id="modal-copy">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Copiar URLs</h3>
                    <button class="modal-close" onclick="document.getElementById('modal-copy').classList.remove('active')">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom:10px;color:#666">Selecciona el formato y copia:</p>
                    <div style="margin-bottom:10px">
                        <button class="btn btn-sm btn-primary" onclick="setFormat('table')">Tabla</button>
                        <button class="btn btn-sm btn-primary" onclick="setFormat('urls')">Solo URLs</button>
                        <button class="btn btn-sm btn-primary" onclick="setFormat('csv')">CSV</button>
                    </div>
                    <textarea id="copyContent" readonly><?php
                        $lines = "Fecha\tDía\tHora\tURL\n";
                        foreach($appointments as $apt) {
                            $lines .= "{$apt['fecha']}\t{$apt['dia']}\t{$apt['hora']}\t{$apt['url']}\n";
                        }
                        echo $lines;
                    ?></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success" onclick="copyAll()"><span class="material-icons">content_copy</span> Copiar al Portapapeles</button>
                </div>
            </div>
        </div>
        <?php elseif($selectedType): ?>
        <div class="alert alert-info">No se encontraron citas para este Appointment Type.</div>
        <?php endif; ?>
    </div>
    
    <script>
    const appointments = <?=json_encode($appointments)?>;
    
    function setFormat(format) {
        let content = '';
        if (format === 'table') {
            content = "Fecha\tDía\tHora\tURL\n";
            appointments.forEach(a => content += a.fecha + "\t" + a.dia + "\t" + a.hora + "\t" + a.url + "\n");
        } else if (format === 'urls') {
            appointments.forEach(a => content += a.url + "\n");
        } else if (format === 'csv') {
            content = "Fecha,Día,Hora,URL\n";
            appointments.forEach(a => content += a.fecha + "," + a.dia + "," + a.hora + "," + a.url + "\n");
        }
        document.getElementById('copyContent').value = content;
    }
    
    function copyUrl(btn, url) {
        navigator.clipboard.writeText(url).then(function() {
            btn.textContent = '✓';
            setTimeout(function() { btn.textContent = 'Copiar'; }, 1500);
        });
    }
    
    function copyAll() {
        var textarea = document.getElementById('copyContent');
        textarea.select();
        navigator.clipboard.writeText(textarea.value).then(function() {
            alert('¡Copiado al portapapeles!');
        });
    }
    
    document.querySelector('form').addEventListener('submit', function(e) {
        var manual = document.querySelector('input[name="type_manual"]').value;
        if (manual) {
            document.getElementById('typeSelect').name = '';
            document.querySelector('input[name="type_manual"]').name = 'type';
        }
    });
    </script>
</body>
</html>
