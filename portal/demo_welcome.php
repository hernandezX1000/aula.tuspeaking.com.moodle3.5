<?php
require('../config.php');
require_login();

$velcro_users = ['aldiaz@velcro.com', 'brodriguez@velcro.com', 'mlinan@velcro.com', 'sherrera@velcro.com', 'ddroege@velcro.com'];
$demo_users = ['demo.cenieh', 'demo.mar.abgcc', 'demo.natalia.victoria', 'demo.test.company', 'demo.pamesa', 'demo.cap.capital', 'demo.rg.iberia', 'demo.new.lush', 'demo.manusa', 'demo.fritermia', 'demo.salva', 'demo.ambit', 'demo.envases', 'demo.casas', 'demo.unitedit', 'demo.antonpaar', 'demo.dermik', 'demo.totem', 'demo.aarus', 'demo.ivi', 'demo.abionica', 'demo.novasonic', 'demo.ibeforum', 'demo.ironhack', 'demo.biopharma', 'demo.metalco', 'demo.tradisa', 'demo.synergie', 'demo.adecco.new'];

$allowed = array_merge($velcro_users, $demo_users);

if (!in_array($USER->username, $allowed)) {
    redirect($CFG->wwwroot . '/my/');
}

$is_rrhh = ($USER->username === 'aldiaz@velcro.com');

$PAGE->set_url(new moodle_url('/portal/demo_welcome.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Bienvenido - Demo');
$PAGE->set_pagelayout('mydashboard');
echo $OUTPUT->header();
?>
<style>
.demo-container{max-width:900px;margin:0 auto;padding:20px}
.demo-header{text-align:center;margin-bottom:30px}
.demo-header h1{color:#333;font-weight:normal;margin:0}
.demo-header h1 strong{color:#008ba3}
.demo-cards{display:flex;justify-content:center;gap:20px;margin-top:30px}.demo-card{width:220px}
.demo-card{background:#fff;border-radius:12px;padding:25px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.08);transition:transform 0.2s,box-shadow 0.2s;text-decoration:none;color:#333;border:1px solid #eee}
.demo-card:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.12);text-decoration:none;color:#333}
.demo-card-icon{font-size:40px;margin-bottom:15px}
.demo-card-title{font-size:16px;font-weight:600;margin-bottom:8px;color:#008ba3}
.demo-card-desc{font-size:13px;color:#666;line-height:1.4}
.demo-info{background:#e3f2fd;border-radius:10px;padding:20px;margin-top:30px;text-align:center}
.demo-info p{margin:0;color:#1565c0;font-size:14px}
</style>

<div class="demo-container">
    <div class="demo-header">
        <h1>Hola, <strong><?php echo $USER->firstname; ?></strong> 👋</h1>
        <p style="color:#666;margin-top:10px;">Bienvenido a la plataforma de formación tuSpeaking</p>
    </div>

    <div class="demo-cards">
        <a href="/course/view.php?id=1866" class="demo-card">
            <div class="demo-card-icon">📚</div>
            <div class="demo-card-title">Mi Curso</div>
            <div class="demo-card-desc">Accede a tu contenido de formación, ejercicios y recursos</div>
        </a>

        <a href="/misclases.php" class="demo-card">
            <div class="demo-card-icon">📅</div>
            <div class="demo-card-title">Mis Clases</div>
            <div class="demo-card-desc">Consulta tu historial, asistencia y próximas clases</div>
        </a>



        <?php if ($is_rrhh): ?>
        <a href="/portal/dashboard_rrhh.php" class="demo-card">
            <div class="demo-card-icon">📊</div>
            <div class="demo-card-title">Dashboard RRHH</div>
            <div class="demo-card-desc">Panel de seguimiento para responsables de formación</div>
        </a>
        <?php endif; ?>
    </div>

    <div class="demo-info">
        <p>💡 Para reservar una clase con un profesor, accede a <strong>Mi Curso</strong> y usa el botón <strong>Reservar</strong> en el panel lateral.</p>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
