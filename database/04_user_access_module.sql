-- =====================================================================
-- EcoCampus - User & Access module migration for existing databases
-- Author  : Phang Jun Hong (2406646)
-- Module  : User & Access Management
-- =====================================================================

USE ecocampus;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;
