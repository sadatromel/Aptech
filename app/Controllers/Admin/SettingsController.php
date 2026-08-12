<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class SettingsController extends BaseController
{
    public function index(): void
    {
        $this->requireRole('admin');

        // Fetch all settings
        $settings = $this->getAllSettings();
        $environment = $settings['environment'] ?? 'production';
        
        $env_colors = [
            'development' => 'info',
            'staging' => 'warning',
            'production' => 'success'
        ];
        $env_color = $env_colors[$environment] ?? 'secondary';

        $this->view('admin/settings', [
            'settings' => $settings,
            'environment' => $environment,
            'env_color' => $env_color,
            'is_super_admin' => ($_SESSION['role_id'] ?? 0) == 1,
            'page_title' => 'System Settings'
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();
        $this->requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->jsonResponse(['success' => false, 'error' => 'Invalid request method']);
             return;
        }

        try {
            $settings = $_POST['settings'] ?? [];
            
            // Handle File Uploads
            $uploadDir = __DIR__ . '/../../../assets/uploads/settings/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $files = ['logo_file' => 'logo_url', 'favicon_file' => 'favicon_url'];

            foreach ($files as $fileKey => $settingKey) {
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES[$fileKey]['tmp_name'];
                    $fileName = $_FILES[$fileKey]['name'];
                    $fileSize = $_FILES[$fileKey]['size'];
                    $fileType = $_FILES[$fileKey]['type'];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));

                    // Sanitize file name
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    
                    $allowedfileExtensions = array('jpg', 'gif', 'png', 'ico', 'jpeg');

                    if (in_array($fileExtension, $allowedfileExtensions)) {
                        $dest_path = $uploadDir . $newFileName;
                        
                        if(move_uploaded_file($fileTmpPath, $dest_path)) {
                            // Save relative path to DB
                             $settings[$settingKey] = '/Aptech/assets/uploads/settings/' . $newFileName;
                        }
                    }
                }
            }



            // Start transaction
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                // Check if setting exists
                $count = $this->db->count('settings', ['setting_key' => $key]);
                
                $userId = $_SESSION['user_id'] ?? null;

                if ($count > 0) {
                    $this->db->update('settings', [
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $userId
                    ], ['setting_key' => $key]);
                } else {
                    $this->db->insert('settings', [
                        'setting_key' => $key,
                        'value' => $value,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $userId
                    ]);
                }
                
                // Audit log would go here
            }

            $this->db->commit();
            
            $this->jsonResponse(['success' => true, 'message' => 'Settings saved successfully']);

        } catch (\Throwable $e) {
            if (isset($this->db) && method_exists($this->db, 'rollBack')) {
                try { $this->db->rollBack(); } catch (\Exception $ex) {}
            }
            // Log the error if logger exists
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    // Helper to get all settings as key-value pair
    private function getAllSettings(): array
    {
        $rawSettings = $this->db->select('settings', ['setting_key', 'value']);
        $settings = [];
        foreach ($rawSettings as $row) {
            $settings[$row['setting_key']] = $row['value'];
        }
        return $settings;
    }

    protected function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
