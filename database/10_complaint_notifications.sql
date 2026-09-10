-- =====================================================================
-- EcoCampus - Complaint notification inbox
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management - Observer design pattern
--
-- Written by ComplaintNotificationObserver whenever a complaint is
-- submitted or changes state. Kept separate from complaint_status_history
-- so that the audit trail and the admin inbox stay independent concerns -
-- which is precisely why the Observer pattern is used here.
-- =====================================================================

USE ecocampus;

CREATE TABLE IF NOT EXISTS complaint_notifications (
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
