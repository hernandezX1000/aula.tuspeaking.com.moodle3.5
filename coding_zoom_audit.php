<?php
/**
 * Panel de Auditoría Zoom v2.2 - UX corregida
 */
require('config.php');

$admins = get_admins();
$isadmin = false;
foreach($admins as $admin){
    if ($admin->id == $USER->id){ $isadmin = true; break; }
}
if (!$isadmin){ header("Location: /app/moodle"); die(); }

$msg = '';
$current_user = $USER->username;
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'mark_auto_matched') {
        $clases = $DB->get_records_sql("
            SELECT DISTINCT az.id, az.zoom_meetingid, az.studentid, u.firstname, u.lastname,
                p.participant_name, p.participant_email, p.duration_minutes, p.source,
                CASE 
                    WHEN LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.firstname), '%') THEN 'nombre'
                    WHEN LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.lastname), '%') THEN 'apellido'
                    WHEN LOWER(p.participant_email) LIKE CONCAT('%', LOWER(SUBSTRING_INDEX(u.email, '@', 1)), '%') THEN 'email'
                END as match_type
            FROM mdl_i3code_acuityZoom az
            INNER JOIN mdl_user u ON az.studentid = u.id
            INNER JOIN mdl_coding_zoom_participants_unified p ON az.zoom_meetingid = p.zoom_meetingid
            WHERE az.zoom_clasecompletada = 0 AND az.acuity_datetime < ?
            AND p.participant_email NOT LIKE '%tuspeaking%' AND p.duration_minutes >= 10
            AND (LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.firstname), '%')
                OR LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.lastname), '%')
                OR LOWER(p.participant_email) LIKE CONCAT('%', LOWER(SUBSTRING_INDEX(u.email, '@', 1)), '%'))
        ", [$tomorrow]);
        
        $count = 0;
        foreach ($clases as $c) {
            $DB->execute("UPDATE mdl_i3code_acuityZoom SET zoom_clasecompletada = 1 WHERE id = ?", [$c->id]);
            $log = new stdClass();
            $log->acuityzoom_id = $c->id;
            $log->zoom_meetingid = $c->zoom_meetingid;
            $log->studentid = $c->studentid;
            $log->action = 'marked_auto';
            $log->match_type = $c->match_type;
            $log->participant_matched = $c->participant_name;
            $log->participant_email = $c->participant_email;
            $log->participant_duration = $c->duration_minutes;
            $log->data_source = $c->source;
            $log->marked_by = $current_user;
            $log->marked_at = date('Y-m-d H:i:s');
            try { $DB->insert_record('coding_zoom_audit_log', $log); } catch (Exception $e) {}
            $count++;
        }
        $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> '.$count.' clases marcadas</div>';
    }
    
    if ($_POST['action'] === 'mark_manual' && isset($_POST['az_id'])) {
        $az_id = intval($_POST['az_id']);
        $participant_name = $_POST['participant_name'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $az = $DB->get_record_sql("SELECT * FROM mdl_i3code_acuityZoom WHERE id = ?", [$az_id]);
        if ($az) {
            // manual_override=1: la ingesta NO volvera a tocar el estado de esta clase.
            $DB->execute("UPDATE mdl_i3code_acuityZoom SET zoom_clasecompletada = 1, manual_override = 1, manual_motivo = ?, manual_fecha = NOW() WHERE id = ?", [$notes, $az_id]);
            $log = new stdClass();
            $log->acuityzoom_id = $az_id;
            $log->zoom_meetingid = $az->zoom_meetingid;
            $log->studentid = $az->studentid;
            $log->action = 'marked_manual';
            $log->match_type = 'manual';
            $log->participant_matched = $participant_name;
            $log->data_source = 'mixed';
            $log->marked_by = $current_user;
            $log->marked_at = date('Y-m-d H:i:s');
            $log->notes = $notes;
            try { $DB->insert_record('coding_zoom_audit_log', $log); } catch (Exception $e) {}
            $msg = '<div class="alert alert-success"><i class="fas fa-check"></i> Clase marcada</div>';
        }
    }
    
    if ($_POST['action'] === 'mark_no_attendance' && isset($_POST['az_id'])) {
        $az_id = intval($_POST['az_id']);
        $notes = $_POST['notes'] ?? '';
        $az = $DB->get_record_sql("SELECT * FROM mdl_i3code_acuityZoom WHERE id = ?", [$az_id]);
        if ($az) {
            // "No asistio": marca ausencia (comp=2) y protege con manual_override=1.
            $DB->execute("UPDATE mdl_i3code_acuityZoom SET zoom_clasecompletada = 2, manual_override = 1, manual_motivo = ?, manual_fecha = NOW() WHERE id = ?", [$notes, $az_id]);
            $log = new stdClass();
            $log->acuityzoom_id = $az_id;
            $log->zoom_meetingid = $az->zoom_meetingid;
            $log->studentid = $az->studentid;
            $log->action = 'no_attendance';
            $log->match_type = 'sin_match';
            $log->data_source = 'mixed';
            $log->marked_by = $current_user;
            $log->marked_at = date('Y-m-d H:i:s');
            $log->notes = $notes;
            try { $DB->insert_record('coding_zoom_audit_log', $log); } catch (Exception $e) {}
            $msg = '<div class="alert alert-warning"><i class="fas fa-times"></i> Marcado como no asistió</div>';
        }
    }
    
    if ($_POST['action'] === 'refresh_cache') {
        $DB->execute("TRUNCATE TABLE mdl_coding_zoom_cobertura_cache");
        $DB->execute("INSERT INTO mdl_coding_zoom_cobertura_cache (mes, total_clases, con_datos, sin_datos, updated_at)
            SELECT DATE_FORMAT(STR_TO_DATE(SUBSTRING(acuity_datetime,1,10),'%Y-%m-%d'),'%Y-%m'), COUNT(*),
                SUM(CASE WHEN zoom_meetingid IN (SELECT DISTINCT zoom_meetingid FROM mdl_coding_zoom_participants_unified) THEN 1 ELSE 0 END),
                SUM(CASE WHEN zoom_meetingid NOT IN (SELECT DISTINCT zoom_meetingid FROM mdl_coding_zoom_participants_unified) THEN 1 ELSE 0 END), NOW()
            FROM mdl_i3code_acuityZoom WHERE zoom_meetingid IS NOT NULL AND acuity_datetime >= '2025-01-01'
            GROUP BY DATE_FORMAT(STR_TO_DATE(SUBSTRING(acuity_datetime,1,10),'%Y-%m-%d'),'%Y-%m')");
        $msg = '<div class="alert alert-success"><i class="fas fa-sync"></i> Cache actualizado</div>';
    }
    
    if ($_POST['action'] === 'delete_import' && isset($_POST['import_id'])) {
        $id = intval($_POST['import_id']);
        $DB->delete_records('coding_zoom_participants', ['import_id' => $id]);
        $DB->delete_records('coding_zoom_meetings', ['import_id' => $id]);
        $DB->delete_records('coding_zoom_imports', ['id' => $id]);
        $msg = '<div class="alert alert-info"><i class="fas fa-trash"></i> Importación eliminada</div>';
    }
}

$tab = $_GET['tab'] ?? 'validacion';

$total_participantes = $DB->count_records_sql("SELECT COUNT(*) FROM mdl_coding_zoom_participants_unified");
$stats = $DB->get_record_sql("SELECT COUNT(*) as total_con_zoom,
    SUM(CASE WHEN zoom_clasecompletada=1 THEN 1 ELSE 0 END) as total_completadas,
    SUM(CASE WHEN zoom_clasecompletada=0 AND acuity_datetime < ? THEN 1 ELSE 0 END) as total_pendientes
    FROM mdl_i3code_acuityZoom WHERE zoom_meetingid IS NOT NULL", [$tomorrow]);
$total_auditados = $DB->count_records('coding_zoom_audit_log');

if ($tab === 'validacion') {
    $pending_auto = $DB->get_records_sql("
        SELECT DISTINCT az.id, az.acuity_datetime, az.zoom_meetingid, u.firstname, u.lastname, u.email,
            c.shortname as course_name, p.participant_name, p.participant_email, p.duration_minutes, p.source,
            CASE 
                WHEN LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.firstname), '%') THEN 'nombre'
                WHEN LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.lastname), '%') THEN 'apellido'
                WHEN LOWER(p.participant_email) LIKE CONCAT('%', LOWER(SUBSTRING_INDEX(u.email, '@', 1)), '%') THEN 'email'
            END as match_type
        FROM mdl_i3code_acuityZoom az
        INNER JOIN mdl_user u ON az.studentid = u.id
        INNER JOIN mdl_course c ON az.courseid = c.id
        INNER JOIN mdl_coding_zoom_participants_unified p ON az.zoom_meetingid = p.zoom_meetingid
        LEFT JOIN mdl_coding_zoom_audit_log l ON az.id = l.acuityzoom_id
        WHERE az.zoom_clasecompletada = 0 AND az.acuity_datetime < ? AND l.id IS NULL
        AND p.participant_email NOT LIKE '%tuspeaking%' AND p.duration_minutes >= 10
        AND (LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.firstname), '%')
            OR LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.lastname), '%')
            OR LOWER(p.participant_email) LIKE CONCAT('%', LOWER(SUBSTRING_INDEX(u.email, '@', 1)), '%'))
        ORDER BY az.acuity_datetime DESC LIMIT 100
    ", [$tomorrow]);
    $count_auto = count($pending_auto);
}

if ($tab === 'revision') {
    $pending_manual = $DB->get_records_sql("
        SELECT az.id, az.acuity_datetime, az.zoom_meetingid, u.firstname, u.lastname, u.email, c.shortname as course_name
        FROM mdl_i3code_acuityZoom az
        INNER JOIN mdl_user u ON az.studentid = u.id
        INNER JOIN mdl_course c ON az.courseid = c.id
        LEFT JOIN mdl_coding_zoom_audit_log l ON az.id = l.acuityzoom_id
        WHERE az.zoom_clasecompletada = 0 AND az.zoom_meetingid IS NOT NULL AND az.acuity_datetime < ? AND l.id IS NULL
        AND EXISTS (SELECT 1 FROM mdl_coding_zoom_participants_unified p2 WHERE p2.zoom_meetingid = az.zoom_meetingid
            AND p2.participant_email NOT LIKE '%tuspeaking%' AND p2.duration_minutes >= 10)
        AND NOT EXISTS (SELECT 1 FROM mdl_coding_zoom_participants_unified p WHERE p.zoom_meetingid = az.zoom_meetingid
            AND p.participant_email NOT LIKE '%tuspeaking%' AND p.duration_minutes >= 10
            AND (LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.firstname), '%')
                OR LOWER(p.participant_name) LIKE CONCAT('%', LOWER(u.lastname), '%')
                OR LOWER(p.participant_email) LIKE CONCAT('%', LOWER(SUBSTRING_INDEX(u.email, '@', 1)), '%')))
        ORDER BY az.acuity_datetime DESC LIMIT 50
    ", [$tomorrow]);
    $count_manual = count($pending_manual);
}

if ($tab === 'trazabilidad') {
    $audit_log = $DB->get_records_sql("
        SELECT l.*, u.firstname, u.lastname, az.acuity_datetime
        FROM mdl_coding_zoom_audit_log l
        INNER JOIN mdl_i3code_acuityZoom az ON l.acuityzoom_id = az.id
        INNER JOIN mdl_user u ON l.studentid = u.id
        ORDER BY l.marked_at DESC LIMIT 100
    ");
}

if ($tab === 'integridad') {
    $imports = $DB->get_records_sql("SELECT i.id, i.filename, i.date_from, i.date_to, 
        i.total_meetings as esp_m, i.total_participants as esp_p,
        COUNT(DISTINCT m.zoom_meetingid) as real_m,
        (SELECT COUNT(*) FROM mdl_coding_zoom_participants WHERE import_id=i.id) as real_p
        FROM mdl_coding_zoom_imports i LEFT JOIN mdl_coding_zoom_meetings m ON i.id=m.import_id
        GROUP BY i.id ORDER BY i.id DESC");
}

if ($tab === 'cobertura') {
    $cobertura = $DB->get_records_sql("SELECT * FROM mdl_coding_zoom_cobertura_cache ORDER BY mes");
    $cache_time = $DB->get_field_sql("SELECT MAX(updated_at) FROM mdl_coding_zoom_cobertura_cache");
}
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auditoría Zoom v2.2</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css">
<style>
body{background:#f5f5f5}.hdr{background:linear-gradient(135deg,#008ba3,#00bcd4);color:#fff;padding:20px 0;margin-bottom:30px}
.card{border:none;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-bottom:20px}.card-header{background:#00bcd4;color:#fff}
.card-header.success{background:#27ae60}.card-header.warning{background:#f39c12}.card-header.info{background:#17a2b8}
.stat-box{text-align:center;padding:12px;background:#fff;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,.1)}
.stat-box h2{margin:0;font-size:1.6rem}.stat-box.green h2{color:#27ae60}.stat-box.orange h2{color:#f39c12}.stat-box.blue h2{color:#008ba3}
table{font-size:12px}.nav-tabs .nav-link.active{background:#008ba3;color:#fff}.nav-tabs .nav-link{color:#008ba3}
.badge-source{font-size:9px}
</style>
</head><body>
<div class="hdr"><div class="container">
<div style="display:flex;justify-content:space-between;align-items:center;"><div style="font-size:26px;color:white;font-weight:300;">tu<span style="font-weight:600;">Speaking</span></div><a href="coding_zoom_import.php" style="color:#fff;background:rgba(255,255,255,0.2);padding:8px 16px;border-radius:4px;text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver</a></div>
<h1 class="mt-2"><i class="fas fa-clipboard-check"></i> Auditoría Zoom v2.2</h1>
<small>Trazabilidad 100% - Solo clases pasadas (< <?=$tomorrow?>)</small>
</div></div>
<div class="container">
<?=$msg?>
<div class="row mb-3">
<div class="col"><div class="stat-box blue"><h2><?=$stats->total_con_zoom??0?></h2><small>Con Zoom</small></div></div>
<div class="col"><div class="stat-box green"><h2><?=$stats->total_completadas??0?></h2><small>Completadas</small></div></div>
<div class="col"><div class="stat-box orange"><h2><?=$stats->total_pendientes??0?></h2><small>Pendientes</small></div></div>
<div class="col"><div class="stat-box"><h2><?=$total_participantes?></h2><small>Participantes</small></div></div>
<div class="col"><div class="stat-box"><h2><?=$total_auditados?></h2><small>Auditados</small></div></div>
</div>
<ul class="nav nav-tabs mb-3">
<li class="nav-item"><a class="nav-link <?=$tab==='validacion'?'active':''?>" href="?tab=validacion"><i class="fas fa-check-double"></i> Auto Match</a></li>
<li class="nav-item"><a class="nav-link <?=$tab==='revision'?'active':''?>" href="?tab=revision"><i class="fas fa-user-edit"></i> Revisión Manual</a></li>
<li class="nav-item"><a class="nav-link <?=$tab==='trazabilidad'?'active':''?>" href="?tab=trazabilidad"><i class="fas fa-history"></i> Trazabilidad</a></li>
<li class="nav-item"><a class="nav-link <?=$tab==='integridad'?'active':''?>" href="?tab=integridad"><i class="fas fa-database"></i> Integridad</a></li>
<li class="nav-item"><a class="nav-link <?=$tab==='cobertura'?'active':''?>" href="?tab=cobertura"><i class="fas fa-chart-pie"></i> Cobertura</a></li>
</ul>

<?php if($tab==='validacion'): ?>
<div class="card"><div class="card-header success"><i class="fas fa-robot"></i> Match Automático (<?=$count_auto?>)
<form method="POST" style="display:inline;float:right"><input type="hidden" name="action" value="mark_auto_matched">
<button type="submit" class="btn btn-sm btn-light" onclick="return confirm('¿Marcar <?=$count_auto?> clases?')"><i class="fas fa-check-double"></i> Marcar Todas</button></form>
</div><div class="card-body p-0">
<?php if(empty($pending_auto)): ?><p class="text-muted p-3">No hay clases pendientes</p>
<?php else: ?><div class="table-responsive"><table class="table table-sm table-hover mb-0">
<thead class="thead-light"><tr><th>Fecha</th><th>Estudiante</th><th>Participante Zoom</th><th>Match</th><th>Fuente</th><th>Min</th></tr></thead><tbody>
<?php foreach($pending_auto as $r): ?>
<tr><td><?=date('d/m H:i',strtotime($r->acuity_datetime))?></td>
<td><strong><?=htmlspecialchars($r->firstname.' '.$r->lastname)?></strong><br><small class="text-muted"><?=$r->course_name?></small></td>
<td><?=htmlspecialchars($r->participant_name)?><br><small class="text-muted"><?=htmlspecialchars($r->participant_email)?></small></td>
<td><span class="badge badge-success"><?=$r->match_type?></span></td>
<td><span class="badge badge-<?=$r->source=='api'?'primary':'info'?> badge-source"><?=$r->source=='api'?'Auto':'Manual'?></span></td>
<td><span class="badge badge-info"><?=$r->duration_minutes?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>

<?php elseif($tab==='revision'): ?>
<div class="card"><div class="card-header warning"><i class="fas fa-user-edit"></i> Revisión Manual (<?=$count_manual?>)</div>
<div class="card-body p-0">
<?php if(empty($pending_manual)): ?><p class="text-muted p-3">No hay clases pendientes</p>
<?php else: ?><div class="table-responsive"><table class="table table-sm mb-0">
<thead class="thead-light"><tr><th>Fecha</th><th>Estudiante</th><th>Curso</th><th>Meeting</th><th>Acción</th></tr></thead><tbody>
<?php foreach($pending_manual as $r): ?>
<tr>
<td><?=date('d/m H:i',strtotime($r->acuity_datetime))?></td>
<td><strong><?=htmlspecialchars($r->firstname.' '.$r->lastname)?></strong><br><small><?=htmlspecialchars($r->email)?></small></td>
<td><small><?=$r->course_name?></small></td>
<td><code style="font-size:10px"><?=$r->zoom_meetingid?></code></td>
<td><button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modal<?=$r->id?>"><i class="fas fa-search"></i> Ver</button></td>
</tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>

<!-- Modales fuera de la tabla -->
<?php if($tab==='revision' && !empty($pending_manual)): ?>
<?php foreach($pending_manual as $r): 
    $participantes = $DB->get_records_sql("SELECT * FROM mdl_coding_zoom_participants_unified WHERE zoom_meetingid = ? AND participant_email NOT LIKE '%tuspeaking%' AND duration_minutes >= 10 ORDER BY duration_minutes DESC LIMIT 10", [$r->zoom_meetingid]);
?>
<div class="modal fade" id="modal<?=$r->id?>" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header bg-warning text-dark">
<h5 class="mb-0"><i class="fas fa-user-edit"></i> <?=htmlspecialchars($r->firstname.' '.$r->lastname)?></h5>
<button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<div class="row mb-3">
<div class="col-md-6">
<strong>Estudiante:</strong><br>
<span class="text-primary"><?=htmlspecialchars($r->firstname.' '.$r->lastname)?></span><br>
<small class="text-muted"><?=$r->email?></small>
</div>
<div class="col-md-6">
<strong>Clase:</strong><br>
<?=$r->course_name?><br>
<small class="text-muted"><?=date('d/m/Y H:i',strtotime($r->acuity_datetime))?></small>
</div>
</div>
<hr>
<h6><i class="fas fa-users"></i> Participantes en Zoom (>10 min):</h6>
<?php if(empty($participantes)): ?>
<div class="alert alert-danger mb-3">No hay participantes válidos</div>
<?php else: ?>
<table class="table table-sm table-bordered mb-3">
<thead class="thead-light"><tr><th>Nombre</th><th>Email</th><th>Min</th><th>Fuente</th><th></th></tr></thead>
<tbody>
<?php foreach($participantes as $p): ?>
<tr>
<td><strong><?=htmlspecialchars($p->participant_name)?></strong></td>
<td><small><?=htmlspecialchars($p->participant_email)?></small></td>
<td><span class="badge badge-success"><?=$p->duration_minutes?></span></td>
<td><span class="badge badge-<?=$p->source=='api'?'primary':'info'?>"><?=$p->source=='api'?'Auto':'Manual'?></span></td>
<td>
<form method="POST" style="display:inline">
<input type="hidden" name="action" value="mark_manual">
<input type="hidden" name="az_id" value="<?=$r->id?>">
<input type="hidden" name="participant_name" value="<?=htmlspecialchars($p->participant_name)?>">
<input type="hidden" name="notes" value="<?=htmlspecialchars($p->participant_name)?> = <?=htmlspecialchars($r->firstname.' '.$r->lastname)?>">
<button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Es este</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<hr>
<form method="POST">
<input type="hidden" name="action" value="mark_no_attendance">
<input type="hidden" name="az_id" value="<?=$r->id?>">
<div class="form-group">
<label>Notas:</label>
<input type="text" name="notes" class="form-control form-control-sm" placeholder="Razón...">
</div>
<button class="btn btn-danger"><i class="fas fa-times"></i> No asistió</button>
</form>
</div>
</div></div></div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif($tab==='trazabilidad'): ?>
<div class="card"><div class="card-header info"><i class="fas fa-history"></i> Log de Auditoría</div>
<div class="card-body p-0">
<?php if(empty($audit_log)): ?><p class="text-muted p-3">No hay registros</p>
<?php else: ?><div class="table-responsive"><table class="table table-sm mb-0">
<thead class="thead-light"><tr><th>Marcado</th><th>Clase</th><th>Estudiante</th><th>Acción</th><th>Match</th><th>Participante</th><th>Fuente</th><th>Por</th></tr></thead><tbody>
<?php foreach($audit_log as $l): ?>
<tr>
<td><small><?=date('d/m H:i',strtotime($l->marked_at))?></small></td>
<td><small><?=date('d/m',strtotime($l->acuity_datetime))?></small></td>
<td><?=htmlspecialchars($l->firstname.' '.$l->lastname)?></td>
<td><span class="badge badge-<?=$l->action=='marked_auto'?'success':($l->action=='marked_manual'?'primary':'danger')?>"><?=str_replace(['marked_','_'],' ',$l->action)?></span></td>
<td><small><?=$l->match_type?></small></td>
<td><small><?=htmlspecialchars($l->participant_matched)?></small></td>
<td><span class="badge badge-<?=$l->data_source=='api'?'primary':'info'?> badge-source"><?=$l->data_source?></span></td>
<td><small><?=$l->marked_by?></small></td>
</tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>

<?php elseif($tab==='integridad'): ?>
<div class="card"><div class="card-header"><i class="fas fa-database"></i> Importaciones CSV</div><div class="card-body p-0">
<table class="table table-sm mb-0"><thead class="thead-light"><tr><th>ID</th><th>Archivo</th><th>Rango</th><th>Meetings</th><th>Part.</th><th></th></tr></thead><tbody>
<?php foreach($imports as $i): ?>
<tr><td>#<?=$i->id?></td><td><small><?=substr($i->filename,0,30)?></small></td><td><small><?=$i->date_from?>→<?=$i->date_to?></small></td>
<td><?=$i->real_m?>/<?=$i->esp_m?></td><td><?=$i->real_p?>/<?=$i->esp_p?></td>
<td><form method="POST" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="action" value="delete_import">
<input type="hidden" name="import_id" value="<?=$i->id?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div>

<?php elseif($tab==='cobertura'): ?>
<div class="card"><div class="card-header"><i class="fas fa-chart-pie"></i> Cobertura
<form method="POST" style="display:inline;float:right"><input type="hidden" name="action" value="refresh_cache">
<button class="btn btn-sm btn-light"><i class="fas fa-sync"></i></button></form></div>
<div class="card-body p-0"><table class="table table-sm mb-0">
<thead class="thead-light"><tr><th>Mes</th><th>Total</th><th>Con Datos</th><th>Sin</th><th>%</th></tr></thead><tbody>
<?php foreach($cobertura as $c): $pct=$c->total_clases>0?round(($c->con_datos/$c->total_clases)*100):0; ?>
<tr><td><strong><?=$c->mes?></strong></td><td><?=$c->total_clases?></td><td class="text-success"><?=$c->con_datos?></td><td class="text-danger"><?=$c->sin_datos?></td>
<td><div class="progress" style="height:18px"><div class="progress-bar bg-success" style="width:<?=$pct?>%"><?=$pct?>%</div></div></td></tr>
<?php endforeach; ?></tbody></table>
<div class="card-footer"><small>Actualizado: <?=$cache_time?></small></div></div></div>
<?php endif; ?>

<p class="text-center text-muted mt-4"><small>Coding Zoom Audit v2.2.0 © 2025</small></p>
</div>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
</body></html>
