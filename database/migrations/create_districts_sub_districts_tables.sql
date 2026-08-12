-- Module: Master Library
-- Create Districts Table
CREATE TABLE IF NOT EXISTS `districts` (
  `district_id` INT AUTO_INCREMENT PRIMARY KEY,
  `district_name` VARCHAR(100) NOT NULL,
  `district_code` VARCHAR(20) DEFAULT NULL,
  `division_state` VARCHAR(100) DEFAULT NULL,
  `country_id` INT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_districts_country` (`country_id`),
  INDEX `idx_districts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Sub-Districts (Upazila / Thana) Table
CREATE TABLE IF NOT EXISTS `sub_districts` (
  `sub_district_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sub_district_name` VARCHAR(100) NOT NULL,
  `sub_district_code` VARCHAR(20) DEFAULT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `district_id` INT NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sub_districts_district` (`district_id`),
  INDEX `idx_sub_districts_status` (`status`),
  CONSTRAINT `fk_sub_districts_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`district_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
