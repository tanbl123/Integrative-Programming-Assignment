-- =====================================================================
-- EcoCampus - complete database, exported from a working installation
--
-- Exported by : Tan Boon Leong (2402865)
-- Module      : Whole system
-- Exported on : 12 September 2026
--
-- WHAT THIS IS
-- A phpMyAdmin dump of the whole database: every table, and the rows in
-- it at the time of export. It is a REPLACEMENT for 01_schema.sql and
-- 02_seed.sql, not something to run after them.
--
-- HOW TO IMPORT
--   phpMyAdmin -> Import -> choose this file -> Go
--
-- The database must be EMPTY. The dump creates the tables and has no
-- DROP TABLE statements, so importing it over an existing ecocampus
-- stops at the first table with "Table already exists". To start again,
-- drop the ecocampus database first and import this on its own.
--
-- DO NOT ALSO RUN 01 TO 19. Everything those files build is already
-- here: change_type including Withdrawn (11, 18), complaint_revisions
-- (12), superseded_at (13), complaint_types (15), the dropped
-- description column (16), marks_bin_full (17) and the location
-- coordinates (19). Nothing needs running after this file.
--
-- The file keeps the number 14 it was first given, because that is the
-- name the team knows it by. It is not a step in the sequence.
--
-- The numbered files are still the record of how the schema was designed
-- and who owns each table; this file is the quickest way to a working
-- copy for a demonstration.
--
-- The CREATE DATABASE and USE lines below are not part of the phpMyAdmin
-- export. They were added so the file can be imported without a database
-- having been selected first, which is the commonest way this import
-- fails.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `ecocampus`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `ecocampus`;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 11, 2026 at 09:46 PM
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
-- Database: `ecocampus`
--

-- --------------------------------------------------------

--
-- Table structure for table `bins`
--

CREATE TABLE `bins` (
  `bin_id` int(11) NOT NULL,
  `bin_code` varchar(50) NOT NULL,
  `location_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `fill_status` enum('Empty','Half','Full','Under Maintenance') NOT NULL DEFAULT 'Empty',
  `capacity_litre` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bins`
--

INSERT INTO `bins` (`bin_id`, `bin_code`, `location_id`, `category_id`, `fill_status`, `capacity_litre`, `is_active`, `last_updated`) VALUES
(1, 'BIN-A-001', 1, 1, 'Full', 120, 1, '2026-09-12 03:10:45'),
(2, 'BIN-A-002', 1, 2, 'Empty', 120, 1, '2026-09-10 20:12:31'),
(3, 'BIN-A-003', 2, 1, 'Full', 240, 1, '2026-09-10 20:12:31'),
(4, 'BIN-A-004', 2, 3, 'Full', 240, 1, '2026-09-10 20:12:31'),
(5, 'BIN-B-001', 3, 2, 'Full', 120, 1, '2026-09-12 02:23:12'),
(6, 'BIN-B-002', 4, 1, 'Full', 120, 1, '2026-09-11 23:29:40'),
(7, 'BIN-B-003', 4, 4, 'Empty', 60, 1, '2026-09-12 01:31:56'),
(8, 'BIN-C-001', 5, 1, 'Full', 240, 1, '2026-09-10 20:12:31'),
(9, 'BIN-P-001', 6, 1, 'Under Maintenance', 240, 1, '2026-09-10 20:12:31'),
(10, 'BIN-D-001', 7, 2, 'Half', 120, 1, '2026-09-10 20:12:31');

-- --------------------------------------------------------

--
-- Table structure for table `bin_status_updates`
--

CREATE TABLE `bin_status_updates` (
  `update_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `cleaner_id` int(11) NOT NULL,
  `old_status` enum('Empty','Half','Full','Under Maintenance') NOT NULL,
  `new_status` enum('Empty','Half','Full','Under Maintenance') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bin_status_updates`
--

INSERT INTO `bin_status_updates` (`update_id`, `bin_id`, `cleaner_id`, `old_status`, `new_status`, `remarks`, `updated_at`) VALUES
(1, 6, 1, 'Full', 'Empty', 'Complaint CMP-2026-0009 resolved; no reports left open.', '2026-09-11 23:26:27'),
(2, 6, 3, 'Empty', 'Full', 'Reported full by complaint CMP-2026-0010.', '2026-09-11 23:29:40'),
(3, 7, 3, 'Empty', 'Full', 'Reported full by complaint CMP-2026-0013.', '2026-09-12 01:27:47'),
(4, 7, 1, 'Full', 'Empty', 'Complaint CMP-2026-0013 resolved; no reports left open.', '2026-09-12 01:31:56'),
(5, 5, 3, 'Empty', 'Full', 'Reported full by complaint CMP-2026-0016.', '2026-09-12 02:23:12'),
(6, 1, 3, 'Half', 'Full', 'Reported full by complaint CMP-2026-0017.', '2026-09-12 03:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `collection_assignments`
--

CREATE TABLE `collection_assignments` (
  `assignment_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `cleaner_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `source_complaint_id` int(11) DEFAULT NULL,
  `priority` enum('Routine','Normal','Urgent') NOT NULL DEFAULT 'Normal',
  `assignment_status` enum('Assigned','Completed','Skipped') NOT NULL DEFAULT 'Assigned',
  `reason` varchar(255) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` varchar(1000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collection_assignments`
--

INSERT INTO `collection_assignments` (`assignment_id`, `schedule_id`, `cleaner_id`, `bin_id`, `source_complaint_id`, `priority`, `assignment_status`, `reason`, `completed_at`, `completion_notes`) VALUES
(1, 1, 6, 3, 1, 'Urgent', 'Skipped', 'Unresolved complaint #1: Overflow', NULL, 'Schedule cancelled by administrator.'),
(2, 1, 6, 4, 2, 'Urgent', 'Skipped', 'Unresolved complaint #2: Full Bin', NULL, 'Schedule cancelled by administrator.'),
(3, 1, 6, 8, 3, 'Urgent', 'Skipped', 'Unresolved complaint #3: Damaged Bin', NULL, 'Schedule cancelled by administrator.'),
(4, 1, 6, 1, 5, 'Urgent', 'Skipped', 'Unresolved complaint #5: Wrong Waste Disposal', NULL, 'Schedule cancelled by administrator.'),
(5, 1, 6, 6, 9, 'Urgent', 'Skipped', 'Unresolved complaint #9: Overflow', NULL, 'Schedule cancelled by administrator.'),
(6, 2, 6, 6, 10, 'Urgent', 'Assigned', 'Complaint CMP-2026-0010: Overflow', NULL, NULL),
(7, 3, 7, 9, 11, 'Urgent', 'Assigned', 'Complaint CMP-2026-0011: Damaged Bin', NULL, NULL),
(8, 4, 6, 7, 13, 'Urgent', 'Assigned', 'Complaint CMP-2026-0013: Overflow', NULL, NULL),
(9, 5, 7, 3, 1, 'Urgent', 'Assigned', 'Complaint CMP-2026-0001: Overflow', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `collection_records`
--

CREATE TABLE `collection_records` (
  `record_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `cleaner_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `estimated_weight_kg` decimal(8,2) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `collected_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collection_schedules`
--

CREATE TABLE `collection_schedules` (
  `schedule_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `strategy` enum('Full Bins','Complaint Priority','Routine') NOT NULL,
  `schedule_status` enum('Planned','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collection_schedules`
--

INSERT INTO `collection_schedules` (`schedule_id`, `admin_id`, `schedule_date`, `time_slot`, `strategy`, `schedule_status`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, '2026-09-11', '09:00–12:00', 'Complaint Priority', 'Cancelled', NULL, '2026-09-11 22:53:26', '2026-09-11 23:45:36', NULL),
(2, 1, '2026-09-11', '09:00-12:00', 'Complaint Priority', 'Planned', 'please do cleaning', '2026-09-11 23:54:56', '2026-09-11 23:54:56', NULL),
(3, 1, '2026-09-12', '09:00-09:30', 'Complaint Priority', 'Planned', 'please replace a new bin', '2026-09-12 00:05:00', '2026-09-12 00:05:00', NULL),
(4, 1, '2026-09-12', '09:00-12:00', 'Complaint Priority', 'Planned', 'please clean the bin', '2026-09-12 01:29:24', '2026-09-12 01:29:24', NULL),
(5, 1, '2026-09-12', '09:00-12:00', 'Complaint Priority', 'Planned', 'please clean this area', '2026-09-12 01:58:46', '2026-09-12 01:58:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `complaint_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `complaint_status` enum('New','Assigned','Resolved','Rejected') NOT NULL DEFAULT 'New',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `reporter_id`, `bin_id`, `complaint_type`, `description`, `complaint_status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 3, 3, 'Overflow', 'The bin beside the food counter is overflowing and rubbish is on the floor.', 'Assigned', '2026-09-10 20:12:31', '2026-09-11 22:53:26', NULL),
(2, 4, 4, 'Full Bin', 'Organic waste bin in the cafeteria is completely full since this morning.', 'Assigned', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(3, 5, 8, 'Damaged Bin', 'The lid of the bin at the rear exit of LH1 is broken and will not close.', 'Assigned', '2026-09-10 20:12:31', '2026-09-11 22:53:26', NULL),
(4, 3, 9, 'Dirty Area', 'Area around the car park bin is dirty and smells strongly.', 'Resolved', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(5, 4, 1, 'Wrong Waste Disposal', 'Someone put food waste into the general waste bin in the main lobby.', 'Assigned', '2026-09-10 20:12:31', '2026-09-11 22:53:26', NULL),
(6, 4, 3, 'Overflow', 'Rubbish is spilling out of the bin next to the drinks stall. It has been like this since breakfast.', 'Assigned', '2026-09-11 05:15:05', '2026-09-12 01:58:46', NULL),
(7, 5, 3, 'Overflow', 'Cafeteria bin is overflowing again. Flies around it and the floor is sticky.', 'Assigned', '2026-09-11 05:15:05', '2026-09-11 05:15:05', NULL),
(8, 3, 8, 'Damaged Bin', 'The bin outside LH1 is cracked down one side, and it also has not been emptied for days.', 'Assigned', '2026-09-11 05:15:05', '2026-09-11 23:12:25', NULL),
(9, 3, 6, 'Overflow', 'Please clean the bin ASAP!!', 'Resolved', '2026-09-11 15:39:30', '2026-09-11 23:26:26', NULL),
(10, 3, 6, 'Overflow', 'please clean the rubbish bin.', 'Assigned', '2026-09-11 23:29:40', '2026-09-11 23:54:56', NULL),
(11, 3, 9, 'Damaged Bin', 'please replace the bin', 'Assigned', '2026-09-12 00:04:07', '2026-09-12 00:05:00', NULL),
(12, 4, 8, 'Damaged Bin', 'please replace a new bin, the current bin is broken', 'New', '2026-09-12 00:08:39', '2026-09-12 00:08:39', '2026-09-12 01:16:26'),
(13, 3, 7, 'Overflow', 'Please ask a cleaner to clear the bin', 'Resolved', '2026-09-12 01:27:47', '2026-09-12 01:31:56', NULL),
(14, 3, 7, 'Dirty Area', 'the surrounding of the bin is too dirty', 'Resolved', '2026-09-12 01:40:23', '2026-09-12 02:09:34', '2026-09-12 02:09:42'),
(15, 3, 2, 'Damaged Bin', 'the bin already damage please replace it', 'Assigned', '2026-09-12 01:55:11', '2026-09-12 01:58:04', NULL),
(16, 3, 5, 'Overflow', 'please clean the bin', 'New', '2026-09-12 02:23:12', '2026-09-12 02:23:12', NULL),
(17, 3, 1, 'Overflow', 'Please clean this bin', 'New', '2026-09-12 03:10:45', '2026-09-12 03:10:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaint_attachments`
--

CREATE TABLE `complaint_attachments` (
  `attachment_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `superseded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_attachments`
--

INSERT INTO `complaint_attachments` (`attachment_id`, `complaint_id`, `original_name`, `stored_name`, `mime_type`, `file_size`, `uploaded_at`, `superseded_at`) VALUES
(1, 9, 'clock.png', '59b87005b1dae75bfc14db5c0ee7775cd40792b7.png', 'image/png', 517143, '2026-09-11 15:39:30', '2026-09-11 11:55:21'),
(2, 9, 'download (1).jpg', 'cb75021cf24a189b73c1c539497ef5bf66febad1.jpg', 'image/jpeg', 7079, '2026-09-11 17:55:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaint_notifications`
--

CREATE TABLE `complaint_notifications` (
  `notification_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `recipient_role` enum('Reporter','Cleaner','Administrator') NOT NULL,
  `title` varchar(150) NOT NULL,
  `body` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_notifications`
--

INSERT INTO `complaint_notifications` (`notification_id`, `complaint_id`, `recipient_role`, `title`, `body`, `is_read`, `created_at`) VALUES
(1, 9, 'Administrator', 'New complaint CMP-2026-0009 - Overflow', 'A new Overflow issue was reported for BIN-B-002.', 0, '2026-09-11 09:39:30'),
(2, 9, 'Reporter', 'Complaint CMP-2026-0009 is being dealt with', 'Your report about BIN-B-002 has been accepted and work is being arranged. we will do it ASAP', 1, '2026-09-11 22:52:38'),
(3, 1, 'Reporter', 'Complaint CMP-2026-0001 is being dealt with', 'Your report about BIN-A-003 has been accepted and work is being arranged. Collection scheduled (schedule #1).', 0, '2026-09-11 22:53:26'),
(4, 3, 'Reporter', 'Complaint CMP-2026-0003 is being dealt with', 'Your report about BIN-C-001 has been accepted and work is being arranged. Collection scheduled (schedule #1).', 0, '2026-09-11 22:53:26'),
(5, 5, 'Reporter', 'Complaint CMP-2026-0005 is being dealt with', 'Your report about BIN-A-001 has been accepted and work is being arranged. Collection scheduled (schedule #1).', 0, '2026-09-11 22:53:26'),
(6, 8, 'Reporter', 'Complaint CMP-2026-0008 is being dealt with', 'Your report about BIN-C-001 has been accepted and work is being arranged.', 0, '2026-09-11 23:12:25'),
(7, 9, 'Reporter', 'Complaint CMP-2026-0009 resolved', 'Your report about BIN-B-002 was marked Resolved.', 0, '2026-09-11 23:26:27'),
(8, 10, 'Administrator', 'New complaint CMP-2026-0010 - Overflow', 'A new Overflow issue was reported for BIN-B-002.', 0, '2026-09-11 23:29:40'),
(9, 10, 'Reporter', 'Complaint CMP-2026-0010 is being dealt with', 'Your report about BIN-B-002 has been accepted and work is being arranged. Collection scheduled (schedule #2).', 0, '2026-09-11 23:54:56'),
(10, 11, 'Administrator', 'New complaint CMP-2026-0011 - Damaged Bin', 'A new Damaged Bin issue was reported for BIN-P-001.', 0, '2026-09-12 00:04:07'),
(11, 11, 'Reporter', 'Complaint CMP-2026-0011 is being dealt with', 'Your report about BIN-P-001 has been accepted and work is being arranged. Collection scheduled (schedule #3).', 0, '2026-09-12 00:05:00'),
(12, 12, 'Administrator', 'New complaint CMP-2026-0012 - Damaged Bin', 'A new Damaged Bin issue was reported for BIN-C-001.', 0, '2026-09-12 00:08:39'),
(13, 12, 'Administrator', 'Complaint CMP-2026-0012 withdrawn', 'Admin withdrew the Damaged Bin report about BIN-C-001.', 0, '2026-09-12 01:16:26'),
(14, 13, 'Administrator', 'New complaint CMP-2026-0013 - Overflow', 'A new Overflow issue was reported for BIN-B-003.', 0, '2026-09-12 01:27:47'),
(15, 13, 'Reporter', 'Complaint CMP-2026-0013 is being dealt with', 'Your report about BIN-B-003 has been accepted and work is being arranged. The administrator says: we already assign cleaner to do cleaning', 0, '2026-09-12 01:29:24'),
(16, 13, 'Reporter', 'Complaint CMP-2026-0013 resolved', 'Your report about BIN-B-003 was marked Resolved.', 0, '2026-09-12 01:31:56'),
(17, 14, 'Administrator', 'New complaint CMP-2026-0014 - Dirty Area', 'A new Dirty Area issue was reported for BIN-B-003.', 0, '2026-09-12 01:40:23'),
(18, 15, 'Administrator', 'New complaint CMP-2026-0015 - Dirty Area', 'A new Dirty Area issue was reported for BIN-A-002.', 0, '2026-09-12 01:55:11'),
(19, 15, 'Reporter', 'Complaint CMP-2026-0015 is being dealt with', 'Your report about BIN-A-002 has been accepted and work is being arranged.', 0, '2026-09-12 01:56:38'),
(20, 15, 'Reporter', 'Complaint CMP-2026-0015 is waiting again', 'The collection arranged for BIN-A-002 is no longer going ahead, so your report is waiting to be dealt with again.', 0, '2026-09-12 01:57:45'),
(21, 15, 'Reporter', 'Complaint CMP-2026-0015 is being dealt with', 'Your report about BIN-A-002 has been accepted and work is being arranged.', 0, '2026-09-12 01:58:04'),
(22, 6, 'Reporter', 'Complaint CMP-2026-0006 is being dealt with', 'Your report about BIN-A-003 has been accepted and work is being arranged. The administrator says: we will clean it ASAP', 0, '2026-09-12 01:58:46'),
(23, 14, 'Reporter', 'Complaint CMP-2026-0014 is being dealt with', 'Your report about BIN-B-003 has been accepted and work is being arranged.', 0, '2026-09-12 02:09:29'),
(24, 14, 'Reporter', 'Complaint CMP-2026-0014 resolved', 'Your report about BIN-B-003 was marked Resolved.', 0, '2026-09-12 02:09:34'),
(25, 14, 'Administrator', 'Complaint CMP-2026-0014 withdrawn', 'Admin withdrew the Dirty Area report about BIN-B-003.', 0, '2026-09-12 02:09:42'),
(26, 16, 'Administrator', 'New complaint CMP-2026-0016 - Overflow', 'A new Overflow issue was reported for BIN-B-001.', 0, '2026-09-12 02:23:12'),
(27, 17, 'Administrator', 'New complaint CMP-2026-0017 - Overflow', 'A new Overflow issue was reported for BIN-A-001.', 0, '2026-09-12 03:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_revisions`
--

CREATE TABLE `complaint_revisions` (
  `revision_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `edited_by` int(11) DEFAULT NULL,
  `bin_id` int(11) DEFAULT NULL,
  `complaint_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `attachment_id` int(11) DEFAULT NULL,
  `edited_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_revisions`
--

INSERT INTO `complaint_revisions` (`revision_id`, `complaint_id`, `edited_by`, `bin_id`, `complaint_type`, `description`, `attachment_id`, `edited_at`) VALUES
(1, 9, 3, 6, 'Overflow', 'Please clean the bin ASAP', 1, '2026-09-11 17:55:21'),
(2, 9, 3, 6, 'Overflow', 'Please clean the bin ASAP', 2, '2026-09-11 21:20:02'),
(3, 10, 3, 6, 'Overflow', 'please clean the rubbish bin', NULL, '2026-09-11 23:29:50'),
(4, 14, 3, 7, 'Dirty Area', 'the surrounding of the bin is too dirty', NULL, '2026-09-12 01:41:23'),
(5, 14, 3, 7, 'Damaged Bin', 'the surrounding of the bin is too dirty', NULL, '2026-09-12 01:41:58'),
(6, 15, 3, 2, 'Dirty Area', 'please clean the bin area', NULL, '2026-09-12 01:55:59');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_status_history`
--

CREATE TABLE `complaint_status_history` (
  `history_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `change_type` enum('Status','Details','Withdrawn') NOT NULL DEFAULT 'Status',
  `remarks` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_status_history`
--

INSERT INTO `complaint_status_history` (`history_id`, `complaint_id`, `updated_by`, `old_status`, `new_status`, `change_type`, `remarks`, `updated_at`) VALUES
(1, 2, 1, 'New', 'Assigned', 'Status', 'Assigned to cleaner Ahmad Zaki.', '2026-09-10 20:12:31'),
(2, 4, 1, 'New', 'Assigned', 'Status', 'Cleaning team notified.', '2026-09-10 20:12:31'),
(3, 4, 1, 'Assigned', 'Resolved', 'Status', 'Area cleaned and bin emptied.', '2026-09-10 20:12:31'),
(4, 9, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-11 15:39:30'),
(5, 9, 3, 'New', 'New', 'Details', 'Edited: photo.', '2026-09-11 17:55:21'),
(6, 9, 3, 'New', 'New', 'Details', 'Edited: description.', '2026-09-11 21:20:02'),
(7, 9, 1, 'New', 'Assigned', 'Status', 'we will do it ASAP', '2026-09-11 22:52:38'),
(8, 1, 1, 'New', 'Assigned', 'Status', 'Collection scheduled (schedule #1).', '2026-09-11 22:53:26'),
(9, 3, 1, 'New', 'Assigned', 'Status', 'Collection scheduled (schedule #1).', '2026-09-11 22:53:26'),
(10, 5, 1, 'New', 'Assigned', 'Status', 'Collection scheduled (schedule #1).', '2026-09-11 22:53:26'),
(11, 8, 1, 'New', 'Assigned', 'Status', NULL, '2026-09-11 23:12:25'),
(12, 9, 1, 'Assigned', 'Resolved', 'Status', NULL, '2026-09-11 23:26:27'),
(13, 10, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-11 23:29:40'),
(14, 10, 3, 'New', 'New', 'Details', 'Edited: description.', '2026-09-11 23:29:50'),
(15, 10, 1, 'New', 'Assigned', 'Status', 'Collection scheduled (schedule #2).', '2026-09-11 23:54:56'),
(16, 11, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 00:04:07'),
(17, 11, 1, 'New', 'Assigned', 'Status', 'Collection scheduled (schedule #3).', '2026-09-12 00:05:00'),
(18, 12, 4, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 00:08:39'),
(19, 12, 1, 'New', 'New', 'Withdrawn', 'Withdrawn by Admin.', '2026-09-12 01:16:26'),
(20, 13, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 01:27:47'),
(21, 13, 1, 'New', 'Assigned', 'Status', 'Collection scheduled (schedule #4).', '2026-09-12 01:29:24'),
(22, 13, 1, 'Assigned', 'Resolved', 'Status', NULL, '2026-09-12 01:31:56'),
(23, 14, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 01:40:23'),
(24, 14, 3, 'New', 'New', 'Details', 'Edited: issue type.', '2026-09-12 01:41:23'),
(25, 14, 3, 'New', 'New', 'Details', 'Edited: issue type.', '2026-09-12 01:41:58'),
(26, 15, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 01:55:11'),
(27, 15, 3, 'New', 'New', 'Details', 'Edited: issue type, description.', '2026-09-12 01:55:59'),
(28, 15, 1, 'New', 'Assigned', 'Status', 'we will send people to replace the bin', '2026-09-12 01:56:38'),
(29, 15, 1, 'Assigned', 'New', 'Status', NULL, '2026-09-12 01:57:45'),
(30, 15, 1, 'New', 'Assigned', 'Status', NULL, '2026-09-12 01:58:04'),
(31, 6, 1, 'New', 'Assigned', 'Status', 'we will clean it ASAP', '2026-09-12 01:58:46'),
(32, 14, 1, 'New', 'Assigned', 'Status', NULL, '2026-09-12 02:09:29'),
(33, 14, 1, 'Assigned', 'Resolved', 'Status', NULL, '2026-09-12 02:09:34'),
(34, 14, 1, 'Resolved', 'Resolved', 'Withdrawn', 'Withdrawn by Admin.', '2026-09-12 02:09:42'),
(35, 16, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 02:23:12'),
(36, 17, 3, NULL, 'New', 'Status', 'Complaint submitted.', '2026-09-12 03:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_types`
--

CREATE TABLE `complaint_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `marks_bin_full` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_types`
--

INSERT INTO `complaint_types` (`type_id`, `type_name`, `marks_bin_full`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Full Bin', 1, 1, 10, '2026-09-11 18:25:10'),
(2, 'Overflow', 1, 1, 20, '2026-09-11 18:25:10'),
(3, 'Damaged Bin', 0, 1, 30, '2026-09-11 18:25:10'),
(4, 'Dirty Area', 0, 1, 40, '2026-09-11 18:25:10'),
(5, 'Wrong Waste Disposal', 0, 1, 50, '2026-09-11 18:25:10'),
(6, 'Other', 0, 1, 60, '2026-09-11 18:25:10');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `building_name` varchar(100) DEFAULT NULL,
  `floor_no` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `location_name`, `building_name`, `floor_no`, `description`, `latitude`, `longitude`, `deleted_at`) VALUES
(1, 'Main Lobby', 'Block A', 'Ground', 'Beside the main entrance', NULL, NULL, NULL),
(2, 'Cafeteria', 'Block A', 'Level 1', 'Next to the food counters', NULL, NULL, NULL),
(3, 'Library Entrance', 'Block B', 'Ground', 'Outside the turnstiles', NULL, NULL, NULL),
(4, 'Computer Lab 3', 'Block B', 'Level 2', 'Corridor outside the lab', NULL, NULL, NULL),
(5, 'Lecture Hall LH1', 'Block C', 'Ground', 'Rear exit', NULL, NULL, NULL),
(6, 'Student Car Park', 'Open Area', 'Ground', 'Near the motorcycle bay', NULL, NULL, NULL),
(7, 'Hostel Block D', 'Block D', 'Level 1', 'Common room area', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone_no` varchar(20) DEFAULT NULL,
  `address_line1` varchar(200) DEFAULT NULL,
  `address_line2` varchar(200) DEFAULT NULL,
  `ic_no` varchar(14) DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postcode` varchar(12) DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `role` enum('Reporter','Cleaner','Administrator') NOT NULL,
  `account_status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `phone_no`, `address_line1`, `address_line2`, `ic_no`, `gender`, `birth_date`, `city`, `state`, `postcode`, `nationality`, `role`, `account_status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Admin', 'admin@ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '012-3456789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Administrator', 'Active', '2026-09-10 20:12:31', '2026-09-11 08:42:23', NULL),
(2, 'Ng Zi Zhang', 'zizhang@ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '012-3456790', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Administrator', 'Active', '2026-09-10 20:12:31', '2026-09-11 08:42:14', NULL),
(3, 'Siti Nurhaliza', 'siti@student.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '013-2223344', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Reporter', 'Active', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(4, 'Lim Wei Jie', 'weijie@student.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '013-2223355', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Reporter', 'Active', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(5, 'Raj Kumar', 'raj@staff.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '014-5556677', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Reporter', 'Active', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(6, 'Ahmad Zaki', 'zaki@cleaner.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '016-7778899', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cleaner', 'Active', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(7, 'Mary Chong', 'mary@cleaner.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '016-7778800', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cleaner', 'Active', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `waste_categories`
--

CREATE TABLE `waste_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `waste_categories`
--

INSERT INTO `waste_categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'General', 'Non-recyclable general waste'),
(2, 'Recyclable', 'Paper, plastic, glass and aluminium'),
(3, 'Organic', 'Food waste and compostable material'),
(4, 'E-Waste', 'Batteries, cables and small electronics');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bins`
--
ALTER TABLE `bins`
  ADD PRIMARY KEY (`bin_id`),
  ADD UNIQUE KEY `bin_code` (`bin_code`),
  ADD KEY `fk_bins_location` (`location_id`),
  ADD KEY `fk_bins_category` (`category_id`),
  ADD KEY `idx_bins_status` (`fill_status`);

--
-- Indexes for table `bin_status_updates`
--
ALTER TABLE `bin_status_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `fk_bin_updates_cleaner` (`cleaner_id`),
  ADD KEY `idx_bin_updates_bin_time` (`bin_id`,`updated_at`);

--
-- Indexes for table `collection_assignments`
--
ALTER TABLE `collection_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_schedule_bin` (`schedule_id`,`bin_id`),
  ADD KEY `fk_assignments_bin` (`bin_id`),
  ADD KEY `fk_assignments_complaint` (`source_complaint_id`),
  ADD KEY `idx_assignments_cleaner` (`cleaner_id`,`assignment_status`);

--
-- Indexes for table `collection_records`
--
ALTER TABLE `collection_records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `assignment_id` (`assignment_id`),
  ADD KEY `fk_records_cleaner` (`cleaner_id`),
  ADD KEY `fk_records_bin` (`bin_id`),
  ADD KEY `fk_records_category` (`category_id`);

--
-- Indexes for table `collection_schedules`
--
ALTER TABLE `collection_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `fk_schedules_admin` (`admin_id`),
  ADD KEY `idx_schedules_date` (`schedule_date`,`schedule_status`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `fk_complaints_bin` (`bin_id`),
  ADD KEY `idx_complaints_status` (`complaint_status`),
  ADD KEY `idx_complaints_reporter` (`reporter_id`),
  ADD KEY `fk_complaints_type` (`complaint_type`);

--
-- Indexes for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `fk_attachments_complaint` (`complaint_id`);

--
-- Indexes for table `complaint_notifications`
--
ALTER TABLE `complaint_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notifications_complaint` (`complaint_id`),
  ADD KEY `idx_notifications_unread` (`recipient_role`,`is_read`);

--
-- Indexes for table `complaint_revisions`
--
ALTER TABLE `complaint_revisions`
  ADD PRIMARY KEY (`revision_id`),
  ADD KEY `fk_revisions_user` (`edited_by`),
  ADD KEY `fk_revisions_bin` (`bin_id`),
  ADD KEY `idx_revisions_complaint` (`complaint_id`,`edited_at`),
  ADD KEY `fk_revisions_attachment` (`attachment_id`);

--
-- Indexes for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_history_complaint` (`complaint_id`),
  ADD KEY `fk_history_user` (`updated_by`);

--
-- Indexes for table `complaint_types`
--
ALTER TABLE `complaint_types`
  ADD PRIMARY KEY (`type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `idx_locations_deleted` (`deleted_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_deleted` (`deleted_at`);

--
-- Indexes for table `waste_categories`
--
ALTER TABLE `waste_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bins`
--
ALTER TABLE `bins`
  MODIFY `bin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `bin_status_updates`
--
ALTER TABLE `bin_status_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `collection_assignments`
--
ALTER TABLE `collection_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `collection_records`
--
ALTER TABLE `collection_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collection_schedules`
--
ALTER TABLE `collection_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `complaint_notifications`
--
ALTER TABLE `complaint_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `complaint_revisions`
--
ALTER TABLE `complaint_revisions`
  MODIFY `revision_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `complaint_types`
--
ALTER TABLE `complaint_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `waste_categories`
--
ALTER TABLE `waste_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bins`
--
ALTER TABLE `bins`
  ADD CONSTRAINT `fk_bins_category` FOREIGN KEY (`category_id`) REFERENCES `waste_categories` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bins_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON UPDATE CASCADE;

--
-- Constraints for table `bin_status_updates`
--
ALTER TABLE `bin_status_updates`
  ADD CONSTRAINT `fk_bin_updates_bin` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bin_updates_cleaner` FOREIGN KEY (`cleaner_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `collection_assignments`
--
ALTER TABLE `collection_assignments`
  ADD CONSTRAINT `fk_assignments_bin` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_cleaner` FOREIGN KEY (`cleaner_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_complaint` FOREIGN KEY (`source_complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `collection_schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `collection_records`
--
ALTER TABLE `collection_records`
  ADD CONSTRAINT `fk_records_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `collection_assignments` (`assignment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_records_bin` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_records_category` FOREIGN KEY (`category_id`) REFERENCES `waste_categories` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_records_cleaner` FOREIGN KEY (`cleaner_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `collection_schedules`
--
ALTER TABLE `collection_schedules`
  ADD CONSTRAINT `fk_schedules_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `fk_complaints_bin` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_complaints_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_complaints_type` FOREIGN KEY (`complaint_type`) REFERENCES `complaint_types` (`type_name`) ON UPDATE CASCADE;

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD CONSTRAINT `fk_attachments_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_notifications`
--
ALTER TABLE `complaint_notifications`
  ADD CONSTRAINT `fk_notifications_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_revisions`
--
ALTER TABLE `complaint_revisions`
  ADD CONSTRAINT `fk_revisions_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `complaint_attachments` (`attachment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_revisions_bin` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_revisions_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_revisions_user` FOREIGN KEY (`edited_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  ADD CONSTRAINT `fk_history_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
