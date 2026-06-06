<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$q = $_GET['q'] ?? '';

$sql = "SELECT u.id, u.firstname, u.lastname, u.email,
        (SELECT COUNT(DISTINCT e.courseid) FROM mdl_user_enrolments ue 
         JOIN mdl_enrol e ON ue.enrolid=e.id WHERE ue.userid=u.id) as cursos
        FROM mdl_user u WHERE u.deleted=0 AND u.suspended=0 AND u.id>1";
if ($q) $sql .= " AND (u.firstname LIKE :q OR u.lastname LIKE :q2 OR u.email LIKE :q3)";
$sql .= " ORDER BY u.lastname LIMIT 50";

$stmt = $db->prepare($sql);
if ($q) {
    $like = "%$q%";
    $stmt->execute([':q'=>$like,':q2'=>$like,':q3'=>$like]);
} else {
    $stmt->execute();
}
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Por Usuario - <?=h(APP_NAME)?></title>
    <link rel="stylesheet" href="assets/contenido.css">
</head>
<body>
    <header class="header">
        <h1>👤 Por Usuario</h1>
        <a href="index.php" class="btn btn-outline">← Volver</a>
    </header>
    
    <div class="container">
        <form class="search-box" method="get">
            <input type="text" name="q" value="<?=h($q)?>" placeholder="Buscar usuario...">
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
        </form>
        
        <?php if(empty($usuarios)): ?>
            <div class="empty-state">No se encontraron usuarios</div>
        <?php else: ?>
            <?php foreach($usuarios as $u): ?>
            <div class="user-card">
                <div class="user-header">
                    <div>
                        <div class="user-name">👤 <?=h($u['firstname'].' '.$u['lastname'])?></div>
                        <div class="user-email"><?=h($u['email'])?></div>
                    </div>
                    <span class="badge badge-info"><?=$u['cursos']?> cursos</span>
                </div>
                <div class="user-content">
                    <?php
                    $stmtC = $db->prepare("
                        SELECT c.id, c.fullname,
                        (SELECT COUNT(*) FROM mdl_course_modules cm WHERE cm.course=c.id) as total,
                        (SELECT COUNT(*) FROM mdl_course_modules_completion cmc 
                         JOIN mdl_course_modules cm ON cmc.coursemoduleid=cm.id 
                         WHERE cm.course=c.id AND cmc.userid=:uid AND cmc.completionstate>0) as done
                        FROM mdl_course c
                        JOIN mdl_enrol e ON e.courseid=c.id
                        JOIN mdl_user_enrolments ue ON ue.enrolid=e.id
                        WHERE ue.userid=:uid2 AND c.id>1 GROUP BY c.id ORDER BY c.fullname
                    ");
                    $stmtC->execute([':uid'=>$u['id'],':uid2'=>$u['id']]);
                    $cursos = $stmtC->fetchAll();
                    ?>
                    <?php foreach($cursos as $c): 
                        $pct = $c['total']>0 ? round($c['done']/$c['total']*100) : 0;
                        $cls = $pct>=75?'high':($pct>=50?'medium':'low');
                    ?>
                    <div class="course-item">
                        <div class="course-info">
                            <h4><?=h($c['fullname'])?></h4>
                            <div class="course-meta">
                                <span>📊 <?=$pct?>%</span>
                                <span>✓ <?=$c['done']?>/<?=$c['total']?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill progress-<?=$cls?>" style="width:<?=$pct?>%"></div>
                            </div>
                        </div>
                        <a href="asignar.php?usuario=<?=$u['id']?>&curso=<?=$c['id']?>" class="btn btn-primary">➕</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
