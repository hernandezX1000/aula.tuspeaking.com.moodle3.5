<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
require_once('../config.php');
$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $jsonInput['action'] ?? '';
try { $pdo = new PDO("mysql:host={$CFG->dbhost};dbname={$CFG->dbname};charset=utf8mb4", $CFG->dbuser, $CFG->dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } catch (PDOException $e) { die(json_encode(['success' => false, 'error' => 'DB error'])); }

function getBillingPeriod($date) { $d=new DateTime($date); $day=(int)$d->format('d'); $m=(int)$d->format('m'); $y=(int)$d->format('Y'); if($day>=25){$sm=$m;$sy=$y;$em=$m+1;$ey=$y;if($em>12){$em=1;$ey++;}}else{$sm=$m-1;$sy=$y;if($sm<1){$sm=12;$sy--;}$em=$m;$ey=$y;} return sprintf('%04d-%02d-25_%04d-%02d-24',$sy,$sm,$ey,$em); }
function generateYearPeriods($year) { $p=[]; $p[]=sprintf('%04d-12-25_%04d-01-24',$year-1,$year); for($m=1;$m<=11;$m++){$p[]=sprintf('%04d-%02d-25_%04d-%02d-24',$year,$m,$year,$m+1);} return $p; }
function generateAllBillingPeriods() { $p=[];$cy=(int)date('Y');for($y=$cy-1;$y<=$cy+1;$y++){for($m=1;$m<=12;$m++){$em=$m+1;$ey=$y;if($em>12){$em=1;$ey++;}$p[]=sprintf('%04d-%02d-25_%04d-%02d-24',$y,$m,$ey,$em);}}rsort($p);return $p; }
function isAdmin($pdo,$uid){global $CFG;return in_array($uid,explode(',',$CFG->siteadmins??''));}
function logAccess($pdo,$uid,$uname,$fname,$act='login'){$ip=$_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'unknown';$ua=$_SERVER['HTTP_USER_AGENT']??'unknown';$s=$pdo->prepare("INSERT INTO mdl_timelog_access_log (user_id,username,fullname,ip_address,user_agent,action) VALUES (?,?,?,?,?,?)");$s->execute([$uid,$uname,$fname,$ip,$ua,$act]);}

$response = ['success'=>false,'error'=>'Invalid action'];

switch($action) {
  case 'login':
    $u=$_POST['username']??'';$p=$_POST['password']??'';
    $s=$pdo->prepare("SELECT id,username,password,firstname,lastname,email,suspended,deleted FROM mdl_user WHERE username=? OR email=?");$s->execute([$u,$u]);$user=$s->fetch(PDO::FETCH_ASSOC);
    if(!$user||$user['suspended']==1||$user['deleted']==1||!password_verify($p,$user['password'])){$response=['success'=>false,'error'=>'Invalid credentials'];}
    else{$fn=$user['firstname'].' '.$user['lastname'];logAccess($pdo,$user['id'],$user['username'],$fn);$response=['success'=>true,'user'=>['id'=>$user['id'],'username'=>$user['username'],'fullname'=>$fn,'email'=>$user['email'],'isAdmin'=>isAdmin($pdo,$user['id'])]];}
    break;

  case 'admin_getAccessLog':
    $l=$_GET['limit']??100;$s=$pdo->query("SELECT *,DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') as formatted_date FROM mdl_timelog_access_log ORDER BY created_at DESC LIMIT ".(int)$l);$response=['success'=>true,'logs'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;
  case 'admin_getAccessStats':
    $s=$pdo->query("SELECT user_id,username,fullname,COUNT(*) as login_count,MAX(created_at) as last_login,DATE_FORMAT(MAX(created_at),'%Y-%m-%d %H:%i') as last_login_formatted FROM mdl_timelog_access_log WHERE action='login' GROUP BY user_id ORDER BY last_login DESC");$response=['success'=>true,'stats'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;

  case 'admin_getTeachers':
    $showAll=$_GET['show_all']??'0';$search=$_GET['search']??'';
    $sql="SELECT u.id,u.username,u.firstname,u.lastname,u.email,u.suspended,COALESCE(t.active,0) as timelog_active,t.notes,t.payment_method,t.bank_name,t.bank_iban,t.bank_swift,t.bank_holder,t.paypal_email,t.wise_email,t.notes_payment,t.content_supplement_pct,t.content_supplement_active,t.retention_pct,t.retention_active,(SELECT COUNT(*) FROM mdl_timelog_classes WHERE teacher_id=u.id) as total_classes,(SELECT SUM(CASE WHEN status='completed' THEN rate ELSE 0 END) FROM mdl_timelog_classes WHERE teacher_id=u.id) as total_earnings FROM mdl_user u LEFT JOIN mdl_timelog_teachers t ON t.user_id=u.id WHERE u.deleted=0";
    $params=[];
    if($showAll!=='1'){$sql.=" AND t.active=1";}else{$sql.=" AND (t.user_id IS NOT NULL OR EXISTS(SELECT 1 FROM mdl_role_assignments ra JOIN mdl_role r ON r.id=ra.roleid WHERE ra.userid=u.id AND(r.shortname='editingteacher' OR r.shortname='teacher')))";}
    if($search){$sql.=" AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR CONCAT(u.firstname,' ',u.lastname) LIKE ?)";$st="%{$search}%";$params=[$st,$st,$st,$st];}
    $sql.=" ORDER BY u.lastname,u.firstname";
    $s=$pdo->prepare($sql);$s->execute($params);
    $response=['success'=>true,'teachers'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;

  case 'admin_addTeacher':
    $s=$pdo->prepare("SELECT id FROM mdl_user WHERE id=? OR username=? OR email=?");$s->execute([$jsonInput['user_id']??0,$jsonInput['username']??'',$jsonInput['email']??'']);$u=$s->fetch(PDO::FETCH_ASSOC);
    if($u){$s=$pdo->prepare("INSERT INTO mdl_timelog_teachers(user_id,active) VALUES(?,1) ON DUPLICATE KEY UPDATE active=1");$s->execute([$u['id']]);$response=['success'=>true];}else{$response=['success'=>false,'error'=>'User not found'];}break;

  case 'admin_toggleTeacher':
    $s=$pdo->prepare("INSERT INTO mdl_timelog_teachers(user_id,active) VALUES(?,?) ON DUPLICATE KEY UPDATE active=?");$s->execute([$jsonInput['user_id'],$jsonInput['active']?1:0,$jsonInput['active']?1:0]);$response=['success'=>true];break;

  case 'admin_updateTeacherPayment':
    $s=$pdo->prepare("UPDATE mdl_timelog_teachers SET payment_method=?,bank_name=?,bank_iban=?,bank_swift=?,bank_holder=?,paypal_email=?,wise_email=?,notes_payment=? WHERE user_id=?");
    $s->execute([$jsonInput['payment_method'],$jsonInput['bank_name']??'',$jsonInput['bank_iban']??'',$jsonInput['bank_swift']??'',$jsonInput['bank_holder']??'',$jsonInput['paypal_email']??'',$jsonInput['wise_email']??'',$jsonInput['notes_payment']??'',$jsonInput['user_id']]);$response=['success'=>true];break;

  case 'admin_updateTeacherExtras':
    $s=$pdo->prepare("UPDATE mdl_timelog_teachers SET content_supplement_pct=?,content_supplement_active=?,retention_pct=?,retention_active=? WHERE user_id=?");
    $s->execute([$jsonInput['content_supplement_pct']??0,$jsonInput['content_supplement_active']?1:0,$jsonInput['retention_pct']??15,$jsonInput['retention_active']?1:0,$jsonInput['user_id']]);$response=['success'=>true];break;

  case 'admin_searchMoodleUsers':
    $search=$_GET['search']??'';if(strlen($search)>=2){$s=$pdo->prepare("SELECT u.id,u.username,u.firstname,u.lastname,u.email,EXISTS(SELECT 1 FROM mdl_timelog_teachers t WHERE t.user_id=u.id AND t.active=1) as already_added FROM mdl_user u WHERE u.deleted=0 AND(u.username LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?) ORDER BY u.lastname LIMIT 20");$st="%{$search}%";$s->execute([$st,$st,$st,$st]);$response=['success'=>true,'users'=>$s->fetchAll(PDO::FETCH_ASSOC)];}else{$response=['success'=>true,'users'=>[]];}break;

  case 'getServices':
    $s=$pdo->query("SELECT * FROM mdl_timelog_services WHERE active=1 ORDER BY duration_minutes");$response=['success'=>true,'services'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;
  case 'addService':
    $s=$pdo->prepare("INSERT INTO mdl_timelog_services(service_name,duration_minutes,default_rate,active) VALUES(?,?,?,1)");$s->execute([$jsonInput['service_name'],$jsonInput['duration_minutes'],$jsonInput['default_rate']]);$response=['success'=>true,'id'=>$pdo->lastInsertId()];break;
  case 'updateService':
    $s=$pdo->prepare("UPDATE mdl_timelog_services SET service_name=?,duration_minutes=?,default_rate=? WHERE id=?");$s->execute([$jsonInput['service_name'],$jsonInput['duration_minutes'],$jsonInput['default_rate'],$jsonInput['id']]);$response=['success'=>true];break;
  case 'deleteService':
    $s=$pdo->prepare("UPDATE mdl_timelog_services SET active=0 WHERE id=?");$s->execute([$jsonInput['id']]);$response=['success'=>true];break;

  case 'getRates':
    $tid=$_GET['teacher_id']??0;$s=$pdo->prepare("SELECT service_name,duration_minutes,rate FROM mdl_timelog_teacher_rates WHERE userid=? AND active=1 ORDER BY duration_minutes");$s->execute([$tid]);$rates=$s->fetchAll(PDO::FETCH_ASSOC);
    if(empty($rates)){$s=$pdo->query("SELECT service_name,duration_minutes,default_rate as rate FROM mdl_timelog_services WHERE active=1 ORDER BY duration_minutes");$rates=$s->fetchAll(PDO::FETCH_ASSOC);}
    $response=['success'=>true,'rates'=>$rates];break;

  case 'admin_getTeacherRates':
    $tid=$_GET['teacher_id']??0;$s=$pdo->prepare("SELECT s.id as service_id,s.service_name,s.duration_minutes,s.default_rate,tr.rate,tr.active as assigned FROM mdl_timelog_services s LEFT JOIN mdl_timelog_teacher_rates tr ON tr.service_name=s.service_name AND tr.userid=? WHERE s.active=1 ORDER BY s.duration_minutes");$s->execute([$tid]);$rates=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($rates as &$r){$r['rate']=$r['rate']??$r['default_rate'];$r['assigned']=($r['assigned']!==null&&$r['assigned']==1);}
    $response=['success'=>true,'rates'=>$rates];break;

  case 'admin_updateRate':
    $s=$pdo->prepare("INSERT INTO mdl_timelog_teacher_rates(userid,service_name,duration_minutes,rate,active) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE rate=?,active=?");$s->execute([$jsonInput['teacher_id'],$jsonInput['service_name'],$jsonInput['duration_minutes'],$jsonInput['rate'],$jsonInput['active']?1:0,$jsonInput['rate'],$jsonInput['active']?1:0]);$response=['success'=>true];break;

  case 'admin_assignService':
    $s=$pdo->prepare("INSERT INTO mdl_timelog_teacher_rates(userid,service_name,duration_minutes,rate,active) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE active=?");$s->execute([$jsonInput['teacher_id'],$jsonInput['service_name'],$jsonInput['duration_minutes'],$jsonInput['rate'],$jsonInput['assigned']?1:0,$jsonInput['assigned']?1:0]);$response=['success'=>true];break;

  case 'getClasses':
    $tid=$_GET['teacher_id']??0;$bp=$_GET['billing_period']??'';$sql="SELECT * FROM mdl_timelog_classes WHERE teacher_id=?";$p=[$tid];if($bp){$sql.=" AND billing_period=?";$p[]=$bp;}$sql.=" ORDER BY class_date DESC,class_time DESC";$s=$pdo->prepare($sql);$s->execute($p);$response=['success'=>true,'classes'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;

  case 'addClass':
    $bp=getBillingPeriod($jsonInput['class_date']);$s=$pdo->prepare("INSERT INTO mdl_timelog_classes(teacher_id,student_name,company,live_class_type,platform,class_date,class_time,service_name,duration_minutes,rate,status,notes,billing_period) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");$s->execute([$jsonInput['teacher_id'],$jsonInput['student_name'],$jsonInput['company']??'',$jsonInput['live_class_type']??'',$jsonInput['platform']??'',$jsonInput['class_date'],$jsonInput['class_time'],$jsonInput['service_name'],$jsonInput['duration_minutes'],$jsonInput['rate'],$jsonInput['status']??'completed',$jsonInput['notes']??'',$bp]);$response=['success'=>true,'id'=>$pdo->lastInsertId()];break;

  case 'updateClass':
    $bp=getBillingPeriod($jsonInput['class_date']);$s=$pdo->prepare("UPDATE mdl_timelog_classes SET student_name=?,company=?,live_class_type=?,platform=?,class_date=?,class_time=?,service_name=?,duration_minutes=?,rate=?,status=?,notes=?,billing_period=? WHERE id=? AND teacher_id=?");$s->execute([$jsonInput['student_name'],$jsonInput['company']??'',$jsonInput['live_class_type']??'',$jsonInput['platform']??'',$jsonInput['class_date'],$jsonInput['class_time'],$jsonInput['service_name'],$jsonInput['duration_minutes'],$jsonInput['rate'],$jsonInput['status']??'completed',$jsonInput['notes']??'',$bp,$jsonInput['id'],$jsonInput['teacher_id']]);$response=['success'=>true];break;

  case 'deleteClass':
    $s=$pdo->prepare("DELETE FROM mdl_timelog_classes WHERE id=? AND teacher_id=?");$s->execute([$jsonInput['id'],$jsonInput['teacher_id']]);$response=['success'=>true];break;

  case 'getBillingPeriods':case 'admin_getBillingPeriods':
    $response=['success'=>true,'periods'=>generateAllBillingPeriods(),'current'=>getBillingPeriod(date('Y-m-d'))];break;

  case 'admin_getAllClasses':
    $bp=$_GET['billing_period']??'';$tid=$_GET['teacher_id']??'';$sql="SELECT c.*,u.firstname,u.lastname FROM mdl_timelog_classes c JOIN mdl_user u ON u.id=c.teacher_id WHERE 1=1";$p=[];if($bp){$sql.=" AND c.billing_period=?";$p[]=$bp;}if($tid){$sql.=" AND c.teacher_id=?";$p[]=$tid;}$sql.=" ORDER BY c.class_date DESC";$s=$pdo->prepare($sql);$s->execute($p);$response=['success'=>true,'classes'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;

  case 'admin_getSummary':
    $bp=$_GET['billing_period']??getBillingPeriod(date('Y-m-d'));$s=$pdo->prepare("SELECT c.teacher_id,u.firstname,u.lastname,u.email,COUNT(*) as total_classes,SUM(CASE WHEN c.status='completed' THEN 1 ELSE 0 END) as completed,SUM(CASE WHEN c.status='noshow' THEN 1 ELSE 0 END) as noshow,SUM(CASE WHEN c.status='completed' THEN c.rate ELSE 0 END) as earnings FROM mdl_timelog_classes c JOIN mdl_user u ON u.id=c.teacher_id WHERE c.billing_period=? GROUP BY c.teacher_id ORDER BY u.lastname");$s->execute([$bp]);$response=['success'=>true,'summary'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;

  case 'admin_export':
    $bp=$_GET['billing_period']??'';$tid=$_GET['teacher_id']??'';$sql="SELECT u.firstname as teacher_firstname,u.lastname as teacher_lastname,c.student_name,c.company,c.class_date,c.class_time,c.service_name,c.duration_minutes,c.rate,c.status,c.billing_period FROM mdl_timelog_classes c JOIN mdl_user u ON u.id=c.teacher_id WHERE 1=1";$p=[];if($bp){$sql.=" AND c.billing_period=?";$p[]=$bp;}if($tid){$sql.=" AND c.teacher_id=?";$p[]=$tid;}$sql.=" ORDER BY u.lastname,c.class_date";$s=$pdo->prepare($sql);$s->execute($p);$response=['success'=>true,'data'=>$s->fetchAll(PDO::FETCH_ASSOC)];break;

  case 'admin_getYearlyView':
    $year=$_GET['year']??date('Y');$periods=generateYearPeriods($year);
    $s=$pdo->query("SELECT u.id,u.firstname,u.lastname,t.payment_method,t.content_supplement_pct,t.content_supplement_active,t.retention_pct,t.retention_active FROM mdl_user u JOIN mdl_timelog_teachers t ON t.user_id=u.id WHERE t.active=1 AND u.deleted=0 ORDER BY u.lastname");$teachers=$s->fetchAll(PDO::FETCH_ASSOC);
    $s=$pdo->prepare("SELECT teacher_id,billing_period,SUM(CASE WHEN status='completed' THEN rate ELSE 0 END) as earnings FROM mdl_timelog_classes WHERE billing_period IN(".implode(',',array_fill(0,count($periods),'?')).") GROUP BY teacher_id,billing_period");$s->execute($periods);$earnings=[];while($r=$s->fetch(PDO::FETCH_ASSOC)){$earnings[$r['teacher_id']][$r['billing_period']]=floatval($r['earnings']);}
    $s=$pdo->prepare("SELECT teacher_id,billing_period,status,payment_date FROM mdl_timelog_payments WHERE billing_period IN(".implode(',',array_fill(0,count($periods),'?')).")");$s->execute($periods);$payments=[];while($r=$s->fetch(PDO::FETCH_ASSOC)){$payments[$r['teacher_id']][$r['billing_period']]=['status'=>$r['status'],'date'=>$r['payment_date']];}
    $result=[];foreach($teachers as $t){$td=['id'=>$t['id'],'name'=>$t['firstname'].' '.$t['lastname'],'payment_method'=>$t['payment_method'],'content_supplement_pct'=>floatval($t['content_supplement_pct']),'content_supplement_active'=>(bool)$t['content_supplement_active'],'retention_pct'=>floatval($t['retention_pct']),'retention_active'=>(bool)$t['retention_active'],'months'=>[]];$total=0;foreach($periods as $p){$amt=$earnings[$t['id']][$p]??0;$st=$payments[$t['id']][$p]['status']??($amt>0?'pending':null);$finalAmt=$amt;
if($amt>0 && $t['content_supplement_active']){$finalAmt+=$amt*floatval($t['content_supplement_pct'])/100;}
if($amt>0 && $t['retention_active']){$retAmt=$finalAmt*floatval($t['retention_pct'])/100;$td['months'][$p]=['amount'=>round($finalAmt,2),'base'=>round($amt,2),'supplement'=>round($finalAmt-$amt,2),'retention'=>round($retAmt,2),'net'=>round($finalAmt-$retAmt,2),'status'=>$st];} else {$td['months'][$p]=['amount'=>round($finalAmt,2),'status'=>$st];}
$total+=$finalAmt;}$td['total']=$total;$result[]=$td;}
    $response=['success'=>true,'year'=>$year,'periods'=>$periods,'teachers'=>$result];break;

  case 'admin_markPaid':
    $s=$pdo->prepare("INSERT INTO mdl_timelog_payments(teacher_id,billing_period,amount,payment_method,payment_date,status) VALUES(?,?,?,?,?,'paid') ON DUPLICATE KEY UPDATE status='paid',payment_date=?,payment_method=?");$pd=$jsonInput['payment_date']??date('Y-m-d');$s->execute([$jsonInput['teacher_id'],$jsonInput['billing_period'],$jsonInput['amount'],$jsonInput['payment_method']??'',$pd,$pd,$jsonInput['payment_method']??'']);$response=['success'=>true];break;

  case 'admin_markUnpaid':
    $s=$pdo->prepare("UPDATE mdl_timelog_payments SET status='pending' WHERE teacher_id=? AND billing_period=?");$s->execute([$jsonInput['teacher_id'],$jsonInput['billing_period']]);$response=['success'=>true];break;

  case 'admin_getTeacherPaymentInfo':
    $tid=$_GET['teacher_id']??0;$s=$pdo->prepare("SELECT t.*,u.firstname,u.lastname,u.email FROM mdl_timelog_teachers t JOIN mdl_user u ON u.id=t.user_id WHERE t.user_id=?");$s->execute([$tid]);$response=['success'=>true,'teacher'=>$s->fetch(PDO::FETCH_ASSOC)];break;
}
echo json_encode($response);
