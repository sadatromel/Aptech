<?php

declare(strict_types=1);

namespace App\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

class Router
{
    private Dispatcher $dispatcher;

    public function __construct()
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            // API Routes
            $r->addGroup('/api', function (RouteCollector $r) {
                $r->get('/users', ['App\Controllers\Api\UserController', 'index']);
                $r->post('/users', ['App\Controllers\Api\UserController', 'store']);
                $r->get('/users/{id}', ['App\Controllers\Api\UserController', 'show']);
                $r->put('/users/{id}', ['App\Controllers\Api\UserController', 'update']);
                $r->delete('/users/{id}', ['App\Controllers\Api\UserController', 'destroy']);

                // HR APIs
                $r->get('/hr/employee-details', ['App\Controllers\HR\LeaveController', 'getEmployeeDetailsApi']);
                $r->get('/hr/leave-balance', ['App\Controllers\HR\LeaveController', 'getLeaveBalanceApi']);
            });

            // Cutting Routes
            $r->addGroup('/cutting', function (RouteCollector $r) {
                // Cutting Plans CRUD
                $r->addRoute(['POST'], '/plans/store', ['App\Controllers\Cutting\CuttingPlanController', 'store']);
                $r->addRoute(['POST'], '/plans/{id:\d+}/update-status', ['App\Controllers\Cutting\CuttingPlanController', 'updateStatus']);
                $r->addRoute(['POST'], '/plans/{id:\d+}/delete', ['App\Controllers\Cutting\CuttingPlanController', 'destroy']);

                // Markers CRUD
                $r->addRoute(['POST'], '/markers/store', ['App\Controllers\Cutting\MarkerController', 'store']);
                $r->addRoute(['POST'], '/markers/{id:\d+}/delete', ['App\Controllers\Cutting\MarkerController', 'destroy']);

                // Bundles CRUD
                $r->addRoute(['POST'], '/bundles/store', ['App\Controllers\Cutting\BundleController', 'store']);
                $r->addRoute(['POST'], '/bundles/{id:\d+}/delete', ['App\Controllers\Cutting\BundleController', 'destroy']);

                // Catch-all tab routes (must be last)
                $r->addRoute(['GET', 'POST'], '/{tab:[a-zA-Z0-9_-]+}', ['App\Controllers\Cutting\CuttingController', 'showTab']);
                $r->addRoute(['GET', 'POST'], '', ['App\Controllers\Cutting\CuttingController', 'index']);
            });

            // Admin Routes
            $r->addGroup('/admin', function (RouteCollector $r) {
                // Role Management
                $r->get('/roles', ['App\Controllers\Admin\RoleController', 'index']);
                $r->get('/roles/create', ['App\Controllers\Admin\RoleController', 'create']);
                $r->post('/roles', ['App\Controllers\Admin\RoleController', 'store']);
                $r->get('/roles/{id:\d+}/edit', ['App\Controllers\Admin\RoleController', 'edit']);
                $r->post('/roles/{id:\d+}', ['App\Controllers\Admin\RoleController', 'update']);
                $r->delete('/roles/{id:\d+}', ['App\Controllers\Admin\RoleController', 'destroy']);
                
                // Enhanced Permission Management
                $r->get('/roles/{id:\d+}/permissions', ['App\Controllers\Admin\RoleController', 'permissions']);
                $r->post('/roles/{id:\d+}/update-permissions', ['App\Controllers\Admin\RoleController', 'updatePermissions']);
                
                // User Permissions Management
                $r->addRoute(['GET', 'POST'], '/permissions', ['App\Controllers\Admin\PermissionController', 'index']);
                $r->addRoute(['GET', 'POST'], '/permissions/{userId:\d+}', ['App\Controllers\Admin\PermissionController', 'index']);
                $r->post('/permissions/update', ['App\Controllers\Admin\PermissionController', 'update']);
                $r->post('/permissions/reset', ['App\Controllers\Admin\PermissionController', 'reset']);

                // User Management
                $r->get('/users', ['App\Controllers\Admin\UserController', 'index']);
                $r->get('/users/create', ['App\Controllers\Admin\UserController', 'create']);
                $r->post('/users', ['App\Controllers\Admin\UserController', 'store']);
                $r->get('/users/{id:\d+}', ['App\Controllers\Admin\UserController', 'show']);
                $r->get('/users/{id:\d+}/edit', ['App\Controllers\Admin\UserController', 'edit']);
                $r->post('/users/{id:\d+}', ['App\Controllers\Admin\UserController', 'update']);
                $r->delete('/users/{id:\d+}', ['App\Controllers\Admin\UserController', 'destroy']);
                $r->post('/users/{id:\d+}/reset-password', ['App\Controllers\Admin\UserController', 'resetPassword']);
                $r->post('/users/{id:\d+}/toggle-status', ['App\Controllers\Admin\UserController', 'toggleStatus']);

                // Dashboard & Audit
                $r->get('/dashboard', ['App\Controllers\Admin\DashboardController', 'index']);
                $r->get('/audit-logs', ['App\Controllers\Admin\AuditLogController', 'index']);

                // Setup & Installation Routes
                $r->get('/setup', ['App\Controllers\Admin\SetupInstallationController', 'index']);
                $r->get('/setup/system-info', ['App\Controllers\Admin\SetupInstallationController', 'systemInfo']);
                $r->get('/setup/configuration', ['App\Controllers\Admin\SetupInstallationController', 'configuration']);
                $r->post('/setup/save-config', ['App\Controllers\Admin\SetupInstallationController', 'saveConfiguration']);
                $r->post('/setup/delete-config', ['App\Controllers\Admin\SetupInstallationController', 'deleteConfiguration']);
                $r->get('/setup/backup', ['App\Controllers\Admin\SetupInstallationController', 'backup']);
                $r->post('/setup/create-backup', ['App\Controllers\Admin\SetupInstallationController', 'createBackup']);
                $r->get('/setup/download', ['App\Controllers\Admin\SetupInstallationController', 'downloadBackup']);
                $r->post('/setup/delete-backup', ['App\Controllers\Admin\SetupInstallationController', 'deleteBackup']);
                $r->get('/setup/wizard', ['App\Controllers\Admin\SetupInstallationController', 'wizard']);
                $r->post('/setup/install', ['App\Controllers\Admin\SetupInstallationController', 'install']);
                
                // Settings Routes
                $r->get('/settings', ['App\Controllers\Admin\SettingsController', 'index']);
                $r->post('/settings', ['App\Controllers\Admin\SettingsController', 'update']);
            });

            // Auth Routes
            $r->get('/login', ['App\Controllers\AuthController', 'showLogin']);
            $r->post('/login', ['App\Controllers\AuthController', 'login']);
            $r->post('/logout', ['App\Controllers\AuthController', 'logout']);
            $r->get('/logout', ['App\Controllers\AuthController', 'logout']);



            // HR Routes
            $r->addGroup('/hr', function (RouteCollector $r) {
                $r->get('', ['App\Controllers\HR\HRDashboardController', 'index']);
                $r->get('/dashboard', ['App\Controllers\HR\HRDashboardController', 'index']);
                $r->get('/showcase/{section}/{page}', ['App\Controllers\HR\HRDashboardController', 'showcase']);
                $r->post('/showcase/{section}/{page}', ['App\Controllers\HR\HRDashboardController', 'storeRecord']);
                $r->delete('/showcase/{id:\d+}', ['App\Controllers\HR\HRDashboardController', 'deleteRecord']);

                $r->get('/employees', ['App\Controllers\HR\EmployeeController', 'index']);
                $r->get('/employees/create', ['App\Controllers\HR\EmployeeController', 'create']);
                $r->post('/employees', ['App\Controllers\HR\EmployeeController', 'store']);
                $r->post('/employees/search', ['App\Controllers\HR\EmployeeController', 'search']);
                $r->get('/employees/export', ['App\Controllers\HR\EmployeeController', 'export']);
                $r->get('/employees/{id:\d+}', ['App\Controllers\HR\EmployeeController', 'show']);
                $r->get('/employees/{id:\d+}/edit', ['App\Controllers\HR\EmployeeController', 'edit']);
                $r->put('/employees/{id:\d+}', ['App\Controllers\HR\EmployeeController', 'update']);
                $r->delete('/employees/{id:\d+}', ['App\Controllers\HR\EmployeeController', 'destroy']);
                
                $r->get('/attendance', ['App\Controllers\HR\AttendanceController', 'index']);
                $r->post('/attendance', ['App\Controllers\HR\AttendanceController', 'store']);
                
                $r->get('/payroll', ['App\Controllers\HR\PayrollController', 'index']);
                $r->post('/payroll/generate', ['App\Controllers\HR\PayrollController', 'generate']);
                $r->get('/payroll/{id:\d+}', ['App\Controllers\HR\PayrollController', 'show']);
                $r->post('/payroll/{id:\d+}/approve', ['App\Controllers\HR\PayrollController', 'approve']);
                $r->post('/payroll/{id:\d+}/pay', ['App\Controllers\HR\PayrollController', 'pay']);
                $r->post('/payroll/{id:\d+}/destroy', ['App\Controllers\HR\PayrollController', 'destroy']);
                $r->delete('/payroll/{id:\d+}', ['App\Controllers\HR\PayrollController', 'destroy']);

                // Extended HR Payroll Processes
                $r->addRoute(['GET', 'POST'], '/payroll/bonus-reprocess', ['App\Controllers\HR\HRPayrollProcessController', 'bonusReprocess']);
                $r->addRoute(['GET', 'POST'], '/payroll/cost-center-salary', ['App\Controllers\HR\HRPayrollProcessController', 'costCenterSalary']);
                $r->addRoute(['GET', 'POST'], '/payroll/earn-leave-monthly', ['App\Controllers\HR\HRPayrollProcessController', 'earnLeaveMonthly']);
                $r->addRoute(['GET', 'POST'], '/payroll/earn-leave-yearly', ['App\Controllers\HR\HRPayrollProcessController', 'earnLeaveYearly']);
                $r->addRoute(['GET', 'POST'], '/payroll/final-settlement', ['App\Controllers\HR\HRPayrollProcessController', 'finalSettlement']);
                $r->addRoute(['GET', 'POST'], '/payroll/fraction-salary-reprocess', ['App\Controllers\HR\HRPayrollProcessController', 'fractionSalaryReprocess']);
                $r->addRoute(['GET', 'POST'], '/payroll/holiday-allowance', ['App\Controllers\HR\HRPayrollProcessController', 'holidayAllowance']);
                $r->addRoute(['GET', 'POST'], '/payroll/monthly-bill-process', ['App\Controllers\HR\HRPayrollProcessController', 'monthlyBillProcess']);
                $r->addRoute(['GET', 'POST'], '/payroll/night-allowance', ['App\Controllers\HR\HRPayrollProcessController', 'nightAllowance']);
                $r->addRoute(['GET', 'POST'], '/payroll/salary-adjustment', ['App\Controllers\HR\HRPayrollProcessController', 'salaryAdjustment']);
                $r->addRoute(['GET', 'POST'], '/payroll/period-lock', ['App\Controllers\HR\HRPayrollProcessController', 'salaryPeriodLock']);
                $r->addRoute(['GET', 'POST'], '/payroll/tiffin-bill', ['App\Controllers\HR\HRPayrollProcessController', 'tiffinBill']);

                // Extended HR Employee Career Processes
                $r->addRoute(['GET', 'POST'], '/employees/increment-entry', ['App\Controllers\HR\HRCareerProcessController', 'incrementEntry']);
                $r->addRoute(['GET', 'POST'], '/employees/increment-bulk', ['App\Controllers\HR\HRCareerProcessController', 'incrementBulk']);
                $r->addRoute(['GET', 'POST'], '/employees/promotion-entry', ['App\Controllers\HR\HRCareerProcessController', 'promotionEntry']);
                $r->addRoute(['GET', 'POST'], '/employees/promotion-bulk', ['App\Controllers\HR\HRCareerProcessController', 'promotionBulk']);
                $r->addRoute(['GET', 'POST'], '/employees/lay-off', ['App\Controllers\HR\HRCareerProcessController', 'layoffEmployee']);
                
                // Employee Information Routes
                $r->addRoute(['GET', 'POST'], '/employees/weekend', ['App\Controllers\HR\HREmployeeInfoController', 'weekendInfo']);
                $r->addRoute(['GET', 'POST'], '/employees/basic-info', ['App\Controllers\HR\HREmployeeInfoController', 'basicInfo']);
                $r->addRoute(['GET', 'POST'], '/employees/details-info', ['App\Controllers\HR\HREmployeeInfoController', 'detailsInfo']);
                $r->addRoute(['GET', 'POST'], '/employees/policy-tagging', ['App\Controllers\HR\HREmployeeInfoController', 'policyTagging']);
                $r->addRoute(['GET', 'POST'], '/employees/salary-info', ['App\Controllers\HR\HREmployeeInfoController', 'salaryInfo']);
                $r->addRoute(['GET', 'POST'], '/employees/submitted-documents', ['App\Controllers\HR\HREmployeeInfoController', 'submittedDocuments']);
                $r->addRoute(['GET', 'POST'], '/employees/vaccination-info', ['App\Controllers\HR\HREmployeeInfoController', 'vaccinationInfo']);
                $r->addRoute(['GET', 'POST'], '/employees/health-medical', ['App\Controllers\HR\HREmployeeInfoController', 'healthMedicalInfo']);
                $r->addRoute(['GET', 'POST'], '/employees/tax-certificate', ['App\Controllers\HR\HREmployeeInfoController', 'taxCertificateInfo']);
                
                // Extended HR Attendance & Roster Operations
                $r->addRoute(['GET', 'POST'], '/attendance/movement-bulk', ['App\Controllers\HR\HRAttendanceOperationController', 'bulkMovement']);
                $r->addRoute(['GET', 'POST'], '/attendance/date-transfer', ['App\Controllers\HR\HRAttendanceOperationController', 'dateAttendanceTransfer']);
                $r->addRoute(['GET', 'POST'], '/attendance/define-holiday', ['App\Controllers\HR\HRAttendanceOperationController', 'defineHoliday']);
                $r->addRoute(['GET', 'POST'], '/attendance/define-weekend', ['App\Controllers\HR\HRAttendanceOperationController', 'defineWeekend']);
                $r->addRoute(['GET', 'POST'], '/attendance/roster-reprocess', ['App\Controllers\HR\HRAttendanceOperationController', 'dutyRosterReprocess']);
                $r->addRoute(['GET', 'POST'], '/attendance/holiday-remove', ['App\Controllers\HR\HRAttendanceOperationController', 'holidayRemove']);
                $r->addRoute(['GET', 'POST'], '/attendance/manual-entry', ['App\Controllers\HR\HRAttendanceOperationController', 'manualAttendanceEntry']);
                $r->addRoute(['GET'], '/attendance/movement-register', ['App\Controllers\HR\HRAttendanceOperationController', 'movementRegister']);
                $r->addRoute(['GET', 'POST'], '/attendance/ot-requisition', ['App\Controllers\HR\HRAttendanceOperationController', 'otRequisition']);
                
                // Leave Routes
                $r->get('/leave', ['App\Controllers\HR\LeaveController', 'index']);
                $r->get('/leave/create', ['App\Controllers\HR\LeaveController', 'create']);
                $r->post('/leave', ['App\Controllers\HR\LeaveController', 'store']);
                $r->post('/leave/update-status', ['App\Controllers\HR\LeaveController', 'updateStatus']);
                $r->get('/leaves', ['App\Controllers\HR\LeaveController', 'index']);
                $r->post('/leaves', ['App\Controllers\HR\LeaveController', 'store']);
                $r->addRoute(['GET', 'POST'], '/leave-entry', ['App\Controllers\HR\LeaveController', 'entryPage']);
                $r->post('/leave-entry/store', ['App\Controllers\HR\LeaveController', 'storeEntry']);
                $r->addRoute(['GET', 'POST'], '/leave-delete', ['App\Controllers\HR\LeaveController', 'deletePage']);
                $r->post('/leave-delete/perform', ['App\Controllers\HR\LeaveController', 'deletePerform']);

                // RMG Compliance Routes
                $r->get('/rmg-compliance', ['App\Controllers\HR\RmgComplianceController', 'index']);
                $r->get('/rmg-compliance/wage-compliance', ['App\Controllers\HR\RmgComplianceController', 'wageCompliance']);
                $r->get('/rmg-compliance/attendance-bonus', ['App\Controllers\HR\RmgComplianceController', 'attendanceBonus']);
                $r->get('/rmg-compliance/festival-bonus', ['App\Controllers\HR\RmgComplianceController', 'festivalBonus']);
                $r->get('/rmg-compliance/maternity', ['App\Controllers\HR\RmgComplianceController', 'maternity']);
                $r->post('/rmg-compliance/maternity', ['App\Controllers\HR\RmgComplianceController', 'createMaternityCase']);
                $r->post('/rmg-compliance/maternity/{id:\d+}/approve', ['App\Controllers\HR\RmgComplianceController', 'approveMaternityCase']);
                $r->post('/rmg-compliance/maternity/{id:\d+}/payout', ['App\Controllers\HR\RmgComplianceController', 'payoutMaternity']);
                $r->delete('/rmg-compliance/maternity/{id:\d+}', ['App\Controllers\HR\RmgComplianceController', 'deleteMaternityCase']);
                $r->post('/rmg-compliance/maternity/{id:\d+}/delete', ['App\Controllers\HR\RmgComplianceController', 'deleteMaternityCase']);
                
                // HR Reports
                $r->get('/reports', ['App\Controllers\HR\HRReportController', 'index']);
                $r->get('/reports/job-card', ['App\Controllers\HR\HRReportController', 'jobCardReport']);
                $r->get('/reports/job-card-four-hours', ['App\Controllers\HR\HRReportController', 'jobCardFourHoursReport']);
                $r->get('/reports/extra-ot-payment-sheet', ['App\Controllers\HR\HRReportController', 'extraOtPaymentSheetReport']);
                $r->get('/reports/extra-ot-payment-sheet/export-excel', ['App\Controllers\HR\HRReportController', 'exportExtraOtPaymentExcel']);
                $r->get('/reports/salary-sheet-staff', ['App\Controllers\HR\HRReportController', 'salarySheetStaffReport']);
                $r->get('/reports/salary-sheet-worker', ['App\Controllers\HR\HRReportController', 'salarySheetWorkerReport']);
                $r->get('/reports/ot-payment-sheet', ['App\Controllers\HR\HRReportController', 'otPaymentSheetReport']);
                $r->get('/reports/ot-payment-sheet/export-excel', ['App\Controllers\HR\HRReportController', 'exportOtPaymentExcel']);
                $r->get('/reports/payslip-bangla', ['App\Controllers\HR\HRReportController', 'payslipBanglaReport']);
                $r->get('/reports/payslip-english', ['App\Controllers\HR\HRReportController', 'payslipEnglishReport']);
                $r->get('/reports/leave-status', ['App\Controllers\HR\HRReportController', 'leaveStatusReport']);
                $r->get('/reports/employee-leave-record', ['App\Controllers\HR\HRReportController', 'employeeLeaveRecordReport']);
                $r->get('/reports/annual-leave-statement', ['App\Controllers\HR\HRReportController', 'annualLeaveStatementReport']);
                $r->get('/reports/yearly-leave-balance', ['App\Controllers\HR\HRReportController', 'yearlyLeaveBalanceReport']);
                $r->get('/reports/monthly-detailed-attendance', ['App\Controllers\HR\HRReportController', 'monthlyDetailedAttendance']);
                $r->get('/reports/monthly-detailed-attendance/export-excel', ['App\Controllers\HR\HRReportController', 'exportMonthlyAttendanceExcel']);
            });

            // Generic Module Reports Hub Routes
            $r->get('/commercial/reports', function() {
                (new \App\Controllers\GenericReportController())->show('commercial');
            });
            $r->get('/merchandising/reports', function() {
                (new \App\Controllers\GenericReportController())->show('merchandising');
            });
            $r->get('/procurement/reports', function() {
                (new \App\Controllers\GenericReportController())->show('procurement');
            });
            $r->get('/inventory/reports', function() {
                (new \App\Controllers\GenericReportController())->show('inventory');
            });
            $r->get('/transport/reports', function() {
                (new \App\Controllers\GenericReportController())->show('transport');
            });
            $r->get('/master_library/reports', function() {
                (new \App\Controllers\GenericReportController())->show('master_library');
            });

            // Commercial Routes
            $r->addGroup('/commercial', function (RouteCollector $r) {
                $r->get('/customers', ['App\Controllers\Commercial\CustomerController', 'index']);
                $r->get('/customers/create', ['App\Controllers\Commercial\CustomerController', 'create']);
                $r->post('/customers', ['App\Controllers\Commercial\CustomerController', 'store']);
                $r->get('/customers/{id:\d+}', ['App\Controllers\Commercial\CustomerController', 'show']);
                $r->get('/customers/{id:\d+}/edit', ['App\Controllers\Commercial\CustomerController', 'edit']);
                $r->put('/customers/{id:\d+}', ['App\Controllers\Commercial\CustomerController', 'update']);
                $r->delete('/customers/{id:\d+}', ['App\Controllers\Commercial\CustomerController', 'destroy']);
                $r->get('/customers/export', ['App\Controllers\Commercial\CustomerController', 'export']);

                // Commercial Dashboard
                $r->get('', ['App\Controllers\Commercial\CommercialDashboardController', 'index']);
                $r->get('/dashboard', ['App\Controllers\Commercial\CommercialDashboardController', 'index']);

                // Quotations
                $r->get('/quotations', ['App\Controllers\Commercial\QuotationController', 'index']);
                $r->get('/quotations/create', ['App\Controllers\Commercial\QuotationController', 'create']);
                $r->post('/quotations', ['App\Controllers\Commercial\QuotationController', 'store']);
                $r->get('/quotations/{id:\d+}', ['App\Controllers\Commercial\QuotationController', 'show']);
                $r->get('/quotations/{id:\d+}/edit', ['App\Controllers\Commercial\QuotationController', 'edit']);
                $r->post('/quotations/{id:\d+}', ['App\Controllers\Commercial\QuotationController', 'update']);
                $r->delete('/quotations/{id:\d+}', ['App\Controllers\Commercial\QuotationController', 'destroy']);

                // Sales Contracts
                $r->get('/sales-contracts', ['App\Controllers\Commercial\SalesContractController', 'index']);
                $r->get('/sales-contracts/create', ['App\Controllers\Commercial\SalesContractController', 'create']);
                $r->get('/sales-contracts/allocation/{number}', ['App\Controllers\Commercial\SalesContractController', 'getOrdersByAllocation']);
                $r->post('/sales-contracts', ['App\Controllers\Commercial\SalesContractController', 'store']);
                $r->get('/sales-contracts/{id:\d+}', ['App\Controllers\Commercial\SalesContractController', 'show']);
                $r->get('/sales-contracts/{id:\d+}/edit', ['App\Controllers\Commercial\SalesContractController', 'edit']);
                $r->post('/sales-contracts/{id:\d+}', ['App\Controllers\Commercial\SalesContractController', 'update']);
                $r->delete('/sales-contracts/{id:\d+}', ['App\Controllers\Commercial\SalesContractController', 'destroy']);

                // Sales Orders
                $r->get('/sales-orders', ['App\Controllers\Commercial\SalesOrderController', 'index']);
                $r->get('/sales-orders/create', ['App\Controllers\Commercial\SalesOrderController', 'create']);
                $r->post('/sales-orders', ['App\Controllers\Commercial\SalesOrderController', 'store']);
                $r->get('/sales-orders/{id:\d+}', ['App\Controllers\Commercial\SalesOrderController', 'show']);
                $r->get('/sales-orders/{id:\d+}/edit', ['App\Controllers\Commercial\SalesOrderController', 'edit']);
                $r->post('/sales-orders/{id:\d+}', ['App\Controllers\Commercial\SalesOrderController', 'update']);
                $r->delete('/sales-orders/{id:\d+}', ['App\Controllers\Commercial\SalesOrderController', 'destroy']);
                
                // Commercial Invoices
                $r->get('/invoices', ['App\Controllers\Commercial\InvoiceController', 'index']);
                $r->get('/invoices/create', ['App\Controllers\Commercial\InvoiceController', 'create']);
                $r->post('/invoices', ['App\Controllers\Commercial\InvoiceController', 'store']);
                $r->get('/invoices/{id:\d+}', ['App\Controllers\Commercial\InvoiceController', 'show']);
                $r->post('/invoices/{id:\d+}/payment', ['App\Controllers\Commercial\InvoiceController', 'receivePayment']);
                $r->get('/invoices/{id:\d+}/print', ['App\Controllers\Commercial\InvoiceController', 'printInvoice']);

                // Export Documents
                $r->get('/export-documents', ['App\Controllers\Commercial\ExportController', 'index']);
                $r->post('/export-documents', ['App\Controllers\Commercial\ExportController', 'store']);
                $r->get('/export-documents/{id:\d+}', ['App\Controllers\Commercial\ExportController', 'show']);
                $r->get('/export', ['App\Controllers\Commercial\ExportController', 'index']);

                // Letters of Credit
                $r->get('/letters-of-credit', ['App\Controllers\Commercial\LetterOfCreditController', 'index']);
                $r->post('/letters-of-credit', ['App\Controllers\Commercial\LetterOfCreditController', 'store']);
            });

            // Production Routes
            $r->addGroup('/production', function (RouteCollector $r) {
                $r->get('', ['App\Controllers\Production\ProductionController', 'index']);
                $r->get('/dashboard', ['App\Controllers\Production\ProductionController', 'index']);

                // Planning
                $r->get('/planning', ['App\Controllers\Production\PlanningController', 'index']);
                $r->get('/planning/create', ['App\Controllers\Production\PlanningController', 'create']);
                $r->get('/planning/line-loading', ['App\Controllers\Production\PlanningController', 'lineLoading']);
                $r->get('/planning/capacity-report', ['App\Controllers\Production\PlanningController', 'capacityReport']);

                // Capacity
                $r->get('/capacity', ['App\Controllers\Production\CapacityController', 'index']);

                // Cutting
                $r->get('/cutting', ['App\Controllers\Production\CuttingController', 'index']);
                $r->get('/cutting/create', ['App\Controllers\Production\CuttingController', 'create']);
                $r->post('/cutting/store', ['App\Controllers\Production\CuttingController', 'store']);
                $r->get('/cutting/bundles/{id:\d+}', ['App\Controllers\Production\CuttingController', 'bundles']);
                $r->get('/cutting/generate-bundles/{id:\d+}', ['App\Controllers\Production\CuttingController', 'generateBundles']);

                // Sewing
                $r->get('/sewing', ['App\Controllers\Production\SewingController', 'index']);
                $r->get('/sewing/lines', ['App\Controllers\Production\SewingController', 'lines']);
                $r->get('/sewing/create', ['App\Controllers\Production\SewingController', 'create']);
                $r->post('/sewing/store', ['App\Controllers\Production\SewingController', 'store']);
                $r->get('/sewing/edit/{id:\d+}', ['App\Controllers\Production\SewingController', 'edit']);
                $r->get('/sewing/performance', ['App\Controllers\Production\SewingController', 'performance']);

                // Finishing
                $r->get('/finishing', ['App\Controllers\Production\FinishingController', 'index']);
                $r->get('/finishing/{tab}', ['App\Controllers\Production\FinishingController', 'showTab']);

                // Quality
                $r->get('/quality', ['App\Controllers\Production\QualityController', 'index']);
                $r->get('/quality/{tab}', ['App\Controllers\Production\QualityController', 'showTab']);

                // Packing
                $r->get('/packing', ['App\Controllers\Production\PackingController', 'plans']);
                $r->get('/packing/plans', ['App\Controllers\Production\PackingController', 'plans']);
                $r->get('/packing/create', ['App\Controllers\Production\PackingController', 'createPlan']);
                $r->get('/packing/cartons', ['App\Controllers\Production\PackingController', 'cartons']);

                // Washing
                $r->get('/washing', ['App\Controllers\Production\WashingController', 'batches']);
                $r->get('/washing/create', ['App\Controllers\Production\WashingController', 'createBatch']);
            });


            // Merchandising Routes
            $r->addGroup('/merchandising', function (RouteCollector $r) {
                $r->get('/dashboard', ['App\Controllers\Merchandising\MerchandisingDashboardController', 'index']);
                
                $r->get('/tna', ['App\Controllers\Merchandising\TnaController', 'index']);
                $r->get('/tna/{poId:\d+}', ['App\Controllers\Merchandising\TnaController', 'show']);
                $r->post('/tna/update-milestone/{id:\d+}', ['App\Controllers\Merchandising\TnaController', 'updateMilestone']);

                $r->get('/tech-packs', ['App\Controllers\Merchandising\StyleController', 'techPacksIndex']);
                $r->get('/styles/{id:\d+}/tech-pack', ['App\Controllers\Merchandising\StyleController', 'techPack']);

                $r->get('/bulk-costing', ['App\Controllers\Merchandising\BulkCostingController', 'index']);
                $r->get('/bulk-costing/create', ['App\Controllers\Merchandising\BulkCostingController', 'create']);
                $r->post('/bulk-costing', ['App\Controllers\Merchandising\BulkCostingController', 'store']);
                $r->get('/bulk-costing/{id:\d+}', ['App\Controllers\Merchandising\BulkCostingController', 'show']);
                $r->get('/bulk-costing/{id:\d+}/edit', ['App\Controllers\Merchandising\BulkCostingController', 'edit']);
                $r->get('/bulk-costing/{id:\d+}/report', ['App\Controllers\Merchandising\BulkCostingController', 'report']);
                $r->put('/bulk-costing/{id:\d+}', ['App\Controllers\Merchandising\BulkCostingController', 'update']);
                $r->delete('/bulk-costing/{id:\d+}', ['App\Controllers\Merchandising\BulkCostingController', 'destroy']);

                // Costing aliases matching sidebar & views
                $r->get('/costing', ['App\Controllers\Merchandising\BulkCostingController', 'index']);
                $r->get('/costing/create', ['App\Controllers\Merchandising\BulkCostingController', 'create']);
                $r->post('/costing', ['App\Controllers\Merchandising\BulkCostingController', 'store']);
                $r->get('/costing/{id:\d+}', ['App\Controllers\Merchandising\BulkCostingController', 'show']);
                $r->get('/costing/{id:\d+}/edit', ['App\Controllers\Merchandising\BulkCostingController', 'edit']);
                $r->get('/costing/{id:\d+}/report', ['App\Controllers\Merchandising\BulkCostingController', 'report']);
                $r->put('/costing/{id:\d+}', ['App\Controllers\Merchandising\BulkCostingController', 'update']);
                $r->delete('/costing/{id:\d+}', ['App\Controllers\Merchandising\BulkCostingController', 'destroy']);

                $r->get('/bom', ['App\Controllers\Merchandising\BomController', 'index']);
                $r->post('/bom', ['App\Controllers\Merchandising\BomController', 'store']);
                $r->get('/bom/{id:\d+}', ['App\Controllers\Merchandising\BomController', 'show']);
                $r->put('/bom/{id:\d+}', ['App\Controllers\Merchandising\BomController', 'update']);
                $r->delete('/bom/{id:\d+}', ['App\Controllers\Merchandising\BomController', 'destroy']);

                $r->get('/production-orders', ['App\Controllers\Merchandising\ProductionOrderController', 'index']);
                $r->post('/production-orders/store', ['App\Controllers\Merchandising\ProductionOrderController', 'store']);
                $r->get('/production-orders/show/{poId:\d+}', ['App\Controllers\Merchandising\ProductionOrderController', 'show']);
                $r->post('/production-orders/update/{poId:\d+}', ['App\Controllers\Merchandising\ProductionOrderController', 'update']);
                $r->post('/production-orders/update-status/{poId:\d+}', ['App\Controllers\Merchandising\ProductionOrderController', 'updateStatus']);
                $r->post('/production-orders/delete/{poId:\d+}', ['App\Controllers\Merchandising\ProductionOrderController', 'destroy']);
                $r->get('/production-orders/style-details/{styleId:\d+}', ['App\Controllers\Merchandising\ProductionOrderController', 'getStyleDetails']);
                $r->get('/production-orders/get-cities/{countryId:\d+}', ['App\Controllers\Merchandising\ProductionOrderController', 'getCities']);
                $r->get('/sampling', ['App\Controllers\Merchandising\SamplingController', 'index']);
                $r->post('/sampling', ['App\Controllers\Merchandising\SamplingController', 'store']);
                $r->get('/sampling/{id:\d+}', ['App\Controllers\Merchandising\SamplingController', 'show']);
                $r->put('/sampling/{id:\d+}', ['App\Controllers\Merchandising\SamplingController', 'update']);
                $r->post('/sampling/{id:\d+}/status', ['App\Controllers\Merchandising\SamplingController', 'updateStatus']);
                $r->delete('/sampling/{id:\d+}', ['App\Controllers\Merchandising\SamplingController', 'destroy']);

                $r->get('/size-ranges', ['App\Controllers\Merchandising\SizeRangeController', 'index']);
                $r->post('/size-ranges', ['App\Controllers\Merchandising\SizeRangeController', 'store']);
                $r->get('/size-ranges/{id:\d+}', ['App\Controllers\Merchandising\SizeRangeController', 'show']);
                $r->put('/size-ranges/{id:\d+}', ['App\Controllers\Merchandising\SizeRangeController', 'update']);
                $r->delete('/size-ranges/{id:\d+}', ['App\Controllers\Merchandising\SizeRangeController', 'destroy']);
                $r->post('/size-ranges/{id:\d+}/breakdowns', ['App\Controllers\Merchandising\SizeRangeController', 'storeBreakdown']);
                $r->put('/size-ranges/breakdowns/{breakdownId:\d+}', ['App\Controllers\Merchandising\SizeRangeController', 'updateBreakdown']);
                $r->delete('/size-ranges/breakdowns/{breakdownId:\d+}', ['App\Controllers\Merchandising\SizeRangeController', 'destroyBreakdown']);

                $r->get('/styles', ['App\Controllers\Merchandising\StyleController', 'index']);
                $r->post('/styles', ['App\Controllers\Merchandising\StyleController', 'store']);
                $r->get('/styles/download-template', ['App\Controllers\Merchandising\StyleController', 'downloadTemplate']);
                $r->post('/styles/bulk-upload', ['App\Controllers\Merchandising\StyleController', 'bulkUpload']);
                $r->post('/styles/bulk-store', ['App\Controllers\Merchandising\StyleController', 'bulkStore']);
                $r->get('/styles/{id:\d+}', ['App\Controllers\Merchandising\StyleController', 'show']);
                $r->put('/styles/{id:\d+}', ['App\Controllers\Merchandising\StyleController', 'update']);
                $r->delete('/styles/{id:\d+}', ['App\Controllers\Merchandising\StyleController', 'destroy']);

                $r->get('/purchase-requests', ['App\Controllers\Merchandising\PurchaseRequestController', 'index']);
                $r->post('/purchase-requests', ['App\Controllers\Merchandising\PurchaseRequestController', 'store']);
                $r->get('/purchase-requests/{id:\d+}', ['App\Controllers\Merchandising\PurchaseRequestController', 'show']);
                $r->put('/purchase-requests/{id:\d+}', ['App\Controllers\Merchandising\PurchaseRequestController', 'update']);
                $r->post('/purchase-requests/{id:\d+}/status', ['App\Controllers\Merchandising\PurchaseRequestController', 'updateStatus']);
                $r->delete('/purchase-requests/{id:\d+}', ['App\Controllers\Merchandising\PurchaseRequestController', 'destroy']);

                $r->get('/transfer', ['App\Controllers\Merchandising\TransferController', 'index']);
                $r->post('/transfer', ['App\Controllers\Merchandising\TransferController', 'store']);
                $r->get('/transfer/history', ['App\Controllers\Merchandising\TransferController', 'history']);
                $r->get('/transfer/{id:\d+}', ['App\Controllers\Merchandising\TransferController', 'show']);
                $r->get('/transfer/skus', ['App\Controllers\Merchandising\TransferController', 'getPOSKUs']);
            });

            // Transport Routes
            $r->addGroup('/transport', function (RouteCollector $r) {
                // Parts Inventory
                $r->get('/parts-inventory', ['App\Controllers\Transport\PartsInventoryController', 'index']);
                $r->post('/parts-inventory', ['App\Controllers\Transport\PartsInventoryController', 'store']);
                $r->get('/parts-inventory/{id:\d+}', ['App\Controllers\Transport\PartsInventoryController', 'show']);
                $r->put('/parts-inventory/{id:\d+}', ['App\Controllers\Transport\PartsInventoryController', 'update']);
                $r->delete('/parts-inventory/{id:\d+}', ['App\Controllers\Transport\PartsInventoryController', 'destroy']);

                // Vehicles
                $r->get('/vehicles', ['App\Controllers\Transport\TransportController', 'vehicles']);
                $r->get('/vehicles/create', ['App\Controllers\Transport\TransportController', 'createVehicle']);
                $r->post('/vehicles', ['App\Controllers\Transport\TransportController', 'storeVehicle']);
                
                // Routes
                $r->get('/routes', ['App\Controllers\Transport\TransportController', 'routes']);
                
                // Requisition & Seat Allotment & Maintenance
                $r->get('/vehicle-requisition', ['App\Controllers\Transport\TransportController', 'requisition']);
                $r->get('/employee-seat-allotment', ['App\Controllers\Transport\TransportController', 'seatAllotment']);
                $r->get('/maintenance', ['App\Controllers\Transport\TransportController', 'maintenance']);
            });

            // Procurement routes
            $r->addGroup('/procurement', function ($r) {
                $r->get('/purchase-orders', ['App\Controllers\Procurement\PurchaseOrderController', 'index']);
                $r->post('/purchase-orders', ['App\Controllers\Procurement\PurchaseOrderController', 'store']);
                $r->get('/purchase-orders/{id:\d+}', ['App\Controllers\Procurement\PurchaseOrderController', 'show']);
                $r->put('/purchase-orders/{id:\d+}', ['App\Controllers\Procurement\PurchaseOrderController', 'update']);
                $r->post('/purchase-orders/{id:\d+}/status', ['App\Controllers\Procurement\PurchaseOrderController', 'updateStatus']);
                $r->delete('/purchase-orders/{id:\d+}', ['App\Controllers\Procurement\PurchaseOrderController', 'destroy']);

                $r->get('/vendors', ['App\Controllers\Procurement\VendorController', 'index']);
                $r->post('/vendors', ['App\Controllers\Procurement\VendorController', 'index']);
                $r->get('/vendors/create', ['App\Controllers\Procurement\VendorController', 'create']);
                $r->post('/vendors/create', ['App\Controllers\Procurement\VendorController', 'store']);
                $r->post('/vendors/store', ['App\Controllers\Procurement\VendorController', 'store']);
                $r->get('/vendors/edit/{id:\d+}', ['App\Controllers\Procurement\VendorController', 'edit']);
                $r->post('/vendors/edit/{id:\d+}', ['App\Controllers\Procurement\VendorController', 'update']);
                $r->post('/vendors/update', ['App\Controllers\Procurement\VendorController', 'update']);
                $r->post('/vendors/delete', ['App\Controllers\Procurement\VendorController', 'destroy']);
                $r->get('/vendors/{id:\d+}/edit', ['App\Controllers\Procurement\VendorController', 'edit']);
                $r->post('/vendors/{id:\d+}', ['App\Controllers\Procurement\VendorController', 'update']);
                $r->post('/vendors/{id:\d+}/delete', ['App\Controllers\Procurement\VendorController', 'destroy']);
            });

            // Inventory routes
            $r->addGroup('/inventory', function ($r) {
                $r->get('/dashboard', ['App\Controllers\Inventory\StoreDashboardController', 'index']);
                $r->get('/items', ['App\Controllers\Inventory\ItemMasterController', 'index']);
                $r->post('/items/store', ['App\Controllers\Inventory\ItemMasterController', 'store']);
                $r->post('/items/category', ['App\Controllers\Inventory\ItemMasterController', 'storeCategory']);
                $r->post('/items/opening-balance', ['App\Controllers\Inventory\ItemMasterController', 'updateOpeningBalance']);
                
                $r->get('/warehouses', ['App\Controllers\Inventory\WarehouseController', 'index']);
                $r->post('/warehouses/store', ['App\Controllers\Inventory\WarehouseController', 'storeWarehouse']);
                $r->post('/warehouses/room', ['App\Controllers\Inventory\WarehouseController', 'storeRoom']);
                $r->post('/warehouses/rack', ['App\Controllers\Inventory\WarehouseController', 'storeRack']);
                
                $r->get('/transactions', ['App\Controllers\Inventory\StockTransactionController', 'index']);
                $r->post('/transactions/grn', ['App\Controllers\Inventory\StockTransactionController', 'storeGRN']);
                $r->post('/transactions/requisition', ['App\Controllers\Inventory\StockTransactionController', 'storeRequisition']);
                $r->post('/transactions/issue', ['App\Controllers\Inventory\StockTransactionController', 'storeIssue']);
                $r->post('/transactions/transfer', ['App\Controllers\Inventory\StockTransactionController', 'storeTransfer']);
                $r->post('/transactions/adjustment', ['App\Controllers\Inventory\StockTransactionController', 'storeAdjustment']);
                
                $r->get('/production', ['App\Controllers\Inventory\ProductionStoreController', 'index']);
                $r->post('/production/inspection', ['App\Controllers\Inventory\ProductionStoreController', 'storeInspection']);
                
                $r->get('/control', ['App\Controllers\Inventory\InventoryControlController', 'index']);
                $r->post('/control/approval', ['App\Controllers\Inventory\InventoryControlController', 'processApproval']);
                $r->get('/control/barcode', ['App\Controllers\Inventory\InventoryControlController', 'generateBarcode']);
                
                // Keep compatibility routes
                $r->get('/stock', ['App\Controllers\Inventory\StockController', 'index']);
                $r->post('/stock', ['App\Controllers\Inventory\StockController', 'store']);
                $r->get('/stock/{id:\d+}', ['App\Controllers\Inventory\StockController', 'show']);
                $r->post('/stock/{id:\d+}/adjust', ['App\Controllers\Inventory\StockController', 'adjust']);
                $r->delete('/stock/{id:\d+}', ['App\Controllers\Inventory\StockController', 'destroy']);
                $r->get('/gate-passes', ['App\Controllers\Inventory\GatePassController', 'index']);
                $r->get('/gate-passes/create', ['App\Controllers\Inventory\GatePassController', 'create']);
                $r->post('/gate-passes/store', ['App\Controllers\Inventory\GatePassController', 'store']);
                $r->get('/gate-passes/show/{id:\d+}', ['App\Controllers\Inventory\GatePassController', 'show']);
                $r->post('/gate-passes/delete/{id:\d+}', ['App\Controllers\Inventory\GatePassController', 'destroy']);
            });


            $r->addGroup('/finance', function ($r) {
                // Dashboard
                $r->get('', ['App\Controllers\Finance\FinanceDashboardController', 'index']);
                $r->get('/', ['App\Controllers\Finance\FinanceDashboardController', 'index']);
                $r->get('/dashboard', ['App\Controllers\Finance\FinanceDashboardController', 'index']);

                // Chart of Accounts & Daybook
                $r->get('/accounts', ['App\Controllers\Finance\ChartOfAccountsController', 'index']);
                $r->post('/accounts/store', ['App\Controllers\Finance\ChartOfAccountsController', 'storeAccount']);
                $r->post('/accounts/opening-balance', ['App\Controllers\Finance\ChartOfAccountsController', 'storeOpeningBalance']);
                $r->post('/accounts/fiscal-year', ['App\Controllers\Finance\ChartOfAccountsController', 'storeFiscalYear']);

                // Vouchers
                $r->get('/vouchers', ['App\Controllers\Finance\FinanceVoucherController', 'index']);
                $r->post('/vouchers/store', ['App\Controllers\Finance\FinanceVoucherController', 'store']);
                $r->post('/vouchers/approve', ['App\Controllers\Finance\FinanceVoucherController', 'approve']);

                // Cash & Bank
                $r->get('/cash-bank', ['App\Controllers\Finance\CashBankController', 'index']);
                $r->post('/cash-bank/transfer', ['App\Controllers\Finance\CashBankController', 'storeTransfer']);
                $r->post('/cash-bank/cheque', ['App\Controllers\Finance\CashBankController', 'storeCheque']);
                $r->post('/cash-bank/reconciliation', ['App\Controllers\Finance\CashBankController', 'storeReconciliation']);

                // Subledgers, Cost & Profit Centers
                $r->get('/subledgers', ['App\Controllers\Finance\FinanceSubledgerController', 'index']);
                $r->post('/subledgers/receipt', ['App\Controllers\Finance\FinanceSubledgerController', 'storeReceipt']);
                $r->post('/subledgers/payment', ['App\Controllers\Finance\FinanceSubledgerController', 'storePayment']);
                $r->post('/subledgers/cost-center', ['App\Controllers\Finance\FinanceSubledgerController', 'storeCostCenter']);

                // General Ledger
                $r->get('/general-ledger', ['App\Controllers\Finance\GeneralLedgerController', 'index']);
                $r->post('/general-ledger/store', ['App\Controllers\Finance\GeneralLedgerController', 'store']);
                $r->post('/general-ledger/journal', ['App\Controllers\Finance\GeneralLedgerController', 'storeJournal']);
                $r->get('/general-ledger/show/{id:\d+}', ['App\Controllers\Finance\GeneralLedgerController', 'show']);
                $r->post('/general-ledger/delete/{id:\d+}', ['App\Controllers\Finance\GeneralLedgerController', 'destroy']);

                // Financial Reports, Statements & Budgets
                $r->get('/reports', ['App\Controllers\Finance\FinancialReportsController', 'index']);
                $r->get('/reports/trial-balance', ['App\Controllers\Finance\FinancialReportsController', 'trialBalance']);
                $r->get('/reports/profit-and-loss', ['App\Controllers\Finance\FinancialReportsController', 'profitAndLoss']);
                $r->get('/reports/balance-sheet', ['App\Controllers\Finance\FinancialReportsController', 'balanceSheet']);
                $r->get('/reports/cash-book', ['App\Controllers\Finance\FinancialReportsController', 'cashBook']);
                $r->post('/reports/depreciation', ['App\Controllers\Finance\FinancialReportsController', 'postDepreciation']);

                // Backward-compatibility General Ledger Aliases
                $r->get('/general-ledger/trial-balance', ['App\Controllers\Finance\FinancialReportsController', 'trialBalance']);
                $r->get('/general-ledger/profit-and-loss', ['App\Controllers\Finance\FinancialReportsController', 'profitAndLoss']);
                $r->get('/general-ledger/balance-sheet', ['App\Controllers\Finance\FinancialReportsController', 'balanceSheet']);
                $r->get('/general-ledger/cash-book', ['App\Controllers\Finance\FinancialReportsController', 'cashBook']);
                $r->post('/reports/budget', ['App\Controllers\Finance\FinancialReportsController', 'storeBudget']);
                $r->post('/reports/vat', ['App\Controllers\Finance\FinancialReportsController', 'storeVAT']);
            });

            // Master Library Routes
            $r->addGroup('/master_library', function (RouteCollector $r) {
                $r->get('', ['App\Controllers\MasterLibrary\MasterLibraryController', 'index']);
                
                // Countries
                $r->get('/countries', ['App\Controllers\MasterLibrary\CountryController', 'index']);
                $r->get('/countries/create', ['App\Controllers\MasterLibrary\CountryController', 'create']);
                $r->post('/countries', ['App\Controllers\MasterLibrary\CountryController', 'store']);
                $r->get('/countries/{id:\d+}/edit', ['App\Controllers\MasterLibrary\CountryController', 'edit']);
                $r->post('/countries/{id:\d+}', ['App\Controllers\MasterLibrary\CountryController', 'update']);
                $r->post('/countries/{id:\d+}/delete', ['App\Controllers\MasterLibrary\CountryController', 'destroy']);

                // Cities
                $r->get('/cities', ['App\Controllers\MasterLibrary\CityController', 'index']);
                $r->get('/cities/create', ['App\Controllers\MasterLibrary\CityController', 'create']);
                $r->post('/cities', ['App\Controllers\MasterLibrary\CityController', 'store']);
                $r->get('/cities/{id:\d+}/edit', ['App\Controllers\MasterLibrary\CityController', 'edit']);
                $r->post('/cities/{id:\d+}', ['App\Controllers\MasterLibrary\CityController', 'update']);
                $r->post('/cities/{id:\d+}/delete', ['App\Controllers\MasterLibrary\CityController', 'destroy']);

                // Banks
                $r->get('/banks', ['App\Controllers\MasterLibrary\BankController', 'index']);
                $r->get('/banks/create', ['App\Controllers\MasterLibrary\BankController', 'create']);
                $r->post('/banks', ['App\Controllers\MasterLibrary\BankController', 'store']);
                $r->get('/banks/{id:\d+}/edit', ['App\Controllers\MasterLibrary\BankController', 'edit']);
                $r->post('/banks/{id:\d+}', ['App\Controllers\MasterLibrary\BankController', 'update']);
                $r->post('/banks/{id:\d+}/delete', ['App\Controllers\MasterLibrary\BankController', 'destroy']);

                // Currencies
                $r->get('/currencies', ['App\Controllers\MasterLibrary\CurrencyController', 'index']);
                $r->get('/currencies/create', ['App\Controllers\MasterLibrary\CurrencyController', 'create']);
                $r->post('/currencies', ['App\Controllers\MasterLibrary\CurrencyController', 'store']);
                $r->get('/currencies/{id:\d+}/edit', ['App\Controllers\MasterLibrary\CurrencyController', 'edit']);
                $r->post('/currencies/{id:\d+}', ['App\Controllers\MasterLibrary\CurrencyController', 'update']);
                $r->post('/currencies/{id:\d+}/delete', ['App\Controllers\MasterLibrary\CurrencyController', 'destroy']);

                // Factories
                $r->get('/factories', ['App\Controllers\MasterLibrary\FactoryController', 'index']);
                $r->get('/factories/create', ['App\Controllers\MasterLibrary\FactoryController', 'create']);
                $r->post('/factories', ['App\Controllers\MasterLibrary\FactoryController', 'store']);
                $r->get('/factories/{id:\d+}/edit', ['App\Controllers\MasterLibrary\FactoryController', 'edit']);
                $r->post('/factories/{id:\d+}', ['App\Controllers\MasterLibrary\FactoryController', 'update']);
                $r->post('/factories/{id:\d+}/delete', ['App\Controllers\MasterLibrary\FactoryController', 'destroy']);

                // Stores
                $r->get('/stores', ['App\Controllers\MasterLibrary\StoreController', 'index']);
                $r->get('/stores/create', ['App\Controllers\MasterLibrary\StoreController', 'create']);
                $r->post('/stores', ['App\Controllers\MasterLibrary\StoreController', 'store']);
                $r->get('/stores/{id:\d+}/edit', ['App\Controllers\MasterLibrary\StoreController', 'edit']);
                $r->post('/stores/{id:\d+}', ['App\Controllers\MasterLibrary\StoreController', 'update']);
                $r->post('/stores/{id:\d+}/delete', ['App\Controllers\MasterLibrary\StoreController', 'destroy']);

                // Store Locations
                $r->get('/store_locations', ['App\Controllers\MasterLibrary\StoreLocationController', 'index']);
                $r->get('/store_locations/create', ['App\Controllers\MasterLibrary\StoreLocationController', 'create']);
                $r->post('/store_locations/create', ['App\Controllers\MasterLibrary\StoreLocationController', 'store']);
                $r->post('/store_locations/store', ['App\Controllers\MasterLibrary\StoreLocationController', 'store']);
                $r->post('/store_locations', ['App\Controllers\MasterLibrary\StoreLocationController', 'store']);
                $r->get('/store_locations/edit/{id:\d+}', ['App\Controllers\MasterLibrary\StoreLocationController', 'edit']);
                $r->post('/store_locations/edit/{id:\d+}', ['App\Controllers\MasterLibrary\StoreLocationController', 'update']);
                $r->get('/store_locations/{id:\d+}/edit', ['App\Controllers\MasterLibrary\StoreLocationController', 'edit']);
                $r->post('/store_locations/{id:\d+}', ['App\Controllers\MasterLibrary\StoreLocationController', 'update']);
                $r->post('/store_locations/delete', ['App\Controllers\MasterLibrary\StoreLocationController', 'destroy']);
                $r->post('/store_locations/{id:\d+}/delete', ['App\Controllers\MasterLibrary\StoreLocationController', 'destroy']);
                $r->get('/store_locations/get-stores', ['App\Controllers\MasterLibrary\StoreLocationController', 'getStoresByFactory']);
                $r->get('/store_locations/get-locations', ['App\Controllers\MasterLibrary\StoreLocationController', 'getLocationsByStore']);

                // Item Categories
                $r->get('/item_categories', ['App\Controllers\MasterLibrary\ItemCategoryController', 'index']);
                $r->get('/item_categories/create', ['App\Controllers\MasterLibrary\ItemCategoryController', 'create']);
                $r->post('/item_categories', ['App\Controllers\MasterLibrary\ItemCategoryController', 'store']);
                $r->get('/item_categories/{id:\d+}/edit', ['App\Controllers\MasterLibrary\ItemCategoryController', 'edit']);
                $r->post('/item_categories/{id:\d+}', ['App\Controllers\MasterLibrary\ItemCategoryController', 'update']);
                $r->post('/item_categories/{id:\d+}/delete', ['App\Controllers\MasterLibrary\ItemCategoryController', 'destroy']);

                // Production Floors
                $r->get('/production_floors', ['App\Controllers\MasterLibrary\ProductionFloorController', 'index']);
                $r->get('/production_floors/create', ['App\Controllers\MasterLibrary\ProductionFloorController', 'create']);
                $r->post('/production_floors', ['App\Controllers\MasterLibrary\ProductionFloorController', 'store']);
                $r->get('/production_floors/{id:\d+}/edit', ['App\Controllers\MasterLibrary\ProductionFloorController', 'edit']);
                $r->post('/production_floors/{id:\d+}', ['App\Controllers\MasterLibrary\ProductionFloorController', 'update']);
                $r->post('/production_floors/{id:\d+}/delete', ['App\Controllers\MasterLibrary\ProductionFloorController', 'destroy']);
                $r->get('/production_floors/get-floors', ['App\Controllers\MasterLibrary\ProductionFloorController', 'getFloorsByLocation']);

                // Store Rooms
                $r->get('/store_rooms', ['App\Controllers\MasterLibrary\StoreRoomController', 'index']);
                $r->get('/store_rooms/create', ['App\Controllers\MasterLibrary\StoreRoomController', 'create']);
                $r->post('/store_rooms/create', ['App\Controllers\MasterLibrary\StoreRoomController', 'store']);
                $r->post('/store_rooms/store', ['App\Controllers\MasterLibrary\StoreRoomController', 'store']);
                $r->post('/store_rooms', ['App\Controllers\MasterLibrary\StoreRoomController', 'store']);
                $r->get('/store_rooms/edit/{id:\d+}', ['App\Controllers\MasterLibrary\StoreRoomController', 'edit']);
                $r->post('/store_rooms/edit/{id:\d+}', ['App\Controllers\MasterLibrary\StoreRoomController', 'update']);
                $r->get('/store_rooms/{id:\d+}/edit', ['App\Controllers\MasterLibrary\StoreRoomController', 'edit']);
                $r->post('/store_rooms/{id:\d+}', ['App\Controllers\MasterLibrary\StoreRoomController', 'update']);
                $r->post('/store_rooms/delete', ['App\Controllers\MasterLibrary\StoreRoomController', 'destroy']);
                $r->post('/store_rooms/{id:\d+}/delete', ['App\Controllers\MasterLibrary\StoreRoomController', 'destroy']);
                $r->get('/store_rooms/get-rooms', ['App\Controllers\MasterLibrary\StoreRoomController', 'getRoomsByFloor']);

                // Store Racks
                $r->get('/store_racks', ['App\Controllers\MasterLibrary\StoreRackController', 'index']);
                $r->get('/store_racks/create', ['App\Controllers\MasterLibrary\StoreRackController', 'create']);
                $r->post('/store_racks/create', ['App\Controllers\MasterLibrary\StoreRackController', 'store']);
                $r->post('/store_racks/store', ['App\Controllers\MasterLibrary\StoreRackController', 'store']);
                $r->post('/store_racks', ['App\Controllers\MasterLibrary\StoreRackController', 'store']);
                $r->get('/store_racks/edit/{id:\d+}', ['App\Controllers\MasterLibrary\StoreRackController', 'edit']);
                $r->post('/store_racks/edit/{id:\d+}', ['App\Controllers\MasterLibrary\StoreRackController', 'update']);
                $r->get('/store_racks/{id:\d+}/edit', ['App\Controllers\MasterLibrary\StoreRackController', 'edit']);
                $r->post('/store_racks/{id:\d+}', ['App\Controllers\MasterLibrary\StoreRackController', 'update']);
                $r->post('/store_racks/delete', ['App\Controllers\MasterLibrary\StoreRackController', 'destroy']);
                $r->post('/store_racks/{id:\d+}/delete', ['App\Controllers\MasterLibrary\StoreRackController', 'destroy']);

                // Production Lines
                $r->get('/production_lines', ['App\Controllers\MasterLibrary\ProductionLineController', 'index']);
                $r->get('/production_lines/create', ['App\Controllers\MasterLibrary\ProductionLineController', 'create']);
                $r->post('/production_lines', ['App\Controllers\MasterLibrary\ProductionLineController', 'store']);
                $r->get('/production_lines/{id:\d+}/edit', ['App\Controllers\MasterLibrary\ProductionLineController', 'edit']);
                $r->post('/production_lines/{id:\d+}', ['App\Controllers\MasterLibrary\ProductionLineController', 'update']);
                $r->post('/production_lines/{id:\d+}/delete', ['App\Controllers\MasterLibrary\ProductionLineController', 'destroy']);

                // Trims Costing Template
                $r->get('/trims_costing_template', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'index']);
                $r->get('/trims_costing_template/create', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'create']);
                $r->post('/trims_costing_template', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'store']);
                $r->get('/trims_costing_template/{id:\d+}/edit', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'edit']);
                $r->post('/trims_costing_template/{id:\d+}', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'update']);
                $r->post('/trims_costing_template/{id:\d+}/delete', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'destroy']);
                
                // API endpoints for AJAX operations
                $r->get('/trims_costing_template/show', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'show']);
                $r->get('/trims_costing_template/templates-by-buyer', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'getTemplatesByBuyer']);
                $r->post('/trims_costing_template/copy', ['App\Controllers\MasterLibrary\TrimsCostingTemplateController', 'copyTemplate']);

                // Employee Departments
                $r->get('/employee_departments', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'index']);
                $r->post('/employee_departments', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'index']);
                $r->get('/employee_departments/create', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'create']);
                $r->post('/employee_departments/create', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'store']);
                $r->post('/employee_departments/store', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'store']);
                $r->get('/employee_departments/edit/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'edit']);
                $r->post('/employee_departments/edit/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'update']);
                $r->post('/employee_departments/update', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'update']);
                $r->post('/employee_departments/delete', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'destroy']);
                $r->get('/employee_departments/{id:\d+}/edit', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'edit']);
                $r->post('/employee_departments/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'update']);
                $r->post('/employee_departments/{id:\d+}/delete', ['App\Controllers\MasterLibrary\EmployeeDepartmentController', 'destroy']);

                // Employee Sections
                $r->get('/employee_sections', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'index']);
                $r->post('/employee_sections', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'index']);
                $r->get('/employee_sections/create', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'create']);
                $r->post('/employee_sections/create', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'store']);
                $r->post('/employee_sections/store', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'store']);
                $r->get('/employee_sections/edit/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'edit']);
                $r->post('/employee_sections/edit/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'update']);
                $r->post('/employee_sections/update', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'update']);
                $r->post('/employee_sections/delete', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'destroy']);
                $r->get('/employee_sections/{id:\d+}/edit', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'edit']);
                $r->post('/employee_sections/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'update']);
                $r->post('/employee_sections/{id:\d+}/delete', ['App\Controllers\MasterLibrary\EmployeeSectionController', 'destroy']);

                // Employee Designations
                $r->get('/employee_designations', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'index']);
                $r->post('/employee_designations', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'index']);
                $r->get('/employee_designations/create', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'create']);
                $r->post('/employee_designations/create', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'store']);
                $r->post('/employee_designations/store', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'store']);
                $r->get('/employee_designations/edit/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'edit']);
                $r->post('/employee_designations/edit/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'update']);
                $r->post('/employee_designations/update', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'update']);
                $r->post('/employee_designations/delete', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'destroy']);
                $r->get('/employee_designations/{id:\d+}/edit', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'edit']);
                $r->post('/employee_designations/{id:\d+}', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'update']);
                $r->post('/employee_designations/{id:\d+}/delete', ['App\Controllers\MasterLibrary\EmployeeDesignationController', 'destroy']);

                // Garment Sample Types
                $r->get('/garment_sample_types', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'index']);
                $r->get('/garment_sample_types/create', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'create']);
                $r->post('/garment_sample_types/create', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'store']);
                $r->post('/garment_sample_types/store', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'store']);
                $r->post('/garment_sample_types', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'store']);
                $r->get('/garment_sample_types/edit/{id:\d+}', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'edit']);
                $r->post('/garment_sample_types/edit/{id:\d+}', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'update']);
                $r->post('/garment_sample_types/update', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'update']);
                $r->post('/garment_sample_types/delete', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'destroy']);
                
                // Compatibility with slash/id suffix formats
                $r->get('/garment_sample_types/{id:\d+}/edit', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'edit']);
                $r->post('/garment_sample_types/{id:\d+}', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'update']);
                $r->post('/garment_sample_types/{id:\d+}/delete', ['App\Controllers\MasterLibrary\GarmentSampleTypeController', 'destroy']);

                // Product Natures
                $r->get('/product_natures', ['App\Controllers\MasterLibrary\ProductNatureController', 'index']);
                $r->post('/product_natures', ['App\Controllers\MasterLibrary\ProductNatureController', 'index']);
                $r->get('/product_natures/create', ['App\Controllers\MasterLibrary\ProductNatureController', 'create']);
                $r->post('/product_natures/create', ['App\Controllers\MasterLibrary\ProductNatureController', 'store']);
                $r->post('/product_natures/store', ['App\Controllers\MasterLibrary\ProductNatureController', 'store']);
                $r->get('/product_natures/edit/{id:\d+}', ['App\Controllers\MasterLibrary\ProductNatureController', 'edit']);
                $r->post('/product_natures/edit/{id:\d+}', ['App\Controllers\MasterLibrary\ProductNatureController', 'update']);
                $r->post('/product_natures/update', ['App\Controllers\MasterLibrary\ProductNatureController', 'update']);
                $r->post('/product_natures/delete', ['App\Controllers\MasterLibrary\ProductNatureController', 'destroy']);
                $r->get('/product_natures/{id:\d+}/edit', ['App\Controllers\MasterLibrary\ProductNatureController', 'edit']);
                $r->post('/product_natures/{id:\d+}', ['App\Controllers\MasterLibrary\ProductNatureController', 'update']);
                $r->post('/product_natures/{id:\d+}/delete', ['App\Controllers\MasterLibrary\ProductNatureController', 'destroy']);

                // Product Types
                $r->get('/product_types', ['App\Controllers\MasterLibrary\ProductTypeController', 'index']);
                $r->post('/product_types', ['App\Controllers\MasterLibrary\ProductTypeController', 'index']);
                $r->get('/product_types/create', ['App\Controllers\MasterLibrary\ProductTypeController', 'create']);
                $r->post('/product_types/create', ['App\Controllers\MasterLibrary\ProductTypeController', 'store']);
                $r->post('/product_types/store', ['App\Controllers\MasterLibrary\ProductTypeController', 'store']);
                $r->get('/product_types/edit/{id:\d+}', ['App\Controllers\MasterLibrary\ProductTypeController', 'edit']);
                $r->post('/product_types/edit/{id:\d+}', ['App\Controllers\MasterLibrary\ProductTypeController', 'update']);
                $r->post('/product_types/update', ['App\Controllers\MasterLibrary\ProductTypeController', 'update']);
                $r->post('/product_types/delete', ['App\Controllers\MasterLibrary\ProductTypeController', 'destroy']);
                $r->get('/product_types/{id:\d+}/edit', ['App\Controllers\MasterLibrary\ProductTypeController', 'edit']);
                $r->post('/product_types/{id:\d+}', ['App\Controllers\MasterLibrary\ProductTypeController', 'update']);
                $r->post('/product_types/{id:\d+}/delete', ['App\Controllers\MasterLibrary\ProductTypeController', 'destroy']);

                // Garments Items
                $r->get('/garments_items', ['App\Controllers\MasterLibrary\GarmentsItemController', 'index']);
                $r->post('/garments_items', ['App\Controllers\MasterLibrary\GarmentsItemController', 'index']);
                $r->get('/garments_items/create', ['App\Controllers\MasterLibrary\GarmentsItemController', 'create']);
                $r->post('/garments_items/create', ['App\Controllers\MasterLibrary\GarmentsItemController', 'store']);
                $r->post('/garments_items/store', ['App\Controllers\MasterLibrary\GarmentsItemController', 'store']);
                $r->get('/garments_items/edit/{id:\d+}', ['App\Controllers\MasterLibrary\GarmentsItemController', 'edit']);
                $r->post('/garments_items/edit/{id:\d+}', ['App\Controllers\MasterLibrary\GarmentsItemController', 'update']);
                $r->post('/garments_items/update', ['App\Controllers\MasterLibrary\GarmentsItemController', 'update']);
                $r->post('/garments_items/delete', ['App\Controllers\MasterLibrary\GarmentsItemController', 'destroy']);
                $r->get('/garments_items/{id:\d+}/edit', ['App\Controllers\MasterLibrary\GarmentsItemController', 'edit']);
                $r->post('/garments_items/{id:\d+}/delete', ['App\Controllers\MasterLibrary\GarmentsItemController', 'destroy']);
                $r->post('/garments_items/{id:\d+}', ['App\Controllers\MasterLibrary\GarmentsItemController', 'update']);

                // Units of Measure (UOM)
                $r->get('/uoms', ['App\Controllers\MasterLibrary\UomController', 'index']);
                $r->post('/uoms', ['App\Controllers\MasterLibrary\UomController', 'index']);
                $r->get('/uoms/create', ['App\Controllers\MasterLibrary\UomController', 'create']);
                $r->post('/uoms/create', ['App\Controllers\MasterLibrary\UomController', 'store']);
                $r->post('/uoms/store', ['App\Controllers\MasterLibrary\UomController', 'store']);
                $r->get('/uoms/edit/{id:\d+}', ['App\Controllers\MasterLibrary\UomController', 'edit']);
                $r->post('/uoms/edit/{id:\d+}', ['App\Controllers\MasterLibrary\UomController', 'update']);
                $r->post('/uoms/update', ['App\Controllers\MasterLibrary\UomController', 'update']);
                $r->post('/uoms/delete', ['App\Controllers\MasterLibrary\UomController', 'destroy']);
                $r->get('/uoms/{id:\d+}/edit', ['App\Controllers\MasterLibrary\UomController', 'edit']);
                $r->post('/uoms/{id:\d+}', ['App\Controllers\MasterLibrary\UomController', 'update']);
                $r->post('/uoms/{id:\d+}/delete', ['App\Controllers\MasterLibrary\UomController', 'destroy']);
            });

            // Default route
            $r->get('/', ['App\Controllers\HomeController', 'index']);
        });
    }

    public function dispatch(string $httpMethod, string $uri): array
    {
        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);
        
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return [404, 'Not Found'];
            case Dispatcher::METHOD_NOT_ALLOWED:
                return [405, 'Method Not Allowed'];
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                return [200, $handler, $vars];
            default:
                return [404, 'Not Found']; // Handle unexpected return values
        }
    }
}
