<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class ProductionOrderService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('production_orders') ?? 0);
        $draft = (int) ($this->db->count('production_orders', ['status' => 'draft']) ?? 0);
        $approved = (int) ($this->db->count('production_orders', ['status' => ['approved', 'confirmed']]) ?? 0);
        $inProduction = (int) ($this->db->count('production_orders', ['status' => ['in_production', 'in_progress']]) ?? 0);

        return [
            'total' => $total,
            'draft' => $draft,
            'approved' => $approved,
            'in_production' => $inProduction,
        ];
    }

    public function getOrdersWithFilters(array $filters): array
    {
        $where = ['ORDER' => ['po.order_date' => 'DESC']];

        if (!empty($filters['status'])) {
            $where['po.status'] = $filters['status'];
        }

        if (!empty($filters['factory_id'])) {
            $where['po.factory_id'] = (int) $filters['factory_id'];
        }

        if (!empty($filters['date_from'])) {
            $where['po.order_date[>=]'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where['po.order_date[<=]'] = $filters['date_to'];
        }

        $orders = $this->db->select('production_orders po', [
            '[>]styles s' => ['po.style_id' => 'style_id'],
            '[>]buyers b' => ['s.buyer_id' => 'buyer_id'],
            '[>]countries c' => ['po.country_id' => 'country_id'],
            '[>]cities ct' => ['po.city_id' => 'city_id'],
            '[>]currencies cur' => ['po.currency_id' => 'currency_id']
        ], [
            'po_id' => 'po.po_id',
            'po_number' => 'po.po_number',
            'allocation_number' => 'po.allocation_number',
            'po.order_date',
            'required_date' => 'po.required_date',
            'quantity_ordered' => 'po.quantity_ordered',
            'po.unit_price',
            'po.currency_id',
            'po.conversion_rate',
            'po.priority',
            'po.status',
            'buyer_name' => 'b.buyer_name',
            'style_code' => 's.style_code',
            'style_name' => 's.style_description',
            'ex_factory_date' => 'po.ex_factory_date',
            'country_id' => 'po.country_id',
            'city_id' => 'po.city_id',
            'country_name' => 'c.country_name',
            'city_name' => 'ct.city_name',
            'currency_code' => 'cur.currency_code',
            'currency_symbol' => 'cur.symbol'
        ], $where);

        if (!empty($orders)) {
            $poIds = array_column($orders, 'po_id');
            $idList = implode(',', array_map('intval', $poIds));
            $breakdowns = $this->db->query(
                "SELECT order_id, 
                        COUNT(id) as sku_count, 
                        SUM(quantity) as total_breakdown_qty, 
                        SUM(quantity * unit_price) as total_breakdown_val,
                        (SUM(quantity * unit_price) / NULLIF(SUM(quantity), 0)) as avg_breakdown_price
                 FROM order_breakdowns 
                 WHERE order_id IN ($idList)
                 GROUP BY order_id"
            )->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

            foreach ($orders as &$order) {
                $poId = (int) $order['po_id'];
                $bd = $breakdowns[$poId] ?? null;
                $order['line_items_count'] = (int) ($bd['sku_count'] ?? 0);
                $order['total_amount'] = (float) ($bd['total_breakdown_val'] ?? 0);
                $calculatedAvg = !empty($bd['avg_breakdown_price']) 
                    ? (float) $bd['avg_breakdown_price'] 
                    : (float) ($order['unit_price'] ?? 0);
                if ($order['total_amount'] <= 0 && $order['quantity_ordered'] > 0 && $calculatedAvg > 0) {
                    $order['total_amount'] = (float) ($order['quantity_ordered'] * $calculatedAvg);
                }
                $order['avg_unit_price'] = $calculatedAvg;
                $order['unit_price'] = $calculatedAvg;
            }
            unset($order);
        }

        return $orders;
    }

    public function getDropdownData(): array
    {
        return [
            'buyers' => $this->db->select('buyers', ['buyer_id', 'buyer_name'], ['ORDER' => 'buyer_name']),
            'countries' => $this->db->select('countries', ['country_id', 'country_name'], ['ORDER' => 'country_name']),
            'factories' => $this->db->select('factories', '*', ['ORDER' => 'factory_name']),
            'styles' => $this->db->select('styles', ['style_id', 'style_code', 'style_name'], ['ORDER' => 'style_code']),
            'currencies' => $this->db->select('currencies', ['currency_id', 'currency_code', 'currency_name', 'symbol', 'exchange_rate'], ['status' => 'active', 'ORDER' => 'currency_code']),
            'size_ranges' => $this->db->select('size_ranges', ['size_range_id', 'range_name', 'sizes'], ['ORDER' => 'range_name']),
            'priorities' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High']
            ],
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'in_production', 'label' => 'In Production'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled']
            ],
            'color_types' => $this->db->query(
                "SELECT DISTINCT color_type FROM order_breakdowns WHERE color_type IS NOT NULL AND color_type != '' ORDER BY color_type"
            )->fetchAll(\PDO::FETCH_COLUMN)
        ];
    }

    public function getStyleSizes(int $styleId): array
    {
        // Fetch sizes from linked size ranges
        // We expect a single size range per style for this logic, or we take the first one
        $range = $this->db->query(
            "SELECT sr.size_range_id, sr.range_name, sr.sizes 
             FROM size_ranges sr 
             JOIN size_breakdowns sb ON sr.size_range_id = sb.size_range_id 
             WHERE sb.style_id = ? 
             LIMIT 1", 
            [$styleId]
        )->fetch();

        if (!$range) {
            return [
                'range_id' => null,
                'range_name' => null,
                'sizes' => []
            ];
        }

        $decoded = json_decode($range['sizes'] ?? '[]', true);
        $sizes = is_array($decoded) ? $decoded : [];

        return [
            'range_id' => (int) $range['size_range_id'],
            'range_name' => $range['range_name'],
            'sizes' => $sizes
        ];
    }

    public function getOrderTotals(array $filters): array
    {
        $orders = $this->getOrdersWithFilters($filters);
        
        return [
            'total_orders' => count($orders),
            'total_quantity' => array_sum(array_column($orders, 'quantity')),
            'total_value' => array_sum(array_column($orders, 'total_value'))
        ];
    }

    public function createOrder(array $data): string
    {
        $rangeId = $this->handleSizeRange($data);

        $orderNumber = $this->generateOrderNumber();

        // Fetch buyer_id from Style if not provided
        $buyerId = $data['buyer_id'] ?? null;
        if (!$buyerId && !empty($data['style_id'])) {
            $style = $this->db->get('styles', 'buyer_id', ['style_id' => $data['style_id']]);
            $buyerId = $style['buyer_id'] ?? null;
        }

        $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : 0.0;
        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $calcQty = 0;
            $calcVal = 0;
            foreach ($data['line_items'] as $item) {
                $q = (float) ($item['quantity'] ?? 0);
                $p = (float) ($item['unit_price'] ?? 0);
                $calcQty += $q;
                $calcVal += ($q * $p);
            }
            if ($calcQty > 0) {
                $unitPrice = round($calcVal / $calcQty, 4);
            }
        }

        $orderData = [
            'po_number' => $orderNumber,
            'order_number' => $orderNumber, // Populate required field
            'allocation_number' => !empty($data['allocation_number']) ? $data['allocation_number'] : null,
            'factory_id' => $data['factory_id'],
            'style_id' => $data['style_id'],
            'buyer_id' => $buyerId, 
            'order_date' => $data['order_date'],
            'required_date' => $data['delivery_date'] ?? $data['required_date'],
            'quantity' => $data['quantity'] ?? 0, // Ensure int
            'quantity_ordered' => $data['quantity'] ?? 0, 
            'unit_price' => $unitPrice, 
            'currency_id' => !empty($data['currency_id']) ? $data['currency_id'] : null,
            'conversion_rate' => !empty($data['conversion_rate']) ? $data['conversion_rate'] : null,
            'priority' => !empty($data['priority']) ? strtolower(trim((string)$data['priority'])) : 'medium',
            'status' => !empty($data['status']) ? strtolower(trim((string)$data['status'])) : 'draft',
            'created_by' => $data['created_by'] ?? 0,
            'ex_factory_date' => !empty($data['ex_factory_date']) ? $data['ex_factory_date'] : null,
            'country_id' => !empty($data['country_id']) ? $data['country_id'] : null,
            'city_id' => !empty($data['city_id']) ? $data['city_id'] : null,
            'size_range_id' => $rangeId,
            'size_range_name' => $data['size_range_name'] ?? null,
            'size_details' => $data['size_details'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('production_orders', $orderData);
        $poId = (int) $this->db->lastInsertId();

        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $conversionRate = !empty($data['conversion_rate']) ? (float)$data['conversion_rate'] : 1.0;
            foreach ($data['line_items'] as $item) {
                $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0.0;
                $convertedPrice = $unitPrice * $conversionRate;
                
                $this->db->insert('order_breakdowns', [
                    'order_id' => $poId,
                    'color' => $item['color'],
                    'color_type' => $item['color_type'] ?? null,
                    'size' => $item['size'],
                    'inseam' => $item['inseam'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'converted_unit_price' => $convertedPrice,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        return $orderNumber;
    }

    public function getOrderWithDetails(int $id): ?array
    {
        $order = $this->db->get('production_orders po', [
            '[>]styles s' => ['po.style_id' => 'style_id'],
            '[>]buyers b' => ['s.buyer_id' => 'buyer_id'],
            '[>]currencies cur' => ['po.currency_id' => 'currency_id']
        ], [
            'po.*',
            'customer_name' => 'b.buyer_name',
            'b.email',
            'b.phone',
            'style_number' => 's.style_code',
            's.style_description',
            'currency_code' => 'cur.currency_code',
            'currency_symbol' => 'cur.symbol'
        ], ['po.po_id' => $id]);

        if (!$order) {
            return null;
        }

        // Get order breakdown/line items
        $breakdowns = $this->db->select('order_breakdowns', '*', [
            'order_id' => $id,
            'ORDER' => ['id' => 'ASC']
        ]);

        return [
            'order' => $order,
            'line_items' => $breakdowns, // Rename for frontend consistency
            'breakdowns' => $breakdowns // Keep for legacy
        ];
    }

    public function updateOrder(int $id, array $data): bool
    {
        $rangeId = $this->handleSizeRange($data);

        $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : 0.0;
        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $calcQty = 0;
            $calcVal = 0;
            foreach ($data['line_items'] as $item) {
                $q = (float) ($item['quantity'] ?? 0);
                $p = (float) ($item['unit_price'] ?? 0);
                $calcQty += $q;
                $calcVal += ($q * $p);
            }
            if ($calcQty > 0) {
                $unitPrice = round($calcVal / $calcQty, 4);
            }
        }

        $updateData = [
            'factory_id' => $data['factory_id'],
            'allocation_number' => !empty($data['allocation_number']) ? $data['allocation_number'] : null,
            'style_id' => $data['style_id'],
            'order_date' => $data['order_date'],
            'required_date' => $data['delivery_date'] ?? $data['required_date'],
            'quantity_ordered' => $data['quantity'], // Ensure frontend sends total
            'unit_price' => $unitPrice,
            'currency_id' => !empty($data['currency_id']) ? $data['currency_id'] : null,
            'conversion_rate' => !empty($data['conversion_rate']) ? $data['conversion_rate'] : null,
            'ex_factory_date' => !empty($data['ex_factory_date']) ? $data['ex_factory_date'] : null,
            'country_id' => !empty($data['country_id']) ? $data['country_id'] : null,
            'city_id' => !empty($data['city_id']) ? $data['city_id'] : null,
            'priority' => !empty($data['priority']) ? strtolower(trim((string)$data['priority'])) : 'medium',
            'status' => !empty($data['status']) ? strtolower(trim((string)$data['status'])) : 'draft',
            'size_range_id' => $rangeId,
            'size_range_name' => $data['size_range_name'] ?? null,
            'size_details' => $data['size_details'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->db->update('production_orders', $updateData, ['po_id' => $id]);

        // Sync line items: Delete all and re-insert
        $this->db->delete('order_breakdowns', ['order_id' => $id]);

        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $conversionRate = !empty($data['conversion_rate']) ? (float)$data['conversion_rate'] : 1.0;
            foreach ($data['line_items'] as $item) {
                $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0.0;
                $convertedPrice = $unitPrice * $conversionRate;
                
                $this->db->insert('order_breakdowns', [
                    'order_id' => $id,
                    'color' => $item['color'],
                    'color_type' => $item['color_type'] ?? null,
                    'size' => $item['size'],
                    'inseam' => $item['inseam'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'converted_unit_price' => $convertedPrice,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        return true;
    }

    public function updateOrderStatus(int $id, array $data): bool
    {
        $status = strtolower(trim((string)($data['status'] ?? 'draft')));
        $result = $this->db->update('production_orders', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['po_id' => $id]);
        return $result !== null;
    }

    public function deleteOrder(int $id): bool
    {
        $this->db->delete('order_breakdowns', ['order_id' => $id]);
        $result = $this->db->delete('production_orders', ['po_id' => $id]);
        return $result !== null;
    }

    private function handleSizeRange(array $data): ?int
    {
        $styleId = (int) ($data['style_id'] ?? 0);
        $rangeName = trim($data['size_range_name'] ?? '');
        $rangeId = $data['size_range_id'] ?? null;
        $sizeDetails = $data['size_details'] ?? '';

        if (!$styleId || empty($rangeName)) {
            return null;
        }

        // 1. Resolve Range ID
        if (empty($rangeId)) {
            // Check if exists by name
            $existing = $this->db->get('size_ranges', ['size_range_id', 'sizes'], [
                'range_name' => $rangeName
            ]);

            if ($existing) {
                $rangeId = $existing['size_range_id'];
            } else {
                // Create New Range
                $sizesArray = array_map('trim', array_filter(explode(',', $sizeDetails)));
                $sizesArray = array_values($sizesArray); // Re-index
                
                $this->db->insert('size_ranges', [
                    'range_name' => $rangeName,
                    'sizes' => json_encode($sizesArray),
                ]);
                $rangeId = $this->db->lastInsertId();
            }
        }

        // 2. Link to Style (Size Breakdown)
        if ($rangeId) {
             $hasBreakdown = $this->db->count('size_breakdowns', [
                 'style_id' => $styleId,
                 'size_range_id' => $rangeId
             ]) > 0;

             if (!$hasBreakdown) {
                 $this->db->insert('size_breakdowns', [
                     'style_id' => $styleId,
                     'size_range_id' => $rangeId,
                     'breakdown_name' => $rangeName,
                     'size_name' => substr($rangeName, 0, 10), // Truncate to avoid 1406 error
                     'color' => 'General',
                     'size_quantities' => '{}',
                     'created_at' => date('Y-m-d H:i:s')
                 ]);
             }
        }
        
        return $rangeId ? (int) $rangeId : null;
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'PO';
        $date = date('Ymd');
        
        // Get today's count
        $count = $this->db->count('production_orders', [
            'order_number[~]' => $prefix . $date
        ]) + 1;
        
        return sprintf("%s%s%03d", $prefix, $date, $count);
    }

    public function getCitiesByCountry(int $countryId): array
    {
        return $this->db->select('cities', ['city_id', 'city_name'], [
            'country_id' => $countryId,
            'ORDER' => 'city_name'
        ]);
    }
}
