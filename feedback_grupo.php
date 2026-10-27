<?php
/**
 * feedback_grupo.php — T-306 (27/10/2026) · «Feedback del tutor» del grupo
 *
 * Todos los participantes de la acción formativa en una tabla: una fila por tutoría con el informe que el tutor escribió después
 * (copia de Learn en mdl_tuspeaking_tutor_feedback). Solo lo que permite tus_feedback_lib.php: curso encendido, informe que pasa los
 * controles de calidad o aprobado por En, y como mucho tantos como tutorías comunicadas a FUNDAE por participante. Solo tutorías
 * dentro de las fechas de la acción de cada participante (regla T-279e, igual que conexiones_grupo.php). Lo que falta no se marca.
 * Pantalla con filtro por participante + descarga .xlsx con la misma tabla. Solo lectura.
 * Acceso: report/progress:view (inspector/supervisor, profesor, admin), como el resto de páginas del bloque.
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');
require_once($CFG->dirroot . '/tus_feedback_lib.php');

$courseid = required_param('courseid', PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$filtro = optional_param('participante', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course, false);
require_capability('report/progress:view', $context);

$PAGE->set_url('/feedback_grupo.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Feedback del tutor - ' . format_string($course->fullname));
$PAGE->set_heading('Feedback del tutor');
$PAGE->set_pagelayout('incourse');

$tzm = new DateTimeZone('Europe/Madrid');
$CAMPOS = tus_feedback_campos();

// ─── Participantes del grupo (como conexiones_grupo.php) ───
$student = $DB->get_record('role', ['shortname' => 'student'], 'id', MUST_EXIST);
$participantes = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
    FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
    WHERE ra.contextid = ? AND ra.roleid = ? AND " . $DB->sql_like('u.email', '?', false, false, true) . "
    ORDER BY u.lastname, u.firstname", [$context->id, $student->id, '%tuspeaking.com%']);

// T-279e (26/10/2026), regla de En: solo tutorías dentro de las fechas de SU acción formativa (copia de conexiones_grupo.php).
$tus_afs = []; $tus_grupo = [];
try {
    foreach ($DB->get_records_sql("SELECT f.id, f.c_fundae, COALESCE(o.fecha_inicio, f.fecha_inicio) AS ini, COALESCE(o.fecha_fin, f.fecha_fin) AS fin
            FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ?", [$courseid]) as $f) {
        $tus_afs[(string)$f->c_fundae] = [substr((string)$f->ini, 0, 10), substr((string)$f->fin, 0, 10)];
    }
    foreach ($DB->get_records_sql("SELECT gm.id, gm.userid, g.name FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
            WHERE g.courseid = ?", [$courseid]) as $g) {
        if (isset($tus_afs[(string)$g->name])) { $tus_grupo[(int)$g->userid] = (string)$g->name; }
    }
} catch (Exception $e) { }
$en_su_accion = function ($userid, $t) use ($tus_afs, $tus_grupo) {
    if (!$tus_afs) { return true; }
    if (!$t) { return false; }
    $d = (new DateTime('@' . $t))->setTimezone(new DateTimeZone('Europe/Madrid'))->format('Y-m-d');
    $afs = isset($tus_grupo[(int)$userid]) ? [$tus_afs[$tus_grupo[(int)$userid]]] : array_values($tus_afs);
    foreach ($afs as $a) { if (($a[0] === '' || $a[0] <= $d) && ($a[1] === '' || $d <= $a[1])) { return true; } }
    return false;
};

// ─── Tutorías de cada participante (sin canceladas, dentro de su acción) ───
$clases = [];
foreach ($participantes as $u) {
    $rs = $DB->get_records_sql("SELECT z.id, z.acuity_datetime, z.acuity_canceled, CONCAT(t.firstname, ' ', t.lastname) AS profesor
            FROM " . $CFG->prefix . "i3code_acuityZoom z LEFT JOIN {user} t ON t.id = z.teacherid
            WHERE z.courseid = ? AND z.studentid = ?", [$courseid, $u->id]);
    foreach ($rs as $z) {
        if ((int)$z->acuity_canceled === 1) { continue; }
        try { $dt = new DateTime($z->acuity_datetime); $dt->setTimezone($tzm); } catch (Exception $e) { continue; }
        if (!$en_su_accion($u->id, $dt->getTimestamp())) { continue; }
        $clases[(int)$z->id] = ['ts' => $dt->getTimestamp(), 'fecha' => $dt->format('d/m/Y'), 'hora' => $dt->format('H:i'),
                                'profesor' => trim((string)$z->profesor), 'userid' => (int)$u->id, 'nombre' => fullname($u)];
    }
}
$feedback = tus_feedback_visibles($courseid, 0, array_keys($clases));

$filas = [];
foreach ($feedback as $zid => $fb) {
    $cl = $clases[$zid];
    if ($filtro && $cl['userid'] != $filtro) { continue; }
    $filas[] = $cl + ['fb' => $fb, 'escrito' => (new DateTime('@' . $fb->timewritten))->setTimezone($tzm)->format('d/m/Y')];
}
usort($filas, function ($a, $b) { return [$a['ts'], $a['nombre']] <=> [$b['ts'], $b['nombre']]; });
$con_feedback = [];
foreach ($feedback as $zid => $fb) { $con_feedback[$clases[$zid]['userid']] = true; }

// ─── Ficha FUNDAE (cabecera) ───
$ag = []; $ids = [];
try {
    $rs = $DB->get_recordset_sql("SELECT f.c_fundae, o.id_grupo FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ? ORDER BY f.c_fundae", [$courseid]);
    foreach ($rs as $f) { $ag[] = str_replace('-', '/', (string)$f->c_fundae); $ids[] = $f->id_grupo ?: '-'; }
    $rs->close();
} catch (Exception $e) { }
$NOTA = 'Informe que el tutor escribe después de cada tutoría y que el participante recibe por correo. Texto literal del tutor.';

// ─── Excel (.xlsx): la misma tabla, un campo por columna (respeta el filtro) ───
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Feedback_del_tutor_' . $limpio($course->fullname) . '_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Feedback');
    $ws->write_string(0, 0, 'Feedback del tutor · grupo', $f_titulo);
    $meta = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'], ['Curso', $course->fullname],
             ['Participante', $filtro && isset($participantes[$filtro]) ? fullname($participantes[$filtro]) : 'Todos']];
    foreach ($meta as $i => $m) { $ws->write_string(2 + $i, 0, $m[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$m[1]); }
    $r0 = 7;
    $cab = array_merge(['Fecha de la tutoría', 'Hora (Madrid)', 'Participante', 'Tutor', 'Escrito el'], array_values($CAMPOS));
    foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); $ws->set_column($c, $c, $c < 5 ? 18 : 45); }
    $r = $r0 + 1;
    foreach ($filas as $f) {
        $v = [$f['fecha'], $f['hora'], $f['nombre'], $f['fb']->teacher_name, $f['escrito']];
        foreach (array_keys($CAMPOS) as $k) { $v[] = (string)($f['fb']->datos[$k] ?? ''); }
        foreach ($v as $c => $x) { $ws->write_string($r, $c, $x); }
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
.fg-meta{background:#e0f7fa;border-left:4px solid #008ba3;padding:8px 12px;margin:8px 0}
.fg-meta b{display:inline-block;min-width:200px}
.fg-wrap{overflow-x:auto;margin:10px 0 18px}
.fg-tabla{border-collapse:collapse;font-size:12.5px;width:100%}
.fg-tabla th{background:#008ba3;color:#fff;padding:6px 7px;text-align:left;white-space:nowrap}
.fg-tabla td{border-bottom:1px solid #e2e8f0;padding:6px 7px;vertical-align:top}
.fg-tabla td.nw{white-space:nowrap}
.fg-tabla details summary{cursor:pointer;color:#008ba3;font-weight:600}
.fg-tabla dl{margin:6px 0 0}
.fg-tabla dt{margin-top:8px;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#64748b}
.fg-tabla dd{margin:2px 0 0;overflow-wrap:anywhere}
.fg-nota{color:#666;font-style:italic;font-size:12px}
</style>
<h2>Feedback del tutor</h2>
<div class="fg-meta">
  <div><b>Acción / grupo</b><?php echo s($ag ? implode(', ', $ag) : '-'); ?></div>
  <div><b>ID de grupo FUNDAE</b><?php echo s($ids ? implode(', ', $ids) : '-'); ?></div>
  <div><b>Curso</b><?php echo format_string($course->fullname); ?></div>
</div>
<?php
if (!tus_feedback_max($courseid) || !$feedback) {
    echo $OUTPUT->notification('Este curso no tiene feedback del tutor para mostrar.', 'info');
    echo $OUTPUT->footer();
    exit;
}
echo '<form method="get" style="margin:6px 0"><input type="hidden" name="courseid" value="' . $courseid . '">'
   . '<label>Participante: <select name="participante" onchange="this.form.submit()"><option value="0">Todos</option>';
foreach ($participantes as $u) {
    if (empty($con_feedback[$u->id])) { continue; }
    echo '<option value="' . $u->id . '"' . ($filtro == $u->id ? ' selected' : '') . '>' . s(fullname($u)) . '</option>';
}
echo '</select></label> <noscript><button type="submit">Filtrar</button></noscript> &nbsp; ';
echo '<a class="btn btn-secondary" href="' . (new moodle_url('/feedback_grupo.php', ['courseid' => $courseid, 'participante' => $filtro, 'export' => 'excel']))->out() . '">Descargar Excel</a></form>';
echo '<p>' . count($filas) . ' informes.</p>';
echo '<div class="fg-wrap"><table class="fg-tabla"><thead><tr>';
foreach (['Fecha de la tutoría', 'Hora (Madrid)', 'Participante', 'Tutor', 'Escrito el', 'Informe'] as $t) { echo '<th>' . s($t) . '</th>'; }
echo '</tr></thead><tbody>';
foreach ($filas as $f) {
    $tema = trim((string)($f['fb']->datos['main_topic'] ?? ''));
    echo '<tr><td class="nw">' . s($f['fecha']) . '</td><td class="nw">' . s($f['hora']) . '</td><td class="nw">' . s($f['nombre']) . '</td>'
       . '<td class="nw">' . s($f['fb']->teacher_name) . '</td><td class="nw">' . s($f['escrito']) . '</td><td><details><summary>'
       . s($tema !== '' ? $tema : 'Ver informe') . '</summary><dl>';
    foreach ($CAMPOS as $k => $etq) {
        $v = trim((string)($f['fb']->datos[$k] ?? ''));
        if ($v === '') { continue; }
        echo '<dt>' . s($etq) . '</dt><dd>' . nl2br(s($v)) . '</dd>';
    }
    echo '</dl></details></td></tr>';
}
echo '</tbody></table></div>';
echo '<p class="fg-nota">' . s($NOTA) . '</p>';
echo $OUTPUT->footer();
