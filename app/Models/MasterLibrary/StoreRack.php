<?php
declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class StoreRack extends Model
{
    protected string $table = 'store_racks';
    protected string $primaryKey = 'rack_id';
    
    protected array $fillable = [
        'rack_code',
        'rack_name',
        'room_id',
        'description',
        'status',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public function getAllWithDetails(): array
    {
        $sql = "SELECT r.*, rm.room_name, f.floor_name, sl.rack_name AS store_location_name, s.location_name AS store_name, fac.factory_name
                FROM {$this->table} r
                LEFT JOIN store_rooms rm ON r.room_id = rm.room_id
                LEFT JOIN production_floors f ON rm.floor_id = f.floor_id
                LEFT JOIN racks sl ON f.rack_id = sl.rack_id
                LEFT JOIN store_locations s ON sl.warehouse_id = s.location_id
                LEFT JOIN factories fac ON s.factory_id = fac.factory_id
                ORDER BY r.rack_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
