<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ProductionLine extends Model
{
    protected string $table = 'production_lines';
    protected string $primaryKey = 'production_line_id';
    
    protected array $fillable = [
        'line_code',
        'floor_id',
        'factory_id',
        'line_name',
        'line_type',
        'capacity_per_day',
        'manager_id',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getAllWithDetails(): array
    {
        $sql = "SELECT pl.*, pf.floor_name
                FROM {$this->table} pl
                LEFT JOIN production_floors pf ON pl.floor_id = pf.floor_id
                ORDER BY pl.production_line_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
