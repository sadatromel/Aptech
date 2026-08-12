<?php
/**
 * Vehicle Requisition Class
 * Handles vehicle requisition management operations
 */

class VehicleRequisition {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Create new requisition
     */
    public function create($data, $userId) {
        $this->validateRequisitionData($data);
        
        $requisitionNumber = 'VR-' . date('Y') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $sql = "INSERT INTO vehicle_requisitions (
            requisition_number, requested_by, purpose, trip_type, priority,
            origin_location, destination_location, departure_date, departure_time, return_date, return_time,
            estimated_distance_km, preferred_vehicle_type, required_seats, special_requirements,
            passenger_names, total_passengers, status, estimated_cost, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())";
        
        $params = [
            $requisitionNumber,
            $requisitionNumber,
            $userId,
            $data['purpose'],
            $data['trip_type'] ?? 'official',
            $data['priority'] ?? 'medium',
            $data['origin_location'],
            $data['destination_location'],
            $data['departure_date'],
            $data['departure_time'] ?? null,
            $data['return_date'] ?? null,
            $data['return_time'] ?? null,
            $data['estimated_distance'] ?? 0,
            $data['preferred_vehicle_type'] ?? 'any',
            $data['required_seats'] ?? 1,
            $data['special_requirements'] ?? '',
            $data['passenger_names'] ?? '',
            $data['total_passengers'] ?? 1,
            $data['estimated_cost'] ?? 0
        ];
        
        $this->db->query($sql, $params);
        $requisitionId = $this->db->lastInsertId();
        
        if (class_exists('Logger')) {
            Logger::activity($userId, 'Requisition Created', "New vehicle requisition $requisitionNumber created");
        }
        
        return $requisitionId;
    }
    
    /**
     * Approve requisition
     */
    public function approve($id, $approverId) {
        $sql = "UPDATE vehicle_requisitions SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE requisition_id = ?";
        $this->db->query($sql, [$approverId, $id]);
        
        if (class_exists('Logger')) {
            Logger::activity($approverId, 'Requisition Approved', "Requisition #$id approved");
        }
        
        return true;
    }
    
    /**
     * Reject requisition
     */
    public function reject($id, $rejectorId, $reason) {
        $sql = "UPDATE vehicle_requisitions SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE requisition_id = ?";
        $this->db->query($sql, [$rejectorId, $reason, $id]);
        
        if (class_exists('Logger')) {
            Logger::activity($rejectorId, 'Requisition Rejected', "Requisition #$id rejected. Reason: $reason");
        }
        
        return true;
    }
    
    /**
     * Delete requisition
     */
    public function delete($id) {
        // Only allow deletion if status is draft or submitted (check should be done in controller/view too, but good to have here)
        // For now, simpler delete
        $sql = "DELETE FROM vehicle_requisitions WHERE requisition_id = ?";
        $this->db->query($sql, [$id]);
        
        return true;
    }
    
    /**
     * Get all requisitions
     */
    public function getAll() {
        $sql = "SELECT 
            vr.*,
            vr.*,
            CONCAT(u1.first_name, ' ', u1.last_name) as requested_by_name,
            CONCAT(u2.first_name, ' ', u2.last_name) as approved_by_name
        FROM vehicle_requisitions vr
        LEFT JOIN users u1 ON vr.requested_by = u1.user_id
        LEFT JOIN users u2 ON vr.approved_by = u2.user_id
        ORDER BY vr.created_at DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get available vehicles
     */
    public function getAvailableVehicles() {
        $sql = "SELECT vehicle_id, registration_number, make, model, total_seats 
                FROM vehicles 
                WHERE status = 'active' 
                ORDER BY registration_number";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Validate data
     */
    private function validateRequisitionData($data) {
        $errors = [];
        if (empty($data['purpose'])) $errors[] = "Purpose is required";
        if (empty($data['origin_location'])) $errors[] = "Origin location is required";
        if (empty($data['destination_location'])) $errors[] = "Destination location is required";
        if (empty($data['departure_date'])) $errors[] = "Departure date is required";
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
    }
    
    /**
     * Get requisition statistics
     */
    /**
     * Get requisition statistics
     */
    public function getStats() {
        $stats = [];
        
        // Pending Requests
        $stats['pending_requests'] = $this->db->fetch("SELECT COUNT(*) as count FROM vehicle_requisitions WHERE status IN ('submitted', 'pending_approval')")['count'];
        
        // Completed Trips (This Month) - Assuming 'completed' status exists, adjusting based on typical logic
        $stats['completed_trips'] = $this->db->fetch("SELECT COUNT(*) as count FROM vehicle_requisitions WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")['count'];
        
        // Total Distance (This Month) - Summing estimated_distance_km for completed trips
        $stats['total_distance'] = $this->db->fetch("SELECT SUM(estimated_distance_km) as total FROM vehicle_requisitions WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")['total'] ?? 0;
        
        return $stats;
    }
}
?>
