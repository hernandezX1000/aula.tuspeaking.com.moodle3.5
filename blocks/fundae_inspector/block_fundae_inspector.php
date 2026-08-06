<?php
defined('MOODLE_INTERNAL') || die();

class block_fundae_inspector extends block_base {

    public function init() {
        $this->title = 'Inspector FUNDAE';
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
        $html .= '<p style="font-size:11px;color:#888;margin:0 0 10px;">Accesos inspector</p>';
        $html .= '<ul style="list-style:none;padding:0;margin:0;">';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/tutorias_con_profesor.php?courseid=' . $courseid . '">Tutorias con profesor</a></li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/report/log/index.php?id=' . $courseid . '">Registros de actividad</a></li>';
        $html .= '<li style="margin-bottom:8px;"><a href="' . $base . '/report/progress/index.php?course=' . $courseid . '">Finalizacion de actividad</a></li>';
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
