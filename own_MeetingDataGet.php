<?php
// SEC-9 (25-oct-2026): exige sesión de Moodle (antes, datos de la reunión de Zoom sin sesión).
require_once(__DIR__ . '/config.php');
require_login();
require_once './own_MeetingData.php';
if (!empty($_GET)){
    $item = new MeetingData($_GET['zoomID']);
    echo json_encode($item->getData());
}