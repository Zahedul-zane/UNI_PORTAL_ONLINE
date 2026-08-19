<?php
// Session & Authorization Utility File
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Get logged-in user details from session
function get_session_user() {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

// Restrict page access to logged in users
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

// Restrict page to specific role ('student', 'faculty', 'admin')
function require_role($role) {
    require_login();
    if ($_SESSION['user_role'] !== $role) {
        // Redirect to appropriate dashboard if role mismatch
        switch ($_SESSION['user_role']) {
            case 'student':
                header("Location: " . BASE_URL . "/student/dashboard.php");
                break;
            case 'faculty':
                header("Location: " . BASE_URL . "/faculty/dashboard.php");
                break;
            case 'admin':
                header("Location: " . BASE_URL . "/admin/dashboard.php");
                break;
            default:
                header("Location: " . BASE_URL . "/login.php");
                break;
        }
        exit();
    }
}

// Fetch complete profile record based on role
function get_full_user_profile($pdo) {
    if (!is_logged_in()) return [];
    
    $role = $_SESSION['user_role'];
    $id = $_SESSION['user_id'];
    
    if ($role === 'student') {
        $stmt = $pdo->prepare("
            SELECT s.*, d.Dept_Name, 
                   CONCAT(f.First_name, ' ', f.Last_name) AS Advisor_Name,
                   f.E_mail AS Advisor_Email,
                   sp.Phone_Number1, sp.Phone_Number2
            FROM Student s
            LEFT JOIN Department d ON s.Dept_ID = d.Dept_ID
            LEFT JOIN Faculty f ON s.Faculty_ID = f.Faculty_ID
            LEFT JOIN Student_PhoneNum sp ON s.Student_ID = sp.Student_ID
            WHERE s.Student_ID = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return is_array($data) ? $data : [];
    } elseif ($role === 'faculty') {
        $stmt = $pdo->prepare("
            SELECT f.*, d.Dept_Name, 
                   fp.Phone_Number1, fp.Phone_Number2,
                   CASE WHEN d.Head_Faculty_ID = f.Faculty_ID THEN 1 ELSE 0 END AS Is_Head
            FROM Faculty f
            LEFT JOIN Department d ON f.Dept_ID = d.Dept_ID
            LEFT JOIN Faculty_PhoneNum fp ON f.Faculty_ID = fp.Faculty_ID
            WHERE f.Faculty_ID = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return is_array($data) ? $data : [];
    } elseif ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM Admin WHERE Admin_ID = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return is_array($data) ? $data : [];
    }
    
    return [];
}
?>
