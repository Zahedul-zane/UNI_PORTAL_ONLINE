<?php
$pageTitle = "Official Course Advising & Section Registration";
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$studentId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Load student profile & advisor info
$profile = get_full_user_profile($pdo);
$advisorId = $profile['Faculty_ID'] ?? null;
$deptId    = $profile['Dept_ID'] ?? 101;

// ─── Helper: Get alternate course code (CSE101 <-> CSE-101) ───────────────
function get_alt_code($code) {
    if (strpos($code, '-') !== false) {
        return str_replace('-', '', $code);
    }
    return preg_replace('/^([A-Za-z]+)(\d+)$/', '$1-$2', $code);
}

// ─── Timing Parser & Clash Detection Engine ───────────────────────────────
if (!function_exists('parse_ewu_time_slots')) {
    function parse_ewu_time_slots($timingString) {
        $slots = [];
        if (empty($timingString)) return $slots;

        $parts = preg_split('/[&,\|]+/', $timingString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (preg_match('/^([A-Za-z\-]+)\s+(\d{1,2}:\d{2}\s*(?:AM|PM))\s*-\s*(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $part, $m)) {
                $dayCode   = strtoupper(trim($m[1]));
                $startTime = trim($m[2]);
                $endTime   = trim($m[3]);

                $startMin = convert_time_to_minutes($startTime);
                $endMin   = convert_time_to_minutes($endTime);
                $days     = expand_day_code($dayCode);

                foreach ($days as $d) {
                    $slots[] = [
                        'day'       => $d,
                        'start_min' => $startMin,
                        'end_min'   => $endMin,
                        'raw_day'   => $dayCode,
                        'raw_time'  => "$startTime - $endTime",
                        'display'   => "$d $startTime - $endTime"
                    ];
                }
            }
        }
        return $slots;
    }
}

if (!function_exists('convert_time_to_minutes')) {
    function convert_time_to_minutes($timeStr) {
        $timeStr = trim(strtoupper($timeStr));
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/', $timeStr, $m)) {
            $h = intval($m[1]);
            $min = intval($m[2]);
            $meridiem = $m[3];

            if ($meridiem === 'PM' && $h < 12) $h += 12;
            if ($meridiem === 'AM' && $h === 12) $h = 0;

            return ($h * 60) + $min;
        }
        return 0;
    }
}

if (!function_exists('expand_day_code')) {
    function expand_day_code($code) {
        $code = strtoupper(trim($code));

        if ($code === 'S' || $code === 'SUN') return ['Sunday'];
        if ($code === 'M' || $code === 'MON') return ['Monday'];
        if ($code === 'T' || $code === 'TUE') return ['Tuesday'];
        if ($code === 'W' || $code === 'WED') return ['Wednesday'];
        if ($code === 'R' || $code === 'THU' || $code === 'THUR') return ['Thursday'];
        if ($code === 'F' || $code === 'FRI') return ['Friday'];
        if ($code === 'A' || $code === 'SAT') return ['Saturday'];

        if ($code === 'SUN-TUE' || $code === 'S-T' || $code === 'ST') return ['Sunday', 'Tuesday'];
        if ($code === 'MON-WED' || $code === 'M-W' || $code === 'MW') return ['Monday', 'Wednesday'];
        if ($code === 'T-R' || $code === 'TR' || $code === 'TUE-THU') return ['Tuesday', 'Thursday'];
        if ($code === 'S-M' || $code === 'SM') return ['Sunday', 'Monday'];

        $result = [];
        if (strpos($code, 'S') !== false && strpos($code, 'SUN') === false) $result[] = 'Sunday';
        if (strpos($code, 'M') !== false && strpos($code, 'MON') === false) $result[] = 'Monday';
        if (strpos($code, 'T') !== false && strpos($code, 'TUE') === false) $result[] = 'Tuesday';
        if (strpos($code, 'W') !== false && strpos($code, 'WED') === false) $result[] = 'Wednesday';
        if (strpos($code, 'R') !== false && strpos($code, 'THU') === false) $result[] = 'Thursday';

        return !empty($result) ? array_unique($result) : [$code];
    }
}

if (!function_exists('check_schedule_clash')) {
    function check_schedule_clash($slotList1, $slotList2) {
        foreach ($slotList1 as $s1) {
            foreach ($slotList2 as $s2) {
                if ($s1['day'] === $s2['day']) {
                    // Interval overlap condition: start1 < end2 && start2 < end1
                    if ($s1['start_min'] < $s2['end_min'] && $s2['start_min'] < $s1['end_min']) {
                        return [
                            'clash'   => true,
                            'day'     => $s1['day'],
                            'time1'   => $s1['raw_time'],
                            'time2'   => $s2['raw_time'],
                            'details' => "{$s1['day']} ({$s1['raw_time']})"
                        ];
                    }
                }
            }
        }
        return ['clash' => false];
    }
}

// ─── Fetch All Currently Enrolled Sections for this Student ───────────────
function get_student_enrolled_schedule($pdo, $studentId) {
    $stmt = $pdo->prepare("
        SELECT e.Enrollment_ID, sec.Section_Id, sec.Section_No, sec.Time_Slot, sec.Room_No,
               sec.Course_ID, c.Course_Title
        FROM Enrollment e
        JOIN Section sec ON e.Section_Id = sec.Section_Id
        JOIN Course c ON sec.Course_ID = c.Course_ID
        WHERE e.Student_ID = ?
    ");
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll();

    $enrolledSections = [];
    foreach ($rows as $r) {
        $slots = parse_ewu_time_slots($r['Time_Slot']);
        $enrolledSections[] = [
            'enrollment_id' => $r['Enrollment_ID'],
            'course_id'     => $r['Course_ID'],
            'course_title'  => $r['Course_Title'],
            'section_no'    => $r['Section_No'],
            'time_slot'     => $r['Time_Slot'],
            'room_no'       => $r['Room_No'],
            'slots'         => $slots
        ];
    }
    return $enrolledSections;
}

// ─── POST: Take / Enroll in a Section (With Conflict Prevention) ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_section'])) {
    $sectionId = intval($_POST['section_id'] ?? 0);
    $courseId  = sanitize($_POST['course_id'] ?? '');
    $altCode   = get_alt_code($courseId);

    if ($sectionId <= 0 || empty($courseId)) {
        $msg = 'Please select a valid section.';
        $msgType = 'danger';
    } else {
        try {
            // 1. Verify this course is APPROVED by advisor in Pre-Advising
            $stmtVerify = $pdo->prepare("
                SELECT ic.Status, c.Course_Title, c.Credits, ic.Course_ID
                FROM IncludedCourse ic
                JOIN Pre_Advising pa ON ic.Pre_Advising_ID = pa.Pre_Advising_ID
                JOIN Course c ON ic.Course_ID = c.Course_ID
                WHERE pa.Student_ID = ? AND (ic.Course_ID = ? OR ic.Course_ID = ?) AND ic.Status = 'Approved'
                LIMIT 1
            ");
            $stmtVerify->execute([$studentId, $courseId, $altCode]);
            $approvedCourse = $stmtVerify->fetch();

            if (!$approvedCourse) {
                $msg = "Access Denied: Course $courseId has not been approved by your advisor yet. Only advisor-approved courses can be taken during advising.";
                $msgType = 'danger';
            } else {
                $actualCourseId = $approvedCourse['Course_ID'];

                // 2. Check if already enrolled in this course (either code format)
                $stmtCheck = $pdo->prepare("
                    SELECT e.Enrollment_ID, sec.Section_No
                    FROM Enrollment e
                    JOIN Section sec ON e.Section_Id = sec.Section_Id
                    WHERE e.Student_ID = ? AND (sec.Course_ID = ? OR sec.Course_ID = ?)
                ");
                $stmtCheck->execute([$studentId, $actualCourseId, get_alt_code($actualCourseId)]);
                $existing = $stmtCheck->fetch();

                if ($existing) {
                    $msg = "You are already enrolled in Section {$existing['Section_No']} for course $courseId. Drop it first if you want to switch sections.";
                    $msgType = 'warning';
                } else {
                    // 3. Verify section exists and has seat capacity
                    $stmtSec = $pdo->prepare("
                        SELECT sec.*, COUNT(e.Enrollment_ID) AS EnrolledCount
                        FROM Section sec
                        LEFT JOIN Enrollment e ON sec.Section_Id = e.Section_Id
                        WHERE sec.Section_Id = ?
                        GROUP BY sec.Section_Id
                    ");
                    $stmtSec->execute([$sectionId]);
                    $secInfo = $stmtSec->fetch();

                    if (!$secInfo) {
                        $msg = 'Selected section does not exist.';
                        $msgType = 'danger';
                    } elseif ($secInfo['Capacity'] !== null && $secInfo['EnrolledCount'] >= $secInfo['Capacity']) {
                        $msg = "Section {$secInfo['Section_No']} is full (Capacity: {$secInfo['Capacity']}). Please select another available section.";
                        $msgType = 'danger';
                    } else {
                        // 4. CLASH DETECTION: Check if candidate section clashes with any already enrolled course
                        $candidateSlots = parse_ewu_time_slots($secInfo['Time_Slot']);
                        $enrolledSchedule = get_student_enrolled_schedule($pdo, $studentId);

                        $hasClash = false;
                        $clashInfo = null;

                        foreach ($enrolledSchedule as $enrSec) {
                            $res = check_schedule_clash($candidateSlots, $enrSec['slots']);
                            if ($res['clash']) {
                                $hasClash = true;
                                $clashInfo = [
                                    'course'     => $enrSec['course_id'],
                                    'section_no' => $enrSec['section_no'],
                                    'day'        => $res['day'],
                                    'time'       => $res['time1'],
                                    'details'    => $res['details']
                                ];
                                break;
                            }
                        }

                        if ($hasClash) {
                            $msg = "⚠️ <strong>Schedule Clash Prevented:</strong> Section {$secInfo['Section_No']} of <strong>$courseId</strong> conflicts with your enrolled course <strong>{$clashInfo['course']} (Section {$clashInfo['section_no']})</strong> on <strong>{$clashInfo['details']}</strong>. Please select another section to avoid timetable conflicts.";
                            $msgType = 'danger';
                        } else {
                            // 5. Enroll the student in the section
                            $stmtEnroll = $pdo->prepare("
                                INSERT INTO Enrollment (Enrollment_Type, Advising_Status, Section_Id, ManagedBy_Faculty_ID, Student_ID, Semester, Year)
                                VALUES ('Regular', 'Approved', ?, ?, ?, 'Summer', 2026)
                            ");
                            $stmtEnroll->execute([$sectionId, $advisorId, $studentId]);

                            $msg = "🎉 Success! You have officially enrolled in <strong>$courseId</strong> (Section {$secInfo['Section_No']}) for Summer 2026 without any schedule conflicts.";
                            $msgType = 'success';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $msg = 'Enrollment failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── POST: Drop / Change Section ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_section'])) {
    $enrollmentId = intval($_POST['enrollment_id'] ?? 0);
    $courseId     = sanitize($_POST['course_id'] ?? '');

    if ($enrollmentId > 0) {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM Enrollment WHERE Enrollment_ID = ? AND Student_ID = ?");
            $stmtDel->execute([$enrollmentId, $studentId]);
            $msg = "Section dropped for <strong>$courseId</strong>. You can now select another section.";
            $msgType = 'info';
        } catch (Exception $e) {
            $msg = 'Error dropping section: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── Load ALL Courses Approved by Advisor in Pre-Advising ─────────────────
$stmtApproved = $pdo->prepare("
    SELECT ic.Course_ID, c.Course_Title, c.Credits, MAX(pa.Pre_Advising_ID) AS Pre_Advising_ID
    FROM IncludedCourse ic
    JOIN Pre_Advising pa ON ic.Pre_Advising_ID = pa.Pre_Advising_ID
    JOIN Course c ON ic.Course_ID = c.Course_ID
    WHERE pa.Student_ID = ? AND ic.Status = 'Approved'
    GROUP BY ic.Course_ID, c.Course_Title, c.Credits
    ORDER BY ic.Course_ID ASC
");
$stmtApproved->execute([$studentId]);
$approvedCourses = $stmtApproved->fetchAll();

// Get active enrolled schedule for conflict checking on UI
$activeEnrolledSchedule = get_student_enrolled_schedule($pdo, $studentId);

// Build detailed advising list
$advisingList = [];
$totalApprovedCredits = 0;
$totalEnrolledCredits = 0;
$enrolledRoutineList = [];

foreach ($approvedCourses as $c) {
    $cid = $c['Course_ID'];
    $altCid = get_alt_code($cid);
    $totalApprovedCredits += $c['Credits'];

    // 1. Fetch sections from faculty_courses
    $stmtFC = $pdo->prepare("
        SELECT fc.course, fc.section, fc.faculty, fc.capacity,
               GROUP_CONCAT(fc.timing ORDER BY fc.id SEPARATOR '||') AS TimingRaw,
               GROUP_CONCAT(fc.room_no ORDER BY fc.id SEPARATOR '||') AS RoomRaw
        FROM faculty_courses fc
        WHERE fc.course = ? OR fc.course = ?
        GROUP BY fc.course, fc.section, fc.faculty, fc.capacity
        ORDER BY CAST(fc.section AS UNSIGNED) ASC
    ");
    $stmtFC->execute([$cid, $altCid]);
    $fcSections = $stmtFC->fetchAll();

    $sections = [];

    if (!empty($fcSections)) {
        foreach ($fcSections as $fc) {
            $secNo = intval($fc['section']);
            $cap = 30;
            if (preg_match('/\/(\d+)/', $fc['capacity'], $m)) {
                $cap = intval($m[1]);
            }

            // Find matching record in Section table
            $stmtSecId = $pdo->prepare("
                SELECT sec.Section_Id,
                       (SELECT COUNT(*) FROM Enrollment e WHERE e.Section_Id = sec.Section_Id) AS Filled
                FROM Section sec
                WHERE (sec.Course_ID = ? OR sec.Course_ID = ?) AND sec.Section_No = ?
                LIMIT 1
            ");
            $stmtSecId->execute([$cid, $altCid, $secNo]);
            $secRecord = $stmtSecId->fetch();

            $timings = explode('||', $fc['TimingRaw']);
            $rooms   = explode('||', $fc['RoomRaw']);
            $meetingSlots = [];
            for ($i = 0; $i < count($timings); $i++) {
                $meetingSlots[] = [
                    'timing' => $timings[$i] ?? '',
                    'room'   => $rooms[$i] ?? ''
                ];
            }

            $timingText = implode(' & ', $timings);
            $roomText   = implode(' / ', $rooms);

            if ($secRecord) {
                $sectionId = $secRecord['Section_Id'];
                $filled = $secRecord['Filled'];
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO Section (Section_No, Time_Slot, Room_No, Capacity, Course_ID, Faculty_ID)
                    VALUES (?, ?, ?, ?, ?, '1652688915')
                ");
                $stmtIns->execute([$secNo, substr($timingText, 0, 100), substr($roomText, 0, 50), $cap, $cid]);
                $sectionId = $pdo->lastInsertId();
                $filled = 0;
            }

            // Check if this section clashes with any ALREADY ENROLLED section (excluding this same course)
            $thisSecSlots = parse_ewu_time_slots($timingText);
            $hasClash = false;
            $clashWith = '';

            foreach ($activeEnrolledSchedule as $enrSec) {
                if ($enrSec['course_id'] === $cid || $enrSec['course_id'] === $altCid) {
                    continue; // Skip comparing against same course
                }
                $clashRes = check_schedule_clash($thisSecSlots, $enrSec['slots']);
                if ($clashRes['clash']) {
                    $hasClash = true;
                    $clashWith = "{$enrSec['course_id']} Sec {$enrSec['section_no']} ({$clashRes['details']})";
                    break;
                }
            }

            $sections[] = [
                'Section_Id'   => $sectionId,
                'Section_No'   => $secNo,
                'Faculty'      => $fc['faculty'],
                'Meetings'     => $meetingSlots,
                'Capacity'     => $cap,
                'Filled'       => $filled,
                'IsFull'       => ($filled >= $cap),
                'HasClash'     => $hasClash,
                'ClashWith'    => $clashWith
            ];
        }
    } else {
        // Fallback to Section table
        $stmtStdSec = $pdo->prepare("
            SELECT sec.*, CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name,
                   f.Faculty_ID AS Faculty_Code,
                   (SELECT COUNT(*) FROM Enrollment e WHERE e.Section_Id = sec.Section_Id) AS Filled
            FROM Section sec
            LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
            WHERE sec.Course_ID = ? OR sec.Course_ID = ?
            ORDER BY sec.Section_No ASC
        ");
        $stmtStdSec->execute([$cid, $altCid]);
        $rawSecs = $stmtStdSec->fetchAll();

        foreach ($rawSecs as $rs) {
            $thisSecSlots = parse_ewu_time_slots($rs['Time_Slot']);
            $hasClash = false;
            $clashWith = '';

            foreach ($activeEnrolledSchedule as $enrSec) {
                if ($enrSec['course_id'] === $cid || $enrSec['course_id'] === $altCid) {
                    continue;
                }
                $clashRes = check_schedule_clash($thisSecSlots, $enrSec['slots']);
                if ($clashRes['clash']) {
                    $hasClash = true;
                    $clashWith = "{$enrSec['course_id']} Sec {$enrSec['section_no']} ({$clashRes['details']})";
                    break;
                }
            }

            $sections[] = [
                'Section_Id'   => $rs['Section_Id'],
                'Section_No'   => $rs['Section_No'],
                'Faculty'      => $rs['Faculty_Code'] ?? 'TBA',
                'Meetings'     => [
                    ['timing' => $rs['Time_Slot'] ?? '', 'room' => $rs['Room_No'] ?? '']
                ],
                'Capacity'     => $rs['Capacity'] ?? 35,
                'Filled'       => $rs['Filled'] ?? 0,
                'IsFull'       => (($rs['Filled'] ?? 0) >= ($rs['Capacity'] ?? 35)),
                'HasClash'     => $hasClash,
                'ClashWith'    => $clashWith
            ];
        }
    }

    // Check if student is currently enrolled in this course
    $stmtEnr = $pdo->prepare("
        SELECT e.Enrollment_ID, e.Advising_Status, sec.Section_Id, sec.Section_No, sec.Time_Slot, sec.Room_No,
               CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name, f.Faculty_ID AS FacCode
        FROM Enrollment e
        JOIN Section sec ON e.Section_Id = sec.Section_Id
        LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
        WHERE e.Student_ID = ? AND (sec.Course_ID = ? OR sec.Course_ID = ?)
    ");
    $stmtEnr->execute([$studentId, $cid, $altCid]);
    $currentEnrollment = $stmtEnr->fetch();

    if ($currentEnrollment) {
        $totalEnrolledCredits += $c['Credits'];
        $enrolledRoutineList[] = [
            'course'     => $c,
            'enrollment' => $currentEnrollment
        ];
    }

    $advisingList[] = [
        'course'     => $c,
        'sections'   => $sections,
        'enrollment' => $currentEnrollment
    ];
}

// Fetch non-approved pre-advising courses
$stmtNonApp = $pdo->prepare("
    SELECT ic.Course_ID, c.Course_Title, c.Credits, ic.Status
    FROM IncludedCourse ic
    JOIN Pre_Advising pa ON ic.Pre_Advising_ID = pa.Pre_Advising_ID
    JOIN Course c ON ic.Course_ID = c.Course_ID
    WHERE pa.Student_ID = ? 
      AND ic.Status != 'Approved'
      AND ic.Course_ID NOT IN (
          SELECT ic2.Course_ID 
          FROM IncludedCourse ic2 
          JOIN Pre_Advising pa2 ON ic2.Pre_Advising_ID = pa2.Pre_Advising_ID 
          WHERE pa2.Student_ID = ? AND ic2.Status = 'Approved'
      )
    GROUP BY ic.Course_ID, c.Course_Title, c.Credits, ic.Status
    ORDER BY ic.Course_ID ASC
");
$stmtNonApp->execute([$studentId, $studentId]);
$nonApprovedCourses = $stmtNonApp->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─── Neomorphic Advising UI Styles ─── */
.advising-hero {
    background: linear-gradient(145deg, #0d253f, #081726);
    border-radius: var(--radius-lg);
    padding: 26px 30px;
    color: #ffffff;
    box-shadow: var(--neo-lg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 26px;
    position: relative;
    overflow: hidden;
}

.advising-hero::after {
    content: "EWU ADVISING";
    position: absolute;
    right: 20px;
    bottom: -15px;
    font-family: var(--font-heading);
    font-size: 72px;
    font-weight: 900;
    color: rgba(255, 255, 255, 0.03);
    letter-spacing: 4px;
    pointer-events: none;
}

.advising-metric-chip {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.course-card-adv {
    background: var(--surface-soft);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-soft);
    box-shadow: var(--neo-md);
    overflow: hidden;
    transition: var(--transition);
    margin-bottom: 24px;
}

.course-card-adv:hover {
    box-shadow: var(--neo-lg);
    transform: translateY(-2px);
}

.course-card-header {
    background: transparent;
    padding: 18px 24px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.meeting-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--surface-soft);
    box-shadow: var(--neo-xs);
    border: 1px solid var(--border-soft);
    color: #2d3748;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    margin: 2px 0;
}

.meeting-tag.lab {
    background: #e6fffa;
    color: #234e52;
    border: 1px solid #b2f5ea;
}

.meeting-tag.room {
    background: #ebf8ff;
    color: #2b6cb0;
    border: 1px solid #bee3f8;
    font-weight: 700;
}

.faculty-badge {
    background: #fefcbf;
    color: #744210;
    border: 1px solid #faf089;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 11.5px;
    letter-spacing: 0.5px;
    box-shadow: var(--neo-xs);
}

.btn-take-sec {
    background: var(--gold-gradient);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
    font-weight: 700;
    padding: 9px 18px;
    border-radius: 10px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    box-shadow: 4px 4px 10px #c2c9d4, -4px -4px 10px #ffffff;
}

.btn-take-sec:hover {
    transform: translateY(-2px);
    box-shadow: 6px 6px 14px #bcc3ce, -6px -6px 14px #ffffff;
}

.btn-take-sec:active {
    box-shadow: inset 2px 2px 5px #944e04, inset -2px -2px 5px #f5a623;
    transform: translateY(1px);
}

.enrolled-ribbon {
    background: linear-gradient(145deg, #10b981, #059669);
    color: white;
    padding: 16px 20px;
    border-radius: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 5px 5px 15px rgba(16, 185, 129, 0.3);
}

.live-filter-bar {
    background: var(--surface-soft);
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-md);
    box-shadow: var(--neo-inset-sm);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.clash-badge {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    box-shadow: var(--neo-xs);
}
</style>

<!-- ═══════════════════════════════════════════════════
     HERO BANNER & ADVISING METRICS
══════════════════════════════════════════════════════ -->
<div class="advising-hero">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(212, 175, 55, 0.2); color: var(--gold); border: 1px solid rgba(212, 175, 55, 0.4); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 8px;">
                ⚡ ACTIVE ADVISING PERIOD • SUMMER 2026 • AUTOMATIC CLASH PREVENTION
            </div>
            <h1 style="color: #ffffff; font-size: 26px; margin-bottom: 4px;">
                Official Course Advising & Section Registration
            </h1>
            <p style="color: rgba(255, 255, 255, 0.8); font-size: 14px; max-width: 600px;">
                Select your class sections and build your conflict-free timetable from courses approved by your advisor.
            </p>
        </div>

        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
            <div class="advising-metric-chip">
                <div style="font-size: 24px;">👨‍🏫</div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.7);">Advisor</div>
                    <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($profile['Advisor_Name'] ?? 'Assigned Faculty') ?></div>
                </div>
            </div>
            <div class="advising-metric-chip">
                <div style="font-size: 24px;">📚</div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.7);">Approved Cr.</div>
                    <div style="font-weight: 700; font-size: 14px;"><?= number_format($totalApprovedCredits, 1) ?> Credits</div>
                </div>
            </div>
            <div class="advising-metric-chip" style="background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.4);">
                <div style="font-size: 24px;">✅</div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: #a7f3d0;">Registered</div>
                    <div style="font-weight: 800; font-size: 15px; color: #34d399;"><?= number_format($totalEnrolledCredits, 1) ?> Cr</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 22px; font-size: 14px; font-weight: 500;">
        <span><?= $msg ?></span>
    </div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════
     MAIN GRID: Left = Course Sections | Right = Live Routine
══════════════════════════════════════════════════════ -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start;">

    <!-- Left Column: Advisor-Approved Courses List -->
    <div>
        <!-- Live Search Bar -->
        <div class="live-filter-bar">
            <span style="font-size: 18px;">🔍</span>
            <input type="text" id="course_search_input" class="form-control" placeholder="Search by course code (e.g. CSE101), faculty (e.g. MAR, MSHQ), or room..." style="border: none; background: transparent; font-size: 14px; padding: 0; box-shadow: none;">
        </div>

        <?php if (empty($advisingList)): ?>
            <div class="panel">
                <div class="panel-body" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                    <div style="font-size: 52px; margin-bottom: 15px;">📋</div>
                    <h3 style="color: var(--primary); margin-bottom: 8px;">No Approved Courses Available for Advising</h3>
                    <p style="font-size: 14px; max-width: 480px; margin: 0 auto 20px; line-height: 1.6;">
                        You must first submit your desired courses in <strong>Pre-Advising</strong>, and your faculty advisor must approve them before class sections become available for registration.
                    </p>
                    <a href="pre_advising.php" class="btn btn-gold" style="font-weight: 700; padding: 12px 24px; display: inline-flex; align-items: center; gap: 8px;">
                        📝 Open Pre-Advising Form →
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div id="advising_courses_container">
                <?php foreach ($advisingList as $item): 
                    $c = $item['course'];
                    $sections = $item['sections'];
                    $enr = $item['enrollment'];
                ?>
                    <div class="course-card-adv course-block" data-course="<?= htmlspecialchars($c['Course_ID']) ?>">
                        
                        <!-- Header -->
                        <div class="course-card-header">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 800; color: var(--primary);">
                                    <?= htmlspecialchars($c['Course_ID']) ?>
                                </span>
                                <span style="font-size: 15px; font-weight: 600; color: var(--text-primary);">
                                    <?= htmlspecialchars($c['Course_Title']) ?>
                                </span>
                                <span class="badge badge-info" style="font-weight: 700;">
                                    <?= number_format($c['Credits'], 1) ?> Credits
                                </span>
                            </div>

                            <div>
                                <span class="badge badge-success" style="font-size: 12px; padding: 5px 12px; font-weight: 600;">
                                    ✅ Approved by Advisor
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div style="padding: 20px;">
                            <?php if ($enr): ?>
                                <!-- Enrolled State Ribbon -->
                                <div class="enrolled-ribbon">
                                    <div style="display: flex; align-items: center; gap: 14px;">
                                        <div style="font-size: 32px;">🎉</div>
                                        <div>
                                            <div style="font-size: 16px; font-weight: 800; letter-spacing: 0.3px;">
                                                Registered in Section <?= htmlspecialchars($enr['Section_No']) ?>
                                            </div>
                                            <div style="font-size: 13px; opacity: 0.95; margin-top: 4px;">
                                                🕒 <?= htmlspecialchars($enr['Time_Slot']) ?> &nbsp;•&nbsp; 📍 Room: <?= htmlspecialchars($enr['Room_No']) ?> &nbsp;•&nbsp; 👨‍🏫 Faculty: <?= htmlspecialchars($enr['Faculty_Name'] ?? 'TBA') ?>
                                            </div>
                                        </div>
                                    </div>

                                    <form action="advising.php" method="POST" onsubmit="return confirm('Are you sure you want to drop this section and choose another?')">
                                        <input type="hidden" name="enrollment_id" value="<?= $enr['Enrollment_ID'] ?>">
                                        <input type="hidden" name="course_id" value="<?= htmlspecialchars($c['Course_ID']) ?>">
                                        <button type="submit" name="drop_section" class="btn btn-sm btn-danger" style="padding: 8px 16px; font-size: 12px; font-weight: 700; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.4);">
                                            🔄 Change / Drop Section
                                        </button>
                                    </form>
                                </div>
                            <?php elseif (count($sections) > 0): ?>
                                <!-- Available Sections Grid / Table -->
                                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; font-weight: 600;">
                                    Select an available class section for Summer 2026:
                                </div>

                                <div class="table-responsive">
                                    <table class="data-table" style="font-size: 13px;">
                                        <thead>
                                            <tr style="background: #f1f5f9;">
                                                <th style="width: 70px; text-align: center;">Sec</th>
                                                <th style="width: 80px; text-align: center;">Faculty</th>
                                                <th>Class & Lab Timings</th>
                                                <th style="width: 100px; text-align: center;">Capacity</th>
                                                <th style="width: 130px; text-align: center;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sections as $sec): ?>
                                                <tr class="sec-row" data-faculty="<?= htmlspecialchars($sec['Faculty']) ?>">
                                                    <td style="text-align: center;">
                                                        <strong style="font-size: 15px; color: var(--primary);">
                                                            Sec <?= htmlspecialchars($sec['Section_No']) ?>
                                                        </strong>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <span class="faculty-badge">
                                                            <?= htmlspecialchars($sec['Faculty']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                                            <?php foreach ($sec['Meetings'] as $m): ?>
                                                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                                    <span class="meeting-tag <?= strpos($m['room'], 'Lab') !== false ? 'lab' : '' ?>">
                                                                        🕒 <?= htmlspecialchars($m['timing']) ?>
                                                                    </span>
                                                                    <span class="meeting-tag room">
                                                                        📍 <?= htmlspecialchars($m['room']) ?>
                                                                    </span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if ($sec['HasClash']): ?>
                                                                <div>
                                                                    <span class="clash-badge">
                                                                        ⚠️ Clashes with <?= htmlspecialchars($sec['ClashWith']) ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if ($sec['IsFull']): ?>
                                                            <span class="badge badge-danger">Full (<?= $sec['Filled'] ?>/<?= $sec['Capacity'] ?>)</span>
                                                        <?php else: ?>
                                                            <div style="font-weight: 700; color: #2f855a; font-size: 12px;">
                                                                <?= $sec['Filled'] ?> / <?= $sec['Capacity'] ?> seats
                                                            </div>
                                                            <div style="width: 100%; height: 5px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 4px;">
                                                                <div style="height: 100%; width: <?= min(100, round(($sec['Filled'] / max(1, $sec['Capacity'])) * 100)) ?>%; background: #38a169;"></div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if ($sec['IsFull']): ?>
                                                            <button class="btn btn-sm btn-secondary" disabled style="opacity: 0.5; padding: 6px 14px; font-size: 12px;">
                                                                Full
                                                            </button>
                                                        <?php elseif ($sec['HasClash']): ?>
                                                            <button class="btn btn-sm btn-secondary" disabled style="opacity: 0.6; padding: 6px 12px; font-size: 12px; cursor: not-allowed; color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5;" title="Time conflicts with: <?= htmlspecialchars($sec['ClashWith']) ?>">
                                                                ⚠️ Time Clash
                                                            </button>
                                                        <?php else: ?>
                                                            <form action="advising.php" method="POST" style="display: inline;">
                                                                <input type="hidden" name="course_id" value="<?= htmlspecialchars($c['Course_ID']) ?>">
                                                                <input type="hidden" name="section_id" value="<?= $sec['Section_Id'] ?>">
                                                                <button type="submit" name="take_section" class="btn-take-sec">
                                                                    ⚡ Take Section
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px; color: var(--text-muted); font-size: 13px; text-align: center;">
                                    ℹ️ This course is approved, but the department has not scheduled sections yet. Please check back later.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Live Routine & Course Progress -->
    <div>
        <!-- Live Routine Card -->
        <div class="panel" style="margin-bottom: 24px; position: sticky; top: 20px;">
            <div class="panel-header" style="background: #0f2b48; color: white;">
                <div class="panel-title" style="color: white; font-size: 15px;">
                    🗓️ Registered Weekly Routine (Summer 2026)
                </div>
            </div>

            <div class="panel-body" style="padding: 18px;">
                <?php if (!empty($enrolledRoutineList)): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($enrolledRoutineList as $er): 
                            $c = $er['course'];
                            $enr = $er['enrollment'];
                        ?>
                            <div style="border-left: 4px solid var(--gold); background: #fdfbf7; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid #edf2f7; border-left-width: 4px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: var(--primary); font-size: 14px;">
                                        <?= htmlspecialchars($c['Course_ID']) ?> • Sec <?= htmlspecialchars($enr['Section_No']) ?>
                                    </strong>
                                    <span class="badge badge-info" style="font-weight: 700;"><?= number_format($c['Credits'], 1) ?> Cr</span>
                                </div>
                                <div style="font-size: 12px; color: #4a5568; margin-top: 5px; font-weight: 500;">
                                    🕒 <?= htmlspecialchars($enr['Time_Slot']) ?>
                                </div>
                                <div style="font-size: 12px; color: #718096; margin-top: 3px; display: flex; justify-content: space-between;">
                                    <span>📍 <?= htmlspecialchars($enr['Room_No']) ?></span>
                                    <span>👨‍🏫 <?= htmlspecialchars($enr['FacCode'] ?? $enr['Faculty_Name'] ?? 'TBA') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div style="margin-top: 10px; padding-top: 12px; border-top: 2px dashed #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);">Total Registered Credits:</span>
                            <span style="font-size: 16px; font-weight: 800; color: var(--primary);">
                                <?= number_format($totalEnrolledCredits, 1) ?> Cr
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 30px 10px;">
                        <div style="font-size: 36px; margin-bottom: 8px;">🗓️</div>
                        <div style="font-size: 13px; font-weight: 600;">No Sections Registered Yet</div>
                        <div style="font-size: 12px; margin-top: 4px;">
                            Choose sections from your approved courses on the left to build your schedule.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending / Rejected Courses Quick Reference -->
        <?php if (!empty($nonApprovedCourses)): ?>
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title" style="font-size: 14px;">⏳ Pre-Advising Status Overview</div>
                </div>
                <div class="panel-body" style="padding: 16px;">
                    <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px;">
                        Courses waiting for advisor review:
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($nonApprovedCourses as $nac): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #fafafa; padding: 9px 12px; border-radius: 6px; border: 1px solid #edf2f7; font-size: 12px;">
                                <div>
                                    <strong style="color: var(--primary);"><?= htmlspecialchars($nac['Course_ID']) ?></strong>
                                    <div style="color: var(--text-muted); font-size: 11px;"><?= htmlspecialchars($nac['Course_Title']) ?></div>
                                </div>
                                <div>
                                    <?php if ($nac['Status'] === 'Rejected'): ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Awaiting Advisor</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 14px;">
                        <a href="pre_advising.php" class="btn btn-sm btn-secondary" style="width: 100%; justify-content: center;">
                            📝 Pre-Advising Requests
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Client-side Interactive Search & Filtering Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('course_search_input');
    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const courseCards = document.querySelectorAll('.course-block');

        courseCards.forEach(card => {
            const courseText = card.textContent.toLowerCase();
            if (courseText.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
