<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use Core\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VendorService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('vendors') ?? 0);
        $active = (int) ($this->db->count('vendors', ['status' => 'active']) ?? 0);
        $inactive = (int) ($this->db->count('vendors', ['status' => 'inactive']) ?? 0);
        $blacklisted = (int) ($this->db->count('vendors', ['status' => 'blacklisted']) ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'blacklisted' => $blacklisted,
        ];
    }

    public function listVendors(array $filters = []): array
    {
        $where = ['ORDER' => ['vendor_name' => 'ASC']];

        if (!empty($filters['status'])) {
            $where['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'vendor_name[~]' => $term,
                'vendor_code[~]' => $term,
                'contact_person[~]' => $term,
            ];
        }

        $vendors = $this->db->select('vendors', '*', $where) ?? [];

        return array_map(function ($vendor) {
            $vendor['total_pos'] = $this->db->count('purchase_orders', ['vendor_id' => $vendor['vendor_id']]) ?? 0;
            
            $poValueSql = "SELECT SUM(pl.line_total) as total 
                           FROM po_lines pl 
                           JOIN purchase_orders po ON pl.po_id = po.po_id 
                           WHERE po.vendor_id = ?";
            $stmt = $this->db->query($poValueSql, [$vendor['vendor_id']]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $vendor['total_po_value'] = (float) ($result['total'] ?? 0);
            
            $vendor['category'] = $vendor['vendor_type'] ?? '';
            return $vendor;
        }, $vendors);
    }

    public function getVendor(int $vendorId): ?array
    {
        $vendor = $this->db->get('vendors', '*', ['vendor_id' => $vendorId]);
        if ($vendor) {
            $vendor['category'] = $vendor['vendor_type'] ?? '';
        }
        return $vendor;
    }

    public function createVendor(array $data): int
    {
        $payload = [
            'vendor_name' => $data['vendor_name'],
            'vendor_code' => $data['vendor_code'],
            'vendor_type' => $data['category'] ?? '',
            'company_name' => $data['company_name'] ?? $data['vendor_name'],
            'contact_person' => $data['contact_person'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'province' => $data['province'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'country' => $data['country'] ?? '',
            'payment_terms' => $data['payment_terms'] ?? '',
            'lead_time_days' => (int) ($data['lead_time_days'] ?? 0),
            'tax_id' => $data['tax_id'] ?? '',
            'status' => $data['status'] ?? 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('vendors', $payload);
    }

    public function updateVendor(int $vendorId, array $data): bool
    {
        $payload = [
            'vendor_name' => $data['vendor_name'],
            'vendor_code' => $data['vendor_code'],
            'vendor_type' => $data['category'] ?? '',
            'company_name' => $data['company_name'] ?? $data['vendor_name'],
            'contact_person' => $data['contact_person'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'province' => $data['province'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'country' => $data['country'] ?? '',
            'payment_terms' => $data['payment_terms'] ?? '',
            'lead_time_days' => (int) ($data['lead_time_days'] ?? 0),
            'tax_id' => $data['tax_id'] ?? '',
            'status' => $data['status'] ?? 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('vendors', $payload, ['vendor_id' => $vendorId]);
    }

    public function deleteVendor(int $vendorId): bool
    {
        $poCount = $this->db->count('purchase_orders', ['vendor_id' => $vendorId]) ?? 0;
        if ($poCount > 0) {
            throw new \RuntimeException('Cannot delete vendor with existing purchase orders.');
        }

        return (bool) $this->db->delete('vendors', ['vendor_id' => $vendorId]);
    }

    public function getDropdownData(): array
    {
        $statuses = [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'blacklisted', 'label' => 'Blacklisted'],
        ];

        return ['statuses' => $statuses];
    }

    public function importVendors(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) <= 1) {
            throw new \RuntimeException('The Excel file is empty or missing data rows.');
        }

        $header = array_shift($rows); // Remove header row

        $imported = 0;
        $updated = 0;

        foreach ($rows as $index => $row) {
            // Check if row is empty
            if (empty($row) || (empty($row[0]) && empty($row[1]))) {
                continue;
            }

            $vendorCode = trim((string) ($row[0] ?? ''));
            $vendorName = trim((string) ($row[1] ?? ''));

            if (empty($vendorCode) || empty($vendorName)) {
                throw new \RuntimeException("Row " . ($index + 2) . ": Vendor Code and Vendor Name are required.");
            }

            $status = strtolower(trim((string) ($row[15] ?? 'active')));
            if (!in_array($status, ['active', 'inactive'])) {
                $status = 'active';
            }

            $vendorData = [
                'vendor_name' => $vendorName,
                'vendor_code' => $vendorCode,
                'vendor_type' => trim((string) ($row[3] ?? '')),
                'company_name' => trim((string) ($row[2] ?? '')) ?: $vendorName,
                'contact_person' => trim((string) ($row[4] ?? '')),
                'email' => trim((string) ($row[5] ?? '')),
                'phone' => trim((string) ($row[6] ?? '')),
                'address' => trim((string) ($row[7] ?? '')),
                'city' => trim((string) ($row[8] ?? '')),
                'province' => trim((string) ($row[9] ?? '')),
                'postal_code' => trim((string) ($row[10] ?? '')),
                'country' => trim((string) ($row[11] ?? '')),
                'payment_terms' => trim((string) ($row[12] ?? '')),
                'lead_time_days' => (int) ($row[13] ?? 0),
                'tax_id' => trim((string) ($row[14] ?? '')),
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Check if vendor already exists
            $existing = $this->db->get('vendors', '*', ['vendor_code' => $vendorCode]);

            if ($existing) {
                // Update
                $this->db->update('vendors', $vendorData, ['vendor_id' => $existing['vendor_id']]);
                $updated++;
            } else {
                // Create
                $vendorData['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('vendors', $vendorData);
                $imported++;
            }
        }

        return ['imported' => $imported, 'updated' => $updated];
    }
}
