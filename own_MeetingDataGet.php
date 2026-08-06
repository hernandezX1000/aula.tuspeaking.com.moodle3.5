<?php
require_once './own_MeetingData.php';
if (!empty($_GET)){
    $item = new MeetingData($_GET['zoomID']);
    echo json_encode($item->getData());
}