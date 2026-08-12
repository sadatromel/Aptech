<?php

namespace App\Models;

use Core\Model;

class Buyer extends Model
{
    protected string $table = 'buyers';
    protected string $primaryKey = 'buyer_id';
    
    protected array $fillable = [
        'buyer_name',
        'buyer_code',
        'country',
        'contact_person',
        'email',
        'phone',
        'address',
        'status', // active, inactive
        'created_at',
        'updated_at'
    ];
    
    public function getAllWithFilters(array $filters = []): array
    {
        $where = ['ORDER' => ['buyer_id' => 'DESC']];

        if (!empty($filters['search'])) {
            $where['OR'] = [
                'buyer_name[~]' => $filters['search'],
                'buyer_code[~]' => $filters['search'],
                'country[~]' => $filters['search']
            ];
        }

        if (!empty($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        return $this->db->select($this->table, '*', $where);
    }

    public function getStats(): array
    {
        return [
            'total' => $this->db->count($this->table),
            'active' => $this->db->count($this->table, ['status' => 'active']),
            'inactive' => $this->db->count($this->table, ['status' => 'inactive'])
        ];
    }
}
