<?php
/**
 * ============================================================
 * CODING - Importar Notas desde Moodle CESCE
 * ============================================================
 * Archivo: importar_notas_moodle_cesce.php
 * Ruta: /home/aulatuspeaking/www/app/moodle/reportes_cesce/
 * Fecha: 2025-12-28
 * ============================================================
 */

// BD Local (aula.tuspeaking.com)
$local_host = 'localhost';
$local_db = 'aulatuspeaking35';
$local_user = 'moodle35';
$local_pass = 'TuspeakingFix2025!';

// BD Remota (cesce.tuspeaking.com)
$remote_host = 'mysql-5603.dinaserver.com';
$remote_db = 'aulatuspeaking35cesce';
$remote_user = 'moodle35cesce';
$remote_pass = 'Eemcdmoce2000*';

$edicion = '25.2';

echo "============================================================\n";
echo "  CODING - Importar Notas Moodle CESCE\n";
echo "  Edicion: $edicion\n";
echo "  Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// Conectar BD local
try {
    $local = new PDO("mysql:host=$local_host;dbname=$local_db;charset=utf8mb4", $local_user, $local_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "[OK] Conectado a BD local (aula.tuspeaking.com)\n";
} catch (PDOException $e) {
    die("[ERROR] BD local: " . $e->getMessage() . "\n");
}

// Conectar BD remota
try {
    $remote = new PDO("mysql:host=$remote_host;dbname=$remote_db;charset=utf8mb4", $remote_user, $remote_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "[OK] Conectado a BD remota (cesce.tuspeaking.com)\n\n";
} catch (PDOException $e) {
    die("[ERROR] BD remota: " . $e->getMessage() . "\n");
}

// Obtener cursos mapeados
$stmt = $local->query("SELECT moodle_course_id, moodle_course_name, acuity_type FROM mdl_cesce_mapeo_cursos WHERE edicion = '$edicion'");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Cursos mapeados: " . count($cursos) . "\n\n";

$total_importados = 0;
$total_actualizados = 0;
$total_errores = 0;

foreach ($cursos as $curso) {
    $course_id = $curso['moodle_course_id'];
    $course_name = $curso['moodle_course_name'];
    $grupo_acuity = $curso['acuity_type'];
    
    echo "------------------------------------------------------------\n";
    echo "Curso: $course_name (ID: $course_id)\n";
    
    // Obtener notas finales del curso
    $sql = "SELECT 
                c.id as course_id,
                c.shortname as curso,
                u.id as user_id,
                u.email,
                CONCAT(u.firstname, ' ', u.lastname) as alumno,
                ROUND(gg.finalgrade, 2) as nota_final,
                FROM_UNIXTIME(gg.timemodified) as fecha
            FROM mdl_grade_grades gg
            JOIN mdl_grade_items gi ON gg.itemid = gi.id
            JOIN mdl_course c ON gi.courseid = c.id
            JOIN mdl_user u ON gg.userid = u.id
            WHERE c.id = :course_id
            AND gi.itemtype = 'course'
            AND gg.finalgrade IS NOT NULL";
    
    $stmt = $remote->prepare($sql);
    $stmt->execute(['course_id' => $course_id]);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Alumnos con nota: " . count($notas) . "\n";
    
    foreach ($notas as $nota) {
        $email = strtolower(trim($nota['email']));
        $nota_final = $nota['nota_final'];
        $user_id = $nota['user_id'];
        
        // Detectar nivel del nombre del curso
        $nivel = 'B1';
        if (preg_match('/(A1|A2|B1\.2|B1|B2|C1|C2)/i', $course_name, $m)) {
            $nivel = strtoupper($m[1]);
        }
        
        // Detectar profesor
        $profesor = '';
        $profesores = ['Kate', 'Steve', 'RJ', 'Rj', 'Jessica', 'Amber', 'Candice', 'Odile', 'Eldina', 'Javier', 'Michaela'];
        foreach ($profesores as $p) {
            if (stripos($course_name, $p) !== false) {
                $profesor = $p;
                break;
            }
        }
        
        // Verificar si existe
        $stmt = $local->prepare("SELECT id FROM mdl_cesce_calificaciones WHERE empleado_email = ? AND edicion = ?");
        $stmt->execute([$email, $edicion]);
        $existe = $stmt->fetch();
        
        try {
            if ($existe) {
                // Actualizar
                $sql = "UPDATE mdl_cesce_calificaciones SET 
                        grupo = ?, profesor = ?, nivel = ?, nota_final = ?,
                        moodle_course_id = ?, moodle_user_id = ?, fecha_importacion = NOW(),
                        pass_yn = CASE WHEN ? >= 5 THEN 'Y' ELSE 'N' END
                        WHERE id = ?";
                $local->prepare($sql)->execute([
                    $grupo_acuity, $profesor, $nivel, $nota_final,
                    $course_id, $user_id, $nota_final, $existe['id']
                ]);
                $total_actualizados++;
            } else {
                // Insertar
                $sql = "INSERT INTO mdl_cesce_calificaciones 
                        (edicion, empleado_email, grupo, profesor, nivel, nota_final,
                         moodle_course_id, moodle_user_id, fecha_importacion, pass_yn, fecha_evaluacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, CURDATE())";
                $local->prepare($sql)->execute([
                    $edicion, $email, $grupo_acuity, $profesor, $nivel, $nota_final,
                    $course_id, $user_id, ($nota_final >= 5 ? 'Y' : 'N')
                ]);
                $total_importados++;
            }
        } catch (PDOException $e) {
            echo "  [ERROR] $email: " . $e->getMessage() . "\n";
            $total_errores++;
        }
    }
}

echo "\n============================================================\n";
echo "  RESUMEN\n";
echo "============================================================\n";
echo "  Nuevos importados: $total_importados\n";
echo "  Actualizados: $total_actualizados\n";
echo "  Errores: $total_errores\n";
echo "  Total procesados: " . ($total_importados + $total_actualizados) . "\n";
echo "============================================================\n";
?>
