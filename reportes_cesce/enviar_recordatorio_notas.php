<?php
/**
 * CODING - Enviar Recordatorio de Notas a Profesores CESCE
 */

$edicion = '25.2';
$deadline = 'Tuesday, December 31st, 2025 at 14:00 CET';
$test_mode = false;

// Emails de profesores (corporativo + personal)
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

$admin_email = 'hfernandez@tuspeaking.com';

$local = new PDO("mysql:host=localhost;dbname=aulatuspeaking35;charset=utf8mb4", 
                 "moodle35", "TuspeakingFix2025!");

$timestamp = date('Y-m-d H:i:s');
echo "============================================================\n";
echo "  CODING - Recordatorio Notas CESCE\n";
echo "  Edicion: $edicion\n";
echo "  Fecha: $timestamp\n";
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

$profesores_pendientes = [];
$profesores_completados = [];

foreach ($por_profesor as $profesor => $cursos_prof) {
    echo "------------------------------------------------------------\n";
    echo "Profesor: $profesor\n";
    
    $tiene_pendientes = false;
    $total_alumnos_prof = 0;
    $total_oral = 0;
    $total_clase = 0;
    
    foreach ($cursos_prof as $c) {
        $total_alumnos_prof += $c['total_alumnos'];
        $total_oral += $c['con_oral'];
        $total_clase += $c['con_clase'];
        if ($c['con_oral'] < $c['total_alumnos'] || $c['con_clase'] < $c['total_alumnos']) {
            $tiene_pendientes = true;
        }
    }
    
    if (!$tiene_pendientes) {
        echo "  ✅ Todas las notas completadas ($total_alumnos_prof alumnos)\n";
        $profesores_completados[] = $profesor;
        continue;
    }
    
    $profesores_pendientes[] = [
        'nombre' => $profesor,
        'alumnos' => $total_alumnos_prof,
        'oral' => $total_oral,
        'clase' => $total_clase
    ];
    
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
        echo "  📧 [TEST] Email preparado para: " . implode(', ', $emails_to) . "\n";
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

// Enviar resumen al admin
$resumen_html = "<h2>📊 Resumen CESCE Grades - $timestamp</h2>";
$resumen_html .= "<h3>✅ Profesores Completados (" . count($profesores_completados) . "):</h3><ul>";
foreach ($profesores_completados as $p) {
    $resumen_html .= "<li>$p</li>";
}
$resumen_html .= "</ul>";

$resumen_html .= "<h3>❌ Profesores Pendientes (" . count($profesores_pendientes) . "):</h3><ul>";
foreach ($profesores_pendientes as $p) {
    $resumen_html .= "<li>{$p['nombre']}: Oral {$p['oral']}/{$p['alumnos']}, Clase {$p['clase']}/{$p['alumnos']}</li>";
}
$resumen_html .= "</ul>";

if (!$test_mode) {
    $headers_admin = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: TuSpeaking <notificaciones@tuspeaking.com>'
    ];
    mail($admin_email, "[CESCE] Status Update - " . date('d/m H:i'), $resumen_html, implode("\r\n", $headers_admin));
    echo "\n📧 Resumen enviado a admin: $admin_email\n";
}

echo "\n============================================================\n";
echo "  RESUMEN FINAL\n";
echo "============================================================\n";
echo "  Profesores completados: " . count($profesores_completados) . "\n";
echo "  Profesores pendientes: " . count($profesores_pendientes) . "\n";
echo "============================================================\n";
?>
