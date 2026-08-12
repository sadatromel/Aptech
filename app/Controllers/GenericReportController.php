<?php

declare(strict_types=1);

namespace App\Controllers;

class GenericReportController extends BaseController
{
    public function show(string $module): void
    {
        $this->requireAuth();
        
        $validModules = [
            'commercial',
            'merchandising',
            'procurement',
            'inventory',
            'transport',
            'master_library'
        ];

        if (!in_array($module, $validModules, true)) {
            $this->notFound();
            return;
        }

        $this->view("{$module}/reports", [
            'activeModule' => $module,
            'activeSubModule' => 'reports'
        ]);
    }
}
