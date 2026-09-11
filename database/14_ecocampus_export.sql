-- =====================================================================
-- EcoCampus - complete database, exported from a working installation
--
-- Exported by : Tan Boon Leong (2402865)
-- Module      : Whole system
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
-- DO NOT ALSO RUN 01 to 13. Everything they build is already here,
-- including change_type, complaint_revisions and superseded_at, which
-- were added by migrations 11, 12 and 13.
--
-- The numbered files are still the record of how the schema was designed
-- and who owns each table; this file is the quickest way to a working
-- copy for a demonstration.
--
-- ONE EXCEPTION, and it matters. This dump was taken before
-- 15_complaint_types.sql existed, so it has no complaint_types table and
-- its complaints.complaint_type is still an ENUM. The application reads
-- the issue types from that table, so a database built from this file
-- alone cannot open the complaint form.
--
--   After importing this file, run 15_complaint_types.sql.
--
-- It is written to be safe on top of an existing database: the table is
-- created only if absent, the six types are inserted only if absent, and
-- the ENUM is converted in place. Any migration numbered above this file
-- should be applied the same way.
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
-- Generation Time: Sep 11, 2026 at 12:01 PM
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
(1, 'BIN-A-001', 1, 1, 'Half', 120, 1, '2026-09-10 20:12:31'),
(2, 'BIN-A-002', 1, 2, 'Empty', 120, 1, '2026-09-10 20:12:31'),
(3, 'BIN-A-003', 2, 1, 'Full', 240, 1, '2026-09-10 20:12:31'),
(4, 'BIN-A-004', 2, 3, 'Full', 240, 1, '2026-09-10 20:12:31'),
(5, 'BIN-B-001', 3, 2, 'Empty', 120, 1, '2026-09-10 20:12:31'),
(6, 'BIN-B-002', 4, 1, 'Full', 120, 1, '2026-09-11 09:39:30'),
(7, 'BIN-B-003', 4, 4, 'Empty', 60, 1, '2026-09-10 20:12:31'),
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

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `complaint_type` enum('Full Bin','Overflow','Damaged Bin','Dirty Area','Wrong Waste Disposal','Other') NOT NULL,
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
(1, 3, 3, 'Overflow', 'The bin beside the food counter is overflowing and rubbish is on the floor.', 'New', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(2, 4, 4, 'Full Bin', 'Organic waste bin in the cafeteria is completely full since this morning.', 'Assigned', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(3, 5, 8, 'Damaged Bin', 'The lid of the bin at the rear exit of LH1 is broken and will not close.', 'New', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(4, 3, 9, 'Dirty Area', 'Area around the car park bin is dirty and smells strongly.', 'Resolved', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(5, 4, 1, 'Wrong Waste Disposal', 'Someone put food waste into the general waste bin in the main lobby.', 'New', '2026-09-10 20:12:31', '2026-09-10 20:12:31', NULL),
(6, 4, 3, 'Overflow', 'Rubbish is spilling out of the bin next to the drinks stall. It has been like this since breakfast.', 'New', '2026-09-11 05:15:05', '2026-09-11 05:15:05', NULL),
(7, 5, 3, 'Overflow', 'Cafeteria bin is overflowing again. Flies around it and the floor is sticky.', 'Assigned', '2026-09-11 05:15:05', '2026-09-11 05:15:05', NULL),
(8, 3, 8, 'Damaged Bin', 'The bin outside LH1 is cracked down one side, and it also has not been emptied for days.', 'New', '2026-09-11 05:15:05', '2026-09-11 05:15:05', NULL),
(9, 3, 6, 'Overflow', 'Please clean the bin ASAP', 'New', '2026-09-11 15:39:30', '2026-09-11 11:55:21', NULL);

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
(1, 9, 'Administrator', 'New complaint CMP-2026-0009 - Overflow', 'A new Overflow issue was reported for BIN-B-002.', 0, '2026-09-11 09:39:30');

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
(1, 9, 3, 6, 'Overflow', 'Please clean the bin ASAP', 1, '2026-09-11 17:55:21');

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
  `change_type` enum('Status','Details') NOT NULL DEFAULT 'Status',
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
(5, 9, 3, 'New', 'New', 'Details', 'Edited: photo.', '2026-09-11 17:55:21');

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
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `location_name`, `building_name`, `floor_no`, `description`, `deleted_at`) VALUES
(1, 'Main Lobby', 'Block A', 'Ground', 'Beside the main entrance', NULL),
(2, 'Cafeteria', 'Block A', 'Level 1', 'Next to the food counters', NULL),
(3, 'Library Entrance', 'Block B', 'Ground', 'Outside the turnstiles', NULL),
(4, 'Computer Lab 3', 'Block B', 'Level 2', 'Corridor outside the lab', NULL),
(5, 'Lecture Hall LH1', 'Block C', 'Ground', 'Rear exit', NULL),
(6, 'Student Car Park', 'Open Area', 'Ground', 'Near the motorcycle bay', NULL),
(7, 'Hostel Block D', 'Block D', 'Level 1', 'Common room area', NULL);

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
  ADD KEY `idx_complaints_reporter` (`reporter_id`);

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
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collection_assignments`
--
ALTER TABLE `collection_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collection_records`
--
ALTER TABLE `collection_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collection_schedules`
--
ALTER TABLE `collection_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `complaint_notifications`
--
ALTER TABLE `complaint_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaint_revisions`
--
ALTER TABLE `complaint_revisions`
  MODIFY `revision_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  ADD CONSTRAINT `fk_complaints_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
