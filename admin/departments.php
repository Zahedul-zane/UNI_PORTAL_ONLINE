<?php
$pageTitle = "Department Management";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

// Handle Add / Edit Department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = intval($_POST['dept_id'] ?? 0);
    $deptName = sanitize($_POST['dept_name'] ?? '');
    $headFacultyId = !empty($_POST['head_faculty_id']) ? sanitize($_POST['head_faculty_id']) : null;

    if (isset($_POST['add_dept'])) {
        if ($deptId <= 0 || empty($deptName)) {
            $msg = 'Please provide a valid Department ID and Name.';
            $msgType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO Department (Dept_ID, Dept_Name, Head_Faculty_ID) VALUES (?, ?, ?)");
                $stmt->execute([$deptId, $deptName, $headFacultyId]);
                $msg = 'New Department created successfully!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error creating department: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    } elseif (isset($_POST['edit_dept'])) {
        try {
            $stmt = $pdo->prepare("UPDATE Department SET Dept_Name = ?, Head_Faculty_ID = ? WHERE Dept_ID = ?");
            $stmt->execute([$deptName, $headFacultyId, $deptId]);
            $msg = 'Department updated successfully!';
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Update failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM Department WHERE Dept_ID = ?");
        $stmt->execute([$delId]);
        $msg = 'Department deleted successfully.';
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = 'Error deleting department: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// Fetch Departments
$departments = $pdo->query("
    SELECT d.*, CONCAT(f.First_name, ' ', f.Last_name) AS Head_Name, f.Designation AS Head_Designation
    FROM Department d
    LEFT JOIN Faculty f ON d.Head_Faculty_ID = f.Faculty_ID
    ORDER BY d.Dept_ID ASC
")->fetchAll();

// Fetch Faculty List for Head Selector
$facultyList = $pdo->query("SELECT Faculty_ID, First_name, Last_name, Designation FROM Faculty ORDER BY First_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Academic Department Management</h1>
    <p>Configure university departments and assign Head of Department leadership.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">

    <!-- Add Form -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">➕ Add New Department</div>
        </div>
        <div class="panel-body">
            <form action="departments.php" method="POST">
                <div class="form-group">
                    <label for="dept_id">Department ID (Numeric PK)</label>
                    <input type="number" name="dept_id" id="dept_id" class="form-control" placeholder="e.g. 105" required>
                </div>

                <div class="form-group">
                    <label for="dept_name">Department Name (*)</label>
                    <input type="text" name="dept_name" id="dept_name" class="form-control" placeholder="e.g. Department of Law" required>
                </div>

                <div class="form-group">
                    <label for="head_faculty_id">Head of Department (Faculty)</label>
                    <select name="head_faculty_id" id="head_faculty_id" class="form-control">
                        <option value="">-- None / Unassigned --</option>
                        <?php foreach ($facultyList as $fac): ?>
                            <option value="<?= $fac['Faculty_ID'] ?>">
                                <?= htmlspecialchars($fac['First_name'] . ' ' . $fac['Last_name']) ?> (<?= htmlspecialchars($fac['Faculty_ID']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="add_dept" class="btn btn-gold" style="width: 100%; padding: 12px;">
                    💾 Create Department
                </button>
            </form>
        </div>
    </div>

    <!-- Departments Table -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">🏢 Active University Departments</div>
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Dept ID</th>
                            <th>Department Name</th>
                            <th>Head of Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td><strong><?= $d['Dept_ID'] ?></strong></td>
                                <td><?= htmlspecialchars($d['Dept_Name']) ?></td>
                                <td>
                                    <?php if ($d['Head_Name']): ?>
                                        <strong style="color: var(--primary);"><?= htmlspecialchars($d['Head_Name']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($d['Head_Designation'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="editDept(<?= $d['Dept_ID'] ?>, '<?= htmlspecialchars($d['Dept_Name'], ENT_QUOTES) ?>', '<?= $d['Head_Faculty_ID'] ?>')">
                                        ✏️ Edit
                                    </button>
                                    <a href="departments.php?delete_id=<?= $d['Dept_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this department?')">
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

<!-- Modal: Edit Dept -->
<div class="modal-overlay" id="editDeptModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Department</h3>
            <button class="modal-close" onclick="closeModal('editDeptModal')">&times;</button>
        </div>
        <form action="departments.php" method="POST">
            <div class="modal-body">
                <input type="hidden" name="dept_id" id="edit_dept_id">
                <div class="form-group">
                    <label for="edit_dept_name">Department Name</label>
                    <input type="text" name="dept_name" id="edit_dept_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_head_faculty_id">Head of Department</label>
                    <select name="head_faculty_id" id="edit_head_faculty_id" class="form-control">
                        <option value="">-- None / Unassigned --</option>
                        <?php foreach ($facultyList as $fac): ?>
                            <option value="<?= $fac['Faculty_ID'] ?>">
                                <?= htmlspecialchars($fac['First_name'] . ' ' . $fac['Last_name']) ?> (<?= htmlspecialchars($fac['Faculty_ID']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editDeptModal')">Cancel</button>
                <button type="submit" name="edit_dept" class="btn btn-gold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editDept(id, name, headId) {
    document.getElementById('edit_dept_id').value = id;
    document.getElementById('edit_dept_name').value = name;
    document.getElementById('edit_head_faculty_id').value = headId || '';
    openModal('editDeptModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
