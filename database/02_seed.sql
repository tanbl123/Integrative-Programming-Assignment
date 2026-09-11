-- =====================================================================
-- EcoCampus Waste Management System - initial data
-- Author  : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
--           Tan Boon Leong (2402865), Phang Jun Hong (2406646)
-- Module : Shared core
--
-- Run AFTER 01_schema.sql.
--
-- Every seeded account uses the password:  password123
-- The stored value is a real bcrypt hash produced by PHP's password_hash(),
-- so password_verify() will accept it. Plain passwords are never stored.
-- =====================================================================

USE ecocampus;

-- ---------------------------------------------------------------------
-- Users - one of each role, plus extra reporters and cleaners
-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- Issue types - maintained by an Administrator, seeded with the six the
-- Initial Deliverable identified.
-- ---------------------------------------------------------------------
INSERT INTO complaint_types (type_name, sort_order) VALUES
('Full Bin', 10), ('Overflow', 20), ('Damaged Bin', 30),
('Dirty Area', 40), ('Wrong Waste Disposal', 50), ('Other', 60);

INSERT INTO users (full_name, email, password_hash, phone_no, role) VALUES
('Admin',  'admin@ecocampus.my',    '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '012-3456789', 'Administrator'),
('Ng Zi Zhang',     'zizhang@ecocampus.my',  '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '012-3456790', 'Administrator'),
('Siti Nurhaliza',  'siti@student.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '013-2223344', 'Reporter'),
('Lim Wei Jie',     'weijie@student.ecocampus.my','$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '013-2223355', 'Reporter'),
('Raj Kumar',       'raj@staff.ecocampus.my',    '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '014-5556677', 'Reporter'),
('Ahmad Zaki',      'zaki@cleaner.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '016-7778899', 'Cleaner'),
('Mary Chong',      'mary@cleaner.ecocampus.my', '$2y$12$S2ADOnIbQkqYaQ0Z2unGO.0IygbU3ytlX/sFN0KdkAuWgpmngM4g6', '016-7778800', 'Cleaner');

-- ---------------------------------------------------------------------
-- Locations
-- ---------------------------------------------------------------------
INSERT INTO locations (location_name, building_name, floor_no, description) VALUES
('Main Lobby',        'Block A', 'Ground', 'Beside the main entrance'),
('Cafeteria',         'Block A', 'Level 1', 'Next to the food counters'),
('Library Entrance',  'Block B', 'Ground', 'Outside the turnstiles'),
('Computer Lab 3',    'Block B', 'Level 2', 'Corridor outside the lab'),
('Lecture Hall LH1',  'Block C', 'Ground', 'Rear exit'),
('Student Car Park',  'Open Area', 'Ground', 'Near the motorcycle bay'),
('Hostel Block D',    'Block D', 'Level 1', 'Common room area');

-- ---------------------------------------------------------------------
-- Waste categories
-- ---------------------------------------------------------------------
INSERT INTO waste_categories (category_name, description) VALUES
('General',    'Non-recyclable general waste'),
('Recyclable', 'Paper, plastic, glass and aluminium'),
('Organic',    'Food waste and compostable material'),
('E-Waste',    'Batteries, cables and small electronics');

-- ---------------------------------------------------------------------
-- Bins
-- ---------------------------------------------------------------------
INSERT INTO bins (bin_code, location_id, category_id, fill_status, capacity_litre) VALUES
('BIN-A-001', 1, 1, 'Half',              120),
('BIN-A-002', 1, 2, 'Empty',             120),
('BIN-A-003', 2, 1, 'Full',              240),
('BIN-A-004', 2, 3, 'Full',              240),
('BIN-B-001', 3, 2, 'Empty',             120),
('BIN-B-002', 4, 1, 'Half',              120),
('BIN-B-003', 4, 4, 'Empty',              60),
('BIN-C-001', 5, 1, 'Full',              240),
('BIN-P-001', 6, 1, 'Under Maintenance', 240),
('BIN-D-001', 7, 2, 'Half',              120);

-- ---------------------------------------------------------------------
-- Complaints - sample data for the Complaint / Report Management module
-- ---------------------------------------------------------------------
INSERT INTO complaints (reporter_id, bin_id, complaint_type, description, complaint_status) VALUES
(3, 3, 'Overflow',            'The bin beside the food counter is overflowing and rubbish is on the floor.', 'New'),
(4, 4, 'Full Bin',            'Organic waste bin in the cafeteria is completely full since this morning.',   'Assigned'),
(5, 8, 'Damaged Bin',         'The lid of the bin at the rear exit of LH1 is broken and will not close.',    'New'),
(3, 9, 'Dirty Area',          'Area around the car park bin is dirty and smells strongly.',                  'Resolved'),
(4, 1, 'Wrong Waste Disposal','Someone put food waste into the general waste bin in the main lobby.',        'New');

-- ---------------------------------------------------------------------
-- Status history for the complaints that have already moved on
-- ---------------------------------------------------------------------
INSERT INTO complaint_status_history (complaint_id, updated_by, old_status, new_status, remarks) VALUES
(2, 1, 'New',      'Assigned', 'Assigned to cleaner Ahmad Zaki.'),
(4, 1, 'New',      'Assigned', 'Cleaning team notified.'),
(4, 1, 'Assigned', 'Resolved', 'Area cleaned and bin emptied.');

-- ---------------------------------------------------------------------
-- Duplicate reports - several people reporting the same bin
--
-- Author : Tan Boon Leong (2402865)
-- Module : Complaint / Report Management
--
-- Complaint #1 above is already Siti's Overflow report on BIN-A-003. These
-- add two more reports of the same issue on the same bin, and a second,
-- smaller group on BIN-C-001, so the administrator's duplicate grouping has
-- something to show without anyone having to submit forms by hand.
--
-- Note the wording. The BIN-A-003 reports describe one problem three ways.
-- The second BIN-C-001 report mentions the bin also being unemptied, which is
-- arguably a different issue - exactly the judgement the "Reason given" column
-- exists to support, rather than assuming every group is truly duplicated.
--
-- One report is left Assigned to show that a group may mix New and Assigned
-- complaints; both permit a transition to Rejected, so neither is stranded.
-- ---------------------------------------------------------------------
INSERT INTO complaints (reporter_id, bin_id, complaint_type, description, complaint_status) VALUES
-- Group 1: BIN-A-003 (bin_id 3), Overflow - 3 reports in total with #1
(4, 3, 'Overflow',    'Rubbish is spilling out of the bin next to the drinks stall. It has been like this since breakfast.', 'New'),
(5, 3, 'Overflow',    'Cafeteria bin is overflowing again. Flies around it and the floor is sticky.',                        'Assigned'),
-- Group 2: BIN-C-001 (bin_id 8), Damaged Bin - 2 reports in total with #3
(3, 8, 'Damaged Bin', 'The bin outside LH1 is cracked down one side, and it also has not been emptied for days.',           'New');
