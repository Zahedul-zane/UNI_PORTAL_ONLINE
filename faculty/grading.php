<?php
$pageTitle = "Gradebook & Evaluation";
require_once __DIR__ . '/../config/auth.php';
require_role('faculty');

$facultyId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Handle Single Student Grade Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $enrollmentId = intval($_POST['enrollment_id'] ?? 0);
    $midMark = $_POST['mid_mark'] !== '' ? floatval($_POST['mid_mark']) : null;
    $finalMark = $_POST['final_mark'] !== '' ? floatval($_POST['final_mark']) : null;
    $grade = sanitize($_POST['grade'] ?? 'N/A');
    $status = sanitize($_POST['advising_status'] ?? 'Approved');

    // Auto calculate grade from score if requested or left empty
    if (($grade === 'AUTO' || $grade === '') && $midMark !== null && $finalMark !== null) {
        $totalScore = $midMark + $finalMark;
        $calc = ewu_calculate_grade($totalScore);
        $grade = $calc['grade'];
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE Enrollment 
            SET Mid_Mark = ?, Final_Mark = ?, Grade = ?, Advising_Status = ?, ManagedBy_Faculty_ID = ?
            WHERE Enrollment_ID = ?
        ");
        $stmt->execute([$midMark, $finalMark, $grade, $status, $facultyId, $enrollmentId]);

        $msg = "Marks and letter grade ($grade) updated successfully!";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = "Grade update failed: " . $e->getMessage();
        $msgType = 'danger';
    }
}

// Handle Auto-Grade All for Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_grade_all'])) {
    $secId = intval($_POST['section_id'] ?? 0);
    try {
        $stmtR = $pdo->prepare("SELECT Enrollment_ID, Mid_Mark, Final_Mark FROM Enrollment WHERE Section_Id = ?");
        $stmtR->execute([$secId]);
        $rows = $stmtR->fetchAll();
        $count = 0;

        $upStmt = $pdo->prepare("UPDATE Enrollment SET Grade = ?, ManagedBy_Faculty_ID = ? WHERE Enrollment_ID = ?");
        foreach ($rows as $r) {
            if ($r['Mid_Mark'] !== null && $r['Final_Mark'] !== null) {
                $total = floatval($r['Mid_Mark']) + floatval($r['Final_Mark']);
                $calc = ewu_calculate_grade($total);
                $upStmt->execute([$calc['grade'], $facultyId, $r['Enrollment_ID']]);
                $count++;
            }
        }
        $msg = "Successfully computed EWU official grades for $count students!";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = "Auto-grade failed: " . $e->getMessage();
        $msgType = 'danger';
    }
}

// Fetch Faculty Sections
$stmtSec = $pdo->prepare("
    SELECT sec.*, c.Course_ID, c.Course_Title
    FROM Section sec
    JOIN Course c ON sec.Course_ID = c.Course_ID
    WHERE sec.Faculty_ID = ?
");
$stmtSec->execute([$facultyId]);
$sections = $stmtSec->fetchAll();

$selectedSectionId = intval($_GET['section_id'] ?? ($sections[0]['Section_Id'] ?? 0));

// Fetch Enrolled Roster for Selected Section
$roster = [];
$currentSection = null;
if ($selectedSectionId > 0) {
    foreach ($sections as $s) {
        if ($s['Section_Id'] === $selectedSectionId) {
            $currentSection = $s;
            break;
        }
    }

    $stmtRoster = $pdo->prepare("
        SELECT e.*, s.Student_ID, s.First_name, s.Last_name
        FROM Enrollment e
        JOIN Student s ON e.Student_ID = s.Student_ID
        WHERE e.Section_Id = ?
        ORDER BY s.Student_ID ASC
    ");
    $stmtRoster->execute([$selectedSectionId]);
    $roster = $stmtRoster->fetchAll();
}

$ewuTiers = ewu_get_grading_policy_tiers();
$officialGrades = ['N/A', 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'F'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>Section Gradebook & Mark Evaluation</h1>
            <p>Enter midterm and final marks, auto-compute official East West University grades (4.00 Scale), and manage advising status.</p>
        </div>
        <div>
            <button class="btn btn-outline-primary" style="background: rgba(255,255,255,0.9); font-weight: 700;" onclick="document.getElementById('ewu_policy_panel').classList.toggle('hidden')">
                📜 View EWU Grading Policy
            </button>
        </div>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<!-- Section Selector & Quick Tools -->
<div class="panel" style="margin-bottom: 25px;">
    <div class="panel-body" style="padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="font-weight: 700; color: var(--primary); font-size: 15px;">Select Section:</div>
            <form action="grading.php" method="GET" style="margin: 0;">
                <select name="section_id" class="form-control" onchange="this.form.submit()" style="min-width: 320px; font-weight: 600;">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['Section_Id'] ?>" <?= $selectedSectionId === $s['Section_Id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['Course_ID']) ?> - Section <?= $s['Section_No'] ?> (<?= htmlspecialchars($s['Course_Title']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedSectionId > 0 && count($roster) > 0): ?>
            <form action="grading.php?section_id=<?= $selectedSectionId ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Auto-compute official EWU letter grades for all students with entered marks in this section?')">
                <input type="hidden" name="section_id" value="<?= $selectedSectionId ?>">
                <button type="submit" name="auto_grade_all" class="btn btn-gold" style="box-shadow: var(--shadow-sm);">
                    ⚡ Auto-Grade Entire Section (EWU Policy)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- EWU Official Grading Policy Card (Collapsible) -->
<div class="panel" id="ewu_policy_panel" style="margin-bottom: 25px; border-top: 4px solid var(--gold-dark);">
    <div class="panel-header" style="background: linear-gradient(135deg, rgba(30, 58, 138, 0.05), rgba(217, 119, 6, 0.08));">
        <div class="panel-title" style="display: flex; align-items: center; gap: 8px;">
            <span>🎓</span>
            <strong style="color: var(--primary-dark);">East West University Official Grading Policy</strong>
        </div>
        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Authorized Scale</span>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="data-table" style="font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="color: var(--primary-dark);">Numerical Scores</th>
                        <th style="text-align: center; color: var(--primary-dark);">Letter Grade</th>
                        <th style="text-align: center; color: var(--primary-dark);">Grade Point</th>
                        <th style="color: var(--primary-dark);">Evaluation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ewuTiers as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['score_range']) ?></strong></td>
                            <td style="text-align: center;">
                                <span class="badge <?= $t['badge'] ?>" style="font-weight: 800; font-size: 13px; min-width: 42px; display: inline-block;">
                                    <?= $t['grade'] ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 800; color: var(--primary);">
                                <?= number_format($t['point'], 2) ?>
                            </td>
                            <td style="color: var(--text-secondary);"><?= $t['evaluation'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Gradebook Table -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">
            📝 Student Evaluation Roster 
            <?php if ($currentSection): ?>
                <span style="font-size: 14px; font-weight: normal; color: var(--text-muted);">
                    (<?= htmlspecialchars($currentSection['Course_ID']) ?> Sec <?= $currentSection['Section_No'] ?> — <?= count($roster) ?> Students)
                </span>
            <?php endif; ?>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
            💡 <em>Grades auto-sync in real-time as you enter midterm (max 40) and final (max 70) marks.</em>
        </div>
    </div>
    <div class="panel-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table" id="gradebook_table">
                <thead>
                    <tr>
                        <th style="width: 120px;">Student ID</th>
                        <th>Student Name</th>
                        <th style="width: 120px;">Midterm (Max 40)</th>
                        <th style="width: 120px;">Final (Max 70)</th>
                        <th style="text-align: right; width: 110px;">Total Mark</th>
                        <th style="text-align: center; width: 120px;">Letter Grade</th>
                        <th style="text-align: center; width: 100px;">Grade Point</th>
                        <th style="width: 130px;">Advising Status</th>
                        <th style="text-align: center; width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($roster) > 0): ?>
                        <?php foreach ($roster as $index => $r): 
                            $mid = $r['Mid_Mark'] !== null ? floatval($r['Mid_Mark']) : null;
                            $fin = $r['Final_Mark'] !== null ? floatval($r['Final_Mark']) : null;
                            $hasMarks = ($mid !== null || $fin !== null);
                            $total = ($mid ?? 0) + ($fin ?? 0);
                            $calc = ewu_calculate_grade($hasMarks ? $total : null);
                            $currentGrade = !empty($r['Grade']) ? $r['Grade'] : ($hasMarks ? $calc['grade'] : 'N/A');
                            $currentGP = ewu_get_grade_point($currentGrade);
                            $gradeBadge = ewu_get_grade_badge_class($currentGrade);
                        ?>
                            <tr class="grade-row" id="row_<?= $r['Enrollment_ID'] ?>">
                                <form action="grading.php?section_id=<?= $selectedSectionId ?>" method="POST">
                                    <input type="hidden" name="enrollment_id" value="<?= $r['Enrollment_ID'] ?>">
                                    
                                    <td><code><?= htmlspecialchars($r['Student_ID']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($r['First_name'] . ' ' . $r['Last_name']) ?></strong></td>
                                    
                                    <td>
                                        <input type="number" step="0.5" min="0" max="40" name="mid_mark" 
                                               class="form-control mark-input mid-mark" 
                                               style="width: 100%; font-weight: 600;" 
                                               value="<?= $r['Mid_Mark'] !== null ? $r['Mid_Mark'] : '' ?>" 
                                               placeholder="0.0"
                                               data-row="<?= $r['Enrollment_ID'] ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="70" name="final_mark" 
                                               class="form-control mark-input final-mark" 
                                               style="width: 100%; font-weight: 600;" 
                                               value="<?= $r['Final_Mark'] !== null ? $r['Final_Mark'] : '' ?>" 
                                               placeholder="0.0"
                                               data-row="<?= $r['Enrollment_ID'] ?>">
                                    </td>
                                    <td style="text-align: right;">
                                        <strong class="total-mark-display" style="color: var(--primary); font-size: 15px;">
                                            <?= $hasMarks ? number_format($total, 2) : '—' ?>
                                        </strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <select name="grade" class="form-control grade-select" style="width: 100%; font-weight: 800;" data-row="<?= $r['Enrollment_ID'] ?>">
                                            <?php foreach ($officialGrades as $g): ?>
                                                <option value="<?= $g ?>" <?= ($currentGrade === $g) ? 'selected' : '' ?>>
                                                    <?= $g ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="gp-display" style="font-weight: 800; color: var(--gold-dark); font-size: 14px;">
                                            <?= $currentGP !== null ? number_format($currentGP, 2) : '—' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <select name="advising_status" class="form-control" style="width: 100%;">
                                            <option value="Approved" <?= $r['Advising_Status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="Pending" <?= $r['Advising_Status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Rejected" <?= $r['Advising_Status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="submit" name="save_grades" class="btn btn-sm btn-gold" style="width: 100%;">
                                            💾 Save
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 35px; color: var(--text-muted);">No students found in this section.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Real-time East West University live grade computation in Gradebook table
document.addEventListener('DOMContentLoaded', function () {
    const EWU_SCALE = {
        'A+': 4.00,
        'A':  3.75,
        'A-': 3.50,
        'B+': 3.25,
        'B':  3.00,
        'B-': 2.75,
        'C+': 2.50,
        'C':  2.25,
        'D':  2.00,
        'F':  0.00
    };

    function calculateEWUGrade(score) {
        if (score === null || isNaN(score) || score === '') {
            return { grade: 'N/A', point: null };
        }
        score = parseFloat(score);
        if (score >= 80.0) return { grade: 'A+', point: 4.00 };
        if (score >= 75.0) return { grade: 'A',  point: 3.75 };
        if (score >= 70.0) return { grade: 'A-', point: 3.50 };
        if (score >= 65.0) return { grade: 'B+', point: 3.25 };
        if (score >= 60.0) return { grade: 'B',  point: 3.00 };
        if (score >= 55.0) return { grade: 'B-', point: 2.75 };
        if (score >= 50.0) return { grade: 'C+', point: 2.50 };
        if (score >= 45.0) return { grade: 'C',  point: 2.25 };
        if (score >= 40.0) return { grade: 'D',  point: 2.00 };
        return { grade: 'F', point: 0.00 };
    }

    document.querySelectorAll('.mark-input').forEach(input => {
        input.addEventListener('input', function () {
            const rowId = this.getAttribute('data-row');
            const row = document.getElementById('row_' + rowId);
            if (!row) return;

            const midVal = row.querySelector('.mid-mark').value.trim();
            const finVal = row.querySelector('.final-mark').value.trim();
            const totalDisplay = row.querySelector('.total-mark-display');
            const gradeSelect = row.querySelector('.grade-select');
            const gpDisplay = row.querySelector('.gp-display');

            if (midVal === '' && finVal === '') {
                totalDisplay.textContent = '—';
                gpDisplay.textContent = '—';
                return;
            }

            const mid = parseFloat(midVal) || 0;
            const fin = parseFloat(finVal) || 0;
            const total = mid + fin;

            totalDisplay.textContent = total.toFixed(2);

            const result = calculateEWUGrade(total);
            gradeSelect.value = result.grade;
            gpDisplay.textContent = result.point !== null ? result.point.toFixed(2) : '—';
        });
    });

    document.querySelectorAll('.grade-select').forEach(select => {
        select.addEventListener('change', function () {
            const rowId = this.getAttribute('data-row');
            const row = document.getElementById('row_' + rowId);
            if (!row) return;
            const gpDisplay = row.querySelector('.gp-display');
            const gp = EWU_SCALE[this.value];
            gpDisplay.textContent = (gp !== undefined) ? gp.toFixed(2) : '—';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
