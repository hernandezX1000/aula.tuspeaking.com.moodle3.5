<?php
$DB_CONFIG = ['host'=>'localhost','database'=>'aulatuspeaking35','user'=>'moodle35','password'=>'TuspeakingFix2025!'];
try { $pdo = new PDO("mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['database']};charset=utf8mb4",$DB_CONFIG['user'],$DB_CONFIG['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch(PDOException $e) { die("Error: ".$e->getMessage()); }

function extraerNota($v) {
    if (preg_match('/\((\d+(?:\.\d+)?)\)/', $v, $m)) return floatval($m[1]);
    if (preg_match('/\((\d+)\s*-\s*(\d+)\)/', $v, $m)) return (floatval($m[1]) + floatval($m[2])) / 2;
    if (preg_match('/Excelent/i', $v)) return 9.5;
    if (preg_match('/Very Good/i', $v)) return 7.5;
    if (preg_match('/Good/i', $v)) return 5.5;
    if (preg_match('/Not so good/i', $v)) return 3.5;
    if (preg_match('/Bad/i', $v)) return 1.5;
    return 0;
}

function mapRec($v) {
    if (preg_match('/next|up/i', $v)) return 'subir_nivel';
    if (preg_match('/down|lower/i', $v)) return 'bajar_nivel';
    return 'mantener_nivel';
}

function calcPromedio($row, $cols) {
    if (empty($cols)) return 0;
    $sum = 0;
    foreach ($cols as $c) {
        $sum += extraerNota(isset($row[$c]) ? $row[$c] : '');
    }
    return $sum / count($cols);
}

function importarCSV($pdo, $archivo, $nivel) {
    if (!file_exists($archivo)) { echo "ERROR: $archivo\n"; return 0; }
    $fp = fopen($archivo, 'r');
    $h = fgetcsv($fp);
    $cols = array('g'=>array(),'p'=>array(),'c'=>array());
    foreach ($h as $i => $col) {
        $l = strtolower($col);
        if (strpos($l,'teacher')!==false && !isset($cols['teacher'])) $cols['teacher']=$i;
        if (strpos($l,'student')!==false && !isset($cols['student'])) $cols['student']=$i;
        if (strpos($l,'justify')!==false) $cols['justify']=$i;
        if (strpos($l,'should the student')!==false) $cols['rec']=$i;
        if (strpos($l,'recommended level')!==false) $cols['level']=$i;
        if (strpos($l,'oral exam')!==false) $cols['oral']=$i;
        if (strpos($l,'participation')!==false) $cols['part']=$i;
        if (strpos($l,'homework status')!==false) $cols['hw']=$i;
        if (strpos($l,'homework desc')!==false) $cols['hwd']=$i;
        if (strpos($l,'submission')!==false) $cols['date']=$i;
        if (strpos($l,'grammar')!==false) $cols['g'][]=$i;
        if (strpos($l,'pronunciation')!==false) $cols['p'][]=$i;
        if (strpos($l,'communicative')!==false) $cols['c'][]=$i;
    }
    $imp=0; $dup=0;
    while (($r = fgetcsv($fp)) !== false) {
        $student = trim(isset($r[$cols['student']]) ? $r[$cols['student']] : '');
        if (empty($student)) continue;
        
        $g = calcPromedio($r, $cols['g']);
        $p = calcPromedio($r, $cols['p']);
        $c = calcPromedio($r, $cols['c']);
        $oral = extraerNota(isset($r[$cols['oral']]) ? $r[$cols['oral']] : '');
        $part = extraerNota(isset($r[$cols['part']]) ? $r[$cols['part']] : '');
        $hw = extraerNota(isset($r[$cols['hw']]) ? $r[$cols['hw']] : '');
        $nota = round(($g*2+$p*2+$c*2+$oral+$part+$hw)/6, 2);
        $tipo = $nota >= 7.5 ? 'superacion' : 'participacion';
        
        $st = $pdo->prepare("SELECT id FROM mdl_coding_evaluaciones WHERE alumno_nombre=? AND nivel_evaluado=?");
        $st->execute(array($student,$nivel));
        if ($st->fetch()) { $dup++; continue; }
        
        $fecha = isset($r[$cols['date']]) ? $r[$cols['date']] : date('Y-m-d');
        $teacher = isset($r[$cols['teacher']]) ? $r[$cols['teacher']] : '';
        $rec = mapRec(isset($r[$cols['rec']]) ? $r[$cols['rec']] : '');
        $lev = isset($r[$cols['level']]) ? $r[$cols['level']] : $nivel;
        $just = isset($r[$cols['justify']]) ? $r[$cols['justify']] : '';
        $hwd = isset($r[$cols['hwd']]) ? $r[$cols['hwd']] : '';
        
        $st = $pdo->prepare("INSERT INTO mdl_coding_evaluaciones (empresa_id,fecha_evaluacion,profesor,alumno_nombre,nivel_evaluado,nota_grammar_vocabulary,nota_pronunciation,nota_communicative,nota_oral_exam,nota_participacion,nota_homework,nota_final,recomendacion,nivel_recomendado,justificacion,comentarios_homework,certificado_tipo,fecha_creacion) VALUES (0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $st->execute(array($fecha,$teacher,$student,$nivel,round($g,2),round($p,2),round($c,2),$oral,$part,$hw,$nota,$rec,$lev,$just,$hwd,$tipo));
        $imp++;
    }
    fclose($fp);
    echo "$nivel: Importados=$imp Duplicados=$dup\n";
    return $imp;
}

echo "=== IMPORTACION LEGACY ===\n";
$b = __DIR__.'/legacy/';
$t = importarCSV($pdo,$b.'legacy_jotform_A2.csv','A2');
$t += importarCSV($pdo,$b.'legacy_jotform_B1.csv','B1');
$t += importarCSV($pdo,$b.'legacy_jotform_B2.csv','B2');
$t += importarCSV($pdo,$b.'legacy_jotform_C1.csv','C1');
echo "=== TOTAL: $t ===\n";
