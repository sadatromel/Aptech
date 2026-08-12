-- Migration: Create HR Attendance Operations & Movement Tables

CREATE TABLE IF NOT EXISTS `employee_movements` (
    `movement_id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `movement_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NULL,
    `destination` VARCHAR(255) NOT NULL,
    `purpose` VARCHAR(255) NOT NULL,
    `movement_type` ENUM('official','personal','buyer_visit') DEFAULT 'official',
    `status` ENUM('approved','pending','rejected') DEFAULT 'approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `factory_holidays` (
    `holiday_id` INT AUTO_INCREMENT PRIMARY KEY,
    `holiday_title` VARCHAR(255) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `holiday_type` ENUM('government','festival','company_declared') DEFAULT 'festival',
    `is_recurring` TINYINT(1) DEFAULT 0,
    `status` ENUM('active','removed') DEFAULT 'active',
    `remarks` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `factory_weekends` (
    `weekend_id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NULL,
    `weekend_day` ENUM('Friday','Saturday','Sunday') DEFAULT 'Friday',
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ot_requisitions` (
    `requisition_id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `requisition_date` DATE NOT NULL,
    `requested_ot_hours` DECIMAL(5,2) NOT NULL,
    `worker_count` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `status` ENUM('approved','pending','rejected') DEFAULT 'pending',
    `approved_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_transfers` (
    `transfer_id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_date` DATE NOT NULL,
    `target_date` DATE NOT NULL,
    `department_id` INT NULL,
    `status` ENUM('completed','pending') DEFAULT 'completed',
    `transferred_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
