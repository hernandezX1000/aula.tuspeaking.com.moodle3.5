<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>
    	<?php
    		$tipo = $_POST["tipo"];
			if ($tipo == "cambio") {echo "Petici&oacute;n de cambio de profesor";
			} else {echo "Soporte t&eacute;cnico para moodle";};
    	?>	
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
</head>
<body>
	<div class="row">
        <div class="col-sm-1"></div>
        <div class="col-lg-4"><img src="./img/logo.png"></div>
        <div class="col-sm-6"><br /><br /><h2 style="color: rgb(85,85,85)"><strong>
        	<?php
    			$tipo = $_POST["tipo"];
				if ($tipo == "cambio") {echo "Petici&oacute;n de cambio de profesor";
				} else {echo "Soporte t&eacute;cnico para moodle";};
    		?>	
        </strong></h2></div>
        <div class="col-sm-1"></div>
    </div>
    <hr />
    <div class="row">
    	<div class="col-sm-1"></div>
    	<div class="col-sm-10">
<?php
$para = "soporte@tuspeaking.com";
$tipo = $_POST["tipo"];
if ($tipo == "cambio") {$asunto = "Peticion de cambio de profesor";
} else {$asunto = "Soporte técnico para moodle";};
$nombre = $_POST["nombre"];
$correo = $_POST["correo"];
$motivo = $_POST["motivo"];
$header = 'From: Moodle aula.tuspeaking.com' . "\r\n" . 'Reply-To: tuspeaking@tuspeaking.com' . "\r\n" . 'X-Mailer: PHP/' . phpversion();

$message = "De " . $nombre . "\n". $correo . "\nRazón: \n" . $motivo;
if (mail($para, $asunto, $message, $header)){
echo "<p style='color: rgb(85,85,85)'>Mensaje enviado correctamente</p><a href='javascript:window.history.go(-2);'>Volver</a>";
} else {
echo "<p style='color: rgb(85,85,85)'>Algo ha fallado, int&eacute;ntelo de nuevo m&aacute;s tarde</p>";
}
?>
		</div>
		<div class="col-sm-1"></div>
	</div>
</body>
</html>