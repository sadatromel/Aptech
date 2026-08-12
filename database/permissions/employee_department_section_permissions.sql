-- Insert permissions for Employee Departments
INSERT INTO permissions (module, permission_name, permission_code, description, category) VALUES
('master_library', 'View Employee Departments', 'view_employee_departments', 'Can view employee departments list and details', 'view'),
('master_library', 'Create Employee Departments', 'create_employee_departments', 'Can create new employee departments', 'view'),
('master_library', 'Edit Employee Departments', 'edit_employee_departments', 'Can edit existing employee departments', 'view'),
('master_library', 'Delete Employee Departments', 'delete_employee_departments', 'Can delete employee departments', 'view');

-- Insert permissions for Employee Sections
INSERT INTO permissions (module, permission_name, permission_code, description, category) VALUES
('master_library', 'View Employee Sections', 'view_employee_sections', 'Can view employee sections list and details', 'view'),
('master_library', 'Create Employee Sections', 'create_employee_sections', 'Can create new employee sections', 'view'),
('master_library', 'Edit Employee Sections', 'edit_employee_sections', 'Can edit existing employee sections', 'view'),
('master_library', 'Delete Employee Sections', 'delete_employee_sections', 'Can delete employee sections', 'view');

-- Grant permissions to default roles (System Administrator: 1, Supper Admin: 2, Manager: 3)
-- For Employee Departments
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'view_employee_departments', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'create_employee_departments', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'edit_employee_departments', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'delete_employee_departments', 1, 1, 1, 1, 1 FROM roles r;

-- For Employee Sections
INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'view_employee_sections', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'create_employee_sections', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'edit_employee_sections', 1, 1, 1, 1, 1 FROM roles r;

INSERT INTO role_permissions (role_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.role_id, 'master_library', 'delete_employee_sections', 1, 1, 1, 1, 1 FROM roles r;
