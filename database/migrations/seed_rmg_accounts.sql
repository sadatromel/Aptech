-- BD RMG Sector Standard Chart of Accounts Seeder
-- This script inserts the standard chart of accounts into the chart_of_accounts table.

-- Disable foreign key checks if necessary
-- SET FOREIGN_KEY_CHECKS=0;

-- Ensure table is empty or just insert if not exists (assuming table has a unique constraint on account_code)
-- Alternatively, use INSERT IGNORE or ON DUPLICATE KEY UPDATE.
-- Using simple INSERT for now.

INSERT INTO chart_of_accounts (account_code, account_name, account_type, status, created_by, created_at, updated_at) VALUES
-- ASSETS
('1000', 'Cash and Cash Equivalents', 'Asset', 'active', 1, NOW(), NOW()),
('1010', 'Cash in Hand', 'Current Asset', 'active', 1, NOW(), NOW()),
('1020', 'Bank - Main Operating Account', 'Current Asset', 'active', 1, NOW(), NOW()),
('1021', 'Bank - ERQ (Export Retention Quota)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1022', 'Bank - Margin Account (L/C)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1100', 'Accounts Receivable - Export Buyers', 'Current Asset', 'active', 1, NOW(), NOW()),
('1110', 'Accounts Receivable - Local Sales', 'Current Asset', 'active', 1, NOW(), NOW()),
('1200', 'Inventory - Raw Materials (Fabric)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1210', 'Inventory - Raw Materials (Accessories & Trims)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1220', 'Inventory - Work in Progress (WIP)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1230', 'Inventory - Finished Goods (Garments)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1300', 'Advances, Deposits & Prepayments', 'Current Asset', 'active', 1, NOW(), NOW()),
('1310', 'Advance to Suppliers', 'Current Asset', 'active', 1, NOW(), NOW()),
('1320', 'Advance Income Tax (AIT)', 'Current Asset', 'active', 1, NOW(), NOW()),
('1400', 'Property, Plant & Equipment', 'Fixed Asset', 'active', 1, NOW(), NOW()),
('1410', 'Plant & Machinery (Sewing, Cutting, Washing)', 'Fixed Asset', 'active', 1, NOW(), NOW()),
('1420', 'Factory Building', 'Fixed Asset', 'active', 1, NOW(), NOW()),

-- LIABILITIES
('2000', 'Accounts Payable - Suppliers (Local)', 'Current Liability', 'active', 1, NOW(), NOW()),
('2010', 'Accounts Payable - Suppliers (Foreign - Back to Back L/C)', 'Current Liability', 'active', 1, NOW(), NOW()),
('2100', 'Short Term Bank Loans (PC, CC)', 'Current Liability', 'active', 1, NOW(), NOW()),
('2200', 'Accrued Wages & Salaries', 'Current Liability', 'active', 1, NOW(), NOW()),
('2210', 'Provision for Festival Bonus', 'Current Liability', 'active', 1, NOW(), NOW()),
('2220', 'Workers Profit Participation Fund (WPPF) Payable', 'Current Liability', 'active', 1, NOW(), NOW()),
('2300', 'Long Term Bank Loans (Term Loan)', 'Liability', 'active', 1, NOW(), NOW()),

-- EQUITY
('3000', 'Share Capital', 'Equity', 'active', 1, NOW(), NOW()),
('3100', 'Retained Earnings', 'Equity', 'active', 1, NOW(), NOW()),

-- INCOME / REVENUE
('4000', 'Revenue - Export Sales (FOB/CM/C&F)', 'Income', 'active', 1, NOW(), NOW()),
('4100', 'Revenue - Local Sales / Sub-contracting', 'Income', 'active', 1, NOW(), NOW()),
('4200', 'Cash Incentive on Export', 'Income', 'active', 1, NOW(), NOW()),
('4300', 'Wastage / Scrap Sales', 'Income', 'active', 1, NOW(), NOW()),

-- EXPENSES (Direct Costs)
('5000', 'Direct Material Consumed - Fabric', 'Expense', 'active', 1, NOW(), NOW()),
('5010', 'Direct Material Consumed - Accessories & Trims', 'Expense', 'active', 1, NOW(), NOW()),
('5100', 'Direct Labor - Sewing Operators', 'Expense', 'active', 1, NOW(), NOW()),
('5110', 'Direct Labor - Cutting Operators', 'Expense', 'active', 1, NOW(), NOW()),
('5120', 'Direct Labor - Washing Operators', 'Expense', 'active', 1, NOW(), NOW()),
('5130', 'Direct Labor - Packing & Finishing', 'Expense', 'active', 1, NOW(), NOW()),

-- EXPENSES (Factory Overheads)
('6000', 'Factory Rent', 'Expense', 'active', 1, NOW(), NOW()),
('6010', 'Factory Electricity & Utility', 'Expense', 'active', 1, NOW(), NOW()),
('6020', 'Generator Fuel (Diesel/Gas)', 'Expense', 'active', 1, NOW(), NOW()),
('6030', 'Factory Maintenance & Spare Parts', 'Expense', 'active', 1, NOW(), NOW()),
('6040', 'Depreciation - Plant & Machinery', 'Expense', 'active', 1, NOW(), NOW()),
('6050', 'Washing Chemicals', 'Expense', 'active', 1, NOW(), NOW()),

-- EXPENSES (Admin & Selling Overheads)
('7000', 'Salaries & Allowances (Admin)', 'Expense', 'active', 1, NOW(), NOW()),
('7010', 'Directors Remuneration', 'Expense', 'active', 1, NOW(), NOW()),
('7020', 'Office Electricity & Utility', 'Expense', 'active', 1, NOW(), NOW()),
('7030', 'Bank Charges & Commission (L/C, Export)', 'Expense', 'active', 1, NOW(), NOW()),
('7040', 'Freight & Forwarding Charges (Export)', 'Expense', 'active', 1, NOW(), NOW()),
('7050', 'Courier & Postage', 'Expense', 'active', 1, NOW(), NOW()),
('7060', 'Audit & Legal Fees', 'Expense', 'active', 1, NOW(), NOW()),
('7070', 'Entertainment & Guest House Expenses', 'Expense', 'active', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
