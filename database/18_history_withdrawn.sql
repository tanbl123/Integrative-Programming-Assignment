-- =====================================================================
-- EcoCampus - record a complaint being withdrawn or deleted
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management - Observer design pattern
--
-- Withdrawing a complaint was the one thing that happened to a complaint
-- without the observers being told. A reporter withdrew their report and
-- it simply vanished: no history row saying who removed it or when, no
-- notification, and - the part that was an outright bug - no chance for
-- the bin observer to release a bin that only that complaint had marked
-- Full. The bin stayed Full with nothing open to justify it.
--
-- delete() now raises a third kind of event, so change_type needs a
-- third value. Existing rows are unaffected.
--
-- Run after 11_complaint_history_change_type.sql. Safe to re-run.
-- =====================================================================

USE ecocampus;

ALTER TABLE complaint_status_history
    MODIFY change_type ENUM('Status', 'Details', 'Withdrawn')
        NOT NULL DEFAULT 'Status';
