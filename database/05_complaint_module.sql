-- =====================================================================
-- EcoCampus - Complaint module migration for existing databases
-- Author : Ong Kar Heng (2408830)
-- =====================================================================

USE ecocampus;

ALTER TABLE complaints
    MODIFY complaint_status VARCHAR(20) NOT NULL DEFAULT 'New';

UPDATE complaints SET complaint_status = 'New' WHERE complaint_status = 'Pending';
UPDATE complaints SET complaint_status = 'Assigned' WHERE complaint_status = 'In Progress';
UPDATE complaint_status_history SET old_status = 'New' WHERE old_status = 'Pending';
UPDATE complaint_status_history SET new_status = 'New' WHERE new_status = 'Pending';
UPDATE complaint_status_history SET old_status = 'Assigned' WHERE old_status = 'In Progress';
UPDATE complaint_status_history SET new_status = 'Assigned' WHERE new_status = 'In Progress';

ALTER TABLE complaints
    MODIFY complaint_status ENUM('New', 'Assigned', 'Resolved', 'Rejected')
        NOT NULL DEFAULT 'New';
