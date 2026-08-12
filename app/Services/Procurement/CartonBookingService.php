<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use Core\Database;

class CartonBookingService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        try {
            $total = (int) ($this->db->count('carton_bookings') ?? 0);
            $pending = (int) ($this->db->count('carton_bookings', ['status' => 'pending']) ?? 0);
            $approved = (int) ($this->db->count('carton_bookings', ['status' => 'approved']) ?? 0);
            $completed = (int) ($this->db->count('carton_bookings', ['status' => 'completed']) ?? 0);
        } catch (\PDOException $e) {
            return [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'completed' => 0,
            ];
        }

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'completed' => $completed,
        ];
    }

    public function createBooking(array $data): int
    {
        $length = (float)($data['length'] ?? 0);
        $width  = (float)($data['width'] ?? 0);
        $height = (float)($data['height'] ?? 0);
        $unit   = $data['unit'] ?? 'cm';
        $qty    = (int)($data['quantity'] ?? 1);

        // Calculate CBM volume based on unit (convert to cubic meters)
        $cbmPerCarton = 0.0000;
        if ($unit === 'cm') {
            $cbmPerCarton = ($length * $width * $height) / 1000000.0;
        } elseif ($unit === 'in') {
            $cbmPerCarton = ($length * $width * $height) / 61023.7; // 1 Cubic Meter = 61,023.7 cubic inches
        } elseif ($unit === 'mm') {
            $cbmPerCarton = ($length * $width * $height) / 1000000000.0;
        }
        $totalCbm = $cbmPerCarton * $qty;

        $orderId = isset($data['order_id']) && $data['order_id'] !== '' ? (int)$data['order_id'] : null;
        $styleId = isset($data['style_id']) && $data['style_id'] !== '' ? (int)$data['style_id'] : null;
        $buyerId = isset($data['buyer_id']) && $data['buyer_id'] !== '' ? (int)$data['buyer_id'] : null;

        return $this->db->insert('carton_bookings', [
            'booking_number' => 'CB-' . date('Ymd') . '-' . rand(100, 999),
            'booking_date'   => $data['booking_date'] ?? date('Y-m-d'),
            'order_id'       => $orderId,
            'style_id'       => $styleId,
            'buyer_id'       => $buyerId,
            'length'         => $length,
            'width'          => $width,
            'height'         => $height,
            'unit'           => $unit,
            'cbm_per_carton' => $cbmPerCarton,
            'total_cbm'      => $totalCbm,
            'quantity'       => $qty,
            'status'         => 'pending',
            'notes'          => $data['notes'] ?? '',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s')
        ]);
    }

    public function updateStatus(int $bookingId, string $status): bool
    {
        return $this->db->update('carton_bookings', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $bookingId]) > 0;
    }

    public function deleteBooking(int $bookingId): bool
    {
        return $this->db->delete('carton_bookings', ['id' => $bookingId]) > 0;
    }

    public function listBookings(array $filters = []): array
    {
        try {
            $sql = "
                SELECT cb.*, po.po_number, s.style_code, s.style_name, b.buyer_name 
                FROM carton_bookings cb
                LEFT JOIN production_orders po ON cb.order_id = po.po_id
                LEFT JOIN styles s ON cb.style_id = s.style_id
                LEFT JOIN buyers b ON cb.buyer_id = b.buyer_id
            ";

            $whereParts = [];
            $params = [];

            if (!empty($filters['status'])) {
                $whereParts[] = "cb.status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $whereParts[] = "cb.booking_number LIKE :search";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            if (!empty($whereParts)) {
                $sql .= " WHERE " . implode(" AND ", $whereParts);
            }

            $sql .= " ORDER BY cb.booking_date DESC, cb.id DESC";

            return $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getDropdownData(): array
    {
        return [
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ]
        ];
    }
}
