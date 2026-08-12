<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\TermsCondition;
use Core\Container;

class TermsConditionController extends BaseController
{
    private TermsCondition $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(TermsCondition::class);
    }

    // ---------------------------------------------------------------
    // LIST
    // ---------------------------------------------------------------
    public function index(): void
    {
        $this->view('master_library/terms_conditions/index', [
            'terms'      => $this->model->all(),
            'termTypes'  => TermsCondition::termTypes(),
            'appliesTo'  => TermsCondition::appliesTo(),
        ]);
    }

    // ---------------------------------------------------------------
    // CREATE FORM
    // ---------------------------------------------------------------
    public function create(): void
    {
        $this->view('master_library/terms_conditions/create', [
            'termTypes' => TermsCondition::termTypes(),
            'appliesTo' => TermsCondition::appliesTo(),
        ]);
    }

    // ---------------------------------------------------------------
    // STORE
    // ---------------------------------------------------------------
    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $data['created_by'] = $_SESSION['user_id'] ?? null;

        // Sanitise optional date fields
        foreach (['effective_from', 'effective_to'] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
            }
        }

        $this->model->create($data);
        $this->redirect('master_library/terms_conditions');
    }

    // ---------------------------------------------------------------
    // EDIT FORM
    // ---------------------------------------------------------------
    public function edit(int $id): void
    {
        $term = $this->model->find($id);
        $this->view('master_library/terms_conditions/edit', [
            'term'      => $term,
            'termTypes' => TermsCondition::termTypes(),
            'appliesTo' => TermsCondition::appliesTo(),
        ]);
    }

    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------
    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();

        foreach (['effective_from', 'effective_to'] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
            }
        }

        $this->model->update($id, $data);
        $this->redirect('master_library/terms_conditions');
    }

    // ---------------------------------------------------------------
    // DESTROY
    // ---------------------------------------------------------------
    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->model->delete($id);
        $this->redirect('master_library/terms_conditions');
    }
}
