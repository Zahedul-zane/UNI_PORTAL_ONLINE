<?php
/**
 * Excel Database Engine
 * East West University Portal
 * 
 * Replaces MySQL/SQL Server with 100% Excel-based file storage.
 * - Stores all data in individual CSV/Excel files (data/*.csv)
 * - Generates and maintains a Master Multi-Sheet Excel Workbook (data/EWU_University_Portal_Database.xml)
 * - Provides two-way real-time synchronization between Excel spreadsheets and the application
 * - Requires zero external database server installations
 */

class ExcelDatabase {
    private static ?ExcelPDO $pdoInstance = null;
    private static string $dataDir = '';
    private static string $dbFile = '';
    private static array $tables = [
        'admin',
        'department',
        'faculty',
        'faculty_phonenum',
        'student',
        'student_phonenum',
        'course',
        'course_prerequisite',
        'section',
        'enrollment',
        'pre_advising',
        'includedcourse',
        'payment',
        'faculty_courses'
    ];

    private static bool $isDirty = false;
    private static array $dirtyTables = [];

    public static function init(string $dataDirectory = ''): void {
        if (empty($dataDirectory)) {
            self::$dataDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
        } else {
            self::$dataDir = rtrim($dataDirectory, '/\\');
        }

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        self::$dbFile = self::$dataDir . DIRECTORY_SEPARATOR . '.portal_database.sqlite';
    }

    public static function getDataDir(): string {
        if (empty(self::$dataDir)) {
            self::init();
        }
        return self::$dataDir;
    }

    public static function getTables(): array {
        return self::$tables;
    }

    public static function markDirty(?string $tableName = null): void {
        self::$isDirty = true;
        if ($tableName && in_array(strtolower($tableName), self::$tables)) {
            self::$dirtyTables[strtolower($tableName)] = true;
        } else {
            foreach (self::$tables as $t) {
                self::$dirtyTables[$t] = true;
            }
        }
    }

    public static function getConnection(): ExcelPDO {
        if (self::$pdoInstance !== null) {
            return self::$pdoInstance;
        }

        self::init();

        $isNewDb = !file_exists(self::$dbFile);
        
        try {
            self::$pdoInstance = new ExcelPDO('sqlite:' . self::$dbFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Register custom SQLite functions for MySQL compatibility
            self::registerCompatibilityFunctions(self::$pdoInstance);

            // Enable foreign keys
            self::$pdoInstance->exec("PRAGMA foreign_keys = ON;");

            if ($isNewDb || self::needsSchemaInitialization()) {
                self::createSchema(self::$pdoInstance);
                self::seedInitialData(self::$pdoInstance);
                self::saveAllToExcel();
            } else {
                // Check if any CSV file has been modified externally
                self::syncFromExternalExcelFiles(self::$pdoInstance);
            }

            // Register shutdown handler to ensure all pending writes are saved to Excel files
            register_shutdown_function([self::class, 'saveAllIfDirty']);

            return self::$pdoInstance;
        } catch (Exception $e) {
            die("<div style='font-family: sans-serif; padding: 30px; background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; border-radius: 8px; margin: 40px auto; max-width: 600px;'>"
              . "<h2>EWU Portal - Excel Database Error</h2>"
              . "<p>Unable to initialize the Excel database engine.</p>"
              . "<p><strong>Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>"
              . "</div>");
        }
    }

    private static function registerCompatibilityFunctions(PDO $pdo): void {
        $regFn = function(string $name, callable $fn) use ($pdo) {
            if (method_exists($pdo, 'createFunction')) {
                $pdo->createFunction($name, $fn);
            } elseif (method_exists($pdo, 'sqliteCreateFunction')) {
                @$pdo->sqliteCreateFunction($name, $fn);
            }
        };

        // CONCAT(a, b, c, ...)
        $regFn('CONCAT', function (...$args) {
            return implode('', $args);
        });

        // ROW_COUNT()
        $regFn('ROW_COUNT', function () use ($pdo) {
            return $pdo->query("SELECT changes()")->fetchColumn();
        });

        // IFNULL(val, default)
        $regFn('IFNULL', function ($val, $default) {
            return ($val !== null && $val !== '') ? $val : $default;
        });

        // NOW()
        $regFn('NOW', function () {
            return date('Y-m-d H:i:s');
        });
    }

    private static function needsSchemaInitialization(): bool {
        if (!file_exists(self::$dbFile)) {
            return true;
        }
        $count = self::$pdoInstance->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='student'")->fetchColumn();
        return $count == 0;
    }

    private static function createSchema(PDO $pdo): void {
        $schema = "
        CREATE TABLE IF NOT EXISTS admin (
            Admin_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Username TEXT UNIQUE NOT NULL,
            Password TEXT NOT NULL,
            Full_Name TEXT NOT NULL,
            E_mail TEXT UNIQUE NOT NULL,
            Created_At TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS department (
            Dept_ID INTEGER PRIMARY KEY,
            Dept_Name TEXT NOT NULL,
            Head_Faculty_ID TEXT DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS faculty (
            Faculty_ID TEXT PRIMARY KEY,
            First_name TEXT NOT NULL,
            Last_name TEXT NOT NULL,
            Designation TEXT DEFAULT NULL,
            Room_No TEXT DEFAULT NULL,
            E_mail TEXT DEFAULT NULL,
            Password TEXT NOT NULL,
            Dept_ID INTEGER DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS faculty_phonenum (
            Faculty_ID TEXT PRIMARY KEY,
            Phone_Number1 TEXT NOT NULL,
            Phone_Number2 TEXT DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS student (
            Student_ID TEXT PRIMARY KEY,
            First_name TEXT NOT NULL,
            Last_name TEXT NOT NULL,
            E_mail TEXT UNIQUE DEFAULT NULL,
            Password TEXT NOT NULL,
            Address TEXT DEFAULT NULL,
            DOB TEXT DEFAULT NULL,
            Faculty_ID TEXT DEFAULT NULL,
            Dept_ID INTEGER DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS student_phonenum (
            Student_ID TEXT PRIMARY KEY,
            Phone_Number1 TEXT NOT NULL,
            Phone_Number2 TEXT DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS course (
            Course_ID TEXT PRIMARY KEY,
            Course_Title TEXT NOT NULL,
            Credits REAL NOT NULL,
            Dept_ID INTEGER DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS course_prerequisite (
            Course_ID TEXT NOT NULL,
            Pre_Course_ID TEXT NOT NULL,
            PRIMARY KEY (Course_ID, Pre_Course_ID)
        );

        CREATE TABLE IF NOT EXISTS section (
            Section_Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Section_No INTEGER NOT NULL,
            Time_Slot TEXT DEFAULT NULL,
            Room_No TEXT DEFAULT NULL,
            Capacity INTEGER DEFAULT NULL,
            Course_ID TEXT DEFAULT NULL,
            Faculty_ID TEXT DEFAULT NULL,
            UNIQUE (Course_ID, Section_No)
        );

        CREATE TABLE IF NOT EXISTS enrollment (
            Enrollment_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Enrollment_Type TEXT DEFAULT 'Regular',
            Advising_Status TEXT DEFAULT 'Pending',
            Mid_Mark REAL DEFAULT NULL,
            Final_Mark REAL DEFAULT NULL,
            Grade TEXT DEFAULT 'N/A',
            Section_Id INTEGER DEFAULT NULL,
            ManagedBy_Faculty_ID TEXT DEFAULT NULL,
            Student_ID TEXT DEFAULT NULL,
            Semester TEXT NOT NULL DEFAULT 'Summer',
            Year INTEGER NOT NULL DEFAULT 2026
        );

        CREATE TABLE IF NOT EXISTS pre_advising (
            Pre_Advising_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Submission_TimeStamp TEXT DEFAULT CURRENT_TIMESTAMP,
            Semester TEXT DEFAULT NULL,
            Year INTEGER DEFAULT NULL,
            Student_ID TEXT DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS includedcourse (
            Pre_Advising_ID INTEGER NOT NULL,
            Course_ID TEXT NOT NULL,
            Status TEXT NOT NULL DEFAULT 'Pending',
            PRIMARY KEY (Pre_Advising_ID, Course_ID)
        );

        CREATE TABLE IF NOT EXISTS payment (
            Payment_Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Transaction_Id TEXT UNIQUE NOT NULL,
            Payment_Status TEXT DEFAULT 'Pending',
            Amount REAL NOT NULL,
            Semester TEXT DEFAULT NULL,
            Year INTEGER DEFAULT NULL,
            Payment_Date TEXT DEFAULT NULL,
            Student_ID TEXT DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS faculty_courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course TEXT NOT NULL,
            section TEXT NOT NULL,
            faculty TEXT NOT NULL,
            capacity TEXT NOT NULL,
            timing TEXT NOT NULL,
            room_no TEXT NOT NULL
        );
        ";

        $pdo->exec($schema);
    }

    private static function seedInitialData(PDO $pdo): void {
        // If CSV files already exist in data/, load from them; otherwise seed defaults
        $hasCsvFiles = false;
        foreach (self::$tables as $table) {
            $csvFile = self::$dataDir . DIRECTORY_SEPARATOR . $table . '.csv';
            if (file_exists($csvFile) && filesize($csvFile) > 10) {
                $hasCsvFiles = true;
                break;
            }
        }

        if ($hasCsvFiles) {
            self::loadAllFromCsv($pdo);
            return;
        }

        // Insert Admin
        $pdo->exec("INSERT INTO admin (Admin_ID, Username, Password, Full_Name, E_mail) VALUES
        (1, 'admin', '\$2y\$10\$8.u0j.P4cOa9rJgK/W1M/uN4oP8qR7sT6uV5wX4yZ3aB2c1d0e9f', 'System Administrator', 'admin@ewubd.edu');");

        // Insert Departments
        $pdo->exec("INSERT INTO department (Dept_ID, Dept_Name, Head_Faculty_ID) VALUES
        (101, 'Computer Science & Engineering', '1652688915'),
        (102, 'Electrical & Electronic Engineering', '1652688917'),
        (103, 'Business Administration', '1652688918'),
        (104, 'Pharmacy', NULL);");

        // Insert Faculty
        $pdo->exec("INSERT INTO faculty (Faculty_ID, First_name, Last_name, Designation, Room_No, E_mail, Password, Dept_ID) VALUES
        ('1652688915', 'Dr. Ahmed', 'Hasan', 'Professor & Chairperson', 'AB1-602', 'ahmed.hasan@ewubd.edu', '\$2y\$10\$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 101),
        ('1652688916', 'Dr. Farhana', 'Sultana', 'Associate Professor', 'AB2-405', 'farhana.sultana@ewubd.edu', '\$2y\$10\$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 101),
        ('1652688917', 'Md. Rashidul', 'Islam', 'Assistant Professor', 'AB1-508', 'rashidul.islam@ewubd.edu', '\$2y\$10\$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 102),
        ('1652688918', 'Prof. Shahana', 'Begum', 'Professor', 'AB3-301', 'shahana.begum@ewubd.edu', '\$2y\$10\$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 103);");

        // Faculty Phone Numbers
        $pdo->exec("INSERT INTO faculty_phonenum (Faculty_ID, Phone_Number1, Phone_Number2) VALUES
        ('1652688915', '+8801711122334', '+8801811122334'),
        ('1652688916', '+8801722233445', NULL),
        ('1652688917', '+8801733344556', '+8801933344556'),
        ('1652688918', '+8801744455667', NULL);");

        // Students
        $pdo->exec("INSERT INTO student (Student_ID, First_name, Last_name, E_mail, Password, Address, DOB, Faculty_ID, Dept_ID) VALUES
        ('2023-3-60-621', 'Tanvir', 'Ahmed', 'tanvir.621@std.ewubd.edu', '\$2y\$10\$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'House 45, Road 12, Block C, Aftabnagar, Dhaka', '2002-05-14', '1652688915', 101),
        ('2023-3-60-622', 'Anika', 'Rahman', 'anika.622@std.ewubd.edu', '\$2y\$10\$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'Sector 7, Uttara, Dhaka', '2003-08-22', '1652688916', 101),
        ('2023-3-60-623', 'Sabbir', 'Hossain', 'sabbir.623@std.ewubd.edu', '\$2y\$10\$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'Badda, Rampura, Dhaka', '2001-11-03', '1652688917', 102),
        ('2023-3-60-624', 'Mahir', 'Faisal', 'mahir.624@std.ewubd.edu', '\$2y\$10\$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'Dhanmondi 32, Dhaka', '2002-01-19', '1652688918', 103);");

        // Student Phone Numbers
        $pdo->exec("INSERT INTO student_phonenum (Student_ID, Phone_Number1, Phone_Number2) VALUES
        ('2023-3-60-621', '+8801812345678', '+8801512345678'),
        ('2023-3-60-622', '+8801912345679', NULL),
        ('2023-3-60-623', '+8801612345680', '+8801712345680'),
        ('2023-3-60-624', '+8801312345681', NULL);");

        // Courses
        $pdo->exec("INSERT INTO course (Course_ID, Course_Title, Credits, Dept_ID) VALUES
        ('CSE101', 'Discrete Mathematics', 3.0, 101),
        ('CSE102', 'Structured Programming Language', 3.0, 101),
        ('CSE103', 'Structured Programming Language', 4.0, 101),
        ('CSE104', 'Discrete Mathematics & Logic', 3.0, 101),
        ('CSE105', 'Data Structures', 3.0, 101),
        ('CSE107', 'Object Oriented Programming', 3.0, 101),
        ('CSE109', 'Object Oriented Programming Lab', 1.0, 101),
        ('CSE110', 'Algorithms', 3.0, 101),
        ('CSE205', 'Algorithms & Complexities', 3.0, 101),
        ('CSE207', 'Data Structures and Algorithms', 4.0, 101),
        ('CSE209', 'Digital Logic Design', 3.0, 101),
        ('CSE246', 'Algorithms Analysis & Design', 3.0, 101),
        ('CSE251', 'Electronic Devices and Circuits', 3.0, 101),
        ('CSE299', 'Junior Design Project', 3.0, 101),
        ('CSE301', 'Database Management Systems', 3.0, 101),
        ('CSE302', 'Database Systems', 3.0, 101),
        ('CSE303', 'Database Systems Lab', 1.0, 101),
        ('CSE325', 'Operating Systems', 3.0, 101),
        ('CSE327', 'Software Engineering', 3.0, 101),
        ('CSE331', 'Microprocessor and Microcontrollers', 3.0, 101),
        ('CSE338', 'Computer Networks', 3.0, 101),
        ('CSE347', 'Information System Analysis and Design', 3.0, 101),
        ('CSE350', 'Computer Architecture', 3.0, 101),
        ('CSE360', 'Artificial Intelligence', 3.0, 101),
        ('CSE366', 'Machine Learning', 3.0, 101),
        ('CSE381', 'Web Programming', 3.0, 101),
        ('CSE401', 'Software Engineering & Design', 3.0, 101),
        ('CSE441', 'Computer Graphics', 3.0, 101),
        ('CSE442', 'Digital Image Processing', 3.0, 101),
        ('CSE447', 'Compiler Design', 3.0, 101),
        ('CSE479', 'Cloud Computing', 3.0, 101),
        ('CSE480', 'Mobile Application Development', 3.0, 101),
        ('CSE496', 'Computer Security & Cryptography', 3.0, 101),
        ('CSE499A', 'Senior Design Project / Thesis I', 2.0, 101),
        ('CSE499B', 'Senior Design Project / Thesis II', 2.0, 101),
        ('MAT101', 'Differential and Integral Calculus', 3.0, 101),
        ('PHY109', 'Engineering Physics & Electromagnetics', 4.0, 101),
        ('ENG101', 'Basic Functional English', 3.0, 101),
        ('EEE101', 'Electrical Circuits I', 3.0, 102),
        ('BBA101', 'Principles of Management', 3.0, 103);");

        // Course Prerequisites
        $pdo->exec("INSERT INTO course_prerequisite (Course_ID, Pre_Course_ID) VALUES
        ('CSE302', 'CSE103'),
        ('CSE303', 'CSE302'),
        ('CSE401', 'CSE302'),
        ('CSE104', 'CSE103'),
        ('CSE207', 'CSE102'),
        ('CSE246', 'CSE207'),
        ('CSE325', 'CSE207');");

        // Sections
        $pdo->exec("INSERT INTO section (Section_Id, Section_No, Time_Slot, Room_No, Capacity, Course_ID, Faculty_ID) VALUES
        (1, 1, 'Sun-Tue 08:30 AM - 10:00 AM', 'AB1-502', 35, 'CSE302', '1652688915'),
        (2, 2, 'Mon-Wed 10:10 AM - 11:40 AM', 'AB1-503', 35, 'CSE302', '1652688916'),
        (3, 1, 'Sun-Tue 10:10 AM - 11:40 AM', 'AB1-Lab3', 30, 'CSE303', '1652688915'),
        (4, 1, 'Mon-Wed 01:30 PM - 03:00 PM', 'AB1-401', 40, 'CSE103', '1652688916'),
        (5, 1, 'Sun-Tue 11:50 AM - 01:20 PM', 'AB2-302', 35, 'EEE101', '1652688917'),
        (6, 1, 'Mon-Wed 08:30 AM - 10:00 AM', 'AB3-201', 45, 'BBA101', '1652688918'),
        (7, 1, 'Sun-Tue 01:30 PM - 03:00 PM', 'AB1-601', 35, 'CSE401', '1652688915'),
        (8, 1, 'Mon-Wed 03:10 PM - 04:40 PM', 'AB1-Lab1', 30, 'CSE104', '1652688916'),
        (9, 1, 'Sun-Tue 08:30 AM - 10:00 AM', 'AB2-201', 40, 'MAT101', '1652688916');");

        // Enrollments
        $pdo->exec("INSERT INTO enrollment (Enrollment_ID, Enrollment_Type, Advising_Status, Mid_Mark, Final_Mark, Grade, Section_Id, ManagedBy_Faculty_ID, Student_ID, Semester, Year) VALUES
        (1, 'Regular', 'Approved', 28.50, 56.00, 'A+', 1, '1652688915', '2023-3-60-621', 'Summer', 2026),
        (2, 'Regular', 'Approved', 25.00, 49.50, 'A-', 3, '1652688915', '2023-3-60-621', 'Summer', 2026),
        (3, 'Regular', 'Approved', 29.00, 58.00, 'A+', 2, '1652688916', '2023-3-60-622', 'Summer', 2026),
        (4, 'Regular', 'Pending', 22.00, 41.00, 'B', 5, '1652688917', '2023-3-60-623', 'Summer', 2026),
        (5, 'Regular', 'Approved', 27.00, 53.00, 'A+', 6, '1652688918', '2023-3-60-624', 'Summer', 2026),
        (101, 'Regular', 'Approved', 29.50, 58.00, 'A+', 1, '1652688915', '2023-3-60-621', 'Spring', 2026),
        (102, 'Regular', 'Approved', 27.00, 53.50, 'A+', 8, '1652688916', '2023-3-60-621', 'Spring', 2026),
        (103, 'Regular', 'Approved', 25.50, 50.00, 'A', 9, '1652688916', '2023-3-60-621', 'Spring', 2026),
        (104, 'Regular', 'Approved', 28.00, 56.50, 'A+', 6, '1652688918', '2023-3-60-621', 'Spring', 2026),
        (105, 'Regular', 'Approved', 30.00, 59.00, 'A+', 4, '1652688915', '2023-3-60-621', 'Fall', 2025),
        (106, 'Regular', 'Approved', 26.50, 52.00, 'A', 5, '1652688917', '2023-3-60-621', 'Fall', 2025),
        (107, 'Regular', 'Approved', 28.50, 55.00, 'A+', 7, '1652688915', '2023-3-60-621', 'Fall', 2025),
        (108, 'Regular', 'Approved', 28.00, 55.00, 'A+', 1, '1652688915', '2023-3-60-622', 'Spring', 2026),
        (109, 'Regular', 'Approved', 26.00, 50.00, 'A', 8, '1652688916', '2023-3-60-622', 'Spring', 2026),
        (110, 'Regular', 'Approved', 29.00, 57.00, 'A+', 4, '1652688915', '2023-3-60-622', 'Fall', 2025),
        (111, 'Regular', 'Approved', 27.50, 53.00, 'A+', 5, '1652688917', '2023-3-60-622', 'Fall', 2025);");

        // Pre Advising
        $pdo->exec("INSERT INTO pre_advising (Pre_Advising_ID, Submission_TimeStamp, Semester, Year, Student_ID) VALUES
        (1, datetime('now', '-2 day'), 'Summer', 2026, '2023-3-60-621'),
        (2, datetime('now', '-5 day'), 'Summer', 2026, '2023-3-60-622'),
        (3, datetime('now', '-2 day'), 'Summer', 2026, '2023-3-60-623');");

        // Included Courses
        $pdo->exec("INSERT INTO includedcourse (Pre_Advising_ID, Course_ID, Status) VALUES
        (1, 'CSE101', 'Approved'),
        (1, 'CSE102', 'Approved'),
        (1, 'CSE104', 'Approved'),
        (1, 'CSE207', 'Approved'),
        (1, 'CSE302', 'Approved'),
        (1, 'CSE325', 'Pending'),
        (2, 'CSE302', 'Approved'),
        (2, 'CSE401', 'Pending'),
        (3, 'EEE101', 'Pending');");

        // Payments
        $pdo->exec("INSERT INTO payment (Payment_Id, Transaction_Id, Payment_Status, Amount, Semester, Year, Payment_Date, Student_ID) VALUES
        (1, 'TXN-2026-EWU-9821', 'Paid', 34500.00, 'Spring', 2026, '2026-01-15', '2023-3-60-621'),
        (2, 'TXN-2026-EWU-9822', 'Paid', 34500.00, 'Spring', 2026, '2026-01-16', '2023-3-60-622'),
        (3, 'TXN-2026-EWU-9823', 'Pending', 18500.00, 'Summer', 2026, NULL, '2023-3-60-621'),
        (4, 'TXN-2026-EWU-9824', 'Paid', 31000.00, 'Spring', 2026, '2026-01-20', '2023-3-60-623');");

        // Faculty Courses Schedule
        $pdo->exec("INSERT INTO faculty_courses (course, section, faculty, capacity, timing, room_no) VALUES
        ('CSE101', '1', 'MAR', '0/30', 'W 10:10 AM - 11:40 AM', '429'),
        ('CSE101', '1', 'MAR', '0/30', 'M 10:10 AM - 11:40 AM', '530 (C. Lab-2)'),
        ('CSE101', '2', 'AT', '0/30', 'S 08:30 AM - 10:00 AM', '212'),
        ('CSE101', '2', 'AT', '0/30', 'T 08:30 AM - 10:00 AM', '372 (SEIP Lab)'),
        ('CSE101', '3', 'AT', '0/30', 'T 10:10 AM - 11:40 AM', '372 (SEIP Lab)'),
        ('CSE101', '3', 'AT', '0/30', 'S 10:10 AM - 11:40 AM', '223'),
        ('CSE101', '4', 'MSHQ', '0/30', 'T 08:30 AM - 10:00 AM', 'AB2-201'),
        ('CSE101', '4', 'MSHQ', '0/30', 'R 08:30 AM - 10:00 AM', '372 (SEIP Lab)'),
        ('CSE101', '5', 'DMZM', '0/30', 'T 03:10 PM - 04:40 PM', '531'),
        ('CSE101', '5', 'DMZM', '0/30', 'S 03:10 PM - 04:40 PM', '241'),
        ('CSE101', '6', 'DMZM', '0/30', 'T 04:50 PM - 06:20 PM', '533 (C. Lab-3)'),
        ('CSE101', '6', 'DMZM', '0/30', 'S 04:50 PM - 06:20 PM', '372'),
        ('CSE101', '7', 'AQUIB', '0/30', 'S 03:10 PM - 04:40 PM', '217'),
        ('CSE101', '7', 'AQUIB', '0/30', 'T 03:10 PM - 04:40 PM', '372 (SEIP Lab)'),
        ('CSE101', '8', 'AQUIB', '0/30', 'T 04:50 PM - 06:20 PM', '372 (SEIP Lab)'),
        ('CSE101', '8', 'AQUIB', '0/30', 'S 04:50 PM - 06:20 PM', '221'),
        ('CSE101', '9', 'TZE', '0/30', 'S 03:10 PM - 04:40 PM', 'AB2-301'),
        ('CSE101', '9', 'TZE', '0/30', 'T 03:10 PM - 04:40 PM', '435 (Virtual Reality and Augmented Reality Lab)'),
        ('CSE101', '10', 'TZE', '0/30', 'T 04:50 PM - 06:20 PM', '534 (C. Lab-4)'),
        ('CSE101', '10', 'TZE', '0/30', 'S 04:50 PM - 06:20 PM', '435'),
        ('CSE103', '1', 'FHT', '0/35', 'S 08:30 AM - 10:00 AM', '321'),
        ('CSE103', '1', 'FHT', '0/35', 'T 08:30 AM - 10:00 AM', '530 (C. Lab-2)'),
        ('CSE103', '2', 'SRH', '0/35', 'M 10:10 AM - 11:40 AM', '325'),
        ('CSE103', '2', 'SRH', '0/35', 'W 10:10 AM - 11:40 AM', '372 (SEIP Lab)'),
        ('CSE104', '1', 'FAR', '0/30', 'M 03:10 PM - 04:40 PM', 'AB1-Lab1'),
        ('CSE104', '1', 'FAR', '0/30', 'W 03:10 PM - 04:40 PM', '429'),
        ('CSE104', '2', 'MSHQ', '0/30', 'S 01:30 PM - 03:00 PM', 'AB1-Lab1'),
        ('CSE104', '2', 'MSHQ', '0/30', 'T 01:30 PM - 03:00 PM', '321'),
        ('CSE207', '1', 'DMZM', '0/35', 'S 10:10 AM - 11:40 AM', '431'),
        ('CSE207', '1', 'DMZM', '0/35', 'T 10:10 AM - 11:40 AM', '534 (C. Lab-4)'),
        ('CSE207', '2', 'TZE', '0/35', 'M 08:30 AM - 10:00 AM', '217'),
        ('CSE207', '2', 'TZE', '0/35', 'W 08:30 AM - 10:00 AM', '533 (C. Lab-3)'),
        ('CSE302', '1', 'AH', '0/35', 'S 08:30 AM - 10:00 AM', 'AB1-502'),
        ('CSE302', '1', 'AH', '0/35', 'T 08:30 AM - 10:00 AM', 'AB1-502'),
        ('CSE302', '2', 'FAR', '0/35', 'M 10:10 AM - 11:40 AM', 'AB1-503'),
        ('CSE302', '2', 'FAR', '0/35', 'W 10:10 AM - 11:40 AM', 'AB1-503'),
        ('CSE303', '1', 'AH', '0/30', 'S 10:10 AM - 11:40 AM', 'AB1-Lab3'),
        ('CSE303', '1', 'AH', '0/30', 'T 10:10 AM - 11:40 AM', 'AB1-Lab3'),
        ('CSE401', '1', 'AH', '0/35', 'S 01:30 PM - 03:00 PM', 'AB1-601'),
        ('CSE401', '1', 'AH', '0/35', 'T 01:30 PM - 03:00 PM', 'AB1-601'),
        ('CSE401', '2', 'FHT', '0/35', 'M 01:30 PM - 03:00 PM', 'AB1-602'),
        ('CSE401', '2', 'FHT', '0/35', 'W 01:30 PM - 03:00 PM', 'AB1-602'),
        ('MAT101', '1', 'FAR', '0/40', 'S 08:30 AM - 10:00 AM', 'AB2-201'),
        ('MAT101', '1', 'FAR', '0/40', 'T 08:30 AM - 10:00 AM', 'AB2-201'),
        ('MAT101', '2', 'MAR', '0/40', 'M 11:50 AM - 01:20 PM', 'AB2-202'),
        ('MAT101', '2', 'MAR', '0/40', 'W 11:50 AM - 01:20 PM', 'AB2-202'),
        ('PHY109', '1', 'SRH', '0/35', 'S 11:50 AM - 01:20 PM', 'AB2-301'),
        ('PHY109', '1', 'SRH', '0/35', 'T 11:50 AM - 01:20 PM', 'AB2-Lab1'),
        ('ENG101', '1', 'MSHQ', '0/40', 'M 08:30 AM - 10:00 AM', 'AB3-101'),
        ('ENG101', '1', 'MSHQ', '0/40', 'W 08:30 AM - 10:00 AM', 'AB3-101');");
    }

    public static function saveAllIfDirty(): void {
        if (self::$isDirty && self::$pdoInstance !== null) {
            self::saveAllToExcel();
            self::$isDirty = false;
            self::$dirtyTables = [];
        }
    }

    public static function saveAllToExcel(): void {
        if (self::$pdoInstance === null) {
            return;
        }

        foreach (self::$tables as $table) {
            self::saveTableToCsv(self::$pdoInstance, $table);
        }

        self::generateMasterExcelXml(self::$pdoInstance);
    }

    public static function saveTableToCsv(PDO $pdo, string $tableName): void {
        $csvFile = self::$dataDir . DIRECTORY_SEPARATOR . $tableName . '.csv';
        $stmt = $pdo->query("SELECT * FROM " . $tableName);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fp = fopen($csvFile, 'w');
        if (!$fp) {
            return;
        }

        // Write header
        if (!empty($rows)) {
            $headers = array_keys($rows[0]);
            fputcsv($fp, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                // Ensure null values output as empty strings
                $formattedRow = array_map(function($v) {
                    return $v === null ? '' : (string)$v;
                }, $row);
                fputcsv($fp, $formattedRow, ',', '"', '\\');
            }
        } else {
            // If table has no rows, get columns from table info
            $colStmt = $pdo->query("PRAGMA table_info(" . $tableName . ")");
            $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = array_column($cols, 'name');
            fputcsv($fp, $headers, ',', '"', '\\');
        }

        fclose($fp);
    }

    public static function loadTableFromCsv(PDO $pdo, string $tableName): void {
        $csvFile = self::$dataDir . DIRECTORY_SEPARATOR . $tableName . '.csv';
        if (!file_exists($csvFile)) {
            return;
        }

        $fp = fopen($csvFile, 'r');
        if (!$fp) {
            return;
        }

        $headers = fgetcsv($fp, 0, ',', '"', '\\');
        if (!$headers || empty($headers)) {
            fclose($fp);
            return;
        }

        // Clean headers (remove BOM if any)
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $pdo->exec("DELETE FROM " . $tableName);

        $placeholders = implode(', ', array_fill(0, count($headers), '?'));
        $colNames = implode(', ', array_map(fn($c) => '"' . $c . '"', $headers));
        $insertSql = "INSERT INTO " . $tableName . " (" . $colNames . ") VALUES (" . $placeholders . ")";
        $stmt = $pdo->prepare($insertSql);

        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            // Adjust row size to match headers count
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }

            $bindValues = array_map(function($val) {
                return $val === '' ? null : $val;
            }, $row);

            $stmt->execute($bindValues);
        }

        fclose($fp);
    }

    public static function loadAllFromCsv(PDO $pdo): void {
        foreach (self::$tables as $table) {
            self::loadTableFromCsv($pdo, $table);
        }
    }

    public static function syncFromExternalExcelFiles(PDO $pdo): void {
        $metaFile = self::$dataDir . DIRECTORY_SEPARATOR . '.sync_meta.json';
        $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : [];

        $updated = false;
        foreach (self::$tables as $table) {
            $csvFile = self::$dataDir . DIRECTORY_SEPARATOR . $table . '.csv';
            if (file_exists($csvFile)) {
                $mtime = filemtime($csvFile);
                if (!isset($meta[$table]) || $mtime > $meta[$table]) {
                    self::loadTableFromCsv($pdo, $table);
                    $meta[$table] = $mtime;
                    $updated = true;
                }
            }
        }

        if ($updated) {
            file_put_contents($metaFile, json_encode($meta));
        }
    }

    /**
     * Generates a Master Multi-Sheet Excel Workbook in SpreadsheetML (.xml)
     * which opens natively in Microsoft Excel with formatted multi-sheet tabs and styling.
     */
    public static function generateMasterExcelXml(PDO $pdo): string {
        $xmlFile = self::$dataDir . DIRECTORY_SEPARATOR . 'EWU_University_Portal_Database.xml';

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $xml .= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
        $xml .= " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
        $xml .= " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";
        
        // Styles
        $xml .= " <Styles>\n";
        $xml .= "  <Style ss:ID=\"Default\" ss:Name=\"Normal\">\n";
        $xml .= "   <Alignment ss:Vertical=\"Center\"/>\n";
        $xml .= "   <Font ss:FontName=\"Segoe UI\" ss:Size=\"10\" ss:Color=\"#333333\"/>\n";
        $xml .= "  </Style>\n";
        $xml .= "  <Style ss:ID=\"HeaderStyle\">\n";
        $xml .= "   <Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\"/>\n";
        $xml .= "   <Borders>\n";
        $xml .= "    <Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/>\n";
        $xml .= "   </Borders>\n";
        $xml .= "   <Font ss:FontName=\"Segoe UI\" ss:Size=\"11\" ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/>\n";
        $xml .= "   <Interior ss:Color=\"#1E3A8A\" ss:Pattern=\"Solid\"/>\n";
        $xml .= "  </Style>\n";
        $xml .= "  <Style ss:ID=\"RowEven\">\n";
        $xml .= "   <Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>\n";
        $xml .= "  </Style>\n";
        $xml .= "  <Style ss:ID=\"RowOdd\">\n";
        $xml .= "   <Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>\n";
        $xml .= "  </Style>\n";
        $xml .= " </Styles>\n";

        foreach (self::$tables as $table) {
            $sheetName = ucfirst($table);
            if (strlen($sheetName) > 31) {
                $sheetName = substr($sheetName, 0, 31);
            }

            $stmt = $pdo->query("SELECT * FROM " . $table);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get columns
            if (!empty($rows)) {
                $headers = array_keys($rows[0]);
            } else {
                $colStmt = $pdo->query("PRAGMA table_info(" . $table . ")");
                $headers = array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'name');
            }

            $xml .= " <Worksheet ss:Name=\"" . htmlspecialchars($sheetName) . "\">\n";
            $xml .= "  <Table ss:DefaultRowHeight=\"20\">\n";

            // Columns definition
            foreach ($headers as $h) {
                $xml .= "   <Column ss:AutoFitWidth=\"1\" ss:Width=\"120\"/>\n";
            }

            // Header row
            $xml .= "   <Row ss:Height=\"24\">\n";
            foreach ($headers as $h) {
                $xml .= "    <Cell ss:StyleID=\"HeaderStyle\"><Data ss:Type=\"String\">" . htmlspecialchars($h) . "</Data></Cell>\n";
            }
            $xml .= "   </Row>\n";

            // Data rows
            $rowIndex = 0;
            foreach ($rows as $row) {
                $rowStyle = ($rowIndex % 2 === 0) ? 'RowEven' : 'RowOdd';
                $xml .= "   <Row ss:StyleID=\"" . $rowStyle . "\">\n";
                foreach ($headers as $col) {
                    $val = $row[$col] ?? '';
                    $type = is_numeric($val) && !preg_match('/^0[0-9]+/', $val) && !str_contains((string)$col, 'ID') && !str_contains((string)$col, 'Phone') ? 'Number' : 'String';
                    $xml .= "    <Cell><Data ss:Type=\"" . $type . "\">" . htmlspecialchars((string)$val) . "</Data></Cell>\n";
                }
                $xml .= "   </Row>\n";
                $rowIndex++;
            }

            $xml .= "  </Table>\n";
            $xml .= " </Worksheet>\n";
        }

        $xml .= "</Workbook>\n";

        file_put_contents($xmlFile, $xml);
        return $xmlFile;
    }
}

/**
 * Extended PDO wrapper that intercepts write queries to automatically track changes
 * and sync them back to the Excel spreadsheets.
 */
class ExcelPDO extends PDO {
    public function exec(string $statement): int|false {
        $result = parent::exec($statement);
        $this->checkStatementForWrites($statement);
        return $result;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $result = parent::query($query, ...$fetchModeArgs);
        $this->checkStatementForWrites($query);
        return $result;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): ExcelPDOStatement|false {
        $stmt = parent::prepare($query, $options);
        if ($stmt instanceof PDOStatement) {
            return new ExcelPDOStatement($stmt, $query);
        }
        return false;
    }

    private function checkStatementForWrites(string $sql): void {
        $trimmed = trim(strtoupper($sql));
        if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE|DROP|CREATE|ALTER)\b/i', $trimmed)) {
            // Find which table is affected
            foreach (ExcelDatabase::getTables() as $t) {
                if (preg_match('/\b' . preg_quote($t, '/') . '\b/i', $sql)) {
                    ExcelDatabase::markDirty($t);
                    return;
                }
            }
            ExcelDatabase::markDirty();
        }
    }
}

/**
 * Extended PDOStatement wrapper that tracks executed prepared statements for write operations.
 */
class ExcelPDOStatement {
    private PDOStatement $stmt;
    private string $queryString;

    public function __construct(PDOStatement $stmt, string $query) {
        $this->stmt = $stmt;
        $this->queryString = $query;
    }

    public function execute(?array $params = null): bool {
        $result = $this->stmt->execute($params);
        if ($result) {
            $trimmed = trim(strtoupper($this->queryString));
            if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $trimmed)) {
                foreach (ExcelDatabase::getTables() as $t) {
                    if (preg_match('/\b' . preg_quote($t, '/') . '\b/i', $this->queryString)) {
                        ExcelDatabase::markDirty($t);
                        return $result;
                    }
                }
                ExcelDatabase::markDirty();
            }
        }
        return $result;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->stmt->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->stmt->fetchAll($mode, ...$args);
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->stmt->fetchColumn($column);
    }

    public function rowCount(): int {
        return $this->stmt->rowCount();
    }

    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool {
        return $this->stmt->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {
        return $this->stmt->bindValue($param, $value, $type);
    }

    public function closeCursor(): bool {
        return $this->stmt->closeCursor();
    }

    public function __call(string $name, array $arguments) {
        return call_user_func_array([$this->stmt, $name], $arguments);
    }
}
