<?php
// =========================================================
// EWU Portal - Global Configuration & Base URL Detection
// =========================================================
// Auto-detects whether running under Apache (/ewu_university_portal/)
// or PHP built-in dev server (/) and sets BASE_URL accordingly.

if (!defined('BASE_URL')) {
    // Detect server context: Apache with subdirectory vs built-in PHP server
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptName, '/ewu_university_portal/') !== false) {
        define('BASE_URL', '/ewu_university_portal');
    } else {
        define('BASE_URL', '');  // PHP built-in server at root
    }
}
?>
