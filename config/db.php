<?php
// Configuration & Database Connection File
// East West University Portal
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/grading.php';
require_once __DIR__ . '/scheduling.php';

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'portal_management_project');

try {
    // Connect to primary MySQL Database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If the database does not exist yet in MySQL, initialize it from database.sql
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $sqlPath = __DIR__ . '/../database.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            $pdo->exec($sql);
            
            // Reconnect after initialization
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            die("Database error: Database '" . DB_NAME . "' does not exist and database.sql was not found.");
        }
    } catch (PDOException $ex) {
        die("<div style='font-family: sans-serif; padding: 30px; background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; border-radius: 8px; margin: 40px auto; max-width: 600px;'>"
          . "<h2>EWU Portal - Database Connection Error</h2>"
          . "<p>Unable to connect to MySQL database at <code>" . DB_HOST . "</code>.</p>"
          . "<p><strong>Details:</strong> " . htmlspecialchars($ex->getMessage()) . "</p>"
          . "<hr/><p><em>Please ensure MySQL/MariaDB server is running in XAMPP and credentials in <code>config/db.php</code> are correct.</em></p>"
          . "</div>");
    }
}

// Function to sanitize user inputs
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
