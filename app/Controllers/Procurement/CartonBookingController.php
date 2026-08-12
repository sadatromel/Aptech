<?php

declare(strict_types=1);

namespace App\Controllers\Procurement;

use App\Controllers\BaseController;
use App\Services\Procurement\CartonBookingService;
use Core\Container;

class CartonBookingController extends BaseController
{
    private CartonBookingService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(CartonBookingService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        // ── Ensure schema is correct on load ────────────────────────────────
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS carton_bookings (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                booking_number VARCHAR(50) NOT NULL UNIQUE,
                booking_date   DATE,
                order_id       INT NULL,
                style_id       INT NULL,
                length         DECIMAL(8,2) DEFAULT 0.00,
                width          DECIMAL(8,2) DEFAULT 0.00,
                height         DECIMAL(8,2) DEFAULT 0.00,
                unit           VARCHAR(10) DEFAULT 'cm',
                cbm_per_carton DECIMAL(10,4) DEFAULT 0.0000,
                total_cbm      DECIMAL(10,4) DEFAULT 0.0000,
                quantity       INT DEFAULT 1,
                status         VARCHAR(30) DEFAULT 'pending',
                notes          TEXT,
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            $columns = [
                'order_id'       => "INT NULL AFTER booking_date",
                'style_id'       => "INT NULL AFTER order_id",
                'buyer_id'       => "INT NULL AFTER style_id",
                'cbm_per_carton' => "DECIMAL(10,4) DEFAULT 0.0000 AFTER unit",
                'total_cbm'      => "DECIMAL(10,4) DEFAULT 0.0000 AFTER cbm_per_carton"
            ];
            
            foreach ($columns as $col => $definition) {
                try {
                    $this->db->query("ALTER TABLE carton_bookings ADD COLUMN {$col} {$definition}");
                } catch (\Exception $e) {
                    // Column already exists
                }
            }
        } catch (\Exception $e) {
            // Error fallback
        }

        // ── Process status update or other custom actions ──────────────────
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->input('action', '');
            
            if ($action === 'update_status') {
                $id = (int)$this->request->input('booking_id', 0);
                $status = $this->request->input('status', 'pending');
                $this->service->updateStatus($id, $status);
                $_SESSION['success_message'] = "Booking status updated to " . strtoupper($status);
                $this->redirect('procurement/carton-booking');
                return;
            }
        }

        $filters = [
            'status' => $this->request->get('status', ''),
            'search' => $this->request->get('search', ''),
        ];

        // Fetch Order, Style, and Buyer lists for dropdown selectors
        $orders = [];
        $styles = [];
        $buyers = [];
        
        try {
            $orders = $this->db->query("SELECT po_id AS id, po_number, quantity FROM production_orders ORDER BY po_id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("CartonBooking orders load fail: " . $e->getMessage());
        }
        
        try {
            $styles = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("CartonBooking styles load fail: " . $e->getMessage());
        }
        
        try {
            $buyers = $this->db->query("SELECT buyer_id, buyer_name, buyer_code FROM buyers WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("CartonBooking buyers load fail: " . $e->getMessage());
        }

        $this->view('procurement/carton-booking/index', [
            'bookings'  => $this->service->listBookings($filters),
            'stats'     => $this->service->getStats(),
            'filters'   => $filters,
            'orders'    => $orders,
            'styles'    => $styles,
            'buyers'    => $buyers,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        $data = $this->request->all();
        
        if (empty($data['booking_date']) || empty($data['length']) || empty($data['width']) || empty($data['height'])) {
             $_SESSION['error_message'] = 'All dimensions are required';
             $this->redirect('procurement/carton-booking');
             return;
        }

        try {
            $this->service->createBooking($data);
            $_SESSION['success_message'] = "Carton Booking registered successfully!";
        } catch (\Throwable $e) {
             $_SESSION['error_message'] = 'Failed to create booking: ' . $e->getMessage();
        }
        $this->redirect('procurement/carton-booking');
    }

    public function destroy(int $bookingId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        try {
            if ($this->service->deleteBooking($bookingId)) {
                $this->json(['success' => true, 'message' => 'Booking deleted successfully.']);
            } else {
                throw new \Exception("Booking not found or already deleted.");
            }
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
