-- phpMyAdmin SQL Dump
-- version 5.2.1
-- Host: 127.0.0.1
-- Generation Time: 2025-12-07 10:00:00
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
  `Activity_Picture` varchar(255) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL,
  `Admin_FName` varchar(50) NOT NULL,
  `Admin_LName` varchar(50) NOT NULL,
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
  `Admin_Role` enum('Super Admin','Admin') NOT NULL DEFAULT 'Admin', -- ✅ 加回 Role 字段
  `Admin_Status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Active',
  `Admin_LastLogin` datetime DEFAULT NULL,
  `Admin_CreatedAt` datetime DEFAULT current_timestamp(),
  `Admin_UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Admin_Comment` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Branch_Description` text NOT NULL,
  `Admin_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `Contact_ID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Subject` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `Status` enum('New','Read','Replied') NOT NULL DEFAULT 'New',
  `Created_At` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donor`
--

CREATE TABLE `donor` (
  `Donor_ID` int(11) NOT NULL,
  `Donor_FName` varchar(50) NOT NULL,
  `Donor_LName` varchar(50) NOT NULL,
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
  `Donor_ProfilePicture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Updated_At` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `Order_ID` int(11) NOT NULL,
  `Order_FName` varchar(50) NOT NULL,
  `Order_LName` varchar(50) NOT NULL,
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
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Visible, 1=In Trash Bin', -- ✅ 新增：软删除
  `Order_Created_At` datetime NOT NULL,
  `Order_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Payment_ID` int(11) DEFAULT NULL,
  `Branch_ID` int(11) DEFAULT NULL,
  `Activity_ID` int(11) NULL, -- ✅ 已设为 NULL (可选)
  `Case_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Payment_Created_At` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Recurring_StartDate` date NOT NULL DEFAULT current_timestamp(), -- ✅ 新增
  `Recurring_EndDate` date DEFAULT NULL, -- ✅ 新增
  `Recurring_Deduction_Date` date NOT NULL,
  `Last_Payment_Date` datetime DEFAULT NULL, -- ✅ 新增
  `Recurring_Status` enum('Active','Stopped','Completed','Failed') NOT NULL DEFAULT 'Active', -- ✅ 修改状态
  `Recurring_Created_At` datetime NOT NULL,
  `Recurring_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Redemption_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_item`
--

CREATE TABLE `reward_item` (
  `Reward_ID` int(11) NOT NULL,
  `Reward_ItemName` varchar(100) NOT NULL,
  `Reward_Description` text NOT NULL,
  `Reward_RequiredPoint` int(11) NOT NULL,
  `Reward_Stock` int(11) NOT NULL,
  `Reward_Status` varchar(50) NOT NULL,
  `Reward_PhotoPath` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_case`
--

CREATE TABLE `special_case` (
  `Case_ID` int(11) NOT NULL,
  `Case_Title` varchar(100) NOT NULL,
  `Case_Description` text DEFAULT NULL,
  `Target_Amount` decimal(10,2) NOT NULL,
  `Raised_Amount` decimal(10,2) DEFAULT 0.00,
  `Case_Status` varchar(50) DEFAULT 'Active',
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `Staff_ID` int(11) NOT NULL,
  `Staff_FName` varchar(50) NOT NULL,
  `Staff_LName` varchar(50) NOT NULL,
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
  `Admin_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Story_Date` date NOT NULL,
  `Donor_Description` text NOT NULL,
  `Story_Image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`Admin_ID`, `Admin_FName`, `Admin_LName`, `Admin_ContactNumber`, `Admin_ICNUMBER`, `Admin_Email`, `Admin_Password`, `Admin_DOB`, `Admin_Address1`, `Admin_Address2`, `Admin_Address3`, `Admin_City`, `Admin_State`, `Admin_PostalCode`, `Admin_Country`, `Admin_ProfilePicture`, `Admin_Role`, `Admin_Status`, `Admin_LastLogin`, `Admin_CreatedAt`, `Admin_UpdatedAt`, `Admin_Comment`) VALUES
(1, 'Super', 'Admin', '0123456789', '990101010101', 'admin@lovebridge.org.my', 'admin123', '1999-01-01', 'Level 12', 'Menara Love Bridge', 'Jalan Charity', 'Kuala Lumpur', 'Wilayah Persekutuan', '50000', 'Malaysia', NULL, 'Super Admin', 'Active', NULL, '2025-11-28 12:00:00', '2025-11-28 12:00:00', 'System Super Administrator');

--
-- Dumping data for table `headquarters`
--

INSERT INTO `headquarters` (`HQ_ID`, `HQ_Name`, `HQ_ContactNumber`, `HQ_Email`, `HQ_Address`, `HQ_Description`, `HQ_Story`, `HQ_FoundingDate`, `HQ_Image`, `Updated_At`) VALUES
(1, 'Love Bridge Headquarters', '+603-1234 5678', 'info@lovebridge.org.my', 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur, Malaysia', 'Love Bridge is a non-profit organization dedicated to helping those in need.', 'Founded in 2010, Love Bridge started with a small group of volunteers...', '2010-01-01', NULL, '2025-11-26 12:00:00');

--
-- Indexes for dumped tables
--

-- Indexes for table `achievement`
ALTER TABLE `achievement` ADD PRIMARY KEY (`Achievement_ID`);
-- Indexes for table `activity`
ALTER TABLE `activity` ADD PRIMARY KEY (`Activity_ID`), ADD KEY `Activity_BrANCH_ID_FK` (`Branch_ID`);
-- Indexes for table `admin`
ALTER TABLE `admin` ADD PRIMARY KEY (`Admin_ID`);
-- Indexes for table `branch`
ALTER TABLE `branch` ADD PRIMARY KEY (`Branch_ID`), ADD KEY `branch_admin_id_fk` (`Admin_ID`);
-- Indexes for table `contact_messages`
ALTER TABLE `contact_messages` ADD PRIMARY KEY (`Contact_ID`);
-- Indexes for table `donor`
ALTER TABLE `donor` ADD PRIMARY KEY (`Donor_ID`);
-- Indexes for table `donor_achievement`
ALTER TABLE `donor_achievement` ADD PRIMARY KEY (`DonorAchievement_ID`), ADD KEY `DonorAchievement_Donor_ID_FK` (`Donor_ID`), ADD KEY `DonorAchievement_Achievement_ID_FK` (`Achievement_ID`);
-- Indexes for table `headquarters`
ALTER TABLE `headquarters` ADD PRIMARY KEY (`HQ_ID`);
-- Indexes for table `item_donation`
ALTER TABLE `item_donation` ADD PRIMARY KEY (`Item_ID`), ADD KEY `Item_Donor_ID_FK` (`Donor_ID`), ADD KEY `Item_Activity_ID_FK` (`Activity_ID`);
-- Indexes for table `login_attempts`
ALTER TABLE `login_attempts` ADD PRIMARY KEY (`id`), ADD KEY `idx_email_time` (`email`,`attempt_time`), ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);
-- Indexes for table `orders`
ALTER TABLE `orders` ADD PRIMARY KEY (`Order_ID`), ADD KEY `Order_Donor_ID_FK` (`Donor_ID`), ADD KEY `Order_Activity_ID_FK` (`Activity_ID`), ADD KEY `Order_Payment_ID_FK` (`Payment_ID`), ADD KEY `Order_Case_ID_FK` (`Case_ID`);
-- Indexes for table `password_resets`
ALTER TABLE `password_resets` ADD PRIMARY KEY (`id`), ADD KEY `idx_email` (`email`), ADD KEY `idx_token` (`token`);
-- Indexes for table `payment`
ALTER TABLE `payment` ADD PRIMARY KEY (`Payment_ID`);
-- Indexes for table `point`
ALTER TABLE `point` ADD PRIMARY KEY (`Points_ID`), ADD KEY `Point_Donor_ID_FK` (`Donor_ID`);
-- Indexes for table `receipt`
ALTER TABLE `receipt` ADD PRIMARY KEY (`Receipt_ID`), ADD KEY `Receipt_Donor_ID_FK` (`Donor_ID`), ADD KEY `Receipt_Order_ID_FK` (`Order_ID`);
-- Indexes for table `recurring_donation`
ALTER TABLE `recurring_donation` ADD PRIMARY KEY (`Recurring_ID`), ADD KEY `RecurringDonation_Donor_ID_FK` (`Donor_ID`);
-- Indexes for table `redemption_order`
ALTER TABLE `redemption_order` ADD PRIMARY KEY (`Redemption_ID`), ADD KEY `RedemptionOrder_Donor_ID_FK` (`Donor_ID`), ADD KEY `RedemptionOrder_Reward_ID_FK` (`Reward_ID`);
-- Indexes for table `reward_item`
ALTER TABLE `reward_item` ADD PRIMARY KEY (`Reward_ID`);
-- Indexes for table `special_case`
ALTER TABLE `special_case` ADD PRIMARY KEY (`Case_ID`);
-- Indexes for table `staff`
ALTER TABLE `staff` ADD PRIMARY KEY (`Staff_ID`), ADD KEY `staff_admin_id_fk` (`Admin_ID`), ADD KEY `staff_branch_id_fk` (`Branch_ID`);
-- Indexes for table `staff_activity`
ALTER TABLE `staff_activity` ADD PRIMARY KEY (`StaffActivity_ID`), ADD KEY `StaffActivity_Staff_ID_FK` (`Staff_ID`), ADD KEY `StaffActivity_Activity_ID_FK` (`Activity_ID`);
-- Indexes for table `story`
ALTER TABLE `story` ADD PRIMARY KEY (`Story_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `achievement` MODIFY `Achievement_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `activity` MODIFY `Activity_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `admin` MODIFY `Admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `branch` MODIFY `Branch_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `contact_messages` MODIFY `Contact_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `donor` MODIFY `Donor_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `donor_achievement` MODIFY `DonorAchievement_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `headquarters` MODIFY `HQ_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `item_donation` MODIFY `Item_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `login_attempts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `orders` MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `password_resets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `payment` MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `point` MODIFY `Points_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `receipt` MODIFY `Receipt_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `recurring_donation` MODIFY `Recurring_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `redemption_order` MODIFY `Redemption_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `reward_item` MODIFY `Reward_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `special_case` MODIFY `Case_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `staff` MODIFY `Staff_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `staff_activity` MODIFY `StaffActivity_ID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `story` MODIFY `Story_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

ALTER TABLE `activity` ADD CONSTRAINT `Activity_BrANCH_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);
ALTER TABLE `branch` ADD CONSTRAINT `branch_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`);
ALTER TABLE `donor_achievement` ADD CONSTRAINT `DonorAchievement_Achievement_ID_FK` FOREIGN KEY (`Achievement_ID`) REFERENCES `achievement` (`Achievement_ID`), ADD CONSTRAINT `DonorAchievement_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);
ALTER TABLE `item_donation` ADD CONSTRAINT `Item_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`), ADD CONSTRAINT `Item_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);
ALTER TABLE `orders` ADD CONSTRAINT `Order_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`), ADD CONSTRAINT `Order_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`), ADD CONSTRAINT `Order_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`), ADD CONSTRAINT `Order_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`), ADD CONSTRAINT `Order_Payment_ID_FK` FOREIGN KEY (`Payment_ID`) REFERENCES `payment` (`Payment_ID`);
ALTER TABLE `point` ADD CONSTRAINT `Point_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);
ALTER TABLE `receipt` ADD CONSTRAINT `Receipt_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`), ADD CONSTRAINT `Receipt_Order_ID_FK` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`);
ALTER TABLE `recurring_donation` ADD CONSTRAINT `RecurringDonation_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);
ALTER TABLE `redemption_order` ADD CONSTRAINT `RedemptionOrder_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`), ADD CONSTRAINT `RedemptionOrder_Reward_ID_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`);
ALTER TABLE `staff` ADD CONSTRAINT `staff_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`), ADD CONSTRAINT `staff_branch_id_fk` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);
ALTER TABLE `staff_activity` ADD CONSTRAINT `StaffActivity_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`), ADD CONSTRAINT `StaffActivity_Staff_ID_FK` FOREIGN KEY (`Staff_ID`) REFERENCES `staff` (`Staff_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;