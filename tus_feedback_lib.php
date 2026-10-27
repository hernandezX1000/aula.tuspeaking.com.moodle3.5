<?php
/**
 * tus_feedback_lib.php — T-306 (27/10/2026) · Feedback del tutor tras cada tutoría: qué se enseña en el aula.
 *
 * El feedback lo escribe el tutor en Learn y se copia a mdl_tuspeaking_tutor_feedback (script t306-28, con los controles de
 * calidad ya calculados en las columnas calidad y motivos). Reglas de En (27/10/2026):
 *   1. Interruptor por curso, APAGADO por defecto: config_plugins plugin «tuspeaking_feedback», nombre «c<courseid>», valor = número
 *      de tutorías comunicadas a FUNDAE (entero > 0). Sin valor o 0 = apagado: no se enseña nada (script t306-29 on/off).
 *   2. Solo el feedback que pasa los controles (calidad = 'mostrar') o que En aprueba a mano (decision = 1). decision = 0 lo oculta siempre.
 *   3. Como mucho tantos como tutorías comunicadas a FUNDAE, por participante: los primeros por fecha de clase.
 *   4. Los campos de progreso en la plataforma no se enseñan nunca (el aula tiene el progreso real en sus páginas).
 * Lo usan tutorias_con_profesor.php, feedback_grupo.php y el bloque fundae_inspector. Si la tabla no existe, no hay nada.
 *
 * @copyright 2026 Micro Ventures S.L.
 */
defined('MOODLE_INTERNAL') || die();

/** Campos que se enseñan, en orden, con su etiqueta. */
function tus_feedback_campos() {
    return [
        'main_topic' => 'Tema de la sesión', 'topic_questions' => 'Preguntas y temas tratados', 'used_resources' => 'Recursos utilizados',
        'vocabulary' => 'Vocabulario', 'what_did_well' => 'Lo que ha hecho bien', 'where_improve' => 'Qué mejorar',
        'for_next_class' => 'Para la próxima sesión', 'teacher_comments' => 'Comentarios del tutor', 'class_recording' => 'Grabación de la clase',
    ];
}

/** Tutorías comunicadas a FUNDAE si el curso tiene el feedback encendido; 0 si está apagado. */
function tus_feedback_max($courseid) {
    $v = get_config('tuspeaking_feedback', 'c' . (int)$courseid);
    return ($v !== false && (int)$v > 0) ? (int)$v : 0;
}

/**
 * Feedback que se puede enseñar en el curso, indexado por id de la tutoría (mdl_i3code_acuityZoom.id).
 * @param int $courseid
 * @param int $userid 0 = todos los participantes
 * @param array|null $permitidos ids de tutorías que la página enseña (p. ej. solo las de las fechas de su acción); null = todas
 * @return array acuityzoomid => registro, con ->datos (array de campos) añadido
 */
function tus_feedback_visibles($courseid, $userid = 0, $permitidos = null) {
    global $DB;
    $max = tus_feedback_max($courseid);
    if (!$max) { return []; }
    $w = 'courseid = :c AND acuityzoomid IS NOT NULL';
    $p = ['c' => (int)$courseid];
    if ($userid) { $w .= ' AND userid = :u'; $p['u'] = (int)$userid; }
    try {
        $todos = $DB->get_records_select('tuspeaking_tutor_feedback', $w, $p, 'userid, fecha_clase, timewritten');
    } catch (Exception $e) {
        return [];
    }
    $out = []; $n = [];
    foreach ($todos as $fb) {
        $ok = ($fb->decision !== null && (int)$fb->decision === 1) || ($fb->decision === null && $fb->calidad === 'mostrar');
        if (!$ok) { continue; }
        if ($permitidos !== null && !in_array((int)$fb->acuityzoomid, array_map('intval', $permitidos), true)) { continue; }
        $n[$fb->userid] = ($n[$fb->userid] ?? 0) + 1;
        if ($n[$fb->userid] > $max) { continue; }
        $fb->datos = json_decode((string)$fb->campos, true) ?: [];
        $out[(int)$fb->acuityzoomid] = $fb;
    }
    return $out;
}
