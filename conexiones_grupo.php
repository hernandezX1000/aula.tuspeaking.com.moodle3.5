<?php
/**
 * conexiones_grupo.php — T-185 (15/10/2026) · «Conexiones Zoom del grupo»
 *
 * Un panel único con TODAS las conexiones de Zoom de TODOS los participantes de la acción formativa, con las mismas columnas que la
 * hoja «Conexiones» del Excel individual validado (tutorias_con_profesor.php, md5 92d8ef6a…, T-181) más «Participante (clase)».
 * Las consultas de tutorias_de() son copia literal de las QUERY 2 y QUERY 3 de esa página. Participantes: rol de alumno, sin cuentas
 * @tuspeaking, con o sin matrícula activa. Pantalla con filtro por participante + descarga .xlsx con la misma tabla.
 * Solo lectura. Acceso: report/progress:view (inspector/supervisor, profesor, admin).
 * Ubicación: /mnt/moodle-data/moodle-code/conexiones_grupo.php
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');

$courseid = required_param('courseid', PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$filtro = optional_param('participante', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course, false);
require_capability('report/progress:view', $context);

$PAGE->set_url('/conexiones_grupo.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Conexiones Zoom del grupo - ' . format_string($course->fullname));
$PAGE->set_heading('Conexiones Zoom del grupo');
$PAGE->set_pagelayout('incourse');

$pdo = new PDO("mysql:host={$CFG->dbhost};dbname={$CFG->dbname};charset=utf8mb4", $CFG->dbuser, $CFG->dbpass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ahora = time();
$dia = function ($t) { return $t ? userdate($t, '%d/%m/%Y', 'Europe/Madrid') : ''; };

/**
 * Clases y conexiones de un participante en el curso. COPIA de tutorias_con_profesor.php (T-181): QUERY 2, QUERY 3 y la nota por clase.
 * Devuelve [clases sin canceladas, conexiones por reunión].
 */
function tutorias_de(PDO $pdo, $courseid, $view_userid) {
    $tz_madrid = new DateTimeZone('Europe/Madrid');
    $stmt_detalle = $pdo->prepare("
        SELECT z.id, z.acuity_datetime, z.acuity_canceled, z.zoom_clasecompletada, z.zoom_meetingid,
               z.zoom_starttime, z.zoom_endtime, z.zoom_duration, z.zoom_topic, z.manual_override, z.manual_motivo, z.manual_fecha,
               CONCAT(t.firstname, ' ', t.lastname) AS profesor
        FROM mdl_i3code_acuityZoom z
        LEFT JOIN mdl_user t ON t.id = z.teacherid
        WHERE z.courseid = ? AND z.studentid = ?
    ");
    $stmt_detalle->execute([$courseid, $view_userid]);
    $detalle_clases = [];
    foreach ($stmt_detalle->fetchAll(PDO::FETCH_ASSOC) as $z) {
        try { $dt = new DateTime($z['acuity_datetime']); $dt->setTimezone($tz_madrid); } catch (Exception $e) { $dt = null; }
        if ((int)$z['acuity_canceled'] === 1) { $estado = 'CANCELADA'; }
        else {
            switch ((int)$z['zoom_clasecompletada']) {
                case 1: $estado = 'ASISTIO'; break;
                case 2: $estado = 'AUSENCIA'; break;
                case 3: $estado = 'VERIFICANDO'; break;
                default: $estado = ($dt && $dt->getTimestamp() > time()) ? 'PENDIENTE' : 'SIN DATOS';
            }
        }
        $detalle_clases[] = [
            'id' => (int)$z['id'],
            'ts' => $dt ? $dt->getTimestamp() : 0,
            'fecha' => $dt ? $dt->format('d/m/Y') : '',
            'hora_agendada' => $dt ? $dt->format('H:i') : '',
            'inicio_real_ts' => $z['zoom_starttime'] ? strtotime($z['zoom_starttime']) : null,
            'fin_real_ts' => $z['zoom_endtime'] ? strtotime($z['zoom_endtime']) : null,
            'estado' => $estado,
            'profesor' => trim((string)$z['profesor']),
            'meeting_id' => $z['zoom_meetingid'] ?: null,
            'tema' => (string)($z['zoom_topic'] ?? ''),
            'manual' => !empty($z['manual_override']) ? ('Sí' . ($z['manual_motivo'] ? ': ' . $z['manual_motivo'] : '') . ($z['manual_fecha'] ? ' (' . $z['manual_fecha'] . ')' : '')) : 'No',
            'nota' => '',
        ];
    }
    usort($detalle_clases, function ($a, $b) { return $a['ts'] <=> $b['ts']; });

    $stmt_meetings = $pdo->prepare("SELECT DISTINCT z.zoom_meetingid FROM mdl_i3code_acuityZoom z WHERE z.courseid = ? AND z.studentid = ? AND z.zoom_meetingid IS NOT NULL");
    $stmt_meetings->execute([$courseid, $view_userid]);
    $meeting_ids = $stmt_meetings->fetchAll(PDO::FETCH_COLUMN);
    $participantes_zoom = [];
    if (!empty($meeting_ids)) {
        $placeholders = implode(',', array_fill(0, count($meeting_ids), '?'));
        $tz = "CASE WHEN %1\$s >= '2026-03-29 01:00:00' AND %1\$s < '2026-10-25 01:00:00' THEN '+02:00'
                    WHEN %1\$s >= '2025-03-30 01:00:00' AND %1\$s < '2025-10-26 01:00:00' THEN '+02:00' ELSE '+01:00' END";
        $tzj = sprintf($tz, 'p.join_time');
        $tzl = sprintf($tz, 'p.leave_time');
        $stmt_part = $pdo->prepare("
            SELECT p.zoom_meetingid AS meeting_id, p.participant_name AS participante, p.participant_email AS email,
                   p.join_time AS join_time_utc, p.leave_time AS leave_time_utc,
                   DATE_FORMAT(CONVERT_TZ(p.join_time, '+00:00', $tzj), '%H:%i:%s') AS hora_entrada,
                   DATE_FORMAT(CONVERT_TZ(p.leave_time, '+00:00', $tzl), '%H:%i:%s') AS hora_salida,
                   p.duration_minutes AS duracion_min
            FROM mdl_coding_zoom_participants_unified p
            JOIN mdl_i3code_acuityZoom z ON z.zoom_meetingid = p.zoom_meetingid AND z.studentid = ?
            LEFT JOIN mdl_user t ON t.id = z.teacherid
            WHERE p.zoom_meetingid IN ($placeholders) AND p.duration_minutes > 0
            ORDER BY p.join_time ASC, p.duration_minutes DESC
        ");
        $stmt_part->execute(array_merge([$view_userid], $meeting_ids));
        foreach ($stmt_part->fetchAll(PDO::FETCH_ASSOC) as $p) { $participantes_zoom[$p['meeting_id']][] = $p; }
    }
    foreach ($detalle_clases as $i => $cl) {
        $parts = $cl['meeting_id'] ? ($participantes_zoom[$cl['meeting_id']] ?? []) : [];
        if ($cl['estado'] === 'ASISTIO' && empty($parts)) {
            $nota = 'Sin registro de conexión de Zoom para esta clase';
            foreach ($detalle_clases as $o) {
                if ($o['id'] !== $cl['id'] && $o['meeting_id'] && !empty($participantes_zoom[$o['meeting_id']])
                    && $o['inicio_real_ts'] && $o['fin_real_ts'] && $cl['ts'] >= $o['inicio_real_ts'] - 300 && $cl['ts'] < $o['fin_real_ts']) {
                    $nota = 'Dada en la misma reunión que la clase de las ' . $o['hora_agendada'] . ' (Zoom ' . $o['meeting_id'] . ', '
                          . date('H:i', $o['inicio_real_ts']) . '–' . date('H:i', $o['fin_real_ts']) . ')';
                    break;
                }
            }
            $detalle_clases[$i]['nota'] = $nota;
        }
    }
    $detalle_clases = array_values(array_filter($detalle_clases, function ($c) { return $c['estado'] !== 'CANCELADA'; }));
    return [$detalle_clases, $participantes_zoom];
}

// ─── Participantes del grupo (como finalizacion_actividad.php, T-183) ───
$student = $DB->get_record('role', ['shortname' => 'student'], 'id', MUST_EXIST);
$participantes = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
    FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
    WHERE ra.contextid = ? AND ra.roleid = ? AND " . $DB->sql_like('u.email', '?', false, false, true) . "
    ORDER BY u.lastname, u.firstname", [$context->id, $student->id, '%tuspeaking.com%']);
$matricula = [];
if ($participantes) {
    list($in, $p) = $DB->get_in_or_equal(array_keys($participantes));
    $rs = $DB->get_recordset_sql("SELECT ue.id, ue.userid, ue.status, ue.timestart, ue.timeend, ue.timemodified, e.status AS estado_metodo
        FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = ? AND ue.userid $in", array_merge([$courseid], $p));
    foreach ($rs as $ue) {
        $activa = (int)$ue->status === 0 && (int)$ue->estado_metodo === 0 && $ue->timestart <= $ahora && ($ue->timeend == 0 || $ue->timeend > $ahora);
        if ($activa) { $txt = 'Activa'; }
        else if ((int)$ue->status === 1) { $txt = 'Cerrada (suspendida el ' . $dia($ue->timemodified) . ')'; }
        else if ($ue->timeend && $ue->timeend <= $ahora) { $txt = 'Cerrada (terminó el ' . $dia($ue->timeend) . ')'; }
        else { $txt = 'Cerrada (método de matrícula desactivado)'; }
        if (!isset($matricula[$ue->userid]) || $activa) { $matricula[$ue->userid] = $txt; }
    }
    $rs->close();
}

// ─── Datos de cada participante ───
$ESTADOS = ['ASISTIO' => 'Asistió', 'AUSENCIA' => 'Ausencia', 'VERIFICANDO' => 'Verificando', 'SIN DATOS' => 'Sin datos', 'PENDIENTE' => 'Pendiente'];
$datos = []; $todas = [];
foreach ($participantes as $u) {
    list($clases, $zoom) = tutorias_de($pdo, $courseid, $u->id);
    $cuenta = array_fill_keys(array_keys($ESTADOS), 0); $tutores = [];
    foreach ($clases as $cl) {
        $cuenta[$cl['estado']] = ($cuenta[$cl['estado']] ?? 0) + 1;
        if ($cl['profesor'] !== '') { $tutores[$cl['profesor']] = true; }
        $obs = $cl['nota'];
        if ($cl['manual'] !== 'No') { $obs = trim($obs . ($obs ? '. ' : '') . 'Corrección manual: ' . substr($cl['manual'], strlen('Sí: '))); }
        $todas[] = $cl + ['userid' => $u->id, 'nombre' => fullname($u), 'obs' => $obs];
    }
    $datos[$u->id] = ['u' => $u, 'clases' => $clases, 'zoom' => $zoom, 'cuenta' => $cuenta, 'tutores' => array_keys($tutores),
                      'primera' => $clases ? $clases[0]['fecha'] : '', 'ultima' => $clases ? $clases[count($clases) - 1]['fecha'] : ''];
}
usort($todas, function ($a, $b) { return [$a['ts'], $a['nombre']] <=> [$b['ts'], $b['nombre']]; });

// ─── Ficha FUNDAE (cabecera) ───
$ag = []; $ids = [];
try {
    $rs = $DB->get_recordset_sql("SELECT f.c_fundae, o.id_grupo FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ? ORDER BY f.c_fundae", [$courseid]);
    foreach ($rs as $f) { $ag[] = str_replace('-', '/', (string)$f->c_fundae); $ids[] = $f->id_grupo ?: '-'; }
    $rs->close();
} catch (Exception $e) { }

// ─── Filas: una por conexión, como la hoja «Conexiones» del Excel individual ───
$filas = [];
foreach ($todas as $cl) {
    if (!$cl['meeting_id']) { continue; }
    foreach (($datos[$cl['userid']]['zoom'][$cl['meeting_id']] ?? []) as $p) {
        $is_tutor = !empty($p['email']) && strpos($p['email'], '@live') !== false && strpos($p['email'], 'tuspeaking') !== false;
        $filas[] = ['userid' => $cl['userid'], 'tutor' => $is_tutor,
            'v' => [$cl['fecha'], $cl['nombre'], (string)$cl['meeting_id'], $p['participante'], $p['email'] ?: '(invitado)', $is_tutor ? 'Tutor' : 'Alumno',
                    $p['hora_entrada'], $p['hora_salida'], (int)$p['duracion_min'], (string)$p['join_time_utc'], (string)($p['leave_time_utc'] ?? '')]];
    }
}
$CAB = ['Fecha', 'Participante (clase)', 'Zoom ID', 'Nombre en Zoom', 'Email', 'Rol', 'Entrada (Madrid)', 'Salida (Madrid)', 'Duración (min)', 'Entrada (UTC, tal cual Zoom)', 'Salida (UTC, tal cual Zoom)'];
$NOTA = 'Datos del registro automático de conexiones de Zoom. Horas en hora de Madrid (CET/CEST) y, al lado, tal cual las da Zoom (UTC).';
$visibles = array_values(array_filter($filas, function ($f) use ($filtro) { return !$filtro || $f['userid'] == $filtro; }));

// ─── Excel (.xlsx): una hoja «Conexiones» con la tabla de la pantalla (respeta el filtro) ───
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Conexiones_Zoom_del_grupo_' . $limpio($course->fullname) . '_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Conexiones');
    $ws->write_string(0, 0, 'Conexiones de Zoom · grupo', $f_titulo);
    $meta = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'], ['Curso', $course->fullname],
             ['Participante', $filtro && isset($datos[$filtro]) ? fullname($datos[$filtro]['u']) : 'Todos']];
    foreach ($meta as $i => $m) { $ws->write_string(2 + $i, 0, $m[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$m[1]); }
    $r0 = 7;
    foreach ($CAB as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([12, 26, 15, 22, 30, 9, 15, 15, 13, 24, 24] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($visibles as $f) {
        foreach ($f['v'] as $c => $v) { if (is_int($v)) { $ws->write_number($r, $c, $v); } else { $ws->write_string($r, $c, (string)$v); } }
        $r++;
    }
    $ws->write_string($r + 1, 0, $NOTA, $f_nota);
    $workbook->close();
    exit;
}

// ─── Pantalla ───
echo $OUTPUT->header();
?>
<style>
.cg-meta{background:#e0f7fa;border-left:4px solid #008ba3;padding:8px 12px;margin:8px 0}
.cg-meta b{display:inline-block;min-width:200px}
.cg-wrap{overflow-x:auto;margin:10px 0 18px}
.cg-tabla{border-collapse:collapse;font-size:12.5px;width:100%}
.cg-tabla th{background:#008ba3;color:#fff;padding:6px 7px;text-align:left;white-space:nowrap}
.cg-tabla td{border-bottom:1px solid #e2e8f0;padding:4px 7px;white-space:nowrap}
.cg-tabla td.n{text-align:right}
.cg-tabla tr.cg-tutor td{background:#f4fbfc}
.cg-nota{color:#666;font-style:italic;font-size:12px}
</style>
<h2>Conexiones Zoom del grupo</h2>
<div class="cg-meta">
  <div><b>Acción / grupo</b><?php echo s($ag ? implode(', ', $ag) : '-'); ?></div>
  <div><b>ID de grupo FUNDAE</b><?php echo s($ids ? implode(', ', $ids) : '-'); ?></div>
  <div><b>Curso</b><?php echo format_string($course->fullname); ?></div>
</div>
<?php
if (!$participantes) {
    echo $OUTPUT->notification('Este curso no tiene participantes.', 'info');
    echo $OUTPUT->footer();
    exit;
}
echo '<form method="get" style="margin:6px 0"><input type="hidden" name="courseid" value="' . $courseid . '">'
   . '<label>Participante: <select name="participante" onchange="this.form.submit()"><option value="0">Todos</option>';
foreach ($datos as $d) { echo '<option value="' . $d['u']->id . '"' . ($filtro == $d['u']->id ? ' selected' : '') . '>' . s(fullname($d['u'])) . '</option>'; }
echo '</select></label> <noscript><button type="submit">Filtrar</button></noscript> &nbsp; ';
echo '<a class="btn btn-secondary" href="' . (new moodle_url('/conexiones_grupo.php', ['courseid' => $courseid, 'participante' => $filtro, 'export' => 'excel']))->out() . '">Descargar Excel</a></form>';
echo '<p>' . count($visibles) . ' conexiones.</p>';
echo '<div class="cg-wrap"><table class="cg-tabla"><thead><tr>';
foreach ($CAB as $t) { echo '<th>' . s($t) . '</th>'; }
echo '</tr></thead><tbody>';
foreach ($visibles as $f) {
    echo '<tr' . ($f['tutor'] ? ' class="cg-tutor"' : '') . '>';
    foreach ($f['v'] as $c => $v) { echo '<td' . ($c === 8 ? ' class="n"' : '') . '>' . s((string)$v) . '</td>'; }
    echo '</tr>';
}
if (!$visibles) { echo '<tr><td colspan="11">Sin conexiones registradas.</td></tr>'; }
echo '</tbody></table></div>';
echo '<p class="cg-nota">' . s($NOTA) . '</p>';
echo $OUTPUT->footer();
