<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class StoreLocation extends Model
{
    protected string $table = 'racks';
    protected string $primaryKey = 'rack_id';
    
    protected array $fillable = [
        'rack_code',
        'rack_name',
        'warehouse_id', // referencing store_locations.location_id
        'zone',
        'aisle',
        'level',
        'bin',
        'capacity_weight',
        'capacity_volume',
        'status',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public function getAllWithStoreAndFactory(): array
    {
        $sql = "SELECT r.*, s.location_name AS store_name, f.factory_name, f.factory_id
                FROM {$this->table} r
                LEFT JOIN store_locations s ON r.warehouse_id = s.location_id
                LEFT JOIN factories f ON s.factory_id = f.factory_id
                ORDER BY r.rack_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
