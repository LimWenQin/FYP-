-- phpMyAdmin SQL Dump
-- Combined Version: donation_system.sql (Latest Data) + Clean Reset Logic
-- Generation Time: Dec 15, 2025
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0; -- 临时关闭外键检查以顺利删除/创建表

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `donation_system`
--

-- --------------------------------------------------------
-- ⚠️ 清理旧表 (来源: donation_system 4 logic, 补充了所有新表)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff_activity`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `redemption_order`;
DROP TABLE IF EXISTS `recurring_donation`;
DROP TABLE IF EXISTS `receipt`;
DROP TABLE IF EXISTS `point`;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `notifications`; -- 新版独有
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `item_donation`;
DROP TABLE IF EXISTS `headquarters`;
DROP TABLE IF EXISTS `donor_achievement`;
DROP TABLE IF EXISTS `donor`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `branch`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `activity`;
DROP TABLE IF EXISTS `achievement`;
DROP TABLE IF EXISTS `special_case`;
DROP TABLE IF EXISTS `reward_item`;
DROP TABLE IF EXISTS `story`;

-- --------------------------------------------------------

--
-- Table structure for table `achievement`
--

CREATE TABLE `achievement` (
  `Achievement_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Achievement_Name` varchar(100) NOT NULL,
  `Achievement_Description` text NOT NULL,
  `Achievement_PointRequired` int(11) NOT NULL,
  `Achievement_Image` varchar(255) NOT NULL,
  PRIMARY KEY (`Achievement_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `Activity_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Activity_Name` varchar(255) NOT NULL DEFAULT 'Default Activity Name',
  `Activity_Date` date DEFAULT NULL,
  `Activity_StartDate` date DEFAULT NULL,
  `Activity_EndDate` date DEFAULT NULL,
  `Activity_Details` text NOT NULL,
  `Activity_Picture` varchar(255) DEFAULT NULL,
  `Activity_Status` varchar(50) NOT NULL,
  `Activity_GetAmount` decimal(10,2) NOT NULL,
  `Activity_TargetAmount` decimal(10,2) DEFAULT 0.00,
  `Activity_Address1` varchar(255) NOT NULL,
  `Activity_Address2` varchar(255) DEFAULT NULL,
  `Activity_Address3` varchar(255) DEFAULT NULL,
  `Activity_City` varchar(100) NOT NULL,
  `Activity_State` varchar(100) NOT NULL,
  `Activity_PostalCode` varchar(20) NOT NULL,
  `Activity_Country` varchar(100) DEFAULT 'Malaysia',
  `Branch_ID` int(11) NOT NULL,
  PRIMARY KEY (`Activity_ID`),
  KEY `Activity_BrANCH_ID_FK` (`Branch_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity`
--

INSERT INTO `activity` (`Activity_ID`, `Activity_Name`, `Activity_Date`, `Activity_StartDate`, `Activity_EndDate`, `Activity_Details`, `Activity_Picture`, `Activity_Status`, `Activity_GetAmount`, `Activity_TargetAmount`, `Activity_Address1`, `Activity_Address2`, `Activity_Address3`, `Activity_City`, `Activity_State`, `Activity_PostalCode`, `Activity_Country`, `Branch_ID`) VALUES
(1, 'Charity Fun Run 2025', NULL, '2025-12-20', '2025-12-25', 'A 5km run to raise funds for local orphanages. Join us for a healthy morning!', NULL, 'Active', 12500.00, 50000.00, 'Dataran Merdeka', NULL, NULL, 'Kuala Lumpur', 'Kuala Lumpur', '50050', 'Malaysia', 1),
(2, 'Beach Cleanup Drive', NULL, '2025-11-01', '2025-11-02', 'Cleaning up Batu Ferringhi beach to protect marine life. Volunteers needed.', NULL, 'Completed', 5000.00, 5000.00, 'Batu Ferringhi Public Beach', NULL, NULL, 'Batu Ferringhi', 'Penang', '11100', 'Malaysia', 2),
(3, 'Soup Kitchen Weekly', NULL, '2026-01-10', '2026-01-10', 'Distributing warm meals to the homeless community in Chow Kit area.', NULL, 'Active', 0.00, 2000.00, 'Jalan Chow Kit', NULL, NULL, 'Kuala Lumpur', 'Kuala Lumpur', '50300', 'Malaysia', 1),
(4, 'Flood Relief Mission', NULL, '2025-10-15', '2025-10-20', 'Emergency relief for flood victims in the East Coast. Collecting dry food and clothes.', NULL, 'Completed', 85000.00, 100000.00, 'Community Hall', NULL, NULL, 'Kuantan', 'Pahang', '25000', 'Malaysia', 1),
(5, 'Senior Care Visit', NULL, '2025-12-15', '2025-12-18', 'Spending time with the elderly, playing games, and providing medical checkups.', NULL, 'Active', 4500.00, 8000.00, 'Rumah Kasih Sayang', NULL, NULL, 'Petaling Jaya', 'Selangor', '46000', 'Malaysia', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Admin_Name` varchar(100) NOT NULL,
  `Admin_ContactNumber` varchar(20) NOT NULL,
  `Admin_ICNUMBER` varchar(20) NOT NULL,
  `Admin_Email` varchar(100) NOT NULL,
  `Admin_Password` varchar(255) NOT NULL,
  `Admin_DOB` date NOT NULL,
  `Admin_Address1` varchar(255) NOT NULL,
  `Admin_Address2` varchar(255) DEFAULT NULL,
  `Admin_Address3` varchar(255) DEFAULT NULL,
  `Admin_City` varchar(100) NOT NULL,
  `Admin_State` varchar(100) NOT NULL,
  `Admin_PostalCode` varchar(20) NOT NULL,
  `Admin_Country` varchar(100) DEFAULT 'Malaysia',
  `Admin_ProfilePicture` varchar(255) DEFAULT NULL,
  `Admin_Role` enum('Super Admin','Admin') NOT NULL DEFAULT 'Admin',
  `Admin_Status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Active',
  `Admin_LastLogin` datetime DEFAULT NULL,
  `Admin_CreatedAt` datetime DEFAULT current_timestamp(),
  `Admin_UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Admin_Comment` text NOT NULL,
  `Admin_LoginAttempts` int(11) DEFAULT 0,
  `Admin_LastFailedLogin` datetime DEFAULT NULL,
  PRIMARY KEY (`Admin_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`Admin_ID`, `Admin_Name`, `Admin_ContactNumber`, `Admin_ICNUMBER`, `Admin_Email`, `Admin_Password`, `Admin_DOB`, `Admin_Address1`, `Admin_Address2`, `Admin_Address3`, `Admin_City`, `Admin_State`, `Admin_PostalCode`, `Admin_Country`, `Admin_ProfilePicture`, `Admin_Role`, `Admin_Status`, `Admin_LastLogin`, `Admin_CreatedAt`, `Admin_UpdatedAt`, `Admin_Comment`, `Admin_LoginAttempts`, `Admin_LastFailedLogin`) VALUES
(1, 'Super Admin', '0123456789', '990101010101', 'admin@lovebridge.org.my', 'admin123', '1999-01-01', 'Level 12', 'Menara Love Bridge', 'Jalan Charity', 'Kuala Lumpur', 'Wilayah Persekutuan', '50000', 'Malaysia', NULL, 'Super Admin', 'Active', '2025-12-06 20:35:49', '2025-11-28 12:00:00', '2025-12-10 21:15:44', 'System Super Administrator', 5, '2025-12-10 21:15:44'),
(2, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', '$2y$10$bL3GDvE//gV.k149JNo6rejq/rwqubykoY3eMULa9vrndOYWFMjq.', '2003-05-17', '11， jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', 'uploads/admins/admin_1765013470_6933f7de52098.jpg', 'Super Admin', 'Active', '2025-12-14 00:10:24', '2025-12-06 17:31:10', '2025-12-14 00:10:24', 'Added via admin management system', 0, '2025-12-11 11:35:09'),
(3, 'Tan Choo Yee', '+6019-9878299', '010203-05-1234', 'ujintan218@gmail.com', '$2y$10$5zA9gCym9VbjQDdR0XcxK.de.K7M8mtKAh92m7r2IgOsJ..2Z2MVa', '2001-02-03', 'Blk 914Jurong West Street 91', 'taman bukit beruang utama', '', 'Melaka', 'Melaka', '75450', 'Malaysia', NULL, 'Admin', 'Active', '2025-12-10 21:17:38', '2025-12-07 20:35:18', '2025-12-10 21:17:38', 'Added via admin management system', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branch`
--

CREATE TABLE `branch` (
  `Branch_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Branch_Name` varchar(100) NOT NULL,
  `Branch_Type` varchar(50) NOT NULL,
  `Branch_Address1` varchar(255) NOT NULL,
  `Branch_Address2` varchar(255) DEFAULT NULL,
  `Branch_Address3` varchar(255) DEFAULT NULL,
  `Branch_City` varchar(100) NOT NULL,
  `Branch_State` varchar(100) NOT NULL,
  `Branch_PostalCode` varchar(20) NOT NULL,
  `Branch_Country` varchar(100) DEFAULT 'Malaysia',
  `Branch_ContactNumber` varchar(20) NOT NULL,
  `Branch_Description` text NOT NULL,
  `Admin_ID` int(11) NOT NULL,
  `Branch_ProfilePicture` varchar(255) DEFAULT NULL,
  `Branch_OperationalStatus` enum('Open','Closed') DEFAULT 'Open',
  `Branch_TargetAmount` decimal(10,2) DEFAULT 10000.00,
  PRIMARY KEY (`Branch_ID`),
  KEY `branch_admin_id_fk` (`Admin_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branch`
--

INSERT INTO `branch` (`Branch_ID`, `Branch_Name`, `Branch_Type`, `Branch_Address1`, `Branch_Address2`, `Branch_Address3`, `Branch_City`, `Branch_State`, `Branch_PostalCode`, `Branch_Country`, `Branch_ContactNumber`, `Branch_Description`, `Admin_ID`, `Branch_ProfilePicture`, `Branch_OperationalStatus`, `Branch_TargetAmount`) VALUES
(1, 'fourace', 'Orphanage', 'Blk 914Jurong West Street 91', '', '', 'Skudai', 'Johor', '81300', 'Malaysia', '+6011-1190233', '', 2, NULL, 'Open', 200000.00),
(2, 'KL Main Branch', 'Headquarters', 'Level 12, Menara Love Bridge', NULL, NULL, 'Kuala Lumpur', 'Kuala Lumpur', '50450', 'Malaysia', '03-12345678', 'Main operating center for central region.', 1, NULL, 'Open', 10000.00),
(3, 'Penang Outreach', 'Regional Center', '15, Jalan Georgetown', NULL, NULL, 'Georgetown', 'Penang', '10200', 'Malaysia', '04-2223333', 'Northern region support center.', 1, NULL, 'Open', 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `Contact_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Subject` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `Status` enum('New','Read','Replied') NOT NULL DEFAULT 'New',
  `Created_At` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Contact_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donor`
--

CREATE TABLE `donor` (
  `Donor_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Donor_Name` varchar(100) NOT NULL,
  `Donor_ContactNumber` varchar(20) NOT NULL,
  `Donor_ICNumber` varchar(20) NOT NULL,
  `Donor_Email` varchar(100) NOT NULL,
  `Donor_Password` varchar(255) NOT NULL,
  `Donor_Address1` varchar(255) NOT NULL,
  `Donor_Address2` varchar(255) DEFAULT NULL,
  `Donor_Address3` varchar(255) DEFAULT NULL,
  `Donor_City` varchar(100) NOT NULL,
  `Donor_State` varchar(100) NOT NULL,
  `Donor_PostalCode` varchar(20) NOT NULL,
  `Donor_Country` varchar(100) DEFAULT 'Malaysia',
  `Donor_DOB` date NOT NULL,
  `Donor_Description` text NOT NULL,
  `Donor_RegisteredAt` datetime DEFAULT current_timestamp(),
  `Donor_ProfilePicture` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`Donor_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donor`
--

INSERT INTO `donor` (`Donor_ID`, `Donor_Name`, `Donor_ContactNumber`, `Donor_ICNumber`, `Donor_Email`, `Donor_Password`, `Donor_Address1`, `Donor_Address2`, `Donor_Address3`, `Donor_City`, `Donor_State`, `Donor_PostalCode`, `Donor_Country`, `Donor_DOB`, `Donor_Description`, `Donor_RegisteredAt`, `Donor_ProfilePicture`) VALUES
(1, 'Ronald Tan Bin Hong', '0123456789', '050404011183', 'ronaldtan0404@gmail.com', 'Rd2535@T', 'No19, Jalan Melawati', 'Taman Melawati', NULL, 'Kuala Lumpur', 'Wilayah Persekutuan', '53100', 'Malaysia', '2005-04-04', 'Newly registered donor', '2025-12-06 08:56:24', NULL),
(2, 'RONALD TAN BIN HONG', '012-7212535', '030404011183', 'abc@gmail.com', '$2y$10$3hsuDLLcdM4mr9bqhvJekOZqH80/6aF.7GR2vLxXHbcgWT.RjOe.O', 'No19.jalan melawati 19, taman melawati', '', '', 'Johor Bahru', 'Johor', '81300', 'Malaysia', '2003-04-04', '', '2025-12-15 22:27:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `donor_achievement`
--

CREATE TABLE `donor_achievement` (
  `DonorAchievement_ID` int(11) NOT NULL AUTO_INCREMENT,
  `DonorAchievement_AchievedAt` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Achievement_ID` int(11) NOT NULL,
  PRIMARY KEY (`DonorAchievement_ID`),
  KEY `DonorAchievement_Donor_ID_FK` (`Donor_ID`),
  KEY `DonorAchievement_Achievement_ID_FK` (`Achievement_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `headquarters`
--

CREATE TABLE `headquarters` (
  `HQ_ID` int(11) NOT NULL AUTO_INCREMENT,
  `HQ_Name` varchar(100) NOT NULL DEFAULT 'Love Bridge Foundation',
  `HQ_ContactNumber` varchar(30) NOT NULL,
  `HQ_Email` varchar(100) NOT NULL,
  `HQ_Address` text NOT NULL,
  `HQ_Description` text NOT NULL,
  `HQ_Story` longtext NOT NULL,
  `HQ_FoundingDate` date DEFAULT NULL,
  `HQ_Image` varchar(255) DEFAULT NULL,
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`HQ_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `headquarters`
--

INSERT INTO `headquarters` (`HQ_ID`, `HQ_Name`, `HQ_ContactNumber`, `HQ_Email`, `HQ_Address`, `HQ_Description`, `HQ_Story`, `HQ_FoundingDate`, `HQ_Image`, `Updated_At`) VALUES
(1, 'Love Bridge Headquarters', '+603-1234 5678', 'info@lovebridge.org.my', 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur, Malaysia', 'Love Bridge is a non-profit organization dedicated to helping those in need.', 'Founded in 2010, Love Bridge started with a small group of volunteers who wanted to make a difference. Over the years, we have grown into a nationwide organization supporting various causes including elderly care, orphanages, and disaster relief.', '2010-01-01', NULL, '2025-11-26 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `item_donation`
--

CREATE TABLE `item_donation` (
  `Item_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Item_Name` varchar(100) NOT NULL,
  `Item_Quantity` int(11) NOT NULL,
  `Item_Condition` varchar(50) NOT NULL,
  `Item_Description` text NOT NULL,
  `Item_PhotoPath` varchar(255) NOT NULL,
  `Item_DropOff_Method` varchar(50) NOT NULL,
  `Item_Pickup_Address1` varchar(255) DEFAULT NULL,
  `Item_Pickup_Address2` varchar(255) DEFAULT NULL,
  `Item_Pickup_Address3` varchar(255) DEFAULT NULL,
  `Item_Pickup_City` varchar(100) DEFAULT NULL,
  `Item_Pickup_State` varchar(100) DEFAULT NULL,
  `Item_Pickup_PostalCode` varchar(20) DEFAULT NULL,
  `Item_Pickup_Country` varchar(100) DEFAULT 'Malaysia',
  `Item_Status` varchar(50) NOT NULL,
  `Item_ReceivedBy` varchar(100) NOT NULL,
  `Item_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Activity_ID` int(11) NOT NULL,
  PRIMARY KEY (`Item_ID`),
  KEY `Item_Donor_ID_FK` (`Donor_ID`),
  KEY `Item_Activity_ID_FK` (`Activity_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL,
  `status` enum('failed','locked') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_time` (`email`,`attempt_time`),
  KEY `idx_ip_time` (`ip_address`,`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `Notification_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Donor_ID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `Is_Read` tinyint(1) DEFAULT 0,
  `Created_At` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Notification_ID`),
  KEY `Notification_Donor_ID_FK` (`Donor_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `Order_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Order_Name` varchar(100) NOT NULL,
  `Order_ContactNumber` varchar(20) NOT NULL,
  `Order_ICNumber` varchar(20) NOT NULL,
  `Order_Email` varchar(100) NOT NULL,
  `Order_Amount` decimal(10,2) NOT NULL,
  `Order_Points_Earned` int(11) DEFAULT 0,
  `Order_Currency` varchar(10) NOT NULL,
  `Order_PaymentMethod` varchar(50) NOT NULL,
  `Order_PaymentStatus` varchar(50) NOT NULL,
  `Order_Admin_Status` varchar(50) DEFAULT 'Completed',
  `Order_TXN_Ref` varchar(100) NOT NULL,
  `Order_Type` varchar(50) NOT NULL,
  `Order_Status` text NOT NULL,
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Visible, 1=In Trash Bin',
  `Order_Created_At` datetime NOT NULL,
  `Order_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Payment_ID` int(11) DEFAULT NULL,
  `Branch_ID` int(11) DEFAULT NULL,
  `Activity_ID` int(11) DEFAULT NULL,
  `Case_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`Order_ID`),
  KEY `Order_Donor_ID_FK` (`Donor_ID`),
  KEY `Order_Activity_ID_FK` (`Activity_ID`),
  KEY `Order_Payment_ID_FK` (`Payment_ID`),
  KEY `Order_Case_ID_FK` (`Case_ID`),
  KEY `Order_Branch_ID_FK` (`Branch_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`Order_ID`, `Order_Name`, `Order_ContactNumber`, `Order_ICNumber`, `Order_Email`, `Order_Amount`, `Order_Points_Earned`, `Order_Currency`, `Order_PaymentMethod`, `Order_PaymentStatus`, `Order_Admin_Status`, `Order_TXN_Ref`, `Order_Type`, `Order_Status`, `Is_Deleted`, `Order_Created_At`, `Order_Updated_At`, `Donor_ID`, `Payment_ID`, `Branch_ID`, `Activity_ID`, `Case_ID`) VALUES
(1, 'RONALD TAN BIN HONG', '012-7212535', '030404011183', 'abc@gmail.com', 100.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-20251215222806', 'Recurring', 'Completed', 0, '2025-12-15 22:28:06', '2025-12-15 22:28:06', 2, 1, NULL, NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(2, 'ujintan218@gmail.com', 'ae15e0b65780f84ad3a13eaeba8fab791c35617bcea01e57447b0c14a27fee7d', '2025-12-07 21:38:58', '2025-12-07 12:38:58');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Payment_Method` varchar(50) NOT NULL,
  `Payment_Status` varchar(50) NOT NULL,
  `Payment_TXN_Ref` varchar(100) NOT NULL,
  `Payment_Amount` decimal(10,2) NOT NULL,
  `Payment_Paid_At` datetime NOT NULL,
  `Payment_Bank_Name` varchar(100) NOT NULL,
  `Payment_Bank_Masked` varchar(20) NOT NULL,
  `Payment_Created_At` datetime NOT NULL,
  PRIMARY KEY (`Payment_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`Payment_ID`, `Payment_Method`, `Payment_Status`, `Payment_TXN_Ref`, `Payment_Amount`, `Payment_Paid_At`, `Payment_Bank_Name`, `Payment_Bank_Masked`, `Payment_Created_At`) VALUES
(1, 'TNG eWallet', 'Success', 'TXN-20251215222806', 100.00, '2025-12-15 22:28:06', 'TNG eWallet', 'QR Payment', '2025-12-15 22:28:06');

-- --------------------------------------------------------

--
-- Table structure for table `point`
--

CREATE TABLE `point` (
  `Points_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Points_Earned` int(11) NOT NULL,
  `Points_Total` int(11) NOT NULL,
  `Points_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  PRIMARY KEY (`Points_ID`),
  KEY `Point_Donor_ID_FK` (`Donor_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `point`
--

INSERT INTO `point` (`Points_ID`, `Points_Earned`, `Points_Total`, `Points_Updated_At`, `Donor_ID`) VALUES
(1, 10, 10, '2025-12-15 22:28:06', 2);

-- --------------------------------------------------------

--
-- Table structure for table `receipt`
--

CREATE TABLE `receipt` (
  `Receipt_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Receipt_Receipt_Number` varchar(50) NOT NULL,
  `Receipt_Generated_At` datetime NOT NULL,
  `Receipt_Receipt_File` varchar(255) NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  PRIMARY KEY (`Receipt_ID`),
  KEY `Receipt_Donor_ID_FK` (`Donor_ID`),
  KEY `Receipt_Order_ID_FK` (`Order_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_donation`
--

CREATE TABLE `recurring_donation` (
  `Recurring_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Recurring_Amount` decimal(10,2) NOT NULL,
  `Recurring_Payment_Method` varchar(50) NOT NULL,
  `Recurring_StartDate` date NOT NULL DEFAULT current_timestamp(),
  `Recurring_EndDate` date DEFAULT NULL,
  `Recurring_Deduction_Date` date NOT NULL,
  `Last_Payment_Date` datetime DEFAULT NULL,
  `Recurring_Status` enum('Active','Paused','Cancelled','Stopped','Completed','Failed') NOT NULL DEFAULT 'Active',
  `Recurring_Created_At` datetime NOT NULL,
  `Recurring_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Branch_ID` int(11) DEFAULT NULL,
  `Activity_ID` int(11) DEFAULT NULL,
  `Case_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`Recurring_ID`),
  KEY `RecurringDonation_Donor_ID_FK` (`Donor_ID`),
  KEY `RecurringDonation_Branch_ID_FK` (`Branch_ID`),
  KEY `RecurringDonation_Activity_ID_FK` (`Activity_ID`),
  KEY `RecurringDonation_Case_ID_FK` (`Case_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recurring_donation`
--

INSERT INTO `recurring_donation` (`Recurring_ID`, `Recurring_Amount`, `Recurring_Payment_Method`, `Recurring_StartDate`, `Recurring_EndDate`, `Recurring_Deduction_Date`, `Last_Payment_Date`, `Recurring_Status`, `Recurring_Created_At`, `Recurring_Updated_At`, `Donor_ID`, `Branch_ID`, `Activity_ID`, `Case_ID`) VALUES
(1, 50.00, 'Credit Card', '2025-12-08', NULL, '2025-12-18', NULL, 'Active', '2025-12-15 14:06:21', '2025-12-15 14:06:21', 1, NULL, NULL, NULL),
(2, 100.00, 'TNG eWallet', '2025-12-15', NULL, '2026-01-01', NULL, 'Cancelled', '2025-12-15 22:28:06', '2025-12-15 22:28:51', 2, NULL, NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `redemption_order`
--

CREATE TABLE `redemption_order` (
  `Redemption_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Redemption_Address1` varchar(255) NOT NULL,
  `Redemption_Address2` varchar(255) DEFAULT NULL,
  `Redemption_Address3` varchar(255) DEFAULT NULL,
  `Redemption_City` varchar(100) NOT NULL,
  `Redemption_State` varchar(100) NOT NULL,
  `Redemption_PostalCode` varchar(20) NOT NULL,
  `Redemption_Country` varchar(100) DEFAULT 'Malaysia',
  `Redemption_ContactNumber` varchar(20) NOT NULL,
  `Redemption_PointsSpent` int(11) NOT NULL,
  `Redemption_Status` varchar(50) NOT NULL,
  `Redemption_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL,
  PRIMARY KEY (`Redemption_ID`),
  KEY `RedemptionOrder_Donor_ID_FK` (`Donor_ID`),
  KEY `RedemptionOrder_Reward_ID_FK` (`Reward_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_item`
--

CREATE TABLE `reward_item` (
  `Reward_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Reward_ItemName` varchar(100) NOT NULL,
  `Reward_Description` text NOT NULL,
  `Reward_RequiredPoint` int(11) NOT NULL,
  `Reward_Stock` int(11) NOT NULL,
  `Reward_Status` varchar(50) NOT NULL,
  `Reward_PhotoPath` varchar(255) NOT NULL,
  PRIMARY KEY (`Reward_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_case`
--

CREATE TABLE `special_case` (
  `Case_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Case_Title` varchar(100) NOT NULL,
  `Case_Description` text DEFAULT NULL,
  `Target_Amount` decimal(10,2) NOT NULL,
  `Raised_Amount` decimal(10,2) DEFAULT 0.00,
  `Case_Status` varchar(50) DEFAULT 'Active',
  `Created_At` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Case_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `special_case`
--

INSERT INTO `special_case` (`Case_ID`, `Case_Title`, `Case_Description`, `Target_Amount`, `Raised_Amount`, `Case_Status`, `Created_At`) VALUES
(1, 'Urgent Heart Surgery for Baby Ali', 'Baby Ali was born with a hole in his heart and requires immediate surgery to survive. The family cannot afford the medical bills.', 45000.00, 15000.00, 'Active', '2025-12-01 10:00:00'),
(2, 'Rebuilding Pak Abu\'s Burnt House', 'A tragic fire destroyed Pak Abu\'s wooden house in the village. He needs funds to buy materials for reconstruction.', 25000.00, 25000.00, 'Completed', '2025-11-15 09:30:00'),
(3, 'Education Fund for Siti', 'Siti is a brilliant student who got offered a place in university but cannot afford the registration fees and laptop.', 5000.00, 1300.00, 'Active', '2025-12-05 14:20:00'),
(4, 'Emergency Kidney Dialysis Aid', 'Uncle Wong needs dialysis 3 times a week. His savings have been depleted. Seeking public support for 3 months of treatment.', 12000.00, 8000.00, 'Active', '2025-11-20 11:00:00'),
(5, 'Animal Shelter Food Crisis', 'The Happy Paws shelter is running out of kibbles for 150 dogs due to supplier issues and lack of funds.', 3000.00, 3000.00, 'Completed', '2025-10-10 16:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `Staff_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Staff_FullName` varchar(100) NOT NULL,
  `Staff_ContactNumber` varchar(20) NOT NULL,
  `Staff_ICNumber` varchar(20) NOT NULL,
  `Staff_Email` varchar(100) NOT NULL,
  `Staff_Password` varchar(15) NOT NULL,
  `Staff_DOB` date NOT NULL,
  `Staff_Address1` varchar(255) NOT NULL,
  `Staff_Address2` varchar(255) DEFAULT NULL,
  `Staff_Address3` varchar(255) DEFAULT NULL,
  `Staff_City` varchar(100) NOT NULL,
  `Staff_State` varchar(100) NOT NULL,
  `Staff_PostalCode` varchar(20) NOT NULL,
  `Staff_Country` varchar(100) DEFAULT 'Malaysia',
  `Staff_Comment` text NOT NULL,
  `Staff_Role` varchar(50) NOT NULL DEFAULT 'Staff',
  `Staff_Status` varchar(50) NOT NULL DEFAULT 'Active',
  `Branch_ID` int(11) DEFAULT NULL,
  `Staff_JoinDate` date DEFAULT current_timestamp(),
  `Staff_ProfilePicture` varchar(255) DEFAULT NULL,
  `Admin_ID` int(11) NOT NULL,
  PRIMARY KEY (`Staff_ID`),
  KEY `staff_admin_id_fk` (`Admin_ID`),
  KEY `staff_branch_id_fk` (`Branch_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`Staff_ID`, `Staff_FullName`, `Staff_ContactNumber`, `Staff_ICNumber`, `Staff_Email`, `Staff_Password`, `Staff_DOB`, `Staff_Address1`, `Staff_Address2`, `Staff_Address3`, `Staff_City`, `Staff_State`, `Staff_PostalCode`, `Staff_Country`, `Staff_Comment`, `Staff_Role`, `Staff_Status`, `Branch_ID`, `Staff_JoinDate`, `Staff_ProfilePicture`, `Admin_ID`) VALUES
(1, 'Lim Wen Qin', '+6011-11190233', '030303-01-0303', 'lim.wen.qin@student.mmu.edu.my', '$2y$10$wI3ynrr3', '2003-03-03', '19, jalan bukit beruang utama 6', 'taman buklit beruang utama', '', 'melaka', 'Melaka', '75450', 'Malaysia', '', 'Staff', 'Active', NULL, '2025-12-07', 'uploads/staff_profiles/staff_1765123541_6935a5d520873.png', 2);

-- --------------------------------------------------------

--
-- Table structure for table `staff_activity`
--

CREATE TABLE `staff_activity` (
  `StaffActivity_ID` int(11) NOT NULL AUTO_INCREMENT,
  `StaffActivity_Role` varchar(50) NOT NULL,
  `Staff_ID` int(11) NOT NULL,
  `Activity_ID` int(11) NOT NULL,
  PRIMARY KEY (`StaffActivity_ID`),
  KEY `StaffActivity_Staff_ID_FK` (`Staff_ID`),
  KEY `StaffActivity_Activity_ID_FK` (`Activity_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `story`
--

CREATE TABLE `story` (
  `Story_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Story_Date` date NOT NULL,
  `Donor_Description` text NOT NULL,
  `Story_Image` varchar(255) NOT NULL,
  PRIMARY KEY (`Story_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Constraints for dumped tables
--

ALTER TABLE `activity`
  ADD CONSTRAINT `Activity_BrANCH_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

ALTER TABLE `branch`
  ADD CONSTRAINT `branch_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`);

ALTER TABLE `donor_achievement`
  ADD CONSTRAINT `DonorAchievement_Achievement_ID_FK` FOREIGN KEY (`Achievement_ID`) REFERENCES `achievement` (`Achievement_ID`),
  ADD CONSTRAINT `DonorAchievement_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

ALTER TABLE `item_donation`
  ADD CONSTRAINT `Item_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `Item_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

ALTER TABLE `notifications`
  ADD CONSTRAINT `Notification_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

ALTER TABLE `orders`
  ADD CONSTRAINT `Order_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `Order_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `Order_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `Order_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `Order_Payment_ID_FK` FOREIGN KEY (`Payment_ID`) REFERENCES `payment` (`Payment_ID`);

ALTER TABLE `point`
  ADD CONSTRAINT `Point_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

ALTER TABLE `receipt`
  ADD CONSTRAINT `Receipt_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `Receipt_Order_ID_FK` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`);

ALTER TABLE `recurring_donation`
  ADD CONSTRAINT `RecurringDonation_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `RecurringDonation_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `RecurringDonation_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `RecurringDonation_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

ALTER TABLE `redemption_order`
  ADD CONSTRAINT `RedemptionOrder_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `RedemptionOrder_Reward_ID_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`);

ALTER TABLE `staff`
  ADD CONSTRAINT `staff_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`),
  ADD CONSTRAINT `staff_branch_id_fk` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

ALTER TABLE `staff_activity`
  ADD CONSTRAINT `StaffActivity_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `StaffActivity_Staff_ID_FK` FOREIGN KEY (`Staff_ID`) REFERENCES `staff` (`Staff_ID`);

SET FOREIGN_KEY_CHECKS = 1; -- 重新开启外键检查
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;