<?php
$pageTitle = "Master Enrollment Management";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

// Handle Add Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_enrollment'])) {
    $studentId = sanitize($_POST['student_id'] ?? '');
    $sectionId = intval($_POST['section_id'] ?? 0);
    $enrollmentType = sanitize($_POST['enrollment_type'] ?? 'Regular');
    $status = sanitize($_POST['advising_status'] ?? 'Approved');

    $semester = sanitize($_POST['semester'] ?? 'Summer');
    $year = intval($_POST['year'] ?? 2026);

    if (empty($studentId) || empty($sectionId)) {
        $msg = 'Please select both a student and a course section.';
        $msgType = 'danger';
    } else {
        try {
            // Check if already enrolled in this section
            $stmtCheck = $pdo->prepare("SELECT * FROM Enrollment WHERE Student_ID = ? AND Section_Id = ?");
            $stmtCheck->execute([$studentId, $sectionId]);
            if ($stmtCheck->fetch()) {
                $msg = 'Student is already enrolled in this section.';
                $msgType = 'danger';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO Enrollment (Enrollment_Type, Advising_Status, Section_Id, Student_ID, Semester, Year)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$enrollmentType, $status, $sectionId, $studentId, $semester, $year]);

                $msg = "Enrollment created successfully for student $studentId!";
                $msgType = 'success';
            }
        } catch (Exception $e) {
            $msg = 'Error creating enrollment: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Delete Enrollment
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM Enrollment WHERE Enrollment_ID = ?");
        $stmt->execute([$delId]);
        $msg = 'Enrollment record deleted.';
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = 'Error deleting enrollment: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// Fetch Master Enrollments
$enrollments = $pdo->query("
    SELECT e.*, s.First_name, s.Last_name, c.Course_ID, c.Course_Title, sec.Section_No,
           CONCAT(f.First_name, ' ', f.Last_name) AS Manager_Faculty
    FROM Enrollment e
    JOIN Student s ON e.Student_ID = s.Student_ID
    JOIN Section sec ON e.Section_Id = sec.Section_Id
    JOIN Course c ON sec.Course_ID = c.Course_ID
    LEFT JOIN Faculty f ON e.ManagedBy_Faculty_ID = f.Faculty_ID
    ORDER BY e.Enrollment_ID DESC
")->fetchAll();

$students = $pdo->query("SELECT Student_ID, First_name, Last_name FROM Student ORDER BY Student_ID ASC")->fetchAll();
$sections = $pdo->query("
    SELECT sec.Section_Id, sec.Section_No, c.Course_ID, c.Course_Title
    FROM Section sec
    JOIN Course c ON sec.Course_ID = c.Course_ID
    ORDER BY c.Course_ID ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Master Student Course Enrollments</h1>
    <p>Direct manual registration of students into course sections and status oversight.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">

    <!-- Add Enrollment Form -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📝 Manual Enrollment</div>
        </div>
        <div class="panel-body">
            <form action="enrollments.php" method="POST">
                <div class="form-group">
                    <label for="student_id">Select Student (*)</label>
                    <select name="student_id" id="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $st): ?>
                            <option value="<?= $st['Student_ID'] ?>"><?= htmlspecialchars($st['Student_ID'] . ' - ' . $st['First_name'] . ' ' . $st['Last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="section_id">Select Course Section (*)</label>
                    <select name="section_id" id="section_id" class="form-control" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?= $sec['Section_Id'] ?>"><?= htmlspecialchars($sec['Course_ID'] . ' (Sec ' . $sec['Section_No'] . ') - ' . $sec['Course_Title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" class="form-control">
                            <option value="Summer">Summer</option>
                            <option value="Spring">Spring</option>
                            <option value="Fall">Fall</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" name="year" id="year" class="form-control" value="2026" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label for="enrollment_type">Type</label>
                        <select name="enrollment_type" id="enrollment_type" class="form-control">
                            <option value="Regular">Regular</option>
                            <option value="Retake">Retake</option>
                            <option value="Recycle">Recycle</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="advising_status">Advising Status</label>
                        <select name="advising_status" id="advising_status" class="form-control">
                            <option value="Approved">Approved</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="add_enrollment" class="btn btn-gold" style="width: 100%; padding: 12px;">
                    💾 Confirm Enrollment
                </button>
            </form>
        </div>
    </div>

    <!-- Enrollments Table -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Active System Enrollments</div>
            <input type="text" class="form-control table-search-input" data-table="enroll_table" placeholder="🔍 Search enrollment..." style="width: 200px;">
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table" id="enroll_table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Course & Sec</th>
                            <th>Term / Year</th>
                            <th>Type</th>
                            <th>Mid / Final / Grade</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $e): ?>
                            <tr>
                                <td><code>#<?= $e['Enrollment_ID'] ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($e['First_name'] . ' ' . $e['Last_name']) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($e['Student_ID']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($e['Course_ID']) ?></strong> (Sec <?= $e['Section_No'] ?>)
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars(($e['Semester'] ?? 'Summer') . ' ' . ($e['Year'] ?? '2026')) ?></span>
                                </td>
                                <td><?= htmlspecialchars($e['Enrollment_Type']) ?></td>
                                <td>
                                    <span style="font-size: 13px;">
                                        Mid: <?= $e['Mid_Mark'] !== null ? number_format($e['Mid_Mark'], 1) : '-' ?> | 
                                        Final: <?= $e['Final_Mark'] !== null ? number_format($e['Final_Mark'], 1) : '-' ?>
                                    </span><br>
                                    <span class="badge <?= ewu_get_grade_badge_class($e['Grade']) ?>"><?= htmlspecialchars($e['Grade'] ?? 'N/A') ?></span>
                                </td>
                                <td><span class="badge badge-<?= $e['Advising_Status'] === 'Approved' ? 'success' : 'warning' ?>"><?= htmlspecialchars($e['Advising_Status']) ?></span></td>
                                <td>
                                    <a href="enrollments.php?delete_id=<?= $e['Enrollment_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete enrollment #<?= $e['Enrollment_ID'] ?>?')">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
