<?php
require_once './config.php';
$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
global $PAGE;
global $CFG;
$courseid = $PAGE->course->id;
$admins = get_admins();
$isadmin = false;
foreach($admins as $admin){
    if ($admin->id == $USER->id){
        $isadmin = true;
        break;
    }
}
if (!$isadmin){
    $context = get_context_instance(CONTEXT_COURSE, $courseid, true);
    $roles = get_user_roles($context, $USER->id, true);
    $userLevel = $roles[array_keys($roles)[0]]->shortname;
} else {
    $userLevel = "admin";
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" href="https://aula.tuspeaking.com/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="./own_StyleOwnTemplate.css">

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="./own_PrintZoom.js"></script>
</head>
<body>
<main>
    <? echo "<form><input type='hidden' id='hiddenUserID' value=' {$USER->id}'><input type='hidden' id='hiddenUserLevel' value='{$userLevel}'><input type='hidden' id='hiddenCourseID' value='{$courseid}'></form>"; ?>
    <div class="row">
        <div class="col-sm-2"></div>
        <div id="content" class="col-sm-8 trackAccordion"></div>
        <div class="col-sm-2"></div>
    </div>
    <div id="loading">
        <div id="progressCircle" class="center">
            <div class="showBox">
                <div class="loader">
                    <svg class="circular" viewBox="25 25 50 50">
                        <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="4" stroke-miterlimit="10"/>
                    </svg>
                    <p>CARGANDO...</p>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>