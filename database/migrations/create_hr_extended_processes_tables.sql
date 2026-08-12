-- Module: HR & Payroll Extended Processes

-- 1. Salary Period Lock
CREATE TABLE IF NOT EXISTS `payroll_period_locks` (
  `lock_id` INT AUTO_INCREMENT PRIMARY KEY,
  `year_month` VARCHAR(7) NOT NULL,
  `department_id` INT DEFAULT NULL,
  `lock_status` ENUM('unlocked', 'locked') DEFAULT 'locked',
  `locked_by` INT DEFAULT NULL,
  `locked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `remarks` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Increments (Single & Bulk)
CREATE TABLE IF NOT EXISTS `employee_increments` (
  `increment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `increment_type` ENUM('annual', 'special', 'performance') DEFAULT 'annual',
  `effective_date` DATE NOT NULL,
  `previous_gross` DECIMAL(12,2) NOT NULL,
  `increment_amount` DECIMAL(12,2) NOT NULL,
  `new_gross` DECIMAL(12,2) NOT NULL,
  `approved_by` INT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'processed') DEFAULT 'approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Promotions (Single & Bulk)
CREATE TABLE IF NOT EXISTS `employee_promotions` (
  `promotion_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `effective_date` DATE NOT NULL,
  `previous_designation` VARCHAR(100) NOT NULL,
  `new_designation` VARCHAR(100) NOT NULL,
  `previous_department` VARCHAR(100) DEFAULT NULL,
  `new_department` VARCHAR(100) DEFAULT NULL,
  `salary_increment` DECIMAL(12,2) DEFAULT 0.00,
  `remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Layoffs
CREATE TABLE IF NOT EXISTS `employee_layoffs` (
  `layoff_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `layoff_reason` VARCHAR(255) NOT NULL,
  `compensation_percentage` DECIMAL(5,2) DEFAULT 50.00,
  `status` ENUM('active', 'recalled', 'terminated') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Salary Adjustments
CREATE TABLE IF NOT EXISTS `salary_adjustments` (
  `adjustment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `year_month` VARCHAR(7) NOT NULL,
  `adjustment_type` ENUM('addition', 'deduction') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'applied') DEFAULT 'applied',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Earn Leave Payouts (Monthly & Yearly)
CREATE TABLE IF NOT EXISTS `earn_leave_payouts` (
  `payout_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `payout_type` ENUM('monthly', 'yearly') NOT NULL,
  `year_month` VARCHAR(7) NOT NULL,
  `leave_days` DECIMAL(5,1) NOT NULL,
  `daily_rate` DECIMAL(10,2) NOT NULL,
  `total_payout` DECIMAL(12,2) NOT NULL,
  `status` ENUM('processed', 'paid') DEFAULT 'processed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Special Allowances & Bills
CREATE TABLE IF NOT EXISTS `special_allowance_processes` (
  `process_id` INT AUTO_INCREMENT PRIMARY KEY,
  `process_type` ENUM('night_allowance', 'tiffin_bill', 'holiday_allowance', 'monthly_bill') NOT NULL,
  `year_month` VARCHAR(7) NOT NULL,
  `total_employees` INT DEFAULT 0,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `processed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Final Settlements
CREATE TABLE IF NOT EXISTS `final_settlements` (
  `settlement_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `resignation_date` DATE NOT NULL,
  `release_date` DATE NOT NULL,
  `service_length_years` DECIMAL(4,1) DEFAULT 0.0,
  `encashment_amount` DECIMAL(12,2) DEFAULT 0.00,
  `gratuity_amount` DECIMAL(12,2) DEFAULT 0.00,
  `notice_pay` DECIMAL(12,2) DEFAULT 0.00,
  `deductions` DECIMAL(12,2) DEFAULT 0.00,
  `net_settlement` DECIMAL(12,2) NOT NULL,
  `status` ENUM('draft', 'approved', 'disbursed') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
