-- =====================================================================
-- EcoCampus - User & Access module migration for existing databases
-- =====================================================================

USE ecocampus;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;
