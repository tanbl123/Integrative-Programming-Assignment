-- =====================================================================
-- EcoCampus - keep what a complaint said before it was edited
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management - Observer design pattern
--
-- complaint_status_history records THAT a complaint was edited and which
-- fields moved. It cannot hold what they previously said: remarks is 255
-- characters and a description may run to 2,000.
--
-- Each row here is one complete snapshot of a complaint as it stood
-- immediately BEFORE an edit, written by ComplaintRevisionObserver. The
-- reporter can therefore see their original words after an Administrator
-- has changed them, and the Administrator can see what they replaced.
--
-- Rows are written once and never updated or deleted; a complaint's own
-- deletion is soft, so its revisions outlive it too.
--
-- Run after 01_schema.sql. Safe to re-run.
-- =====================================================================

USE ecocampus;

CREATE TABLE IF NOT EXISTS complaint_revisions (
    revision_id    INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id   INT NOT NULL,
    edited_by      INT NULL,
    bin_id         INT NULL,
    complaint_type VARCHAR(50) NOT NULL,
    description    TEXT NOT NULL,
    edited_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_revisions_complaint
        FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- The editor's account may go; what they replaced must not.
    CONSTRAINT fk_revisions_user
        FOREIGN KEY (edited_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    -- A bin may be removed from the system without erasing the fact that
    -- this complaint once named it.
    CONSTRAINT fk_revisions_bin
        FOREIGN KEY (bin_id) REFERENCES bins(bin_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_revisions_complaint (complaint_id, edited_at)
) ENGINE=InnoDB;
