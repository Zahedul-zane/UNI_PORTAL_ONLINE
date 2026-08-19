<?php
$pageTitle = "Students & Advisor Assignment";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg     = '';
$msgType = '';

// ─── Assign / change advisor ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_advisor'])) {
    $sid = sanitize($_POST['student_id'] ?? '');
    $fid = !empty($_POST['faculty_id']) ? sanitize($_POST['faculty_id']) : null;

    if (!empty($sid)) {
        try {
            $pdo->prepare("UPDATE Student SET Faculty_ID = ? WHERE Student_ID = ?")
                ->execute([$fid, $sid]);

            $advisorLabel = $fid ? 'assigned' : 'removed';
            $msg     = "Advisor $advisorLabel for student <strong>$sid</strong>.";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg     = 'Update failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── Register new student ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $studentId = sanitize($_POST['student_id'] ?? '');
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName  = sanitize($_POST['last_name'] ?? '');
    $email     = sanitize($_POST['email'] ?? '');
    $address   = sanitize($_POST['address'] ?? '');
    $dob       = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $deptId    = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : null;
    $facultyId = !empty($_POST['faculty_id_reg']) ? sanitize($_POST['faculty_id_reg']) : null;
    $phone1    = sanitize($_POST['phone1'] ?? '');
    $phone2    = sanitize($_POST['phone2'] ?? '');

    if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email)) {
        $msg     = 'Please fill all required fields (*).';
        $msgType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            $hash = password_hash('student123', PASSWORD_BCRYPT);
            $pdo->prepare("
                INSERT INTO Student (Student_ID, First_name, Last_name, E_mail, Password, Address, DOB, Faculty_ID, Dept_ID)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$studentId, $firstName, $lastName, $email, $hash, $address, $dob, $facultyId, $deptId]);

            if (!empty($phone1)) {
                $pdo->prepare("INSERT INTO Student_PhoneNum (Student_ID, Phone_Number1, Phone_Number2) VALUES (?, ?, ?)")
                    ->execute([$studentId, $phone1, $phone2 ?: null]);
            }
            $pdo->commit();
            $msg     = "Student $firstName $lastName registered. Default password: <code>student123</code>";
            $msgType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg     = 'Error: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── Delete student ────────────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $delId = sanitize($_GET['delete_id']);
    try {
        $pdo->prepare("DELETE FROM Student WHERE Student_ID = ?")->execute([$delId]);
        $msg     = "Student $delId and all associated records (phone numbers, enrollments, payments, pre-advising) deleted successfully.";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg     = 'Deletion failed: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// ─── Fetch data ────────────────────────────────────────────────────────────
$students = $pdo->query("
    SELECT s.Student_ID, s.First_name, s.Last_name, s.E_mail, s.Faculty_ID,
           d.Dept_Name,
           CONCAT(f.First_name, ' ', f.Last_name) AS Advisor_Name,
           f.E_mail AS Advisor_Email,
           sp.Phone_Number1
    FROM Student s
    LEFT JOIN Department d ON s.Dept_ID = d.Dept_ID
    LEFT JOIN Faculty f ON s.Faculty_ID = f.Faculty_ID
    LEFT JOIN Student_PhoneNum sp ON s.Student_ID = sp.Student_ID
    ORDER BY s.First_name ASC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM Department ORDER BY Dept_Name ASC")->fetchAll();
$facultyList = $pdo->query("SELECT Faculty_ID, First_name, Last_name, Designation FROM Faculty ORDER BY First_name ASC")->fetchAll();

$noAdvisor = array_filter($students, fn($s) => empty($s['Faculty_ID']));

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>👨‍🎓 Students & Advisor Assignment</h1>
    <p>Register students and assign faculty advisors. Advisors can then approve pre-advising course requests.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 20px;">
        <span><?= $msg ?></span>
    </div>
<?php endif; ?>

<!-- Summary stats -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon primary">👥</div>
        <div class="stat-details"><h3><?= count($students) ?></h3><p>Total Students</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">✅</div>
        <div class="stat-details">
            <h3><?= count($students) - count($noAdvisor) ?></h3>
            <p>Advisor Assigned</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger">⚠️</div>
        <div class="stat-details">
            <h3><?= count($noAdvisor) ?></h3>
            <p>No Advisor Yet</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">👨‍🏫</div>
        <div class="stat-details"><h3><?= count($facultyList) ?></h3><p>Available Advisors</p></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">

    <!-- ─── LEFT: Register new student ──────────── -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">➕ Register New Student</div>
        </div>
        <div class="panel-body">
            <form action="students.php" method="POST">
                <div class="form-group">
                    <label>Student ID * <small style="color:var(--text-muted);">(e.g. 2023-3-60-625)</small></label>
                    <input type="text" name="student_id" class="form-control" placeholder="2023-3-60-625" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" placeholder="name@std.ewubd.edu" required>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="dept_id" class="form-control">
                        <option value="">-- Select --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['Dept_ID'] ?>"><?= htmlspecialchars($d['Dept_Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>🎯 Assign Faculty Advisor</label>
                    <select name="faculty_id_reg" class="form-control">
                        <option value="">-- No Advisor Yet --</option>
                        <?php foreach ($facultyList as $fac): ?>
                            <option value="<?= $fac['Faculty_ID'] ?>">
                                <?= htmlspecialchars($fac['First_name'] . ' ' . $fac['Last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="text" name="phone1" class="form-control" placeholder="+88018..." required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Dhaka, Bangladesh">
                </div>
                <div style="background:#fffbeb; border:1px solid #f6c90e; border-radius:var(--radius-sm); padding:10px; font-size:12px; color:#856404; margin-bottom:14px;">
                    🔑 Default password is <strong>student123</strong>
                </div>
                <button type="submit" name="add_student" class="btn btn-gold" style="width:100%; padding:12px; font-size:14px;">
                    💾 Register Student
                </button>
            </form>
        </div>
    </div>

    <!-- ─── RIGHT: Student roster + advisor assignment ── -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Student Roster — Advisor Assignment</div>
            <input type="text" class="form-control table-search-input"
                   data-table="student_tbl" placeholder="🔍 Search name, ID..."
                   style="width:200px;">
        </div>
        <div class="panel-body" style="padding:0;">
            <table class="data-table" id="student_tbl">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Dept</th>
                        <th>Contact</th>
                        <th style="min-width:230px;">🎯 Faculty Advisor</th>
                        <th>Del</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; font-size:13px;">
                                    <?= htmlspecialchars($s['First_name'] . ' ' . $s['Last_name']) ?>
                                </div>
                                <code style="font-size:11px; color:var(--primary);"><?= htmlspecialchars($s['Student_ID']) ?></code>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-size:11px;">
                                    <?= htmlspecialchars($s['Dept_Name'] ?? '—') ?>
                                </span>
                            </td>
                            <td style="font-size:12px; color:var(--text-secondary);">
                                <?= htmlspecialchars($s['E_mail']) ?><br>
                                <span style="color:var(--text-muted);"><?= htmlspecialchars($s['Phone_Number1'] ?? '—') ?></span>
                            </td>
                            <td>
                                <!-- Inline advisor assignment — auto-submits on change -->
                                <form action="students.php" method="POST">
                                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($s['Student_ID']) ?>">
                                    <input type="hidden" name="update_advisor" value="1">
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <select name="faculty_id" class="form-control"
                                                style="font-size:12px; padding:5px 8px;"
                                                onchange="this.form.submit()">
                                            <option value="">— No Advisor —</option>
                                            <?php foreach ($facultyList as $fac): ?>
                                                <option value="<?= htmlspecialchars($fac['Faculty_ID']) ?>"
                                                    <?= $s['Faculty_ID'] == $fac['Faculty_ID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($fac['First_name'] . ' ' . $fac['Last_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                                <?php if (!empty($s['Advisor_Name'])): ?>
                                    <div style="font-size:11px; color:var(--success); margin-top:4px; padding-left:2px;">
                                        ✅ <?= htmlspecialchars($s['Advisor_Name']) ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:11px; color:var(--danger); margin-top:4px; padding-left:2px;">
                                        ⚠️ No advisor assigned
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="students.php?delete_id=<?= urlencode($s['Student_ID']) ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete student <?= htmlspecialchars(addslashes($s['First_name'])) ?>?')">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
