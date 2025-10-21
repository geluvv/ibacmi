-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2025 at 05:58 PM
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
(4, 'immediate', 'google_drive', 1, NULL, '2025-10-20 16:49:26');

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
(18, 17, 'REGISTER', 'New staff registration - pending approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 14:33:14');

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
(17, 17, '', 'Staff', '', '', '../uploads/staff_profiles/staff_17_1761057510.jpg', NULL, '2025-10-21 14:33:14', '2025-10-21 14:39:06', 'Jessica', 'Montefrio', 'Bayang', '2004-06-21', 'angelacaldoza07@gmail.com');

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
(17, 'Jessica', 'Montefrio', 'Bayang', 'angelacaldoza07@gmail.com', 'jessica', '$2y$10$uAkBXZSbszg3JfigX2eesubcnSjme3g4r.Vfw9curz90hhgB5AMc2', 'staff', 1, 1, '2025-10-21 14:33:14', '2025-10-21 14:39:06', NULL, 'approved', 'uploads/ids/id_68f79994e12b1.jpg');

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
(62, 'current_school_year', '2025-2026', '2025-07-30 15:11:05', '2025-10-20 16:40:42'),
(116, 'last_cleanup_date', '2025-10-12', '2025-10-11 11:15:16', '2025-10-12 07:43:53'),
(251, 'backup_directory', 'C:\\xampp\\htdocs\\ibacmi\\backups', '2025-10-17 19:44:49', '2025-10-20 07:04:16'),
(291, 'auto_sync_status', 'disabled', '2025-10-19 08:39:05', '2025-10-21 14:25:20'),
(404, 'archival_timing', 'immediate', '2025-10-20 03:19:02', '2025-10-20 14:40:02'),
(405, 'auto_archival_enabled', '1', '2025-10-20 03:19:02', '2025-10-20 14:40:02');

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
(12, 'Angela', 'Pagaling', 'Caldoza', 'angelcaldoza07@gmail.com', 'gela', '$2y$10$Y1qWY6/YRlDb08jaFMQb4ul.6ZNLW6Xkwb5YhKn8ueX7ESLiP5NlS', 'admin', '2025-10-21 14:30:57', '2025-10-21 14:30:57', NULL, 1, NULL);


