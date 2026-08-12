<?php

namespace App\Services;

use Core\Database;
use PDO;

class ExportDocumentService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function listDocuments(string $limit = '20', string $search = ''): array
    {
        $query = "
            SELECT ed.*, COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number, 
                   s.style_code, s.style_name, b.buyer_name
            FROM export_documents ed
            LEFT JOIN production_orders po ON ed.order_id = po.po_id
            LEFT JOIN styles s ON po.style_id = s.style_id
            LEFT JOIN buyers b ON ed.buyer_id = b.buyer_id
        ";
        
        $params = [];
        if ($search) {
            $query .= " WHERE ed.document_number LIKE ? OR po.po_number LIKE ? OR b.buyer_name LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query .= " ORDER BY ed.export_date DESC, ed.document_id DESC LIMIT $limit";

        return $this->db->query($query, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocument(int $id): ?array
    {
        $document = $this->db->query("
            SELECT ed.*, COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number, 
                   s.style_code, s.style_name, b.buyer_name
            FROM export_documents ed
            LEFT JOIN production_orders po ON ed.order_id = po.po_id
            LEFT JOIN styles s ON po.style_id = s.style_id
            LEFT JOIN buyers b ON ed.buyer_id = b.buyer_id
            WHERE ed.document_id = ?
        ", [$id])->fetch(PDO::FETCH_ASSOC);

        if ($document) {
            $document['cartons'] = $this->db->query("SELECT * FROM export_cartons WHERE document_id = ?", [$id])->fetchAll(PDO::FETCH_ASSOC);
        }

        return $document ?: null;
    }

    public function createDocument(array $data, array $cartons, int $userId): int
    {
        try {
            $this->db->beginTransaction();

            $this->db->query(
                "INSERT INTO export_documents (document_number, order_id, document_type, export_date, buyer_id, consignee_name, consignee_address, port_of_loading, port_of_discharge, vessel_name, voyage_number, invoice_number, invoice_value, currency, payment_terms, shipment_terms, notes, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['document_number'],
                    $data['order_id'],
                    $data['document_type'],
                    $data['export_date'],
                    $data['buyer_id'],
                    $data['consignee_name'] ?? '',
                    $data['consignee_address'] ?? '',
                    $data['port_of_loading'] ?? '',
                    $data['port_of_discharge'] ?? '',
                    $data['vessel_name'] ?? '',
                    $data['voyage_number'] ?? '',
                    $data['invoice_number'] ?? '',
                    $data['invoice_value'] ?? 0,
                    $data['currency'] ?? 'USD',
                    $data['payment_terms'] ?? '',
                    $data['shipment_terms'] ?? '',
                    $data['notes'] ?? '',
                    $userId
                ]
            );

            $documentId = (int)$this->db->lastInsertId();

            foreach ($cartons as $carton) {
                if (!empty($carton['carton_number']) && isset($carton['quantity'])) {
                    $this->db->query(
                        "INSERT INTO export_cartons (document_id, carton_number, quantity, description, hs_code) VALUES (?, ?, ?, ?, ?)",
                        [$documentId, $carton['carton_number'], $carton['quantity'], $carton['description'], $carton['hs_code'] ?? '']
                    );
                }
            }

            $this->db->commit();
            return $documentId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteDocument(int $id): bool
    {
        return (bool)$this->db->query("DELETE FROM export_documents WHERE document_id = ?", [$id]);
    }

    public function getStats(): array
    {
        $currentMonth = date('Y-m');
        return [
            'total_documents' => $this->db->query("SELECT COUNT(*) FROM export_documents")->fetchColumn(),
            'total_value' => $this->db->query("SELECT SUM(invoice_value) FROM export_documents")->fetchColumn() ?: 0,
            'total_cartons' => $this->db->query("SELECT SUM(quantity) FROM export_cartons")->fetchColumn() ?: 0,
            'monthly_documents' => $this->db->query("SELECT COUNT(*) FROM export_documents WHERE DATE_FORMAT(export_date, '%Y-%m') = ?", [$currentMonth])->fetchColumn()
        ];
    }
}
