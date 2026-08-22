-- =====================================================================
-- EcoCampus Waste Management System - database schema
--
-- Author  : Tan Boon Leong (2402865)
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
    role           ENUM('Reporter', 'Cleaner', 'Administrator') NOT NULL,
    account_status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
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
    description   VARCHAR(255) NULL
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
    complaint_status ENUM('Pending', 'In Progress', 'Resolved', 'Rejected')
                         NOT NULL DEFAULT 'Pending',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
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
