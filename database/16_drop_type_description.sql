-- =====================================================================
-- EcoCampus - remove the unused description from complaint_types
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management
--
-- complaint_types was created with a description column. An Administrator
-- typed it and nobody ever read it: a reporter never sees it, and the
-- type's name says the same thing. 15_complaint_types.sql no longer
-- creates it, but a database built from the first version of that file
-- still has it, where it invites the question of what it is for.
--
-- Run after 15_complaint_types.sql. Safe to re-run, and safe on a
-- database that never had the column.
-- =====================================================================

USE ecocampus;

ALTER TABLE complaint_types
    DROP COLUMN IF EXISTS description;
