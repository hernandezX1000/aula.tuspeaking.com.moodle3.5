<?php
require('./config.php');
require('./acuityapi.php');
require('./askddbb.php');
$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
global $PAGE;
global $CFG;
$courseid = $PAGE->course->id;
$userid = $USER->id;
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
	<link rel="stylesheet" href="./acuityrecursive.css">
	
	<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
	<script src="./acuityrecursive.js"></script>
	<script src="./timezones.full.min.js"></script>
	
	<title>Reservar clase recurrente</title>
	<style>
		.no-close .ui-dialog-titlebar-close {
			display: none;
		}
		.noTitleStuff .ui-dialog-titlebar {
			display:none;
		}
	</style>
</head>
<body>	
	<header>
<?
$sql = "SELECT own_acuity_course.acuityid, own_acuitytypes.acuitytype FROM own_acuity_course, own_acuitytypes WHERE own_acuity_course.courseid = $courseid AND own_acuity_course.acuityid = own_acuitytypes.acuityid";
$result = askmysql($sql);
$acuityid = $result[0]['acuityid'];
$acuitytype = $result[0]['acuitytype'];
echo "<h4 class='center'>" . explode("(", $acuitytype)[0] . "<span>(" . explode("(", $acuitytype)[1] . "</span></h4>";
?>
	</header>
	<main>
		<div id="profesores">
			<h5>Elige profesor:</h5>
<?
	echo "<div class='teacher-group' data-value='" . $acuityid . "'>";
?>
				<div class="teacher" data-value="any">Cualquiera disponible</div>
<?
/* Obtenemos los ID de los calendarios de Acuity correspondientes a Acuity Type del curso de Moodle en el que estamos */
	$appointments = callAPI('appointment-types', '');
	foreach($appointments as $key=>$val){
		if ($val['id'] == $acuityid){
			$calendarIDs = $val['calendarIDs'];
		}
	}
	
/* Obtenemos la informacion de los calendarios de Acuity */
	$calendars = callAPI('calendars', '');
	foreach ($calendars as $key=>$val){
		if (in_array($val['id'], $calendarIDs)){
			echo "<div class='teacher' data-value='" . $val['id'] . "'>" . $val['name'] . "</div>";
		} 
	}
?>
			</div>
		</div>
		<div id="dayhour" class="hide">
			<div class="center">
				<select id="seltimezone"></select>
			</div>
			<div id="otherdays">
				<div id='lessdays' data-value=''><span class='hide'><i class='material-icons'>keyboard_arrow_left</i>D&iacute;as anteriores</span></div>
				<div id='moredays' data-value=''><span>D&iacute;as posteriores<i class='material-icons'>keyboard_arrow_right</i></span></div>
			</div>
<?
	echo "<input type='hidden' id='extrainfo' data-value='{\"firstName\": \"$USER->firstname\", \"lastName\": \"$USER->lastname\", \"email\": \"$USER->email\", \"userID\": $USER->id, \"courseID\": $courseid}'>";
?>
			<div id="writedays"></div>
		</div>
		<div id="repetitions" class="hide">
		</div>
		<div id="confirmdays" class="hide">
		</div>
	</main>
	<div id="editday" title="Editar d&iacute;a" style="text-align: center; padding-top:20px;">
		<select id='editedday'><option>1</option><option>2</option></select>&nbsp;
		<select id='editeddayhour'></select>&nbsp;
		<button type="button" id="saveeditedhour">Seleccionar hora</button>
	</div>
	<div id="loading" style="heigth: 100px;">
		<div id="progresscircle" class="center">
		<div class="showbox">
		<div class="loader">
			<svg class="circular" viewBox="25 25 50 50">
				<circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="4" stroke-miterlimit="10"/>
			</svg>
			<p>CARGANDO...</p>
		</div>
		</div>		
		</div>
	</div>
	<div id="mssg">
	</div>
</body>
</html>