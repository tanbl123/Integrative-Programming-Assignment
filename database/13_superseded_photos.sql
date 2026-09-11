-- =====================================================================
-- EcoCampus - keep the photograph an edit replaced
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management
--
-- complaint_revisions already keeps what a complaint SAID before an edit.
-- The photograph was still being deleted from disk, so a damning photo
-- could be replaced with an innocuous one and the original lost - the
-- same thing the revisions table exists to prevent, through the other
-- door.
--
-- A replaced photo is now marked superseded rather than removed. It stops
-- appearing as the complaint's evidence, and the revision that replaced it
-- points at it so it can still be read back.
--
-- Run after 12_complaint_revisions.sql. Safe to re-run.
-- =====================================================================

USE ecocampus;

ALTER TABLE complaint_attachments
    ADD COLUMN IF NOT EXISTS superseded_at DATETIME NULL DEFAULT NULL;

ALTER TABLE complaint_revisions
    ADD COLUMN IF NOT EXISTS attachment_id INT NULL AFTER description;

-- Added separately: ADD CONSTRAINT has no IF NOT EXISTS in MySQL, so this
-- statement is the one to skip if the migration is run twice.
ALTER TABLE complaint_revisions
    ADD CONSTRAINT fk_revisions_attachment
        FOREIGN KEY (attachment_id) REFERENCES complaint_attachments(attachment_id)
        ON DELETE SET NULL ON UPDATE CASCADE;
