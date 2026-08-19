<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'student') header("Location: " . BASE_URL . "/student/dashboard.php");
    elseif ($_SESSION['user_role'] === 'faculty') header("Location: " . BASE_URL . "/faculty/dashboard.php");
    elseif ($_SESSION['user_role'] === 'admin') header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}

$error = '';
$selectedRole = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRole = sanitize($_POST['selected_role'] ?? 'student');
    $user_id = sanitize($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($user_id) || empty($password)) {
        $error = 'Please enter both User ID and Password.';
    } else {
        if ($selectedRole === 'student') {
            $stmt = $pdo->prepare("SELECT * FROM Student WHERE Student_ID = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // Default student password is 'student123'
            if ($user && (password_verify($password, $user['Password']) || $password === 'student123')) {
                $_SESSION['user_id'] = $user['Student_ID'];
                $_SESSION['user_role'] = 'student';
                $_SESSION['user_name'] = $user['First_name'] . ' ' . $user['Last_name'];
                $_SESSION['user_email'] = $user['E_mail'];
                header("Location: " . BASE_URL . "/student/dashboard.php");
                exit();
            } else {
                $error = 'Invalid Student ID or Password.';
            }

        } elseif ($selectedRole === 'faculty') {
            $stmt = $pdo->prepare("SELECT * FROM Faculty WHERE Faculty_ID = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // Default faculty password is 'faculty123'
            if ($user && (password_verify($password, $user['Password']) || $password === 'faculty123')) {
                $_SESSION['user_id'] = $user['Faculty_ID'];
                $_SESSION['user_role'] = 'faculty';
                $_SESSION['user_name'] = $user['First_name'] . ' ' . $user['Last_name'];
                $_SESSION['user_email'] = $user['E_mail'];
                header("Location: " . BASE_URL . "/faculty/dashboard.php");
                exit();
            } else {
                $error = 'Invalid Faculty ID or Password.';
            }

        } elseif ($selectedRole === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM Admin WHERE Username = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // Default admin password is 'admin123'
            if ($user && (password_verify($password, $user['Password']) || $password === 'admin123')) {
                $_SESSION['user_id'] = $user['Admin_ID'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['user_name'] = $user['Full_Name'];
                $_SESSION['user_email'] = $user['E_mail'];
                header("Location: " . BASE_URL . "/admin/dashboard.php");
                exit();
            } else {
                $error = 'Invalid Admin Username or Password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>East West University - Portal Login</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="login-body">

    <div class="login-card">
        <div class="login-header">
            <div class="login-brand-logo">
                <img src="<?= BASE_URL ?>/images/EWU_LOGO.png" alt="East West University Logo">
            </div>
            <h2>East West University</h2>
            <p>Integrated Portal Authentication</p>
        </div>

        <!-- Role Tabs -->
        <div class="role-tabs">
            <button type="button" class="role-tab <?= $selectedRole === 'student' ? 'active' : '' ?>" data-role="student">Student</button>
            <button type="button" class="role-tab <?= $selectedRole === 'faculty' ? 'active' : '' ?>" data-role="faculty">Faculty</button>
            <button type="button" class="role-tab <?= $selectedRole === 'admin' ? 'active' : '' ?>" data-role="admin">Admin</button>
        </div>

        <div class="login-form-container">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>⚠️ <?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="hidden" name="selected_role" id="selected_role" value="<?= htmlspecialchars($selectedRole) ?>">

                <div class="form-group">
                    <label id="user_id_label" for="user_id">
                        <?= $selectedRole === 'student' ? 'Student ID (e.g. 2023-3-60-621)' : ($selectedRole === 'faculty' ? 'Faculty ID (e.g. 1652688915)' : 'Username (admin)') ?>
                    </label>
                    <input type="text" name="user_id" id="user_id" class="form-control" placeholder="Enter your ID or Username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                </div>

                <button type="submit" class="btn-primary">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    Sign In to Portal
                </button>
            </form>

            <!-- Quick Demo Login Helpers -->
            <div class="demo-credentials-box">
                <h4>⚡ Demo Credentials (Click to Auto-fill):</h4>
                <div class="demo-chip-list">
                    <div class="demo-chip" onclick="fillDemo('student', '2023-3-60-621', 'student123')">🎓 Student: 2023-3-60-621</div>
                    <div class="demo-chip" onclick="fillDemo('faculty', '1652688915', 'faculty123')">👨‍🏫 Faculty: 1652688915</div>
                    <div class="demo-chip" onclick="fillDemo('admin', 'admin', 'admin123')">🔑 Admin: admin</div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
