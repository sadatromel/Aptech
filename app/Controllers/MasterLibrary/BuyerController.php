<?php


namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\Buyer;
use App\Models\MasterLibrary\Country;
use Core\Container;
use Exception;

class BuyerController extends BaseController
{
    private Buyer $buyerModel;
    private Country $countryModel;

    public function __construct()
    {
        parent::__construct();
        $this->buyerModel = Container::get(Buyer::class);
        $this->countryModel = Container::get(Country::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? ''
        ];

        $this->view('master_library/buyers/index', [
            'buyers' => $this->buyerModel->getAllWithFilters($filters),
            'filters' => $filters,
            'stats' => $this->buyerModel->getStats()
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->view('master_library/buyers/create', [
            'countries' => $this->countryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();

        try {
            if (empty($data['buyer_name'])) {
                throw new Exception('Buyer name is required');
            }

            $createData = [
                'buyer_name' => $data['buyer_name'],
                'buyer_code' => $data['buyer_code'] ?? '',
                'country' => $data['country'] ?? '',
                'contact_person' => $data['contact_person'] ?? '',
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? '',
                'address' => $data['address'] ?? '',
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->buyerModel->create($createData);
            
            $_SESSION['success_message'] = "Buyer created successfully";
            $this->redirect('master_library/buyers');

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect('master_library/buyers/create');
        }
    }
    
    public function edit(int $id): void
    {
        $this->requireAuth();
        $buyer = $this->buyerModel->find($id);
        
        if (!$buyer) {
            $_SESSION['error_message'] = "Buyer not found";
            $this->redirect('master_library/buyers');
            return;
        }

        $this->view('master_library/buyers/edit', [
            'buyer' => $buyer,
            'countries' => $this->countryModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();

        try {
            $updateData = [
                'buyer_name' => $data['buyer_name'],
                'buyer_code' => $data['buyer_code'] ?? '',
                'country' => $data['country'] ?? '',
                'contact_person' => $data['contact_person'] ?? '',
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? '',
                'address' => $data['address'] ?? '',
                'status' => $data['status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->buyerModel->update($id, $updateData);
            
            $_SESSION['success_message'] = "Buyer updated successfully";
            $this->redirect('master_library/buyers');

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect("master_library/buyers/edit/$id");
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        try {
            // Check for dependencies (Styles) - Using generic Model count logic if accessible or direct DB if needed primarily for checks
            // Or better, move specific logic to Model: $this->buyerModel->canDelete($id)
            $count = $this->db->count('styles', ['buyer_id' => $id]);
            if ($count > 0) {
                throw new Exception("Cannot delete buyer. Associated with $count styles.");
            }

            $this->buyerModel->delete($id);
            
            $_SESSION['success_message'] = "Buyer deleted successfully";
            $this->redirect('master_library/buyers');

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect('master_library/buyers');
        }
    }
    
    public function show(int $id): void
    {
        $this->edit($id);
    }
}

