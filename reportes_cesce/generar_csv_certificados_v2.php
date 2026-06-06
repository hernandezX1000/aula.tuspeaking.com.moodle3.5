<?php
/**
 * Generar CSV para Canva - Certificados CESCE v2
 * Nombres en formato Title Case (Luis Verdalles Guzmán)
 */

$local = new PDO("mysql:host=localhost;dbname=aulatuspeaking35;charset=utf8mb4", 
                 "moodle35", "TuspeakingFix2025!");

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

function formatearFecha($fecha, $meses) {
    if (!$fecha) return '';
    $parts = explode('-', $fecha);
    $dia = intval($parts[2]);
    $mes = $meses[$parts[1]];
    $anio = $parts[0];
    return "$dia de $mes de $anio";
}

function formatearNombre($nombre) {
    // Convertir a minúsculas y luego capitalizar cada palabra
    $nombre = mb_strtolower($nombre, 'UTF-8');
    $nombre = mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
    
    // Corregir preposiciones y artículos que deben ir en minúscula
    $excepciones = [' De ', ' Del ', ' La ', ' Las ', ' Los ', ' El ', ' Y '];
    $reemplazos = [' de ', ' del ', ' la ', ' las ', ' los ', ' el ', ' y '];
    $nombre = str_replace($excepciones, $reemplazos, $nombre);
    
    return $nombre;
}

$sql = "SELECT 
    CONCAT(e.firstname, ' ', e.lastname) as nombre,
    c.nivel,
    c.idioma,
    c.horas_curso,
    c.fecha_inicio_curso,
    c.fecha_fin_curso,
    c.nota_final
FROM mdl_cesce_calificaciones c
JOIN mdl_cesce_empleados e ON c.empleado_email = e.email
WHERE c.edicion = '25.2'
ORDER BY c.idioma, c.grupo, e.lastname";

$stmt = $local->query($sql);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$archivo = '/home/aulatuspeaking/www/app/moodle/reportes_cesce/certificados_cesce_25.2_v2.csv';
$fp = fopen($archivo, 'w');

// Cabecera
fputcsv($fp, ['titulo', 'nombre', 'parrafo']);

$superacion = 0;
$participacion = 0;
$sin_certificado = 0;

foreach ($alumnos as $a) {
    // Para prueba: si nota es NULL, usar valor aleatorio
    $nota = $a['nota_final'] ?? rand(40, 90) / 10;
    
    if ($nota >= 7.5) {
        $titulo = 'Certificado de superación';
        $verbo = 'ha finalizado satisfactoriamente';
        $superacion++;
    } elseif ($nota >= 5) {
        $titulo = 'Certificado de participación';
        $verbo = 'ha participado en';
        $participacion++;
    } else {
        $sin_certificado++;
        continue;
    }
    
    $nombre = formatearNombre($a['nombre']);
    $fecha_inicio = formatearFecha($a['fecha_inicio_curso'], $meses);
    $fecha_fin = formatearFecha($a['fecha_fin_curso'], $meses);
    
    $parrafo = "$verbo el curso de {$a['idioma']} de nivel {$a['nivel']} organizado por CESCE con una duración de {$a['horas_curso']} horas, desde el $fecha_inicio al $fecha_fin habiendo adquirido las competencias previstas en la formación.";
    
    fputcsv($fp, [
        $titulo,
        $nombre,
        $parrafo
    ]);
}

fclose($fp);

echo "============================================================\n";
echo "  CSV v2 Generado: $archivo\n";
echo "============================================================\n";
echo "  Certificados de superación: $superacion\n";
echo "  Certificados de participación: $participacion\n";
echo "  Sin certificado: $sin_certificado\n";
echo "============================================================\n";
?>
