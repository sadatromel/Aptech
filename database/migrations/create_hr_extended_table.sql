-- Database Migration for Extended HRMS module tables
CREATE TABLE IF NOT EXISTS `hr_extended_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `section` VARCHAR(50) NOT NULL,
    `page` VARCHAR(50) NOT NULL,
    `data` TEXT NOT NULL, -- Storing JSON-encoded data row
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
