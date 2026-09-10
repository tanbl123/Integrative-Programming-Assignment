-- Preserve campus location deletion history.
-- Author  : Ong Kar Heng (2408830)
-- Module  : Bin & Location Management
USE ecocampus;
ALTER TABLE locations ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
