<?php

declare(strict_types=1);

namespace App\Controllers\Transport;

use App\Controllers\BaseController;

use App\Services\VehicleService;
use Core\Container;
use App\Http\Response;

class VehicleController extends BaseController
{
    private VehicleService $vehicleService;

    public function __construct()
    {
        parent::__construct();
        $this->vehicleService = Container::get(VehicleService::class);
    }

    /**
     * Show vehicle list (legacy compatibility)
     */
    public function index(): void
    {
        $this->requireAuth();

        $vehicles = $this->vehicleService->getAllVehicles();
        $stats = $this->vehicleService->getVehicleStats();

        $this->view('vehicles/index', [
            'vehicles' => $vehicles,
            'stats' => $stats,
            'pageTitle' => 'Vehicle Management',
            'user' => $this->auth->user(),
            'page' => 'vehicles'
        ]);
    }



    /**
     * Show vehicle details
     */
    public function show(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/login' : '/Aptech/login')->send();
            exit;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $vehicle = $this->vehicleService->getVehicleById($id);

        if (!$vehicle) {
            $_SESSION['error_message'] = 'Vehicle not found';
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles' : '/Aptech/vehicles')->send();
            exit;
        }

        $this->response->view('vehicles/show', [
            'vehicle' => $vehicle,
            'pageTitle' => 'Vehicle Details'
        ])->send();
    }

    /**
     * Show create vehicle form
     */
    public function create(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/login' : '/Aptech/login')->send();
            exit;
        }

        $vehicleTypes = $this->vehicleService->getVehicleTypes();
        $statuses = $this->vehicleService->getVehicleStatuses();
        $drivers = $this->vehicleService->getDrivers();

        $this->response->view('vehicles/create', [
            'vehicleTypes' => $vehicleTypes,
            'statuses' => $statuses,
            'drivers' => $drivers,
            'pageTitle' => 'Add New Vehicle'
        ])->send();
    }

    /**
     * Store new vehicle
     */
    public function store(): void
    {
        $this->verifyCsrf();
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/login' : '/Aptech/login')->send();
            exit;
        }

        $data = [
            'vehicle_number' => $_POST['vehicle_number'] ?? '',
            'vehicle_type_id' => (int) ($_POST['vehicle_type_id'] ?? 0),
            'make' => $_POST['make'] ?? '',
            'model' => $_POST['model'] ?? '',
            'year' => (int) ($_POST['year'] ?? 0),
            'license_plate' => $_POST['license_plate'] ?? '',
            'capacity' => (int) ($_POST['capacity'] ?? 0),
            'status' => $_POST['status'] ?? 1,
            'token_number' => $_POST['token_number'] ?? '',
            'last_registration_date' => $_POST['last_registration_date'] ?? null,
            'registration_due_date' => $_POST['registration_due_date'] ?? null,
            'last_token_renewal_date' => $_POST['last_token_renewal_date'] ?? null,
            'token_renewal_due_date' => $_POST['token_renewal_due_date'] ?? null,
            'assigned_driver' => $_POST['assigned_driver'] ?? null
        ];

        $result = $this->vehicleService->createVehicle($data);

        if ($result['success']) {
            $_SESSION['success_message'] = 'Vehicle created successfully';
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles' : '/Aptech/vehicles')->send();
        } else {
            $_SESSION['error_message'] = $result['message'];
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles?action=create' : '/Aptech/vehicles?action=create')->send();
        }
        exit;
    }

    /**
     * Show edit vehicle form
     */
    public function edit(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/login' : '/Aptech/login')->send();
            exit;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $vehicle = $this->vehicleService->getVehicleById($id);

        if (!$vehicle) {
            $_SESSION['error_message'] = 'Vehicle not found';
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles' : '/Aptech/vehicles')->send();
            exit;
        }

        $vehicleTypes = $this->vehicleService->getVehicleTypes();
        $statuses = $this->vehicleService->getVehicleStatuses();
        $drivers = $this->vehicleService->getDrivers();

        $this->response->view('vehicles/edit', [
            'vehicle' => $vehicle,
            'vehicleTypes' => $vehicleTypes,
            'statuses' => $statuses,
            'drivers' => $drivers,
            'pageTitle' => 'Edit Vehicle'
        ])->send();
    }

    /**
     * Update vehicle
     */
    public function update(): void
    {
        $this->verifyCsrf();
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/login' : '/Aptech/login')->send();
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'vehicle_number' => $_POST['vehicle_number'] ?? '',
            'vehicle_type_id' => (int) ($_POST['vehicle_type_id'] ?? 0),
            'make' => $_POST['make'] ?? '',
            'model' => $_POST['model'] ?? '',
            'year' => (int) ($_POST['year'] ?? 0),
            'license_plate' => $_POST['license_plate'] ?? '',
            'capacity' => (int) ($_POST['capacity'] ?? 0),
            'status' => $_POST['status'] ?? 1,
            'token_number' => $_POST['token_number'] ?? '',
            'last_registration_date' => $_POST['last_registration_date'] ?? null,
            'registration_due_date' => $_POST['registration_due_date'] ?? null,
            'last_token_renewal_date' => $_POST['last_token_renewal_date'] ?? null,
            'token_renewal_due_date' => $_POST['token_renewal_due_date'] ?? null,
            'assigned_driver' => $_POST['assigned_driver'] ?? null
        ];

        $result = $this->vehicleService->updateVehicle($id, $data);

        if ($result['success']) {
            $_SESSION['success_message'] = 'Vehicle updated successfully';
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles' : '/Aptech/vehicles')->send();
        } else {
            $_SESSION['error_message'] = $result['message'];
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles?action=edit&id=' . $id : '/Aptech/vehicles?action=edit&id=' . $id)->send();
        }
        exit;
    }

    /**
     * Delete vehicle
     */
    public function destroy(): void
    {
        $this->verifyCsrf();
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/login' : '/Aptech/login')->send();
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $result = $this->vehicleService->deleteVehicle($id);

        if ($result['success']) {
            $_SESSION['success_message'] = 'Vehicle deleted successfully';
        } else {
            $_SESSION['error_message'] = $result['message'];
        }

        $this->response->redirect(defined('BASE_URL') ? BASE_URL . '/vehicles' : '/Aptech/vehicles')->send();
        exit;
    }

    /**
     * Show vehicle requisitions
     */
    public function requisitions(): void
    {
        $this->requireAuth();

        $requisitions = $this->vehicleService->getVehicleRequisitions();

        $this->view('vehicles/requisitions', [
            'requisitions' => $requisitions,
            'pageTitle' => 'Vehicle Requisitions',
            'user' => $this->auth->user(),
            'page' => 'vehicle_requisition'
        ]);
    }

    /**
     * Create vehicle requisition
     */
    /**
     * Create vehicle requisition
     */
    public function createRequisition(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'vehicle_id' => (int) ($_POST['vehicle_id'] ?? 0),
                'employee_id' => (int) ($_POST['employee_id'] ?? 0),
                'purpose' => $_POST['purpose'] ?? '',
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'requested_by' => (int) $_SESSION['user_id'],
                'status' => 'pending'
            ];

            $result = $this->vehicleService->createRequisition($data);

            if ($result['success']) {
                $_SESSION['success_message'] = 'Requisition created successfully';
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/vehicle_requisitions');
            } else {
                $_SESSION['error_message'] = $result['message'];
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/vehicle_requisitions');
            }
            exit;
        }

        // If GET request, redirect to requisitions list where the modal exists
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/vehicle_requisitions');
        exit;
    }

    /**
     * Update requisition status
     */
    public function updateRequisitionStatus(): void
    {
        $this->verifyCsrf();
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $approvedBy = (int) $_SESSION['user_id'];

        $result = $this->vehicleService->updateRequisitionStatus($id, $status, $approvedBy);

        if ($result['success']) {
            $_SESSION['success_message'] = 'Requisition status updated successfully';
        } else {
            $_SESSION['error_message'] = $result['message'];
        }

        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/vehicle_requisitions');
        exit;
    }
}
