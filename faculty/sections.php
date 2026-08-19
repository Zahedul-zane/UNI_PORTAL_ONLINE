<?php
$pageTitle = "Assigned Class Sections";
require_once __DIR__ . '/../config/auth.php';
require_role('faculty');

$facultyId = $_SESSION['user_id'];
$selectedSectionId = intval($_GET['section_id'] ?? 0);

// Fetch Faculty Sections
$stmtSec = $pdo->prepare("
    SELECT sec.*, c.Course_ID, c.Course_Title, c.Credits,
           (SELECT COUNT(*) FROM Enrollment e WHERE e.Section_Id = sec.Section_Id) AS EnrolledCount
    FROM Section sec
    JOIN Course c ON sec.Course_ID = c.Course_ID
    WHERE sec.Faculty_ID = ?
    ORDER BY c.Course_ID ASC
");
$stmtSec->execute([$facultyId]);
$sections = $stmtSec->fetchAll();

// If section selected, fetch enrolled roster
$roster = [];
$selectedSecInfo = null;

if ($selectedSectionId > 0) {
    foreach ($sections as $s) {
        if ($s['Section_Id'] === $selectedSectionId) {
            $selectedSecInfo = $s;
            break;
        }
    }

    $stmtRoster = $pdo->prepare("
        SELECT e.*, s.Student_ID, s.First_name, s.Last_name, s.E_mail,
               sp.Phone_Number1
        FROM Enrollment e
        JOIN Student s ON e.Student_ID = s.Student_ID
        LEFT JOIN Student_PhoneNum sp ON s.Student_ID = sp.Student_ID
        WHERE e.Section_Id = ?
        ORDER BY s.Student_ID ASC
    ");
    $stmtRoster->execute([$selectedSectionId]);
    $roster = $stmtRoster->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Assigned Class Sections & Student Rosters</h1>
    <p>View section schedules, room assignments, and student enrollment lists.</p>
</div>

<div class="stats-grid">
    <?php foreach ($sections as $sec): ?>
        <a href="sections.php?section_id=<?= $sec['Section_Id'] ?>" style="text-decoration: none;">
            <div class="stat-card" style="border: 2px solid <?= $selectedSectionId === $sec['Section_Id'] ? 'var(--gold)' : 'transparent' ?>;">
                <div class="stat-icon primary">📖</div>
                <div class="stat-details">
                    <h3><?= htmlspecialchars($sec['Course_ID']) ?> (Sec <?= $sec['Section_No'] ?>)</h3>
                    <p><?= htmlspecialchars($sec['Time_Slot']) ?> • <?= $sec['EnrolledCount'] ?>/<?= $sec['Capacity'] ?> Students</p>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($selectedSecInfo): ?>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                👥 Roster for <?= htmlspecialchars($selectedSecInfo['Course_ID']) ?> (Section <?= $selectedSecInfo['Section_No'] ?>)
            </div>
            <a href="grading.php?section_id=<?= $selectedSectionId ?>" class="btn btn-gold">
                ✏️ Gradebook Management
            </a>
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Contact Phone</th>
                            <th>Mid Mark</th>
                            <th>Final Mark</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($roster) > 0): ?>
                            <?php foreach ($roster as $r): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($r['Student_ID']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($r['First_name'] . ' ' . $r['Last_name']) ?></strong></td>
                                    <td><a href="mailto:<?= htmlspecialchars($r['E_mail']) ?>"><?= htmlspecialchars($r['E_mail']) ?></a></td>
                                    <td><?= htmlspecialchars($r['Phone_Number1'] ?? 'N/A') ?></td>
                                    <td><?= $r['Mid_Mark'] !== null ? number_format($r['Mid_Mark'], 2) : 'N/A' ?></td>
                                    <td><?= $r['Final_Mark'] !== null ? number_format($r['Final_Mark'], 2) : 'N/A' ?></td>
                                    <td><span class="badge <?= ewu_get_grade_badge_class($r['Grade']) ?>"><?= htmlspecialchars($r['Grade'] ?? 'N/A') ?></span></td>
                                    <td><span class="badge badge-<?= $r['Advising_Status'] === 'Approved' ? 'success' : 'warning' ?>"><?= htmlspecialchars($r['Advising_Status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 25px; color: var(--text-muted);">No enrolled students in this section.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
