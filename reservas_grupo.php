<?php
/**
 * reservas_grupo.php — T-186 (15/10/2026) · «Reservas de tutorías» del grupo
 *
 * Todas las reservas de tutoría (clases por Zoom agendadas en Acuity) de TODOS los participantes de la acción formativa, tal como
 * están en mdl_i3code_acuityZoom, INCLUIDAS las canceladas y reprogramadas: fecha y hora agendadas (Madrid), duración reservada,
 * tutor, estado de la reserva, fecha original si se reprogramó, estado de la clase (mismas reglas que tutorias_con_profesor.php,
 * RUNBOOK_asistencias §1), Zoom ID, nombre de la reunión, inicio y fin reales en Zoom, duración real y corrección manual.
 * v2 (15/10, validada por En en el chat): + tipo de cita, anfitrión de Zoom y nº de participantes (ya en mdl_i3code_acuityZoom),
 *   + datos de Acuity de la tabla own_acuity_detalle (T-186 W09/W10, si existe): reservada el, calendario en Acuity,
 *   cancelada según Acuity, no presentado. El Zoom ID enlaza a conexiones_grupo.php filtrada por el participante.
 *   v3 (15/10, En): fuera «Reservada por» (Acuity no lo rellena: salía «No consta»; el dato sigue en la tabla).
 *   v4 (15/10, En): la columna «Clase» pasa a llamarse «Tutoría».
 *   v5 (15/10, En): fuera «No presentado (Acuity)» (noShow solo viene en canceladas; no aporta). El dato sigue en la tabla.
 *   v6 (15/10, En, T-193): «Historial de reservas de tutorías» (la página nueva «Tutorías programadas» es la de las próximas).
 *   Fila en naranja si no cuadran tutor del aula, calendario de Acuity y anfitrión de Zoom (no cambia ningún dato).
 *   No se muestran: correo/teléfono del alumno, formularios, precios, enlaces de Acuity ni la contraseña de Zoom (acuity_location).
 * Participantes: rol de alumno, sin cuentas @tuspeaking, con o sin matrícula activa. Solo lectura.
 * Acceso: report/progress:view (inspector/supervisor, profesor, admin). Ubicación: /mnt/moodle-data/moodle-code/reservas_grupo.php
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

$PAGE->set_url('/reservas_grupo.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Historial de reservas de tutorías - ' . format_string($course->fullname));
$PAGE->set_heading('Historial de reservas de tutorías');
$PAGE->set_pagelayout('incourse');

$ahora = time();
$dia = function ($t) { return $t ? userdate($t, '%d/%m/%Y', 'Europe/Madrid') : ''; };

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

// ─── Ficha FUNDAE (cabecera) ───
$ag = []; $ids = [];
try {
    $rs = $DB->get_recordset_sql("SELECT f.c_fundae, o.id_grupo FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ? ORDER BY f.c_fundae", [$courseid]);
    foreach ($rs as $f) { $ag[] = str_replace('-', '/', (string)$f->c_fundae); $ids[] = $f->id_grupo ?: '-'; }
    $rs->close();
} catch (Exception $e) { }


// ─── Reservas ───
$tz_madrid = new DateTimeZone('Europe/Madrid');
$madrid = function ($s) use ($tz_madrid) {
    if ($s === null || $s === '') { return [0, '', '']; }
    try { $dt = new DateTime($s); $dt->setTimezone($tz_madrid); return [$dt->getTimestamp(), $dt->format('d/m/Y'), $dt->format('H:i')]; }
    catch (Exception $e) { return [0, (string)$s, '']; }
};
$hms = function ($s) { return $s ? date('H:i:s', strtotime($s)) : ''; };   // zoom_starttime/endtime ya están en hora de Madrid
$CLASE = [1 => 'Asistió', 2 => 'Ausencia', 3 => 'Verificando'];
$hay_detalle = true;   // tabla de T-186 W09; si aún no existe, las columnas de Acuity salen vacías
try { $DB->get_records_sql('SELECT acuityid FROM own_acuity_detalle LIMIT 1'); } catch (Exception $e) { $hay_detalle = false; }
$norm = function ($s) { return core_text::strtolower(trim(preg_replace('/\s+/', ' ', (string)$s))); };
$filas = [];
if ($participantes) {
    list($in, $p) = $DB->get_in_or_equal(array_keys($participantes));
    $rs = $DB->get_recordset_sql("SELECT z.id, z.acuityid, z.studentid, z.acuity_datetime, z.acuity_duration, z.acuity_canceled, z.acuity_rescheduled,
            z.acuity_original_datetime, z.zoom_clasecompletada, z.zoom_meetingid, z.zoom_topic, z.zoom_starttime, z.zoom_endtime, z.zoom_duration,
            z.manual_override, z.manual_motivo, z.manual_fecha, CONCAT(t.firstname, ' ', t.lastname) AS profesor,
            z.acuity_type, z.zoom_username, z.zoom_email, z.zoom_participants" . ($hay_detalle ? ",
            d.datetime_created, d.scheduled_by, d.calendar, d.canceled AS ac_canceled, d.no_show, d.consultado" : '') . "
        FROM mdl_i3code_acuityZoom z LEFT JOIN {user} t ON t.id = z.teacherid" . ($hay_detalle ? "
        LEFT JOIN own_acuity_detalle d ON d.acuityid = z.acuityid" : '') . "
        WHERE z.courseid = ? AND z.studentid $in", array_merge([$courseid], $p));
    foreach ($rs as $z) {
        list($ts, $fecha, $hora) = $madrid($z->acuity_datetime);
        $cancelada = (int)$z->acuity_canceled === 1;
        $reserva = $cancelada ? 'Cancelada' : ((int)$z->acuity_rescheduled === 1 ? 'Reprogramada' : 'Activa');
        $original = '';
        if (!empty($z->acuity_original_datetime)) { list(, $of, $oh) = $madrid($z->acuity_original_datetime); $original = trim("$of $oh"); }
        if ($cancelada) { $clase = '—'; }
        else if (isset($CLASE[(int)$z->zoom_clasecompletada])) { $clase = $CLASE[(int)$z->zoom_clasecompletada]; }
        else { $clase = ($ts && $ts > $ahora) ? 'Pendiente' : 'Sin datos'; }
        $tutor = trim((string)$z->profesor);
        $anfitrion = trim((string)$z->zoom_username);
        $cal = $hay_detalle ? trim((string)$z->calendar) : '';
        // Mismo nombre si coinciden salvo 2 letras (p. ej. «McCobb» en el aula y «MacCobb» en Zoom).
        $nombres = array_values(array_filter([$norm($tutor), $norm($anfitrion), $norm($cal)]));
        $descuadre = false;
        for ($i = 0; $i < count($nombres); $i++) { for ($k = $i + 1; $k < count($nombres); $k++) {
            if (levenshtein($nombres[$i], $nombres[$k]) > 2) { $descuadre = true; }
        } }
        $creada = '';
        if ($hay_detalle && !empty($z->datetime_created)) { list(, $cf, $ch) = $madrid($z->datetime_created); $creada = trim("$cf $ch"); }
        $cargada = $hay_detalle && !empty($z->consultado);
        $manual = !empty($z->manual_override) ? trim('Sí' . ($z->manual_motivo ? ': ' . $z->manual_motivo : '') . ($z->manual_fecha ? ' (' . $z->manual_fecha . ')' : '')) : '';
        $sino = function ($v) use ($cargada) { return !$cargada || $v === null || $v === '' ? '' : ((int)$v === 1 ? 'Sí' : 'No'); };
        $filas[] = ['userid' => (int)$z->studentid, 'ts' => $ts, 'cancelada' => $cancelada, 'descuadre' => $descuadre, 'v' => [
            fullname($participantes[$z->studentid]), (string)($z->acuityid ?? ''), $fecha, $hora,
            $creada,
            (string)($z->acuity_type ?? ''),
            $z->acuity_duration !== null && $z->acuity_duration !== '' ? (int)$z->acuity_duration : '',
            $tutor ?: 'Sin datos', $cal, $reserva, $sino($cargada ? $z->ac_canceled : null), $original, $clase,
            (string)($z->zoom_meetingid ?: ''), (string)($z->zoom_topic ?? ''),
            trim($anfitrion . ($z->zoom_email ? ' · ' . $z->zoom_email : '')),
            $z->zoom_participants !== null && $z->zoom_participants !== '' ? (int)$z->zoom_participants : '',
            $hms($z->zoom_starttime), $hms($z->zoom_endtime), $z->zoom_duration !== null && $z->zoom_duration !== '' ? (int)$z->zoom_duration : '', $manual]];
    }
    $rs->close();
}
usort($filas, function ($a, $b) { return [$a['ts'], $a['v'][0]] <=> [$b['ts'], $b['v'][0]]; });
$CAB = ['Participante', 'ID de reserva', 'Fecha', 'Hora (Madrid)', 'Reservada el (Madrid)', 'Tipo de cita', 'Duración reservada (min)',
        'Tutor', 'Calendario en Acuity', 'Reserva', 'Cancelada según Acuity', 'Fecha original (si se reprogramó)', 'Tutoría',
        'Zoom ID', 'Nombre de la reunión en Zoom', 'Anfitrión de Zoom', 'Participantes en Zoom', 'Inicio real (Madrid)', 'Fin real (Madrid)',
        'Duración real (min)', 'Corrección manual'];
$NOTA = 'Reservas de tutoría por videoconferencia (Acuity) registradas en la plataforma, incluidas las canceladas y reprogramadas. Hora de Madrid. «Tutoría» con los estados del registro de tutorías. «Reservada el», «Calendario en Acuity», y «Cancelada según Acuity» son los datos de la reserva en Acuity' . ($hay_detalle ? '' : ' (aún no cargados)') . '. En naranja, reservas en las que no coinciden tutor, calendario de Acuity y anfitrión de Zoom.';
$visibles = array_values(array_filter($filas, function ($f) use ($filtro) { return !$filtro || $f['userid'] == $filtro; }));

// ─── Excel (.xlsx), respeta el filtro ───
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Historial_de_reservas_de_tutorias_' . $limpio($course->fullname) . '_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Reservas');
    $ws->write_string(0, 0, 'Historial de reservas de tutorías · grupo', $f_titulo);
    $meta = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'], ['Curso', $course->fullname],
             ['Participante', $filtro && isset($participantes[$filtro]) ? fullname($participantes[$filtro]) : 'Todos']];
    foreach ($meta as $i => $m) { $ws->write_string(2 + $i, 0, $m[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$m[1]); }
    $r0 = 7;
    foreach ($CAB as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([26, 13, 11, 9, 17, 45, 11, 22, 22, 13, 12, 18, 12, 15, 60, 40, 11, 12, 12, 11, 40] as $c => $a) { $ws->set_column($c, $c, $a); }
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
.rg-meta{background:#e0f7fa;border-left:4px solid #008ba3;padding:8px 12px;margin:8px 0}
.rg-meta b{display:inline-block;min-width:200px}
.rg-wrap{overflow-x:auto;margin:10px 0 18px}
.rg-tabla{border-collapse:collapse;font-size:12.5px;width:100%}
.rg-tabla th{background:#008ba3;color:#fff;padding:6px 7px;text-align:left;white-space:nowrap}
.rg-tabla td{border-bottom:1px solid #e2e8f0;padding:4px 7px;white-space:nowrap}
.rg-tabla tr.rg-cancelada td{color:#999}
.rg-tabla tr.rg-descuadre td{background:#fff3e0}
.rg-nota{color:#666;font-style:italic;font-size:12px}
</style>
<h2>Historial de reservas de tutorías</h2>
<div class="rg-meta">
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
foreach ($participantes as $u) { echo '<option value="' . $u->id . '"' . ($filtro == $u->id ? ' selected' : '') . '>' . s(fullname($u)) . '</option>'; }
echo '</select></label> <noscript><button type="submit">Filtrar</button></noscript> &nbsp; ';
echo '<a class="btn btn-secondary" href="' . (new moodle_url('/reservas_grupo.php', ['courseid' => $courseid, 'participante' => $filtro, 'export' => 'excel']))->out() . '">Descargar Excel</a></form>';
$nc = count(array_filter($visibles, function ($f) { return $f['cancelada']; }));
echo '<p>' . count($visibles) . ' reservas (' . $nc . ' canceladas).</p>';
echo '<div class="rg-wrap"><table class="rg-tabla"><thead><tr>';
foreach ($CAB as $t) { echo '<th>' . s($t) . '</th>'; }
echo '</tr></thead><tbody>';
foreach ($visibles as $f) {
    $cls = trim(($f['cancelada'] ? 'rg-cancelada ' : '') . ($f['descuadre'] ? 'rg-descuadre' : ''));
    echo '<tr' . ($cls ? ' class="' . $cls . '"' : '') . '>';
    foreach ($f['v'] as $c => $v) {
        if ($c === 13 && $v !== '') {
            $url = new moodle_url('/conexiones_grupo.php', ['courseid' => $courseid, 'participante' => $f['userid']]);
            echo '<td><a href="' . $url->out() . '" title="Ver las conexiones de Zoom de este participante">' . s((string)$v) . '</a></td>';
        } else { echo '<td>' . s((string)$v) . '</td>'; }
    }
    echo '</tr>';
}
if (!$visibles) { echo '<tr><td colspan="21">Sin reservas registradas.</td></tr>'; }
echo '</tbody></table></div>';
echo '<p class="rg-nota">' . s($NOTA) . '</p>';
echo $OUTPUT->footer();
