<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ProductionFloor extends Model
{
    protected string $table = 'production_floors';
    protected string $primaryKey = 'floor_id';
    
    protected array $fillable = [
        'floor_code',
        'floor_name',
        'floor_incharge',
        'factory_id',
        'rack_id', // referencing racks.rack_id (Store Location)
        'manager_id',
        'area_sqft',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getAllWithDetails(): array
    {
        $sql = "SELECT pf.*, f.factory_name, s.location_name AS store_name, r.rack_name AS store_location_name
                FROM {$this->table} pf
                LEFT JOIN factories f ON pf.factory_id = f.factory_id
                LEFT JOIN racks r ON pf.rack_id = r.rack_id
                LEFT JOIN store_locations s ON r.warehouse_id = s.location_id
                ORDER BY pf.floor_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
