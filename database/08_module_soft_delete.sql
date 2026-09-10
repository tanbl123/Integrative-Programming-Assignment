-- Preserve complaint attachments/history and schedule collection records.
USE ecocampus;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE collection_schedules ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
