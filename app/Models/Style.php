<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Style extends Model
{
    protected string $table = 'styles';
    protected string $primaryKey = 'style_id';

    protected array $fillable = [
        'style_code',
        'style_name',
        'article_number',
        'buyer_id',
        'season',
        'category',
        'garment_type',
        'style_description',
        'description',
        'price',
        'status',
        'photo_path',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    public function findWithBuyer(int $styleId): ?array
    {
        $styles = $this->db->select('styles s', [
            '[>]buyers b' => ['s.buyer_id' => 'buyer_id']
        ], [
            's.*',
            'b.buyer_name'
        ], ['s.style_id' => $styleId]);

        return !empty($styles) ? $styles[0] : null;
    }

    public function getAllWithBuyers(array $filters = []): array
    {
        $where = ['ORDER' => ['s.style_id' => 'DESC']];

        if (!empty($filters['search'])) {
            $where['OR'] = [
                's.style_code[~]' => $filters['search'],
                's.style_name[~]' => $filters['search'],
                's.article_number[~]' => $filters['search']
            ];
        }

        if (!empty($filters['buyer_id'])) {
            $where['s.buyer_id'] = $filters['buyer_id'];
        }

        if (!empty($filters['status'])) {
            $where['s.status'] = $filters['status'];
        }

        if (!empty($filters['season'])) {
            $where['s.season[~]'] = $filters['season'];
        }

        return $this->db->select('styles s', [
            '[>]buyers b' => ['s.buyer_id' => 'buyer_id']
        ], [
            's.*',
            'b.buyer_name'
        ], $where);
    }

    public function getStats(): array
    {
        return [
            'total' => $this->db->count('styles'),
            'active' => $this->db->count('styles', ['status' => ['draft', 'active']]),
            'completed' => $this->db->count('styles', ['status' => 'cancelled'])
        ];
    }

    public function getDropdownOptions(): array
    {
        return [
            'buyers' => $this->db->select('buyers', '*', ['status' => 'active', 'ORDER' => 'buyer_name']),
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'design', 'label' => 'In Design'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'approved_for_production', 'label' => 'Approved Production'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled']
            ]
        ];
    }

    
    // Legacy logic had size_ranges and breakdowns in separate tables.
    // Models for them might be needed or handled via this model's methods.
    
    public function sizeRanges(int $styleId): array
    {
        // This logic is tricky because legacy `size_ranges` table didn't seem to have `style_id` FK directly 
        // based on the INSERT in styles.php: 
        // "INSERT INTO size_breakdowns (style_id, size_range_id...)"
        // But "INSERT INTO size_ranges (range_name...)" NO style_id.
        // It seems `size_ranges` are reusable or created per style with a specific name format?
        // Legacy: $range_name = $style_name . ' - ' . $rangeData['range_name'];
        // So they are loosely coupled by name pattern or linked via `size_breakdowns`.
        
        // Better to find size ranges via size_breakdowns distinct size_range_id
        return $this->db->query(
            "SELECT DISTINCT sr.* FROM size_ranges sr 
             JOIN size_breakdowns sb ON sr.size_range_id = sb.size_range_id 
             WHERE sb.style_id = ?", 
            [$styleId]
        )->fetchAll();
    }
    
    public function breakdowns(int $styleId): array
    {
        return $this->db->select('size_breakdowns', '*', ['style_id' => $styleId]);
    }

    public function getTechPackData(int $styleId): ?array
    {
        $styles = $this->db->select('styles s', [
            '[>]buyers b' => ['s.buyer_id' => 'buyer_id']
        ], [
            's.*',
            'b.buyer_name',
            'b.contact_person',
            'b.email'
        ], ['s.style_id' => $styleId]);

        if (empty($styles)) {
            return null;
        }

        $style = $styles[0];

        // Fetch BOM and components
        $bom = $this->db->select('boms', '*', ['style_id' => $styleId, 'LIMIT' => 1]);
        $bomComponents = [];
        if (!empty($bom)) {
            $bomId = $bom[0]['bom_id'];
            $bomComponents = $this->db->select('bom_components', '*', ['bom_id' => $bomId]);
        }

        // Fetch Size Breakdowns
        $breakdowns = $this->breakdowns($styleId);

        // Fetch Sampling history
        $samples = [];
        try {
            $samples = $this->db->select('sampling', '*', ['style_id' => $styleId, 'ORDER' => ['sample_id' => 'DESC']]);
        } catch (\Throwable $e) {
            try {
                $samples = $this->db->select('sample_requisitions', '*', ['style_id' => $styleId, 'ORDER' => ['id' => 'DESC']]);
            } catch (\Throwable $ex) {
                $samples = [];
            }
        }

        // Fetch Production Orders
        $productionOrders = [];
        try {
            $productionOrders = $this->db->select('production_orders', '*', ['style_id' => $styleId, 'ORDER' => ['po_id' => 'DESC']]);
        } catch (\Throwable $e) {
            $productionOrders = [];
        }

        return [
            'style'             => $style,
            'bom'               => $bom[0] ?? null,
            'bom_components'    => $bomComponents,
            'breakdowns'        => $breakdowns,
            'samples'           => $samples,
            'production_orders' => $productionOrders
        ];
    }
}

