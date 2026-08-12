<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\SamplingService;
use Core\Container;

class SamplingController extends BaseController
{
    private SamplingService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(SamplingService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $filters = [
            'status' => $this->request->get('status', ''),
            'style_id' => $this->request->get('style_id', ''),
            'sample_type' => $this->request->get('sample_type', ''),
            'from_date' => $this->request->get('from_date', ''),
            'to_date' => $this->request->get('to_date', ''),
            'search' => $this->request->get('search', ''),
        ];

        $dropdowns = $this->service->getDropdownData();

        $this->view('merchandising/sampling/index', [
            'samples' => $this->service->getSamples($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'buyers' => $dropdowns['buyers'] ?? [],
            'styles' => $dropdowns['styles'],
            'sample_types' => $dropdowns['sample_types'],
            'statuses' => $dropdowns['statuses'],
            'user' => $this->auth->user() ?? [],
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $rules = [
            'style_id' => 'required|integer',
            'sample_type' => 'required',
            'request_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => 'required',
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $id = $this->service->createSample($data, (int) $this->auth->user()['user_id']);
            $this->json(['success' => true, 'sample_id' => $id, 'message' => 'Sample created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Failed to create sample.'], 500);
        }
    }

    public function show(int $sampleId): void
    {
        $this->requireAuth();
        $sample = $this->service->getSample($sampleId);
        if (!$sample) {
            $this->json(['error' => 'Sample not found'], 404);
            return;
        }

        $this->json(['data' => $sample]);
    }

    public function update(int $sampleId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $rules = [
            'style_id' => 'required|integer',
            'sample_type' => 'required',
            'request_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => 'required',
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->service->updateSample($sampleId, $data)) {
            $this->json(['success' => true, 'message' => 'Sample updated successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to update sample.'], 500);
    }

    public function updateStatus(int $sampleId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $rules = [
            'status' => 'required',
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->service->updateStatus($sampleId, $data['status'], $data['notes'] ?? null)) {
            $this->json(['success' => true, 'message' => 'Sample status updated.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to update sample status.'], 500);
    }

    public function destroy(int $sampleId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        if ($this->service->deleteSample($sampleId)) {
            $this->json(['success' => true, 'message' => 'Sample deleted.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to delete sample.'], 500);
    }
}
