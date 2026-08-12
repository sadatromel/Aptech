-- ============================================================
-- Migration: Create Unit of Measure (UOM) Table
-- Module: Master Library
-- ============================================================

CREATE TABLE IF NOT EXISTS `uoms` (
    `uom_id`      INT(11)       NOT NULL AUTO_INCREMENT,
    `uom_code`    VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Unique short code e.g. PCS, YDS, KGS',
    `uom_name`    VARCHAR(100)  NOT NULL COMMENT 'Descriptive name e.g. Pieces, Yards, Kilograms',
    `description` TEXT          DEFAULT NULL,
    `status`      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`uom_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores Unit of Measure (UOM) master records';

-- Seed default UOM values
INSERT INTO `uoms` (`uom_code`, `uom_name`, `description`, `status`) VALUES
('PCS', 'Pieces', 'Individual units/pieces count', 'active'),
('Yds', 'Yards', 'Length measurement in yards', 'active'),
('Kgs', 'Kilograms', 'Weight measurement in kilograms', 'active'),
('Mtr', 'Meters', 'Length measurement in meters', 'active'),
('Ltr', 'Liters', 'Volume measurement in liters', 'active'),
('Box', 'Box', 'Packaging units in boxes', 'active'),
('Dzn', 'Dozens', 'Set of 12 units', 'active'),
('Roll', 'Roll', 'Fabric or packaging materials in rolls', 'active'),
('Cone', 'Cone', 'Sewing thread or yarn on cones', 'active');
