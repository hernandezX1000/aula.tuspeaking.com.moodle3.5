<?php
require('./config.php');
require('./askddbb.php');
global $CFG;
global $USER;
$admins = get_admins();
$userid = $USER->id;
$isadmin = false;
foreach($admins as $admin){
if ($admin->id == $USER->id){
$isadmin = true;
break;
}
}
if (!$isadmin){
header("Location: http://aula.tuspeaking.com/app/moodle");
die();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="https://aula.tuspeaking.com/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <!-- Select2 para búsqueda en dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./own_CourseAcuity.js?v=13"></script>
    <title>Course - Appointment Type | tuSpeaking</title>
    <style>
        :root {
            --tus-primary: #008ba3;
            --tus-secondary: #00bcd4;
            --tus-dark: #454545;
        }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: var(--tus-dark); margin: 0; }
        header { background: linear-gradient(135deg, var(--tus-primary), var(--tus-secondary)); padding: 15px 30px; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 26px; color: white; font-weight: 300; }
        .logo span { font-weight: 600; }
        header h2 { color: white; font-size: 18px; font-weight: 400; margin: 0; }
        header a { color: white; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 4px; text-decoration: none; }
        header a:hover { background: rgba(255,255,255,0.3); color: white; }
        main { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        fieldset { background: white; border: none; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        legend { background: var(--tus-secondary); color: white; padding: 12px 20px; font-size: 15px; font-weight: 500; width: 100%; border-radius: 8px 8px 0 0; margin: 0; }
        fieldset table { width: 100%; }
        fieldset table td { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        fieldset table tr:last-child td { border-bottom: none; }
        select, input[type="number"] { border: 1px solid #ddd; border-radius: 4px; padding: 6px 10px; }
        select:focus, input:focus { outline: none; border-color: var(--tus-secondary); }
        .tipo-clase-select { background: #f8f9fa; font-weight: 500; }
        #sendButton { background: var(--tus-primary); color: white; border: none; padding: 12px 30px; font-size: 15px; border-radius: 6px; cursor: pointer; margin: 20px 0 40px; }
        #sendButton:hover { background: var(--tus-secondary); }
        i.material-icons { cursor: pointer; color: #999; }
        i.material-icons:hover { color: var(--tus-secondary); }
        #loading { padding: 50px; text-align: center; color: #666; }
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; }
        /* Select2 customization */
        .select2-container { width: 100% !important; }
        .select2-selection { min-height: 34px !important; border: 1px solid #ddd !important; border-radius: 4px !important; }
        .select2-selection__rendered { padding: 4px 8px !important; }
        .select2-search__field { border: 1px solid #ddd !important; border-radius: 4px !important; padding: 6px !important; }
        .select2-results__option--highlighted { background: var(--tus-secondary) !important; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">tu<span>Speaking</span></div>
        <h2>Course - Appointment Type</h2>
        <a href="https://aula.tuspeaking.com/app/moodle"><i class="fas fa-arrow-left"></i> Ir a Moodle</a>
    </div>
</header>
<main>
    <div id="data">
        <div id="loading"><img src="https://cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif" style="width:32px;"><br>Cargando cursos...</div>
    </div>
    <button id="sendButton"><i class="fas fa-save"></i> Guardar Cambios</button>
</main>
<script>
// Inicializar Select2 después de cargar los datos
$(document).on('ajaxComplete', function() {
    setTimeout(function() {
        if ($('.acuity-select').length > 0 && !$('.acuity-select').hasClass('select2-hidden-accessible')) {
            $('.acuity-select').select2({
                placeholder: 'Buscar...',
                allowClear: false,
                width: '100%'
            });
        }
    }, 1000);
});
</script>
</body>
</html>
