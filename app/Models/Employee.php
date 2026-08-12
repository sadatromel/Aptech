<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class Employee extends Model
{
    protected string $table = 'employees';
    protected string $primaryKey = 'employee_id';
    protected array $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'date_of_birth',
        'gender',
        'blood_group',
        'marital_status',
        'nationality',
        'religion',
        'nid_number',
        'passport_number',
        'present_address',
        'permanent_address',
        'city',
        'postal_code',
        'country',
        'department_id',
        'position_id',
        'worker_grade',
        'section_id',
        'line_id',
        'shift_id',
        'employment_type',
        'hire_date',
        'confirmation_date',
        'status',
        'basic_salary',
        'house_rent',
        'medical_allowance',
        'transport_allowance',
        'food_allowance',
        'other_allowances',
        'bank_name',
        'bank_account',
        'bank_branch',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'education_qualification',
        'experience_years',
        'previous_company',
        'skills',
        'notes',
        'created_by',
        'created_at',
        'updated_at'
    ];

    private ?array $data = null;

    public function find(int $id): ?array
    {
        $this->data = parent::find($id);
        return $this->data;
    }

    public function getFullName(): string
    {
        if (!$this->data) {
            return '';
        }
        return trim($this->data['first_name'] . ' ' . $this->data['last_name']);
    }

    public function getDepartment(): string
    {
        return $this->data['department'] ?? '';
    }

    public function getPosition(): string
    {
        return $this->data['position'] ?? '';
    }

    public function isActive(): bool
    {
        return ($this->data['status'] ?? 'inactive') === 'active';
    }

    public function attendance(): array
    {
        if (!$this->data) {
            return [];
        }
        
        $stmt = $this->db->prepare(
            "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 30"
        );
        $stmt->execute([$this->data['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function payroll(): array
    {
        if (!$this->data) {
            return [];
        }
        
        $stmt = $this->db->prepare(
            "SELECT * FROM payroll WHERE employee_id = ? ORDER BY pay_date DESC LIMIT 12"
        );
        $stmt->execute([$this->data['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email): ?array
    {
        $result = $this->where('email', $email)->first();
        $this->data = $result;
        return $result;
    }

    public function findByDepartment(string $department): array
    {
        return $this->where('department', $department)->get();
    }

    public function activeEmployees(): array
    {
        return $this->where('status', 'active')->orderBy('last_name')->get();
    }

    public function getDepartmentStats(): array
    {
        $sql = "
            SELECT 
                department,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                AVG(salary) as avg_salary
            FROM employees 
            GROUP BY department
            ORDER BY department
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
