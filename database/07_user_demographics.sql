-- Apply once to an existing EcoCampus database before using the updated User module.
-- Nullable demographics preserve existing accounts and make disclosure optional.
-- Author: Ong Kar Heng (2408830).
USE ecocampus;
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS address_line1 VARCHAR(200) NULL,
    ADD COLUMN IF NOT EXISTS address_line2 VARCHAR(200) NULL,
    ADD COLUMN IF NOT EXISTS ic_no VARCHAR(14) NULL,
    ADD COLUMN IF NOT EXISTS gender VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS birth_date DATE NULL,
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS postcode VARCHAR(12) NULL,
    ADD COLUMN IF NOT EXISTS nationality VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
