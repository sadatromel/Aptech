# AIP Transport ERP System - Complete Setup Guide

## Project Overview
A comprehensive Fleet and Maintenance Management ERP system for managing vehicles, maintenance tracking, parts inventory, cost reports, and multi-user role-based access.

## Features Included
✅ Vehicle Management (CRUD)
✅ Maintenance Tracking
✅ Parts Inventory Management
✅ Cost Reports
✅ Multi-user Role-based System
✅ Activity Logging
✅ Attractive Modern Interface

## Database Setup

### 1. Create Database
```sql
CREATE DATABASE aptech;
USE aptech;
```

### 2. Run Complete Schema
Execute the contents of `complete_setup.sql` file to create all tables and insert sample data.

## Files Structure

### Core Files
- `index.php` - Login Page (Attractive modern interface)
- `dashboard.php` - Main Dashboard with statistics
- `register.php` - User Registration
- `vehicles.php` - Vehicle Management (Add, View, Edit, Delete)
- `maintenance.php` - Maintenance Tracking System
- `parts_inventory.php` - Parts Inventory Management
- `cost_reports.php` - Cost Analysis and Reports
- `users.php` - User Management (Role-based)
- `complete_setup.sql` - Database schema with sample data

### Database Tables
1. **user_roles** - Store user roles (Super Admin, Manager, Technician, Viewer)
2. **users** - User accounts with email and password
3. **user_activity_logs** - Track all user activities
4. **vehicles** - Fleet vehicle database
5. **maintenance_schedule** - Maintenance records
6. **parts_inventory** - Parts stock management
7. **maintenance_parts_usage** - Track parts used in maintenance
8. **cost_reports** - Monthly cost analysis

## Sample Login Credentials

After running `complete_setup.sql`, use:

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@aiptransport.com | password123 |
| Manager | manager@aiptransport.com | password123 |
| Technician | tech@aiptransport.com | password123 |

## Features by Module

### 1. Vehicle Management
- Add new vehicles with details (Make, Model, Year, Fuel Type)
- Track current mileage
- Record purchase date and cost
- View all active vehicles
- Edit and delete vehicle records

### 2. Maintenance Tracking
- Schedule maintenance for vehicles
- Track maintenance types (Oil Change, Filter, Brake Service, etc.)
- Record maintenance costs
- Mark maintenance as completed
- Add notes and descriptions

### 3. Parts Inventory
- Add parts with part codes and categories
- Track quantity in stock
- Set reorder levels
- Alert system for low stock
- Record supplier information
- Display unit prices

### 4. Cost Reports
- Generate monthly cost reports per vehicle
- Track maintenance costs
- Track parts costs
- Track fuel costs
- Track other operational costs
- View cost summaries and totals

### 5. Role-Based Access
- **Super Admin**: Full system access
- **Manager**: Can manage vehicles, maintenance, and reports
- **Technician**: Can view vehicles and log maintenance
- **Viewer**: Read-only access to reports

### 6. Dashboard
- Real-time vehicle count
- Pending maintenance alerts
- Low stock parts notifications
- Recent activity log
- Quick navigation menu

## Installation Steps

1. **Create Database**
   - Open phpMyAdmin
   - Create database named `aptech`

2. **Import Schema**
   - Execute the `complete_setup.sql` file

3. **Configure Database**
   - Update database credentials in each PHP file if different from defaults
   - Default: Host=localhost, User=root, Password=(empty)

4. **Access the System**
   - Open browser and go to `http://localhost/AIP%20Transport/`
   - Login with demo credentials

5. **First Steps**
   - Add vehicles from Vehicles menu
   - Create maintenance records
   - Add parts to inventory
   - Generate cost reports

## Security Features

✓ Password hashing with PHP PASSWORD_DEFAULT
✓ Prepared statements to prevent SQL injection
✓ Session-based authentication
✓ Email validation
✓ Role-based access control
✓ Activity logging
✓ User account status tracking (active/inactive/suspended)

## Technology Stack

- **Backend**: PHP 7.0+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3
- **Icons**: Font Awesome 6.4
- **Styling**: Modern Gradient UI

## Browser Support
- Chrome (Recommended)
- Firefox
- Safari
- Edge

## Notes

- All costs are in Pakistani Rupees (Rs.)
- Mileage is in kilometers
- All timestamps use server timezone
- Activity logs retain complete history
- Backup your database regularly

## Future Enhancements

- PDF report generation
- Email notifications
- SMS alerts for low stock
- GPS tracking integration
- Mobile app
- Advanced analytics and charts
- Fuel consumption tracking
- Depreciation calculations

## Support

For any issues or questions, please contact the system administrator.

---
**Version**: 1.0
**Created**: November 2025
**Organization**: AIP Transport
