<?php
/**
 * Enviar solo a emails personales (complemento)
 */

$deadline = 'Tuesday, December 31st, 2025 at 14:00 CET';

// Emails personales
$personales = [
    'Eldina' => 'eldina_n@yahoo.com',
    'Kate' => 'kateklopper44@gmail.com',
    'Odile' => 'tezel_odile@hotmail.fr',
    'Steve' => 'teacherklopper@gmail.com'
];

$local = new PDO("mysql:host=localhost;dbname=aulatuspeaking35;charset=utf8mb4", 
                 "moodle35", "TuspeakingFix2025!");

echo "Enviando a emails personales...\n\n";

foreach ($personales as $profesor => $email) {
    $stmt = $local->prepare("SELECT grupo, moodle_course_id, 
        COUNT(*) as total,
        SUM(CASE WHEN nota_oral IS NOT NULL THEN 1 ELSE 0 END) as oral,
        SUM(CASE WHEN nota_clase IS NOT NULL THEN 1 ELSE 0 END) as clase
        FROM mdl_cesce_calificaciones 
        WHERE profesor = ? AND edicion = '25.2'
        GROUP BY grupo, moodle_course_id");
    $stmt->execute([$profesor]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cursos)) {
        echo "$profesor: Sin cursos\n";
        continue;
    }
    
    $courses_html = "";
    foreach ($cursos as $c) {
        $url = "https://cesce.tuspeaking.com/app/moodle/grade/report/grader/index.php?id={$c['moodle_course_id']}";
        $oral_status = ($c['oral'] == $c['total']) ? "✅ Complete" : "❌ Pending ({$c['oral']}/{$c['total']})";
        $clase_status = ($c['clase'] == $c['total']) ? "✅ Complete" : "❌ Pending ({$c['clase']}/{$c['total']})";
        $courses_html .= "<tr><td style='padding:10px;border:1px solid #ddd'><strong>{$c['grupo']}</strong></td>
            <td style='padding:10px;border:1px solid #ddd'>$oral_status</td>
            <td style='padding:10px;border:1px solid #ddd'>$clase_status</td>
            <td style='padding:10px;border:1px solid #ddd'><a href='$url'>Open Gradebook</a></td></tr>";
    }
    
    $body = "<html><body style='font-family:Arial,sans-serif;line-height:1.6'>
        <h2>Hi $profesor,</h2>
        <p>This is a friendly reminder that some grades for your CESCE courses are still pending.</p>
        <h3>📋 Your Courses Status:</h3>
        <table style='border-collapse:collapse;width:100%'>
            <tr style='background:#f2f2f2'><th style='padding:10px;border:1px solid #ddd'>Course</th>
            <th style='padding:10px;border:1px solid #ddd'>Oral Exam (60%)</th>
            <th style='padding:10px;border:1px solid #ddd'>Participation (20%)</th>
            <th style='padding:10px;border:1px solid #ddd'>Action</th></tr>
            $courses_html
        </table>
        <h3>📝 How to Submit Grades:</h3>
        <ol><li>Click <strong>'Open Gradebook'</strong></li>
        <li>Find <strong>'Final Group Speaking Assessment'</strong> → Enter oral grade (0-10)</li>
        <li>Find <strong>'Participation Grade (Discretionary)'</strong> → Enter participation grade (0-10)</li>
        <li>Click <strong>'Save changes'</strong></li></ol>
        <p style='background:#fff3cd;padding:15px;border-radius:5px'><strong>⏰ Deadline:</strong> $deadline</p>
        <p>The platform activities grade (20%) is calculated automatically.</p>
        <p>Thank you!<br><strong>TuSpeaking Team</strong></p>
        </body></html>";
    
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: TuSpeaking <notificaciones@tuspeaking.com>";
    
    if (mail($email, "[ACTION REQUIRED] CESCE Grades Pending - Edition 25.2", $body, $headers)) {
        echo "✅ $profesor: $email\n";
    } else {
        echo "❌ $profesor: $email\n";
    }
}
echo "\nCompletado.\n";
?>
