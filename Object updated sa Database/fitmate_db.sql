CREATE DATABASE IF NOT EXISTS `fitmate_db`;
USE `fitmate_db`;


-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2026 at 09:03 AM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fitmate_db`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `sp_ProcessMemberPayment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ProcessMemberPayment` (IN `p_MemberID` VARCHAR(15), IN `p_StaffID` VARCHAR(15), IN `p_SubscriptionID` VARCHAR(15), IN `p_Amount` DECIMAL(10,2), IN `p_PaymentMethod` VARCHAR(30), IN `p_ReferenceNumber` VARCHAR(50))   BEGIN
	DECLARE next_id INT;
    SELECT IFNULL(MAX(PaymentID), 5000) + 1 INTO next_id from payment;
   
    INSERT INTO payment (
        PaymentID, MemberID, StaffID, SubscriptionID,
        Amount, PaymentDate, PaymentTime, PaymentMethod, ReferenceNumber
        )
        VALUES(
            next_id, p_MemberID, p_StaffID, p_SubscriptionID,
            p_Amount, CURDATE(), CURTIME(), p_PaymentMethod, p_ReferenceNUmber
            );
            
            UPDATE subscription
            SET Status = 'Active'
            WHERE SubscriptionID = p_SubscriptionID AND MemberID = p_MemberID;
            END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `application`
--

DROP TABLE IF EXISTS `application`;
CREATE TABLE `application` (
  `ApplicationID` varchar(15) NOT NULL,
  `MemberID` varchar(15) NOT NULL,
  `ProgramID` varchar(15) NOT NULL,
  `ApplicationDate` date NOT NULL,
  `Status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application`
--

INSERT INTO `application` (`ApplicationID`, `MemberID`, `ProgramID`, `ApplicationDate`, `Status`) VALUES
('APP301', 'MEM101', 'PRG201', '2026-08-01', 'Approved'),
('APP302', 'MEM102', 'PRG202', '2026-09-05', 'Approved'),
('APP303', 'MEM103', 'PRG201', '2026-10-10', 'Approved'),
('APP304', 'MEM104', 'PRG203', '2026-06-12', 'Approved'),
('APP305', 'MEM105', 'PRG206', '2026-11-01', 'Approved'),
('APP306', 'MEM106', 'PRG202', '2026-09-15', 'Approved'),
('APP307', 'MEM107', 'PRG205', '2026-11-03', 'Approved'),
('APP308', 'MEM108', 'PRG201', '2026-07-20', 'Approved'),
('APP309', 'MEM109', 'PRG202', '2026-09-01', 'Rejected'),
('APP310', 'MEM110', 'PRG204', '2026-10-08', 'Approved'),
('APP311', 'MEM111', 'PRG205', '2026-05-22', 'Approved'),
('APP312', 'MEM112', 'PRG207', '2026-11-05', 'Approved'),
('APP313', 'MEM113', 'PRG208', '2026-11-05', 'Approved'),
('APP314', 'MEM114', 'PRG204', '2026-09-25', 'Approved'),
('APP315', 'MEM115', 'PRG204', '2026-11-10', 'Pending'),
('APP316', 'MEM101', 'PRG206', '2026-11-21', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `AttendanceID` varchar(15) NOT NULL,
  `MemberID` varchar(15) NOT NULL,
  `Date` date NOT NULL,
  `TimeIn` time NOT NULL,
  `TimeOut` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`AttendanceID`, `MemberID`, `Date`, `TimeIn`, `TimeOut`) VALUES
('ATT8001', 'MEM101', '2026-11-11', '07:50:00', '09:45:00'),
('ATT8002', 'MEM102', '2026-11-11', '09:15:00', '11:00:00'),
('ATT8003', 'MEM103', '2026-11-11', '09:50:00', '11:30:00'),
('ATT8004', 'MEM104', '2026-11-11', '13:50:00', '15:30:00'),
('ATT8005', 'MEM105', '2026-11-12', '09:55:00', '11:30:00'),
('ATT8006', 'MEM106', '2026-11-12', '15:50:00', '17:20:00'),
('ATT8007', 'MEM107', '2026-11-13', '15:45:00', '17:15:00'),
('ATT8008', 'MEM108', '2026-11-13', '16:50:00', '18:30:00'),
('ATT8009', 'MEM109', '2026-11-14', '10:50:00', '12:30:00'),
('ATT8010', 'MEM110', '2026-11-14', '08:20:00', '10:00:00'),
('ATT8011', 'MEM111', '2026-11-12', '12:50:00', '14:20:00'),
('ATT8012', 'MEM112', '2026-11-13', '15:20:00', '17:00:00'),
('ATT8013', 'MEM113', '2026-11-11', '10:50:00', '12:15:00'),
('ATT8014', 'MEM114', '2026-11-13', '13:20:00', '14:50:00'),
('ATT8015', 'MEM115', '2026-11-13', '07:55:00', '09:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contact`
--

DROP TABLE IF EXISTS `emergency_contact`;
CREATE TABLE `emergency_contact` (
  `ContactID` varchar(15) NOT NULL,
  `MemberID` varchar(15) NOT NULL,
  `ContactName` varchar(100) NOT NULL,
  `Relationship` varchar(50) NOT NULL,
  `PhoneNumber` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_contact`
--

INSERT INTO `emergency_contact` (`ContactID`, `MemberID`, `ContactName`, `Relationship`, `PhoneNumber`) VALUES
('EMG401', 'MEM101', 'Jooheon Lee', 'Bandmate', '0917-845-1201'),
('EMG402', 'MEM102', 'Lee Know', 'Bandmate', '0918-932-6202'),
('EMG403', 'MEM103', 'Han Jisung', 'Bandmate', '0927-419-8203'),
('EMG404', 'MEM104', 'Wonwoo Jeon', 'Bandmate', '0919-754-3204'),
('EMG405', 'MEM105', 'Jimin Park', 'Bandmate', '0945-286-9205'),
('EMG406', 'MEM106', 'Key Kim', 'Bandmate', '0956-613-7206'),
('EMG407', 'MEM107', 'Wooyoung Jung', 'Bandmate', '0920-347-5207'),
('EMG408', 'MEM108', 'Minhyun Hwang', 'Bandmate', '0966-891-2208'),
('EMG409', 'MEM109', 'Kihyun Yoo', 'Bandmate', '0939-502-6209'),
('EMG410', 'MEM110', 'Chaewon Kim', 'Bandmate', '0977-638-1210'),
('EMG411', 'MEM111', 'Nayeon Im', 'Bandmate', '0995-174-9211'),
('EMG412', 'MEM112', 'Moonbyul Moon', 'Bandmate', '0961-825-3212'),
('EMG413', 'MEM113', 'Mina Kang', 'Friend', '0998-460-7213'),
('EMG414', 'MEM114', 'Bora Yoon', 'Bandmate', '0915-903-4214'),
('EMG415', 'MEM115', 'Sakura Miyawaki', 'Bandmate', '0999-217-8215');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `EquipmentID` varchar(15) NOT NULL,
  `StaffID` varchar(15) NOT NULL,
  `EquipmentName` varchar(100) NOT NULL,
  `EquipmentStatus` varchar(50) NOT NULL,
  `PurchaseDate` date NOT NULL,
  `Vendor` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`EquipmentID`, `StaffID`, `EquipmentName`, `EquipmentStatus`, `PurchaseDate`, `Vendor`) VALUES
('EQ101', 'MNT-003', 'Treadmill Pro 5000', 'Operational', '2025-01-15', 'FitTech Supplies'),
('EQ102', 'MNT-003', 'Olympic Bench Press', 'Under Maintenance', '2024-06-10', 'Iron Grip Co.'),
('EQ103', 'MNT-004', 'Rowing Machine X', 'Under Maintenance', '2025-08-20', 'FitTech Supplies'),
('EQ104', 'MNT-004', 'Cable Crossover Maachine', 'Operationl', '2024-11-01', 'HeavyDuty Gym'),
('EQ105', 'MNT-003', 'Reformer Pilates Bed', 'Operational', '2025-05-12', 'CoreBalance Studio');

-- --------------------------------------------------------

--
-- Table structure for table `gym_member`
--

DROP TABLE IF EXISTS `gym_member`;
CREATE TABLE `gym_member` (
  `MemberID` varchar(15) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `DateOfBirth` date NOT NULL,
  `PhoneNumber` varchar(20) NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_member`
--

INSERT INTO `gym_member` (`MemberID`, `FirstName`, `LastName`, `DateOfBirth`, `PhoneNumber`, `is_archived`) VALUES
('MEM101', 'Wonho', 'Lee', '1993-03-01', '0917-342-8101', 0),
('MEM102', 'Bang Chan', 'Christopher', '1997-10-03', '0918-529-4102', 0),
('MEM103', 'Changbin', 'Seo', '1999-08-11', '0927-814-6103', 0),
('MEM104', 'Mingyu', 'Kim', '1997-04-06', '0919-603-9104', 0),
('MEM105', 'Jungkook', 'Jeon', '1997-09-01', '0945-771-2105', 0),
('MEM106', 'Minho', 'Choi', '1991-12-09', '0956-482-5106', 0),
('MEM107', 'San', 'Choi', '1999-07-10', '0920-915-7107', 0),
('MEM108', 'Baekho', 'Kang', '1995-07-21', '0966-308-4108', 0),
('MEM109', 'Shownu', 'Son', '1992-06-18', '0939-641-8109', 0),
('MEM110', 'Kazuha', 'Nakamura', '2003-08-09', '0977-259-3110', 0),
('MEM111', 'Jihyo', 'Park', '1997-02-01', '0995-830-6111', 0),
('MEM112', 'Solar', 'Kim', '1991-02-21', '0961-492-7112', 0),
('MEM113', 'Sejeong', 'Kim', '1996-08-28', '0998-714-2113', 0),
('MEM114', 'Soyou', 'Kang', '1992-02-12', '0915-368-9114', 0),
('MEM115', 'Yunjin', 'Huh', '2001-10-08', '0999-825-4115', 0);

--
-- Triggers `gym_member`
--
DROP TRIGGER IF EXISTS `trg_ArchiveMemberSubscription`;
DELIMITER $$
CREATE TRIGGER `trg_ArchiveMemberSubscription` AFTER UPDATE ON `gym_member` FOR EACH ROW BEGIN
	IF NEW.is_archived = 1 AND OLD.is_archived = 0 THEN
    	UPDATE subscription
        SET Status = 'Inactive'
        WHERE MemberID = New.MemberID;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `gym_staff`
--

DROP TABLE IF EXISTS `gym_staff`;
CREATE TABLE `gym_staff` (
  `StaffID` varchar(15) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `DateOfBirth` date NOT NULL,
  `StaffType` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_staff`
--

INSERT INTO `gym_staff` (`StaffID`, `FirstName`, `LastName`, `DateOfBirth`, `StaffType`) VALUES
('AD-001', 'Keith Nico', 'Carcueba', '2005-11-11', 'Admin'),
('AD-002', 'Kyle', 'Carcueba', '2000-01-01', 'Admin'),
('MNT-003', 'Yugyeom', 'Kim', '1997-11-17', 'Maintenance'),
('MNT-004', 'Mark', 'Tuan', '1993-09-04', 'Maintenance'),
('TRN-001', 'Sungjin', 'Park', '1993-01-16', 'Trainer'),
('TRN-002', 'Young K', 'Kang', '1993-12-19', 'Trainer'),
('TRN-005', 'Wonpil', 'Kim', '1994-04-28', 'Trainer'),
('TRN-006', 'BamBam', 'Kunpimook', '1997-05-02', 'Trainer'),
('TRN-007', 'Jackson', 'Wang', '1994-03-28', 'Trainer'),
('TRN-008', 'Kyle', 'Carcueba', '2011-02-16', 'Trainer');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `LoginID` int(11) NOT NULL,
  `StaffID` varchar(15) NOT NULL,
  `LoginDate` date NOT NULL,
  `LoginTime` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`LoginID`, `StaffID`, `LoginDate`, `LoginTime`) VALUES
(1, 'AD-001', '2026-11-19', '20:56:56'),
(2, 'AD-001', '2026-11-20', '06:57:27'),
(3, 'AD-001', '2026-11-20', '07:02:19'),
(4, 'AD-001', '2026-11-20', '07:08:20'),
(5, 'AD-001', '2026-11-20', '08:26:04'),
(6, 'AD-001', '2026-11-20', '08:27:19'),
(7, 'AD-001', '2026-11-20', '08:27:33'),
(8, 'AD-001', '2026-11-20', '10:02:45'),
(9, 'AD-001', '2026-11-20', '10:34:07'),
(10, 'AD-001', '2026-11-20', '12:56:15'),
(11, 'AD-001', '2026-11-20', '13:05:25'),
(12, 'AD-001', '2026-11-20', '13:08:45'),
(13, 'AD-001', '2026-11-20', '13:11:56'),
(14, 'AD-001', '2026-11-20', '13:30:17'),
(15, 'AD-001', '2026-11-20', '13:35:54'),
(17, 'AD-001', '2026-11-20', '17:00:46'),
(18, 'AD-001', '2026-11-20', '17:03:16'),
(19, 'AD-001', '2026-11-20', '20:34:25'),
(20, 'AD-001', '2026-11-21', '12:35:13'),
(21, 'AD-001', '2026-11-21', '16:11:50'),
(22, 'AD-001', '2026-11-21', '17:40:06'),
(23, 'AD-002', '2026-11-21', '17:43:50'),
(24, 'AD-001', '2026-11-21', '17:54:07'),
(25, 'AD-001', '2026-11-22', '17:16:10'),
(26, 'AD-001', '2026-11-22', '18:01:43'),
(27, 'AD-001', '2026-11-23', '14:51:44'),
(28, 'AD-001', '2026-11-23', '15:32:04');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

DROP TABLE IF EXISTS `maintenance`;
CREATE TABLE `maintenance` (
  `StaffID` varchar(15) NOT NULL,
  `WorkSchedule` varchar(150) NOT NULL,
  `AssignedTask` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`StaffID`, `WorkSchedule`, `AssignedTask`) VALUES
('MNT-003', 'Monday, Tuesday, Wednesday', 'Belt lubrication, bench upholstery & spring calibration; Treadmill motor diagnostics & safety key check'),
('MNT-004', 'Thursday, Friday, Saturday', 'Drive chain replacement & cable tension inspection');

-- --------------------------------------------------------

--
-- Table structure for table `membership_plan`
--

DROP TABLE IF EXISTS `membership_plan`;
CREATE TABLE `membership_plan` (
  `PlanID` varchar(15) NOT NULL,
  `PlanName` varchar(50) NOT NULL,
  `DurationDays` varchar(30) NOT NULL,
  `Price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plan`
--

INSERT INTO `membership_plan` (`PlanID`, `PlanName`, `DurationDays`, `Price`) VALUES
('PLN01', 'Monthly Standard', '30 Days', 1500.00),
('PLN02', 'Quarterly Basic', '90 Days', 4000.00),
('PLN03', 'Annual VIP', '365 Days', 14500.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `PaymentID` int(11) NOT NULL,
  `MemberID` varchar(15) NOT NULL,
  `StaffID` varchar(15) NOT NULL,
  `SubscriptionID` varchar(15) NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `PaymentDate` date NOT NULL,
  `PaymentTime` time NOT NULL,
  `PaymentMethod` varchar(30) NOT NULL,
  `ReferenceNumber` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`PaymentID`, `MemberID`, `StaffID`, `SubscriptionID`, `Amount`, `PaymentDate`, `PaymentTime`, `PaymentMethod`, `ReferenceNumber`) VALUES
(5001, 'MEM101', 'AD-001', 'SUB1001', 14500.00, '2026-08-11', '08:15:00', 'Credit Card', 'REF-20260801-001'),
(5002, 'MEM102', 'AD-001', 'SUB1002', 14500.00, '2026-09-05', '10:30:00', 'Credit Card', 'REF-20260905-002'),
(5003, 'MEM103', 'AD-001', 'SUB1003', 4000.00, '2026-10-10', '14:20:00', 'Debit Card', 'REF-20261010-003'),
(5004, 'MEM104', 'AD-001', 'SUB1004', 14500.00, '2026-06-12', '11:05:00', 'Credit Card', 'REF-20260612-004'),
(5005, 'MEM105', 'AD-001', 'SUB1005', 1500.00, '2026-11-01', '16:45:00', 'Cash', 'OR-20261101-005'),
(5006, 'MEM106', 'AD-001', 'SUB1006', 4000.00, '2026-09-15', '09:50:00', 'Credit Card', 'REF-20260915-006'),
(5007, 'MEM107', 'AD-001', 'SUB1007', 1500.00, '2026-11-03', '13:10:00', 'GCash', 'GC-9012837465'),
(5008, 'MEM108', 'AD-001', 'SUB1008', 14500.00, '2026-07-20', '15:00:00', 'Credit Card', 'REF-20260720-008'),
(5009, 'MEM109', 'AD-001', 'SUB1009', 1500.00, '2026-09-01', '12:30:00', 'Cash', 'OR-20260901-009'),
(5010, 'MEM110', 'AD-001', 'SUB1010', 14500.00, '2026-10-08', '09:00:00', 'Debit Card', 'REF-20261008-010'),
(5011, 'MEM111', 'AD-001', 'SUB1011', 4000.00, '2026-10-18', '17:15:00', 'Credit Card', 'REF-20261018-011'),
(5012, 'MEM112', 'AD-001', 'SUB1012', 14500.00, '2026-05-22', '10:40:00', 'Credit Card', 'REF-20260522-012'),
(5013, 'MEM113', 'AD-001', 'SUB1013', 1500.00, '2026-11-05', '11:25:00', 'Maya', 'MY-8473625190'),
(5014, 'MEM114', 'AD-001', 'SUB1014', 4000.00, '2026-09-25', '14:25:00', 'Debit Card', 'REF-20260925-014'),
(5015, 'MEM115', 'AD-001', 'SUB1015', 1500.00, '2026-11-10', '16:05:00', 'GCash', 'GC-7362519480'),
(5016, 'MEM109', 'AD-001', 'SUB1009', 1500.00, '2026-11-22', '17:57:56', 'Cash', 'OR-20261122-016');

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

DROP TABLE IF EXISTS `program`;
CREATE TABLE `program` (
  `ProgramID` varchar(15) NOT NULL,
  `StaffID` varchar(15) NOT NULL,
  `ProgramName` varchar(100) NOT NULL,
  `Schedule` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`ProgramID`, `StaffID`, `ProgramName`, `Schedule`) VALUES
('PRG201', 'TRN-007', 'Heavy Hypertrophy & Bulk', 'Mon/Wed/Fri 08:00 AM'),
('PRG202', 'TRN-005', 'Full-Body Strength & Conditioning', 'Tue/Thu 09:30 AM'),
('PRG203', 'TRN-002', 'Upper Body Power & Core', 'Mon/Wed 2:00 PM'),
('PRG204', 'TRN-006', 'Ballet COre & Pilates', 'Tue/Thu 10:00 AM'),
('PRG205', 'TRN-002', 'High-Intensity Dance Stamina', 'Wed/Fri 04:00 PM'),
('PRG206', 'TRN-005', 'Athletic Boxing & Cardio', 'Sat 11:00 AM'),
('PRG207', 'TRN-001', 'Funtional Kettlebell & Pole Prep', 'Mon/Thu 01:00 PM'),
('PRG208', 'TRN-001', 'Endurance & Calisthenics', 'Tue/Fri 03:30 PM');

-- --------------------------------------------------------

--
-- Table structure for table `progress`
--

DROP TABLE IF EXISTS `progress`;
CREATE TABLE `progress` (
  `RecordID` varchar(15) NOT NULL,
  `MemberID` varchar(15) NOT NULL,
  `Date` date NOT NULL,
  `Height` decimal(5,2) NOT NULL,
  `Weight` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `progress`
--

INSERT INTO `progress` (`RecordID`, `MemberID`, `Date`, `Height`, `Weight`) VALUES
('REC901', 'MEM101', '2026-08-01', 178.00, 74.00),
('REC902', 'MEM101', '2026-11-01', 178.00, 76.50),
('REC903', 'MEM102', '2026-09-05', 171.00, 68.50),
('REC904', 'MEM102', '2026-11-05', 171.00, 70.00),
('REC905', 'MEM104', '2026-06-12', 187.00, 78.00),
('REC906', 'MEM104', '2026-11-12', 187.00, 79.60),
('REC907', 'MEM110', '2026-10-08', 170.00, 52.00),
('REC908', 'MEM110', '2026-11-08', 170.00, 52.40),
('REC909', 'MEM111', '2026-10-18', 160.00, 49.50),
('REC910', 'MEM111', '2026-11-12', 159.99, 48.92);

-- --------------------------------------------------------

--
-- Table structure for table `subscription`
--

DROP TABLE IF EXISTS `subscription`;
CREATE TABLE `subscription` (
  `SubscriptionID` varchar(15) NOT NULL,
  `MemberID` varchar(15) NOT NULL,
  `PlanID` varchar(15) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `Status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription`
--

INSERT INTO `subscription` (`SubscriptionID`, `MemberID`, `PlanID`, `StartDate`, `EndDate`, `Status`) VALUES
('SUB1001', 'MEM101', 'PLN03', '2026-08-01', '2027-08-01', 'Active'),
('SUB1002', 'MEM102', 'PLN03', '2026-09-05', '2027-09-05', 'Active'),
('SUB1003', 'MEM103', 'PLN02', '2026-10-10', '2027-01-08', 'Active'),
('SUB1004', 'MEM104', 'PLN03', '2026-06-12', '2027-06-12', 'Active'),
('SUB1005', 'MEM105', 'PLN01', '2026-11-01', '2026-12-01', 'Active'),
('SUB1006', 'MEM106', 'PLN02', '2026-09-15', '2026-12-14', 'Active'),
('SUB1007', 'MEM107', 'PLN01', '2026-11-03', '2026-12-03', 'Active'),
('SUB1008', 'MEM108', 'PLN03', '2026-07-20', '2027-07-20', 'Active'),
('SUB1009', 'MEM109', 'PLN01', '2026-09-01', '2026-10-01', 'Active'),
('SUB1010', 'MEM110', 'PLN03', '2026-10-08', '2027-10-08', 'Active'),
('SUB1011', 'MEM111', 'PLN02', '2026-10-18', '2027-01-16', 'Active'),
('SUB1012', 'MEM112', 'PLN03', '2026-05-22', '2027-05-22', 'Active'),
('SUB1013', 'MEM113', 'PLN01', '2026-11-05', '2026-12-05', 'Active'),
('SUB1014', 'MEM114', 'PLN02', '2026-09-25', '2026-12-24', 'Active'),
('SUB1015', 'MEM115', 'PLN01', '2026-11-10', '2026-12-10', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `system_access`
--

DROP TABLE IF EXISTS `system_access`;
CREATE TABLE `system_access` (
  `StaffID` varchar(15) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `Role` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_access`
--

INSERT INTO `system_access` (`StaffID`, `Username`, `Password`, `Role`) VALUES
('AD-001', 'kit', '$2y$10$xB46m.k2b4ZJknfvEAl48ez43qP3K9POqdMIPwhJ5qDJY7TTzrDbu', 'Admin'),
('AD-002', 'admin', '$2y$10$/G2M8yuX7YRvT78sPY4wrOA9MGI2HwMZRNGpUia3iY/UBs4KCntea', 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `trainer`
--

DROP TABLE IF EXISTS `trainer`;
CREATE TABLE `trainer` (
  `StaffID` varchar(15) NOT NULL,
  `Certification` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trainer`
--

INSERT INTO `trainer` (`StaffID`, `Certification`) VALUES
('TRN-001', 'NASM Certified Personal Trainer'),
('TRN-002', 'ACE Master Fitness Specialist'),
('TRN-005', 'CSCS (Certified Strength & Conditioning Specialist'),
('TRN-006', 'PMA-CPT Certified Pilates Master'),
('TRN-007', 'IFBB Pro Coach & NSCA-CPT'),
('TRN-008', 'Certified Fitness Coach');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_member_subscriptions`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_member_subscriptions`;
CREATE TABLE `vw_member_subscriptions` (
`MemberID` varchar(15)
,`FirstName` varchar(50)
,`LastName` varchar(50)
,`PlanName` varchar(50)
,`Price` decimal(10,2)
,`StartDate` date
,`EndDate` date
,`Status` varchar(20)
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `application`
--
ALTER TABLE `application`
  ADD PRIMARY KEY (`ApplicationID`),
  ADD KEY `fk_application_member` (`MemberID`),
  ADD KEY `fk_application_program` (`ProgramID`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `fk_attendance_member` (`MemberID`);

--
-- Indexes for table `emergency_contact`
--
ALTER TABLE `emergency_contact`
  ADD PRIMARY KEY (`ContactID`),
  ADD KEY `fk_emergency_member` (`MemberID`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`EquipmentID`),
  ADD KEY `fk_equipment_staff` (`StaffID`);

--
-- Indexes for table `gym_member`
--
ALTER TABLE `gym_member`
  ADD PRIMARY KEY (`MemberID`);

--
-- Indexes for table `gym_staff`
--
ALTER TABLE `gym_staff`
  ADD PRIMARY KEY (`StaffID`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`LoginID`),
  ADD KEY `fk_login_history_staff` (`StaffID`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`StaffID`);

--
-- Indexes for table `membership_plan`
--
ALTER TABLE `membership_plan`
  ADD PRIMARY KEY (`PlanID`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `fk_payment_member` (`MemberID`),
  ADD KEY `fk_payment_staff` (`StaffID`),
  ADD KEY `fk_payment_subscription` (`SubscriptionID`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`ProgramID`),
  ADD KEY `fk_program_trainer` (`StaffID`);

--
-- Indexes for table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`RecordID`),
  ADD KEY `fk_progress_member` (`MemberID`);

--
-- Indexes for table `subscription`
--
ALTER TABLE `subscription`
  ADD PRIMARY KEY (`SubscriptionID`),
  ADD KEY `fk_subscription_member` (`MemberID`),
  ADD KEY `fk_subscription_plan` (`PlanID`);

--
-- Indexes for table `system_access`
--
ALTER TABLE `system_access`
  ADD PRIMARY KEY (`StaffID`);

--
-- Indexes for table `trainer`
--
ALTER TABLE `trainer`
  ADD PRIMARY KEY (`StaffID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `LoginID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5017;

-- --------------------------------------------------------

--
-- Structure for view `vw_member_subscriptions`
--
DROP TABLE IF EXISTS `vw_member_subscriptions`;

DROP VIEW IF EXISTS `vw_member_subscriptions`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_member_subscriptions`  AS SELECT `m`.`MemberID` AS `MemberID`, `m`.`FirstName` AS `FirstName`, `m`.`LastName` AS `LastName`, `p`.`PlanName` AS `PlanName`, `p`.`Price` AS `Price`, `s`.`StartDate` AS `StartDate`, `s`.`EndDate` AS `EndDate`, `s`.`Status` AS `Status` FROM ((`gym_member` `m` join `subscription` `s` on(`s`.`MemberID` = `m`.`MemberID`)) join `membership_plan` `p` on(`p`.`PlanID` = `s`.`PlanID`)) ORDER BY `p`.`Price` ASC, `m`.`MemberID` ASC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `application`
--
ALTER TABLE `application`
  ADD CONSTRAINT `fk_application_member` FOREIGN KEY (`MemberID`) REFERENCES `gym_member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_application_program` FOREIGN KEY (`ProgramID`) REFERENCES `program` (`ProgramID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_member` FOREIGN KEY (`MemberID`) REFERENCES `gym_member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `emergency_contact`
--
ALTER TABLE `emergency_contact`
  ADD CONSTRAINT `fk_emergency_member` FOREIGN KEY (`MemberID`) REFERENCES `gym_member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_equipment_staff` FOREIGN KEY (`StaffID`) REFERENCES `gym_staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `fk_login_history_staff` FOREIGN KEY (`StaffID`) REFERENCES `system_access` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `fk_maintenance_staff` FOREIGN KEY (`StaffID`) REFERENCES `gym_staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `fk_payment_member` FOREIGN KEY (`MemberID`) REFERENCES `gym_member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_staff` FOREIGN KEY (`StaffID`) REFERENCES `gym_staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_subscription` FOREIGN KEY (`SubscriptionID`) REFERENCES `subscription` (`SubscriptionID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `program`
--
ALTER TABLE `program`
  ADD CONSTRAINT `fk_program_trainer` FOREIGN KEY (`StaffID`) REFERENCES `trainer` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `progress`
--
ALTER TABLE `progress`
  ADD CONSTRAINT `fk_progress_member` FOREIGN KEY (`MemberID`) REFERENCES `gym_member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subscription`
--
ALTER TABLE `subscription`
  ADD CONSTRAINT `fk_subscription_member` FOREIGN KEY (`MemberID`) REFERENCES `gym_member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subscription_plan` FOREIGN KEY (`PlanID`) REFERENCES `membership_plan` (`PlanID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `system_access`
--
ALTER TABLE `system_access`
  ADD CONSTRAINT `fk_system_access_staff` FOREIGN KEY (`StaffID`) REFERENCES `gym_staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `trainer`
--
ALTER TABLE `trainer`
  ADD CONSTRAINT `fk_trainer_staff` FOREIGN KEY (`StaffID`) REFERENCES `gym_staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
