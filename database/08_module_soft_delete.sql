-- Preserve complaint attachments/history and schedule collection records.
-- Author  : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
--           Tan Boon Leong (2402865), Phang Jun Hong (2406646)
-- Module  : Shared - alters tables owned by more than one module
USE ecocampus;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE collection_schedules ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
