-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2025 at 03:15 PM
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
-- Database: `iba_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `archival_settings`
--

CREATE TABLE `archival_settings` (
  `id` int(11) NOT NULL,
  `timing_option` enum('immediate','6_months','1_year','2_years') DEFAULT '1_year',
  `storage_destination` enum('local','external','google_drive') DEFAULT 'google_drive',
  `auto_archival_enabled` tinyint(1) DEFAULT 1,
  `last_run` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archival_settings`
--

INSERT INTO `archival_settings` (`id`, `timing_option`, `storage_destination`, `auto_archival_enabled`, `last_run`, `updated_at`) VALUES
(1, '2_years', 'google_drive', 1, '2025-10-18 00:50:17', '2025-10-20 01:00:49');

-- --------------------------------------------------------

--
-- Table structure for table `archive_logs`
--

CREATE TABLE `archive_logs` (
  `id` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `archival_type` enum('school_year','graduation') DEFAULT 'school_year',
  `student_id` int(11) DEFAULT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `graduation_date` date DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `file_count` int(11) DEFAULT 0,
  `archive_size` bigint(20) DEFAULT 0,
  `storage_type` enum('local','cloud') DEFAULT 'cloud',
  `archive_path` varchar(500) DEFAULT NULL,
  `status` enum('success','failed','in_progress') DEFAULT 'in_progress',
  `error_message` text DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `backup_type` enum('manual','automatic') DEFAULT 'manual',
  `staff_user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `storage_type` varchar(50) NOT NULL,
  `student_count` int(11) DEFAULT 0,
  `file_count` int(11) DEFAULT 0,
  `files_uploaded` int(11) DEFAULT 0,
  `files_updated` int(11) DEFAULT 0,
  `files_skipped` int(11) DEFAULT 0,
  `is_incremental` tinyint(1) DEFAULT 0,
  `backup_size` bigint(20) DEFAULT 0,
  `created_by` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('success','failed','in_progress') DEFAULT 'in_progress',
  `error_message` text DEFAULT NULL,
  `backup_path` varchar(500) DEFAULT NULL,
  `file_id` varchar(255) DEFAULT NULL,
  `google_drive_file_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_logs`
--

INSERT INTO `backup_logs` (`id`, `backup_type`, `staff_user_id`, `filename`, `storage_type`, `student_count`, `file_count`, `files_uploaded`, `files_updated`, `files_skipped`, `is_incremental`, `backup_size`, `created_by`, `created_at`, `completed_at`, `status`, `error_message`, `backup_path`, `file_id`, `google_drive_file_id`) VALUES
(288, 'manual', 0, '', 'cloud', 1, 3, 3, 0, 0, 1, 0, '', '2025-10-19 19:06:11', '2025-10-19 11:06:39', 'success', NULL, 'IBACMI Backup ', NULL, NULL),
(289, 'automatic', 0, '', 'cloud', 1, 1, 1, 0, 3, 1, 0, '', '2025-10-19 19:43:20', '2025-10-19 11:43:38', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(290, 'automatic', 0, '', 'cloud', 1, 1, 1, 0, 3, 1, 0, '', '2025-10-19 19:43:20', '2025-10-19 11:43:38', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(291, 'automatic', 0, '', 'cloud', 1, 1, 1, 0, 3, 1, 0, '', '2025-10-19 19:43:29', '2025-10-19 11:43:43', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(292, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 4, 1, 0, '', '2025-10-19 19:43:46', '2025-10-19 11:43:51', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(293, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 4, 1, 0, '', '2025-10-19 19:49:46', '2025-10-19 11:49:52', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(294, 'automatic', 0, '', 'cloud', 1, 2, 2, 0, 4, 1, 0, '', '2025-10-19 19:53:16', '2025-10-19 11:53:43', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(295, 'automatic', 0, '', 'cloud', 1, 1, 1, 0, 5, 1, 0, '', '2025-10-19 19:53:27', '2025-10-19 11:53:44', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(296, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 6, 1, 0, '', '2025-10-19 19:53:37', '2025-10-19 11:53:45', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(297, 'automatic', 0, '', 'cloud', 1, 2, 2, 0, 6, 1, 0, '', '2025-10-19 19:56:10', '2025-10-19 11:56:30', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(298, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 8, 1, 0, '', '2025-10-19 19:56:30', '2025-10-19 11:56:37', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(299, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 8, 1, 0, '', '2025-10-19 19:58:31', '2025-10-19 11:58:40', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(300, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 8, 1, 0, '', '2025-10-19 20:02:07', '2025-10-19 12:02:15', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(301, 'automatic', 0, '', 'cloud', 1, 1, 1, 0, 8, 1, 0, '', '2025-10-19 20:02:59', '2025-10-19 12:03:14', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(302, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 9, 1, 0, '', '2025-10-19 20:04:29', '2025-10-19 12:04:37', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(303, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 9, 1, 0, '', '2025-10-19 20:06:30', '2025-10-19 12:06:39', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(304, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 9, 1, 0, '', '2025-10-19 20:07:38', '2025-10-19 12:07:46', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(305, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 9, 1, 0, '', '2025-10-19 20:07:52', '2025-10-19 12:08:00', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(306, 'automatic', 0, '', 'cloud', 0, 0, 0, 0, 5, 1, 0, '', '2025-10-19 20:09:39', '2025-10-19 12:09:46', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(307, 'manual', 0, '', 'local', 2, 9, 9, 0, 0, 1, 26203366, '', '2025-10-19 20:10:10', '2025-10-19 12:10:11', 'success', NULL, 'backups/IBACMI_Backup_2025_2026', NULL, NULL),
(308, 'automatic', 0, '', 'cloud', 1, 1, 1, 0, 9, 1, 0, '', '2025-10-20 10:14:29', '2025-10-20 02:14:43', 'success', NULL, 'IBACMI Backup 2025-2026', NULL, NULL),
(309, 'manual', 0, '', 'local', 2, 10, 10, 0, 0, 1, 26378543, '', '2025-10-20 16:39:58', '2025-10-20 08:39:58', 'success', NULL, 'IBACMI_Backup_2025_2026', NULL, NULL),
(310, 'manual', 0, '', 'local', 1, 2, 2, 0, 10, 1, 7622031, '', '2025-10-20 16:41:53', '2025-10-20 08:41:54', 'success', NULL, 'IBACMI_Backup_2025_2026', NULL, NULL),
(311, 'manual', 0, '', 'cloud', 0, 0, 0, 0, 0, 1, 0, '', '2025-10-20 16:42:17', '2025-10-20 08:42:17', 'failed', 'Google Drive is not connected. Please connect first.', NULL, NULL, NULL),
(312, 'manual', 0, '', 'cloud', 0, 0, 0, 0, 0, 1, 0, '', '2025-10-20 16:56:58', '2025-10-20 08:56:58', 'failed', 'Google Drive is not connected. Please connect first.', NULL, NULL, NULL),
(313, 'manual', 0, '', 'local', 1, 2, 2, 0, 12, 1, 7545652, '', '2025-10-20 19:37:54', '2025-10-20 11:37:55', 'success', NULL, 'IBACMI_Backup_2025_2026', NULL, NULL),
(314, 'manual', 0, '', 'cloud', 1, 4, 4, 0, 10, 1, 0, '', '2025-10-20 20:08:46', '2025-10-20 12:09:45', 'success', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `backup_manifest`
--

CREATE TABLE `backup_manifest` (
  `id` int(11) NOT NULL,
  `backup_log_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `file_hash` varchar(32) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `google_drive_file_id` varchar(255) DEFAULT NULL,
  `google_drive_folder_id` varchar(255) DEFAULT NULL,
  `backup_type` enum('new','modified','unchanged','repaired','archived') DEFAULT 'new',
  `backed_up_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_synced_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_manifest`
--

INSERT INTO `backup_manifest` (`id`, `backup_log_id`, `student_id`, `document_id`, `file_hash`, `file_size`, `file_path`, `google_drive_file_id`, `google_drive_folder_id`, `backup_type`, `backed_up_at`, `last_synced_at`) VALUES
(158, 288, 227, 959, 'b6ef6c59c2128d5a54020515cd4855ba', 34166, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Shanelle Mae_Harina\\227_5_1760871863.jpg', '1ekh5hQgj1VnW4T71KATfSzIN0-2v9iW6', '1nlct6yNnzrZkpSaXL1fu6HEmp4XAwVR4', '', '2025-10-19 11:06:26', '2025-10-19 11:06:26'),
(159, 288, 227, 957, 'e14cb1c3de0c03488cc6db58aa8db9db', 790877, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Shanelle Mae_Harina\\227_1_1760871863.jpg', '1p3aagkv9KwdN74hTtpkd3UcQyRLM_zao', '1nlct6yNnzrZkpSaXL1fu6HEmp4XAwVR4', '', '2025-10-19 11:06:32', '2025-10-19 11:06:32'),
(160, 288, 227, 958, 'c7d06b177463d5d10a3aad27865de0a5', 2680279, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Shanelle Mae_Harina\\227_2_1760871863.png', '13jAH8NmxsgUveG-xNh4ZzA07zxXCbFPk', '1nlct6yNnzrZkpSaXL1fu6HEmp4XAwVR4', '', '2025-10-19 11:06:39', '2025-10-19 11:06:39'),
(161, 289, 227, 960, '8e231de321a6fcd44edb64494c202989', 8311621, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\227_birth_1760874171.png', '19SrWr2KT3PCYBzGHWO7nMvfbgXSxYUMl', '1vx0fIKGrdtNmvwdR4tULDNZHlGJud6e2', '', '2025-10-19 11:43:38', '2025-10-19 11:43:38'),
(162, 290, 227, 960, '8e231de321a6fcd44edb64494c202989', 8311621, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\227_birth_1760874171.png', '1W30b49l5s2UJp4Fuk2kur-mQk72EtB6z', '1mYlS1RMX0OlGAK0geWJP2Wlkui3YY5oS', '', '2025-10-19 11:43:38', '2025-10-19 11:43:38'),
(163, 291, 227, 960, '8e231de321a6fcd44edb64494c202989', 8311621, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\227_birth_1760874171.png', '1jUoxjGC4m46a1XRUr68IP0-76U-M_vk0', '1mYlS1RMX0OlGAK0geWJP2Wlkui3YY5oS', '', '2025-10-19 11:43:43', '2025-10-19 11:43:43'),
(164, 294, 228, 961, '00008e56d7aec5ad3328bc4c9083624d', 2603169, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Cleford_Gumonan\\228_2_1760874716.png', '1XARWsBdg8rSvfWGWR621zyDKWhCeaZxb', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-19 11:53:29', '2025-10-19 11:53:29'),
(165, 294, 228, 962, 'cd268b3cb4dc485c17a1df331053a27e', 8535014, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Cleford_Gumonan\\228_3_1760874716.png', '1htEEysUbWAWtJWWChxvrIVFFqHJ2qiJ_', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-19 11:53:38', '2025-10-19 11:53:38'),
(166, 295, 228, 962, 'cd268b3cb4dc485c17a1df331053a27e', 8535014, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Cleford_Gumonan\\228_3_1760874716.png', '1PYi8GjcUe1VRY7lxfcyvWLsbd5hpRpqJ', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-19 11:53:38', '2025-10-19 11:53:38'),
(167, 297, 228, 964, '594fcb3b62b3beca2faa1f2b739c648c', 91571, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\228_id_1760874943.jpg', '1EMxigg0lNmNj7dpS6AC-CEtkoJL1vxsf', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-19 11:56:18', '2025-10-19 11:56:18'),
(168, 297, 228, 963, 'a8db100636152d697df43a1426d93a12', 2806687, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\228_gradeslip_1760874928.png', '1iXaC4INjp6Je20D1iGYY5iXXIyaVrFOK', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-19 11:56:26', '2025-10-19 11:56:26'),
(169, 301, 228, 965, '453d4fe09c7cc5179c6b8b4bb602a300', 349982, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\228_honorable_1760875359.png', '1kCy7EVCtRZ7CYYNNfWdhLplPo1ySiQbR', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-19 12:03:10', '2025-10-19 12:03:10'),
(170, 308, 228, 966, '70cd827448269ff3cb97a7af86bcbf7d', 175177, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\228_tor_1760923559.jpg', '1RoewXVWnQ4yijg0hz9RmG0LwBUpddsCq', '1PoQ3M_FWUzUaQtEhbLyofEAteREFqQbU', '', '2025-10-20 02:14:39', '2025-10-20 02:14:39'),
(171, 314, 229, 967, 'c38b025122c4382907e1f7f0d36620bd', 3224694, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Hannah_Jaspe\\229_1_1760949677.png', '1sIQgsCtSe9ookK_6HAelKBCol8kQ5O15', '1F0HvA5MVQ4FRQDrET9f8aHPQUiUDf7GS', '', '2025-10-20 12:09:12', '2025-10-20 12:09:12'),
(172, 314, 229, 968, 'baec5e8ef23211465797ff6323426d44', 4397337, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\Hannah_Jaspe\\229_2_1760949677.png', '1TluY6fYuOzp4IAdpwAUdIoHoVnaqIN7r', '1F0HvA5MVQ4FRQDrET9f8aHPQUiUDf7GS', '', '2025-10-20 12:09:25', '2025-10-20 12:09:25'),
(173, 314, 229, 969, '57906c0842de545088082735d125aac4', 7358328, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\229_birth_1760960259.png', '1r3LPQ3RgxiDF2Rk6pyTyMKNs_mGVLw0F', '1F0HvA5MVQ4FRQDrET9f8aHPQUiUDf7GS', '', '2025-10-20 12:09:35', '2025-10-20 12:09:35'),
(174, 314, 229, 970, 'e276c183ed498a5dc8671fdd59cee5e7', 187324, 'C:\\xampp\\htdocs\\ibacmi\\uploads\\2025\\229_marriage_1760960259.jpg', '11rUK4oFTVInY6MkajDY0p9-l7lIPvI8_', '1F0HvA5MVQ4FRQDrET9f8aHPQUiUDf7GS', '', '2025-10-20 12:09:45', '2025-10-20 12:09:45');

-- --------------------------------------------------------

--
-- Table structure for table `backup_pending_items`
--

CREATE TABLE `backup_pending_items` (
  `id` int(11) NOT NULL,
  `item_type` enum('student','document') NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','backed_up') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_files`
--

CREATE TABLE `document_files` (
  `id` int(11) NOT NULL,
  `file_hash` varchar(32) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `doc_name` varchar(100) NOT NULL,
  `doc_code` varchar(20) NOT NULL,
  `required_for` enum('All','Regular','Transferee') NOT NULL DEFAULT 'All',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `doc_name`, `doc_code`, `required_for`, `description`, `is_active`) VALUES
(1, 'Card 138', 'card138', 'Regular', 'Form 138 / Report Card', 1),
(2, 'Certificate of Good Moral', 'moral', 'All', 'Certificate of Good Moral Character', 1),
(3, 'PSA Birth Certificate', 'birth', 'All', 'Philippine Statistics Authority Birth Certificate', 1),
(4, 'PSA Marriage Certificate', 'marriage', 'All', 'Philippine Statistics Authority Marriage Certificate (if applicable)', 1),
(5, '2x2 Picture', 'id', 'All', '2x2 ID Picture with white background', 1),
(6, 'Transcript of Record', 'tor', 'Transferee', 'Official Transcript of Records', 1),
(7, 'Honorable Dismissal', 'honorable', 'Transferee', 'Honorable Dismissal from previous school', 1),
(8, 'Grade Slip', 'gradeslip', 'Transferee', 'Grade Slip from previous school', 1);

-- --------------------------------------------------------

--
-- Table structure for table `enhanced_sync_logs`
--

CREATE TABLE `enhanced_sync_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `google_file_id` varchar(255) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `upload_filename` varchar(255) DEFAULT NULL,
  `folder_path` text DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `local_backup_manifest`
--

CREATE TABLE `local_backup_manifest` (
  `id` int(11) NOT NULL,
  `backup_log_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `local_file_path` varchar(500) NOT NULL,
  `file_hash` varchar(64) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `backup_type` enum('new','modified') DEFAULT 'new',
  `backed_up_at` datetime NOT NULL,
  `last_synced_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `local_backup_manifest`
--

INSERT INTO `local_backup_manifest` (`id`, `backup_log_id`, `student_id`, `document_id`, `local_file_path`, `file_hash`, `file_size`, `backup_type`, `backed_up_at`, `last_synced_at`) VALUES
(119, 309, 228, 961, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Gumonan, Cleford 522612\\Certificate of Good Moral.png', '00008e56d7aec5ad3328bc4c9083624d', 2603169, 'new', '2025-10-20 16:39:58', NULL),
(120, 309, 228, 962, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Gumonan, Cleford 522612\\PSA Birth Certificate.png', 'cd268b3cb4dc485c17a1df331053a27e', 8535014, 'new', '2025-10-20 16:39:58', NULL),
(121, 309, 228, 963, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Gumonan, Cleford 522612\\Grade Slip.png', 'a8db100636152d697df43a1426d93a12', 2806687, 'new', '2025-10-20 16:39:58', NULL),
(122, 309, 228, 964, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Gumonan, Cleford 522612\\2x2 Picture.jpg', '594fcb3b62b3beca2faa1f2b739c648c', 91571, 'new', '2025-10-20 16:39:58', NULL),
(123, 309, 228, 965, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Gumonan, Cleford 522612\\Honorable Dismissal.png', '453d4fe09c7cc5179c6b8b4bb602a300', 349982, 'new', '2025-10-20 16:39:58', NULL),
(124, 309, 228, 966, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Gumonan, Cleford 522612\\Transcript of Record.jpg', '70cd827448269ff3cb97a7af86bcbf7d', 175177, 'new', '2025-10-20 16:39:58', NULL),
(125, 309, 227, 957, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Harina, Shanelle Mae 5225613\\Card 138.jpg', 'e14cb1c3de0c03488cc6db58aa8db9db', 790877, 'new', '2025-10-20 16:39:58', NULL),
(126, 309, 227, 958, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Harina, Shanelle Mae 5225613\\Certificate of Good Moral.png', 'c7d06b177463d5d10a3aad27865de0a5', 2680279, 'new', '2025-10-20 16:39:58', NULL),
(127, 309, 227, 959, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Harina, Shanelle Mae 5225613\\2x2 Picture.jpg', 'b6ef6c59c2128d5a54020515cd4855ba', 34166, 'new', '2025-10-20 16:39:58', NULL),
(128, 309, 227, 960, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Harina, Shanelle Mae 5225613\\PSA Birth Certificate.png', '8e231de321a6fcd44edb64494c202989', 8311621, 'new', '2025-10-20 16:39:58', NULL),
(129, 310, 229, 967, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Jaspe, Hannah 5225614\\Card 138.png', 'c38b025122c4382907e1f7f0d36620bd', 3224694, 'new', '2025-10-20 16:41:53', NULL),
(130, 310, 229, 968, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Jaspe, Hannah 5225614\\Certificate of Good Moral.png', 'baec5e8ef23211465797ff6323426d44', 4397337, 'new', '2025-10-20 16:41:54', NULL),
(131, 313, 229, 969, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Jaspe, Hannah 5225614\\PSA Birth Certificate.png', '57906c0842de545088082735d125aac4', 7358328, 'new', '2025-10-20 19:37:55', NULL),
(132, 313, 229, 970, 'C:\\Users\\Angela\\Downloads\\ORDDB Backup\\IBACMI_Backup_2025_2026\\Jaspe, Hannah 5225614\\PSA Marriage Certificate.jpg', 'e276c183ed498a5dc8671fdd59cee5e7', 187324, 'new', '2025-10-20 19:37:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_type` enum('admin','staff') NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_type`, `user_id`, `email`, `token`, `created_at`, `expires_at`, `used`) VALUES
(1, 'staff', 16, 'angelacaldoza07@gmail.com', '32431a3848b02ef5e26d5dfb99622bb70ccac19c95c70a5d9a1bfe0a1019ddd9', '2025-10-19 07:14:28', '2025-10-19 08:14:28', 0),
(2, 'staff', 16, 'angelacaldoza07@gmail.com', '93c89c23ddc83b798bd9a3831b0c7c3a31cbc063779eeece610dc470f4daaf05', '2025-10-19 07:15:15', '2025-10-19 08:15:15', 0),
(3, 'staff', 16, 'angelacaldoza07@gmail.com', '732e194fb2e9da9c902f85d1da0406f6e5c861a3d340b11d7debfcb9e35edd0d', '2025-10-19 07:18:11', '2025-10-19 08:18:11', 0),
(4, 'staff', 16, 'angelacaldoza07@gmail.com', '5dcaaa5efe4c045b77fdb1c586bf7a3f3133bad853b12d35d6467dde38059812', '2025-10-19 07:19:51', '2025-10-19 08:19:51', 0),
(5, 'staff', 16, 'angelacaldoza07@gmail.com', '309e9e02730cbe4cd26c85ef3102ff9a4a2862438928b92dd231233cf2cd21c2', '2025-10-19 07:33:20', '2025-10-19 08:33:20', 1),
(6, 'admin', 9, 'angelcaldoza07@gmail.com', 'd3cd58252bd5d951df97060a1815c807b74fb2d1461d2ce3909693dd9c545792', '2025-10-19 07:49:03', '2025-10-19 08:49:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `end_date` date DEFAULT NULL,
  `auto_advance_enabled` tinyint(1) DEFAULT 1,
  `last_advancement_check` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `school_year`, `is_active`, `end_date`, `auto_advance_enabled`, `last_advancement_check`, `created_at`, `updated_at`) VALUES
(7, '2025-2026', 1, '2025-10-19', 1, NULL, '2025-10-20 02:58:29', '2025-10-20 12:39:27');

-- --------------------------------------------------------

--
-- Table structure for table `staff_activity_log`
--

CREATE TABLE `staff_activity_log` (
  `id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_activity_log`
--

INSERT INTO `staff_activity_log` (`id`, `staff_user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(17, 16, 'REGISTER', 'New staff registration - pending approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 06:34:35');

-- --------------------------------------------------------

--
-- Table structure for table `staff_password_reset_tokens`
--

CREATE TABLE `staff_password_reset_tokens` (
  `id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `id_document` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`id`, `staff_user_id`, `department`, `position`, `phone`, `address`, `profile_picture`, `id_document`, `created_at`, `updated_at`, `first_name`, `middle_name`, `last_name`, `birthday`, `email`) VALUES
(16, 16, '', 'Staff', '', '', '../uploads/staff_profiles/staff_16_1760922032.png', NULL, '2025-10-19 06:34:35', '2025-10-20 01:00:32', 'jonamie', '', 'Member', '0000-00-00', 'angelacaldoza07@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `staff_sessions`
--

CREATE TABLE `staff_sessions` (
  `id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_users`
--

CREATE TABLE `staff_users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('staff','admin') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `id_document` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_users`
--

INSERT INTO `staff_users` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `username`, `password_hash`, `role`, `is_active`, `email_verified`, `created_at`, `updated_at`, `last_login`, `status`, `id_document`) VALUES
(16, 'jonamie', 'Pagaling', 'Member', 'angelacaldoza07@gmail.com', 'jonamie', '$2y$10$oibYmXVLTxyznUgl4mQEkuAidc0WpOrHpL7jhCLXQDfMhayZNGY9e', 'staff', 1, 1, '2025-10-19 06:34:35', '2025-10-20 01:00:32', NULL, 'approved', 'StaffAccount/uploads/ids/id_68f486579773b.png');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `course` varchar(10) NOT NULL,
  `year_level` int(11) NOT NULL,
  `student_type` enum('Regular','Transferee') NOT NULL DEFAULT 'Regular',
  `marriage_cert_required` tinyint(1) DEFAULT 0,
  `status` enum('complete','incomplete','archived') NOT NULL DEFAULT 'incomplete',
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `graduation_date` date DEFAULT NULL,
  `is_graduated` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `course`, `year_level`, `student_type`, `marriage_cert_required`, `status`, `date_added`, `graduation_date`, `is_graduated`, `is_archived`) VALUES
(227, '5225613', 'Shanelle Mae', 'Genita', 'Harina', 'BSIT', 1, 'Regular', 0, 'incomplete', '2025-10-19 11:04:23', NULL, 0, 0),
(228, '522612', 'Cleford', '', 'Gumonan', 'BSCRIM', 1, 'Transferee', 0, 'incomplete', '2025-10-19 11:51:56', NULL, 0, 0),
(229, '5225614', 'Hannah', '', 'Jaspe', 'BEED', 4, 'Regular', 1, 'incomplete', '2025-10-20 08:41:17', NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `student_documents`
--

CREATE TABLE `student_documents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `google_drive_file_id` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `is_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `submission_date` timestamp NULL DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `validation_confidence` decimal(5,2) DEFAULT NULL,
  `extracted_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_data`)),
  `validation_status` enum('valid','review','invalid') DEFAULT 'valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_documents`
--

INSERT INTO `student_documents` (`id`, `student_id`, `document_type_id`, `file_name`, `file_path`, `google_drive_file_id`, `original_filename`, `file_size`, `file_type`, `is_submitted`, `submission_date`, `last_updated`, `notes`, `validation_confidence`, `extracted_data`, `validation_status`) VALUES
(957, 227, 1, '227_1_1760871863.jpg', 'uploads/2025/Shanelle Mae_Harina/227_1_1760871863.jpg', NULL, 'FORM 137.jpg', 790877, 'image/jpeg', 1, '2025-10-19 11:04:23', '2025-10-19 11:04:23', NULL, 80.00, NULL, 'valid'),
(958, 227, 2, '227_2_1760871863.png', 'uploads/2025/Shanelle Mae_Harina/227_2_1760871863.png', NULL, 'GMC.png', 2680279, 'image/png', 1, '2025-10-19 11:04:23', '2025-10-19 11:04:23', NULL, 60.00, NULL, 'valid'),
(959, 227, 5, '227_5_1760871863.jpg', 'uploads/2025/Shanelle Mae_Harina/227_5_1760871863.jpg', NULL, 'dca27e7ecde7f00a213f19819789467a - Copy.jpg', 34166, 'image/jpeg', 1, '2025-10-19 11:04:23', '2025-10-19 11:04:23', NULL, 100.00, NULL, 'valid'),
(960, 227, 3, 'uploads/2025/227_birth_1760874171.png', 'uploads/2025/227_birth_1760874171.png', NULL, 'PSA.png', 8311621, 'image/png', 1, '2025-10-19 11:42:51', '2025-10-19 11:42:51', NULL, NULL, NULL, 'valid'),
(961, 228, 2, '228_2_1760874716.png', 'uploads/2025/Cleford_Gumonan/228_2_1760874716.png', NULL, 'GMC.png', 2603169, 'image/png', 1, '2025-10-19 11:51:56', '2025-10-19 11:51:56', NULL, 60.00, NULL, 'valid'),
(962, 228, 3, '228_3_1760874716.png', 'uploads/2025/Cleford_Gumonan/228_3_1760874716.png', NULL, 'PSA.png', 8535014, 'image/png', 1, '2025-10-19 11:51:56', '2025-10-19 11:51:56', NULL, 80.00, NULL, 'valid'),
(963, 228, 8, 'uploads/2025/228_gradeslip_1760874928.png', 'uploads/2025/228_gradeslip_1760874928.png', NULL, 'FORM 137.png', 2806687, 'image/png', 1, '2025-10-19 11:55:28', '2025-10-19 11:55:28', NULL, NULL, NULL, 'valid'),
(964, 228, 5, 'uploads/2025/228_id_1760874943.jpg', 'uploads/2025/228_id_1760874943.jpg', NULL, '8c0ebadc6381b512114029f5f73634ba.jpg', 91571, 'image/jpeg', 1, '2025-10-19 11:55:43', '2025-10-19 11:55:43', NULL, NULL, NULL, 'valid'),
(965, 228, 7, 'uploads/2025/228_honorable_1760875359.png', 'uploads/2025/228_honorable_1760875359.png', NULL, 'thumb_1200_1553.png', 349982, 'image/png', 1, '2025-10-19 12:02:39', '2025-10-19 12:02:39', NULL, NULL, NULL, 'valid'),
(966, 228, 6, 'uploads/2025/228_tor_1760923559.jpg', 'uploads/2025/228_tor_1760923559.jpg', NULL, 'download (28).jpg', 175177, 'image/jpeg', 1, '2025-10-20 01:25:59', '2025-10-20 01:25:59', NULL, NULL, NULL, 'valid'),
(967, 229, 1, '229_1_1760949677.png', 'uploads/2025/Hannah_Jaspe/229_1_1760949677.png', NULL, 'FORM 137.png', 3224694, 'image/png', 1, '2025-10-20 08:41:17', '2025-10-20 08:41:17', NULL, 80.00, NULL, 'valid'),
(968, 229, 2, '229_2_1760949677.png', 'uploads/2025/Hannah_Jaspe/229_2_1760949677.png', NULL, 'GMC.png', 4397337, 'image/png', 1, '2025-10-20 08:41:17', '2025-10-20 08:41:17', NULL, 60.00, NULL, 'valid'),
(969, 229, 3, 'uploads/2025/229_birth_1760960259.png', 'uploads/2025/229_birth_1760960259.png', NULL, 'tor 1.png', 7358328, 'image/png', 1, '2025-10-20 11:37:39', '2025-10-20 11:37:39', NULL, NULL, NULL, 'valid'),
(970, 229, 4, 'uploads/2025/229_marriage_1760960259.jpg', 'uploads/2025/229_marriage_1760960259.jpg', NULL, 'My Marriage Certificate.jpg', 187324, 'image/jpeg', 1, '2025-10-20 11:37:39', '2025-10-20 11:37:39', NULL, NULL, NULL, 'valid');

-- --------------------------------------------------------

--
-- Table structure for table `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `google_drive_file_id` varchar(255) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
(39, 'last_sync_time', '', '2025-07-29 09:05:00', '2025-07-29 09:05:00'),
(40, 'sync_interval', '300', '2025-07-29 09:05:00', '2025-07-29 09:05:00'),
(62, 'current_school_year', '2025-2026', '2025-07-30 15:11:05', '2025-10-20 02:58:35'),
(116, 'last_cleanup_date', '2025-10-12', '2025-10-11 11:15:16', '2025-10-12 07:43:53'),
(251, 'backup_directory', 'C:\\Users\\Angela\\Downloads\\ORDDB Backup', '2025-10-17 19:44:49', '2025-10-20 07:04:16'),
(291, 'auto_sync_status', 'disabled', '2025-10-19 08:39:05', '2025-10-20 07:54:59'),
(404, 'archival_timing', 'immediate', '2025-10-20 03:19:02', '2025-10-20 12:49:09'),
(405, 'auto_archival_enabled', '1', '2025-10-20 03:19:02', '2025-10-20 12:49:09');

-- --------------------------------------------------------

--
-- Table structure for table `transferees`
--

CREATE TABLE `transferees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('staff','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verification_code` varchar(6) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `code_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `username`, `password`, `role`, `created_at`, `updated_at`, `verification_code`, `is_verified`, `code_expires_at`) VALUES
(4, 'Admin', 'Admin', 'Admin', 'admin@gmail.com', 'admin', '$2y$10$OOpMB6K5jKQbxAKMP6ajReKx9AKbOHy5z1hAxHSrzYMnp6fHsDOKG', 'staff', '2025-05-13 14:47:38', '2025-05-13 14:47:38', NULL, 0, NULL),
(9, 'Angela', 'Pagaling', 'Caldoza', 'angelcaldoza07@gmail.com', 'gela', '$2y$10$AVS0e8N2QW6EpJEmoiVusum/Q9qHTmJgCDmloDOYK6FBiKz9WBpf2', 'admin', '2025-10-17 16:00:54', '2025-10-19 07:49:46', NULL, 0, NULL),
(10, 'Kiana', 'Go', 'Perez', 'angelacaldoza07@gmail.com', 'kiana', '$2y$10$J5z4ivljum4UdVwpClZ/duJ9DWuV6MIl2JdYLD56CZ1jKOu2hcNoS', 'admin', '2025-10-18 18:01:51', '2025-10-18 18:01:51', NULL, 1, NULL),
(11, 'Angela', 'Pagaling', 'Caldoza', 'ibacmiorddb@gmail.com', 'angela', '$2y$10$mpw1McaHww5U8pqu91e4iu0JAcPj5KY4ZHq0B3MV1P.Jp7IUnUjmy', 'admin', '2025-10-19 03:08:09', '2025-10-19 03:08:09', NULL, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archival_settings`
--
ALTER TABLE `archival_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `archive_logs`
--
ALTER TABLE `archive_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archive_logs_year` (`school_year`),
  ADD KEY `idx_archive_logs_status` (`status`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_archival_type` (`archival_type`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `backup_manifest`
--
ALTER TABLE `backup_manifest`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backup_student` (`backup_log_id`,`student_id`),
  ADD KEY `idx_file_hash` (`file_hash`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_gdrive_file` (`google_drive_file_id`),
  ADD KEY `idx_google_file_id` (`google_drive_file_id`);

--
-- Indexes for table `backup_pending_items`
--
ALTER TABLE `backup_pending_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type_status` (`item_type`,`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `document_files`
--
ALTER TABLE `document_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_hash` (`file_hash`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `doc_code` (`doc_code`);

--
-- Indexes for table `enhanced_sync_logs`
--
ALTER TABLE `enhanced_sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_status` (`student_id`,`status`),
  ADD KEY `idx_archived` (`archived`,`synced_at`),
  ADD KEY `idx_school_year` (`school_year`),
  ADD KEY `idx_folder_path` (`folder_path`(100));

--
-- Indexes for table `local_backup_manifest`
--
ALTER TABLE `local_backup_manifest`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_document` (`student_id`,`document_id`),
  ADD KEY `backup_log_id` (`backup_log_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_year` (`school_year`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_end_date` (`end_date`);

--
-- Indexes for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_activity_user_id` (`staff_user_id`),
  ADD KEY `idx_staff_activity_date` (`created_at`);

--
-- Indexes for table `staff_password_reset_tokens`
--
ALTER TABLE `staff_password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_staff_reset_token` (`token`),
  ADD KEY `idx_staff_reset_user_id` (`staff_user_id`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_user_id` (`staff_user_id`),
  ADD KEY `idx_staff_profile_user_id` (`staff_user_id`);

--
-- Indexes for table `staff_sessions`
--
ALTER TABLE `staff_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_staff_session_token` (`session_token`),
  ADD KEY `idx_staff_session_user_id` (`staff_user_id`);

--
-- Indexes for table `staff_users`
--
ALTER TABLE `staff_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_staff_email` (`email`),
  ADD KEY `idx_staff_username` (`username`),
  ADD KEY `idx_staff_role` (`role`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_graduated` (`is_graduated`,`graduation_date`),
  ADD KEY `idx_archived` (`is_archived`);

--
-- Indexes for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `document_type_id` (`document_type_id`);

--
-- Indexes for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_logs_student_status` (`student_id`,`status`),
  ADD KEY `idx_sync_logs_archived` (`archived`,`synced_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `transferees`
--
ALTER TABLE `transferees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `archival_settings`
--
ALTER TABLE `archival_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `archive_logs`
--
ALTER TABLE `archive_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=315;

--
-- AUTO_INCREMENT for table `backup_manifest`
--
ALTER TABLE `backup_manifest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `backup_pending_items`
--
ALTER TABLE `backup_pending_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_files`
--
ALTER TABLE `document_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `enhanced_sync_logs`
--
ALTER TABLE `enhanced_sync_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `local_backup_manifest`
--
ALTER TABLE `local_backup_manifest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `staff_password_reset_tokens`
--
ALTER TABLE `staff_password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `staff_sessions`
--
ALTER TABLE `staff_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_users`
--
ALTER TABLE `staff_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `student_documents`
--
ALTER TABLE `student_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=971;

--
-- AUTO_INCREMENT for table `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1315;

--
-- AUTO_INCREMENT for table `transferees`
--
ALTER TABLE `transferees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backup_manifest`
--
ALTER TABLE `backup_manifest`
  ADD CONSTRAINT `backup_manifest_ibfk_1` FOREIGN KEY (`backup_log_id`) REFERENCES `backup_logs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `backup_manifest_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD CONSTRAINT `staff_activity_log_ibfk_1` FOREIGN KEY (`staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_password_reset_tokens`
--
ALTER TABLE `staff_password_reset_tokens`
  ADD CONSTRAINT `staff_password_reset_tokens_ibfk_1` FOREIGN KEY (`staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `staff_profiles_ibfk_1` FOREIGN KEY (`staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_sessions`
--
ALTER TABLE `staff_sessions`
  ADD CONSTRAINT `staff_sessions_ibfk_1` FOREIGN KEY (`staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD CONSTRAINT `student_documents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_documents_ibfk_2` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`);

--
-- Constraints for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD CONSTRAINT `sync_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
