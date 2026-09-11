-- =====================================================================
-- EcoCampus - map coordinates for campus locations
--
-- Author : Ong Kar Heng (2408830)
-- Module : Bin & Location Management
--
-- Adds optional coordinates used by the OpenStreetMap location directory.
-- Existing locations remain valid and can be pinned later from Edit location.
-- Safe to re-run on MariaDB/MySQL installations that support IF NOT EXISTS.
-- =====================================================================

USE ecocampus;

ALTER TABLE locations
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 7) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10, 7) NULL AFTER latitude;
