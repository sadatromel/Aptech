<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class MaternityBenefit extends Model
{
    protected string $table = 'maternity_benefits';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'employee_id',
        'expected_delivery_date',
        'leave_start_date',
        'leave_end_date',
        'avg_daily_wage',
        'total_benefit_amount',
        'payment_status',
        'payment1_amount',
        'payment1_date',
        'payment2_amount',
        'payment2_date',
        'status',
        'created_by'
    ];

    /**
     * Get all maternity benefit records with employee details
     */
    public function getAllWithEmployeeDetails(): array
    {
        return $this->db->select($this->table, [
            '[>]employees' => ['maternity_benefits.employee_id' => 'employees.employee_id'],
            '[>]departments' => ['employees.department_id' => 'departments.department_id']
        ], [
            'maternity_benefits.id',
            'maternity_benefits.employee_id',
            'maternity_benefits.expected_delivery_date',
            'maternity_benefits.leave_start_date',
            'maternity_benefits.leave_end_date',
            'maternity_benefits.avg_daily_wage',
            'maternity_benefits.total_benefit_amount',
            'maternity_benefits.payment_status',
            'maternity_benefits.payment1_amount',
            'maternity_benefits.payment1_date',
            'maternity_benefits.payment2_amount',
            'maternity_benefits.payment2_date',
            'maternity_benefits.status',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'departments.department_name'
        ], [
            'ORDER' => ['maternity_benefits.created_at' => 'DESC']
        ]);
    }

    /**
     * Get a single maternity benefit record with employee details
     */
    public function getWithEmployeeDetails(int $id): ?array
    {
        $result = $this->db->select($this->table, [
            '[>]employees' => ['maternity_benefits.employee_id' => 'employees.employee_id'],
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'maternity_benefits.id',
            'maternity_benefits.employee_id',
            'maternity_benefits.expected_delivery_date',
            'maternity_benefits.leave_start_date',
            'maternity_benefits.leave_end_date',
            'maternity_benefits.avg_daily_wage',
            'maternity_benefits.total_benefit_amount',
            'maternity_benefits.payment_status',
            'maternity_benefits.payment1_amount',
            'maternity_benefits.payment1_date',
            'maternity_benefits.payment2_amount',
            'maternity_benefits.payment2_date',
            'maternity_benefits.status',
            'maternity_benefits.created_at',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'employees.basic_salary',
            'employees.house_rent',
            'employees.medical_allowance',
            'employees.transport_allowance',
            'employees.food_allowance',
            'employees.other_allowances',
            'departments.department_name',
            'positions.position_name'
        ], [
            'maternity_benefits.id' => $id
        ]);

        return !empty($result) ? $result[0] : null;
    }
}
