<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class AuditLogController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $page = (int) ($this->request->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $user = $this->request->get('user', '');
        $action = $this->request->get('action', '');
        $date_from = $this->request->get('date_from', '');
        $date_to = $this->request->get('date_to', '');

        // Build where conditions
        $where = [];
        if (!empty($user)) {
            $where['user_name[~]'] = $user;
        }
        if (!empty($action)) {
            $where['action'] = $action;
        }
        if (!empty($date_from)) {
            $where['created_at[>=]'] = $date_from . ' 00:00:00';
        }
        if (!empty($date_to)) {
            $where['created_at[<=]'] = $date_to . ' 23:59:59';
        }

        $logs = $this->db->select('audit_logs', '*', [
            'WHERE' => $where,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [$offset, $limit]
        ]);

        $total = $this->db->count('audit_logs', ['WHERE' => $where]);
        $pages = ceil($total / $limit);

        // Get unique actions for filter
        $actions = $this->db->select('audit_logs', 'action', [
            'GROUP' => 'action',
            'ORDER' => 'action'
        ]);

        $this->view('admin/audit_logs/index', [
            'logs' => $logs,
            'actions' => $actions,
            'pagination' => [
                'current' => $page,
                'total' => $pages,
                'limit' => $limit,
                'totalItems' => $total
            ],
            'filters' => [
                'user' => $user,
                'action' => $action,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ]);
    }
}
