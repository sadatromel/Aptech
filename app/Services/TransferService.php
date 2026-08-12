<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transfer;
use App\Models\TransferDetail;
use Core\Database;
use PDO;
use Exception;

class TransferService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getProductionOrders(): array
    {
        $sql = "SELECT po.*, st.style_code, st.style_name, b.buyer_name
                FROM production_orders po
                LEFT JOIN styles st ON po.style_id = st.style_id
                LEFT JOIN buyers b ON st.buyer_id = b.buyer_id
                WHERE po.status != 'cancelled'
                ORDER BY po.order_date DESC";
        
        return $this->db->query($sql)->fetchAll();
    }

    public function getPOSKUs(int $poId): array
    {
        $sql = "SELECT li.*, s.sku_code, s.color, s.size, s.inseam
                FROM po_line_items li
                LEFT JOIN style_skus s ON li.sku_id = s.sku_id
                WHERE li.po_id = ? AND li.status != 'deleted'
                ORDER BY s.color, s.inseam, s.size";
        
        return $this->db->query($sql, [$poId])->fetchAll();
    }

    public function executeTransfer(array $data): array
    {
        $sourcePOId = (int)$data['source_po_id'];
        $targetPOId = (int)$data['target_po_id'];
        $notes = $data['notes'] ?? '';
        $transferItems = $data['transfer_items'] ?? [];
        $userId = $_SESSION['user_id'] ?? 1;

        if ($sourcePOId === $targetPOId) {
            throw new Exception('Source and Target PO cannot be the same');
        }

        if (empty($transferItems)) {
            throw new Exception('No items to transfer');
        }

        $this->db->beginTransaction();

        try {
            // Create transfer record
            $insertTransferSql = "INSERT INTO po_transfers 
                                  (source_po_id, target_po_id, transferred_by, transfer_date, notes) 
                                  VALUES (?, ?, ?, NOW(), ?)";
            $this->db->query($insertTransferSql, [
                $sourcePOId,
                $targetPOId,
                $userId,
                $notes
            ]);
            $transferId = (int)$this->db->lastInsertId();

            // Process each transfer item
            foreach ($transferItems as $lineId => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity <= 0) continue;

                // Get source line item with lock
                $lineSql = "SELECT * FROM po_line_items WHERE line_id = ? FOR UPDATE";
                $lineItem = $this->db->query($lineSql, [$lineId])->fetch();

                if (!$lineItem || $lineItem['quantity_ordered'] < $quantity) {
                    throw new Exception("Insufficient quantity for line item {$lineId}");
                }

                // Reduce source quantity
                $updateSourceSql = "UPDATE po_line_items SET quantity_ordered = quantity_ordered - ? WHERE line_id = ?";
                $this->db->query($updateSourceSql, [$quantity, $lineId]);

                // Check if target line item exists
                $targetLineSql = "SELECT line_id FROM po_line_items 
                                  WHERE po_id = ? AND color = ? AND size = ? AND inseam = ?";
                $targetLine = $this->db->query($targetLineSql, [
                    $targetPOId,
                    $lineItem['color'],
                    $lineItem['size'],
                    $lineItem['inseam']
                ])->fetch();

                if ($targetLine) {
                    // Update existing target
                    $updateTargetSql = "UPDATE po_line_items SET quantity_ordered = quantity_ordered + ? WHERE line_id = ?";
                    $this->db->query($updateTargetSql, [$quantity, $targetLine['line_id']]);
                    $targetLineId = $targetLine['line_id'];
                } else {
                    // Create new target line item
                    $insertTargetSql = "INSERT INTO po_line_items (po_id, color, size, inseam, quantity_ordered, unit_price, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $this->db->query($insertTargetSql, [
                        $targetPOId,
                        $lineItem['color'],
                        $lineItem['size'],
                        $lineItem['inseam'],
                        $quantity,
                        $lineItem['unit_price'] ?? 0
                    ]);
                    $targetLineId = (int)$this->db->lastInsertId();
                }

                // Record transfer detail
                $insertDetailSql = "INSERT INTO po_transfer_details 
                                    (transfer_id, line_item_id, sku_id, quantity_transferred) 
                                    VALUES (?, ?, ?, ?)";
                $this->db->query($insertDetailSql, [
                    $transferId,
                    $lineId,
                    $lineItem['sku_id'],
                    $quantity
                ]);
            }

            // Update PO totals
            $this->updatePOQuantities($sourcePOId);
            $this->updatePOQuantities($targetPOId);

            $this->db->commit();

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'message' => 'Transfer completed successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getTransferHistory(int $limit = 50): array
    {
        $sql = "SELECT t.*, 
                       spo.po_number as source_po_number,
                       tpo.po_number as target_po_number,
                       CONCAT(u.first_name, ' ', u.last_name) as transferred_by_name
                FROM po_transfers t
                LEFT JOIN production_orders spo ON t.source_po_id = spo.po_id
                LEFT JOIN production_orders tpo ON t.target_po_id = tpo.po_id
                LEFT JOIN users u ON t.transferred_by = u.user_id
                ORDER BY t.transfer_date DESC
                LIMIT " . (int)$limit;
        
        return $this->db->query($sql)->fetchAll();
    }

    public function getTransferDetails(int $transferId): array
    {
        $sql = "SELECT * FROM po_transfers WHERE transfer_id = ?";
        $stmt = $this->db->query($sql, [$transferId]);
        $transferData = $stmt->fetch();
        
        if (!$transferData) {
            throw new Exception('Transfer not found');
        }

        // Get source and target PO details
        $sourcePOSql = "SELECT po.*, st.style_code, st.style_name, b.buyer_name 
                        FROM production_orders po
                        LEFT JOIN styles st ON po.style_id = st.style_id
                        LEFT JOIN buyers b ON st.buyer_id = b.buyer_id
                        WHERE po.po_id = ?";
        $sourcePO = $this->db->query($sourcePOSql, [$transferData['source_po_id']])->fetch();
        
        $targetPO = $this->db->query($sourcePOSql, [$transferData['target_po_id']])->fetch();
        
        // Get transfer details
        $detailsSql = "SELECT td.*, 
                              pli.color, pli.size, pli.inseam,
                              s.sku_code
                       FROM po_transfer_details td
                       LEFT JOIN po_line_items pli ON td.line_item_id = pli.line_id
                       LEFT JOIN style_skus s ON pli.sku_id = s.sku_id
                       WHERE td.transfer_id = ?";
        $details = $this->db->query($detailsSql, [$transferId])->fetchAll();
        
        // Calculate total quantity
        $totalSql = "SELECT COALESCE(SUM(quantity_transferred), 0) as total
                     FROM po_transfer_details 
                     WHERE transfer_id = ?";
        $totalResult = $this->db->query($totalSql, [$transferId])->fetch();
        $totalQuantity = (int)($totalResult['total'] ?? 0);

        return [
            'transfer' => $transferData,
            'source_po' => $sourcePO,
            'target_po' => $targetPO,
            'details' => $details,
            'total_quantity' => $totalQuantity
        ];
    }

    private function updatePOQuantities(int $poId): void
    {
        $sql = "UPDATE production_orders po 
                SET quantity_ordered = (
                    SELECT COALESCE(SUM(quantity_ordered), 0) 
                    FROM po_line_items 
                    WHERE po_id = po.po_id AND status != 'deleted'
                ) 
                WHERE po_id = ?";
        
        $this->db->query($sql, [$poId]);
    }

    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_transfers,
                    COALESCE(SUM(td.quantity_transferred), 0) as total_quantity_transferred,
                    COUNT(DISTINCT t.source_po_id) as unique_source_pos,
                    COUNT(DISTINCT t.target_po_id) as unique_target_pos,
                    DATE(t.transfer_date) as transfer_date
                FROM po_transfers t
                LEFT JOIN po_transfer_details td ON t.transfer_id = td.transfer_id
                WHERE t.transfer_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                GROUP BY DATE(t.transfer_date)
                ORDER BY transfer_date DESC
                LIMIT 30";
        
        return $this->db->query($sql)->fetchAll();
    }
}
