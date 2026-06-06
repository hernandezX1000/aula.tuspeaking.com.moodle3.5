<?php
/**
 * Panel Admin Integrado Feedback - TuSpeaking
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$seccion = $_GET['s'] ?? 'dashboard';
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$filtro_profesor = $_GET['profesor'] ?? '';
$filtro_valoracion = $_GET['valoracion'] ?? '';
$filtro_email = $_GET['email'] ?? '';

$where = "submission_date >= '$desde' AND submission_date <= '$hasta 23:59:59'";
if ($filtro_profesor) $where .= " AND profesor = '".$conn->real_escape_string($filtro_profesor)."'";
if ($filtro_valoracion) $where .= " AND valoracion <= ".intval($filtro_valoracion);
if ($filtro_email) $where .= " AND email LIKE '%".$conn->real_escape_string($filtro_email)."%'";

$staff_emails = "('carmen@tuspeaking.com','hfernandez@tuspeaking.com','javier@tuspeaking.com','sergio@tuspeaking.com','miriam@tuspeaking.com')";
$where_no_staff = "$where AND email NOT IN $staff_emails";

// EXPORTACIONES
if (isset($_GET['export'])) {
    $datos = $conn->query("SELECT * FROM own_feedback_nps WHERE $where_no_staff ORDER BY submission_date DESC");
    if ($_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=feedback_'.date('Y-m-d').'.csv');
        echo "\xEF\xBB\xBF";
        echo "Fecha,Idioma,Profesor,Valoracion,Problema Conexion,Recibio Feedback,Comentarios,Email\n";
        while ($r = $datos->fetch_assoc()) {
            echo '"'.date('Y-m-d H:i', strtotime($r['submission_date'])).'","'.$r['idioma'].'","'.$r['profesor'].'","'.$r['valoracion'].'","'.$r['problema_conexion'].'","'.$r['recibio_feedback'].'","'.str_replace('"','""',$r['comentarios']).'","'.$r['email'].'"'."\n";
        }
        exit;
    }
    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=feedback_'.date('Y-m-d').'.xls');
        echo '<html><head><meta charset="utf-8"></head><body>';
        echo '<table border="1"><tr><th>Fecha</th><th>Idioma</th><th>Profesor</th><th>Valoracion</th><th>Problema</th><th>Feedback</th><th>Comentarios</th><th>Email</th></tr>';
        while ($r = $datos->fetch_assoc()) {
            echo '<tr><td>'.date('Y-m-d H:i', strtotime($r['submission_date'])).'</td><td>'.$r['idioma'].'</td><td>'.$r['profesor'].'</td><td>'.$r['valoracion'].'</td><td>'.$r['problema_conexion'].'</td><td>'.$r['recibio_feedback'].'</td><td>'.htmlspecialchars($r['comentarios']).'</td><td>'.$r['email'].'</td></tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}

$stats = $conn->query("SELECT COUNT(*) as total, AVG(valoracion) as media, SUM(valoracion<=3) as alertas FROM own_feedback_nps WHERE $where_no_staff")->fetch_assoc();
$total_clases = $conn->query("SELECT COUNT(*) as t FROM mdl_i3code_acuityZoom WHERE acuity_datetime >= '$desde' AND acuity_datetime <= '$hasta 23:59:59' AND acuity_email IS NOT NULL AND acuity_email != ''")->fetch_assoc()['t'];
$total_enviados = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59'")->fetch_assoc()['t'];
$total_abiertos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND abierto_at IS NOT NULL")->fetch_assoc()['t'];
$total_respondidos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND respondido_at IS NOT NULL")->fetch_assoc()['t'];
$total_antiguos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_nps WHERE submission_date >= '$desde' AND submission_date <= '$hasta 23:59:59' AND (enviado_auto IS NULL OR enviado_auto = 0) AND email NOT IN $staff_emails")->fetch_assoc()['t'];

$tasa_apertura = $total_enviados > 0 ? round($total_abiertos * 100 / $total_enviados, 1) : 0;
$tasa_nuevo = $total_enviados > 0 ? round($total_respondidos * 100 / $total_enviados, 1) : 0;
$tasa_antiguo = $total_clases > 0 ? round($total_antiguos * 100 / $total_clases, 1) : 0;

$profesores = $conn->query("SELECT DISTINCT profesor FROM own_feedback_nps WHERE profesor != '' ORDER BY profesor");
$ranking = $conn->query("SELECT profesor, COUNT(*) as total, AVG(valoracion) as media, SUM(valoracion<=3) as alertas, SUM(valoracion>=9) as excelentes FROM own_feedback_nps WHERE $where_no_staff AND profesor != '' GROUP BY profesor ORDER BY media DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Feedback - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;display:flex;min-height:100vh}
        .sidebar{width:240px;background:linear-gradient(135deg,#008ba3,#00bcd4);padding:20px 0;position:fixed;height:100vh;overflow-y:auto}
        .sidebar-logo{color:#fff;font-size:22px;font-weight:700;padding:0 20px 20px;border-bottom:1px solid rgba(255,255,255,.2)}
        .sidebar-menu{list-style:none;margin-top:20px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:14px 20px;color:rgba(255,255,255,.8);text-decoration:none;transition:all .2s}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,.15);color:#fff}
        .sidebar-menu .material-icons{font-size:22px}
        .main{margin-left:240px;flex:1;padding:24px}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .header h1{color:#333;font-size:24px;display:flex;align-items:center;gap:8px}
        .filters{background:#fff;padding:16px;border-radius:8px;margin-bottom:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
        .filters input,.filters select{padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px}
        .filters button{background:#008ba3;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:4px}
        .filters button:hover{background:#007a91}
        .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
        .kpi{background:#fff;padding:20px;border-radius:12px;text-align:center}
        .kpi-value{font-size:32px;font-weight:700;color:#008ba3}
        .kpi-label{font-size:13px;color:#666;margin-top:4px}
        .kpi-sub{font-size:11px;color:#999;margin-top:4px}
        .kpi.success .kpi-value{color:#27ae60}
        .kpi.warning .kpi-value{color:#f39c12}
        .kpi.danger .kpi-value{color:#e74c3c}
        .card{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px}
        .card h3{color:#333;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .card h3 .material-icons{color:#008ba3}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;text-align:left;border-bottom:1px solid #eee}
        th{color:#666;font-weight:500;font-size:13px;background:#f9f9f9}
        tr:hover{background:#f5f5f5}
        .badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge-success{background:#e8f5e9;color:#27ae60}
        .badge-warning{background:#fff8e1;color:#f39c12}
        .badge-danger{background:#ffebee;color:#e74c3c}
        .badge-info{background:#e3f2fd;color:#1976d2}
        .export-btns{display:flex;gap:8px}
        .btn-export{padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:500;display:flex;align-items:center;gap:4px}
        .btn-csv{background:#27ae60;color:#fff}
        .btn-excel{background:#217346;color:#fff}
        .btn-csv:hover{background:#2ecc71}
        .btn-excel:hover{background:#1e6b3e}
        .progress{height:8px;background:#eee;border-radius:4px;overflow:hidden}
        .progress-fill{height:100%;background:#008ba3}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        @media(max-width:900px){.grid-2{grid-template-columns:1fr}}
        @media(max-width:768px){.sidebar{width:60px}.sidebar-logo,.sidebar-menu span:not(.material-icons){display:none}.main{margin-left:60px}}
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-logo">tuSpeaking</div>
        <ul class="sidebar-menu">
            <li><a href="?s=dashboard&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='dashboard'?'active':''?>"><span class="material-icons">dashboard</span><span>Dashboard</span></a></li>
            <li><a href="?s=feedbacks&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='feedbacks'?'active':''?>"><span class="material-icons">rate_review</span><span>Feedbacks</span></a></li>
            <li><a href="?s=profesores&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='profesores'?'active':''?>"><span class="material-icons">school</span><span>Profesores</span></a></li>
            <li><a href="?s=exportar&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='exportar'?'active':''?>"><span class="material-icons">download</span><span>Exportar</span></a></li>
            <li><a href="?s=config&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='config'?'active':''?>"><span class="material-icons">settings</span><span>Configuración</span></a></li>
        </ul>
    </nav>
    
    <main class="main">
        <form class="filters" method="GET">
            <input type="hidden" name="s" value="<?=$seccion?>">
            <span class="material-icons" style="color:#666">date_range</span>
            <input type="date" name="desde" value="<?=$desde?>">
            <span>a</span>
            <input type="date" name="hasta" value="<?=$hasta?>">
            <button type="submit"><span class="material-icons" style="font-size:18px">filter_alt</span> Filtrar</button>
        </form>

<?php if ($seccion == 'dashboard'): ?>
        <div class="header"><h1><span class="material-icons">dashboard</span> Dashboard</h1></div>
        <div class="kpis">
            <div class="kpi"><div class="kpi-value"><?=number_format($stats['total'])?></div><div class="kpi-label">Total Feedbacks</div></div>
            <div class="kpi <?=$stats['media']>=8?'success':($stats['media']>=6?'warning':'danger')?>"><div class="kpi-value"><?=number_format($stats['media'],1)?></div><div class="kpi-label">Valoracion Media</div></div>
            <div class="kpi <?=$stats['alertas']>0?'danger':''?>"><div class="kpi-value"><?=intval($stats['alertas'])?></div><div class="kpi-label">Alertas (<=3)</div></div>
            <div class="kpi <?=$tasa_nuevo>=15?'success':($tasa_nuevo>=8?'warning':'danger')?>"><div class="kpi-value"><?=$tasa_nuevo?>%</div><div class="kpi-label">Tasa Respuesta</div><div class="kpi-sub">Sistema nuevo</div></div>
        </div>
        <div class="grid-2">
            <div class="card">
                <h3><span class="material-icons">speed</span> Sistema Nuevo (30 min)</h3>
                <table>
                    <tr><td>Emails enviados</td><td><strong><?=number_format($total_enviados)?></strong></td></tr>
                    <tr><td>Abiertos</td><td><strong><?=number_format($total_abiertos)?></strong> <span class="badge badge-info"><?=$tasa_apertura?>%</span></td></tr>
                    <tr><td>Respondidos</td><td><strong><?=number_format($total_respondidos)?></strong> <span class="badge <?=$tasa_nuevo>=15?'badge-success':'badge-warning'?>"><?=$tasa_nuevo?>%</span></td></tr>
                </table>
            </div>
            <div class="card">
                <h3><span class="material-icons">history</span> Sistema Antiguo (24h)</h3>
                <table>
                    <tr><td>Total clases</td><td><strong><?=number_format($total_clases)?></strong></td></tr>
                    <tr><td>Feedbacks recibidos</td><td><strong><?=number_format($total_antiguos)?></strong></td></tr>
                    <tr><td>Tasa respuesta</td><td><span class="badge <?=$tasa_antiguo>=15?'badge-success':($tasa_antiguo>=8?'badge-warning':'badge-danger')?>"><?=$tasa_antiguo?>%</span></td></tr>
                </table>
            </div>
        </div>
        <div class="card" style="background:#e3f2fd">
            <h3><span class="material-icons">flag</span> Objetivo</h3>
            <p>Conseguir tasa de respuesta del <strong>15-20%</strong> para muestra representativa (~100 feedbacks/mes).</p>
            <div style="margin-top:12px">
                <div class="progress" style="height:20px;background:#bbdefb"><div class="progress-fill" style="width:<?=min($tasa_nuevo/20*100,100)?>%;background:#1976d2"></div></div>
                <small style="color:#1976d2"><?=$tasa_nuevo?>% de 20% objetivo</small>
            </div>
        </div>

<?php elseif ($seccion == 'feedbacks'): ?>
        <div class="header"><h1><span class="material-icons">rate_review</span> Feedbacks</h1></div>
        <form class="filters" method="GET" style="margin-top:-10px">
            <input type="hidden" name="s" value="feedbacks">
            <input type="hidden" name="desde" value="<?=$desde?>">
            <input type="hidden" name="hasta" value="<?=$hasta?>">
            <input type="text" name="email" value="<?=htmlspecialchars($filtro_email)?>" placeholder="Filtrar por email...">
            <select name="profesor"><option value="">Todos los profesores</option><?php $profesores->data_seek(0); while($p=$profesores->fetch_assoc()): ?><option <?=$filtro_profesor==$p['profesor']?'selected':''?>><?=htmlspecialchars($p['profesor'])?></option><?php endwhile; ?></select>
            <select name="valoracion"><option value="">Todas las valoraciones</option><option value="3" <?=$filtro_valoracion=='3'?'selected':''?>>Alertas (<=3)</option><option value="6" <?=$filtro_valoracion=='6'?'selected':''?>>Bajas (<=6)</option></select>
            <button type="submit"><span class="material-icons" style="font-size:18px">search</span> Buscar</button>
        </form>
        <div class="card">
            <?php $feedbacks = $conn->query("SELECT * FROM own_feedback_nps WHERE $where_no_staff ORDER BY submission_date DESC LIMIT 100"); ?>
            <table>
                <thead><tr><th>Fecha</th><th>Profesor</th><th>Valor.</th><th>Email</th><th>Comentarios</th></tr></thead>
                <tbody>
                    <?php while($f = $feedbacks->fetch_assoc()): ?>
                    <tr>
                        <td><?=date('d/m/Y H:i', strtotime($f['submission_date']))?></td>
                        <td><?=htmlspecialchars($f['profesor'])?></td>
                        <td><span class="badge <?=$f['valoracion']<=3?'badge-danger':($f['valoracion']<=6?'badge-warning':'badge-success')?>"><?=$f['valoracion']?></span></td>
                        <td style="font-size:12px"><?=htmlspecialchars($f['email'])?></td>
                        <td style="max-width:300px;font-size:12px;color:#666"><?=htmlspecialchars(substr($f['comentarios'],0,100))?><?=strlen($f['comentarios'])>100?'...':''?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

<?php elseif ($seccion == 'profesores'): ?>
        <div class="header"><h1><span class="material-icons">school</span> Ranking Profesores</h1></div>
        <div class="card">
            <table>
                <thead><tr><th>#</th><th>Profesor</th><th>Feedbacks</th><th>Media</th><th>Excelentes (>=9)</th><th>Alertas (<=3)</th><th>Rendimiento</th></tr></thead>
                <tbody>
                    <?php $pos=1; $ranking->data_seek(0); while($r = $ranking->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?=$pos++?></strong></td>
                        <td><?=htmlspecialchars($r['profesor'])?></td>
                        <td><?=$r['total']?></td>
                        <td><span class="badge <?=$r['media']>=8?'badge-success':($r['media']>=6?'badge-warning':'badge-danger')?>"><?=number_format($r['media'],1)?></span></td>
                        <td><span class="badge badge-success"><?=$r['excelentes']?></span></td>
                        <td><?php if($r['alertas']>0): ?><span class="badge badge-danger"><?=$r['alertas']?></span><?php else: ?>-<?php endif; ?></td>
                        <td style="width:150px"><div class="progress"><div class="progress-fill" style="width:<?=$r['media']*10?>%;background:<?=$r['media']>=8?'#27ae60':($r['media']>=6?'#f39c12':'#e74c3c')?>"></div></div></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

<?php elseif ($seccion == 'exportar'): ?>
        <div class="header"><h1><span class="material-icons">download</span> Exportar Datos</h1></div>
        <div class="card">
            <h3><span class="material-icons">filter_alt</span> Filtros de Exportacion</h3>
            <form method="GET" style="display:flex;flex-direction:column;gap:16px;max-width:400px">
                <input type="hidden" name="s" value="exportar">
                <div><label style="display:block;margin-bottom:4px;color:#666;font-size:13px">Desde</label><input type="date" name="desde" value="<?=$desde?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
                <div><label style="display:block;margin-bottom:4px;color:#666;font-size:13px">Hasta</label><input type="date" name="hasta" value="<?=$hasta?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
                <div><label style="display:block;margin-bottom:4px;color:#666;font-size:13px">Email cliente (opcional)</label><input type="text" name="email" value="<?=htmlspecialchars($filtro_email)?>" placeholder="@empresa.com" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
                <div><label style="display:block;margin-bottom:4px;color:#666;font-size:13px">Profesor (opcional)</label><select name="profesor" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"><option value="">Todos</option><?php $profesores->data_seek(0); while($p=$profesores->fetch_assoc()): ?><option <?=$filtro_profesor==$p['profesor']?'selected':''?>><?=htmlspecialchars($p['profesor'])?></option><?php endwhile; ?></select></div>
                <div class="export-btns" style="margin-top:8px">
                    <button type="submit" name="export" value="csv" class="btn-export btn-csv"><span class="material-icons" style="font-size:18px">description</span> CSV</button>
                    <button type="submit" name="export" value="excel" class="btn-export btn-excel"><span class="material-icons" style="font-size:18px">table_chart</span> Excel</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3><span class="material-icons">info</span> Datos a Exportar</h3>
            <p style="color:#666">Se exportaran <strong><?=number_format($stats['total'])?> registros</strong> del periodo <?=$desde?> a <?=$hasta?>.</p>
        </div>

<?php elseif ($seccion == 'config'): ?>
        <div class="header"><h1><span class="material-icons">settings</span> Configuración</h1></div>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
            foreach ($_POST as $clave => $valor) {
                if ($clave != 'guardar_config') {
                    $conn->query("UPDATE own_feedback_config SET valor = '".$conn->real_escape_string($valor)."' WHERE clave = '".$conn->real_escape_string($clave)."'");
                }
            }
            echo '<div style="background:#e8f5e9;color:#27ae60;padding:12px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:8px;"><span class="material-icons">check_circle</span> Configuracion guardada correctamente</div>';
        }
        $configs = $conn->query("SELECT * FROM own_feedback_config ORDER BY id");
        ?>
        <div class="card">
            <h3><span class="material-icons">tune</span> Reglas de Envio</h3>
            <form method="POST">
                <table>
                    <thead><tr><th>Parametro</th><th>Valor</th><th>Descripcion</th></tr></thead>
                    <tbody>
                    <?php while ($cfg = $configs->fetch_assoc()): ?>
                        <tr>
                            <td><code><?=htmlspecialchars($cfg['clave'])?></code></td>
                            <td style="width:150px">
                                <?php if ($cfg['clave'] == 'habilitar_recordatorio_24h'): ?>
                                <select name="<?=htmlspecialchars($cfg['clave'])?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px">
                                    <option value="1" <?=$cfg['valor']=='1'?'selected':''?>>Si</option>
                                    <option value="0" <?=$cfg['valor']=='0'?'selected':''?>>No</option>
                                </select>
                                <?php else: ?>
                                <input type="text" name="<?=htmlspecialchars($cfg['clave'])?>" value="<?=htmlspecialchars($cfg['valor'])?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px">
                                <?php endif; ?>
                            </td>
                            <td style="color:#666;font-size:13px"><?=htmlspecialchars($cfg['descripcion'])?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <div style="margin-top:20px">
                    <button type="submit" name="guardar_config" value="1" style="background:#008ba3;color:#fff;border:none;padding:12px 24px;border-radius:6px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:14px"><span class="material-icons" style="font-size:18px">save</span> Guardar cambios</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3><span class="material-icons">info</span> Explicacion de Reglas</h3>
            <table>
                <tr><td><strong>max_feedbacks_semana</strong></td><td>Limita cuantos emails de feedback recibe un alumno por semana. Evita saturar a alumnos con muchas clases.</td></tr>
                <tr><td><strong>minutos_espera_envio</strong></td><td>Tiempo despues de terminar la clase para enviar el email. 30 min es optimo.</td></tr>
                <tr><td><strong>minutos_limite_envio</strong></td><td>Si pasaron mas minutos que este valor, no se envia (clase muy antigua).</td></tr>
                <tr><td><strong>habilitar_recordatorio_24h</strong></td><td>Si el alumno no responde, enviar recordatorio al dia siguiente.</td></tr>
                <tr><td><strong>email_alertas</strong></td><td>Direccion que recibe alertas cuando hay valoraciones <=3.</td></tr>
            </table>
        </div>

<?php endif; ?>
    </main>
</body>
</html>
<?php $conn->close(); ?>
