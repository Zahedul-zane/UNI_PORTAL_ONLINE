<?php
$pageTitle = "Student Advising & Course Enrollment";
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$studentId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Load student profile (includes advisor info)
$profile = get_full_user_profile($pdo);
$advisorId = $profile['Faculty_ID'] ?? null;
$deptId    = $profile['Dept_ID'] ?? 101;

// ─── POST: Submit Pre-Advising Form ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_preadvising'])) {
    $selectedCourses = $_POST['selected_courses'] ?? [];
    $semester = sanitize($_POST['semester'] ?? 'Summer');
    $year     = intval($_POST['year'] ?? date('Y'));

    if (empty($selectedCourses)) {
        $msg = 'Please select at least one course.';
        $msgType = 'danger';
    } elseif (empty($advisorId)) {
        $msg = 'You do not have an assigned advisor yet. Please contact the admin office.';
        $msgType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO Pre_Advising (Semester, Year, Student_ID) VALUES (?, ?, ?)");
            $stmt->execute([$semester, $year, $studentId]);
            $paId = $pdo->lastInsertId();

            $stmtInc = $pdo->prepare("INSERT INTO IncludedCourse (Pre_Advising_ID, Course_ID, Status) VALUES (?, ?, 'Pending')");
            foreach ($selectedCourses as $cid) {
                $stmtInc->execute([$paId, sanitize($cid)]);
            }
            $pdo->commit();
            $msg     = 'Pre-Advising submitted for ' . count($selectedCourses) . ' course(s). Waiting for your advisor to review.';
            $msgType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg     = 'Error: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── POST: Enroll in a section (only for advisor-approved courses) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_section'])) {
    $sectionId = intval($_POST['section_id'] ?? 0);
    $courseId  = sanitize($_POST['course_id'] ?? '');

    if ($sectionId <= 0 || empty($courseId)) {
        $msg = 'Invalid section.';
        $msgType = 'danger';
    } else {
        // Must be approved in pre-advising
        $stmtOk = $pdo->prepare("
            SELECT ic.Status FROM IncludedCourse ic
            JOIN Pre_Advising pa ON ic.Pre_Advising_ID = pa.Pre_Advising_ID
            WHERE pa.Student_ID = ? AND ic.Course_ID = ? AND ic.Status = 'Approved'
        ");
        $stmtOk->execute([$studentId, $courseId]);

        if (!$stmtOk->fetch()) {
            $msg     = "Course $courseId has not been approved by your advisor yet.";
            $msgType = 'danger';
        } else {
            // Already enrolled?
            $stmtDup = $pdo->prepare("
                SELECT e.Enrollment_ID FROM Enrollment e
                JOIN Section sec ON e.Section_Id = sec.Section_Id
                WHERE e.Student_ID = ? AND sec.Course_ID = ?
            ");
            $stmtDup->execute([$studentId, $courseId]);
            if ($stmtDup->fetch()) {
                $msg     = "You are already enrolled in $courseId.";
                $msgType = 'warning';
            } else {
                // Check capacity
                $stmtCap = $pdo->prepare("
                    SELECT sec.*, COUNT(e.Enrollment_ID) AS Filled
                    FROM Section sec
                    LEFT JOIN Enrollment e ON sec.Section_Id = e.Section_Id
                    WHERE sec.Section_Id = ?
                    GROUP BY sec.Section_Id
                ");
                $stmtCap->execute([$sectionId]);
                $sec = $stmtCap->fetch();

                if (!$sec) {
                    $msg = 'Section not found.';
                    $msgType = 'danger';
                } elseif ($sec['Filled'] >= $sec['Capacity']) {
                    $msg     = "Section {$sec['Section_No']} is full. Please choose another.";
                    $msgType = 'danger';
                } else {
                    $stmtE = $pdo->prepare("
                        INSERT INTO Enrollment (Enrollment_Type, Advising_Status, Section_Id, ManagedBy_Faculty_ID, Student_ID, Semester, Year)
                        VALUES ('Regular', 'Approved', ?, ?, ?, 'Summer', 2026)
                    ");
                    $stmtE->execute([$sectionId, $advisorId, $studentId]);
                    $msg     = "✅ Enrolled in $courseId — Section {$sec['Section_No']} ({$sec['Time_Slot']}).";
                    $msgType = 'success';
                }
            }
        }
    }
}

// ─── Load available courses for this dept ─────────────────────────────────
$stmtCourses = $pdo->prepare("
    SELECT c.*, GROUP_CONCAT(cp.Pre_Course_ID SEPARATOR ', ') AS Prerequisites
    FROM Course c
    LEFT JOIN Course_Prerequisite cp ON c.Course_ID = cp.Course_ID
    WHERE c.Dept_ID = ? OR c.Dept_ID IS NULL
    GROUP BY c.Course_ID
    ORDER BY c.Course_ID ASC
");
$stmtCourses->execute([$deptId]);
$deptCourses = $stmtCourses->fetchAll();

// ─── Load all pre-advising forms this student submitted ───────────────────
$stmtPA = $pdo->prepare("
    SELECT pa.Pre_Advising_ID, pa.Semester, pa.Year, pa.Submission_TimeStamp,
           ic.Course_ID, ic.Status, c.Course_Title, c.Credits
    FROM Pre_Advising pa
    JOIN IncludedCourse ic ON pa.Pre_Advising_ID = ic.Pre_Advising_ID
    JOIN Course c ON ic.Course_ID = c.Course_ID
    WHERE pa.Student_ID = ?
    ORDER BY pa.Submission_TimeStamp DESC, ic.Course_ID ASC
");
$stmtPA->execute([$studentId]);
$paRows = $stmtPA->fetchAll();

// Group by Pre_Advising_ID
$forms = [];
foreach ($paRows as $row) {
    $pid = $row['Pre_Advising_ID'];
    if (!isset($forms[$pid])) {
        $forms[$pid] = [
            'id'        => $pid,
            'semester'  => $row['Semester'],
            'year'      => $row['Year'],
            'submitted' => $row['Submission_TimeStamp'],
            'courses'   => []
        ];
    }
    // For each approved course get its sections + enrollment
    $sections   = [];
    $enrollment = null;
    if ($row['Status'] === 'Approved') {
        $stmtSec = $pdo->prepare("
            SELECT sec.*, CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name,
                   (SELECT COUNT(*) FROM Enrollment e WHERE e.Section_Id = sec.Section_Id) AS Filled
            FROM Section sec
            LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
            WHERE sec.Course_ID = ?
            ORDER BY sec.Section_No ASC
        ");
        $stmtSec->execute([$row['Course_ID']]);
        $sections = $stmtSec->fetchAll();

        $stmtEnr = $pdo->prepare("
            SELECT e.*, sec.Section_No, sec.Time_Slot, sec.Room_No
            FROM Enrollment e
            JOIN Section sec ON e.Section_Id = sec.Section_Id
            WHERE e.Student_ID = ? AND sec.Course_ID = ?
        ");
        $stmtEnr->execute([$studentId, $row['Course_ID']]);
        $enrollment = $stmtEnr->fetch();
    }

    $forms[$pid]['courses'][$row['Course_ID']] = [
        'course_id'   => $row['Course_ID'],
        'title'       => $row['Course_Title'],
        'credits'     => $row['Credits'],
        'status'      => $row['Status'],
        'sections'    => $sections,
        'enrollment'  => $enrollment
    ];
}

// Summary counters
$totalApproved = 0;
$totalPending  = 0;
$totalEnrolled = 0;
foreach ($forms as $f) {
    foreach ($f['courses'] as $c) {
        if ($c['status'] === 'Approved') $totalApproved++;
        if ($c['status'] === 'Pending')  $totalPending++;
        if ($c['enrollment'])            $totalEnrolled++;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>📋 Pre-Advising & Course Enrollment</h1>
    <p>Submit course requests to your advisor, then enroll in sections once they are approved.</p>
</div>

<div style="background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%); border-radius: var(--radius-md); padding: 18px 24px; margin-bottom: 24px; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: var(--shadow-md);">
    <div>
        <div style="font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
            <span>🎓</span> Official Advising & Section Selection is Live!
        </div>
        <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
            Once your advisor approves your requested courses, open Official Advising to select your section and schedule.
        </div>
    </div>
    <a href="advising.php" class="btn btn-gold" style="font-weight: 800; padding: 10px 20px; font-size: 14px; white-space: nowrap;">
        ⚡ Go to Official Advising →
    </a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 20px;">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════
     TOP ROW: Advisor card + stats
══════════════════════════════════════════════════════ -->
<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 18px; margin-bottom: 28px;">

    <!-- Advisor info -->
    <div class="stat-card" style="grid-column: span 2; align-items: flex-start; gap: 14px; flex-direction: row;">
        <div class="stat-icon primary" style="font-size: 26px; flex-shrink:0;">👨‍🏫</div>
        <div>
            <?php if ($advisorId && !empty($profile['Advisor_Name'])): ?>
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--text-muted); margin-bottom: 3px;">Your Assigned Advisor</div>
                <div style="font-size: 17px; font-weight: 700; color: var(--primary);"><?= htmlspecialchars($profile['Advisor_Name']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">
                    <?= htmlspecialchars($profile['Dept_Name'] ?? '') ?>
                    &nbsp;·&nbsp;
                    <a href="mailto:<?= htmlspecialchars($profile['Advisor_Email'] ?? '') ?>" style="color:var(--primary);">
                        <?= htmlspecialchars($profile['Advisor_Email'] ?? 'N/A') ?>
                    </a>
                </div>
            <?php else: ?>
                <div style="font-size: 13px; font-weight: 600; color: var(--danger);">⚠️ No Advisor Assigned</div>
                <div style="font-size: 12px; color: var(--text-muted);">Please contact the admin office to get an advisor assigned.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">⏳</div>
        <div class="stat-details"><h3><?= $totalPending ?></h3><p>Awaiting Approval</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">✅</div>
        <div class="stat-details"><h3><?= $totalApproved ?></h3><p>Advisor Approved</p></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MAIN GRID: Left = Submit form | Right = Status & Enroll
══════════════════════════════════════════════════════ -->
<div style="display: grid; grid-template-columns: 5fr 7fr; gap: 25px; align-items: start;">

    <!-- ─── LEFT: Submit Pre-Advising ─────────────────── -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📝 Step 1 — Request Courses from Advisor</div>
        </div>
        <div class="panel-body">
            <?php if (empty($advisorId)): ?>
                <div style="background:#fff3cd; border:1px solid #ffc107; border-radius: var(--radius-sm); padding:14px; font-size:13px; color:#856404;">
                    ⚠️ You must have an advisor assigned before you can submit a pre-advising request.
                    Please contact the admin office.
                </div>
            <?php else: ?>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px; line-height: 1.6;">
                    Select the courses you want to take this semester. Your advisor
                    <strong><?= htmlspecialchars($profile['Advisor_Name']) ?></strong>
                    will review and approve each course.
                </p>
                <form action="pre_advising.php" method="POST">
                    <input type="hidden" name="semester" value="Summer">
                    <input type="hidden" name="year" value="2026">
                    <div style="max-height: 420px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-sm); margin-bottom: 16px;">
                        <table class="data-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width:36px; text-align:center;">✓</th>
                                    <th>Course</th>
                                    <th>Cr.</th>
                                    <th>Pre-req</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deptCourses as $course): ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="selected_courses[]"
                                                   value="<?= htmlspecialchars($course['Course_ID']) ?>"
                                                   style="width:16px;height:16px;cursor:pointer;">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($course['Course_ID']) ?></strong><br>
                                            <small style="color:var(--text-muted);"><?= htmlspecialchars($course['Course_Title']) ?></small>
                                        </td>
                                        <td><span class="badge badge-info"><?= number_format($course['Credits'], 1) ?></span></td>
                                        <td>
                                            <?php if ($course['Prerequisites']): ?>
                                                <span class="badge badge-warning" style="font-size:11px;"><?= htmlspecialchars($course['Prerequisites']) ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-size:11px;">None</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="submit_preadvising"
                            class="btn btn-gold" style="width:100%; padding:11px; font-size:14px; font-weight:600;">
                        📤 Send to Advisor for Review
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── RIGHT: Advisor Status + Section Enrollment ── -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">🎓 Step 2 & 3 — Approval Status & Section Enrollment</div>
        </div>
        <div class="panel-body" style="padding: 0;">
            <?php if (empty($forms)): ?>
                <div style="text-align:center; padding: 50px 20px; color: var(--text-muted);">
                    <div style="font-size: 40px; margin-bottom: 12px;">📭</div>
                    <div style="font-size: 14px;">No pre-advising requests submitted yet.<br>Use the form on the left to get started.</div>
                </div>
            <?php else: ?>
                <?php foreach ($forms as $f): ?>
                    <div style="border-bottom: 2px solid var(--border-color); padding: 0;">
                        <!-- Form Header -->
                        <div style="background: var(--bg-secondary); padding: 12px 18px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-weight:700; color:var(--primary); font-size:14px;">
                                    📅 <?= htmlspecialchars($f['semester']) ?> <?= htmlspecialchars($f['year']) ?> — Form #<?= $f['id'] ?>
                                </span>
                                <span style="font-size:11px; color:var(--text-muted); margin-left:10px;">
                                    Submitted: <?= date('d M Y, g:i A', strtotime($f['submitted'])) ?>
                                </span>
                            </div>
                            <span class="badge badge-info"><?= count($f['courses']) ?> course(s)</span>
                        </div>

                        <!-- Course rows -->
                        <?php foreach ($f['courses'] as $c): ?>
                            <div style="padding: 14px 18px; border-bottom: 1px solid #f1f5f9;">

                                <!-- Course title row -->
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <span style="font-weight:700; font-size:14px; color:var(--primary-dark);">
                                            <?= htmlspecialchars($c['course_id']) ?>
                                        </span>
                                        <span style="font-size:13px; color:var(--text-secondary); margin-left:6px;">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </span>
                                        <span class="badge badge-info" style="margin-left:6px;"><?= number_format($c['credits'], 1) ?> Cr</span>
                                    </div>
                                    <!-- Status badge -->
                                    <?php if ($c['status'] === 'Approved'): ?>
                                        <span class="badge badge-success" style="font-size:12px; padding:5px 10px;">✅ Advisor Approved</span>
                                    <?php elseif ($c['status'] === 'Rejected'): ?>
                                        <span class="badge badge-danger" style="font-size:12px; padding:5px 10px;">❌ Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="font-size:12px; padding:5px 10px;">⏳ Awaiting Review</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Enrollment area -->
                                <?php if ($c['status'] === 'Approved'): ?>
                                    <?php if ($c['enrollment']): ?>
                                        <!-- Already enrolled -->
                                        <div style="background:#f0fff4; border:1px solid #38a169; border-radius:var(--radius-sm); padding:10px 14px; display:flex; align-items:center; gap:10px;">
                                            <span style="font-size:20px;">🎉</span>
                                            <div>
                                                <div style="font-weight:700; font-size:13px; color:#276749;">Enrolled — Section <?= htmlspecialchars($c['enrollment']['Section_No']) ?></div>
                                                <div style="font-size:12px; color:#2f855a;">
                                                    🕐 <?= htmlspecialchars($c['enrollment']['Time_Slot']) ?>
                                                    &nbsp;&nbsp;📍 <?= htmlspecialchars($c['enrollment']['Room_No']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif (count($c['sections']) > 0): ?>
                                        <!-- Pick a section and enroll -->
                                        <form action="pre_advising.php" method="POST">
                                            <input type="hidden" name="course_id" value="<?= htmlspecialchars($c['course_id']) ?>">
                                            <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                                                <select name="section_id" class="form-control"
                                                        style="flex:1; min-width:230px; font-size:13px;" required>
                                                    <option value="">— Choose a class section —</option>
                                                    <?php foreach ($c['sections'] as $sec): ?>
                                                        <?php $full = $sec['Filled'] >= $sec['Capacity']; ?>
                                                        <option value="<?= $sec['Section_Id'] ?>"
                                                                <?= $full ? 'disabled' : '' ?>>
                                                            Sec <?= $sec['Section_No'] ?>
                                                            | <?= htmlspecialchars($sec['Time_Slot']) ?>
                                                            | Room <?= htmlspecialchars($sec['Room_No']) ?>
                                                            | <?= htmlspecialchars($sec['Faculty_Name'] ?? 'TBA') ?>
                                                            [<?= $sec['Filled'] ?>/<?= $sec['Capacity'] ?>]
                                                            <?= $full ? ' — FULL' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="enroll_section"
                                                        class="btn btn-success" style="padding:8px 16px; font-size:13px; white-space:nowrap;">
                                                    ⚡ Enroll
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div style="font-size:12px; color:var(--text-muted); background:#f8f9fa; padding:8px 12px; border-radius:var(--radius-sm);">
                                            ℹ️ Approved — no class sections scheduled yet. Please check back later.
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($c['status'] === 'Rejected'): ?>
                                    <div style="font-size:12px; color:#c53030; background:#fff5f5; padding:8px 12px; border-radius:var(--radius-sm);">
                                        This course was rejected by your advisor. Please consult them for guidance.
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:12px; color:#d69e2e; background:#fffbeb; padding:8px 12px; border-radius:var(--radius-sm);">
                                        🔒 Section enrollment unlocks once your advisor approves this course.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
