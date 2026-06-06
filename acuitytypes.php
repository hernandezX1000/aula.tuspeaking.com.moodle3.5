<?php
require('config.php');
$admins = get_admins();
$userid = $USER->id;
$isadmin = false;
foreach($admins as $admin){
    if ($admin->id == $USER->id){
        $isadmin = true;
        break;
    }
}
if (!$isadmin){
    header("Location: http://aula.tuspeaking.com/app/moodle");
    die();
}

$message = "";
$messageType = "";

if (!empty($_POST)){
    $types = array();
    $i = 0;
    foreach ($_POST as $key=>$val){
        if (substr($key, 0, 2) == "id"){
            $types[$i]['id'] = $val;
        } else if (substr($key, 0, 2) == "ty"){
            $types[$i]['type'] = $val;
            $i++;
        } else if ($key == "newid" && $val != ""){
            $types[$i]['id'] = $val;
        } else if ($key == "newtype" && $val != ""){
            $types[$i]['type'] = $val;
            $i++;
        }
    }
    $sql = "INSERT INTO own_acuitytypes (acuityid, acuitytype) VALUES ";
    foreach ($types as $k=>$v){
        $sql .= "(" . $v['id'] . ", '" . $v['type'] . "'),";
    }
    $sql = substr($sql, 0, -1);
    $sql .= " ON DUPLICATE KEY UPDATE acuityid=VALUES(acuityid), acuitytype=VALUES(acuitytype)";
} else if (!empty($_GET['id'])){
    $sql = "DELETE FROM own_acuitytypes WHERE id = " . intval($_GET['id']);
}

if (!empty($sql)){
    try {
        $conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
        $conn->exec($sql);
        $message = "Base de datos actualizada correctamente";
        $messageType = "success";
    }
    catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
    finally {
        $conn = null;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="https://aula.tuspeaking.com/app/moodle/theme/image.php/lambda/theme/1547126939/favicon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <title>Appointment Types | tuSpeaking</title>
    <style>
        :root {
            --tus-primary: #008ba3;
            --tus-secondary: #00bcd4;
            --tus-dark: #454545;
        }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: var(--tus-dark); margin: 0; }
        header { background: linear-gradient(135deg, var(--tus-primary), var(--tus-secondary)); padding: 15px 30px; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 26px; color: white; font-weight: 300; }
        .logo span { font-weight: 600; }
        header h2 { color: white; font-size: 18px; font-weight: 400; margin: 0; }
        header a { color: white; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 4px; text-decoration: none; }
        header a:hover { background: rgba(255,255,255,0.3); color: white; }
        main { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-header { background: var(--tus-secondary); color: white; padding: 12px 20px; font-size: 16px; font-weight: 500; border-radius: 8px 8px 0 0; }
        .card-header-new { background: var(--tus-primary); }
        .card-body { padding: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: none; border-left: 4px solid #4CAF50; }
        .alert-error { background: #ffebee; color: #c62828; border: none; border-left: 4px solid #f44336; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; padding: 12px 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
        td { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:hover { background: #fafafa; }
        input[type="text"] { border: 1px solid #ddd; border-radius: 4px; padding: 8px 12px; width: 100%; }
        input[type="text"]:focus { outline: none; border-color: var(--tus-secondary); box-shadow: 0 0 0 2px rgba(0,188,212,0.2); }
        .btn-save { background: var(--tus-primary); color: white; border: none; padding: 12px 30px; font-size: 15px; border-radius: 6px; cursor: pointer; }
        .btn-save:hover { background: var(--tus-secondary); }
        .btn-delete { color: #f44336; background: none; border: none; cursor: pointer; font-size: 18px; }
        .btn-delete:hover { color: #c62828; }
        .new-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .new-form input { flex: 1; min-width: 150px; }
        .new-form .input-id { max-width: 180px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-box { background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-number { font-size: 28px; font-weight: 600; color: var(--tus-primary); }
        .stat-label { color: #666; font-size: 14px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">tu<span>Speaking</span></div>
        <h2>Appointment Types</h2>
        <a href="https://aula.tuspeaking.com/app/moodle"><i class="fas fa-arrow-left"></i> Ir a Moodle</a>
    </div>
</header>
<main>
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>" style="padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <?php echo $messageType == 'success' ? '✓' : '✗'; ?> <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <?php
    try {
        $conn = new PDO("mysql:host=".$CFG->dbhost.";dbname=".$CFG->dbname, $CFG->dbuser, $CFG->dbpass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
        $sql = "SELECT id, acuityid, acuitytype FROM own_acuitytypes ORDER BY acuitytype ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchall();
        $totalTypes = count($result);
    }
    catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
    finally {
        $conn = null;
    }
    ?>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo $totalTypes; ?></div>
            <div class="stat-label">Tipos de Cita Configurados</div>
        </div>
    </div>

    <form action='./acuitytypes.php' method='post'>
        <!-- NUEVO TIPO - ARRIBA -->
        <div class="card">
            <div class="card-header card-header-new">
                <i class="fas fa-plus-circle"></i> Añadir Nuevo Tipo de Cita
            </div>
            <div class="card-body">
                <div class="new-form">
                    <input type='text' class="input-id" name='newid' placeholder='Acuity ID (número)'>
                    <input type='text' name='newtype' placeholder='Nombre del tipo de cita'>
                    <button type='submit' class="btn-save"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </div>
        </div>

        <!-- LISTA EXISTENTE -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calendar-alt"></i> Tipos de Cita Existentes (<?php echo $totalTypes; ?>)
            </div>
            <div class="card-body" style="padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 180px;">Acuity ID</th>
                            <th>Nombre del Tipo</th>
                            <th style="width: 60px;">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result as $val): ?>
                        <tr>
                            <td>
                                <input type='text' name='id<?php echo $val['acuityid']; ?>' value='<?php echo $val['acuityid']; ?>' readonly style="background:#f5f5f5; color:#666;">
                            </td>
                            <td>
                                <input type='text' name='ty<?php echo $val['acuityid']; ?>' value='<?php echo htmlspecialchars($val['acuitytype']); ?>'>
                            </td>
                            <td style="text-align: center;">
                                <a href='./acuitytypes.php?id=<?php echo $val['id']; ?>' class="btn-delete" onclick="return confirm('¿Eliminar este tipo de cita?');" title="Eliminar">
                                    <i class='fas fa-trash'></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error" style="padding: 15px; border-radius: 4px;">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
