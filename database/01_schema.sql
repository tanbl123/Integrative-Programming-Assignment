-- =====================================================================
-- EcoCampus Waste Management System - database schema
--
-- Author  : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
--           Tan Boon Leong (2402865), Phang Jun Hong (2406646)
-- Module  : Shared core
-- Course  : BMIT3173 Integrative Programming, Group D
-- SDG     : 11 - Sustainable Cities and Communities
--
-- Run this file FIRST, then 02_seed.sql.
-- phpMyAdmin -> Import -> choose file -> Go
--
-- Scope note: this covers the shared tables plus the Complaint / Report
-- Management module. Bin & Location, Scheduling and User & Access members
-- extend it with their own tables as their modules grow.
-- =====================================================================

DROP DATABASE IF EXISTS ecocampus;
CREATE DATABASE ecocampus
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE ecocampus;

-- ---------------------------------------------------------------------
-- users - every person who can log in
-- Owner: Phang Jun Hong (User & Access Management)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(100) NOT NULL UNIQUE,
    -- Stores a password_hash() result, never a plain password.
    password_hash  VARCHAR(255) NOT NULL,
    phone_no       VARCHAR(20)  NULL,
    address_line1  VARCHAR(200) NULL,
    address_line2  VARCHAR(200) NULL,
    ic_no          VARCHAR(14)  NULL,
    gender         VARCHAR(30)  NULL,
    birth_date     DATE         NULL,
    city           VARCHAR(100) NULL,
    state          VARCHAR(100) NULL,
    postcode       VARCHAR(12)  NULL,
    nationality    VARCHAR(80)  NULL,
    role           ENUM('Reporter', 'Cleaner', 'Administrator') NOT NULL,
    account_status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME NULL DEFAULT NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_deleted (deleted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- locations - where bins physically sit
-- Owner: Ong Kar Heng (Bin & Location Management)
-- ---------------------------------------------------------------------
CREATE TABLE locations (
    location_id   INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    building_name VARCHAR(100) NULL,
    floor_no      VARCHAR(20)  NULL,
    description   VARCHAR(255) NULL,
    deleted_at    DATETIME NULL DEFAULT NULL,
    INDEX idx_locations_deleted (deleted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- waste_categories - General, Recyclable, Organic, ...
-- Owner: Ong Kar Heng (Bin & Location Management)
-- ---------------------------------------------------------------------
CREATE TABLE waste_categories (
    category_id   INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description   VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- bins - the master bin record
-- Owner: Ong Kar Heng (Bin & Location Management)
--
-- fill_status matches the Initial Deliverable wording (Empty / Half /
-- Full / Under Maintenance). The Week-4 draft SQL used a different set
-- (Normal / Overflow / Dirty); this version is the one the code follows.
-- ---------------------------------------------------------------------
CREATE TABLE bins (
    bin_id         INT AUTO_INCREMENT PRIMARY KEY,
    bin_code       VARCHAR(50) NOT NULL UNIQUE,
    location_id    INT NOT NULL,
    category_id    INT NOT NULL,
    fill_status    ENUM('Empty', 'Half', 'Full', 'Under Maintenance')
                       NOT NULL DEFAULT 'Empty',
    capacity_litre INT NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    last_updated   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bins_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_bins_category
        FOREIGN KEY (category_id) REFERENCES waste_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_bins_status (fill_status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- bin_status_updates - audit trail for cleaner inspections/services
-- Owner: Ong Kar Heng (Bin & Location Management)
-- ---------------------------------------------------------------------
CREATE TABLE bin_status_updates (
    update_id  INT AUTO_INCREMENT PRIMARY KEY,
    bin_id     INT NOT NULL,
    cleaner_id INT NOT NULL,
    old_status ENUM('Empty', 'Half', 'Full', 'Under Maintenance') NOT NULL,
    new_status ENUM('Empty', 'Half', 'Full', 'Under Maintenance') NOT NULL,
    remarks    VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bin_updates_bin
        FOREIGN KEY (bin_id) REFERENCES bins(bin_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bin_updates_cleaner
        FOREIGN KEY (cleaner_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_bin_updates_bin_time (bin_id, updated_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- complaints - a waste issue reported against a bin
-- Owner: Tan Boon Leong (Complaint / Report Management)
-- ---------------------------------------------------------------------
CREATE TABLE complaints (
    complaint_id     INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id      INT NOT NULL,
    bin_id           INT NOT NULL,
    complaint_type   ENUM('Full Bin', 'Overflow', 'Damaged Bin',
                          'Dirty Area', 'Wrong Waste Disposal', 'Other')
                         NOT NULL,
    description      TEXT NOT NULL,
    complaint_status ENUM('New', 'Assigned', 'Resolved', 'Rejected')
                         NOT NULL DEFAULT 'New',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_complaints_reporter
        FOREIGN KEY (reporter_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_complaints_bin
        FOREIGN KEY (bin_id) REFERENCES bins(bin_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_complaints_status (complaint_status),
    INDEX idx_complaints_reporter (reporter_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- complaint_attachments - optional photo evidence
-- Owner: Tan Boon Leong (Complaint / Report Management)
--
-- stored_name is a system-generated filename. The user's original filename
-- is kept only for display and is never used as a path on disk - that is
-- part of the Malicious File Upload defence.
-- ---------------------------------------------------------------------
CREATE TABLE complaint_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id  INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    file_size     INT NOT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attachments_complaint
        FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- complaint_status_history - audit trail of every status change
-- Owner: Tan Boon Leong (Complaint / Report Management)
--
-- Written by the ActivityLogger observer whenever a complaint changes.
-- ---------------------------------------------------------------------
CREATE TABLE complaint_status_history (
    history_id   INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    updated_by   INT NULL,
    old_status   VARCHAR(50) NULL,
    new_status   VARCHAR(50) NOT NULL,
    remarks      VARCHAR(255) NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_complaint
        FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (updated_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- complaint_notifications - dashboard alerts raised by complaint events
-- Owner: Tan Boon Leong (Complaint / Report Management)
--
-- Written by ComplaintNotificationObserver. Kept separate from
-- complaint_status_history so the audit trail and the alert inbox remain
-- independent concerns - the reason the Observer pattern is used here.
-- ---------------------------------------------------------------------
CREATE TABLE complaint_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id    INT NOT NULL,
    recipient_role  ENUM('Reporter', 'Cleaner', 'Administrator') NOT NULL,
    title           VARCHAR(150) NOT NULL,
    body            VARCHAR(255) NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_complaint
        FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_notifications_unread (recipient_role, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Collection Scheduling & Assignment module
-- Owner: Ng Zi Zhang (implemented/integrated by Ong Kar Heng)
-- ---------------------------------------------------------------------
CREATE TABLE collection_schedules (
    schedule_id   INT AUTO_INCREMENT PRIMARY KEY,
    admin_id      INT NOT NULL,
    schedule_date DATE NOT NULL,
    time_slot     VARCHAR(50) NOT NULL,
    strategy      ENUM('Full Bins', 'Complaint Priority', 'Routine') NOT NULL,
    schedule_status ENUM('Planned', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Planned',
    notes         VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_schedules_admin FOREIGN KEY (admin_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_schedules_date (schedule_date, schedule_status)
) ENGINE=InnoDB;

CREATE TABLE collection_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id   INT NOT NULL,
    cleaner_id    INT NOT NULL,
    bin_id        INT NOT NULL,
    source_complaint_id INT NULL,
    priority      ENUM('Routine', 'Normal', 'Urgent') NOT NULL DEFAULT 'Normal',
    assignment_status ENUM('Assigned', 'Completed', 'Skipped') NOT NULL DEFAULT 'Assigned',
    reason        VARCHAR(255) NULL,
    completed_at  DATETIME NULL,
    completion_notes VARCHAR(1000) NULL,
    CONSTRAINT fk_assignments_schedule FOREIGN KEY (schedule_id) REFERENCES collection_schedules(schedule_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_cleaner FOREIGN KEY (cleaner_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_bin FOREIGN KEY (bin_id) REFERENCES bins(bin_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_complaint FOREIGN KEY (source_complaint_id) REFERENCES complaints(complaint_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY uq_schedule_bin (schedule_id, bin_id),
    INDEX idx_assignments_cleaner (cleaner_id, assignment_status)
) ENGINE=InnoDB;

CREATE TABLE collection_records (
    record_id     INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL UNIQUE,
    cleaner_id    INT NOT NULL,
    bin_id        INT NOT NULL,
    category_id   INT NOT NULL,
    estimated_weight_kg DECIMAL(8,2) NULL,
    notes         VARCHAR(1000) NULL,
    collected_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_records_assignment FOREIGN KEY (assignment_id) REFERENCES collection_assignments(assignment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_records_cleaner FOREIGN KEY (cleaner_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_bin FOREIGN KEY (bin_id) REFERENCES bins(bin_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_category FOREIGN KEY (category_id) REFERENCES waste_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
