<?php
/**
 * finalizacion_75.php — T-189 Fase 3 (15/10/2026) · «Finalización de actividad (75 %)». Igual que la página de prueba (md5 d76b7987…) sin el aviso
 * «PRUEBA». Resumen por alumno: controles REALIZADOS (H5P con nota, cuestionario terminado, tarea entregada) y «Finaliza (≥ 75 %)», + «Teoría vista».
 * Controles = actividades visibles de los temas (sección > 1, modelo de curso: la sección 1 es la guía). El bloque «Seguimiento y control · FUNDAE»
 * enlaza aquí SOLO en los cursos de CENIEH (3230–3234), cuyas guías declaran este criterio; el resto sigue con finalizacion_actividad.php.
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

$PAGE->set_url('/finalizacion_75.php', ['courseid' => $courseid]);
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
// ─── 75 % de controles realizados (T-189): controles del curso y evidencia de cada alumno ───
$ctrl = $DB->get_records_sql("SELECT cm.id, m.name AS tipo, cm.instance FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
    JOIN {course_sections} cs ON cs.id = cm.section
    LEFT JOIN {grade_items} gi ON gi.courseid = cm.course AND gi.itemtype = 'mod' AND gi.itemmodule = 'hvp' AND gi.iteminstance = cm.instance AND gi.itemnumber = 0 AND m.name = 'hvp'
    WHERE cm.course = ? AND cm.visible = 1 AND cm.deletioninprogress = 0 AND cs.section > 1
    AND (m.name IN ('quiz', 'assign') OR (m.name = 'hvp' AND gi.gradetype > 0))", [$courseid]);
$nctrl = count($ctrl);
$realizado = []; // [userid][cmid] => fecha
if ($participantes && $ctrl) {
    $porinst = [];
    foreach ($ctrl as $c) { $porinst[$c->tipo][$c->instance] = $c->id; }
    list($inu, $pu) = $DB->get_in_or_equal(array_keys($participantes));
    $consultas = [
        'hvp' => "SELECT gg.id, gg.userid, gi.iteminstance AS inst, gg.timemodified AS t FROM {grade_items} gi JOIN {grade_grades} gg ON gg.itemid = gi.id
                  WHERE gi.courseid = ? AND gi.itemtype = 'mod' AND gi.itemmodule = 'hvp' AND gi.itemnumber = 0 AND gg.finalgrade IS NOT NULL AND gi.iteminstance %IN% AND gg.userid %INU%",
        'quiz' => "SELECT MIN(qa.id) AS id, qa.userid, qa.quiz AS inst, MIN(qa.timefinish) AS t FROM {quiz_attempts} qa
                  WHERE ? > 0 AND qa.state = 'finished' AND qa.quiz %IN% AND qa.userid %INU% GROUP BY qa.userid, qa.quiz",
        'assign' => "SELECT s.id, s.userid, s.assignment AS inst, s.timemodified AS t FROM {assign_submission} s
                  WHERE ? > 0 AND s.status = 'submitted' AND s.latest = 1 AND s.assignment %IN% AND s.userid %INU%",
    ];
    foreach ($consultas as $tipo => $sql) {
        if (empty($porinst[$tipo])) { continue; }
        list($ini, $pi) = $DB->get_in_or_equal(array_keys($porinst[$tipo]));
        $rs = $DB->get_recordset_sql(str_replace(['%IN%', '%INU%'], [$ini, $inu], $sql), array_merge([$courseid], $pi, $pu));
        foreach ($rs as $x) { $realizado[$x->userid][$porinst[$tipo][$x->inst]] = (int)$x->t; }
        $rs->close();
    }
}
$resumen = function ($uid) use ($realizado, $nctrl) {
    $r = isset($realizado[$uid]) ? count($realizado[$uid]) : 0;
    return [$r, $nctrl ? (int)round(100 * $r / $nctrl) : 0, $nctrl && $r * 4 >= $nctrl * 3];
};
// ─── Teoría vista (T-190): ficheros de teoría de los temas con seguimiento «al abrir»; NO cuenta para el 75 % ───
$teoria = $DB->get_fieldset_sql("SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
    JOIN {course_sections} cs ON cs.id = cm.section WHERE cm.course = ? AND cm.visible = 1 AND cm.deletioninprogress = 0 AND cs.section > 1", [$courseid]);
$nteoria = count($teoria);
$vista = []; // [userid] => nº de ficheros vistos
if ($participantes && $teoria) {
    list($inu, $pu) = $DB->get_in_or_equal(array_keys($participantes));
    list($int, $pt) = $DB->get_in_or_equal($teoria);
    $rs = $DB->get_recordset_sql("SELECT cc.userid, COUNT(*) AS n FROM {course_modules_completion} cc JOIN {course_modules} cm ON cm.id = cc.coursemoduleid AND cm.completion > 0
        WHERE cc.userid $inu AND cc.coursemoduleid $int AND cc.completionstate IN (1, 2) GROUP BY cc.userid", array_merge($pu, $pt));
    foreach ($rs as $x) { $vista[$x->userid] = (int)$x->n; }
    $rs->close();
}
$NOTATEORIA = 'Teoría vista: ficheros de teoría (PDF y PowerPoint) de los temas que el alumno ha abierto. No cuenta para el 75 %.';
$NOTA75 = 'Controles: actividades de los temas que se corrigen (H5P con nota, cuestionarios y tareas). Realizado: H5P con nota, cuestionario terminado o tarea entregada. Finaliza: ha realizado al menos el 75 % de los controles.';

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
    $wr = $workbook->add_worksheet('Controles 75 %');
    $wr->write_string(0, 0, 'Controles realizados (criterio del 75 %)', $f_titulo);
    $wr->write_string(2, 0, 'Curso', $f_negrita); $wr->write_string(2, 1, $course->fullname);
    $wr->write_string(3, 0, 'Controles del curso', $f_negrita); $wr->write_number(3, 1, $nctrl);
    $wr->write_string(4, 0, 'Descargado', $f_negrita); $wr->write_string(4, 1, $fecha($ahora));
    foreach (['Participante', 'Email', 'Matrícula', 'Controles realizados', 'Controles del curso', '%', 'Finaliza (≥ 75 %)', 'Teoría vista', 'Ficheros de teoría'] as $c => $tx) { $wr->write_string(6, $c, $tx, $f_cabecera); }
    foreach ([28, 34, 30, 20, 18, 8, 18, 14, 18] as $c => $a) { $wr->set_column($c, $c, $a); }
    $r = 7;
    foreach ($participantes as $u) {
        list($nr, $pct, $fin) = $resumen($u->id);
        $wr->write_string($r, 0, fullname($u)); $wr->write_string($r, 1, $u->email); $wr->write_string($r, 2, $matricula[$u->id] ?? 'Sin matrícula');
        $wr->write_number($r, 3, $nr); $wr->write_number($r, 4, $nctrl); $wr->write_number($r, 5, $pct); $wr->write_string($r, 6, $fin ? 'SÍ' : 'No');
        $wr->write_number($r, 7, $vista[$u->id] ?? 0); $wr->write_number($r, 8, $nteoria);
        $r++;
    }
    $wr->write_string($r + 1, 0, $NOTA75, $f_nota);
    $wr->write_string($r + 2, 0, $NOTATEORIA, $f_nota);
    $ws = $workbook->add_worksheet('Finalización');
    $ws->write_string(0, 0, 'Finalización de actividad', $f_titulo);
    $filas = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'],
              ['Curso', $course->fullname], ['Descargado', $fecha($ahora)]];
    foreach ($filas as $i => $f) { $ws->write_string(2 + $i, 0, $f[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$f[1]); }
    $r0 = 7;
    $cab = ['Participante', 'Email', 'Matrícula', 'Actividad', 'Tipo', 'Estado', 'Fecha de finalización', 'Control (cuenta para el 75 %)', 'Realizado'];
    foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([28, 34, 30, 50, 16, 24, 20, 16, 12] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($participantes as $u) {
        foreach ($actividades as $cmid => $a) {
            list($st, $t) = $estado[$u->id][$cmid] ?? [COMPLETION_INCOMPLETE, 0];
            $fila = [fullname($u), $u->email, $matricula[$u->id] ?? 'Sin matrícula', $a['nombre'], $a['tipo'], $etiqueta[$st] ?? (string)$st, $hecha($st) ? $fecha($t) : '',
                     isset($ctrl[$cmid]) ? 'Sí' : 'No', isset($ctrl[$cmid]) ? (isset($realizado[$u->id][$cmid]) ? 'Sí' : 'No') : ''];
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
.fa-res{border-collapse:collapse;font-size:13px;margin:8px 0 14px}.fa-res th,.fa-res td{border:1px solid #dcdcdc;padding:5px 9px}
.fa-res th{background:#008ba3;color:#fff;text-align:left}.fa-ok{color:#1e8449;font-weight:bold}.fa-ko{color:#c0392b;font-weight:bold}
.fa-prueba{background:#fff3cd;border:1px solid #ffe08a;padding:6px 10px;margin:8px 0}
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
    echo '<h3>Controles realizados (criterio del 75 %)</h3><p>' . s($NOTA75) . ' Controles de este curso: <b>' . $nctrl . '</b>; para finalizar hacen falta <b>' . (int)ceil($nctrl * 0.75) . '</b>.</p>';
    echo '<table class="fa-res"><tr><th>Participante</th><th>Matrícula</th><th>Controles realizados</th><th>%</th><th>Finaliza (≥ 75 %)</th><th>Teoría vista</th></tr>';
    foreach ($participantes as $u) {
        list($nr, $pct, $fin) = $resumen($u->id);
        echo '<tr><td>' . s(fullname($u)) . '</td><td>' . s($matricula[$u->id] ?? 'Sin matrícula') . '</td><td>' . $nr . ' de ' . $nctrl . '</td><td>' . $pct . ' %</td><td class="' . ($fin ? 'fa-ok">SÍ' : 'fa-ko">No') . '</td><td>' . ($vista[$u->id] ?? 0) . ' de ' . $nteoria . '</td></tr>';
    }
    echo '</table><p><small>' . s($NOTATEORIA) . '</small></p><h3>Detalle por actividad</h3>';
    echo '<p><a class="btn btn-secondary" href="' . (new moodle_url('/finalizacion_75.php', ['courseid' => $courseid, 'export' => 'excel']))->out() . '">Descargar Excel</a></p>';
    echo '<div class="fa-wrap"><table class="fa-tabla"><thead><tr><th>Participante</th><th>Matrícula</th>';
    foreach ($actividades as $a) { echo '<th class="fa-act" title="' . s($a['nombre']) . '"><div>' . s($a['nombre']) . '</div></th>'; }
    echo '<th>Completadas</th><th>Controles realizados</th><th>Finaliza (≥ 75 %)</th><th>Teoría vista</th></tr></thead><tbody>';
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
        list($nr, $pct, $fin) = $resumen($u->id);
        echo '<td>' . $n . ' de ' . count($actividades) . '</td><td>' . $nr . ' de ' . $nctrl . ' (' . $pct . ' %)</td><td class="' . ($fin ? 'fa-ok">SÍ' : 'fa-ko">No') . '</td><td>' . ($vista[$u->id] ?? 0) . ' de ' . $nteoria . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<p><small>✔ completada · <span class="fa-fail">✔</span> completada sin aprobar · — no completada. Fuente: registro de finalización de actividades de Moodle.</small></p>';
}
echo $OUTPUT->footer();
