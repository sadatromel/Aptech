CREATE TABLE IF NOT EXISTS `bundles` (
  `bundle_id` INT(11) NOT NULL AUTO_INCREMENT,
  `plan_id` INT(11) NOT NULL,
  `bundle_number` VARCHAR(50) NOT NULL,
  `size` VARCHAR(20) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `quantity` INT(11) DEFAULT 0,
  `start_serial` INT(11) DEFAULT 1,
  `end_serial` INT(11) DEFAULT 1,
  `barcode` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('generated', 'in_sewing', 'completed', 'rejected') DEFAULT 'generated',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bundle_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_bundle_number` (`bundle_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
