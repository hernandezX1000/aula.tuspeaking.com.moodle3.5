<?php
require_once('config.php');
require_login();
global $USER, $DB, $PAGE, $OUTPUT;

$PAGE->set_url('/misclases.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Mis Clases');
$PAGE->set_heading('Mis Clases');

$is_admin = is_siteadmin();
$view_userid = optional_param('userid', $USER->id, PARAM_INT);
$view_courseid = optional_param('courseid', 0, PARAM_INT);
$view_user = $DB->get_record('user', ['id' => $view_userid]);

$pdo = new PDO("mysql:host=localhost;dbname=aulatuspeaking35;charset=utf8mb4", "moodle35", "TuspeakingFix2025!");

$fecha_minima = '2026-01-01';

// Consulta de cursos - EXCLUYE CANCELADAS del conteo
$sql_cursos = "SELECT az.courseid, c.fullname as curso, c.category, oac.classnmbr as contratadas, oac.tipo_clase,
    COALESCE(DATE(FROM_UNIXTIME(c.startdate)), ee.fecha_inicio) as fecha_inicio, COALESCE(DATE(FROM_UNIXTIME(c.enddate)), ee.fecha_fin) as fecha_fin, 
    COUNT(CASE WHEN az.acuity_canceled = 0 THEN 1 END) as total,
    SUM(CASE WHEN az.zoom_clasecompletada = 1 AND az.acuity_canceled = 0 THEN 1 ELSE 0 END) as asistidas,
    SUM(CASE WHEN az.zoom_clasecompletada = 2 AND az.acuity_canceled = 0 THEN 1 ELSE 0 END) as ausencias,
    SUM(CASE WHEN az.zoom_clasecompletada = 3 AND az.acuity_canceled = 0 THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN az.acuity_canceled = 1 THEN 1 ELSE 0 END) as canceladas
FROM mdl_i3code_acuityZoom az
JOIN mdl_course c ON c.id = az.courseid
LEFT JOIN own_acuity_course oac ON oac.courseid = az.courseid
LEFT JOIN own_empresa_ediciones ee ON ee.categoria_id = c.category
WHERE az.studentid = ? AND az.acuity_datetime >= COALESCE(oac.fecha_inicio, ?)
GROUP BY az.courseid, c.fullname, c.category, oac.classnmbr, oac.tipo_clase, c.startdate, c.enddate, ee.fecha_inicio, ee.fecha_fin
ORDER BY c.startdate DESC, c.fullname";

$stmt = $pdo->prepare($sql_cursos);
$stmt->execute([$view_userid, $fecha_minima]);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Próxima clase - EXCLUYE CANCELADAS
$sql_proxima = "SELECT az.acuity_datetime, az.zoom_meetingid, az.zoom_url, az.acuity_type, CONCAT(t.firstname, ' ', t.lastname) as profesor
FROM mdl_i3code_acuityZoom az LEFT JOIN mdl_user t ON t.id = az.teacherid LEFT JOIN own_acuity_course oac2 ON oac2.courseid = az.courseid
WHERE az.studentid = ? AND az.zoom_clasecompletada = 3 AND (az.zoom_meetingid IS NOT NULL OR az.zoom_url IS NOT NULL) AND az.acuity_canceled = 0 AND az.acuity_datetime > NOW()
ORDER BY az.acuity_datetime ASC LIMIT 1";
$stmt_prox = $pdo->prepare($sql_proxima);
$stmt_prox->execute([$view_userid]);
$proxima = $stmt_prox->fetch(PDO::FETCH_ASSOC);

$clases = [];
$curso_actual = null;
if ($view_courseid > 0) {
    foreach ($cursos as $c) { if ($c['courseid'] == $view_courseid) { $curso_actual = $c; break; } }
    // Detalle de clases - INCLUYE CANCELADAS para mostrarlas tachadas
    $sql_clases = "SELECT az.acuity_datetime as fecha, az.acuity_type as grupo, az.acuity_duration as duracion,
        az.zoom_clasecompletada as estado, az.zoom_meetingid, az.zoom_url, CONCAT(t.firstname, ' ', t.lastname) as profesor,
        az.acuity_canceled, az.acuity_rescheduled, az.acuity_original_datetime
    FROM mdl_i3code_acuityZoom az LEFT JOIN mdl_user t ON t.id = az.teacherid LEFT JOIN own_acuity_course oac2 ON oac2.courseid = az.courseid
    WHERE az.studentid = ? AND az.courseid = ? AND az.acuity_datetime >= COALESCE(oac2.fecha_inicio, ?)
    ORDER BY az.acuity_datetime DESC";
    $stmt2 = $pdo->prepare($sql_clases);
    $stmt2->execute([$view_userid, $view_courseid, $fecha_minima]);
    $clases = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

// Estados: 0=Sin datos, 1=Asistió, 2=Ausencia, 3=Agendada, 4=Cancelada (virtual)
$estados = [
    0 => ['⏳', '#64748b', 'Sin datos'],
    1 => ['✅', '#27ae60', 'Asistió'],
    2 => ['❌', '#e74c3c', 'Ausencia'],
    3 => ['🕐', '#f39c12', 'Agendada'],
    4 => ['🚫', '#9ca3af', 'Cancelada'],
    5 => ['🔄', '#64748b', 'Verificando asistencia']
];

echo $OUTPUT->header();
?>
<style>
:root {
    --ts-primary: #008ba3;
    --ts-primary-dark: #006d80;
    --ts-primary-darker: #005566;
    --ts-secondary: #00bcd4;
    --ts-light: #e0f7fa;
    --ts-success: #27ae60;
    --ts-warning: #f39c12;
    --ts-danger: #e74c3c;
    --ts-dark: #424242;
    --ts-gray: #64748b;
    --ts-canceled: #9ca3af;
}
.mc{max-width:1100px;margin:0 auto;padding:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.new-feature{background:linear-gradient(135deg,var(--ts-light),#b2ebf2);border:1px solid var(--ts-secondary);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px}
.new-feature-icon{background:var(--ts-primary);color:#fff;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2em;flex-shrink:0}
.new-feature-text h4{margin:0 0 4px;color:var(--ts-dark);font-size:.95em}
.new-feature-text p{margin:0;color:var(--ts-gray);font-size:.85em}
.new-badge{background:var(--ts-primary);color:#fff;padding:2px 8px;border-radius:10px;font-size:.7em;font-weight:600;margin-left:8px}
.admin-box{background:#fff3cd;padding:12px 15px;border-radius:8px;margin-bottom:20px;border:1px solid #ffc107}
.curso-card{background:#fff;border-radius:12px;padding:20px;margin:12px 0;box-shadow:0 2px 8px rgba(0,0,0,.08);border-left:4px solid var(--ts-primary);cursor:pointer;transition:all .2s}
.curso-card:hover{transform:translateX(5px);box-shadow:0 4px 16px rgba(0,139,163,.15)}
.curso-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px}
.curso-header h4{margin:0;color:var(--ts-dark);font-size:1em;font-weight:600}
.tipo-badge{padding:4px 12px;border-radius:20px;font-size:.75em;font-weight:600}
.chevron{margin-left:auto;font-size:1.5em;color:var(--ts-primary);font-weight:bold}
.tipo-1TO1{background:#e3f2fd;color:#1565c0}
.tipo-GRUPAL{background:#f3e5f5;color:#7b1fa2}
.periodo{color:var(--ts-gray);font-size:.85em;margin:8px 0 12px}
.periodo.has-dates{color:var(--ts-primary);font-weight:500}
.curso-stats{display:flex;gap:10px;flex-wrap:wrap}
.curso-stat{text-align:center;padding:10px 14px;background:#f8fafc;border-radius:8px;min-width:70px}
.curso-stat strong{display:block;font-size:1.4em;font-weight:700;color:var(--ts-primary)}
.curso-stat.ok strong{color:var(--ts-success)}
.curso-stat.warn strong{color:var(--ts-warning)}
.curso-stat.bad strong{color:var(--ts-danger)}
.curso-stat.canceled strong{color:var(--ts-canceled)}
.curso-stat small{color:var(--ts-gray);font-size:.75em;display:block;margin-top:2px}
.resumen-curso{background:#fff;border-radius:16px;margin-bottom:24px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
.resumen-header{background:linear-gradient(135deg,var(--ts-primary),var(--ts-secondary));color:#fff;padding:24px}
.resumen-header h4{margin:0 0 6px;font-size:1.2em;font-weight:600;color:#fff}
.resumen-header .periodo-white{color:rgba(255,255,255,.9);margin:0;font-size:.9em}
.resumen-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(80px,1fr));padding:20px;gap:12px;background:#f8fafc}
.stat-box{text-align:center;padding:16px 12px;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.stat-box strong{display:block;font-size:1.8em;font-weight:700;margin-bottom:4px}
.stat-box small{color:var(--ts-gray);font-size:.8em}
.stat-box.green strong{color:var(--ts-success)}
.stat-box.red strong{color:var(--ts-danger)}
.stat-box.orange strong{color:var(--ts-warning)}
.stat-box.blue strong{color:var(--ts-primary)}
.stat-box.gray strong{color:var(--ts-gray)}
.stat-box.canceled strong{color:var(--ts-canceled)}
.btn-conectar{display:inline-flex;align-items:center;justify-content:center;gap:10px;background:var(--ts-primary-darker);color:#ffffff !important;padding:16px 32px;border-radius:12px;text-decoration:none !important;font-weight:700;font-size:1.05em;margin:16px 20px 20px;transition:all .25s ease;box-shadow:0 4px 14px rgba(0,139,163,0.4);border:none;cursor:pointer;letter-spacing:0.3px;text-shadow:0 1px 2px rgba(0,0,0,0.2)}
.btn-conectar:hover{background:var(--ts-primary);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,139,163,0.5);color:#ffffff !important;text-decoration:none !important}
.btn-conectar:active{transform:translateY(0);background:var(--ts-primary-dark)}
.btn-conectar svg{width:20px;height:20px;flex-shrink:0}
.badge-conectar{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#ffffff !important;color:var(--ts-primary-dark) !important;border:2px solid var(--ts-primary) !important;padding:8px 16px;border-radius:8px;text-decoration:none !important;font-weight:700;font-size:.85em;transition:all .2s ease;white-space:nowrap}
.badge-conectar:hover{background:var(--ts-primary) !important;color:#ffffff !important;text-decoration:none !important;transform:scale(1.03);box-shadow:0 4px 12px rgba(0,139,163,0.35)}
.badge-conectar svg{width:14px;height:14px;flex-shrink:0}
.badge{display:inline-flex;align-items:center;padding:6px 14px;border-radius:20px;color:#fff;font-size:.8em;font-weight:500}
.proxima-clase{background:linear-gradient(135deg,var(--ts-light),#b2ebf2);border:2px solid var(--ts-primary);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.proxima-clase-info{display:flex;align-items:center;gap:12px}
.proxima-clase-icon{background:var(--ts-primary);color:#fff;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4em}
.proxima-clase-text strong{display:block;color:var(--ts-dark);font-size:.95em}
.proxima-clase-text span{color:var(--ts-gray);font-size:.85em}
.historial-container{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.clase{padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;background:#fff;gap:12px}
.clase:hover{background:#f8fafc}
.clase:last-child{border-bottom:none}
.clase.cancelada{background:#f9fafb;opacity:0.7}
.clase.cancelada .clase-info b{text-decoration:line-through;color:var(--ts-canceled)}
.clase.cancelada .clase-info small{color:var(--ts-canceled)}
.clase.reprogramada{background:#fffbeb;border-left:3px solid var(--ts-warning)}
.clase-info{flex:1;min-width:0}
.clase-info b{color:var(--ts-dark);font-weight:500;display:block;margin-bottom:4px}
.clase-info small{color:var(--ts-gray);display:block;font-size:.85em}
.clase-info .reprog-note{color:var(--ts-warning);font-size:.75em;font-weight:500;margin-top:4px}
h3.section{margin-top:28px;color:var(--ts-dark);border-bottom:2px solid #f1f5f9;padding-bottom:12px;font-size:1.1em;font-weight:600}
.back-link{color:var(--ts-primary);text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:16px;font-weight:500}
.back-link:hover{text-decoration:underline;color:var(--ts-primary-dark)}
.leyenda{display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;padding:12px 16px;background:#f8fafc;border-radius:8px;font-size:.85em}
.leyenda-item{display:flex;align-items:center;gap:6px}
.leyenda-color{width:12px;height:12px;border-radius:50%}
@media (max-width:768px){.btn-conectar{width:calc(100% - 40px);padding:14px 20px}.proxima-clase{flex-direction:column;text-align:center}.proxima-clase .btn-conectar{width:100%;margin:12px 0 0 0}.clase{flex-direction:column;align-items:flex-start;gap:10px}.badge-conectar{width:100%;justify-content:center;padding:10px 16px}.resumen-stats{grid-template-columns:repeat(3,1fr)}}
</style>
<div class="mc">
<h2 style="margin:0 0 20px;color:var(--ts-dark)">📚 <?php echo ($view_userid != $USER->id) ? 'Clases de '.fullname($view_user) : 'Mis Clases'; ?></h2>
<?php if ($is_admin): ?><div class="admin-box">🔧 Admin: <input id="uid" value="<?php echo $view_userid; ?>" style="width:60px;padding:4px 8px;border:1px solid #ddd;border-radius:4px"> <button onclick="location='?userid='+document.getElementById('uid').value" style="padding:4px 12px;cursor:pointer">Ver</button> <span style="margin-left:15px">📧 <?php echo $view_user->email; ?></span><span class="chevron">›</span></div><?php endif; ?>
<?php if ($view_courseid > 0 && $curso_actual): 
    $contratadas = intval($curso_actual['contratadas']);
    $asistidas = intval($curso_actual['asistidas']);
    $ausencias = intval($curso_actual['ausencias']);
    $agendadas = intval($curso_actual['pendientes']);
    $canceladas = intval($curso_actual['canceladas']);
    $consumidas = $asistidas + $ausencias;
    $por_agendar = max(0, $contratadas - $consumidas - $agendadas);
    $pct = $contratadas > 0 ? round(($asistidas / $contratadas) * 100) : ($consumidas > 0 ? round(($asistidas / $consumidas) * 100) : 0);
?>
<a href="?userid=<?php echo $view_userid; ?>" class="back-link">← Volver a mis cursos</a>
<div class="resumen-curso">
    <div class="resumen-header"><h4><?php echo htmlspecialchars($curso_actual['curso']); ?></h4><?php if ($curso_actual['fecha_inicio']): ?><p class="periodo-white">📅 <?php echo date('d/m/Y', strtotime($curso_actual['fecha_inicio'])); ?> → <?php echo date('d/m/Y', strtotime($curso_actual['fecha_fin'])); ?></p><?php endif; ?></div>
    <div class="resumen-stats">
        <div class="stat-box blue"><strong><?php echo $contratadas ?: '-'; ?></strong><small>Contratadas</small></div>
        <div class="stat-box green"><strong><?php echo $asistidas; ?></strong><small>Asistidas</small></div>
        <div class="stat-box red"><strong><?php echo $ausencias; ?></strong><small>Ausencias</small></div>
        <div class="stat-box orange"><strong><?php echo $agendadas; ?></strong><small>Agendadas</small></div>
        <div class="stat-box gray"><strong><?php echo $por_agendar; ?></strong><small>Por agendar</small></div>
        <div class="stat-box <?php echo $pct >= 75 ? 'green' : ($pct >= 50 ? 'orange' : 'red'); ?>"><strong><?php echo $pct; ?>%</strong><small>Asistencia</small></div>
        <?php if ($canceladas > 0): ?><div class="stat-box canceled"><strong><?php echo $canceladas; ?></strong><small>Canceladas</small></div><?php endif; ?>
    </div>
    <?php if ($contratadas > 0 && ($asistidas + $agendadas) >= $contratadas): ?><div style="background:#fff3cd;border:1px solid #f39c12;border-radius:8px;padding:12px 16px;margin-top:12px;display:flex;align-items:center;gap:8px"><span style="font-size:1.3em">⚠️</span><span style="color:#856404"><strong>Límite alcanzado:</strong> Has consumido o agendado todas tus <?php echo $contratadas; ?> clases contratadas. Contacta con administración para ampliar tu bono.</span></div><?php endif; ?>
    <?php $proxima_curso = null; foreach ($clases as $cl) { if ($cl['estado'] == 3 && ($cl['zoom_meetingid'] || $cl['zoom_url']) && !$cl['acuity_canceled'] && strtotime($cl['fecha']) > time()) { $proxima_curso = $cl; } } if ($proxima_curso): ?>
    <a href="<?php echo $proxima_curso['zoom_url'] ?: 'https://zoom.us/j/'.$proxima_curso['zoom_meetingid']; ?>" target="_blank" class="btn-conectar"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>Conectar a clase · <?php echo date('d/m H:i', strtotime($proxima_curso['fecha'])); ?> · <?php echo htmlspecialchars($proxima_curso['profesor']); ?></a>
    <?php endif; ?>
</div>
<h3 class="section">📅 Historial de Clases (<?php echo count($clases); ?>)</h3>
<div class="leyenda">
    <div class="leyenda-item"><span class="leyenda-color" style="background:var(--ts-success)"></span> Asistió</div>
    <div class="leyenda-item"><span class="leyenda-color" style="background:var(--ts-danger)"></span> Ausencia</div>
    <div class="leyenda-item"><span class="leyenda-color" style="background:var(--ts-warning)"></span> Agendada</div>
    <div class="leyenda-item"><span class="leyenda-color" style="background:var(--ts-canceled)"></span> Cancelada</div>
</div>
<div class="historial-container">
<?php foreach ($clases as $cl): 
    $es_cancelada = !empty($cl['acuity_canceled']);
    $es_reprogramada = !empty($cl['acuity_rescheduled']);
    $estado_real = $es_cancelada ? 4 : ($cl['estado'] == 3 && strtotime($cl['fecha']) < time() ? 5 : $cl['estado']);
    $e = $estados[$estado_real] ?? $estados[0]; 
    $es_proxima = ($cl['estado'] == 3 && !$es_cancelada && strtotime($cl['fecha']) > time()); 
    $clase_extra = $es_cancelada ? 'cancelada' : ($es_reprogramada ? 'reprogramada' : '');
?>
<div class="clase <?php echo $clase_extra; ?>">
    <div class="clase-info">
        <b><?php echo $e[0].' '.htmlspecialchars($cl['grupo']); ?></b>
        <small>📅 <?php echo date('d/m/Y H:i', strtotime($cl['fecha'])); ?> · ⏱ <?php echo $cl['duracion']; ?> min · 👨‍🏫 <?php echo htmlspecialchars($cl['profesor']); ?></small>
        <?php if ($es_reprogramada && $cl['acuity_original_datetime']): ?>
        <div class="reprog-note">🔄 Reprogramada desde <?php echo date('d/m/Y H:i', strtotime($cl['acuity_original_datetime'])); ?></div>
        <?php endif; ?>
    </div>
    <?php if ($es_proxima && ($cl['zoom_meetingid'] || $cl['zoom_url'])): ?>
    <a href="<?php echo $cl['zoom_url'] ?: 'https://zoom.us/j/'.$cl['zoom_meetingid']; ?>" target="_blank" class="badge-conectar"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>Conectar</a>
    <?php else: ?>
    <span class="badge" style="background:<?php echo $e[1]; ?>"><?php echo $e[2]; ?></span>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<?php if ($proxima): ?>
<div class="proxima-clase">
    <div class="proxima-clase-info"><div class="proxima-clase-icon">🕐</div><div class="proxima-clase-text"><strong>Próxima clase: <?php echo date('d/m/Y H:i', strtotime($proxima['acuity_datetime'])); ?></strong><span>con <?php echo htmlspecialchars($proxima['profesor']); ?></span><span class="chevron">›</span></div></div>
    <a href="<?php echo $proxima['zoom_url'] ?: 'https://zoom.us/j/'.$proxima['zoom_meetingid']; ?>" target="_blank" class="btn-conectar" style="margin:0"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>Conectar ahora</a>
</div>
<?php endif; ?>
<h3 class="section">📖 Mis Ediciones / Cursos</h3>
<?php if (empty($cursos)): ?><p style="color:var(--ts-gray);padding:20px;background:#f8fafc;border-radius:8px;text-align:center">No hay cursos registrados desde 2026.</p>
<?php else: foreach ($cursos as $c): 
    $contratadas = intval($c['contratadas']); $asistidas = intval($c['asistidas']); $ausencias = intval($c['ausencias']); $agendadas = intval($c['pendientes']); $canceladas = intval($c['canceladas']);
    $consumidas = $asistidas + $ausencias; $por_agendar = max(0, $contratadas - $consumidas - $agendadas);
    $pct = $contratadas > 0 ? round(($asistidas / $contratadas) * 100) : ($consumidas > 0 ? round(($asistidas / $consumidas) * 100) : 0);
    $has_dates = !empty($c['fecha_inicio']); $f_ini = $has_dates ? date('d/m/Y', strtotime($c['fecha_inicio'])) : '-'; $f_fin = $has_dates ? date('d/m/Y', strtotime($c['fecha_fin'])) : '-';
?>
<div class="curso-card" onclick="location='?userid=<?php echo $view_userid; ?>&courseid=<?php echo $c['courseid']; ?>'">
    <div class="curso-header"><h4><?php echo htmlspecialchars($c['curso']); ?></h4><span class="tipo-badge tipo-<?php echo $c['tipo_clase'] ?: 'GRUPAL'; ?>"><?php echo $c['tipo_clase'] == '1TO1' ? '1 to 1' : 'Grupal'; ?></span><span class="chevron">›</span></div>
    <div class="periodo <?php echo $has_dates ? 'has-dates' : ''; ?>">📅 <?php echo $f_ini; ?> → <?php echo $f_fin; ?></div>
    <div class="curso-stats">
        <div class="curso-stat"><strong><?php echo $contratadas ?: '-'; ?></strong><small>Contratadas</small></div>
        <div class="curso-stat ok"><strong><?php echo $asistidas; ?></strong><small>Asistidas</small></div>
        <div class="curso-stat bad"><strong><?php echo $ausencias; ?></strong><small>Ausencias</small></div>
        <div class="curso-stat warn"><strong><?php echo $agendadas; ?></strong><small>Agendadas</small></div>
        <div class="curso-stat"><strong><?php echo $por_agendar; ?></strong><small>Por agendar</small></div>
        <div class="curso-stat <?php echo $pct >= 75 ? 'ok' : ($pct >= 50 ? 'warn' : 'bad'); ?>"><strong><?php echo $pct; ?>%</strong><small>Asistencia</small></div>
        <?php if ($canceladas > 0): ?><div class="curso-stat canceled"><strong><?php echo $canceladas; ?></strong><small>Canceladas</small></div><?php endif; ?>
    </div>
</div>
<?php endforeach; endif; endif; ?>
</div>
<?php echo $OUTPUT->footer(); ?>
