-- =====================================================================
-- EcoCampus - issue types an administrator can maintain
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management
--
-- The six issue types were fixed twice over: as an array in
-- Complaint::types() and as an ENUM on complaints.complaint_type. Adding
-- a seventh meant editing code and altering a column, so in practice
-- nobody could.
--
-- complaint_types now holds them, and complaints.complaint_type is a
-- foreign key to it. The table has a surrogate key like every other table
-- here, and the foreign key points at the UNIQUE type_name rather than at
-- that id - which is why no existing row has to be rewritten. Every
-- complaint already stores the name, and the database enforces what the
-- ENUM enforced: a complaint cannot name a type that does not exist.
--
-- ON UPDATE CASCADE so that correcting a label corrects it everywhere it
-- was used; ON DELETE RESTRICT so that a type which complaints were filed
-- under cannot be deleted out from under them. Withdrawing a type from
-- use is what is_active is for: it disappears from the form while every
-- complaint already filed under it stays readable.
--
-- Run after 01_schema.sql. Safe to re-run.
-- =====================================================================

USE ecocampus;

CREATE TABLE IF NOT EXISTS complaint_types (
    type_id    INT AUTO_INCREMENT PRIMARY KEY,
    type_name  VARCHAR(50) NOT NULL UNIQUE,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    -- Where the type sits in the reporter's dropdown. Not something an
    -- administrator sets: the six seeded types keep Other last, and a new
    -- type is appended after them.
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- The six the module shipped with, so nothing already filed is orphaned.
INSERT IGNORE INTO complaint_types (type_name, sort_order) VALUES
('Full Bin', 10), ('Overflow', 20), ('Damaged Bin', 30),
('Dirty Area', 40), ('Wrong Waste Disposal', 50), ('Other', 60);

-- The ENUM becomes a plain string so the lookup table can govern it.
ALTER TABLE complaints
    MODIFY complaint_type VARCHAR(50) NOT NULL;

-- ADD CONSTRAINT has no IF NOT EXISTS in MySQL; this is the statement to
-- skip if the migration is run a second time.
ALTER TABLE complaints
    ADD CONSTRAINT fk_complaints_type
        FOREIGN KEY (complaint_type) REFERENCES complaint_types(type_name)
        ON UPDATE CASCADE ON DELETE RESTRICT;
