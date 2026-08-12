<?php

declare(strict_types=1);

namespace App\Controllers\Procurement;

use App\Controllers\BaseController;
use App\Services\Procurement\VendorService;
use Core\Container;

class VendorController extends BaseController
{
    private VendorService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(VendorService::class);
    }

    /**
     * Display the index page with list of vendors and stats
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $filters = [
            'status' => $this->request->get('status', ''),
            'search' => $this->request->get('search', ''),
        ];

        $this->view('procurement/vendors/index', [
            'vendors'   => $this->service->listVendors($filters),
            'stats'     => $this->service->getStats(),
            'filters'   => $filters,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }

    /**
     * Show the create form
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $this->view('procurement/vendors/create', [
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }

    /**
     * Store a newly created vendor
     */
    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $data = $this->request->all();

        $errors = $this->validate([
            'vendor_name' => 'required',
            'vendor_code' => 'required',
        ], $data);

        if (!empty($errors)) {
            $this->redirect('procurement/vendors/create');
            return;
        }

        try {
            $this->service->createVendor($data);
            $this->redirect('procurement/vendors');
        } catch (\Throwable $e) {
            $this->redirect('procurement/vendors/create');
        }
    }

    /**
     * Show the edit form pre-filled with vendor data
     */
    public function edit(int $vendorId): void
    {
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $vendor = $this->service->getVendor($vendorId);
        if (!$vendor) {
            $this->redirect('procurement/vendors');
            return;
        }

        $this->view('procurement/vendors/edit', [
            'vendor'    => $vendor,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }

    /**
     * Update an existing vendor
     */
    public function update(int $vendorId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $vendor = $this->service->getVendor($vendorId);
        if (!$vendor) {
            $this->redirect('procurement/vendors');
            return;
        }

        $data = $this->request->all();

        $errors = $this->validate([
            'vendor_name' => 'required',
            'vendor_code' => 'required',
        ], $data);

        if (!empty($errors)) {
            $this->redirect("procurement/vendors/{$vendorId}/edit");
            return;
        }

        try {
            $this->service->updateVendor($vendorId, $data);
            $this->redirect('procurement/vendors');
        } catch (\Throwable $e) {
            $this->redirect("procurement/vendors/{$vendorId}/edit");
        }
    }

    /**
     * Delete a vendor (only if no purchase orders exist)
     */
    public function destroy(int $vendorId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        try {
            $this->service->deleteVendor($vendorId);
        } catch (\Throwable $e) {
            // Vendor has POs — silently redirect back
        }

        $this->redirect('procurement/vendors');
    }

    /**
     * Import vendors from Excel file
     */
    public function import(): void
    {
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        if (!isset($_FILES['excel_file'])) {
            $this->json(['success' => false, 'message' => 'No file uploaded.'], 400);
            return;
        }

        $file = $_FILES['excel_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Upload error occurred.'], 400);
            return;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'])) {
            $this->json(['success' => false, 'message' => 'Please upload a valid Excel file (.xlsx or .xls).'], 400);
            return;
        }

        try {
            $result = $this->service->importVendors($file['tmp_name']);
            $this->json([
                'success' => true,
                'message' => "Import completed successfully! Imported: {$result['imported']}, Updated: {$result['updated']}"
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download vendor import Excel template
     */
    public function downloadTemplate(): void
    {
        $this->requireAuth();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vendors Template');

        $headers = [
            'Vendor Code (Required)',
            'Vendor Name (Required)',
            'Company Name',
            'Category',
            'Contact Person',
            'Email',
            'Phone',
            'Address',
            'City',
            'Province',
            'Postal Code',
            'Country',
            'Payment Terms',
            'Lead Time (Days)',
            'Tax ID',
            'Status (active/inactive)'
        ];

        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '1', $header);
        }

        $headerRange = 'A1:P1';
        $styleArray = [
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A6FDC'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ]
        ];
        $sheet->getStyle($headerRange)->applyFromArray($styleArray);

        $exampleRow = [
            'VND-001', 'Acme Fabrics Inc.', 'Acme Fabrics LLC', 'Fabric',
            'John Smith', 'john@acmefabrics.com', '+123456789',
            '123 Industrial Way, Sector 4', 'Dhaka', 'Dhaka Division', '1207',
            'Bangladesh', 'Net 30', '15', 'TX-9876543', 'active'
        ];

        foreach ($exampleRow as $colIndex => $value) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '2', $value);
        }

        foreach (range(1, 16) as $colIndex) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="vendors_import_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
