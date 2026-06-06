<?php
/**
 * CODING - Importar Nota Plataforma desde Moodle CESCE
 */

$local_host = 'localhost';
$local_db = 'aulatuspeaking35';
$local_user = 'moodle35';
$local_pass = 'TuspeakingFix2025!';

$remote_host = 'mysql-5603.dinaserver.com';
$remote_db = 'aulatuspeaking35cesce';
$remote_user = 'moodle35cesce';
$remote_pass = 'Eemcdmoce2000*';

echo "============================================================\n";
echo "  CODING - Importar Nota Plataforma (Promedio Actividades)\n";
echo "  Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

$local = new PDO("mysql:host=$local_host;dbname=$local_db;charset=utf8mb4", $local_user, $local_pass);
$remote = new PDO("mysql:host=$remote_host;dbname=$remote_db;charset=utf8mb4", $remote_user, $remote_pass);

$sql = "SELECT 
    u.email,
    c.id as course_id,
    ROUND(AVG(gg.finalgrade), 2) as promedio_actividades
FROM mdl_grade_grades gg
JOIN mdl_grade_items gi ON gg.itemid = gi.id
JOIN mdl_user u ON gg.userid = u.id
JOIN mdl_course c ON gi.courseid = c.id
WHERE c.category = 207
AND gi.itemtype = 'mod'
AND gg.finalgrade IS NOT NULL
GROUP BY u.id, c.id";

$stmt = $remote->query($sql);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Registros encontrados: " . count($notas) . "\n\n";

$actualizados = 0;
foreach ($notas as $nota) {
    $email = strtolower(trim($nota['email']));
    $course_id = $nota['course_id'];
    $promedio = $nota['promedio_actividades'];
    
    $stmt = $local->prepare("UPDATE mdl_cesce_calificaciones 
                             SET nota_plataforma = ? 
                             WHERE empleado_email = ? 
                             AND moodle_course_id = ?
                             AND edicion = '25.2'");
    $stmt->execute([$promedio, $email, $course_id]);
    
    if ($stmt->rowCount() > 0) {
        $actualizados++;
        echo "  [OK] $email: $promedio\n";
    }
}

echo "\n============================================================\n";
echo "  Actualizados: $actualizados\n";
echo "============================================================\n";
?>
