<?php
/**
 * tutorias_programadas.php — T-193 (15/10/2026) · «Tutorías programadas» del grupo (para entrar en directo)
 *
 * Acta SEPE 14/10, punto 2: «se debe de poder acceder por nuestra parte en tiempo real, y para ello tendrán que comunicar la
 * programación previamente de las videoconferencias». Lista las tutorías por videoconferencia (Acuity + Zoom, mdl_i3code_acuityZoom)
 * de los participantes del grupo que aún no han terminado: la que está en curso y las próximas, con fecha y hora (Madrid), duración,
 * profesor que la da, participante, cuándo se reservó y estado. El botón «Entrar en la videoconferencia» (enlace de Zoom de la reserva)
 * SOLO lo ve el inspector (rol supervisor en el curso) o un administrador; el resto de usuarios ve la programación sin enlace.
 * Excel y calendario (.ics) para tener la programación por adelantado. Canceladas fuera. Solo lectura.
 * Participantes: rol de alumno, sin cuentas @tuspeaking (como reservas_grupo.php). Acceso: report/progress:view.
 * Ubicación: /mnt/moodle-data/moodle-code/tutorias_programadas.php
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');

$courseid = required_param('courseid', PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course, false);
require_capability('report/progress:view', $context);

$PAGE->set_url('/tutorias_programadas.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Tutorías programadas - ' . format_string($course->fullname));
$PAGE->set_heading('Tutorías programadas');
$PAGE->set_pagelayout('incourse');

$ahora = time();
// ¿Puede ver el enlace para entrar? Solo inspector (supervisor) del curso o administrador.
$puede_entrar = is_siteadmin();
if (!$puede_entrar) { foreach (get_user_roles($context, $USER->id, true) as $ra) { if ($ra->shortname === 'supervisor') { $puede_entrar = true; break; } } }

// ─── Participantes del grupo (como reservas_grupo.php) ───
$student = $DB->get_record('role', ['shortname' => 'student'], 'id', MUST_EXIST);
$participantes = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
    FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
    WHERE ra.contextid = ? AND ra.roleid = ? AND " . $DB->sql_like('u.email', '?', false, false, true) . "
    ORDER BY u.lastname, u.firstname", [$context->id, $student->id, '%tuspeaking.com%']);

// ─── Ficha FUNDAE (cabecera) ───
$ag = []; $ids = [];
try {
    $rs = $DB->get_recordset_sql("SELECT f.c_fundae, o.id_grupo FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ? ORDER BY f.c_fundae", [$courseid]);
    foreach ($rs as $f) { $ag[] = str_replace('-', '/', (string)$f->c_fundae); $ids[] = $f->id_grupo ?: '-'; }
    $rs->close();
} catch (Exception $e) { }

// ─── Tutorías no terminadas ───
$tz = new DateTimeZone('Europe/Madrid');
$ts = function ($s) { if ($s === null || $s === '') { return 0; } try { return (new DateTime($s))->getTimestamp(); } catch (Exception $e) { return 0; } };
$fmt = function ($t, $f) use ($tz) { return $t ? (new DateTime('@' . $t))->setTimezone($tz)->format($f) : ''; };
$enlace = function ($z) {
    foreach ([(string)$z->zoom_url, (string)$z->acuity_location] as $s) {
        if (preg_match('~https://[a-z0-9.-]*zoom\.us/(j|w|my)/[^\s"\'<>]+~i', $s, $m)) { return $m[0]; }
    }
    return '';
};
$hay_detalle = true;
try { $DB->get_records_sql('SELECT acuityid FROM own_acuity_detalle LIMIT 1'); } catch (Exception $e) { $hay_detalle = false; }
$filas = [];
if ($participantes) {
    list($in, $p) = $DB->get_in_or_equal(array_keys($participantes));
    $rs = $DB->get_recordset_sql("SELECT z.id, z.acuityid, z.studentid, z.acuity_datetime, z.acuity_duration, z.acuity_canceled, z.acuity_rescheduled,
            z.zoom_url, z.acuity_location, z.zoom_meetingid, CONCAT(t.firstname, ' ', t.lastname) AS profesor" . ($hay_detalle ? ", d.datetime_created" : '') . "
        FROM mdl_i3code_acuityZoom z LEFT JOIN {user} t ON t.id = z.teacherid" . ($hay_detalle ? " LEFT JOIN own_acuity_detalle d ON d.acuityid = z.acuityid" : '') . "
        WHERE z.courseid = ? AND z.studentid $in AND (z.acuity_canceled IS NULL OR z.acuity_canceled = 0)", array_merge([$courseid], $p));
    foreach ($rs as $z) {
        $ini = $ts($z->acuity_datetime); if (!$ini) { continue; }
        $dur = (int)$z->acuity_duration ?: 30; $fin = $ini + $dur * 60;
        if ($fin <= $ahora) { continue; }                        // ya terminada
        $encurso = $ini <= $ahora;
        $min = (int)ceil(($ini - $ahora) / 60);
        $estado = $encurso ? 'En curso ahora' : ($min <= 60 ? "Empieza en $min min" : ($fmt($ini, 'Y-m-d') === $fmt($ahora, 'Y-m-d') ? 'Hoy' : 'Programada'));
        $creada = $hay_detalle && !empty($z->datetime_created) ? $fmt($ts($z->datetime_created), 'd/m/Y H:i') : '';
        $filas[] = ['ini' => $ini, 'fin' => $fin, 'encurso' => $encurso, 'url' => $enlace($z), 'prof' => trim((string)$z->profesor) ?: 'Sin datos',
                    'alumno' => fullname($participantes[$z->studentid]), 'v' => [$fmt($ini, 'd/m/Y'), $fmt($ini, 'H:i'), $fmt($fin, 'H:i'), $dur,
                    trim((string)$z->profesor) ?: 'Sin datos', fullname($participantes[$z->studentid]), $creada, $estado, (string)($z->zoom_meetingid ?: '')]];
    }
    $rs->close();
}
usort($filas, function ($a, $b) { return [$a['ini'], $a['v'][5]] <=> [$b['ini'], $b['v'][5]]; });
$CAB = ['Fecha', 'Hora de inicio (Madrid)', 'Hora de fin (Madrid)', 'Duración (min)', 'Profesor', 'Participante', 'Reservada el (Madrid)', 'Estado', 'Zoom ID'];
$NOTA = 'Tutorías individuales por videoconferencia (Zoom) reservadas por los participantes del grupo que aún no han terminado: la que está en curso y las próximas. Hora de Madrid. Las canceladas no salen; el historial completo está en «Tutorías: reservas del grupo». El botón «Entrar» abre la reunión de Zoom de la tutoría; si el profesor tiene la sala de espera activada, le admitirá.';
$limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };

// ─── Calendario (.ics) ───
if ($export === 'ics') {
    $esc = function ($s) { return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s); };
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//tuSpeaking//Tutorias programadas//ES\r\nCALSCALE:GREGORIAN\r\n";
    foreach ($filas as $f) {
        $out .= "BEGIN:VEVENT\r\nUID:tutoria-" . md5($f['ini'] . $f['alumno'] . $courseid) . "@aula.tuspeaking.com\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n"
              . "DTSTART:" . gmdate('Ymd\THis\Z', $f['ini']) . "\r\nDTEND:" . gmdate('Ymd\THis\Z', $f['fin']) . "\r\n"
              . "SUMMARY:" . $esc('Tutoría por videoconferencia · ' . $course->fullname) . "\r\n"
              . "DESCRIPTION:" . $esc('Profesor: ' . $f['prof'] . "\nParticipante: " . $f['alumno'] . ($ag ? "\nAcción / grupo: " . implode(', ', $ag) : '') . ($puede_entrar && $f['url'] ? "\nEntrar: " . $f['url'] : '')) . "\r\n"
              . ($puede_entrar && $f['url'] ? "URL:" . $f['url'] . "\r\n" : '') . "END:VEVENT\r\n";
    }
    $out .= "END:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="Tutorias_programadas_' . $limpio($course->fullname) . '.ics"');
    echo $out; exit;
}

// ─── Excel (.xlsx) ───
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Tutorias_programadas_' . $limpio($course->fullname) . '_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Tutorías programadas');
    $ws->write_string(0, 0, 'Tutorías programadas · grupo', $f_titulo);
    $meta = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'], ['Curso', $course->fullname],
             ['Generado el', $fmt($ahora, 'd/m/Y H:i') . ' (Madrid)']];
    foreach ($meta as $i => $m) { $ws->write_string(2 + $i, 0, $m[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$m[1]); }
    $cab = $CAB; if ($puede_entrar) { $cab[] = 'Enlace para entrar'; }
    $r0 = 7; foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([11, 12, 12, 10, 24, 28, 18, 16, 14, 60] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($filas as $f) {
        foreach ($f['v'] as $c => $v) { if (is_int($v)) { $ws->write_number($r, $c, $v); } else { $ws->write_string($r, $c, (string)$v); } }
        if ($puede_entrar) { $ws->write_string($r, count($f['v']), $f['url']); }
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
.tp-meta{background:#e0f7fa;border-left:4px solid #008ba3;padding:8px 12px;margin:8px 0}
.tp-meta b{display:inline-block;min-width:200px}
.tp-wrap{overflow-x:auto;margin:10px 0 18px}
.tp-tabla{border-collapse:collapse;font-size:13px;width:100%}
.tp-tabla th{background:#008ba3;color:#fff;padding:6px 8px;text-align:left;white-space:nowrap}
.tp-tabla td{border-bottom:1px solid #e2e8f0;padding:5px 8px;white-space:nowrap}
.tp-tabla tr.tp-encurso td{background:#e8f5e9;font-weight:bold}
.tp-entrar{background:#2e7d32;color:#fff !important;padding:4px 10px;border-radius:4px;text-decoration:none;display:inline-block}
.tp-nota{color:#666;font-style:italic;font-size:12px}
</style>
<h2>Tutorías programadas</h2>
<div class="tp-meta">
  <div><b>Acción / grupo</b><?php echo s($ag ? implode(', ', $ag) : '-'); ?></div>
  <div><b>ID de grupo FUNDAE</b><?php echo s($ids ? implode(', ', $ids) : '-'); ?></div>
  <div><b>Curso</b><?php echo format_string($course->fullname); ?></div>
  <div><b>Consultado</b><?php echo s($fmt($ahora, 'd/m/Y H:i')); ?> (hora de Madrid)</div>
</div>
<?php
echo '<p><a class="btn btn-secondary" href="' . (new moodle_url('/tutorias_programadas.php', ['courseid' => $courseid, 'export' => 'excel']))->out() . '">Descargar Excel</a> '
   . '<a class="btn btn-secondary" href="' . (new moodle_url('/tutorias_programadas.php', ['courseid' => $courseid, 'export' => 'ics']))->out() . '">Añadir a mi calendario (.ics)</a></p>';
$nen = count(array_filter($filas, function ($f) { return $f['encurso']; }));
echo '<p>' . count($filas) . ' tutorías programadas' . ($nen ? ' · <b>' . $nen . ' en curso ahora</b>' : '') . '.</p>';
echo '<div class="tp-wrap"><table class="tp-tabla"><thead><tr>';
foreach ($CAB as $t) { echo '<th>' . s($t) . '</th>'; }
if ($puede_entrar) { echo '<th>Entrar</th>'; }
echo '</tr></thead><tbody>';
foreach ($filas as $f) {
    echo '<tr' . ($f['encurso'] ? ' class="tp-encurso"' : '') . '>';
    foreach ($f['v'] as $v) { echo '<td>' . s((string)$v) . '</td>'; }
    if ($puede_entrar) {
        echo '<td>' . ($f['url'] ? '<a class="tp-entrar" target="_blank" rel="noopener" href="' . s($f['url']) . '">Entrar en la videoconferencia</a>' : 'Sin enlace') . '</td>';
    }
    echo '</tr>';
}
if (!$filas) { echo '<tr><td colspan="' . (count($CAB) + ($puede_entrar ? 1 : 0)) . '">No hay tutorías programadas en este momento.</td></tr>'; }
echo '</tbody></table></div>';
echo '<p class="tp-nota">' . s($NOTA) . '</p>';
echo $OUTPUT->footer();
