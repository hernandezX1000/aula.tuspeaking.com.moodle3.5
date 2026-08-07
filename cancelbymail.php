<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" href="https://aula.tuspeaking.com/app/moodle/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>

    <title>Cancelar clase por e-mail</title>
    <style>
        main {
            padding-top: 50px;
        }
        div.row:nth-of-type(2){
            background-color: #006064;
        }
        div.row:nth-of-type(2)>div.col-sm-10:nth-of-type(2){
            color: white;
        }
        .btn-navbar {
            font-size: 12px;
            text-align: center;
            background-color: #00b1c6;
            padding: 15px 20px;
            border-radius: 0px;
        }
        main div p {
            text-align: justify;
        }
        button.btn {
            background-color: #00bcd4;
            border-color: #00bcd4;
            border-top-color: #00a0b4;
            border-bottom-color: #00a0b4;
        }
        button.btn:hover {
            background-color: #00d3ed;
            border-color: #00d3ed;
            border-top-color: #00a0b4;
            border-bottom-color: #00a0b4;
        }
    </style>
</head>
<body>
<header>
    <div class="row">
        <div class="col-sm-1"></div>
        <div class="col-lg-4"><img src="./img/logo.png"></div>
        <div class="col-sm-6"><br /><br /><h2><b>Cancelar clase por e-mail</b></h2></div>
        <div class="col-sm-1"></div>
    </div>
    <div class="row">
        <div class="col-sm-1"></div>
        <div class="col-sm-10">
            <a class="btn btn-navbar">Cancelar Clase</a>
        </div>
        <div class="col-sm-1"></div>
    </div>
</header>
<main>
    <div class="row">
        <?
        require_once ("./config.php");
        require_once ("./acuityapi.php");
        require_once ("./mailsender.php");
        require_once ("./askddbb.php");
            if (!empty($_GET) && empty($_POST)){
                echo "<div class='col-sm-3'></div>";
                $acuityID = askmysql("SELECT acuityid FROM own_acuity WHERE studenteventid = {$_GET['eventid']}")[0]['acuityid'];
                $url = 'appointments/' . $acuityID;
                $acuityInfo = callAPI('appointments/' . $acuityID, '');
                $date = new DateTime($acuityInfo['datetime']);
				
				$fechaclase = $date->format('Y-m-d H:i:s');
				$d = new DateTime();
				//$fechahoy = $d->format('2021-05-07 10:00:00');
				$fechahoy = $d->format('Y-m-d H:i:s');
				
				$datetime1 = date_create($fechaclase);
				$datetime2 = date_create($fechahoy);
				
				$contador = date_diff($datetime1, $datetime2);
				//print_r($contador);
				$differenceFormat = '%h';
				$horasdiferencia = $contador->format($differenceFormat);
				
				
			

                echo "<div class='col-sm-6'>";
                    echo "<p>Usted se dispone a cancelar la clase: <b>{$acuityInfo['type']}</b> del calendario: <b>{$acuityInfo['calendar']}</b> a nombre de <b>{$acuityInfo['firstName']} {$acuityInfo['lastName']}</b> con correo <b>{$acuityInfo['email']}</b> y fecha <b>{$date->format('Y-m-d H:i:s')}</b>.</p>";
					
					if ($horasdiferencia < 12){
						//echo "No puede cancelar con menos de 12 horas";
						//echo "<p>Le informamos que al tratarse de una cancelaci&oacute;n con menos de 12 horas su clase ser&aacute; tomada como impartida.</p>";
						echo "<p>Le informamos que no puede cancelar esta clase ya que se intenta cancelarla con menos de 12 horas. Aunque no pueda acudir, su clase ser&aacute; tomada como impartida.</p>";
					} else {
						//echo "si";
						echo "<form action='cancelbymail.php' method='POST'>";
                        echo " <input type='hidden' id='acuityID' name='acuityID' value='{$acuityID}'>";
                        echo "<div class='form-group'>
                                <label for='comment'>Comentario:</label>
                                <textarea class='form-control' rows='5' id='comment' name='comment' placeholder='Su comentario aqu&iacute;'></textarea>
                            </div>";
                        echo "<button type='submit' class='btn btn-primary'>Cancelar Clase</button>";
						echo "</form>";
					}
                    
                    
					
					
					
                echo "</div>";
                echo "<div class='col-sm-3'></div>";
            } else if (!empty($_POST)){
                $acuityID = $_POST['acuityID'];
                $acuityInfo = callAPI('appointments/' . $acuityID, '');
                $date = new DateTime($acuityInfo['datetime']);
                $acuityCancel = putAPI('appointments/' . $acuityID . '/cancel?noEmail=true&admin=true', '');
                $insert = setMysql("UPDATE own_acuity SET isteached = 't' WHERE acuityid = {$acuityID}");
                echo "<script>console.log({$insert});</script>";
                if ($insert){
                    $mails = askmysql("SELECT mdl_user.email FROM mdl_user, mdl_event, own_acuity WHERE own_acuity.acuityid = {$acuityID} AND mdl_event.userid = mdl_user.id AND (own_acuity.studenteventid = mdl_event.id OR own_acuity.teachereventid = mdl_event.id OR own_acuity.heventid = mdl_event.id OR own_acuity.ceventid = mdl_event.id OR own_acuity.geventid = mdl_event.id)
");
                    $subject = "Clase {$acuityInfo['calendar']} {$date->format('Y-m-d H:i:s')} cancelada.";
                    $content = "<div><p>Le informamos que la clase  <b>{$acuityInfo['type']}</b> del calendario: <b>{$acuityInfo['calendar']}</b> a nombre de <b>{$acuityInfo['firstName']} {$acuityInfo['lastName']}</b> con correo <b>{$acuityInfo['email']}</b> y fecha <b>{$date->format('Y-m-d H:i:s')}</b> ha sido cancelada.</p><p>Lamentablemente al no realizar la cancelación con más de 12 horas su clase ha sido tomada como impartida.</p></div>";
                    if ($_POST['comment']){
                        $content .= "<div>Comentario del alumno:<br><div style='margin-left: 50px; padding: 20px 10px; background-color: whitesmoke;'>{$_POST['comment']}</div></div>";
                    }
                    $teacherContent = "<div>Le informamos de la cancelaci&oacute;n de la clase por parte del alumno (sin posibilidad de recuperaci&oacute;n) con la siguiente informaci&oacute;n</div>";
                    sendEmail($mails[0]['email'], $subject, $content);
                    for ($i = 1; $i < count($mails); $i++){
                        sendEmail($mails[$i]['email'], $subject, ($teacherContent . $content));
                    }
                    $formValues = "";
                    foreach ($acuityInfo['forms'] as $value){
                        if ($value['name'] == "Form interno Moodle"){
                            $formValues = $value['values'];
                            break;
                        }
                    }
                    $courseID = "";
                    foreach ($formValues as $value) {
                        if ($value['name'] == "CourseID"){
                            $courseID = $value['value'];
                        }
                    }
                    echo "<div class='col-sm-3'></div>";
                    echo "<div class='col-sm-6'>";
                        echo "<div>Su clase ha sido cancelada, se le ha enviado un correo de notificaci&oacute;n as&iacute; como a su profesor.</div>";
                        echo "<div><a href=\"./course/view.php?id={$courseID}\" class=\"acuity-embed-button\" style=\"background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;\">Volver al curso</a></div>";
                    echo "</div>";
                    echo "<div class='col-sm-3'></div>";
                }
            } else {
                header("Location: http://aula.tuspeaking.com/app/moodle");
                die();
            }
        ?>
    </div>
</main>
</body>
</html>