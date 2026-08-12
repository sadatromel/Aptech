-- Add Unit of Measure (UOM) permissions to the system
-- These permissions will be available in the Permission Console

INSERT INTO permissions (module, permission_name, permission_code, description) VALUES
('master_library', 'View UOMs', 'view_uoms', 'Can view unit of measure list and details'),
('master_library', 'Create UOMs', 'create_uoms', 'Can create new units of measure'),
('master_library', 'Edit UOMs', 'edit_uoms', 'Can edit existing units of measure'),
('master_library', 'Delete UOMs', 'delete_uoms', 'Can delete units of measure');

-- Grant permissions to admin role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 1, 0
FROM roles r, permissions p
WHERE r.role_name = 'Admin' 
AND p.permission_code IN ('view_uoms', 'create_uoms', 'edit_uoms', 'delete_uoms')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);

-- Grant permissions to manager role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 0, 0
FROM roles r, permissions p
WHERE r.role_name = 'Manager' 
AND p.permission_code IN ('view_uoms', 'create_uoms', 'edit_uoms')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);
