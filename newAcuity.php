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
		$userID = '15680788';
		$key = '7727321b66b8210424f1d4d984584693';
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
		$userID = '15680788';
		$key = '7727321b66b8210424f1d4d984584693';
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

/* ═══════════════════════════════════════════════════════════════════════════
 * ING-9 (07-ago-2026) — CICLO COMPLETO DE LA RESERVA
 *
 * El webhook de Acuity que apunta aqui es el catch-all "Scheduled or Updated":
 * recibe ALTAS, REPROGRAMACIONES y CANCELACIONES por la misma puerta.
 *
 * Hasta hoy este fichero solo sabia dar de alta: ante una reprogramacion volvia
 * a INSERTAR, dejando eventos de calendario y filas de own_acuity duplicados
 * (caso real 06-ago: la cita 1716436192 de Dolors Camacho se inserto 4 veces en
 * 26 segundos, 15 eventos para una sola clase). Y las cancelaciones ni se
 * miraban, pese a que Acuity envia `canceled` en la respuesta.
 *
 * Quien cubria las tres ramas era `modifyAndCreateAcuity.php`, perdido en el
 * borrado del 1-ago-2026 y sin copia en ningun backup ni en git.
 *
 * Orden de decision:
 *   1) cancelada          -> borrar eventos + marcar cancelada en las 2 tablas
 *   2) ya existe la cita  -> REPROGRAMACION: mover los eventos y actualizar filas
 *   3) no existe          -> ALTA: sigue el flujo de siempre (mas abajo)
 * ═══════════════════════════════════════════════════════════════════════════ */
$esCancelacion = !empty($appointment['canceled']);

$previaRows = askmysql("SELECT id, studenteventid, teachereventid, heventid, ceventid, geventid, modifiedtimes FROM own_acuity WHERE acuityid = $acuityID ORDER BY id DESC LIMIT 1");
$previa = (!empty($previaRows) && isset($previaRows[0])) ? $previaRows[0] : null;

if ($esCancelacion || $previa) {
	try {
		$connU = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
		$connU->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		/* Los 5 eventos de calendario de la reserva previa (alumno, profesor y cortesias) */
		$eventIds = array();
		if ($previa) {
			foreach (array('studenteventid','teachereventid','heventid','ceventid','geventid') as $campo) {
				if (!empty($previa[$campo]) && (int)$previa[$campo] > 0) {
					$eventIds[] = (int)$previa[$campo];
				}
			}
		}
		$listaEventos = implode(',', $eventIds);

		if ($esCancelacion) {
			/* ─── RAMA 1: CANCELACION ─────────────────────────────────────── */
			if ($listaEventos !== '') {
				$connU->exec("DELETE FROM mdl_event WHERE id IN ($listaEventos)");
			}
			$connU->exec("UPDATE own_acuity SET iscancelled = 't', lastmodified = NOW() WHERE acuityid = $acuityID");
			$connU->exec("UPDATE mdl_i3code_acuityZoom SET acuity_canceled = 1 WHERE acuityid = $acuityID");

		} else {
			/* ─── RAMA 2: REPROGRAMACION ──────────────────────────────────── */
			$nuevoTs = strtotime($appointment['datetime']);

			/* Se rehace la descripcion: lleva la fecha y la hora dentro */
			$calNameU = escapeshellcmd($appointment['type']) . " " . escapeshellcmd($appointment['calendar']);
			$locTxtU  = ($locationURL != "%location%")
				? "<br>URL: <a href=\'$locationURL\'  target=\'_blank\'>$locationURL</a>" : "";
			$calDescU = $appointment['type'] . " (" . $appointment['firstName'] . " " . $appointment['lastName'] . ") "
				. $appointment['date'] . " " . $appointment['time'] . " - " . $appointment['endTime'] . " "
				. escapeshellcmd($appointment['calendar']) . $locTxtU;

			/* Los botones se reconstruyen igual que en el alta, no se recortan de la
			 * descripcion anterior: si el texto no coincidiera, el alumno se quedaria
			 * sin el boton de Modificar/Cancelar. */
			$confirmTxtU = "<br><a href=\'$confirmPage\' target=\'_blank\' class=\'acuity-embed-button\' style=\'background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;\'>Modificar/Cancelar Sesi&oacute;n</a>";
			$feedbackU   = "<br><a href=\'https://aula.tuspeaking.com/formFeedback.php?acuityid=".$acuityID."\' target=\'_blank\' class=\'acuity-embed-button\' style=\'background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;\'>Feedback</a>";

			if ($nuevoTs) {
				/* Alumno: descripcion + boton Modificar/Cancelar */
				if (!empty($previa['studenteventid'])) {
					$connU->exec("UPDATE mdl_event SET timestart = $nuevoTs, name = " . $connU->quote($calNameU) . ",
						description = " . $connU->quote($calDescU . $confirmTxtU) . ", timemodified = $ourTime
						WHERE id = " . (int)$previa['studenteventid']);
				}
				/* Profesor: descripcion + boton Feedback */
				if (!empty($previa['teachereventid'])) {
					$connU->exec("UPDATE mdl_event SET timestart = $nuevoTs, name = " . $connU->quote($calNameU) . ",
						description = " . $connU->quote($calDescU . $feedbackU) . ", timemodified = $ourTime
						WHERE id = " . (int)$previa['teachereventid']);
				}
				/* Copias de cortesia (Hansel y, en reservas viejas, Carmen y Guillermo):
				 * descripcion base. Se mueven para que no queden a la hora antigua. */
				$otros = array();
				foreach (array('heventid','ceventid','geventid') as $campo) {
					if (!empty($previa[$campo]) && (int)$previa[$campo] > 0) { $otros[] = (int)$previa[$campo]; }
				}
				if ($otros) {
					$connU->exec("UPDATE mdl_event SET timestart = $nuevoTs, name = " . $connU->quote($calNameU) . ",
						description = " . $connU->quote($calDescU) . ", timemodified = $ourTime
						WHERE id IN (" . implode(',', $otros) . ")");
				}
			}

			$connU->exec("UPDATE own_acuity
				SET modifiedtimes = modifiedtimes + 1, lastmodified = NOW()
				WHERE acuityid = $acuityID");

			/* acuity_original_datetime solo se fija la PRIMERA vez que se mueve */
			$connU->exec("UPDATE mdl_i3code_acuityZoom SET
					acuity_original_datetime = COALESCE(acuity_original_datetime, acuity_datetime),
					acuity_datetime  = " . $connU->quote($appointment['datetime'] ?? '') . ",
					acuity_starttime = " . $connU->quote($appointment['time'] ?? '') . ",
					acuity_endtime   = " . $connU->quote($appointment['endTime'] ?? '') . ",
					acuity_location  = " . $connU->quote($appointment['location'] ?? '') . ",
					zoom_meetingid   = " . ((isset($locationID) && ctype_digit((string)$locationID)) ? (string)$locationID : "NULL") . ",
					acuity_rescheduled = 1,
					acuity_canceled  = 0
				WHERE acuityid = $acuityID");
		}

		$connU = null;
	}
	catch (PDOException $eCiclo) {
		$error .= "Error ING-9 (" . ($esCancelacion ? "cancelacion" : "reprogramacion") . ") acuityid $acuityID:<br>" . $eCiclo->getMessage() . "<br><br>";
		goto err;
	}

	/* Tratada. NO seguir al flujo de alta: eso duplicaria eventos y filas. */
	goto fin;
}
/* ═══ Fin ING-9 · a partir de aqui, ALTA NUEVA ═══════════════════════════ */

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
			// ELIMINADOS 07-ago-2026 (ING-7) — dos copias de cortesía a cuentas inactivas:
			//   · userid 2  — Carmen Lacabe: SUSPENDIDA (suspended=1), sin acceder desde feb-2026
			//   · userid 48 — Guillermo Bethencourt: BORRADO de Moodle en 2018 (deleted=1)
			// Cada reserva creaba un evento de calendario para ellos. El de Guillermo hacía
			// que el cron nocturno de recordatorios ABORTARA con "Usuario no válido", y todos
			// los eventos posteriores se quedaban SIN AVISO al alumno (roto desde el
			// 26-jul-2026, el cutover). 192 eventos futuros huérfanos limpiados.
			// Quedan: alumno, profesor y Hansel (14).
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
			// ING-7 (07-ago-2026): quitados `OR userid = 2` (Carmen, suspendida) y
			// `OR userid = 48` (Guillermo, borrado en 2018). Ya no se crean sus eventos,
			// así que buscarlos aquí no tendría sentido.
			$sql = "SELECT id FROM mdl_event WHERE (userid = $studentID OR userid = $teacherID OR userid = 14) AND timestart = " . strtotime($appointment['datetime']) . " AND timemodified = $ourTime";
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
			$sql = "INSERT INTO own_acuity (acuityid, courseid, studentid, studenteventid, teacherid, teachereventid, fundaeid, heventid, ceventid, geventid, lastmodified) VALUES ($acuityID, $courseID, $studentID, $userIDs[0], $teacherID, $userIDs[1], $fundaeid, $userIDs[2], NULL, NULL, NOW())";
		/* Fin SQL */
		/* Ejecutamos sentencia SQL */
			$conn->exec($sql);
	/** Fin insert **/

	/** ING-9 (07-ago-2026) — La clase debe VERSE en "Mis clases" desde que se reserva **/
	/*
	 * Antes esta fila la creaba solo la ingesta (`i3code_download_zoomdata.php`), que corre
	 * UNA VEZ AL DIA a las 04:05 UTC. Resultado: quien reservaba por la tarde no veia su
	 * clase hasta el dia siguiente, y ademas nacia con zoom_clasecompletada = 0 (el DEFAULT),
	 * que `misclases.php` pinta como "Sin datos" y SIN boton Conectar.
	 *
	 * `misclases.php` lee de esta tabla y exige `zoom_clasecompletada = 3` ("Agendada") para
	 * mostrar el boton. Aqui ya tenemos la URL y el Meeting ID, asi que los escribimos.
	 *
	 * Solo se rellenan los campos de la RESERVA. Los de asistencia (zoom_starttime,
	 * zoom_duration, zoom_participants...) los sigue poniendo la ingesta despues de la clase.
	 */
		$meetingIdSql = (isset($locationID) && ctype_digit((string)$locationID)) ? (string)$locationID : "NULL";

		$sqlIZ = "INSERT INTO mdl_i3code_acuityZoom
				(acuityid, courseid, studentid, teacherid,
				 acuity_firstname, acuity_lastname, acuity_phone, acuity_email,
				 acuity_datetime, acuity_starttime, acuity_endtime, acuity_duration,
				 acuity_type, acuity_location, zoom_meetingid,
				 zoom_clasecompletada, acuity_canceled, acuity_rescheduled)
			 VALUES ($acuityID, $courseID, $studentID, $teacherID, "
				. $conn->quote($appointment['firstName'] ?? '') . ", "
				. $conn->quote($appointment['lastName'] ?? '') . ", "
				. $conn->quote($appointment['phone'] ?? '') . ", "
				. $conn->quote($appointment['email'] ?? '') . ", "
				. $conn->quote($appointment['datetime'] ?? '') . ", "
				. $conn->quote($appointment['time'] ?? '') . ", "
				. $conn->quote($appointment['endTime'] ?? '') . ", "
				. (int)($appointment['duration'] ?? 0) . ", "
				. $conn->quote($appointment['type'] ?? '') . ", "
				. $conn->quote($appointment['location'] ?? '') . ", "
				. $meetingIdSql . ",
				 3, 0, 0)
			 ON DUPLICATE KEY UPDATE
				 acuity_datetime = VALUES(acuity_datetime),
				 acuity_location = VALUES(acuity_location),
				 zoom_meetingid  = VALUES(zoom_meetingid)";

		/*
		 * En try/catch propio: si esto fallara, la reserva NO debe romperse. El alumno ya
		 * tiene su evento de calendario y su fila en own_acuity, y la ingesta rellenaria el
		 * hueco esa misma noche. Es una mejora de visibilidad, no un paso critico.
		 */
		try {
			$conn->exec($sqlIZ);
		} catch (PDOException $eIZ) {
			$error .= "AVISO ING-9: no se pudo crear la fila en mdl_i3code_acuityZoom (acuityid $acuityID): " . $eIZ->getMessage() . "<br>";
		}
	/** Fin ING-9 **/
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