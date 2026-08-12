<?php

namespace App\Models\Inventory;

use Core\Model;

class GatePass extends Model
{
    protected string $table = 'gate_passes';

    protected array $fillable = [
        'gate_pass_no',
        'challan_no',
        'challan_date',
        'prepared_for',
        'address',
        'bearer_by',
        'name_and_desig',
        'department_id',
        'basis',
        'remarks',
        'returnable',
        'date',
        'time',
        'exp_return_date',
        'verified_by',
        'approve_by',
        'prepared_by',
        'designation',
        'mobile_no',
        'email',
        'supervisor'
    ];

    /**
     * Get items associated with this gate pass
     */
    public function getItems(int $gatePassId): array
    {
        $sql = "
            SELECT 
                gpi.*,
                b.buyer_name as buyer_name,
                s.style_code as style_name,
                ic.category_name as category_name
            FROM gate_pass_items gpi
            LEFT JOIN buyers b ON gpi.buyer_id = b.buyer_id
            LEFT JOIN styles s ON gpi.style_id = s.style_id
            LEFT JOIN item_categories ic ON gpi.item_category_id = ic.category_id
            WHERE gpi.gate_pass_id = ?
        ";
        
        return $this->db->query($sql, [$gatePassId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a gate pass with full details joined
     */
    public function getWithDetails(int $id)
    {
        $sql = "
            SELECT 
                gp.*,
                d.department_name as department_name,
                v.username as verified_by_name,
                a.username as approve_by_name,
                p.username as prepared_by_name
            FROM gate_passes gp
            LEFT JOIN departments d ON gp.department_id = d.department_id
            LEFT JOIN users v ON gp.verified_by = v.user_id
            LEFT JOIN users a ON gp.approve_by = a.user_id
            LEFT JOIN users p ON gp.prepared_by = p.user_id
            WHERE gp.id = ?
        ";
        
        return $this->db->query($sql, [$id])->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate next gate pass number
     */
    public function generateGatePassNo(): string
    {
        $prefix = "BBCL-GPE-" . date('y') . "-";
        
        // Find the latest number for this year
        $sql = "SELECT gate_pass_no FROM gate_passes WHERE gate_pass_no LIKE ? ORDER BY id DESC LIMIT 1";
        $latest = $this->db->query($sql, [$prefix . '%'])->fetch(\PDO::FETCH_ASSOC);
        
        $nextNum = 1;
        if ($latest && isset($latest['gate_pass_no'])) {
            $parts = explode('-', $latest['gate_pass_no']);
            $lastNum = (int)end($parts);
            $nextNum = $lastNum + 1;
        }

        return $prefix . str_pad((string)$nextNum, 5, '0', STR_PAD_LEFT);
    }
}
