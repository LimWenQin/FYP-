-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2025-11-28 12:30:00
-- 服务器版本： 10.4.32-MariaDB
-- PHP 版本： 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `donation_system`
--

-- --------------------------------------------------------

--
-- 表的结构 `achievement`
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
-- 表的结构 `activity`
--

CREATE TABLE `activity` (
  `Activity_ID` int(11) NOT NULL,
  `Activity_Name` varchar(255) NOT NULL DEFAULT 'Default Activity Name', -- ✅ 新增
  `Activity_Date` date DEFAULT NULL,
  `Activity_StartDate` date DEFAULT NULL, -- ✅ 新增
  `Activity_EndDate` date DEFAULT NULL,   -- ✅ 新增
  `Activity_Details` text NOT NULL,
  `Activity_Picture` varchar(255) NOT NULL,
  `Activity_Status` varchar(50) NOT NULL,
  `Activity_GetAmount` decimal(10,2) NOT NULL,
  `Activity_TargetAmount` decimal(10,2) DEFAULT 0.00, -- ✅ 新增
  `Branch_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `address`
--

CREATE TABLE `address` (
  `Address_ID` int(11) NOT NULL,
  `Address_Line1` varchar(255) NOT NULL,
  `Address_Line2` varchar(255) DEFAULT NULL,
  `Address_Line3` varchar(255) DEFAULT NULL,
  `City` varchar(100) NOT NULL,
  `State` varchar(100) NOT NULL,
  `Postal_Code` varchar(20) NOT NULL,
  `Country` varchar(100) DEFAULT 'Malaysia',
  `Donor_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL,
  `Admin_FName` varchar(50) NOT NULL,
  `Admin_LName` varchar(50) NOT NULL,
  `Admin_ContactNumber` varchar(20) NOT NULL,
  `Admin_ICNUMBER` varchar(20) NOT NULL,
  `Admin_Email` varchar(100) NOT NULL,
  `Admin_Password` varchar(255) NOT NULL, -- ✅ 修改：长度改为 255
  `Admin_DOB` date NOT NULL,
  `Admin_Address` text NOT NULL,
  `Admin_ProfilePicture` varchar(255) DEFAULT NULL, -- ✅ 新增
  `Admin_Role` enum('Super Admin','Admin','Moderator') NOT NULL DEFAULT 'Admin', -- ✅ 新增
  `Admin_Status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Active', -- ✅ 新增
  `Admin_LastLogin` datetime DEFAULT NULL, -- ✅ 新增
  `Admin_CreatedAt` datetime DEFAULT current_timestamp(), -- ✅ 新增
  `Admin_UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(), -- ✅ 新增
  `Admin_Comment` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `branch`
--

CREATE TABLE `branch` (
  `Branch_ID` int(11) NOT NULL,
  `Branch_Name` varchar(100) NOT NULL,
  `Branch_Type` varchar(50) NOT NULL,
  `Branch_Address` text NOT NULL,
  `Branch_ContactNumber` varchar(20) NOT NULL,
  `Branch_Description` text NOT NULL,
  `Admin_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `donor`
--

CREATE TABLE `donor` (
  `Donor_ID` int(11) NOT NULL,
  `Donor_FName` varchar(50) NOT NULL,
  `Donor_LName` varchar(50) NOT NULL,
  `Donor_ContactNumber` varchar(20) NOT NULL,
  `Donor_ICNumber` varchar(20) NOT NULL,
  `Donor_Email` varchar(100) NOT NULL,
  `Donor_Password` varchar(255) NOT NULL,
  `Donor_Address` text NOT NULL,
  `Donor_DOB` date NOT NULL,
  `Donor_Description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `donor_achievement`
--

CREATE TABLE `donor_achievement` (
  `DonorAchievement_ID` int(11) NOT NULL,
  `DonorAchievement_AchievedAt` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Achievement_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `headquarters`
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
-- 表的结构 `item_donation`
--

CREATE TABLE `item_donation` (
  `Item_ID` int(11) NOT NULL,
  `Item_Name` varchar(100) NOT NULL,
  `Item_Quantity` int(11) NOT NULL,
  `Item_Condition` varchar(50) NOT NULL,
  `Item_Description` text NOT NULL,
  `Item_PhotoPath` varchar(255) NOT NULL,
  `Item_DropOff_Method` varchar(50) NOT NULL,
  `Item_Pickup_Address` text NOT NULL,
  `Item_Status` varchar(50) NOT NULL,
  `Item_ReceivedBy` varchar(100) NOT NULL,
  `Item_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Activity_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `login_attempts`
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
-- 表的结构 `orders`
--

CREATE TABLE `orders` (
  `Order_ID` int(11) NOT NULL,
  `Order_FName` varchar(50) NOT NULL,
  `Order_LName` varchar(50) NOT NULL,
  `Order_ContactNumber` varchar(20) NOT NULL,
  `Order_ICNumber` varchar(20) NOT NULL,
  `Order_Email` varchar(100) NOT NULL,
  `Order_Amount` decimal(10,2) NOT NULL,
  `Order_Points_Earned` int(11) DEFAULT 0, -- ✅ 新增
  `Order_Currency` varchar(10) NOT NULL,
  `Order_PaymentMethod` varchar(50) NOT NULL,
  `Order_PaymentStatus` varchar(50) NOT NULL,
  `Order_Admin_Status` varchar(50) DEFAULT 'Completed', -- ✅ 新增
  `Order_TXN_Ref` varchar(100) NOT NULL,
  `Order_Type` varchar(50) NOT NULL,
  `Order_Status` text NOT NULL,
  `Order_Created_At` datetime NOT NULL,
  `Order_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Payment_ID` int(11) DEFAULT NULL,
  `Branch_ID` int(11) DEFAULT NULL,
  `Activity_ID` int(11) NOT NULL,
  `Case_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `password_resets`
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
-- 表的结构 `payment`
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
-- 表的结构 `point`
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
-- 表的结构 `receipt`
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
-- 表的结构 `recurring_donation`
--

CREATE TABLE `recurring_donation` (
  `Recurring_ID` int(11) NOT NULL,
  `Recurring_Amount` decimal(10,2) NOT NULL,
  `Recurring_Payment_Method` varchar(50) NOT NULL,
  `Recurring_Deduction_Date` date NOT NULL,
  `Recurring_Status` varchar(50) NOT NULL,
  `Recurring_Created_At` datetime NOT NULL,
  `Recurring_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `redemption_order`
--

CREATE TABLE `redemption_order` (
  `Redemption_ID` int(11) NOT NULL,
  `Redemption_Address` text NOT NULL,
  `Redemption_ContactNumber` varchar(20) NOT NULL,
  `Redemption_PointsSpent` int(11) NOT NULL,
  `Redemption_Status` varchar(50) NOT NULL,
  `Redemption_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `reward_item`
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
-- 表的结构 `special_case`
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
-- 表的结构 `staff`
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
  `Staff_Address` text NOT NULL,
  `Staff_Comment` text NOT NULL,
  `Staff_Role` varchar(50) NOT NULL DEFAULT 'Staff', -- ✅ 新增
  `Staff_Status` varchar(50) NOT NULL DEFAULT 'Active', -- ✅ 新增
  `Branch_ID` int(11) DEFAULT NULL, -- ✅ 新增
  `Staff_JoinDate` date DEFAULT current_timestamp(), -- ✅ 新增
  `Staff_ProfilePicture` varchar(255) DEFAULT NULL, -- ✅ 新增
  `Admin_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `staff_activity`
--

CREATE TABLE `staff_activity` (
  `StaffActivity_ID` int(11) NOT NULL,
  `StaffActivity_Role` varchar(50) NOT NULL,
  `Staff_ID` int(11) NOT NULL,
  `Activity_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `story`
--

CREATE TABLE `story` (
  `Story_ID` int(11) NOT NULL,
  `Story_Date` date NOT NULL,
  `Donor_Description` text NOT NULL,
  `Story_Image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `contact_messages`
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

--
-- 转储表中的数据
--

--
-- 转储表中的数据 `admin`
--

INSERT INTO `admin` (`Admin_ID`, `Admin_FName`, `Admin_LName`, `Admin_ContactNumber`, `Admin_ICNUMBER`, `Admin_Email`, `Admin_Password`, `Admin_DOB`, `Admin_Address`, `Admin_ProfilePicture`, `Admin_Role`, `Admin_Status`, `Admin_LastLogin`, `Admin_CreatedAt`, `Admin_UpdatedAt`, `Admin_Comment`) VALUES
(1, 'Super', 'Admin', '0123456789', '990101010101', 'admin@lovebridge.org.my', 'admin123', '1999-01-01', 'Love Bridge HQ', NULL, 'Super Admin', 'Active', NULL, '2025-11-28 12:00:00', '2025-11-28 12:00:00', 'System Super Administrator');

--
-- 转储表中的数据 `headquarters`
--

INSERT INTO `headquarters` (`HQ_ID`, `HQ_Name`, `HQ_ContactNumber`, `HQ_Email`, `HQ_Address`, `HQ_Description`, `HQ_Story`, `HQ_FoundingDate`, `HQ_Image`, `Updated_At`) VALUES
(1, 'Love Bridge Headquarters', '+603-1234 5678', 'info@lovebridge.org.my', 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur, Malaysia', 'Love Bridge is a non-profit organization dedicated to helping those in need.', 'Founded in 2010, Love Bridge started with a small group of volunteers who wanted to make a difference. Over the years, we have grown into a nationwide organization supporting various causes including elderly care, orphanages, and disaster relief.', '2010-01-01', NULL, '2025-11-26 12:00:00');

--
-- 转储表的索引
--

--
-- 表的索引 `achievement`
--
ALTER TABLE `achievement`
  ADD PRIMARY KEY (`Achievement_ID`);

--
-- 表的索引 `activity`
--
ALTER TABLE `activity`
  ADD PRIMARY KEY (`Activity_ID`),
  ADD KEY `Activity_BrANCH_ID_FK` (`Branch_ID`);

--
-- 表的索引 `address`
--
ALTER TABLE `address`
  ADD PRIMARY KEY (`Address_ID`),
  ADD KEY `Address_Donor_ID_FK` (`Donor_ID`);

--
-- 表的索引 `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`Admin_ID`);

--
-- 表的索引 `branch`
--
ALTER TABLE `branch`
  ADD PRIMARY KEY (`Branch_ID`),
  ADD KEY `branch_admin_id_fk` (`Admin_ID`);

--
-- 表的索引 `donor`
--
ALTER TABLE `donor`
  ADD PRIMARY KEY (`Donor_ID`);

--
-- 表的索引 `donor_achievement`
--
ALTER TABLE `donor_achievement`
  ADD PRIMARY KEY (`DonorAchievement_ID`),
  ADD KEY `DonorAchievement_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `DonorAchievement_Achievement_ID_FK` (`Achievement_ID`);

--
-- 表的索引 `headquarters`
--
ALTER TABLE `headquarters`
  ADD PRIMARY KEY (`HQ_ID`);

--
-- 表的索引 `item_donation`
--
ALTER TABLE `item_donation`
  ADD PRIMARY KEY (`Item_ID`),
  ADD KEY `Item_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Item_Activity_ID_FK` (`Activity_ID`);

--
-- 表的索引 `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);

--
-- 表的索引 `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `Order_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Order_Activity_ID_FK` (`Activity_ID`),
  ADD KEY `Order_Payment_ID_FK` (`Payment_ID`),
  ADD KEY `Order_Case_ID_FK` (`Case_ID`);

--
-- 表的索引 `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- 表的索引 `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_ID`);

--
-- 表的索引 `point`
--
ALTER TABLE `point`
  ADD PRIMARY KEY (`Points_ID`),
  ADD KEY `Point_Donor_ID_FK` (`Donor_ID`);

--
-- 表的索引 `receipt`
--
ALTER TABLE `receipt`
  ADD PRIMARY KEY (`Receipt_ID`),
  ADD KEY `Receipt_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Receipt_Order_ID_FK` (`Order_ID`);

--
-- 表的索引 `recurring_donation`
--
ALTER TABLE `recurring_donation`
  ADD PRIMARY KEY (`Recurring_ID`),
  ADD KEY `RecurringDonation_Donor_ID_FK` (`Donor_ID`);

--
-- 表的索引 `redemption_order`
--
ALTER TABLE `redemption_order`
  ADD PRIMARY KEY (`Redemption_ID`),
  ADD KEY `RedemptionOrder_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `RedemptionOrder_Reward_ID_FK` (`Reward_ID`);

--
-- 表的索引 `reward_item`
--
ALTER TABLE `reward_item`
  ADD PRIMARY KEY (`Reward_ID`);

--
-- 表的索引 `special_case`
--
ALTER TABLE `special_case`
  ADD PRIMARY KEY (`Case_ID`);

--
-- 表的索引 `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`Staff_ID`),
  ADD KEY `staff_admin_id_fk` (`Admin_ID`),
  ADD KEY `staff_branch_id_fk` (`Branch_ID`);

--
-- 表的索引 `staff_activity`
--
ALTER TABLE `staff_activity`
  ADD PRIMARY KEY (`StaffActivity_ID`),
  ADD KEY `StaffActivity_Staff_ID_FK` (`Staff_ID`),
  ADD KEY `StaffActivity_Activity_ID_FK` (`Activity_ID`);

--
-- 表的索引 `story`
--
ALTER TABLE `story`
  ADD PRIMARY KEY (`Story_ID`);

--
-- 表的索引 `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`Contact_ID`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `achievement`
--
ALTER TABLE `achievement`
  MODIFY `Achievement_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `activity`
--
ALTER TABLE `activity`
  MODIFY `Activity_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `address`
--
ALTER TABLE `address`
  MODIFY `Address_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin`
--
ALTER TABLE `admin`
  MODIFY `Admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `branch`
--
ALTER TABLE `branch`
  MODIFY `Branch_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `donor`
--
ALTER TABLE `donor`
  MODIFY `Donor_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `donor_achievement`
--
ALTER TABLE `donor_achievement`
  MODIFY `DonorAchievement_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `headquarters`
--
ALTER TABLE `headquarters`
  MODIFY `HQ_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `item_donation`
--
ALTER TABLE `item_donation`
  MODIFY `Item_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `point`
--
ALTER TABLE `point`
  MODIFY `Points_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `receipt`
--
ALTER TABLE `receipt`
  MODIFY `Receipt_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `recurring_donation`
--
ALTER TABLE `recurring_donation`
  MODIFY `Recurring_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `redemption_order`
--
ALTER TABLE `redemption_order`
  MODIFY `Redemption_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `reward_item`
--
ALTER TABLE `reward_item`
  MODIFY `Reward_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `special_case`
--
ALTER TABLE `special_case`
  MODIFY `Case_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `staff`
--
ALTER TABLE `staff`
  MODIFY `Staff_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `staff_activity`
--
ALTER TABLE `staff_activity`
  MODIFY `StaffActivity_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `story`
--
ALTER TABLE `story`
  MODIFY `Story_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `Contact_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 限制导出的表
--

--
-- 限制表 `activity`
--
ALTER TABLE `activity`
  ADD CONSTRAINT `Activity_BrANCH_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

--
-- 限制表 `address`
--
ALTER TABLE `address`
  ADD CONSTRAINT `Address_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `branch`
--
ALTER TABLE `branch`
  ADD CONSTRAINT `branch_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`);

--
-- 限制表 `donor_achievement`
--
ALTER TABLE `donor_achievement`
  ADD CONSTRAINT `DonorAchievement_Achievement_ID_FK` FOREIGN KEY (`Achievement_ID`) REFERENCES `achievement` (`Achievement_ID`),
  ADD CONSTRAINT `DonorAchievement_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- 限制表 `item_donation`
--
ALTER TABLE `item_donation`
  ADD CONSTRAINT `Item_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `Item_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- 限制表 `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `Order_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `Order_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `Order_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `Order_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `Order_Payment_ID_FK` FOREIGN KEY (`Payment_ID`) REFERENCES `payment` (`Payment_ID`);

--
-- 限制表 `point`
--
ALTER TABLE `point`
  ADD CONSTRAINT `Point_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- 限制表 `receipt`
--
ALTER TABLE `receipt`
  ADD CONSTRAINT `Receipt_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `Receipt_Order_ID_FK` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`);

--
-- 限制表 `recurring_donation`
--
ALTER TABLE `recurring_donation`
  ADD CONSTRAINT `RecurringDonation_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- 限制表 `redemption_order`
--
ALTER TABLE `redemption_order`
  ADD CONSTRAINT `RedemptionOrder_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `RedemptionOrder_Reward_ID_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`);

--
-- 限制表 `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`),
  ADD CONSTRAINT `staff_branch_id_fk` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

--
-- 限制表 `staff_activity`
--
ALTER TABLE `staff_activity`
  ADD CONSTRAINT `StaffActivity_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `StaffActivity_Staff_ID_FK` FOREIGN KEY (`Staff_ID`) REFERENCES `staff` (`Staff_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;