<?php
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

$filtro_profesor = $_GET['profesor'] ?? '';
$filtro_valoracion = $_GET['valoracion'] ?? '';
$filtro_email = $_GET['email'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Staff emails para excluir de métricas
$staff_emails = [];
$r = $conn->query("SELECT u.email FROM teacher_zoom_map t JOIN mdl_user u ON t.teacher_id = u.id WHERE t.is_staff = 1");
while($row = $r->fetch_assoc()) $staff_emails[] = $row['email'];

$where = "1=1";
if ($filtro_profesor) $where .= " AND profesor = '".$conn->real_escape_string($filtro_profesor)."'";
if ($filtro_valoracion) $where .= " AND valoracion <= ".intval($filtro_valoracion);
if ($filtro_email) $where .= " AND email LIKE '%".$conn->real_escape_string($filtro_email)."%'";
if ($fecha_inicio) $where .= " AND submission_date >= '".$conn->real_escape_string($fecha_inicio)."'";
if ($fecha_fin) $where .= " AND submission_date <= '".$conn->real_escape_string($fecha_fin)." 23:59:59'";

// Stats excluyendo staff
$where_no_staff = $where;
if (!empty($staff_emails)) {
    $staff_list = "'".implode("','", array_map([$conn, 'real_escape_string'], $staff_emails))."'";
    $where_no_staff .= " AND email NOT IN ($staff_list)";
}

// EXPORTAR CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'feedback_nps_'.($filtro_email ? str_replace(['@','.'], '_', $filtro_email) : 'todos').'_'.date('Y-m-d').'.csv';
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($output, ['Fecha', 'Profesor', 'Idioma', 'Valoración', 'Problema Conexión', 'Recibió Feedback', 'Comentarios', 'Email']);
    $datos = $conn->query("SELECT * FROM own_feedback_nps WHERE $where ORDER BY submission_date DESC");
    while($row = $datos->fetch_assoc()) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['submission_date'])),
            $row['profesor'], $row['idioma'], $row['valoracion'],
            $row['problema_conexion'], $row['recibio_feedback'],
            $row['comentarios'], $row['email']
        ]);
    }
    fclose($output);
    exit;
}

// EXPORTAR EXCEL
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    $filename = 'feedback_nps_'.($filtro_email ? str_replace(['@','.'], '_', $filtro_email) : 'todos').'_'.date('Y-m-d').'.xls';
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background:#008ba3;color:#fff;font-weight:bold"><td>Fecha</td><td>Profesor</td><td>Idioma</td><td>Valoración</td><td>Problema Conexión</td><td>Recibió Feedback</td><td>Comentarios</td><td>Email</td></tr>';
    $datos = $conn->query("SELECT * FROM own_feedback_nps WHERE $where ORDER BY submission_date DESC");
    while($row = $datos->fetch_assoc()) {
        $color = $row['valoracion'] <= 3 ? '#f8d7da' : ($row['valoracion'] <= 6 ? '#fff3cd' : '#d4edda');
        echo '<tr style="background:'.$color.'">';
        echo '<td>'.date('d/m/Y', strtotime($row['submission_date'])).'</td>';
        echo '<td>'.htmlspecialchars($row['profesor']).'</td>';
        echo '<td>'.htmlspecialchars($row['idioma']).'</td>';
        echo '<td style="text-align:center">'.$row['valoracion'].'</td>';
        echo '<td>'.htmlspecialchars($row['problema_conexion']).'</td>';
        echo '<td>'.htmlspecialchars($row['recibio_feedback']).'</td>';
        echo '<td>'.htmlspecialchars($row['comentarios']).'</td>';
        echo '<td>'.htmlspecialchars($row['email']).'</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

// EXPORTAR PDF
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once(__DIR__ . '/../lib/tcpdf/tcpdf.php');
    
    $stats = $conn->query("SELECT COUNT(*) as total, AVG(valoracion) as media, SUM(valoracion<=3) as alertas FROM own_feedback_nps WHERE $where_no_staff")->fetch_assoc();
    $datos = $conn->query("SELECT * FROM own_feedback_nps WHERE $where ORDER BY submission_date DESC");
    
    $cliente = $filtro_email ? strtoupper(str_replace(['@','.'], ' ', $filtro_email)) : 'TODOS LOS CLIENTES';
    $periodo = ($fecha_inicio && $fecha_fin) ? "$fecha_inicio a $fecha_fin" : 'Todo el período';
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('tuSpeaking');
    $pdf->SetAuthor('tuSpeaking');
    $pdf->SetTitle('Reporte Feedback NPS - '.$cliente);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();
    
    // Header
    $pdf->SetFillColor(0, 139, 163);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 15, 'tuSpeaking - Reporte Feedback NPS', 0, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, $cliente.' | Período: '.$periodo, 0, 1, 'C', true);
    
    $pdf->Ln(5);
    
    // Stats
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'RESUMEN', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(90, 8, 'Total respuestas: '.number_format($stats['total']), 1, 0, 'L');
    $pdf->Cell(90, 8, 'Valoración media: '.number_format($stats['media'], 1), 1, 0, 'L');
    $pdf->Cell(90, 8, 'Alertas (<=3): '.intval($stats['alertas']), 1, 1, 'L');
    
    $pdf->Ln(5);
    
    // Tabla
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'DETALLE DE RESPUESTAS', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(0, 139, 163);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(22, 7, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Profesor', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Idioma', 1, 0, 'C', true);
    $pdf->Cell(15, 7, 'Valor', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Conexión', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Feedback', 1, 0, 'C', true);
    $pdf->Cell(80, 7, 'Comentarios', 1, 0, 'C', true);
    $pdf->Cell(55, 7, 'Email', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    while($row = $datos->fetch_assoc()) {
        if ($row['valoracion'] <= 3) {
            $pdf->SetFillColor(248, 215, 218);
        } elseif ($row['valoracion'] <= 6) {
            $pdf->SetFillColor(255, 243, 205);
        } else {
            $pdf->SetFillColor(212, 237, 218);
        }
        
        $comentario = mb_substr($row['comentarios'], 0, 50);
        if (strlen($row['comentarios']) > 50) $comentario .= '...';
        
        $pdf->Cell(22, 6, date('d/m/Y', strtotime($row['submission_date'])), 1, 0, 'C', true);
        $pdf->Cell(35, 6, mb_substr($row['profesor'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell(20, 6, $row['idioma'], 1, 0, 'C', true);
        $pdf->Cell(15, 6, $row['valoracion'], 1, 0, 'C', true);
        $pdf->Cell(20, 6, $row['problema_conexion'], 1, 0, 'C', true);
        $pdf->Cell(20, 6, $row['recibio_feedback'], 1, 0, 'C', true);
        $pdf->Cell(80, 6, $comentario, 1, 0, 'L', true);
        $pdf->Cell(55, 6, $row['email'], 1, 1, 'L', true);
    }
    
    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Generado el '.date('d/m/Y H:i').' | tuSpeaking - Formación de idiomas', 0, 1, 'C');
    
    $filename = 'feedback_nps_'.($filtro_email ? str_replace(['@','.'], '_', $filtro_email) : 'todos').'_'.date('Y-m-d').'.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

$stats = $conn->query("SELECT COUNT(*) as total, AVG(valoracion) as media, SUM(valoracion<=3) as alertas FROM own_feedback_nps WHERE $where_no_staff")->fetch_assoc();
$profesores = $conn->query("SELECT DISTINCT profesor FROM own_feedback_nps WHERE profesor != '' ORDER BY profesor");
$datos = $conn->query("SELECT * FROM own_feedback_nps WHERE $where ORDER BY submission_date DESC LIMIT 200");
$total_filtrado = $conn->query("SELECT COUNT(*) as c FROM own_feedback_nps WHERE $where")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Feedback | tuSpeaking</title>
<link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;padding:20px}
.header{background:linear-gradient(135deg,#008ba3,#00bcd4);padding:20px 30px;color:#fff;display:flex;justify-content:space-between;align-items:center;border-radius:8px 8px 0 0}
.logo{font-size:24px;font-weight:300}.logo span{font-weight:600}
.container{max-width:1400px;margin:0 auto}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-bottom:20px;overflow:hidden}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;padding:20px}
.stat-box{background:#f8f9fa;padding:20px;border-radius:8px;text-align:center}
.stat-box h3{font-size:32px;color:#008ba3}.stat-box.alert h3{color:#e74c3c}
.stat-box p{color:#666;font-size:14px;margin-top:5px}
.filters{padding:20px;display:flex;gap:15px;flex-wrap:wrap;border-bottom:1px solid #eee;align-items:center}
.filters select,.filters input{padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
.filters input[type="text"]{width:180px}
.filters button{padding:10px 20px;background:#008ba3;color:#fff;border:none;border-radius:6px;cursor:pointer}
.filters button:hover{background:#00bcd4}
.filter-group{display:flex;align-items:center;gap:8px}
.filter-group label{font-size:13px;color:#666}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee;font-size:14px}
th{background:#f8f9fa;font-weight:600;color:#333}
tr:hover{background:#f8f9fa}
tr.staff{background:#fff3cd}
.val{display:inline-block;width:30px;height:30px;border-radius:50%;text-align:center;line-height:30px;color:#fff;font-weight:600;font-size:13px}
.val.low{background:#e74c3c}.val.med{background:#f39c12}.val.high{background:#27ae60}
.comentario{max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.back{color:#fff;text-decoration:none;background:rgba(255,255,255,.2);padding:8px 15px;border-radius:6px}
.badge-staff{background:#ffc107;color:#000;padding:2px 6px;border-radius:4px;font-size:11px;margin-left:5px}
.note{padding:10px 20px;background:#e3f2fd;color:#1565c0;font-size:13px;border-bottom:1px solid #eee}
.export-buttons{display:flex;gap:10px}
.btn-export{padding:10px 15px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:5px}
.btn-csv{background:#27ae60;color:#fff}
.btn-excel{background:#217346;color:#fff}
.btn-pdf{background:#e74c3c;color:#fff}
.btn-csv:hover{background:#2ecc71}
.btn-excel:hover{background:#1e6b3e}
.btn-pdf:hover{background:#c0392b}
.results-info{padding:10px 20px;background:#f8f9fa;font-size:13px;color:#666;border-bottom:1px solid #eee}
.search-hint{font-size:11px;color:#999;margin-top:2px}
</style>
</head>
<body>
<div class="container">
<div class="card">
<div class="header">
<div class="logo">tu<span>Speaking</span> - Panel Feedback NPS</div>
<a href="/app/moodle/admin-panel/" class="back">← Volver</a>
</div>
<div class="note">📊 Las métricas excluyen respuestas de staff<?php if($filtro_email): ?> | 🔍 Filtro email: <strong><?=htmlspecialchars($filtro_email)?></strong><?php endif; ?></div>
<div class="stats">
<div class="stat-box"><h3><?=number_format($stats['total'])?></h3><p>Total respuestas</p></div>
<div class="stat-box"><h3><?=number_format($stats['media'],1)?></h3><p>Valoración media</p></div>
<div class="stat-box alert"><h3><?=intval($stats['alertas'])?></h3><p>Alertas (≤3)</p></div>
</div>
<form class="filters" method="GET">
<div class="filter-group">
<label>Email/Empresa:</label>
<div>
<input type="text" name="email" value="<?=htmlspecialchars($filtro_email)?>" placeholder="@tekia.es, @cesce.es...">
<div class="search-hint">Ej: @tekia.es, juan@, cesce</div>
</div>
</div>
<div class="filter-group">
<label>Profesor:</label>
<select name="profesor"><option value="">Todos</option><?php while($p=$profesores->fetch_assoc()): ?><option <?=$filtro_profesor==$p['profesor']?'selected':''?>><?=htmlspecialchars($p['profesor'])?></option><?php endwhile; ?></select>
</div>
<div class="filter-group">
<label>Valoración:</label>
<select name="valoracion"><option value="">Todas</option><option value="3" <?=$filtro_valoracion=='3'?'selected':''?>>Alertas (≤3)</option><option value="6" <?=$filtro_valoracion=='6'?'selected':''?>>Bajas (≤6)</option></select>
</div>
<div class="filter-group">
<label>Desde:</label>
<input type="date" name="fecha_inicio" value="<?=$fecha_inicio?>">
</div>
<div class="filter-group">
<label>Hasta:</label>
<input type="date" name="fecha_fin" value="<?=$fecha_fin?>">
</div>
<button type="submit">🔍 Filtrar</button>
<a href="admin.php" style="padding:10px;color:#666;text-decoration:none;">Limpiar</a>
<div class="export-buttons" style="margin-left:auto">
<button type="submit" name="export" value="csv" class="btn-export btn-csv">📄 CSV</button>
<button type="submit" name="export" value="excel" class="btn-export btn-excel">📊 Excel</button>
<button type="submit" name="export" value="pdf" class="btn-export btn-pdf">📑 PDF</button>
</div>
</form>
<div class="results-info">
Mostrando <?=min(200, $total_filtrado)?> de <?=number_format($total_filtrado)?> resultados
<?php if($fecha_inicio || $fecha_fin): ?>
 | Período: <?=$fecha_inicio?:'-'?> a <?=$fecha_fin?:'-'?>
<?php endif; ?>
</div>
<table>
<thead><tr><th>Fecha</th><th>Profesor</th><th>Idioma</th><th>Valor</th><th>Conexión</th><th>Feedback</th><th>Comentarios</th><th>Email</th></tr></thead>
<tbody>
<?php while($row=$datos->fetch_assoc()): 
$c=$row['valoracion']<=3?'low':($row['valoracion']<=6?'med':'high');
$is_staff = in_array($row['email'], $staff_emails);
?>
<tr class="<?=$is_staff?'staff':''?>">
<td><?=date('d/m/Y',strtotime($row['submission_date']))?></td>
<td><?=htmlspecialchars($row['profesor'])?><?=$is_staff?'<span class="badge-staff">STAFF</span>':''?></td>
<td><?=htmlspecialchars($row['idioma'])?></td>
<td><span class="val <?=$c?>"><?=$row['valoracion']?></span></td>
<td><?=htmlspecialchars($row['problema_conexion'])?></td>
<td><?=htmlspecialchars($row['recibio_feedback'])?></td>
<td class="comentario" title="<?=htmlspecialchars($row['comentarios'])?>"><?=htmlspecialchars($row['comentarios'])?></td>
<td><?=htmlspecialchars($row['email'])?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
