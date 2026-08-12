-- ============================================================
-- Migration: Create Garments Sample Types Table
-- Module: Master Library
-- ============================================================

CREATE TABLE IF NOT EXISTS `garment_sample_types` (
    `sample_type_id`   INT(11)       NOT NULL AUTO_INCREMENT,
    `sample_type_code` VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Short unique code e.g. PROTO, FIT, PPS',
    `sample_type_name` VARCHAR(255)  NOT NULL COMMENT 'Descriptive name e.g. Proto Sample',
    `description`      TEXT          DEFAULT NULL,
    `status`           ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sample_type_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores various garment sample types used in RMG Merchandising';

-- Seed default RMG sample types
INSERT INTO `garment_sample_types` (`sample_type_code`, `sample_type_name`, `description`, `status`) VALUES
('PROTO', 'Proto Sample', 'Initial sample made from mock-up fabric to check fit and styling.', 'active'),
('FIT', 'Fit Sample', 'Sample made to test fit, measurements, and patterns on standard models.', 'active'),
('PHOTO', 'Photo Sample', 'Made in actual fabric/colours for catalogue or marketing photoshoots.', 'active'),
('SIZE_SET', 'Size Set Sample', 'Sample set prepared across all sizes to verify scale and grading.', 'active'),
('PP', 'Pre-Production Sample', 'Final sample made in the production sewing line with actual fabric/trims before bulk cutting starts.', 'active'),
('TOP', 'Top of Production Sample', 'Sample pulled from the initial bulk production line run to verify final output.', 'active');
