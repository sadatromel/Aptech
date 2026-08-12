# 🏭 Aptech Enterprise Resource Planning (ERP) System

[![PHP Version](https://img.shields.io/badge/PHP-%5E8.0-blue.svg)](https://php.net)
[![Composer Autoloader](https://img.shields.io/badge/Autoload-PSR--4-orange.svg)](https://getcomposer.org)
[![MVC Architecture](https://img.shields.io/badge/Architecture-Pure%20MVC-green.svg)](https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller)
[![Twig](https://img.shields.io/badge/Template-Twig%203.x-green.svg)](https://twig.symfony.com)
[![FastRoute](https://img.shields.io/badge/Router-FastRoute-blue.svg)](https://github.com/nikic/FastRoute)
[![Theme System](https://img.shields.io/badge/Theme-Dark%20%7C%20Light-blueviolet.svg)](#-premium-ui--theme-engine)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](#)

A comprehensive, modern, **enterprise-grade ERP system** built specifically for the **garment manufacturing and textile industry**. The system streamlines and automates the entire manufacturing lifecycle — from merchandising and procurement to production planning, sewing line performance, chemical washing controls, financial accounting, HR/payroll, RMG compliance, employee information management, and fleet transport logistics.

---

## 📑 Table of Contents

- [Architecture Overview](#️-modern-architectural-design)
- [Directory Structure](#-directory-structure)
- [Domain Modules & Feature Grid](#️-domain-modules--feature-grid)
  - [HR & Payroll Sub-Menu Reorganization](#-hr--payroll-sub-menu-architecture)
- [Instant Multi-Level Sidebar Search](#-instant-multi-level-sidebar-search)
- [Premium UI & Theme Engine](#-premium-ui--theme-engine)
- [Security & Performance](#-enterprise-security--performance)
- [Dependencies](#-dependencies)
- [Installation & Setup](#️-installation--setup)
- [Default Credentials](#-default-credentials)
- [Developer Guidelines & Code Standards](#-developer-guidelines--mvc-code-standards)
- [Testing & Code Quality](#-testing--code-quality)
- [Troubleshooting](#-troubleshooting)
- [Documentation Library](#-documentation-library)

---

## 🏗️ Modern Architectural Design

Aptech ERP has been fully modernized into a **Pure MVC (Model-View-Controller)** architecture designed for high scalability, maintainability, and enterprise security.

### ⚙️ Core Architecture Stack

| Concern | Solution |
| :--- | :--- |
| **Autoloading** | PSR-4 Composer autoloading (`App\`, `Core\`, `Config\`, `Tests\`) |
| **Dependency Injection** | PHP-DI Container v7.x — auto-wiring & decoupled instantiation |
| **Template Engine** | Twig 3.x — secure, sandboxed, clean frontend rendering |
| **Routing** | FastRoute (clean URL dispatch) + legacy `switch($page)` safety net |
| **Logging** | Monolog 3.x — structured activity & DB warning channels |
| **Code Standards** | PSR-12 with PHP CodeSniffer pipelines |
| **CSRF Protection** | Dedicated `CsrfGuard` middleware with token validation |
| **Testing** | PHPUnit 10.x test suite |
| **Excel Export** | PhpSpreadsheet 5.x for report/data exports |

### 🔄 Dual-Routing System

1. **Clean URL Dispatch** — `public/index.php` intercepts REST-like URIs (`/production/planning`, `/hr/employees`, `/hr/employees/salary-info`) via **FastRoute** and dispatches to typed controllers.
2. **Legacy Compatibility Net** — The root `index.php` handles query parameters (`?page=production/planning`) and maps them to their modern controller equivalents, ensuring zero "Page Not Found" regressions.

---

## 📁 Directory Structure

```
Aptech/
├── app/                            # Main Application (MVC)
│   ├── Controllers/                # Module business logic
│   │   ├── Admin/                  # Users, Roles, Settings, Audit Logs, Installation
│   │   ├── Api/                    # REST API & JSON response controllers
│   │   ├── Commercial/             # Quotations, Sales Contracts, Orders, Invoices, Exports
│   │   ├── Cutting/                # Cutting Plans, Markers, Bundle Generation
│   │   ├── Finance/                # Chart of Accounts, Vouchers, Ledger, Reports, Cash/Bank
│   │   ├── HR/                     # HR Controllers (Employee Directory, Info, Payroll, Attendance, Career, Compliance)
│   │   │   ├── AttendanceController.php
│   │   │   ├── EmployeeController.php
│   │   │   ├── HRAttendanceOperationController.php
│   │   │   ├── HRCareerProcessController.php
│   │   │   ├── HRDashboardController.php
│   │   │   ├── HREmployeeInfoController.php        # 🆕 9 Employee Information pages
│   │   │   ├── HRPayrollProcessController.php       # 🆕 Extended Payroll processes
│   │   │   ├── HRReportController.php
│   │   │   ├── LeaveController.php
│   │   │   ├── PayrollController.php
│   │   │   └── RmgComplianceController.php
│   │   ├── Inventory/              # Items, Stock Adjustments, Gate Passes
│   │   ├── MasterLibrary/          # Geographic, Bank, Currency, UOM, Store & Brand masters
│   │   ├── Merchandising/          # Styles, BOMs, Costing, Sampling, Transfer, PO Requests
│   │   ├── Packing/                # Cartons, Export docs, Packing Plans
│   │   ├── Procurement/            # Vendors, Purchase Orders, GRN, Carton Bookings
│   │   ├── Production/             # Capacity, Scheduling, Finishing, QA, Sewing, Washing
│   │   ├── Sewing/                 # Sewing Line configuration & Hourly QC Performance
│   │   ├── Transport/              # Vehicles, Routes, Seat Allotment, Maintenance, Parts
│   │   ├── Visitor/                # Gate pass logs & photo captures
│   │   ├── Washing/                # Wash Batches & Chemical controls
│   │   ├── AdminController.php     # Admin dashboard aggregator
│   │   ├── AuthController.php      # Login / Logout / Password reset
│   │   ├── BaseController.php      # Abstract base with Twig render helpers
│   │   └── ProfileController.php
│   ├── Core/                       # Application base classes
│   ├── Http/                       # Request, Response & FastRoute dispatcher (Router.php)
│   ├── Middleware/                 # Auth guards & permission checks
│   ├── Models/                     # 65+ data models mapped to DB entities
│   ├── Repositories/               # Data access abstraction layers
│   ├── Services/                   # Domain service layers
│   └── Views/                      # Twig template views
│       ├── layouts/                # Global HTML wrappers (app.twig) & partials (sidebar.twig)
│       └── hr/                     # HR Views & Sub-menu Templates
│           ├── attendance/
│           ├── attendance_operations/
│           ├── employee_info/      # 🆕 Employee Info Twig templates (weekend, basic, details, policy, salary, etc.)
│           ├── employee_processes/
│           ├── employees/
│           ├── leaves/
│           ├── payroll/
│           ├── payroll_processes/
│           └── reports/
├── assets/                         # Static assets
│   ├── css/                        # Custom theme stylesheets, dark mode overrides & nested submenus
│   └── img/                        # Logos, favicons, system illustrations
├── backups/                        # Database backup files
├── cache/                          # Twig compiled template cache
├── config/                         # Configuration parameters (routes.php, database.php, etc.)
├── core/                           # System core engine (Auth.php, CsrfGuard.php, Database.php, Model.php)
├── database/                       # DB scripts, migrations & auto-table definitions
├── docs/                           # Developer guides & system documentation
├── public/                         # Modern web root (index.php, CSS, JS)
├── vendor/                         # Composer package dependencies
├── README.md                       # Comprehensive system documentation
└── composer.json                   # PSR-4 package manifest
```

---

## ⚙️ Domain Modules & Feature Grid

The Aptech ERP database is fully normalized (3NF) and contains **35+ core tables** with dedicated modules for every manufacturing domain:

### 🧵 Merchandising
| Feature | Detail |
| :--- | :--- |
| Style Lifecycle | Design → Approval → Production flow with SKU management |
| Bill of Materials | Multi-level BOM with component costing |
| Bulk Costing | Cost template engine with trims & accessory breakdowns |
| Sampling | Sample planning, production tracking, and status workflows |
| Size Ranges | Size range master with breakdown configurations |
| Purchase Requests | PR generation from BOM shortfalls |
| **Key Tables** | `styles`, `style_skus`, `bom`, `bom_components`, `sampling`, `size_ranges`, `size_breakdowns`, `purchase_requests` |

### 🏭 Production
| Feature | Detail |
| :--- | :--- |
| Capacity Planning | Line loading Gantt charts and capacity scheduling |
| Production Orders | Order-to-production linkage with status tracking |
| Finishing | Finishing floor quality gates and throughput tracking |
| Quality Control | QC inspection records, defect logging, AQL reports |
| **Key Tables** | `production_plans`, `production_orders`, `production_lines`, `qc_inspections`, `qc_defects` |

### ✂️ Cutting & 🧵 Sewing
| Feature | Detail |
| :--- | :--- |
| Cutting Plans | Fabric cutting plan management, markers & bundle tags |
| Line Configuration | Sewing line setup (machines, operators, target output) |
| Hourly Performance | Shift-wise and hourly production performance tracking |
| **Key Tables** | `cutting_plans`, `markers`, `bundles`, `sewing_lines`, `line_performance` |

---

### 👩‍💼 HR & Payroll Sub-Menu Architecture

The HR Module has been reorganized into structured, multi-level sub-menus to provide a clean, modern user experience for factory HR administrators:

#### 1. 📂 Employee Information (`/hr/employees/*`)
Controller: `App\Controllers\HR\HREmployeeInfoController`

| Page | Route | Description |
| :--- | :--- | :--- |
| **Employee Weekend** | `/hr/employees/weekend` | Assign & manage employee-specific weekly off days (Friday/Saturday/Sunday). |
| **Employee Basic** | `/hr/employees/basic-info` | Master employee directory overview with department badges & quick filters. |
| **Employee Details** | `/hr/employees/details-info` | 360° master profile tabs (Personal, Address & Contact, Employment & Salary). |
| **Employee Policy Tagging Information** | `/hr/employees/policy-tagging` | Tag OT rules, 15-min grace policies, and conduct rules to employees. |
| **Employee Salary Information** | `/hr/employees/salary-info` | Update basic salary, house rent, medical, transport, and bank payout details. |
| **Employee Submitted Documents** | `/hr/employees/submitted-documents` | Document repository for NID, certificates, nominee forms & audit files. |
| **Employee Vaccination Information** | `/hr/employees/vaccination-info` | RMG buyer audit & compliance immunization registry (COVID-19, Hepatitis, TT). |
| **Health and Medical Information** | `/hr/employees/health-medical` | Track blood group, height/weight, medical clearance, checkups & insurance. |
| **Tax Certificate Information** | `/hr/employees/tax-certificate` | 12-digit TIN, Tax Zone/Circle, Assessment Year, and TDS return tracking. |

#### 2. 📅 Attendance Management (`/hr/attendance/*`)
Controller: `App\Controllers\HR\HRAttendanceOperationController`

| Page | Route | Description |
| :--- | :--- | :--- |
| **Attendance Register** | `/hr/attendance` | Biometric check-in/out logs & daily attendance master. |
| **Bulk Movement Entry** | `/hr/attendance/movement-bulk` | Bulk movement & official duty logs. |
| **Date Attendance Transfer** | `/hr/attendance/date-transfer` | Shift/date attendance transfers. |
| **Define Holiday** | `/hr/attendance/define-holiday` | Factory annual holiday setup. |
| **Define Weekend** | `/hr/attendance/define-weekend` | General factory weekend rules. |
| **Duty Roster Re-process** | `/hr/attendance/roster-reprocess` | Re-evaluate roster shift assignments. |
| **Holiday Remove** | `/hr/attendance/holiday-remove` | Remove or override declared holidays. |
| **Manual Attendance Entry** | `/hr/attendance/manual-entry` | Single/bulk manual punch entry. |
| **Movement Register** | `/hr/attendance/movement-register` | Out-station & official movement tracking. |
| **OT Requisition** | `/hr/attendance/ot-requisition` | Overtime requisition approvals. |

#### 3. 💵 Payment Management (`/hr/payroll/*`)
Controllers: `App\Controllers\HR\HRPayrollProcessController`, `App\Controllers\HR\HRCareerProcessController`

| Page | Route | Description |
| :--- | :--- | :--- |
| **Payroll Management** | `/hr/payroll` | Main payroll calculation & sheet generator. |
| **Bonus Re-process** | `/hr/payroll/bonus-reprocess` | Festival & performance bonus re-calculation. |
| **Cost Center Wise Salary Process** | `/hr/payroll/cost-center-salary` | Salary processing allocated by cost center. |
| **Earn Leave Payment Process Monthly** | `/hr/payroll/earn-leave-monthly` | Monthly earn leave encashment processing. |
| **Earn Leave Payment Process Yearly** | `/hr/payroll/earn-leave-yearly` | Annual earn leave encashment payout. |
| **Employee Promotion Entry Bulk** | `/hr/employees/promotion-bulk` | Bulk designation & grade promotions. |
| **Final Settlement Process** | `/hr/payroll/final-settlement` | Resignation & termination settlement payouts. |
| **Fraction Month Salary Re-process** | `/hr/payroll/fraction-salary-reprocess` | Partial month salary calculations. |
| **Holiday Allowance Process** | `/hr/payroll/holiday-allowance` | Working on holiday allowance payouts. |
| **Increment Entry** | `/hr/employees/increment-entry` | Individual salary increment processing. |
| **Increment Entry [Bulk]** | `/hr/employees/increment-bulk` | Department-wide percentage salary increments. |
| **Lay Off Employee** | `/hr/employees/lay-off` | Worker layoff allowance & status processing. |
| **Monthly Bill Process** | `/hr/payroll/monthly-bill-process` | Food, conveyance, and utility bill processing. |
| **Night Allowance Process** | `/hr/payroll/night-allowance` | Night shift worker allowance processing. |
| **Promotion Entry** | `/hr/employees/promotion-entry` | Single employee designation & salary promotion. |
| **Salary Adjustment Entry** | `/hr/payroll/salary-adjustment` | Advance, loan, or penalty salary adjustments. |
| **Salary Period Lock** | `/hr/payroll/period-lock` | Lock salary periods after audit. |
| **Tiffin Bill Process** | `/hr/payroll/tiffin-bill` | Overtime tiffin bill processing. |

---

### 💰 Finance & Accounting
| Feature | Detail |
| :--- | :--- |
| Chart of Accounts | Custom COA hierarchy with opening balances |
| Double-Entry Journals | Journal entries with line-level allocations |
| Vouchers | Payment, receipt, and journal vouchers |
| General Ledger | Live ledger queries per account |
| Trial Balance | Period-based trial balance reports |
| Financial Reports | P&L, Balance Sheet, and subledger reports |
| **Key Tables** | `chart_of_accounts`, `journal_entries`, `journal_lines`, `transactions` |

---

## 🔍 Instant Multi-Level Sidebar Search

The sidebar search system in `app/Views/layouts/app.twig` provides an instant search and navigation engine:

### 🌟 Key Search Features
- **Full DOM Link Scraping**: Dynamically indexes all navigable page links across all nesting levels (`.submenu .submenu`).
- **Cascading Tree Un-Hiding & Auto-Expansion**: Automatically reveals and expands **all** parent sub-menus and parent `li` containers up the DOM tree when a search query matches any child link or category title.
- **Hierarchical Category Badges**: Displays full parent paths in search results (e.g. `HR Module > Employee Information`, `HR Module > Payment Management`).
- **Keyboard Navigation**: Use `ArrowUp`, `ArrowDown`, `Enter`, and `Escape` to highlight and navigate search results instantly.
- **Clean Reset**: Automatically restores all sub-menus to their original active state when the search input is cleared.

---

## 🎨 Premium UI & Theme Engine

The application features a modern responsive UI built with **Bootstrap 5**, enhanced by a fully customizable theme engine.

### ✨ Visual Capabilities

- **Glassmorphism Settings** — Enable/disable blur levels (`--glass-blur`) and backing opacity (`--glass-opacity`) via the system settings panel.
- **Dynamic CSS Variables** — Core colors, hover highlights, border-radius, and typography are injected into the Twig layout at runtime:
  ```css
  --primary:       {{ settings.primary_color|default('#667eea') }};
  --primary-light: {{ settings.primary_color|default('#667eea') }}1a;
  --primary-dark:  {{ settings.hover_color|default('#5a67d8') }};
  --font-family:   '{{ settings.font_family|default('Inter') }}', sans-serif;
  --border-radius: {{ settings.border_radius|default(8) }}px;
  ```
- **Nested Submenu Styling** — Multi-level nested submenus (`.sidebar .submenu .submenu`) feature an accent border and subtle background tint in both light and dark themes.
- **No FOUC** — Inline `<head>` script reads `localStorage` and applies dark/light mode before first paint, preventing flash of unstyled content.
- **SweetAlert2** — Contextual success/error/confirm dialogs replace browser-native `alert()` calls throughout the system.

---

## 🔒 Enterprise Security & Performance

### 🛡️ Security Guardrails

| Layer | Implementation |
| :--- | :--- |
| **SQL Injection** | All queries use PDO prepared statements with strict type binding |
| **Password Hashing** | PHP `PASSWORD_DEFAULT` (bcrypt) for all user credentials |
| **CSRF Protection** | `CsrfGuard` middleware generates/validates tokens on every POST request |
| **RBAC Middleware** | Controller-level permission check on every action, every request |
| **Activity Auditing** | `activity_logs` records action type, timestamp, HTTP method, user IP |
| **Session Security** | Configurable session lifetime with server-side invalidation |

---

## 📦 Dependencies

| Package | Version | Purpose |
| :--- | :--- | :--- |
| `php` | `^8.0` | Minimum PHP runtime |
| `twig/twig` | `^3.0` | Secure template rendering |
| `nikic/fast-route` | `^1.3` | High-performance HTTP router |
| `php-di/php-di` | `^7.0` | Dependency injection container |
| `monolog/monolog` | `^3.0` | Structured application logging |
| `phpoffice/phpspreadsheet` | `^5.8` | Excel/CSV export generation |

---

## 🛠️ Installation & Setup

1. **Clone & Install Dependencies**:
   ```bash
   git clone <repository-url>
   cd Aptech
   composer install
   ```

2. **Configure Environment**:
   ```bash
   cp .env.example .env
   # Update DB_HOST, DB_NAME, DB_USER, DB_PASS in .env
   ```

3. **Run Setup & Database Migration**:
   Access `http://localhost/Aptech/setup/register.php` in your browser or execute:
   ```bash
   php database/migrate_hr_extended.php
   ```

4. **Serve Application**:
   - Web Server: Configure Apache/Nginx vhost pointing to `public/`.
   - Local Development: Use Apache (XAMPP/WAMP) at `http://localhost/Aptech/`.

---

## 🔑 Default Credentials

- **Admin Username**: `admin`
- **Password**: `admin123`

---

## 📝 Testing & Code Quality

Run tests and PSR-12 style checks:
```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Check PSR-12 code style compliance
vendor/bin/phpcs --standard=PSR12 app/
```

---

## 📄 License
Proprietary software for Aptech Enterprise Resource Planning System. All rights reserved.
