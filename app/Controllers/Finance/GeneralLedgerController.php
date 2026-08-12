<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Services\Finance\GeneralLedgerService;
use Core\Container;

class GeneralLedgerController extends BaseController
{
    private GeneralLedgerService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(GeneralLedgerService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'account_id' => $this->request->get('account_id', ''),
            'cost_center_id' => $this->request->get('cost_center_id', ''),
            'transaction_type' => $this->request->get('transaction_type', ''),
            'date_from' => $this->request->get('date_from', ''),
            'date_to' => $this->request->get('date_to', ''),
            'search' => $this->request->get('search', ''),
        ];

        $this->view('finance/general-ledger/index', [
            'entries' => $this->service->listEntries($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'account_id' => 'required|integer',
            'transaction_date' => 'required|date',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $ledgerId = $this->service->createEntry($data, (int) $this->auth->user()['user_id']);
            $this->json(['success' => true, 'ledger_id' => $ledgerId, 'message' => 'Journal entry created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeJournal(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'transaction_date' => 'required|date',
            'description' => 'required',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->service->createJournalEntry($data, (int) $this->auth->user()['user_id']);
            $this->json(['success' => true, 'message' => 'Journal entry created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(int $ledgerId): void
    {
        $this->requireAuth();
        $entry = $this->service->getEntry($ledgerId);
        if (!$entry) {
            $this->json(['error' => 'Entry not found'], 404);
            return;
        }

        $this->json(['data' => $entry]);
    }

    public function destroy(int $ledgerId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        try {
            $this->service->deleteEntry($ledgerId);
            $this->json(['success' => true, 'message' => 'Entry deleted successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function trialBalance(): void
    {
        Container::get(FinancialReportsController::class)->trialBalance();
    }

    public function profitAndLoss(): void
    {
        Container::get(FinancialReportsController::class)->profitAndLoss();
    }

    public function balanceSheet(): void
    {
        Container::get(FinancialReportsController::class)->balanceSheet();
    }

    public function cashBook(): void
    {
        Container::get(FinancialReportsController::class)->cashBook();
    }
}
