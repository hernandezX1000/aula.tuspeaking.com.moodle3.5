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

if(isset($_POST['sql'])) {
	require('./config.php');
	$sql = $_POST['sql'];
	if ($_POST['type'] == "ask") {
        echo json_encode(askmysql($sql));
    } else if ($_POST['type'] == "set"){
	    echo json_encode(setMysql($sql));
    }
}