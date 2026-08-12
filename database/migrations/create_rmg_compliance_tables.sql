-- Database Migration for RMG Compliance Features (BLA 2006 compliance)

-- 1. Alter employees table to add worker_grade column if it does not exist
SET @dbname = DATABASE();
SET @tablename = "employees";
SET @columnname = "worker_grade";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE employees ADD COLUMN worker_grade INT NULL DEFAULT NULL AFTER position_id"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Create maternity_benefits table to track BLA 2006 maternity leaves & payments
CREATE TABLE IF NOT EXISTS maternity_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    expected_delivery_date DATE NOT NULL,
    leave_start_date DATE NOT NULL,
    leave_end_date DATE NOT NULL,
    avg_daily_wage DECIMAL(10,2) NOT NULL,
    total_benefit_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('Pending', 'Partially Paid', 'Fully Paid') DEFAULT 'Pending',
    payment1_amount DECIMAL(10,2) DEFAULT 0.00,
    payment1_date DATE DEFAULT NULL,
    payment2_amount DECIMAL(10,2) DEFAULT 0.00,
    payment2_date DATE DEFAULT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- 3. Populate default worker_grade values for active employees based on positions if not already set
UPDATE employees SET worker_grade = 1 WHERE position_id = 1 AND worker_grade IS NULL; -- Manager
UPDATE employees SET worker_grade = 2 WHERE position_id = 2 AND worker_grade IS NULL; -- Developer
UPDATE employees SET worker_grade = 3 WHERE position_id = 3 AND worker_grade IS NULL; -- Accountant
UPDATE employees SET worker_grade = 3 WHERE position_id = 4 AND worker_grade IS NULL; -- Operator
UPDATE employees SET worker_grade = 5 WHERE position_id = 5 AND worker_grade IS NULL; -- Driver
