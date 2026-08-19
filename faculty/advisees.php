<?php
$pageTitle = "Assigned Advisees & Pre-Advising Approvals";
require_once __DIR__ . '/../config/auth.php';
require_role('faculty');

$facultyId = $_SESSION['user_id'];
$msg = '';
$msgType = '';
$activeTab = sanitize($_GET['tab'] ?? 'roster');

// ─── POST: Approve or Reject a single course ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course_status'])) {
    $paId     = intval($_POST['pre_advising_id'] ?? 0);
    $courseId = sanitize($_POST['course_id'] ?? '');
    $status   = in_array($_POST['status'] ?? '', ['Approved', 'Rejected', 'Pending'])
                ? $_POST['status'] : 'Approved';

    // Security check: ensure pre-advising belongs to an advisee assigned to this faculty
    $stmtCheck = $pdo->prepare("
        SELECT pa.Pre_Advising_ID FROM Pre_Advising pa
        JOIN Student s ON pa.Student_ID = s.Student_ID
        WHERE pa.Pre_Advising_ID = ? AND s.Faculty_ID = ?
    ");
    $stmtCheck->execute([$paId, $facultyId]);

    if ($stmtCheck->fetch()) {
        $pdo->prepare("UPDATE IncludedCourse SET Status = ? WHERE Pre_Advising_ID = ? AND Course_ID = ?")
            ->execute([$status, $paId, $courseId]);
        $msg     = "Course <strong>$courseId</strong> marked as <strong>$status</strong>.";
        $msgType = 'success';
    } else {
        $msg     = 'Unauthorized action or invalid advisee record.';
        $msgType = 'danger';
    }
}

// ─── POST: Bulk approve/reject all courses in one pre-advising form ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $paId   = intval($_POST['pre_advising_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Approved', 'Rejected'])
              ? $_POST['status'] : 'Approved';

    $stmtCheck = $pdo->prepare("
        SELECT pa.Pre_Advising_ID FROM Pre_Advising pa
        JOIN Student s ON pa.Student_ID = s.Student_ID
        WHERE pa.Pre_Advising_ID = ? AND s.Faculty_ID = ?
    ");
    $stmtCheck->execute([$paId, $facultyId]);

    if ($stmtCheck->fetch()) {
        $pdo->prepare("UPDATE IncludedCourse SET Status = ? WHERE Pre_Advising_ID = ?")
            ->execute([$status, $paId]);
        $count   = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        $msg     = "All courses in Pre-Advising Form #$paId set to <strong>$status</strong>.";
        $msgType = 'success';
    } else {
        $msg     = 'Unauthorized action.';
        $msgType = 'danger';
    }
}

// ─── Load All Assigned Advisees for this Faculty Advisor ─────────────────
$stmtStudents = $pdo->prepare("
    SELECT s.Student_ID, s.First_name, s.Last_name, s.E_mail, s.Address, s.DOB,
           d.Dept_Name, sp.Phone_Number1, sp.Phone_Number2
    FROM Student s
    LEFT JOIN Department d ON s.Dept_ID = d.Dept_ID
    LEFT JOIN Student_PhoneNum sp ON s.Student_ID = sp.Student_ID
    WHERE s.Faculty_ID = ?
    ORDER BY s.Student_ID ASC
");
$stmtStudents->execute([$facultyId]);
$advisees = $stmtStudents->fetchAll();

// Build comprehensive advisee dataset including enrollments & pre-advising
$adviseeRoster = [];
$totalPendingReviews = 0;
$totalApprovedStudents = 0;
$totalNotSubmitted = 0;

foreach ($advisees as $st) {
    $sid = $st['Student_ID'];

    // 1. Fetch Summer 2026 Pre-Advising Forms
    $stmtPa = $pdo->prepare("
        SELECT pa.Pre_Advising_ID, pa.Semester, pa.Year, pa.Submission_TimeStamp,
               ic.Course_ID, ic.Status, c.Course_Title, c.Credits
        FROM Pre_Advising pa
        JOIN IncludedCourse ic ON pa.Pre_Advising_ID = ic.Pre_Advising_ID
        JOIN Course c ON ic.Course_ID = c.Course_ID
        WHERE pa.Student_ID = ?
        ORDER BY pa.Submission_TimeStamp DESC, ic.Course_ID ASC
    ");
    $stmtPa->execute([$sid]);
    $paRows = $stmtPa->fetchAll();

    $forms = [];
    $studentPendingCount = 0;
    $studentApprovedCount = 0;
    $studentRejectedCount = 0;

    foreach ($paRows as $row) {
        $pid = $row['Pre_Advising_ID'];
        if (!isset($forms[$pid])) {
            $forms[$pid] = [
                'id'        => $pid,
                'semester'  => $row['Semester'],
                'year'      => $row['Year'],
                'submitted' => $row['Submission_TimeStamp'],
                'courses'   => [],
                'pending'   => 0,
                'approved'  => 0,
                'rejected'  => 0,
            ];
        }
        $forms[$pid]['courses'][] = [
            'course_id' => $row['Course_ID'],
            'title'     => $row['Course_Title'],
            'credits'   => $row['Credits'],
            'status'    => $row['Status'],
        ];

        if ($row['Status'] === 'Pending')  { $forms[$pid]['pending']++;  $studentPendingCount++; }
        if ($row['Status'] === 'Approved') { $forms[$pid]['approved']++; $studentApprovedCount++; }
        if ($row['Status'] === 'Rejected') { $forms[$pid]['rejected']++; $studentRejectedCount++; }
    }

    // 2. Fetch Active Enrolled Courses for Summer 2026
    $stmtEnr = $pdo->prepare("
        SELECT e.*, c.Course_ID, c.Course_Title, c.Credits,
               sec.Section_No, sec.Time_Slot, sec.Room_No,
               CONCAT(f.First_name, ' ', f.Last_name) AS Teacher_Name
        FROM Enrollment e
        JOIN Section sec ON e.Section_Id = sec.Section_Id
        JOIN Course c ON sec.Course_ID = c.Course_ID
        LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
        WHERE e.Student_ID = ? AND e.Semester = 'Summer' AND e.Year = 2026
    ");
    $stmtEnr->execute([$sid]);
    $enrolledList = $stmtEnr->fetchAll();

    $totalEnrolledCredits = array_sum(array_column($enrolledList, 'Credits'));

    // Determine Overall Status for this student
    if (empty($forms)) {
        $overallStatus = 'Not Submitted';
        $statusBadge = 'badge-secondary';
        $totalNotSubmitted++;
    } elseif ($studentPendingCount > 0) {
        $overallStatus = 'Pending Review';
        $statusBadge = 'badge-warning';
        $totalPendingReviews++;
    } elseif ($studentApprovedCount > 0 && $studentRejectedCount === 0) {
        $overallStatus = 'Approved';
        $statusBadge = 'badge-success';
        $totalApprovedStudents++;
    } else {
        $overallStatus = 'Partial / Action Needed';
        $statusBadge = 'badge-danger';
    }

    $adviseeRoster[] = [
        'student'               => $st,
        'forms'                 => array_values($forms),
        'enrolled_courses'      => $enrolledList,
        'total_enrolled_credits'=> $totalEnrolledCredits,
        'pending_count'         => $studentPendingCount,
        'approved_count'        => $studentApprovedCount,
        'rejected_count'        => $studentRejectedCount,
        'overall_status'        => $overallStatus,
        'status_badge'          => $statusBadge,
    ];
}

$totalAdvisees = count($adviseeRoster);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>👨‍🎓 My Assigned Advisee Students & Approvals</h1>
    <p>View all students assigned under your faculty academic advising, inspect their registered courses, and approve pre-advising submissions.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 24px;">
        <span><?= $msg ?></span>
    </div>
<?php endif; ?>

<!-- Stats Overview Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon primary">👥</div>
        <div class="stat-details">
            <h3><?= $totalAdvisees ?></h3>
            <p>Total Assigned Advisees</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">⏳</div>
        <div class="stat-details">
            <h3><?= $totalPendingReviews ?></h3>
            <p>Advisees Awaiting Review</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">✅</div>
        <div class="stat-details">
            <h3><?= $totalApprovedStudents ?></h3>
            <p>Advising Fully Approved</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">📝</div>
        <div class="stat-details">
            <h3><?= $totalNotSubmitted ?></h3>
            <p>No Pre-Advising Request</p>
        </div>
    </div>
</div>

<!-- Tab Navigation Header -->
<div style="display: flex; gap: 10px; margin-bottom: 24px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px;">
    <button type="button" class="btn <?= $activeTab === 'roster' ? 'btn-primary' : 'btn-secondary' ?>"
            onclick="switchAdviseeTab('roster')" id="tab_btn_roster" style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
        <span>👨‍🎓 Assigned Advisee Students Roster</span>
        <span class="badge" style="background: rgba(255,255,255,0.25); color: inherit;"><?= $totalAdvisees ?></span>
    </button>
    <button type="button" class="btn <?= $activeTab === 'approvals' ? 'btn-primary' : 'btn-secondary' ?>"
            onclick="switchAdviseeTab('approvals')" id="tab_btn_approvals" style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
        <span>📋 Course Approval Workflow</span>
        <?php if ($totalPendingReviews > 0): ?>
            <span class="badge badge-warning"><?= $totalPendingReviews ?> Action Needed</span>
        <?php endif; ?>
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 1: ASSIGNED ADVISEE STUDENTS ROSTER                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab_roster_content" style="display: <?= $activeTab === 'roster' ? 'block' : 'none' ?>;">
    
    <div class="panel">
        <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div class="panel-title">
                📋 All Students Assigned to Your Advising Group
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" id="advisee_search_input" class="form-control" placeholder="🔍 Search student name or ID..." style="width: 250px; font-size: 13px;" onkeyup="filterAdviseeRoster()">
                <select id="advisee_status_filter" class="form-control" style="width: auto; font-size: 13px;" onchange="filterAdviseeRoster()">
                    <option value="">All Statuses</option>
                    <option value="Pending Review">⏳ Pending Review</option>
                    <option value="Approved">✅ Approved</option>
                    <option value="Not Submitted">📝 Not Submitted</option>
                </select>
            </div>
        </div>

        <div class="panel-body" style="padding: 0;">
            <?php if (empty($adviseeRoster)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                    <div style="font-size: 52px; margin-bottom: 12px;">👥</div>
                    <h3 style="margin-bottom: 8px; color: var(--text-primary);">No Advisee Students Assigned Yet</h3>
                    <p style="font-size: 14px;">The administrative office has not linked student records to your advisor ID (<code><?= htmlspecialchars($facultyId) ?></code>).</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table" id="advisee_master_table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Contact Information</th>
                                <th style="text-align: center;">Summer 2026 Status</th>
                                <th style="text-align: center;">Enrolled Courses</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adviseeRoster as $item):
                                $st = $item['student'];
                                $enrolledCount = count($item['enrolled_courses']);
                            ?>
                                <tr data-student-id="<?= htmlspecialchars($st['Student_ID']) ?>"
                                    data-student-name="<?= htmlspecialchars($st['First_name'] . ' ' . $st['Last_name']) ?>"
                                    data-status="<?= htmlspecialchars($item['overall_status']) ?>">
                                    <td>
                                        <code style="font-weight: 700; font-size: 13px; color: var(--primary);">
                                            <?= htmlspecialchars($st['Student_ID']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                                                <?= strtoupper(substr($st['First_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($st['First_name'] . ' ' . $st['Last_name']) ?></strong>
                                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($st['E_mail']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 13px;"><?= htmlspecialchars($st['Dept_Name'] ?? 'General') ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;">
                                            <div>📞 <?= htmlspecialchars($st['Phone_Number1'] ?? 'N/A') ?></div>
                                            <?php if (!empty($st['Phone_Number2'])): ?>
                                                <small style="color: var(--text-muted);">Alt: <?= htmlspecialchars($st['Phone_Number2']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge <?= $item['status_badge'] ?>" style="font-size: 12px; padding: 5px 10px;">
                                            <?= htmlspecialchars($item['overall_status']) ?>
                                        </span>
                                        <?php if ($item['pending_count'] > 0): ?>
                                            <div style="font-size: 11px; color: #d97706; margin-top: 3px; font-weight: 600;">
                                                (<?= $item['pending_count'] ?> course pending)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($enrolledCount > 0): ?>
                                            <span class="badge badge-info" style="font-size: 12px;">
                                                <?= $enrolledCount ?> Course(s) • <?= number_format($item['total_enrolled_credits'], 1) ?> Cr
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 12px;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 6px;">
                                            <!-- View Dossier Modal Button -->
                                            <button type="button" class="btn btn-sm btn-primary" title="View Full Dossier"
                                                    onclick='openAdviseeModal(<?= json_encode($item) ?>)'>
                                                👁️ Dossier
                                            </button>
                                            <!-- Review Button if pending -->
                                            <?php if (!empty($item['forms'])): ?>
                                                <button type="button" class="btn btn-sm btn-gold" title="Go to Approvals"
                                                        onclick="switchAdviseeTab('approvals')">
                                                    ✍️ Review
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 2: COURSE-BY-COURSE APPROVAL WORKFLOW                                   -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab_approvals_content" style="display: <?= $activeTab === 'approvals' ? 'block' : 'none' ?>;">

    <?php if (empty($adviseeRoster)): ?>
        <div class="panel">
            <div class="panel-body" style="text-align: center; padding: 60px; color: var(--text-muted);">
                <div style="font-size: 48px; margin-bottom: 12px;">👥</div>
                <div style="font-size: 16px; font-weight: 600;">No advisees assigned yet.</div>
            </div>
        </div>
    <?php else: ?>
        <?php
        $submissionsFound = false;
        foreach ($adviseeRoster as $item):
            $student = $item['student'];
            $forms   = $item['forms'];
            if (empty($forms)) continue;
            $submissionsFound = true;
        ?>
            <div class="panel" style="margin-bottom: 24px;">
                <!-- Student Header Banner -->
                <div class="panel-header" style="background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%); color: white;">
                    <div class="panel-title" style="color: white; display: flex; align-items: center; gap: 12px;">
                        <div style="width: 42px; height: 42px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0;">
                            <?= strtoupper(substr($student['First_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size: 16px; font-weight: 700;"><?= htmlspecialchars($student['First_name'] . ' ' . $student['Last_name']) ?></div>
                            <div style="font-size: 12px; opacity: .85; font-weight: 400;">
                                ID: <strong><?= htmlspecialchars($student['Student_ID']) ?></strong>
                                &nbsp;·&nbsp; <?= htmlspecialchars($student['Dept_Name'] ?? 'Department') ?>
                                &nbsp;·&nbsp; ✉️ <?= htmlspecialchars($student['E_mail']) ?>
                                &nbsp;·&nbsp; 📞 <?= htmlspecialchars($student['Phone_Number1'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                    <span style="background: rgba(255,255,255,0.2); color: white; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                        <?= count($forms) ?> Submission Form(s)
                    </span>
                </div>

                <div class="panel-body" style="padding: 0;">
                    <?php foreach ($forms as $f): ?>
                        <div style="border-bottom: 1px solid var(--border-color);">
                            <!-- Form Sub-header -->
                            <div style="background: #f8fafc; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color);">
                                <div>
                                    <strong style="color: var(--primary); font-size: 14px;">
                                        📅 <?= htmlspecialchars($f['semester']) ?> <?= htmlspecialchars($f['year']) ?> — Form #<?= $f['id'] ?>
                                    </strong>
                                    <span style="font-size: 12px; color: var(--text-muted); margin-left: 10px;">
                                        Submitted on: <?= date('d M Y, g:i A', strtotime($f['submitted'])) ?>
                                    </span>
                                </div>

                                <!-- Bulk Actions -->
                                <div style="display: flex; gap: 8px;">
                                    <form action="advisees.php?tab=approvals" method="POST" style="display: inline;" onsubmit="return confirm('Approve ALL course requests in this form?')">
                                        <input type="hidden" name="pre_advising_id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" name="bulk_action" class="btn btn-sm btn-success" style="font-weight: 600;">
                                            ✅ Approve All Courses
                                        </button>
                                    </form>
                                    <form action="advisees.php?tab=approvals" method="POST" style="display: inline;" onsubmit="return confirm('Reject ALL course requests in this form?')">
                                        <input type="hidden" name="pre_advising_id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" name="bulk_action" class="btn btn-sm btn-danger" style="font-weight: 600;">
                                            ❌ Reject All Courses
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Course by Course Table -->
                            <div class="table-responsive">
                                <table class="data-table" style="font-size: 13px;">
                                    <thead>
                                        <tr>
                                            <th style="width: 140px;">Course Code</th>
                                            <th>Course Title</th>
                                            <th style="text-align: center; width: 100px;">Credits</th>
                                            <th style="text-align: center; width: 160px;">Advisor Decision</th>
                                            <th style="text-align: center; width: 220px;">Review Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($f['courses'] as $c): ?>
                                            <tr>
                                                <td><strong style="color: var(--primary);"><?= htmlspecialchars($c['course_id']) ?></strong></td>
                                                <td><?= htmlspecialchars($c['title']) ?></td>
                                                <td style="text-align: center;">
                                                    <span class="badge badge-info"><?= number_format($c['credits'], 1) ?> Cr</span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <?php if ($c['status'] === 'Approved'): ?>
                                                        <span class="badge badge-success" style="padding: 5px 10px;">✅ Approved</span>
                                                    <?php elseif ($c['status'] === 'Rejected'): ?>
                                                        <span class="badge badge-danger" style="padding: 5px 10px;">❌ Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning" style="padding: 5px 10px;">⏳ Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <div style="display: flex; justify-content: center; gap: 8px;">
                                                        <!-- Approve Button -->
                                                        <form action="advisees.php?tab=approvals" method="POST" style="display: inline;">
                                                            <input type="hidden" name="pre_advising_id" value="<?= $f['id'] ?>">
                                                            <input type="hidden" name="course_id" value="<?= htmlspecialchars($c['course_id']) ?>">
                                                            <input type="hidden" name="status" value="Approved">
                                                            <button type="submit" name="update_course_status"
                                                                    class="btn btn-sm btn-success"
                                                                    style="padding: 4px 12px; font-size: 12px;"
                                                                    <?= $c['status'] === 'Approved' ? 'disabled' : '' ?>>
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <!-- Reject Button -->
                                                        <form action="advisees.php?tab=approvals" method="POST" style="display: inline;">
                                                            <input type="hidden" name="pre_advising_id" value="<?= $f['id'] ?>">
                                                            <input type="hidden" name="course_id" value="<?= htmlspecialchars($c['course_id']) ?>">
                                                            <input type="hidden" name="status" value="Rejected">
                                                            <button type="submit" name="update_course_status"
                                                                    class="btn btn-sm btn-danger"
                                                                    style="padding: 4px 12px; font-size: 12px;"
                                                                    <?= $c['status'] === 'Rejected' ? 'disabled' : '' ?>>
                                                                Reject
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Summary Pills -->
                            <div style="padding: 10px 20px; background: #fafafa; display: flex; gap: 10px; flex-wrap: wrap;">
                                <span class="badge badge-warning">⏳ <?= $f['pending'] ?> Pending</span>
                                <span class="badge badge-success">✅ <?= $f['approved'] ?> Approved</span>
                                <span class="badge badge-danger">❌ <?= $f['rejected'] ?> Rejected</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$submissionsFound): ?>
            <div class="panel">
                <div class="panel-body" style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <div style="font-size: 40px; margin-bottom: 10px;">📝</div>
                    <div style="font-size: 16px; font-weight: 600;">No pending pre-advising submissions from your assigned advisees.</div>
                    <div style="font-size: 13px; margin-top: 6px;">When students submit course requests in Pre-Advising, they will appear here for your review.</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<!-- ─── MODAL: Advisee Student Dossier ──────────────────────────────────────── -->
<div class="modal-overlay" id="advisee_dossier_modal">
    <div class="modal-card" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="dossier_title">👨‍🎓 Advisee Student Dossier</h3>
            <button type="button" class="modal-close" onclick="closeModal('advisee_dossier_modal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;" id="dossier_body">
            <!-- Dynamically populated via JS -->
        </div>
        <div class="modal-footer" style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('advisee_dossier_modal')">Close</button>
        </div>
    </div>
</div>

<script>
// Tab Switching
function switchAdviseeTab(tabName) {
    const rosterContent = document.getElementById('tab_roster_content');
    const approvalsContent = document.getElementById('tab_approvals_content');
    const rosterBtn = document.getElementById('tab_btn_roster');
    const approvalsBtn = document.getElementById('tab_btn_approvals');

    if (tabName === 'roster') {
        rosterContent.style.display = 'block';
        approvalsContent.style.display = 'none';
        rosterBtn.className = 'btn btn-primary';
        approvalsBtn.className = 'btn btn-secondary';
    } else {
        rosterContent.style.display = 'none';
        approvalsContent.style.display = 'block';
        rosterBtn.className = 'btn btn-secondary';
        approvalsBtn.className = 'btn btn-primary';
    }
}

// Live Filter on Advisee Roster Table
function filterAdviseeRoster() {
    const searchVal = document.getElementById('advisee_search_input').value.toLowerCase();
    const statusVal = document.getElementById('advisee_status_filter').value;
    const rows = document.querySelectorAll('#advisee_master_table tbody tr');

    rows.forEach(row => {
        const name = (row.getAttribute('data-student-name') || '').toLowerCase();
        const id   = (row.getAttribute('data-student-id') || '').toLowerCase();
        const stat = row.getAttribute('data-status') || '';

        let matchesSearch = (!searchVal || name.includes(searchVal) || id.includes(searchVal));
        let matchesStatus = (!statusVal || stat === statusVal);

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

// Open Advisee Student Dossier Modal
function openAdviseeModal(item) {
    const st = item.student;
    const titleEl = document.getElementById('dossier_title');
    const bodyEl = document.getElementById('dossier_body');

    titleEl.textContent = `👨‍🎓 Student Dossier: ${st.First_name} ${st.Last_name}`;

    let enrolledHtml = '';
    if (item.enrolled_courses && item.enrolled_courses.length > 0) {
        enrolledHtml = `
            <table class="data-table" style="font-size:12px; margin-top:8px;">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Section</th>
                        <th>Time Slot</th>
                        <th>Room</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    ${item.enrolled_courses.map(c => `
                        <tr>
                            <td><strong>${c.Course_ID}</strong><br><small style="color:var(--text-muted);">${c.Course_Title}</small></td>
                            <td>Sec ${c.Section_No}</td>
                            <td>${c.Time_Slot}</td>
                            <td><span class="badge badge-info">${c.Room_No}</span></td>
                            <td><span class="badge badge-success">${c.Grade || 'N/A'}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        enrolledHtml = `<p style="color:var(--text-muted); font-size:13px; margin-top:6px;">No officially registered sections for Summer 2026 yet.</p>`;
    }

    bodyEl.innerHTML = `
        <!-- Profile Card Header -->
        <div style="display:flex; align-items:center; gap:16px; padding-bottom:18px; border-bottom:1px solid var(--border-color); margin-bottom:18px;">
            <div style="width:54px; height:54px; border-radius:50%; background:linear-gradient(135deg, var(--primary), #2563eb); color:white; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700;">
                ${st.First_name.charAt(0).toUpperCase()}
            </div>
            <div>
                <h3 style="margin:0; font-size:18px; color:var(--text-primary);">${st.First_name} ${st.Last_name}</h3>
                <div style="font-size:13px; color:var(--text-muted); margin-top:3px;">
                    ID: <strong>${st.Student_ID}</strong> &nbsp;•&nbsp; ${st.Dept_Name || 'Department'}
                </div>
            </div>
        </div>

        <!-- Academic & Contact Details Grid -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; font-size:13px;">
            <div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid var(--border-color);">
                <div style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:600;">Email Address</div>
                <div style="font-weight:600; margin-top:2px;"><a href="mailto:${st.E_mail}">${st.E_mail}</a></div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid var(--border-color);">
                <div style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:600;">Phone Contact</div>
                <div style="font-weight:600; margin-top:2px;">${st.Phone_Number1 || 'N/A'} ${st.Phone_Number2 ? ' / ' + st.Phone_Number2 : ''}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid var(--border-color);">
                <div style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:600;">Residential Address</div>
                <div style="font-weight:600; margin-top:2px;">${st.Address || 'Dhaka, Bangladesh'}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid var(--border-color);">
                <div style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:600;">Date of Birth</div>
                <div style="font-weight:600; margin-top:2px;">${st.DOB || 'N/A'}</div>
            </div>
        </div>

        <!-- Active Enrolled Courses -->
        <div>
            <h4 style="margin:0 0 8px 0; font-size:14px; color:var(--primary); display:flex; justify-content:space-between;">
                <span>📚 Current Enrolled Sections (Summer 2026)</span>
                <span>${item.total_enrolled_credits} Credits</span>
            </h4>
            ${enrolledHtml}
        </div>
    `;

    openModal('advisee_dossier_modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
