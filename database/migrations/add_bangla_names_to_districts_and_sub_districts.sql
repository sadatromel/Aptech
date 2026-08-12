-- Add Bangla name columns to districts and sub_districts tables
ALTER TABLE `districts` ADD COLUMN `district_name_bn` VARCHAR(150) DEFAULT NULL AFTER `district_name`;
ALTER TABLE `sub_districts` ADD COLUMN `sub_district_name_bn` VARCHAR(150) DEFAULT NULL AFTER `sub_district_name`;
