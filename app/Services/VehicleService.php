<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class VehicleService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Get all vehicles
     */
    public function getAllVehicles(): array
    {
        $query = "SELECT v.*, vt.type_name as vehicle_type, s.name as status_name
                  FROM vehicles v
                  LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.vehicle_type_id
                  LEFT JOIN vehicle_status s ON v.status = s.status_id
                  ORDER BY v.vehicle_number";
        
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get vehicle by ID
     */
    public function getVehicleById(int $id): ?array
    {
        $query = "SELECT v.*, vt.type_name as vehicle_type, s.name as status_name
                  FROM vehicles v
                  LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.vehicle_type_id
                  LEFT JOIN vehicle_status s ON v.status = s.status_id
                  WHERE v.vehicle_id = ?";
        
        $result = $this->db->query($query, [$id]);
        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create new vehicle
     */
    public function createVehicle(array $data): array
    {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO vehicles (
                vehicle_number, vehicle_type_id, make, model, year, license_plate, capacity, status, registration_number, 
                token_number, last_registration_date, registration_due_date, last_token_renewal_date, token_renewal_due_date, assigned_driver,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $this->db->query($query, [
                $data['vehicle_number'],
                $data['vehicle_type_id'],
                $data['make'],
                $data['model'],
                $data['year'],
                $data['license_plate'],
                $data['capacity'],
                $data['status'],
                $data['vehicle_number'], // registration_number
                $data['token_number'] ?? null,
                $data['last_registration_date'] ?: null,
                $data['registration_due_date'] ?: null,
                $data['last_token_renewal_date'] ?: null,
                $data['token_renewal_due_date'] ?: null,
                $data['assigned_driver'] ?: null
            ]);

            $vehicleId = $this->db->lastInsertId();
            $this->db->commit();

            return ['success' => true, 'vehicle_id' => $vehicleId];
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update vehicle
     */
    public function updateVehicle(int $id, array $data): array
    {
        try {
            $query = "UPDATE vehicles 
                      SET vehicle_number = ?, vehicle_type_id = ?, make = ?, model = ?, 
                          year = ?, license_plate = ?, capacity = ?, status = ?, registration_number = ?,
                          token_number = ?, last_registration_date = ?, registration_due_date = ?, 
                          last_token_renewal_date = ?, token_renewal_due_date = ?, assigned_driver = ?,
                          updated_at = NOW()
                      WHERE vehicle_id = ?";
            
            $this->db->query($query, [
                $data['vehicle_number'],
                $data['vehicle_type_id'],
                $data['make'],
                $data['model'],
                $data['year'],
                $data['license_plate'],
                $data['capacity'],
                $data['status'],
                $data['vehicle_number'],
                $data['token_number'] ?? null,
                $data['last_registration_date'] ?: null,
                $data['registration_due_date'] ?: null,
                $data['last_token_renewal_date'] ?: null,
                $data['token_renewal_due_date'] ?: null,
                $data['assigned_driver'] ?: null,
                $id
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete vehicle
     */
    public function deleteVehicle(int $id): array
    {
        try {
            $this->db->beginTransaction();

            // Check if vehicle has active requisitions
            $checkQuery = "SELECT COUNT(*) as count FROM vehicle_requisitions 
                           WHERE vehicle_id = ? AND status IN ('pending', 'approved')";
            $result = $this->db->query($checkQuery, [$id]);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            $count = $row['count'];

            if ($count > 0) {
                return ['success' => false, 'message' => 'Cannot delete vehicle with active requisitions'];
            }

            // Delete vehicle
            $deleteQuery = "DELETE FROM vehicles WHERE vehicle_id = ?";
            $this->db->query($deleteQuery, [$id]);

            $this->db->commit();
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get vehicle types
     */
    public function getVehicleTypes(): array
    {
        $query = "SELECT * FROM vehicle_types ORDER BY type_name";
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get vehicle status options
     */
    public function getVehicleStatuses(): array
    {
        $query = "SELECT * FROM vehicle_status ORDER BY name";
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get vehicle statistics
     */
    public function getVehicleStats(): array
    {
        // Fetch vehicle statistics
        $stats = [];

        // Total vehicles
        $query = "SELECT COUNT(*) as total FROM vehicles";
        $result = $this->db->query($query);
        $stats['total'] = (int) ($result->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

        // By status
        $query = "SELECT s.name, COUNT(v.vehicle_id) as count 
                  FROM vehicle_status s
                  LEFT JOIN vehicles v ON s.status_id = v.status
                  GROUP BY s.status_id, s.name";
        $result = $this->db->query($query);
        $stats['by_status'] = $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // By type
        $query = "SELECT vt.type_name, COUNT(v.vehicle_id) as count 
                  FROM vehicle_types vt
                  LEFT JOIN vehicles v ON vt.vehicle_type_id = v.vehicle_type_id
                  GROUP BY vt.vehicle_type_id, vt.type_name";
        $result = $this->db->query($query);
        $stats['by_type'] = $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $stats;
    }

    /**
     * Get vehicle requisitions
     */
    public function getVehicleRequisitions(): array
    {
        $query = "SELECT vr.*, v.vehicle_number, 
                         u.username as requested_by_name, s.name as status_name
                  FROM vehicle_requisitions vr
                  LEFT JOIN vehicles v ON vr.vehicle_id = v.vehicle_id
                  LEFT JOIN users u ON vr.requested_by = u.user_id
                  LEFT JOIN requisition_status s ON vr.status = s.status_id COLLATE utf8mb4_unicode_ci
                  ORDER BY vr.created_at DESC";
        
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create vehicle requisition
     */
    public function createRequisition(array $data): array
    {
        try {
            $query = "INSERT INTO vehicle_requisitions 
                      (vehicle_id, employee_id, purpose, start_date, end_date, start_time, end_time, requested_by, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $this->db->query($query, [
                $data['vehicle_id'],
                $data['employee_id'],
                $data['purpose'],
                $data['start_date'],
                $data['end_date'],
                $data['start_time'],
                $data['end_time'],
                $data['requested_by'],
                $data['status']
            ]);

            $requisitionId = $this->db->lastInsertId();
            return ['success' => true, 'requisition_id' => $requisitionId];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update requisition status
     */
    public function updateRequisitionStatus(int $id, string $status, int $approvedBy = null): array
    {
        try {
            $query = "UPDATE vehicle_requisitions 
                      SET status = ?, approved_by = ?, approved_at = NOW() 
                      WHERE requisition_id = ?";
            
            $this->db->query($query, [$status, $approvedBy, $id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    /**
     * Get drivers
     */
    public function getDrivers(): array
    {
        $query = "SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) as full_name 
                  FROM employees e
                  JOIN positions p ON e.position_id = p.position_id
                  WHERE p.position_name = 'Driver' AND e.status = 'active'
                  ORDER BY e.first_name";
        
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
