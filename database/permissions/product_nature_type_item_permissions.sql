-- Add Product Nature, Product Type, and Garments Item permissions to the system
-- These permissions will be available in the Permission Console

INSERT INTO permissions (module, permission_name, permission_code, description) VALUES
('master_library', 'View Product Natures', 'view_product_natures', 'Can view product natures list and details'),
('master_library', 'Create Product Natures', 'create_product_natures', 'Can create new product natures'),
('master_library', 'Edit Product Natures', 'edit_product_natures', 'Can edit existing product natures'),
('master_library', 'Delete Product Natures', 'delete_product_natures', 'Can delete product natures'),

('master_library', 'View Product Types', 'view_product_types', 'Can view product types list and details'),
('master_library', 'Create Product Types', 'create_product_types', 'Can create new product types'),
('master_library', 'Edit Product Types', 'edit_product_types', 'Can edit existing product types'),
('master_library', 'Delete Product Types', 'delete_product_types', 'Can delete product types'),

('master_library', 'View Garments Items', 'view_garments_items', 'Can view garments items list and details'),
('master_library', 'Create Garments Items', 'create_garments_items', 'Can create new garments items'),
('master_library', 'Edit Garments Items', 'edit_garments_items', 'Can edit existing garments items'),
('master_library', 'Delete Garments Items', 'delete_garments_items', 'Can delete garments items');

-- Grant permissions to admin role by default
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 1, 0
FROM roles r, permissions p
WHERE r.role_name = 'Admin' 
AND p.permission_code IN (
    'view_product_natures', 'create_product_natures', 'edit_product_natures', 'delete_product_natures',
    'view_product_types', 'create_product_types', 'edit_product_types', 'delete_product_types',
    'view_garments_items', 'create_garments_items', 'edit_garments_items', 'delete_garments_items'
)
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);

-- Grant permissions to manager role by default (no delete)
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', p.permission_code, 1, 1, 1, 0, 0
FROM roles r, permissions p
WHERE r.role_name = 'Manager' 
AND p.permission_code IN (
    'view_product_natures', 'create_product_natures', 'edit_product_natures',
    'view_product_types', 'create_product_types', 'edit_product_types',
    'view_garments_items', 'create_garments_items', 'edit_garments_items'
)
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.page_name = p.permission_code
);
