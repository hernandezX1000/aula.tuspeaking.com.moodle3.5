<?php
require('../config.php');
require_login();

$PAGE->set_url(new moodle_url('/portal/topics.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Conversaciones - Topics');
$PAGE->set_heading('Conversaciones');
echo $OUTPUT->header();
?>
<style>
.topics-container{width:100%;height:calc(100vh - 200px);min-height:600px;border:none;border-radius:8px;overflow:hidden}
.topics-wrapper{padding:10px 0}
.topics-note{background:#e3f2fd;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.9em;color:#1565c0}
</style>
<div class="topics-wrapper">
    <div class="topics-note">
        💡 Si es tu primera vez, puede que necesites iniciar sesión en la plataforma de conversaciones.
    </div>
    <iframe src="https://learn.tuspeaking.com/topics" class="topics-container" allow="microphone"></iframe>
</div>
<?php echo $OUTPUT->footer(); ?>
