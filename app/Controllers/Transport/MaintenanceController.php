<?php

declare(strict_types=1);

namespace App\Controllers\Transport;

use App\Controllers\BaseController;
use App\Models\MaintenanceSchedule;
use App\Models\Transport\Vehicle;
use Core\Container;

class MaintenanceController extends BaseController
{
    private MaintenanceSchedule $maintenanceModel;
    private Vehicle $vehicleModel;

    public function __construct()
    {
        parent::__construct();
        $this->maintenanceModel = new MaintenanceSchedule();
        $this->vehicleModel = new Vehicle();
    }

    /**
     * Display maintenance list page
     */
    public function index(): void
    {
        // Check authentication - support both legacy and MVC auth
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        // Get filters from request
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        // Get data
        $maintenanceRecords = $this->maintenanceModel->searchWithFilters($search, $status, $dateFrom, $dateTo);
        $vehicles = $this->vehicleModel->getActive();

        // Render view
        $this->view('transport/maintenance/index', [
            'maintenance' => $maintenanceRecords,
            'vehicles' => $vehicles,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ],
            'user' => $this->auth->user() ?? [],
            'session' => $_SESSION,
            'app_base_url' => rtrim(dirname(ASSETS_URL), '/'),
            'message' => $_SESSION['message'] ?? ''
        ]);

        // Clear message after displaying
        unset($_SESSION['message']);
    }

    /**
     * Store new maintenance record
     */
    public function store(): void
    {
        $this->verifyCsrf();
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        // Validate inputs
        $vehicleId = intval($_POST['vehicle_id'] ?? 0);
        $maintenanceType = trim($_POST['maintenance_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduledDate = $_POST['scheduled_date'] ?? '';
        $cost = floatval($_POST['cost'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if (empty($vehicleId)) {
            $_SESSION['message'] = 'Error: Please select a vehicle';
            $this->redirect('?page=vehicle_management/maintenance');
            return;
        }

        if (empty($scheduledDate)) {
            $_SESSION['message'] = 'Error: Scheduled date is required';
            $this->redirect('?page=vehicle_management/maintenance');
            return;
        }

        // Validate date format
        $date = \DateTime::createFromFormat('Y-m-d', $scheduledDate);
        if ($date === false) {
            $_SESSION['message'] = 'Error: Invalid date format';
            $this->redirect('?page=vehicle_management/maintenance');
            return;
        }

        try {
            $data = [
                'vehicle_id' => $vehicleId,
                'maintenance_type' => $maintenanceType,
                'description' => $description,
                'scheduled_date' => $date->format('Y-m-d'),
                'cost' => $cost,
                'status' => 'scheduled',
                'notes' => $notes,
                'created_by' => $this->auth->user()['user_id'] ?? $_SESSION['user_id']
            ];

            $this->maintenanceModel->create($data);
            $_SESSION['message'] = 'Maintenance record added successfully!';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('?page=vehicle_management/maintenance');
    }

    /**
     * Update maintenance status
     */
    public function updateStatus(): void
    {
        $this->verifyCsrf();
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $maintenanceId = intval($_POST['maintenance_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        if (empty($maintenanceId) || empty($newStatus)) {
            $_SESSION['message'] = 'Error: Invalid request';
            $this->redirect('?page=vehicle_management/maintenance');
            return;
        }

        try {
            $this->maintenanceModel->updateStatus($maintenanceId, $newStatus);
            $_SESSION['message'] = 'Maintenance status updated successfully!';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('?page=vehicle_management/maintenance');
    }

    /**
     * Delete maintenance record
     */
    public function destroy(): void
    {
        $this->verifyCsrf();
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $maintenanceId = intval($_POST['maintenance_id'] ?? 0);

        if (empty($maintenanceId)) {
            $_SESSION['message'] = 'Error: Invalid request';
            $this->redirect('?page=vehicle_management/maintenance');
            return;
        }

        try {
            $this->maintenanceModel->delete($maintenanceId);
            $_SESSION['message'] = 'Maintenance record deleted successfully!';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('?page=vehicle_management/maintenance');
    }

    /**
     * Get maintenance record as JSON (for AJAX requests)
     */
    public function show(int $id): void
    {
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $maintenance = $this->maintenanceModel->findWithVehicle($id);
        
        if (!$maintenance) {
            $this->json(['success' => false, 'message' => 'Maintenance record not found'], 404);
            return;
        }

        $this->json(['success' => true, 'data' => $maintenance]);
    }
}
