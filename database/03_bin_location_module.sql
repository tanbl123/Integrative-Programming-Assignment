-- =====================================================================
-- EcoCampus - Bin & Location module migration for existing databases
-- Run once after 01_schema.sql and 02_seed.sql on an existing setup.
-- Author  : Ong Kar Heng (2408830)
-- Module  : Bin & Location Management
-- =====================================================================

USE ecocampus;

CREATE TABLE IF NOT EXISTS bin_status_updates (
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
