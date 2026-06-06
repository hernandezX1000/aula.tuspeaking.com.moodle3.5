<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$cursoId = $_GET['id'] ?? null;

$cursos = $db->query("SELECT id,fullname FROM mdl_course WHERE visible=1 AND id>1 ORDER BY fullname")->fetchAll();
$detalle = null;
$alumnos = [];
$secciones = [];

if ($cursoId) {
    $stmt = $db->prepare("SELECT * FROM mdl_course WHERE id=:id");
    $stmt->execute([':id'=>$cursoId]);
    $detalle = $stmt->fetch();
    
    if ($detalle) {
        $stmt = $db->prepare("
            SELECT u.id,u.firstname,u.lastname,u.email,
            (SELECT COUNT(*) FROM mdl_course_modules_completion cmc 
             JOIN mdl_course_modules cm ON cmc.coursemoduleid=cm.id 
             WHERE cm.course=:c1 AND cmc.userid=u.id AND cmc.completionstate>0) as done,
            (SELECT COUNT(*) FROM mdl_course_modules cm WHERE cm.course=:c2) as total
            FROM mdl_user u
            JOIN mdl_user_enrolments ue ON ue.userid=u.id
            JOIN mdl_enrol e ON ue.enrolid=e.id
            WHERE e.courseid=:c3 AND u.deleted=0 ORDER BY u.lastname
        ");
        $stmt->execute([':c1'=>$cursoId,':c2'=>$cursoId,':c3'=>$cursoId]);
        $alumnos = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT id,section,name FROM mdl_course_sections WHERE course=:c ORDER BY section");
        $stmt->execute([':c'=>$cursoId]);
        $secciones = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Por Curso - <?=h(APP_NAME)?></title>
    <link rel="stylesheet" href="assets/contenido.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single { height: 42px; padding: 6px; border-radius: 8px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
        .select2-dropdown { border-radius: 8px; }
        .select2-search--dropdown .select2-search__field { padding: 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <header class="header">
        <h1>📖 Por Curso</h1>
        <a href="index.php" class="btn btn-outline">← Volver</a>
    </header>
    
    <div class="container">
        <form class="search-box" method="get">
            <select name="id" id="curso-select">
                <option value="">Buscar curso...</option>
                <?php foreach($cursos as $c): ?>
                <option value="<?=$c['id']?>" <?=$cursoId==$c['id']?'selected':''?>><?=h($c['fullname'])?></option>
                <?php endforeach; ?>
            </select>
            <?php if($cursoId): ?>
            <a href="asignar.php?curso_destino=<?=$cursoId?>" class="btn btn-primary">➕ Importar</a>
            <?php endif; ?>
        </form>
        
        <?php if($detalle): ?>
        <div class="course-card">
            <div class="course-header">
                <div class="course-name">📖 <?=h($detalle['fullname'])?></div>
                <span class="badge badge-info"><?=count($alumnos)?> alumnos</span>
            </div>
            <div class="course-content">
                <div class="section-title">👥 Alumnos</div>
                <table class="data-table">
                    <thead><tr><th>Alumno</th><th>Email</th><th>Progreso</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($alumnos as $a): 
                        $pct=$a['total']>0?round($a['done']/$a['total']*100):0;
                        $cls=$pct>=75?'high':($pct>=50?'medium':'low');
                    ?>
                    <tr>
                        <td><?=h($a['firstname'].' '.$a['lastname'])?></td>
                        <td><?=h($a['email'])?></td>
                        <td>
                            <?=$pct?>%
                            <div class="progress-bar" style="width:80px;display:inline-block;vertical-align:middle">
                                <div class="progress-fill progress-<?=$cls?>" style="width:<?=$pct?>%"></div>
                            </div>
                        </td>
                        <td><a href="asignar.php?usuario=<?=$a['id']?>&curso=<?=$cursoId?>" class="btn btn-primary btn-sm">Asignar</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="section-title" style="margin-top:2rem">📦 Secciones</div>
                <?php foreach($secciones as $s): ?>
                <div class="course-item">
                    <span>📁 <?=h($s['name']?:'Sección '.$s['section'])?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">Selecciona un curso</div>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#curso-select').select2({
            placeholder: 'Buscar curso...',
            allowClear: true,
            language: {
                noResults: function() { return "No se encontraron cursos"; },
                searching: function() { return "Buscando..."; }
            }
        }).on('change', function() {
            if (this.value) this.form.submit();
        });
    });
    </script>
</body>
</html>
