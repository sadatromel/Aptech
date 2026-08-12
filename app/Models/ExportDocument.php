<?php

namespace App\Models;

class ExportDocument
{
    public int $document_id;
    public string $document_number;
    public int $order_id;
    public string $document_type;
    public string $export_date;
    public int $buyer_id;
    public string $consignee_name;
    public string $consignee_address;
    public string $port_of_loading;
    public string $port_of_discharge;
    public string $vessel_name;
    public string $voyage_number;
    public string $invoice_number;
    public float $invoice_value;
    public string $currency;
    public string $payment_terms;
    public string $shipment_terms;
    public string $notes;
    public int $created_by;
    public string $created_at;

    // Relations
    public ?string $order_number = null;
    public ?string $style_code = null;
    public ?string $style_name = null;
    public ?string $buyer_name = null;
    public array $cartons = []; // Array of ExportCarton objects
}
