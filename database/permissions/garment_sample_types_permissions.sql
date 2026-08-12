-- Add Garment Sample Types permissions to the system
-- These permissions will be available in the Permission Console

INSERT INTO permissions (module, permission_name, permission_code, description) VALUES
('master_library', 'View Garment Sample Types', 'view_garment_sample_types', 'Can view garment sample types list and details'),
('master_library', 'Create Garment Sample Types', 'create_garment_sample_types', 'Can create new garment sample types'),
('master_library', 'Edit Garment Sample Types', 'edit_garment_sample_types', 'Can edit existing garment sample types'),
('master_library', 'Delete Garment Sample Types', 'delete_garment_sample_types', 'Can delete garment sample types');

-- Grant permissions to admin role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 1, 0
FROM roles r, permissions p
WHERE r.role_name = 'Admin' 
AND p.permission_code IN ('view_garment_sample_types', 'create_garment_sample_types', 'edit_garment_sample_types', 'delete_garment_sample_types')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);

-- Grant permissions to manager role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 0, 0
FROM roles r, permissions p
WHERE r.role_name = 'Manager' 
AND p.permission_code IN ('view_garment_sample_types', 'create_garment_sample_types', 'edit_garment_sample_types')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);
