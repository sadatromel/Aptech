<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class District extends Model
{
    protected string $table = 'districts';
    protected string $primaryKey = 'district_id';
    
    protected array $fillable = [
        'district_name',
        'district_name_bn',
        'district_code',
        'division_state',
        'country_id',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getWithCountry(int $id): ?array
    {
        return $this->db->get($this->table, [
            '[>]countries' => ['districts.country_id' => 'countries.country_id']
        ], [
            'districts.district_id',
            'districts.district_name',
            'districts.district_name_bn',
            'districts.district_code',
            'districts.division_state',
            'districts.status',
            'districts.country_id',
            'districts.created_at',
            'countries.country_name'
        ], [
            'districts.district_id' => $id
        ]);
    }

    public function getAllWithCountry(): array
    {
        return $this->db->select($this->table, [
            '[>]countries' => ['districts.country_id' => 'countries.country_id']
        ], [
            'districts.district_id',
            'districts.district_name',
            'districts.district_name_bn',
            'districts.district_code',
            'districts.division_state',
            'districts.status',
            'districts.country_id',
            'districts.created_at',
            'countries.country_name'
        ], [
            'ORDER' => ['districts.district_name' => 'ASC']
        ]);
    }
}
