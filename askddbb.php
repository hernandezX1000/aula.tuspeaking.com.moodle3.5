<?php
//require('./config.php');
function askmysql($sql){
	global $CFG;
	try {
		$conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
		// set the PDO error mode to exception
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$conn->exec("set names utf8");
		$stmt = $conn->prepare($sql); 
		$stmt->execute();
		$result = $stmt->fetchAll();
	}
	catch (PDOException $e) {
		$result = "Error Base de Datos.<br>Sentencia SQL:<br>" . $sql . "<br>Error:<br>" . $e->getMessage();
	}
	finally {
		$conn = null;
		return $result;
	}
}
function setMysql($sql){
    global $CFG;
    try {
        $conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
        $conn->exec($sql);
        $result = true;
    }
    catch (PDOException $e) {
        $sql = str_replace("<", "&lt", $sql);
        $sql = str_replace(">", "&gt", $sql);
        $result = "Error Base de Datos.<br>Sentencia SQL:<br>" . $sql . "<br>Error:<br>" . $e->getMessage();
    }
    finally {
        $conn = null;
        return $result;
    }
}

// SEC-8 (01-09-2026) - Este endpoint ejecutaba SQL recibido por POST sin
// comprobar sesion ni permisos. Dos guardas:
//   1) Solo se ejecuta cuando se llama DIRECTAMENTE a askddbb.php. Si el
//      fichero se incluye desde otra pagina (newAcuity.php, cancelbymail.php,
//      formClass.php, reminder.php...), este bloque queda inerte y esas
//      paginas siguen usando askmysql()/setMysql() como siempre.
//   2) Sesion de Moodle obligatoria; escritura (type=set) solo para admins.
//      type=set solo lo usa own_CourseAcuity.js, que vive en courseacuity.php
//      (pagina de admin). own_PrintZoom.js y acuityrecursive.js solo usan
//      type=ask y siguen funcionando para profesores y alumnos.
if (isset($_POST['sql']) && basename($_SERVER['SCRIPT_FILENAME']) === 'askddbb.php') {
	require('./config.php');
	require_login();

	$sql = $_POST['sql'];
	if ($_POST['type'] == "ask") {
		echo json_encode(askmysql($sql));
	} else if ($_POST['type'] == "set") {
		if (!is_siteadmin()) {
			http_response_code(403);
			echo json_encode(false);
			exit;
		}
		echo json_encode(setMysql($sql));
	}
}
