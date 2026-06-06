<?php
require('../config.php');
require_login();

$PAGE->set_url(new moodle_url('/portal/dashboard_rrhh.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Dashboard RRHH - tuSpeaking Success');
$PAGE->set_heading('Dashboard RRHH');
echo $OUTPUT->header();
?>
<style>
.rrhh-container{width:100%;height:calc(100vh - 200px);min-height:600px;border:none;border-radius:8px;overflow:hidden}
.rrhh-wrapper{padding:10px 0}
.rrhh-note{background:#e8f5e9;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.9em;color:#2e7d32}
</style>
<div class="rrhh-wrapper">
    <div class="rrhh-note">
        📊 Panel de seguimiento de formación en tiempo real
    </div>
    <iframe src="https://success.tuspeaking.com" class="rrhh-container" allow="fullscreen"></iframe>
</div>
<?php echo $OUTPUT->footer(); ?>
