<?php
define('CLI_SCRIPT', true);
require_once '/home/aulatuspeaking/www/app/moodle/config.php';
require_once('/home/aulatuspeaking/www/app/moodle/askddbb.php');
global $CFG;
$tomorrow = new DateTime();
//$tomorrow->add(new DateInterval('P1DT1H'));
$tomorrow->add(new DateInterval('P0DT13H'));
$now = new DateTime();
echo $now->format('Y-m-d H:i:s').":\r\n";
$result = askmysql("SELECT * FROM own_acuity WHERE iscancelled = 'f' AND isteached != 't'");
foreach ($result as $value) {
    $res = askmysql("SELECT description, timestart FROM mdl_event WHERE visible = 1 AND id = {$value['studenteventid']}");
    if (count($res[0]) > 0) {
        if ($res[0]['timestart'] >= $now->getTimestamp() && $res[0]['timestart'] <= $tomorrow->getTimestamp()) {
            $desc = $res[0]['description'];
            $exp = explode("<a", $desc);
            $desc = $exp[0] . "<a" . $exp[1];
            $desc .= "<a href='https://aula.tuspeaking.com/app/moodle/cancelbymail.php?eventid={$value['studenteventid']}' class='acuity-embed-button' style='background: #00bcd4; color: #fff; padding: 8px 12px; border: 0px; -webkit-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;-moz-box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;box-shadow: 0 -2px 0 rgba(0,0,0,0.15) inset;border-radius: 4px; text-decoration: none; display: inline-block;'>Cancelar sesi&oacute;n</a>";
            echo "StudentEventID: {$value['studenteventid']} - result: " . setMysql("UPDATE mdl_event SET description = \"{$desc}\" WHERE id = {$value['studenteventid']}") . "\r\n";
        }
    }
}
echo "\r\n\r\n\r\n";