-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2026 at 11:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `portal_management_project`
--
CREATE DATABASE IF NOT EXISTS `portal_management_project` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `portal_management_project`;

-- Drop existing tables to ensure clean schema update
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `includedcourse`;
DROP TABLE IF EXISTS `pre_advising`;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `enrollment`;
DROP TABLE IF EXISTS `section`;
DROP TABLE IF EXISTS `course_prerequisite`;
DROP TABLE IF EXISTS `course`;
DROP TABLE IF EXISTS `student_phonenum`;
DROP TABLE IF EXISTS `student`;
DROP TABLE IF EXISTS `faculty_phonenum`;
DROP TABLE IF EXISTS `department`;
DROP TABLE IF EXISTS `faculty`;
DROP TABLE IF EXISTS `admin`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Full_Name` varchar(100) NOT NULL,
  `E_mail` varchar(100) NOT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `Course_ID` varchar(15) NOT NULL,
  `Course_Title` varchar(100) NOT NULL,
  `Credits` decimal(2,1) NOT NULL,
  `Dept_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_prerequisite`
--

CREATE TABLE `course_prerequisite` (
  `Course_ID` varchar(15) NOT NULL,
  `Pre_Course_ID` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `Dept_ID` int(11) NOT NULL,
  `Dept_Name` varchar(100) NOT NULL,
  `Head_Faculty_ID` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment`
--

CREATE TABLE `enrollment` (
  `Enrollment_ID` int(11) NOT NULL,
  `Enrollment_Type` varchar(50) DEFAULT NULL,
  `Advising_Status` varchar(50) DEFAULT 'Pending',
  `Mid_Mark` decimal(5,2) DEFAULT NULL,
  `Final_Mark` decimal(5,2) DEFAULT NULL,
  `Grade` varchar(5) DEFAULT 'N/A',
  `Section_Id` int(11) DEFAULT NULL,
  `ManagedBy_Faculty_ID` varchar(20) DEFAULT NULL,
  `Student_ID` varchar(20) DEFAULT NULL,
  `Semester` varchar(20) NOT NULL DEFAULT 'Summer',
  `Year` int(11) NOT NULL DEFAULT 2026
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `Faculty_ID` varchar(20) NOT NULL,
  `First_name` varchar(50) NOT NULL,
  `Last_name` varchar(50) NOT NULL,
  `Designation` varchar(50) DEFAULT NULL,
  `Room_No` varchar(20) DEFAULT NULL,
  `E_mail` varchar(100) DEFAULT NULL,
  `Password` varchar(255) NOT NULL DEFAULT '$2y$10$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V',
  `Dept_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_phonenum`
--

CREATE TABLE `faculty_phonenum` (
  `Faculty_ID` varchar(20) NOT NULL,
  `Phone_Number1` varchar(20) NOT NULL,
  `Phone_Number2` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `includedcourse`
--

CREATE TABLE `includedcourse` (
  `Pre_Advising_ID` int(11) NOT NULL,
  `Course_ID` varchar(15) NOT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------



--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_Id` int(11) NOT NULL,
  `Transaction_Id` varchar(100) NOT NULL,
  `Payment_Status` varchar(50) DEFAULT 'Pending',
  `Amount` decimal(10,2) NOT NULL,
  `Semester` varchar(20) DEFAULT NULL,
  `Year` int(11) DEFAULT NULL,
  `Payment_Date` date DEFAULT NULL,
  `Student_ID` varchar(20) DEFAULT NULL
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pre_advising`
--

CREATE TABLE `pre_advising` (
  `Pre_Advising_ID` int(11) NOT NULL,
  `Submission_TimeStamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Semester` varchar(20) DEFAULT NULL,
  `Year` int(11) DEFAULT NULL,
  `Student_ID` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `Section_Id` int(11) NOT NULL,
  `Section_No` int(11) NOT NULL,
  `Time_Slot` varchar(50) DEFAULT NULL,
  `Room_No` varchar(20) DEFAULT NULL,
  `Capacity` int(11) DEFAULT NULL,
  `Course_ID` varchar(15) DEFAULT NULL,
  `Faculty_ID` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `Student_ID` varchar(20) NOT NULL,
  `First_name` varchar(50) NOT NULL,
  `Last_name` varchar(50) NOT NULL,
  `E_mail` varchar(100) DEFAULT NULL,
  `Password` varchar(255) NOT NULL DEFAULT '$2y$10$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9',
  `Address` varchar(255) DEFAULT NULL,
  `DOB` date DEFAULT NULL,
  `Faculty_ID` varchar(20) DEFAULT NULL,
  `Dept_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_phonenum`
--

CREATE TABLE `student_phonenum` (
  `Student_ID` varchar(20) NOT NULL,
  `Phone_Number1` varchar(20) NOT NULL,
  `Phone_Number2` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- SEED DATA INSERTIONS
--

INSERT INTO `admin` (`Admin_ID`, `Username`, `Password`, `Full_Name`, `E_mail`) VALUES
(1, 'admin', '$2y$10$8.u0j.P4cOa9rJgK/W1M/uN4oP8qR7sT6uV5wX4yZ3aB2c1d0e9f', 'System Administrator', 'admin@ewubd.edu');

INSERT INTO `department` (`Dept_ID`, `Dept_Name`, `Head_Faculty_ID`) VALUES
(101, 'Computer Science & Engineering', NULL),
(102, 'Electrical & Electronic Engineering', NULL),
(103, 'Business Administration', NULL),
(104, 'Pharmacy', NULL);

INSERT INTO `faculty` (`Faculty_ID`, `First_name`, `Last_name`, `Designation`, `Room_No`, `E_mail`, `Password`, `Dept_ID`) VALUES
('1652688915', 'Dr. Ahmed', 'Hasan', 'Professor & Chairperson', 'AB1-602', 'ahmed.hasan@ewubd.edu', '$2y$10$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 101),
('1652688916', 'Dr. Farhana', 'Sultana', 'Associate Professor', 'AB2-405', 'farhana.sultana@ewubd.edu', '$2y$10$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 101),
('1652688917', 'Md. Rashidul', 'Islam', 'Assistant Professor', 'AB1-508', 'rashidul.islam@ewubd.edu', '$2y$10$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 102),
('1652688918', 'Prof. Shahana', 'Begum', 'Professor', 'AB3-301', 'shahana.begum@ewubd.edu', '$2y$10$e84WvT6bX.8K7aJpZl1G7.R03wX/VqWj.6T8m9E1P3Q5R7S9T0U1V', 103);

UPDATE `department` SET `Head_Faculty_ID` = '1652688915' WHERE `Dept_ID` = 101;
UPDATE `department` SET `Head_Faculty_ID` = '1652688917' WHERE `Dept_ID` = 102;
UPDATE `department` SET `Head_Faculty_ID` = '1652688918' WHERE `Dept_ID` = 103;

INSERT INTO `faculty_phonenum` (`Faculty_ID`, `Phone_Number1`, `Phone_Number2`) VALUES
('1652688915', '+8801711122334', '+8801811122334'),
('1652688916', '+8801722233445', NULL),
('1652688917', '+8801733344556', '+8801933344556'),
('1652688918', '+8801744455667', NULL);

INSERT INTO `student` (`Student_ID`, `First_name`, `Last_name`, `E_mail`, `Password`, `Address`, `DOB`, `Faculty_ID`, `Dept_ID`) VALUES
('2023-3-60-621', 'Tanvir', 'Ahmed', 'tanvir.621@std.ewubd.edu', '$2y$10$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'House 45, Road 12, Block C, Aftabnagar, Dhaka', '2002-05-14', '1652688915', 101),
('2023-3-60-622', 'Anika', 'Rahman', 'anika.622@std.ewubd.edu', '$2y$10$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'Sector 7, Uttara, Dhaka', '2003-08-22', '1652688916', 101),
('2023-3-60-623', 'Sabbir', 'Hossain', 'sabbir.623@std.ewubd.edu', '$2y$10$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'Badda, Rampura, Dhaka', '2001-11-03', '1652688917', 102),
('2023-3-60-624', 'Mahir', 'Faisal', 'mahir.624@std.ewubd.edu', '$2y$10$5M8yvW7cX9L0b8a1Z2G3H4.I5j6K7L8M9N0O1P2Q3R4S5T6U7V8W9', 'Dhanmondi 32, Dhaka', '2002-01-19', '1652688918', 103);

INSERT INTO `student_phonenum` (`Student_ID`, `Phone_Number1`, `Phone_Number2`) VALUES
('2023-3-60-621', '+8801812345678', '+8801512345678'),
('2023-3-60-622', '+8801912345679', NULL),
('2023-3-60-623', '+8801612345680', '+8801712345680'),
('2023-3-60-624', '+8801312345681', NULL);

INSERT INTO `course` (`Course_ID`, `Course_Title`, `Credits`, `Dept_ID`) VALUES
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
('BBA101', 'Principles of Management', 3.0, 103);

INSERT INTO `course_prerequisite` (`Course_ID`, `Pre_Course_ID`) VALUES
('CSE302', 'CSE103'),
('CSE303', 'CSE302'),
('CSE401', 'CSE302'),
('CSE104', 'CSE103'),
('CSE207', 'CSE102'),
('CSE246', 'CSE207'),
('CSE325', 'CSE207');

INSERT INTO `section` (`Section_Id`, `Section_No`, `Time_Slot`, `Room_No`, `Capacity`, `Course_ID`, `Faculty_ID`) VALUES
(1, 1, 'Sun-Tue 08:30 AM - 10:00 AM', 'AB1-502', 35, 'CSE302', '1652688915'),
(2, 2, 'Mon-Wed 10:10 AM - 11:40 AM', 'AB1-503', 35, 'CSE302', '1652688916'),
(3, 1, 'Sun-Tue 10:10 AM - 11:40 AM', 'AB1-Lab3', 30, 'CSE303', '1652688915'),
(4, 1, 'Mon-Wed 01:30 PM - 03:00 PM', 'AB1-401', 40, 'CSE103', '1652688916'),
(5, 1, 'Sun-Tue 11:50 AM - 01:20 PM', 'AB2-302', 35, 'EEE101', '1652688917'),
(6, 1, 'Mon-Wed 08:30 AM - 10:00 AM', 'AB3-201', 45, 'BBA101', '1652688918'),
(7, 1, 'Sun-Tue 01:30 PM - 03:00 PM', 'AB1-601', 35, 'CSE401', '1652688915'),
(8, 1, 'Mon-Wed 03:10 PM - 04:40 PM', 'AB1-Lab1', 30, 'CSE104', '1652688916'),
(9, 1, 'Sun-Tue 08:30 AM - 10:00 AM', 'AB2-201', 40, 'MAT101', '1652688916');

INSERT INTO `enrollment` (`Enrollment_ID`, `Enrollment_Type`, `Advising_Status`, `Mid_Mark`, `Final_Mark`, `Grade`, `Section_Id`, `ManagedBy_Faculty_ID`, `Student_ID`, `Semester`, `Year`) VALUES
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
(111, 'Regular', 'Approved', 27.50, 53.00, 'A+', 5, '1652688917', '2023-3-60-622', 'Fall', 2025);

INSERT INTO `pre_advising` (`Pre_Advising_ID`, `Submission_TimeStamp`, `Semester`, `Year`, `Student_ID`) VALUES
(1, CURRENT_TIMESTAMP - INTERVAL 2 DAY, 'Summer', 2026, '2023-3-60-621'),
(2, CURRENT_TIMESTAMP - INTERVAL 5 DAY, 'Summer', 2026, '2023-3-60-622'),
(3, CURRENT_TIMESTAMP - INTERVAL 2 DAY, 'Summer', 2026, '2023-3-60-623');

INSERT INTO `includedcourse` (`Pre_Advising_ID`, `Course_ID`, `Status`) VALUES
(1, 'CSE101', 'Approved'),
(1, 'CSE102', 'Approved'),
(1, 'CSE104', 'Approved'),
(1, 'CSE207', 'Approved'),
(1, 'CSE302', 'Approved'),
(1, 'CSE325', 'Pending'),
(2, 'CSE302', 'Approved'),
(2, 'CSE401', 'Pending'),
(3, 'EEE101', 'Pending');

INSERT INTO `payment` (`Payment_Id`, `Transaction_Id`, `Payment_Status`, `Amount`, `Semester`, `Year`, `Payment_Date`, `Student_ID`) VALUES
(1, 'TXN-2026-EWU-9821', 'Paid', 34500.00, 'Spring', 2026, '2026-01-15', '2023-3-60-621'),
(2, 'TXN-2026-EWU-9822', 'Paid', 34500.00, 'Spring', 2026, '2026-01-16', '2023-3-60-622'),
(3, 'TXN-2026-EWU-9823', 'Pending', 18500.00, 'Summer', 2026, NULL, '2023-3-60-621'),
(4, 'TXN-2026-EWU-9824', 'Paid', 31000.00, 'Spring', 2026, '2026-01-20', '2023-3-60-623');

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`Admin_ID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `E_mail` (`E_mail`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`Course_ID`),
  ADD KEY `Dept_ID` (`Dept_ID`);

--
-- Indexes for table `course_prerequisite`
--
ALTER TABLE `course_prerequisite`
  ADD PRIMARY KEY (`Course_ID`,`Pre_Course_ID`),
  ADD KEY `Pre_Course_ID` (`Pre_Course_ID`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`Dept_ID`),
  ADD KEY `FK_Dept_Head` (`Head_Faculty_ID`);

--
-- Indexes for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`Enrollment_ID`),
  ADD KEY `Section_Id` (`Section_Id`),
  ADD KEY `ManagedBy_Faculty_ID` (`ManagedBy_Faculty_ID`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`Faculty_ID`),
  ADD KEY `Dept_ID` (`Dept_ID`);

--
-- Indexes for table `faculty_phonenum`
--
ALTER TABLE `faculty_phonenum`
  ADD PRIMARY KEY (`Faculty_ID`);

--
-- Indexes for table `includedcourse`
--
ALTER TABLE `includedcourse`
  ADD PRIMARY KEY (`Pre_Advising_ID`,`Course_ID`),
  ADD KEY `Course_ID` (`Course_ID`);


--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_Id`),
  ADD UNIQUE KEY `Transaction_Id` (`Transaction_Id`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- Indexes for table `pre_advising`
--
ALTER TABLE `pre_advising`
  ADD PRIMARY KEY (`Pre_Advising_ID`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`Section_Id`),
  ADD UNIQUE KEY `unique_course_section` (`Course_ID`, `Section_No`),
  ADD KEY `Course_ID` (`Course_ID`),
  ADD KEY `Faculty_ID` (`Faculty_ID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`Student_ID`),
  ADD UNIQUE KEY `E_mail` (`E_mail`),
  ADD KEY `Faculty_ID` (`Faculty_ID`),
  ADD KEY `Dept_ID` (`Dept_ID`);

--
-- Indexes for table `student_phonenum`
--
ALTER TABLE `student_phonenum`
  ADD PRIMARY KEY (`Student_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `admin`
  MODIFY `Admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `enrollment`
  MODIFY `Enrollment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

ALTER TABLE `payment`
  MODIFY `Payment_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `pre_advising`
  MODIFY `Pre_Advising_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `section`
  MODIFY `Section_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `course`
--
ALTER TABLE `course`
  ADD CONSTRAINT `course_ibfk_1` FOREIGN KEY (`Dept_ID`) REFERENCES `department` (`Dept_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `course_prerequisite`
--
ALTER TABLE `course_prerequisite`
  ADD CONSTRAINT `course_prerequisite_ibfk_1` FOREIGN KEY (`Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `course_prerequisite_ibfk_2` FOREIGN KEY (`Pre_Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `FK_Dept_Head` FOREIGN KEY (`Head_Faculty_ID`) REFERENCES `faculty` (`Faculty_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`Section_Id`) REFERENCES `section` (`Section_Id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `enrollment_ibfk_2` FOREIGN KEY (`ManagedBy_Faculty_ID`) REFERENCES `faculty` (`Faculty_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `enrollment_ibfk_3` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `faculty`
--
ALTER TABLE `faculty`
  ADD CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`Dept_ID`) REFERENCES `department` (`Dept_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `faculty_phonenum`
--
ALTER TABLE `faculty_phonenum`
  ADD CONSTRAINT `faculty_phonenum_ibfk_1` FOREIGN KEY (`Faculty_ID`) REFERENCES `faculty` (`Faculty_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `includedcourse`
--
ALTER TABLE `includedcourse`
  ADD CONSTRAINT `includedcourse_ibfk_1` FOREIGN KEY (`Pre_Advising_ID`) REFERENCES `pre_advising` (`Pre_Advising_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `includedcourse_ibfk_2` FOREIGN KEY (`Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE;


--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pre_advising`
--
ALTER TABLE `pre_advising`
  ADD CONSTRAINT `pre_advising_ibfk_1` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `section`
--
ALTER TABLE `section`
  ADD CONSTRAINT `section_ibfk_1` FOREIGN KEY (`Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `section_ibfk_2` FOREIGN KEY (`Faculty_ID`) REFERENCES `faculty` (`Faculty_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`Faculty_ID`) REFERENCES `faculty` (`Faculty_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `student_ibfk_2` FOREIGN KEY (`Dept_ID`) REFERENCES `department` (`Dept_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student_phonenum`
--
ALTER TABLE `student_phonenum`
  ADD CONSTRAINT `student_phonenum_ibfk_1` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Table structure for table `faculty_courses`
--
CREATE TABLE IF NOT EXISTS `faculty_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course` varchar(50) NOT NULL,
  `section` varchar(10) NOT NULL,
  `faculty` varchar(50) NOT NULL,
  `capacity` varchar(20) NOT NULL,
  `timing` varchar(100) NOT NULL,
  `room_no` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `faculty_courses` (`course`, `section`, `faculty`, `capacity`, `timing`, `room_no`) VALUES
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
('ENG101', '1', 'MSHQ', '0/40', 'W 08:30 AM - 10:00 AM', 'AB3-101');

COMMIT;
