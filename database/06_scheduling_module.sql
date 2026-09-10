-- =====================================================================
-- EcoCampus - Collection Scheduling module migration
-- Author  : Ng Zi Zhang (2406898)
-- Module  : Collection Scheduling & Assignment
-- =====================================================================
USE ecocampus;

CREATE TABLE IF NOT EXISTS collection_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    schedule_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    strategy ENUM('Full Bins', 'Complaint Priority', 'Routine') NOT NULL,
    schedule_status ENUM('Planned', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Planned',
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedules_admin FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_schedules_date (schedule_date, schedule_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS collection_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    cleaner_id INT NOT NULL,
    bin_id INT NOT NULL,
    source_complaint_id INT NULL,
    priority ENUM('Routine', 'Normal', 'Urgent') NOT NULL DEFAULT 'Normal',
    assignment_status ENUM('Assigned', 'Completed', 'Skipped') NOT NULL DEFAULT 'Assigned',
    reason VARCHAR(255) NULL,
    completed_at DATETIME NULL,
    completion_notes VARCHAR(1000) NULL,
    CONSTRAINT fk_assignments_schedule FOREIGN KEY (schedule_id) REFERENCES collection_schedules(schedule_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_cleaner FOREIGN KEY (cleaner_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_bin FOREIGN KEY (bin_id) REFERENCES bins(bin_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_complaint FOREIGN KEY (source_complaint_id) REFERENCES complaints(complaint_id) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY uq_schedule_bin (schedule_id, bin_id),
    INDEX idx_assignments_cleaner (cleaner_id, assignment_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS collection_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL UNIQUE,
    cleaner_id INT NOT NULL,
    bin_id INT NOT NULL,
    category_id INT NOT NULL,
    estimated_weight_kg DECIMAL(8,2) NULL,
    notes VARCHAR(1000) NULL,
    collected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_records_assignment FOREIGN KEY (assignment_id) REFERENCES collection_assignments(assignment_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_records_cleaner FOREIGN KEY (cleaner_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_bin FOREIGN KEY (bin_id) REFERENCES bins(bin_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_category FOREIGN KEY (category_id) REFERENCES waste_categories(category_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
