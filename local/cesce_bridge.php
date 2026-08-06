<?php
/**
 * CESCE Auth Bridge - validates Moodle session and redirects to app tools
 * Usage: /app/moodle/local/cesce_bridge.php?to=viewer&id=8&course=1432
 *        /app/moodle/local/cesce_bridge.php?to=evaluation
 */
require_once(__DIR__ . '/../config.php');
require_login();

$userid = $USER->id;

// Generate temporary token (8 hours)
$token = bin2hex(random_bytes(16));
$expires = time() + 28800;

// Clean expired tokens
$DB->delete_records_select('cesce_auth_tokens', 'expires < ?', array(time()));

// Insert new token
$record = new stdClass();
$record->token = $token;
$record->userid = $userid;
$record->expires = $expires;
$record->created = time();
$record->used = 0;
$DB->insert_record('cesce_auth_tokens', $record);

// Build redirect URL
$to = required_param('to', PARAM_ALPHA);
$base = 'https://cesce.tuspeaking.com/app/';

$params = array();
parse_str($_SERVER['QUERY_STRING'], $params);
unset($params['to']);
$params['token'] = $token;

$targets = array(
    'viewer' => 'lesson-viewer.php',
    'evaluation' => 'class-evaluation.php',
    'download' => 'download-lesson.php',
    'dashboard' => 'dashboard.php'
);

if (!isset($targets[$to])) {
    print_error('Invalid destination');
}

redirect($base . $targets[$to] . '?' . http_build_query($params));
