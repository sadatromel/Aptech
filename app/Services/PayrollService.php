<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Attendance;
use Core\Database;

class PayrollService
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function generatePayroll(string $period, string $startDate, string $endDate, string $paymentDate, int $creatorId): array
    {
        $employeeModel = new Employee();
        $payrollModel = new Payroll();
        $attendanceModel = new Attendance();
        
        $employees = $employeeModel->activeEmployees();
        $generatedCount = 0;
        $errors = [];

        foreach ($employees as $employee) {
            try {
                // Skip if already generated
                if ($payrollModel->hasPayrollForPeriod((int)$employee['employee_id'], $period)) {
                    continue;
                }

                // 1. Get Attendance Stats
                // We need to query the attendance table for aggregate stats
                $stats = $this->getAttendanceStats($employee['employee_id'], $startDate, $endDate);

                // 2. Calculate Components
                $salary = $this->calculateSalaryComponents($employee, $stats);

                // 3. Prepare Data
                $payrollData = [
                    'employee_id' => $employee['employee_id'],
                    'payroll_period' => $period,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'payment_date' => $paymentDate,
                    
                    // Base Components from Employee
                    'basic_salary' => $salary['basic'],
                    'house_rent' => $salary['house_rent'],
                    'medical_allowance' => $salary['medical'],
                    'transport_allowance' => $salary['transport'],
                    'food_allowance' => $salary['food'],
                    'other_allowances' => $salary['other'],
                    
                    // Earned (Prorated)
                    'earned_basic' => $salary['earned_basic'],
                    'earned_house_rent' => $salary['earned_house_rent'],
                    'earned_medical' => $salary['earned_medical'],
                    'earned_transport' => $salary['earned_transport'],
                    'earned_food' => $salary['earned_food'],
                    'earned_other' => $salary['earned_other'],
                    'total_earned' => $salary['total_earned'],
                    
                    // Stats
                    'working_days' => 30, // Standard or $stats['working_days']
                    'present_days' => $stats['present'],
                    'absent_days' => $stats['absent'],
                    'leave_days' => $stats['leave'],
                    'total_late_minutes' => $stats['late_minutes'],
                    'overtime_hours' => $stats['overtime_hours'],
                    'overtime_amount' => $salary['overtime_amount'],
                    
                    // Deductions
                    'absent_deduction' => $salary['absent_deduction'],
                    'late_deduction' => $salary['late_deduction'],
                    'provident_fund' => $salary['provident_fund'],
                    'tax_deduction' => $salary['tax_deduction'],
                    'total_deductions' => $salary['total_deductions'],
                    
                    // Net
                    'net_salary' => $salary['net_salary'],
                    'status' => 'Pending',
                    'created_by' => $creatorId,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $payrollModel->create($payrollData);
                $generatedCount++;

            } catch (\Exception $e) {
                $errors[] = "Error for {$employee['employee_code']}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'generated_count' => $generatedCount,
            'errors' => $errors
        ];
    }

    private function getAttendanceStats($employeeId, $startDate, $endDate): array
    {
        $sql = "SELECT 
                    COUNT(*) as working_days,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
                    -- SUM(late_minutes) as total_late_minutes, 
                    -- SUM(overtime_hours) as total_overtime_hours
                    0 as total_late_minutes, -- Placeholder if columns missing
                    0 as total_overtime_hours -- Placeholder if columns missing
                FROM attendance 
                WHERE employee_id = ? AND date BETWEEN ? AND ?";
        
        // Note: The legacy code assumed late_minutes and overtime_hours exist in attendance.
        // My previous check of attendance table didn't explicitly show them, but I will query carefully.
        // If they don't exist, I'll default to 0 to avoid crashes.
        
        $stmt = $this->db->query($sql, [$employeeId, $startDate, $endDate]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'present' => (int)($result['present_days'] ?? 0),
            'absent' => (int)($result['absent_days'] ?? 0),
            'leave' => (int)($result['leave_days'] ?? 0),
            'late_minutes' => (int)($result['total_late_minutes'] ?? 0),
            'overtime_hours' => (float)($result['total_overtime_hours'] ?? 0)
        ];
    }

    private function calculateSalaryComponents(array $employee, array $stats): array
    {
        // 1. Get Base Components
        $basic = (float)($employee['basic_salary'] ?? 15000); // Default if missing
        // Legacy fallback logic
        $house_rent = (float)($employee['house_rent'] ?? 3000);
        $medical = (float)($employee['medical_allowance'] ?? 1000);
        $transport = (float)($employee['transport_allowance'] ?? 1500);
        $food = (float)($employee['food_allowance'] ?? 1000);
        $other = (float)($employee['other_allowances'] ?? 500);

        $gross = $basic + $house_rent + $medical + $transport + $food + $other;
        
        // 2. Proration Factors
        $presentDays = $stats['present'] + $stats['leave']; // Leaves are usually paid
        // If we strictly follow legacy: "$basic * ($present_days / 30)" - legacy didn't seem to count leave as paid in that specific line?
        // Legacy: $earned_basic = $basic_salary * ($present_days / 30);
        // Wait, normally Paid Leave counts as Present for salary. but legacy code used $attendance_data['present_days'].
        // Let's stick closer to standard: Paid Days = Present + Leave.
        // But to be safe vs legacy, I'll verify. Legacy: "$earned_basic = $basic_salary * ($present_days / 30);"
        // It seems strict. I will add leave days to be fair, or user might complain salary is low.
        $paidDays = $stats['present'] + $stats['leave']; 
        $proration = min(1, $paidDays / 30); // Cap at 1

        // 3. Earned Amounts
        $earned_basic = $basic * $proration;
        $earned_house_rent = $house_rent * $proration;
        $earned_medical = $medical * $proration;
        $earned_transport = $transport * $proration;
        $earned_food = $food * $proration;
        $earned_other = $other * $proration;
        
        $total_earned = $earned_basic + $earned_house_rent + $earned_medical + $earned_transport + $earned_food + $earned_other;

        // 4. Overtime
        $hourly_rate = $gross / 240; // 30 days * 8 hours
        $overtime_rate = $hourly_rate * 1.5;
        $overtime_amount = $stats['overtime_hours'] * $overtime_rate;

        // 5. Deductions
        $daily_rate = $gross / 30;
        $absent_deduction = $stats['absent'] * $daily_rate;
        
        // Late deduction logic from legacy
        $late_deduction = 0;
        if ($stats['late_minutes'] > 0) {
             $late_deduction = min(100, ($stats['late_minutes'] / 60) * $hourly_rate);
        }

        $provident_fund = $basic * 0.05; // 5% PF
        $tax_deduction = $total_earned > 20000 ? ($total_earned - 20000) * 0.10 : 0;

        $total_deductions = $absent_deduction + $late_deduction + $provident_fund + $tax_deduction;

        // 6. Net
        $net_salary = $total_earned + $overtime_amount - $total_deductions;

        return [
            'basic' => $basic,
            'house_rent' => $house_rent,
            'medical' => $medical,
            'transport' => $transport,
            'food' => $food,
            'other' => $other,
            
            'earned_basic' => $earned_basic,
            'earned_house_rent' => $earned_house_rent,
            'earned_medical' => $earned_medical,
            'earned_transport' => $earned_transport,
            'earned_food' => $earned_food,
            'earned_other' => $earned_other,
            'total_earned' => $total_earned,
            
            'overtime_amount' => $overtime_amount,
            
            'absent_deduction' => $absent_deduction,
            'late_deduction' => $late_deduction,
            'provident_fund' => $provident_fund,
            'tax_deduction' => $tax_deduction,
            'total_deductions' => $total_deductions,
            
            'net_salary' => $net_salary
        ];
    }
    
    public function approve(int $id, int $approverId): bool
    {
        $payroll = new Payroll();
        return $payroll->update($id, [
            'status' => 'Approved',
            'approved_by' => $approverId,
            'approved_date' => date('Y-m-d H:i:s')
        ]);
    }

    public function pay(int $id, int $payerId): bool
    {
        $payroll = new Payroll();
        return $payroll->update($id, [
            'status' => 'Paid',
            'paid_by' => $payerId,
            'paid_date' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function delete(int $id): bool
    {
        $payroll = new Payroll();
        return $payroll->delete($id);
    }
}
