<?php
require ('./config.php');
require ('./askddbb.php');
if (!empty($_GET))
{
	$user = $_GET["user"];
	$pass = $_GET["pass"];
	$respuesta = askmysql('SELECT count(`username`) as cuenta FROM `mdl_user` WHERE `username` = "'.$user.'"');
	if ($respuesta[0]['cuenta'] >=1)
	{
	$pass2 = askmysql('SELECT `password` FROM `mdl_user` WHERE `username` = "'.$user.'"');
	if (password_verify($pass, $pass2[0]['password'])) {
    	echo "true";
		} else {
    	echo "false";
		}
	}
	else
	{
		echo "false";
	}
}
else 
{
	echo "false";
}

?>