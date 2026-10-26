<?php
/**
 * tutorias_programadas_todas.php — T-279e (26/10/2026) · «Tutorías programadas · todas las acciones» para el inspector FUNDAE/SEPE.
 *
 * Actas del SEPE: 10/06 [P2] «se podrá acceder a visualizar las tutorías previstas»; 14/10 [2] «En teleformación las horas de
 * conversación agendadas tienen la consideración de tutorías […] se debe de poder acceder por nuestra parte en tiempo real, y para
 * ello tendrán que comunicar la programación previamente de las videoconferencias». Checklist A1.P2.05, A2.P2.06, A2.P2.10.
 *
 * Una sola pantalla con TODAS las tutorías por videoconferencia en curso y próximas de las acciones formativas FUNDAE (mdl_fundae
 * bonificable = 1 y validado = 1) en las que el usuario es inspector (rol «supervisor» en el curso): fecha y hora (Madrid), acción /
 * grupo e ID de FUNDAE, empresa, curso, profesor, participante, estado y botón «Entrar» (enlace de Zoom de la reserva). Filtros por
 * periodo, empresa y acción; Excel y calendario (.ics). Es la vista conjunta de tutorias_programadas.php (por curso): mismos datos
 * (mdl_i3code_acuityZoom) y mismas reglas (participantes = rol de alumno sin cuenta @tuspeaking; canceladas fuera). Enlace desde el
 * panel «Dashboard FUNDAE» (block_fundae).
 * Seguridad: solo lectura; con sesión; entra un administrador (todas las acciones) o quien sea supervisor en al menos un curso FUNDAE
 * (solo esas acciones); parámetros con lista blanca; todo lo que se pinta va escapado; el enlace de Zoom solo si es https://…zoom.us.
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');

require_login(null, false);
if (isguestuser()) { throw new moodle_exception('nopermissions', 'error', '', 'tutorías programadas'); }

$periodo = optional_param('periodo', '14', PARAM_ALPHANUM);
if (!in_array($periodo, ['hoy', '7', '14', 'todo'], true)) { $periodo = '14'; }
$fempresa = optional_param('empresa', '', PARAM_TEXT);
$faf = optional_param('af', '', PARAM_TEXT);
$export = optional_param('export', '', PARAM_ALPHA);
if (!in_array($export, ['', 'excel', 'ics'], true)) { $export = ''; }

$PAGE->set_url('/tutorias_programadas_todas.php', ['periodo' => $periodo]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Tutorías programadas · todas las acciones');
$PAGE->set_heading('Tutorías programadas · todas las acciones');

// ─── Cursos FUNDAE que puede ver ───
$admin = is_siteadmin();
$sql = "SELECT f.id AS ficha, f.courseid, f.c_fundae, f.empresa, o.id_grupo, c.fullname
          FROM {fundae} f JOIN {course} c ON c.id = f.courseid
          LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae
         WHERE f.bonificable = 1 AND f.validado = 1";
$params = [];
if (!$admin) {
    $sql .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'supervisor'
                 JOIN {context} x ON x.id = ra.contextid AND x.contextlevel = :nivel AND x.instanceid = f.courseid WHERE ra.userid = :uid)";
    $params = ['nivel' => CONTEXT_COURSE, 'uid' => $USER->id];
}
$fichas = $DB->get_records_sql($sql, $params);
if (!$fichas && !$admin) { throw new moodle_exception('nopermissions', 'error', '', 'tutorías programadas'); }
$cursos = [];   // courseid => ['nombre', 'afs' => [c_fundae => ['id' => id_grupo, 'empresa']]]
foreach ($fichas as $f) {
    $cursos[$f->courseid]['nombre'] = $f->fullname;
    $cursos[$f->courseid]['afs'][(string)$f->c_fundae] = ['id' => $f->id_grupo ?: '-', 'empresa' => (string)$f->empresa];
}

// ─── Participantes (rol de alumno, sin cuentas @tuspeaking) y su grupo de Moodle (para elegir la AF si el curso tiene varias) ───
$filas = [];
$ahora = time();
$tz = new DateTimeZone('Europe/Madrid');
$ts = function ($s) { if ($s === null || $s === '') { return 0; } try { return (new DateTime($s))->getTimestamp(); } catch (Exception $e) { return 0; } };
$fmt = function ($t, $f) use ($tz) { return $t ? (new DateTime('@' . $t))->setTimezone($tz)->format($f) : ''; };
$enlace = function ($z) {
    foreach ([(string)$z->zoom_url, (string)$z->acuity_location] as $s) {
        if (preg_match('~https://[a-z0-9.-]*zoom\.us/(j|w|my)/[^\s"\'<>]+~i', $s, $m)) { return $m[0]; }
    }
    return '';
};
$hasta = ['hoy' => (new DateTime('tomorrow', $tz))->getTimestamp(), '7' => $ahora + 7 * 86400, '14' => $ahora + 14 * 86400, 'todo' => PHP_INT_MAX][$periodo];
if ($cursos) {
    $student = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    list($inc, $pc) = $DB->get_in_or_equal(array_keys($cursos), SQL_PARAMS_NAMED, 'c');
    $alumnos = [];   // courseid => [userid => user]
    $rs = $DB->get_recordset_sql("SELECT ra.id, x.instanceid AS courseid, u.id AS userid, u.firstname, u.lastname, u.firstnamephonetic,
            u.lastnamephonetic, u.middlename, u.alternatename
          FROM {role_assignments} ra JOIN {context} x ON x.id = ra.contextid AND x.contextlevel = :nivel
          JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
         WHERE ra.roleid = :rol AND x.instanceid $inc AND " . $DB->sql_like('u.email', ':dom', false, false, true),
        $pc + ['nivel' => CONTEXT_COURSE, 'rol' => $student, 'dom' => '%tuspeaking.com%']);
    foreach ($rs as $r) { $alumnos[$r->courseid][$r->userid] = $r; }
    $rs->close();
    $grupoaf = [];   // courseid-userid => c_fundae (grupo de Moodle llamado como el código)
    $rs = $DB->get_recordset_sql("SELECT gm.id, g.courseid, gm.userid, g.name FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
         WHERE g.courseid $inc", $pc);
    foreach ($rs as $r) { if (isset($cursos[$r->courseid]['afs'][(string)$r->name])) { $grupoaf[$r->courseid . '-' . $r->userid] = (string)$r->name; } }
    $rs->close();

    $rs = $DB->get_recordset_sql("SELECT z.id, z.courseid, z.studentid, z.acuity_datetime, z.acuity_duration, z.zoom_url, z.acuity_location,
            z.zoom_meetingid, CONCAT(t.firstname, ' ', t.lastname) AS profesor
          FROM mdl_i3code_acuityZoom z LEFT JOIN {user} t ON t.id = z.teacherid
         WHERE z.courseid $inc AND (z.acuity_canceled IS NULL OR z.acuity_canceled = 0)", $pc);
    foreach ($rs as $z) {
        if (empty($alumnos[$z->courseid][$z->studentid])) { continue; }
        $ini = $ts($z->acuity_datetime); if (!$ini) { continue; }
        $dur = (int)$z->acuity_duration ?: 30; $fin = $ini + $dur * 60;
        if ($fin <= $ahora || $ini >= $hasta) { continue; }
        $af = $grupoaf[$z->courseid . '-' . $z->studentid] ?? null;
        $afs = $af !== null ? [$af => $cursos[$z->courseid]['afs'][$af]] : $cursos[$z->courseid]['afs'];
        $codigos = implode(', ', array_map(function ($c) { return str_replace('-', '/', $c); }, array_keys($afs)));
        $idsg = implode(', ', array_unique(array_column($afs, 'id')));
        $empresa = implode(', ', array_unique(array_column($afs, 'empresa')));
        if ($fempresa !== '' && $empresa !== $fempresa) { continue; }
        if ($faf !== '' && strpos(', ' . $codigos . ', ', ', ' . $faf . ', ') === false) { continue; }
        $encurso = $ini <= $ahora;
        $min = (int)ceil(($ini - $ahora) / 60);
        $estado = $encurso ? 'En curso ahora' : ($min <= 60 ? "Empieza en $min min" : ($fmt($ini, 'Y-m-d') === $fmt($ahora, 'Y-m-d') ? 'Hoy' : 'Programada'));
        $filas[] = ['ini' => $ini, 'fin' => $fin, 'encurso' => $encurso, 'url' => $enlace($z), 'courseid' => (int)$z->courseid,
            'empresa' => $empresa, 'codigos' => $codigos,
            'v' => [$fmt($ini, 'd/m/Y'), $fmt($ini, 'H:i'), $fmt($fin, 'H:i'), $dur, $codigos, $idsg, $empresa, $cursos[$z->courseid]['nombre'],
                    trim((string)$z->profesor) ?: 'Sin datos', fullname($alumnos[$z->courseid][$z->studentid]), $estado]];
    }
    $rs->close();
}
usort($filas, function ($a, $b) { return [$a['ini'], $a['v'][4]] <=> [$b['ini'], $b['v'][4]]; });
$CAB = ['Fecha', 'Inicio (Madrid)', 'Fin (Madrid)', 'Duración (min)', 'Acción / grupo', 'ID de grupo FUNDAE', 'Empresa', 'Curso', 'Profesor', 'Participante', 'Estado'];
$NOTA = 'Tutorías individuales por videoconferencia (Zoom) reservadas por los participantes de las acciones formativas que aún no han terminado. Hora de Madrid. Las canceladas no salen. El botón «Entrar» abre la videoconferencia de la reserva; si tiene sala de espera, el profesor admite la entrada.';
$limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
$PERIODOS = ['hoy' => 'Hoy', '7' => 'Próximos 7 días', '14' => 'Próximos 14 días', 'todo' => 'Todas'];

// ─── Calendario (.ics) ───
if ($export === 'ics') {
    $esc = function ($s) { return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s); };
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//tuSpeaking//Tutorias programadas todas//ES\r\nCALSCALE:GREGORIAN\r\n";
    foreach ($filas as $f) {
        $out .= "BEGIN:VEVENT\r\nUID:tutoria-" . md5($f['ini'] . $f['v'][9] . $f['courseid']) . "@aula.tuspeaking.com\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n"
              . "DTSTART:" . gmdate('Ymd\THis\Z', $f['ini']) . "\r\nDTEND:" . gmdate('Ymd\THis\Z', $f['fin']) . "\r\n"
              . "SUMMARY:" . $esc('Tutoría ' . $f['codigos'] . ' · ' . $f['empresa']) . "\r\n"
              . "DESCRIPTION:" . $esc('Curso: ' . $f['v'][7] . "\nProfesor: " . $f['v'][8] . "\nParticipante: " . $f['v'][9] . "\nID de grupo FUNDAE: " . $f['v'][5]
                  . ($f['url'] ? "\nEntrar: " . $f['url'] : '')) . "\r\n"
              . ($f['url'] ? "URL:" . $f['url'] . "\r\n" : '') . "END:VEVENT\r\n";
    }
    $out .= "END:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="Tutorias_programadas_' . date('Y-m-d') . '.ics"');
    echo $out; exit;
}

// ─── Excel (.xlsx) ───
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Tutorias_programadas_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Tutorías programadas');
    $ws->write_string(0, 0, 'Tutorías programadas · todas las acciones', $f_titulo);
    $meta = [['Periodo', $PERIODOS[$periodo]], ['Empresa', $fempresa ?: 'Todas'], ['Acción / grupo', $faf ?: 'Todas'], ['Generado el', $fmt($ahora, 'd/m/Y H:i') . ' (Madrid)']];
    foreach ($meta as $i => $m) { $ws->write_string(2 + $i, 0, $m[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$m[1]); }
    $cab = $CAB; $cab[] = 'Enlace para entrar';
    $r0 = 7; foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([11, 10, 10, 10, 14, 14, 22, 40, 24, 28, 16, 60] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($filas as $f) {
        foreach ($f['v'] as $c => $v) { if (is_int($v)) { $ws->write_number($r, $c, $v); } else { $ws->write_string($r, $c, (string)$v); } }
        $ws->write_string($r, count($f['v']), $f['url']);
        $r++;
    }
    $ws->write_string($r + 1, 0, $NOTA, $f_nota);
    $workbook->close();
    exit;
}

// ─── Pantalla ───
$empresas = []; $codigos = [];
foreach ($cursos as $c) { foreach ($c['afs'] as $cod => $d) { $empresas[$d['empresa']] = true; $codigos[str_replace('-', '/', $cod)] = true; } }
ksort($empresas); ksort($codigos);
echo $OUTPUT->header();
?>
<style>
.tp-filtros{background:#f7f9fa;border:1px solid #d6dde3;padding:10px;margin:8px 0;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.tp-wrap{overflow-x:auto;margin:10px 0 18px}
.tp-tabla{border-collapse:collapse;font-size:13px;width:100%}
.tp-tabla th{background:#008ba3;color:#fff;padding:6px 8px;text-align:left;white-space:nowrap}
.tp-tabla td{border-bottom:1px solid #e2e8f0;padding:5px 8px;white-space:nowrap}
.tp-tabla tr.tp-encurso td{background:#e8f5e9;font-weight:bold}
.tp-entrar{background:#2e7d32;color:#fff !important;padding:4px 10px;border-radius:4px;text-decoration:none;display:inline-block}
.tp-nota{color:#666;font-style:italic;font-size:12px}
</style>
<?php
$base = new moodle_url('/tutorias_programadas_todas.php');
echo '<form method="get" action="' . $base->out() . '" class="tp-filtros">';
echo '<label>Periodo <select name="periodo">';
foreach ($PERIODOS as $k => $t) { echo '<option value="' . s($k) . '"' . ($k === $periodo ? ' selected' : '') . '>' . s($t) . '</option>'; }
echo '</select></label><label>Empresa <select name="empresa"><option value="">Todas</option>';
foreach (array_keys($empresas) as $e) { echo '<option value="' . s($e) . '"' . ($e === $fempresa ? ' selected' : '') . '>' . s($e) . '</option>'; }
echo '</select></label><label>Acción / grupo <select name="af"><option value="">Todas</option>';
foreach (array_keys($codigos) as $c) { echo '<option value="' . s($c) . '"' . ($c === $faf ? ' selected' : '') . '>' . s($c) . '</option>'; }
echo '</select></label><button type="submit" class="btn btn-primary">Ver</button></form>';
$q = ['periodo' => $periodo, 'empresa' => $fempresa, 'af' => $faf];
echo '<p><a class="btn btn-secondary" href="' . (new moodle_url('/tutorias_programadas_todas.php', $q + ['export' => 'excel']))->out() . '">Descargar Excel</a> '
   . '<a class="btn btn-secondary" href="' . (new moodle_url('/tutorias_programadas_todas.php', $q + ['export' => 'ics']))->out() . '">Añadir a mi calendario (.ics)</a></p>';
$nen = count(array_filter($filas, function ($f) { return $f['encurso']; }));
echo '<p>' . count($filas) . ' tutorías programadas · ' . s($PERIODOS[$periodo]) . ($nen ? ' · <b>' . $nen . ' en curso ahora</b>' : '')
   . ' · consultado ' . s($fmt($ahora, 'd/m/Y H:i')) . ' (hora de Madrid).</p>';
echo '<div class="tp-wrap"><table class="tp-tabla"><thead><tr>';
foreach ($CAB as $t) { echo '<th>' . s($t) . '</th>'; }
echo '<th>Entrar</th></tr></thead><tbody>';
foreach ($filas as $f) {
    echo '<tr' . ($f['encurso'] ? ' class="tp-encurso"' : '') . '>';
    foreach ($f['v'] as $i => $v) {
        if ($i === 7) { echo '<td><a href="' . (new moodle_url('/course/view.php', ['id' => $f['courseid']]))->out() . '">' . s((string)$v) . '</a></td>'; continue; }
        echo '<td>' . s((string)$v) . '</td>';
    }
    echo '<td>' . ($f['url'] ? '<a class="tp-entrar" target="_blank" rel="noopener noreferrer" href="' . s($f['url']) . '">Entrar</a>' : 'Sin enlace') . '</td></tr>';
}
if (!$filas) { echo '<tr><td colspan="' . (count($CAB) + 1) . '">No hay tutorías programadas en este periodo.</td></tr>'; }
echo '</tbody></table></div>';
echo '<p class="tp-nota">' . s($NOTA) . '</p>';
echo $OUTPUT->footer();
