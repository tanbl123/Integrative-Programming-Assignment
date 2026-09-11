-- =====================================================================
-- EcoCampus - let an issue type say whether it means "the bin is full"
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management - Observer design pattern
--
-- ComplaintBinFlagObserver decided whether a complaint meant a bin was
-- full by comparing its type against the literal strings 'Overflow' and
-- 'Full Bin'. That was safe while the six types were an ENUM nothing
-- could change. It stopped being safe the moment 15_complaint_types.sql
-- let an Administrator maintain them:
--
--   * renaming 'Overflow' cascades into every complaint, and the
--     observer then matches nothing - silently, with no error anywhere;
--   * a new type that means the same thing ('Bin Spilling Over') is
--     invisible to the observer, because it was not in the list.
--
-- The name is a label an Administrator may change. Whether the type
-- means "this bin needs collecting" is meaning, and belongs on the row
-- next to the name, where renaming cannot touch it.
--
-- The two seeded types that carried that meaning are set here, so the
-- behaviour before and after this migration is identical.
--
-- Run after 15_complaint_types.sql. Safe to re-run.
-- =====================================================================

USE ecocampus;

ALTER TABLE complaint_types
    ADD COLUMN IF NOT EXISTS marks_bin_full TINYINT(1) NOT NULL DEFAULT 0
        AFTER type_name;

-- The behaviour the hard-coded list used to give, moved onto the rows.
-- Named rather than matched loosely, so a type an Administrator has
-- since renamed is not re-flagged by accident.
UPDATE complaint_types
    SET marks_bin_full = 1
    WHERE type_name IN ('Full Bin', 'Overflow');
