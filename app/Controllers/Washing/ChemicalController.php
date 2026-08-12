<?php

namespace App\Controllers\Washing;

use App\Controllers\BaseController;
use App\Services\ChemicalService;

class ChemicalController extends BaseController
{
    protected ChemicalService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ChemicalService($this->db);
    }

    public function index()
    {
        $data = [
            'chemicals' => $this->service->listChemicals(),
            'stats' => $this->service->getStats(),
            'transactions' => $this->service->getRecentTransactions()
        ];

        $this->view('washing/chemical/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $data = $_POST;
            $this->service->createChemical($data);
            $_SESSION['success_message'] = "Chemical added successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error adding chemical: " . $e->getMessage();
        }
        
        $this->redirect('washing/chemical_controls');
    }

    public function updateStock(int $id)
    {
        $this->verifyCsrf();
        try {
            $newStock = (float)$_POST['new_stock'];
            $type = $_POST['transaction_type'];
            $notes = $_POST['notes'];
            
            $this->service->updateStock($id, $newStock, $type, $notes);
            $_SESSION['success_message'] = "Stock updated successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error updating stock: " . $e->getMessage();
        }

        $this->redirect('washing/chemical_controls');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deleteChemical($id);
            $_SESSION['success_message'] = "Chemical deleted successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting chemical: " . $e->getMessage();
        }

        $this->redirect('washing/chemical_controls');
    }

    public function getData(int $id)
    {
        $chemical = $this->service->getChemical($id);
        if ($chemical) {
            $this->json($chemical);
        } else {
            $this->json(['error' => 'Chemical not found'], 404);
        }
    }
}
