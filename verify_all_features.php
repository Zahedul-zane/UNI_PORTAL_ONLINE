<?php
/**
 * Comprehensive Verification Test Suite
 * East West University Portal - Excel Database
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

echo "============================================================" . PHP_EOL;
echo "   EAST WEST UNIVERSITY PORTAL - EXCEL DB TEST SUITE        " . PHP_EOL;
echo "============================================================" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertTest($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[ PASS ] $name" . PHP_EOL;
        $passed++;
    } else {
        echo "[ FAIL ] $name" . PHP_EOL;
        $failed++;
    }
}

// 1. Check all tables exist and have seed data
$tables = ExcelDatabase::getTables();
assertTest("All 14 tables registered", count($tables) === 14);

foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM " . $t)->fetchColumn();
    assertTest("Table '$t' has records ($count rows)", $count > 0);
}

// 2. Authentication tests
$stmt = $pdo->prepare("SELECT * FROM Student WHERE Student_ID = ?");
$stmt->execute(['2023-3-60-621']);
$student = $stmt->fetch();
assertTest("Student '2023-3-60-621' retrieved", !empty($student) && $student['First_name'] === 'Tanvir');

$stmt = $pdo->prepare("SELECT * FROM Faculty WHERE Faculty_ID = ?");
$stmt->execute(['1652688915']);
$faculty = $stmt->fetch();
assertTest("Faculty '1652688915' retrieved", !empty($faculty) && $faculty['First_name'] === 'Dr. Ahmed');

$stmt = $pdo->prepare("SELECT * FROM Admin WHERE Username = ?");
$stmt->execute(['admin']);
$admin = $stmt->fetch();
assertTest("Admin 'admin' retrieved", !empty($admin) && $admin['Full_Name'] === 'System Administrator');

// 3. MySQL Functions Compatibility (CONCAT, ROW_COUNT, IFNULL)
$concatResult = $pdo->query("SELECT CONCAT(First_name, ' ', Last_name) AS full_name FROM Student WHERE Student_ID = '2023-3-60-621'")->fetchColumn();
assertTest("CONCAT() function works: '$concatResult'", $concatResult === 'Tanvir Ahmed');

$pdo->prepare("UPDATE IncludedCourse SET Status = 'Approved' WHERE Pre_Advising_ID = 1 AND Course_ID = 'CSE101'")->execute();
$rowCount = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
assertTest("ROW_COUNT() function works: ($rowCount rows affected)", $rowCount >= 1);

// 4. Complex JOIN query test (Student Enrolled Courses)
$enrollments = $pdo->query("
    SELECT e.Enrollment_ID, c.Course_ID, c.Course_Title, s.Section_No, e.Grade
    FROM Enrollment e
    JOIN Section s ON e.Section_Id = s.Section_Id
    JOIN Course c ON s.Course_ID = c.Course_ID
    WHERE e.Student_ID = '2023-3-60-621'
")->fetchAll();
assertTest("Complex multi-JOIN query executed (" . count($enrollments) . " enrollments)", count($enrollments) > 0);

// 5. Scheduling Clash Detection Function test
require_once __DIR__ . '/config/scheduling.php';
$hasRoomClash = ewu_check_room_clash($pdo, 'AB1-502', 'Sun-Tue 08:30 AM - 10:00 AM');
assertTest("Scheduling engine detected room clash on busy room/slot", $hasRoomClash['clash'] === true);

$hasRoomClashFree = ewu_check_room_clash($pdo, 'AB1-502', 'Fri 08:00 AM - 09:30 AM');
assertTest("Scheduling engine allows free room/slot without clash", $hasRoomClashFree['clash'] === false);

// 6. Test Two-Way Excel CSV file update
$testStudentId = '2023-TEST-999';
$pdo->prepare("DELETE FROM Student WHERE Student_ID = ?")->execute([$testStudentId]);
$pdo->prepare("
    INSERT INTO Student (Student_ID, First_name, Last_name, E_mail, Password, Address, DOB, Faculty_ID, Dept_ID)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
")->execute([$testStudentId, 'AutoTest', 'User', 'autotest@ewubd.edu', 'testpass', 'Dhaka', '2000-01-01', '1652688915', 101]);

ExcelDatabase::saveAllToExcel();

$studentCsv = file_get_contents(ExcelDatabase::getDataDir() . '/student.csv');
assertTest("New Student record persists in data/student.csv", str_contains($studentCsv, $testStudentId));

$masterXml = file_get_contents(ExcelDatabase::getDataDir() . '/EWU_University_Portal_Database.xml');
assertTest("New Student record persists in Master Multi-Sheet Excel XML", str_contains($masterXml, $testStudentId));

// Clean up
$pdo->prepare("DELETE FROM Student WHERE Student_ID = ?")->execute([$testStudentId]);
ExcelDatabase::saveAllToExcel();

$studentCsvCleaned = file_get_contents(ExcelDatabase::getDataDir() . '/student.csv');
assertTest("Cleaned up student record from data/student.csv", !str_contains($studentCsvCleaned, $testStudentId));

echo PHP_EOL . "============================================================" . PHP_EOL;
echo "TEST RESULTS: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "============================================================" . PHP_EOL;

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! The Excel Database Engine is 100% operational." . PHP_EOL;
}
