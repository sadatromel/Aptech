<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;

class CuttingController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        
        $plans = [];
        try {
            $plans = $this->db->select('cutting_plans cp', [
                '[>]styles s' => ['cp.style_id' => 'style_id']
            ], [
                'cp.plan_id',
                'cp.plan_number',
                'cp.style_id',
                'cp.total_quantity',
                'cp.planned_date',
                'cp.status',
                'style_code' => 's.style_code',
                'style_name' => 's.style_name'
            ], [
                'ORDER' => ['cp.planned_date' => 'DESC']
            ]);
        } catch (\Throwable $e) {
            $plans = [];
        }
        
        $this->view('production/cutting/index', ['plans' => $plans]);
    }
    
    public function create(): void
    {
        $this->requireAuth();
        $styles = $this->db->select('styles', '*', ['status' => 'active']);
        $this->view('production/cutting/create', ['styles' => $styles]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        try {
            $this->db->insert('cutting_plans', [
                'plan_number' => $data['plan_number'] ?? ('CP-' . date('Ymd') . '-' . rand(100, 999)),
                'style_id' => (int)($data['style_id'] ?? 0),
                'planned_date' => $data['planned_date'] ?? date('Y-m-d'),
                'total_quantity' => (int)($data['total_quantity'] ?? 0),
                'status' => 'planned',
                'created_by' => $_SESSION['user_id'] ?? 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->redirect('/production/cutting');
        } catch (\Exception $e) {
            $this->redirect('/production/cutting/create');
        }
    }
    
    public function bundles(int $planId): void
    {
        $this->requireAuth();
        
        $plan = $this->db->get('cutting_plans', '*', ['plan_id' => $planId]);
        $bundles = [];
        try {
            $bundles = $this->db->select('bundles', '*', ['plan_id' => $planId]);
        } catch (\Throwable $e) {
            $bundles = [];
        }
        
        $this->view('production/cutting/bundles', [
            'plan' => $plan,
            'bundles' => $bundles
        ]);
    }
    
    public function generateBundles(int $planId): void
    {
        $this->requireAuth();
        
        $plan = $this->db->get('cutting_plans', '*', ['plan_id' => $planId]);
        if (!$plan) {
            $this->notFound();
            return;
        }

        $totalQty = (int)($plan['total_quantity'] ?? 100);
        $bundleSize = 25;
        $numberOfBundles = (int)ceil($totalQty / $bundleSize);

        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        $existing = 0;
        try {
            $existing = (int)$this->db->count('bundles', ['plan_id' => $planId]);
        } catch (\Throwable $e) {
            $existing = 0;
        }

        if ($existing == 0) {
            for ($i = 1; $i <= $numberOfBundles; $i++) {
                $size = $sizes[($i - 1) % count($sizes)];
                $qty = ($i === $numberOfBundles) ? ($totalQty - (($i - 1) * $bundleSize)) : $bundleSize;
                $startSerial = (($i - 1) * $bundleSize) + 1;
                $endSerial = $startSerial + $qty - 1;
                $bundleNo = 'BDL-' . $planId . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
                $barcode = 'BC-' . date('Ymd') . '-' . $planId . '-' . $i;

                $this->db->insert('bundles', [
                    'plan_id'       => $planId,
                    'bundle_number' => $bundleNo,
                    'size'          => $size,
                    'color'         => 'Assorted',
                    'quantity'      => $qty,
                    'start_serial'  => $startSerial,
                    'end_serial'    => $endSerial,
                    'barcode'       => $barcode,
                    'status'        => 'generated',
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->redirect("/production/cutting/bundles/$planId");
    }
}
