<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\WashBatch;
use Exception;

class WashingController extends BaseController
{
    public function batches(): void
    {
        $this->requireAuth();
        
        $batches = $this->db->select('wash_batches wb', [
            '[>]styles s' => ['wb.style_id' => 'style_id']
        ], [
            'wb.*',
            's.style_code'
        ], [
            'ORDER' => ['wb.batch_id' => 'DESC']
        ]);
        
        $this->view('production/washing/batches', ['batches' => $batches]);
    }
    
    public function createBatch(): void
    {
        $this->requireAuth();
         $styles = $this->db->select('styles', '*', ['status' => 'active']);
         $this->view('production/washing/create_batch', ['styles' => $styles]);
    }

    public function storeBatch(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        try {
            $batchModel = new WashBatch();
            $batchModel->create([
                'batch_no' => $data['batch_no'],
                'style_id' => $data['style_id'],
                'quantity' => $data['quantity'],
                'machine_no' => $data['machine_no'],
                'start_time' => $data['start_time'],
                'status' => 'scheduled',
                'created_by' => $this->auth->user()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->redirect('/washing/batches');
        } catch (Exception $e) {
            $this->redirect('/washing/batches/create');
        }
    }
}
