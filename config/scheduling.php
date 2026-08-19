<?php
/**
 * East West University - Scheduling, Room Management & Clash Prevention Engine
 * 
 * Provides centralized definitions of EWU Campus Classrooms, Labs, Standard Time Slots,
 * and high-precision Schedule Collision Detection for Rooms, Faculty, and Students.
 */

/**
 * Returns grouped list of standard EWU Rooms & Labs
 */
function ewu_get_standard_rooms() {
    return [
        'Academic Building 1 (AB1)' => [
            'AB1-401'  => 'AB1-401 (Lecture Hall - 45 Seats)',
            'AB1-502'  => 'AB1-502 (Smart Classroom - 35 Seats)',
            'AB1-503'  => 'AB1-503 (Smart Classroom - 35 Seats)',
            'AB1-508'  => 'AB1-508 (Seminar Room - 30 Seats)',
            'AB1-601'  => 'AB1-601 (Auditorium Class - 40 Seats)',
            'AB1-602'  => 'AB1-602 (Lecture Room - 35 Seats)',
            'AB1-Lab1' => 'AB1-Lab1 (Computer Lab 1 - 30 PCs)',
            'AB1-Lab2' => 'AB1-Lab2 (Computer Lab 2 - 30 PCs)',
            'AB1-Lab3' => 'AB1-Lab3 (Database & Systems Lab - 30 PCs)',
        ],
        'Academic Building 2 (AB2)' => [
            'AB2-201'  => 'AB2-201 (Lecture Hall - 40 Seats)',
            'AB2-202'  => 'AB2-202 (Lecture Hall - 40 Seats)',
            'AB2-301'  => 'AB2-301 (Smart Class - 35 Seats)',
            'AB2-302'  => 'AB2-302 (Electronics Lab - 35 Seats)',
            'AB2-405'  => 'AB2-405 (Microprocessor Lab - 30 Seats)',
            'AB2-Lab1' => 'AB2-Lab1 (Physics & Circuits Lab - 35 Seats)',
            'AB2-Lab2' => 'AB2-Lab2 (Telecommunications Lab - 30 Seats)',
        ],
        'Academic Building 3 (AB3)' => [
            'AB3-101'  => 'AB3-101 (BBA Lecture Hall - 45 Seats)',
            'AB3-201'  => 'AB3-201 (BBA Smart Class - 45 Seats)',
            'AB3-301'  => 'AB3-301 (Conference Hall - 40 Seats)',
            'AB3-Lab1' => 'AB3-Lab1 (Pharmacy Lab - 35 Seats)',
        ],
        'Specialized Labs & Floor Classrooms' => [
            '212' => 'Room 212 (General Classroom - 30 Seats)',
            '217' => 'Room 217 (Classroom - 30 Seats)',
            '221' => 'Room 221 (Classroom - 30 Seats)',
            '223' => 'Room 223 (Classroom - 30 Seats)',
            '241' => 'Room 241 (Classroom - 30 Seats)',
            '321' => 'Room 321 (Classroom - 35 Seats)',
            '325' => 'Room 325 (Classroom - 35 Seats)',
            '372 (SEIP Lab)' => '372 (SEIP Software Lab - 30 PCs)',
            '429' => 'Room 429 (Classroom - 30 Seats)',
            '431' => 'Room 431 (Classroom - 35 Seats)',
            '435 (VR/AR Lab)' => '435 (Virtual & Augmented Reality Lab - 30 PCs)',
            '530 (C. Lab-2)' => '530 (Computer Lab 2 - 30 PCs)',
            '531' => 'Room 531 (Programming Room - 30 Seats)',
            '533 (C. Lab-3)' => '533 (Computer Lab 3 - 30 PCs)',
            '534 (C. Lab-4)' => '534 (Computer Lab 4 - 30 PCs)',
        ]
    ];
}

/**
 * Returns grouped list of standard EWU Time Slots
 */
function ewu_get_standard_time_slots() {
    return [
        'Sunday & Tuesday (Sun-Tue / S-T)' => [
            'Sun-Tue 08:30 AM - 10:00 AM' => 'Sun-Tue 08:30 AM - 10:00 AM (Slot 1)',
            'Sun-Tue 10:10 AM - 11:40 AM' => 'Sun-Tue 10:10 AM - 11:40 AM (Slot 2)',
            'Sun-Tue 11:50 AM - 01:20 PM' => 'Sun-Tue 11:50 AM - 01:20 PM (Slot 3)',
            'Sun-Tue 01:30 PM - 03:00 PM' => 'Sun-Tue 01:30 PM - 03:00 PM (Slot 4)',
            'Sun-Tue 03:10 PM - 04:40 PM' => 'Sun-Tue 03:10 PM - 04:40 PM (Slot 5)',
            'Sun-Tue 04:50 PM - 06:20 PM' => 'Sun-Tue 04:50 PM - 06:20 PM (Slot 6)',
        ],
        'Monday & Wednesday (Mon-Wed / M-W)' => [
            'Mon-Wed 08:30 AM - 10:00 AM' => 'Mon-Wed 08:30 AM - 10:00 AM (Slot 1)',
            'Mon-Wed 10:10 AM - 11:40 AM' => 'Mon-Wed 10:10 AM - 11:40 AM (Slot 2)',
            'Mon-Wed 11:50 AM - 01:20 PM' => 'Mon-Wed 11:50 AM - 01:20 PM (Slot 3)',
            'Mon-Wed 01:30 PM - 03:00 PM' => 'Mon-Wed 01:30 PM - 03:00 PM (Slot 4)',
            'Mon-Wed 03:10 PM - 04:40 PM' => 'Mon-Wed 03:10 PM - 04:40 PM (Slot 5)',
            'Mon-Wed 04:50 PM - 06:20 PM' => 'Mon-Wed 04:50 PM - 06:20 PM (Slot 6)',
        ],
        'Thursday & Saturday (Thu-Sat / R-A)' => [
            'Thu-Sat 08:30 AM - 10:00 AM' => 'Thu-Sat 08:30 AM - 10:00 AM (Slot 1)',
            'Thu-Sat 10:10 AM - 11:40 AM' => 'Thu-Sat 10:10 AM - 11:40 AM (Slot 2)',
            'Thu-Sat 11:50 AM - 01:20 PM' => 'Thu-Sat 11:50 AM - 01:20 PM (Slot 3)',
            'Thu-Sat 01:30 PM - 03:00 PM' => 'Thu-Sat 01:30 PM - 03:00 PM (Slot 4)',
            'Thu-Sat 03:10 PM - 04:40 PM' => 'Thu-Sat 03:10 PM - 04:40 PM (Slot 5)',
        ],
        'Friday (Weekend / Special Lab Sessions)' => [
            'Fri 09:00 AM - 12:00 PM' => 'Fri 09:00 AM - 12:00 PM (Morning 3h Lab)',
            'Fri 03:00 PM - 06:00 PM' => 'Fri 03:00 PM - 06:00 PM (Afternoon 3h Lab)',
            'Fri 06:00 PM - 09:00 PM' => 'Fri 06:00 PM - 09:00 PM (Evening Session)',
        ]
    ];
}

/**
 * Converts a time string (e.g. '08:30 AM') into integer minutes from midnight
 */
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

/**
 * Expands short or compound day codes into full day names
 */
if (!function_exists('expand_day_code')) {
    function expand_day_code($code) {
        $code = strtoupper(trim($code));

        if ($code === 'S' || $code === 'SUN' || $code === 'SUNDAY') return ['Sunday'];
        if ($code === 'M' || $code === 'MON' || $code === 'MONDAY') return ['Monday'];
        if ($code === 'T' || $code === 'TUE' || $code === 'TUESDAY') return ['Tuesday'];
        if ($code === 'W' || $code === 'WED' || $code === 'WEDNESDAY') return ['Wednesday'];
        if ($code === 'R' || $code === 'THU' || $code === 'THUR' || $code === 'THURSDAY') return ['Thursday'];
        if ($code === 'F' || $code === 'FRI' || $code === 'FRIDAY') return ['Friday'];
        if ($code === 'A' || $code === 'SAT' || $code === 'SATURDAY') return ['Saturday'];

        if ($code === 'SUN-TUE' || $code === 'S-T' || $code === 'ST' || $code === 'SUN/TUE') return ['Sunday', 'Tuesday'];
        if ($code === 'MON-WED' || $code === 'M-W' || $code === 'MW' || $code === 'MON/WED') return ['Monday', 'Wednesday'];
        if ($code === 'THU-SAT' || $code === 'T-S' || $code === 'TS' || $code === 'R-A' || $code === 'RA' || $code === 'THU/SAT') return ['Thursday', 'Saturday'];
        if ($code === 'T-R' || $code === 'TR' || $code === 'TUE-THU' || $code === 'TUE/THU') return ['Tuesday', 'Thursday'];
        if ($code === 'S-M' || $code === 'SM' || $code === 'SUN-MON') return ['Sunday', 'Monday'];

        $result = [];
        if (strpos($code, 'SUN') !== false || (strpos($code, 'S') !== false && strpos($code, 'SAT') === false && strpos($code, 'SEIP') === false)) $result[] = 'Sunday';
        if (strpos($code, 'MON') !== false || strpos($code, 'M') !== false) $result[] = 'Monday';
        if (strpos($code, 'TUE') !== false || strpos($code, 'T') !== false) $result[] = 'Tuesday';
        if (strpos($code, 'WED') !== false || strpos($code, 'W') !== false) $result[] = 'Wednesday';
        if (strpos($code, 'THU') !== false || strpos($code, 'R') !== false) $result[] = 'Thursday';
        if (strpos($code, 'FRI') !== false || strpos($code, 'F') !== false) $result[] = 'Friday';
        if (strpos($code, 'SAT') !== false || strpos($code, 'A') !== false) $result[] = 'Saturday';

        return !empty($result) ? array_unique($result) : [$code];
    }
}

/**
 * Parses timing strings into structured slot intervals with minute bounds
 */
if (!function_exists('parse_ewu_time_slots')) {
    function parse_ewu_time_slots($timingString) {
        $slots = [];
        if (empty($timingString)) return $slots;

        $parts = preg_split('/[&,\|;]+/', $timingString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (preg_match('/^([A-Za-z\/\-]+)\s+(\d{1,2}:\d{2}\s*(?:AM|PM))\s*-\s*(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $part, $m)) {
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

/**
 * Compares two lists of parsed time slots to find schedule overlap
 */
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

/**
 * Checks if a section number already exists for a course (ensures uniqueness)
 *
 * @param PDO $pdo
 * @param string $courseId
 * @param int $secNo
 * @param int|null $excludeSectionId Section_Id to ignore when updating
 * @return bool True if duplicate exists, False otherwise
 */
function ewu_check_duplicate_section_no($pdo, $courseId, $secNo, $excludeSectionId = null) {
    if (empty($courseId) || empty($secNo)) return false;

    $sql = "SELECT Section_Id FROM Section WHERE Course_ID = ? AND Section_No = ?";
    $params = [$courseId, intval($secNo)];

    if (!empty($excludeSectionId)) {
        $sql .= " AND Section_Id != ?";
        $params[] = intval($excludeSectionId);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

/**
 * Computes the next available Section Number for a course
 */
function ewu_get_next_available_section_no($pdo, $courseId) {
    if (empty($courseId)) return 1;
    $stmt = $pdo->prepare("SELECT MAX(Section_No) AS max_sec FROM Section WHERE Course_ID = ?");
    $stmt->execute([$courseId]);
    $maxSec = $stmt->fetchColumn();
    return $maxSec ? (intval($maxSec) + 1) : 1;
}

/**
 * Checks for Room double-booking / schedule collision
 *
 * @param PDO $pdo
 * @param string $roomNo Room Number to test
 * @param string $timeSlot Time slot string
 * @param int|null $excludeSectionId Section_Id to ignore when updating
 * @return array ['clash' => bool, 'message' => string, 'conflicts' => array]
 */
function ewu_check_room_clash($pdo, $roomNo, $timeSlot, $excludeSectionId = null) {
    if (empty($roomNo) || empty($timeSlot)) {
        return ['clash' => false, 'message' => '', 'conflicts' => []];
    }

    $candidateSlots = parse_ewu_time_slots($timeSlot);
    if (empty($candidateSlots)) {
        return ['clash' => false, 'message' => '', 'conflicts' => []];
    }

    // Fetch existing sections with the same Room_No
    $sql = "
        SELECT sec.Section_Id, sec.Section_No, sec.Time_Slot, sec.Room_No, sec.Course_ID,
               c.Course_Title, CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name
        FROM Section sec
        JOIN Course c ON sec.Course_ID = c.Course_ID
        LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
        WHERE LOWER(TRIM(sec.Room_No)) = LOWER(TRIM(?))
    ";
    $params = [trim($roomNo)];

    if (!empty($excludeSectionId)) {
        $sql .= " AND sec.Section_Id != ?";
        $params[] = intval($excludeSectionId);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existingSections = $stmt->fetchAll();

    foreach ($existingSections as $ex) {
        $existingSlots = parse_ewu_time_slots($ex['Time_Slot']);
        $clashRes = check_schedule_clash($candidateSlots, $existingSlots);

        if ($clashRes['clash']) {
            return [
                'clash'     => true,
                'message'   => "Room {$ex['Room_No']} is already occupied by <strong>{$ex['Course_ID']} (Section {$ex['Section_No']})</strong> at <strong>{$ex['Time_Slot']}</strong>.",
                'conflicts' => [
                    'section_id' => $ex['Section_Id'],
                    'course_id'  => $ex['Course_ID'],
                    'section_no' => $ex['Section_No'],
                    'time_slot'  => $ex['Time_Slot'],
                    'room_no'    => $ex['Room_No'],
                    'details'    => $clashRes['details']
                ]
            ];
        }
    }

    return ['clash' => false, 'message' => '', 'conflicts' => []];
}

/**
 * Checks for Faculty Instructor double-booking / schedule collision
 *
 * @param PDO $pdo
 * @param string $facultyId Faculty ID
 * @param string $timeSlot Time slot string
 * @param int|null $excludeSectionId Section_Id to ignore when updating
 * @return array ['clash' => bool, 'message' => string]
 */
function ewu_check_faculty_clash($pdo, $facultyId, $timeSlot, $excludeSectionId = null) {
    if (empty($facultyId) || empty($timeSlot)) {
        return ['clash' => false, 'message' => ''];
    }

    $candidateSlots = parse_ewu_time_slots($timeSlot);
    if (empty($candidateSlots)) {
        return ['clash' => false, 'message' => ''];
    }

    $sql = "
        SELECT sec.Section_Id, sec.Section_No, sec.Time_Slot, sec.Room_No, sec.Course_ID,
               CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name
        FROM Section sec
        JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
        WHERE sec.Faculty_ID = ?
    ";
    $params = [$facultyId];

    if (!empty($excludeSectionId)) {
        $sql .= " AND sec.Section_Id != ?";
        $params[] = intval($excludeSectionId);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existingSections = $stmt->fetchAll();

    foreach ($existingSections as $ex) {
        $existingSlots = parse_ewu_time_slots($ex['Time_Slot']);
        $clashRes = check_schedule_clash($candidateSlots, $existingSlots);

        if ($clashRes['clash']) {
            return [
                'clash'   => true,
                'message' => "Instructor <strong>{$ex['Faculty_Name']}</strong> is already scheduled to teach <strong>{$ex['Course_ID']} (Section {$ex['Section_No']})</strong> at <strong>{$ex['Time_Slot']}</strong> (Room: {$ex['Room_No']})."
            ];
        }
    }

    return ['clash' => false, 'message' => ''];
}

/**
 * ═════════════════════════════════════════════════════════════════════
 * EAST WEST UNIVERSITY - COURSE-BASED SEMESTER FEE CALCULATION ENGINE
 * ═════════════════════════════════════════════════════════════════════
 */
if (!defined('EWU_PER_CREDIT_RATE')) {
    define('EWU_PER_CREDIT_RATE', 5500.00); // Standard EWU tuition rate: ৳ 5,500 per credit
}
if (!defined('EWU_FACILITY_FEE')) {
    define('EWU_FACILITY_FEE', 1500.00);    // Standard EWU activity & lab/library resource fee
}

/**
 * Calculates itemized course-by-course tuition fees, facility charges,
 * payments recorded, and net due balance for a student in a semester.
 *
 * @param PDO $pdo
 * @param string $studentId Student ID
 * @param string $semester Semester name (e.g. 'Summer', 'Spring', 'Fall')
 * @param int $year Academic year (e.g. 2026)
 * @return array Full financial assessment breakdown
 */
function ewu_calculate_student_semester_fee($pdo, $studentId, $semester = 'Summer', $year = 2026) {
    // 1. Fetch Enrolled courses
    $stmtEnr = $pdo->prepare("
        SELECT e.Enrollment_ID, e.Advising_Status, e.Semester, e.Year,
               c.Course_ID, c.Course_Title, c.Credits,
               sec.Section_No, sec.Time_Slot, sec.Room_No
        FROM Enrollment e
        JOIN Section sec ON e.Section_Id = sec.Section_Id
        JOIN Course c ON sec.Course_ID = c.Course_ID
        WHERE e.Student_ID = ? AND e.Semester = ? AND e.Year = ?
        ORDER BY c.Course_ID ASC
    ");
    $stmtEnr->execute([$studentId, $semester, $year]);
    $enrollments = $stmtEnr->fetchAll();

    $itemizedCourses = [];
    $totalCredits = 0.0;
    $tuitionSubtotal = 0.0;

    foreach ($enrollments as $enr) {
        $credits = floatval($enr['Credits']);
        $courseFee = $credits * EWU_PER_CREDIT_RATE;
        $totalCredits += $credits;
        $tuitionSubtotal += $courseFee;

        $itemizedCourses[] = [
            'enrollment_id'   => $enr['Enrollment_ID'],
            'course_id'       => $enr['Course_ID'],
            'title'           => $enr['Course_Title'],
            'section_no'      => $enr['Section_No'],
            'time_slot'       => $enr['Time_Slot'],
            'room_no'         => $enr['Room_No'],
            'credits'         => $credits,
            'rate_per_credit' => EWU_PER_CREDIT_RATE,
            'course_fee'      => $courseFee,
        ];
    }

    $facilityFee = (count($itemizedCourses) > 0) ? EWU_FACILITY_FEE : 0.00;
    $totalPayable = $tuitionSubtotal + $facilityFee;

    // 2. Fetch Payments made by this student for this semester
    $stmtPay = $pdo->prepare("
        SELECT *
        FROM Payment
        WHERE Student_ID = ? AND Semester = ? AND Year = ?
        ORDER BY Payment_Id DESC
    ");
    $stmtPay->execute([$studentId, $semester, $year]);
    $payments = $stmtPay->fetchAll();

    $totalPaid = 0.0;
    $totalPending = 0.0;

    foreach ($payments as $p) {
        if ($p['Payment_Status'] === 'Paid') {
            $totalPaid += floatval($p['Amount']);
        } elseif ($p['Payment_Status'] === 'Pending') {
            $totalPending += floatval($p['Amount']);
        }
    }

    $netDue = max(0.0, $totalPayable - $totalPaid);

    if ($totalPayable == 0) {
        $status = 'No Courses Enrolled';
        $statusBadge = 'badge-secondary';
    } elseif ($netDue <= 0.01) {
        $status = 'Paid (Cleared)';
        $statusBadge = 'badge-success';
    } elseif ($totalPending > 0 && ($totalPaid + $totalPending >= $totalPayable)) {
        $status = 'Pending Approval';
        $statusBadge = 'badge-warning';
    } else {
        $status = 'Due / Unpaid';
        $statusBadge = 'badge-danger';
    }

    return [
        'student_id'       => $studentId,
        'semester'         => $semester,
        'year'             => $year,
        'courses'          => $itemizedCourses,
        'course_count'     => count($itemizedCourses),
        'total_credits'    => $totalCredits,
        'rate_per_credit'  => EWU_PER_CREDIT_RATE,
        'tuition_subtotal' => $tuitionSubtotal,
        'facility_fee'     => $facilityFee,
        'total_payable'    => $totalPayable,
        'total_paid'       => $totalPaid,
        'total_pending'    => $totalPending,
        'net_due'          => $netDue,
        'status'           => $status,
        'status_badge'     => $statusBadge,
        'payments'         => $payments
    ];
}

