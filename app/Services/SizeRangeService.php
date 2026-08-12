<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class SizeRangeService
{
    public function __construct(private Database $db)
    {
    }

    public function listRanges(array $filters = []): array
    {
        $where = [];

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'range_name[~]' => $term,
                'description[~]' => $term,
            ];
        }

        if (!empty($filters['sort'])) {
            $order = strtoupper($filters['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $where['ORDER'] = [$filters['sort'] => $order];
        } else {
            $where['ORDER'] = ['updated_at' => 'DESC'];
        }

        $ranges = $this->db->select('size_ranges', [
            'size_range_id',
            'range_name',
            'description',
            'sizes',
            'created_at',
            'updated_at',
        ], $where) ?? [];

        return array_map(function (array $range) {
            $range['sizes_array'] = $this->decodeSizes($range['sizes'] ?? '[]');
            $range['breakdown_count'] = $this->db->count('size_breakdowns', ['size_range_id' => $range['size_range_id']]) ?? 0;
            return $range;
        }, $ranges);
    }

    public function getRange(int $rangeId): ?array
    {
        $range = $this->db->get('size_ranges', '*', ['size_range_id' => $rangeId]);
        if (!$range) {
            return null;
        }
        $range['sizes_array'] = $this->decodeSizes($range['sizes'] ?? '[]');
        $range['breakdowns'] = $this->listBreakdowns($rangeId);
        return $range;
    }

    public function createRange(array $data): int
    {
        $payload = [
            'range_name' => $data['range_name'],
            'description' => $data['description'] ?? '',
            'sizes' => $this->encodeSizes($data['sizes'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('size_ranges', $payload);
    }

    public function updateRange(int $rangeId, array $data): bool
    {
        $payload = [
            'range_name' => $data['range_name'],
            'description' => $data['description'] ?? '',
            'sizes' => $this->encodeSizes($data['sizes'] ?? []),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('size_ranges', $payload, ['size_range_id' => $rangeId]);
    }

    public function deleteRange(int $rangeId): bool
    {
        $this->db->delete('size_breakdowns', ['size_range_id' => $rangeId]);
        return (bool) $this->db->delete('size_ranges', ['size_range_id' => $rangeId]);
    }

    public function listBreakdowns(int $rangeId, ?int $styleId = null): array
    {
        $where = ['size_range_id' => $rangeId];
        if ($styleId !== null) {
            $where['style_id'] = $styleId;
        }

        $breakdowns = $this->db->select('size_breakdowns', [
            'breakdown_id',
            'style_id',
            'size_range_id',
            'breakdown_name',
            'color',
            'size_quantities',
            'created_at',
            'updated_at',
        ], [
            'AND' => $where,
            'ORDER' => ['created_at' => 'DESC'],
        ]) ?? [];

        return array_map(function (array $breakdown) {
            $breakdown['quantities'] = $this->decodeQuantities($breakdown['size_quantities'] ?? '{}');
            return $breakdown;
        }, $breakdowns);
    }

    public function createBreakdown(array $data): int
    {
        $payload = [
            'style_id' => (int) $data['style_id'],
            'size_range_id' => (int) $data['size_range_id'],
            'breakdown_name' => $data['breakdown_name'],
            'color' => $data['color'] ?? '',
            'size_quantities' => $this->encodeQuantities($data['quantities'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('size_breakdowns', $payload);
    }

    public function updateBreakdown(int $breakdownId, array $data): bool
    {
        $payload = [
            'breakdown_name' => $data['breakdown_name'],
            'color' => $data['color'] ?? '',
            'size_quantities' => $this->encodeQuantities($data['quantities'] ?? []),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['style_id'])) {
            $payload['style_id'] = (int) $data['style_id'];
        }

        return (bool) $this->db->update('size_breakdowns', $payload, ['breakdown_id' => $breakdownId]);
    }

    public function deleteBreakdown(int $breakdownId): bool
    {
        return (bool) $this->db->delete('size_breakdowns', ['breakdown_id' => $breakdownId]);
    }

    public function getDropdownData(): array
    {
        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
        ], [
            'ORDER' => ['style_code' => 'ASC'],
        ]) ?? [];

        return [
            'styles' => $styles,
        ];
    }

    private function decodeSizes(string $json): array
    {
        try {
            $sizes = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($sizes) ? array_values($sizes) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function encodeSizes(array $sizes): string
    {
        $clean = array_values(array_filter(array_map('trim', $sizes), fn ($size) => $size !== ''));
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    private function decodeQuantities(string $json): array
    {
        try {
            $quantities = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($quantities) ? $quantities : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function encodeQuantities(array $quantities): string
    {
        $clean = [];
        foreach ($quantities as $size => $qty) {
            if ($size === '' || !is_scalar($qty)) {
                continue;
            }
            $clean[$size] = (int) $qty;
        }

        $clean['total_quantity'] = array_sum($clean);
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}
