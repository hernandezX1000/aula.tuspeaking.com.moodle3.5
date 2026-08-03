<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Petici&oacute;n de cancelaci&oacute;n de clases</title>
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
        <div class="col-sm-6"><br /><br /><h2 style="color: rgb(85,85,85)"><strong>Petici&oacute;n cancelaci&oacute; de clases</strong></h2></div>
        <div class="col-sm-1"></div>
    </div>
    <hr />
    <div class="row">
    	<div class="col-sm-1"></div>
    	<div class="col-sm-10">
<?php
$empresa = $_POST["empresaDesapunta"];
$para = "soporte.tuspeaking@gmail.com";
$asunto = "Solicitud de cancelación de clases";
$nombre = $_POST["nombreDesapunta"];
$correo = $_POST["correoDesapunta"];
$profesor = $_POST["nombreProfeDesapunta"];
$hora = $_POST["horaDesapunta"];
$motivo = $_POST["motivoDesapunta"];
$header = 'From: Moodle aula.tuspeaking.com' . "\r\n" . 'X-Mailer: PHP/' . phpversion();

$message = "El trabajador con nombre " . $nombre . " con correo electrónico " . $correo . ", tenía reservada una clase con el profesor ". $profesor . ", el próximo: " . $hora . ". Ha solicitado cancelarla por el siguiente motivo: " . $motivo;
if (mail($para, $asunto, $message, $header)){
echo "<p style='color: rgb(85,85,85)'>Mensaje enviado correctamente</p><a class=\"btn btn-info\" href='javascript:window.history.go(-2);'>Volver al curso</a>";
} else {
echo "<p style='color: rgb(85,85,85)'>Algo ha fallado, int&eacute;ntelo de nuevo m&aacute;s tarde</p>";
}
?>
		</div>
		<div class="col-sm-1"></div>
	</div>
</body>
</html>