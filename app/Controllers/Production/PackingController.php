<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\PackingPlan;
use App\Models\Carton;
use Exception;

class PackingController extends BaseController
{
    // PLANS
    public function plans(): void
    {
        $this->requireAuth();
        
        $plans = $this->db->select('packing_plans pp', [
            '[>]styles s' => ['pp.style_id' => 'style_id']
        ], [
            'pp.*',
            's.style_code'
        ], ['ORDER' => 'plan_id']);
        
        $this->view('production/packing/plans', ['plans' => $plans]);
    }
    
    public function createPlan(): void
    {
         $this->requireAuth();
         $styles = $this->db->select('styles', '*', ['status' => 'active']);
         // Orders logic omitted for brevity
         $this->view('production/packing/create_plan', ['styles' => $styles]);
    }
    
    public function storePlan(): void
    {
        $this->verifyCsrf();
         $this->requireAuth();
         $data = $this->request->all();
         
         try {
             $planModel = new PackingPlan();
             $planModel->create([
                 'plan_code' => $data['plan_code'],
                 'style_id' => $data['style_id'],
                 'target_date' => $data['target_date'],
                 'total_cartons' => $data['total_cartons'],
                 'status' => 'planned',
                 'created_by' => $this->auth->user()['id'],
                 'created_at' => date('Y-m-d H:i:s')
             ]);
             $this->redirect('/packing/plans');
         } catch (Exception $e) {
             $this->redirect('/packing/plans/create');
         }
    }

    // CARTONS
    public function cartons(): void
    {
        $this->requireAuth();
        $cartons = $this->db->select('cartons c', [
            '[>]styles s' => ['c.style_id' => 'style_id']
        ], [
            'c.*',
            's.style_code'
        ], ['ORDER' => 'carton_number']);
        
        $this->view('production/packing/cartons', ['cartons' => $cartons]);
    }
}
