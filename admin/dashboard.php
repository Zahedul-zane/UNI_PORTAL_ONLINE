<?php
$pageTitle = "Admin Control Panel";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

// Fetch System Overview Metrics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM Student")->fetchColumn();
$totalFaculty = $pdo->query("SELECT COUNT(*) FROM Faculty")->fetchColumn();
$totalDepts = $pdo->query("SELECT COUNT(*) FROM Department")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM Course")->fetchColumn();
$totalSections = $pdo->query("SELECT COUNT(*) FROM Section")->fetchColumn();
$totalPayments = $pdo->query("SELECT SUM(Amount) FROM Payment WHERE Payment_Status = 'Paid'")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>EWU Portal Administrator Control Center</h1>
    <p>Complete management of university departments, faculty, students, course scheduling, and financial ledgers.</p>
</div>

<!-- Key System Metrics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">🏢</div>
        <div class="stat-details">
            <h3><?= $totalDepts ?></h3>
            <p>Academic Departments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">👨‍🏫</div>
        <div class="stat-details">
            <h3><?= $totalFaculty ?></h3>
            <p>Faculty Members</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">🎓</div>
        <div class="stat-details">
            <h3><?= $totalStudents ?></h3>
            <p>Enrolled Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">📚</div>
        <div class="stat-details">
            <h3><?= $totalCourses ?> (<?= $totalSections ?> Secs)</h3>
            <p>Offered Courses & Sections</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary">💳</div>
        <div class="stat-details">
            <h3>৳ <?= number_format($totalPayments ?? 0) ?></h3>
            <p>Total Tuition Fees Collected</p>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🛠️ System Operations Directory</div>
    </div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
            
            <a href="departments.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon primary">🏢</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Departments</h3>
                    <p>Manage Depts & Head Faculty</p>
                </div>
            </a>

            <a href="faculty.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon gold">👨‍🏫</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Faculty Directory</h3>
                    <p>Register Faculty & Phone Numbers</p>
                </div>
            </a>

            <a href="students.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon success">🎓</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Student Records</h3>
                    <p>Register Students & Assign Advisors</p>
                </div>
            </a>

            <a href="courses.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon warning">📖</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Courses & Pre-reqs</h3>
                    <p>Manage Curriculum & Prerequisites</p>
                </div>
            </a>

            <a href="sections.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon primary">🗓️</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Class Sections</h3>
                    <p>Schedule Rooms, Slots & Capacity</p>
                </div>
            </a>

            <a href="enrollments.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon gold">📝</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Enrollments</h3>
                    <p>Master Course Enrollments</p>
                </div>
            </a>

            <a href="payments.php" class="stat-card" style="border: 1px solid var(--border-color); text-decoration: none;">
                <div class="stat-icon success">💳</div>
                <div class="stat-details">
                    <h3 style="font-size: 16px;">Payments Ledger</h3>
                    <p>Financial Verification & Status</p>
                </div>
            </a>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
