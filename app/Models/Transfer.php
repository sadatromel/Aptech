<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use Core\Database;
use DateTime;

class Transfer extends Model
{
    protected string $table = 'po_transfers';
    
    protected array $fillable = [
        'source_po_id',
        'target_po_id',
        'transferred_by',
        'transfer_date',
        'notes'
    ];

    protected array $casts = [
        'transfer_id' => 'int',
        'source_po_id' => 'int',
        'target_po_id' => 'int',
        'transferred_by' => 'int',
        'transfer_date' => 'datetime',
        'notes' => 'string'
    ];

    public function getSourcePO(): ?array
    {
        $sql = "SELECT po.*, st.style_code, st.style_name, b.buyer_name 
                FROM production_orders po
                LEFT JOIN styles st ON po.style_id = st.style_id
                LEFT JOIN buyer_profiles b ON st.buyer_id = b.buyer_id
                WHERE po.po_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->source_po_id]);
        return $stmt->fetch() ?: null;
    }

    public function getTargetPO(): ?array
    {
        $sql = "SELECT po.*, st.style_code, st.style_name, b.buyer_name 
                FROM production_orders po
                LEFT JOIN styles st ON po.style_id = st.style_id
                LEFT JOIN buyer_profiles b ON st.buyer_id = b.buyer_id
                WHERE po.po_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->target_po_id]);
        return $stmt->fetch() ?: null;
    }

    public function getTransferredByUser(): ?array
    {
        $sql = "SELECT user_id, first_name, last_name, email 
                FROM users 
                WHERE user_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->transferred_by]);
        return $stmt->fetch() ?: null;
    }

    public function getDetails(): array
    {
        $sql = "SELECT td.*, 
                       pli.color, pli.size, pli.inseam,
                       s.sku_code
                FROM po_transfer_details td
                LEFT JOIN po_line_items pli ON td.line_item_id = pli.line_id
                LEFT JOIN style_skus s ON pli.sku_id = s.sku_id
                WHERE td.transfer_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->transfer_id]);
        return $stmt->fetchAll();
    }

    public function getTotalQuantity(): int
    {
        $sql = "SELECT COALESCE(SUM(quantity_transferred), 0) as total
                FROM po_transfer_details 
                WHERE transfer_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->transfer_id]);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    public function save(): bool
    {
        if (isset($this->transfer_id)) {
            // Update
            $sql = "UPDATE po_transfers SET 
                    source_po_id = ?, 
                    target_po_id = ?, 
                    transferred_by = ?, 
                    transfer_date = ?, 
                    notes = ?
                    WHERE transfer_id = ?";
            
            $params = [
                $this->source_po_id,
                $this->target_po_id,
                $this->transferred_by,
                $this->transfer_date->format('Y-m-d H:i:s'),
                $this->notes,
                $this->transfer_id
            ];
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Insert
            $sql = "INSERT INTO po_transfers 
                    (source_po_id, target_po_id, transferred_by, transfer_date, notes) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $params = [
                $this->source_po_id,
                $this->target_po_id,
                $this->transferred_by,
                $this->transfer_date->format('Y-m-d H:i:s'),
                $this->notes
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            if ($result) {
                $this->transfer_id = (int)$this->db->lastInsertId();
            }
            return $result;
        }
    }
}
