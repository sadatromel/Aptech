<?php

namespace App\Models;

use Core\Model;

class Incentive extends Model
{
    protected string $table = 'employee_incentives';
    protected string $primaryKey = 'incentive_id';
    
    protected array $fillable = [
        'employee_id',
        'month',
        'incentive_type',
        'base_salary',
        'attendance_rate',
        'production_target',
        'actual_production',
        'quality_rate',
        'efficiency_rate',
        'incentive_amount',
        'total_earnings',
        'remarks',
        'created_by'
    ];
}
