-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2026-01-18 08:40:36
-- 服务器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

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
-- 表的结构 `about_us_info`
--

CREATE TABLE `about_us_info` (
  `id` int(11) NOT NULL,
  `hero_title` varchar(255) DEFAULT NULL,
  `hero_description` text DEFAULT NULL,
  `story_title` varchar(255) DEFAULT 'Our Story',
  `story_content` text DEFAULT NULL,
  `vision_title` varchar(255) DEFAULT 'Our Vision',
  `vision_desc` text DEFAULT NULL,
  `vision_points` longtext DEFAULT NULL,
  `mission_title` varchar(255) DEFAULT 'Our Mission',
  `mission_desc` text DEFAULT NULL,
  `mission_points` longtext DEFAULT NULL,
  `core_values` longtext DEFAULT NULL,
  `focus_areas` longtext DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `about_us_info`
--

INSERT INTO `about_us_info` (`id`, `hero_title`, `hero_description`, `story_title`, `story_content`, `vision_title`, `vision_desc`, `vision_points`, `mission_title`, `mission_desc`, `mission_points`, `core_values`, `focus_areas`, `updated_at`) VALUES
(1, 'Building Bridges of Love and Hope', 'Love Bridge Foundation is a non-profit organization dedicated to creating positive change through compassion, care, and community support. Since 2010, we\'ve been connecting generous hearts with those in need.', 'Love Bridge Foundation', 'Founded in 2010, Love Bridge started as a small community initiative by a group of passionate volunteers who believed in the power of collective kindness. What began as a simple act of helping a few families in need has grown into a nationwide movement touching thousands of lives.', 'Our Vision', 'To create a world where compassion bridges every gap, and no one is left behind in times of need. We envision communities where every individual has access to basic necessities, healthcare, education, and opportunities for a dignified life.', '[\"Build sustainable support systems for vulnerable communities\",\"Create bridges of hope between donors and recipients\",\"Foster a culture of giving and social responsibility\",\"Ensure every donation creates maximum impact\"]', 'Our Mission', 'To efficiently connect compassionate donors with credible causes through a transparent, secure, and user-friendly platform. We are committed to ensuring that every contribution, big or small, reaches those who need it most.', '[\"Provide immediate relief during emergencies and crises\",\"Support long-term community development projects\",\"Maintain 100% transparency in fund allocation\",\"Engage and empower volunteers in meaningful work\",\"Educate and raise awareness about social issues\"]', '[{\"title\":\"Compassion\",\"desc\":\"We approach every situation with empathy, understanding, and a genuine desire to alleviate suffering.\"},{\"title\":\"Integrity\",\"desc\":\"We maintain the highest standards of transparency and accountability in all our operations.\"},{\"title\":\"Sustainability\",\"desc\":\"We create programs that provide long-term solutions, not just temporary relief.\"},{\"title\":\"Community\",\"desc\":\"We believe in the power of collective action and community-driven solutions.\"},{\"title\":\"Innovation\",\"desc\":\"We continuously seek new and better ways to serve and make a lasting impact.\"},{\"title\":\"Respect\",\"desc\":\"We honor the dignity of every individual we serve, regardless of their circumstances.\"}]', '[{\"title\":\"Children & Orphanages\",\"desc\":\"Supporting orphanages, educational programs, nutrition initiatives, and healthcare for underprivileged children.\"},{\"title\":\"Elderly Care\",\"desc\":\"Providing companionship, medical support, and basic necessities for senior citizens in need.\"},{\"title\":\"Stray Animals\",\"desc\":\"Take care of the animals by providing them with food, shelter, and medical care.\"},{\"title\":\"Education Support\",\"desc\":\"Scholarships, school supplies, and learning facilities for children from low-income families.\"},{\"title\":\"Medical Aid\",\"desc\":\"Assistance with medical bills, surgeries, and essential healthcare services for those who cannot afford them.\"},{\"title\":\"Community Development\",\"desc\":\"Empowering communities through skills training, micro-enterprises, and infrastructure projects.\"}]', '2026-01-13 23:01:26');

-- --------------------------------------------------------

--
-- 表的结构 `activity`
--

CREATE TABLE `activity` (
  `Activity_ID` int(11) NOT NULL,
  `Activity_Name` varchar(255) NOT NULL DEFAULT 'Default Activity Name',
  `Activity_Venue` varchar(255) DEFAULT NULL,
  `Activity_Date` date DEFAULT NULL,
  `Activity_StartDate` date DEFAULT NULL,
  `Activity_EndDate` date DEFAULT NULL,
  `Activity_Description` text DEFAULT NULL,
  `Activity_Organizer` varchar(100) DEFAULT NULL,
  `Activity_Contact_Name` varchar(100) DEFAULT NULL,
  `Activity_Contact_Number` varchar(20) DEFAULT NULL,
  `Activity_Contact_Email` varchar(100) DEFAULT NULL,
  `Activity_Max_Participants` int(11) DEFAULT 0,
  `Activity_Images` longtext DEFAULT NULL,
  `Activity_Status` varchar(50) NOT NULL,
  `Activity_BankName` varchar(100) DEFAULT NULL,
  `Activity_BankAccount` varchar(50) DEFAULT NULL,
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
  `Cancel_Reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `activity`
--

INSERT INTO `activity` (`Activity_ID`, `Activity_Name`, `Activity_Venue`, `Activity_Date`, `Activity_StartDate`, `Activity_EndDate`, `Activity_Description`, `Activity_Organizer`, `Activity_Contact_Name`, `Activity_Contact_Number`, `Activity_Contact_Email`, `Activity_Max_Participants`, `Activity_Images`, `Activity_Status`, `Activity_BankName`, `Activity_BankAccount`, `Activity_GetAmount`, `Activity_TargetAmount`, `Activity_Address1`, `Activity_Address2`, `Activity_Address3`, `Activity_City`, `Activity_State`, `Activity_PostalCode`, `Activity_Country`, `Branch_ID`, `Cancel_Reason`) VALUES
(1, 'Hope in Ashes - Sentul Kampung Fire Reconstruction', 'Sentul Community Hall', NULL, '2026-02-01', '2026-03-01', 'A sudden tragedy struck the squatter area of Sentul early yesterday morning, where a fire engulfed 30 wooden houses in just two hours, leaving over 120 residents homeless. Most victims are from low-income B40 families for whom this loss is devastating. Love Bridge is collaborating with local leaders to launch an emergency relief plan. Donations will provide RM500 emergency rental subsidies per family, essential household items like mattresses and cooking utensils, and school supplies for 40 affected children to ensure their education is not interrupted. We are also accepting donations of new clothing at our KL HQ.', 'Love Bridge Urban Aid', 'Ms. Sarah Lim (Project Coordinator)', '+6016-1234567', 'urban.aid@lovebridge.org.my', 0, '[\"uploads\\/activities\\/act_1768129494_696383d67fe11_0.png\",\"uploads\\/activities\\/act_1768129494_696383d681da0_1.png\"]', 'Active', NULL, NULL, 11827.75, 50000.00, 'Dewan Komuniti Sentul Pasar', 'Jalan Sentul Manis', 'Kampung Sentul Pasar', 'Kuala Lumpur', 'Kuala Lumpur', '51000', 'Malaysia', 2, NULL),
(2, '\"Dreams Take Flight\" - Weekend Care Day', 'Four Ace Sanctuary', NULL, '2026-02-14', '2026-02-14', 'The 45 children at our sanctuary each have unique and difficult backgrounds, but they all share a longing for love and attention. This Valentine\'s Day weekend, we invite the public to spread love by joining us for the \"Dreams Take Flight\" party. The event will include ice-breaking games, a \"Home of My Dreams\" drawing contest, story-sharing sessions, and a special KFC lunch treat. We are looking for 20 patient and fully vaccinated volunteers to spend the afternoon bringing joy to these children.', 'Johor Youth Volunteers', 'Mrs. Sarah Wong (Branch Head)', '+6012-3456781', 'fourace@lovebridge.org.my', 20, '[\"uploads\\/activities\\/act_1768129933_6963858d70e61_0.png\",\"uploads\\/activities\\/act_1768129933_6963858d72c0b_1.png\"]', 'Upcoming', NULL, NULL, 0.00, 5000.00, '22, Jalan Impian Emas 4', 'Taman Impian Emas', 'Skudai', 'Johor Bahru', 'Johor', '81300', 'Malaysia', 1, NULL),
(3, '2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', 'Flood Relief Center (PPS) SK Kota Bharu', NULL, '2026-01-15', '2026-02-15', 'The relentless Northeast Monsoon rains have caused severe flooding across low-lying areas in Kelantan and Terengganu, marking the worst flood in a decade. Over 5,000 families have been displaced to temporary relief centers, many evacuating with nothing but the clothes on their backs. Love Bridge has urgently launched \"Operation Ark,\" mobilizing our Quick Response Team (QRT) to deploy aid. Funds raised will be strictly used to purchase heavy-duty inflatable boats for rescue missions, clean water tablets, dry food packs, and hygiene kits to prevent post-disaster disease outbreaks. We are also seeking volunteers with 4x4 vehicles to assist in transportation.', 'Love Bridge Crisis Response Team', 'Mr. David Lee (Branch Manager)', '+6012-3456782', 'crisis@lovebridge.org.my', 0, '[\"uploads\\/activities\\/act_1768129368_6963835879e82_0.png\",\"uploads\\/activities\\/act_1768129368_696383587a5c4_1.png\"]', 'Active', NULL, NULL, 63729.96, 100000.00, 'Sekolah Kebangsaan Kota Bharu', 'Jalan Hospital', 'Pusat Pemindahan Banjir (PPS)', 'Kota Bharu', 'Kelantan', '15000', 'Malaysia', 2, NULL),
(4, '\"Quiet Years\" - CNY Senior Celebration', 'Silver Years Haven', '2026-02-10', '2026-02-10', '2026-02-10', 'Festive seasons can be the loneliest times for the elderly in care homes. To bring the warmth of family to them this Chinese New Year, we are transforming Silver Years Haven into a 1960s-style \"Nanyang Dance Hall.\" The celebration will feature free festive haircuts, health screenings, a karaoke session featuring classic hits, and the distribution of Ang Paos and mandarin oranges. Funds raised will cover the costs of a traditional Poon Choi feast, decorations, and nutritional supplies like milk powder. Musicians are welcome to volunteer and perform!', 'Penang Care Team', 'Mr. Ronald Tan (Branch Head)', '+6011-11190233', 'penang@lovebridge.org.my', -1, '[\"uploads\\/activities\\/act_1768129984_696385c05f140_0.png\",\"uploads\\/activities\\/act_1768129984_696385c05fc84_1.png\",\"uploads\\/activities\\/act_1768663698_696baa928e7d1_0.jpg\"]', 'Active', 'CIMB', '7070169114', 5354.66, 8000.00, '15, Jalan Georgetown', 'Heritage Zone', '', 'George Town', 'Penang', '10200', 'Malaysia', 3, NULL),
(5, 'Batang Kali Landslide - Psychological & Medical Aid', 'Batang Kali Site (Forward Base)', NULL, '2025-12-15', '2026-01-15', 'Following the tragic landslide at the campsite, the nation is in mourning. While search and rescue operations have concluded, the road to recovery for survivors is just beginning. Many survivors face complex surgeries for fractures, while the families of the victims are enduring immense psychological trauma. This completed fundraising campaign focused on funding medical treatments and mobility aids for the severely injured, as well as providing professional PTSD counseling for affected families to help them navigate through their grief and trauma.', 'Love Bridge QRT (Quick Response Team)', 'Dr. Wong (Medical Volunteer Lead)', '+6019-9988776', 'medical@lovebridge.org.my', 0, '[\"uploads\\/activities\\/act_1768129618_6963845210b3c_0.png\",\"uploads\\/activities\\/act_1768129618_696384521266e_1.png\"]', 'Completed', NULL, NULL, 30000.00, 30000.00, 'Father\'s Organic Farm Site', 'Jalan Batang Kali - Genting Highlands', 'Hulu Selangor District', 'Batang Kali', 'Selangor', '44300', 'Malaysia', 2, NULL),
(6, '2026 Back to School - Aid for Underprivileged Students', 'Four Ace Sanctuary Hall (Distribution Center)', NULL, '2026-01-12', '2026-05-01', 'With the rising cost of living, many B40 families struggle significantly during the school reopening season. We are proud to announce the successful conclusion of this year\'s \"Back to School\" initiative, which provided 200 primary students from rural villages around Johor with complete school supplies. Each student received a care package worth RM150 containing uniforms, branded school shoes, a spinal-support backpack, and a year\'s supply of stationery. By providing these essentials, we aim to give these children the confidence to walk into school with their heads held high.', 'Love Bridge Education Fund', 'Cikgu Siti (Community Liaison)', '+6013-5566778', 'edu@lovebridge.org.my', 0, '[\"uploads\\/activities\\/act_1768130006_696385d6bbe11_0.png\",\"uploads\\/activities\\/act_1768130006_696385d6be46b_1.png\"]', 'Completed', NULL, NULL, 30000.00, 30000.00, '22, Jalan Impian Emas 4', 'Taman Impian Emas', 'Skudai', 'Johor Bahru', 'Johor', '81300', 'Malaysia', 1, NULL),
(7, 'City Light - KL Soup Kitchen Night Distribution', 'Medan Selera Pudu (Distribution Point)', NULL, '2026-02-01', '2026-12-31', 'Hidden beneath the city\'s neon lights are the homeless and urban poor who go hungry every night. This is a year-long initiative where Love Bridge\'s food truck visits Pudu and Chow Kit every Friday night to distribute aid. We provide 500 sets of hot nutritious meals, mineral water, and fruits, while our volunteers offer basic wound dressing services and distribute second-hand clothing. A donation of just RM10 can sponsor a warm meal for someone in need. We welcome corporate sponsors to adopt a week of distribution.', 'KL Soup Kitchen Crew', 'Mr. Jason Teoh (Operations Lead)', '+6017-7788990', 'soupkitchen@lovebridge.org.my', 50, '[\"uploads\\/activities\\/act_1768130263_696386d77bbef_0.png\",\"uploads\\/activities\\/act_1768130263_696386d77cde2_1.png\"]', 'Active', NULL, NULL, 64711.83, 120000.00, 'Open Car Park Area', 'Jalan Pudu', 'Near Pudu LRT Station', 'Kuala Lumpur', 'Kuala Lumpur', '55100', 'Malaysia', 2, NULL),
(8, 'Community Health Day - Free Checkup & Physio', 'Silver Years Haven Hall', '2026-03-15', '2026-03-15', '2026-03-15', 'Many elderly individuals living alone neglect their health due to financial constraints or lack of transport. In collaboration with volunteer doctors from Penang General Hospital, Silver Years Haven is hosting a \"Community Health Day\" open to the public. Services include free screenings for blood sugar, blood pressure, and cholesterol, as well as one-on-one medical consultations. A physiotherapist will also lead a workshop on fall prevention exercises. We are raising funds to purchase test strips, medical consumables, and reading glasses to be given away to low-income seniors.', 'Penang Medical Volunteers', 'Dr. Lim (Volunteer Doctor)', '+6012-2233445', 'health@lovebridge.org.my', 100, '[\"uploads\\/activities\\/act_1768130286_696386ee2c482_0.png\",\"uploads\\/activities\\/act_1768130286_696386ee2e3e2_1.png\"]', 'Upcoming', '', '', 0.00, 3000.00, '15, Jalan Georgetown', 'Heritage Zone', 'George Town', 'George Town', 'Penang', '10200', 'Malaysia', 3, NULL);

--
-- 触发器 `activity`
--
DELIMITER $$
CREATE TRIGGER `notify_activity_insert` AFTER INSERT ON `activity` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('New Activity Created: ', NEW.Activity_Name), 'Target', 'activity_management.php', 0, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_activity_update` AFTER UPDATE ON `activity` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('Activity Updated: ', NEW.Activity_Name), 'Update', 'activity_management.php', 0, NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL,
  `Admin_Name` varchar(100) NOT NULL,
  `Admin_ContactNumber` varchar(20) NOT NULL,
  `Admin_ICNUMBER` varchar(20) NOT NULL,
  `Admin_Email` varchar(100) NOT NULL,
  `Admin_Password` varchar(255) DEFAULT NULL,
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
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted',
  `Admin_IsFirstLogin` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `admin`
--

INSERT INTO `admin` (`Admin_ID`, `Admin_Name`, `Admin_ContactNumber`, `Admin_ICNUMBER`, `Admin_Email`, `Admin_Password`, `Admin_DOB`, `Admin_Address1`, `Admin_Address2`, `Admin_Address3`, `Admin_City`, `Admin_State`, `Admin_PostalCode`, `Admin_Country`, `Admin_ProfilePicture`, `Admin_Role`, `Admin_Status`, `Admin_LastLogin`, `Admin_CreatedAt`, `Admin_UpdatedAt`, `Admin_Comment`, `Admin_LoginAttempts`, `Admin_LastFailedLogin`, `Is_Deleted`, `Admin_IsFirstLogin`) VALUES
(1, 'Super Admin', '0123456789', '990101010101', 'admin@lovebridge.org.my', 'admin123', '1999-01-01', 'Level 12', 'Menara Love Bridge', 'Jalan Charity', 'Kuala Lumpur', 'Wilayah Persekutuan', '50000', 'Malaysia', NULL, 'Super Admin', 'Active', '2025-12-06 20:35:49', '2025-11-28 12:00:00', '2026-01-15 09:00:45', 'System Super Administrator', 3, '2026-01-15 09:00:45', 0, 1),
(2, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', '$2y$10$yRnaaAeLqift7YsGSVjF8.70L7.tQMHtDFrLkD7gbeapVr4KLfHR2', '2003-05-17', '11， jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', 'uploads/profiles/admin_2_1767838052.jpg', 'Super Admin', 'Active', '2026-01-18 13:00:06', '2025-12-06 17:31:10', '2026-01-18 13:00:06', 'Added via admin management system', 0, '2026-01-16 21:22:57', 0, 0),
(5, 'ujin', '+6019-9878299', '050218-05-1234', 'ujintan218@gmail.com', '$2y$10$1BvJVRIgBpkgfPOpeiXBOulnxttZFxJKfHEOtMyImfaLzjSUvkJhO', '2005-02-18', '', '', '', '', '', '', 'Malaysia', NULL, 'Admin', 'Active', '2026-01-08 22:00:05', '2026-01-08 21:55:12', '2026-01-08 23:33:10', '', 0, NULL, 0, 0),
(6, 'Lim Wen Qin', '+6011-19848732', '060504-03-0201', 'qinwenlin989@gmail.com', '$2y$10$CpJi2tClyKoRBpDt4sR4B.QRW/yTjENkBVnKLReWKaGGts1eAKAUy', '2006-05-04', '', '', '', '', '', '', 'Malaysia', 'uploads/profiles/admin_1768558087_696a0e076968e.jpeg', 'Admin', 'Active', '2026-01-14 14:05:52', '2026-01-09 13:44:39', '2026-01-16 18:08:07', '', 0, NULL, 0, 0);

-- --------------------------------------------------------

--
-- 表的结构 `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `AdminNotification_ID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `Contact_ID` int(11) DEFAULT NULL,
  `Type` varchar(50) NOT NULL,
  `Link` varchar(255) DEFAULT NULL,
  `Is_Read` tinyint(1) DEFAULT 0,
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0,
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `admin_notifications`
--

INSERT INTO `admin_notifications` (`AdminNotification_ID`, `Message`, `Contact_ID`, `Type`, `Link`, `Is_Read`, `Is_Deleted`, `Created_At`) VALUES
(1, 'New Donor Registered: thong', NULL, 'New_Donor', 'admin_donor_page.php', 1, 1, '2026-01-11 01:09:57'),
(2, 'Activity Updated: 2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 15:06:04'),
(3, 'Activity Updated: Hope in Ashes - Sentul Kampung Fire Reconstruction', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 15:09:54'),
(4, 'Activity Updated: Batang Kali Landslide - Psychological & Medical Aid', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 16:00:13'),
(5, 'Activity Updated: \"Dreams Take Flight\" - Weekend Care Day', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 16:04:55'),
(6, 'Activity Updated: \"Quiet Years\" - CNY Senior Celebration', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 16:08:08'),
(7, 'New Activity Created: 2026 Back to School - Aid for Underprivileged Students', NULL, 'Target', 'activity_management.php', 1, 1, '2026-01-11 16:12:06'),
(8, 'New Activity Created: City Light - KL Soup Kitchen Night Distribution', NULL, 'Target', 'activity_management.php', 1, 1, '2026-01-11 16:14:48'),
(9, 'New Activity Created: Community Health Day - Free Checkup & Physio', NULL, 'Target', 'activity_management.php', 1, 1, '2026-01-11 16:17:01'),
(10, 'New Fundraising Case: Shattered Hoop Dreams - Emergency Chemo Fund for Jason', NULL, 'Target', NULL, 1, 1, '2026-01-11 16:44:25'),
(11, 'New Fundraising Case: Fighting for Love - Single Mom\'s Last Hope', NULL, 'Target', NULL, 1, 1, '2026-01-11 16:46:33'),
(12, 'New Fundraising Case: Rebirth from Fire - Skin Graft Surgery for Xiao Mei', NULL, 'Target', NULL, 1, 1, '2026-01-11 16:48:45'),
(13, 'New Fundraising Case: Child of the Moon\" - Aid for Albino Student Aman', NULL, 'Target', NULL, 1, 1, '2026-01-11 16:52:39'),
(14, 'Activity Updated: 2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:02:48'),
(15, 'Activity Updated: Hope in Ashes - Sentul Kampung Fire Reconstruction', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:04:54'),
(16, 'Activity Updated: Batang Kali Landslide - Psychological & Medical Aid', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:06:58'),
(17, 'Activity Updated: \"Dreams Take Flight\" - Weekend Care Day', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:12:13'),
(18, 'Activity Updated: \"Quiet Years\" - CNY Senior Celebration', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:13:04'),
(19, 'Activity Updated: 2026 Back to School - Aid for Underprivileged Students', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:13:26'),
(20, 'Activity Updated: City Light - KL Soup Kitchen Night Distribution', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:17:43'),
(21, 'Activity Updated: Community Health Day - Free Checkup & Physio', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 19:18:06'),
(22, 'New Donor Registered: RONALD TAN BIN HONG', NULL, 'New_Donor', 'admin_donor_page.php', 1, 1, '2026-01-11 21:21:48'),
(23, 'Activity Updated: Batang Kali Landslide - Psychological & Medical Aid', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:05'),
(24, 'Activity Updated: 2026 Back to School - Aid for Underprivileged Students', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:05'),
(25, 'Activity Updated: \"Dreams Take Flight\" - Weekend Care Day', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:05'),
(26, 'Activity Updated: Community Health Day - Free Checkup & Physio', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:05'),
(27, 'Activity Updated: Hope in Ashes - Sentul Kampung Fire Reconstruction', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:06'),
(28, 'Activity Updated: 2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:06'),
(29, 'Activity Updated: \"Quiet Years\" - CNY Senior Celebration', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:06'),
(30, 'Activity Updated: City Light - KL Soup Kitchen Night Distribution', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:40:06'),
(31, 'New Donation:  100.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-11 21:41:44'),
(32, 'New Donation: MYR 20.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-11 21:41:57'),
(33, 'New Donation: MYR 50.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-11 21:53:38'),
(34, 'Case Update: Rebirth from Fire - Skin Graft Surgery for Xiao Mei raised RM50.00', NULL, 'Donation', NULL, 1, 1, '2026-01-11 21:53:38'),
(35, 'New Donation: MYR 50.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-11 21:56:56'),
(36, 'Activity Updated: 2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', NULL, 'Update', 'activity_management.php', 1, 1, '2026-01-11 21:56:56'),
(38, 'New Donation: MYR 20.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:21:32'),
(39, 'New Donation: MYR 50.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:23:11'),
(40, 'Case Update: Rebirth from Fire - Skin Graft Surgery for Xiao Mei raised RM100.00', NULL, 'Donation', NULL, 1, 1, '2026-01-12 12:23:11'),
(44, 'New Donation:  20.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:31:19'),
(45, 'New Donation:  20.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:35:36'),
(46, 'New Donation:  50.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:43:27'),
(47, 'New Donation:  50.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:55:21'),
(48, 'New Donation: MYR 100.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:55:47'),
(49, 'Case Update: Fighting for Love - Single Mom\'s Last Hope raised RM100.00', NULL, 'Donation', NULL, 1, 1, '2026-01-12 12:55:47'),
(50, 'New Donation: MYR 50.00 from RONALD TAN BIN HONG', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-12 12:57:36'),
(51, 'New Donor Registered: thong', NULL, 'New_Donor', 'admin_donor_page.php', 1, 1, '2026-01-15 10:03:57'),
(52, 'New Donation: MYR 60000.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-15 10:16:20'),
(53, 'Case Update: Fighting for Love - Single Mom\'s Last Hope raised RM60100.00', NULL, 'Donation', NULL, 1, 1, '2026-01-15 10:16:24'),
(54, 'New Donation:  50.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-15 10:19:37'),
(55, 'New Donation: MYR 30.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 1, '2026-01-15 10:30:29'),
(56, 'New Recurring Donation Setup: RM30.00', NULL, 'Donation', NULL, 1, 1, '2026-01-15 10:30:29'),
(57, 'New Reward Redemption Order #3', NULL, 'Target', 'redemption_order_management.php', 1, 1, '2026-01-15 10:33:20'),
(58, 'New Donor Registered: john', NULL, 'New_Donor', 'admin_donor_page.php', 1, 0, '2026-01-15 10:48:37'),
(59, 'Redemption Order #3 is now Pending', NULL, 'Update', NULL, 1, 0, '2026-01-15 18:01:14'),
(60, 'New Staff Member Added: thong', NULL, 'New_Staff', 'staff_management_page.php', 1, 0, '2026-01-15 22:01:39'),
(61, 'New Donor Registered: thong', NULL, 'New_Donor', 'admin_donor_page.php', 1, 0, '2026-01-15 22:03:08'),
(62, 'New Staff Member Added: jojo', NULL, 'New_Staff', 'staff_management_page.php', 1, 0, '2026-01-16 17:53:19'),
(63, 'New Donor Registered: thng', NULL, 'New_Donor', 'admin_donor_page.php', 1, 0, '2026-01-17 09:28:27'),
(64, 'New Donor Registered: thong', NULL, 'New_Donor', 'admin_donor_page.php', 1, 0, '2026-01-17 21:13:55'),
(65, 'New Donation: MYR 50.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 0, '2026-01-17 21:17:54'),
(66, 'New Recurring Donation Setup: RM50.00', NULL, 'Donation', NULL, 1, 0, '2026-01-17 21:17:54'),
(67, 'New Donation: MYR 50.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 0, '2026-01-17 21:19:06'),
(68, 'Case Update: Rebirth from Fire - Skin Graft Surgery for Xiao Mei raised RM150.00', NULL, 'Donation', NULL, 1, 0, '2026-01-17 21:19:06'),
(69, 'New Donation:  300.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 0, '2026-01-17 21:21:16'),
(70, 'New Donation:  300.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 0, '2026-01-17 21:21:34'),
(71, 'New Donation: MYR 100.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 0, '2026-01-17 21:22:01'),
(72, 'Case Update: Operation \"Fix A Heart\" - Urgent Fund for Baby Ali raised RM1400.00', NULL, 'Donation', NULL, 1, 0, '2026-01-17 21:22:01'),
(73, 'New Donation: MYR 100.00 from thong', NULL, 'Donation', 'payment_management.php', 1, 0, '2026-01-17 21:23:24'),
(74, 'Activity Updated: 2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', NULL, 'Update', 'activity_management.php', 1, 0, '2026-01-17 21:23:24'),
(75, 'Activity Updated: Community Health Day - Free Checkup & Physio', NULL, 'Update', 'activity_management.php', 1, 0, '2026-01-17 23:12:00'),
(76, 'Activity Updated: \"Quiet Years\" - CNY Senior Celebration', NULL, 'Update', 'activity_management.php', 1, 0, '2026-01-17 23:28:18'),
(77, 'Activity Updated: \"Quiet Years\" - CNY Senior Celebration', NULL, 'Update', 'activity_management.php', 1, 0, '2026-01-17 23:30:40'),
(78, 'New Activity Created: 123', NULL, 'Target', 'activity_management.php', 1, 0, '2026-01-17 23:47:31'),
(79, 'Activity Updated: \"Quiet Years\" - CNY Senior Celebration', NULL, 'Update', 'activity_management.php', 1, 0, '2026-01-18 00:20:27'),
(80, 'New Fundraising Case: farhan', NULL, 'Target', NULL, 1, 0, '2026-01-18 03:23:52');

-- --------------------------------------------------------

--
-- 表的结构 `branch`
--

CREATE TABLE `branch` (
  `Branch_ID` int(11) NOT NULL,
  `Branch_Name` varchar(100) NOT NULL,
  `Branch_Head` varchar(100) NOT NULL,
  `Branch_Head_Contact` varchar(20) DEFAULT NULL,
  `Branch_Head_Email` varchar(100) DEFAULT NULL,
  `Branch_Type` enum('Old Folks Home','Orphanage','Disabled Care Center','Headquarters','Branch') NOT NULL,
  `Branch_Capacity` int(11) NOT NULL DEFAULT 0,
  `Branch_EstablishedDate` date DEFAULT NULL,
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
  `Branch_Images` text DEFAULT NULL,
  `Admin_ID` int(11) NOT NULL,
  `Branch_OperationalStatus` enum('Open','Closed') DEFAULT 'Open',
  `Branch_BankName` varchar(100) DEFAULT NULL,
  `Branch_BankAccount` varchar(50) DEFAULT NULL,
  `Branch_CreatedAt` datetime DEFAULT current_timestamp(),
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `branch`
--

INSERT INTO `branch` (`Branch_ID`, `Branch_Name`, `Branch_Head`, `Branch_Head_Contact`, `Branch_Head_Email`, `Branch_Type`, `Branch_Capacity`, `Branch_EstablishedDate`, `Branch_Address1`, `Branch_Address2`, `Branch_Address3`, `Branch_City`, `Branch_State`, `Branch_PostalCode`, `Branch_Country`, `Branch_ContactNumber`, `Branch_Email`, `Branch_Description`, `Branch_Images`, `Admin_ID`, `Branch_OperationalStatus`, `Branch_BankName`, `Branch_BankAccount`, `Branch_CreatedAt`, `Is_Deleted`) VALUES
(1, 'Love Bridge Four Ace Sanctuary', 'Mrs. Sarah Wong', '+6012-3456781', 'sarah.wong@lovebridge.org.my', 'Orphanage', 45, '2015-06-01', '22, Jalan Impian Emas 4, ', 'Taman Impian Emas, ', '', 'Skudai', 'Johor', '81300', 'Malaysia', '+6011-1190233', 'fourace@lovebridge.org.my', 'Founded in 2015 and located in Skudai, Johor, the Four Ace Sanctuary is more than just a shelter; it is a home built on four core promises to our children: Academic excellence, Character building, Emotional health, and Social development. The facility features a comforting living area, a \"Digital Hope Laboratory\" equipped for computer learning, and an organic vegetable garden. We are dedicated to providing holistic education and care, committed to nurturing vulnerable children into the confident future leaders of tomorrow.', '[\"uploads/branches/br_1768128819_696381332f9bc_0.png\",\"uploads/branches/br_1768128849_696381517fb2d_0.jpg\",\"uploads/branches/br_1768137820_6963a45cef105_0.png\"]', 2, 'Open', NULL, NULL, '2025-12-15 23:02:23', 0),
(2, 'KL Main Operations Hub', 'Mr. David Lee', '+6012-3456782', 'david.lee@lovebridge.org.my', 'Headquarters', 8, '2010-01-15', 'Ground Floor, Menara Love Bridge, ', 'Jalan Charity, Pudu, ', '', 'Kuala Lumpur', 'Kuala Lumpur', '55100 ', 'Malaysia', '+6011-12345633', 'admin@donationsystem.com', 'Serving as the operational heartbeat of the organization in the city center, this branch focuses on aiding the urban poor and managing disaster relief efforts. It operates a daily Soup Kitchen to feed the homeless, manages a central Food Bank warehouse for resource distribution, and houses the Quick Response Team (QRT) for emergency deployments. Additionally, the hub features a homeless transformation station that provides vocational training and sanitation facilities to help individuals reintegrate into society.', '[\"uploads/branches/br_1768128793_69638119eed3f_0.png\",\"uploads/branches/br_1768128793_69638119f05fc_1.png\",\"uploads/branches/br_1768128839_696381473f1bd_0.jpg\"]', 1, 'Open', NULL, NULL, '2025-12-15 23:02:23', 0),
(3, 'Penang Silver Years Haven', 'Mr. Ronald Tan', '+6012-7212535', 'ronaldtan0404@gmail.com', 'Old Folks Home', 32, '2018-08-30', '15, Jalan Georgetown, ', 'Heritage Zone, George Town, ', '', 'Georgetown', 'Penang', '10200', 'Malaysia', '+6011-11190233', 'penang@lovebridge.org.my', 'Nestled within a heritage building in George Town, Penang, this center specializes in care for the elderly, particularly those with mild dementia or who have been abandoned. We utilize Reminiscence Therapy, featuring an environment designed to mimic the 1960s \"Nanyang\" style to provide comfort and familiarity. With 24-hour nursing care, physical therapy, and our unique \"Life Storybook\" project, we ensure that every resident enjoys a twilight year filled with dignity, respect, and joy.', '[\"uploads/branches/br_1767960081_6960ee113ef84_0.jpg\",\"uploads/branches/br_1768128774_6963810604e14_0.png\",\"uploads/branches/br_1768128774_69638106055dc_1.png\"]', 1, 'Open', 'CIMB', '7070169114', '2025-12-15 23:02:23', 0);

-- --------------------------------------------------------

--
-- 表的结构 `case_comments`
--

CREATE TABLE `case_comments` (
  `Comment_ID` int(11) NOT NULL,
  `Case_ID` int(11) NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Comment_Text` text NOT NULL,
  `Created_At` datetime DEFAULT current_timestamp()
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
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `Attachment` varchar(255) DEFAULT NULL,
  `Status` enum('New','Read','Replied') NOT NULL DEFAULT 'New',
  `Created_At` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `contact_messages`
--

INSERT INTO `contact_messages` (`Contact_ID`, `Name`, `Email`, `Phone`, `Title`, `Message`, `Attachment`, `Status`, `Created_At`) VALUES
(17, 'LIM WEN QIN', 'lll8694798586@gmail.com', '011-12345678', 'a', 'qw', NULL, 'Read', '2026-01-06 00:46:27');

--
-- 触发器 `contact_messages`
--
DELIMITER $$
CREATE TRIGGER `notify_message_insert` AFTER INSERT ON `contact_messages` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`, `Contact_ID`) 
    VALUES (CONCAT('New Contact Message from ', NEW.Name, ': ', NEW.Title), 'New_Contact', 'admin_notifications_all.php', 0, NOW(), NEW.Contact_ID);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `contact_settings`
--

CREATE TABLE `contact_settings` (
  `Setting_ID` int(11) NOT NULL,
  `Address` text DEFAULT NULL,
  `Phone` varchar(50) DEFAULT NULL,
  `Whatsapp_Link` varchar(255) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Working_Hours` text DEFAULT NULL,
  `Map_Embed_Src` text DEFAULT NULL,
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `contact_settings`
--

INSERT INTO `contact_settings` (`Setting_ID`, `Address`, `Phone`, `Whatsapp_Link`, `Email`, `Working_Hours`, `Map_Embed_Src`, `Updated_At`) VALUES
(1, 'Jalan Ayer Keroh Lama,<br>75450 Bukit Beruang, Melaka<br>(MULTIMEDIA UNIVERSITY)', '+60 11-1119 0233', 'https://wa.me/601111190233', 'lovebridge1201@gmail.com', 'Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 9:00 AM - 1:00 PM<br>Sunday: Closed', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3986.7570420789075!2d102.27367637583344!3d2.245656058045656!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1e56b9710cf4b%3A0x66b6b12b75469278!2sMultimedia%20University%2C%20Melaka%20Campus!5e0!3m2!1sen!2smy!4v1700000000000!5m2!1sen!2smy', '2026-01-15 20:47:58');

-- --------------------------------------------------------

--
-- 表的结构 `donor`
--

CREATE TABLE `donor` (
  `Donor_ID` int(11) NOT NULL,
  `Donor_Name` varchar(100) NOT NULL,
  `Donor_ContactNumber` varchar(20) NOT NULL,
  `Donor_ICNumber` varchar(20) NOT NULL,
  `Donor_Email` varchar(100) NOT NULL,
  `Donor_Password` varchar(255) DEFAULT NULL,
  `Donor_Wallet` decimal(10,2) NOT NULL DEFAULT 0.00,
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
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted',
  `donor_reset_count` int(11) DEFAULT 0,
  `donor_last_reset_request` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `donor`
--

INSERT INTO `donor` (`Donor_ID`, `Donor_Name`, `Donor_ContactNumber`, `Donor_ICNumber`, `Donor_Email`, `Donor_Password`, `Donor_Wallet`, `Donor_Address1`, `Donor_Address2`, `Donor_Address3`, `Donor_City`, `Donor_State`, `Donor_PostalCode`, `Donor_Country`, `Donor_DOB`, `Donor_Description`, `Donor_RegisteredAt`, `Donor_LastLogin`, `Donor_ProfilePicture`, `Is_Deleted`, `donor_reset_count`, `donor_last_reset_request`) VALUES
(2, 'RONALD TAN BIN HONG', '012-7212535', '030404011183', 'abc@gmail.com', '$2y$10$3hsuDLLcdM4mr9bqhvJekOZqH80/6aF.7GR2vLxXHbcgWT.RjOe.O', 0.00, 'No19.jalan melawati 19, taman melawati', '', '', 'Johor Bahru', 'Johor', '81300', 'Malaysia', '2003-04-04', '', '2025-12-15 22:27:40', NULL, NULL, 0, 0, NULL),
(3, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', '$2y$10$PNOaF1e8KP0y9i404VCz9OWhn3XVyLHx8roawWiJjvPR0hyRunfeW', 0.00, 'No 11, jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', '2003-05-17', '', '2026-01-03 21:43:26', NULL, 'uploads/donors/donor_1767879100_695fb1bc6d8e7.png', 0, 1, NULL),
(4, 'johndoe', '+6011-11190233', '', 'admin@donationsystem.com', NULL, 0.00, '', '', '', '', '', '', 'Malaysia', '0000-00-00', '', '2026-01-08 10:17:29', NULL, NULL, 0, 0, NULL),
(5, 'thong', '+6011-11190233', '', 'thong@example.com', '$2y$10$yIcrpElLqaB33nLEva5rB.cfnVFib.XdekEbMYbJROuPcpHi.lshi', 0.00, '', '', '', '', '', '', 'Malaysia', '0000-00-00', '', '2026-01-11 01:09:57', NULL, NULL, 0, 0, NULL),
(6, 'RONALD TAN BIN HONG', '+6012-7212535', '050404-01-1183', 'ronaldtan0404@gmail.com', '$2y$10$xOPon039anG.Ft.X5qZCjuMJ.zwoKZBfFpWlAm61hiBHvnJZaHamK', 0.00, 'No19.jalan melawati 19, taman melawati', 'taman melawati', 'Skudai', 'Johor Bahru', 'Johor', '81300', 'Malaysia', '2005-04-04', '', '2026-01-11 21:21:48', NULL, NULL, 0, 4, NULL),
(7, 'thong', '012-34567812', '691121011234', 'qinwenlin989@gmail.com', '$2y$10$EM8PfzEjVKw6tO3bgzOg0uX21x2Qhh8geTUFON816oItisuI2GMQK', 20.00, 'Blk 914Jurong West Street 91', 'taman abc', '', 'Skudai', 'Johor', '640914', 'Malaysia', '1969-11-21', '', '2026-01-15 10:03:57', NULL, NULL, 0, 1, NULL),
(8, 'john', '+6011-11190233', '', 'user@donationsystem.my', '$2y$10$yj0hTeFgb8HPGcVTCUCJ/e8xbghBuFPUq3wHlVLBG2TRU8PcUO7rO', 0.00, '', '', '', '', '', '', 'Malaysia', '0000-00-00', '', '2026-01-15 10:48:37', NULL, NULL, 0, 0, NULL),
(9, 'thong', '+6011-11190233', '030517', 'qinwen@gmail.org', '$2y$10$wAnF5LHfhwNjlio1fzpDvu/pZLmtNrXFiGKzQaVaSZKyb44cEKyby', 0.00, '', '', '', '', '', '', 'Malaysia', '2003-05-17', '', '2026-01-15 22:03:08', NULL, NULL, 1, 0, NULL),
(10, 'thng', '+6011-99999988', '', 'thong@gmail.com', '$2y$10$L717kleNhzBgPvIGg97uL.P3xote/p30Bi31augcoWYwhCixI9QNS', 0.00, '', '', '', '', '', '', 'Malaysia', '0000-00-00', '', '2026-01-17 09:28:27', NULL, NULL, 0, 0, NULL),
(11, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', '$2y$10$KeXcc0clhsomf6U24F.KyOSP0x1F/AsuS951EG84mxF.BAAnCc7mC', 400.00, '11, jalan silat lincah 10', '', '', 'Skudai', 'Johor', '81300', 'Malaysia', '2003-05-17', '', '2026-01-17 21:13:55', NULL, NULL, 0, 0, NULL);

--
-- 触发器 `donor`
--
DELIMITER $$
CREATE TRIGGER `notify_donor_insert` AFTER INSERT ON `donor` FOR EACH ROW BEGIN
    -- 为新用户初始化积分表 (防止后续加分失败)
    INSERT INTO point (Donor_ID, Points_Earned, Points_Total, Points_Updated_At) 
    VALUES (NEW.Donor_ID, 0, 0, NOW());
    
    -- 发送通知
    INSERT INTO admin_notifications (Message, Type, Link, Is_Read, Created_At) 
    VALUES (CONCAT('New Donor Registered: ', NEW.Donor_Name), 'New_Donor', 'admin_donor_page.php', 0, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_donor_update` AFTER UPDATE ON `donor` FOR EACH ROW BEGIN
    IF OLD.Donor_Name != NEW.Donor_Name OR OLD.Donor_Email != NEW.Donor_Email THEN
        INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`) 
        VALUES (CONCAT('Donor Profile Updated: ', NEW.Donor_Name), 'Update', 'admin_donor_page.php', 0, NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `donor_login_attempts`
--

CREATE TABLE `donor_login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('failed','success') NOT NULL DEFAULT 'failed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `donor_login_attempts`
--

INSERT INTO `donor_login_attempts` (`id`, `email`, `ip_address`, `attempt_time`, `status`) VALUES
(2, 'qinwe@gmail.com', '::1', '2026-01-15 10:05:40', 'failed'),
(4, 'qinwenlin989@gmail.com', '::1', '2026-01-15 11:09:26', 'failed'),
(5, 'qinwenlin989@gmail.com', '::1', '2026-01-15 11:09:37', 'failed'),
(6, 'qinwenlin989@gmail.com', '::1', '2026-01-17 21:12:06', 'failed'),
(7, 'qinwenlin989@gmail.com', '::1', '2026-01-17 21:12:30', 'failed');

-- --------------------------------------------------------

--
-- 表的结构 `donor_password_reset`
--

CREATE TABLE `donor_password_reset` (
  `reset_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `reset_token` varchar(255) NOT NULL,
  `reset_email` varchar(255) NOT NULL,
  `reset_expires` datetime NOT NULL,
  `reset_created` datetime NOT NULL DEFAULT current_timestamp(),
  `reset_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `reset_status` enum('pending','used','expired','invalidated') NOT NULL DEFAULT 'pending',
  `reset_used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `donor_password_reset`
--

INSERT INTO `donor_password_reset` (`reset_id`, `donor_id`, `reset_token`, `reset_email`, `reset_expires`, `reset_created`, `reset_updated`, `ip_address`, `user_agent`, `reset_status`, `reset_used`) VALUES
(1, 7, 'ab359c5f40bb6b27a7b67435d222c6e1d489f232f7c57928a1d7ece665e4f984', 'qinwenlin989@gmail.com', '2026-01-15 10:21:20', '2026-01-15 10:06:20', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'pending', 0),
(2, 3, '53c43aabfc75a861b0b06d285820509c6c8ce7773e129f3c591fc5d8a886a3b5', 'thongyuenzhen@gmail.com', '2026-01-15 10:21:41', '2026-01-15 10:06:41', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'pending', 0),
(6, 6, '981a0b8d07287f48088e4415c7ac698c821f852dd5685098b91b42bcbec92ec7', 'ronaldtan0404@gmail.com', '2026-01-15 10:25:47', '2026-01-15 10:10:47', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'pending', 0);

-- --------------------------------------------------------

--
-- 表的结构 `donor_security_logs`
--

CREATE TABLE `donor_security_logs` (
  `log_id` int(11) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `log_type` varchar(50) NOT NULL,
  `log_action` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `log_details` text DEFAULT NULL,
  `log_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `donor_security_logs`
--

INSERT INTO `donor_security_logs` (`log_id`, `donor_id`, `log_type`, `log_action`, `ip_address`, `user_agent`, `log_details`, `log_date`) VALUES
(1, 7, 'password_reset', 'reset_email_sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Success', '2026-01-15 10:06:24'),
(2, 3, 'password_reset', 'reset_email_sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Success', '2026-01-15 10:06:45'),
(3, 6, 'password_reset', 'reset_email_sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Success', '2026-01-15 10:09:20'),
(4, 6, 'password_reset', 'reset_email_sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Success', '2026-01-15 10:10:05'),
(5, 6, 'password_reset', 'reset_email_sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Success', '2026-01-15 10:10:29'),
(6, 6, 'password_reset', 'reset_email_sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Success', '2026-01-15 10:10:51');

-- --------------------------------------------------------

--
-- 表的结构 `email_logs`
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
-- 转存表中的数据 `email_logs`
--

INSERT INTO `email_logs` (`Email_ID`, `To_Email`, `Title`, `Content`, `Status`, `Sent_At`, `Created_At`) VALUES
(1, 'admin@lovebridge.org.my', 'New Contact Form Submission: a', 'A new contact form has been submitted:\n\nName: LIM WEN QIN\nEmail: lll8694798586@gmail.com\nPhone: 011-12345678\nTitle: a\nMessage:\nqw\n\nLogin to admin panel to view details.', 'Sent', '2026-01-06 00:44:13', '2026-01-06 00:44:13'),
(2, 'admin@lovebridge.org.my', 'New Contact Form Submission: a', 'A new contact form has been submitted:\n\nName: LIM WEN QIN\nEmail: lll8694798586@gmail.com\nPhone: 011-12345678\nTitle: a\nMessage:\nqw\n\nLogin to admin panel to view details.', 'Sent', '2026-01-06 00:46:27', '2026-01-06 00:46:27');

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
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Headquarters_State` varchar(50) DEFAULT 'Kuala Lumpur',
  `HQ_BankName` varchar(100) DEFAULT NULL,
  `HQ_BankAccount` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `headquarters`
--

INSERT INTO `headquarters` (`HQ_ID`, `HQ_Name`, `HQ_ContactNumber`, `HQ_Email`, `HQ_Address`, `HQ_Description`, `HQ_Story`, `HQ_FoundingDate`, `HQ_Image`, `Updated_At`, `Headquarters_State`, `HQ_BankName`, `HQ_BankAccount`) VALUES
(1, 'Love Bridge International Headquarters', '+603-2145 7788', 'hq@lovebridge.org.my', 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur, Malaysia', 'The strategic command center for Love Bridge. Responsible for formulating long-term charitable strategies, managing fund flows, executing transparent financial audits, and coordinating cross-border aid. It is also the R&D base for our blockchain charity system, dedicated to using technology to improve charitable efficiency.', '[Origins & Mission] Founded in 2010, evolving from a 5-person team into a data-driven modern charitable organization.\n\n[Core Functions] The HQ houses a \"Global Command Center\" that monitors relief supplies and beneficiary status nationwide in real-time. We aim not just to do good, but to establish industry standards: ensuring transparency through independent audits, empowering branches through standardized training, and promoting sustainable charity through corporate partnerships.', '2010-01-01', NULL, '2026-01-11 13:51:41', 'Kuala Lumpur', NULL, NULL);

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
-- 表的结构 `notifications`
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
-- 表的结构 `orders`
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
  `Order_PaymentMethod` enum('Credit Card','System E-Wallet','TNG eWallet') NOT NULL,
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
-- 转存表中的数据 `orders`
--

INSERT INTO `orders` (`Order_ID`, `Order_Name`, `Order_ContactNumber`, `Order_ICNumber`, `Order_Email`, `Order_Amount`, `Order_Points_Earned`, `Order_Currency`, `Order_PaymentMethod`, `Order_PaymentStatus`, `Order_Admin_Status`, `Order_TXN_Ref`, `Order_Type`, `Order_Status`, `Tax_Receipt_Status`, `Is_Deleted`, `Order_Created_At`, `Order_Updated_At`, `Donor_ID`, `Payment_ID`, `Branch_ID`, `Activity_ID`, `Case_ID`) VALUES
(1, 'RONALD TAN BIN HONG', '012-7212535', '030404011183', 'abc@gmail.com', 100.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-20251215222806', 'Recurring', 'Completed', 'Not_Requested', 0, '2025-12-15 22:28:06', '2025-12-15 22:28:06', 2, 1, NULL, NULL, 3),
(2, 'thong yuen zhen', '+6011-11190233', '030517-01-0373', 'thongyuenzhen@gmail.com', 1000.00, 100, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'MAN-IN-20260104001033-858', 'One-Time', 'Completed', 'Not_Requested', 0, '2026-01-04 00:10:33', '2026-01-04 00:10:33', 3, 3, NULL, NULL, NULL),
(3, 'RONALD TAN BIN HONG', '012-7212535', '', 'ronaldtan0404@gmail.com', 100.00, 0, '', 'TNG eWallet', '', 'Completed', 'TXN-TOPUP-20260111214144-355', 'One-time', 'Completed', 'Not_Requested', 0, '2026-01-11 21:41:44', '2026-01-11 21:41:44', 6, 4, NULL, NULL, NULL),
(4, 'RONALD TAN BIN HONG', '012-7212535', '', 'ronaldtan0404@gmail.com', 20.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-EW-20260111214157-306', 'One-time', 'Completed', 'Not_Requested', 0, '2026-01-11 21:41:57', '2026-01-11 21:41:57', 6, 5, NULL, NULL, NULL),
(5, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 50.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-20260111215338-973', 'One-time', 'Completed', 'Generated', 0, '2026-01-11 21:53:38', '2026-01-11 21:53:38', 6, 6, NULL, NULL, 8),
(6, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 50.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-20260111215656-414', 'One-time', 'Completed', 'Generated', 0, '2026-01-11 21:56:56', '2026-01-11 21:56:56', 6, 7, NULL, 3, NULL),
(8, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 20.00, 0, 'MYR', 'System E-Wallet', 'Success', 'Completed', 'TXN-EW-20260112122132-446', 'One-time', 'Completed', 'Not_Requested', 0, '2026-01-12 12:21:32', '2026-01-12 12:21:32', 6, 9, 1, NULL, NULL),
(9, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 50.00, 0, 'MYR', 'System E-Wallet', 'Success', 'Completed', 'TXN-EW-20260112122311-972', 'One-time', 'Completed', 'Generated', 0, '2026-01-12 12:23:11', '2026-01-12 12:23:11', 6, 10, NULL, NULL, 8),
(13, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 20.00, 0, '', 'TNG eWallet', '', 'Completed', 'TXN-TOPUP-20260112123119-346', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-12 12:31:19', '2026-01-12 12:31:19', 6, 14, NULL, NULL, NULL),
(14, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 20.00, 0, '', 'System E-Wallet', '', 'Completed', 'TXN-TOPUP-20260112123536-925', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-12 12:35:36', '2026-01-12 12:35:36', 6, 15, NULL, NULL, NULL),
(15, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 50.00, 0, '', 'TNG eWallet', '', 'Completed', 'TXN-TOPUP-20260112124327-573', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-12 12:43:27', '2026-01-12 12:43:27', 6, 16, NULL, NULL, NULL),
(16, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 50.00, 0, '', 'System E-Wallet', '', 'Completed', 'TXN-TOPUP-20260112125521-816', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-12 12:55:21', '2026-01-12 12:55:21', 6, 17, NULL, NULL, NULL),
(17, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 100.00, 0, 'MYR', 'System E-Wallet', 'Success', 'Completed', 'TXN-EW-20260112125547-106', 'One-time', 'Completed', 'Generated', 0, '2026-01-12 12:55:47', '2026-01-12 12:55:47', 6, 18, NULL, NULL, 7),
(18, 'RONALD TAN BIN HONG', '012-7212535', '050404011183', 'ronaldtan0404@gmail.com', 50.00, 0, 'MYR', 'System E-Wallet', 'Success', 'Completed', 'TXN-EW-20260112125736-492', 'One-time', 'Completed', 'Not_Requested', 0, '2026-01-12 12:57:36', '2026-01-12 12:57:36', 6, 19, 2, NULL, NULL),
(19, 'thong', '012-34567812', '', 'qinwenlin989@gmail.com', 60000.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-20260115101620-996', 'One-time', 'Completed', 'Generated', 0, '2026-01-15 10:16:20', '2026-01-15 10:16:20', 7, 20, NULL, NULL, 7),
(20, 'thong', '012-34567812', '', 'qinwenlin989@gmail.com', 50.00, 0, '', '', '', 'Completed', 'TXN-TOPUP-20260115101937-547', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-15 10:19:37', '2026-01-15 10:19:37', 7, 21, NULL, NULL, NULL),
(21, 'thong', '012-34567812', '691121011234', 'qinwenlin989@gmail.com', 30.00, 0, 'MYR', '', 'Success', 'Completed', 'TXN-EW-20260115103029-579', 'Recurring', 'Completed', 'Rejected', 0, '2026-01-15 10:30:29', '2026-01-15 10:30:29', 7, 22, 2, NULL, NULL),
(22, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', 50.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-TNG-20260117211754-890', 'Recurring', 'Completed', 'Not_Requested', 0, '2026-01-17 21:17:54', '2026-01-17 21:17:54', 11, 23, 2, NULL, NULL),
(23, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', 50.00, 0, 'MYR', 'TNG eWallet', 'Success', 'Completed', 'TXN-TNG-20260117211906-993', 'One-time', 'Completed', 'Requested', 0, '2026-01-17 21:19:06', '2026-01-17 21:19:06', 11, 24, NULL, NULL, 8),
(24, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', 300.00, 0, '', 'TNG eWallet', '', 'Completed', 'TXN-TOPUP-20260117212116-248', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-17 21:21:16', '2026-01-17 21:21:16', 11, 25, NULL, NULL, NULL),
(25, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', 300.00, 0, '', 'TNG eWallet', '', 'Completed', 'TXN-TOPUP-20260117212134-309', 'Top-up', 'Completed', 'Not_Requested', 0, '2026-01-17 21:21:34', '2026-01-17 21:21:34', 11, 26, NULL, NULL, NULL),
(26, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', 100.00, 0, 'MYR', 'System E-Wallet', 'Success', 'Completed', 'TXN-EW-20260117212201-255', 'One-time', 'Completed', 'Requested', 0, '2026-01-17 21:22:01', '2026-01-17 21:22:01', 11, 27, NULL, NULL, 3),
(27, 'thong', '011-22334455', '030517010375', 'thong@gmail.org', 100.00, 0, 'MYR', 'System E-Wallet', 'Success', 'Completed', 'TXN-EW-20260117212324-565', 'One-time', 'Completed', 'Requested', 0, '2026-01-17 21:23:24', '2026-01-17 21:23:24', 11, 28, NULL, 3, NULL);

--
-- 触发器 `orders`
--
DELIMITER $$
CREATE TRIGGER `after_tax_receipt_request` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF NEW.Tax_Receipt_Status = 'Requested' AND OLD.Tax_Receipt_Status != 'Requested' THEN
        INSERT INTO admin_notifications (Message, Type, `Link`, Is_Read, Created_At)
        VALUES (CONCAT('New Tax Receipt Request: ', NEW.Order_TXN_Ref), 'receipt_request', 'admin_receipts.php', 0, NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_order_insert_points` BEFORE INSERT ON `orders` FOR EACH ROW BEGIN
    -- 逻辑：RM10 = 1 Point
    -- 只要状态是 Success 或 Completed，就计算积分
    IF (NEW.Order_PaymentStatus = 'Success' OR NEW.Order_PaymentStatus = 'Completed' OR NEW.Order_Status = 'Success' OR NEW.Order_Status = 'Completed') THEN
        SET NEW.Order_Points_Earned = FLOOR(NEW.Order_Amount / 10);
    ELSE 
        SET NEW.Order_Points_Earned = 0;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_order_update_points` BEFORE UPDATE ON `orders` FOR EACH ROW BEGIN
    -- 检查状态是否从未完成变成了完成
    IF (NEW.Order_PaymentStatus = 'Success' OR NEW.Order_PaymentStatus = 'Completed' OR NEW.Order_Status = 'Success' OR NEW.Order_Status = 'Completed') 
       AND (OLD.Order_PaymentStatus != 'Success' AND OLD.Order_PaymentStatus != 'Completed' AND OLD.Order_Status != 'Success' AND OLD.Order_Status != 'Completed') THEN
        
        -- 补算积分
        SET NEW.Order_Points_Earned = FLOOR(NEW.Order_Amount / 10);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `calculate_points_on_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    -- 只有当该订单确实获得了积分 (>0) 且状态成功时，才更新用户总分
    IF NEW.Order_Points_Earned > 0 AND (NEW.Order_PaymentStatus = 'Success' OR NEW.Order_PaymentStatus = 'Completed' OR NEW.Order_Status = 'Success' OR NEW.Order_Status = 'Completed') THEN
        UPDATE point 
        SET Points_Total = Points_Total + NEW.Order_Points_Earned, 
            Points_Earned = Points_Earned + NEW.Order_Points_Earned,
            Points_Updated_At = NOW()
        WHERE Donor_ID = NEW.Donor_ID;
    END IF;
    
    -- 生成新捐款通知
    INSERT INTO admin_notifications (Message, Type, Link, Is_Read, Created_At) 
    VALUES (CONCAT('New Donation: ', NEW.Order_Currency, ' ', NEW.Order_Amount, ' from ', NEW.Order_Name), 'Donation', 'payment_management.php', 0, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `calculate_points_on_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    -- 如果状态变更为成功，且该订单有积分
    IF (NEW.Order_PaymentStatus = 'Success' OR NEW.Order_PaymentStatus = 'Completed' OR NEW.Order_Status = 'Success' OR NEW.Order_Status = 'Completed') 
       AND (OLD.Order_PaymentStatus != 'Success' AND OLD.Order_PaymentStatus != 'Completed' AND OLD.Order_Status != 'Success' AND OLD.Order_Status != 'Completed') 
       AND NEW.Order_Points_Earned > 0 THEN
        
        UPDATE point 
        SET Points_Total = Points_Total + NEW.Order_Points_Earned, 
            Points_Earned = Points_Earned + NEW.Order_Points_Earned,
            Points_Updated_At = NOW()
        WHERE Donor_ID = NEW.Donor_ID;
    END IF;

    -- 状态变更通知
    IF OLD.Order_Status != NEW.Order_Status THEN
        INSERT INTO admin_notifications (Message, Type, Is_Read, Created_At) 
        VALUES (CONCAT('Order #', NEW.Order_ID, ' Status Changed to ', NEW.Order_Status), 'Update', 0, NOW());
    END IF;
    
    -- 收据请求通知
    IF NEW.Tax_Receipt_Status = 'Requested' AND OLD.Tax_Receipt_Status != 'Requested' THEN
        INSERT INTO admin_notifications (Message, Type, `Link`, Is_Read, Created_At)
        VALUES (CONCAT('New Tax Receipt Request: ', NEW.Order_TXN_Ref), 'receipt_request', 'admin_receipts.php', 0, NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_order_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('New Donation: ', NEW.Order_Currency, ' ', NEW.Order_Amount, ' from ', NEW.Order_Name), 'Donation', 'payment_management.php', 0, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_order_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.Order_Status != NEW.Order_Status THEN
        INSERT INTO `admin_notifications` (`Message`, `Type`, `Is_Read`, `Created_At`) 
        VALUES (CONCAT('Order #', NEW.Order_ID, ' Status Changed to ', NEW.Order_Status), 'Update', 0, NOW());
    END IF;
END
$$
DELIMITER ;

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

--
-- 转存表中的数据 `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(2, 'ujintan218@gmail.com', 'ae15e0b65780f84ad3a13eaeba8fab791c35617bcea01e57447b0c14a27fee7d', '2025-12-07 21:38:58', '2025-12-07 12:38:58'),
(11, 'thongyuenzhen@gmail.com', '705ee8e26f820b75db7f0cb2cc46f6b866d4e36fe245b7d1d97fdd4a5ba8ebe0', '2026-01-16 10:51:55', '2026-01-16 01:51:55');

-- --------------------------------------------------------

--
-- 表的结构 `payment`
--

CREATE TABLE `payment` (
  `Payment_ID` int(11) NOT NULL,
  `Payment_Method` enum('Credit Card','System E-Wallet','TNG eWallet') NOT NULL,
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
-- 转存表中的数据 `payment`
--

INSERT INTO `payment` (`Payment_ID`, `Payment_Method`, `Payment_Status`, `Payment_TXN_Ref`, `Payment_Amount`, `Payment_Paid_At`, `Payment_Bank_Name`, `Payment_Bank_Masked`, `Payment_Proof`, `Payment_Created_At`) VALUES
(1, 'TNG eWallet', 'Success', 'TXN-20251215222806', 100.00, '2025-12-15 22:28:06', 'TNG eWallet', 'QR Payment', NULL, '2025-12-15 22:28:06'),
(3, 'TNG eWallet', 'Success', 'MAN-IN-20260104001033-858', 1000.00, '2026-01-04 00:10:33', 'Manual Entry', 'N/A', NULL, '2026-01-04 00:10:33'),
(4, 'TNG eWallet', 'Success', 'TXN-TOPUP-20260111214144-355', 100.00, '2026-01-11 21:41:44', 'TNG eWallet', 'Top-up', NULL, '2026-01-11 21:41:44'),
(5, 'TNG eWallet', 'Success', 'TXN-EW-20260111214157-306', 20.00, '2026-01-11 21:41:57', 'My E-Wallet', 'Wallet Balance', NULL, '2026-01-11 21:41:57'),
(6, 'TNG eWallet', 'Success', 'TXN-20260111215338-973', 50.00, '2026-01-11 21:53:38', 'TNG eWallet', 'QR Payment', NULL, '2026-01-11 21:53:38'),
(7, 'TNG eWallet', 'Success', 'TXN-20260111215656-414', 50.00, '2026-01-11 21:56:56', 'MasterCard', '5124 **** **** 1931', NULL, '2026-01-11 21:56:56'),
(9, '', 'Success', 'TXN-EW-20260112122132-446', 20.00, '2026-01-12 12:21:32', 'My E-Wallet Balance', 'Wallet-Spending', NULL, '2026-01-12 12:21:32'),
(10, '', 'Success', 'TXN-EW-20260112122311-972', 50.00, '2026-01-12 12:23:11', 'My E-Wallet Balance', 'Wallet-Spending', NULL, '2026-01-12 12:23:11'),
(14, 'TNG eWallet', 'Success', 'TXN-TOPUP-20260112123119-346', 20.00, '0000-00-00 00:00:00', 'TNG eWallet', 'Top-up Account', NULL, '2026-01-12 12:31:19'),
(15, '', 'Success', 'TXN-TOPUP-20260112123536-925', 20.00, '0000-00-00 00:00:00', 'Visa Card', '4136 **** **** 1073', NULL, '2026-01-12 12:35:36'),
(16, 'TNG eWallet', 'Success', 'TXN-TOPUP-20260112124327-573', 50.00, '0000-00-00 00:00:00', 'TNG eWallet', 'Top-up Account', NULL, '2026-01-12 12:43:27'),
(17, '', 'Success', 'TXN-TOPUP-20260112125521-816', 50.00, '0000-00-00 00:00:00', 'MasterCard', '5124 **** **** 8232', NULL, '2026-01-12 12:55:21'),
(18, '', 'Success', 'TXN-EW-20260112125547-106', 100.00, '2026-01-12 12:55:47', 'My E-Wallet Balance', 'Wallet-Spending', NULL, '2026-01-12 12:55:47'),
(19, '', 'Success', 'TXN-EW-20260112125736-492', 50.00, '2026-01-12 12:57:36', 'My E-Wallet Balance', 'Wallet-Spending', NULL, '2026-01-12 12:57:36'),
(20, 'TNG eWallet', 'Success', 'TXN-20260115101620-996', 60000.00, '2026-01-15 10:16:20', 'TNG eWallet', 'QR Payment', NULL, '2026-01-15 10:16:20'),
(21, '', 'Success', 'TXN-TOPUP-20260115101937-547', 50.00, '0000-00-00 00:00:00', 'MasterCard', '5165 **** **** 7077', NULL, '2026-01-15 10:19:37'),
(22, '', 'Success', 'TXN-EW-20260115103029-579', 30.00, '2026-01-15 10:30:29', 'My E-Wallet Balance', 'Wallet-Spending', NULL, '2026-01-15 10:30:29'),
(23, 'TNG eWallet', 'Success', 'TXN-TNG-20260117211754-890', 50.00, '2026-01-17 21:17:54', 'TNG eWallet', 'QR Payment', NULL, '2026-01-17 21:17:54'),
(24, 'TNG eWallet', 'Success', 'TXN-TNG-20260117211906-993', 50.00, '2026-01-17 21:19:06', 'TNG eWallet', 'QR Payment', NULL, '2026-01-17 21:19:06'),
(25, 'TNG eWallet', 'Success', 'TXN-TOPUP-20260117212116-248', 300.00, '0000-00-00 00:00:00', 'TNG eWallet', 'Top-up Account', NULL, '2026-01-17 21:21:16'),
(26, 'TNG eWallet', 'Success', 'TXN-TOPUP-20260117212134-309', 300.00, '0000-00-00 00:00:00', 'TNG eWallet', 'Top-up Account', NULL, '2026-01-17 21:21:34'),
(27, 'System E-Wallet', 'Success', 'TXN-EW-20260117212201-255', 100.00, '2026-01-17 21:22:01', 'Internal Wallet', 'Wallet-Spending', NULL, '2026-01-17 21:22:01'),
(28, 'System E-Wallet', 'Success', 'TXN-EW-20260117212324-565', 100.00, '2026-01-17 21:23:24', 'Internal Wallet', 'Wallet-Spending', NULL, '2026-01-17 21:23:24');

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

--
-- 转存表中的数据 `point`
--

INSERT INTO `point` (`Points_ID`, `Points_Earned`, `Points_Total`, `Points_Updated_At`, `Donor_ID`) VALUES
(1, 10, 10, '2025-12-15 22:28:06', 2),
(2, 100, 65, '2026-01-04 23:11:46', 3),
(3, 0, 0, '2026-01-08 10:17:29', 4),
(4, 0, 0, '2026-01-11 01:09:57', 5),
(5, 34, 34, '2026-01-12 12:57:41', 6),
(6, 35, 35, '2026-01-12 12:57:41', 6),
(7, 17, 17, '2026-01-12 12:57:41', 6),
(8, 20, 20, '2026-01-12 12:57:41', 6),
(9, 20, 20, '2026-01-12 12:57:41', 6),
(10, 6006, 5956, '2026-01-15 10:33:20', 7),
(11, 11, 5956, '2026-01-15 10:33:20', 7),
(12, 0, 0, '2026-01-15 10:48:37', 8),
(13, 0, 0, '2026-01-15 22:03:08', 9),
(14, 0, 0, '2026-01-17 09:28:27', 10),
(15, 55, 55, '2026-01-17 21:23:27', 11),
(16, 70, 70, '2026-01-17 21:23:27', 11),
(17, 70, 70, '2026-01-17 21:23:27', 11);

-- --------------------------------------------------------

--
-- 表的结构 `policy_acceptances`
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
-- 表的结构 `privacy_policy`
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
-- 转存表中的数据 `privacy_policy`
--

INSERT INTO `privacy_policy` (`id`, `version`, `content`, `effective_date`, `created_at`, `is_active`) VALUES
(1, '1.0', '<h2>Love Bridge Donation Platform - Privacy Policy</h2>\r\n<p><strong>Effective Date:</strong> [CURRENT_DATE]</p>\r\n\r\n<h3>1. Information We Collect</h3>\r\n<p>We collect information you provide directly, such as when you make a donation, including name, email address, and payment information.</p>\r\n\r\n<h3>2. How We Use Your Information</h3>\r\n<p>We use your information to process donations, send receipts, communicate about our programs, and improve our services.</p>\r\n\r\n<h3>3. Information Sharing</h3>\r\n<p>We do not sell or rent your personal information. We may share information with payment processors and as required by law.</p>\r\n\r\n<h3>4. Data Security</h3>\r\n<p>We implement reasonable security measures to protect your information, but no system is 100% secure.</p>\r\n\r\n<h3>5. Your Choices</h3>\r\n<p>You may opt out of promotional communications at any time. Required transactional communications will still be sent.</p>\r\n\r\n<h3>6. Changes to This Policy</h3>\r\n<p>We may update this policy periodically. We will notify users of material changes via email or platform notice.</p>\r\n\r\n<h3>7. Contact Us</h3>\r\n<p>For privacy-related questions, contact privacy@lovebridge.org.</p>', '2026-01-06', '2026-01-05 17:01:32', 1);

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

--
-- 转存表中的数据 `receipt`
--

INSERT INTO `receipt` (`Receipt_ID`, `Receipt_Receipt_Number`, `Receipt_Generated_At`, `Receipt_Receipt_File`, `Donor_ID`, `Order_ID`) VALUES
(1, 'REC-2026-000005', '2026-01-12 12:58:46', 'receipt_5.pdf', 6, 5),
(2, 'REC-2026-000006', '2026-01-12 19:59:49', 'receipt_6.pdf', 6, 6),
(3, 'REC-2026-000009', '2026-01-12 19:59:56', 'receipt_9.pdf', 6, 9),
(4, 'REC-2026-000017', '2026-01-12 20:00:03', 'receipt_17.pdf', 6, 17),
(5, 'REC-2026-000019', '2026-01-15 11:07:25', 'receipt_19.pdf', 7, 19);

-- --------------------------------------------------------

--
-- 表的结构 `recurring_donation`
--

CREATE TABLE `recurring_donation` (
  `Recurring_ID` int(11) NOT NULL,
  `Recurring_Amount` decimal(10,2) NOT NULL,
  `Recurring_Payment_Method` enum('Credit Card','System E-Wallet','TNG eWallet') NOT NULL,
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
-- 转存表中的数据 `recurring_donation`
--

INSERT INTO `recurring_donation` (`Recurring_ID`, `Recurring_Amount`, `Recurring_Payment_Method`, `Recurring_StartDate`, `Recurring_EndDate`, `Recurring_Deduction_Date`, `Last_Payment_Date`, `Recurring_Status`, `Recurring_Created_At`, `Recurring_Updated_At`, `Donor_ID`, `Branch_ID`, `Activity_ID`, `Case_ID`) VALUES
(2, 100.00, 'TNG eWallet', '2025-12-15', NULL, '2026-01-01', NULL, 'Cancelled', '2025-12-15 22:28:06', '2025-12-15 22:28:51', 2, NULL, NULL, 3),
(3, 30.00, '', '2026-01-15', NULL, '2026-02-15', NULL, 'Paused', '2026-01-15 10:30:29', '2026-01-15 10:34:52', 7, 2, NULL, NULL),
(4, 50.00, 'TNG eWallet', '2026-01-17', NULL, '2026-02-17', NULL, 'Active', '2026-01-17 21:17:54', '2026-01-17 21:17:54', 11, 2, NULL, NULL);

--
-- 触发器 `recurring_donation`
--
DELIMITER $$
CREATE TRIGGER `notify_recurring_insert` AFTER INSERT ON `recurring_donation` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('New Recurring Donation Setup: RM', NEW.Recurring_Amount), 'Donation', 0, NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `redemption_order`
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
  `Redemption_CancelReason` text DEFAULT NULL,
  `Redemption_TrackingNumber` varchar(50) DEFAULT NULL,
  `Redemption_Updated_At` datetime NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL,
  `Redemption_Quantity` int(11) NOT NULL DEFAULT 1,
  `Redemption_Shipped_At` datetime DEFAULT NULL,
  `Redemption_Est_Delivery_Date` date DEFAULT NULL,
  `Redemption_FollowUp_Sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `redemption_order`
--

INSERT INTO `redemption_order` (`Redemption_ID`, `Redemption_Address1`, `Redemption_Address2`, `Redemption_Address3`, `Redemption_City`, `Redemption_State`, `Redemption_PostalCode`, `Redemption_Country`, `Redemption_ContactNumber`, `Redemption_PointsSpent`, `Redemption_Status`, `Redemption_CancelReason`, `Redemption_TrackingNumber`, `Redemption_Updated_At`, `Donor_ID`, `Reward_ID`, `Redemption_Quantity`, `Redemption_Shipped_At`, `Redemption_Est_Delivery_Date`, `Redemption_FollowUp_Sent`) VALUES
(1, '11， jalan  silat lincah 10', NULL, NULL, 'Skudai', 'Johor', '81300', 'Malaysia', '+6011-11190233', 35, 'Cancelled', NULL, NULL, '2026-01-04 23:03:56', 3, 2, 1, NULL, NULL, 0),
(2, 'No 11, jalan silat lincah 10', 'Taman Selesa Jaya', '', 'Skudai', 'Johor', '81300', 'Malaysia', '11-11190233', 35, 'Shipped', NULL, 'JNT-123654798', '2026-01-13 20:08:54', 3, 2, 1, NULL, NULL, 0),
(3, 'Blk 914Jurong West Street 91', 'taman abc', '', 'Skudai', 'Johor', '640914', 'Malaysia', '012-34567812', 50, 'Pending', NULL, NULL, '2026-01-15 10:33:20', 7, 15, 1, NULL, NULL, 0);

--
-- 触发器 `redemption_order`
--
DELIMITER $$
CREATE TRIGGER `notify_redemption_insert` AFTER INSERT ON `redemption_order` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('New Reward Redemption Order #', NEW.Redemption_ID), 'Target', 'redemption_order_management.php', 0, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_redemption_update` AFTER UPDATE ON `redemption_order` FOR EACH ROW BEGIN
    IF OLD.Redemption_Status != NEW.Redemption_Status THEN
        INSERT INTO `admin_notifications` (`Message`, `Type`, `Is_Read`, `Created_At`) 
        VALUES (CONCAT('Redemption Order #', NEW.Redemption_ID, ' is now ', NEW.Redemption_Status), 'Update', 0, NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `reward_item`
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
-- 转存表中的数据 `reward_item`
--

INSERT INTO `reward_item` (`Reward_ID`, `Reward_Code`, `Reward_ItemName`, `Reward_Category`, `Reward_Description`, `Reward_RequiredPoint`, `Reward_Supplier`, `Reward_Stock`, `Reward_ExpiryDate`, `Reward_Status`, `Reward_PhotoPath`) VALUES
(1, 'HO-001', 'Handmade Organic Soap', 'Household', 'Natural lavender scented soap bars made with essential oils. Gentle on skin.', 12, 'Mama Earth Crafts', 50, NULL, 'Active', 'reward_1768482550_6968e6f6a0b03.jpg'),
(2, 'AP-002', 'Batik Cotton Scarf', 'Apparel', 'Traditional Malaysian Batik hand-painted scarf. 100% Cotton, vibrant colors.', 35, 'East Coast Batik', 23, NULL, 'Active', 'reward_1768482787_6968e7e3eb582.jpg'),
(3, 'HO-003', 'Rattan Coaster Set', 'Household', 'Set of 4 hand-woven rattan coasters. Durable and eco-friendly.', 15, 'Village Weavers', 40, NULL, 'Active', 'reward_1768482779_6968e7dbe170c.jpg'),
(4, 'HA-004', 'Beaded Key Chain', 'Handicraft', 'Colorful hand-beaded keychain with traditional motifs. Random designs.', 5, 'Love Bridge Volunteers', 100, NULL, 'Active', 'reward_1768482771_6968e7d307f31.jpg'),
(5, 'HO-005', 'Scented Soy Candle', 'Household', 'Eco-friendly soy wax candle in a glass jar. Lemongrass scent.', 20, 'Candle Studio', 30, NULL, 'Active', 'reward_1768482757_6968e7c5caa68.jpg'),
(6, 'HO-006', 'Mengkuang Woven Mat', 'Household', 'Small woven mat suitable for table centerpiece or wall decoration.', 25, 'Kampung Heritage', 15, NULL, 'Active', 'reward_1768482747_6968e7bb402a2.jpg'),
(7, 'HO-007', 'Clay Flower Pot', 'Household', 'Mini terracotta pot hand-painted with floral designs.', 10, 'Earth Pottery', 8, NULL, 'Low Stock', 'reward_1768482736_6968e7b0a895c.jpg'),
(8, 'AP-008', 'Tie-Dye Tote Bag', 'Apparel', 'Canvas tote bag featuring unique tie-dye patterns. Reusable and stylish.', 18, 'Youth Art Center', 60, NULL, 'Active', 'reward_1768482725_6968e7a5517f8.jpg'),
(9, 'HO-009', 'Coconut Shell Bowl', 'Household', 'Polished natural coconut shell bowl, perfect for smoothie bowls or decor.', 12, 'Island Crafts', 45, NULL, 'Active', 'reward_1768482715_6968e79b4b5cb.jpg'),
(10, 'OT-010', 'Hand-Stitched Notebook', 'Others', 'A5 notebook with recycled paper and hand-stitched binding.', 15, 'Green Stationers', 35, NULL, 'Active', 'reward_1768482703_6968e78f84c20.jpg'),
(11, 'HA-011', 'Macrame Wall Hanging', 'Handicraft', 'Bohemian style wall decoration made from cotton rope.', 45, 'Knotty Arts', 12, NULL, 'Low Stock', 'reward_1768482689_6968e7819a019.jpg'),
(12, 'AP-012', 'Embroidered Pouch', 'Apparel', 'Zipper pouch with detailed floral embroidery. Great for cosmetics.', 22, 'Needlework Aunties', 28, NULL, 'Active', 'reward_1768482676_6968e774c0dc3.jpg'),
(13, 'HO-013', 'Bamboo Cutlery Set', 'Household', 'Reusable bamboo spoon, fork, and straw in a cloth pouch.', 10, 'EcoLife', 5, NULL, 'Low Stock', 'reward_1768482658_6968e76232363.jpg'),
(14, 'OT-014', 'Resin Flower Bookmark', 'Others', 'Clear resin bookmark with pressed real dried flowers inside.', 8, 'Floral Press', 0, NULL, 'Inactive', 'reward_1768482645_6968e755f30d3.jpg'),
(15, 'HO-015', 'Hand-Painted Tiffin', 'Household', 'Classic metal tiffin carrier painted with retro floral patterns.', 50, 'Retro Vibes', 9, NULL, 'Low Stock', 'reward_1768482635_6968e74b2b33f.jpg'),
(16, 'HA-016', 'Crochet Plushie Bear', 'Handicraft', 'Cute handmade crochet bear doll. Safe for kids.', 30, 'Grandma Stitches', 20, NULL, 'Active', 'reward_1768482624_6968e7407d579.jpg'),
(17, 'AP-017', 'Upcycled Denim Purse', 'Apparel', 'Coin purse made from recycled denim jeans.', 10, 'Recycle Works', 40, NULL, 'Active', 'reward_1768482609_6968e731d20f6.jpg'),
(18, 'EL-018', 'Wood Carved Phone Stand', 'Electronics', 'Simple wooden stand carved from leftover timber. Fits most phones.', 15, 'Woody Crafts', 15, NULL, 'Active', 'reward_1768482600_6968e72873ec4.jpg'),
(19, 'HO-019', 'Ceramic Mug ', 'Household', 'Imperfection is beauty. Hand-thrown ceramic mug with unique glaze.', 25, 'Earth Pottery', 18, NULL, 'Active', 'reward_1768482579_6968e7133db7e.jpg'),
(20, 'HA-020', 'Traditional Kite ', 'Handicraft', 'Miniature decorative Wau Bulan. Not for flying, for display only.', 40, 'Heritage Fly', 50, NULL, 'Active', 'reward_1768482561_6968e70115ced.jpg');

-- --------------------------------------------------------

--
-- 表的结构 `reward_logs`
--

CREATE TABLE `reward_logs` (
  `Log_ID` int(11) NOT NULL,
  `Reward_ID` int(11) NOT NULL,
  `Admin_ID` int(11) NOT NULL,
  `Action_Type` varchar(50) NOT NULL,
  `Action_Details` text NOT NULL,
  `Log_Created_At` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `reward_logs`
--

INSERT INTO `reward_logs` (`Log_ID`, `Reward_ID`, `Admin_ID`, `Action_Type`, `Action_Details`, `Log_Created_At`) VALUES
(1, 20, 2, 'Stock Update', 'Added 50 qty. New Total: 75', '2026-01-13 12:47:34'),
(2, 20, 2, 'Update', 'Updated details for Traditional Kite . Stock set to 75', '2026-01-13 13:31:58'),
(3, 20, 2, 'Update', 'Updated details for Traditional Kite . Stock set to 0', '2026-01-15 11:09:04'),
(4, 20, 2, 'Update', 'Updated details for Traditional Kite . Stock set to 50', '2026-01-15 11:10:26'),
(5, 18, 2, 'Update', 'Updated details for Wood Carved Phone Stand. Stock set to 0', '2026-01-15 11:11:01'),
(6, 1, 2, 'Update', 'Updated details for Handmade Organic Soap. Stock set to 50', '2026-01-15 21:09:10'),
(7, 20, 2, 'Update', 'Updated details for Traditional Kite . Stock set to 50', '2026-01-15 21:09:21'),
(8, 19, 2, 'Update', 'Updated details for Ceramic Mug . Stock set to 18', '2026-01-15 21:09:39'),
(9, 18, 2, 'Stock Update', 'Added 15 qty. New Total: 15', '2026-01-15 21:09:48'),
(10, 18, 2, 'Update', 'Updated details for Wood Carved Phone Stand. Stock set to 15', '2026-01-15 21:10:00'),
(11, 17, 2, 'Update', 'Updated details for Upcycled Denim Purse. Stock set to 40', '2026-01-15 21:10:09'),
(12, 16, 2, 'Update', 'Updated details for Crochet Plushie Bear. Stock set to 20', '2026-01-15 21:10:24'),
(13, 15, 2, 'Update', 'Updated details for Hand-Painted Tiffin. Stock set to 9', '2026-01-15 21:10:35'),
(14, 14, 2, 'Update', 'Updated details for Resin Flower Bookmark. Stock set to 0', '2026-01-15 21:10:45'),
(15, 13, 2, 'Update', 'Updated details for Bamboo Cutlery Set. Stock set to 5', '2026-01-15 21:10:58'),
(16, 12, 2, 'Update', 'Updated details for Embroidered Pouch. Stock set to 28', '2026-01-15 21:11:16'),
(17, 11, 2, 'Update', 'Updated details for Macrame Wall Hanging. Stock set to 12', '2026-01-15 21:11:29'),
(18, 10, 2, 'Update', 'Updated details for Hand-Stitched Notebook. Stock set to 35', '2026-01-15 21:11:43'),
(19, 9, 2, 'Update', 'Updated details for Coconut Shell Bowl. Stock set to 45', '2026-01-15 21:11:55'),
(20, 8, 2, 'Update', 'Updated details for Tie-Dye Tote Bag. Stock set to 60', '2026-01-15 21:12:05'),
(21, 7, 2, 'Update', 'Updated details for Clay Flower Pot. Stock set to 8', '2026-01-15 21:12:16'),
(22, 6, 2, 'Update', 'Updated details for Mengkuang Woven Mat. Stock set to 15', '2026-01-15 21:12:27'),
(23, 5, 2, 'Update', 'Updated details for Scented Soy Candle. Stock set to 30', '2026-01-15 21:12:37'),
(24, 4, 2, 'Update', 'Updated details for Beaded Key Chain. Stock set to 100', '2026-01-15 21:12:51'),
(25, 3, 2, 'Update', 'Updated details for Rattan Coaster Set. Stock set to 40', '2026-01-15 21:12:59'),
(26, 2, 2, 'Update', 'Updated details for Batik Cotton Scarf. Stock set to 23', '2026-01-15 21:13:07');

-- --------------------------------------------------------

--
-- 表的结构 `special_case`
--

CREATE TABLE `special_case` (
  `Case_ID` int(11) NOT NULL,
  `Case_Title` varchar(100) NOT NULL,
  `Case_LocationName` varchar(255) NOT NULL,
  `Case_Description` text DEFAULT NULL,
  `Case_Images` longtext DEFAULT NULL,
  `Target_Amount` decimal(10,2) NOT NULL,
  `Raised_Amount` decimal(10,2) DEFAULT 0.00,
  `Case_Status` varchar(50) DEFAULT 'Active',
  `Case_BankName` varchar(100) DEFAULT NULL,
  `Case_BankAccount` varchar(50) DEFAULT NULL,
  `Cancel_Reason` text DEFAULT NULL,
  `Completed_At` datetime DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp(),
  `Start_Date` date DEFAULT NULL,
  `End_Date` date DEFAULT NULL,
  `Case_Category` enum('Medical','Disability Support','Emergency Relief','Elderly Care','Children Support','Other Cases') NOT NULL DEFAULT 'Medical',
  `Case_Other_Category` varchar(255) DEFAULT NULL,
  `Urgency` enum('low','medium','high') DEFAULT 'medium',
  `Donor_Count` int(11) DEFAULT 0,
  `Case_Deadline` date DEFAULT NULL,
  `Case_Organizer` varchar(100) DEFAULT NULL,
  `Contact_Name` varchar(100) DEFAULT NULL,
  `Contact_Number` varchar(20) DEFAULT NULL,
  `Contact_Email` varchar(100) DEFAULT NULL,
  `Case_Address1` varchar(255) NOT NULL,
  `Case_Address2` varchar(255) DEFAULT NULL,
  `Case_Address3` varchar(255) DEFAULT NULL,
  `Case_City` varchar(100) NOT NULL,
  `Case_State` varchar(100) NOT NULL,
  `Case_PostalCode` varchar(20) NOT NULL,
  `Case_Country` varchar(100) DEFAULT 'Malaysia',
  `Case_Medical_Report` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `special_case`
--

INSERT INTO `special_case` (`Case_ID`, `Case_Title`, `Case_LocationName`, `Case_Description`, `Case_Images`, `Target_Amount`, `Raised_Amount`, `Case_Status`, `Case_BankName`, `Case_BankAccount`, `Cancel_Reason`, `Completed_At`, `Created_At`, `Start_Date`, `End_Date`, `Case_Category`, `Case_Other_Category`, `Urgency`, `Donor_Count`, `Case_Deadline`, `Case_Organizer`, `Contact_Name`, `Contact_Number`, `Contact_Email`, `Case_Address1`, `Case_Address2`, `Case_Address3`, `Case_City`, `Case_State`, `Case_PostalCode`, `Case_Country`, `Case_Medical_Report`) VALUES
(1, 'Rising from the Ashes - Help Siti Realize Her University Dream', 'Universiti Malaya (UM)', '19-year-old Siti comes from a hardcore poor family in Kedah. With her father paralyzed from a workplace accident and the family relying solely on her mother’s meager income from rubber tapping, life has been incredibly hard. Despite these challenges, Siti scored a perfect 4.0 CGPA in her STPM exams and received an offer to study Accounting at Universiti Malaya. However, the registration fees, the cost of a laptop, and living expenses in KL are insurmountable hurdles. We aim to raise RM 15,000 to cover her first-year expenses so that poverty does not clip the wings of this bright young student. ', '[\"uploads\\/cases\\/case_1768137355_6963a28b7d1ae_0.png\",\"uploads\\/cases\\/case_1768137355_6963a28b7ef65_1.png\"]', 15000.00, 15000.00, 'Completed', NULL, NULL, '', NULL, '2025-12-01 10:00:00', '2026-01-15', '2026-03-15', 'Other Cases', NULL, 'medium', 0, NULL, 'Love Bridge Education Fund', 'Cikgu Rahman', '+6013-5556666', 'edu.siti@lovebridge.org.my', 'Lot 123, Kampung Paya Rumput', 'Mukim Sungai Petani', '', 'Sungai Petani', 'Kedah', '08000', 'Malaysia', NULL),
(2, '\"Child of the Moon\" - Aid for Albino Student Aman', 'Pahang Specialist Hospital', '8-year-old Aman was born with Albinism. His lack of melanin makes his skin dangerously susceptible to burns and skin cancer from sunlight, but his biggest hurdle is his vision. Due to nystagmus and photophobia, he cannot see the whiteboard at school, hindering his dream of becoming a teacher. His parents, who work as rubber tappers, cannot afford the specialized visual aids he needs. We are fundraising to purchase custom low-vision glasses, medical-grade sunscreen, and an electronic reading aid to help Aman pursue his education confidently and safely. ', '[\"uploads\\/cases\\/case_1768137303_6963a25708403_0.png\",\"uploads\\/cases\\/case_1768137303_6963a2570a617_1.png\"]', 12000.00, 25000.00, 'Completed', NULL, NULL, '', NULL, '2025-11-15 09:30:00', '2026-03-01', '2026-04-30', 'Children Support', NULL, 'medium', 0, NULL, 'Love Bridge Pahang', 'Encik Rosli (Father)', '+6019-9988776', 'aman.help@lovebridge.org.my', 'Lot 45, Kampung Bahagia', 'Jalan Gambang', '', 'Kuantan', 'Pahang', '25000', 'Malaysia', NULL),
(3, 'Operation \"Fix A Heart\" - Urgent Fund for Baby Ali', 'Institut Jantung Negara (IJN)', 'At just 8 months old, Baby Ali is fighting a race against time. Born with Tetralogy of Fallot, a complex condition involving four heart defects, his blood cannot effectively carry oxygen, causing his lips and fingertips to turn a distressing shade of blue. Every breath is a struggle, and simple actions like feeding can cause him to faint from hypoxia. Doctors have warned that his heart is at its limit and requires immediate open-heart surgery to prevent fatal failure. His father, a delivery rider, and his mother, who left her job to care for him, cannot afford the RM 60,000 surgery fee. Your donation is Ali\'s only hope for a healthy childhood. ', '[\"uploads\\/cases\\/case_1768137371_6963a29b57a75_0.png\",\"uploads\\/cases\\/case_1768137371_6963a29b58377_1.png\"]', 60000.00, 1400.00, 'Active', NULL, NULL, '', NULL, '2025-12-05 14:20:00', '2026-01-13', '2026-05-01', 'Emergency Relief', NULL, 'medium', 1, NULL, 'Love Bridge Medical Fund', 'Puan Aminah (Mother)', '+6012-3456789', 'support.ali@lovebridge.org.my', 'No. 45, Blok B, PPR Kerinchi', 'Jalan Pantai Dalam', '', 'Kuala Lumpur', 'Kuala Lumpur', '59200', 'Malaysia', NULL),
(4, 'Sustaining Life - Emergency Dialysis Aid for Uncle Wong', 'Pusat Dialisis NKF', '68-year-old Uncle Wong, a retired construction worker, has battled End-Stage Renal Failure for three years. Requiring dialysis three times a week, he has completely depleted his life savings and EPF funds. Living alone in a low-cost flat with no income other than small welfare aid, he recently tried to skip treatments to save money, resulting in a near-fatal coma due to toxin buildup. This campaign aims to fund his dialysis treatments, blood booster injections, and transportation for the next 12 months, allowing him to live his remaining years with dignity. ', '[\"uploads\\/cases\\/case_1768137336_6963a278e2799_0.png\",\"uploads\\/cases\\/case_1768137336_6963a278e3e2d_1.png\"]', 25000.00, 8000.00, 'Active', NULL, NULL, '', NULL, '2025-11-20 11:00:00', '2026-01-11', '2026-12-31', 'Medical', NULL, 'medium', 0, NULL, 'Community Care Team', 'Mr. Ronald Tan', '+6012-7212535', 'care@lovebridge.org.my', 'Unit 4-12, Flat Sri Sabah', 'Jalan Cheras', '', 'Cheras', 'Kuala Lumpur', '56100', 'Malaysia', NULL),
(5, 'Standing Strong Again - Amputee Care for Grandma Halimah', 'Hospital Kuala Lumpur (HKL)', '72-year-old Puan Halimah, a diabetic for over 20 years, recently underwent a life-saving amputation of her left leg due to severe gangrene infection. Previously independent, she is now bedridden and unable to care for herself. Her healing process is slow and requires expensive silver ion dressings to prevent reinfection. We are seeking funds to provide her with a custom prosthetic leg, a lightweight wheelchair, wound care supplies, and diabetic nutritional milk. Your support will serve as her crutch, helping her regain her mobility and independence. ', '[\"uploads\\/cases\\/case_1768137269_6963a23522a13_0.png\",\"uploads\\/cases\\/case_1768137269_6963a23523daf_1.png\"]', 20000.00, 3000.00, 'Active', NULL, NULL, '', NULL, '2025-10-10 16:45:00', '2026-01-13', '2026-05-10', 'Elderly Care', NULL, 'medium', 0, NULL, 'Sentul Welfare Team', 'Puan Sarah (Volunteer)', '+6016-2233445', 'welfare@lovebridge.org.my', 'Unit 2-4, Flat Sri Perak', 'Bandar Baru Sentul', 'Sentul', 'Kuala Lumpur', 'Kuala Lumpur', '51000', 'Malaysia', NULL),
(6, 'Shattered Hoop Dreams - Emergency Chemo Fund for Jason', 'Sunway Medical Centre', 'Jason, a 15-year-old basketball captain, thought he had a sports injury until a checkup revealed Osteosarcoma, an aggressive bone cancer eating away at his leg bone. To avoid amputation and save his life, he requires high-dose chemotherapy and complex limb-salvage surgery. The cost of RM 80,000 is impossible for his parents, who are market vendors. Jason asked his mother in tears, \"Will I ever play again?\" We are raising funds to pay for his surgery and chemotherapy, hoping to save not just his leg, but his life and his dreams. ', '[\"uploads\\/cases\\/case_1768137201_6963a1f1c0460_0.png\",\"uploads\\/cases\\/case_1768137279_6963a23f269b3_0.png\"]', 80000.00, 0.00, 'Active', NULL, NULL, '', NULL, '2026-01-11 16:44:25', '2026-01-11', '2026-07-11', 'Children Support', NULL, 'medium', 0, NULL, 'Youth Cancer Support', 'Mr. Lim (Father)', '+6012-1122334', 'hopeforjason@lovebridge.org.my', '12, Jalan Wawasan 2', 'Pusat Bandar Puchong', '', 'Puchong', 'Selangor', '47100', 'Malaysia', NULL),
(7, 'Fighting for Love - Single Mom\'s Last Hope', 'Hospital Sultanah Aminah', '38-year-old Sarah has faced tragedy after tragedy. After losing her husband in a car accident two years ago, she became the sole provider for her two toddlers. Recently, she was diagnosed with Stage 3 Triple-Negative Breast Cancer, an aggressive form of the disease. The severe side effects of chemotherapy have forced her to stop working, cutting off the family\'s income. She fights with the mantra, \"I cannot die; my children are too young.\" This fund will cover her immunotherapy costs and living expenses for her children for one year. ', '[\"uploads\\/cases\\/case_1768137129_6963a1a9534de_0.png\",\"uploads\\/cases\\/case_1768137129_6963a1a9559b7_1.png\"]', 55000.00, 60100.00, 'Completed', NULL, NULL, '', '2026-01-15 10:16:27', '2026-01-11 16:46:33', '2026-01-11', '2026-08-11', 'Emergency Relief', NULL, 'medium', 1, NULL, 'Johor Women Aid', 'Sarah Binti Ali', '+6017-8899000', 'help.sarah@lovebridge.org.my', 'No 88, Jalan Kebudayaan', 'Taman Universiti', '', 'Skudai', 'Johor', '81300', 'Malaysia', NULL),
(8, 'Rebirth from Fire - Skin Graft Surgery for Xiao Mei', 'Hospital Raja Permaisuri Bainun', 'A kitchen accident changed 5-year-old Xiao Mei’s life in an instant when boiling soup spilled over her, causing deep second-degree burns over 40% of her body. While she survived the initial trauma, severe scar contractures are now tightening around her skin, preventing her arm from straightening and affecting her growth. She endures nightly pain and itching. We are raising funds for multiple skin graft surgeries, laser scar treatments, and custom pressure garments to help restore her mobility and reduce the scarring. ', '[\"uploads\\/cases\\/case_1768137109_6963a195d04cc_0.png\",\"uploads\\/cases\\/case_1768137109_6963a195d2193_1.png\"]', 45000.00, 150.00, 'Active', NULL, NULL, '', NULL, '2026-01-11 16:48:45', '2026-01-13', '2026-10-13', 'Children Support', NULL, 'medium', 1, NULL, 'Perak Children Fund', 'Mrs. Wong (Mother)', '+6011-22334455', 'help.xiaomei@lovebridge.org.my', '77, Laluan Tasek Timur', 'Taman Tasek Indra', '', 'Ipoh', 'Perak', '31400', 'Malaysia', NULL),
(10, 'farhan', '', 'too stress', '[\"uploads\\/cases\\/case_1768677832_696be1c833e86_0.jpg\"]', 22.00, 0.00, 'Active', 'AmBank', '1212121212121', '', NULL, '2026-01-18 03:23:52', '2026-01-18', '2026-01-20', 'Medical', NULL, 'medium', 0, NULL, 'farhan', 'wen qin', '+6011-11119233', 'fhfh@gmail.com', '', '', '', '', 'Johor', '', 'Malaysia', NULL);

--
-- 触发器 `special_case`
--
DELIMITER $$
CREATE TRIGGER `notify_case_insert` AFTER INSERT ON `special_case` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('New Fundraising Case: ', NEW.Case_Title), 'Target', 0, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_case_update` AFTER UPDATE ON `special_case` FOR EACH ROW BEGIN
    IF OLD.Raised_Amount != NEW.Raised_Amount THEN
       INSERT INTO `admin_notifications` (`Message`, `Type`, `Is_Read`, `Created_At`) 
       VALUES (CONCAT('Case Update: ', NEW.Case_Title, ' raised RM', NEW.Raised_Amount), 'Donation', 0, NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `staff`
--

CREATE TABLE `staff` (
  `Staff_ID` int(11) NOT NULL,
  `Staff_FullName` varchar(100) NOT NULL,
  `Staff_ContactNumber` varchar(20) NOT NULL,
  `Staff_ICNumber` varchar(20) NOT NULL,
  `Staff_Email` varchar(100) NOT NULL,
  `Staff_Password` varchar(255) DEFAULT NULL,
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
  `Is_Deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Deleted',
  `Staff_IsFirstLogin` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `staff`
--

INSERT INTO `staff` (`Staff_ID`, `Staff_FullName`, `Staff_ContactNumber`, `Staff_ICNumber`, `Staff_Email`, `Staff_Password`, `Staff_DOB`, `Staff_Address1`, `Staff_Address2`, `Staff_Address3`, `Staff_City`, `Staff_State`, `Staff_PostalCode`, `Staff_Country`, `Staff_Comment`, `Staff_Role`, `Staff_Status`, `Branch_ID`, `Staff_JoinDate`, `Staff_ProfilePicture`, `Admin_ID`, `Is_Deleted`, `Staff_IsFirstLogin`) VALUES
(1, 'Lim Wen Qin', '+6011-11190233', '030303-01-0303', 'lim.wen.qin@student.mmu.edu.my', '$2y$10$wI3ynrr3', '2003-03-03', '19, jalan bukit beruang utama 6', 'taman buklit beruang utama', '', 'melaka', 'Melaka', '75450', 'Malaysia', '', 'Staff', 'Active', NULL, '2025-12-07', 'uploads/staff_profiles/staff_1765123541_6935a5d520873.png', 2, 0, 1),
(4, 'steve', '+6012-34567898', '030517-01-0373', 'thongyuenzhen@gmail.com', '$2y$10$s4PtchL9.Lhy9dcKmrf0.eCM1qrxynsrSq4cLqR.c2XypF7q7F7w2', '2003-05-17', '', '', '', '', '', '', 'Malaysia', '', 'Staff', 'Active', NULL, '2026-01-08', NULL, 2, 0, 1),
(6, 'john', '+6012-3456789', '010203-04-0506', 'johndoe123@gmail.com', '$2y$10$FAwwBFD1hIOcrWPKJlvJbOLod5Za.bTjk1l5YsaTUbaRAAWs74tDy', '2001-02-03', '', '', '', '', '', '', 'Malaysia', '', 'Staff', 'Active', NULL, '2026-01-08', NULL, 2, 0, 1),
(8, 'ronald tan bin hong', '+6012-7212535', '010203-04-0506', 'ronaldtan0404@gmail.com', '$2y$10$n.Y6jwngCWE/KxeYcAX6ouE6vwq63ZhL1bHHfbtXhxY5ihQa7O8WK', '2001-02-06', '', '', '', '', '', '', 'Malaysia', '', 'Staff', 'Active', NULL, '2026-01-09', NULL, 2, 0, 0),
(9, 'thong', '+6011-11190233', '030517', 'thong@gmail.org', '$2y$10$b7ruWGX5udro8Hs0jXtAm.LRsO97eDzswp/YGRSNifATt7497zZJK', '2003-05-17', '', '', '', '', '', '', 'Malaysia', '', 'Staff', 'Active', NULL, '2026-01-15', NULL, 2, 0, 1),
(10, 'jojo', '+6011-11190233', '030517-01-0373', 'jojo@example.com', '$2y$10$Ry.gVthd/LQITzeT/fyth.Hv6Y0LGwVxRQLqnoaXXp1EaX46hCYfa', '2003-05-17', '', '', '', '', '', '', 'Malaysia', '', 'Staff', 'Active', NULL, '2026-01-16', NULL, 2, 0, 1);

--
-- 触发器 `staff`
--
DELIMITER $$
CREATE TRIGGER `notify_staff_insert` AFTER INSERT ON `staff` FOR EACH ROW BEGIN
    INSERT INTO `admin_notifications` (`Message`, `Type`, `Link`, `Is_Read`, `Created_At`) 
    VALUES (CONCAT('New Staff Member Added: ', NEW.Staff_FullName), 'New_Staff', 'staff_management_page.php', 0, NOW());
END
$$
DELIMITER ;

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
  `Story_Date` date DEFAULT NULL,
  `Story_Title` varchar(255) NOT NULL,
  `Story_Author` varchar(100) DEFAULT NULL,
  `Story_Category` enum('Donor Story','Impact Report','News','Event','Success Story') DEFAULT 'Donor Story',
  `Story_Description` text NOT NULL,
  `Story_Image` longtext DEFAULT NULL,
  `Story_Status` enum('Published','Draft') DEFAULT 'Published',
  `Created_At` datetime DEFAULT current_timestamp(),
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `story`
--

INSERT INTO `story` (`Story_ID`, `Story_Date`, `Story_Title`, `Story_Author`, `Story_Category`, `Story_Description`, `Story_Image`, `Story_Status`, `Created_At`, `Updated_At`) VALUES
(2, NULL, 'EMERGENCY: \"Operation Ark\" Launched for East Coast Flood Victims', NULL, 'Donor Story', 'The Northeast Monsoon has struck hard. Following continuous heavy rainfall, severe flooding has displaced over 5,000 families in Kelantan and Terengganu. In response, Love Bridge has officially activated \"Operation Ark\" from our KL Main Operations Hub.\r\n\r\nOur Quick Response Team (QRT) is currently deploying inflatable boats to reach cut-off areas in Kota Bharu. We are urgently appealing for public donations to procure clean water tablets, hygiene kits, and dry food packs. Every minute counts in disaster relief. Join us to be the lifeline for those stranded in the floods.', 'uploads/stories/1768137483_Gemini_Generated_Image_lm00oxlm00oxlm00.png', 'Published', '2026-01-11 21:18:03', '2026-01-11 21:18:03'),
(3, NULL, 'oin Us for \"Dreams Take Flight\": A Valentine’s Date with 45 Angels', NULL, 'Donor Story', 'This Valentine’s Day, why not share your love with those who need it most? Our Johor branch, Four Ace Sanctuary, is hosting a special open day titled \"Dreams Take Flight\". We are inviting 20 enthusiastic volunteers to spend the afternoon with our 45 wonderful children.\r\n\r\nThe itinerary includes an \"Ice-Breaking\" session, a drawing contest, and a KFC charity lunch. It is not just about the food or games; it is about showing these children that they are seen, heard, and loved. Registration is now open on our volunteer portal.', 'uploads/stories/1768137557_Gemini_Generated_Image_kaic4tkaic4tkaic.png', 'Published', '2026-01-11 21:19:17', '2026-01-11 21:19:17');

-- --------------------------------------------------------

--
-- 表的结构 `system_pages`
--

CREATE TABLE `system_pages` (
  `Page_ID` int(11) NOT NULL,
  `Page_Key` varchar(50) NOT NULL,
  `Page_Content` longtext NOT NULL,
  `Last_Updated` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `team_members`
--

CREATE TABLE `team_members` (
  `Team_ID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Position` varchar(100) NOT NULL,
  `Description` text NOT NULL,
  `Images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`Images`)),
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `team_members`
--

INSERT INTO `team_members` (`Team_ID`, `Name`, `Position`, `Description`, `Images`, `Created_At`) VALUES
(1, 'Mr. Lim Wen Qin', 'Founder & Chairperson', 'Medical doctor with 20+ years of humanitarian experience', '[\"images/team_1768482933_Lim Wen Qin.jpeg\"]', '2026-01-12 06:21:16'),
(2, 'Mr. Thong Yuen Zhen', 'Executive Director', 'Former corporate leader turned full-time humanitarian', '[\"images/team_1768482948_Thong Yuen Zhen.jpeg\"]', '2026-01-12 06:21:16'),
(3, 'Mr. Ronald Tan Bin Hong', 'Program Director', 'Specialized in community development and disaster response', '[\"images\\/team_1768483579_Ronald Tan Bin Hong.jpeg\"]', '2026-01-12 06:21:16');

-- --------------------------------------------------------

--
-- 表的结构 `terms_conditions`
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
-- 转存表中的数据 `terms_conditions`
--

INSERT INTO `terms_conditions` (`id`, `version`, `content`, `effective_date`, `created_at`, `is_active`) VALUES
(1, '1.0', '<h2>Love Bridge Donation Platform - Terms & Conditions</h2>\r\n<p><strong>Effective Date:</strong> [CURRENT_DATE]</p>\r\n\r\n<h3>1. Acceptance of Terms</h3>\r\n<p>By accessing and using the Love Bridge donation platform, you accept and agree to be bound by the terms and provision of this agreement.</p>\r\n\r\n<h3>2. Donation Policy</h3>\r\n<p>All donations made through Love Bridge are voluntary and non-refundable. Donations are used to support our charitable programs as described on our website.</p>\r\n\r\n<h3>3. User Responsibilities</h3>\r\n<p>Users must provide accurate information when making donations and agree not to use the platform for any unlawful purpose.</p>\r\n\r\n<h3>4. Privacy</h3>\r\n<p>Your privacy is important to us. Please review our Privacy Policy to understand how we collect and use your information.</p>\r\n\r\n<h3>5. Modifications</h3>\r\n<p>Love Bridge reserves the right to modify these terms at any time. Continued use of the platform after changes constitutes acceptance.</p>\r\n\r\n<h3>6. Contact Information</h3>\r\n<p>For questions about these Terms & Conditions, please contact us at terms@lovebridge.org.</p>', '2026-01-06', '2026-01-05 17:01:32', 1);

-- --------------------------------------------------------

--
-- 表的结构 `wallet_transaction`
--

CREATE TABLE `wallet_transaction` (
  `Wallet_Trans_ID` int(11) NOT NULL,
  `Donor_ID` int(11) NOT NULL,
  `Order_ID` int(11) DEFAULT NULL,
  `Transaction_Type` enum('Credit','Debit') NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `wallet_transaction`
--

INSERT INTO `wallet_transaction` (`Wallet_Trans_ID`, `Donor_ID`, `Order_ID`, `Transaction_Type`, `Amount`, `Description`, `Created_At`) VALUES
(1, 6, 8, '', 20.00, '', '2026-01-12 12:21:32'),
(2, 6, 9, '', 50.00, '', '2026-01-12 12:23:11'),
(3, 6, 13, '', 20.00, '', '2026-01-12 12:31:19'),
(4, 6, 14, '', 20.00, '', '2026-01-12 12:35:36'),
(5, 6, 15, 'Credit', 50.00, 'Top-up via TNG eWallet', '2026-01-12 12:43:27'),
(6, 6, 16, 'Credit', 50.00, 'Top-up via Credit/Debit Card', '2026-01-12 12:55:21'),
(7, 6, 17, 'Debit', 100.00, 'Donate to Case: Fighting for Love - Single Mom\'s Last Hope', '2026-01-12 12:55:47'),
(8, 6, 18, '', 50.00, 'Donation by E-Wallet', '2026-01-12 12:57:36'),
(9, 7, 20, 'Credit', 50.00, 'Top-up via Credit/Debit Card', '2026-01-15 10:19:37'),
(10, 7, 21, '', 30.00, 'Donation by E-Wallet', '2026-01-15 10:30:29'),
(11, 11, 24, 'Credit', 300.00, 'Top-up via TNG eWallet', '2026-01-17 21:21:16'),
(12, 11, 25, 'Credit', 300.00, 'Top-up via TNG eWallet', '2026-01-17 21:21:34'),
(13, 11, 26, '', 100.00, 'Donate to Case: Operation \"Fix A Heart\" - Urgent Fund for Baby Ali', '2026-01-17 21:22:01'),
(14, 11, 27, '', 100.00, 'Donate to Activity: 2026 Monsoon Flood - East Coast Emergency Relief (Operation Ark)', '2026-01-17 21:23:24');

-- --------------------------------------------------------

--
-- 表的结构 `withdrawals`
--

CREATE TABLE `withdrawals` (
  `Withdrawal_ID` int(11) NOT NULL,
  `Branch_ID` int(11) NOT NULL,
  `Case_ID` int(11) DEFAULT NULL,
  `Activity_ID` int(11) DEFAULT NULL COMMENT '如果是针对某个活动的提款',
  `Amount` decimal(10,2) NOT NULL,
  `Status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `Request_Date` datetime DEFAULT current_timestamp(),
  `Processed_Date` datetime DEFAULT NULL,
  `Bank_Name` varchar(100) DEFAULT NULL,
  `Bank_Account` varchar(50) DEFAULT NULL,
  `Reference_Proof` text DEFAULT NULL,
  `Admin_ID` int(11) DEFAULT NULL COMMENT '处理提款的管理员',
  `Approved_By` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `withdrawals`
--

INSERT INTO `withdrawals` (`Withdrawal_ID`, `Branch_ID`, `Case_ID`, `Activity_ID`, `Amount`, `Status`, `Request_Date`, `Processed_Date`, `Bank_Name`, `Bank_Account`, `Reference_Proof`, `Admin_ID`, `Approved_By`) VALUES
(1, 2, NULL, NULL, 50.00, 'Completed', '2026-01-15 08:22:20', NULL, 'N/A', 'N/A', '[\"uploads\\/withdrawals\\/wd_1768436540_6968333c252f8_0.jpg\"]', 2, NULL),
(2, 1, NULL, NULL, 20.00, 'Pending', '2026-01-15 08:49:24', NULL, 'N/A', 'N/A', '[\"uploads\\/withdrawals\\/wd_1768438164_69683994b364a_0.jpg\"]', 2, NULL),
(3, 2, NULL, 3, 150.00, 'Pending', '2026-01-18 00:58:51', NULL, 'N/A', 'N/A', '[\"uploads\\/withdrawals\\/wd_1768669131_696bbfcb7e5da_0.jpg\"]', 2, NULL);

--
-- 转储表的索引
--

--
-- 表的索引 `about_us_info`
--
ALTER TABLE `about_us_info`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `activity`
--
ALTER TABLE `activity`
  ADD PRIMARY KEY (`Activity_ID`),
  ADD KEY `Activity_BrANCH_ID_FK` (`Branch_ID`);

--
-- 表的索引 `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`Admin_ID`);

--
-- 表的索引 `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`AdminNotification_ID`),
  ADD KEY `Contact_ID` (`Contact_ID`);

--
-- 表的索引 `branch`
--
ALTER TABLE `branch`
  ADD PRIMARY KEY (`Branch_ID`),
  ADD KEY `branch_admin_id_fk` (`Admin_ID`);

--
-- 表的索引 `case_comments`
--
ALTER TABLE `case_comments`
  ADD PRIMARY KEY (`Comment_ID`),
  ADD KEY `Case_ID` (`Case_ID`),
  ADD KEY `Donor_ID` (`Donor_ID`);

--
-- 表的索引 `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`Contact_ID`);

--
-- 表的索引 `contact_settings`
--
ALTER TABLE `contact_settings`
  ADD PRIMARY KEY (`Setting_ID`);

--
-- 表的索引 `donor`
--
ALTER TABLE `donor`
  ADD PRIMARY KEY (`Donor_ID`);

--
-- 表的索引 `donor_login_attempts`
--
ALTER TABLE `donor_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `attempt_time` (`attempt_time`);

--
-- 表的索引 `donor_password_reset`
--
ALTER TABLE `donor_password_reset`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `donor_id` (`donor_id`);

--
-- 表的索引 `donor_security_logs`
--
ALTER TABLE `donor_security_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- 表的索引 `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`Email_ID`);

--
-- 表的索引 `headquarters`
--
ALTER TABLE `headquarters`
  ADD PRIMARY KEY (`HQ_ID`);

--
-- 表的索引 `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);

--
-- 表的索引 `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`Notification_ID`),
  ADD KEY `Notification_Donor_ID_FK` (`Donor_ID`);

--
-- 表的索引 `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `Order_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Order_Activity_ID_FK` (`Activity_ID`),
  ADD KEY `Order_Payment_ID_FK` (`Payment_ID`),
  ADD KEY `Order_Case_ID_FK` (`Case_ID`),
  ADD KEY `Order_Branch_ID_FK` (`Branch_ID`);

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
-- 表的索引 `policy_acceptances`
--
ALTER TABLE `policy_acceptances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terms_version_id` (`terms_version_id`),
  ADD KEY `privacy_version_id` (`privacy_version_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- 表的索引 `privacy_policy`
--
ALTER TABLE `privacy_policy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_effective_date` (`effective_date`);

--
-- 表的索引 `receipt`
--
ALTER TABLE `receipt`
  ADD PRIMARY KEY (`Receipt_ID`),
  ADD UNIQUE KEY `unique_receipt_number` (`Receipt_Receipt_Number`),
  ADD KEY `Receipt_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `Receipt_Order_ID_FK` (`Order_ID`);

--
-- 表的索引 `recurring_donation`
--
ALTER TABLE `recurring_donation`
  ADD PRIMARY KEY (`Recurring_ID`),
  ADD KEY `RecurringDonation_Donor_ID_FK` (`Donor_ID`),
  ADD KEY `RecurringDonation_Branch_ID_FK` (`Branch_ID`),
  ADD KEY `RecurringDonation_Activity_ID_FK` (`Activity_ID`),
  ADD KEY `RecurringDonation_Case_ID_FK` (`Case_ID`);

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
  ADD PRIMARY KEY (`Reward_ID`),
  ADD KEY `Reward_Code` (`Reward_Code`);

--
-- 表的索引 `reward_logs`
--
ALTER TABLE `reward_logs`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `Log_Reward_FK` (`Reward_ID`),
  ADD KEY `Log_Admin_FK` (`Admin_ID`);

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
-- 表的索引 `system_pages`
--
ALTER TABLE `system_pages`
  ADD PRIMARY KEY (`Page_ID`),
  ADD UNIQUE KEY `Page_Key` (`Page_Key`);

--
-- 表的索引 `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`Team_ID`);

--
-- 表的索引 `terms_conditions`
--
ALTER TABLE `terms_conditions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_effective_date` (`effective_date`);

--
-- 表的索引 `wallet_transaction`
--
ALTER TABLE `wallet_transaction`
  ADD PRIMARY KEY (`Wallet_Trans_ID`),
  ADD KEY `Donor_ID` (`Donor_ID`),
  ADD KEY `Order_ID` (`Order_ID`);

--
-- 表的索引 `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`Withdrawal_ID`),
  ADD KEY `Branch_ID` (`Branch_ID`),
  ADD KEY `Activity_ID` (`Activity_ID`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `about_us_info`
--
ALTER TABLE `about_us_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `activity`
--
ALTER TABLE `activity`
  MODIFY `Activity_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `admin`
--
ALTER TABLE `admin`
  MODIFY `Admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `AdminNotification_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- 使用表AUTO_INCREMENT `branch`
--
ALTER TABLE `branch`
  MODIFY `Branch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `case_comments`
--
ALTER TABLE `case_comments`
  MODIFY `Comment_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `Contact_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- 使用表AUTO_INCREMENT `contact_settings`
--
ALTER TABLE `contact_settings`
  MODIFY `Setting_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `donor`
--
ALTER TABLE `donor`
  MODIFY `Donor_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- 使用表AUTO_INCREMENT `donor_login_attempts`
--
ALTER TABLE `donor_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用表AUTO_INCREMENT `donor_password_reset`
--
ALTER TABLE `donor_password_reset`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `donor_security_logs`
--
ALTER TABLE `donor_security_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `Email_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `headquarters`
--
ALTER TABLE `headquarters`
  MODIFY `HQ_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `notifications`
--
ALTER TABLE `notifications`
  MODIFY `Notification_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- 使用表AUTO_INCREMENT `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- 使用表AUTO_INCREMENT `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- 使用表AUTO_INCREMENT `point`
--
ALTER TABLE `point`
  MODIFY `Points_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- 使用表AUTO_INCREMENT `policy_acceptances`
--
ALTER TABLE `policy_acceptances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `privacy_policy`
--
ALTER TABLE `privacy_policy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `receipt`
--
ALTER TABLE `receipt`
  MODIFY `Receipt_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `recurring_donation`
--
ALTER TABLE `recurring_donation`
  MODIFY `Recurring_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `redemption_order`
--
ALTER TABLE `redemption_order`
  MODIFY `Redemption_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `reward_item`
--
ALTER TABLE `reward_item`
  MODIFY `Reward_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- 使用表AUTO_INCREMENT `reward_logs`
--
ALTER TABLE `reward_logs`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- 使用表AUTO_INCREMENT `special_case`
--
ALTER TABLE `special_case`
  MODIFY `Case_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- 使用表AUTO_INCREMENT `staff`
--
ALTER TABLE `staff`
  MODIFY `Staff_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- 使用表AUTO_INCREMENT `staff_activity`
--
ALTER TABLE `staff_activity`
  MODIFY `StaffActivity_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `story`
--
ALTER TABLE `story`
  MODIFY `Story_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `system_pages`
--
ALTER TABLE `system_pages`
  MODIFY `Page_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `team_members`
--
ALTER TABLE `team_members`
  MODIFY `Team_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `terms_conditions`
--
ALTER TABLE `terms_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `wallet_transaction`
--
ALTER TABLE `wallet_transaction`
  MODIFY `Wallet_Trans_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- 使用表AUTO_INCREMENT `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `Withdrawal_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 限制导出的表
--

--
-- 限制表 `activity`
--
ALTER TABLE `activity`
  ADD CONSTRAINT `Activity_BrANCH_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`);

--
-- 限制表 `branch`
--
ALTER TABLE `branch`
  ADD CONSTRAINT `branch_admin_id_fk` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`);

--
-- 限制表 `case_comments`
--
ALTER TABLE `case_comments`
  ADD CONSTRAINT `case_comments_ibfk_1` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `case_comments_ibfk_2` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- 限制表 `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `Notification_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

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
-- 限制表 `policy_acceptances`
--
ALTER TABLE `policy_acceptances`
  ADD CONSTRAINT `policy_acceptances_ibfk_1` FOREIGN KEY (`terms_version_id`) REFERENCES `terms_conditions` (`id`),
  ADD CONSTRAINT `policy_acceptances_ibfk_2` FOREIGN KEY (`privacy_version_id`) REFERENCES `privacy_policy` (`id`);

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
  ADD CONSTRAINT `RecurringDonation_Activity_ID_FK` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`),
  ADD CONSTRAINT `RecurringDonation_Branch_ID_FK` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `RecurringDonation_Case_ID_FK` FOREIGN KEY (`Case_ID`) REFERENCES `special_case` (`Case_ID`),
  ADD CONSTRAINT `RecurringDonation_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`);

--
-- 限制表 `redemption_order`
--
ALTER TABLE `redemption_order`
  ADD CONSTRAINT `RedemptionOrder_Donor_ID_FK` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`),
  ADD CONSTRAINT `RedemptionOrder_Reward_ID_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`);

--
-- 限制表 `reward_logs`
--
ALTER TABLE `reward_logs`
  ADD CONSTRAINT `Log_Admin_FK` FOREIGN KEY (`Admin_ID`) REFERENCES `admin` (`Admin_ID`),
  ADD CONSTRAINT `Log_Reward_FK` FOREIGN KEY (`Reward_ID`) REFERENCES `reward_item` (`Reward_ID`) ON DELETE CASCADE;

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

--
-- 限制表 `wallet_transaction`
--
ALTER TABLE `wallet_transaction`
  ADD CONSTRAINT `wallet_transaction_ibfk_1` FOREIGN KEY (`Donor_ID`) REFERENCES `donor` (`Donor_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `wallet_transaction_ibfk_2` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`) ON DELETE SET NULL;

--
-- 限制表 `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`),
  ADD CONSTRAINT `withdrawals_ibfk_2` FOREIGN KEY (`Activity_ID`) REFERENCES `activity` (`Activity_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
