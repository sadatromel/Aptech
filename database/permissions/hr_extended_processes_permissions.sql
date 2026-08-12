-- HR Extended Processes Permissions
INSERT INTO permissions (module, permission_name, permission_code, description) VALUES
('hr', 'Bonus Re-process', 'view_bonus_reprocess', 'Can re-process bonus calculations'),
('hr', 'Cost Center Wise Salary Process', 'view_cost_center_salary', 'Can process salary by cost center'),
('hr', 'Earn Leave Monthly Process', 'view_earn_leave_monthly', 'Can process monthly earn leave payouts'),
('hr', 'Earn Leave Yearly Process', 'view_earn_leave_yearly', 'Can process yearly earn leave payouts'),
('hr', 'Employee Promotion Bulk', 'view_promotion_bulk', 'Can process bulk employee promotions'),
('hr', 'Final Settlement Process', 'view_final_settlement', 'Can process employee final settlement'),
('hr', 'Fraction Month Salary Re-process', 'view_fraction_salary_reprocess', 'Can re-process fraction month salary'),
('hr', 'Holiday Allowance Process', 'view_holiday_allowance', 'Can process holiday allowance'),
('hr', 'Increment Entry', 'view_increment_entry', 'Can enter employee increment'),
('hr', 'Increment Entry Bulk', 'view_increment_bulk', 'Can process bulk employee increments'),
('hr', 'Lay Off Employee', 'view_lay_off_employee', 'Can manage employee layoffs'),
('hr', 'Monthly Bill Process', 'view_monthly_bill_process', 'Can process monthly bills'),
('hr', 'Night Allowance Process', 'view_night_allowance', 'Can process night allowance'),
('hr', 'Promotion Entry', 'view_promotion_entry', 'Can enter employee promotion'),
('hr', 'Salary Adjustment Entry', 'view_salary_adjustment', 'Can enter salary adjustments'),
('hr', 'Salary Period Lock', 'view_salary_period_lock', 'Can lock/unlock salary periods'),
('hr', 'Tiffin Bill Process', 'view_tiffin_bill', 'Can process tiffin bills');

-- Grant permissions to Admin role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'hr', p.permission_code, 1, 1, 1, 1, 1
FROM roles r, permissions p
WHERE r.role_name = 'Admin' 
AND p.permission_code IN (
  'view_bonus_reprocess', 'view_cost_center_salary', 'view_earn_leave_monthly', 'view_earn_leave_yearly',
  'view_promotion_bulk', 'view_final_settlement', 'view_fraction_salary_reprocess', 'view_holiday_allowance',
  'view_increment_entry', 'view_increment_bulk', 'view_lay_off_employee', 'view_monthly_bill_process',
  'view_night_allowance', 'view_promotion_entry', 'view_salary_adjustment', 'view_salary_period_lock', 'view_tiffin_bill'
)
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);
