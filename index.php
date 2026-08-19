<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'student') header("Location: " . BASE_URL . "/student/dashboard.php");
    elseif ($_SESSION['user_role'] === 'faculty') header("Location: " . BASE_URL . "/faculty/dashboard.php");
    elseif ($_SESSION['user_role'] === 'admin') header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}
header("Location: " . BASE_URL . "/login.php");
exit();
?>
