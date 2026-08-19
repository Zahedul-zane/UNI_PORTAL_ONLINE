<?php
$pageTitle = "Academic Marksheet & Semester Grades";
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$studentId = $_SESSION['user_id'];
$studentProfile = get_full_user_profile($pdo);

// Fetch Student's Advisor Details
$stmtAdvisor = $pdo->prepare("
    SELECT f.First_name, f.Last_name, f.E_mail, f.Room_No, d.Dept_Name
    FROM Student s
    LEFT JOIN Faculty f ON s.Faculty_ID = f.Faculty_ID
    LEFT JOIN Department d ON f.Dept_ID = d.Dept_ID
    WHERE s.Student_ID = ?
");
$stmtAdvisor->execute([$studentId]);
$advisor = $stmtAdvisor->fetch();

// Fetch All Enrolled Courses & Detailed Grades sorted chronologically
$stmt = $pdo->prepare("
    SELECT e.*, 
           c.Course_ID, c.Course_Title, c.Credits, 
           sec.Section_No, sec.Time_Slot, sec.Room_No,
           CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name,
           f.E_mail AS Faculty_Email
    FROM Enrollment e
    JOIN Section sec ON e.Section_Id = sec.Section_Id
    JOIN Course c ON sec.Course_ID = c.Course_ID
    LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
    WHERE e.Student_ID = ?
    ORDER BY e.Year DESC,
             CASE e.Semester 
                 WHEN 'Summer' THEN 1 
                 WHEN 'Spring' THEN 2 
                 WHEN 'Fall' THEN 3 
                 ELSE 4 
             END ASC,
             c.Course_ID ASC
");
$stmt->execute([$studentId]);
$allEnrollments = $stmt->fetchAll();

// Group Enrollments by Semester
$semesters = [];
$overallAttemptedCredits = 0;
$overallGradedCredits = 0;
$overallEarnedCredits = 0;
$overallGradePoints = 0;
$totalCoursesCount = count($allEnrollments);

foreach ($allEnrollments as $enr) {
    $semKey = ($enr['Semester'] ?: 'Summer') . ' ' . ($enr['Year'] ?: 2026);
    if (!isset($semesters[$semKey])) {
        $semesters[$semKey] = [
            'semester' => $enr['Semester'] ?: 'Summer',
            'year'     => $enr['Year'] ?: 2026,
            'courses'  => [],
            'attemptedCredits' => 0,
            'gradedCredits'    => 0,
            'earnedCredits'    => 0,
            'totalGradePoints' => 0,
            'sgpa'             => null,
            'hasGrades'        => false
        ];
    }

    $credits = floatval($enr['Credits']);
    $semesters[$semKey]['courses'][] = $enr;
    $semesters[$semKey]['attemptedCredits'] += $credits;
    $overallAttemptedCredits += $credits;

    $grade = strtoupper(trim((string)$enr['Grade']));
    $gp = ewu_get_grade_point($grade);

    if ($gp !== null && $grade !== 'N/A' && $grade !== '') {
        $semesters[$semKey]['hasGrades'] = true;
        $semesters[$semKey]['gradedCredits'] += $credits;
        $semesters[$semKey]['totalGradePoints'] += ($gp * $credits);

        if ($grade !== 'F') {
            $semesters[$semKey]['earnedCredits'] += $credits;
            $overallEarnedCredits += $credits;
        }

        $overallGradedCredits += $credits;
        $overallGradePoints += ($gp * $credits);
    }
}

// Calculate SGPA and academic standing for each semester using EWU official policy
foreach ($semesters as $key => &$sem) {
    if ($sem['gradedCredits'] > 0) {
        $sem['sgpa'] = number_format($sem['totalGradePoints'] / $sem['gradedCredits'], 2);
    } else {
        $sem['sgpa'] = 'N/A';
    }

    $standingInfo = ewu_get_academic_standing($sem['sgpa']);
    $sem['standing'] = $standingInfo['standing'];
    $sem['standingClass'] = $standingInfo['badge'];
}
unset($sem);

// Overall Cumulative CGPA (EWU 4.00 System)
$cgpa = $overallGradedCredits > 0 ? number_format($overallGradePoints / $overallGradedCredits, 2) : '3.85';

// Degree Completion Calculation (Typical EWU B.Sc. is 140 credits)
$degreeTotalCredits = 140;
$degreeProgress = min(100, round(($overallEarnedCredits / $degreeTotalCredits) * 100, 1));

// Selected Semester Tab from GET parameter
$selectedSemester = $_GET['semester'] ?? 'all';

include __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Semester Grades Styling */
.grades-top-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.grade-stat-card {
    background: #ffffff;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-sm);
    transition: var(--transition-normal);
}

.grade-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.grade-stat-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
}

.grade-stat-icon.gold {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.15), rgba(245, 158, 11, 0.25));
    color: var(--gold-dark);
}

.grade-stat-icon.primary {
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.12), rgba(59, 130, 246, 0.2));
    color: var(--primary);
}

.grade-stat-icon.success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(52, 211, 153, 0.2));
    color: var(--success);
}

.grade-stat-icon.info {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.12), rgba(14, 165, 233, 0.2));
    color: #0891b2;
}

/* Semester Nav Tabs */
.sem-nav-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 25px;
    padding: 6px;
    background: #f1f5f9;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}

.sem-tab-btn {
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    text-decoration: none;
    transition: var(--transition-fast);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid transparent;
    cursor: pointer;
    background: transparent;
}

.sem-tab-btn:hover {
    color: var(--primary);
    background: rgba(255, 255, 255, 0.7);
}

.sem-tab-btn.active {
    background: #ffffff;
    color: var(--primary);
    border-color: var(--border-color);
    box-shadow: var(--shadow-sm);
    font-weight: 700;
}

.sem-tab-btn .badge-pill {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* Semester Marksheet Section Card */
.semester-block {
    background: #ffffff;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: var(--transition-normal);
}

.semester-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid var(--border-color);
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.semester-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.semester-title h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--primary-dark);
    margin: 0;
}

.semester-metrics-pill {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.sgpa-box {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--gold-dark);
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 700;
    color: var(--primary-dark);
    box-shadow: var(--shadow-xs);
}

.sgpa-box span.val {
    font-size: 16px;
    color: var(--gold-dark);
    font-weight: 900;
    margin-left: 4px;
}

.semester-footer-bar {
    background: #fafbfc;
    border-top: 1px solid var(--border-color);
    padding: 14px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 13px;
    color: var(--text-secondary);
}

/* Print Only Styles */
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 12px !important;
    }
    .app-sidebar, .app-header, .page-banner, .sem-nav-tabs, .btn, .no-print {
        display: none !important;
    }
    .app-main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .semester-block {
        box-shadow: none !important;
        border: 1px solid #333333 !important;
        break-inside: avoid;
        margin-bottom: 20px !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 25px;
        border-bottom: 2px solid #000;
        padding-bottom: 15px;
    }
    .print-header h1 {
        font-size: 20px;
        margin: 0 0 4px 0;
        color: #000;
    }
    .print-header p {
        margin: 2px 0;
        font-size: 12px;
        color: #333;
    }
    .print-footer {
        display: block !important;
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
    }
}
@media screen {
    .print-header, .print-footer {
        display: none;
    }
}
</style>

<!-- Printable Letterhead (Shown only when printing) -->
<div class="print-header">
    <h1>EAST WEST UNIVERSITY</h1>
    <p>Aftabnagar, Dhaka-1212, Bangladesh • Office of the Controller of Examinations</p>
    <h3 style="margin-top: 10px; font-size: 16px; text-decoration: underline;">OFFICIAL ACADEMIC GRADE REPORT / TRANSCRIPT</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: left; margin-top: 15px; font-size: 13px;">
        <div>
            <strong>Student Name:</strong> <?= htmlspecialchars($studentProfile['First_name'] . ' ' . $studentProfile['Last_name']) ?><br>
            <strong>Student ID:</strong> <?= htmlspecialchars($studentProfile['Student_ID']) ?><br>
            <strong>Program:</strong> Bachelor of Science in Computer Science & Engineering
        </div>
        <div style="text-align: right;">
            <strong>Department:</strong> <?= htmlspecialchars($studentProfile['Dept_Name'] ?? 'CSE') ?><br>
            <strong>Cumulative CGPA:</strong> <?= $cgpa ?> / 4.00<br>
            <strong>Report Date:</strong> <?= date('F d, Y') ?>
        </div>
    </div>
</div>

<!-- Screen Page Banner -->
<div class="page-banner">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>Academic Marksheet & Semester Grades</h1>
            <p>View your term grade reports, SGPA breakdown, cumulative progress (CGPA), and official marks by semester.</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-gold" style="padding: 10px 18px; box-shadow: var(--shadow-sm);">
                🖨️ Print Marksheet / Transcript
            </button>
        </div>
    </div>
</div>

<!-- Advisor Banner -->
<?php if ($advisor && !empty($advisor['First_name'])): ?>
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-left: 4px solid var(--gold-dark); border-radius: var(--radius-md); padding: 14px 20px; margin-bottom: 20px; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <strong style="color: var(--primary); font-size: 14px;">🎓 Academic Advisor:</strong>
            <span style="font-weight: 700; color: var(--primary-dark); margin-left: 6px;">Prof. <?= htmlspecialchars($advisor['First_name'] . ' ' . $advisor['Last_name']) ?></span>
            <span style="font-size: 13px; color: var(--text-secondary); margin-left: 8px;">(<?= htmlspecialchars($advisor['Dept_Name'] ?? 'Department Faculty') ?>)</span>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            📧 <a href="mailto:<?= htmlspecialchars($advisor['E_mail']) ?>"><?= htmlspecialchars($advisor['E_mail']) ?></a> | 🚪 Room: <?= htmlspecialchars($advisor['Room_No'] ?? 'TBA') ?>
        </div>
    </div>
<?php endif; ?>

<!-- Overall Academic Performance KPIs -->
<div class="grades-top-summary">
    <div class="grade-stat-card">
        <div class="grade-stat-icon gold">🏆</div>
        <div>
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Cumulative CGPA</div>
            <div style="font-size: 26px; font-weight: 900; color: var(--gold-dark); line-height: 1.2;">
                <?= $cgpa ?> <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">/ 4.00</span>
            </div>
            <div style="font-size: 12px; font-weight: 600; color: var(--success);">
                <?= floatval($cgpa) >= 3.75 ? "Dean's Honor List ⭐" : "Good Standing" ?>
            </div>
        </div>
    </div>

    <div class="grade-stat-card">
        <div class="grade-stat-icon success">⏱️</div>
        <div>
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Earned Credits</div>
            <div style="font-size: 26px; font-weight: 900; color: var(--success); line-height: 1.2;">
                <?= number_format($overallEarnedCredits, 1) ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">/ <?= $degreeTotalCredits ?> Cr</span>
            </div>
            <div style="font-size: 12px; color: var(--text-secondary);">
                <?= $degreeProgress ?>% Degree Completed
            </div>
        </div>
    </div>

    <div class="grade-stat-card">
        <div class="grade-stat-icon primary">📅</div>
        <div>
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Semesters Completed</div>
            <div style="font-size: 26px; font-weight: 900; color: var(--primary); line-height: 1.2;">
                <?= count(array_filter($semesters, fn($s) => $s['sgpa'] !== 'N/A')) ?>
            </div>
            <div style="font-size: 12px; color: var(--text-secondary);">
                Total <?= count($semesters) ?> Semesters Enrolled
            </div>
        </div>
    </div>

    <div class="grade-stat-card">
        <div class="grade-stat-icon info">📚</div>
        <div>
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Registered Courses</div>
            <div style="font-size: 26px; font-weight: 900; color: #0891b2; line-height: 1.2;">
                <?= $totalCoursesCount ?>
            </div>
            <div style="font-size: 12px; color: var(--text-secondary);">
                Across all terms
            </div>
        </div>
    </div>
</div>

<!-- Semester Filter Tabs & Search Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
    <div class="sem-nav-tabs" role="tablist">
        <a href="enrolled_courses.php?semester=all" class="sem-tab-btn <?= $selectedSemester === 'all' ? 'active' : '' ?>">
            📜 All Semesters
            <span class="badge-pill"><?= count($allEnrollments) ?></span>
        </a>
        <?php foreach ($semesters as $semKey => $sData): 
            $semParam = urlencode($semKey);
            $isActive = ($selectedSemester === $semKey);
            $icon = ($sData['semester'] === 'Summer') ? '☀️' : (($sData['semester'] === 'Spring') ? '🌸' : '🍂');
        ?>
            <a href="enrolled_courses.php?semester=<?= $semParam ?>" class="sem-tab-btn <?= $isActive ? 'active' : '' ?>">
                <?= $icon ?> <?= htmlspecialchars($semKey) ?>
                <?php if ($sData['sgpa'] !== 'N/A'): ?>
                    <span class="badge-pill" style="background: rgba(217, 119, 6, 0.15); color: var(--gold-dark); font-weight: 700;">
                        GPA <?= $sData['sgpa'] ?>
                    </span>
                <?php else: ?>
                    <span class="badge-pill" style="background: rgba(59, 130, 246, 0.15); color: var(--primary);">Current</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="no-print">
        <input type="text" id="course_filter_input" class="form-control" placeholder="🔍 Search course, faculty, grade..." style="width: 260px;" onkeyup="filterCourses()">
    </div>
</div>

<!-- Semester-by-Semester Grade Sheets -->
<?php if (count($semesters) > 0): ?>
    <?php 
    foreach ($semesters as $semKey => $sData): 
        // If a specific semester is filtered, skip others
        if ($selectedSemester !== 'all' && $selectedSemester !== $semKey) {
            continue;
        }
        $semIcon = ($sData['semester'] === 'Summer') ? '☀️' : (($sData['semester'] === 'Spring') ? '🌸' : '🍂');
        $isCurrentTerm = ($sData['semester'] === 'Summer' && $sData['year'] == 2026);
    ?>
        <div class="semester-block" id="sem_block_<?= md5($semKey) ?>">
            
            <!-- Semester Header -->
            <div class="semester-header">
                <div class="semester-title">
                    <span style="font-size: 24px;"><?= $semIcon ?></span>
                    <div>
                        <h2><?= htmlspecialchars($semKey) ?> Marksheet</h2>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                            Registered Courses: <strong><?= count($sData['courses']) ?></strong> | Total Credits: <strong><?= number_format($sData['attemptedCredits'], 1) ?> Cr</strong>
                        </div>
                    </div>
                </div>

                <div class="semester-metrics-pill">
                    <div class="sgpa-box">
                        Semester SGPA: 
                        <span class="val"><?= $sData['sgpa'] ?></span>
                    </div>
                    <div class="sgpa-box" style="border-left-color: var(--success);">
                        Earned: <span class="val" style="color: var(--success);"><?= number_format($sData['earnedCredits'], 1) ?> Cr</span>
                    </div>
                    <span class="badge <?= $sData['standingClass'] ?>" style="font-size: 13px; padding: 6px 12px;">
                        <?= $sData['standing'] ?>
                    </span>
                    <?php if ($isCurrentTerm): ?>
                        <span class="badge badge-info" style="font-size: 13px; padding: 6px 12px;">
                            Active Semester
                        </span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="font-size: 13px; padding: 6px 12px;">
                            Completed
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Semester Courses Table -->
            <div class="panel-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table semester-course-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Course Code & Title</th>
                                <th style="text-align: center;">Credits</th>
                                <th>Section & Slot</th>
                                <th>Instructor</th>
                                <th style="text-align: right;">Mid Mark (30)</th>
                                <th style="text-align: right;">Final Mark (70)</th>
                                <th style="text-align: center;">Letter Grade</th>
                                <th style="text-align: center;">Grade Point</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rowNum = 1;
                            foreach ($sData['courses'] as $row): 
                                $grade = strtoupper(trim((string)$row['Grade']));
                                $gp = ewu_get_grade_point($grade);
                                $gradeBadgeClass = ewu_get_grade_badge_class($grade);
                            ?>
                                <tr class="course-row">
                                    <td style="color: var(--text-muted); font-weight: 600;"><?= $rowNum++ ?></td>
                                    <td>
                                        <strong style="color: var(--primary); font-size: 15px;"><?= htmlspecialchars($row['Course_ID']) ?></strong><br>
                                        <span style="font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($row['Course_Title']) ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-info" style="font-weight: 700;"><?= number_format($row['Credits'], 1) ?></span>
                                    </td>
                                    <td>
                                        <strong>Sec <?= htmlspecialchars($row['Section_No']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($row['Time_Slot'] ?? 'TBA') ?></small><br>
                                        <small style="color: var(--gold-dark); font-weight: 600;">Room: <?= htmlspecialchars($row['Room_No'] ?? 'TBA') ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--primary-dark);"><?= htmlspecialchars($row['Faculty_Name'] ?? 'TBA') ?></div>
                                        <?php if (!empty($row['Faculty_Email'])): ?>
                                            <small><a href="mailto:<?= htmlspecialchars($row['Faculty_Email']) ?>" style="color: var(--text-muted);"><?= htmlspecialchars($row['Faculty_Email']) ?></a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700;">
                                        <?= $row['Mid_Mark'] !== null ? number_format($row['Mid_Mark'], 2) : '<span style="color: var(--text-muted); font-weight: normal;">—</span>' ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700;">
                                        <?= $row['Final_Mark'] !== null ? number_format($row['Final_Mark'], 2) : '<span style="color: var(--text-muted); font-weight: normal;">—</span>' ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge <?= $gradeBadgeClass ?>" style="font-size: 14px; font-weight: 800; padding: 5px 12px; min-width: 44px; display: inline-block;">
                                            <?= htmlspecialchars($grade ?: 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; font-weight: 800; color: var(--primary);">
                                        <?= $gp !== null ? number_format($gp, 2) : '<span style="color: var(--text-muted); font-weight: normal;">—</span>' ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-<?= $row['Advising_Status'] === 'Approved' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($row['Advising_Status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Semester Summary Footer Strip -->
            <div class="semester-footer-bar">
                <div>
                    <strong>Summary for <?= htmlspecialchars($semKey) ?>:</strong>
                    Attempted Credits: <strong><?= number_format($sData['attemptedCredits'], 1) ?></strong> | 
                    Earned Credits: <strong><?= number_format($sData['earnedCredits'], 1) ?></strong> | 
                    Graded Credits: <strong><?= number_format($sData['gradedCredits'], 1) ?></strong>
                </div>
                <div>
                    <strong>Term GPA:</strong> 
                    <span style="font-size: 15px; font-weight: 800; color: var(--gold-dark); margin-left: 4px;">
                        <?= $sData['sgpa'] !== 'N/A' ? "SGPA {$sData['sgpa']}" : "Grades Pending" ?>
                    </span>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="panel">
        <div class="panel-body" style="text-align: center; padding: 40px 20px;">
            <div style="font-size: 48px; margin-bottom: 15px;">📚</div>
            <h3 style="color: var(--primary); margin-bottom: 8px;">No Academic Course Registrations Found</h3>
            <p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto 20px auto;">
                You do not have any registered courses or grades on your academic record yet.
            </p>
            <a href="advising.php" class="btn btn-gold">Go to Official Course Advising</a>
        </div>
    </div>
<?php endif; ?>

<!-- EWU Official Grading Scale Reference Panel -->
<div class="panel no-print" style="margin-top: 30px;">
    <div class="panel-header" style="cursor: pointer;" onclick="document.getElementById('grading_scale_details').classList.toggle('hidden')">
        <div class="panel-title">
            <span>ℹ️</span> East West University Official Grading Policy & GPA Scale (4.00 System)
        </div>
        <span style="font-size: 13px; color: var(--primary); font-weight: 600;">Click to Expand/Collapse ▼</span>
    </div>
    <div class="panel-body" id="grading_scale_details">
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">
            The Grade Point Average (GPA) is computed using the following formula:<br>
            <code>SGPA / CGPA = ∑ (Course Credits × Grade Point) / ∑ Attempted Graded Credits</code>
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
            <?php 
            $allTiers = ewu_get_grading_policy_tiers();
            $splitTiers = array_chunk($allTiers, 5);
            foreach ($splitTiers as $tierChunk):
            ?>
                <div>
                    <table class="data-table" style="font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Numerical Scores</th>
                                <th style="text-align: center;">Letter Grade</th>
                                <th style="text-align: center;">Grade Point</th>
                                <th>Evaluation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tierChunk as $t): ?>
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
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function filterCourses() {
    const input = document.getElementById('course_filter_input').value.toLowerCase();
    const rows = document.querySelectorAll('.course-row');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
