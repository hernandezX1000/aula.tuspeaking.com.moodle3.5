<?php
/**
 * ============================================================
 * CODING - Enviar Recordatorio de Notas a Profesores CESCE
 * ============================================================
 */

$edicion = '25.2';
$deadline = 'Tuesday, December 31st, 2025 at 14:00 CET';
$test_mode = true;

// Emails de profesores (corporativo, personal)
$profesores_emails = [
    'Alvaro' => ['alvaro@live2.tuspeaking.com'],
    'Amber' => ['amber@live5.tuspeaking.com'],
    'Candice' => ['candice@live4.tuspeaking.com'],
    'Eldina' => ['eldina@live3.tuspeaking.com', 'eldina_n@yahoo.com'],
    'Javier' => ['javier@tuspeaking.com'],
    'Jessica' => ['jessica@live6.tuspeaking.com'],
    'Kate' => ['kate@live.tuspeaking.com', 'kateklopper44@gmail.com'],
    'Michaela' => ['michaela@live4.tuspeaking.com', 'willow.5@hotmail.com'],
    'Odile' => ['odile@live4.tuspeaking.com', 'tezel_odile@hotmail.fr'],
    'RJ' => ['reddy@live10.tuspeaking.com'],
    'Steve' => ['steve@live.tuspeaking.com', 'teacherklopper@gmail.com']
];

$local = new PDO("mysql:host=localhost;dbname=aulatuspeaking35;charset=utf8mb4", 
                 "moodle35", "TuspeakingFix2025!");

echo "============================================================\n";
echo "  CODING - Recordatorio Notas CESCE\n";
echo "  Edicion: $edicion\n";
echo "  Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "  Modo: " . ($test_mode ? "TEST" : "PRODUCCION") . "\n";
echo "============================================================\n\n";

$sql = "SELECT 
    c.profesor,
    c.grupo,
    c.moodle_course_id,
    COUNT(*) as total_alumnos,
    SUM(CASE WHEN c.nota_oral IS NOT NULL THEN 1 ELSE 0 END) as con_oral,
    SUM(CASE WHEN c.nota_clase IS NOT NULL THEN 1 ELSE 0 END) as con_clase
FROM mdl_cesce_calificaciones c
WHERE c.edicion = :edicion
GROUP BY c.profesor, c.grupo, c.moodle_course_id
ORDER BY c.profesor, c.grupo";

$stmt = $local->prepare($sql);
$stmt->execute(['edicion' => $edicion]);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$por_profesor = [];
foreach ($cursos as $curso) {
    $prof = $curso['profesor'];
    if (!isset($por_profesor[$prof])) {
        $por_profesor[$prof] = [];
    }
    $por_profesor[$prof][] = $curso;
}

foreach ($por_profesor as $profesor => $cursos_prof) {
    echo "------------------------------------------------------------\n";
    echo "Profesor: $profesor\n";
    
    $tiene_pendientes = false;
    foreach ($cursos_prof as $c) {
        if ($c['con_oral'] < $c['total_alumnos'] || $c['con_clase'] < $c['total_alumnos']) {
            $tiene_pendientes = true;
            break;
        }
    }
    
    if (!$tiene_pendientes) {
        echo "  ✅ Todas las notas completadas\n";
        continue;
    }
    
    $emails_to = isset($profesores_emails[$profesor]) ? $profesores_emails[$profesor] : [];
    if (empty($emails_to)) {
        echo "  ⚠️ Email no encontrado para $profesor\n";
        continue;
    }
    
    $courses_html = "";
    foreach ($cursos_prof as $c) {
        $course_id = $c['moodle_course_id'];
        $grupo = $c['grupo'];
        $total = $c['total_alumnos'];
        $oral = $c['con_oral'];
        $clase = $c['con_clase'];
        
        $url_grades = "https://cesce.tuspeaking.com/app/moodle/grade/report/grader/index.php?id=$course_id";
        
        $oral_status = ($oral == $total) ? "✅ Complete ($oral/$total)" : "❌ Pending ($oral/$total)";
        $clase_status = ($clase == $total) ? "✅ Complete ($clase/$total)" : "❌ Pending ($clase/$total)";
        
        $courses_html .= "
        <tr>
            <td style='padding: 10px; border: 1px solid #ddd;'><strong>$grupo</strong></td>
            <td style='padding: 10px; border: 1px solid #ddd;'>$oral_status</td>
            <td style='padding: 10px; border: 1px solid #ddd;'>$clase_status</td>
            <td style='padding: 10px; border: 1px solid #ddd;'><a href='$url_grades'>Open Gradebook</a></td>
        </tr>";
    }
    
    $subject = "[ACTION REQUIRED] CESCE Grades Pending - Edition 25.2";
    
    $body_html = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <h2>Hi $profesor,</h2>
        
        <p>This is a friendly reminder that some grades for your CESCE courses are still pending.</p>
        
        <h3>📋 Your Courses Status:</h3>
        <table style='border-collapse: collapse; width: 100%;'>
            <tr style='background-color: #f2f2f2;'>
                <th style='padding: 10px; border: 1px solid #ddd;'>Course</th>
                <th style='padding: 10px; border: 1px solid #ddd;'>Oral Exam (60%)</th>
                <th style='padding: 10px; border: 1px solid #ddd;'>Participation (20%)</th>
                <th style='padding: 10px; border: 1px solid #ddd;'>Action</th>
            </tr>
            $courses_html
        </table>
        
        <h3>📝 How to Submit Grades:</h3>
        <ol>
            <li>Click the <strong>'Open Gradebook'</strong> link for each course</li>
            <li>Find the column <strong>'Final Group Speaking Assessment'</strong> → Enter the oral exam grade (0-10)</li>
            <li>Find the column <strong>'Participation Grade (Discretionary)'</strong> → Enter the class participation grade (0-10)</li>
            <li>Click <strong>'Save changes'</strong></li>
        </ol>
        
        <p style='background-color: #fff3cd; padding: 15px; border-radius: 5px;'>
            <strong>⏰ Deadline:</strong> $deadline
        </p>
        
        <p>The platform activities grade (20%) is calculated automatically from student completions.</p>
        
        <p>If you have any questions, please reply to this email.</p>
        
        <p>Thank you!<br>
        <strong>TuSpeaking Team</strong></p>
    </body>
    </html>";
    
    if ($test_mode) {
        echo "  📧 Email preparado para: " . implode(', ', $emails_to) . "\n";
        foreach ($cursos_prof as $c) {
            echo "    - {$c['grupo']}: Oral {$c['con_oral']}/{$c['total_alumnos']}, Clase {$c['con_clase']}/{$c['total_alumnos']}\n";
        }
    } else {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: TuSpeaking <notificaciones@tuspeaking.com>',
            'Reply-To: soporte@tuspeaking.com'
        ];
        
        foreach ($emails_to as $email_addr) {
            if (mail($email_addr, $subject, $body_html, implode("\r\n", $headers))) {
                echo "  ✅ Email enviado a: $email_addr\n";
            } else {
                echo "  ❌ Error enviando a: $email_addr\n";
            }
        }
    }
}

echo "\n============================================================\n";
echo "  RESUMEN\n";
echo "============================================================\n";
$stmt = $local->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN nota_oral IS NOT NULL THEN 1 ELSE 0 END) as con_oral,
    SUM(CASE WHEN nota_clase IS NOT NULL THEN 1 ELSE 0 END) as con_clase
FROM mdl_cesce_calificaciones WHERE edicion = '25.2'");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Total alumnos: {$stats['total']}\n";
echo "  Con nota oral: {$stats['con_oral']} (" . round($stats['con_oral']/$stats['total']*100) . "%)\n";
echo "  Con nota clase: {$stats['con_clase']} (" . round($stats['con_clase']/$stats['total']*100) . "%)\n";
echo "============================================================\n";
?>
