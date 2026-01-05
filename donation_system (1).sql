-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 05, 2026 at 06:14 PM
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
-- Database: `donation_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievement`
--

CREATE TABLE `achievement` (
  `Achievement_ID` int(11) NOT NULL,
  `Achievement_Name` varchar(100) NOT NULL,
  `Achievement_Description` text NOT NULL,
  `Achievement_PointRequired` int(11) NOT NULL,
  `Achievement_Image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `Activity_ID` int(11) NOT NULL,
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
  `Branch_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity`
--

INSERT INTO `activity` (`Activity_ID`, `Activity_Name`, `Activity_Date`, `Activity_StartDate`, `Activity_EndDate`, `Activity_Details`, `Activity_Picture`, `Activity_Status`, `Activity_GetAmount`, `Activity_TargetAmount`, `Activity_Address1`, `Activity_Address2`, `Activity_Address3`, `Activity_City`, `Activity_State`, `Activity_PostalCode`, `Activity_Country`, `Branch_ID`) VALUES
(1, 'Charity Fun Run 2025', NULL, '2025-12-20', '2025-12-25', 'A 5km run to raise funds for local orphanages. Join us for a healthy morning!', NULL, 'Cancelled', 12500.00, 50000.00, 'Dataran Merdeka', '', '', 'Kuala Lumpur', 'Kuala Lumpur', '50050', 'Malaysia', 1),
(2, 'Beach Cleanup Drive', NULL, '2025-11-01', '2025-11-02', 'Cleaning up Batu Ferringhi beach to protect marine life. Volunteers needed.', NULL, 'Completed', 5000.00, 5000.00, 'Batu Ferringhi Public Beach', NULL, NULL, 'Batu Ferringhi', 'Penang', '11100', 'Malaysia', 2),
(3, 'Soup Kitchen Weekly', NULL, '2026-01-10', '2026-01-10', 'Distributing warm meals to the homeless community in Chow Kit area.', NULL, 'Active', 0.00, 2000.00, 'Jalan Chow Kit', NULL, NULL, 'Kuala Lumpur', 'Kuala Lumpur', '50300', 'Malaysia', 1),
(4, 'Flood Relief Mission', NULL, '2025-10-15', '2025-10-20', 'Emergency relief for flood victims in the East Coast. Collecting dry food and clothes.', NULL, 'Completed', 85000.00, 100000.00, 'Community Hall', NULL, NULL, 'Kuantan', 'Pahang', '25000', 'Malaysia', 1),
(5, 'Senior Care Visit', NULL, '2025-12-15', '2025-12-18', 'Spending time with the elderly, playing games, and providing medical checkups.', NULL, 'Active', 4500.00, 8000.00, 'Rumah Kasih Sayang', NULL, NULL, 'Petaling Jaya', 'Selangor', '46000', 'Malaysia', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL,
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
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`Admin_ID`, `Admin_Name`, `Admin_ContactNumber`, `Admin_ICNUMBER`, `Admin_Email`, `Admin_Password`, `Admin_DOB`, `Admin_Address1`, `Admin_Address2`, `Admin_Address3`, `Admin_City`, `Admin_State`, `Admin_PostalCode`, `Admin_Country`, `Admin_ProfilePicture`, `Admin_Role`, `Admin_Status`, `Admin_LastLogin`, `Admin_CreatedAt`, `Admin_UpdatedAt`, `Admin_Comment`, `Admin_LoginAttempts`, `Admin_LastFailedLogin`, `Is_Deleted`) VALUES
(1, 'Super Admin', '0123456789', '990101010101', 'admin@lovebridge.org.my', 'admin123', '1999-01-01', 'Level 12', 'Menara Love Bridge', 'Jalan Charity', 'Kuala Lumpur', 'Wilayah Persekutuan', '50000', 'Malaysia', NULL, 'Super Admin', 'Active', '2025-12-06 20:35:49', '2025-11-28 12:00:00', '2025-12-10 21:15:44', 'System Super Administrator', 5, '2025-12-10 21:15:44', 0),
(2, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', '$2y$10$bL3GDvE//gV.k149JNo6rejq/rwqubykoY3eMULa9vrndOYWFMjq.', '2003-05-17', '11， jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', 'uploads/admins/admin_1765013470_6933f7de52098.jpg', 'Super Admin', 'Active', '2026-01-05 21:44:14', '2025-12-06 17:31:10', '2026-01-05 21:44:14', 'Added via admin management system', 0, '2026-01-05 19:43:25', 0),
(3, 'Tan Choo Yee', '+6019-9878299', '010203-05-1234', 'ujintan218@gmail.com', '$2y$10$5zA9gCym9VbjQDdR0XcxK.de.K7M8mtKAh92m7r2IgOsJ..2Z2MVa', '2001-02-03', 'Blk 914Jurong West Street 91', 'taman bukit beruang utama', '', 'Melaka', 'Melaka', '75450', 'Malaysia', NULL, 'Admin', 'Active', '2025-12-10 21:17:38', '2025-12-07 20:35:18', '2025-12-10 21:17:38', 'Added via admin management system', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `AdminNotification_ID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `Contact_ID` int(11) DEFAULT NULL,
  `Type` varchar(50) NOT NULL,
  `Is_Read` tinyint(1) DEFAULT 0,
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`AdminNotification_ID`, `Message`, `Contact_ID`, `Type`, `Is_Read`, `Created_At`) VALUES
(7, 'New contact form submission from LIM WEN QIN', 17, 'New_Contact', 0, '2026-01-06 00:46:27'),
(8, 'New contact form submission from LIM WEN QIN', 17, 'New_Contact', 0, '2026-01-06 00:46:27'),
(9, 'New contact form submission from LIM WEN QIN', 17, 'New_Contact', 0, '2026-01-06 00:46:27');

-- --------------------------------------------------------

--
-- Table structure for table `branch`
--

CREATE TABLE `branch` (
  `Branch_ID` int(11) NOT NULL,
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
  `Branch_Email` varchar(100) NOT NULL,
  `Branch_Description` text NOT NULL,
  `Admin_ID` int(11) NOT NULL,
  `Branch_ProfilePicture` varchar(255) DEFAULT NULL,
  `Branch_OperationalStatus` enum('Open','Closed') DEFAULT 'Open',
  `Branch_TargetAmount` decimal(10,2) DEFAULT 10000.00,
  `Branch_CreatedAt` datetime DEFAULT current_timestamp(),
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branch`
--

INSERT INTO `branch` (`Branch_ID`, `Branch_Name`, `Branch_Type`, `Branch_Address1`, `Branch_Address2`, `Branch_Address3`, `Branch_City`, `Branch_State`, `Branch_PostalCode`, `Branch_Country`, `Branch_ContactNumber`, `Branch_Email`, `Branch_Description`, `Admin_ID`, `Branch_ProfilePicture`, `Branch_OperationalStatus`, `Branch_TargetAmount`, `Branch_CreatedAt`, `Is_Deleted`) VALUES
(1, 'fourace', 'Orphanage', 'Blk 914Jurong West Street 91', '', '', 'Skudai', 'Johor', '81300', 'Malaysia', '+6011-1190233', '', '', 2, NULL, 'Open', 200000.00, '2025-12-15 23:02:23', 0),
(2, 'KL Main Branch', 'Headquarters', 'Level 12, Menara Love Bridge', '', '', 'Kuala Lumpur', 'Kuala Lumpur', '50450', 'Malaysia', '+6011-12345633', 'admin@donationsystem.com', 'Main operating center for central region.', 1, NULL, 'Open', 10000.00, '2025-12-15 23:02:23', 0),
(3, 'Penang Outreach', 'Regional Center', '15, Jalan Georgetown', NULL, NULL, 'Georgetown', 'Penang', '10200', 'Malaysia', '04-2223333', '', 'Northern region support center.', 1, NULL, 'Open', 10000.00, '2025-12-15 23:02:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `Contact_ID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `Status` enum('New','Read','Replied') NOT NULL DEFAULT 'New',
  `Created_At` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`Contact_ID`, `Name`, `Email`, `Phone`, `Title`, `Message`, `Status`, `Created_At`) VALUES
(17, 'LIM WEN QIN', 'lll8694798586@gmail.com', '011-12345678', 'a', 'qw', 'New', '2026-01-06 00:46:27');

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `Donation_ID` int(11) NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Project_Name` varchar(255) DEFAULT 'General Donation',
  `Donation_Amount` decimal(10,2) NOT NULL,
  `Donation_Date` datetime DEFAULT current_timestamp(),
  `Payment_Status` enum('pending','completed','failed') DEFAULT 'completed',
  `Payment_Method` varchar(50) DEFAULT NULL,
  `Transaction_ID` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donor`
--

CREATE TABLE `donor` (
  `Donor_ID` int(11) NOT NULL,
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
  `Donor_LastLogin` datetime DEFAULT NULL,
  `Donor_ProfilePicture` varchar(255) DEFAULT NULL,
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donor`
--

INSERT INTO `donor` (`Donor_ID`, `Donor_Name`, `Donor_ContactNumber`, `Donor_ICNumber`, `Donor_Email`, `Donor_Password`, `Donor_Address1`, `Donor_Address2`, `Donor_Address3`, `Donor_City`, `Donor_State`, `Donor_PostalCode`, `Donor_Country`, `Donor_DOB`, `Donor_Description`, `Donor_RegisteredAt`, `Donor_LastLogin`, `Donor_ProfilePicture`, `Is_Deleted`) VALUES
(2, 'RONALD TAN BIN HONG', '012-7212535', '030404011183', 'abc@gmail.com', '$2y$10$3hsuDLLcdM4mr9bqhvJekOZqH80/6aF.7GR2vLxXHbcgWT.RjOe.O', 'No19.jalan melawati 19, taman melawati', '', '', 'Johor Bahru', 'Johor', '81300', 'Malaysia', '2003-04-04', '', '2025-12-15 22:27:40', NULL, NULL, 0),
(3, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', '$2y$10$PNOaF1e8KP0y9i404VCz9OWhn3XVyLHx8roawWiJjvPR0hyRunfeW', 'No 11, jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', '2003-05-17', '', '2026-01-03 21:43:26', NULL, 'uploads/donors/donor_1767447806_69591cfe10e76.jpg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `donor_achievement`
--

CREATE TABLE `donor_achievement` (
  `DonorAchievement_ID` int(11) NOT NULL,
  `DonorAchievement_AchievedAt` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Achievement_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `Email_ID` int(11) NOT NULL,
  `To_Email` varchar(100) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Content` text NOT NULL,
  `Status` enum('Sent','Failed','Pending') DEFAULT 'Pending',
  `Sent_At` datetime DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`Email_ID`, `To_Email`, `Title`, `Content`, `Status`, `Sent_At`, `Created_At`) VALUES
(1, 'admin@lovebridge.org.my', 'New Contact Form Submission: a', 'A new contact form has been submitted:\n\nName: LIM WEN QIN\nEmail: lll8694798586@gmail.com\nPhone: 011-12345678\nTitle: a\nMessage:\nqw\n\nLogin to admin panel to view details.', 'Sent', '2026-01-06 00:44:13', '2026-01-06 00:44:13'),
(2, 'admin@lovebridge.org.my', 'New Contact Form Submission: a', 'A new contact form has been submitted:\n\nName: LIM WEN QIN\nEmail: lll8694798586@gmail.com\nPhone: 011-12345678\nTitle: a\nMessage:\nqw\n\nLogin to admin panel to view details.', 'Sent', '2026-01-06 00:46:27', '2026-01-06 00:46:27');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `Expense_ID` int(11) NOT NULL,
  `Expense_Title` varchar(255) NOT NULL,
  `Expense_Description` text DEFAULT NULL,
  `Expense_Amount` decimal(10,2) NOT NULL,
  `Expense_Category` varchar(50) NOT NULL,
  `Claimant_Type` enum('Admin','Staff') NOT NULL,
  `Claimant_ID` int(11) NOT NULL,
  `Expense_Date` datetime NOT NULL DEFAULT current_timestamp(),
  `Expense_Status` varchar(50) NOT NULL DEFAULT 'Pending',
  `Recorded_By_Admin_ID` int(11) NOT NULL,
  `Expense_Proof` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`Expense_ID`, `Expense_Title`, `Expense_Description`, `Expense_Amount`, `Expense_Category`, `Claimant_Type`, `Claimant_ID`, `Expense_Date`, `Expense_Status`, `Recorded_By_Admin_ID`, `Expense_Proof`) VALUES
(1, 'Oil', 'cooking ol', 100.00, 'Reimbursement', 'Admin', 2, '2026-01-04 00:18:27', 'Approved', 2, '[\"uploads\\/expenses\\/receipt_1767457107_695941539eabe_0.jpg\"]'),
(2, 'Oil', 'cooking ol', 100.00, 'Reimbursement', 'Admin', 2, '2026-01-04 00:24:35', 'Approved', 2, '[\"uploads\\/expenses\\/receipt_1767457475_695942c3c9dc9_0.jpg\"]');

-- --------------------------------------------------------

--
-- Table structure for table `headquarters`
--

CREATE TABLE `headquarters` (
  `HQ_ID` int(11) NOT NULL,
  `HQ_Name` varchar(100) NOT NULL DEFAULT 'Love Bridge Foundation',
  `HQ_ContactNumber` varchar(30) NOT NULL,
  `HQ_Email` varchar(100) NOT NULL,
  `HQ_Address` text NOT NULL,
  `HQ_Description` text NOT NULL,
  `HQ_Story` longtext NOT NULL,
  `HQ_FoundingDate` date DEFAULT NULL,
  `HQ_Image` varchar(255) DEFAULT NULL,
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Headquarters_State` varchar(50) DEFAULT 'Kuala Lumpur'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `headquarters`
--

INSERT INTO `headquarters` (`HQ_ID`, `HQ_Name`, `HQ_ContactNumber`, `HQ_Email`, `HQ_Address`, `HQ_Description`, `HQ_Story`, `HQ_FoundingDate`, `HQ_Image`, `Updated_At`, `Headquarters_State`) VALUES
(1, 'Love Bridge Headquarters', '+603-1234 5678', 'info@lovebridge.org.my', 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur, Malaysia', 'Love Bridge is a non-profit organization dedicated to helping those in need.', 'Founded in 2010, Love Bridge started with a small group of volunteers who wanted to make a difference. Over the years, we have grown into a nationwide organization supporting various causes including elderly care, orphanages, and disaster relief.', '2010-01-01', NULL, '2025-11-26 12:00:00', 'Kuala Lumpur');

-- --------------------------------------------------------

--
-- Table structure for table `item_donation`
--

CREATE TABLE `item_donation` (
  `Item_ID` int(11) NOT NULL,
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
  `Activity_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL,
  `status` enum('failed','locked') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `Notification_ID` int(11) NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `Is_Read` tinyint(1) DEFAULT 0,
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `Order_ID` int(11) NOT NULL,
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
  `Tax_Receipt_Status` enum('Not_Requested','Requested','Generated','Rejected') DEFAULT 'Not_Requested',
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Visible, 1=In Trash Bin',
  `Order_Created_At` datetime NOT NULL,
  `Order_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Payment_ID` int(11) DEFAULT NULL,
  `Branch_ID` int(11) DEFAULT NULL,
  `Activity_ID` int(11) DEFAULT NULL,
  `Case_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`Order_ID`, `Order_Name`, `Order_ContactNumber`, `Order_ICNumber`, `Order_Email`, `Order_Amount`, `Order_Points_Earned`, `Order_Currency`, `Order_PaymentMethod`, `Order_PaymentStatus`, `Order_Admin_Status`, `Order_TXN_Ref`, `Order_Type`, `Order_Status`, `Tax_Receipt_Status`, `Is_Deleted`, `Order_Created_At`, `Order_Updated_At`, `Donor_ID`, `Payment_ID`, `Branch_ID`, `Activity_ID`, `Case_ID`) VALUES
(1, 'RONALD TAN BIN HONG', '012-7212535', '030404011183', 'abc@gmail.com', 100.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-20251215222806', 'Recurring', 'Completed', 'Not_Requested', 0, '2025-12-15 22:28:06', '2025-12-15 22:28:06', 2, 1, NULL, NULL, 3),
(2, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', 1000.00, 100, 'MYR', 'Cash', 'Success', 'Completed', 'MAN-IN-20260104001033-858', 'One-Time', 'Completed', 'Not_Requested', 0, '2026-01-04 00:10:33', '2026-01-04 00:10:33', 3, 3, NULL, NULL, NULL);

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_tax_receipt_request` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN

    IF NEW.Tax_Receipt_Status = 'Requested' AND OLD.Tax_Receipt_Status != 'Requested' THEN
        INSERT INTO admin_notifications (Message, Type, Is_Read, Created_At)
        VALUES (CONCAT('New Tax Receipt Request: ', NEW.Order_TXN_Ref), 'receipt_request', 0, NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(2, 'ujintan218@gmail.com', 'ae15e0b65780f84ad3a13eaeba8fab791c35617bcea01e57447b0c14a27fee7d', '2025-12-07 21:38:58', '2025-12-07 12:38:58'),
(3, 'thongyuenzhen@gmail.com', '7979343727a2f4f69ba4a295fb44a590e83b00cf138c47c036048f41f34e3ed1', '2025-12-18 22:32:33', '2025-12-18 13:32:33');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_ID` int(11) NOT NULL,
  `Payment_Method` varchar(50) NOT NULL,
  `Payment_Status` varchar(50) NOT NULL,
  `Payment_TXN_Ref` varchar(100) NOT NULL,
  `Payment_Amount` decimal(10,2) NOT NULL,
  `Payment_Paid_At` datetime NOT NULL,
  `Payment_Bank_Name` varchar(100) NOT NULL,
  `Payment_Bank_Masked` varchar(20) NOT NULL,
  `Payment_Proof` varchar(255) DEFAULT NULL,
  `Payment_Created_At` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`Payment_ID`, `Payment_Method`, `Payment_Status`, `Payment_TXN_Ref`, `Payment_Amount`, `Payment_Paid_At`, `Payment_Bank_Name`, `Payment_Bank_Masked`, `Payment_Proof`, `Payment_Created_At`) VALUES
(1, 'TNG eWallet', 'Success', 'TXN-20251215222806', 100.00, '2025-12-15 22:28:06', 'TNG eWallet', 'QR Payment', NULL, '2025-12-15 22:28:06'),
(3, 'Cash', 'Success', 'MAN-IN-20260104001033-858', 1000.00, '2026-01-04 00:10:33', 'Manual Entry', 'N/A', NULL, '2026-01-04 00:10:33');

-- --------------------------------------------------------

--
-- Table structure for table `point`
--

CREATE TABLE `point` (
  `Points_ID` int(11) NOT NULL,
  `Points_Earned` int(11) NOT NULL,
  `Points_Total` int(11) NOT NULL,
  `Points_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `point`
--

INSERT INTO `point` (`Points_ID`, `Points_Earned`, `Points_Total`, `Points_Updated_At`, `Donor_ID`) VALUES
(1, 10, 10, '2025-12-15 22:28:06', 2),
(2, 100, 65, '2026-01-04 23:11:46', 3);

-- --------------------------------------------------------

--
-- Table structure for table `policy_acceptances`
--

CREATE TABLE `policy_acceptances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `terms_version_id` int(11) NOT NULL,
  `privacy_version_id` int(11) NOT NULL,
  `accepted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privacy_policy`
--

CREATE TABLE `privacy_policy` (
  `id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `content` text NOT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `privacy_policy`
--

INSERT INTO `privacy_policy` (`id`, `version`, `content`, `effective_date`, `created_at`, `is_active`) VALUES
(1, '1.0', '<h2>Love Bridge Donation Platform - Privacy Policy</h2>\r\n<p><strong>Effective Date:</strong> [CURRENT_DATE]</p>\r\n\r\n<h3>1. Information We Collect</h3>\r\n<p>We collect information you provide directly, such as when you make a donation, including name, email address, and payment information.</p>\r\n\r\n<h3>2. How We Use Your Information</h3>\r\n<p>We use your information to process donations, send receipts, communicate about our programs, and improve our services.</p>\r\n\r\n<h3>3. Information Sharing</h3>\r\n<p>We do not sell or rent your personal information. We may share information with payment processors and as required by law.</p>\r\n\r\n<h3>4. Data Security</h3>\r\n<p>We implement reasonable security measures to protect your information, but no system is 100% secure.</p>\r\n\r\n<h3>5. Your Choices</h3>\r\n<p>You may opt out of promotional communications at any time. Required transactional communications will still be sent.</p>\r\n\r\n<h3>6. Changes to This Policy</h3>\r\n<p>We may update this policy periodically. We will notify users of material changes via email or platform notice.</p>\r\n\r\n<h3>7. Contact Us</h3>\r\n<p>For privacy-related questions, contact privacy@lovebridge.org.</p>', '2026-01-06', '2026-01-05 17:01:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `receipt`
--

CREATE TABLE `receipt` (
  `Receipt_ID` int(11) NOT NULL,
  `Receipt_Receipt_Number` varchar(50) NOT NULL,
  `Receipt_Generated_At` datetime NOT NULL,
  `Receipt_Receipt_File` varchar(255) NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_donation`
--

CREATE TABLE `recurring_donation` (
  `Recurring_ID` int(11) NOT NULL,
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
  `Case_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recurring_donation`
--

INSERT INTO `recurring_donation` (`Recurring_ID`, `Recurring_Amount`, `Recurring_Payment_Method`, `Recurring_StartDate`, `Recurring_EndDate`, `Recurring_Deduction_Date`, `Last_Payment_Date`, `Recurring_Status`, `Recurring_Created_At`, `Recurring_Updated_At`, `Donor_ID`, `Branch_ID`, `Activity_ID`, `Case_ID`) VALUES
(2, 100.00, 'TNG eWallet', '2025-12-15', NULL, '2026-01-01', NULL, 'Cancelled', '2025-12-15 22:28:06', '2025-12-15 22:28:51', 2, NULL, NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `redemption_order`
--

CREATE TABLE `redemption_order` (
  `Redemption_ID` int(11) NOT NULL,
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
  `Redemption_TrackingNumber` varchar(50) DEFAULT NULL,
  `Redemption_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `redemption_order`
--

INSERT INTO `redemption_order` (`Redemption_ID`, `Redemption_Address1`, `Redemption_Address2`, `Redemption_Address3`, `Redemption_City`, `Redemption_State`, `Redemption_PostalCode`, `Redemption_Country`, `Redemption_ContactNumber`, `Redemption_PointsSpent`, `Redemption_Status`, `Redemption_TrackingNumber`, `Redemption_Updated_At`, `Donor_ID`, `Reward_ID`) VALUES
(1, '11， jalan  silat lincah 10', NULL, NULL, 'Skudai', 'Johor', '81300', 'Malaysia', '+6011-11190233', 35, 'Cancelled', NULL, '2026-01-04 23:03:56', 3, 2),
(2, 'No 11, jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', '+6011-11190233', 35, 'Shipped', 'JNT-123654798', '2026-01-05 12:37:08', 3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `reward_item`
--

CREATE TABLE `reward_item` (
  `Reward_ID` int(11) NOT NULL,
  `Reward_Code` varchar(20) NOT NULL,
  `Reward_ItemName` varchar(100) NOT NULL,
  `Reward_Category` varchar(50) DEFAULT 'Handicraft',
  `Reward_Description` text NOT NULL,
  `Reward_RequiredPoint` int(11) NOT NULL,
  `Reward_Supplier` varchar(100) DEFAULT 'Local Artisans',
  `Reward_Stock` int(11) NOT NULL,
  `Reward_ExpiryDate` date DEFAULT NULL,
  `Reward_Status` varchar(50) NOT NULL,
  `Reward_PhotoPath` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reward_item`
--

INSERT INTO `reward_item` (`Reward_ID`, `Reward_Code`, `Reward_ItemName`, `Reward_Category`, `Reward_Description`, `Reward_RequiredPoint`, `Reward_Supplier`, `Reward_Stock`, `Reward_ExpiryDate`, `Reward_Status`, `Reward_PhotoPath`) VALUES
(1, 'HO-001', 'Handmade Organic Soap', 'Household', 'Natural lavender scented soap bars made with essential oils. Gentle on skin.', 12, 'Mama Earth Crafts', 50, '2026-12-31', 'Active', 'soap_lavender.jpg'),
(2, 'AP-002', 'Batik Cotton Scarf', 'Apparel', 'Traditional Malaysian Batik hand-painted scarf. 100% Cotton, vibrant colors.', 35, 'East Coast Batik', 23, NULL, 'Active', 'batik_scarf.jpg'),
(3, 'HO-003', 'Rattan Coaster Set', 'Household', 'Set of 4 hand-woven rattan coasters. Durable and eco-friendly.', 15, 'Village Weavers', 40, NULL, 'Active', 'rattan_coasters.jpg'),
(4, 'HA-004', 'Beaded Key Chain', 'Handicraft', 'Colorful hand-beaded keychain with traditional motifs. Random designs.', 5, 'Love Bridge Volunteers', 100, NULL, 'Active', 'keychain_beaded.jpg'),
(5, 'HO-005', 'Scented Soy Candle', 'Household', 'Eco-friendly soy wax candle in a glass jar. Lemongrass scent.', 20, 'Candle Studio', 30, NULL, 'Active', 'candle_soy.jpg'),
(6, 'HO-006', 'Mengkuang Woven Mat', 'Household', 'Small woven mat suitable for table centerpiece or wall decoration.', 25, 'Kampung Heritage', 15, NULL, 'Active', 'mengkuang_mat.jpg'),
(7, 'HO-007', 'Clay Flower Pot', 'Household', 'Mini terracotta pot hand-painted with floral designs.', 10, 'Earth Pottery', 8, NULL, 'Low Stock', 'clay_pot.jpg'),
(8, 'AP-008', 'Tie-Dye Tote Bag', 'Apparel', 'Canvas tote bag featuring unique tie-dye patterns. Reusable and stylish.', 18, 'Youth Art Center', 60, NULL, 'Active', 'tote_tiedye.jpg'),
(9, 'HO-009', 'Coconut Shell Bowl', 'Household', 'Polished natural coconut shell bowl, perfect for smoothie bowls or decor.', 12, 'Island Crafts', 45, NULL, 'Active', 'coconut_bowl.jpg'),
(10, 'OT-010', 'Hand-Stitched Notebook', 'Others', 'A5 notebook with recycled paper and hand-stitched binding.', 15, 'Green Stationers', 35, NULL, 'Active', 'notebook_stitched.jpg'),
(11, 'HA-011', 'Macrame Wall Hanging', 'Handicraft', 'Bohemian style wall decoration made from cotton rope.', 45, 'Knotty Arts', 12, NULL, 'Active', 'macrame_wall.jpg'),
(12, 'AP-012', 'Embroidered Pouch', 'Apparel', 'Zipper pouch with detailed floral embroidery. Great for cosmetics.', 22, 'Needlework Aunties', 28, NULL, 'Active', 'pouch_embroidery.jpg'),
(13, 'HO-013', 'Bamboo Cutlery Set', 'Household', 'Reusable bamboo spoon, fork, and straw in a cloth pouch.', 10, 'EcoLife', 5, NULL, 'Low Stock', 'bamboo_set.jpg'),
(14, 'OT-014', 'Resin Flower Bookmark', 'Others', 'Clear resin bookmark with pressed real dried flowers inside.', 8, 'Floral Press', 0, NULL, 'Inactive', 'resin_bookmark.jpg'),
(15, 'HO-015', 'Hand-Painted Tiffin', 'Household', 'Classic metal tiffin carrier painted with retro floral patterns.', 50, 'Retro Vibes', 10, NULL, 'Active', 'tiffin_painted.jpg'),
(16, 'HA-016', 'Crochet Plushie (Bear)', 'Handicraft', 'Cute handmade crochet bear doll. Safe for kids.', 30, 'Grandma Stitches', 20, NULL, 'Active', 'crochet_bear.jpg'),
(17, 'AP-017', 'Upcycled Denim Purse', 'Apparel', 'Coin purse made from recycled denim jeans.', 10, 'Recycle Works', 40, NULL, 'Active', 'denim_purse.jpg'),
(18, 'EL-018', 'Wood Carved Phone Stand', 'Electronics', 'Simple wooden stand carved from leftover timber. Fits most phones.', 15, 'Woody Crafts', 3, NULL, 'Low Stock', 'wood_stand.jpg'),
(19, 'HO-019', 'Ceramic Mug (Handmade)', 'Household', 'Imperfection is beauty. Hand-thrown ceramic mug with unique glaze.', 25, 'Earth Pottery', 18, NULL, 'Active', 'ceramic_mug.jpg'),
(20, 'HA-020', 'Traditional Kite (Wau)', 'Handicraft', 'Miniature decorative Wau Bulan. Not for flying, for display only.', 40, 'Heritage Fly', 25, NULL, 'Active', 'wau_mini.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `reward_logs`
--

CREATE TABLE `reward_logs` (
  `Log_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL,
  `Admin_ID` int(11) NOT NULL,
  `Action_Type` varchar(50) NOT NULL,
  `Action_Details` text NOT NULL,
  `Log_Created_At` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_case`
--

CREATE TABLE `special_case` (
  `Case_ID` int(11) NOT NULL,
  `Case_Title` varchar(100) NOT NULL,
  `Case_Description` text DEFAULT NULL,
  `Case_Image` varchar(255) DEFAULT NULL,
  `Target_Amount` decimal(10,2) NOT NULL,
  `Raised_Amount` decimal(10,2) DEFAULT 0.00,
  `Case_Status` varchar(50) DEFAULT 'Active',
  `Cancel_Reason` text DEFAULT NULL,
  `Completed_At` datetime DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp(),
  `Start_Date` date DEFAULT NULL,
  `Case_Category` enum('medical','daily_needs') DEFAULT 'medical',
  `Urgency` enum('low','medium','high') DEFAULT 'medium',
  `Donor_Count` int(11) DEFAULT 0,
  `Case_Deadline` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `special_case`
--

INSERT INTO `special_case` (`Case_ID`, `Case_Title`, `Case_Description`, `Case_Image`, `Target_Amount`, `Raised_Amount`, `Case_Status`, `Cancel_Reason`, `Completed_At`, `Created_At`, `Start_Date`, `Case_Category`, `Urgency`, `Donor_Count`, `Case_Deadline`) VALUES
(1, 'Urgent Heart Surgery for Baby Ali', 'Baby Ali was born with a hole in his heart and requires immediate surgery to survive. The family cannot afford the medical bills.', NULL, 45000.00, 15000.00, 'Active', NULL, NULL, '2025-12-01 10:00:00', NULL, 'medical', 'medium', 0, NULL),
(2, 'Rebuilding Pak Abu\'s Burnt House', 'A tragic fire destroyed Pak Abu\'s wooden house in the village. He needs funds to buy materials for reconstruction.', NULL, 25000.00, 25000.00, 'Completed', NULL, NULL, '2025-11-15 09:30:00', NULL, 'medical', 'medium', 0, NULL),
(3, 'Education Fund for Siti', 'Siti is a brilliant student who got offered a place in university but cannot afford the registration fees and laptop.', 'uploads/cases/case_1765863532_6940f06c6a99c.jpg', 5000.00, 1300.00, 'Active', NULL, NULL, '2025-12-05 14:20:00', NULL, 'medical', 'medium', 0, NULL),
(4, 'Emergency Kidney Dialysis Aid', 'Uncle Wong needs dialysis 3 times a week. His savings have been depleted. Seeking public support for 3 months of treatment.', NULL, 12000.00, 8000.00, 'Cancelled', NULL, NULL, '2025-11-20 11:00:00', NULL, 'medical', 'medium', 0, NULL),
(5, 'Animal Shelter Food Crisis', 'The Happy Paws shelter is running out of kibbles for 150 dogs due to supplier issues and lack of funds.', NULL, 3000.00, 3000.00, 'Completed', NULL, NULL, '2025-10-10 16:45:00', NULL, 'medical', 'medium', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `Staff_ID` int(11) NOT NULL,
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
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`Staff_ID`, `Staff_FullName`, `Staff_ContactNumber`, `Staff_ICNumber`, `Staff_Email`, `Staff_Password`, `Staff_DOB`, `Staff_Address1`, `Staff_Address2`, `Staff_Address3`, `Staff_City`, `Staff_State`, `Staff_PostalCode`, `Staff_Country`, `Staff_Comment`, `Staff_Role`, `Staff_Status`, `Branch_ID`, `Staff_JoinDate`, `Staff_ProfilePicture`, `Admin_ID`, `Is_Deleted`) VALUES
(1, 'Lim Wen Qin', '+6011-11190233', '030303-01-0303', 'lim.wen.qin@student.mmu.edu.my', '$2y$10$wI3ynrr3', '2003-03-03', '19, jalan bukit beruang utama 6', 'taman buklit beruang utama', '', 'melaka', 'Melaka', '75450', 'Malaysia', '', 'Staff', 'Active', NULL, '2025-12-07', 'uploads/staff_profiles/staff_1765123541_6935a5d520873.png', 2, 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff_activity`
--

CREATE TABLE `staff_activity` (
  `StaffActivity_ID` int(11) NOT NULL,
  `StaffActivity_Role` varchar(50) NOT NULL,
  `Staff_ID` int(11) NOT NULL,
  `Activity_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `story`
--

CREATE TABLE `story` (
  `Story_ID` int(11) NOT NULL,
  `Story_Date` date DEFAULT NULL,
  `Story_Title` varchar(255) NOT NULL,
  `Story_Author` varchar(100) DEFAULT NULL,
  `Story_Category` enum('Donor Story','Impact Report','News','Event','Success Story') DEFAULT 'Donor Story',
  `Story_Description` text NOT NULL,
  `Story_Image` varchar(255) DEFAULT NULL,
  `Story_Status` enum('Published','Draft') DEFAULT 'Published',
  `Created_At` datetime DEFAULT current_timestamp(),
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `story`
--

INSERT INTO `story` (`Story_ID`, `Story_Date`, `Story_Title`, `Story_Author`, `Story_Category`, `Story_Description`, `Story_Image`, `Story_Status`, `Created_At`, `Updated_At`) VALUES
(1, NULL, 'Helping the Flood Victims', NULL, 'Donor Story', 'Last month, we successfully distributed food to 500 families.', 'uploads/stories/default.jpg', 'Published', '2025-12-19 16:50:52', '2026-01-05 23:16:30');

-- --------------------------------------------------------

--
-- Table structure for table `system_pages`
--

CREATE TABLE `system_pages` (
  `Page_ID` int(11) NOT NULL,
  `Page_Key` varchar(50) NOT NULL,
  `Page_Title` varchar(100) NOT NULL,
  `Page_Content` longtext NOT NULL,
  `Last_Updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_pages`
--

INSERT INTO `system_pages` (`Page_ID`, `Page_Key`, `Page_Title`, `Page_Content`, `Last_Updated`) VALUES
(1, 'about_us', 'About Us', 'Welcome to Love Bridge. We are dedicated to helping those in need...', '2025-12-19 20:58:31'),
(2, 'contact_us', 'Contact Us Information', 'You can reach us at 012-3456789 or visit our HQ at KL...', '2025-12-19 20:58:38'),
(3, 'terms_condition', 'Terms & Conditions', 'By using this website, you agree to the following terms...', '2025-12-19 16:50:52'),
(4, 'privacy_policy', 'Privacy Policy', 'We value your privacy and are committed to protecting your personal data...', '2025-12-19 16:50:52');

-- --------------------------------------------------------

--
-- Table structure for table `terms_conditions`
--

CREATE TABLE `terms_conditions` (
  `id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `content` text NOT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `terms_conditions`
--

INSERT INTO `terms_conditions` (`id`, `version`, `content`, `effective_date`, `created_at`, `is_active`) VALUES
(1, '1.0', '<h2>Love Bridge Donation Platform - Terms & Conditions</h2>\r\n<p><strong>Effective Date:</strong> [CURRENT_DATE]</p>\r\n\r\n<h3>1. Acceptance of Terms</h3>\r\n<p>By accessing and using the Love Bridge donation platform, you accept and agree to be bound by the terms and provision of this agreement.</p>\r\n\r\n<h3>2. Donation Policy</h3>\r\n<p>All donations made through Love Bridge are voluntary and non-refundable. Donations are used to support our charitable programs as described on our website.</p>\r\n\r\n<h3>3. User Responsibilities</h3>\r\n<p>Users must provide accurate information when making donations and agree not to use the platform for any unlawful purpose.</p>\r\n\r\n<h3>4. Privacy</h3>\r\n<p>Your privacy is important to us. Please review our Privacy Policy to understand how we collect and use your information.</p>\r\n\r\n<h3>5. Modifications</h3>\r\n<p>Love Bridge reserves the right to modify these terms at any time. Continued use of the platform after changes constitutes acceptance.</p>\r\n\r\n<h3>6. Contact Information</h3>\r\n<p>For questions about these Terms & Conditions, please contact us at terms@lovebridge.org.</p>', '2026-01-06', '2026-01-05 17:01:32', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievement`
--
ALTER TABLE `achievement`
  ADD PRIMARY KEY (`Achievement_ID`);

--
-- Indexes for table `activity`
--
ALTER TABLE `activity`
  ADD PRIMARY KEY (`Activity_ID`),
  ADD KEY `Activity_BrANCH_ID_FK` (`Branch_ID`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`Admin_ID`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`AdminNotification_ID`),
  ADD KEY `Contact_ID` (`Contact_ID`);

--
-- Indexes for table `branch`
--
ALTER TABLE `branch`
  ADD PRIMARY KEY (`Branch_ID`),
  ADD KEY `branch_admin_id_fk` (`Admin_ID`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`Contact_ID`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`Donation_ID`),
  ADD KEY `Donor_ID` (`Donor_ID`);

--
-- Indexes for table `donor`
--
ALTER TABLE `donor`
  ADD PRIMARY KEY (`Donor_ID`);

--
-- Indexes for table `donor_achievement`
--
ALTER TABLE `donor_achievement`
  ADD PRIMARY KEY (`DonorAchievement_ID`),
  ADD KEY `DonorAchievement_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `DonorAchievement_Achievement_ID_FK` (`Achievement_ID`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`Email_ID`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`Expense_ID`);

--
-- Indexes for table `headquarters`
--
ALTER TABLE `headquarters`
  ADD PRIMARY KEY (`HQ_ID`);

--
-- Indexes for table `item_donation`
--
ALTER TABLE `item_donation`
  ADD PRIMARY KEY (`Item_ID`),
  ADD KEY `Item_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Item_Activity_ID_FK` (`Activity_ID`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`Notification_ID`),
  ADD KEY `Notification_Donor_ID_FK` (`Donor_ID`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `Order_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Order_Activity_ID_FK` (`Activity_ID`),
  ADD KEY `Order_Payment_ID_FK` (`Payment_ID`),
  ADD KEY `Order_Case_ID_FK` (`Case_ID`),
  ADD KEY `Order_Branch_ID_FK` (`Branch_ID`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_ID`);

--
-- Indexes for table `point`
--
ALTER TABLE `point`
  ADD PRIMARY KEY (`Points_ID`),
  ADD KEY `Point_Donor_ID_FK` (`Donor_ID`);

--
-- Indexes for table `policy_acceptances`
--
ALTER TABLE `policy_acceptances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terms_version_id` (`terms_version_id`),
  ADD KEY `privacy_version_id` (`privacy_version_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `privacy_policy`
--
ALTER TABLE `privacy_policy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_effective_date` (`effective_date`);

--
-- Indexes for table `receipt`
--
ALTER TABLE `receipt`
  ADD PRIMARY KEY (`Receipt_ID`),
  ADD UNIQUE KEY `unique_receipt_number` (`Receipt_Receipt_Number`),
  ADD KEY `Receipt_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Receipt_Order_ID_FK` (`Order_ID`);

--
-- Indexes for table `recurring_donation`
--
ALTER TABLE `recurring_donation`
  ADD PRIMARY KEY (`Recurring_ID`),
  ADD KEY `RecurringDonation_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `RecurringDonation_Branch_ID_FK` (`Branch_ID`),
  ADD KEY `RecurringDonation_Activity_ID_FK` (`Activity_ID`),
  ADD KEY `RecurringDonation_Case_ID_FK` (`Case_ID`);

--
-- Indexes for table `redemption_order`
--
ALTER TABLE `redemption_order`
  ADD PRIMARY KEY (`Redemption_ID`),
  ADD KEY `RedemptionOrder_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `RedemptionOrder_Reward_ID_FK` (`Reward_ID`);

--
-- Indexes for table `reward_item`
--
ALTER TABLE `reward_item`
  ADD PRIMARY KEY (`Reward_ID`),
  ADD KEY `Reward_Code` (`Reward_Code`);

--
-- Indexes for table `reward_logs`
--
ALTER TABLE `reward_logs`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `Log_Reward_FK` (`Reward_ID`),
  ADD KEY `Log_Admin_FK` (`Admin_ID`);

--
-- Indexes for table `special_case`
--
ALTER TABLE `special_case`
  ADD PRIMARY KEY (`Case_ID`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`Staff_ID`),
  ADD KEY `staff_admin_id_fk` (`Admin_ID`),
  ADD KEY `staff_branch_id_fk` (`Branch_ID`);

--
-- Indexes for table `staff_activity`
--
ALTER TABLE `staff_activity`
  ADD PRIMARY KEY (`StaffActivity_ID`),
  ADD KEY `StaffActivity_Staff_ID_FK` (`Staff_ID`),
  ADD KEY `StaffActivity_Activity_ID_FK` (`Activity_ID`);

--
-- Indexes for table `story`
--
ALTER TABLE `story`
  ADD PRIMARY KEY (`Story_ID`);

--
-- Indexes for table `system_pages`
--
ALTER TABLE `system_pages`
  ADD PRIMARY KEY (`Page_ID`),
  ADD UNIQUE KEY `Page_Key` (`Page_Key`);

--
-- Indexes for table `terms_conditions`
--
ALTER TABLE `terms_conditions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_effective_date` (`effective_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievement`
--
ALTER TABLE `achievement`
  MODIFY `Achievement_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity`
--
ALTER TABLE `activity`
  MODIFY `Activity_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `Admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `AdminNotification_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `branch`
--
ALTER TABLE `branch`
  MODIFY `Branch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `Contact_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `Donation_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donor`
--
ALTER TABLE `donor`
  MODIFY `Donor_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `donor_achievement`
--
ALTER TABLE `donor_achievement`
  MODIFY `DonorAchievement_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `Email_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `Expense_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `headquarters`
--
ALTER TABLE `headquarters`
  MODIFY `HQ_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `item_donation`
--
ALTER TABLE `item_donation`
  MODIFY `Item_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `Notification_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `point`
--
ALTER TABLE `point`
  MODIFY `Points_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `policy_acceptances`
--
ALTER TABLE `policy_acceptances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privacy_policy`
--
ALTER TABLE `privacy_policy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `receipt`
--
ALTER TABLE `receipt`
  MODIFY `Receipt_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recurring_donation`
--
ALTER TABLE `recurring_donation`
  MODIFY `Recurring_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `redemption_order`
--
ALTER TABLE `redemption_order`
  MODIFY `Redemption_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reward_item`
--
ALTER TABLE `reward_item`
  MODIFY `Reward_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `reward_logs`
--
ALTER TABLE `reward_logs`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_case`
--
ALTER TABLE `special_case`
  MODIFY `Case_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `Staff_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_activity`
--
ALTER TABLE `staff_activity`
  MODIFY `StaffActivity_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `story`
--
ALTER TABLE `story`
  MODIFY `Story_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_pages`
--
ALTER TABLE `system_pages`
  MODIFY `Page_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `terms_conditions`
--
ALTER TABLE `terms_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity`
--
ALTER TABLE `activity`
  ADD CONSTRAINT `Activity_BrANCH_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `fk_admin_notifications_contact` FOREIGN KEY (`Contact_ID`) REFERENCES `contact_messages` (`Contact_ID`) ON DELETE CASCADE;

--
-- Constraints for table `branch`
--
ALTER TABLE `branch`
  ADD CONSTRAINT `branch_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`);

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`) ON DELETE CASCADE;

--
-- Constraints for table `donor_achievement`
--
ALTER TABLE `donor_achievement`
  ADD CONSTRAINT `DonorAchievement_Achievement_ID_FK` FOREIGN KEY (`Achievement_ID`) REFERENCES `achievement` (`Achievement_ID`),
  ADD CONSTRAINT `DonorAchievement_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- Constraints for table `item_donation`
--
ALTER TABLE `item_donation`
  ADD CONSTRAINT `Item_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `Item_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `Notification_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `Order_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `Order_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `Order_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `Order_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `Order_Payment_ID_FK` FOREIGN KEY (`Payment_ID`) REFERENCES `payment` (`Payment_ID`);

--
-- Constraints for table `point`
--
ALTER TABLE `point`
  ADD CONSTRAINT `Point_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- Constraints for table `policy_acceptances`
--
ALTER TABLE `policy_acceptances`
  ADD CONSTRAINT `policy_acceptances_ibfk_1` FOREIGN KEY (`terms_version_id`) REFERENCES `terms_conditions` (`id`),
  ADD CONSTRAINT `policy_acceptances_ibfk_2` FOREIGN KEY (`privacy_version_id`) REFERENCES `privacy_policy` (`id`);

--
-- Constraints for table `receipt`
--
ALTER TABLE `receipt`
  ADD CONSTRAINT `Receipt_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `Receipt_Order_ID_FK` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`);

--
-- Constraints for table `recurring_donation`
--
ALTER TABLE `recurring_donation`
  ADD CONSTRAINT `RecurringDonation_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `RecurringDonation_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `RecurringDonation_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `RecurringDonation_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- Constraints for table `redemption_order`
--
ALTER TABLE `redemption_order`
  ADD CONSTRAINT `RedemptionOrder_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `RedemptionOrder_Reward_ID_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`);

--
-- Constraints for table `reward_logs`
--
ALTER TABLE `reward_logs`
  ADD CONSTRAINT `Log_Admin_FK` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`),
  ADD CONSTRAINT `Log_Reward_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`),
  ADD CONSTRAINT `staff_branch_id_fk` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

--
-- Constraints for table `staff_activity`
--
ALTER TABLE `staff_activity`
  ADD CONSTRAINT `StaffActivity_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `StaffActivity_Staff_ID_FK` FOREIGN KEY (`Staff_ID`) REFERENCES `staff` (`Staff_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
