<?php
/**
 * Generador de Fichas FUNDAE - tuSpeaking
 * Versión 1.0 - Enero 2026
 */

class SimpleXLSXWriter {
    private $sheets = [];
    private $currentSheet = 0;
    
    public function __construct() {
        $this->addSheet('Ficha');
    }
    
    public function addSheet($name) {
        $this->sheets[] = [
            'name' => substr(preg_replace('/[\\\\\\/?*\\[\\]:\'"]/', '', $name), 0, 31),
            'rows' => [],
            'colWidths' => []
        ];
        $this->currentSheet = count($this->sheets) - 1;
        return $this;
    }
    
    public function setColumnWidth($col, $width) {
        $this->sheets[$this->currentSheet]['colWidths'][$col] = $width;
        return $this;
    }
    
    public function addRow($data, $styles = []) {
        $this->sheets[$this->currentSheet]['rows'][] = ['data' => $data, 'styles' => $styles];
        return $this;
    }
    
    public function download($filename) {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->save($tempFile);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }
    
    public function save($filename) {
        $zip = new ZipArchive();
        if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot create XLSX file");
        }
        $zip->addFromString('[Content_Types].xml', $this->getContentTypes());
        $zip->addFromString('_rels/.rels', $this->getRels());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRels());
        $zip->addFromString('xl/workbook.xml', $this->getWorkbook());
        $zip->addFromString('xl/styles.xml', $this->getStyles());
        $zip->addFromString('xl/sharedStrings.xml', $this->getSharedStrings());
        foreach ($this->sheets as $idx => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($idx + 1) . '.xml', $this->getSheet($idx));
        }
        $zip->close();
    }
    
    private function getContentTypes() {
        $sheets = '';
        foreach ($this->sheets as $idx => $sheet) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . ($idx + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' . $sheets . '</Types>';
    }
    
    private function getRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }
    
    private function getWorkbookRels() {
        $sheets = '';
        foreach ($this->sheets as $idx => $sheet) {
            $sheets .= '<Relationship Id="rId' . ($idx + 3) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($idx + 1) . '.xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' . $sheets . '</Relationships>';
    }
    
    private function getWorkbook() {
        $sheets = '';
        foreach ($this->sheets as $idx => $sheet) {
            $sheets .= '<sheet name="' . htmlspecialchars($sheet['name']) . '" sheetId="' . ($idx + 1) . '" r:id="rId' . ($idx + 3) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>';
    }
    
    private function getStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF008BA3"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1"/></xf></cellXfs></styleSheet>';
    }
    
    private function getSharedStrings() {
        $strings = [];
        foreach ($this->sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                foreach ($row['data'] as $cell) {
                    if (is_string($cell) && !isset($strings[$cell])) {
                        $strings[$cell] = count($strings);
                    }
                }
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach (array_keys($strings) as $str) {
            $xml .= '<si><t>' . htmlspecialchars($str) . '</t></si>';
        }
        return $xml . '</sst>';
    }
    
    private function getSheet($sheetIndex) {
        $sheet = $this->sheets[$sheetIndex];
        $strings = [];
        foreach ($this->sheets as $s) {
            foreach ($s['rows'] as $row) {
                foreach ($row['data'] as $cell) {
                    if (is_string($cell) && !isset($strings[$cell])) {
                        $strings[$cell] = count($strings);
                    }
                }
            }
        }
        $cols = '';
        if (!empty($sheet['colWidths'])) {
            $cols = '<cols>';
            foreach ($sheet['colWidths'] as $col => $width) {
                $cols .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }
        $rows = '';
        foreach ($sheet['rows'] as $rowIdx => $row) {
            $cells = '';
            foreach ($row['data'] as $colIdx => $cell) {
                $colLetter = $this->getColumnLetter($colIdx);
                $cellRef = $colLetter . ($rowIdx + 1);
                $style = isset($row['styles'][$colIdx]) ? ' s="' . $row['styles'][$colIdx] . '"' : '';
                if (is_string($cell)) {
                    $cells .= '<c r="' . $cellRef . '"' . $style . ' t="s"><v>' . $strings[$cell] . '</v></c>';
                } elseif (is_numeric($cell)) {
                    $cells .= '<c r="' . $cellRef . '"' . $style . '><v>' . $cell . '</v></c>';
                }
            }
            $rows .= '<row r="' . ($rowIdx + 1) . '">' . $cells . '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $cols . '<sheetData>' . $rows . '</sheetData></worksheet>';
    }
    
    private function getColumnLetter($col) {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intval($col / 26) - 1;
        }
        return $letter;
    }
}

$db_host = 'localhost';
$db_name = 'aulatuspeaking35';
$db_user = 'moodle35';
$db_pass = 'TuspeakingFix2025!';

$centro = [
    'razon_social' => 'MICRO VENTURES S.L.',
    'cif' => 'B71259352',
    'direccion' => 'C/ NACEDERO DEL UREDERRA 7, 1ºB SARRIGUREN (31621)',
    'horarios' => 'De lunes a viernes, de 8:30 a 15:30 y de 15:00 a 20:00h',
    'contacto_nombre' => 'Hansel Fernández',
    'contacto_telefono' => '686256425'
];

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $pdo = null;
}

$profesores = [];
$profesores_por_idioma = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT t.teacher_id as id, 
                   CONCAT(u.firstname, ' ', u.lastname) as nombre_completo, 
                   t.zoom_email as email, 
                   COALESCE(t.pasaporte_dni, u.idnumber, '') as pasaporte,
                   COALESCE(t.telefono, '') as telefono,
                   GROUP_CONCAT(ti.idioma ORDER BY ti.idioma SEPARATOR ', ') as idiomas
            FROM teacher_zoom_map t 
            JOIN mdl_user u ON t.teacher_id = u.id 
            LEFT JOIN teacher_idiomas ti ON t.teacher_id = ti.teacher_id
            WHERE t.is_active = 1 
            GROUP BY t.teacher_id, u.firstname, u.lastname, t.zoom_email, t.pasaporte_dni, t.telefono
            ORDER BY u.firstname, u.lastname
        ");
        $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($profesores as $p) {
            if ($p['idiomas']) {
                foreach (explode(', ', $p['idiomas']) as $idioma) {
                    $profesores_por_idioma[trim($idioma)][] = $p;
                }
            }
        }
    } catch(Exception $e) {}
}

$credenciales_fundae = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM fundae_credenciales ORDER BY anio DESC");
        $credenciales_fundae = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

// Cargar fichas de formación con empresas y grupos
$fichas_formacion = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT f.id, f.empresa_id, e.nombre as empresa_nombre, f.codigo_edicion, f.idiomas, 
                   f.fecha_inicio, f.fecha_fin, f.modalidad_clases, f.bonificacion
            FROM own_operaciones_fichas f 
            INNER JOIN own_empresas e ON e.id = f.empresa_id 
            ORDER BY f.fecha_inicio DESC
        ");
        $fichas_formacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

// AJAX: Obtener grupos de una ficha
if (isset($_GET['ajax_grupos'])) {
    header('Content-Type: application/json');
    $fid = intval($_GET['ajax_grupos']);
    try {
        $stmt = $pdo->prepare("SELECT g.id, g.nombre, g.nivel, 
            (SELECT COUNT(*) FROM own_operaciones_alumnos WHERE grupo_id=g.id) as num_alumnos
            FROM own_operaciones_grupos g WHERE g.ficha_id = ? ORDER BY g.nivel, g.nombre");
        $stmt->execute([$fid]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) { echo json_encode([]); }
    exit;
}

// AJAX: Obtener alumnos de un grupo (o todos de una ficha)
if (isset($_GET['ajax_alumnos'])) {
    header('Content-Type: application/json');
    $grupo_id = intval($_GET['ajax_alumnos']);
    $ficha_id_param = intval($_GET['ficha_id'] ?? 0);
    try {
        if ($grupo_id > 0) {
            $stmt = $pdo->prepare("SELECT CONCAT(alumno_nombre, ' ', alumno_apellidos) as nombre, alumno_email as email FROM own_operaciones_alumnos WHERE grupo_id = ? AND (estado != 'baja' OR estado IS NULL) ORDER BY alumno_apellidos, alumno_nombre");
            $stmt->execute([$grupo_id]);
        } elseif ($ficha_id_param > 0) {
            $stmt = $pdo->prepare("SELECT CONCAT(alumno_nombre, ' ', alumno_apellidos) as nombre, alumno_email as email FROM own_operaciones_alumnos WHERE ficha_id = ? AND (estado != 'baja' OR estado IS NULL) ORDER BY alumno_apellidos, alumno_nombre");
            $stmt->execute([$ficha_id_param]);
        } else {
            echo json_encode([]);
            exit;
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) { echo json_encode([]); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_credencial'])) {
    header('Content-Type: application/json');
    $anio = intval($_POST['anio'] ?? 0);
    $usuario = trim($_POST['usuario'] ?? '');
    $email_fundae = trim($_POST['email_fundae'] ?? '');
    $password_fundae = trim($_POST['password_fundae'] ?? '');
    $url_plataforma = trim($_POST['url_plataforma'] ?? '');
    if (!$anio || !$usuario) { echo json_encode(['error' => 'Año y usuario obligatorios']); exit; }
    try {
        $stmt = $pdo->prepare("INSERT INTO fundae_credenciales (anio, usuario, email_fundae, password_fundae, url_plataforma) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE usuario=VALUES(usuario), email_fundae=VALUES(email_fundae), password_fundae=VALUES(password_fundae), url_plataforma=VALUES(url_plataforma)");
        $stmt->execute([$anio, $usuario, $email_fundae, $password_fundae, $url_plataforma]);
        echo json_encode(['ok' => true]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

$objetivos_aula_virtual = [
    'A1' => "Objetivo general\nDesarrollar la competencia comunicativa básica en lengua inglesa del alumnado en un nivel A1.\n\nObjetivos específicos\n- Comprender y utilizar expresiones cotidianas de uso muy frecuente.\n- Presentarse a sí mismo y a otros.\n- Participar en conversaciones muy sencillas.\n- Utilizar estructuras gramaticales básicas.",
    'A2' => "Objetivo general\nDesarrollar la competencia comunicativa en lengua inglesa del alumnado en un nivel A2.\n\nObjetivos específicos\n- Comprender frases y expresiones de uso frecuente.\n- Comunicarse en tareas simples y cotidianas.\n- Describir aspectos de su pasado y entorno.\n- Utilizar estructuras gramaticales básicas con corrección.",
    'B1' => "Objetivo general\nDesarrollar la competencia comunicativa en lengua inglesa del alumnado en un nivel B1, mejorando la fluidez, corrección y comprensión oral en situaciones habituales del ámbito profesional y social.\n\nObjetivos específicos\n- Comprender mensajes orales claros y estructurados sobre temas cotidianos y profesionales.\n- Participar en conversaciones y reuniones virtuales expresando opiniones e ideas.\n- Describir situaciones, procesos y experiencias en diferentes tiempos verbales.\n- Utilizar con corrección las estructuras gramaticales propias del nivel B1.\n- Ampliar y consolidar vocabulario de uso frecuente y profesional.\n- Mejorar la pronunciación, entonación y fluidez.",
    'B1+' => "Objetivo general\nDesarrollar la competencia comunicativa en lengua inglesa del alumnado en un nivel B1–B1+.\n\nObjetivos específicos\n- Comprender mensajes orales claros sobre temas cotidianos y profesionales.\n- Participar en conversaciones y reuniones virtuales sencillas.\n- Describir situaciones y experiencias utilizando estructuras gramaticales propias del nivel.\n- Utilizar correctamente los tiempos verbales básicos y conectores frecuentes.",
    'B1-B2' => "Objetivo general\nDesarrollar la competencia comunicativa en lengua inglesa del alumnado en un nivel B1–B2, mejorando la fluidez, corrección y comprensión oral.\n\nObjetivos específicos\n- Comprender mensajes orales claros y estructurados sobre temas cotidianos y profesionales.\n- Participar en conversaciones y reuniones virtuales expresando opiniones, ideas y experiencias.\n- Describir situaciones, procesos y experiencias en diferentes tiempos verbales.\n- Utilizar con corrección las estructuras gramaticales propias de los niveles B1–B2.\n- Ampliar y consolidar vocabulario de uso frecuente y profesional.\n- Mejorar la pronunciación, entonación y fluidez en la expresión oral.",
    'B2' => "Objetivo general\nDesarrollar la competencia comunicativa en lengua inglesa del alumnado en un nivel B2, mejorando la fluidez, corrección y eficacia comunicativa.\n\nObjetivos específicos\n- Comprender las ideas principales y los detalles relevantes de discursos orales claros.\n- Participar activamente en conversaciones y reuniones virtuales con fluidez razonable.\n- Defender argumentos y justificar decisiones utilizando estructuras gramaticales adecuadas.\n- Utilizar con corrección las estructuras gramaticales propias del nivel B2.\n- Ampliar y consolidar vocabulario de uso frecuente y profesional.",
    'B2-C1' => "Objetivo general\nDesarrollar y consolidar la competencia comunicativa en lengua inglesa del alumnado en un nivel B2–C1, potenciando la fluidez, precisión y adecuación discursiva.\n\nObjetivos específicos\n- Comprender y producir discursos orales complejos, estructurados y coherentes.\n- Interactuar con fluidez y espontaneidad en conversaciones, debates y reuniones virtuales.\n- Expresar opiniones, argumentos y matices con precisión léxica y gramatical.\n- Utilizar estructuras gramaticales avanzadas con alto grado de corrección.\n- Ampliar y consolidar vocabulario especializado.",
    'C1' => "Objetivo general\nConsolidar y perfeccionar la competencia comunicativa en lengua inglesa del alumnado en un nivel C1.\n\nObjetivos específicos\n- Comprender discursos extensos y complejos, incluyendo matices implícitos.\n- Expresarse con fluidez, espontaneidad y precisión en cualquier situación comunicativa.\n- Producir textos orales claros, bien estructurados y detallados sobre temas complejos.\n- Utilizar estructuras gramaticales avanzadas con alto grado de corrección.\n- Dominar vocabulario amplio y especializado, incluyendo expresiones idiomáticas."
];

$contenidos_aula_virtual = [
    'A1' => "Contenidos formativos – Nivel A1\n\n- Expresiones cotidianas de uso muy frecuente y frases sencillas.\n- Presentación personal e información básica.\n- Vocabulario básico: números, colores, días, meses, familia.\n- Estructuras gramaticales básicas: presente simple, artículos, pronombres.\n- Pronunciación básica y entonación de frases simples.",
    'A2' => "Contenidos formativos – Nivel A2\n\n- Expresiones y frases de uso frecuente.\n- Comunicación en tareas simples y cotidianas.\n- Descripción del pasado y entorno inmediato.\n- Estructuras gramaticales: presente simple y continuo, pasado simple, futuro básico.\n- Vocabulario relacionado con información personal, compras, lugares, trabajo básico.",
    'B1' => "Contenidos formativos – Nivel B1\n\n- Desarrollo de la expresión e interacción oral en situaciones cotidianas y profesionales.\n- Participación en conversaciones y reuniones virtuales sencillas.\n- Uso y consolidación de estructuras gramaticales básicas e intermedias.\n- Ampliación de vocabulario de uso frecuente y profesional.\n- Mejora de la comprensión oral, pronunciación, entonación y fluidez.\n- Aplicación de estrategias comunicativas para reformular ideas y mantener la interacción.",
    'B1+' => "Contenidos formativos – Nivel B1–B1+\n\n- Desarrollo de la expresión e interacción oral en situaciones cotidianas y profesionales.\n- Participación en conversaciones y reuniones virtuales sencillas.\n- Uso y consolidación de estructuras gramaticales básicas.\n- Ampliación de vocabulario de uso frecuente y profesional.\n- Mejora de la comprensión oral, pronunciación, entonación y fluidez.",
    'B1-B2' => "Contenidos formativos – Nivel B1–B2\n\n- Desarrollo de la expresión e interacción oral en situaciones cotidianas y profesionales.\n- Participación en conversaciones y reuniones virtuales, expresando opiniones y experiencias.\n- Uso y consolidación de estructuras gramaticales básicas e intermedias.\n- Ampliación de vocabulario de uso frecuente y profesional.\n- Mejora de la comprensión oral, pronunciación, entonación y fluidez.\n- Aplicación de estrategias comunicativas para reformular ideas y mantener la interacción.",
    'B2' => "Contenidos formativos – Nivel B2\n\n- Expresión e interacción oral en situaciones habituales del ámbito profesional y social.\n- Participación en conversaciones, debates y reuniones virtuales.\n- Consolidación de estructuras gramaticales de nivel intermedio-alto.\n- Ampliación y uso correcto de vocabulario de uso frecuente y profesional.\n- Comprensión oral de discursos claros, presentaciones y conversaciones estructuradas.\n- Mejora de la pronunciación, entonación y fluidez en la comunicación oral.",
    'B2-C1' => "Contenidos formativos – Nivel B2–C1\n\n- Desarrollo de la expresión e interacción oral avanzada en contextos profesionales y sociales.\n- Participación en conversaciones, debates y reuniones virtuales, defendiendo opiniones.\n- Consolidación de estructuras gramaticales avanzadas.\n- Ampliación de vocabulario avanzado y semiespecializado.\n- Mejora de la comprensión oral, pronunciación, entonación y claridad expositiva.",
    'C1' => "Contenidos formativos – Nivel C1\n\n- Expresión e interacción oral avanzada en contextos profesionales, académicos y sociales.\n- Producción de discursos claros, bien estructurados y detallados sobre temas complejos.\n- Dominio de estructuras gramaticales avanzadas y matices del idioma.\n- Vocabulario amplio y especializado, expresiones idiomáticas y colocaciones.\n- Comprensión de discursos extensos con matices implícitos."
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar'])) {
    $modalidad = $_POST['modalidad'] ?? 'aula_virtual';
    $denominacion = $_POST['denominacion'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $num_horas = $_POST['num_horas'] ?? '';
    $calendario_horarios = $_POST['calendario_horarios'] ?? '';
    $nivel = $_POST['nivel'] ?? 'B1';
    
    $formadores = [];
    if (!empty($_POST['formadores']) && is_array($_POST['formadores'])) {
        foreach ($_POST['formadores'] as $f) {
            if (!empty($f['nombre'])) {
                $formadores[] = [
                    'nombre' => $f['nombre'], 'pasaporte' => $f['pasaporte'] ?? '',
                    'email' => $f['email'] ?? '', 'telefono' => $f['telefono'] ?? $centro['contacto_telefono'],
                    'horas' => $f['horas'] ?: $num_horas
                ];
            }
        }
    }
    if (empty($formadores)) {
        $formadores[] = ['nombre'=>$_POST['formador_nombre']??'','pasaporte'=>$_POST['formador_pasaporte']??'','email'=>$_POST['formador_email']??'','telefono'=>$_POST['formador_telefono']??$centro['contacto_telefono'],'horas'=>($_POST['formador_horas']??'')?:$num_horas];
    }
    
    $fundae_usuario = $_POST['fundae_usuario'] ?? '';
    $fundae_email = $_POST['fundae_email'] ?? '';
    $fundae_password = $_POST['fundae_password'] ?? '';
    $fundae_url = $_POST['fundae_url'] ?? '';
    $medio_aula = $_POST['medio_aula'] ?? 'ZOOM';
    $url_conexion = $_POST['url_conexion'] ?? '';
    $tutorizacion = $_POST['tutorizacion'] ?? 'Correo electrónico, videoconferencia';
    $objetivos = $_POST['objetivos'] ?: ($objetivos_aula_virtual[$nivel] ?? '');
    $contenidos = $_POST['contenidos'] ?: ($contenidos_aula_virtual[$nivel] ?? '');
    
    $participantes = [];
    if (!empty($_POST['participantes'])) {
        foreach (explode("\n", trim($_POST['participantes'])) as $linea) {
            $linea = trim($linea); if (!empty($linea)) {
                $partes = preg_split('/[\t|,;]/', $linea, 2);
                $nombre = trim($partes[0] ?? ''); $email = trim($partes[1] ?? '');
                if (!empty($nombre)) $participantes[] = ['nombre' => $nombre, 'email' => $email];
            }
        }
    }
    $sesiones = [];
    if (!empty($_POST['sesiones'])) {
        foreach (explode("\n", trim($_POST['sesiones'])) as $linea) {
            $linea = trim($linea); if (!empty($linea)) {
                $partes = preg_split('/[\t|]/', $linea, 2);
                $fecha = trim($partes[0] ?? ''); $url = trim($partes[1] ?? '') ?: $url_conexion;
                if (!empty($fecha)) $sesiones[] = ['fecha' => $fecha, 'url' => $url];
            }
        }
    }
    
    $xlsx = new SimpleXLSXWriter();
    $es_individual = ($modalidad === 'teleformacion' && count($participantes) > 1);
    if ($es_individual) {
        $first = true;
        foreach ($participantes as $participante) {
            if (!$first) $xlsx->addSheet(substr($participante['nombre'], 0, 31));
            $first = false;
            generarFicha($xlsx, $modalidad, $denominacion, $fecha_inicio, $fecha_fin, $num_horas, $calendario_horarios, $centro, $formadores, $medio_aula, $url_conexion, $tutorizacion, $objetivos, $contenidos, [$participante], $sesiones, $fundae_usuario, $fundae_email, $fundae_password, $fundae_url);
        }
    } else {
        generarFicha($xlsx, $modalidad, $denominacion, $fecha_inicio, $fecha_fin, $num_horas, $calendario_horarios, $centro, $formadores, $medio_aula, $url_conexion, $tutorizacion, $objetivos, $contenidos, $participantes, $sesiones, $fundae_usuario, $fundae_email, $fundae_password, $fundae_url);
    }
    $filename = 'Ficha_FUNDAE_' . preg_replace('/[^a-zA-Z0-9]/', '_', $denominacion) . '_' . date('Y-m-d') . '.xlsx';
    $xlsx->download($filename);
    exit;
}

function generarFicha($xlsx, $modalidad, $denominacion, $fecha_inicio, $fecha_fin, $num_horas, $calendario_horarios, $centro, $formadores, $medio_aula, $url_conexion, $tutorizacion, $objetivos, $contenidos, $participantes, $sesiones, $fundae_usuario, $fundae_email, $fundae_password, $fundae_url) {
    $xlsx->setColumnWidth(1, 45)->setColumnWidth(2, 80);
    $xlsx->addRow(['DATOS DE LA ACCIÓN FORMATIVA', ''], [0 => 2]);
    $xlsx->addRow(['DENOMINACIÓN ACCIÓN FORMATIVA:', $denominacion]);
    $xlsx->addRow(['FECHA DE INICIO:', $fecha_inicio]);
    $xlsx->addRow(['FECHA DE FIN:', $fecha_fin]);
    $xlsx->addRow(['Nº DE HORAS:', $num_horas . ' Horas']);
    if ($modalidad !== 'teleformacion') $xlsx->addRow(['CALENDARIO Y HORARIOS:', $calendario_horarios]);
    $xlsx->addRow(['CENTRO FORMADOR', ''], [0 => 2]);
    $xlsx->addRow(['RAZON SOCIAL:', $centro['razon_social']]);
    $xlsx->addRow(['CIF:', $centro['cif']]);
    $xlsx->addRow(['DIRECCIÓN:', $centro['direccion']]);
    if ($modalidad === 'teleformacion') $xlsx->addRow(['HORARIOS', $centro['horarios']]);
    $xlsx->addRow(['FORMADOR/A (si son varios dividir por horas)', ''], [0 => 2]);
    foreach ($formadores as $i => $f) {
        if ($i > 0) $xlsx->addRow(['--- FORMADOR/A ' . ($i + 1) . ' ---', ''], [0 => 1]);
        $xlsx->addRow(['NOMBRE Y APELLIDOS:', $f['nombre']]);
        $xlsx->addRow(['PASAPORTE/DNI', $f['pasaporte']]);
        $xlsx->addRow(['MAIL:', $f['email']]);
        $xlsx->addRow(['TELEFONO:', $f['telefono']]);
        if ($modalidad !== 'teleformacion') $xlsx->addRow(['HORAS DE IMPARTICIÓN:', $f['horas'] . ' Horas']);
    }
    if ($modalidad === 'teleformacion') $xlsx->addRow(['Tutorizacion', $tutorizacion]);
    $xlsx->addRow(['AULA VIRTUAL', ''], [0 => 2]);
    $xlsx->addRow(['MEDIO UTILIZADO COMO AULA VIRTUAL:', $medio_aula]);
    $xlsx->addRow(['MODO DE CONEXIÓN AL AULA VIRTUAL:', $modalidad === 'teleformacion' ? 'URL' : 'URL Serie - ' . $url_conexion]);
    $xlsx->addRow(['PERSONA DE CONTACTO Y TELEFONO', $centro['contacto_nombre'] . '/ ' . $centro['contacto_telefono']]);
    if ($fundae_usuario || $fundae_email) {
        $xlsx->addRow(['DATOS ACCESO PLATAFORMA FUNDAE', ''], [0 => 2]);
        if ($fundae_usuario) $xlsx->addRow(['USUARIO FUNDAE:', $fundae_usuario]);
        if ($fundae_email) $xlsx->addRow(['EMAIL FUNDAE:', $fundae_email]);
        if ($fundae_password) $xlsx->addRow(['CONTRASEÑA FUNDAE:', $fundae_password]);
        if ($fundae_url) $xlsx->addRow(['URL PLATAFORMA:', $fundae_url]);
    }
    $xlsx->addRow(['PROGRMA DE OBJETIVOS Y CONTENIDOS:', ''], [0 => 2]);
    $xlsx->addRow(['OBJETIVOS:', $objetivos], [1 => 3]);
    $xlsx->addRow(['CONTENIDOS:', $contenidos], [1 => 3]);
    $xlsx->addRow(['PARTICIPANTES DEL GRUPO', ''], [0 => 2]);
    foreach ($participantes as $p) $xlsx->addRow([$p['nombre'], $p['email']]);
    if ($modalidad !== 'teleformacion' && !empty($sesiones)) {
        $xlsx->addRow(['ENLACE Y FECHA', ''], [0 => 2]);
        foreach ($sesiones as $s) $xlsx->addRow([$s['fecha'], 'URL Serie - ' . $s['url']]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador Fichas FUNDAE - tuSpeaking</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Inter',sans-serif;background:#f5f7fa;color:#333;line-height:1.6}.container{max-width:1200px;margin:0 auto;padding:20px}.header{background:linear-gradient(135deg,#008ba3 0%,#00bcd4 100%);color:#fff;padding:30px;border-radius:12px;margin-bottom:30px;display:flex;align-items:center;gap:20px}.header h1{font-size:1.8rem;font-weight:600}.header p{opacity:.9;margin-top:5px}.header .material-icons{font-size:48px}.card{background:#fff;border-radius:12px;padding:25px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}.card-header{display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}.card-header .material-icons{color:#008ba3;font-size:28px}.card-header h2{font-size:1.2rem;font-weight:600}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}.form-group{display:flex;flex-direction:column;gap:6px}.form-group.full-width{grid-column:1/-1}.form-group label{font-weight:500;font-size:.9rem;color:#555}.form-group input,.form-group select,.form-group textarea{padding:12px;border:1px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#008ba3;box-shadow:0 0 0 3px rgba(0,139,163,.1)}.form-group textarea{min-height:150px;resize:vertical}.form-group small{color:#888;font-size:.8rem}.modalidad-selector{display:flex;gap:15px;flex-wrap:wrap}.modalidad-option{flex:1;min-width:180px;padding:20px;border:2px solid #e0e0e0;border-radius:12px;cursor:pointer;text-align:center}.modalidad-option:hover{border-color:#008ba3;background:#f8fffe}.modalidad-option.selected{border-color:#008ba3;background:linear-gradient(135deg,rgba(0,139,163,.1) 0%,rgba(0,188,212,.1) 100%)}.modalidad-option input{display:none}.modalidad-option .material-icons{font-size:36px;color:#008ba3;margin-bottom:10px}.modalidad-option h3{font-size:1rem;font-weight:600;margin-bottom:5px}.modalidad-option p{font-size:.8rem;color:#666}.nivel-selector{display:flex;flex-wrap:wrap;gap:10px}.nivel-btn{padding:8px 16px;border:2px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;font-weight:500}.nivel-btn:hover{border-color:#008ba3}.nivel-btn.selected{background:#008ba3;color:#fff;border-color:#008ba3}.profesor-search{display:flex;gap:10px;margin-bottom:15px}.profesor-search select{flex:1;padding:12px;border:1px solid #ddd;border-radius:8px}.profesor-search button{padding:12px 20px;background:#008ba3;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500}.btn-generar{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:18px;background:linear-gradient(135deg,#008ba3 0%,#00bcd4 100%);color:#fff;border:none;border-radius:12px;font-size:1.1rem;font-weight:600;cursor:pointer}.btn-generar:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,139,163,.3)}.back-link{display:inline-flex;align-items:center;gap:5px;color:#008ba3;text-decoration:none;font-weight:500;margin-bottom:20px}.tabs{display:flex;gap:5px;margin-bottom:20px;border-bottom:2px solid #e0e0e0}.tab{padding:12px 20px;background:transparent;border:none;cursor:pointer;font-weight:500;color:#666;border-bottom:3px solid transparent;margin-bottom:-2px}.tab:hover{color:#008ba3}.tab.active{color:#008ba3;border-bottom-color:#008ba3}.tab-content{display:none}.tab-content.active{display:block}
        .formador-card{background:#f8f9fa;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin-bottom:15px;position:relative}
        .formador-card .formador-num{position:absolute;top:10px;right:15px;background:#008ba3;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
        .formador-card .btn-remove{position:absolute;top:10px;right:50px;background:#dc3545;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:11px}
        .formador-card .btn-remove:hover{background:#c82333}
        .btn-add-formador{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#28a745;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;margin-top:5px}
        .btn-add-formador:hover{background:#218838}
        .btn-cargar-idioma{padding:10px 16px;background:#ff9800;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;white-space:nowrap}
        .btn-cargar-idioma:hover{background:#e68a00}
        .idioma-filter{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:15px}
        .credencial-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px}
        .credencial-guardada{background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:12px;margin-bottom:10px;font-size:.9rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
        .credencial-guardada strong{color:#2e7d32}
        .btn-cargar-credencial{padding:6px 14px;background:#6c63ff;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem}
        .btn-cargar-credencial:hover{background:#5a52d5}
        .btn-guardar-credencial{padding:10px 20px;background:#6c63ff;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500}
        .btn-guardar-credencial:hover{background:#5a52d5}
        .credencial-status{font-size:.8rem;margin-left:10px;padding:4px 8px;border-radius:4px;display:inline-block}
    </style>
</head>
<body>
<div class="container">
    <a href="admin.php" class="back-link"><span class="material-icons">arrow_back</span>Volver al Panel</a>
    <div class="header"><span class="material-icons">description</span><div><h1>Generador de Fichas FUNDAE</h1><p>Crea fichas técnicas para formación bonificada</p></div></div>
    <form method="POST">
        <div class="card">
            <div class="card-header"><span class="material-icons">category</span><h2>Modalidad de Formación</h2></div>
            <div class="modalidad-selector">
                <label class="modalidad-option selected"><input type="radio" name="modalidad" value="aula_virtual" checked><span class="material-icons">videocam</span><h3>Aula Virtual</h3><p>Clases grupales síncronas</p></label>
                <label class="modalidad-option"><input type="radio" name="modalidad" value="teleformacion"><span class="material-icons">computer</span><h3>Teleformación</h3><p>Formación individual</p></label>
                <label class="modalidad-option"><input type="radio" name="modalidad" value="mixta"><span class="material-icons">shuffle</span><h3>Mixta</h3><p>Presencial y online</p></label>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="material-icons">school</span><h2>Datos de la Acción Formativa</h2></div>
            <div class="form-grid">
                <div class="form-group full-width"><label>Denominación *</label><input type="text" name="denominacion" placeholder="Ej: CURSO INGLÉS B2" required></div>
                <div class="form-group"><label>Fecha Inicio *</label><input type="date" name="fecha_inicio" required></div>
                <div class="form-group"><label>Fecha Fin *</label><input type="date" name="fecha_fin" required></div>
                <div class="form-group"><label>Nº Horas *</label><input type="number" name="num_horas" required></div>
                <div class="form-group"><label>Calendario/Horarios</label><input type="text" name="calendario_horarios" placeholder="Ej: Martes 8:00-9:00"></div>
                <div class="form-group full-width"><label>Nivel</label>
                    <div class="nivel-selector">
                        <button type="button" class="nivel-btn" data-nivel="A1">A1</button>
                        <button type="button" class="nivel-btn" data-nivel="A2">A2</button>
                        <button type="button" class="nivel-btn selected" data-nivel="B1">B1</button>
                        <button type="button" class="nivel-btn" data-nivel="B1+">B1+</button>
                        <button type="button" class="nivel-btn" data-nivel="B1-B2">B1-B2</button>
                        <button type="button" class="nivel-btn" data-nivel="B2">B2</button>
                        <button type="button" class="nivel-btn" data-nivel="B2-C1">B2-C1</button>
                        <button type="button" class="nivel-btn" data-nivel="C1">C1</button>
                    </div>
                    <input type="hidden" name="nivel" id="nivel" value="B1">
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="material-icons">people</span><h2>Formadores</h2></div>
            <?php if (!empty($profesores)): ?>
            <div class="idioma-filter">
                <span style="font-weight:500;color:#555">Cargar por idioma:</span>
                <select id="idiomaSelect">
                    <option value="">-- Seleccionar idioma --</option>
                    <?php foreach (array_keys($profesores_por_idioma) as $idioma): ?>
                    <option value="<?= htmlspecialchars($idioma) ?>"><?= htmlspecialchars($idioma) ?> (<?= count($profesores_por_idioma[$idioma]) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-cargar-idioma" onclick="cargarPorIdioma()"><span class="material-icons" style="font-size:16px;vertical-align:middle">group_add</span> Cargar todos</button>
                <span style="color:#888;font-size:.85rem">ó individual:</span>
                <select id="profesorSelect">
                    <option value="">-- Buscar profesor --</option>
                    <?php foreach ($profesores as $p): ?><option value="<?= $p['id'] ?>" data-json="<?= htmlspecialchars(json_encode($p)) ?>"><?= htmlspecialchars($p['nombre_completo']) ?> <?= $p['idiomas'] ? '('.$p['idiomas'].')' : '' ?></option><?php endforeach; ?>
                </select>
                <button type="button" style="padding:12px 20px;background:#008ba3;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500" onclick="agregarProfesor()">+ Añadir</button>
            </div>
            <?php endif; ?>
            <div id="formadoresContainer"></div>
            <button type="button" class="btn-add-formador" onclick="agregarFormadorVacio()"><span class="material-icons" style="font-size:18px">add</span> Añadir formador manual</button>
        </div>
        <div class="card">
            <div class="card-header"><span class="material-icons">vpn_key</span><h2>Credenciales FUNDAE</h2></div>
            <?php if (!empty($credenciales_fundae)): ?>
            <div style="margin-bottom:15px">
                <label style="font-weight:500;font-size:.9rem;color:#555;margin-bottom:8px;display:block">Credenciales guardadas:</label>
                <?php foreach ($credenciales_fundae as $cred): ?>
                <div class="credencial-guardada">
                    <span><strong>Año <?= $cred['anio'] ?></strong> — <?= htmlspecialchars($cred['usuario']) ?> | <?= htmlspecialchars($cred['url_plataforma']) ?></span>
                    <button type="button" class="btn-cargar-credencial" onclick='cargarCredencial(<?= json_encode($cred) ?>)'>Usar</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="credencial-grid">
                <div class="form-group"><label>Año</label><select name="fundae_anio" id="fundae_anio"><?php for($y=date('Y');$y>=2024;$y--):?><option value="<?=$y?>"<?=$y==date('Y')?' selected':''?>><?=$y?></option><?php endfor;?></select></div>
                <div class="form-group"><label>Usuario FUNDAE</label><input type="text" name="fundae_usuario" id="fundae_usuario" placeholder="usuario_fundae"></div>
                <div class="form-group"><label>Email FUNDAE</label><input type="email" name="fundae_email" id="fundae_email" placeholder="email@fundae.es"></div>
                <div class="form-group"><label>Contraseña FUNDAE</label><input type="text" name="fundae_password" id="fundae_password" placeholder="contraseña"></div>
                <div class="form-group"><label>URL Plataforma</label><select name="fundae_url" id="fundae_url"><option value="https://aula.tuspeaking.com">aula.tuspeaking.com</option><option value="https://cesce.tuspeaking.com">cesce.tuspeaking.com</option><option value="https://learn.tuspeaking.com">learn.tuspeaking.com</option></select></div>
            </div>
            <div style="margin-top:10px"><button type="button" class="btn-guardar-credencial" onclick="guardarCredencial()"><span class="material-icons" style="font-size:16px;vertical-align:middle">save</span> Guardar para este año</button><span id="credencial-status" class="credencial-status"></span></div>
        </div>
        <div class="card">
            <div class="card-header"><span class="material-icons">video_call</span><h2>Aula Virtual</h2></div>
            <div class="form-grid">
                <div class="form-group"><label>Medio</label><select name="medio_aula"><option>ZOOM</option><option>TEAMS</option><option>GOOGLE MEET</option></select></div>
                <div class="form-group"><label>URL Conexión</label><input type="url" name="url_conexion" placeholder="https://zoom.us/j/..."></div>
                <div class="form-group teleformacion-field" style="display:none"><label>Tutorización</label><input type="text" name="tutorizacion" value="Correo electrónico, videoconferencia"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="material-icons">list_alt</span><h2>Objetivos y Contenidos</h2></div>
            <div class="tabs"><button type="button" class="tab active" data-tab="objetivos">Objetivos</button><button type="button" class="tab" data-tab="contenidos">Contenidos</button></div>
            <div class="tab-content active" id="tab-objetivos"><div class="form-group"><textarea name="objetivos" id="objetivos"></textarea></div></div>
            <div class="tab-content" id="tab-contenidos"><div class="form-group"><textarea name="contenidos" id="contenidos"></textarea></div></div>
        </div>
        <div class="card">
            <div class="card-header"><span class="material-icons">groups</span><h2>Participantes</h2></div>
            <?php if (!empty($fichas_formacion)): ?>
            <div class="idioma-filter" style="margin-bottom:15px">
                <span style="font-weight:500;color:#555">Cargar desde ficha:</span>
                <select id="fichaSelect" onchange="cargarGrupos()">
                    <option value="">-- Seleccionar empresa/ficha --</option>
                    <?php foreach ($fichas_formacion as $ff): ?>
                    <option value="<?= $ff['id'] ?>"><?= htmlspecialchars($ff['empresa_nombre']) ?> — <?= htmlspecialchars($ff['codigo_edicion']) ?> (<?= htmlspecialchars($ff['idiomas']) ?>, <?= date('d/m/Y', strtotime($ff['fecha_inicio'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="grupoSelect" onchange="habilitarCargarAlumnos()">
                    <option value="">-- Primero selecciona ficha --</option>
                </select>
                <button type="button" style="padding:10px 16px;background:#ff9800;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500" onclick="cargarAlumnos()"><span class="material-icons" style="font-size:16px;vertical-align:middle">person_add</span> Cargar alumnos</button>
            </div>
            <?php endif; ?>
            <div class="form-group"><textarea name="participantes" id="participantes" rows="6" placeholder="Nombre Apellidos | email@ejemplo.com"></textarea><small>Un participante por línea. Formato: Nombre | email</small></div>
        </div>
        <div class="card sesiones-card">
            <div class="card-header"><span class="material-icons">event</span><h2>Sesiones</h2></div>
            <div class="form-group"><textarea name="sesiones" rows="6" placeholder="2026-01-13 | https://zoom.us/j/123"></textarea><small>Fecha | URL por línea</small></div>
        </div>
        <button type="submit" name="generar" value="1" class="btn-generar"><span class="material-icons">download</span>Generar Ficha FUNDAE</button>
    </form>
</div>
<script>
const objetivos = <?= json_encode($objetivos_aula_virtual) ?>;
const contenidos = <?= json_encode($contenidos_aula_virtual) ?>;
const profesoresPorIdioma = <?= json_encode($profesores_por_idioma) ?>;
let formadorCount = 0;

function escHTML(s){if(!s)return '';return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function crearFormadorHTML(idx,d){
    d=d||{nombre:'',pasaporte:'',email:'',telefono:'686256425',horas:''};
    var wp=!d.pasaporte?'style="border-color:#f39c12;background:#fffcf0" placeholder="⚠ No registrado"':'';
    return '<div class="formador-card" id="formador_'+idx+'">'+
        '<span class="formador-num">'+(idx+1)+'</span>'+
        '<button type="button" class="btn-remove" onclick="eliminarFormador('+idx+')">✕ Quitar</button>'+
        '<div class="form-grid">'+
        '<div class="form-group"><label>Nombre *</label><input type="text" name="formadores['+idx+'][nombre]" value="'+escHTML(d.nombre)+'" required></div>'+
        '<div class="form-group"><label>Pasaporte/DNI</label><input type="text" name="formadores['+idx+'][pasaporte]" value="'+escHTML(d.pasaporte)+'" '+wp+'></div>'+
        '<div class="form-group"><label>Email</label><input type="email" name="formadores['+idx+'][email]" value="'+escHTML(d.email)+'"></div>'+
        '<div class="form-group"><label>Teléfono</label><input type="text" name="formadores['+idx+'][telefono]" value="'+escHTML(d.telefono||'686256425')+'"></div>'+
        '<div class="form-group"><label>Horas</label><input type="text" name="formadores['+idx+'][horas]" value="'+escHTML(d.horas)+'"></div>'+
        '</div></div>';
}

function agregarFormadorVacio(){var c=document.getElementById('formadoresContainer');c.insertAdjacentHTML('beforeend',crearFormadorHTML(formadorCount,null));formadorCount++;renumerarFormadores()}

function agregarProfesor(){
    var s=document.getElementById('profesorSelect');var opt=s.options[s.selectedIndex];
    if(!s.value||!opt.dataset.json)return;var p=JSON.parse(opt.dataset.json);
    var existente=document.querySelector('input[value="'+escHTML(p.nombre_completo)+'"][name*="[nombre]"]');
    if(existente){alert(p.nombre_completo+' ya está añadido');return}
    var c=document.getElementById('formadoresContainer');
    c.insertAdjacentHTML('beforeend',crearFormadorHTML(formadorCount,{nombre:p.nombre_completo,pasaporte:p.pasaporte,email:p.email,telefono:p.telefono||'686256425',horas:''}));
    formadorCount++;s.selectedIndex=0;renumerarFormadores()
}

function cargarPorIdioma(){
    var sel=document.getElementById('idiomaSelect');var idioma=sel.value;
    if(!idioma)return alert('Selecciona un idioma');
    var profs=profesoresPorIdioma[idioma];if(!profs||!profs.length)return alert('No hay profesores para '+idioma);
    document.getElementById('formadoresContainer').innerHTML='';formadorCount=0;
    profs.forEach(function(p){
        var c=document.getElementById('formadoresContainer');
        c.insertAdjacentHTML('beforeend',crearFormadorHTML(formadorCount,{nombre:p.nombre_completo,pasaporte:p.pasaporte,email:p.email,telefono:p.telefono||'686256425',horas:''}));
        formadorCount++
    });
    renumerarFormadores();sel.selectedIndex=0
}

function eliminarFormador(idx){var el=document.getElementById('formador_'+idx);if(el)el.remove();renumerarFormadores()}

function renumerarFormadores(){
    var cards=document.querySelectorAll('#formadoresContainer .formador-card');
    cards.forEach(function(card,i){
        card.querySelector('.formador-num').textContent=i+1;
        card.querySelectorAll('input').forEach(function(inp){inp.name=inp.name.replace(/formadores\[\d+\]/,'formadores['+i+']')})
    })
}

function cargarCredencial(cred){
    document.getElementById('fundae_anio').value=cred.anio||'';
    document.getElementById('fundae_usuario').value=cred.usuario||'';
    document.getElementById('fundae_email').value=cred.email_fundae||'';
    document.getElementById('fundae_password').value=cred.password_fundae||'';
    var u=document.getElementById('fundae_url');
    for(var i=0;i<u.options.length;i++){if(u.options[i].value===cred.url_plataforma){u.selectedIndex=i;break}}
}

function guardarCredencial(){
    var fd=new FormData();fd.append('guardar_credencial','1');
    fd.append('anio',document.getElementById('fundae_anio').value);
    fd.append('usuario',document.getElementById('fundae_usuario').value);
    fd.append('email_fundae',document.getElementById('fundae_email').value);
    fd.append('password_fundae',document.getElementById('fundae_password').value);
    fd.append('url_plataforma',document.getElementById('fundae_url').value);
    var st=document.getElementById('credencial-status');st.textContent='Guardando...';st.style.background='#fff3cd';st.style.color='#856404';
    fetch(window.location.pathname,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.ok){st.textContent='✔ Guardado';st.style.background='#d4edda';st.style.color='#155724';setTimeout(function(){location.reload()},1500)}
        else{st.textContent='✕ '+d.error;st.style.background='#f8d7da';st.style.color='#721c24'}
    }).catch(function(){st.textContent='✕ Error';st.style.background='#f8d7da';st.style.color='#721c24'})
}

document.querySelectorAll('.modalidad-option').forEach(function(o){o.addEventListener('click',function(){document.querySelectorAll('.modalidad-option').forEach(function(x){x.classList.remove('selected')});this.classList.add('selected');var m=this.querySelector('input').value;document.querySelectorAll('.teleformacion-field').forEach(function(f){f.style.display=m==='teleformacion'?'block':'none'});document.querySelector('.sesiones-card').style.display=m==='teleformacion'?'none':'block'})});
document.querySelectorAll('.nivel-btn').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.nivel-btn').forEach(function(x){x.classList.remove('selected')});this.classList.add('selected');var n=this.dataset.nivel;document.getElementById('nivel').value=n;if(objetivos[n])document.getElementById('objetivos').value=objetivos[n];if(contenidos[n])document.getElementById('contenidos').value=contenidos[n]})});
document.querySelector('.nivel-btn.selected').click();
document.querySelectorAll('.tab').forEach(function(t){t.addEventListener('click',function(){document.querySelectorAll('.tab').forEach(function(x){x.classList.remove('active')});document.querySelectorAll('.tab-content').forEach(function(x){x.classList.remove('active')});this.classList.add('active');document.getElementById('tab-'+this.dataset.tab).classList.add('active')})});

agregarFormadorVacio();

// ============ PARTICIPANTES desde Fichas ============

function cargarGrupos(){
    var fichaId=document.getElementById('fichaSelect').value;
    var gs=document.getElementById('grupoSelect');
    gs.innerHTML='<option value="">Cargando...</option>';
    if(!fichaId){gs.innerHTML='<option value="">-- Primero selecciona ficha --</option>';return}
    fetch(window.location.pathname+'?ajax_grupos='+fichaId)
    .then(function(r){return r.json()})
    .then(function(grupos){
        var html='<option value="todos">📋 Todos los alumnos de la ficha</option>';
        grupos.forEach(function(g){html+='<option value="'+g.id+'">'+g.nombre+' ('+g.nivel+') — '+g.num_alumnos+' alumnos</option>'});
        gs.innerHTML=html
    }).catch(function(){gs.innerHTML='<option value="">Error al cargar</option>'})
}

function habilitarCargarAlumnos(){}

function cargarAlumnos(){
    var fichaId=document.getElementById('fichaSelect').value;
    var grupoId=document.getElementById('grupoSelect').value;
    if(!fichaId)return alert('Selecciona una ficha primero');
    var url=window.location.pathname;
    if(grupoId==='todos'||!grupoId){url+='?ajax_alumnos=0&ficha_id='+fichaId}
    else{url+='?ajax_alumnos='+grupoId}
    fetch(url).then(function(r){return r.json()}).then(function(alumnos){
        if(!alumnos.length)return alert('No se encontraron alumnos');
        var ta=document.getElementById('participantes');
        var lineas=alumnos.map(function(a){return a.nombre+' | '+a.email});
        if(ta.value.trim()){ta.value+='\n'+lineas.join('\n')}else{ta.value=lineas.join('\n')}
    }).catch(function(){alert('Error al cargar alumnos')})
}
</script>
</body>
</html>
