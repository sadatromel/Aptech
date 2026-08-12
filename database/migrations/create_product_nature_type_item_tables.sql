-- ============================================================
-- Migration: Create Product Nature, Product Type, Garments Item & Junction Tables
-- Module: Master Library
-- ============================================================

CREATE TABLE IF NOT EXISTS `product_natures` (
    `nature_id`   INT(11)       NOT NULL AUTO_INCREMENT,
    `nature_code` VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Unique code e.g. WOVEN, KNIT, SWEATER',
    `nature_name` VARCHAR(100)  NOT NULL COMMENT 'Descriptive name e.g. Woven Wear',
    `description` TEXT          DEFAULT NULL,
    `status`      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`nature_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores product nature types';

CREATE TABLE IF NOT EXISTS `product_types` (
    `type_id`     INT(11)       NOT NULL AUTO_INCREMENT,
    `type_code`   VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Unique code e.g. TS, POLO, SHIRT',
    `type_name`   VARCHAR(100)  NOT NULL COMMENT 'Descriptive name e.g. T-Shirt',
    `description` TEXT          DEFAULT NULL,
    `status`      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`type_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores product type classifications';

CREATE TABLE IF NOT EXISTS `garments_items` (
    `item_id`     INT(11)       NOT NULL AUTO_INCREMENT,
    `item_code`   VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Unique code e.g. GITEM_01',
    `item_name`   VARCHAR(100)  NOT NULL COMMENT 'Descriptive name e.g. Basic T-Shirt',
    `description` TEXT          DEFAULT NULL,
    `status`      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`item_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores actual garments items';

-- Many-to-Many Junctions

CREATE TABLE IF NOT EXISTS `product_nature_category` (
    `nature_id`   INT(11) NOT NULL,
    `category_id` INT(11) NOT NULL,
    PRIMARY KEY (`nature_id`, `category_id`),
    KEY `fk_natcat_category` (`category_id`),
    CONSTRAINT `fk_natcat_nature` FOREIGN KEY (`nature_id`) REFERENCES `product_natures` (`nature_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_natcat_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Junction table between Product Nature and Product Category';

CREATE TABLE IF NOT EXISTS `product_category_type` (
    `category_id` INT(11) NOT NULL,
    `type_id`     INT(11) NOT NULL,
    PRIMARY KEY (`category_id`, `type_id`),
    KEY `fk_cattype_type` (`type_id`),
    CONSTRAINT `fk_cattype_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cattype_type` FOREIGN KEY (`type_id`) REFERENCES `product_types` (`type_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Junction table between Product Category and Product Type';

CREATE TABLE IF NOT EXISTS `product_type_garments_item` (
    `type_id` INT(11) NOT NULL,
    `item_id` INT(11) NOT NULL,
    PRIMARY KEY (`type_id`, `item_id`),
    KEY `fk_typeitem_item` (`item_id`),
    CONSTRAINT `fk_typeitem_type` FOREIGN KEY (`type_id`) REFERENCES `product_types` (`type_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_typeitem_item` FOREIGN KEY (`item_id`) REFERENCES `garments_items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Junction table between Product Type and Garments Item';
