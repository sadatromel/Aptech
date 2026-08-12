-- Insert permissions for Employee Designations
INSERT INTO permissions (module, permission_name, permission_code, description, category) VALUES
('master_library', 'View Employee Designations', 'view_employee_designations', 'Can view employee designations list and details', 'view'),
('master_library', 'Create Employee Designations', 'create_employee_designations', 'Can create new employee designations', 'view'),
('master_library', 'Edit Employee Designations', 'edit_employee_designations', 'Can edit existing employee designations', 'view'),
('master_library', 'Delete Employee Designations', 'delete_employee_designations', 'Can delete employee designations', 'view');

-- Grant permissions to default roles (System Administrator: 1, Supper Admin: 2, Manager: 3)
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'view_employee_designations', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'create_employee_designations', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'edit_employee_designations', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'delete_employee_designations', 1, 1, 1, 1, 1 FROM roles r;
