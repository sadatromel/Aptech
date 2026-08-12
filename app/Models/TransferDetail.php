<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use Core\Database;

class TransferDetail extends Model
{
    protected string $table = 'po_transfer_details';
    
    protected array $fillable = [
        'transfer_id',
        'line_item_id',
        'sku_id',
        'quantity_transferred'
    ];

    protected array $casts = [
        'detail_id' => 'int',
        'transfer_id' => 'int',
        'line_item_id' => 'int',
        'sku_id' => 'int',
        'quantity_transferred' => 'int'
    ];

    public function getTransfer(): ?Transfer
    {
        return Transfer::find($this->transfer_id);
    }

    public function getLineItem(): ?array
    {
        $sql = "SELECT li.*, po.po_number 
                FROM po_line_items li
                LEFT JOIN production_orders po ON li.po_id = po.po_id
                WHERE li.line_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->line_item_id]);
        return $stmt->fetch() ?: null;
    }

    public function getSKU(): ?array
    {
        $sql = "SELECT s.*, st.style_code, st.style_name 
                FROM style_skus s
                LEFT JOIN styles st ON s.style_id = st.style_id
                WHERE s.sku_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->sku_id]);
        return $stmt->fetch() ?: null;
    }

    public function save(): bool
    {
        if (isset($this->detail_id)) {
            // Update
            $sql = "UPDATE po_transfer_details SET 
                    transfer_id = ?, 
                    line_item_id = ?, 
                    sku_id = ?, 
                    quantity_transferred = ?
                    WHERE detail_id = ?";
            
            $params = [
                $this->transfer_id,
                $this->line_item_id,
                $this->sku_id,
                $this->quantity_transferred,
                $this->detail_id
            ];
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Insert
            $sql = "INSERT INTO po_transfer_details 
                    (transfer_id, line_item_id, sku_id, quantity_transferred) 
                    VALUES (?, ?, ?, ?)";
            
            $params = [
                $this->transfer_id,
                $this->line_item_id,
                $this->sku_id,
                $this->quantity_transferred
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            if ($result) {
                $this->detail_id = (int)$this->db->lastInsertId();
            }
            return $result;
        }
    }
}
