<?php
/**
 * WEBHOOK RECEPTOR DE EVALUACIONES JOTFORM
 * URL: https://aula.tuspeaking.com/app/moodle/webhook_jotform_evaluaciones.php
 */

define('LOG_FILE', '/home/aulatuspeaking/www/app/moodle/logs/webhook_jotform.log');

$DB_CONFIG = [
    'host' => 'localhost',
    'database' => 'aulatuspeaking35',
    'user' => 'moodle35',
    'password' => 'TuspeakingFix2025!'
];

$ESCALA_NOTAS = [
    'poor(1)' => 1, 'poor (1)' => 1,
    'good(2)' => 2, 'good (2)' => 2,
    'good(3)' => 3, 'good (3)' => 3,
    'good(4)' => 4, 'good (4)' => 4,
    'excelent(5)' => 5, 'excelent (5)' => 5,
    'excellent(5)' => 5, 'excellent (5)' => 5,
];

$ESCALA_FINAL = [
    'bad (less than 3)' => 2, 'bad(less than 3)' => 2,
    'not so good (3-4)' => 3.5, 'not so good(3-4)' => 3.5,
    'good (5-6)' => 5.5, 'good(5-6)' => 5.5, 'good ( 5- 6)' => 5.5,
    'very good (7-8)' => 7.5, 'very good(7-8)' => 7.5, 'very good (7 - 8)' => 7.5,
    'excelent (9-10)' => 9.5, 'excelent(9-10)' => 9.5, 'excelent (9 - 10)' => 9.5,
    'excellent (9-10)' => 9.5, 'excellent(9-10)' => 9.5, 'excellent (9 - 10)' => 9.5,
];

function logMsg($msg, $data = null) {
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $entry = "[" . date('Y-m-d H:i:s') . "] $msg";
    if ($data) $entry .= "\n" . print_r($data, true);
    file_put_contents(LOG_FILE, $entry . "\n---\n", FILE_APPEND);
}

function parsePuntuacion($texto) {
    global $ESCALA_NOTAS;
    return $ESCALA_NOTAS[strtolower(trim($texto))] ?? null;
}

function parsePuntuacionFinal($texto) {
    global $ESCALA_FINAL;
    return $ESCALA_FINAL[strtolower(trim($texto))] ?? null;
}
function procesarWebhook($data) {
    global $DB_CONFIG;
    
    logMsg("Webhook recibido", $data);
    
    try {
        $pdo = new PDO(
            "mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['database']};charset=utf8mb4",
            $DB_CONFIG['user'],
            $DB_CONFIG['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        logMsg("Error BD: " . $e->getMessage());
        return ['success' => false, 'error' => 'DB connection failed'];
    }
    
    // Guardar log
    $stmt = $pdo->prepare("INSERT INTO mdl_coding_webhook_log (source, form_id, submission_id, raw_payload) VALUES ('jotform', ?, ?, ?)");
    $formId = $data['formID'] ?? '';
    $submissionId = $data['submissionID'] ?? uniqid();
    $stmt->execute([$formId, $submissionId, json_encode($data)]);
    
    // Extraer datos
    $teacher = $data['q3_teacher'] ?? $data['teacher'] ?? '';
    $student = $data['q4_student'] ?? $data['student'] ?? '';
    
    if (!$student) {
        logMsg("Sin nombre de estudiante");
        return ['success' => false, 'error' => 'No student name'];
    }
    
    // Detectar nivel por formulario
    $nivel = 'B1';
    if (strpos($formId, '252782516276363') !== false) $nivel = 'B2';
    if (strpos($formId, '211504020601332') !== false) $nivel = 'C1';
    
    // Parsear notas de rubricas
    $grammar = []; $pron = []; $comm = [];
    
    foreach ($data as $key => $value) {
        $k = strtolower($key);
        $score = parsePuntuacion($value);
        if ($score) {
            if (strpos($k, 'grammar') !== false) $grammar[] = $score;
            elseif (strpos($k, 'pronunciation') !== false) $pron[] = $score;
            elseif (strpos($k, 'communicative') !== false) $comm[] = $score;
        }
    }
    
    $notaGrammar = $grammar ? array_sum($grammar) / count($grammar) : null;
    $notaPron = $pron ? array_sum($pron) / count($pron) : null;
    $notaComm = $comm ? array_sum($comm) / count($comm) : null;
    
    // Nota final
    $notas = array_filter([$notaGrammar, $notaPron, $notaComm]);
    $notaFinal = $notas ? (array_sum($notas) / count($notas) - 1) * 9 / 4 + 1 : null;
    
    // Evaluaciones finales
    $oralExam = null; $participation = null; $homework = null;
    foreach ($data as $key => $value) {
        $k = strtolower($key);
        if (strpos($k, 'oral') !== false && strpos($k, 'exam') !== false) $oralExam = parsePuntuacionFinal($value);
        elseif (strpos($k, 'participation') !== false) $participation = parsePuntuacionFinal($value);
        elseif (strpos($k, 'homework') !== false && strpos($k, 'status') !== false) $homework = parsePuntuacionFinal($value);
    }
    
    // Recomendacion
    $shouldMove = $data['q20_shouldThe'] ?? $data['should_the_student'] ?? '';
    $recommendedLevel = $data['q21_recommendedLevel'] ?? $data['recommended_level'] ?? '';
    $recomendacion = (stripos($shouldMove, 'next level') !== false || stripos($shouldMove, 'moved') !== false) ? 'subir_nivel' : 'mantener_nivel';
    
    // Justificacion
    $justificacion = '';
    foreach ($data as $key => $value) {
        if (stripos($key, 'justify') !== false) $justificacion = $value;
    }
    
    // Certificado
    $certificado = 'ninguno';
    if ($notaFinal >= 7.5) $certificado = 'superacion';
    elseif ($notaFinal >= 5) $certificado = 'participacion';
    
    // Obtener empresa/edicion
    $stmt = $pdo->query("SELECT id FROM mdl_coding_empresas WHERE codigo = 'SERVIGUIDE'");
    $empresaId = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT id FROM mdl_coding_ediciones WHERE empresa_id = ? AND activo = 1 ORDER BY fecha_inicio DESC LIMIT 1");
    $stmt->execute([$empresaId]);
    $edicionId = $stmt->fetchColumn();
    
    // Verificar si existe
    $stmt = $pdo->prepare("SELECT id FROM mdl_coding_evaluaciones WHERE alumno_nombre = ? AND empresa_id = ?");
    $stmt->execute([$student, $empresaId]);
    $existente = $stmt->fetchColumn();
    
    if ($existente) {
        $stmt = $pdo->prepare("UPDATE mdl_coding_evaluaciones SET 
            profesor=?, nivel_evaluado=?, nota_grammar_vocabulary=?, nota_pronunciation=?, nota_communicative=?,
            nota_oral_exam=?, nota_participacion=?, nota_homework=?, nota_final=?, recomendacion=?, 
            nivel_recomendado=?, justificacion=?, certificado_tipo=?, jotform_raw_data=?, fecha_modificacion=NOW()
            WHERE id=?");
        $stmt->execute([$teacher, $nivel, $notaGrammar, $notaPron, $notaComm, $oralExam, $participation, $homework,
            $notaFinal, $recomendacion, $recommendedLevel, $justificacion, $certificado, json_encode($data), $existente]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO mdl_coding_evaluaciones 
            (empresa_id, edicion_id, jotform_submission_id, fecha_evaluacion, profesor, alumno_nombre, nivel_evaluado,
            nota_grammar_vocabulary, nota_pronunciation, nota_communicative, nota_oral_exam, nota_participacion, nota_homework,
            nota_final, recomendacion, nivel_recomendado, justificacion, certificado_tipo, jotform_raw_data, fecha_creacion)
            VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$empresaId, $edicionId, $submissionId, $teacher, $student, $nivel,
            $notaGrammar, $notaPron, $notaComm, $oralExam, $participation, $homework,
            $notaFinal, $recomendacion, $recommendedLevel, $justificacion, $certificado, json_encode($data)]);
    }
    
    logMsg("Procesado OK: $student | Nota: $notaFinal | Cert: $certificado");
    return ['success' => true, 'alumno' => $student, 'nota' => $notaFinal, 'certificado' => $certificado];
}

// ENTRADA
$rawInput = file_get_contents('php://input');
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) {
        $data = json_decode($rawInput, true) ?? [];
    } else {
        parse_str($rawInput, $data);
        if (isset($data['rawRequest'])) {
            $data = array_merge($data, json_decode($data['rawRequest'], true) ?? []);
        }
    }
}

if (!empty($data)) {
    header('Content-Type: application/json');
    echo json_encode(procesarWebhook($data));
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ready', 'endpoint' => 'TuSpeaking JotForm Webhook', 'time' => date('Y-m-d H:i:s')]);
}
