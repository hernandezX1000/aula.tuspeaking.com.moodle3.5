<?php
//si948
$datosver = $_GET['veradm'];
//Calculamos las horas que faltan para que no se pueda cancelar o modificar
				$strStart = date("Y-m-d HH:mm:ss");
				$strEnd   = "2021-04-30 12:00:00";
				
				$fecha1 = new DateTime('2016-11-29 03:55:06');//fecha inicial
$fecha2 = new DateTime('2016-11-31 11:55:06');//fecha de cierre
				
				//$intervalo = $fecha1->diff($fecha2);
				
				//echo $intervalo->format('%R%a días');//00 años 0 meses 0 días 08 horas 0 minutos 0 segundos


				
require('config.php');
global $CFG;
require_once($CFG->dirroot.'/secrets.php');			//Credenciales externalizadas (fuera de git)
require_once($CFG->dirroot.'/calendar/lib.php');		//Require para Moodle Calendar API
require_once "./askddbb.php";
require_once "./own_ZoomAPI.php";
$error = "";

if (!empty($_POST)){
	$acuityID = $_POST['id'];
} else if (!empty($_GET)){
	$acuityID = $_GET['id'];
} else {
	$error .= "Error en m&eacute;todo POST.<br><br><br>";
	goto err;
}

/***** Peticion a Acuity *****/
	/**** Credenciales ****/
		$userID = ACUITY_USER_ID;
		$key = ACUITY_API_KEY;
	/**** Fin credenciales ****/
	/*** URL para peticion por ID de appointment ***/
		$url = 'https://acuityscheduling.com/api/v1/appointments/' . $acuityID;
	/*** Fin URL ***/
	/*** Obtenemos la respuesta de la API ***/
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, "$userID:$key");
		$result = curl_exec($ch);
		curl_close($ch);
	/*** Fin respuesta API ***/
	/*** Transformamos el json en un array ***/
		$appointment = json_decode($result, true);
		
		
		
	if ($datosver == "si948"){
		echo "<hr />";
		print_r($appointment);
		echo "<hr />";
	}
				
				
		
	/*** Fin json ***/
	/** Recogemos los valores de las variables que le hemos pasado a Acuity desde Moodle ***/
		foreach ($appointment['forms'] as $key=>$value){
			// Cambiar el texto por el del Intake Form Questions que hayamos creado
			if ($value['name'] == "Form interno Moodle"){
				foreach($value['values'] as $k=>$v){
					// Añadir mas else if si metemos mas campos al Intake
					if($v['fieldID'] == 5837866){
						$studentID = $v['value'];
					} else if ($v['fieldID'] == 5915926){
						$courseID = $v['value'];
					}
				}
				break;
			}
		}

		if ($studentID == ""){
			goto fin;
		}
		
	if ($datosver == "si948"){
	echo "<hr />".$datosver."--3-<hr />";
}	
			
		$locationURL = explode(" ", $appointment['location'])[1];
		$locationID = explode(" ", $appointment['location'])[4];
		$locationID = str_replace("-","", $locationID);
		$confirmPage = $appointment['confirmationPage'];
		$calendarID = $appointment['calendarID'];
	/** Fin valores Moodle ***/
/***** Segunda llamada a API de Acuity *****/
	/**** Credenciales ****/
		$userID = ACUITY_USER_ID;
		$key = ACUITY_API_KEY;
	/**** Fin credenciales ****/
	/*** URL para peticion de calendarios ***/
		$url = 'https://acuityscheduling.com/api/v1/calendars';
	/*** Fin URL ***/
	/*** Obtejenos la respuesta de la API ***/
		$ce = curl_init();
		curl_setopt($ce, CURLOPT_URL, $url);
		curl_setopt($ce, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ce, CURLOPT_USERPWD, "$userID:$key");
		$res = curl_exec($ce);
		curl_close($ce);
	/*** Fin respuesta API ***/
	/*** Transformamos el json en array ***/
		$calendars = json_decode($res, true);
		
if ($datosver == "si948"){
	echo "<hr />";
	print_r($calendars); 
	echo "<hr />----------".$calendarID."<hr />";
}	
		
	/*** Fin json ***/
	/** Recogemos los valores del calendario que nos interesa $calendarID ***/
		foreach ($calendars as $key=>$value) {
			if($value['id'] == $calendarID){
				$mails = explode(",", $value['email']);
				$teachermail = trim($mails[0]);
				break;
			}
		}
if ($datosver == "si948"){
	echo "<hr />-mail acuity---".$teachermail."<hr />";
}		
		
	/** Fin valores calendario ***/
	/** Filtramos los correos ***/
		if($teachermail == "alisonkilkenny@gmail.com"){
			$teachermail = "alison@live.tuspeaking.com";
		} else if ($teachermail == "mark.tobias22120484@gmail.com"){
			$teachermail = "mark@live.tuspeaking.com";
		//} else if ($teachermail == "tjdclarke@gmail.com"){
		} else if ($teachermail == "theo@tuspeaking.com"){
			$teachermail = "theo@live.tuspeaking.com";
		} else if ($teachermail == "unienglishteacher42@gmail.com"){
			$teachermail = "annie@live.tuspeaking.com";
		}
//███████████████████████████████████████████████████████████████████████████QUITAR███████████████████████████████████████████████████████████████████████████
		else if ($teachermail == "hfernandez@live.tuspeaking.com"){
			$teachermail = "carmen@tuspeaking.com";
		}
	/** Fin filtrado correos ***/
/***** Fin peticion a Acuity *****/
$ourTime = time();

if ($datosver == "si948"){
	echo "<hr />-w---|".trim($teachermail)."|||<hr />";
}

try {
	$conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
	// set the PDO error mode to exception
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	/** Consulta Select del id del profesor **/
		/* Generamos sentencia SQL */
			$sql = "SELECT id FROM mdl_user WHERE email = '$teachermail'";
if ($datosver == "si948"){
	echo "<hr />-w---".$sql."<hr />";
}
		/* Fin SQL */
		/* Ejecutamos sentencia SQL */
			$stmt = $conn->prepare($sql); 
			$stmt->execute();
			$teacherID = ($stmt->fetch())['id'];
		/* Fin ejecucion */
	/** Fin select **/
	/** Consulta Insert en los calendarios de Moodle **/
		/* Generamos sentencia SQL */
			$calName = escapeshellcmd($appointment['type']) . " " . escapeshellcmd($appointment['calendar']);
			if ($locationURL != "%location%"){
				$locTxt = "<br>URL: <a href=\'$locationURL\'  target=\'_blank\'>$locationURL</a>";					
			} else {
				$locTxt = "";
			}
			$confirmTxt = "<br><a href=\'$confirmPage\' target=\'_blank\' class=\'acuity-embed-button\' style=\'background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;\'>Modificar/Cancelar Sesi&oacute;n</a>";
			$calDesc = $appointment['type'] . " (" . $appointment['firstName'] . " " . $appointment['lastName'] . ") " . $appointment['date'] . " " . $appointment['time'] . " - " . $appointment['endTime'] . " " . escapeshellcmd($appointment['calendar']) . $locTxt;
			$feedback = "<br><a href=\'https://aula.tuspeaking.com/app/moodle/formFeedback.php?acuityid=".$acuityID."\' target=\'_blank\' class=\'acuity-embed-button\' style=\'background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;\'>Feedback</a>";
			$sql = "INSERT INTO mdl_event (name, description, format, categoryid, courseid, groupid, userid, repeatid, modulename, instance, type, eventtype, timestart, timeduration, visible, uuid, sequence, timemodified)";
			$sql .=	" VALUES ('$calName', '" . $calDesc . $confirmTxt . "', 1, 0, 0, 0, $studentID, 0, 0, 0, 0, 'user', " . strtotime($appointment['datetime']) . ", 0, 1, '', 1, $ourTime)"; // ID Estudiante
			$sql .= ", ('$calName', '".$calDesc.$feedback."', 1, 0, 0, 0, $teacherID, 0, 0, 0, 0, 'user', " . strtotime($appointment['datetime']) . ", 0, 1, '', 1, $ourTime)"; // ID Profesor
			$sql .= ", ('$calName', '$calDesc', 1, 0, 0, 0, 14, 0, 0, 0, 0, 'user', " . strtotime($appointment['datetime']) . ", 0, 1, '', 1, $ourTime)"; // ID de Hansel
			$sql .= ", ('$calName', '$calDesc', 1, 0, 0, 0, 2, 0, 0, 0, 0, 'user', " . strtotime($appointment['datetime']) . ", 0, 1, '', 1, $ourTime)";  // ID de Carmen
			$sql .= ", ('$calName', '$calDesc', 1, 0, 0, 0, 48, 0, 0, 0, 0, 'user', " . strtotime($appointment['datetime']) . ", 0, 1, '', 1, $ourTime)"; // ID de Guillermo
		/* Fin SQL */
		/* Ejecutamos sentencia SQL */
			$conn->exec($sql);
	/** Fin insert **/
}
catch (PDOException $e) {
	$error .= "Error Base de Datos.<br>userID $studentID<br>teachermail $teachermail<br>teacherID $teacherID<br>Sentencia SQL:<br>" . $sql . "<br>Error:<br>" . $e->getMessage() . "<br><br><br>";
	goto err;
}
finally {
	$conn = null;
}	
try {
	$conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
	// set the PDO error mode to exception
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	/** Consulta Select para obtener los ID de los eventos de Moodle **/
		/* Generamos sentencia SQL */
			$sql = "SELECT id FROM mdl_event WHERE (userid = $studentID OR userid = $teacherID OR userid = 14 OR userid = 2 OR userid = 48) AND timestart = " . strtotime($appointment['datetime']) . " AND timemodified = $ourTime";
		/* Fin SQL */
		/* Ejecutamos sentencia SQL */
			$stmt = $conn->prepare($sql); 
			$stmt->execute();
			$result = $stmt->fetchall();
			$userIDs = array();
			foreach ($result as $k=>$v){
				$userIDs[] = $v['id'];
			}
	/** Fin select **/
	/** Consulta si es FUNDAE o no */
	$fundaeid = askmysql("SELECT isfundae FROM own_acuity_course WHERE courseid = $courseID");
	$fundaeid = $fundaeid[0]['isfundae'];
	if ($fundaeid == "f"){
	    $fundaeid = 'null';
    } else if ($fundaeid == "t"){
	    $fundaeid = 161;
        // Grabacion automatica en Zoom ELIMINADA (ago-2026): patchZoom() usaba
        // generateTokenOAuth() (JWT deprecado, funcion inexistente tras la migracion) y
        // rompia toda reserva FUNDAE con "Call to undefined function". No se necesita.
    }
	/** Consulta Insert para almacenar el evento de Acuity **/
		/* Generamos sentencia SQL */
			$sql = "INSERT INTO own_acuity (acuityid, courseid, studentid, studenteventid, teacherid, teachereventid, fundaeid, heventid, ceventid, geventid, lastmodified) VALUES ($acuityID, $courseID, $studentID, $userIDs[0], $teacherID, $userIDs[1], $fundaeid, $userIDs[2], $userIDs[3], $userIDs[4], NOW())";
		/* Fin SQL */
		/* Ejecutamos sentencia SQL */
			$conn->exec($sql);
	/** Fin insert **/
}
catch (PDOException $e) {
	$error .= "Error Base de Datos.<br>Sentencia SQL:<br>" . $sql . "<br>Error:<br>" . $e->getMessage() . "<br><br><br>";
	goto err;
}
finally {
	$conn = null;
}

err:
if ($error != ""){
	if ($datosver == "si948"){
		echo "<hr />hola. 2".$error."--<hr />-".$acuityID."----".$studentID."<hr />";
	}
	
	$recipient = "soporte.tuspeaking@gmail.com";
	// Set the email subject.
	$subject = "Error Acuity. New Event.";

	// Build the email content.
	$email_content = "Error en Appointment con ID: " . $acuityID . ".<br>Para intentar corregir el error pulse <a href='https://aula.tuspeaking.com/app/moodle/newAcuity.php?id=" . $acuityID . "'>aqu&iacute;</a>.<br><br>" . $error;

	// Build the email headers.
	$email_headers = 'MIME-Version: 1.0' . "\r\n";
	$email_headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

	// Send the email.
	if (mail($recipient, $subject, $email_content, $email_headers)) {
		// Set a 200 (okay) response code.
		http_response_code(200);
		echo "Su mensaje ha sido enviado, le contestaremos tan pronto como nos sea posible.";
	} else {
		// Set a 500 (internal server error) response code.
		http_response_code(500);
		echo "¡Ups! Algo ha ido mal y su mensaje no ha sido enviado, vuelva a intentarlo pasados unos instantes.<br>Gracias.";
	}
}
fin:
?>