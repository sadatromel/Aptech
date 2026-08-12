<?php

declare(strict_types=1);

namespace App\Controllers\Transport;

use App\Controllers\BaseController;
use App\Models\Transport\Vehicle;
use App\Models\Route;
use Exception;

class TransportController extends BaseController
{
    // -------------------------------------------------------------------------
    // VEHICLES
    // -------------------------------------------------------------------------
    public function vehicles(): void
    {
        $this->requireAuth();
        
        $vehicles = $this->db->select('vehicles', '*', ['ORDER' => 'registration_number']);
        
        $this->view('transport/vehicles/index', [
            'vehicles' => $vehicles
        ]);
    }
    
    public function createVehicle(): void
    {
        $this->requireAuth();
        $this->view('transport/vehicles/create');
    }

    public function storeVehicle(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        try {
            $vehicleModel = new Vehicle();
            $vehicleModel->create([
                'registration_number' => $data['vehicle_number'], // Form field might still be vehicle_number
                'vehicle_type' => $data['type'],
                'total_seats' => $data['capacity'],
                'assigned_driver' => $data['driver_name'] ?? '',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->redirect('/transport/vehicles');
        } catch (Exception $e) {
            $this->redirect('/transport/vehicles/create');
        }
    }

    // -------------------------------------------------------------------------
    // ROUTES
    // -------------------------------------------------------------------------
    public function routes(): void
    {
        $this->requireAuth();
        
        $routes = $this->db->select('routes r', [
            '[>]vehicles v' => ['r.vehicle_id' => 'vehicle_id']
        ], [
            'r.*',
            'v.vehicle_number',
            'v.registration_number'
        ], ['ORDER' => 'route_name']);

        $vehicleModel = new Vehicle();
        $vehicles = $vehicleModel->getActive();
        
        $this->view('transport/routes/index', [
            'routes' => $routes,
            'vehicles' => $vehicles
        ]);
    }

    public function storeRoute(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $vehicleId = intval($_POST['vehicle_id'] ?? 0);
        $routeName = trim($_POST['route_name'] ?? '');
        $startLocation = trim($_POST['start_location'] ?? '');
        $endLocation = trim($_POST['end_location'] ?? '');
        $distance = floatval($_POST['distance'] ?? 0);
        $duration = trim($_POST['duration'] ?? '');

        if (empty($routeName) || empty($startLocation) || empty($endLocation)) {
            // Flash error message if we had a session implementation for it
            $this->redirect('?page=vehicle_routes');
            return;
        }

        try {
            $routeModel = new Route();
            $routeModel->create([
                'route_name' => $routeName,
                'vehicle_id' => $vehicleId ?: null,
                'start_location' => $startLocation,
                'end_location' => $endLocation,
                'distance' => $distance,
                'duration' => $duration,
                'status' => 'active',
                'created_by' => $_SESSION['user_id'] ?? 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log error
        }

        $this->redirect('?page=vehicle_routes');
    }

    public function updateRoute(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            $this->redirect('?page=vehicle_routes');
            return;
        }

        $vehicleId = intval($_POST['vehicle_id'] ?? 0);
        $routeName = trim($_POST['route_name'] ?? '');
        $startLocation = trim($_POST['start_location'] ?? '');
        $endLocation = trim($_POST['end_location'] ?? '');
        $distance = floatval($_POST['distance'] ?? 0);
        $duration = trim($_POST['duration'] ?? '');
        $status = $_POST['status'] ?? 'active';

        try {
            $routeModel = new Route();
            $routeModel->update($id, [
                'route_name' => $routeName,
                'vehicle_id' => $vehicleId ?: null,
                'start_location' => $startLocation,
                'end_location' => $endLocation,
                'distance' => $distance,
                'duration' => $duration,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log error
        }

        $this->redirect('?page=vehicle_routes');
    }

    public function deleteRoute(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $id = intval($_POST['id'] ?? 0);

        if ($id) {
            $routeModel = new Route();
            $routeModel->delete($id);
        }

        $this->redirect('?page=vehicle_routes');
    }

    // -------------------------------------------------------------------------
    // MISSING STUBS to prevent Fatal Errors
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // SEAT ALLOTMENT
    // -------------------------------------------------------------------------
    public function seatAllotment(): void
    {
        $this->requireAuth();
        
        $allotments = $this->db->select('seat_allotments', [
            '[>]employees' => ['employees.employee_id' => 'seat_allotments.employee_id'],
            '[>]vehicles' => ['vehicles.vehicle_id' => 'seat_allotments.vehicle_id'],
            '[>]routes' => ['routes.route_id' => 'seat_allotments.route_id']
        ], [
            'seat_allotments.*',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'vehicles.vehicle_number',
            'vehicles.registration_number',
            'routes.route_name',
            'routes.start_location',
            'routes.end_location'
        ], ['ORDER' => ['seat_allotments.created_at' => 'DESC']]);

        // Fetch Data for Modals
        $activeVehicles = $this->db->select('vehicles', '*', ['status' => 'active', 'ORDER' => 'vehicle_number']);
        $activeRoutes = $this->db->select('routes', '*', ['status' => 'active', 'ORDER' => 'route_name']);
        
        $this->view('transport/seat_allotment/index', [
            'allotments' => $allotments,
            'vehicles' => $activeVehicles,
            'routes' => $activeRoutes
        ]);
    }

    public function searchEmployees(): void
    {
        $this->requireAuth();
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            $this->json([]);
            return;
        }

        $employees = $this->db->select('employees', [
        'employee_id', 'first_name', 'last_name', 'employee_code', 'department_id'
        ], [
            'OR' => [
                'first_name[~]' => $query,
                'last_name[~]' => $query,
                'employee_code[~]' => $query
            ],
            'LIMIT' => 10
        ]);
        
        $this->json($employees);
    }

    public function createSeatAllotment(): void
    {
        $this->requireAuth();
        
        $activeVehicles = $this->db->select('vehicles', '*', ['status' => 'active', 'ORDER' => 'vehicle_number']);
        $activeRoutes = $this->db->select('routes', '*', ['status' => 'active', 'ORDER' => 'route_name']);

        $this->view('transport/seat_allotment/create', [
            'vehicles' => $activeVehicles,
            'routes' => $activeRoutes
        ]);
    }

    public function storeSeatAllotment(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $employeeId = intval($_POST['employee_id'] ?? 0);
        $vehicleId = intval($_POST['vehicle_id'] ?? 0);
        $routeId = intval($_POST['route_id'] ?? 0);
        $seatNumber = trim($_POST['seat_number'] ?? '');
        $validFrom = $_POST['valid_from'] ?? date('Y-m-d');
        $validTo = !empty($_POST['valid_to']) ? $_POST['valid_to'] : null;

        if (empty($employeeId) || empty($vehicleId) || empty($routeId) || empty($seatNumber)) {
            // Should handle error flashing
            $this->redirect('?page=employee_seat_allotment');
            return;
        }

        try {
            $allotmentModel = new \App\Models\SeatAllotment();
            
            // Check if seat is already occupied for this period
            // This is a naive check; overlapping date logic is more complex
            $exists = $allotmentModel->where('vehicle_id', '=', (string)$vehicleId)
                                     ->where('seat_number', '=', $seatNumber)
                                     ->where('status', '=', 'active')
                                     ->get();
            
            // For now, allow overwrite or simple check. 
            // Ideally we check if valid_from/to overlaps.
            
            $allotmentModel->create([
                'employee_id' => $employeeId,
                'vehicle_id' => $vehicleId,
                'route_id' => $routeId,
                'seat_number' => $seatNumber,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'status' => 'active',
                'created_by' => $_SESSION['user_id'] ?? 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            // Log error
        }

        $this->redirect('?page=employee_seat_allotment');
    }

    public function deleteSeatAllotment(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $id = intval($_POST['id'] ?? 0);

        if ($id) {
            $allotmentModel = new \App\Models\SeatAllotment();
            $allotmentModel->delete($id);
        }

        $this->redirect('?page=employee_seat_allotment');
    }

    public function requisition(): void
    {
        $this->requireAuth();
        $this->view('transport/vehicle_requisition');
    }

    public function maintenance(): void
    {
        $this->requireAuth();
        $this->view('transport/maintenance');
    }

    public function partsInventory(): void
    {
        $this->requireAuth();
        // Point to the existing index.twig in the subdirectory
        $this->view('transport/parts-inventory/index');
    }
}
