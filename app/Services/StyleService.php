<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class StyleService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('styles') ?? 0);
        $active = (int) ($this->db->count('styles', ['status' => ['design', 'approved', 'approved_for_production', 'production']]) ?? 0);
        $completed = (int) ($this->db->count('styles', ['status' => 'completed']) ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
        ];
    }

    public function listStyles(array $filters = []): array
    {
        $where = ['ORDER' => ['style_id' => 'DESC']];

        if (!empty($filters['buyer_id'])) {
            $where['styles.buyer_id'] = (int) $filters['buyer_id'];
        }

        if (!empty($filters['status'])) {
            $where['styles.status'] = $filters['status'];
        }

        if (!empty($filters['season'])) {
            $where['styles.season'] = $filters['season'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'style_code[~]' => $term,
                'style_name[~]' => $term,
                'article_number[~]' => $term,
            ];
        }

        $styles = $this->db->select('styles', [
            '[>]buyer_profiles' => ['buyer_profiles.buyer_id' => 'styles.buyer_id'],
        ], [
            'styles.style_id',
            'styles.style_code',
            'styles.style_name',
            'styles.article_number',
            'styles.buyer_id',
            'styles.season',
            'styles.style_description',
            'styles.price',
            'styles.category',
            'styles.status',
            'styles.created_at',
            'styles.updated_at',
            'buyer_profiles.buyer_name',
        ], $where) ?? [];

        return $styles;
    }

    public function getDropdownData(): array
    {
        $buyers = $this->db->select('buyer_profiles', [
            'buyer_id',
            'buyer_name',
        ], [
            'buyer_status' => 'active',
            'ORDER' => ['buyer_name' => 'ASC'],
        ]) ?? [];

        $sizeRanges = $this->db->select('size_ranges', [
            'size_range_id',
            'range_name',
            'sizes',
        ], [
            'ORDER' => ['range_name' => 'ASC'],
        ]) ?? [];

        return [
            'buyers' => $buyers,
            'size_ranges' => $sizeRanges,
            'statuses' => [
                ['value' => 'design', 'label' => 'Design'],
                ['value' => 'development', 'label' => 'Development'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'approved_for_production', 'label' => 'Approved for Production'],
                ['value' => 'production', 'label' => 'Production'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
        ];
    }

    public function createStyle(array $data, int $userId): int
    {
        $payload = [
            'style_code' => $data['style_code'],
            'style_name' => $data['style_name'],
            'article_number' => $data['article_number'] ?? '',
            'buyer_id' => (int) $data['buyer_id'],
            'season' => $data['season'] ?? '',
            'style_description' => $data['description'] ?? '',
            'price' => (float) ($data['target_price'] ?? 0),
            'category' => $data['garment_type'] ?? '',
            'status' => $data['status'] ?? 'design',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('styles', $payload);
    }

    public function updateStyle(int $styleId, array $data, int $userId): bool
    {
        $payload = [
            'style_code' => $data['style_code'],
            'style_name' => $data['style_name'],
            'article_number' => $data['article_number'] ?? '',
            'buyer_id' => (int) $data['buyer_id'],
            'season' => $data['season'] ?? '',
            'style_description' => $data['description'] ?? '',
            'price' => (float) ($data['target_price'] ?? 0),
            'category' => $data['garment_type'] ?? '',
            'status' => $data['status'] ?? 'design',
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('styles', $payload, ['style_id' => $styleId]);
    }

    public function deleteStyle(int $styleId): bool
    {
        return (bool) $this->db->delete('styles', ['style_id' => $styleId]);
    }

    public function getStyle(int $styleId): ?array
    {
        $style = $this->db->get('styles', '*', ['style_id' => $styleId]);
        if (!$style) {
            return null;
        }

        $style['size_ranges'] = $this->db->select('size_breakdowns', [
            '[>]size_ranges' => ['size_range_id' => 'size_range_id'],
        ], [
            'size_breakdowns.breakdown_id',
            'size_breakdowns.size_range_id',
            'size_breakdowns.breakdown_name',
            'size_breakdowns.color',
            'size_breakdowns.size_quantities',
            'size_ranges.range_name',
            'size_ranges.sizes',
        ], [
            'size_breakdowns.style_id' => $styleId,
        ]) ?? [];

        return $style;
    }
}
