<?php
require_once('config.php');
require_login();

$redirect = optional_param('redirect', '/topics', PARAM_TEXT);
$userid = $USER->id;

// Generar token único
$token = bin2hex(random_bytes(32));

// Guardar token en BD (expira en 60 segundos)
$DB->execute("DELETE FROM {own_sso_tokens} WHERE userid = ? OR created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)", [$userid]);
$DB->execute("INSERT INTO {own_sso_tokens} (userid, token, created_at) VALUES (?, ?, NOW())", [$userid, $token]);

// Redirigir a learn.tuspeaking.com
$url = "https://learn.tuspeaking.com/autologin.php?token=" . $token . "&redirect=" . urlencode($redirect);
redirect($url);
