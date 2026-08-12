-- Add Trims Costing Template permissions to the system
-- These permissions will be available in the Permission Console

INSERT INTO permissions (module, permission_name, permission_code, description) VALUES
('master_library', 'View Trims Costing Template', 'view_trims_costing_template', 'Can view trims costing template list and details'),
('master_library', 'Create Trims Costing Template', 'create_trims_costing_template', 'Can create new trims costing templates'),
('master_library', 'Edit Trims Costing Template', 'edit_trims_costing_template', 'Can edit existing trims costing templates'),
('master_library', 'Delete Trims Costing Template', 'delete_trims_costing_template', 'Can delete trims costing templates'),
('master_library', 'Copy Trims Costing Template', 'copy_trims_costing_template', 'Can copy existing trims costing templates');

-- Grant permissions to admin role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 1, 0
FROM roles r, permissions p
WHERE r.role_name = 'Admin' 
AND p.permission_code IN ('view_trims_costing_template', 'create_trims_costing_template', 'edit_trims_costing_template', 'delete_trims_costing_template', 'copy_trims_costing_template')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);

-- Grant permissions to manager role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 0, 0
FROM roles r, permissions p
WHERE r.role_name = 'Manager' 
AND p.permission_code IN ('view_trims_costing_template', 'create_trims_costing_template', 'edit_trims_costing_template')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);
