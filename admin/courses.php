<?php
$pageTitle = "Course & Prerequisite Management";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

// Handle Add Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $courseId = sanitize($_POST['course_id'] ?? '');
    $title = sanitize($_POST['course_title'] ?? '');
    $credits = floatval($_POST['credits'] ?? 3.0);
    $deptId = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : null;

    if (empty($courseId) || empty($title)) {
        $msg = 'Please specify Course ID and Title.';
        $msgType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Course (Course_ID, Course_Title, Credits, Dept_ID) VALUES (?, ?, ?, ?)");
            $stmt->execute([$courseId, $title, $credits, $deptId]);
            $msg = "Course $courseId created successfully!";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Error creating course: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Handle Add Prerequisite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prereq'])) {
    $mainCourseId = sanitize($_POST['main_course_id'] ?? '');
    $preCourseId = sanitize($_POST['pre_course_id'] ?? '');

    if ($mainCourseId === $preCourseId) {
        $msg = 'A course cannot be its own prerequisite!';
        $msgType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Course_Prerequisite (Course_ID, Pre_Course_ID) VALUES (?, ?)");
            $stmt->execute([$mainCourseId, $preCourseId]);
            $msg = "Prerequisite added successfully ($preCourseId -> $mainCourseId)!";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Error creating prerequisite: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Delete Prerequisite
if (isset($_GET['del_pre_course']) && isset($_GET['del_main_course'])) {
    $mId = sanitize($_GET['del_main_course']);
    $pId = sanitize($_GET['del_pre_course']);
    $stmt = $pdo->prepare("DELETE FROM Course_Prerequisite WHERE Course_ID = ? AND Pre_Course_ID = ?");
    $stmt->execute([$mId, $pId]);
    $msg = 'Prerequisite mapping removed.';
    $msgType = 'success';
}

// Delete Course
if (isset($_GET['delete_course_id'])) {
    $delC = sanitize($_GET['delete_course_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM Course WHERE Course_ID = ?");
        $stmt->execute([$delC]);
        $msg = "Course $delC and all associated prerequisites/sections deleted successfully.";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = 'Error deleting course: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// Fetch Courses & Prerequisites
$courses = $pdo->query("
    SELECT c.*, d.Dept_Name,
           GROUP_CONCAT(cp.Pre_Course_ID SEPARATOR ', ') AS Prerequisites
    FROM Course c
    LEFT JOIN Department d ON c.Dept_ID = d.Dept_ID
    LEFT JOIN Course_Prerequisite cp ON c.Course_ID = cp.Course_ID
    GROUP BY c.Course_ID
    ORDER BY c.Course_ID ASC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM Department ORDER BY Dept_Name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Academic Courses & Prerequisite Architecture</h1>
    <p>Manage curriculum subjects, credit weights, and prerequisite dependencies.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">

    <!-- Left Side: Forms -->
    <div>
        <!-- Add Course -->
        <div class="panel" style="margin-bottom: 25px;">
            <div class="panel-header">
                <div class="panel-title">📚 Add New Course</div>
            </div>
            <div class="panel-body">
                <form action="courses.php" method="POST">
                    <div class="form-group">
                        <label for="course_id">Course ID (* e.g. 'CSE-302')</label>
                        <input type="text" name="course_id" id="course_id" class="form-control" placeholder="CSE-302" required>
                    </div>

                    <div class="form-group">
                        <label for="course_title">Course Title (*)</label>
                        <input type="text" name="course_title" id="course_title" class="form-control" placeholder="Database Systems" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label for="credits">Credits (*)</label>
                            <input type="number" step="0.5" name="credits" id="credits" class="form-control" value="3.0" required>
                        </div>
                        <div class="form-group">
                            <label for="dept_id">Department</label>
                            <select name="dept_id" id="dept_id" class="form-control">
                                <option value="">-- Dept --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['Dept_ID'] ?>"><?= htmlspecialchars($dept['Dept_Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_course" class="btn btn-gold" style="width: 100%; padding: 10px;">
                        💾 Save Course
                    </button>
                </form>
            </div>
        </div>

        <!-- Add Prerequisite -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">🔗 Link Course Prerequisite</div>
            </div>
            <div class="panel-body">
                <form action="courses.php" method="POST">
                    <div class="form-group">
                        <label for="main_course_id">Main Course</label>
                        <select name="main_course_id" id="main_course_id" class="form-control" required>
                            <option value="">-- Select Target Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['Course_ID'] ?>"><?= htmlspecialchars($c['Course_ID'] . ': ' . $c['Course_Title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pre_course_id">Prerequisite Required Course</label>
                        <select name="pre_course_id" id="pre_course_id" class="form-control" required>
                            <option value="">-- Select Prerequisite Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['Course_ID'] ?>"><?= htmlspecialchars($c['Course_ID'] . ': ' . $c['Course_Title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="add_prereq" class="btn btn-secondary" style="width: 100%; padding: 10px;">
                        🔗 Attach Prerequisite
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Side: Courses Table -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📖 Curriculum Course List</div>
            <input type="text" class="form-control table-search-input" data-table="course_table" placeholder="🔍 Search course..." style="width: 200px;">
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table" id="course_table">
                    <thead>
                        <tr>
                            <th>Course ID</th>
                            <th>Course Title</th>
                            <th>Credits</th>
                            <th>Prerequisite Requirements</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><strong style="color: var(--primary);"><?= htmlspecialchars($c['Course_ID']) ?></strong></td>
                                <td><?= htmlspecialchars($c['Course_Title']) ?></td>
                                <td><span class="badge badge-info"><?= number_format($c['Credits'], 1) ?></span></td>
                                <td>
                                    <?php if ($c['Prerequisites']): ?>
                                        <span class="badge badge-warning"><?= htmlspecialchars($c['Prerequisites']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="courses.php?delete_course_id=<?= $c['Course_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete course <?= $c['Course_ID'] ?>?')">
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
