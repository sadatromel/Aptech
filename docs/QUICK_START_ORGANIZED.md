# Quick Start Guide - Organized ERP Structure

## 🚀 Getting Started

Your ERP system has been professionally organized. Here's how to use it:

### 1. Access the System
```
URL: http://localhost/Aptech/
```
This will take you to the login page.

### 2. Default Login
```
Email: admin@aptech.com
Password: admin123
```

### 3. Navigation URLs

#### Core Pages:
- Dashboard: `index.php?page=dashboard`
- Users: `index.php?page=users`
- System Library: `index.php?page=system_library`

#### Module Pages:
- Admin: `index.php?page=admin`
- Commercial: `index.php?page=commercial`
- Finance: `index.php?page=finance`
- HR: `index.php?page=hr`
- Merchandising: `index.php?page=merchandising`
- Production: `index.php?page=production`
- And more...

#### API Endpoints:
- Dashboard API: `index.php?page=api&action=dashboard`
- Banking API: `index.php?page=api&action=banking`

## 📁 Directory Structure

```
Aptech/
├── 📂 admin/          # User management, roles, audit logs
├── 📂 api/            # REST API endpoints
├── 📂 assets/         # CSS, JS, images
├── 📂 commercial/     # Customers, invoices
├── 📂 config/         # Database and path settings
├── 📂 core/           # Main application files
├── 📂 cutting/        # Cutting plans and bundles
├── 📂 data/           # Database files
├── 📂 docs/           # All documentation
├── 📂 finance/        # Accounts, ledger
├── 📂 hr/             # Employee management
├── 📂 includes/       # Shared components
├── 📂 library/        # Third-party libraries
├── 📂 merchandising/  # BOM, costing, samples
├── 📂 packing/        # Packing and shipping
├── 📂 procurement/    # Purchase orders
├── 📂 production/     # Production planning
├── 📂 sewing/         # Sewing operations
├── 📂 setup/          # Installation scripts
├── 📂 sql/            # Database schemas
├── 📂 store_inventory/ # Stock management
├── 📂 tests/          # Test and debug files
├── 📂 uploads/        # User uploaded files
├── 📂 vehicle_management/ # Transport management
├── 📂 washing/        # Washing processes
├── 📄 index.php       # Main router
├── 📄 README.md       # Project documentation
└── 📄 .gitignore      # Version control rules
```

## 🔧 Common Tasks

### Adding a New Module
1. Create directory: `new_module/`
2. Add your PHP files
3. Update `index.php` router to include your module
4. Add menu items in `includes/sidebar.php`

### Creating a New Page
1. Add PHP file to appropriate module directory
2. Include authentication check:
```php
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit();
}
?>
```
3. Include database connection:
```php
require_once __DIR__ . '/../config/database.php';
```

### Using the Database
```php
// Get connection
$conn = get_db_connection();

// Query with parameters
$result = db_query("SELECT * FROM users WHERE role = ?", ['admin']);

// Fetch results
$users = db_fetch_all($result);
```

### Adding Classes (OOP)
1. Create class in `classes/YourClass.php`
2. Use autoloader in your file:
```php
require_once __DIR__ . '/../config/autoloader.php';
$object = new YourClass();
```

## 🛠 Development Workflow

### 1. Making Changes
- Edit files in their respective directories
- Test changes through the router
- Commit changes with descriptive messages

### 2. Testing
- Use files in `tests/` directory
- Name test files: `test_yourfeature.php`
- Debug files: `debug_yourfeature.php`

### 3. Database Changes
- Create SQL script in `sql/` directory
- Use descriptive name: `add_feature_table.sql`
- Run through phpMyAdmin or setup scripts

## 📋 Important Files

### Configuration
- `config/database.php` - Database settings
- `config/paths.php` - Path constants
- `config/autoloader.php` - Class autoloader

### Core Files
- `index.php` - Main router
- `core/rmg_erp_dashboard.php` - RMG ERP Dashboard
- `core/login.php` - Login page
- `core/system_library.php` - System settings

### Documentation
- `README.md` - Project overview
- `docs/INSTALLATION.txt` - Installation guide
- `docs/AUTOLOADER_GUIDE.md` - OOP guide

## 🔒 Security Notes

1. Always validate user input
2. Use prepared statements (db_query function)
3. Check user permissions
4. Log important actions
5. Keep software updated

## 📞 Getting Help

1. Check `docs/` directory for detailed guides
2. Look at existing code for examples
3. Use test files to debug issues
4. Check error logs in PHP error log

## 🎯 Next Steps

1. Explore the dashboard
2. Check each module
3. Review the documentation
4. Customize for your needs
5. Add your own features

Happy coding! 🎉
