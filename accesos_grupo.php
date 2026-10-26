<?php
/**
 * accesos_grupo.php — T-188 (15/10/2026) · «Accesos de los participantes»
 *
 * v2 (15/10, En: «que se muestren los datos en una lista, que ya les toque a ellos filtrar y analizarlo»): LISTA de todos los
 * eventos del registro de Moodle (logstore_standard_log, el mismo que «Registros de actividad») de este curso hechos por los
 * participantes (rol de alumno, sin cuentas @tuspeaking, con o sin matrícula activa) y por los tutores (rol de profesor), ya
 * cargada, sin resúmenes: las mismas columnas que el informe de registros de Moodle (hora, nombre, usuario afectado, contexto,
 * componente, evento, origen, IP) + rol en el curso, email y «hecho por otra persona en su nombre» (realuserid). Hora de Madrid.
 * Pantalla paginada (500 por página) con filtro por persona; Excel con la lista completa (respeta el filtro).
 * v4 (15/10, En): en la MISMA lista, las conexiones de Zoom de tutores y alumnos a cada tutoría (columna «Fuente»: Plataforma / Zoom),
 *   sacadas con tutorias_de() = copia literal de las QUERY 2 y 3 de tutorias_con_profesor.php (T-181). No se escribe nada en el
 *   registro de Moodle: las dos fuentes solo se juntan en pantalla y en el Excel.
 * v5 (15/10, En): + Nº de registro de Moodle (logstore_standard_log.id, para cotejar con el registro oficial), + inicios y cierres de
 *   sesión en la plataforma (user_loggedin / user_loggedout, fuera del curso, entre el inicio y el fin del curso, con IP),
 *   + Nivel (edulevel: Participación / Enseñanza / Otro) y Acción (crud: Ver / Crear / Actualizar / Borrar),
 *   + ID de reserva (Acuity) en las filas de Zoom (llave para cruzar con «Tutorías: reservas del grupo»).
 * v6 (15/10, En): fuera la columna «Hecho por otra persona en su nombre».
 * v7 (15/10, En): sustituye en el bloque a «Registros de actividad» (report/log): se titula así; al pie, enlace al registro completo de Moodle.
 * v8 (15/10, En, T-193): misma trazabilidad que el informe de Moodle: + «Descripción» (texto literal de Moodle, get_description() del evento
 *   restaurado, como report/log), + el nombre del evento enlaza al elemento (get_url()), + «hecho por otra persona» (realuserid, «iniciar
 *   sesión como») marcado en la propia fila, solo cuando ocurre.
 * Solo lectura. Acceso: report/progress:view (inspector/supervisor, profesor, admin). Ubicación: /mnt/moodle-data/moodle-code/accesos_grupo.php
 *
 * @copyright 2026 Micro Ventures S.L.
 */
require_once('config.php');

$courseid = required_param('courseid', PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$filtro = optional_param('persona', 0, PARAM_INT);
$pagina = optional_param('pagina', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course, false);
require_capability('report/progress:view', $context);

$PAGE->set_url('/accesos_grupo.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Registros de actividad - ' . format_string($course->fullname));
$PAGE->set_heading('Registros de actividad');
$PAGE->set_pagelayout('incourse');

$pdo = new PDO("mysql:host={$CFG->dbhost};dbname={$CFG->dbname};charset=utf8mb4", $CFG->dbuser, $CFG->dbpass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ahora = time();
$dia = function ($t) { return $t ? userdate($t, '%d/%m/%Y', 'Europe/Madrid') : ''; };
$fh = function ($t) { return $t ? userdate($t, '%d/%m/%Y %H:%M', 'Europe/Madrid') : ''; };

/**
 * Clases y conexiones de un participante en el curso. COPIA de tutorias_con_profesor.php (T-181): QUERY 2, QUERY 3 y la nota por clase.
 * Devuelve [clases sin canceladas, conexiones por reunión].
 */
function tutorias_de(PDO $pdo, $courseid, $view_userid) {
    $tz_madrid = new DateTimeZone('Europe/Madrid');
    $stmt_detalle = $pdo->prepare("
        SELECT z.id, z.acuity_datetime, z.acuity_canceled, z.zoom_clasecompletada, z.zoom_meetingid,
               z.zoom_starttime, z.zoom_endtime, z.zoom_duration, z.zoom_topic, z.manual_override, z.manual_motivo, z.manual_fecha, z.teacherid, z.acuityid,
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
            'teacherid' => (int)$z['teacherid'],
            'acuityid' => (string)($z['acuityid'] ?? ''),
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

// ─── Ficha FUNDAE (cabecera) ───
$ag = []; $ids_f = [];
try {
    $rs = $DB->get_recordset_sql("SELECT f.c_fundae, o.id_grupo FROM {fundae} f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae WHERE f.courseid = ? ORDER BY f.c_fundae", [$courseid]);
    foreach ($rs as $f) { $ag[] = str_replace('-', '/', (string)$f->c_fundae); $ids_f[] = $f->id_grupo ?: '-'; }
    $rs->close();
} catch (Exception $e) { }



// ─── Tutores: rol de profesor en el curso ───
$tutores = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
    FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('teacher', 'editingteacher')
    JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
    WHERE ra.contextid = ? ORDER BY u.lastname, u.firstname", [$context->id]);
// v3 (15/10, En): fuera las cuentas de Hansel/administración (14, 5677, inspector 5676 y correos de Hansel). Sus eventos siguen en «Registros de actividad».
$FUERA = [14, 5676, 5677];
$esfuera = function ($u) use ($FUERA) { return in_array((int)$u->id, $FUERA, true) || preg_match('/hansel|hfernandez/i', (string)$u->email); };
$personas = [];
foreach ($participantes as $u) { if (!$esfuera($u)) { $personas[$u->id] = ['u' => $u, 'rol' => 'Participante']; } }
foreach ($tutores as $u) { if (!$esfuera($u) && !isset($personas[$u->id])) { $personas[$u->id] = ['u' => $u, 'rol' => 'Tutor']; } }

// ─── Nombres para «Usuario afectado» y «Hecho por» ───
$nombres = [];
$nombre = function ($uid) use (&$nombres, $DB) {
    if (!$uid) { return ''; }
    if (!isset($nombres[$uid])) { $u = $DB->get_record('user', ['id' => $uid], 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename');
        $nombres[$uid] = $u ? fullname($u) : "usuario $uid"; }
    return $nombres[$uid];
};
// ─── Contexto del evento: curso o nombre de la actividad ───
$modinfo = get_fast_modinfo($course);
$contexto = function ($l) use ($modinfo) {
    if ((int)$l->contextlevel === CONTEXT_MODULE && isset($modinfo->cms[$l->contextinstanceid])) {
        $cm = $modinfo->cms[$l->contextinstanceid]; return get_string('modulename', $cm->modname) . ': ' . $cm->get_formatted_name();
    }
    if ((int)$l->contextlevel === CONTEXT_SYSTEM) { return 'Plataforma (fuera del curso)'; }
    if ((int)$l->contextlevel === CONTEXT_COURSE) { return 'Curso: ' . format_string($GLOBALS['course']->fullname); }
    if ((int)$l->contextlevel === CONTEXT_MODULE) { return 'Actividad ' . $l->contextinstanceid . ' (ya no existe)'; }
    return 'Contexto ' . $l->contextlevel . '/' . $l->contextinstanceid;
};
$evnombre = [];
$evento = function ($cls) use (&$evnombre) {
    if (!isset($evnombre[$cls])) { $evnombre[$cls] = (class_exists($cls) && method_exists($cls, 'get_name')) ? $cls::get_name() : $cls; }
    return $evnombre[$cls];
};
$componente = function ($c) {
    if ($c === 'core') { return 'Sistema'; }
    return get_string_manager()->string_exists('pluginname', $c) ? get_string('pluginname', $c) : $c;
};

$CAB = ['Fecha', 'Hora (Madrid)', 'Fuente', 'Nº de registro (Moodle)', 'Nombre', 'Email', 'Rol en el curso', 'Usuario afectado', 'Contexto del evento', 'Componente', 'Nombre del evento',
        'Descripción (Moodle)', 'Nivel', 'Acción', 'Origen', 'Dirección IP', 'ID de reserva (Acuity)', 'Salida (Madrid, Zoom)', 'Duración (min, Zoom)', 'Nombre en Zoom'];
$NIVEL = [0 => 'Otro', 1 => 'Enseñanza', 2 => 'Participación'];
$CRUD = ['c' => 'Crear', 'r' => 'Ver', 'u' => 'Actualizar', 'd' => 'Borrar'];
$NOTA = 'Registro de actividad de la plataforma (Moodle) de este curso y conexiones de Zoom a las tutorías, de los participantes y de los tutores, sin resumir. «Fuente» indica de dónde sale cada fila: Plataforma (registro de Moodle, tal como lo guarda la plataforma; «Nº de registro» permite cotejarla con el registro oficial; incluye los inicios y cierres de sesión en la plataforma durante el curso) o Zoom (registro automático de conexiones de Zoom de cada tutoría; «ID de reserva» la une con «Tutorías: reservas del grupo»). No incluye las cuentas de administración de la plataforma (están en «Registros de actividad»). Hora de Madrid. «Descripción (Moodle)» es el texto literal que guarda Moodle para cada evento (en inglés, como en su informe de registros) y el nombre del evento enlaza al elemento. Si una acción la hizo otra persona que inició sesión como el participante, la fila sale en naranja con «HECHO POR …».';
$ids = $filtro && isset($personas[$filtro]) ? [$filtro] : array_keys($personas);
$total = 0; $where = ''; $params = [];
if ($ids) {
    list($in, $params) = $DB->get_in_or_equal($ids);
    $where = "courseid = ? AND userid $in"; $params = array_merge([$courseid], $params);
    $total = $DB->count_records_sql("SELECT COUNT(*) FROM {logstore_standard_log} WHERE $where", $params);
}
// v8: evento restaurado como en el informe de Moodle (logstore_standard\log\store::get_log_event) → descripción y enlace literales.
$restaura = function ($l) {
    $d = (array)$l; $extra = ['origin' => $d['origin'], 'ip' => $d['ip'], 'realuserid' => $d['realuserid']];
    $o = $d['other'];
    if ($o === null || $o === 'N;') { $other = null; } else { $other = @unserialize($o); if ($other === false && $o !== 'b:0;') { $other = json_decode($o, true); } }
    $d['other'] = $other; unset($d['id'], $d['origin'], $d['ip'], $d['realuserid']);
    try { return \core\event\base::restore($d, $extra); } catch (Throwable $e) { return null; }
};
$descurl = function ($l) use ($restaura) {
    $ev = $restaura($l); $desc = ''; $url = '';
    if ($ev) {
        try { $desc = (string)$ev->get_description(); } catch (Throwable $e) { }
        try { $u = $ev->get_url(); if ($u) { $url = $u->out(false); } } catch (Throwable $e) { }
    }
    return [$desc, $url];
};
$filaDe = function ($l) use ($personas, $nombre, $contexto, $evento, $componente, $descurl) {
    $t = (int)$l->timecreated;
    $p = $personas[$l->userid];
    list($desc, $url) = $descurl($l);
    $por = ($l->realuserid && (int)$l->realuserid !== (int)$l->userid) ? ' · HECHO POR ' . $nombre($l->realuserid) . ' (sesión iniciada como esta persona)' : '';
    return [[userdate($t, '%d/%m/%Y', 'Europe/Madrid'), userdate($t, '%H:%M:%S', 'Europe/Madrid'), 'Plataforma', (string)$l->id, fullname($p['u']) . $por, $p['u']->email, $p['rol'],
            $l->relateduserid ? $nombre($l->relateduserid) : '', $contexto($l), $componente($l->component), $evento($l->eventname), $desc,
            $GLOBALS['NIVEL'][(int)$l->edulevel] ?? (string)$l->edulevel, $GLOBALS['CRUD'][$l->crud] ?? (string)$l->crud,
            (string)$l->origin, (string)$l->ip, '', '', '', ''], $url, $por !== ''];
};
$SQL = "SELECT * FROM {logstore_standard_log} WHERE $where ORDER BY timecreated, id";
// Inicios y cierres de sesión en la plataforma (fuera del curso) entre el inicio y el fin del curso.
$desde = (int)$course->startdate; $hasta = $course->enddate ? (int)$course->enddate + DAYSECS : time();
$SQL_SES = ''; $params_ses = [];
if ($ids) {
    list($in2, $p2) = $DB->get_in_or_equal($ids);
    $SQL_SES = "SELECT * FROM {logstore_standard_log} WHERE courseid = 0 AND eventname IN (?, ?) AND userid $in2 AND timecreated BETWEEN ? AND ? ORDER BY timecreated, id";
    $params_ses = array_merge(['\\core\\event\\user_loggedin', '\\core\\event\\user_loggedout'], $p2, [$desde, $hasta]);
}

// ─── Lista combinada: registro de Moodle + conexiones de Zoom (en memoria, ordenada por fecha y hora) ───
$todo = [];
if ($ids) {
    $rs = $DB->get_recordset_sql($SQL, $params);
    foreach ($rs as $l) { list($fila, $url, $otro) = $filaDe($l); $todo[] = [(int)$l->timecreated, 0, (int)$l->id, $fila, $url, $otro]; }
    $rs->close();
    $rs = $DB->get_recordset_sql($SQL_SES, $params_ses);
    foreach ($rs as $l) { list($fila, $url, $otro) = $filaDe($l); $todo[] = [(int)$l->timecreated, 0, (int)$l->id, $fila, $url, $otro]; }
    $rs->close();
}
$utc = new DateTimeZone('UTC');
$ts = function ($s) use ($utc) { if (!$s) { return 0; } try { return (new DateTime($s, $utc))->getTimestamp(); } catch (Exception $e) { return 0; } };
$vistos = [];
foreach ($participantes as $al) {
    if ($esfuera($al)) { continue; }
    list($clases, $zoom) = tutorias_de($pdo, $courseid, $al->id);
    foreach ($clases as $cl) {
        if (!$cl['meeting_id']) { continue; }
        foreach (($zoom[$cl['meeting_id']] ?? []) as $z) {
            $is_tutor = !empty($z['email']) && strpos($z['email'], '@live') !== false && strpos($z['email'], 'tuspeaking') !== false;
            $uid = $is_tutor ? (int)$cl['teacherid'] : (int)$al->id;
            if (!$uid) { continue; }
            if (!isset($personas[$uid])) {
                $u = $DB->get_record('user', ['id' => $uid], 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email');
                if (!$u || $esfuera($u)) { continue; }
                $personas[$uid] = ['u' => $u, 'rol' => $is_tutor ? 'Tutor' : 'Participante'];
            }
            if ($filtro && $uid != $filtro) { continue; }
            $clave = $cl['meeting_id'] . '|' . $uid . '|' . $z['join_time_utc'] . '|' . $al->id;
            if (isset($vistos[$clave])) { continue; }
            $vistos[$clave] = true;
            $t = $ts($z['join_time_utc']); $p = $personas[$uid];
            $todo[] = [$t, 1, 0, [userdate($t, '%d/%m/%Y', 'Europe/Madrid'), userdate($t, '%H:%M:%S', 'Europe/Madrid'), 'Zoom', '', fullname($p['u']), $p['u']->email, $p['rol'],
                fullname($al), 'Tutoría de ' . fullname($al) . ' · Zoom ' . $cl['meeting_id'] . ($cl['tema'] ? ' · «' . $cl['tema'] . '»' : ''), 'Zoom',
                'Conexión a la tutoría por videoconferencia', '', $is_tutor ? 'Enseñanza' : 'Participación', '', 'zoom', '', (string)$cl['acuityid'],
                $z['hora_salida'], (string)(int)$z['duracion_min'], (string)$z['participante']], '', false];
        }
    }
}
usort($todo, function ($a, $b) { return [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]; });
$total = count($todo);

// ─── Excel (.xlsx): la lista completa (respeta el filtro) ───
if ($export === 'excel' && $ids) {
    require_once($CFG->libdir . '/excellib.class.php');
    core_php_time_limit::raise(300);
    $limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send('Registros_de_actividad_' . $limpio($course->fullname) . '_' . date('Y-m-d') . '.xlsx');
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $ws = $workbook->add_worksheet('Registro');
    $ws->write_string(0, 0, 'Registros de actividad (plataforma y Zoom)', $f_titulo);
    $meta = [['Acción / grupo', $ag ? implode(', ', $ag) : '-'], ['ID de grupo FUNDAE', $ids_f ? implode(', ', $ids_f) : '-'], ['Curso', $course->fullname],
             ['Persona', $filtro && isset($personas[$filtro]) ? fullname($personas[$filtro]['u']) : 'Todos los participantes y tutores'], ['Filas', (string)$total]];
    foreach ($meta as $i => $m) { $ws->write_string(2 + $i, 0, $m[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$m[1]); }
    $r0 = 8;
    foreach ($CAB as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([11, 10, 11, 14, 26, 32, 12, 22, 60, 16, 34, 90, 13, 11, 8, 16, 14, 14, 12, 22] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($todo as $e) { foreach ($e[3] as $c => $v) { $ws->write_string($r, $c, (string)$v); } $r++; }
    $ws->write_string($r + 1, 0, $NOTA, $f_nota);
    $workbook->close();
    exit;
}

// ─── Pantalla (500 eventos por página) ───
$POR = 500;
echo $OUTPUT->header();
?>
<style>
.ag-meta{background:#e0f7fa;border-left:4px solid #008ba3;padding:8px 12px;margin:8px 0}
.ag-meta b{display:inline-block;min-width:200px}
.ag-wrap{overflow-x:auto;margin:10px 0 18px}
.ag-tabla{border-collapse:collapse;font-size:12px;width:100%}
.ag-tabla th{background:#008ba3;color:#fff;padding:6px 7px;text-align:left;white-space:nowrap}
.ag-tabla td{border-bottom:1px solid #e2e8f0;padding:3px 7px;white-space:nowrap}
.ag-tabla tr.ag-tutor td{background:#f4fbfc}
.ag-tabla tr.ag-zoom td:nth-child(3){color:#2d8cff;font-weight:bold}
.ag-tabla tr.ag-otro td{background:#fff3e0}
.ag-tabla td.ag-desc{white-space:normal;min-width:380px;color:#444}
.ag-aviso{color:#b45309;font-weight:bold}
.ag-nota{color:#666;font-style:italic;font-size:12px}
</style>
<h2>Registros de actividad</h2>
<p>Plataforma y Zoom: eventos de los participantes y de los tutores del curso, en una sola lista ordenada por fecha y hora.</p>
<div class="ag-meta">
  <div><b>Acción / grupo</b><?php echo s($ag ? implode(', ', $ag) : '-'); ?></div>
  <div><b>ID de grupo FUNDAE</b><?php echo s($ids_f ? implode(', ', $ids_f) : '-'); ?></div>
  <div><b>Curso</b><?php echo format_string($course->fullname); ?></div>
</div>
<?php
echo '<form method="get" style="margin:6px 0"><input type="hidden" name="courseid" value="' . $courseid . '">'
   . '<label>Persona: <select name="persona" onchange="this.form.submit()"><option value="0">Todos los participantes y tutores</option>';
foreach ($personas as $id => $p) { echo '<option value="' . $id . '"' . ($filtro == $id ? ' selected' : '') . '>' . s(fullname($p['u'])) . ' (' . $p['rol'] . ')</option>'; }
echo '</select></label> <noscript><button type="submit">Filtrar</button></noscript> &nbsp; ';
echo '<a class="btn btn-secondary" href="' . (new moodle_url('/accesos_grupo.php', ['courseid' => $courseid, 'persona' => $filtro, 'export' => 'excel']))->out() . '">Descargar Excel (lista completa)</a></form>';
$paginas = max(1, (int)ceil($total / $POR)); $pagina = max(0, min($pagina, $paginas - 1));
echo '<p>' . $total . ' filas (plataforma, incluidos inicios y cierres de sesión, y Zoom)' . ($paginas > 1 ? ' · página ' . ($pagina + 1) . ' de ' . $paginas : '') . '.</p>';
$nav = function () use ($paginas, $pagina, $courseid, $filtro) {
    if ($paginas < 2) { return; }
    echo '<p>';
    for ($i = 0; $i < $paginas; $i++) {
        $u = new moodle_url('/accesos_grupo.php', ['courseid' => $courseid, 'persona' => $filtro, 'pagina' => $i]);
        echo $i === $pagina ? '<b>[' . ($i + 1) . ']</b> ' : '<a href="' . $u->out() . '">' . ($i + 1) . '</a> ';
    }
    echo '</p>';
};
$nav();
echo '<div class="ag-wrap"><table class="ag-tabla"><thead><tr>';
foreach ($CAB as $t) { echo '<th>' . s($t) . '</th>'; }
echo '</tr></thead><tbody>';
if ($total) {
    foreach (array_slice($todo, $pagina * $POR, $POR) as $e) {
        $f = $e[3];
        $cls = trim(($f[6] === 'Tutor' ? 'ag-tutor ' : '') . ($f[2] === 'Zoom' ? 'ag-zoom ' : '') . (!empty($e[5]) ? 'ag-otro' : ''));
        echo '<tr' . ($cls ? ' class="' . $cls . '"' : '') . '>';
        foreach ($f as $c => $v) {
            if ($c === 10 && !empty($e[4])) { echo '<td><a href="' . s($e[4]) . '" target="_blank" rel="noopener">' . s((string)$v) . '</a></td>'; }
            else if ($c === 11) { echo '<td class="ag-desc">' . s((string)$v) . '</td>'; }
            else if ($c === 4 && !empty($e[5])) { echo '<td class="ag-aviso">' . s((string)$v) . '</td>'; }
            else { echo '<td>' . s((string)$v) . '</td>'; }
        }
        echo '</tr>';
    }
} else { echo '<tr><td colspan="20">Sin eventos.</td></tr>'; }
echo '</tbody></table></div>';
$nav();
echo '<p class="ag-nota">' . s($NOTA) . '</p>';
$completo = new moodle_url('/report/log/index.php', ['id' => $courseid]);
echo '<p class="ag-nota">Registro completo de la plataforma (todas las cuentas, sin filtrar): <a href="' . $completo->out() . '">Informes → Registros</a>.</p>';
echo $OUTPUT->footer();
