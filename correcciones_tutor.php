<?php
/**
 * correcciones_tutor.php — T-279 (26/10/2026) · Correcciones propuestas para que la TUTORA las revise y las ponga con SU usuario.
 *
 * Acta SEPE 10/06 [P6] y 14/10 [4]: las entregas se corrigen durante el curso y las firma el tutor comunicado. Para que la tutora
 * no se pierda en Moodle, una sola página con todas sus entregas pendientes de un «lote»: el trabajo del alumno (texto o audio),
 * la nota y el comentario propuestos (editables) y un botón «Save and send to the student» por entrega. Guarda con la API de
 * Moodle (assign::save_grade) como el usuario que ha entrado, así que la corrección queda a nombre de la tutora, con su fecha,
 * y Moodle avisa al alumno como siempre.
 *
 * El lote (tutora, fecha de caducidad y entregas con su propuesta) NO está en este fichero: vive en mdl_config_plugins
 * (plugin «tuspeaking_correcciones», clave «lote_<clave>») y lo carga/borra tuspeaking-lms/scripts/t279/t279-105. Así el repo
 * no lleva datos de alumnos. URL: /correcciones_tutor.php?lote=<clave>. Sin lote, caducado o de otra persona: no se ve nada.
 * Acceso: con sesión; guardar solo la tutora del lote (y con mod/assign:grade en la tarea); un administrador solo lo ve (vista previa).
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$clave = required_param('lote', PARAM_ALPHANUM);
require_login(null, false);

$PL = 'tuspeaking_correcciones';
$lote = json_decode((string)get_config($PL, 'lote_' . $clave));
$ahora = time();
if (!$lote || empty($lote->items) || (int)$lote->tutor <= 0 || (int)$lote->caduca < $ahora) {
    throw new moodle_exception('nopermissions', 'error', '', 'this page (link not valid or expired)');
}
$estutora = ((int)$USER->id === (int)$lote->tutor);
$previa = !$estutora && is_siteadmin();
if (!$estutora && !$previa) {
    throw new moodle_exception('nopermissions', 'error', '', 'this page');
}

$PAGE->set_url('/correcciones_tutor.php', ['lote' => $clave]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Corrections to review');
$PAGE->set_heading('Corrections to review');

$mid = (int)$DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);
$cargar = function ($it) use ($DB, $mid) {
    $a = $DB->get_record('assign', ['id' => (int)$it->assign], '*', IGNORE_MISSING);
    if (!$a) { return null; }
    $cm = $DB->get_record('course_modules', ['instance' => $a->id, 'module' => $mid], '*', IGNORE_MISSING);
    $s = $DB->get_record_sql("SELECT * FROM {assign_submission} WHERE assignment = ? AND userid = ? AND latest = 1 ORDER BY attemptnumber DESC",
        [$a->id, (int)$it->user], IGNORE_MULTIPLE);
    if (!$cm || !$s) { return null; }
    $g = $DB->get_record_sql("SELECT * FROM {assign_grades} WHERE assignment = ? AND userid = ? AND attemptnumber = ?",
        [$a->id, $s->userid, $s->attemptnumber], IGNORE_MULTIPLE);
    return (object)['a' => $a, 'cm' => $cm, 's' => $s, 'g' => ($g && $g->grade !== null && $g->grade >= 0) ? $g : null,
        'ctx' => context_module::instance($cm->id), 'it' => $it];
};

// ─── Guardar (solo la tutora) ───
if ($estutora && optional_param('guardar', 0, PARAM_INT) && confirm_sesskey()) {
    $n = required_param('n', PARAM_INT);
    $nota = trim(str_replace(',', '.', required_param('nota', PARAM_RAW_TRIMMED)));
    $texto = trim(required_param('feedback', PARAM_RAW));
    $it = $lote->items[$n] ?? null;
    $e = $it ? $cargar($it) : null;
    $url = new moodle_url('/correcciones_tutor.php', ['lote' => $clave], 'e' . $n);
    if (!$e) {
        redirect($url, 'This submission could not be found.', null, \core\output\notification::NOTIFY_ERROR);
    }
    require_capability('mod/assign:grade', $e->ctx);
    if (!is_numeric($nota) || $nota < 0 || $nota > (float)$e->a->grade || $texto === '') {
        redirect($url, 'Please write a grade between 0 and ' . (int)$e->a->grade . ' and a comment.', null, \core\output\notification::NOTIFY_ERROR);
    }
    $course = get_course($e->a->course);
    $assign = new assign($e->ctx, $e->cm, $course);
    $datos = (object)[
        'grade' => (float)$nota, 'attemptnumber' => -1, 'addattempt' => 0, 'workflowstate' => '', 'applytoall' => 0,
        'sendstudentnotifications' => 1,
        'assignfeedbackcomments_editor' => ['text' => nl2br(s($texto)), 'format' => FORMAT_HTML],
    ];
    $assign->save_grade($e->s->userid, $datos);
    redirect($url, 'Saved. The student can now see your grade and comment.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// ─── Pintar ───
$fs = get_file_storage();
$filas = []; $hechas = 0;
foreach ($lote->items as $n => $it) {
    $e = $cargar($it);
    if ($e && $e->g) { $hechas++; }
    $filas[$n] = $e;
}
$total = count($lote->items);
$tz = new DateTimeZone('Europe/Madrid');
$fecha = function ($t) use ($tz) { return $t ? (new DateTime('@' . $t))->setTimezone($tz)->format('d/m/Y H:i') : ''; };

echo $OUTPUT->header();
echo '<style>.tsc-card{border:1px solid #d6dde3;border-radius:6px;padding:16px;margin:0 0 18px;background:#fff}
.tsc-card.done{background:#f1f8f2;border-color:#9fd3a9}.tsc-meta{color:#555;font-size:.9em;margin:2px 0 10px}
.tsc-work{background:#f7f9fa;border-left:4px solid #00bcd4;padding:10px 12px;white-space:pre-wrap;margin:8px 0}
.tsc-card textarea{width:100%;min-height:120px}.tsc-card input.nota{width:90px}.tsc-ok{color:#1e7e34;font-weight:bold}</style>';
echo html_writer::tag('p', html_writer::tag('strong', s($lote->nombre ?? 'Corrections')) . ' · ' . $hechas . ' of ' . $total . ' saved · link valid until ' . $fecha((int)$lote->caduca));
if ($previa) {
    echo $OUTPUT->notification('Preview for administrators: only the tutor of this list can save.', 'info');
}
echo html_writer::tag('p', 'For each one: read or listen to the student\'s work, check the proposed grade and comment (change them if you want), then click <strong>Save and send to the student</strong>. Your grade and comment are saved with your name and the student is notified.');
if ($hechas === $total) {
    echo $OUTPUT->notification('All done. Thank you!', 'success');
}

foreach ($filas as $n => $e) {
    if (!$e) {
        echo html_writer::div('Submission ' . ($n + 1) . ': not found (it may have been deleted).', 'tsc-card');
        continue;
    }
    $u = core_user::get_user($e->s->userid);
    $curso = format_string($DB->get_field('course', 'fullname', ['id' => $e->a->course]));
    $clase = 'tsc-card' . ($e->g ? ' done' : '');
    echo html_writer::start_div($clase, ['id' => 'e' . $n]);
    echo html_writer::tag('h4', ($n + 1) . '. ' . s(fullname($u)) . ' · ' . format_string($e->a->name));
    echo html_writer::div(s($curso) . ' · submitted ' . $fecha($e->s->timemodified), 'tsc-meta');
    echo html_writer::tag('details', html_writer::tag('summary', 'Task instructions') . html_writer::div(format_text($e->a->intro, $e->a->introformat, ['context' => $e->ctx])));
    $txt = $DB->get_field('assignsubmission_onlinetext', 'onlinetext', ['submission' => $e->s->id]);
    if ($txt !== false && trim(strip_tags($txt)) !== '') {
        echo html_writer::div(s(trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], "\n", $txt)), ENT_QUOTES, 'UTF-8'))), 'tsc-work');
    }
    foreach ($fs->get_area_files($e->ctx->id, 'assignsubmission_file', 'submission_files', $e->s->id, 'filename', false) as $f) {
        $furl = moodle_url::make_pluginfile_url($e->ctx->id, 'assignsubmission_file', 'submission_files', $e->s->id, $f->get_filepath(), $f->get_filename());
        if (strpos($f->get_mimetype(), 'audio/') === 0) {
            echo html_writer::div(html_writer::tag('audio', '', ['controls' => 'controls', 'preload' => 'none', 'src' => $furl->out(false), 'style' => 'width:100%']) .
                html_writer::link($furl, 'Download the audio (' . s($f->get_filename()) . ')'), 'tsc-work');
        } else {
            echo html_writer::div(html_writer::link($furl, 'Open the file: ' . s($f->get_filename())), 'tsc-work');
        }
    }
    if ($e->g) {
        $quien = core_user::get_user($e->g->grader);
        $com = $DB->get_field('assignfeedback_comments', 'commenttext', ['grade' => $e->g->id]);
        echo html_writer::tag('p', html_writer::span('✔ Saved', 'tsc-ok') . ' by ' . s($quien ? fullname($quien) : '?') . ' on ' . $fecha($e->g->timemodified) .
            ' · grade ' . format_float($e->g->grade, 2) . ' / ' . (int)$e->a->grade);
        if ($com) { echo html_writer::div(format_text($com, FORMAT_HTML), 'tsc-meta'); }
    } else {
        $prop = isset($e->it->grade) && $e->it->grade !== null ? format_float($e->it->grade, 1) : '';
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/correcciones_tutor.php', ['lote' => $clave]))->out(false) . '#e' . $n]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'guardar', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'n', 'value' => $n]);
        echo html_writer::tag('label', 'Grade (out of ' . (int)$e->a->grade . '): ', ['for' => 'nota' . $n]);
        echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'nota', 'id' => 'nota' . $n, 'class' => 'nota', 'value' => $prop,
            'placeholder' => $prop === '' ? 'listen first' : '', 'disabled' => $previa ? 'disabled' : null]);
        echo html_writer::tag('p', 'Comment for the student:', ['style' => 'margin:10px 0 4px']);
        echo html_writer::tag('textarea', s($e->it->feedback ?? ''), ['name' => 'feedback', 'disabled' => $previa ? 'disabled' : null,
            'placeholder' => 'Thank you for your recording. Strengths: … To improve: … Keep practising!']);
        echo html_writer::tag('p', html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
            'value' => 'Save and send to the student', 'disabled' => $previa ? 'disabled' : null]), ['style' => 'margin-top:10px']);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
