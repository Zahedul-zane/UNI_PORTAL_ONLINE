<?php
$pageTitle = "Faculty Dashboard";
require_once __DIR__ . '/../config/auth.php';
require_role('faculty');

$facultyId = $_SESSION['user_id'];
$profile = get_full_user_profile($pdo);

// Fetch Assigned Teaching Sections
$stmtSec = $pdo->prepare("
    SELECT sec.*, c.Course_ID, c.Course_Title, c.Credits,
           (SELECT COUNT(*) FROM Enrollment e WHERE e.Section_Id = sec.Section_Id) AS EnrolledCount
    FROM Section sec
    JOIN Course c ON sec.Course_ID = c.Course_ID
    WHERE sec.Faculty_ID = ?
");
$stmtSec->execute([$facultyId]);
$sections = $stmtSec->fetchAll();

// Fetch All Assigned Advisee Students
$stmtAdvisees = $pdo->prepare("
    SELECT s.Student_ID, s.First_name, s.Last_name, s.E_mail, d.Dept_Name, sp.Phone_Number1,
           (SELECT COUNT(*) FROM Pre_Advising pa WHERE pa.Student_ID = s.Student_ID) AS PACount,
           (SELECT COUNT(*) FROM IncludedCourse ic JOIN Pre_Advising pa ON ic.Pre_Advising_ID = pa.Pre_Advising_ID WHERE pa.Student_ID = s.Student_ID AND ic.Status = 'Pending') AS PendingCount,
           (SELECT COUNT(*) FROM Enrollment e WHERE e.Student_ID = s.Student_ID AND e.Semester = 'Summer' AND e.Year = 2026) AS EnrolledCount
    FROM Student s
    LEFT JOIN Department d ON s.Dept_ID = d.Dept_ID
    LEFT JOIN Student_PhoneNum sp ON s.Student_ID = sp.Student_ID
    WHERE s.Faculty_ID = ?
    ORDER BY s.Student_ID ASC
");
$stmtAdvisees->execute([$facultyId]);
$adviseeList = $stmtAdvisees->fetchAll();

$adviseeCount = count($adviseeList);
$pendingAdvisingCount = array_sum(array_column($adviseeList, 'PendingCount'));

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Welcome, <?= htmlspecialchars($profile['First_name'] . ' ' . $profile['Last_name']) ?></h1>
    <p><?= htmlspecialchars($profile['Designation'] ?? 'Faculty Member') ?> | Department of <?= htmlspecialchars($profile['Dept_Name'] ?? 'CSE') ?> <?= !empty($profile['Is_Head']) ? ' • (Head of Department)' : '' ?></p>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon primary">🏫</div>
        <div class="stat-details">
            <h3><?= count($sections) ?></h3>
            <p>Assigned Sections</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">👨‍🎓</div>
        <div class="stat-details">
            <h3><?= $adviseeCount ?></h3>
            <p>Assigned Advisee Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">📝</div>
        <div class="stat-details">
            <h3><?= $pendingAdvisingCount ?></h3>
            <p>Pending Course Approvals</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">📍</div>
        <div class="stat-details">
            <h3><?= htmlspecialchars($profile['Room_No'] ?? 'AB1-602') ?></h3>
            <p>Faculty Office Room</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-bottom: 28px;">
    
    <!-- Teaching Schedule -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">🗓️ Assigned Course Sections & Schedule</div>
            <a href="sections.php" class="btn btn-sm btn-secondary">View Rosters</a>
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th style="text-align: center;">Section</th>
                            <th>Time Slot</th>
                            <th>Room</th>
                            <th style="text-align: center;">Capacity</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sections) > 0): ?>
                            <?php foreach ($sections as $s): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($s['Course_ID']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($s['Course_Title']) ?></small>
                                    </td>
                                    <td style="text-align: center;"><span class="badge badge-primary">Sec <?= htmlspecialchars($s['Section_No']) ?></span></td>
                                    <td><?= htmlspecialchars($s['Time_Slot']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($s['Room_No']) ?></span></td>
                                    <td style="text-align: center;">
                                        <strong><?= $s['EnrolledCount'] ?></strong> / <?= $s['Capacity'] ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="grading.php?section_id=<?= $s['Section_Id'] ?>" class="btn btn-sm btn-gold">
                                            Grades
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">No assigned sections for this semester.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts Panel -->
    <div>
        <div class="panel" style="margin-bottom: 25px;">
            <div class="panel-header">
                <div class="panel-title">⚡ Quick Management</div>
            </div>
            <div class="panel-body" style="display: flex; flex-direction: column; gap: 12px;">
                <a href="advisees.php" class="btn btn-primary" style="justify-content: flex-start; padding: 12px 16px;">
                    👨‍🎓 My Assigned Advisees Roster
                </a>
                <a href="advisees.php?tab=approvals" class="btn btn-gold" style="justify-content: flex-start; padding: 12px 16px;">
                    ✍️ Approve Pre-Advising Requests
                </a>
                <a href="grading.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 12px 16px;">
                    📊 Section Gradebook Management
                </a>
            </div>
        </div>
    </div>

</div>

<!-- ─── Full Advisee Students Overview on Faculty Dashboard ───────────────── -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="panel-title">
            👨‍🎓 Students Under Your Academic Advising Assignment (<?= $adviseeCount ?>)
        </div>
        <a href="advisees.php" class="btn btn-sm btn-primary">
            Open Full Advisee Portal & Approvals →
        </a>
    </div>
    <div class="panel-body" style="padding: 0;">
        <?php if (empty($adviseeList)): ?>
            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                No advisee students assigned yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Contact Phone</th>
                            <th style="text-align: center;">Pre-Advising Status</th>
                            <th style="text-align: center;">Enrolled Courses</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adviseeList as $adv): ?>
                            <tr>
                                <td><code style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($adv['Student_ID']) ?></code></td>
                                <td><strong><?= htmlspecialchars($adv['First_name'] . ' ' . $adv['Last_name']) ?></strong></td>
                                <td><a href="mailto:<?= htmlspecialchars($adv['E_mail']) ?>"><?= htmlspecialchars($adv['E_mail']) ?></a></td>
                                <td><?= htmlspecialchars($adv['Phone_Number1'] ?? 'N/A') ?></td>
                                <td style="text-align: center;">
                                    <?php if ($adv['PendingCount'] > 0): ?>
                                        <span class="badge badge-warning">⏳ <?= $adv['PendingCount'] ?> Course(s) Pending</span>
                                    <?php elseif ($adv['PACount'] > 0): ?>
                                        <span class="badge badge-success">✅ Approved</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">📝 Not Submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-info"><?= $adv['EnrolledCount'] ?> Course(s)</span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="advisees.php?tab=<?= $adv['PendingCount'] > 0 ? 'approvals' : 'roster' ?>" class="btn btn-sm btn-secondary">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
