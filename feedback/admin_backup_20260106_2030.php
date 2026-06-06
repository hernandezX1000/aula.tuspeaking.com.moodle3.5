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
    if ($_GET["export"] == "pdf") {
        require_once(__DIR__ . "/../lib/tcpdf/tcpdf.php");
        $pdf = new TCPDF("L", "mm", "A4", true, "UTF-8");
        $pdf->SetCreator("tuSpeaking");
        $pdf->SetTitle("Feedback NPS");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont("helvetica", "B", 16);
        $pdf->SetTextColor(0, 139, 163);
        $pdf->Cell(0, 10, "Reporte Feedback NPS - tuSpeaking", 0, 1, "C");
        $pdf->SetFont("helvetica", "", 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, "Periodo: $desde a $hasta", 0, 1, "C");
        $pdf->Ln(5);
        $pdf->SetFont("helvetica", "B", 9);
        $pdf->SetFillColor(0, 139, 163);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(35, 8, "Fecha", 1, 0, "C", true);
        $pdf->Cell(25, 8, "Idioma", 1, 0, "C", true);
        $pdf->Cell(40, 8, "Profesor", 1, 0, "C", true);
        $pdf->Cell(15, 8, "Valor", 1, 0, "C", true);
        $pdf->Cell(60, 8, "Email", 1, 0, "C", true);
        $pdf->Cell(97, 8, "Comentarios", 1, 1, "C", true);
        $pdf->SetFont("helvetica", "", 8);
        $pdf->SetTextColor(0, 0, 0);
        $alt = false;
        while ($r = $datos->fetch_assoc()) {
            $pdf->SetFillColor($alt ? 245 : 255, $alt ? 245 : 255, $alt ? 245 : 255);
            $pdf->Cell(35, 7, date("d/m/Y H:i", strtotime($r["submission_date"])), 1, 0, "C", true);
            $pdf->Cell(25, 7, $r["idioma"], 1, 0, "C", true);
            $pdf->Cell(40, 7, substr($r["profesor"], 0, 20), 1, 0, "L", true);
            $color = $r["valoracion"] <= 3 ? array(231,76,60) : ($r["valoracion"] <= 6 ? array(243,156,18) : array(39,174,96));
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->Cell(15, 7, $r["valoracion"], 1, 0, "C", true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(60, 7, substr($r["email"], 0, 30), 1, 0, "L", true);
            $pdf->Cell(97, 7, substr($r["comentarios"], 0, 50), 1, 1, "L", true);
            $alt = !$alt;
        }
        $pdf->Output("feedback_".date("Y-m-d").".pdf", "D");
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
$ranking = $conn->query("SELECT p.profesor, p.total, p.media, p.alertas, p.excelentes, COALESCE(h.media_anterior, p.media) as media_anterior, (p.media - COALESCE(h.media_anterior, p.media)) as tendencia FROM (SELECT profesor, COUNT(*) as total, AVG(valoracion) as media, SUM(valoracion<=3) as alertas, SUM(valoracion>=9) as excelentes FROM own_feedback_nps WHERE $where_no_staff AND profesor != '' GROUP BY profesor) p LEFT JOIN (SELECT profesor, AVG(valoracion) as media_anterior FROM own_feedback_nps WHERE submission_date >= DATE_SUB('$desde', INTERVAL 90 DAY) AND submission_date < '$desde' AND profesor != '' GROUP BY profesor) h ON p.profesor = h.profesor ORDER BY p.media DESC");
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
            <li><a href="?s=empresas&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='empresas'?'active':''?>"><span class="material-icons">business</span><span>Empresas</span></a></li>
            <li><a href="?s=teams&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='teams'?'active':''?>"><span class="material-icons">groups</span><span>Teams</span></a></li>
            <li><a href="?s=enlaces&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='enlaces'?'active':''?>"><span class="material-icons">link</span><span>Enlaces</span></a></li>
            <li><a href="?s=conversion&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='conversion'?'active':''?>"><span class="material-icons">trending_up</span><span>Conversión</span></a></li>
            <li><a href="?s=auditoria&desde=<?=$desde?>&hasta=<?=$hasta?>" class="<?=$seccion=='auditoria'?'active':''?>"><span class="material-icons">verified</span><span>Auditoría</span></a></li>
            <li><a href="docs.php" target="_blank" class=""><span class="material-icons">menu_book</span><span>Documentación</span></a></li>
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
                <thead><tr><th>#</th><th>Profesor</th><th>Feedbacks</th><th>Media</th><th>Excelentes (>=9)</th><th>Alertas (<=3)</th><th>Rendimiento</th><th>Tendencia</th><th>Relevancia</th></tr></thead>
                <tbody>
                    <?php $pos=1; $ranking->data_seek(0); while($r = $ranking->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?=$pos++?></strong></td>
                        <td><?=htmlspecialchars($r['profesor'])?></td>
                        <td><?=$r['total']?></td>
                        <td><span class="badge <?=$r['media']>=8?'badge-success':($r['media']>=6?'badge-warning':'badge-danger')?>"><?=number_format($r['media'],1)?></span></td>
                        <td><span class="badge badge-success"><?=$r['excelentes']?></span></td>
                        <td><?php if($r['alertas']>0): ?><span class="badge badge-danger"><?=$r['alertas']?></span><?php else: ?>-<?php endif; ?></td>
                        <td style="width:120px"><div class="progress"><div class="progress-fill" style="width:<?=$r["media"]*10?>%;background:<?=$r['media']>=8?'#27ae60':($r['media']>=6?'#f39c12':'#e74c3c')?>"></div></div></td><td><?php if($r["tendencia"]>0.3): ?><span style="color:#27ae60" title="Subió <?=number_format($r["tendencia"],2)?>">▲</span><?php elseif($r["tendencia"]<-0.3): ?><span style="color:#e74c3c" title="Bajó <?=number_format(abs($r["tendencia"]),2)?>">▼</span><?php else: ?><span style="color:#999" title="Estable">●</span><?php endif; ?></td><td><?php if($r["total"]>=100): ?><span class="badge badge-success">Alta</span><?php elseif($r["total"]>=30): ?><span class="badge badge-info">Media</span><?php else: ?><span class="badge badge-warning">Baja (<?=$r["total"]?>/30)</span><?php endif; ?></td>
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
                    <button type="submit" name="export" value="pdf" class="btn-export" style="background:#e74c3c;color:#fff;"><span class="material-icons" style="font-size:18px">picture_as_pdf</span> PDF</button>
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




<?php elseif ($seccion == 'empresas'): ?>
        <!-- EMPRESAS - INDICADORES DE GARANTÍA -->
        <div class="header">
            <h1><span class="material-icons">business</span> Empresas - Indicadores de Garantía</h1>
        </div>
        
        <?php
        // Filtro de categorías
        $categorias = $conn->query("SELECT id, name FROM mdl_course_categories WHERE id >= 470 AND visible = 1 ORDER BY name");
        $filtro_cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
        ?>
        <div class="card" style="padding:12px;margin-bottom:16px;">
            <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="s" value="empresas">
                <span class="material-icons" style="color:#666;">filter_list</span>
                <select name="cat" style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;min-width:250px;" onchange="this.form.submit()">
                    <option value="0">Todas las categorías (por dominio email)</option>
                    <?php while($c = $categorias->fetch_assoc()): ?>
                    <option value="<?=$c['id']?>" <?=$filtro_cat==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
                    <?php endwhile; ?>
                </select>
                <input type="date" name="desde" value="<?=$desde?>" style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                <span>a</span>
                <input type="date" name="hasta" value="<?=$hasta?>" style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                <button type="submit" style="padding:8px 16px;background:#008ba3;color:white;border:none;border-radius:4px;cursor:pointer;">Filtrar</button>
            </form>
        </div>
        
        <div class="card" style="background:linear-gradient(135deg,#fff8e1,#ffffff);border-left:4px solid #f39c12;">
            <h3><span class="material-icons">gavel</span> ¿Por qué estos indicadores?</h3>
            <p style="color:#666;">Para que una empresa <strong>no pueda reclamar insatisfacción</strong>, necesitamos demostrar:</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:12px;">
                <div style="background:#fff;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:#008ba3;">≥80%</div>
                    <div style="font-size:12px;color:#666;">Alumnos cubiertos</div>
                </div>
                <div style="background:#fff;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:#27ae60;">≥15%</div>
                    <div style="font-size:12px;color:#666;">Tasa respuesta</div>
                </div>
                <div style="background:#fff;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:#27ae60;">≥7.5</div>
                    <div style="font-size:12px;color:#666;">Media valoración</div>
                </div>
                <div style="background:#fff;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:#e74c3c;"><5%</div>
                    <div style="font-size:12px;color:#666;">Alertas (≤3)</div>
                </div>
            </div>
        </div>
        
        <?php
        $empresas_data = [];
        
        // Si se seleccionó una categoría específica
        if ($filtro_cat > 0) {
            $cat_info = $conn->query("SELECT name FROM mdl_course_categories WHERE id = $filtro_cat")->fetch_assoc();
            
            // Alumnos matriculados (solo estudiantes, sin profesores)
            $sql_alumnos = "SELECT COUNT(DISTINCT u.id) as total
                FROM mdl_user u
                INNER JOIN mdl_role_assignments ra ON ra.userid = u.id
                INNER JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                INNER JOIN mdl_course c ON c.id = ctx.instanceid
                INNER JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
                WHERE c.category = $filtro_cat
                AND u.id NOT IN (SELECT teacher_id FROM teacher_zoom_map)";
            $result = $conn->query($sql_alumnos);
            $total_alumnos = $result ? $result->fetch_assoc()['total'] : 0;
            
            // Feedbacks de alumnos de esta categoría
            $sql_fb = "SELECT 
                COUNT(DISTINCT f.email) as alumnos_fb,
                COUNT(*) as feedbacks,
                ROUND(AVG(f.valoracion),1) as media,
                SUM(CASE WHEN f.valoracion <= 3 THEN 1 ELSE 0 END) as alertas
            FROM own_feedback_nps f
            INNER JOIN mdl_user u ON LOWER(f.email) COLLATE utf8mb4_general_ci = LOWER(u.email) COLLATE utf8mb4_general_ci
            INNER JOIN mdl_role_assignments ra ON ra.userid = u.id
            INNER JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            INNER JOIN mdl_course c ON c.id = ctx.instanceid
            INNER JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
            WHERE c.category = $filtro_cat
            AND f.submission_date >= '$desde' AND f.submission_date <= '$hasta 23:59:59'";
            $result = $conn->query($sql_fb);
            $r_fb = $result ? $result->fetch_assoc() : ['alumnos_fb'=>0,'feedbacks'=>0,'media'=>null,'alertas'=>0];
            
            // Clases (aproximado por dominio de emails de alumnos)
            $sql_clases = "SELECT COUNT(*) as clases
                FROM mdl_i3code_acuityZoom a
                WHERE a.acuity_datetime >= '$desde' AND a.acuity_datetime <= '$hasta 23:59:59'
                AND EXISTS (
                    SELECT 1 FROM mdl_user u
                    INNER JOIN mdl_role_assignments ra ON ra.userid = u.id
                    INNER JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    INNER JOIN mdl_course c ON c.id = ctx.instanceid
                    WHERE c.category = $filtro_cat
                    AND LOWER(a.acuity_email) = LOWER(u.email)
                )";
            $result = $conn->query($sql_clases);
            $total_clases = $result ? $result->fetch_assoc()['clases'] : 0;
            
            $empresas_data[] = [
                'id' => $filtro_cat,
                'dominio' => '',
                'nombre_empresa' => $cat_info['name'] ?? 'Categoría '.$filtro_cat,
                'categorias' => $filtro_cat,
                'objetivo_cobertura_alumnos' => 80,
                'objetivo_tasa_respuesta' => 15,
                'alerta_media_minima' => 7.5,
                'total_alumnos' => $total_alumnos ?: 0,
                'total_clases' => $total_clases ?: 0,
                'alumnos_con_feedback' => $r_fb['alumnos_fb'] ?: 0,
                'total_feedbacks' => $r_fb['feedbacks'] ?: 0,
                'media' => $r_fb['media'],
                'alertas' => $r_fb['alertas'] ?: 0
            ];
        } else {
            // Modo empresas (sin filtro de categoría)
            $empresas_config = $conn->query("SELECT * FROM own_feedback_empresas WHERE activo = 1");
            
            while ($e = $empresas_config->fetch_assoc()) {
                $dominio = $e['dominio'];
                $categorias_ids = $e['categorias_ids'];
                
                // Si tiene categorías asociadas, usar lógica por categoría
                if (!empty($categorias_ids)) {
                    // Alumnos matriculados en categorías (excluyendo profesores)
                    $sql_alumnos = "SELECT COUNT(DISTINCT u.id) as total
                        FROM mdl_user u
                        INNER JOIN mdl_role_assignments ra ON ra.userid = u.id
                        INNER JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                        INNER JOIN mdl_course c ON c.id = ctx.instanceid
                        INNER JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
                        WHERE c.category IN ($categorias_ids)
                        AND u.id NOT IN (SELECT teacher_id FROM teacher_zoom_map)";
                    $result = $conn->query($sql_alumnos);
                    $total_alumnos = $result ? $result->fetch_assoc()['total'] : 0;
                    
                    // Clases
                    if ($dominio == 'cesce.es') {
                        $tabla_clases = 'mdl_cesce_acuityZoom';
                    } else {
                        $tabla_clases = 'mdl_i3code_acuityZoom';
                    }
                    $sql_clases = "SELECT COUNT(*) as clases 
                        FROM $tabla_clases a
                        WHERE a.acuity_datetime >= '$desde' AND a.acuity_datetime <= '$hasta 23:59:59'
                        AND a.acuity_email LIKE '%@$dominio'";
                    $result = $conn->query($sql_clases);
                    $total_clases = $result ? $result->fetch_assoc()['clases'] : 0;
                    
                    // Feedbacks
                    $sql_fb = "SELECT 
                        COUNT(DISTINCT f.email) as alumnos_fb,
                        COUNT(*) as feedbacks,
                        ROUND(AVG(f.valoracion),1) as media,
                        SUM(CASE WHEN f.valoracion <= 3 THEN 1 ELSE 0 END) as alertas
                    FROM own_feedback_nps f
                    INNER JOIN mdl_user u ON LOWER(f.email) COLLATE utf8mb4_general_ci = LOWER(u.email) COLLATE utf8mb4_general_ci
                    INNER JOIN mdl_role_assignments ra ON ra.userid = u.id
                    INNER JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    INNER JOIN mdl_course c ON c.id = ctx.instanceid
                    INNER JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
                    WHERE c.category IN ($categorias_ids)
                    AND f.submission_date >= '$desde' AND f.submission_date <= '$hasta 23:59:59'";
                    $result = $conn->query($sql_fb);
                    $r_fb = $result ? $result->fetch_assoc() : ['alumnos_fb'=>0,'feedbacks'=>0,'media'=>null,'alertas'=>0];
                } else {
                    // Fallback por dominio
                    if ($dominio == 'cesce.es') {
                        $tabla_clases = 'mdl_cesce_acuityZoom';
                    } else {
                        $tabla_clases = 'mdl_i3code_acuityZoom';
                    }
                    $sql_clases = "SELECT COUNT(DISTINCT acuity_email) as alumnos, COUNT(*) as clases 
                        FROM $tabla_clases 
                        WHERE acuity_datetime >= '$desde' AND acuity_datetime <= '$hasta 23:59:59'
                        AND acuity_email LIKE '%@$dominio'";
                    $result = $conn->query($sql_clases);
                    $r_clases = $result ? $result->fetch_assoc() : ['alumnos'=>0,'clases'=>0];
                    $total_alumnos = $r_clases['alumnos'];
                    $total_clases = $r_clases['clases'];
                    
                    $sql_fb = "SELECT 
                        COUNT(DISTINCT email) as alumnos_fb,
                        COUNT(*) as feedbacks,
                        ROUND(AVG(valoracion),1) as media,
                        SUM(CASE WHEN valoracion <= 3 THEN 1 ELSE 0 END) as alertas
                    FROM own_feedback_nps 
                    WHERE SUBSTRING_INDEX(email, '@', -1) = '$dominio'
                    AND submission_date >= '$desde' AND submission_date <= '$hasta 23:59:59'";
                    $result = $conn->query($sql_fb);
                    $r_fb = $result ? $result->fetch_assoc() : ['alumnos_fb'=>0,'feedbacks'=>0,'media'=>null,'alertas'=>0];
                }
                
                $empresas_data[] = [
                    'id' => $e['id'],
                    'dominio' => $dominio,
                    'nombre_empresa' => $e['nombre_empresa'],
                    'categorias' => $categorias_ids,
                    'objetivo_cobertura_alumnos' => $e['objetivo_cobertura_alumnos'],
                    'objetivo_tasa_respuesta' => $e['objetivo_tasa_respuesta'],
                    'alerta_media_minima' => $e['alerta_media_minima'],
                    'total_alumnos' => $total_alumnos ?: 0,
                    'total_clases' => $total_clases ?: 0,
                    'alumnos_con_feedback' => $r_fb['alumnos_fb'] ?: 0,
                    'total_feedbacks' => $r_fb['feedbacks'] ?: 0,
                    'media' => $r_fb['media'],
                    'alertas' => $r_fb['alertas'] ?: 0
                ];
            }
        }
        
        // Ordenar por clases desc
        usort($empresas_data, function($a, $b) { return $b['total_clases'] - $a['total_clases']; });
        
        $total_garantizadas = 0;
        $total_empresas = 0;
        ?>
        
        <div class="card">
            <h3><span class="material-icons">assessment</span> Estado por Empresa</h3>
            <table>
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Alumnos</th>
                        <th>Clases</th>
                        <th>Feedbacks</th>
                        <th>Cobertura</th>
                        <th>Tasa Resp.</th>
                        <th>Media</th>
                        <th>Alertas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($empresas_data as $e): 
                    $total_empresas++;
                    $pct_cobertura = $e['total_alumnos'] > 0 ? round($e['alumnos_con_feedback'] * 100 / $e['total_alumnos']) : 0;
                    $pct_respuesta = $e['total_clases'] > 0 ? round($e['total_feedbacks'] * 100 / $e['total_clases'], 1) : 0;
                    $pct_alertas = $e['total_feedbacks'] > 0 ? round($e['alertas'] * 100 / $e['total_feedbacks'], 1) : 0;
                    
                    // Evaluar indicadores
                    $ok_cobertura = $pct_cobertura >= $e['objetivo_cobertura_alumnos'];
                    $ok_respuesta = $pct_respuesta >= $e['objetivo_tasa_respuesta'];
                    $ok_media = $e['media'] >= $e['alerta_media_minima'];
                    $ok_alertas = $pct_alertas < 5;
                    
                    $indicadores_ok = ($ok_cobertura ? 1 : 0) + ($ok_respuesta ? 1 : 0) + ($ok_media ? 1 : 0) + ($ok_alertas ? 1 : 0);
                    $garantizada = $indicadores_ok == 4;
                    if ($garantizada) $total_garantizadas++;
                ?>
                <tr>
                    <td><strong><?=htmlspecialchars($e['nombre_empresa'] ?: $e['dominio'])?></strong><br><small style="color:#999;"><?=$e['dominio']?></small></td>
                    <td><?=$e['total_alumnos']?></td>
                    <td><?=number_format($e['total_clases'])?></td>
                    <td><?=$e['total_feedbacks']?></td>
                    <td>
                        <span class="badge <?=$ok_cobertura ? 'badge-success' : 'badge-danger'?>"><?=$pct_cobertura?>%</span>
                        <small style="color:#999;">(<?=$e['alumnos_con_feedback']?>/<?=$e['total_alumnos']?>)</small>
                    </td>
                    <td><span class="badge <?=$ok_respuesta ? 'badge-success' : 'badge-danger'?>"><?=$pct_respuesta?>%</span></td>
                    <td><span class="badge <?=$ok_media ? 'badge-success' : 'badge-warning'?>"><?=$e['media'] ?: '-'?></span></td>
                    <td><span class="badge <?=$ok_alertas ? 'badge-success' : 'badge-danger'?>"><?=$pct_alertas?>%</span> <small>(<?=$e['alertas']?>)</small></td>
                    <td>
                        <?php if($garantizada): ?>
                            <span class="badge badge-success">✓ GARANTIZADA</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><?=$indicadores_ok?>/4</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="kpis">
            <div class="kpi <?=$total_garantizadas == $total_empresas ? 'success' : 'warning'?>">
                <div class="kpi-value"><?=$total_garantizadas?>/<?=$total_empresas?></div>
                <div class="kpi-label">Empresas Garantizadas</div>
            </div>
            <div class="kpi">
                <div class="kpi-value"><?=$total_empresas > 0 ? round($total_garantizadas * 100 / $total_empresas) : 0?>%</div>
                <div class="kpi-label">% Cobertura Legal</div>
            </div>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">lightbulb</span> Acciones para mejorar cobertura</h3>
            <table>
                <tr>
                    <td><span class="badge badge-danger">Cobertura baja</span></td>
                    <td>Enviar feedback a <strong>todos</strong> los alumnos, no solo a quienes tienen clase frecuente. Considerar encuesta trimestral adicional.</td>
                </tr>
                <tr>
                    <td><span class="badge badge-danger">Tasa respuesta baja</span></td>
                    <td>Activar recordatorio 24h. Simplificar formulario (1 click). Enviar en horario óptimo (30 min post-clase).</td>
                </tr>
                <tr>
                    <td><span class="badge badge-warning">Media en riesgo</span></td>
                    <td>Revisar profesores con alertas. Implementar seguimiento de quejas.</td>
                </tr>
                <tr>
                    <td><span class="badge badge-danger">Muchas alertas</span></td>
                    <td>Análisis detallado de comentarios negativos. Reunión con coordinación.</td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">add_business</span> Añadir Empresa</h3>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_empresa'])) {
                $stmt = $conn->prepare("INSERT IGNORE INTO own_feedback_empresas (dominio, nombre_empresa) VALUES (?, ?)");
                $stmt->bind_param("ss", $_POST['dominio'], $_POST['nombre']);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    echo '<div style="background:#e8f5e9;color:#27ae60;padding:12px;border-radius:8px;margin-bottom:12px;">Empresa añadida correctamente</div>';
                }
            }
            ?>
            <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label style="display:block;font-size:12px;color:#666;margin-bottom:4px;">Dominio email</label>
                    <input type="text" name="dominio" placeholder="empresa.com" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#666;margin-bottom:4px;">Nombre empresa</label>
                    <input type="text" name="nombre" placeholder="Nombre Empresa S.L." style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <button type="submit" name="nueva_empresa" value="1" style="padding:10px 16px;background:#008ba3;color:white;border:none;border-radius:4px;cursor:pointer;">Añadir</button>
            </form>
        </div>

<?php elseif ($seccion == 'teams'): ?>
        <!-- TEAMS -->
        <div class="header"><h1><span class="material-icons">groups</span> Grupos Teams</h1><a href="importar_teams.php" style="background:#27ae60;color:white;padding:10px 16px;border-radius:6px;text-decoration:none;font-size:14px;display:flex;align-items:center;gap:6px;"><span class="material-icons" style="font-size:18px;">upload_file</span> Importar Excel</a></div>
        
        <?php
        // Procesar formularios
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['nuevo_grupo'])) {
                $stmt = $conn->prepare("INSERT INTO own_feedback_teams_grupos (empresa, nombre_grupo, profesor, dia_semana, hora_inicio, idioma_formulario) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $_POST['empresa'], $_POST['nombre_grupo'], $_POST['profesor'], $_POST['dia_semana'], $_POST['hora_inicio'], $_POST['idioma']);
                $stmt->execute();
                echo '<div style="background:#e8f5e9;color:#27ae60;padding:12px;border-radius:8px;margin-bottom:16px;">Grupo creado correctamente</div>';
            }
            if (isset($_POST['nuevo_alumno'])) {
                $stmt = $conn->prepare("INSERT INTO own_feedback_teams_alumnos (grupo_id, nombre, email) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $_POST['grupo_id'], $_POST['nombre_alumno'], $_POST['email_alumno']);
                $stmt->execute();
                echo '<div style="background:#e8f5e9;color:#27ae60;padding:12px;border-radius:8px;margin-bottom:16px;">Alumno añadido correctamente</div>';
            }
            if (isset($_POST['toggle_grupo'])) {
                $conn->query("UPDATE own_feedback_teams_grupos SET activo = NOT activo WHERE id = ".intval($_POST['toggle_grupo']));
            }
            if (isset($_POST['eliminar_alumno'])) {
                $conn->query("DELETE FROM own_feedback_teams_alumnos WHERE id = ".intval($_POST['eliminar_alumno']));
            }
        }
        
        $grupos = $conn->query("SELECT g.*, COUNT(a.id) as num_alumnos FROM own_feedback_teams_grupos g LEFT JOIN own_feedback_teams_alumnos a ON g.id = a.grupo_id AND a.activo = 1 GROUP BY g.id ORDER BY g.empresa, g.nombre_grupo");
        $total_grupos = $grupos->num_rows;
        $total_alumnos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_teams_alumnos WHERE activo = 1")->fetch_assoc()['t'];
        ?>
        
        <div class="kpis" style="margin-bottom:20px">
            <div class="kpi"><div class="kpi-value"><?=$total_grupos?></div><div class="kpi-label">Grupos Teams</div></div>
            <div class="kpi"><div class="kpi-value"><?=$total_alumnos?></div><div class="kpi-label">Alumnos</div></div>
            <div class="kpi"><div class="kpi-value">3</div><div class="kpi-label">Empresas</div><div class="kpi-sub">Samsung, Rubi, Lin3s</div></div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <h3><span class="material-icons">add_circle</span> Nuevo Grupo</h3>
                <form method="POST" style="display:grid;gap:12px;">
                    <input type="text" name="empresa" placeholder="Empresa (Samsung, Rubi...)" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <input type="text" name="nombre_grupo" placeholder="Nombre del grupo" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <input type="text" name="profesor" placeholder="Profesor" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <select name="dia_semana" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <option value="">Día de clase</option>
                        <option value="lunes">Lunes</option>
                        <option value="martes">Martes</option>
                        <option value="miercoles">Miércoles</option>
                        <option value="jueves">Jueves</option>
                        <option value="viernes">Viernes</option>
                    </select>
                    <input type="time" name="hora_inicio" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <select name="idioma" style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <option value="es">Español</option>
                        <option value="en">English</option>
                    </select>
                    <button type="submit" name="nuevo_grupo" value="1" style="padding:12px;background:#008ba3;color:white;border:none;border-radius:4px;cursor:pointer;">Crear Grupo</button>
                </form>
            </div>
            
            <div class="card">
                <h3><span class="material-icons">person_add</span> Añadir Alumno</h3>
                <form method="POST" style="display:grid;gap:12px;">
                    <select name="grupo_id" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <option value="">Seleccionar grupo</option>
                        <?php $grupos->data_seek(0); while($g = $grupos->fetch_assoc()): ?>
                        <option value="<?=$g['id']?>"><?=htmlspecialchars($g['empresa'].' - '.$g['nombre_grupo'])?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="nombre_alumno" placeholder="Nombre del alumno" style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <input type="email" name="email_alumno" placeholder="Email del alumno" required style="padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <button type="submit" name="nuevo_alumno" value="1" style="padding:12px;background:#27ae60;color:white;border:none;border-radius:4px;cursor:pointer;">Añadir Alumno</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">list</span> Grupos Configurados</h3>
            <table>
                <thead><tr><th>Empresa</th><th>Grupo</th><th>Profesor</th><th>Día/Hora</th><th>Idioma</th><th>Alumnos</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                <?php $grupos->data_seek(0); while($g = $grupos->fetch_assoc()): ?>
                <tr>
                    <td><strong><?=htmlspecialchars($g['empresa'])?></strong></td>
                    <td><?=htmlspecialchars($g['nombre_grupo'])?></td>
                    <td><?=htmlspecialchars($g['profesor'])?></td>
                    <td><?=ucfirst($g['dia_semana'])?> <?=substr($g['hora_inicio'],0,5)?></td>
                    <td><span class="badge <?=$g['idioma_formulario']=='en'?'badge-info':'badge-success'?>"><?=strtoupper($g['idioma_formulario'])?></span></td>
                    <td><?=$g['num_alumnos']?></td>
                    <td><?=$g['activo'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>'?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="toggle_grupo" value="<?=$g['id']?>" style="padding:4px 8px;background:<?=$g['activo']?'#f39c12':'#27ae60'?>;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;"><?=$g['activo']?'Desactivar':'Activar'?></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">people</span> Alumnos por Grupo</h3>
            <?php
            $grupos->data_seek(0);
            while($g = $grupos->fetch_assoc()):
                $alumnos = $conn->query("SELECT * FROM own_feedback_teams_alumnos WHERE grupo_id = ".$g['id']." ORDER BY nombre");
                if ($alumnos->num_rows > 0):
            ?>
            <details style="margin-bottom:12px;border:1px solid #eee;border-radius:8px;padding:12px;">
                <summary style="cursor:pointer;font-weight:600;"><?=htmlspecialchars($g['empresa'].' - '.$g['nombre_grupo'])?> (<?=$alumnos->num_rows?> alumnos)</summary>
                <table style="margin-top:12px;">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php while($a = $alumnos->fetch_assoc()): ?>
                    <tr>
                        <td><?=htmlspecialchars($a['nombre'] ?: '-')?></td>
                        <td style="font-size:12px;"><?=htmlspecialchars($a['email'])?></td>
                        <td><form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar alumno?');"><button type="submit" name="eliminar_alumno" value="<?=$a['id']?>" style="padding:2px 6px;background:#e74c3c;color:white;border:none;border-radius:4px;cursor:pointer;font-size:11px;">×</button></form></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </details>
            <?php endif; endwhile; ?>
        </div>

<?php elseif ($seccion == 'enlaces'): ?>
        <!-- ENLACES -->
        <div class="header"><h1><span class="material-icons">link</span> Enlaces y Formularios</h1></div>
        
        <div class="card">
            <h3><span class="material-icons">description</span> Formularios Públicos</h3>
            <table>
                <tr>
                    <td><strong>Formulario General</strong><br><small style="color:#666">Para compartir manualmente con alumnos</small></td>
                    <td><a href="https://aula.tuspeaking.com/app/moodle/feedback/" target="_blank" style="color:#008ba3">https://aula.tuspeaking.com/app/moodle/feedback/</a></td>
                    <td><a href="https://aula.tuspeaking.com/app/moodle/feedback/" target="_blank" class="badge badge-info">Abrir ↗</a></td>
                </tr>
                <tr>
                    <td><strong>Formulario Rápido</strong><br><small style="color:#666">Enviado automáticamente por email (requiere token)</small></td>
                    <td><code style="font-size:11px">/feedback/quick.php?a=ACUITYID&t=TOKEN</code></td>
                    <td><span class="badge badge-warning">Solo vía email</span></td>
                </tr>
                <tr>
                    <td><strong>Página Opt-out</strong><br><small style="color:#666">Para darse de baja (requiere token)</small></td>
                    <td><code style="font-size:11px">/feedback/optout.php?t=TOKEN</code></td>
                    <td><span class="badge badge-warning">Post-feedback</span></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">admin_panel_settings</span> Panel de Administración</h3>
            <table>
                <tr>
                    <td><strong>Panel Admin Feedback</strong></td>
                    <td><a href="https://aula.tuspeaking.com/app/moodle/feedback/admin.php" target="_blank" style="color:#008ba3">https://aula.tuspeaking.com/app/moodle/feedback/admin.php</a></td>
                    <td><span class="badge badge-success">Actual</span></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">schedule</span> Crons Programados</h3>
            <table>
                <thead><tr><th>Cron</th><th>Horario</th><th>Función</th></tr></thead>
                <tbody>
                    <tr><td><code>cron_enviar_feedback.php</code></td><td>Cada 30 min</td><td>Envía email 30 min después de clase</td></tr>
                    <tr><td><code>cron_recordatorio_24h.php</code></td><td>9:00 AM</td><td>Recordatorio si no respondió</td></tr>
                    <tr><td><code>cron_resumen_diario.php</code></td><td>8:00 AM</td><td>Resumen diario (60 días)</td></tr>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">content_copy</span> Copiar Enlaces</h3>
            <div style="display:grid;gap:12px;max-width:500px;">
                <div>
                    <label style="font-size:12px;color:#666;">Formulario General:</label>
                    <input type="text" value="https://aula.tuspeaking.com/app/moodle/feedback/" readonly onclick="this.select();document.execCommand('copy');" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;background:#f9f9f9;cursor:pointer;">
                </div>
                <div>
                    <label style="font-size:12px;color:#666;">Panel Admin:</label>
                    <input type="text" value="https://aula.tuspeaking.com/app/moodle/feedback/admin.php" readonly onclick="this.select();document.execCommand('copy');" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;background:#f9f9f9;cursor:pointer;">
                </div>
            </div>
            <p style="color:#999;font-size:12px;margin-top:12px;">Haz clic en el campo para copiar</p>
        </div>


<?php elseif ($seccion == 'conversion'): ?>
        <!-- FUNNEL DE CONVERSIÓN -->
        <div class="header"><h1><span class="material-icons">trending_up</span> Funnel de Conversión</h1></div>
        
        <?php
        // Datos del funnel
        $clases_periodo = $conn->query("SELECT COUNT(*) as t FROM mdl_i3code_acuityZoom WHERE acuity_datetime >= '$desde' AND acuity_datetime <= '$hasta 23:59:59' AND acuity_email IS NOT NULL AND acuity_email != ''")->fetch_assoc()['t'];
        $emails_enviados = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59'")->fetch_assoc()['t'];
        $emails_abiertos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND abierto_at IS NOT NULL")->fetch_assoc()['t'];
        $feedbacks = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND respondido_at IS NOT NULL")->fetch_assoc()['t'];
        $recordatorios = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND recordatorio_enviado IS NOT NULL")->fetch_assoc()['t'];
        
        $pct_envio = $clases_periodo > 0 ? round($emails_enviados * 100 / $clases_periodo, 1) : 0;
        $pct_apertura = $emails_enviados > 0 ? round($emails_abiertos * 100 / $emails_enviados, 1) : 0;
        $pct_respuesta = $emails_enviados > 0 ? round($feedbacks * 100 / $emails_enviados, 1) : 0;
        
        // Config objetivos
        $cfg_obj = $conn->query("SELECT clave, valor FROM own_feedback_config WHERE clave IN ('objetivo_feedbacks_mes','objetivo_tasa_respuesta','confianza_estadistica','margen_error')")->fetch_all(MYSQLI_ASSOC);
        $objetivos = [];
        foreach ($cfg_obj as $c) { $objetivos[$c['clave']] = $c['valor']; }
        
        // Calcular relevancia estadística
        $n_necesario = ceil((1.96 * 1.96 * 0.5 * 0.5) / pow($objetivos['margen_error']/100, 2));
        $es_relevante = $feedbacks >= $n_necesario;
        ?>
        
        <div class="card">
            <h3><span class="material-icons">filter_alt</span> Embudo de Conversión</h3>
            <div style="max-width:600px;margin:0 auto;">
                <div style="background:#008ba3;color:white;padding:20px;text-align:center;border-radius:8px 8px 0 0;">
                    <div style="font-size:28px;font-weight:700;"><?=number_format($clases_periodo)?></div>
                    <div>Clases realizadas</div>
                </div>
                <div style="background:#00a0b8;color:white;padding:16px;text-align:center;margin:0 30px;">
                    <div style="font-size:24px;font-weight:700;"><?=number_format($emails_enviados)?> <small style="font-size:14px">(<?=$pct_envio?>%)</small></div>
                    <div>Emails enviados</div>
                </div>
                <div style="background:#00b4cc;color:white;padding:14px;text-align:center;margin:0 60px;">
                    <div style="font-size:22px;font-weight:700;"><?=number_format($emails_abiertos)?> <small style="font-size:14px">(<?=$pct_apertura?>%)</small></div>
                    <div>Emails abiertos</div>
                </div>
                <div style="background:<?=$pct_respuesta >= $objetivos['objetivo_tasa_respuesta'] ? '#27ae60' : '#f39c12'?>;color:white;padding:12px;text-align:center;margin:0 90px;border-radius:0 0 8px 8px;">
                    <div style="font-size:20px;font-weight:700;"><?=number_format($feedbacks)?> <small style="font-size:14px">(<?=$pct_respuesta?>%)</small></div>
                    <div>Feedbacks recibidos</div>
                </div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <h3><span class="material-icons">analytics</span> Relevancia Estadística</h3>
                <table>
                    <tr><td>Nivel de confianza</td><td><strong><?=$objetivos['confianza_estadistica']?>%</strong></td></tr>
                    <tr><td>Margen de error</td><td><strong>±<?=$objetivos['margen_error']?>%</strong></td></tr>
                    <tr><td>Muestra necesaria</td><td><strong><?=$n_necesario?> feedbacks</strong></td></tr>
                    <tr><td>Muestra actual</td><td><strong><?=$feedbacks?> feedbacks</strong></td></tr>
                    <tr><td>Estado</td><td><?php if($es_relevante): ?><span class="badge badge-success">✓ Relevante</span><?php else: ?><span class="badge badge-warning">Insuficiente (<?=$feedbacks?>/<?=$n_necesario?>)</span><?php endif; ?></td></tr>
                </table>
            </div>
            <div class="card">
                <h3><span class="material-icons">flag</span> Objetivos</h3>
                <table>
                    <tr><td>Objetivo feedbacks/mes</td><td><strong><?=$objetivos['objetivo_feedbacks_mes']?></strong></td></tr>
                    <tr><td>Objetivo tasa respuesta</td><td><strong><?=$objetivos['objetivo_tasa_respuesta']?>%</strong></td></tr>
                    <tr><td>Tasa actual</td><td><span class="badge <?=$pct_respuesta >= $objetivos['objetivo_tasa_respuesta'] ? 'badge-success' : 'badge-warning'?>"><?=$pct_respuesta?>%</span></td></tr>
                    <tr><td>Recordatorios enviados</td><td><strong><?=$recordatorios?></strong></td></tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">do_not_disturb</span> Opt-out (No molestar)</h3>
            <?php $optouts = $conn->query("SELECT * FROM own_feedback_optout ORDER BY created_at DESC LIMIT 20"); ?>
            <?php if ($optouts->num_rows > 0): ?>
            <table>
                <thead><tr><th>Nombre</th><th>Email</th><th>Motivo</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php while ($o = $optouts->fetch_assoc()): ?>
                <tr><td><?=htmlspecialchars($o['nombre'] ?? '-')?></td><td style="font-size:12px"><?=htmlspecialchars($o['email'])?></td><td><?=htmlspecialchars($o['motivo'])?></td><td><?=date('d/m/Y', strtotime($o['created_at']))?></td></tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#666;text-align:center;padding:20px;">No hay solicitudes de opt-out registradas</p>
            <?php endif; ?>
        </div>

<?php elseif ($seccion == 'auditoria'): ?>
        <!-- AUDITORÍA -->
        <div class="header"><h1><span class="material-icons">verified</span> Auditoría del Sistema</h1></div>
        
        <?php
        // Health check
        $cron_ultimo = $conn->query("SELECT MAX(enviado_at) as ultimo FROM own_feedback_envios")->fetch_assoc()['ultimo'];
        $cron_activo = $cron_ultimo && strtotime($cron_ultimo) > strtotime('-2 hours');
        
        $errores_hoy = $conn->query("SELECT COUNT(*) as t FROM own_feedback_logs WHERE tipo = 'error' AND DATE(created_at) = CURDATE()")->fetch_assoc()['t'];
        $envios_hoy = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE DATE(enviado_at) = CURDATE()")->fetch_assoc()['t'];
        $respuestas_hoy = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE DATE(respondido_at) = CURDATE()")->fetch_assoc()['t'];
        ?>
        
        <div class="kpis">
            <div class="kpi <?=$cron_activo ? 'success' : 'danger'?>">
                <div class="kpi-value"><?=$cron_activo ? '✓' : '✗'?></div>
                <div class="kpi-label">Cron Activo</div>
                <div class="kpi-sub"><?=$cron_ultimo ? 'Último: '.date('d/m H:i', strtotime($cron_ultimo)) : 'Sin datos'?></div>
            </div>
            <div class="kpi"><div class="kpi-value"><?=$envios_hoy?></div><div class="kpi-label">Envíos Hoy</div></div>
            <div class="kpi"><div class="kpi-value"><?=$respuestas_hoy?></div><div class="kpi-label">Respuestas Hoy</div></div>
            <div class="kpi <?=$errores_hoy > 0 ? 'danger' : 'success'?>"><div class="kpi-value"><?=$errores_hoy?></div><div class="kpi-label">Errores Hoy</div></div>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">checklist</span> Health Check</h3>
            <table>
                <?php
                $checks = [
                    ['Tabla own_feedback_nps', $conn->query("SELECT 1 FROM own_feedback_nps LIMIT 1") ? true : false],
                    ['Tabla own_feedback_envios', $conn->query("SELECT 1 FROM own_feedback_envios LIMIT 1") ? true : false],
                    ['Tabla own_feedback_config', $conn->query("SELECT 1 FROM own_feedback_config LIMIT 1") ? true : false],
                    ['Configuración cargada', $conn->query("SELECT COUNT(*) as c FROM own_feedback_config")->fetch_assoc()['c'] > 0],
                    ['Cron ejecutándose', $cron_activo || $envios_hoy == 0],
                ];
                foreach ($checks as $check): ?>
                <tr>
                    <td><?=$check[0]?></td>
                    <td><?=$check[1] ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-danger">✗ Error</span>'?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">history</span> Últimos Envíos</h3>
            <?php $ultimos = $conn->query("SELECT e.*, u.firstname as teacher_name FROM own_feedback_envios e LEFT JOIN mdl_user u ON e.teacherid = u.id ORDER BY e.enviado_at DESC LIMIT 15"); ?>
            <table>
                <thead><tr><th>Fecha</th><th>Email</th><th>Profesor</th><th>Abierto</th><th>Respondido</th></tr></thead>
                <tbody>
                <?php while ($e = $ultimos->fetch_assoc()): ?>
                <tr>
                    <td><?=date('d/m H:i', strtotime($e['enviado_at']))?></td>
                    <td style="font-size:12px"><?=htmlspecialchars(substr($e['student_email'],0,25))?></td>
                    <td><?=htmlspecialchars($e['teacher_name'] ?? $e['teacher_name'])?></td>
                    <td><?=$e['abierto_at'] ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-warning">No</span>'?></td>
                    <td><?=$e['respondido_at'] ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>'?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">error_outline</span> Logs de Errores</h3>
            <?php $logs = $conn->query("SELECT * FROM own_feedback_logs WHERE tipo = 'error' ORDER BY created_at DESC LIMIT 20"); ?>
            <?php if ($logs->num_rows > 0): ?>
            <table>
                <thead><tr><th>Fecha</th><th>Mensaje</th><th>Email</th></tr></thead>
                <tbody>
                <?php while ($l = $logs->fetch_assoc()): ?>
                <tr><td><?=date('d/m H:i', strtotime($l['created_at']))?></td><td style="color:#e74c3c"><?=htmlspecialchars($l['mensaje'])?></td><td style="font-size:12px"><?=htmlspecialchars($l['email'])?></td></tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#27ae60;text-align:center;padding:20px;"><span class="material-icons" style="vertical-align:middle">check_circle</span> Sin errores registrados</p>
            <?php endif; ?>
        </div>
        </div>
        
        <div class="card">
            <h3><span class="material-icons">terminal</span> Log del Cron (últimas 30 líneas)</h3>
            <pre style="background:#1a1a2e;color:#0f0;padding:16px;border-radius:8px;font-size:12px;max-height:400px;overflow-y:auto;"><?php
            $log_file = "/home/aulatuspeaking/feedback_cron.log";
            if (file_exists($log_file)) {
                $lines = file($log_file);
                $last_lines = array_slice($lines, -30);
                echo htmlspecialchars(implode("", $last_lines));
            } else {
                echo "Archivo de log no encontrado";
            }
            ?></pre>
            <p style="color:#999;font-size:12px;margin-top:8px;">Archivo: /home/aulatuspeaking/feedback_cron.log</p>

<?php endif; ?>
    </main>
</body>
</html>
<?php $conn->close(); ?>
