<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;

class MasterLibraryController extends BaseController
{
    public function index(): void
    {
        $this->view('master_library/dashboard');
    }
}
