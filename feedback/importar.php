<?php
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

$file = fopen('historico.csv', 'r');
fgetcsv($file);
$count = 0;

while (($row = fgetcsv($file)) !== FALSE) {
    $fecha = date('Y-m-d', strtotime(str_replace(['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'], strtolower($row[0]))));
    $idioma = $conn->real_escape_string($row[1] ?? '');
    $profesor = $conn->real_escape_string($row[2] ?: $row[3] ?: $row[4] ?: $row[5] ?: $row[6] ?: '');
    $valoracion = intval($row[7] ?? 0);
    $problema = $conn->real_escape_string($row[8] ?? '');
    $feedback = $conn->real_escape_string($row[9] ?? '');
    $comentarios = $conn->real_escape_string($row[10] ?? '');
    $email = $conn->real_escape_string($row[11] ?? '');
    
    if ($email) {
        $sql = "INSERT INTO own_feedback_nps (submission_date,idioma,profesor,valoracion,problema_conexion,recibio_feedback,comentarios,email) VALUES ('$fecha','$idioma','$profesor',$valoracion,'$problema','$feedback','$comentarios','$email')";
        if ($conn->query($sql)) $count++;
    }
}
fclose($file);
echo "Importados: $count registros\n";
?>
