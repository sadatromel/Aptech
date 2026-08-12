<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireRole('admin');

        $stats = [
            'totalUsers' => $this->db->count('users'),
            'activeSessions' => $this->db->count('user_sessions', ['active' => 1]),
            'systemLogs' => $this->db->count('audit_logs', ['created_at' => date('Y-m-d')]),
            'failedLogins' => $this->db->count('login_attempts', ['success' => 0, 'created_at' => date('Y-m-d')])
        ];

        $recentLogs = $this->db->select('audit_logs', '*', [
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => 10
        ]);

        $this->view('admin/dashboard', [
            'stats' => $stats,
            'recentLogs' => $recentLogs
        ]);
    }
}
