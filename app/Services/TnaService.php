<?php

declare(strict_types=1);

namespace App\Services;

use Core\Container;
use PDO;

class TnaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Container::get(PDO::class);
    }

    /**
     * Default RMG milestone template definitions with relative day offsets before delivery_date
     */
    public function getMilestoneTemplates(): array
    {
        return [
            ['code' => 'lab_dip',           'name' => 'Lab Dip & Color Approval',       'offset_days' => 60, 'responsible' => 'Lab Specialist'],
            ['code' => 'fit_sample',        'name' => 'Fit Sample Approval',            'offset_days' => 50, 'responsible' => 'Sample Merchant'],
            ['code' => 'fabric_booking',   'name' => 'Bulk Fabric Booking',            'offset_days' => 45, 'responsible' => 'Fabric Purchase'],
            ['code' => 'trim_booking',     'name' => 'Trims & Accessories Booking',    'offset_days' => 40, 'responsible' => 'Trims Merchant'],
            ['code' => 'fabric_inhouse',   'name' => 'Fabric In-House Date',           'offset_days' => 25, 'responsible' => 'Store In-charge'],
            ['code' => 'trim_inhouse',     'name' => 'Trims In-House Date',            'offset_days' => 20, 'responsible' => 'Store In-charge'],
            ['code' => 'pp_sample',        'name' => 'PP Sample Approval',             'offset_days' => 18, 'responsible' => 'Sample Merchant'],
            ['code' => 'pp_meeting',       'name' => 'Pre-Production (PP) Meeting',    'offset_days' => 15, 'responsible' => 'Factory Manager'],
            ['code' => 'cutting_start',    'name' => 'Bulk Cutting Start',             'offset_days' => 14, 'responsible' => 'Cutting In-charge'],
            ['code' => 'sewing_start',     'name' => 'Sewing Line Loading Start',      'offset_days' => 10, 'responsible' => 'Production Manager'],
            ['code' => 'washing_finish',   'name' => 'Washing & Finishing Finish',     'offset_days' => 5,  'responsible' => 'Wash In-charge'],
            ['code' => 'packing_finish',   'name' => 'Packing & Cartoning Finish',     'offset_days' => 3,  'responsible' => 'Packing In-charge'],
            ['code' => 'final_inspection', 'name' => 'Final Quality Inspection (AQL)', 'offset_days' => 2,  'responsible' => 'QA Manager'],
            ['code' => 'ex_factory',       'name' => 'Ex-Factory / Shipment Target',   'offset_days' => 0,  'responsible' => 'Commercial Merchant'],
        ];
    }

    /**
     * Get orders with their T&A status & progress %
     */
    public function getOrdersTnaList(array $filters = []): array
    {
        $sql = "
            SELECT 
                po.po_id as po_id,
                COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number,
                po.order_date,
                COALESCE(po.ex_factory_date, po.required_date, po.due_date) as delivery_date,
                COALESCE(po.quantity_ordered, po.quantity, 0) as quantity,
                po.status as order_status,
                s.style_id,
                s.style_code,
                s.style_name,
                b.buyer_name,
                COUNT(t.id) as total_milestones,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_milestones,
                SUM(CASE WHEN t.status = 'delayed' OR (t.target_date < CURDATE() AND t.status != 'completed') THEN 1 ELSE 0 END) as delayed_milestones
            FROM production_orders po
            LEFT JOIN styles s ON po.style_id = s.style_id
            LEFT JOIN buyers b ON po.buyer_id = b.buyer_id
            LEFT JOIN order_tna t ON po.po_id = t.po_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['buyer_id'])) {
            $sql .= " AND po.buyer_id = :buyer_id";
            $params['buyer_id'] = (int)$filters['buyer_id'];
        }

        if (!empty($filters['style_id'])) {
            $sql .= " AND po.style_id = :style_id";
            $params['style_id'] = (int)$filters['style_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (po.po_number LIKE :search OR po.order_number LIKE :search OR s.style_code LIKE :search OR s.style_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " GROUP BY po.po_id, po.po_number, po.order_number, po.order_date, po.ex_factory_date, po.required_date, po.due_date, po.quantity_ordered, po.quantity, po.status, s.style_id, s.style_code, s.style_name, b.buyer_name ORDER BY po.po_id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Calculate progress percentage
        foreach ($orders as &$order) {
            $total = (int)$order['total_milestones'];
            $comp = (int)$order['completed_milestones'];
            $order['progress_pct'] = $total > 0 ? round(($comp / $total) * 100) : 0;
            
            // Auto-generate milestones if order has none
            if ($total === 0) {
                $this->generateDefaultMilestones((int)$order['po_id'], (int)$order['style_id'], $order['delivery_date']);
                $order['total_milestones'] = count($this->getMilestoneTemplates());
            }
        }

        return $orders;
    }

    /**
     * Get all milestones for a specific PO
     */
    public function getPoMilestones(int $poId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number, 
                   COALESCE(po.ex_factory_date, po.required_date, po.due_date) as delivery_date, 
                   s.style_code
            FROM order_tna t
            JOIN production_orders po ON t.po_id = po.po_id
            LEFT JOIN styles s ON t.style_id = s.style_id
            WHERE t.po_id = ?
            ORDER BY t.id ASC
        ");
        $stmt->execute([$poId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            // Check PO info to generate
            $poStmt = $this->pdo->prepare("
                SELECT po_id, style_id, COALESCE(ex_factory_date, required_date, due_date) as delivery_date 
                FROM production_orders 
                WHERE po_id = ?
            ");
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();
            if ($po) {
                $this->generateDefaultMilestones((int)$po['po_id'], (int)($po['style_id'] ?? 0), $po['delivery_date']);
                return $this->getPoMilestones($poId);
            }
        }

        return $rows;
    }

    /**
     * Generate default T&A milestones based on target delivery date
     */
    public function generateDefaultMilestones(int $poId, int $styleId, ?string $deliveryDate): void
    {
        $baseDate = !empty($deliveryDate) ? strtotime($deliveryDate) : strtotime('+60 days');
        $templates = $this->getMilestoneTemplates();

        $stmt = $this->pdo->prepare("
            INSERT INTO order_tna (po_id, style_id, milestone_code, milestone_name, responsible_person, target_date, status)
            VALUES (:po_id, :style_id, :code, :name, :resp, :target_date, 'pending')
        ");

        foreach ($templates as $tmpl) {
            $targetTimestamp = strtotime("-{$tmpl['offset_days']} days", $baseDate);
            $targetDateStr = date('Y-m-d', $targetTimestamp);

            $stmt->execute([
                'po_id'       => $poId,
                'style_id'    => $styleId > 0 ? $styleId : null,
                'code'        => $tmpl['code'],
                'name'        => $tmpl['name'],
                'resp'        => $tmpl['responsible'],
                'target_date' => $targetDateStr,
            ]);
        }
    }

    /**
     * Update a specific milestone record
     */
    public function updateMilestone(int $id, array $data, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE order_tna 
            SET 
                target_date = :target_date,
                revised_date = :revised_date,
                actual_date = :actual_date,
                status = :status,
                responsible_person = :responsible_person,
                remarks = :remarks,
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            'id'                 => $id,
            'target_date'        => !empty($data['target_date']) ? $data['target_date'] : null,
            'revised_date'       => !empty($data['revised_date']) ? $data['revised_date'] : null,
            'actual_date'        => !empty($data['actual_date']) ? $data['actual_date'] : null,
            'status'             => !empty($data['status']) ? $data['status'] : 'pending',
            'responsible_person' => $data['responsible_person'] ?? null,
            'remarks'            => $data['remarks'] ?? null,
            'updated_by'         => $userId,
        ]);
    }

    public function getDropdownData(): array
    {
        return [
            'buyers' => $this->pdo->query("SELECT buyer_id, buyer_name FROM buyers WHERE status = 'active' ORDER BY buyer_name")->fetchAll(),
            'styles' => $this->pdo->query("SELECT style_id, style_code, style_name FROM styles ORDER BY style_code")->fetchAll(),
        ];
    }
}
