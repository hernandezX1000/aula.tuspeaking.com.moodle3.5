<?php
/*define('CLI_SCRIPT', true);
require_once '/home/aulatuspeaking/www/app/moodle/config.php';
require_once('/home/aulatuspeaking/www/app/moodle/askddbb.php');
require_once('/home/aulatuspeaking/www/app/moodle/mailsender.php');*/
require_once './config.php';
require_once './askddbb.php';
require_once './mailsender.php';
global $CFG;
//$now = new DateTime();
//$now->sub(new DateInterval('P14DT3H'));
//echo $now->getTimestamp() . "<br><br><br><br>";

$days3 = new DateTime();
$days3->sub(new DateInterval('P3DT0H'));
$days3 = $days3->getTimestamp();
$days4 = new DateTime();
$days4->sub(new DateInterval('P4DT0H'));
$days4 = $days4->getTimestamp();
$days10 = new DateTime();
$days10->sub(new DateInterval('P10DT0H'));
$days10 = $days10->getTimestamp();
$days11 = new DateTime();
$days11->sub(new DateInterval('P11DT0H'));
$days11 = $days11->getTimestamp();
$result = askmysql("SELECT id, timecreated, email, firstname FROM mdl_user WHERE id NOT IN (SELECT userid FROM mdl_user_lastaccess) ORDER BY id");
foreach ( $result as $k=>$v){
    $d = new DateTime();
    $d->setTimestamp($v['timecreated']);
    $d = $d->getTimestamp();
    if ($d < $days3 && $d > $days4){
        firstAccessReminder($v['email'], $v['firstname']);
    } else if ($d < $days10 && $d > $days11){
        secondAccessReminder($v['email'], $v['firstname']);
    }
}

$days7 = new DateTime();
$days7->sub(new DateInterval('P7DT0H'));
$days7 = $days7->getTimestamp();
$days8 = new DateTime();
$days8->sub(new DateInterval('P8DT0H'));
$days8 = $days8->getTimestamp();
$days14 = new DateTime();
$days14->sub(new DateInterval('P14DT0H'));
$days14 = $days14->getTimestamp();
$days15 = new DateTime();
$days15->sub(new DateInterval('P15DT0H'));
$days15 = $days15->getTimestamp();

$result = askmysql("SELECT mdl_user_lastaccess.userid, mdl_user_lastaccess.timeaccess, mdl_user.email, mdl_user.firstname FROM mdl_user_lastaccess, mdl_user WHERE mdl_user_lastaccess.userid = mdl_user.id GROUP BY mdl_user_lastaccess.userid ORDER BY mdl_user_lastaccess.userid, mdl_user_lastaccess.timeaccess DESC");
foreach ($result as $k=>$v) {
    $d = new DateTime();
    $d->setTimestamp($v['timeaccess']);
    $d = $d->getTimestamp();
    if ($d < $days7 && $d > $days8){
        firstLogInReminder($v['email'], $v['firstname']);
    } else if ($d < $days14 && $d > $days15){
        secondLogInReminder($v['email'], $v['firstname']);
    }
}


// SQL para ver las clases no canceladas de cada alumno ordenadas por alumno y por fecha mayor
// SELECT own_acuity.acuityid, own_acuity.studentid, mdl_event.timestart FROM own_acuity, mdl_event WHERE own_acuity.studenteventid = mdl_event.id AND own_acuity.iscancelled = 'f'ORDER BY own_acuity.studentid ASC, mdl_event.timestart DESC
$result = askmysql("SELECT own_acuity.acuityid, own_acuity.studentid, mdl_event.timestart FROM own_acuity, mdl_event WHERE own_acuity.studenteventid = mdl_event.id AND own_acuity.iscancelled = 'f'ORDER BY own_acuity.studentid ASC, mdl_event.timestart ASC");
$events = [];
foreach ($result as $k=>$v) {
    $events[$v['studentid']] = array('acuityID'=> $v['acuityid'], 'timeStart'=>$v['timestart']);
}
echo "<br><br>";
var_dump($events);



function firstAccessReminder ($email, $firstName) {
    echo "Access 1 - " . $email . "<br>";
//    sendEmail($email,"First Access Template",firstAccessTemplate($firstName));
}
function firstAccessTemplate($firstName){
    return "<div style='width: 600px;'><div><p>Hola {$firstName},</p></div>" .
        "<div><p>Hace ya varios días que te enviamos el acceso a la plataforma, pero hemos visto que todavía no has accedido.</p></div>".
        "<div><p>Te animamos a que accedas y completes la prueba de nivel para poder comenzar a disfrutar del curso cuanto antes.</p></div>".
        "<div><p>Para acceder haz click en el siguiente botón.</p></div>".
        templateFooter() . "</div>";
}
function secondAccessReminder ($email, $firstName) {
    echo "Access 2 - " . $email . "<br>";
//    sendEmail($email,"First Access Template",firstAccessTemplate($firstName));
}
function secondAccessTemplate($firstName){
    return "<div style='width: 600px;'><div><p>Hola {$firstName},</p></div>" .
        "<div><p>Hace ya varios días que te enviamos el acceso a la plataforma, pero hemos visto que todavía no has accedido.</p></div>".
        "<div><p>Normalmente cuando alguien no accede es por alguna de estas tres razones:</p></div>".
        "<ol>".
        "<li>No recibí las credenciales correctamente o tengo algún problema a la hora de acceder.</li>".
        "<li>No sé qué tengo que hacer para acceder.</li>".
        "<li>No estoy interesado en realizar el curso.</li>".
        "<li>No es por ninguna de las anteriores razones</li>".
        "</ol><div><p>Te rogamos contactes con nosotros si se debe alguna de estas razones para solucionarlo.</p></div>".
        templateFooter() . "</div>";
}
function firstLogInReminder ($email, $firstName) {
    echo "LogIn 1 - " . $email . "<br>";
//    sendEmail($email,"First Access Template",firstAccessTemplate($firstName));
}
function firstLogInTemplate($firstName){
    return "<div style='width: 600px;'><div><p>Hola {$firstName},</p></div>" .
        "<div><p>Nos ponemos en contacto porque hemos visto que hace ya varios días que no accedes al aula virtual.</p></div>".
        "<div><p>Te animamos a que accedas y disfrutes de los nuevos ejercicios disponibles.</p></div>".
        templateFooter() . "</div>";
}
function secondLogInReminder ($email, $firstName) {
    echo "LogIn 2 - " . $email . "<br>";
//    sendEmail($email,"First Access Template",firstAccessTemplate($firstName));
}
function secondLogInTemplate($firstName){
    return "<div style='width: 600px;'><div><p>Hola {$firstName},</p></div>" .
        "<div><p>Nos ponemos en contacto porque hemos visto que hace ya varios días que no accedes al aula virtual.</p></div>".
        "<div><p>¿Está todo bien? Si tienes cualquier problema para acceder, no dudes en comunicárnoslo. Te animamos a que accedas y sigas disfrutando del curso.</p></div>" .
        templateFooter() . "</div>";

}
function templateFooter(){
    return "<div style='margin: auto; width: 180px; padding: 20px 0px;'><a href='http://aula.tuspeaking.com/app/moodle' target='_blank' style='background: #00bcd4;color: #fff;padding: 8px 12px;border: 0px;-webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px;text-decoration: none;display: inline-block;'>Accede a tu aula virtual</a></div>".
        "<div><p>Para cualquier consulta, no dudes en contactar con <a href='mailto: soporte@tuspeaking.com'>soporte@tuspeaking.com</a>.</p></div>".
        "<div><p>Muchas gracias.</p></div>".
        "<div><p>Un saludo,</p></div>".
        "<div><p>El equipo de tuSpeaking.</p></div>";
}