-- Permissions for HR Attendance & Roster Operations

INSERT INTO `permissions` (`module`, `permission_name`, `permission_code`, `description`) VALUES
('hr', 'Bulk Movement Entry', 'view_movement_bulk', 'Can enter bulk employee outdoor duty movements'),
('hr', 'Date Attendance Transfer', 'view_attendance_transfer', 'Can transfer attendance punches between dates'),
('hr', 'Define Holiday', 'view_define_holiday', 'Can define factory and government holidays'),
('hr', 'Define Weekend', 'view_define_weekend', 'Can configure weekly off days'),
('hr', 'Duty Roster Re-process', 'view_roster_reprocess', 'Can re-process duty rosters and shifts'),
('hr', 'Holiday Remove', 'view_holiday_remove', 'Can revoke or remove declared holidays'),
('hr', 'Manual Attendance Entry', 'view_manual_attendance', 'Can enter manual attendance and missed punches'),
('hr', 'Movement Register', 'view_movement_register', 'Can view outdoor movement register and logs'),
('hr', 'OT Requisition', 'view_ot_requisition', 'Can submit and approve overtime requisitions')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- Assign permissions to Admin roles
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `page_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_approve`)
SELECT r.role_id, 'hr', p.permission_code, 1, 1, 1, 1, 1
FROM roles r, permissions p
WHERE p.permission_code IN ('view_movement_bulk', 'view_attendance_transfer', 'view_define_holiday', 'view_define_weekend', 'view_roster_reprocess', 'view_holiday_remove', 'view_manual_attendance', 'view_movement_register', 'view_ot_requisition');
