<?php
/**
 * aula-completion-recalcular.php — recalcula el estado de finalización (completion)
 * de las actividades de un alumno en un curso del aula.
 *
 * DESPLIEGUE (no lo lleva deploy-aula.sh: tus-tools/ esta excluido del rsync).
 * Desde el Mac, con el cambio ya commiteado:
 *
 *   scp tus-tools/moodle-cli/aula-completion-recalcular.php coreadmin@46.225.232.27:/tmp/
 *   ssh coreadmin@46.225.232.27
 *   sudo mkdir -p /mnt/moodle-data/moodle-code/tus-cli
 *   sudo cp /tmp/aula-completion-recalcular.php /mnt/moodle-data/moodle-code/tus-cli/
 *   sudo chown 33:33 /mnt/moodle-data/moodle-code/tus-cli/aula-completion-recalcular.php
 *
 * Uso (SIEMPRE via aula-php, que ejecuta como www-data):
 *
 *   aula-php tus-cli/aula-completion-recalcular.php --curso=4063 --usuario=3583
 *   aula-php tus-cli/aula-completion-recalcular.php --curso=4063 --usuario=3583 --aplicar
 *   aula-php tus-cli/aula-completion-recalcular.php --curso=4063 --usuario=3583 --cm=544118,544130 --aplicar
 *
 * Sin --aplicar solo diagnostica: no escribe nada.
 *
 * Por que existe (02-sep-2026): Chi Aligbe (user 3583) entrego las 3 tareas del curso
 * 4063, pero solo 1 constaba como completada -> el panel mostraba 33,3% de plataforma y
 * parecia trabajo sin hacer. La finalizacion no se habia recalculado. Esto lo rehace
 * usando la API de Moodle (completion_info::update_state), no SQL a pelo.
 *
 * --marcar-manual: solo para actividades con finalizacion MANUAL (la marca el alumno).
 * En ese caso update_state no puede deducir nada; esta bandera la marca en su nombre.
 * Es un cambio de datos deliberado: usarla solo con criterio y dejarlo por escrito.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');

list($options, $unrecognized) = cli_get_params(
    ['curso' => null, 'usuario' => null, 'cm' => null,
     'aplicar' => false, 'marcar-manual' => false, 'help' => false],
    ['h' => 'help']
);

if ($options['help'] || empty($options['curso']) || empty($options['usuario'])) {
    cli_writeln("Uso: --curso=<id> --usuario=<id> [--cm=id,id] [--aplicar] [--marcar-manual]");
    exit(0);
}

$courseid = (int)$options['curso'];
$userid   = (int)$options['usuario'];
$filtro   = $options['cm'] ? array_map('intval', explode(',', $options['cm'])) : null;
$aplicar  = (bool)$options['aplicar'];
$manual   = (bool)$options['marcar-manual'];

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$user   = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,email', MUST_EXIST);

$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    cli_error("El curso {$courseid} NO tiene el seguimiento de finalizacion activado.");
}

cli_writeln(sprintf("Curso %d: %s", $course->id, $course->fullname));
cli_writeln(sprintf("Alumno %d: %s %s <%s>", $user->id, $user->firstname, $user->lastname, $user->email));
cli_writeln($aplicar ? ">>> MODO APLICAR (escribe)" : ">>> Simulacion (no escribe). Anade --aplicar para ejecutar.");
cli_writeln(str_repeat('-', 100));

$modinfo = get_fast_modinfo($course, $userid);
$tipos = [COMPLETION_TRACKING_NONE => 'ninguna',
          COMPLETION_TRACKING_MANUAL => 'MANUAL',
          COMPLETION_TRACKING_AUTOMATIC => 'automatica'];
$estados = [COMPLETION_INCOMPLETE => 'incompleta',
            COMPLETION_COMPLETE => 'COMPLETA',
            COMPLETION_COMPLETE_PASS => 'COMPLETA (aprobado)',
            COMPLETION_COMPLETE_FAIL => 'completa (suspenso)'];

$cambios = 0;
foreach ($modinfo->get_cms() as $cm) {
    if ($filtro && !in_array($cm->id, $filtro, true)) {
        continue;
    }
    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        continue;
    }

    $antes = $completion->get_data($cm, false, $userid)->completionstate;

    $cond = [];
    if ($cm->completionview) { $cond[] = 'ver'; }
    if ($cm->completiongradeitemnumber !== null) { $cond[] = 'calificacion'; }
    if ($cm->modname === 'assign') {
        $sub = $DB->get_field('assign', 'completionsubmit', ['id' => $cm->instance]);
        if ($sub) { $cond[] = 'entrega'; }
    }

    cli_writeln(sprintf("cm %-8d %-8s %-45s seguimiento=%-11s cond=[%-22s] estado=%s",
        $cm->id, $cm->modname, mb_strimwidth($cm->name, 0, 45, '..'),
        $tipos[$cm->completion], implode(',', $cond) ?: '-',
        $estados[$antes] ?? $antes));

    if (!$aplicar) {
        continue;
    }

    if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
    } else if ($manual) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
    } else {
        cli_writeln("    (finalizacion manual: se omite. Usa --marcar-manual si procede)");
        continue;
    }

    $despues = $completion->get_data($cm, false, $userid)->completionstate;
    if ($despues != $antes) {
        $cambios++;
        cli_writeln(sprintf("    -> %s  =>  %s",
            $estados[$antes] ?? $antes, $estados[$despues] ?? $despues));
    } else {
        cli_writeln("    -> sin cambio (la condicion sigue sin cumplirse)");
    }
}

cli_writeln(str_repeat('-', 100));
cli_writeln($aplicar ? "Actividades modificadas: {$cambios}" : "Fin de la simulacion.");
