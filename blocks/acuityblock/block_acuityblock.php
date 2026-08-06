<?php
class block_acuityblock extends block_base {
    public function init() {
        $this->title = get_string('acuityblock', 'block_acuityblock');
    }
    // The PHP tag and the curly bracket for the class definition 
    // will only be closed after there is another function added in the next section.
	public function get_content() {
		if ($this->content !== null) {
		  return $this->content;
		}
		global $CFG;
		global $USER;
		global $PAGE;
		$userid = $USER->id;
		$courseid = $PAGE->course->id;
		$firstname = $USER->firstname;
		$lastname = $USER->lastname;
		$email = $USER->email;
		$phone = " " . $USER->phone1;
		try {
			$conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
			// set the PDO error mode to exception
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$conn->exec("set names utf8");
			/** Consulta Select del Acuity Type ID correspondiente al curso **/
				$sql = "SELECT acuityid FROM own_acuity_course WHERE courseid = $courseid";
				$stmt = $conn->prepare($sql); 
				$stmt->execute();
				$acuityid = ($stmt->fetch())['acuityid'];
			/** Fin Select **/
		}
		catch (PDOException $e) {
			$error .= "Error Base de Datos.<br>Sentencia SQL:<br>" . $sql . "<br>Error:<br>" . $e->getMessage() . "<br><br><br>";
		}
		finally {
			$conn = null;
		}
		if (empty($acuityid)){
			$acuityid = 7273758;
		}
		
		$this->content         =  new stdClass;
		$this->content->text   = "<script src='https://embed.acuityscheduling.com/embed/button/15680788.js' async=''></script>";
		
		/*Clases por reservar*/
		/*$conn2 = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
		// set the PDO error mode to exception
		$conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$conn2->exec("set names utf8");
		$sql_clases = "SELECT COUNT(own_acuity.id) as cuenta, own_acuity_course.classnmbr FROM own_acuity, own_acuity_course WHERE own_acuity_course.courseid = " + $courseid + " AND own_acuity.studentid = " + $userid + " AND own_acuity.courseid = " + $courseid + " AND (own_acuity.iscancelled = 'f' OR own_acuity.isteached = 't')";
		$stmt_clases = $conn2->prepare($sql_clases); 

		$stmt_clases->execute($stmt_clases);

		
		while($row = $stmt_clases->fetch()) {
			//echo $row['classnmbr'] . "<br/>";
			print_r($row);
		}*/
		
		
		
		//print_r($stmt_clases);
		//$stmt_clases->execute();
		//$classnmbr = ($stmt_clases->fetch())['classnmbr'];
		//$cuenta = ($stmt_clases->fetch())['cuenta'];
		
		//echo "----".$classnmbr."....".$cuenta."----";
		
		//$this->content->text  .= "<br><a href='https://tuspeaking.as.me/?field:5837866=$userid&firstName=$firstname&lastName=$lastname&email=$email&phone=$phone&owner=15680788&appointmentType=$acuityid' target='_blank' class='acuity-embed-button' style='background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;'>Reservar</a>";
		
		$this->content->text .= html_writer::empty_tag("br");
		
		if ($courseid == 2 || $courseid == 25 || $courseid == 56 || $courseid == 57 || $courseid == 58 || $courseid == 59 || $courseid == 157 || $courseid == 528 || $courseid == 529 || $courseid == 530 || $courseid == 531){
			
			$url = new moodle_url('../acuityrecursive.php', ['id' => $courseid]);
			$this->content->text .= html_writer::tag("a", "Reservar Clases", array("href" => $url, "target" => "_blank", "class" => "acuity-embed-button", "style" => "background: #00bcd4; color: #fff; padding: 8px 12px; margin-top: 5px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;"));
			
		} else {
			
			$url = new moodle_url('https://tuspeaking.as.me', array('field:5837866' => $userid, 'field:5915926' => $courseid, 'firstName' => $firstname, 'lastName' => $lastname, 'email' => $email, 'phone' => $phone, 'owner' => 15680788, 'appointmentType' => $acuityid));
			$this->content->text .= html_writer::tag("a", "Reservar", array("href" => $url, "target" => "_blank", "class" => "acuity-embed-button", "style" => "background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;"));
			
		}

		return $this->content;
	}

	function has_config() {return true;}
	
	public function specialization() {
		if (isset($this->config)) {
			if (empty($this->config->title)) {
				$this->title = get_string('defaulttitle', 'block_acuityblock');            
			} else {
				$this->title = $this->config->title;
			}
	 
			if (empty($this->config->text)) {
				$this->config->text = get_string('defaulttext', 'block_acuityblock');
			}    
		}
	}
}
?>