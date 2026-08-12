<?php

class Vehicle {
    private $db;
    private $table = 'vehicles';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all active vehicles
     */
    public function getAllActive() {
        $sql = "SELECT * FROM " . $this->table . " WHERE status = 'active' ORDER BY created_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get all vehicles (for admin or list view if needed, currently matching vehicles.php logic which filters by active)
     */
    public function getAll() {
        $sql = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get a single vehicle by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE vehicle_id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Create a new vehicle
     */
    public function create($data) {
        // Handle image upload if present
        $imageFilename = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $imageFilename = $this->uploadImage($_FILES['vehicle_image']);
                error_log("Image uploaded successfully: " . $imageFilename);
            } catch (Exception $e) {
                error_log("Image upload failed: " . $e->getMessage());
                $imageFilename = null;
            }
        }
        
        $sql = "INSERT INTO " . $this->table . " 
                (registration_number, make, model, year, total_seats, status, 
                last_registration_date, registration_due_date, token_number, 
                last_token_renewal_date, token_renewal_due_date, vehicle_type, 
                assigned_driver, image_filename, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $status = in_array($data['status'], ['active', 'inactive', 'maintenance', 'retired']) ? $data['status'] : 'active';
        
        $params = [
            trim($data['registration_number']),
            trim($data['make']),
            trim($data['model']),
            intval($data['year']),
            intval($data['total_seats']),
            $status,
            !empty($data['last_registration_date']) ? $data['last_registration_date'] : null,
            !empty($data['registration_due_date']) ? $data['registration_due_date'] : null,
            trim($data['token_number'] ?? ''),
            !empty($data['last_token_renewal_date']) ? $data['last_token_renewal_date'] : null,
            !empty($data['token_renewal_due_date']) ? $data['token_renewal_due_date'] : null,
            $data['vehicle_type'] ?? 'van',
            $data['assigned_driver'] ?? null,
            $imageFilename
        ];

        return $this->db->query($sql, $params);
    }
    
    /**
     * Upload image and return filename
     */
    private function uploadImage($file) {
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed');
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('vehicle_', true) . '.' . $extension;
        
        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../assets/images/vehicles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move uploaded file
        $uploadPath = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        return $filename;
    }

    /**
     * Update an existing vehicle
     */
    public function update($id, $data) {
        // Handle image upload if present
        $imageFilename = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $imageFilename = $this->uploadImage($_FILES['vehicle_image']);
                error_log("Image uploaded successfully during update: " . $imageFilename);
            } catch (Exception $e) {
                error_log("Image upload failed during update: " . $e->getMessage());
                $imageFilename = null;
            }
        } elseif (isset($data['new_image_filename'])) {
            $imageFilename = $data['new_image_filename'];
        }
        
        // Build SQL based on whether we have a new image
        if ($imageFilename !== null) {
            $sql = "UPDATE " . $this->table . " 
                    SET registration_number = ?, make = ?, model = ?, year = ?, total_seats = ?, status = ?, 
                    last_registration_date = ?, registration_due_date = ?, token_number = ?, 
                    last_token_renewal_date = ?, token_renewal_due_date = ?, 
                    vehicle_type = ?, assigned_driver = ?, payload_capacity = ?, load_volume = ?, 
                    load_length = ?, load_width = ?, load_height = ?, image_filename = ? 
                    WHERE vehicle_id = ?";
            error_log("Updating vehicle $id with new image: $imageFilename");
        } else {
            $sql = "UPDATE " . $this->table . " 
                    SET registration_number = ?, make = ?, model = ?, year = ?, total_seats = ?, status = ?, 
                    last_registration_date = ?, registration_due_date = ?, token_number = ?, 
                    last_token_renewal_date = ?, token_renewal_due_date = ?, 
                    vehicle_type = ?, assigned_driver = ?, payload_capacity = ?, load_volume = ?, 
                    load_length = ?, load_width = ?, load_height = ? 
                    WHERE vehicle_id = ?";
            error_log("Updating vehicle $id without image");
        }
        
        $status = in_array($data['status'], ['active', 'inactive', 'maintenance', 'retired']) ? $data['status'] : 'active';

        $params = [
            trim($data['registration_number']),
            trim($data['make']),
            trim($data['model']),
            intval($data['year']),
            intval($data['total_seats']),
            $status,
            !empty($data['last_registration_date']) ? $data['last_registration_date'] : null,
            !empty($data['registration_due_date']) ? $data['registration_due_date'] : null,
            trim($data['token_number'] ?? ''),
            !empty($data['last_token_renewal_date']) ? $data['last_token_renewal_date'] : null,
            !empty($data['token_renewal_due_date']) ? $data['token_renewal_due_date'] : null,
            $data['vehicle_type'] ?? 'van',
            $data['assigned_driver'] ?? null,
            !empty($data['payload_capacity']) ? floatval($data['payload_capacity']) : null,
            !empty($data['load_volume']) ? floatval($data['load_volume']) : null,
            !empty($data['load_length']) ? floatval($data['load_length']) : null,
            !empty($data['load_width']) ? floatval($data['load_width']) : null,
            !empty($data['load_height']) ? floatval($data['load_height']) : null
        ];
        
        // Add image filename if provided
        if ($imageFilename !== null) {
            $params[] = $imageFilename;
        }
        
        $params[] = intval($id);

        return $this->db->query($sql, $params);
    }

    /**
     * Delete a vehicle
     */
    public function delete($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE vehicle_id = ?";
        return $this->db->query($sql, [intval($id)]);
    }
}
