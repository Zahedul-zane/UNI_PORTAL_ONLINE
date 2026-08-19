<?php
$pageTitle = "Faculty Management";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

// Handle Add / Edit Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facultyId = sanitize($_POST['faculty_id'] ?? '');
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $designation = sanitize($_POST['designation'] ?? '');
    $roomNo = sanitize($_POST['room_no'] ?? '');
    $customRoom = sanitize($_POST['custom_room_no'] ?? '');
    if ($roomNo === '__custom__' && !empty($customRoom)) {
        $roomNo = $customRoom;
    }
    $email = sanitize($_POST['email'] ?? '');
    $deptId = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : null;
    $phone1 = sanitize($_POST['phone1'] ?? '');
    $phone2 = sanitize($_POST['phone2'] ?? '');

    if (isset($_POST['add_faculty'])) {
        if (empty($facultyId) || empty($firstName) || empty($lastName) || empty($email)) {
            $msg = 'Please fill out all required fields (*).';
            $msgType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Default password for faculty is 'faculty123'
                $defaultPass = password_hash('faculty123', PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO Faculty (Faculty_ID, First_name, Last_name, Designation, Room_No, E_mail, Password, Dept_ID)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$facultyId, $firstName, $lastName, $designation, $roomNo, $email, $defaultPass, $deptId]);

                if (!empty($phone1)) {
                    $stmtPhone = $pdo->prepare("INSERT INTO Faculty_PhoneNum (Faculty_ID, Phone_Number1, Phone_Number2) VALUES (?, ?, ?)");
                    $stmtPhone->execute([$facultyId, $phone1, $phone2]);
                }

                $pdo->commit();
                $msg = "Faculty member $firstName $lastName added successfully!";
                $msgType = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Error adding faculty: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delId = sanitize($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM Faculty WHERE Faculty_ID = ?");
        $stmt->execute([$delId]);
        $msg = 'Faculty record removed successfully.';
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = 'Error deleting faculty member: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// Fetch Faculty Roster
$facultyMembers = $pdo->query("
    SELECT f.*, d.Dept_Name, fp.Phone_Number1, fp.Phone_Number2
    FROM Faculty f
    LEFT JOIN Department d ON f.Dept_ID = d.Dept_ID
    LEFT JOIN Faculty_PhoneNum fp ON f.Faculty_ID = fp.Faculty_ID
    ORDER BY f.Faculty_ID ASC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM Department ORDER BY Dept_Name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Faculty Members Management</h1>
    <p>Register new academic staff, assign department roles, and maintain contact parameters.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">

    <!-- Add Faculty Form -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">👨‍🏫 Register New Faculty</div>
        </div>
        <div class="panel-body">
            <form action="faculty.php" method="POST">
                <div class="form-group">
                    <label for="faculty_id">Faculty ID (* Formatted like '1652688915')</label>
                    <input type="text" name="faculty_id" id="faculty_id" class="form-control" placeholder="1652688999" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label for="first_name">First Name (*)</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name (*)</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="designation">Designation</label>
                    <input type="text" name="designation" id="designation" class="form-control" placeholder="e.g. Assistant Professor">
                </div>

                <div class="form-group">
                    <label for="dept_id">Department</label>
                    <select name="dept_id" id="dept_id" class="form-control">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['Dept_ID'] ?>"><?= htmlspecialchars($dept['Dept_Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">Email (*)</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="name@ewubd.edu" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label for="room_no">Office Room No</label>
                        <select name="room_no" id="room_no" class="form-control" onchange="const w=document.getElementById('fac_custom_room_wrap'); if(this.value==='__custom__'){w.style.display='block';}else{w.style.display='none';}">
                            <option value="">-- Select Office Room --</option>
                            <?php $stdRooms = ewu_get_standard_rooms(); foreach ($stdRooms as $building => $rooms): ?>
                                <optgroup label="<?= htmlspecialchars($building) ?>">
                                    <?php foreach ($rooms as $code => $label): ?>
                                        <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                            <option value="__custom__">➕ Other Custom Room...</option>
                        </select>
                        <div id="fac_custom_room_wrap" style="display: none; margin-top: 6px;">
                            <input type="text" name="custom_room_no" class="form-control" placeholder="e.g. AB1-605">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone1">Phone 1 (*)</label>
                        <input type="text" name="phone1" id="phone1" class="form-control" placeholder="+88017..." required>
                    </div>
                </div>

                <button type="submit" name="add_faculty" class="btn btn-gold" style="width: 100%; padding: 12px;">
                    💾 Register Faculty Member
                </button>
            </form>
        </div>
    </div>

    <!-- Faculty Table -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Faculty Members Roster</div>
            <input type="text" class="form-control table-search-input" data-table="faculty_table" placeholder="🔍 Search faculty..." style="width: 200px;">
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table" id="faculty_table">
                    <thead>
                        <tr>
                            <th>Faculty ID</th>
                            <th>Name & Designation</th>
                            <th>Department</th>
                            <th>Room & Email</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facultyMembers as $f): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($f['Faculty_ID']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($f['First_name'] . ' ' . $f['Last_name']) ?></strong><br>
                                    <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($f['Designation'] ?? 'Faculty') ?></span>
                                </td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($f['Dept_Name'] ?? 'Unassigned') ?></span></td>
                                <td>
                                    <div style="font-size: 13px;"><?= htmlspecialchars($f['Room_No'] ?? 'N/A') ?></div>
                                    <small><a href="mailto:<?= htmlspecialchars($f['E_mail']) ?>"><?= htmlspecialchars($f['E_mail']) ?></a></small>
                                </td>
                                <td>
                                    <a href="faculty.php?delete_id=<?= $f['Faculty_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this faculty member?')">
                                        🗑️ Delete
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
