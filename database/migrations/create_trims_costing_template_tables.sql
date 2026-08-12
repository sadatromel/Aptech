-- Create Trims Costing Template Tables
-- This migration creates the necessary tables for the Trims Costing Template module

-- Create main template table
CREATE TABLE IF NOT EXISTS `trims_costing_templates` (
    `template_id` int(11) NOT NULL AUTO_INCREMENT,
    `template_name` varchar(255) NOT NULL,
    `related_buyers` int(11) NOT NULL,
    `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`template_id`),
    KEY `idx_related_buyers` (`related_buyers`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_trims_template_buyer` FOREIGN KEY (`related_buyers`) REFERENCES `buyers` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create template items table
CREATE TABLE IF NOT EXISTS `trims_costing_template_items` (
    `item_id` int(11) NOT NULL AUTO_INCREMENT,
    `template_id` int(11) NOT NULL,
    `user_code` varchar(100) DEFAULT NULL,
    `trims_group` varchar(100) DEFAULT NULL,
    `item_description` text DEFAULT NULL,
    `consumption_uom` varchar(50) DEFAULT 'Pcs',
    `brand_supplier_ref` varchar(255) DEFAULT NULL,
    `consumption_per_garment` decimal(10,4) DEFAULT 0.0000,
    `excess_percentage` decimal(5,2) DEFAULT 0.00,
    `total_consumption` decimal(10,4) DEFAULT 0.0000,
    `purchase_rate` decimal(10,4) DEFAULT 0.0000,
    `amount` decimal(10,4) DEFAULT 0.0000,
    `approval_required` enum('No','Yes') NOT NULL DEFAULT 'No',
    `supplier` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`item_id`),
    KEY `idx_template_id` (`template_id`),
    KEY `idx_trims_group` (`trims_group`),
    KEY `idx_supplier` (`supplier`),
    CONSTRAINT `fk_trims_item_template` FOREIGN KEY (`template_id`) REFERENCES `trims_costing_templates` (`template_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data for testing
-- Note: Make sure buyers table exists and has buyer_id 1 and 2
INSERT INTO `trims_costing_templates` (`template_name`, `related_buyers`, `status`) VALUES
('Basic Shirt Template', 1, 'Active'),
('Pants Template', 1, 'Active'),
('Jacket Template', 2, 'Active');

INSERT INTO `trims_costing_template_items` (`template_id`, `user_code`, `trims_group`, `item_description`, `consumption_uom`, `brand_supplier_ref`, `consumption_per_garment`, `excess_percentage`, `total_consumption`, `purchase_rate`, `amount`, `approval_required`, `supplier`) VALUES
(1, 'BTN001', 'Buttons', 'Plastic Button 15mm', 'Pcs', 'SUP001', 5.0000, 5.00, 5.2500, 0.5000, 2.6250, 'No', NULL),
(1, 'ZIP001', 'Zippers', 'Metal Zipper 20cm', 'Pcs', 'SUP002', 1.0000, 3.00, 1.0300, 2.0000, 2.0600, 'Yes', NULL),
(2, 'BTN002', 'Buttons', 'Metal Button 20mm', 'Pcs', 'SUP001', 8.0000, 5.00, 8.4000, 1.2000, 10.0800, 'No', NULL),
(3, 'ZIP003', 'Zippers', 'Nylon Zipper 30cm', 'Pcs', 'SUP003', 2.0000, 3.00, 2.0600, 1.5000, 3.0900, 'Yes', NULL);
