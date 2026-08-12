-- Add Districts & Sub-Districts permissions to the system

INSERT INTO permissions (module, permission_name, permission_code, description) VALUES
('master_library', 'View Districts', 'view_districts', 'Can view districts list and details'),
('master_library', 'Create Districts', 'create_districts', 'Can create new districts'),
('master_library', 'Edit Districts', 'edit_districts', 'Can edit existing districts'),
('master_library', 'Delete Districts', 'delete_districts', 'Can delete districts'),
('master_library', 'View Sub-Districts', 'view_sub_districts', 'Can view sub-districts list and details'),
('master_library', 'Create Sub-Districts', 'create_sub_districts', 'Can create new sub-districts'),
('master_library', 'Edit Sub-Districts', 'edit_sub_districts', 'Can edit existing sub-districts'),
('master_library', 'Delete Sub-Districts', 'delete_sub_districts', 'Can delete sub-districts');

-- Grant permissions to Admin role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 1, 0
FROM roles r, permissions p
WHERE r.role_name = 'Admin' 
AND p.permission_code IN ('view_districts', 'create_districts', 'edit_districts', 'delete_districts', 'view_sub_districts', 'create_sub_districts', 'edit_sub_districts', 'delete_sub_districts')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);

-- Grant permissions to Manager role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 0, 0
FROM roles r, permissions p
WHERE r.role_name = 'Manager' 
AND p.permission_code IN ('view_districts', 'create_districts', 'edit_districts', 'view_sub_districts', 'create_sub_districts', 'edit_sub_districts')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);
