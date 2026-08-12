<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\TransferService;
use Core\Container;

class TransferController extends BaseController
{
    private TransferService $transferService;

    public function __construct()
    {
        parent::__construct();
        $this->transferService = Container::get(TransferService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireAnyRole(['merchandising_manager', 'admin', 'manager']);

        $productionOrders = $this->transferService->getProductionOrders();
        $sourcePOId = $this->request->get('source', '');
        
        $this->view('merchandising/transfer/index', [
            'production_orders' => $productionOrders,
            'source_po_id' => $sourcePOId,
            'stats' => $this->transferService->getStats(),
            'recent_transfers' => $this->transferService->getTransferHistory(10)
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireAnyRole(['merchandising_manager', 'admin', 'manager']);

        $data = $this->request->all();
        
        try {
            $result = $this->transferService->executeTransfer($data);
            $_SESSION['flash_success'] = $result['message'];
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function history(): void
    {
        $this->requireAuth();
        $this->requireAnyRole(['merchandising_manager', 'admin', 'manager']);

        $page = (int)($this->request->get('page', '1'));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $transfers = $this->transferService->getTransferHistory($limit);
        
        $this->view('merchandising/transfer/history', [
            'transfers' => $transfers,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    public function show(int $transferId): void
    {
        $this->requireAuth();
        $this->requireAnyRole(['merchandising_manager', 'admin', 'manager']);
        
        try {
            $transferData = $this->transferService->getTransferDetails($transferId);
            $this->view('merchandising/transfer/show', $transferData);
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('merchandising/transfer/history');
        }
    }

    public function getPOSKUs(): void
    {
        $this->requireAuth();
        $this->requireAnyRole(['merchandising_manager', 'admin', 'manager']);

        $poId = (int)$this->request->get('po_id', '0');
        
        if (!$poId) {
            $this->json(['error' => 'PO ID is required'], 400);
            return;
        }

        $skus = $this->transferService->getPOSKUs($poId);
        
        $this->json([
            'success' => true,
            'skus' => $skus
        ]);
    }
}
