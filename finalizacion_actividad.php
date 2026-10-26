<?php
/**
 * finalizacion_actividad.php — T-183 (14/10/2026)
 *
 * «Finalización de actividad» para el inspector FUNDAE: lo mismo que el informe de Moodle (report/progress),
 * pero con TODOS los participantes del curso, también los que tienen la matrícula cerrada (suspendida) al terminar.
 * El informe de Moodle 3.5 solo enseña matrículas activas y no tiene ajuste para cambiarlo.
 * Solo lectura: no cambia matrículas ni datos. Los alumnos siguen sin poder entrar al curso.
 *
 * Parámetros: courseid (obligatorio), export=excel (opcional, .xlsx).
 * Acceso: quien tenga report/progress:view en el curso (supervisor/inspector, profesor, admin). Los alumnos no lo tienen.
 * Ubicación: /mnt/moodle-data/moodle-code/finalizacion_actividad.php (raíz web del aula, junto a tutorias_con_profesor.php)
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course, false);
require_capability('report/progress:view', $context);

$PAGE->set_url('/finalizacion_actividad.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Finalización de actividad - ' . format_string($course->fullname));
$PAGE->set_heading('Finalización de actividad');
$PAGE->set_pagelayout('incourse');

$completion = new completion_info($course);
$ahora = time();
$fecha = function ($t) { return $t ? userdate($t, '%d/%m/%Y %H:%M', 'Europe/Madrid') : ''; };
$dia = function ($t) { return $t ? userdate($t, '%d/%m/%Y', 'Europe/Madrid') : ''; };

// ─── Actividades con seguimiento de finalización, en el orden del curso ───
$actividades = [];
if ($completion->is_enabled()) {
    foreach ($completion->get_activities() as $cm) {
        $actividades[$cm->id] = ['nombre' => format_string($cm->name, true, ['context' => $context]), 'tipo' => get_string('modulename', $cm->modname)];
    }
}

// ─── Participantes: rol de alumno en el curso (sin cuentas @tuspeaking), con o sin matrícula activa ───
$student = $DB->get_record('role', ['shortname' => 'student'], 'id', MUST_EXIST);
$participantes = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
    FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
    WHERE ra.contextid = ? AND ra.roleid = ? AND " . $DB->sql_like('u.email', '?', false, false, true) . "
    ORDER BY u.lastname, u.firstname", [$context->id, $student->id, '%tuspeaking.com%']);

// Estado de la matrícula de cada uno (puede tener varias: se toma la mejor).
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

// ─── Finalizaciones registradas ───
$estado = []; // [userid][cmid] => [state, time]
if ($participantes && $actividades) {
    list($inu, $pu) = $DB->get_in_or_equal(array_keys($participantes));
    list($inc, $pc) = $DB->get_in_or_equal(array_keys($actividades));
    $rs = $DB->get_recordset_sql("SELECT id, userid, coursemoduleid, completionstate, timemodified FROM {course_modules_completion}
        WHERE userid $inu AND coursemoduleid $inc", array_merge($pu, $pc));
    foreach ($rs as $c) { $estado[$c->userid][$c->coursemoduleid] = [(int)$c->completionstate, (int)$c->timemodified]; }
    $rs->close();
}
$etiqueta = [COMPLETION_INCOMPLETE => 'No completada', COMPLETION_COMPLETE => 'Completada',
             COMPLETION_COMPLETE_PASS => 'Completada (aprobada)', COMPLETION_COMPLETE_FAIL => 'Completada (no aprobada)'];
$hecha = function ($st) { return in_array($st, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL], true); };

// ─── Ficha FUNDAE del curso (cabecera), si la hay ───
$ag = []; $ids = [];
try {
    $rs = $DB->get_recordset_sql("SELECT f.c_fundae, o.id_grupo FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ? ORDER BY f.c_fundae", [$courseid]);
    foreach ($rs as $f) { $ag[] = str_replace('-', '/', (string)$f->c_fundae); $ids[] = $f->id_grupo ?: '-'; }
    $rs->close();
} catch (Exception $e) { /* sin ficha: la cabecera sale con «-» */ }

// ─── Excel (.xlsx) ───
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Finalizacion_de_actividad_' . $limpio($course->fullname) . '_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Finalización');
    $ws->write_string(0, 0, 'Finalización de actividad', $f_titulo);
    $filas = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'],
              ['Curso', $course->fullname], ['Descargado', $fecha($ahora)]];
    foreach ($filas as $i => $f) { $ws->write_string(2 + $i, 0, $f[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$f[1]); }
    $r0 = 7;
    $cab = ['Participante', 'Email', 'Matrícula', 'Actividad', 'Tipo', 'Estado', 'Fecha de finalización'];
    foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([28, 34, 30, 50, 16, 24, 20] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($participantes as $u) {
        foreach ($actividades as $cmid => $a) {
            list($st, $t) = $estado[$u->id][$cmid] ?? [COMPLETION_INCOMPLETE, 0];
            $fila = [fullname($u), $u->email, $matricula[$u->id] ?? 'Sin matrícula', $a['nombre'], $a['tipo'], $etiqueta[$st] ?? (string)$st, $hecha($st) ? $fecha($t) : ''];
            foreach ($fila as $c => $v) { $ws->write_string($r, $c, (string)$v); }
            $r++;
        }
    }
    $ws->write_string($r + 1, 0, 'Fuente: registro de finalización de actividades de Moodle. Incluye a los participantes con la matrícula cerrada tras finalizar el curso. Horas de Madrid.', $f_nota);
    $workbook->close();
    exit;
}

// ─── Pantalla ───
echo $OUTPUT->header();
?>
<style>
.fa-wrap{overflow-x:auto;margin-top:12px}
.fa-tabla{border-collapse:collapse;font-size:13px}
.fa-tabla th,.fa-tabla td{border:1px solid #dcdcdc;padding:4px 6px;text-align:center;vertical-align:bottom}
.fa-tabla th.fa-act{height:190px;white-space:nowrap;padding:6px 2px}
.fa-tabla th.fa-act div{writing-mode:vertical-rl;transform:rotate(180deg);max-height:180px;overflow:hidden;text-overflow:ellipsis;margin:0 auto}
.fa-tabla td.fa-nom{text-align:left;white-space:nowrap}
.fa-tabla td.fa-mat{text-align:left;white-space:nowrap;color:#666;font-size:12px}
.fa-si{color:#27ae60;font-weight:bold}.fa-no{color:#bbb}.fa-fail{color:#e67e22;font-weight:bold}
.fa-meta{background:#e0f7fa;border-left:4px solid #008ba3;padding:8px 12px;margin:8px 0}
.fa-meta b{display:inline-block;min-width:200px}
</style>
<h2>Finalización de actividad</h2>
<div class="fa-meta">
  <div><b>Acción / grupo</b><?php echo s($ag ? implode(', ', $ag) : '-'); ?></div>
  <div><b>ID de grupo FUNDAE</b><?php echo s($ids ? implode(', ', $ids) : '-'); ?></div>
  <div><b>Curso</b><?php echo format_string($course->fullname); ?></div>
</div>
<p>Incluye a todos los participantes del curso, también a los que tienen la matrícula cerrada tras finalizar. Pase el ratón por una casilla para ver la fecha de finalización (hora de Madrid).</p>
<?php
if (!$completion->is_enabled() || !$actividades) {
    echo $OUTPUT->notification('Este curso no tiene actividades con seguimiento de finalización.', 'info');
} else if (!$participantes) {
    echo $OUTPUT->notification('Este curso no tiene participantes.', 'info');
} else {
    echo '<p><a class="btn btn-secondary" href="' . (new moodle_url('/finalizacion_actividad.php', ['courseid' => $courseid, 'export' => 'excel']))->out() . '">Descargar Excel</a></p>';
    echo '<div class="fa-wrap"><table class="fa-tabla"><thead><tr><th>Participante</th><th>Matrícula</th>';
    foreach ($actividades as $a) { echo '<th class="fa-act" title="' . s($a['nombre']) . '"><div>' . s($a['nombre']) . '</div></th>'; }
    echo '<th>Completadas</th></tr></thead><tbody>';
    foreach ($participantes as $u) {
        $n = 0;
        echo '<tr><td class="fa-nom">' . s(fullname($u)) . '<br><small>' . s($u->email) . '</small></td><td class="fa-mat">' . s($matricula[$u->id] ?? 'Sin matrícula') . '</td>';
        foreach ($actividades as $cmid => $a) {
            list($st, $t) = $estado[$u->id][$cmid] ?? [COMPLETION_INCOMPLETE, 0];
            $tit = s(fullname($u) . ' · ' . $a['nombre'] . ' · ' . ($etiqueta[$st] ?? $st) . ($hecha($st) ? ' · ' . $fecha($t) : ''));
            if ($hecha($st)) { $n++; }
            $marca = $st === COMPLETION_COMPLETE_FAIL ? '<span class="fa-fail">✔</span>' : ($hecha($st) ? '<span class="fa-si">✔</span>' : '<span class="fa-no">—</span>');
            echo '<td title="' . $tit . '">' . $marca . '</td>';
        }
        echo '<td>' . $n . ' de ' . count($actividades) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<p><small>✔ completada · <span class="fa-fail">✔</span> completada sin aprobar · — no completada. Fuente: registro de finalización de actividades de Moodle.</small></p>';
}
echo $OUTPUT->footer();
