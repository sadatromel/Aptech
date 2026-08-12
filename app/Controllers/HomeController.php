<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

class HomeController extends Controller
{
    public function index(): void
    {
        if (!$this->auth->check()) {
            $this->redirect('/login');
            return;
        }

        $user = $this->auth->user();
        $dashboardStats = $this->getDashboardStats();

        $this->view('dashboard/index', [
            'user' => $user,
            'stats' => $dashboardStats
        ]);
    }

    private function getDashboardStats(): array
    {
        return [
            'totalEmployees' => $this->db->count('employees'),
            'activeProjects' => $this->db->count('production_orders', ['status' => 'active']),
            'pendingInvoices' => $this->db->count('commercial_invoices', ['status' => 'pending']),
            'todayAttendance' => $this->db->count('attendance', [
                'date' => date('Y-m-d'),
                'status' => 'present'
            ])
        ];
    }
}
