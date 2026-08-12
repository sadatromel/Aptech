<?php

class Route {
    private $db;
    private $table = 'vehicle_routs';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all routes with vehicle details
     */
    public function getAll() {
        $sql = "SELECT vr.*, v.registration_number, v.make, v.model 
                FROM " . $this->table . " vr 
                LEFT JOIN vehicles v ON vr.vehicle_id = v.vehicle_id 
                WHERE vr.status = 'active' 
                ORDER BY vr.route_name";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get a single route by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE route_id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Create a new route
     */
    public function create($data) {
        $sql = "INSERT INTO " . $this->table . " 
                (route_name, vehicle_id, from_location, to_location, distance_km, estimated_time, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            trim($data['route_name']),
            intval($data['vehicle_id']),
            trim($data['from_location']),
            trim($data['to_location']),
            floatval($data['distance_km']),
            !empty($data['estimated_time']) ? trim($data['estimated_time']) : null,
            in_array($data['status'], ['active', 'inactive']) ? $data['status'] : 'active'
        ];

        return $this->db->query($sql, $params);
    }

    /**
     * Update an existing route
     */
    public function update($id, $data) {
        $sql = "UPDATE " . $this->table . " 
                SET route_name = ?, vehicle_id = ?, from_location = ?, to_location = ?, 
                    distance_km = ?, estimated_time = ?, status = ?, updated_at = NOW() 
                WHERE route_id = ?";
        
        $params = [
            trim($data['route_name']),
            intval($data['vehicle_id']),
            trim($data['from_location']),
            trim($data['to_location']),
            floatval($data['distance_km']),
            !empty($data['estimated_time']) ? trim($data['estimated_time']) : null,
            in_array($data['status'], ['active', 'inactive']) ? $data['status'] : 'active',
            intval($id)
        ];

        return $this->db->query($sql, $params);
    }

    /**
     * Delete a route (soft delete or hard delete based on preference, implementing hard delete as per save_route.php)
     */
    public function delete($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE route_id = ?";
        return $this->db->query($sql, [intval($id)]);
    }

    /**
     * Get active vehicles for dropdown
     */
    public function getActiveVehicles() {
        $sql = "SELECT vehicle_id, registration_number, make, model 
                FROM vehicles 
                WHERE status = 'active' 
                ORDER BY registration_number";
        return $this->db->fetchAll($sql);
    }
}
