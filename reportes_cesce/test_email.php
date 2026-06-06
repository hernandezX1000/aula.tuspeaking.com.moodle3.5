<?php
$test_email = 'hfernandez@tuspeaking.com';

$subject = "[TEST] CESCE Grades Reminder - Edition 25.2";

$body_html = '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Hi Kate,</h2>
    
    <p>This is a friendly reminder that some grades for your CESCE courses are still pending.</p>
    
    <h3>📋 Your Courses Status:</h3>
    <table style="border-collapse: collapse; width: 100%;">
        <tr style="background-color: #f2f2f2;">
            <th style="padding: 10px; border: 1px solid #ddd;">Course</th>
            <th style="padding: 10px; border: 1px solid #ddd;">Oral Exam (60%)</th>
            <th style="padding: 10px; border: 1px solid #ddd;">Participation (20%)</th>
            <th style="padding: 10px; border: 1px solid #ddd;">Action</th>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;"><strong>CESCE 25.1 Inglés B1 (MyJ 8:30) Kate 3</strong></td>
            <td style="padding: 10px; border: 1px solid #ddd;">❌ Pending (0/5)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">❌ Pending (0/5)</td>
            <td style="padding: 10px; border: 1px solid #ddd;"><a href="https://cesce.tuspeaking.com/app/moodle/grade/report/grader/index.php?id=1403">Open Gradebook</a></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;"><strong>CESCE 25.1 Inglés MyJ 14:00 - 15:00 Kate 2</strong></td>
            <td style="padding: 10px; border: 1px solid #ddd;">❌ Pending (0/5)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">❌ Pending (0/5)</td>
            <td style="padding: 10px; border: 1px solid #ddd;"><a href="https://cesce.tuspeaking.com/app/moodle/grade/report/grader/index.php?id=1402">Open Gradebook</a></td>
        </tr>
    </table>
    
    <h3>📝 How to Submit Grades:</h3>
    <ol>
        <li>Click the <strong>Open Gradebook</strong> link for each course</li>
        <li>Find the column <strong>Final Group Speaking Assessment</strong> → Enter the oral exam grade (0-10)</li>
        <li>Find the column <strong>Participation Grade (Discretionary)</strong> → Enter the class participation grade (0-10)</li>
        <li>Click <strong>Save changes</strong></li>
    </ol>
    
    <p style="background-color: #fff3cd; padding: 15px; border-radius: 5px;">
        <strong>⏰ Deadline:</strong> Tuesday, December 31st, 2025 at 14:00 CET
    </p>
    
    <p>The platform activities grade (20%) is calculated automatically from student completions.</p>
    
    <p>If you have any questions, please reply to this email.</p>
    
    <p>Thank you!<br>
    <strong>TuSpeaking Team</strong></p>
</body>
</html>';

$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: TuSpeaking <notificaciones@tuspeaking.com>',
    'Reply-To: soporte@tuspeaking.com'
];

echo "Enviando email de prueba a: $test_email\n";

if (mail($test_email, $subject, $body_html, implode("\r\n", $headers))) {
    echo "✅ Email enviado correctamente\n";
} else {
    echo "❌ Error al enviar email\n";
}
?>
