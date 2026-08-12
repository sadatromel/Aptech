<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\TnaService;
use Core\Container;

class TnaController extends BaseController
{
    private TnaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(TnaService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'search'   => $this->request->get('search', ''),
            'buyer_id' => $this->request->get('buyer_id', ''),
            'style_id' => $this->request->get('style_id', ''),
        ];

        $orders = $this->service->getOrdersTnaList($filters);
        $dropdowns = $this->service->getDropdownData();

        $this->view('merchandising/tna/index', [
            'orders'    => $orders,
            'filters'   => $filters,
            'buyers'    => $dropdowns['buyers'],
            'styles'    => $dropdowns['styles'],
        ]);
    }

    /** AJAX: Get milestones for specific PO */
    public function show(int $poId): void
    {
        $this->requireAuth();

        try {
            $milestones = $this->service->getPoMilestones($poId);
            $this->json(['success' => true, 'milestones' => $milestones]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** AJAX: Update milestone */
    public function updateMilestone(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $userId = (int)($this->auth->user()['user_id'] ?? 0);

        try {
            if ($this->service->updateMilestone($id, $data, $userId)) {
                $this->json(['success' => true, 'message' => 'Milestone updated successfully.']);
            } else {
                $this->json(['success' => false, 'message' => 'Failed to update milestone.'], 500);
            }
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
