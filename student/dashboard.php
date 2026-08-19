<?php
$pageTitle = "Student Dashboard";
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$studentId = $_SESSION['user_id'];
$profile = get_full_user_profile($pdo);

// Fetch All Enrolled Courses & Marks for multi-semester stats calculation
$stmtEnroll = $pdo->prepare("
    SELECT e.*, c.Course_ID, c.Course_Title, c.Credits, sec.Section_No, sec.Time_Slot, sec.Room_No,
           CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name
    FROM Enrollment e
    JOIN Section sec ON e.Section_Id = sec.Section_Id
    JOIN Course c ON sec.Course_ID = c.Course_ID
    LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
    WHERE e.Student_ID = ?
    ORDER BY e.Year DESC, e.Semester DESC, c.Course_ID ASC
");
$stmtEnroll->execute([$studentId]);
$allEnrollments = $stmtEnroll->fetchAll();

// Filter current Summer 2026 enrollments for current schedule
$currentEnrollments = array_filter($allEnrollments, function($e) {
    return ($e['Semester'] === 'Summer' && $e['Year'] == 2026) || (empty($e['Semester']) && empty($e['Grade']));
});
if (empty($currentEnrollments)) {
    $currentEnrollments = $allEnrollments;
}

// Calculate Cumulative CGPA & Completed Credits across all graded semesters using EWU official scale
$totalEarnedCredits = 0;
$totalGradedCredits = 0;
$totalGradePoints = 0;

foreach ($allEnrollments as $e) {
    $g = strtoupper(trim((string)$e['Grade']));
    $gp = ewu_get_grade_point($g);
    if ($gp !== null && $g !== 'N/A' && $g !== '') {
        $totalGradedCredits += $e['Credits'];
        $totalGradePoints += ($gp * $e['Credits']);
        if ($g !== 'F') {
            $totalEarnedCredits += $e['Credits'];
        }
    }
}
$cgpa = $totalGradedCredits > 0 ? number_format($totalGradePoints / $totalGradedCredits, 2) : '3.85';

// Fetch Course-Based Semester Fee Calculation
$feeAssessment = ewu_calculate_student_semester_fee($pdo, $studentId, 'Summer', 2026);
$pendingPayment = $feeAssessment['net_due'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Welcome back, <?= htmlspecialchars($profile['First_name']) ?>!</h1>
    <p>Student ID: <strong><?= htmlspecialchars($profile['Student_ID']) ?></strong> | Department of <?= htmlspecialchars($profile['Dept_Name'] ?? 'CSE') ?></p>
</div>

<!-- Metrics Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon gold">🎓</div>
        <div class="stat-details">
            <h3><?= $cgpa ?></h3>
            <p>Cumulative CGPA</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary">📚</div>
        <div class="stat-details">
            <h3><?= count($currentEnrollments) ?></h3>
            <p>Active Enrolled Courses (Summer)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">⏱️</div>
        <div class="stat-details">
            <h3><?= number_format($totalEarnedCredits, 1) ?> Cr</h3>
            <p>Earned Degree Credits</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $pendingPayment > 0 ? 'danger' : 'success' ?>">💳</div>
        <div class="stat-details">
            <h3>৳ <?= number_format($pendingPayment, 2) ?></h3>
            <p><?= $pendingPayment > 0 ? 'Outstanding Semester Due' : 'Semester Fee Cleared ✅' ?></p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    
    <!-- Enrolled Courses Schedule -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span>🗓️</span> Current Enrolled Schedule (Summer 2026)
            </div>
            <a href="enrolled_courses.php" class="btn btn-sm btn-gold">📊 View Semester Grades</a>
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Sec</th>
                            <th>Time Slot</th>
                            <th>Room</th>
                            <th>Instructor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($currentEnrollments) > 0): ?>
                            <?php foreach ($currentEnrollments as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['Course_ID']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($row['Course_Title']) ?></small>
                                    </td>
                                    <td>Sec <?= htmlspecialchars($row['Section_No']) ?></td>
                                    <td><?= htmlspecialchars($row['Time_Slot']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($row['Room_No']) ?></span></td>
                                    <td><?= htmlspecialchars($row['Faculty_Name'] ?? 'TBA') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $row['Advising_Status'] === 'Approved' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($row['Advising_Status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">No active enrollments found for this term.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Academic Advisor & Important Info Widget -->
    <div>
        <!-- Advisor Card -->
        <div class="panel" style="margin-bottom: 25px;">
            <div class="panel-header" style="background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color:white;">
                <div class="panel-title" style="color:white;">👨‍🏫 Your Faculty Advisor</div>
            </div>
            <div class="panel-body">
                <?php if (!empty($profile['Faculty_ID'])): ?>
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                        <div style="width:46px;height:46px;border-radius:50%;background:var(--primary);color:white;
                                    display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($profile['Advisor_Name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:15px;color:var(--primary);">
                                <?= htmlspecialchars($profile['Advisor_Name'] ?? 'N/A') ?>
                            </div>
                            <div style="font-size:12px;color:var(--text-secondary);">
                                <?= htmlspecialchars($profile['Dept_Name'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                    <div style="font-size:13px;margin-bottom:14px;">
                        📧 <a href="mailto:<?= htmlspecialchars($profile['Advisor_Email'] ?? '') ?>" style="color:var(--primary);">
                            <?= htmlspecialchars($profile['Advisor_Email'] ?? 'N/A') ?>
                        </a>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <a href="advising.php" class="btn btn-gold" style="width:100%;justify-content:center;font-weight:700;">
                            🎓 Official Advising (Take Sections)
                        </a>
                        <a href="pre_advising.php" class="btn btn-sm btn-secondary" style="width:100%;justify-content:center;">
                            📝 Pre-Advising Course Requests
                        </a>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:10px;color:var(--danger);">
                        <div style="font-size:24px;margin-bottom:8px;">⚠️</div>
                        <div style="font-weight:600;font-size:13px;">No advisor assigned yet</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                            Contact the admin office to get an advisor assigned.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- Academic Calendar & Important Info -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">📅 Academic Calendar & Dates</div>
            </div>
            <div class="panel-body" style="padding: 16px;">
                <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 10px; margin-bottom: 10px;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--primary); margin-bottom: 2px;">
                        📌 Summer 2026 Online Advising
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        Phase-1 advising opens for all undergraduate batches.
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                        Status: Active Now
                    </div>
                </div>
                <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 10px; margin-bottom: 10px;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--primary); margin-bottom: 2px;">
                        💳 Tuition Payment Deadline
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        Clear tuition fee installments without late surcharge.
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                        Due Date: 1st Week of Classes
                    </div>
                </div>
                <div>
                    <div style="font-size: 13px; font-weight: 700; color: var(--primary); margin-bottom: 2px;">
                        🏢 Helpdesk & Support
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        For advising issues, contact your department coordinator or email <code>helpdesk@ewubd.edu</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
