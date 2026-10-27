<?php
/**
 * tutorias_con_profesor.php
 * 
 * Registro de tutorías con profesor para auditoría FUNDAE.
 * Muestra datos de trazabilidad Zoom: participantes, hora entrada/salida,
 * duración real, ID de reunión, por sesión.
 * 
 * Parámetros:
 *   - courseid (requerido): ID del curso Moodle
 *   - userid (opcional): ID del alumno (default: usuario actual)
 * 
 * Accesible para: student (propio), teacher, supervisor, admin
 * 
 * Ubicación: /home/aulatuspeaking/.ftp-users/moodle/tutorias_con_profesor.php
 * 
 * @package    local
 * @author     tuSpeaking Platform
 * @copyright  2026 Micro Ventures S.L.
 */

require_once('config.php');
require_login();
global $USER, $DB, $PAGE, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$view_userid = optional_param('userid', 0, PARAM_INT); // 0 = auto-detectar
$export = optional_param('export', '', PARAM_ALPHA);

// Verificar que el curso existe
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Verificar permisos: el usuario debe estar matriculado en el curso
// o ser admin del sitio
$is_admin = is_siteadmin();
$is_enrolled = is_enrolled($context, $USER, '', true);
if (!$is_admin && !$is_enrolled) {
    print_error('nopermissions', 'error', '', 'ver tutorías de este curso');
}

// Determinar rol del usuario actual en el curso
$user_roles = get_user_roles($context, $USER->id, true);
$supervisor_roles = ['teacher', 'editingteacher', 'supervisor', 'manager'];
$is_supervisor = $is_admin;
foreach ($user_roles as $role) {
    if (in_array($role->shortname, $supervisor_roles)) {
        $is_supervisor = true;
        break;
    }
}

// Auto-detección de alumno cuando no se pasa userid
if ($view_userid == 0) {
    if (!$is_supervisor) {
        // Es alumno: mostrar sus propios datos
        $view_userid = $USER->id;
    } else {
        // Es supervisor/teacher/admin: buscar alumnos del curso con rol student
        $student_role = $DB->get_record('role', ['shortname' => 'student']);
        $students = [];
        if ($student_role) {
            $enrolled_students = get_role_users($student_role->id, $context, false, 'u.id, u.firstname, u.lastname, u.email', 'u.lastname ASC');
            $students = $enrolled_students ? array_values($enrolled_students) : [];
            // T-181: fuera las cuentas @tuspeaking (profesores matriculados como alumnos), igual que el panel FUNDAE.
            $students = array_values(array_filter($students, function ($s) { return stripos($s->email, 'tuspeaking.com') === false; }));
        }
        
        if (count($students) == 1) {
            // Solo un alumno: mostrar directamente sus datos
            $view_userid = $students[0]->id;
        } elseif (count($students) > 1) {
            // Varios alumnos: mostrar selector
            $PAGE->set_url('/tutorias_con_profesor.php', ['courseid' => $courseid]);
            $PAGE->set_context($context);
            $PAGE->set_course($course);
            $PAGE->set_title('Tutorías con profesor - Seleccionar alumno');
            $PAGE->set_heading('Tutorías con profesor');
            $PAGE->set_pagelayout('incourse');
            echo $OUTPUT->header();
            echo '<div style="max-width:800px;margin:0 auto;padding:20px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">';
            echo '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $courseid . '" style="color:#008ba3;text-decoration:none;font-weight:500">← Volver al curso</a>';
            echo '<h2 style="color:#424242;margin:20px 0 8px">📋 Tutorías con profesor</h2>';
            echo '<p style="color:#64748b;margin-bottom:24px">' . htmlspecialchars($course->fullname) . '</p>';
            echo '<h3 style="color:#424242;border-bottom:2px solid #e2e8f0;padding-bottom:12px">Selecciona un alumno</h3>';
            foreach ($students as $s) {
                $url = $CFG->wwwroot . '/tutorias_con_profesor.php?courseid=' . $courseid . '&userid=' . $s->id;
                echo '<a href="' . $url . '" style="display:block;background:#fff;border:1px solid #e2e8f0;border-left:4px solid #008ba3;border-radius:8px;padding:16px 20px;margin:8px 0;text-decoration:none;transition:all .2s">';
                echo '<strong style="color:#424242;display:block">' . fullname($s) . '</strong>';
                echo '<span style="color:#64748b;font-size:.85em">' . $s->email . '</span>';
                echo '</a>';
            }
            echo '</div>';
            echo $OUTPUT->footer();
            exit;
        } else {
            // Sin alumnos
            $view_userid = $USER->id;
        }
    }
}

// Si no es admin ni el propio usuario, verificar que tenga rol teacher/supervisor
if (!$is_admin && $view_userid != $USER->id && !$is_supervisor) {
    print_error('nopermissions', 'error', '', 'ver tutorías de otro usuario');
}

$view_user = $DB->get_record('user', ['id' => $view_userid], '*', MUST_EXIST);

$PAGE->set_url('/tutorias_con_profesor.php', ['courseid' => $courseid, 'userid' => $view_userid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Tutorías con profesor - ' . $course->fullname);
$PAGE->set_heading('Tutorías con profesor');
$PAGE->set_pagelayout('incourse');

// Conexión PDO directa (igual que misclases.php)
$pdo = new PDO(
    "mysql:host={$CFG->dbhost};dbname={$CFG->dbname};charset=utf8mb4",
    $CFG->dbuser,
    $CFG->dbpass
); // credenciales de config.php (antes en duro; restaurado 6/10/2026, T-112)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─── QUERY 1: Resumen de asistencia del alumno en el curso ───
$sql_resumen = "
    SELECT 
        user_id,
        course_id,
        course_name,
        company_name,
        clases_asistidas,
        clases_agendadas,
        porcentaje_asistencia,
        minutos_totales,
        primera_clase,
        ultima_clase
    FROM v_zoom_asistencia_estudiantes
    WHERE user_id = ? AND course_id = ?
";
$stmt_resumen = $pdo->prepare($sql_resumen);
$stmt_resumen->execute([$view_userid, $courseid]);
$resumen = $stmt_resumen->fetch(PDO::FETCH_ASSOC);

// ─── QUERY 2: Detalle de clases, UNA FILA POR CLASE (T-181, 14/10/2026) ───
// Antes salía de la vista v_zoom_detalle_clases y la reunión de Zoom se buscaba por el DÍA: con varias clases el mismo
// día, todas mostraban la misma reunión y sus conexiones repetidas. Ahora cada clase lleva SU reunión (zoom_meetingid
// de su fila de mdl_i3code_acuityZoom). Estados según RUNBOOK_asistencias_moodle_tuspeaking.md §1:
// zoom_clasecompletada 1 = asistió, 2 = ausencia, 3 = verificando, 0 = sin datos; acuity_canceled = 1 → cancelada.
// La hora agendada se pasa a hora de Madrid (acuity_datetime trae la zona del profesor, p. ej. -0300).
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
        'hora_real' => $z['zoom_starttime'] ? date('H:i', strtotime($z['zoom_starttime'])) : '',
        'inicio_real_ts' => $z['zoom_starttime'] ? strtotime($z['zoom_starttime']) : null,
        'fin_real_ts' => $z['zoom_endtime'] ? strtotime($z['zoom_endtime']) : null,
        'estado' => $estado,
        'minutos' => $z['zoom_duration'] !== null ? (int)$z['zoom_duration'] : null,
        'profesor' => trim((string)$z['profesor']),
        'profesor_conecto' => 'NO',
        'meeting_id' => $z['zoom_meetingid'] ?: null,
        'tema' => (string)($z['zoom_topic'] ?? ''),
        'manual' => !empty($z['manual_override']) ? ('Sí' . ($z['manual_motivo'] ? ': ' . $z['manual_motivo'] : '') . ($z['manual_fecha'] ? ' (' . $z['manual_fecha'] . ')' : '')) : 'No',
        'nota' => '',
    ];
}
usort($detalle_clases, function ($a, $b) { return $a['ts'] <=> $b['ts']; });

// ─── QUERY 3: Participantes Zoom por reunión (trazabilidad completa) ───
// Obtener los meeting IDs del curso
$sql_meetings = "
    SELECT DISTINCT z.zoom_meetingid
    FROM mdl_i3code_acuityZoom z
    WHERE z.courseid = ? AND z.studentid = ?
    AND z.zoom_meetingid IS NOT NULL
";
$stmt_meetings = $pdo->prepare($sql_meetings);
$stmt_meetings->execute([$courseid, $view_userid]);
$meeting_ids = $stmt_meetings->fetchAll(PDO::FETCH_COLUMN);

$participantes_zoom = [];
if (!empty($meeting_ids)) {
    $placeholders = implode(',', array_fill(0, count($meeting_ids), '?'));
    
    // Determinar offset CET/CEST para cada registro
    // CET = UTC+1, CEST = UTC+2 (último domingo de marzo a último domingo de octubre)
    $sql_participantes = "
        SELECT 
            p.zoom_meetingid AS meeting_id,
            p.participant_name AS participante,
            p.participant_email AS email,
            p.join_time AS join_time_utc,
            p.leave_time AS leave_time_utc,
            DATE_FORMAT(CONVERT_TZ(p.join_time, '+00:00', 
                CASE 
                    WHEN p.join_time >= '2026-03-29 01:00:00' AND p.join_time < '2026-10-25 01:00:00' THEN '+02:00'
                    WHEN p.join_time >= '2025-03-30 01:00:00' AND p.join_time < '2025-10-26 01:00:00' THEN '+02:00'
                    ELSE '+01:00'
                END
            ), '%d/%m/%Y') AS fecha_local,
            DATE_FORMAT(CONVERT_TZ(p.join_time, '+00:00', 
                CASE 
                    WHEN p.join_time >= '2026-03-29 01:00:00' AND p.join_time < '2026-10-25 01:00:00' THEN '+02:00'
                    WHEN p.join_time >= '2025-03-30 01:00:00' AND p.join_time < '2025-10-26 01:00:00' THEN '+02:00'
                    ELSE '+01:00'
                END
            ), '%H:%i:%s') AS hora_entrada,
            DATE_FORMAT(CONVERT_TZ(p.leave_time, '+00:00', 
                CASE 
                    WHEN p.leave_time >= '2026-03-29 01:00:00' AND p.leave_time < '2026-10-25 01:00:00' THEN '+02:00'
                    WHEN p.leave_time >= '2025-03-30 01:00:00' AND p.leave_time < '2025-10-26 01:00:00' THEN '+02:00'
                    ELSE '+01:00'
                END
            ), '%H:%i:%s') AS hora_salida,
            p.duration_minutes AS duracion_min,
            z.zoom_username AS tutor_zoom,
            CONCAT(t.firstname, ' ', t.lastname) AS tutor_nombre,
            z.acuity_datetime AS fecha_agendada
        FROM mdl_coding_zoom_participants_unified p
        JOIN mdl_i3code_acuityZoom z ON z.zoom_meetingid = p.zoom_meetingid AND z.studentid = ?
        LEFT JOIN mdl_user t ON t.id = z.teacherid
        WHERE p.zoom_meetingid IN ($placeholders)
        AND p.duration_minutes > 0
        ORDER BY p.join_time ASC, p.duration_minutes DESC
    ";
    
    $params = array_merge([$view_userid], $meeting_ids);
    $stmt_part = $pdo->prepare($sql_participantes);
    $stmt_part->execute($params);
    $all_participants = $stmt_part->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por meeting_id
    foreach ($all_participants as $p) {
        $mid = $p['meeting_id'];
        if (!isset($participantes_zoom[$mid])) {
            $participantes_zoom[$mid] = [];
        }
        $participantes_zoom[$mid][] = $p;
    }
}

// Por clase: si el profesor se conectó (alguna conexión con correo @tuspeaking) y, si constó como asistida sin
// conexiones propias, dentro de qué reunión cayó (clases seguidas dadas en una sola reunión de Zoom) o que no hay registro.
foreach ($detalle_clases as $i => $cl) {
    $parts = $cl['meeting_id'] ? ($participantes_zoom[$cl['meeting_id']] ?? []) : [];
    foreach ($parts as $p) { if (!empty($p['email']) && stripos($p['email'], 'tuspeaking') !== false) { $detalle_clases[$i]['profesor_conecto'] = 'SI'; break; } }
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

// ─── Ficha FUNDAE del curso (cabecera del Excel) ───
$stmt_fundae = $pdo->prepare("
    SELECT f.c_fundae, f.empresa, f.razon_social, f.cif, f.modalidad, f.fecha_inicio, f.fecha_fin, f.denominacion_oficial,
           o.id_grupo, o.denominacion_oficial AS denom_oficial, o.modalidad AS modalidad_oficial,
           o.fecha_inicio AS inicio_oficial, o.fecha_fin AS fin_oficial, o.duracion AS horas_af
    FROM mdl_fundae f LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae
    WHERE f.courseid = ? ORDER BY f.c_fundae
");
$stmt_fundae->execute([$courseid]);
$fichas_fundae = $stmt_fundae->fetchAll(PDO::FETCH_ASSOC);

// T-181: lo que se enseña y se exporta son las tutorías; las clases canceladas no lo son y no se listan.
$detalle_clases = array_values(array_filter($detalle_clases, function ($c) { return $c['estado'] !== 'CANCELADA'; }));

// T-279e (26/10/2026), regla de En: una clase solo se muestra si su fecha cae dentro de las fechas de SU acción formativa
// (las de FUNDAE y, si no hay, las de la ficha). La acción del participante es su grupo de Moodle con el nombre del código;
// si no está en ninguno, vale cualquiera de las del curso. Curso sin ficha FUNDAE: se muestra todo.
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

$detalle_clases = array_values(array_filter($detalle_clases, function ($c) use ($en_su_accion, $view_userid) { return $en_su_accion($view_userid, $c['ts']); }));   // fuera de las fechas de su acción

// T-306 (27/10/2026): el feedback que el tutor escribe en Learn tras cada tutoría (y que el alumno recibe por correo) se ve aquí,
// en su tutoría, SOLO si lo permite tus_feedback_lib.php (reglas de En): curso encendido con t306-29, informe que pasa los controles
// de calidad o aprobado por En, y como mucho tantos como tutorías comunicadas a FUNDAE. Lo que falta no se marca. Sin tabla, nada.
require_once($CFG->dirroot . '/tus_feedback_lib.php');
$feedback_campos = tus_feedback_campos();
$feedback_por_clase = tus_feedback_visibles($courseid, $view_userid, array_column($detalle_clases, 'id'));
$total_feedback = count($feedback_por_clase);

// ─── QUERY 4: Info del curso desde own_acuity_course ───
$sql_curso_info = "
    SELECT tipo_clase, classnmbr, isfundae, fecha_inicio
    FROM own_acuity_course
    WHERE courseid = ?
    LIMIT 1
";
$stmt_info = $pdo->prepare($sql_curso_info);
$stmt_info->execute([$courseid]);
$curso_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total_sesiones = 0;
$total_asistidas = 0;
$total_canceladas = 0;
$total_pendientes = 0;
$total_minutos = 0;

foreach ($detalle_clases as $cl) {
    $total_sesiones++;
    switch ($cl['estado']) {
        case 'ASISTIO': $total_asistidas++; $total_minutos += intval($cl['minutos']); break;
        case 'CANCELADA': $total_canceladas++; break;
        case 'PENDIENTE': case 'NO INICIADA': $total_pendientes++; break;
    }
}

$pct_asistencia = ($total_asistidas + intval($resumen['clases_agendadas'] ?? 0)) > 0
    ? round(($total_asistidas / ($total_asistidas + ($total_sesiones - $total_asistidas - $total_canceladas - $total_pendientes))) * 100, 1)
    : 0;

// T-181: el porcentaje sale de las mismas clases que se enseñan (asistidas ÷ realizadas no canceladas ni pendientes),
// no de la vista v_zoom_asistencia_estudiantes, para que pantalla y Excel cuadren con el detalle.

// ─── EXPORTACIÓN EXCEL (.xlsx real, con la librería de Moodle) — T-181, 14/10/2026 ───
// Solo lo que piden el acta del SEPE de 14/10/2026 (A2.P2.01: fecha, hora, tutor y participante; A2.P2.09: registro de conexión
// de tutor y participante, entrada, salida y duración, «tal cual») y la respuesta de FUNDAE de 14/10/2026 (registros de conexión de
// la herramienta), más lo mínimo para que el registro no engañe: a qué grupo corresponde, el estado de cada clase, el nombre que Zoom
// dio a la reunión y una observación cuando la clase no tiene conexión propia o se corrigió a mano. Formato validado por En (14/10).
//   Tutorías    → una fila por clase (sin las canceladas), con SU reunión de Zoom
//   Conexiones  → una fila por conexión, tal como la da Zoom (el email sale «(invitado)» cuando Zoom no lo registra)
if ($export === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    $limpio = function ($s) { return trim(preg_replace('/[^A-Za-z0-9]+/', '_', core_text::specialtoascii($s)), '_'); };
    $filename = 'Tutorias_con_profesor_' . $limpio($course->fullname) . '_' . $limpio(fullname($view_user)) . '_' . date('Y-m-d') . '.xlsx';
    $etiqueta = ['ASISTIO' => 'Asistió', 'AUSENCIA' => 'Ausencia', 'PENDIENTE' => 'Pendiente', 'SIN DATOS' => 'Sin datos', 'VERIFICANDO' => 'Verificando'];

    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename);
    $f_titulo = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $f_negrita = $workbook->add_format(['bold' => 1]);
    $f_cabecera = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#008BA3']);
    $f_nota = $workbook->add_format(['italic' => 1, 'color' => 'gray']);
    $escribe = function ($ws, $r, $c, $v, $fmt = null) {
        if (is_int($v) || is_float($v)) { $ws->write_number($r, $c, $v, $fmt); } else { $ws->write_string($r, $c, (string)$v, $fmt); }
    };
    $cabecera = function ($ws) use ($fichas_fundae, $course, $view_user, $f_titulo, $f_negrita) {
        $ws->write_string(0, 0, 'Registro de tutorías con profesor', $f_titulo);
        $ag = []; $ids = [];
        foreach ($fichas_fundae as $ff) { $ag[] = str_replace('-', '/', (string)$ff['c_fundae']); $ids[] = $ff['id_grupo'] ?: '-'; }
        $filas = [
            ['Acción / grupo', $ag ? implode(', ', $ag) : '-'],
            ['ID de grupo FUNDAE', $ids ? implode(', ', $ids) : '-'],
            ['Curso', $course->fullname],
            ['Participante', fullname($view_user)],
            ['Email', $view_user->email],
        ];
        foreach ($filas as $i => $f) { $ws->write_string(2 + $i, 0, $f[0], $f_negrita); $ws->write_string(2 + $i, 1, (string)$f[1]); }
        return 8; // fila (0-based) de la cabecera de la tabla
    };

    // Hoja 1: Tutorías
    $ws = $workbook->add_worksheet('Tutorías');
    $r0 = $cabecera($ws);
    $cab = ['Fecha', 'Hora (Madrid)', 'Tutor', 'Participante', 'Estado', 'Zoom ID', 'Nombre de la reunión en Zoom', 'Observación', 'Feedback del tutor'];
    foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([30, 14, 22, 28, 12, 15, 62, 50, 40] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($detalle_clases as $cl) {
        $obs = $cl['nota'];
        if ($cl['manual'] !== 'No') { $obs = trim($obs . ($obs ? '. ' : '') . 'Corrección manual: ' . substr($cl['manual'], strlen('Sí: '))); }
        $fila = [$cl['fecha'], $cl['hora_agendada'], $cl['profesor'] ?: 'Sin datos', fullname($view_user),
                 $etiqueta[$cl['estado']] ?? $cl['estado'], $cl['meeting_id'] ? (string)$cl['meeting_id'] : '', $cl['tema'], $obs,
                 isset($feedback_por_clase[$cl['id']]) ? 'Sí: ' . $feedback_por_clase[$cl['id']]->teacher_name . ', escrito el ' . userdate($feedback_por_clase[$cl['id']]->timewritten, '%d/%m/%Y') . ' (hoja «Feedback»)' : ''];
        foreach ($fila as $c => $v) { $escribe($ws, $r, $c, $v); }
        $r++;
    }
    $ws->write_string($r + 1, 0, 'Observación: solo se rellena si la clase no tiene conexión propia en Zoom o se corrigió a mano.', $f_nota);

    // Hoja 2: Conexiones
    $ws = $workbook->add_worksheet('Conexiones');
    $r0 = $cabecera($ws);
    $cab = ['Fecha', 'Zoom ID', 'Nombre en Zoom', 'Email', 'Rol', 'Entrada (Madrid)', 'Salida (Madrid)', 'Duración (min)', 'Entrada (UTC, tal cual Zoom)', 'Salida (UTC, tal cual Zoom)'];
    foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
    foreach ([30, 15, 22, 30, 9, 15, 15, 13, 24, 24] as $c => $a) { $ws->set_column($c, $c, $a); }
    $r = $r0 + 1;
    foreach ($detalle_clases as $cl) {
        if (!$cl['meeting_id']) { continue; }
        foreach (($participantes_zoom[$cl['meeting_id']] ?? []) as $p) {
            $is_tutor = !empty($p['email']) && strpos($p['email'], '@live') !== false && strpos($p['email'], 'tuspeaking') !== false;
            $fila = [$cl['fecha'], (string)$cl['meeting_id'], $p['participante'], $p['email'] ?: '(invitado)', $is_tutor ? 'Tutor' : 'Alumno',
                     $p['hora_entrada'], $p['hora_salida'], (int)$p['duracion_min'], (string)$p['join_time_utc'], (string)($p['leave_time_utc'] ?? '')];
            foreach ($fila as $c => $v) { $escribe($ws, $r, $c, $v); }
            $r++;
        }
    }
    $ws->write_string($r + 1, 0, 'Datos del registro automático de conexiones de Zoom. Horas en hora de Madrid (CET/CEST) y, al lado, tal cual las da Zoom (UTC).', $f_nota);

    // Hoja 3: Feedback (T-306): el informe que el tutor escribió tras cada tutoría, tal cual
    if ($total_feedback) {
        $ws = $workbook->add_worksheet('Feedback');
        $r0 = $cabecera($ws);
        $cab = array_merge(['Fecha', 'Tutor', 'Escrito el'], array_values($feedback_campos));
        foreach ($cab as $c => $t) { $ws->write_string($r0, $c, $t, $f_cabecera); }
        foreach ($cab as $c => $t) { $ws->set_column($c, $c, $c < 3 ? 22 : 45); }
        $r = $r0 + 1;
        foreach ($detalle_clases as $cl) {
            if (!isset($feedback_por_clase[$cl['id']])) { continue; }
            $fb = $feedback_por_clase[$cl['id']]; $fbc = $fb->datos;
            $fila = [$cl['fecha'], $fb->teacher_name, userdate($fb->timewritten, '%d/%m/%Y %H:%M')];
            foreach (array_keys($feedback_campos) as $k) { $fila[] = (string)($fbc[$k] ?? ''); }
            foreach ($fila as $c => $v) { $escribe($ws, $r, $c, $v); }
            $r++;
        }
        $ws->write_string($r + 1, 0, 'Informe que el tutor escribe tras cada tutoría y que el participante recibe por correo. Copia literal.', $f_nota);
    }

    $workbook->close();
    exit;
}

echo $OUTPUT->header();
?>
<style>
:root {
    --ts-primary: #008ba3;
    --ts-primary-dark: #006d80;
    --ts-secondary: #00bcd4;
    --ts-light: #e0f7fa;
    --ts-success: #27ae60;
    --ts-warning: #f39c12;
    --ts-danger: #e74c3c;
    --ts-dark: #424242;
    --ts-gray: #64748b;
    --ts-canceled: #9ca3af;
}

.tp-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.tp-header {
    background: linear-gradient(135deg, var(--ts-primary), var(--ts-secondary));
    color: #fff;
    padding: 24px 28px;
    border-radius: 16px 16px 0 0;
    margin-bottom: 0;
}

.tp-header h2 {
    margin: 0 0 4px;
    font-size: 1.3em;
    font-weight: 700;
    color: #fff;
}

.tp-header .tp-subtitle {
    color: rgba(255,255,255,.9);
    font-size: .9em;
    margin: 0;
}

.tp-header .tp-badge-fundae {
    display: inline-block;
    background: rgba(255,255,255,.2);
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .75em;
    font-weight: 600;
    margin-top: 8px;
    border: 1px solid rgba(255,255,255,.3);
}

.tp-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    padding: 20px 28px;
    gap: 12px;
    background: #f8fafc;
    border-left: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
}

.tp-stat {
    text-align: center;
    padding: 14px 10px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.tp-stat strong {
    display: block;
    font-size: 1.6em;
    font-weight: 700;
    margin-bottom: 4px;
}

.tp-stat small {
    color: var(--ts-gray);
    font-size: .8em;
}

.tp-stat.green strong { color: var(--ts-success); }
.tp-stat.red strong { color: var(--ts-danger); }
.tp-stat.orange strong { color: var(--ts-warning); }
.tp-stat.blue strong { color: var(--ts-primary); }
.tp-stat.gray strong { color: var(--ts-gray); }

.tp-alumno-info {
    padding: 16px 28px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    font-size: .9em;
}

.tp-alumno-info .tp-field {
    display: flex;
    flex-direction: column;
}

.tp-alumno-info .tp-field label {
    color: var(--ts-gray);
    font-size: .75em;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
}

.tp-alumno-info .tp-field span {
    color: var(--ts-dark);
    font-weight: 500;
}

.tp-section-title {
    margin: 28px 0 16px;
    color: var(--ts-dark);
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 12px;
    font-size: 1.1em;
    font-weight: 600;
}

/* ─── Tabla de sesiones ─── */
.tp-sesion {
    background: #fff;
    border-radius: 12px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.tp-sesion-header {
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.tp-sesion-header .tp-fecha {
    font-weight: 600;
    color: var(--ts-dark);
    font-size: .95em;
}

.tp-sesion-header .tp-meeting-id {
    color: var(--ts-gray);
    font-size: .8em;
    font-family: 'Courier New', monospace;
}

.tp-sesion-header .tp-estado {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .75em;
    font-weight: 600;
    color: #fff;
}

.tp-estado-asistio { background: var(--ts-success); }
.tp-estado-cancelada { background: var(--ts-canceled); }
.tp-estado-pendiente { background: var(--ts-warning); }
.tp-estado-noiniciada { background: var(--ts-gray); }

.tp-sesion-meta {
    padding: 10px 20px;
    background: #fafbfc;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    font-size: .85em;
    color: var(--ts-gray);
}

.tp-sesion-meta strong {
    color: var(--ts-dark);
}

/* Tabla de participantes dentro de cada sesión */
.tp-participantes-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .85em;
}

.tp-participantes-table thead {
    background: #f1f5f9;
}

.tp-participantes-table th {
    padding: 10px 16px;
    text-align: left;
    color: var(--ts-gray);
    font-weight: 600;
    font-size: .8em;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid #e2e8f0;
}

.tp-participantes-table td {
    padding: 10px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: var(--ts-dark);
}

.tp-participantes-table tr:last-child td {
    border-bottom: none;
}

.tp-participantes-table .tp-rol-tutor {
    color: var(--ts-primary);
    font-weight: 600;
}

.tp-participantes-table .tp-rol-alumno {
    color: var(--ts-dark);
}

.tp-no-zoom {
    padding: 16px 20px;
    color: var(--ts-gray);
    font-style: italic;
    font-size: .85em;
}

.tp-back-link {
    color: var(--ts-primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 16px;
    font-weight: 500;
}

.tp-back-link:hover {
    text-decoration: underline;
    color: var(--ts-primary-dark);
}

.tp-footer-note {
    margin-top: 24px;
    padding: 16px 20px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: .8em;
    color: var(--ts-gray);
}

.tp-footer-note strong {
    color: var(--ts-dark);
}

.tp-export-bar {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-bottom: 16px;
}

.tp-btn-export {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: var(--ts-primary);
    border: 2px solid var(--ts-primary);
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: .9em;
    transition: all .2s;
    cursor: pointer;
}

.tp-btn-export:hover {
    background: var(--ts-primary);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0,139,163,.3);
}

.tp-feedback {
    margin: 12px 0 4px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid var(--ts-primary);
    border-radius: 8px;
    background: #f8fafc;
}

.tp-feedback summary {
    cursor: pointer;
    padding: 10px 16px;
    font-weight: 600;
    color: var(--ts-dark);
}

.tp-feedback dl {
    margin: 0;
    padding: 4px 16px 12px;
}

.tp-feedback dt {
    margin-top: 10px;
    font-size: .8em;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: var(--ts-gray);
}

.tp-feedback dd {
    margin: 2px 0 0;
    white-space: normal;
    overflow-wrap: anywhere;
}

@media (max-width: 768px) {
    .tp-stats { grid-template-columns: repeat(3, 1fr); }
    .tp-sesion-header { flex-direction: column; align-items: flex-start; }
    .tp-sesion-meta { flex-direction: column; gap: 8px; }
    .tp-participantes-table { font-size: .8em; }
    .tp-participantes-table th, .tp-participantes-table td { padding: 8px 10px; }
}
</style>

<div class="tp-container">

    <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>" class="tp-back-link">← Volver al curso</a>

    <div class="tp-export-bar">
        <a href="?courseid=<?php echo $courseid; ?>&userid=<?php echo $view_userid; ?>&export=excel" class="tp-btn-export">
            📥 Descargar Excel
        </a>
    </div>

    <!-- ─── CABECERA ─── -->
    <div class="tp-header">
        <h2>📋 Tutorías con profesor</h2>
        <p class="tp-subtitle"><?php echo htmlspecialchars($course->fullname); ?></p>
        <?php if ($curso_info && $curso_info['isfundae']): ?>
        <span class="tp-badge-fundae">📑 Curso FUNDAE · Tipo: <?php echo $curso_info['tipo_clase'] == '1TO1' ? '1 to 1' : 'Grupal'; ?></span>
        <?php endif; ?>
    </div>

    <!-- ─── GRUPO FUNDAE (T-181: sin porcentajes ni totales; solo lo que piden el SEPE y FUNDAE) ─── -->
    <?php if (!empty($fichas_fundae)): ?>
    <div class="tp-alumno-info">
        <?php foreach ($fichas_fundae as $ff): ?>
        <div class="tp-field"><label>Acción / grupo</label><span><?php echo htmlspecialchars(str_replace('-', '/', (string)$ff['c_fundae'])); ?></span></div>
        <div class="tp-field"><label>ID de grupo FUNDAE</label><span><?php echo htmlspecialchars($ff['id_grupo'] ?: '-'); ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ─── DATOS DEL ALUMNO ─── -->
    <div class="tp-alumno-info">
        <div class="tp-field">
            <label>Alumno/a</label>
            <span><?php echo fullname($view_user); ?></span>
        </div>
        <div class="tp-field">
            <label>Email</label>
            <span><?php echo $view_user->email; ?></span>
        </div>
        <div class="tp-field">
            <label>Curso</label>
            <span><?php echo htmlspecialchars($course->fullname); ?></span>
        </div>
        <?php if ($resumen): ?>
        <div class="tp-field">
            <label>Primera clase</label>
            <span><?php echo $detalle_clases ? $detalle_clases[0]['fecha'] : '-'; ?></span>
        </div>
        <div class="tp-field">
            <label>Última clase</label>
            <span><?php echo $detalle_clases ? $detalle_clases[count($detalle_clases) - 1]['fecha'] : '-'; ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── DETALLE POR SESIÓN ─── -->
    <h3 class="tp-section-title">📅 Registro de sesiones (<?php echo count($detalle_clases); ?>)<?php if ($total_feedback): ?> · 📝 con feedback del tutor: <?php echo $total_feedback; ?><?php endif; ?></h3>

    <?php foreach ($detalle_clases as $cl):
        $estado = trim($cl['estado']);
        $estado_class = 'tp-estado-asistio';
        $estado_label = $estado;
        
        switch ($estado) {
            case 'ASISTIO': $estado_class = 'tp-estado-asistio'; $estado_label = 'Asistió'; break;
            case 'CANCELADA': $estado_class = 'tp-estado-cancelada'; $estado_label = 'Cancelada'; break;
            case 'PENDIENTE': $estado_class = 'tp-estado-pendiente'; $estado_label = 'Pendiente'; break;
            case 'NO INICIADA': $estado_class = 'tp-estado-noiniciada'; $estado_label = 'No iniciada'; break;
            case 'AUSENCIA': $estado_class = 'tp-estado-noiniciada'; $estado_label = 'Ausencia'; break;
            case 'VERIFICANDO': $estado_class = 'tp-estado-pendiente'; $estado_label = 'Verificando'; break;
            case 'SIN DATOS': $estado_class = 'tp-estado-noiniciada'; $estado_label = 'Sin datos'; break;
        }
        
        // T-181: la reunión es la de esta clase (antes se buscaba por el día y se repetía en clases del mismo día).
        $meeting_id = $cl['meeting_id'] ?: null;
        
        $participants = $meeting_id ? ($participantes_zoom[$meeting_id] ?? []) : [];
        
        // Generar hash de verificación SHA-256 para trazabilidad FUNDAE
        $verify_hash = null;
        $zoom_url = null;
        if ($meeting_id && !empty($participants)) {
            // Datos inmutables para el hash: meeting_id + todos los join/leave times
            $hash_data = $meeting_id;
            foreach ($participants as $p) {
                $hash_data .= '|' . $p['join_time_utc'] . '|' . ($p['leave_time_utc'] ?? '');
            }
            $verify_hash = strtoupper(substr(hash('sha256', $hash_data), 0, 16));
            $zoom_url = 'https://zoom.us/j/' . $meeting_id;
        }
    ?>
    <div class="tp-sesion">
        <div class="tp-sesion-header">
            <span class="tp-fecha">📅 <?php echo $cl['fecha']; ?></span>
            <?php if ($meeting_id): ?>
            <span class="tp-meeting-id">Zoom ID: <a href="<?php echo $zoom_url; ?>" target="_blank" style="color:inherit;text-decoration:underline;"><?php echo $meeting_id; ?></a></span>
            <?php endif; ?>
            <span class="tp-estado <?php echo $estado_class; ?>"><?php echo $estado_label; ?></span>
        </div>
        
        <?php if ($estado !== 'CANCELADA'): ?>
        <div class="tp-sesion-meta">
            <span>👨‍🏫 Tutor: <strong><?php echo htmlspecialchars($cl['profesor'] ?: 'Sin datos'); ?></strong></span>
            <span>🕐 Hora agendada: <strong><?php echo $cl['hora_agendada'] ?: '-'; ?></strong></span>
            <?php if ($cl['tema'] !== ''): ?><span>🏷 Reunión en Zoom: <strong><?php echo htmlspecialchars($cl['tema']); ?></strong></span><?php endif; ?>
            <?php if ($cl['manual'] !== 'No'): ?><span>✍️ Corrección manual: <strong><?php echo htmlspecialchars($cl['manual']); ?></strong></span><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php $fb = $feedback_por_clase[$cl['id']] ?? null; if ($fb): $fbc = $fb->datos; ?>
        <details class="tp-feedback">
            <summary>📝 Feedback de la tutoría · <?php echo htmlspecialchars($fb->teacher_name); ?> · escrito el <?php echo userdate($fb->timewritten, '%d/%m/%Y'); ?></summary>
            <dl>
                <?php foreach ($feedback_campos as $k => $etq): if (!isset($fbc[$k]) || trim((string)$fbc[$k]) === '') { continue; } ?>
                <dt><?php echo $etq; ?></dt>
                <dd><?php echo nl2br(htmlspecialchars((string)$fbc[$k])); ?></dd>
                <?php endforeach; ?>
            </dl>
        </details>
        <?php endif; ?>
        
        <?php if (!empty($participants)): ?>
        <table class="tp-participantes-table">
            <thead>
                <tr>
                    <th>Participante</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Hora entrada</th>
                    <th>Hora salida</th>
                    <th>Duración (min)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $p): 
                    $is_tutor = !empty($p['email']) && strpos($p['email'], '@live') !== false && strpos($p['email'], 'tuspeaking') !== false;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['participante']); ?></td>
                    <td><?php echo htmlspecialchars($p['email'] ?: '(invitado)'); ?></td>
                    <td class="<?php echo $is_tutor ? 'tp-rol-tutor' : 'tp-rol-alumno'; ?>">
                        <?php echo $is_tutor ? '👨‍🏫 Tutor' : '👤 Alumno'; ?>
                    </td>
                    <td><?php echo $p['hora_entrada']; ?></td>
                    <td><?php echo $p['hora_salida']; ?></td>
                    <td><?php echo $p['duracion_min']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif ($estado === 'ASISTIO'): ?>
        <div class="tp-no-zoom">
            ℹ️ Sesión registrada como asistida. <?php echo htmlspecialchars($cl['nota']); ?>.
        </div>
        <?php elseif ($estado === 'CANCELADA'): ?>
        <div class="tp-no-zoom">
            🚫 Sesión cancelada — sin registro de participantes.
        </div>
        <?php elseif ($estado === 'PENDIENTE'): ?>
        <div class="tp-no-zoom">
            🕐 Sesión pendiente de realización.
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($detalle_clases)): ?>
    <div class="tp-sesion">
        <div class="tp-no-zoom" style="padding: 30px; text-align: center;">
            No se encontraron sesiones de tutoría registradas para este curso.
        </div>
    </div>
    <?php endif; ?>

    <!-- ─── PIE ─── -->
    <div class="tp-footer-note">
        <strong>Nota sobre los datos:</strong> Los horarios se muestran en hora local (Europe/Madrid, CET/CEST). 
        Los datos de participantes y duración provienen del registro automático de Zoom. 
        La hora de entrada y salida corresponden al momento exacto en que cada participante 
        se conectó y desconectó de la videoconferencia.
        <br><br>
        <strong>Generado:</strong> <?php echo date('d/m/Y H:i:s'); ?> · 
        <strong>Alumno:</strong> <?php echo fullname($view_user); ?> · 
        <strong>Curso:</strong> <?php echo htmlspecialchars($course->fullname); ?> (ID: <?php echo $courseid; ?>)
    </div>

</div>

<?php
echo $OUTPUT->footer();
?>
