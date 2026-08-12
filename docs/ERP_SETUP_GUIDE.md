# Complete ERP Database Setup Guide

## Overview
This guide provides step-by-step instructions for setting up the complete RMG/Manufacturing ERP system with all database tables, sample data, and verification tools.

## What's Included

### 1. **Database Schema Files**
- **create_complete_erp_schema.sql** - SQL file with 30+ normalized tables
- **setup_complete_erp_tables.php** - Visual PHP setup wizard with progress tracking
- **verify_erp_schema.php** - Comprehensive schema verification and audit tool
- **populate_erp_data.php** - Sample data insertion for testing

### 2. **Database Tables Organized by Module**

#### Core Organization (5 tables)
- `factories` - Factory/plant management
- `roles` - User roles and permissions
- `departments` - Department structure
- `users` - User accounts and authentication
- `activity_logs` - User activity tracking

#### Merchandising & Buying (3 tables)
- `buyer_profiles` - Buyer profiles and contact info
- `styles` - Style/product definitions
- `style_skus` - Style variants by size/color

#### Inventory & Procurement (8 tables)
- `vendors` - Supplier profiles
- `items` - Raw materials and finished goods
- `warehouses` - Storage locations
- `stock` - Inventory levels
- `purchase_orders` - PO headers
- `po_lines` - PO line items
- `grn` - Goods Receipt Notes
- `grn_lines` - GRN line items

#### Production & Manufacturing (5 tables)
- `bom` - Bill of Materials
- `production_orders` - Production order headers
- `production_lines` - Production line setup
- `workstations` - Individual workstation setup
- `qc_checks` - Quality control inspections
- `defects` - Defect tracking

#### HR & Payroll (2 tables)
- `attendance_records` - Daily attendance
- `shift_assignments` - Shift allocation

#### Finance & Accounting (3 tables)
- `chart_of_accounts` - Chart of accounts
- `journal_entries` - Journal entry headers
- `journal_lines` - Journal entry line items

#### Transport & Logistics (4 tables)
- `transport_routes` - Transportation routes
- `vehicles` - Vehicle management (already exists)
- `routes` - Delivery routes
- `employee_seat_allotment` - Seat allocation (already exists)

#### System & Audit (3 tables)
- `audit_logs` - Comprehensive audit trail
- `cost_centers` - Cost center management
- `system_settings` - System configuration

---

## Setup Instructions

### Step 1: Create Database Tables
**Option A: Using PHP Wizard (Recommended)**
1. Open your browser and navigate to: `http://localhost/Aptech/setup_complete_erp_tables.php`
2. Click the "Create Tables" button
3. The wizard will create all tables and insert default data
4. View the progress report

**Option B: Using SQL File Directly**
```bash
mysql -u root -p aptech < create_complete_erp_schema.sql
```

### Step 2: Verify Schema Creation
1. Navigate to: `http://localhost/Aptech/verify_erp_schema.php`
2. Review the verification report showing:
   - Number of tables created
   - Total records and storage size
   - Foreign key relationships
   - Index summary
   - Module coverage percentage

### Step 3: Insert Sample Data
1. Navigate to: `http://localhost/Aptech/populate_erp_data.php`
2. Review the data insertion report showing:
   - Test users created
   - Departments set up
   - Buyers (buyer_profiles), styles, vendors, items added
   - Warehouses and production lines created
   - Total records inserted

### Step 4: Verify Data Integrity
Run these checks:
```sql
-- Check table counts
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'aptech';

-- Verify foreign keys
SELECT COUNT(*) FROM information_schema.referential_constraints 
WHERE constraint_schema = 'aptech';

-- Check sample data
SELECT COUNT(*) FROM buyer_profiles;
SELECT COUNT(*) FROM styles;
SELECT COUNT(*) FROM vendors;
SELECT COUNT(*) FROM items;
```

---

## Database Features

### Design Principles
- ✅ Normalized schema (3NF) to eliminate redundancy
- ✅ Proper foreign key constraints for data integrity
- ✅ Audit timestamps (created_at, updated_at) on all tables
- ✅ Status enums for workflow management
- ✅ Indexes for query performance
- ✅ UTF8MB4 character encoding for international support
- ✅ Prepared statements for SQL injection protection

### Key Features by Table

**factories**
- Multi-factory support for enterprise deployments
- Time zone and currency configuration per factory
- Status tracking (active/inactive/maintenance)

**styles**
- Complete product lifecycle management
- SKU variants by size and color
- Buyer-to-style tracking
- Approval workflow (design → approved → production → completed)

**production_orders**
- Links to styles for production
- Quantity and timeline tracking
- Status workflow (draft → approved → planned → in_production → completed)
- Priority levels (low/medium/high/urgent)

**vendors & purchase_orders**
- Multi-vendor support with performance rating
- Lead time tracking
- Payment terms management
- PO line-level tracking
- GRN (Goods Receipt) workflow

**qc_checks & defects**
- Multiple inspection types (inline, final, incoming, process)
- Defect severity tracking (minor/major/critical)
- Root cause analysis
- Corrective action tracking

**audit_logs**
- Comprehensive activity tracking
- User, timestamp, and IP logging
- Old vs. new value tracking for audits
- Action type categorization (CREATE/READ/UPDATE/DELETE/EXPORT/IMPORT)

---

## Testing the Setup

### 1. Verify All Tables Exist
```php
<?php
require_once 'db_connect.php';

$tables = $conn->query("
    SELECT TABLE_NAME FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'aptech'
")->fetch_all();

echo "Total Tables: " . count($tables);
?>
```

### 2. Test Sample Data
```php
<?php
require_once 'db_connect.php';

$query = "SELECT 
    (SELECT COUNT(*) FROM buyer_profiles) as buyers,
    (SELECT COUNT(*) FROM styles) as styles,
    (SELECT COUNT(*) FROM vendors) as vendors,
    (SELECT COUNT(*) FROM items) as items,
    (SELECT COUNT(*) FROM users) as users";

$result = $conn->query($query)->fetch_assoc();
print_r($result);
?>
```

### 3. Check Foreign Keys
```sql
SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'aptech';
```

---

## Next Steps After Setup

### 1. Create CRUD Interfaces
Recommended order:
1. `buyer_profiles.php` - Buyer management
2. `styles.php` - Style management
3. `vendors.php` - Vendor management
4. `items.php` - Inventory management
5. `production_orders.php` - Production order management

### 2. Update Dashboard
Modify `dashboard.php` to display:
- Total active production orders
- Inventory levels by warehouse
- Employee attendance summary
- Recent quality issues
- Finance overview

### 3. Create Reports
- Inventory reports
- Production status reports
- Quality summary reports
- Finance/payroll reports
- HR reports

### 4. Implement Workflows
- Approval workflows for POs
- Quality control gates
- Payroll processing
- Journal entry reconciliation

---

## Troubleshooting

### Issue: Foreign Key Constraint Errors
**Solution:** Check that parent records exist before inserting child records
```sql
-- Example: Check buyer before inserting style
SELECT * FROM buyer_profiles WHERE buyer_id = 1;
```

### Issue: Duplicate Key Errors
**Solution:** Tables use UNIQUE constraints on codes. Ensure unique codes:
```sql
-- Check for duplicates
SELECT item_code, COUNT(*) FROM items GROUP BY item_code HAVING COUNT(*) > 1;
```

### Issue: Character Encoding Problems
**Solution:** Ensure connection uses UTF8MB4:
```sql
SET NAMES utf8mb4;
SET CHARACTER_SET_CLIENT = utf8mb4;
SET CHARACTER_SET_CONNECTION = utf8mb4;
```

### Issue: Date Format Errors
**Solution:** Always use YYYY-MM-DD format for dates:
```php
// Correct format
$date = date('Y-m-d'); // 2024-01-15
```

---

## Key Database Statistics

| Metric | Value |
|--------|-------|
| Total Tables | 30+ |
| Total Columns | 300+ |
| Foreign Keys | 40+ |
| Indexes | 50+ |
| Storage (with sample data) | ~2 MB |
| Max supported records (per table) | 16 Million |

---

## Security Considerations

1. **User Permissions**
   - Create database user with limited privileges
   - Different users for development/staging/production
   - Regular password rotation

2. **Data Protection**
   - All user passwords hashed with SHA
   - Audit logs track all changes
   - Foreign key constraints prevent orphaned records

3. **Backup Strategy**
   ```bash
   # Daily backup
   mysqldump -u root -p aptech > backup_$(date +%Y%m%d).sql
   
   # Restore from backup
   mysql -u root -p aptech < backup_20240115.sql
   ```

---

## Support Files

- **db_connect.php** - Database connection configuration
- **quick_login.php** - Login interface
- **dashboard.php** - Main dashboard
- **check_erp_tables.php** - Alternative table verification

---

## Quick Links

| Action | URL |
|--------|-----|
| Create Schema | `/setup_complete_erp_tables.php` |
| Verify Schema | `/verify_erp_schema.php` |
| Insert Sample Data | `/populate_erp_data.php` |
| Login | `/quick_login.php` |
| Dashboard | `/dashboard.php` |

---

## Document Information

**Created:** January 2024
**Version:** 1.0
**Status:** Production Ready
**Last Updated:** January 2024

For questions or issues, refer to the inline PHP documentation in each setup file.
