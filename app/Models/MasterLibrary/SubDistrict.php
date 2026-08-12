<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class SubDistrict extends Model
{
    protected string $table = 'sub_districts';
    protected string $primaryKey = 'sub_district_id';
    
    protected array $fillable = [
        'sub_district_name',
        'sub_district_name_bn',
        'sub_district_code',
        'postal_code',
        'district_id',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getWithDistrict(int $id): ?array
    {
        return $this->db->get($this->table, [
            '[>]districts' => ['sub_districts.district_id' => 'districts.district_id'],
            '[>]countries' => ['districts.country_id' => 'countries.country_id']
        ], [
            'sub_districts.sub_district_id',
            'sub_districts.sub_district_name',
            'sub_districts.sub_district_name_bn',
            'sub_districts.sub_district_code',
            'sub_districts.postal_code',
            'sub_districts.status',
            'sub_districts.district_id',
            'districts.district_name',
            'districts.district_name_bn',
            'countries.country_name'
        ], [
            'sub_districts.sub_district_id' => $id
        ]);
    }

    public function getAllWithDistrictAndCountry(): array
    {
        return $this->db->select($this->table, [
            '[>]districts' => ['sub_districts.district_id' => 'districts.district_id'],
            '[>]countries' => ['districts.country_id' => 'countries.country_id']
        ], [
            'sub_districts.sub_district_id',
            'sub_districts.sub_district_name',
            'sub_districts.sub_district_name_bn',
            'sub_districts.sub_district_code',
            'sub_districts.postal_code',
            'sub_districts.status',
            'sub_districts.district_id',
            'districts.district_name',
            'districts.district_name_bn',
            'countries.country_name'
        ], [
            'ORDER' => ['sub_districts.sub_district_name' => 'ASC']
        ]);
    }

    public function getByDistrictId(int $districtId): array
    {
        return $this->db->select($this->table, [
            'sub_district_id',
            'sub_district_name',
            'sub_district_name_bn',
            'sub_district_code',
            'postal_code',
            'status'
        ], [
            'district_id' => $districtId,
            'status' => 'active',
            'ORDER' => ['sub_district_name' => 'ASC']
        ]);
    }
}
