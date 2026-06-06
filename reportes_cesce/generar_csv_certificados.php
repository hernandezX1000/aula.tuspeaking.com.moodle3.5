<?php
/**
 * Generar CSV para Canva Bulk Create - Certificados CESCE
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

// Archivo CSV
$archivo = '/home/aulatuspeaking/www/app/moodle/reportes_cesce/certificados_cesce_25.2.csv';
$fp = fopen($archivo, 'w');

// Cabecera
fputcsv($fp, ['tipo_certificado', 'nombre', 'idioma', 'nivel', 'horas', 'fecha_inicio', 'fecha_fin']);

$superacion = 0;
$participacion = 0;
$sin_certificado = 0;

foreach ($alumnos as $a) {
    // Para prueba: si nota es NULL, usar valor aleatorio entre 4 y 9
    $nota = $a['nota_final'] ?? rand(40, 90) / 10;
    
    if ($nota >= 7.5) {
        $tipo = 'superación';
        $superacion++;
    } elseif ($nota >= 5) {
        $tipo = 'participación';
        $participacion++;
    } else {
        $tipo = 'sin_certificado';
        $sin_certificado++;
        continue; // No generar certificado
    }
    
    fputcsv($fp, [
        $tipo,
        $a['nombre'],
        $a['idioma'],
        $a['nivel'],
        $a['horas_curso'],
        formatearFecha($a['fecha_inicio_curso'], $meses),
        formatearFecha($a['fecha_fin_curso'], $meses)
    ]);
}

fclose($fp);

echo "============================================================\n";
echo "  CSV Generado: $archivo\n";
echo "============================================================\n";
echo "  Certificados de superación: $superacion\n";
echo "  Certificados de participación: $participacion\n";
echo "  Sin certificado: $sin_certificado\n";
echo "============================================================\n";
?>
