<?php

declare(strict_types=1);

namespace App\Controllers\Visitor;

use App\Controllers\BaseController;
use App\Models\Visitor\Visitor;

class VisitorController extends BaseController
{
    private Visitor $visitorModel;

    public function __construct()
    {
        parent::__construct();
        $this->visitorModel = new Visitor();
    }

    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        // Check Permissions
        if (!canView('Visitor', 'visitor_log')) {
            $this->response->setStatusCode(403);
            die('Access Denied: You do not have permission to view this page.');
        }

        $visitors = $this->visitorModel->all(); // Assuming all() or findAll() exists in base Model, usually standard
        
        // Calculate stats
        $stats = [
            'total_today' => 0,
            'current_in_building' => 0,
            'checked_out_today' => 0
        ];
        
        $today = date('Y-m-d');
        
        // Optimize: Should perform DB count queries in Model, but array filter is fine for small scale
        // Ideally: $this->visitorModel->getStats($today);
        
        foreach ($visitors as $visitor) {
            $checkInDate = date('Y-m-d', strtotime($visitor['check_in_time']));
            
            if ($checkInDate === $today) {
                $stats['total_today']++;
                if ($visitor['status'] === 'Checked Out') {
                    $stats['checked_out_today']++;
                }
            }
            
            if ($visitor['status'] === 'Checked In') {
                $stats['current_in_building']++;
            }
        }
        
        // Sort visitors by check_in_time desc
        usort($visitors, function($a, $b) {
            return strtotime($b['check_in_time']) - strtotime($a['check_in_time']);
        });

        $this->view('visitor/visitor_log', [
            'visitors' => $visitors,
            'stats' => $stats,
            'title' => 'Visitor Log'
        ]);
    }

    public function create(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }
        
        // Check Permissions
        if (!canCreate('Visitor', 'add_visitor') && !canView('Visitor', 'add_visitor')) {
             $this->response->setStatusCode(403);
             die('Access Denied: You do not have permission to access this page.');
        }

        $this->view('visitor/add_visitor', [
            'title' => 'Check In Visitor'
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->redirect('visitor_log');
             return;
        }

        // Check Permissions
        if (!canCreate('Visitor', 'add_visitor')) {
             $this->response->setStatusCode(403);
             die('Access Denied: You do not have permission to create visitors.');
        }

        // Basic validation
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        if (empty($name) || empty($phone)) {
            // handle error
        }
        
        $data = [
            'name' => $name,
            'phone' => $phone,
            'email' => $_POST['email'] ?? '',
            'category' => $_POST['category'] ?? 'Visitor',
            'company_name' => $_POST['company_name'] ?? '',
            'purpose' => $_POST['purpose'] ?? '',
            'meet_person' => $_POST['meet_person'] ?? '',
            'department' => $_POST['department'] ?? '',
            'id_type' => $_POST['id_type'] ?? '',
            'id_number' => $_POST['id_number'] ?? '',
            'card_number' => $_POST['card_number'] ?? '',
            'check_in_time' => date('Y-m-d H:i:s'),
            'status' => 'Checked In',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        // Handle Photo Upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
             $uploadDir = 'public/uploads/visitors/';
             if (!is_dir($uploadDir)) {
                 mkdir($uploadDir, 0777, true);
             }
             $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
             $targetPath = $uploadDir . $fileName;
             if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                 $data['photo_path'] = $targetPath;
             }
        }

        $this->visitorModel->create($data);
        
        $this->redirect('visitor_log');
    }
    
    public function checkOut(): void
    {
         if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
             // Check Permissions
             if (!canEdit('Visitor', 'visitor_log')) { 
                 // Using 'visitor_log' edit permission for check out as it is an update action
                 $_SESSION['error'] = 'Access Denied';
                 $this->redirect('visitor_log');
                 return;
             }
             
             $id = (int)$_POST['id'];
             $this->visitorModel->update($id, [
                 'status' => 'Checked Out',
                 'check_out_time' => date('Y-m-d H:i:s')
             ]);
         }
         $this->redirect('visitor_log');
    }
}
