<?php
declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class StoreRoom extends Model
{
    protected string $table = 'store_rooms';
    protected string $primaryKey = 'room_id';
    
    protected array $fillable = [
        'room_code',
        'room_name',
        'floor_id',
        'description',
        'status',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public function getAllWithDetails(): array
    {
        $sql = "SELECT r.*, f.floor_name, sl.rack_name AS store_location_name, s.location_name AS store_name, fac.factory_name
                FROM {$this->table} r
                LEFT JOIN production_floors f ON r.floor_id = f.floor_id
                LEFT JOIN racks sl ON f.rack_id = sl.rack_id
                LEFT JOIN store_locations s ON sl.warehouse_id = s.location_id
                LEFT JOIN factories fac ON s.factory_id = fac.factory_id
                ORDER BY r.room_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
