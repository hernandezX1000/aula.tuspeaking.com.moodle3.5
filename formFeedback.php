<?php
require_once './config.php';
require_once './askddbb.php';
if (empty($_POST)) {
$acuityID = required_param('acuityid', PARAM_INT);
$event = askmysql("SELECT courseid, studentid FROM own_acuity WHERE acuityid = $acuityID");
$courseid = $event[0]['courseid'];
$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
    if ($admin->id == $USER->id) {
        $isadmin = true;
        break;
    }
}
if (!$isadmin) {
    $context = get_context_instance(CONTEXT_COURSE, $courseid, true);
    $roles = get_user_roles($context, $USER->id, true);
    $userLevel = $roles[array_keys($roles)[0]]->shortname;
    if ($userLevel != "manager" && $userLevel != "teacher" && $userLevel != "editingteacher") {
        header("Location: http://aula.tuspeaking.com/app/moodle");
        die();
    }
}
?><!doctype html>
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
    <title>Form</title>
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
        input[type='text'], textarea {
            width: 100%;
        }
    </style>
</head>
<body>
<header>
    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm-3"><img src="./img/logo.png"></div>
        <div class="col-sm-5"><br/><br/>
            <h2><strong>Feedback report</strong></h2></div>
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
    <form action="formFeedback.php" method="post">
        <input type="hidden" id="studentID" name="studentID" value="<? echo $event[0]['studentid']; ?>">
        <input type="hidden" id="teacherID" name="teacherID" value="<? echo $USER->id; ?>">
        <input type="hidden" id="acuityID" name="acuityID" value="<? echo $acuityID; ?>">
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>Conversation topic:</p>
            </div>
            <div class="col-sm-6">
                <input id="topic" name="topic" type="text" placeholder="Conversation topic." required="required" value="">
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>Topic related questions:</p>
            </div>
            <div class="col-sm-6">
                <textarea id="questions" name="questions" rows="5" cols="52" minlength="1" placeholder="What questions have been asked about the conversation topic?" required="required"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>Resources used:</p>
            </div>
            <div class="col-sm-6">
                <textarea id="resource" name="resource" rows="5" cols="52" minlength="1" placeholder="What resources have been used during the conversation?" required="required"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>Vocabulary and corrections:</p>
            </div>
            <div class="col-sm-6">
                <textarea id="corrections" name="corrections" rows="5" cols="52" minlength="1" placeholder="What corrections have been made during the conversation? What vocabulary has the student used during the conversation?" required="required"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>What you did well:</p>
            </div>
            <div class="col-sm-6">
                <textarea id="good" name="good" rows="5" cols="52" minlength="1" placeholder="In-class well-dones." required="required"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>What you need to improve:</p>
            </div>
            <div class="col-sm-6">
                <textarea id="bad" name="bad" rows="5" cols="52" minlength="1" placeholder="In-class errors." required="required"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>For next class:</p>
            </div>
            <div class="col-sm-6">
                <input id="next" name="next" type="text" placeholder="Next class topic." required="required" value="">
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-2">
                <p>Comments:</p>
            </div>
            <div class="col-sm-6">
                <textarea id="comments" name="comments" rows="5" cols="52" minlength="1" placeholder="<? echo "Some words for the student?\r\nDefault text: 'Thank you XXX. It was a pleasure to speak with you.'";?>"></textarea>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <div class="row">
            <div class="col-sm-4"></div>
            <div class="col-sm-6">
                <input type="button" class="btn btn-info" onclick="javascript:history.back(1)" value="Back">
                <input type="submit" class="btn btn-info" id="submit" value="Send">
            </div>
            <div class="col-sm-2"></div>
        </div>
    </form>
</main>
</body>
</html>
<?
} else {
    $student = askmysql("SELECT firstname, lastname, email FROM mdl_user WHERE id = {$_POST['studentID']}");
    $teacher = askmysql("SELECT firstname, lastname, email FROM mdl_user WHERE id = {$_POST['teacherID']}");
    $acuityID = $_POST['acuityID'];
    $topic = str_replace("\n", "<br>", utf8_decode($_POST['topic']));
    $questions = str_replace("\n", "<br>", utf8_decode($_POST['questions']));
    $resource = str_replace("\n", "<br>", utf8_decode($_POST['resource']));
    $corrections = str_replace("\n", "<br>", utf8_decode($_POST['corrections']));
    $good = str_replace("\n", "<br>", utf8_decode($_POST['good']));
    $bad = str_replace("\n", "<br>", utf8_decode($_POST['bad']));
    $next = str_replace("\n", "<br>", utf8_decode($_POST['next']));
    $comments = str_replace("\n", "<br>", utf8_decode($_POST['comments']));
    if ($comments == "")
        $comments = "Thank you {$student[0]['firstname']}. It was a pleasure to speak with you.";

    $path = '/home/aulatuspeaking/www/app/moodle/pdf/';
    $now = new DateTime();
    $fileName = $student[0]['firstname'] . "-" . $student[0]['lastname'] . "-" . $now->format('d-m-Y') . ".pdf";
    $now = $now->getTimestamp();
    setlocale(LC_ALL, "es_ES");
    $now = ucfirst(strftime('%A', $now)) . ", " . strftime('%e', $now) . " " . strftime('%B', $now) . " " . strftime('%G', $now);

    $pdfHTML = "<p><b>Conversation topic / ".utf8_decode('Tema de conversación')."</b><br>{$topic}</p>".
        "<p><b>Topic related questions / Preguntas relacionadas con el tema</b><br>{$questions}</p>".
        "<p><b>Resources used / Recursos utilizados</b><br>{$resource}</p>".
        "<p><b>Vocabulary and corrections / Correcciones y vocabulario</b><br>{$corrections}</p>".
        "<p><b>What you did well / ".utf8_decode('Qué has hecho bien')."</b><br>{$good}</p>".
        "<p><b>Where you need to improve / ".utf8_decode("Qué has hecho mal")."</b><br>{$bad}</p>".
        "<p><b>For next class / ". utf8_decode("Tema para la próxima clase")."</b><br>{$next}</p>".
        "<p><b>Teacher comments / Comentarios del profesor</b><br>{$comments}</p>";

    require_once './fpdf/fpdfHTML.php';

    // Creación del objeto de la clase heredada
    $pdf = new PDF_HTML();
    //$pdf->setMargins(5, 1);
    $pdf->setTopMargin(4);
    $pdf->SetTitle('Reporte de Feedback');
    $pdf->setName( utf8_decode($student[0]['firstname'] . " " . $student[0]['lastname']));
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial','',20);
    $pdf->Cell(0, 10, "Reporte de Feedback", 0, 0, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial','',12);
    $pdf->WriteHTML($pdfHTML);
//    $pdf->Output();
    $pdf->Output($path . $fileName, 'F');
    require_once './mailsender.php';
    echo sendEmail($student[0]['email'], 'Feedback de '.$student[0]["firstname"]." ".$student[0]['lastname'] .".", 'Contenido del mensaje del Feedback. ', 'hansel@tuspeaking.com,carmen@tuspeaking.com, guillermo@tuspeaking.com',$path . $fileName);
//    unlink($path . $fileName);
    rename($path.$fileName, $path.$acuityID.".pdf");

    $events = askmysql("SELECT studenteventid, teachereventid, heventid, ceventid, geventid FROM own_acuity WHERE acuityid = $acuityID");
    $teacherDesc = askmysql("SELECT description FROM mdl_event WHERE id = {$events[0]['teachereventid']}");
    $teacherDesc = explode("<br>", $teacherDesc[0]['description']);
    $viewPDF = "<br><div style='margin-top: 10px;'><a href='/app/moodle/pdf/{$acuityID}.pdf' target='_blank' class='acuity-embed-button' style='background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;'>View PDF</a></div>";
    $teacherDesc = $teacherDesc[0] . "<br>" . $teacherDesc[1] . $viewPDF;

   setMysql("UPDATE mdl_event SET description = \"{$teacherDesc}\" WHERE id = {$events[0]['teachereventid']};");
    setMysql("UPDATE mdl_event SET description = \"". askmysql("SELECT description FROM mdl_event WHERE id = {$events[0]['studenteventid']}")[0]['description'] . $viewPDF ."\" WHERE id = {$events[0]['studenteventid']};");
    setMysql("UPDATE mdl_event SET description = \"". askmysql("SELECT description FROM mdl_event WHERE id = {$events[0]['heventid']}")[0]['description'] . $viewPDF ."\" WHERE id = {$events[0]['heventid']};");
    setMysql("UPDATE mdl_event SET description = \"". askmysql("SELECT description FROM mdl_event WHERE id = {$events[0]['ceventid']}")[0]['description'] . $viewPDF ."\" WHERE id = {$events[0]['ceventid']};");
    setMysql("UPDATE mdl_event SET description = \"". askmysql("SELECT description FROM mdl_event WHERE id = {$events[0]['geventid']}")[0]['description'] . $viewPDF ."\" WHERE id = {$events[0]['geventid']};");
}
?>