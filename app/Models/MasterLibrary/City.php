<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class City extends Model
{
    protected string $table = 'cities';
    protected string $primaryKey = 'city_id';
    
    protected array $fillable = [
        'city_name',
        'country_id',
        'state_province',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getWithCountry(int $id): ?array
    {
        return $this->db->get($this->table, [
            '[>]countries' => ['cities.country_id' => 'countries.country_id']
        ], [
            'cities.city_id',
            'cities.city_name',
            'cities.state_province',
            'cities.status',
            'cities.country_id',
            'countries.country_name'
        ], [
            'cities.city_id' => $id
        ]);
    }

    public function getAllWithCountry(): array
    {
        return $this->db->select($this->table, [
            '[>]countries' => ['cities.country_id' => 'countries.country_id']
        ], [
            'cities.city_id',
            'cities.city_name',
            'cities.state_province',
            'cities.status',
            'cities.country_id',
            'countries.country_name'
        ]);
    }
}
