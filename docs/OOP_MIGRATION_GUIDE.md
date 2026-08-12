# OOP Migration Guide

## Overview
This guide will help you migrate your ERP system from procedural programming to Object-Oriented Programming (OOP) using the autoloader system.

## Why Migrate to OOP?

### Benefits:
1. **Better Organization**: Related code grouped into classes
2. **Reusability**: Classes can be reused across modules
3. **Easier Maintenance**: Changes isolated to specific classes
4. **Testability**: Easier to unit test individual components
5. **Scalability**: Easier to add new features
6. **Modern Standards**: Follows current PHP best practices

## Current OOP Implementation

### Core Classes Created:
- **Database** - Database connection and queries
- **Auth** - Authentication and session management
- **Config** - Configuration management
- **Logger** - System logging
- **Request** - HTTP request handling
- **User** - User management operations

### Example Implementation:
See `core/users_oop.php` for a complete OOP example of user management.

## Migration Strategy

### Phase 1: Foundation (✅ Complete)
- [x] Create autoloader
- [x] Create core classes
- [x] Implement example module

### Phase 2: Gradual Migration
1. Start with non-critical modules
2. Keep old versions running in parallel
3. Test thoroughly before switching
4. Document changes

### Phase 3: Full Migration
1. Convert all modules to OOP
2. Remove old procedural code
3. Implement unit tests
4. Optimize performance

## Step-by-Step Migration

### 1. Create a Class for Your Module

```php
<?php
// classes/YourModule.php
class YourModule {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM your_table");
    }
    
    public function getById($id) {
        return $this->db->fetch("SELECT * FROM your_table WHERE id = ?", [$id]);
    }
    
    public function create($data) {
        // Validation logic
        $this->validate($data);
        
        // Insert logic
        $sql = "INSERT INTO your_table (field1, field2) VALUES (?, ?)";
        $this->db->query($sql, [$data['field1'], $data['field2']]);
        
        return $this->db->lastInsertId();
    }
    
    private function validate($data) {
        if (empty($data['field1'])) {
            throw new Exception("Field 1 is required");
        }
    }
}
```

### 2. Create OOP Version of Your Page

```php
<?php
// core/your_module_oop.php

// Load autoloader
require_once __DIR__ . '/../config/autoloader.php';

// Initialize classes
$auth = new Auth();
$request = new Request();
$module = new YourModule();

// Require login
$auth->requireLogin();

// Handle form submissions
if ($request->post('action') === 'create') {
    try {
        $data = $request->post();
        $id = $module->create($data);
        $message = "Created successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data
$items = $module->getAll();

// Include your HTML template
require_once __DIR__ . '/../templates/your_module_template.php';
```

### 3. Update Router

Add your OOP route to `index.php`:

```php
case 'your_module_oop':
    require_once CORE_PATH . '/your_module_oop.php';
    break;
```

### 4. Test Thoroughly

1. Test all functionality
2. Check error handling
3. Verify performance
4. Test edge cases

## Best Practices

### 1. Class Design
- **Single Responsibility**: Each class should have one purpose
- **Dependency Injection**: Pass dependencies through constructor
- **Private Methods**: Keep internal logic private
- **Exception Handling**: Use exceptions for error handling

### 2. Database Operations
```php
// Good - Uses prepared statements
public function getUsersByRole($role) {
    return $this->db->fetchAll(
        "SELECT * FROM users WHERE role = ?", 
        [$role]
    );
}

// Bad - Vulnerable to SQL injection
public function getUsersByRole($role) {
    return $this->db->fetchAll(
        "SELECT * FROM users WHERE role = '$role'"
    );
}
```

### 3. Input Validation
```php
// Use the Request class for validation
$errors = $request->validate([
    'email' => ['required' => true, 'email' => true],
    'name' => ['required' => true, 'min' => 3],
    'age' => ['numeric' => true, 'min' => 18]
]);

if (!empty($errors)) {
    throw new Exception(implode(', ', $errors));
}
```

### 4. Logging
```php
// Log important operations
Logger::info("User created", ['user_id' => $userId]);
Logger::error("Database error", ['error' => $e->getMessage()]);
```

## Migration Checklist

### For Each Module:
- [ ] Create class in `classes/` directory
- [ ] Move business logic to class methods
- [ ] Create OOP version of page
- [ ] Add route to `index.php`
- [ ] Test all functionality
- [ ] Update documentation
- [ ] Remove old procedural code (after testing)

### Security Considerations:
- [ ] All database queries use prepared statements
- [ ] Input validation implemented
- [ ] Authentication checks in place
- [ ] CSRF protection used
- [ ] Error handling doesn't expose sensitive info

### Performance Considerations:
- [ ] Database queries optimized
- [ ] No unnecessary queries in loops
- [ ] Caching implemented where needed
- [ ] Large datasets use pagination

## Example: Full Module Migration

### Before (Procedural):
```php
<?php
// old_module.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
$conn = get_db_connection();

if ($_POST['action'] === 'create') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    
    $sql = "INSERT INTO contacts (name, email) VALUES ('$name', '$email')";
    $conn->query($sql);
}

$contacts = $conn->query("SELECT * FROM contacts");
?>
```

### After (OOP):
```php
<?php
// Contact.php (class)
class Contact {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM contacts");
    }
    
    public function create($data) {
        $this->validate($data);
        $sql = "INSERT INTO contacts (name, email) VALUES (?, ?)";
        $this->db->query($sql, [$data['name'], $data['email']]);
        return $this->db->lastInsertId();
    }
    
    private function validate($data) {
        if (empty($data['name'])) throw new Exception("Name required");
        if (empty($data['email'])) throw new Exception("Email required");
    }
}

// contacts_oop.php (page)
require_once __DIR__ . '/../config/autoloader.php';

$auth = new Auth();
$request = new Request();
$contact = new Contact();

$auth->requireLogin();

if ($request->post('action') === 'create') {
    try {
        $contact->create($request->post());
        $message = "Contact created!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$contacts = $contact->getAll();
```

## Next Steps

1. **Start Small**: Begin with a simple module
2. **Learn Patterns**: Study the existing OOP examples
3. **Refactor Gradually**: Don't try to convert everything at once
4. **Write Tests**: Test your classes as you create them
5. **Get Feedback**: Have others review your code

## Resources

- [PHP OOP Documentation](https://www.php.net/manual/en/language.oop5.php)
- [PSR-4 Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [Design Patterns in PHP](https://refactoring.guru/design-patterns/php)

## Support

If you need help during migration:
1. Check the existing OOP examples
2. Review the autoloader documentation
3. Look at the Logger for debugging
4. Test incrementally

Remember: The goal is gradual, safe migration. Keep the old code running until the new OOP version is fully tested!
