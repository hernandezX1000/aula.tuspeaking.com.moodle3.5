<?php
defined('MOODLE_INTERNAL') || die();

class block_fundae_inspector extends block_base {

    public function init() {
        $this->title = 'Seguimiento y control · FUNDAE';
    }

    public function get_content() {
        global $CFG, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';

        if ($PAGE->context->contextlevel != CONTEXT_COURSE) {
            return $this->content;
        }

        // Solo el inspector FUNDAE (rol `supervisor`, «Supervisor/inspector FUNDAE») y los administradores.
        // Sin contenido, Moodle no pinta el bloque. Antes lo veía cualquiera que entrara en el curso (25-oct-2026, T-279).
        global $USER;
        $ver = is_siteadmin();
        if (!$ver) {
            foreach (get_user_roles($PAGE->context, $USER->id, true) as $rol) {
                if ($rol->shortname === 'supervisor') {
                    $ver = true;
                    break;
                }
            }
        }
        if (!$ver) {
            return $this->content;
        }

        $courseid = $PAGE->course->id;
        $base = $CFG->wwwroot . '';

        $html  = '<div style="padding:4px;">';
        $html .= '<ul style="list-style:none;padding:0;margin:0;">';
        global $DB;
        // T-193 (15/10): guía didáctica del curso (acta SEPE 10/06: «debe estar visible para el SEPE»). Si no hay, no sale el grupo.
        $t193gd = $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'page' JOIN {page} p ON p.id = cm.instance WHERE cm.course = ? AND cm.visible = 1 AND cm.deletioninprogress = 0 AND " . $DB->sql_like('p.name', '?', false) . " ORDER BY cm.id DESC", [$courseid, 'Guía didáctica%'], IGNORE_MULTIPLE);
        if ($t193gd) {
            $html .= '<li style="list-style:none;margin:10px 0 4px -14px;font-weight:bold;color:#555;">Documentación del curso</li>';
            $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/mod/page/view.php?id=' . $t193gd->id . '">Guía didáctica</a></li>';
        }
        $html .= '<li style="list-style:none;margin:10px 0 4px -14px;font-weight:bold;color:#555;">Tutorías por videoconferencia</li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/tutorias_programadas.php?courseid=' . $courseid . '">Programadas: entrar en directo</a></li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/reservas_grupo.php?courseid=' . $courseid . '">Historial de reservas</a></li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/conexiones_grupo.php?courseid=' . $courseid . '">Conexiones de Zoom</a></li>';
        // T-306 (27/10/2026): «Feedback del tutor» solo si el curso lo tiene encendido y hay algún informe que se pueda enseñar
        // (reglas en tus_feedback_lib.php: interruptor por curso, controles de calidad, tope de tutorías comunicadas a FUNDAE).
        if (is_readable($CFG->dirroot . '/tus_feedback_lib.php')) {
            require_once($CFG->dirroot . '/tus_feedback_lib.php');
            if (tus_feedback_visibles($courseid)) {
                $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/feedback_grupo.php?courseid=' . $courseid . '">Feedback del tutor</a></li>';
            }
        }
        $html .= '<li style="list-style:none;margin:10px 0 4px -14px;font-weight:bold;color:#555;">Seguimiento de los participantes</li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/accesos_grupo.php?courseid=' . $courseid . '">Registros de actividad</a></li>';
        // Página del 75 % SOLO en los cursos cuya guía declara «realizar al menos el 75 % de los controles» (H5P con nota, cuestionarios
        // y tareas entregadas de los temas) y en los que se ha comprobado que la cuenta coincide con la guía. Se añade curso a curso.
        // T-189 Fase 3 (15/10): CENIEH 3230–3234. T-306 (26/10): Lin3s 2.ª 4064–4070 (tareas voluntarias: cuentan si se entregan).
        if (in_array((int)$courseid, [3230, 3231, 3232, 3233, 3234, 4064, 4065, 4066, 4067, 4068, 4069, 4070], true)) {
            $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/finalizacion_75.php?courseid=' . $courseid . '">Finalización de actividad (75 %)</a></li>';
        } else {
            $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/finalizacion_actividad.php?courseid=' . $courseid . '">Finalización de actividad</a></li>';
        }
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/grade/report/grader/index.php?id=' . $courseid . '">Calificaciones</a></li>';
        $html .= '</ul>';
        $html .= '</div>';

        $this->content->text = $html;
        return $this->content;
    }

    public function applicable_formats() {
        return array('course-view' => true, 'all' => false);
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return false;
    }
}
