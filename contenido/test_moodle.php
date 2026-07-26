<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/app/moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "Moodle cargado OK\n";
echo "Versión: " . $CFG->version . "\n";
echo "Función duplicate_module existe: " . (function_exists('duplicate_module') ? 'SI' : 'NO') . "\n";
