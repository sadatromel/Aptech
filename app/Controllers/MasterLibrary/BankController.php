<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\Bank;
use Core\Container;

class BankController extends BaseController
{
    private Bank $bankModel;

    public function __construct()
    {
        parent::__construct();
        $this->bankModel = Container::get(Bank::class);
    }

    public function index(): void
    {
        $data = [
            'banks' => $this->bankModel->all()
        ];
        $this->view('master_library/bank/index', $data);
    }

    public function import(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            
            if (is_uploaded_file($file)) {
                $handle = fopen($file, "r");
                $header = fgetcsv($handle, 1000, ","); // Skip header
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Start from index 1 if index 0 is ID (depending on CSV structure)
                    // Assuming structure: Name, Account Number, Branch, SWIFT, Address, Contact, Phone
                    // Adjust indices based on your qualified Excel/CSV template
                    
                    // Simple validation - check if bank name exists
                    if (!empty($data[0])) {
                        $bankData = [
                            'bank_name' => $data[0] ?? '',
                            'account_number' => $data[1] ?? '',
                            'branch_name' => $data[2] ?? '',
                            'swift_code' => $data[3] ?? '',
                            'address' => $data[4] ?? '',
                            'contact_person' => $data[5] ?? '',
                            'phone' => $data[6] ?? '',
                            'bank_status' => 'Active',
                            'created_by' => $_SESSION['user_id'] ?? 1 // Fallback or current user
                        ];
                        
                        // Check availability or strict duplication logic here if needed
                        $this->bankModel->create($bankData);
                    }
                }
                fclose($handle);
                // Redirect with success message (if flash messages implemented)
            }
        }
        $this->redirect('master_library/banks');
    }

    public function create(): void
    {
        $this->view('master_library/bank/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->bankModel->create($data);
        $this->redirect('master_library/banks');
    }

    public function edit(int $id): void
    {
        $bank = $this->bankModel->find($id);
        $this->view('master_library/bank/edit', ['bank' => $bank]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->bankModel->update($id, $data);
        $this->redirect('master_library/banks');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->bankModel->delete($id);
        $this->redirect('master_library/banks');
    }
}
