<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$action = $_GET['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch($action) {
    case 'contenido_curso':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT id,section,name FROM mdl_course_sections WHERE course=:c ORDER BY section");
        $stmt->execute([':c'=>$id]);
        echo json_encode(['success'=>true,'secciones'=>$stmt->fetchAll()]);
        break;
    
    case 'cursos_categoria':
        $catId = intval($_GET['cat_id'] ?? 0);
        $stmt = $db->prepare("SELECT id,fullname,shortname FROM mdl_course WHERE category=:cat AND visible=1 ORDER BY fullname");
        $stmt->execute([':cat'=>$catId]);
        echo json_encode(['success'=>true,'cursos'=>$stmt->fetchAll()]);
        break;
        
    case 'usuarios':
        $q = $_GET['q'] ?? '';
        $sql = "SELECT id,firstname,lastname,email FROM mdl_user WHERE deleted=0 AND id>1";
        if ($q) $sql .= " AND (firstname LIKE '%$q%' OR lastname LIKE '%$q%' OR email LIKE '%$q%')";
        $sql .= " LIMIT 50";
        echo json_encode(['success'=>true,'usuarios'=>$db->query($sql)->fetchAll()]);
        break;
        
    case 'cursos':
        echo json_encode(['success'=>true,'cursos'=>$db->query("SELECT id,fullname FROM mdl_course WHERE visible=1 AND id>1 ORDER BY fullname")->fetchAll()]);
        break;
        
    default:
        echo json_encode(['success'=>false,'error'=>'Acción no válida']);
}
