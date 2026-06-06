<?php
/**
 * Funciones del Panel de Administración
 * TuSpeaking
 */

require_once __DIR__ . '/config.php';

// === HERRAMIENTAS JSON ===
function loadTools() {
    if (file_exists(TOOLS_FILE)) {
        $json = file_get_contents(TOOLS_FILE);
        $tools = json_decode($json, true);
        if ($tools) return $tools;
    }
    return array();
}

function saveTools($tools) {
    return file_put_contents(TOOLS_FILE, json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addTool($category, $name, $url, $icon = '🔗', $options = array()) {
    $tools = loadTools();
    if (!isset($tools[$category])) {
        $tools[$category] = array('title' => $category, 'items' => array());
    }
    $item = array('name' => $name, 'url' => $url, 'icon' => $icon);
    if (!empty($options['external'])) $item['external'] = true;
    if (!empty($options['new'])) $item['new'] = true;
    if (!empty($options['pending'])) $item['pending'] = true;
    $tools[$category]['items'][] = $item;
    return saveTools($tools);
}

function removeTool($category, $index) {
    $tools = loadTools();
    if (isset($tools[$category]['items'][$index])) {
        array_splice($tools[$category]['items'], $index, 1);
        return saveTools($tools);
    }
    return false;
}

// === CACHÉ ===
function getCache($file, $maxAge) {
    if (file_exists($file) && (time() - filemtime($file)) < $maxAge) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) return $data;
    }
    return null;
}

function setCache($file, $data) {
    return file_put_contents($file, json_encode($data));
}

// === BASE DE DATOS ===
function getDB() {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8');
    return $db;
}

// === FUNDAE ===
function getFundaeActivity($limit = 50) {
    $cacheFile = PANEL_PATH . '/cache_fundae.json';
    $cached = getCache($cacheFile, CACHE_TIME);
    
    if ($cached) {
        return array('data' => $cached['activity'], 'fromCache' => true, 'cacheAge' => time() - filemtime($cacheFile));
    }
    
    $db = getDB();
    $sql = "SELECT 
        l.timecreated,
        FROM_UNIXTIME(l.timecreated) as fecha,
        u.username,
        l.action,
        l.target,
        c.fullname as curso,
        l.ip
    FROM mdl_logstore_standard_log l 
    JOIN mdl_user u ON l.userid = u.id 
    LEFT JOIN mdl_course c ON l.courseid = c.id 
    WHERE u.username LIKE 'fundaemicro%' 
    ORDER BY l.timecreated DESC 
    LIMIT " . intval($limit);
    
    $result = $db->query($sql);
    $activity = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activity[] = $row;
        }
    }
    $db->close();
    
    setCache($cacheFile, array('activity' => $activity));
    return array('data' => $activity, 'fromCache' => false, 'cacheAge' => 0);
}

function analyzeBotBehavior($activity) {
    $score = 0;
    $reasons = array();
    $isOnline = false;
    $lastActivity = null;
    
    if (count($activity) > 0) {
        $lastActivity = $activity[0];
        $lastTime = (int)$lastActivity['timecreated'];
        $isOnline = (time() - $lastTime) < 600;
        
        // Analizar intervalos
        $intervals = array();
        for ($i = 0; $i < min(19, count($activity) - 1); $i++) {
            $diff = (int)$activity[$i]['timecreated'] - (int)$activity[$i+1]['timecreated'];
            if ($diff > 0 && $diff < 300) $intervals[] = $diff;
        }
        
        if (count($intervals) > 5) {
            $avg = array_sum($intervals) / count($intervals);
            if ($avg < 3) { $score += 40; $reasons[] = "Velocidad muy alta (" . round($avg, 1) . "s)"; }
            elseif ($avg < 5) { $score += 20; $reasons[] = "Velocidad alta (" . round($avg, 1) . "s)"; }
            
            $variance = 0;
            foreach ($intervals as $int) $variance += pow($int - $avg, 2);
            $stdDev = sqrt($variance / count($intervals));
            if ($stdDev < 1 && $avg < 10) { $score += 30; $reasons[] = "Ritmo constante (robótico)"; }
        }
        
        // Actividad continua
        $continuous = 0;
        for ($i = 0; $i < count($activity) - 1; $i++) {
            $diff = (int)$activity[$i]['timecreated'] - (int)$activity[$i+1]['timecreated'];
            if ($diff < 120) $continuous += $diff / 60;
            else break;
        }
        if ($continuous > 60) { $score += 25; $reasons[] = "Actividad continua " . round($continuous) . " min"; }
        
        // Horario
        $hour = (int)date('G', $lastTime);
        if ($hour >= 3 && $hour <= 6) { $score += 15; $reasons[] = "Horario inusual ({$hour}h)"; }
        
        // Repetición
        $repeat = 1;
        $lastAction = $activity[0]['action'] . $activity[0]['target'];
        for ($i = 1; $i < min(10, count($activity)); $i++) {
            $thisAction = $activity[$i]['action'] . $activity[$i]['target'];
            if ($thisAction === $lastAction) $repeat++;
            else break;
        }
        if ($repeat >= 5) { $score += 20; $reasons[] = "Acción repetida x{$repeat}"; }
    }
    
    $score = min(100, $score);
    if ($score >= 60) { $label = '🤖 BOT'; $color = '#fc8181'; }
    elseif ($score >= 30) { $label = '⚠️ Sospechoso'; $color = '#fbd38d'; }
    else { $label = '👤 Humano'; $color = '#68d391'; }
    
    return array(
        'score' => $score,
        'reasons' => $reasons,
        'label' => $label,
        'color' => $color,
        'isOnline' => $isOnline,
        'lastActivity' => $lastActivity
    );
}

// === DISCO ===
function getDiskInfo($path = '/home/aulatuspeaking') {
    $total = @disk_total_space($path);
    $free = @disk_free_space($path);
    
    if ($total && $free) {
        $used = $total - $free;
        $percent = round(($used / $total) * 100, 1);
    } else {
        $df = shell_exec("df -B1 $path 2>/dev/null | tail -1");
        if ($df && preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%/', $df, $m)) {
            $total = (float)$m[1]; $used = (float)$m[2]; $free = (float)$m[3]; $percent = (float)$m[4];
        } else {
            $total = $used = $free = $percent = 0;
        }
    }
    
    return array('total' => $total, 'used' => $used, 'free' => $free, 'percent' => $percent);
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}

// === CAPTCHA ===
function isCaptchaActive() {
    return file_exists(PANEL_PATH . '/captcha_fundae.flag');
}

function setCaptcha($active) {
    $file = PANEL_PATH . '/captcha_fundae.flag';
    if ($active) file_put_contents($file, date('Y-m-d H:i:s'));
    else @unlink($file);
}
