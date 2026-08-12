-- ============================================================
-- Migration: Create Commercial Terms & Conditions Table
-- Module: Master Library
-- ============================================================

CREATE TABLE IF NOT EXISTS `commercial_terms_conditions` (
    `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
    `term_code`           VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Unique short code e.g. T001',
    `term_name`           VARCHAR(255)  NOT NULL COMMENT 'Descriptive name of the term',
    `term_type`           ENUM('Payment','Shipment','Delivery','Quality','Warranty','Return','General','Other') NOT NULL DEFAULT 'General',
    `applies_to`          ENUM('Buyer','Supplier','Both') NOT NULL DEFAULT 'Both',
    `description`         TEXT          DEFAULT NULL COMMENT 'Full description / clause text',
    `effective_from`      DATE          DEFAULT NULL,
    `effective_to`        DATE          DEFAULT NULL,
    `is_mandatory`        ENUM('Yes','No') NOT NULL DEFAULT 'No',
    `status`              ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_by`          INT(11)       DEFAULT NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_term_type`   (`term_type`),
    KEY `idx_applies_to`  (`applies_to`),
    KEY `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores commercial terms and conditions for buyers and suppliers';

-- Sample seed data
INSERT INTO `commercial_terms_conditions`
    (`term_code`, `term_name`, `term_type`, `applies_to`, `description`, `is_mandatory`, `status`)
VALUES
    ('T001', 'Net 30 Payment', 'Payment', 'Buyer',
     'Payment shall be made within 30 days of invoice date. Late payments will attract an interest of 1.5% per month.',
     'Yes', 'Active'),
    ('T002', 'FOB Chittagong', 'Shipment', 'Both',
     'Goods are considered delivered when loaded on board the vessel at Chittagong Port. Risk transfers to buyer at that point.',
     'Yes', 'Active'),
    ('T003', 'AQL 2.5 Quality Standard', 'Quality', 'Buyer',
     'Shipments will be inspected per AQL 2.5 sampling plan. Lots exceeding the AQL level shall be rejected.',
     'Yes', 'Active'),
    ('T004', '30-Day Return Window', 'Return', 'Buyer',
     'Defective goods may be returned within 30 days of delivery with prior written authorisation.',
     'No', 'Active');
