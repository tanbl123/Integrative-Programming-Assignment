-- =====================================================================
-- EcoCampus - record what kind of change a history row describes
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management - Observer design pattern
--
-- complaint_status_history began as a record of status transitions only.
-- It now also records an edit to a complaint's own details, so that an
-- Administrator correcting a reporter's words leaves a trace instead of
-- silently replacing them. change_type says which kind of event a row
-- describes; existing rows are all status transitions, which is why the
-- column defaults to 'Status'.
--
-- Run after 01_schema.sql. Safe to re-run.
-- =====================================================================

USE ecocampus;

ALTER TABLE complaint_status_history
    ADD COLUMN IF NOT EXISTS change_type ENUM('Status', 'Details')
        NOT NULL DEFAULT 'Status'
        AFTER new_status;
