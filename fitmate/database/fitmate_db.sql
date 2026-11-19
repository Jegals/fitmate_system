-- Create and select the database
CREATE DATABASE IF NOT EXISTS `fitmate_db`;
USE `fitmate_db`;

-- --------------------------------------------------------
-- 1. GYM_STAFF
-- --------------------------------------------------------
CREATE TABLE `GYM_STAFF` (
  `StaffID` VARCHAR(15) NOT NULL,
  `FirstName` VARCHAR(50) NOT NULL,
  `LastName` VARCHAR(50) NOT NULL,
  `DateofBirth` DATE NOT NULL,
  `StaffType` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`StaffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `GYM_STAFF` (`StaffID`, `FirstName`, `LastName`, `DateofBirth`, `StaffType`) VALUES
('TRN-001', 'Sungjin', 'Park', '1993-01-16', 'Trainer'),
('TRN-002', 'Young K', 'Kang', '1993-12-19', 'Trainer'),
('MNT-003', 'Yugyeom', 'Kim', '1997-11-17', 'Maintenance'),
('MNT-004', 'Mark', 'Tuan', '1993-09-04', 'Maintenance'),
('TRN-005', 'Wonpil', 'Kim', '1994-04-28', 'Trainer'),
('TRN-006', 'BamBam', 'Kunpimook', '1997-05-02', 'Trainer'),
('TRN-007', 'Jackson', 'Wang', '1994-03-28', 'Trainer'),
('AD-001', 'Miko', 'Ocfemia', '1995-01-01', 'Admin');

-- --------------------------------------------------------
-- 2. SYSTEM_ACCESS
-- --------------------------------------------------------
CREATE TABLE `SYSTEM_ACCESS` (
  `StaffID` VARCHAR(15) NOT NULL,
  `Username` VARCHAR(50) NOT NULL,
  `Password` VARCHAR(255) NULL,
  `Role` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`StaffID`),
  CONSTRAINT `fk_system_access_staff` FOREIGN KEY (`StaffID`) REFERENCES `GYM_STAFF` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `SYSTEM_ACCESS` (`StaffID`, `Username`, `Password`, `Role`) VALUES
('AD-001', 'Mikonism', NULL, 'Admin');

-- --------------------------------------------------------
-- 3. LOGIN_HISTORY
-- --------------------------------------------------------
CREATE TABLE `LOGIN_HISTORY` (
  `LoginID` INT AUTO_INCREMENT NOT NULL,
  `StaffID` VARCHAR(15) NOT NULL,
  `LoginDate` DATE NOT NULL,
  `LoginTime` TIME NOT NULL,
  PRIMARY KEY (`LoginID`),
  CONSTRAINT `fk_login_history_staff` FOREIGN KEY (`StaffID`) REFERENCES `SYSTEM_ACCESS` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. TRAINER
-- --------------------------------------------------------
CREATE TABLE `TRAINER` (
  `StaffID` VARCHAR(15) NOT NULL,
  `Certification` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`StaffID`),
  CONSTRAINT `fk_trainer_staff` FOREIGN KEY (`StaffID`) REFERENCES `GYM_STAFF` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `TRAINER` (`StaffID`, `Certification`) VALUES
('TRN-001', 'NASM Certified Personal Trainer'),
('TRN-002', 'ACE Master Fitness Specialist'),
('TRN-005', 'CSCS (Certified Strength & Conditioning Specialist'),
('TRN-006', 'PMA-CPT Certified Pilates Master'),
('TRN-007', 'IFBB Pro Coach & NSCA-CPT');

-- --------------------------------------------------------
-- 5. MAINTENANCE
-- --------------------------------------------------------
CREATE TABLE `MAINTENANCE` (
  `StaffID` VARCHAR(15) NOT NULL,
  `WorkSchedule` VARCHAR(150) NOT NULL,
  `AssignedTask` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`StaffID`),
  CONSTRAINT `fk_maintenance_staff` FOREIGN KEY (`StaffID`) REFERENCES `GYM_STAFF` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `MAINTENANCE` (`StaffID`, `WorkSchedule`, `AssignedTask`) VALUES
('MNT-003', 'Monday, Tuesday, Wednesday / Sunday, Monday,Wednesday', 'Belt lubrication, bench upholstery & spring calibration; Treadmill motor diagnostics & safety key check'),
('MNT-004', 'Thursday, Friday, Saturday', 'Drive chain replacement & cable tension inspection');

-- --------------------------------------------------------
-- 6. PROGRAM
-- --------------------------------------------------------
CREATE TABLE `PROGRAM` (
  `ProgramID` VARCHAR(15) NOT NULL,
  `StaffID` VARCHAR(15) NOT NULL,
  `ProgramName` VARCHAR(100) NOT NULL,
  `Schedule` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`ProgramID`),
  CONSTRAINT `fk_program_trainer` FOREIGN KEY (`StaffID`) REFERENCES `TRAINER` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `PROGRAM` (`ProgramID`, `StaffID`, `ProgramName`, `Schedule`) VALUES
('PRG201', 'TRN-007', 'Heavy Hypertrophy & Bulk', 'Mon/Wed/Fri 08:00 AM'),
('PRG202', 'TRN-005', 'Full-Body Strength & Conditioning', 'Tue/Thu 09:30 AM'),
('PRG203', 'TRN-002', 'Upper Body Power & Core', 'Mon/Wed 2:00 PM'),
('PRG204', 'TRN-006', 'Ballet COre & Pilates', 'Tue/Thu 10:00 AM'),
('PRG205', 'TRN-002', 'High-Intensity Dance Stamina', 'Wed/Fri 04:00 PM'),
('PRG206', 'TRN-005', 'Athletic Boxing & Cardio', 'Sat 11:00 AM'),
('PRG207', 'TRN-001', 'Funtional Kettlebell & Pole Prep', 'Mon/Thu 01:00 PM'),
('PRG208', 'TRN-001', 'Endurance & Calisthenics', 'Tue/Fri 03:30 PM');

-- --------------------------------------------------------
-- 7. EQUIPMENT
-- --------------------------------------------------------
CREATE TABLE `EQUIPMENT` (
  `EquipmentID` VARCHAR(15) NOT NULL,
  `StaffID` VARCHAR(15) NOT NULL,
  `EquipmentName` VARCHAR(100) NOT NULL,
  `EquipmentStatus` VARCHAR(50) NOT NULL,
  `PurchaseDate` DATE NOT NULL,
  `Vendor` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`EquipmentID`),
  CONSTRAINT `fk_equipment_staff` FOREIGN KEY (`StaffID`) REFERENCES `GYM_STAFF` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `EQUIPMENT` (`EquipmentID`, `StaffID`, `EquipmentName`, `EquipmentStatus`, `PurchaseDate`, `Vendor`) VALUES
('EQ101', 'MNT-003', 'Treadmill Pro 5000', 'Operational', '2025-01-15', 'FitTech Supplies'),
('EQ102', 'MNT-003', 'Olympic Bench Press', 'Under Maintenance', '2024-06-10', 'Iron Grip Co.'),
('EQ103', 'MNT-004', 'Rowing Machine X', 'Under Maintenance', '2025-08-20', 'FitTech Supplies'),
('EQ104', 'MNT-004', 'Cable Crossover Maachine', 'Operationl', '2024-11-01', 'HeavyDuty Gym'),
('EQ105', 'MNT-003', 'Reformer Pilates Bed', 'Operational', '2025-05-12', 'CoreBalance Studio');

-- --------------------------------------------------------
-- 8. MEMBERSHIP_PLAN
-- --------------------------------------------------------
CREATE TABLE `MEMBERSHIP_PLAN` (
  `PlanID` VARCHAR(15) NOT NULL,
  `PlanName` VARCHAR(50) NOT NULL,
  `DurationDays` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`PlanID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `MEMBERSHIP_PLAN` (`PlanID`, `PlanName`, `DurationDays`) VALUES
('PLN01', 'Monthly Standard', '30 Days'),
('PLN02', 'Quarterly Basic', '90 Days'),
('PLN03', 'Annual VIP', '365 Days');

-- --------------------------------------------------------
-- 9. GYM_MEMBER
-- --------------------------------------------------------
CREATE TABLE `GYM_MEMBER` (
  `MemberID` VARCHAR(15) NOT NULL,
  `FirstName` VARCHAR(50) NOT NULL,
  `LastName` VARCHAR(50) NOT NULL,
  `DateofBirth` DATE NOT NULL,
  `Phone` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`MemberID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `GYM_MEMBER` (`MemberID`, `FirstName`, `LastName`, `DateofBirth`, `Phone`) VALUES
('MEM101', 'Wonho', 'Lee', '1993-03-01', '0917-342-8101'),
('MEM102', 'Bang Chan', 'Christopher', '1997-10-03', '0918-529-4102'),
('MEM103', 'Changbin', 'Seo', '1999-08-11', '0927-814-6103'),
('MEM104', 'Mingyu', 'Kim', '1997-04-06', '0919-603-9104'),
('MEM105', 'Jungkook', 'Jeon', '1997-09-01', '0945-771-2105'),
('MEM106', 'Minho', 'Choi', '1991-12-09', '0956-482-5106'),
('MEM107', 'San', 'Choi', '1999-07-10', '0920-915-7107'),
('MEM108', 'Baekho', 'Kang', '1995-07-21', '0966-308-4108'),
('MEM109', 'Shownu', 'Son', '1992-06-18', '0939-641-8109'),
('MEM110', 'Kazuha', 'Nakamura', '2003-08-09', '0977-259-3110'),
('MEM111', 'Jihyo', 'Park', '1997-02-01', '0995-830-6111'),
('MEM112', 'Solar', 'Kim', '1991-02-21', '0961-492-7112'),
('MEM113', 'Sejeong', 'Kim', '1996-08-28', '0998-714-2113'),
('MEM114', 'Soyou', 'Kang', '1992-02-12', '0915-368-9114'),
('MEM115', 'Yunjin', 'Huh', '2001-10-08', '0999-825-4115');

-- --------------------------------------------------------
-- 10. SUBSCRIPTION
-- --------------------------------------------------------
CREATE TABLE `SUBSCRIPTION` (
  `SubscriptionID` VARCHAR(15) NOT NULL,
  `MemberID` VARCHAR(15) NOT NULL,
  `PlanID` VARCHAR(15) NOT NULL,
  `StartDate` DATE NOT NULL,
  `EndDate` DATE NOT NULL,
  `Status` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`SubscriptionID`),
  CONSTRAINT `fk_subscription_member` FOREIGN KEY (`MemberID`) REFERENCES `GYM_MEMBER` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_plan` FOREIGN KEY (`PlanID`) REFERENCES `MEMBERSHIP_PLAN` (`PlanID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `SUBSCRIPTION` (`SubscriptionID`, `MemberID`, `PlanID`, `StartDate`, `EndDate`, `Status`) VALUES
('SUB1001', 'MEM101', 'PLN03', '2026-08-01', '2027-08-01', 'Active'),
('SUB1002', 'MEM102', 'PLN03', '2026-09-05', '2027-09-05', 'Active'),
('SUB1003', 'MEM103', 'PLN02', '2026-10-10', '2027-01-08', 'Active'),
('SUB1004', 'MEM104', 'PLN03', '2026-06-12', '2027-06-12', 'Active'),
('SUB1005', 'MEM105', 'PLN01', '2026-11-01', '2026-12-01', 'Active'),
('SUB1006', 'MEM106', 'PLN02', '2026-09-15', '2026-12-14', 'Active'),
('SUB1007', 'MEM107', 'PLN01', '2026-11-03', '2026-12-03', 'Active'),
('SUB1008', 'MEM108', 'PLN03', '2026-07-20', '2027-07-20', 'Active'),
('SUB1009', 'MEM109', 'PLN01', '2026-09-01', '2026-10-01', 'Expired'),
('SUB1010', 'MEM110', 'PLN03', '2026-10-08', '2027-10-08', 'Active'),
('SUB1011', 'MEM111', 'PLN02', '2026-10-18', '2027-01-16', 'Active'),
('SUB1012', 'MEM112', 'PLN03', '2026-05-22', '2027-05-22', 'Active'),
('SUB1013', 'MEM113', 'PLN01', '2026-11-05', '2026-12-05', 'Active'),
('SUB1014', 'MEM114', 'PLN02', '2026-09-25', '2026-12-24', 'Active'),
('SUB1015', 'MEM115', 'PLN01', '2026-11-10', '2026-12-10', 'Active');

-- --------------------------------------------------------
-- 11. PAYMENT
-- --------------------------------------------------------
CREATE TABLE `PAYMENT` (
  `PaymentID` INT NOT NULL,
  `MemberID` VARCHAR(15) NOT NULL,
  `StaffID` VARCHAR(15) NOT NULL,
  `SubscriptionID` VARCHAR(15) NOT NULL,
  `Amount` DECIMAL(10, 2) NOT NULL,
  `PaymentDate` DATE NOT NULL,
  `PaymentTime` TIME NOT NULL,
  `PaymentMethod` VARCHAR(30) NOT NULL,
  `ReferenceNumber` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`PaymentID`),
  CONSTRAINT `fk_payment_member` FOREIGN KEY (`MemberID`) REFERENCES `GYM_MEMBER` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_staff` FOREIGN KEY (`StaffID`) REFERENCES `GYM_STAFF` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_subscription` FOREIGN KEY (`SubscriptionID`) REFERENCES `SUBSCRIPTION` (`SubscriptionID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `PAYMENT` (`PaymentID`, `MemberID`, `StaffID`, `SubscriptionID`, `Amount`, `PaymentDate`, `PaymentTime`, `PaymentMethod`, `ReferenceNumber`) VALUES
(5001, 'MEM101', 'TRN-001', 'SUB1001', 14500.00, '2026-08-11', '08:15:00', 'Credit Card', 'REF-20260801-001'),
(5002, 'MEM102', 'TRN-002', 'SUB1002', 14500.00, '2026-09-05', '10:30:00', 'Credit Card', 'REF-20260905-002'),
(5003, 'MEM103', 'TRN-002', 'SUB1003', 4000.00, '2026-10-10', '14:20:00', 'Debit Card', 'REF-20261010-003'),
(5004, 'MEM104', 'TRN-005', 'SUB1004', 14500.00, '2026-06-12', '11:05:00', 'Credit Card', 'REF-20260612-004'),
(5005, 'MEM105', 'TRN-002', 'SUB1005', 1500.00, '2026-11-01', '16:45:00', 'Cash', 'OR-20261101-005'),
(5006, 'MEM106', 'TRN-006', 'SUB1006', 4000.00, '2026-09-15', '09:50:00', 'Credit Card', 'REF-20260915-006'),
(5007, 'MEM107', 'TRN-001', 'SUB1007', 1500.00, '2026-11-03', '13:10:00', 'GCash', 'GC-9012837465'),
(5008, 'MEM108', 'TRN-007', 'SUB1008', 14500.00, '2026-07-20', '15:00:00', 'Credit Card', 'REF-20260720-008'),
(5009, 'MEM109', 'TRN-002', 'SUB1009', 1500.00, '2026-09-01', '12:30:00', 'Cash', 'OR-20260901-009'),
(5010, 'MEM110', 'TRN-006', 'SUB1010', 14500.00, '2026-10-08', '09:00:00', 'Debit Card', 'REF-20261008-010'),
(5011, 'MEM111', 'TRN-005', 'SUB1011', 4000.00, '2026-10-18', '17:15:00', 'Credit Card', 'REF-20261018-011'),
(5012, 'MEM112', 'TRN-001', 'SUB1012', 14500.00, '2026-05-22', '10:40:00', 'Credit Card', 'REF-20260522-012'),
(5013, 'MEM113', 'TRN-007', 'SUB1013', 1500.00, '2026-11-05', '11:25:00', 'Maya', 'MY-8473625190'),
(5014, 'MEM114', 'TRN-006', 'SUB1014', 4000.00, '2026-09-25', '14:25:00', 'Debit Card', 'REF-20260925-014'),
(5015, 'MEM115', 'TRN-002', 'SUB1015', 1500.00, '2026-11-10', '16:05:00', 'GCash', 'GC-7362519480');

-- --------------------------------------------------------
-- 12. APPLICATION
-- --------------------------------------------------------
CREATE TABLE `APPLICATION` (
  `ApplicationID` VARCHAR(15) NOT NULL,
  `MemberID` VARCHAR(15) NOT NULL,
  `ProgramID` VARCHAR(15) NOT NULL,
  `ApplicationDate` DATE NOT NULL,
  `Status` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`ApplicationID`),
  CONSTRAINT `fk_application_member` FOREIGN KEY (`MemberID`) REFERENCES `GYM_MEMBER` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_program` FOREIGN KEY (`ProgramID`) REFERENCES `PROGRAM` (`ProgramID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `APPLICATION` (`ApplicationID`, `MemberID`, `ProgramID`, `ApplicationDate`, `Status`) VALUES
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
('APP315', 'MEM115', 'PRG204', '2026-11-10', 'Pending');

-- --------------------------------------------------------
-- 13. ATTENDANCE
-- --------------------------------------------------------
CREATE TABLE `ATTENDANCE` (
  `AttendanceID` VARCHAR(15) NOT NULL,
  `MemberID` VARCHAR(15) NOT NULL,
  `Date` DATE NOT NULL,
  `TimeIn` TIME NOT NULL,
  `TimeOut` TIME NOT NULL,
  PRIMARY KEY (`AttendanceID`),
  CONSTRAINT `fk_attendance_member` FOREIGN KEY (`MemberID`) REFERENCES `GYM_MEMBER` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ATTENDANCE` (`AttendanceID`, `MemberID`, `Date`, `TimeIn`, `TimeOut`) VALUES
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
-- 14. PROGRESS
-- --------------------------------------------------------
CREATE TABLE `PROGRESS` (
  `RecordID` VARCHAR(15) NOT NULL,
  `MemberID` VARCHAR(15) NOT NULL,
  `Date` DATE NOT NULL,
  `Height` DECIMAL(5, 2) NOT NULL,
  `Weight` DECIMAL(5, 2) NOT NULL,
  PRIMARY KEY (`RecordID`),
  CONSTRAINT `fk_progress_member` FOREIGN KEY (`MemberID`) REFERENCES `GYM_MEMBER` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `PROGRESS` (`RecordID`, `MemberID`, `Date`, `Height`, `Weight`) VALUES
('REC901', 'MEM101', '2026-08-01', 178, 74.0),
('REC902', 'MEM101', '2026-11-01', 178, 76.5),
('REC903', 'MEM102', '2026-09-05', 171, 68.5),
('REC904', 'MEM102', '2026-11-05', 171, 70.0),
('REC905', 'MEM104', '2026-06-12', 187, 78.0),
('REC906', 'MEM104', '2026-11-12', 187, 79.6),
('REC907', 'MEM110', '2026-10-08', 170, 52.0),
('REC908', 'MEM110', '2026-11-08', 170, 52.4),
('REC909', 'MEM111', '2026-10-18', 160, 49.5),
('REC910', 'MEM111', '2026-11-12', 160, 48.9);

-- --------------------------------------------------------
-- 15. EMERGENCY_CONTACT
-- --------------------------------------------------------
CREATE TABLE `EMERGENCY_CONTACT` (
  `ContactID` VARCHAR(15) NOT NULL,
  `MemberID` VARCHAR(15) NOT NULL,
  `ContactName` VARCHAR(100) NOT NULL,
  `Relationship` VARCHAR(50) NOT NULL,
  `PhoneNumber` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`ContactID`),
  CONSTRAINT `fk_emergency_member` FOREIGN KEY (`MemberID`) REFERENCES `GYM_MEMBER` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `EMERGENCY_CONTACT` (`ContactID`, `MemberID`, `ContactName`, `Relationship`, `PhoneNumber`) VALUES
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


