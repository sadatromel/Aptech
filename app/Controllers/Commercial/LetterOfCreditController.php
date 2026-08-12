<?php

declare(strict_types=1);

namespace App\Controllers\Commercial;

use App\Controllers\BaseController;
use App\Services\LetterOfCreditService;
use Core\Container;
use Core\Database;

class LetterOfCreditController extends BaseController
{
    private LetterOfCreditService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(LetterOfCreditService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        $lcs = $this->service->getList($filters);
        $stats = $this->service->getSummaryStats();

        /** @var Database $db */
        $db = Container::get(Database::class);
        $buyers = $db->query("SELECT buyer_id, buyer_name FROM buyers ORDER BY buyer_name ASC")->fetchAll();

        $this->view('commercial/letters_of_credit/index', [
            'title'   => 'Letters of Credit (L/C) Management',
            'lcs'     => $lcs,
            'stats'   => $stats,
            'buyers'  => $buyers,
            'filters' => $filters,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $lcNo = $_POST['lc_number'] ?? ('LC-' . date('Ymd') . '-' . rand(100, 999));
        $data = [
            'lc_number'       => $lcNo,
            'lc_type'         => $_POST['lc_type'] ?? 'master_lc',
            'buyer_id'        => (int)($_POST['buyer_id'] ?? 0),
            'opening_bank'    => $_POST['opening_bank'] ?? '',
            'advising_bank'   => $_POST['advising_bank'] ?? '',
            'lc_amount'       => (float)($_POST['lc_amount'] ?? 0),
            'utilized_amount' => (float)($_POST['utilized_amount'] ?? 0),
            'currency'        => $_POST['currency'] ?? 'USD',
            'issue_date'      => $_POST['issue_date'] ?? date('Y-m-d'),
            'expiry_date'     => $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+90 days')),
            'shipment_date'   => $_POST['shipment_date'] ?? date('Y-m-d', strtotime('+60 days')),
            'tenure_days'     => (int)($_POST['tenure_days'] ?? 60),
            'status'          => $_POST['status'] ?? 'active',
            'notes'           => $_POST['notes'] ?? '',
        ];

        try {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $this->service->create($data, $userId);
            $_SESSION['flash_success'] = 'Letter of Credit (L/C) recorded successfully!';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to save L/C: ' . $e->getMessage();
        }

        $this->redirect('/commercial/letters-of-credit');
    }
}
