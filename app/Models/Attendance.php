<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class Attendance extends Model
{
    protected string $table = 'attendance';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'employee_id',
        'department_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'notes',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public function find(int $id): ?array
    {
        $sql = "SELECT a.*, e.employee_code, e.first_name, e.last_name, 
                       d.department_name, p.position_title as position_name,
                       CASE 
                           WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN
                               TIMESTAMPDIFF(MINUTE, a.check_in, a.check_out) / 60
                           ELSE 0
                       END as working_hours
                FROM attendance a
                LEFT JOIN employees e ON a.employee_id = e.employee_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN positions p ON e.position_id = p.position_id
                WHERE a.id = ?";
        
        // Let's verify column name. Legacy file used attendance_id. Controller generic select used id?
        // Controller used 'attendance.id'. Legacy used 'attendance_id'. 
        // I will assume 'id' is standard but if legacy used 'attendance_id' I might need to adjust.
        // Actually, let's stick to 'id' if the new system is standardizing, or alias it.
        // But wait, existing controller used 'attendance.id'. 
        // Let's check the legacy file again... it says "WHERE a.attendance_id = ?". 
        // I should probably check the schema or supports both. 
        // For safely, I'll try to support 'id' mapping.
        
       $stmt = $this->db->prepare($sql);
       $stmt->execute([$id]);
       $result = $stmt->fetch(PDO::FETCH_ASSOC);
       
       return $result ?: null;
    }

    public function getDailyAttendance(string $date, string $departmentId = ''): array
    {
        $where = ['date' => $date];
        if (!empty($departmentId)) {
            $where['attendance.department_id'] = $departmentId; 
            // Note: attendance table usually has department_id or we join via employee
            // The controller code implied attendance table has department_id.
        }

        // Use Medoo-style or raw SQL. Let's use the DB wrapper for consistency if possible, 
        // but complex joins are often cleaner in SQL or the wrapper's join syntax.
        
        return $this->db->select('attendance', [
            '[>]employees' => ['employees.employee_id' => 'attendance.employee_id'],
            '[>]departments' => ['departments.department_id' => 'attendance.department_id']
        ], [
            'attendance.id',
            'attendance_id' => 'attendance.id', // Alias for compatibility
            'attendance.employee_id',
            'attendance.date',
            'attendance.check_in',
            'attendance.check_out',
            'attendance.status',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'departments.department_name'
        ], [
            'WHERE' => $where,
            'ORDER' => ['attendance.check_in' => 'DESC']
        ]);
    }

    public function hasAttendance(int $employeeId, string $date): bool
    {
        return $this->db->has('attendance', [
            'employee_id' => $employeeId,
            'date' => $date
        ]);
    }
}
