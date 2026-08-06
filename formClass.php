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
    <link rel="stylesheet" href="./own_StyleOwnTemplate.css">

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <title>Feedback</title>
    <style>
        p, ::placeholder {
            font-size: 14px;
        }

        main > form > div.row {
            padding-bottom: 20px;
        }

        main > form > div.row:nth-last-of-type(-n+2) {
            padding-bottom: 10px;
        }
        body>header>div.row>div.col-sm-8>p.btn {
            cursor: default;
            color: white;
            margin: 0px;
        }
        textarea {
            resize: both;
        }
        table, textarea {
            width: 100%;
        }
        tr:first-child {
            border-bottom: 1px solid #ccc;
        }
        td {
            padding: 5px 10px;
            color: grey;
            font-size: 13px;
        }
    </style>
</head>
<body>
<header>
    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm-3"><img src="./img/logo.png"></div>
        <div class="col-sm-5"><br/><br/>
            <h2><strong>Feedback de Clase</strong></h2></div>
        <div class="col-sm-2"></div>
    </div>
    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm-8">
            <p id="#" class="btn btn-navbar">Feedback</p>
        </div>
        <div class="col-sm-2"></div>
    </div>
</header>
<main>
<?php
if (empty($_POST)) {
require_once './config.php';
$acuityID = required_param('acuityid', PARAM_INT);
?>
    <form action="formClass.php" method="post">
        <input type="hidden" id="acuityID" name="acuityID" value="<? echo $acuityID; ?>">
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>&iquest;Recomendar&iacute;as este servicio a alg&uacute;n compa&ntilde;ero de trabajo o amigo?</p>
            </div>
            <div class="col-sm-6">
                <table>
                    <tr>
                        <td></td>
                        <td>1</td>
                        <td>2</td>
                        <td>3</td>
                        <td>4</td>
                        <td>5</td>
                        <td>6</td>
                        <td>7</td>
                        <td>8</td>
                        <td>9</td>
                        <td>10</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Nada probable</td>
                        <td><input type="radio" name="recommend" value="1" required></td>
                        <td><input type="radio" name="recommend" value="2"></td>
                        <td><input type="radio" name="recommend" value="3"></td>
                        <td><input type="radio" name="recommend" value="4"></td>
                        <td><input type="radio" name="recommend" value="5"></td>
                        <td><input type="radio" name="recommend" value="6"></td>
                        <td><input type="radio" name="recommend" value="7"></td>
                        <td><input type="radio" name="recommend" value="8"></td>
                        <td><input type="radio" name="recommend" value="9"></td>
                        <td><input type="radio" name="recommend" value="10"></td>
                        <td>Muy probable</td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>&iquest;C&oacute;mo podr&iacute;amos mejorar nuestros servicios?</p>
            </div>
            <div class="col-sm-6">
                <textarea id="improve" name="improve" rows="5" cols="52" minlength="5" placeholder="&iquest;C&oacute;mo podr&iacute;amos mejorar nuestros servicios?"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-4"></div>
            <div class="col-sm-6">
                <input type="button" class="btn btn-info" onclick="javascript:history.back(1)" value="Volver">
                <input type="submit" class="btn btn-info" id="submit" value="Enviar">
            </div>
            <div class="col-sm-2"></div>
        </div>
    </form>
<?
} else {
    require_once './config.php';
    require_once './askddbb.php';
    require_once './mailsender.php';
    $acuityID = $_POST['acuityID'];
    $student = askmysql("SELECT firstname, lastname, email FROM mdl_user, own_acuity WHERE mdl_user.id = own_acuity.studentid AND own_acuity.acuityid = $acuityID");
    $teacher = askmysql("SELECT firstname, lastname, email FROM mdl_user, own_acuity WHERE mdl_user.id = own_acuity.teacherid AND own_acuity.acuityid = $acuityID");
    $event = askmysql("SELECT email FROM mdl_user, own_acuity WHERE mdl_user.id = own_acuity.teacherid AND own_acuity.acuityid = $acuityID");
    $dateStamp = askmysql("SELECT timestart FROM mdl_event, own_acuity WHERE mdl_event.id = own_acuity.studenteventid AND own_acuity.acuityid = $acuityID")[0]['timestart'];
    $date = new DateTime();
    $date->setTimestamp($dateStamp);
    $recommend = $_POST['recommend'];
    $improve = str_replace("\n", "<br>", utf8_decode($_POST['improve']));

    $studentMail = "<br>Hola {$student[0]['firstname']},<br>Desde <a href='https://tuspeaking.com/' target='_blank'>tuSpeaking.com</a> te damos las gracias por valorar el servicio!<br><br>Un saludo.";
    sendEmail($student[0]['email'],'Agradecimiento de Feedback',$studentMail);

    $adminMail = "<div style='border: 3px solid #eeeeef; width: 490px;'>".
            "<div style='background-color: #eeeeef; padding: 10px 20px;'>".
                "<div>".
                    "<h3 style='color: #49d1d1; margin: 0;'>".utf8_decode('¿Qué tal lo estamos haciendo?')."</h3>".
                "</div>".
            "</div>".
            "<div>".
                "<div style='background-color: white;'>".
                    "<div style='width: 36%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>Fecha</p></div>".
                    "<div style='width: 5%; display: inline-block;'></div>".
                    "<div style='width: 56%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>{$date->format('d-m-Y H:i')}</p></div>".
                "</div>".
                "<div style='background-color: #f3f3f3'>".
                    "<div style='width: 36%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>Alumno</p></div>".
                    "<div style='width: 5%; display: inline-block;'></div>".
                    "<div style='width: 56%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode($student[0]['firstname'])." ".utf8_decode($student[0]['lastname'])." (<a href='mailto: ".$student[0]['email']."'>".$student[0]['email']."</a>)</p></div>".
                "</div>".
                "<div style='background-color: white;'>".
                    "<div style='width: 36%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>Profesor</p></div>".
                    "<div style='width: 5%; display: inline-block;'></div>".
                    "<div style='width: 56%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode($teacher[0]['firstname'])." ".utf8_decode($teacher[0]['lastname'])." (<a href='mailto: ".$teacher[0]['email']."'>".utf8_decode($teacher[0]['email'])."</a>)</p></div>".
                "</div>".
                "<div style='background-color: #f3f3f3'>".
                    "<div style='width: 36%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode('¿Recomendarías este servicio a algún compañero de trabajo o amigo?')."</p></div>".
                    "<div style='width: 5%; display: inline-block;'></div>".
                    "<div style='width: 56%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>{$recommend}</p></div>".
                "</div>".
                "<div style='background-color: white; display: none;'>".
                    "<div style='width: 36%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode('¿Cómo podríamos mejorar nuestros servicios?')."'</p></div>".
                    "<div style='width: 5%; display: inline-block;'></div>".
                    "<div style='width: 56%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode($improve)."</p></div>".
                "</div>".
                "<div style='background-color: white;'>".
                    "<div style='width: 36%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode('¿Cómo podríamos mejorar nuestros servicios?')."</p></div>".
                    "<div style='width: 5%; display: inline-block;'></div>".
                    "<div style='width: 56%; display: inline-block; padding: 3px; vertical-align: middle;'><p style='margin: 0px;'>".utf8_decode($improve)."</p></div>".
                "</div>".
            "</div>".
        "</div>";
    sendEmail('hansel@tuspeaking.com, carmen@tuspeaking.com', 'Agradecimiento de Feedback', $adminMail);
    echo "<div class='row'><div class='col-sm-2'></div><div class='col-sm-8'><h3>Gracias por su opini&oacute;n.</h3></div><div class='col-sm-2'></div></div>";
}
?>
</main>
</body>
</html>