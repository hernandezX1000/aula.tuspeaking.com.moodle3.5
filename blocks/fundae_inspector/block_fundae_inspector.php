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
        $html .= '<li style="list-style:none;margin:10px 0 4px -14px;font-weight:bold;color:#555;">Seguimiento de los participantes</li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/accesos_grupo.php?courseid=' . $courseid . '">Registros de actividad</a></li>';
        // T-189 Fase 3 (15/10): en CENIEH la guía declara «realizar al menos el 75 % de los controles» → página con ese cálculo.
        if (in_array((int)$courseid, [3230, 3231, 3232, 3233, 3234], true)) {
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
