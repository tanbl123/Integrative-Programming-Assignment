-- Author: Ong Kar Heng (2408830). Preserve campus location deletion history.
USE ecocampus;
ALTER TABLE locations ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
