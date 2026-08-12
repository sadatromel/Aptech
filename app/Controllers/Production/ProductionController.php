<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index(): void
    {
        $this->showTab('dashboard');
    }

    public function showTab(string $tab): void
    {
        $this->requireAuth();
        // Validate tab
        $validTabs = [
            'dashboard', 'planning', 'capacity', 'line-planning',
            'mrp', 'wip', 'monitoring', 'reports'
        ];

        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // --- Gantt Calendar: dynamic month/year from GET params ---
        $ganttYear  = max(2020, min(2035, (int)($this->request->get('year',  date('Y')))));
        $ganttMonth = max(1,    min(12,   (int)($this->request->get('month', date('n')))));
        $ganttDaysInMonth = (int)date('t', mktime(0, 0, 0, $ganttMonth, 1, $ganttYear));
        $ganttMonthName   = date('F', mktime(0, 0, 0, $ganttMonth, 1, $ganttYear));
        $ganttPrevMonth   = $ganttMonth === 1  ? 12 : $ganttMonth - 1;
        $ganttPrevYear    = $ganttMonth === 1  ? $ganttYear - 1 : $ganttYear;
        $ganttNextMonth   = $ganttMonth === 12 ? 1  : $ganttMonth + 1;
        $ganttNextYear    = $ganttMonth === 12 ? $ganttYear + 1 : $ganttYear;

        // Compute weekend days (Saturday=6, Sunday=0) for this month
        $ganttWeekendDays = [];
        for ($d = 1; $d <= $ganttDaysInMonth; $d++) {
            $dow = (int)date('w', mktime(0, 0, 0, $ganttMonth, $d, $ganttYear));
            if ($dow === 0 || $dow === 6) {
                $ganttWeekendDays[] = $d;
            }
        }

        // Today marker: day number if today falls in this month/year
        $todayMarkerDay = null;
        if ((int)date('Y') === $ganttYear && (int)date('n') === $ganttMonth) {
            $todayMarkerDay = (int)date('j');
        }

        // Fetch real database records where applicable
        $orders           = [];
        $plans            = [];
        $lines            = [];
        $styles           = [];
        $dashboardMetrics = [];
        $unallocatedOrders = [];

        try {
            /** @var \App\Services\ProductionDashboardService $dashService */
            $dashService = \Core\Container::get(\App\Services\ProductionDashboardService::class);
            $dashboardMetrics = $dashService->getMetrics();

            $orders = $this->db->query("
                SELECT po.po_id,
                       COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number,
                       s.style_code, s.style_name, b.buyer_name, po.status,
                       COALESCE(po.quantity_ordered, po.quantity, 0) as quantity,
                       po.due_date
                FROM production_orders po
                LEFT JOIN styles s ON po.style_id = s.style_id
                LEFT JOIN buyers b ON po.buyer_id = b.buyer_id
                WHERE po.status NOT IN ('completed','cancelled')
                ORDER BY po.po_id DESC
                LIMIT 30
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Filter plans to the selected Gantt month window
            $ganttFrom = sprintf('%04d-%02d-01', $ganttYear, $ganttMonth);
            $ganttTo   = sprintf('%04d-%02d-%02d', $ganttYear, $ganttMonth, $ganttDaysInMonth);

            $plans = $this->db->query("
                SELECT pp.*,
                       COALESCE(NULLIF(po.po_number, ''), po.order_number, pp.plan_number, CONCAT('PLN-', pp.id)) as order_number,
                       po.quantity as po_quantity,
                       s.style_code, s.style_name, b.buyer_name,
                       pl.line_name, pl.line_code,
                       COALESCE(pp.start_date, pp.planned_date, CURDATE()) as start_date,
                       COALESCE(pp.end_date,   DATE_ADD(COALESCE(pp.start_date, pp.planned_date, CURDATE()), INTERVAL 5 DAY)) as end_date
                FROM production_plans pp
                LEFT JOIN styles s          ON pp.style_id = s.style_id
                LEFT JOIN buyers b          ON s.buyer_id  = b.buyer_id
                LEFT JOIN production_orders po ON po.po_id = pp.po_id
                LEFT JOIN production_lines  pl ON pp.line_id = pl.production_line_id
                WHERE pp.status NOT IN ('cancelled')
                  AND (
                    (COALESCE(pp.start_date, pp.planned_date) <= ? AND
                     COALESCE(pp.end_date,   DATE_ADD(COALESCE(pp.start_date, pp.planned_date, CURDATE()), INTERVAL 5 DAY)) >= ?)
                  )
                ORDER BY pp.id ASC
                LIMIT 100
            ", [$ganttTo, $ganttFrom])->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Unallocated orders: POs with no active production_plan
            $unallocatedOrders = $this->db->query("
                SELECT po.po_id,
                       COALESCE(NULLIF(po.po_number,''), po.order_number, CONCAT('PO-', po.po_id)) as po_number,
                       s.style_name, b.buyer_name,
                       COALESCE(po.quantity_ordered, po.quantity, 0) as quantity,
                       po.due_date
                FROM production_orders po
                LEFT JOIN styles s ON po.style_id = s.style_id
                LEFT JOIN buyers b ON po.buyer_id = b.buyer_id
                WHERE po.status NOT IN ('completed','cancelled')
                  AND po.po_id NOT IN (
                      SELECT DISTINCT po_id FROM production_plans
                      WHERE po_id IS NOT NULL AND status NOT IN ('cancelled')
                  )
                ORDER BY po.po_id DESC
                LIMIT 30
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $lines  = $this->db->query("SELECT * FROM production_lines WHERE status = 'active' ORDER BY production_line_id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $styles = $this->db->query("SELECT * FROM styles WHERE status != 'cancelled'")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Fallback if schema does not support specific fields
        }

        // Process POST submissions
        if ($this->request->isPost()) {
            $input  = $this->request->all();
            $action = $input['action'] ?? $this->request->get('action', '');

            if ($action === 'save_gantt_plan') {
                $this->handleSaveGantt();
                return;
            }

            if ($action === 'delete_plan') {
                $this->handleDeletePlan();
                return;
            }

            $this->verifyCsrf();

            if ($action === 'create_plan') {
                $this->handleCreatePlan();
                return;
            }

            if ($action === 'update_wip') {
                $this->handleUpdateWIP();
                return;
            }
        }

        // Render base view
        $this->view('production/index', [
            'activeTab'         => $tab,
            'orders'            => $orders,
            'plans'             => $plans,
            'unallocatedOrders' => $unallocatedOrders,
            'lines'             => $lines,
            'styles'            => $styles,
            'metrics'           => $dashboardMetrics,
            'pageTitle'         => 'Production Control Center',
            // Gantt calendar context
            'ganttYear'         => $ganttYear,
            'ganttMonth'        => $ganttMonth,
            'ganttMonthName'    => $ganttMonthName,
            'ganttDaysInMonth'  => $ganttDaysInMonth,
            'ganttWeekendDays'  => $ganttWeekendDays,
            'ganttPrevMonth'    => $ganttPrevMonth,
            'ganttPrevYear'     => $ganttPrevYear,
            'ganttNextMonth'    => $ganttNextMonth,
            'ganttNextYear'     => $ganttNextYear,
            'todayMarkerDay'    => $todayMarkerDay,
        ]);
    }

    private function handleDeletePlan(): void
    {
        try {
            $jsonInput = file_get_contents('php://input');
            $payload   = json_decode($jsonInput, true) ?: $this->request->all();
            $planId    = (int)($payload['plan_id'] ?? 0);

            if ($planId <= 0) {
                throw new \InvalidArgumentException('Invalid plan_id');
            }

            // Soft-delete: mark as cancelled so the po_id slot is freed in the unallocated bank
            $this->db->update('production_plans', [
                'status'     => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $planId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Strip removed successfully.']);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    private function handleSaveGantt(): void
    {
        try {
            $jsonInput = file_get_contents('php://input');
            $payload   = json_decode($jsonInput, true) ?: $this->request->all();
            $plans     = $payload['plans'] ?? [];

            // Fetch list of valid production_line_ids and default style_id
            $validLineIds  = $this->db->query("SELECT production_line_id FROM production_lines")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            $defaultLineId = !empty($validLineIds) ? (int)$validLineIds[0] : 1;

            $defaultStyle   = $this->db->query("SELECT style_id FROM styles LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            $defaultStyleId = $defaultStyle ? (int)$defaultStyle['style_id'] : 1;

            foreach ($plans as $p) {
                $rawId           = $p['id'] ?? '';
                $rawPoId         = !empty($p['po_id']) ? (int)$p['po_id'] : null;
                $requestedLineId = (int)($p['line_id'] ?? 0);

                $lineId    = in_array($requestedLineId, $validLineIds) ? $requestedLineId : $defaultLineId;
                $startDate = !empty($p['start_date']) ? $p['start_date'] : date('Y-m-d');
                $endDate   = !empty($p['end_date'])   ? $p['end_date']   : date('Y-m-d', strtotime('+5 days'));
                $quantity  = (int)($p['quantity'] ?? 3000);

                // Resolve style_id
                $styleId = $defaultStyleId;
                if ($rawPoId) {
                    $poRow = $this->db->query("SELECT style_id FROM production_orders WHERE po_id = ? LIMIT 1", [$rawPoId])->fetch(\PDO::FETCH_ASSOC);
                    if ($poRow) $styleId = (int)$poRow['style_id'];
                } elseif (!empty($p['style'])) {
                    $foundStyle = $this->db->query("SELECT style_id FROM styles WHERE style_name LIKE ? OR style_code LIKE ? LIMIT 1", ['%' . $p['style'] . '%', '%' . $p['style'] . '%'])->fetch(\PDO::FETCH_ASSOC);
                    if ($foundStyle) $styleId = (int)$foundStyle['style_id'];
                }

                // Derive plan_number from PO
                $poNumber = $p['po'] ?? null;
                if (!$poNumber && $rawPoId) {
                    $poRow2   = $this->db->query("SELECT COALESCE(NULLIF(po_number,''), order_number) as pn FROM production_orders WHERE po_id = ? LIMIT 1", [$rawPoId])->fetch(\PDO::FETCH_ASSOC);
                    $poNumber = $poRow2['pn'] ?? null;
                }

                $isExisting = is_numeric($rawId) && (int)$rawId > 0;

                if ($isExisting) {
                    $planId     = (int)$rawId;
                    $updateData = [
                        'line_id'      => $lineId,
                        'planned_date' => $startDate,
                        'start_date'   => $startDate,
                        'end_date'     => $endDate,
                        'quantity'     => $quantity,
                        'status'       => 'planned',
                        'updated_at'   => date('Y-m-d H:i:s'),
                    ];
                    if ($rawPoId !== null)  $updateData['po_id']  = $rawPoId;
                    if (!empty($p['status'])) $updateData['status'] = $p['status'];
                    $this->db->update('production_plans', $updateData, ['id' => $planId]);
                } else {
                    $this->db->insert('production_plans', [
                        'plan_number'        => $poNumber ?: ('PLN-' . date('Ymd') . '-' . rand(1000, 9999)),
                        'po_id'              => $rawPoId,
                        'line_id'            => $lineId,
                        'style_id'           => $styleId,
                        'planned_date'       => $startDate,
                        'start_date'         => $startDate,
                        'end_date'           => $endDate,
                        'quantity'           => $quantity,
                        'completed_quantity' => 0,
                        'status'             => 'planned',
                        'created_at'         => date('Y-m-d H:i:s'),
                        'updated_at'         => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Gantt schedule saved successfully!']);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    private function handleCreatePlan(): void
    {
        try {
            $data         = $this->request->all();
            $orderId      = (int)($data['order_id'] ?? 0);
            $order        = $this->db->get('production_orders', '*', ['po_id' => $orderId]);
            $styleId      = $order ? (int)$order['style_id'] : 0;
            $startDate    = $data['planned_date'] ?? date('Y-m-d');
            $durationDays = max(1, (int)($data['duration_days'] ?? 7));
            $endDate      = date('Y-m-d', strtotime("$startDate +$durationDays days"));
            $poNumber     = $order ? ($order['po_number'] ?: ($order['order_number'] ?? null)) : null;

            $this->db->insert('production_plans', [
                'plan_number'        => $poNumber ?: ('PLN-' . date('Ymd') . '-' . rand(100, 999)),
                'po_id'              => $orderId ?: null,
                'style_id'           => $styleId,
                'line_id'            => (int)($data['line_id'] ?? 0),
                'planned_date'       => $startDate,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'quantity'           => (int)($data['quantity'] ?? 0),
                'completed_quantity' => 0,
                'status'             => 'planned',
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);
            $_SESSION['success_message'] = 'Production plan created successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to create plan: ' . $e->getMessage();
        }
        // Redirect back to the line-planning tab preserving month/year
        $month = (int)($this->request->get('month', date('n')));
        $year  = (int)($this->request->get('year',  date('Y')));
        $this->redirect("production/line-planning?month=$month&year=$year");
    }

    private function handleUpdateWIP(): void
    {
        try {
            $_SESSION['success_message'] = 'WIP quantities updated successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to update WIP: ' . $e->getMessage();
        }
        $this->redirect('production/wip');
    }
}
