<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class Store extends Model
{
    protected string $table = 'store_locations';
    protected string $primaryKey = 'location_id';
    
    protected array $fillable = [
        'location_name',
        'location_code',
        'factory_id',
        'company_id',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'phone',
        'email',
        'manager_name',
        'location_type',
        'status',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public function getAllWithFactory(): array
    {
        $sql = "SELECT s.*, f.factory_name 
                FROM {$this->table} s 
                LEFT JOIN factories f ON s.factory_id = f.factory_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
