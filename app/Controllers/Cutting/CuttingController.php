<?php

declare(strict_types=1);

namespace App\Controllers\Cutting;

use App\Controllers\BaseController;

class CuttingController extends BaseController
{
    public function index(): void
    {
        $this->showTab('dashboard');
    }

    public function showTab(string $tab): void
    {
        $this->requireAuth();

        $validTabs = [
            'dashboard', 'plans', 'markers', 'marker', 'lay', 'fabric',
            'execution', 'bundles', 'numbering', 'ticketing', 'qc', 'reports'
        ];

        // Normalize 'marker' -> 'markers' for consistency
        if ($tab === 'marker') {
            $tab = 'markers';
        }

        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // Process POST submissions
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->get('action', '');

            if ($action === 'create_plan') {
                $this->handleCreatePlan();
                return;
            }
            if ($action === 'create_marker') {
                $this->handleCreateMarker();
                return;
            }
            if ($action === 'create_lay') {
                $this->handleCreateLay();
                return;
            }
            if ($action === 'create_bundle') {
                $this->handleCreateBundle();
                return;
            }
            if ($action === 'create_execution') {
                $this->handleCreateExecution();
                return;
            }
            if ($action === 'create_numbering') {
                $this->handleCreateNumbering();
                return;
            }
            if ($action === 'create_ticketing') {
                $this->handleCreateTicketing();
                return;
            }
            if ($action === 'create_qc') {
                $this->handleCreateQC();
                return;
            }
        }

        // Shared dropdown data
        $plans = [];
        $markers = [];
        $bundles = [];
        $orders = [];
        $styles = [];
        $cutting_rooms = [];
        $lays = [];
        $fabric_rolls = [];
        $executions = [];
        $numbering_logs = [];
        $ticketing_logs = [];
        $qc_records = [];
        $stats = [
            'total_plans' => 0, 'active_plans' => 0,
            'completed_plans' => 0, 'total_cut_quantity' => 0,
            'total_markers' => 0, 'approved_markers' => 0,
            'avg_efficiency' => 0,
            'total_bundles' => 0, 'active_bundles' => 0,
            'total_pieces' => 0,
        ];

        try {
            // Core dropdowns always needed
            $orders = $this->db->query("
                SELECT po.po_id as order_id, po.po_number as order_number, s.style_code, s.style_name
                FROM production_orders po
                LEFT JOIN styles s ON po.style_id = s.style_id
                WHERE po.status IN ('approved', 'planned', 'in_production')
                ORDER BY po.po_number
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $styles = $this->db->query(
                "SELECT s.style_id, s.style_code, s.style_name FROM styles s WHERE status != 'cancelled' ORDER BY s.style_code"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $cutting_rooms = $this->db->query(
                "SELECT cutting_room_id, cutting_room_name FROM cutting_rooms WHERE status = 'active' ORDER BY cutting_room_name"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Plans data
            $plans = $this->db->query("
                SELECT cp.*, po.po_number as order_number, s.style_code, s.style_name, cr.cutting_room_name
                FROM cutting_plans cp
                LEFT JOIN production_orders po ON cp.order_id = po.po_id
                LEFT JOIN styles s ON cp.style_id = s.style_id
                LEFT JOIN cutting_rooms cr ON cp.cutting_room_id = cr.cutting_room_id
                ORDER BY cp.plan_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Compute plan stats
            $active_plans = 0;
            $completed_plans = 0;
            $total_cut = 0;
            foreach ($plans as $p) {
                if ($p['status'] === 'completed') {
                    $completed_plans++;
                } else {
                    $active_plans++;
                }
                $total_cut += (int)($p['completed_quantity'] ?? 0);
            }

            // Markers data
            $markers = $this->db->query("
                SELECT m.*, cp.plan_number, s.style_code, s.style_name
                FROM markers m
                LEFT JOIN cutting_plans cp ON m.plan_id = cp.plan_id
                LEFT JOIN styles s ON m.style_id = s.style_id
                ORDER BY m.marker_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $approved_markers = 0;
            $total_efficiency = 0;
            $efficiency_count = 0;
            foreach ($markers as $m) {
                if (($m['status'] ?? '') === 'approved') $approved_markers++;
                if (!empty($m['efficiency'])) {
                    $total_efficiency += (float)$m['efficiency'];
                    $efficiency_count++;
                }
            }

            // Bundles data
            $bundles = $this->db->query("
                SELECT bg.*, m.marker_number, s.style_code, s.style_name, cr.cutting_room_name
                FROM bundle_generation bg
                LEFT JOIN markers m ON bg.marker_id = m.marker_id
                LEFT JOIN styles s ON bg.style_id = s.style_id
                LEFT JOIN cutting_rooms cr ON bg.cutting_room_id = cr.cutting_room_id
                ORDER BY bg.bundle_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $active_bundles = 0;
            $total_pieces = 0;
            foreach ($bundles as $b) {
                if (in_array($b['status'] ?? '', ['generated', 'issued'])) $active_bundles++;
                $total_pieces += (int)($b['quantity'] ?? 0);
            }

            // Lay sheets
            $lays = $this->db->query("
                SELECT ls.*, cp.plan_number, s.style_code, m.marker_number, cr.cutting_room_name
                FROM lay_sheets ls
                LEFT JOIN cutting_plans cp ON ls.plan_id = cp.plan_id
                LEFT JOIN styles s ON ls.style_id = s.style_id
                LEFT JOIN markers m ON ls.marker_id = m.marker_id
                LEFT JOIN cutting_rooms cr ON ls.cutting_room_id = cr.cutting_room_id
                ORDER BY ls.lay_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Fabric rolls
            $fabric_rolls = $this->db->query("
                SELECT fr.*, s.style_code, s.style_name, cr.cutting_room_name
                FROM fabric_rolls fr
                LEFT JOIN styles s ON fr.style_id = s.style_id
                LEFT JOIN cutting_rooms cr ON fr.cutting_room_id = cr.cutting_room_id
                ORDER BY fr.roll_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Cutting executions
            $executions = $this->db->query("
                SELECT ce.*, cp.plan_number, s.style_code, cr.cutting_room_name
                FROM cutting_executions ce
                LEFT JOIN cutting_plans cp ON ce.plan_id = cp.plan_id
                LEFT JOIN styles s ON ce.style_id = s.style_id
                LEFT JOIN cutting_rooms cr ON ce.cutting_room_id = cr.cutting_room_id
                ORDER BY ce.execution_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Numbering logs
            $numbering_logs = $this->db->query("
                SELECT pn.*, bg.bundle_number, s.style_code
                FROM panel_numbering pn
                LEFT JOIN bundle_generation bg ON pn.bundle_id = bg.bundle_id
                LEFT JOIN styles s ON pn.style_id = s.style_id
                ORDER BY pn.numbering_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Ticketing logs
            $ticketing_logs = $this->db->query("
                SELECT bt.*, bg.bundle_number, s.style_code
                FROM bundle_tickets bt
                LEFT JOIN bundle_generation bg ON bt.bundle_id = bg.bundle_id
                LEFT JOIN styles s ON bt.style_id = s.style_id
                ORDER BY bt.ticket_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // QC records
            $qc_records = $this->db->query("
                SELECT cq.*, cp.plan_number, s.style_code, cr.cutting_room_name
                FROM cutting_qc cq
                LEFT JOIN cutting_plans cp ON cq.plan_id = cp.plan_id
                LEFT JOIN styles s ON cq.style_id = s.style_id
                LEFT JOIN cutting_rooms cr ON cq.cutting_room_id = cr.cutting_room_id
                ORDER BY cq.qc_id DESC
                LIMIT 50
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stats = [
                'total_plans'       => count($plans),
                'active_plans'      => $active_plans,
                'completed_plans'   => $completed_plans,
                'total_cut_quantity'=> $total_cut,
                'total_markers'     => count($markers),
                'approved_markers'  => $approved_markers,
                'avg_efficiency'    => $efficiency_count > 0 ? round($total_efficiency / $efficiency_count, 1) : 0,
                'total_bundles'     => count($bundles),
                'active_bundles'    => $active_bundles,
                'total_pieces'      => $total_pieces,
                'total_lays'        => count($lays),
                'total_fabric_rolls'=> count($fabric_rolls),
                'total_executions'  => count($executions),
                'qc_passed'         => count(array_filter($qc_records, fn($q) => ($q['result'] ?? '') === 'pass')),
                'qc_failed'         => count(array_filter($qc_records, fn($q) => ($q['result'] ?? '') === 'fail')),
            ];
        } catch (\Exception $e) {
            // Tables may not exist yet – silently use empty arrays
            error_log('CuttingController data error: ' . $e->getMessage());
        }

        // Render Cutting view
        $this->view('Cutting/index', [
            'activeTab'       => $tab,
            'plans'           => $plans,
            'markers'         => $markers,
            'bundles'         => $bundles,
            'orders'          => $orders,
            'styles'          => $styles,
            'cutting_rooms'   => $cutting_rooms,
            'lays'            => $lays,
            'fabric_rolls'    => $fabric_rolls,
            'executions'      => $executions,
            'numbering_logs'  => $numbering_logs,
            'ticketing_logs'  => $ticketing_logs,
            'qc_records'      => $qc_records,
            'stats'           => $stats,
            'pageTitle'       => 'Cutting Control Center',
        ]);
    }

    private function handleCreatePlan(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO cutting_plans
                 (plan_number, order_id, style_id, total_quantity, completed_quantity,
                  planned_date, cutting_room_id, marker_efficiency, spreading_method, notes, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'planned', ?, NOW(), NOW())",
                [
                    $data['plan_number'] ?? ('CP-' . date('Ymd') . '-' . rand(100, 999)),
                    (int)($data['order_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    (int)($data['total_quantity'] ?? 0),
                    $data['planned_date'] ?? date('Y-m-d'),
                    (int)($data['cutting_room_id'] ?? 0),
                    (float)($data['marker_efficiency'] ?? 0),
                    $data['spreading_method'] ?? '',
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'Cutting plan created successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to create cutting plan: ' . $e->getMessage();
        }
        $this->redirect('cutting/plans');
    }

    private function handleCreateMarker(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO markers
                 (marker_number, plan_id, style_id, marker_type, total_pieces,
                  marker_length, marker_width, fabric_consumption, efficiency,
                  created_date, notes, created_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
                [
                    $data['marker_number'] ?? ('MK-' . date('Ymd') . '-' . rand(100, 999)),
                    (int)($data['plan_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    $data['marker_type'] ?? 'Standard',
                    (int)($data['total_pieces'] ?? 0),
                    (float)($data['marker_length'] ?? 0),
                    (float)($data['marker_width'] ?? 0),
                    (float)($data['fabric_consumption'] ?? 0),
                    (float)($data['efficiency'] ?? 0),
                    $data['created_date'] ?? date('Y-m-d'),
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'Marker created successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to create marker: ' . $e->getMessage();
        }
        $this->redirect('cutting/markers');
    }

    private function handleCreateLay(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO lay_sheets
                 (lay_number, plan_id, style_id, marker_id, cutting_room_id,
                  total_plies, fabric_width, lay_length, spreading_method,
                  relaxation_hours, lay_date, notes, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
                [
                    $data['lay_number'] ?? ('LAY-' . date('Ymd') . '-' . rand(100, 999)),
                    (int)($data['plan_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    (int)($data['marker_id'] ?? 0) ?: null,
                    (int)($data['cutting_room_id'] ?? 0),
                    (int)($data['total_plies'] ?? 0),
                    (float)($data['fabric_width'] ?? 0),
                    (float)($data['lay_length'] ?? 0),
                    $data['spreading_method'] ?? 'Face-to-face',
                    (int)($data['relaxation_hours'] ?? 24),
                    $data['lay_date'] ?? date('Y-m-d'),
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'Lay sheet created successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to create lay sheet: ' . $e->getMessage();
        }
        $this->redirect('cutting/lay');
    }

    private function handleCreateBundle(): void
    {
        try {
            $data = $this->request->all();
            $bundleNumber = $data['bundle_number'] ?? ('BD-' . date('Ymd') . '-' . rand(100, 999));
            $quantity = (int)($data['bundle_quantity'] ?? 0);

            $this->db->beginTransaction();

            $this->db->query(
                "INSERT INTO bundle_generation
                 (bundle_number, marker_id, style_id, size_name, quantity,
                  bundle_type, generated_date, cutting_room_id, notes,
                  piece_count, cut_pieces, created_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'generated')",
                [
                    $bundleNumber,
                    (int)($data['marker_id'] ?? 0) ?: null,
                    (int)($data['style_id'] ?? 0),
                    $data['size_name'] ?? '',
                    $quantity,
                    $data['bundle_type'] ?? 'Standard',
                    $data['generated_date'] ?? date('Y-m-d'),
                    (int)($data['cutting_room_id'] ?? 0),
                    $data['notes'] ?? '',
                    $quantity,
                    $quantity,
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $bundleId = (int)$this->db->lastInsertId();

            // Generate piece rows
            for ($i = 1; $i <= $quantity; $i++) {
                $pieceNumber = $bundleNumber . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
                $this->db->query(
                    "INSERT INTO bundle_pieces (bundle_id, piece_number, piece_status) VALUES (?, ?, 'cut')",
                    [$bundleId, $pieceNumber]
                );
            }

            $this->db->commit();
            $_SESSION['success_message'] = 'Bundle generated successfully!';
        } catch (\Exception $e) {
            try { $this->db->rollBack(); } catch (\Exception $ex) {}
            $_SESSION['error_message'] = 'Failed to generate bundle: ' . $e->getMessage();
        }
        $this->redirect('cutting/bundles');
    }

    private function handleCreateExecution(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO cutting_executions
                 (execution_number, plan_id, style_id, cutting_room_id,
                  operator_name, cutting_date, start_time, end_time,
                  cut_quantity, defect_quantity, notes, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)",
                [
                    $data['execution_number'] ?? ('EX-' . date('Ymd') . '-' . rand(100, 999)),
                    (int)($data['plan_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    (int)($data['cutting_room_id'] ?? 0),
                    $data['operator_name'] ?? '',
                    $data['cutting_date'] ?? date('Y-m-d'),
                    $data['start_time'] ?? null,
                    $data['end_time'] ?? null,
                    (int)($data['cut_quantity'] ?? 0),
                    (int)($data['defect_quantity'] ?? 0),
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'Cutting execution logged successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to log execution: ' . $e->getMessage();
        }
        $this->redirect('cutting/execution');
    }

    private function handleCreateNumbering(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO panel_numbering
                 (bundle_id, style_id, size_name, panel_count, numbering_date,
                  operator_name, notes, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)",
                [
                    (int)($data['bundle_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    $data['size_name'] ?? '',
                    (int)($data['panel_count'] ?? 0),
                    $data['numbering_date'] ?? date('Y-m-d'),
                    $data['operator_name'] ?? '',
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'Numbering log saved successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to save numbering: ' . $e->getMessage();
        }
        $this->redirect('cutting/numbering');
    }

    private function handleCreateTicketing(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO bundle_tickets
                 (bundle_id, style_id, size_name, ticket_count, ticket_type,
                  printed_date, operator_name, notes, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'printed', ?)",
                [
                    (int)($data['bundle_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    $data['size_name'] ?? '',
                    (int)($data['ticket_count'] ?? 0),
                    $data['ticket_type'] ?? 'Barcode',
                    $data['printed_date'] ?? date('Y-m-d'),
                    $data['operator_name'] ?? '',
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'Tickets printed successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to print tickets: ' . $e->getMessage();
        }
        $this->redirect('cutting/ticketing');
    }

    private function handleCreateQC(): void
    {
        try {
            $data = $this->request->all();
            $this->db->query(
                "INSERT INTO cutting_qc
                 (plan_id, style_id, cutting_room_id, inspector_name,
                  inspection_date, checked_quantity, defect_quantity,
                  defect_type, result, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    (int)($data['plan_id'] ?? 0),
                    (int)($data['style_id'] ?? 0),
                    (int)($data['cutting_room_id'] ?? 0),
                    $data['inspector_name'] ?? '',
                    $data['inspection_date'] ?? date('Y-m-d'),
                    (int)($data['checked_quantity'] ?? 0),
                    (int)($data['defect_quantity'] ?? 0),
                    $data['defect_type'] ?? '',
                    $data['result'] ?? 'pass',
                    $data['notes'] ?? '',
                    $_SESSION['user_id'] ?? 1,
                ]
            );
            $_SESSION['success_message'] = 'QC inspection recorded successfully!';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Failed to save QC record: ' . $e->getMessage();
        }
        $this->redirect('cutting/qc');
    }
}
