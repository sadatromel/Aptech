# Autoloader Implementation Guide

## What is an Autoloader?

An autoloader is a PHP feature that automatically loads class files when they are needed, eliminating the need for manual `require_once` or `include` statements.

## Why Use an Autoloader?

### 1. **Better Performance**
- Only loads files that are actually used
- Reduces memory usage by not loading unnecessary classes
- Faster page load times

### 2. **Cleaner Code**
- No long lists of include/require statements at the top of files
- Code is more readable and maintainable
- Less chance of missing includes

### 3. **Easier Maintenance**
- Add new classes without updating include lists
- Automatic dependency resolution
- Follows modern PHP standards (PSR-4)

### 4. **Better Organization**
- Classes can be organized in directories and namespaces
- Clear structure for your application
- Easier to find and modify classes

## How It Works

### Before Autoloader (Traditional Approach):
```php
<?php
// At the top of every file
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Auth.php';
require_once 'classes/Logger.php';

// Your code
$user = new User();
$db = new Database();
$auth = new Auth();
```

### After Autoloader (Modern Approach):
```php
<?php
// Just include the autoloader once at the beginning
require_once __DIR__ . '/config/autoloader.php';

// Your code - classes are loaded automatically!
$user = new User();
$db = new Database();
$auth = new Auth();
```

## Implementation Steps

### 1. Create the Autoloader
The autoloader is already created at `config/autoloader.php`

### 2. Organize Your Classes
Place your classes in the `classes/` directory with proper structure:
```
classes/
├── Database.php
├── User.php
├── Auth.php
└── modules/
    ├── Merchandising/
    │   └── BOM.php
    └── Production/
        └── Plan.php
```

### 3. Use Namespaces (Optional but Recommended)
```php
<?php
namespace Modules\Merchandising;

class BOM {
    // Class implementation
}
```

### 4. Load the Autoloader
Add this line to your main entry point (index.php):
```php
require_once __DIR__ . '/config/autoloader.php';
```

## Example Usage

### Creating a New Class:
1. Create file: `classes/ReportGenerator.php`
2. Add class:
```php
<?php
class ReportGenerator {
    public function generateSalesReport($data) {
        // Generate report logic
    }
}
```
3. Use anywhere in your code:
```php
$report = new ReportGenerator(); // Automatically loaded!
```

### Using Namespaces:
1. Create file: `classes/Modules/Production/LineManager.php`
2. Add class:
```php
<?php
namespace Modules\Production;

class LineManager {
    public function allocateProduction($data) {
        // Allocation logic
    }
}
```
3. Use in your code:
```php
use Modules\Production\LineManager;
$manager = new LineManager(); // Automatically loaded!
```

## Benefits for Your ERP System

1. **Module Independence**: Each module can have its own classes
2. **Faster Loading**: Only loads classes for active modules
3. **Better Testing**: Easier to mock and test individual classes
4. **Future-Proof**: Follows PHP best practices
5. **IDE Support**: Better autocomplete and code navigation

## Migration Strategy

1. **Start Small**: Begin with new classes using the autoloader
2. **Gradual Migration**: Slowly convert existing procedural code to classes
3. **Keep Compatibility**: Old code continues to work during transition
4. **Test Thoroughly**: Ensure everything works after each migration

## Best Practices

1. **One Class Per File**: Each PHP file should contain only one class
2. **Consistent Naming**: Class names should match file names
3. **Use Namespaces**: Organize related classes in namespaces
4. **Follow PSR-4**: Use standard autoloader conventions
5. **Document Classes**: Add PHPDoc comments for better IDE support

## Example: Converting Existing Code

### Before (Procedural):
```php
<?php
// functions/user_functions.php
function get_user($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Using the function
require_once 'functions/user_functions.php';
$user = get_user(123);
```

### After (OOP with Autoloader):
```php
<?php
// classes/User.php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id) {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }
}

// Using the class
$user = new User();
$userData = $user->getById(123);
```

This approach makes your code more organized, testable, and maintainable!
