<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class Payroll extends Model
{
    protected string $table = 'payroll';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'employee_id',
        'payroll_period',
        'start_date',
        'end_date',
        'payment_date',
        // Salary Components
        'basic_salary',
        'house_rent',
        'medical_allowance',
        'transport_allowance',
        'food_allowance',
        'other_allowances',
        // Earned Components
        'earned_basic',
        'earned_house_rent',
        'earned_medical',
        'earned_transport',
        'earned_food',
        'earned_other',
        'total_earned',
        // Overtime
        'overtime_hours',
        'overtime_amount',
        // Attendance Data
        'working_days',
        'present_days',
        'absent_days',
        'leave_days',
        'total_late_minutes',
        // Deductions
        'absent_deduction',
        'late_deduction',
        'provident_fund',
        'tax_deduction',
        'total_deductions',
        // Final
        'net_salary',
        // Status
        'status',
        'created_by',
        'approved_by',
        'approved_date',
        'paid_by',
        'paid_date',
        'created_at'
    ];

    public function find(int $id): ?array
    {
        $sql = "SELECT p.*, p.id as payroll_id, e.employee_code, e.first_name, e.last_name, 
                       d.department_name, pos.position_title as position_name
                FROM payroll p
                LEFT JOIN employees e ON p.employee_id = e.employee_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN positions pos ON e.position_id = pos.position_id
                WHERE p.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    public function getByPeriod(string $period, string $departmentId = ''): array
    {
        // Period format YYYY-MM
        $where = ['p.payroll_period' => $period];
        
        if (!empty($departmentId)) {
            $where['e.department_id'] = $departmentId;
        }

        return $this->db->select('payroll p', [
            '[>]employees e' => ['p.employee_id' => 'employee_id'],
            '[>]departments d' => ['e.department_id' => 'department_id']
        ], [
            'payroll_id' => 'p.id',
            'p.employee_id',
            'p.payroll_period',
            'p.net_salary',
            'p.status',
            'p.payment_date',
            'e.employee_code',
            'e.first_name',
            'e.last_name',
            'd.department_name'
        ], [
            'WHERE' => $where,
            'ORDER' => ['e.department_id', 'e.first_name']
        ]);
    }
    
    public function hasPayrollForPeriod(int $employeeId, string $period): bool
    {
        return $this->db->has('payroll', [
            'employee_id' => $employeeId,
            'payroll_period' => $period
        ]);
    }
}
