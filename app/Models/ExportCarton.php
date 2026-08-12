<?php

namespace App\Models;

class ExportCarton
{
    public int $id;
    public int $document_id;
    public string $carton_number;
    public int $quantity;
    public string $description;
    public string $hs_code;
}
