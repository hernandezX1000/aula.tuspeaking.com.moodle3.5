<?php
/**
 * ============================================================
 * CODING - Importar Notas Completas desde Moodle CESCE
 * ============================================================
 * Importa: Nota Oral, Nota Plataforma, Nota Clase
 * Calcula: Nota Final Ponderada, Pass Y/N, Diploma
 * ============================================================
 */

$local_host = 'localhost';
$local_db = 'aulatuspeaking35';
$local_user = 'moodle35';
$local_pass = 'TuspeakingFix2025!';

$remote_host = 'mysql-5603.dinaserver.com';
$remote_db = 'aulatuspeaking35cesce';
$remote_user = 'moodle35cesce';
$remote_pass = 'Eemcdmoce2000*';

$edicion = '25.2';

echo "============================================================\n";
echo "  CODING - Importar Notas Completas Moodle CESCE\n";
echo "  Edicion: $edicion\n";
echo "  Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// Conectar BD local
$local = new PDO("mysql:host=$local_host;dbname=$local_db;charset=utf8mb4", $local_user, $local_pass);
$local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "[OK] Conectado a BD local\n";

// Conectar BD remota
$remote = new PDO("mysql:host=$remote_host;dbname=$remote_db;charset=utf8mb4", $remote_user, $remote_pass);
$remote->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "[OK] Conectado a BD remota\n\n";

// Obtener mapeo de cursos
$stmt = $local->query("SELECT moodle_course_id, moodle_course_name, acuity_type FROM mdl_cesce_mapeo_cursos WHERE edicion = '$edicion'");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Cursos mapeados: " . count($cursos) . "\n\n";

$stats = ['nuevos' => 0, 'actualizados' => 0, 'errores' => 0];

foreach ($cursos as $curso) {
    $course_id = $curso['moodle_course_id'];
    $course_name = $curso['moodle_course_name'];
    $grupo_acuity = $curso['acuity_type'];
    
    echo "------------------------------------------------------------\n";
    echo "Curso: $course_name (ID: $course_id)\n";
    
    // 1. Obtener NOTA ORAL (Final Group Speaking Assessment)
    $sql_oral = "SELECT u.id as user_id, u.email, gg.finalgrade as nota
                 FROM mdl_grade_grades gg
                 JOIN mdl_grade_items gi ON gg.itemid = gi.id
                 JOIN mdl_user u ON gg.userid = u.id
                 WHERE gi.courseid = :course_id
                 AND gi.itemname = 'Final Group Speaking Assessment'
                 AND gg.finalgrade IS NOT NULL";
    $stmt = $remote->prepare($sql_oral);
    $stmt->execute(['course_id' => $course_id]);
    $notas_oral_map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $notas_oral_map[strtolower($row['email'])] = $row['nota'];
    }
    echo "  Notas Oral: " . count($notas_oral_map) . "\n";
    
    // 2. Obtener NOTA CLASE (Participation Grade)
    $sql_clase = "SELECT u.id as user_id, u.email, gg.finalgrade as nota
                  FROM mdl_grade_grades gg
                  JOIN mdl_grade_items gi ON gg.itemid = gi.id
                  JOIN mdl_user u ON gg.userid = u.id
                  WHERE gi.courseid = :course_id
                  AND gi.itemname = 'Participation Grade (Discretionary)'
                  AND gg.finalgrade IS NOT NULL";
    $stmt = $remote->prepare($sql_clase);
    $stmt->execute(['course_id' => $course_id]);
    $notas_clase_map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $notas_clase_map[strtolower($row['email'])] = $row['nota'];
    }
    echo "  Notas Clase: " . count($notas_clase_map) . "\n";
    
    // 3. Obtener NOTA PLATAFORMA (promedio actividades mod)
    $sql_plataforma = "SELECT u.id as user_id, u.email, ROUND(AVG(gg.finalgrade), 2) as nota
                       FROM mdl_grade_grades gg
                       JOIN mdl_grade_items gi ON gg.itemid = gi.id
                       JOIN mdl_user u ON gg.userid = u.id
                       WHERE gi.courseid = :course_id
                       AND gi.itemtype = 'mod'
                       AND gg.finalgrade IS NOT NULL
                       GROUP BY u.id, u.email";
    $stmt = $remote->prepare($sql_plataforma);
    $stmt->execute(['course_id' => $course_id]);
    $notas_plataforma_map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $notas_plataforma_map[strtolower($row['email'])] = $row['nota'];
    }
    echo "  Notas Plataforma: " . count($notas_plataforma_map) . "\n";
    
    // 4. Obtener TODOS los alumnos matriculados en el curso
    $sql_alumnos = "SELECT DISTINCT u.id as user_id, u.email, u.firstname, u.lastname
                    FROM mdl_user u
                    JOIN mdl_user_enrolments ue ON ue.userid = u.id
                    JOIN mdl_enrol e ON e.id = ue.enrolid
                    WHERE e.courseid = :course_id
                    AND u.email LIKE '%@%'
                    AND u.email NOT LIKE '%tuspeaking%'";
    $stmt = $remote->prepare($sql_alumnos);
    $stmt->execute(['course_id' => $course_id]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Alumnos matriculados: " . count($alumnos) . "\n";
    
    // Detectar nivel y profesor del nombre del curso
    $nivel = 'B1';
    if (preg_match('/(A1|A2|B1\.2|B1|B2|C1|C2)/i', $course_name, $m)) {
        $nivel = strtoupper($m[1]);
    }
    $profesor = '';
    $profesores = ['Kate', 'Steve', 'RJ', 'Rj', 'Jessica', 'Amber', 'Candice', 'Odile', 'Eldina', 'Javier', 'Michaela', 'Rosario'];
    foreach ($profesores as $p) {
        if (stripos($course_name, $p) !== false) {
            $profesor = $p;
            break;
        }
    }
    
    // 5. Procesar cada alumno matriculado
    foreach ($alumnos as $alumno) {
        $email = strtolower(trim($alumno['email']));
        $user_id = $alumno['user_id'];
        
        $nota_oral = isset($notas_oral_map[$email]) ? round($notas_oral_map[$email], 2) : null;
        $nota_clase = isset($notas_clase_map[$email]) ? round($notas_clase_map[$email], 2) : null;
        $nota_plataforma = isset($notas_plataforma_map[$email]) ? round($notas_plataforma_map[$email], 2) : null;
        
        
        // Calcular nota final ponderada si tenemos las 3 notas
        $nota_final = null;
        $pass_yn = null;
        $diploma = null;
        
        if ($nota_oral !== null && $nota_plataforma !== null && $nota_clase !== null) {
            $nota_final = round(($nota_oral * 0.6) + ($nota_plataforma * 0.2) + ($nota_clase * 0.2), 2);
            $pass_yn = ($nota_final >= 5) ? 'Y' : 'N';
            $diploma = ($nota_final >= 7.5) ? 'Superación' : null;
        }
        
        // Verificar si existe
        $stmt = $local->prepare("SELECT id FROM mdl_cesce_calificaciones WHERE empleado_email = ? AND edicion = ?");
        $stmt->execute([$email, $edicion]);
        $existe = $stmt->fetch();
        
        try {
            if ($existe) {
                $sql = "UPDATE mdl_cesce_calificaciones SET 
                        grupo = ?, profesor = ?, nivel = ?,
                        nota_oral = ?, nota_plataforma = ?, nota_clase = ?,
                        nota_final = ?, pass_yn = ?, diploma = ?,
                        moodle_course_id = ?, moodle_user_id = ?, 
                        fecha_importacion = NOW()
                        WHERE id = ?";
                $local->prepare($sql)->execute([
                    $grupo_acuity, $profesor, $nivel,
                    $nota_oral, $nota_plataforma, $nota_clase,
                    $nota_final, $pass_yn, $diploma,
                    $course_id, $user_id, $existe['id']
                ]);
                $stats['actualizados']++;
            } else {
                $sql = "INSERT INTO mdl_cesce_calificaciones 
                        (edicion, empleado_email, grupo, profesor, nivel,
                         nota_oral, nota_plataforma, nota_clase,
                         nota_final, pass_yn, diploma,
                         moodle_course_id, moodle_user_id, fecha_importacion, fecha_evaluacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), CURDATE())";
                $local->prepare($sql)->execute([
                    $edicion, $email, $grupo_acuity, $profesor, $nivel,
                    $nota_oral, $nota_plataforma, $nota_clase,
                    $nota_final, $pass_yn, $diploma,
                    $course_id, $user_id
                ]);
                $stats['nuevos']++;
            }
        } catch (PDOException $e) {
            echo "  [ERROR] $email: " . $e->getMessage() . "\n";
            $stats['errores']++;
        }
    }
}

echo "\n============================================================\n";
echo "  RESUMEN\n";
echo "============================================================\n";
echo "  Nuevos: {$stats['nuevos']}\n";
echo "  Actualizados: {$stats['actualizados']}\n";
echo "  Errores: {$stats['errores']}\n";
echo "============================================================\n";

// Mostrar resumen de notas
echo "\n  ESTADO DE NOTAS:\n";
$stmt = $local->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN nota_oral IS NOT NULL THEN 1 ELSE 0 END) as con_oral,
    SUM(CASE WHEN nota_plataforma IS NOT NULL THEN 1 ELSE 0 END) as con_plataforma,
    SUM(CASE WHEN nota_clase IS NOT NULL THEN 1 ELSE 0 END) as con_clase,
    SUM(CASE WHEN nota_final IS NOT NULL THEN 1 ELSE 0 END) as con_final,
    SUM(CASE WHEN pass_yn = 'Y' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN diploma IS NOT NULL THEN 1 ELSE 0 END) as con_diploma
FROM mdl_cesce_calificaciones WHERE edicion = '25.2'");
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Total alumnos: {$resumen['total']}\n";
echo "  Con nota oral: {$resumen['con_oral']}\n";
echo "  Con nota plataforma: {$resumen['con_plataforma']}\n";
echo "  Con nota clase: {$resumen['con_clase']}\n";
echo "  Con nota final: {$resumen['con_final']}\n";
echo "  Aprobados: {$resumen['aprobados']}\n";
echo "  Con diploma: {$resumen['con_diploma']}\n";
echo "============================================================\n";
?>
