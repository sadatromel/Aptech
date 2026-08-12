# AIP TRANSPORT ERP - QUICK START GUIDE

## 🚀 IMMEDIATE SETUP (5 Minutes)

### Step 1: Create Database
```sql
CREATE DATABASE aptech;
```

### Step 2: Import Schema
Open phpMyAdmin → Select `aptech` database → Import `complete_setup.sql`

### Step 3: Access System
```
http://localhost/AIP%20Transport/
```

### Step 4: Login with Demo Credentials
```
Email: admin@aiptransport.com
Password: password123
```

---

## 📁 PROJECT FILES

### Core Application Files
```
index.php              → Login page
register.php           → User registration
dashboard.php          → Main dashboard
vehicles.php           → Vehicle management
maintenance.php        → Maintenance tracking
parts_inventory.php    → Parts inventory
cost_reports.php       → Cost analysis
users.php              → User management (Admin only)
logout.php             → Logout handler
```

### Database File
```
complete_setup.sql     → Full database schema + sample data
```

### Documentation
```
README.md              → Complete documentation
QUICKSTART.md          → This file
```

---

## 🎯 DEFAULT USERS

After importing `complete_setup.sql`:

| Role | Email | Password | Access |
|------|-------|----------|--------|
| Super Admin | admin@aiptransport.com | password123 | Everything |
| Manager | manager@aiptransport.com | password123 | Vehicles, Maintenance, Reports |
| Technician | tech@aiptransport.com | password123 | View Vehicles, Log Maintenance |

**Default password for all**: `password123`

---

## 🔧 SYSTEM REQUIREMENTS

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Safari, Edge)
- XAMPP/WAMP/LAMP installed

---

## ✨ MAIN FEATURES

### 1️⃣ VEHICLE MANAGEMENT
- Add/Edit/Delete vehicles
- Track registration numbers, make, model, year
- Monitor current mileage
- View purchase date and cost

### 2️⃣ MAINTENANCE TRACKING
- Schedule maintenance for each vehicle
- Track maintenance type (Oil Change, Filter, Brake, etc.)
- Record costs
- Mark as complete
- Add notes and descriptions

### 3️⃣ PARTS INVENTORY
- Manage spare parts stock
- Set reorder levels
- Automatic low stock alerts
- Track suppliers
- Display unit prices

### 4️⃣ COST REPORTS
- Generate monthly cost reports
- Track maintenance costs
- Track parts costs
- Track fuel costs
- View cost summaries

### 5️⃣ USER MANAGEMENT
- Create users with different roles
- Role-based access control
- Activity logging
- User status management (active/inactive/suspended)

### 6️⃣ DASHBOARD
- Real-time statistics
- Active vehicles count
- Pending maintenance count
- Low stock parts alerts
- Recent activity log

---

## 📊 DATABASE STRUCTURE

```
user_roles              → Role definitions
users                   → User accounts
user_activity_logs      → Activity tracking
vehicles                → Fleet vehicles
maintenance_schedule    → Maintenance records
parts_inventory         → Spare parts stock
maintenance_parts_usage → Parts used in maintenance
cost_reports            → Monthly cost analysis
```

---

## 🔐 SECURITY FEATURES

✓ Password hashing (PASSWORD_DEFAULT)
✓ SQL injection prevention (Prepared statements)
✓ Session-based authentication
✓ Email validation
✓ Role-based access control
✓ Activity logging
✓ Account status tracking

---

## 🎨 INTERFACE FEATURES

- Modern gradient UI (Purple & Blue theme)
- Responsive design
- Font Awesome icons
- Sidebar navigation
- Dashboard cards with statistics
- Data tables with sorting
- Form validation
- Success/Error messages

---

## 📝 WORKFLOW EXAMPLE

1. **Login** → Use admin credentials
2. **Dashboard** → View statistics and alerts
3. **Add Vehicle** → Go to Vehicles → Fill form → Submit
4. **Create Maintenance** → Go to Maintenance → Select vehicle → Add record
5. **Manage Parts** → Go to Parts Inventory → Add parts
6. **Generate Reports** → Go to Cost Reports → Fill monthly data
7. **Manage Users** → Go to Users (Admin only) → Create user accounts

---

## 🐛 COMMON ISSUES & SOLUTIONS

### Issue: "Connection failed"
**Solution**: Check database credentials in PHP files (default: root, no password)

### Issue: "Foreign key constraint fails"
**Solution**: Ensure `complete_setup.sql` is imported completely

### Issue: "Page not found"
**Solution**: Check file paths and ensure files are in correct directory

### Issue: "Session expires"
**Solution**: Session lasts 24 minutes by default. Log in again.

---

## 📞 SUPPORT

For database issues, ensure:
- MySQL service is running
- XAMPP/WAMP is started
- Database `aptech` is created
- All tables are imported

---

## 🔄 DATA ENTRY TIPS

### Vehicles
- Use realistic registration numbers (e.g., DHA-001)
- Enter mileage in kilometers
- Dates in YYYY-MM-DD format
- Costs in Pakistani Rupees (Rs.)

### Maintenance
- Select vehicle first
- Choose maintenance type from dropdown
- Enter scheduled date
- Add detailed notes for reference

### Parts Inventory
- Use unique part codes
- Set reorder level based on usage
- Track supplier information
- Monitor stock regularly

### Cost Reports
- Generate monthly reports
- Include all cost categories
- Total is calculated automatically
- Reports are permanent records

---

## 🎓 TRAINING TIPS

1. Start with demo credentials
2. Explore each module
3. Add sample data
4. Test all functions
5. Generate reports
6. Create new users with different roles
7. Check activity logs

---

## 📈 FUTURE ENHANCEMENTS

- PDF report export
- Email notifications
- SMS alerts
- GPS tracking
- Mobile app
- Advanced charts & graphs
- Fuel consumption tracking
- Vehicle depreciation

---

## ✅ VERIFICATION CHECKLIST

- [ ] Database `aptech` created
- [ ] `complete_setup.sql` imported
- [ ] All PHP files in correct directory
- [ ] Can login with admin@aiptransport.com
- [ ] Dashboard displays statistics
- [ ] Can add new vehicle
- [ ] Can create maintenance record
- [ ] Parts inventory page works
- [ ] Cost reports generate successfully
- [ ] User management accessible (Admin only)

---

## 📚 FILE LOCATIONS

All files should be in:
```
C:\xampp\htdocs\AIP Transport\
```

---

**Version**: 1.0
**Last Updated**: November 2025
**Organization**: AIP Transport
**Status**: Production Ready ✅
