<?php
// Configuration & Excel Database Connection File
// East West University Portal
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/grading.php';
require_once __DIR__ . '/scheduling.php';
require_once __DIR__ . '/excel_db.php';

// Initialize and connect to the Excel Database Engine (zero SQL server required)
$pdo = ExcelDatabase::getConnection();

// Function to sanitize user inputs
function sanitize($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}
?>
