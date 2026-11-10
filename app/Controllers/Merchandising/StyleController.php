<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Models\Style;
use Core\Container;
use Exception;

class StyleController extends BaseController
{
    private Style $styleModel;

    public function __construct()
    {
        parent::__construct();
        $this->styleModel = Container::get(Style::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        
        $filters = [
            'search' => $_GET['search'] ?? '',
            'buyer_id' => $_GET['buyer_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'season' => $_GET['season'] ?? ''
        ];

        $this->view('merchandising/styles/index', [
            'styles' => $this->styleModel->getAllWithBuyers($filters),
            'stats' => $this->styleModel->getStats(),
            'dropdowns' => $this->styleModel->getDropdownOptions(),
            'filters' => $filters
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        
        $this->view('merchandising/styles/create', [
            'dropdowns' => $this->styleModel->getDropdownOptions()
        ]); 
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        try {
            if (empty($data['style_code']) || empty($data['style_name']) || empty($data['buyer_id'])) {
                 throw new Exception('Required fields missing');
            }

            // Handle photo upload
            if (isset($_FILES['style_photo']) && $_FILES['style_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../public/uploads/styles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = uniqid('style_', true) . '.' . pathinfo($_FILES['style_photo']['name'], PATHINFO_EXTENSION);
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['style_photo']['tmp_name'], $destination)) {
                    $data['photo_path'] = 'uploads/styles/' . $filename;
                }
            }

            $data['created_by'] = $this->auth->user()['user_id'] ?? 0;
            $this->styleModel->create($data);
            
            $_SESSION['success_message'] = "Style created successfully";
            $this->redirect('merchandising/styles');

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect('merchandising/styles/create');
        }
    }
    
    public function edit(int $id): void
    {
        $this->requireAuth();
        
        $style = $this->styleModel->find($id);
        if (!$style) {
            $_SESSION['error_message'] = "Style not found";
            $this->redirect('merchandising/styles');
            return;
        }

        $this->view('merchandising/styles/edit', [
            'style' => $style,
            'dropdowns' => $this->styleModel->getDropdownOptions()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        try {
            // Handle photo upload
            if (isset($_FILES['style_photo']) && $_FILES['style_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../public/uploads/styles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = uniqid('style_', true) . '.' . pathinfo($_FILES['style_photo']['name'], PATHINFO_EXTENSION);
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['style_photo']['tmp_name'], $destination)) {
                    // Delete old photo if it exists
                    $oldStyle = $this->styleModel->find($id);
                    if ($oldStyle && !empty($oldStyle['photo_path'])) {
                        $oldPhotoPath = __DIR__ . '/../../../public/' . $oldStyle['photo_path'];
                        if (file_exists($oldPhotoPath) && is_file($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    $data['photo_path'] = 'uploads/styles/' . $filename;
                }
            }

            $data['updated_by'] = $this->auth->user()['user_id'] ?? 0;
            $this->styleModel->update($id, $data);
            
            $_SESSION['success_message'] = "Style updated successfully";
            $this->redirect('merchandising/styles');

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect("merchandising/styles/edit/$id");
        }
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        $style = $this->styleModel->find($id);
        if ($style) {
            if (!empty($style['photo_path'])) {
                $photoPath = __DIR__ . '/../../../public/' . $style['photo_path'];
                if (file_exists($photoPath) && is_file($photoPath)) {
                    unlink($photoPath);
                }
            }
            $this->styleModel->delete($id);
        }
        
        $_SESSION['success_message'] = "Style deleted successfully";
        $this->redirect('merchandising/styles');
    }
    
    public function show(int $id): void
    {
        $this->requireAuth();
        
        $style = $this->styleModel->findWithBuyer($id) ?? $this->styleModel->find($id);
        if (!$style) {
            $isJson = $this->request->isAjax()
                || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'))
                || (isset($_GET['format']) && $_GET['format'] === 'json');

            if ($isJson) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Style not found']);
                return;
            }

            $_SESSION['error_message'] = "Style not found";
            $this->redirect('merchandising/styles');
            return;
        }

        $breakdowns = $this->styleModel->breakdowns($id) ?? [];
        $style['size_ranges'] = $breakdowns;

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $isHtmlRequest = str_contains($accept, 'text/html');
        $isJson = (isset($_GET['format']) && $_GET['format'] === 'json')
            || ($this->request->isAjax() && !$isHtmlRequest)
            || (str_contains($accept, 'application/json') && !$isHtmlRequest);

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['data' => $style]);
            return;
        }

        $this->view('merchandising/styles/show', [
            'style' => $style,
            'breakdowns' => $breakdowns
        ]);
    }

    /**
     * Download Excel import template for styles
     */
    public function downloadTemplate(): void
    {
        $this->requireAuth();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Styles Import Template');

        $headers = [
            'Style Code (Required)',
            'Style Name (Required)',
            'Article Number',
            'Buyer (Name or ID - Required)',
            'Season',
            'Category',
            'Garment Type',
            'Target Price ($)',
            'Status (draft/design/active/completed)',
            'Style Description',
            'Internal Notes'
        ];

        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '1', $header);
        }

        $headerRange = 'A1:K1';
        $styleArray = [
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3C72'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ];
        $sheet->getStyle($headerRange)->applyFromArray($styleArray);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Add example rows
        $exampleRows = [
            [
                'ST-2025-001', 'Slim Fit Stretch Denim Jeans', 'ART-9901', 'Levi\'s',
                'Autumn/Winter 25', 'Menswear', 'Denim Pants', '24.50',
                'active', 'Classic 5-pocket stretch denim with enzyme wash', 'Sample approved by buyer'
            ],
            [
                'ST-2025-002', 'Cotton Oversized Graphic Hoodie', 'ART-9902', 'Target',
                'Spring/Summer 25', 'Womenswear', 'Hoodie', '18.00',
                'design', '350 GSM heavy fleece with custom front print', 'Tech pack in progress'
            ],
            [
                'ST-2025-003', 'Casual Polo Shirt Pique Cotton', 'ART-9903', 'Zara',
                'Summer 25', 'Menswear', 'Polo Shirt', '12.80',
                'draft', '100% combed cotton pique polo with ribs', 'Awaiting fabric lab dip'
            ]
        ];

        foreach ($exampleRows as $rowIndex => $row) {
            $rowNum = $rowIndex + 2;
            foreach ($row as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($colLetter . $rowNum, $value);
            }
        }

        foreach (range(1, 11) as $colIndex) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="styles_import_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * Bulk Upload Styles from CSV / Excel file
     */
    public function bulkUpload(): void
    {
        $this->requireAuth();

        header('Content-Type: application/json');

        $fileKey = isset($_FILES['file']) ? 'file' : (isset($_FILES['bulk_file']) ? 'bulk_file' : (isset($_FILES['excel_file']) ? 'excel_file' : null));

        if (!$fileKey || !isset($_FILES[$fileKey])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
            return;
        }

        $file = $_FILES[$fileKey];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File upload failed with error code ' . $file['error']]);
            return;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Please upload an Excel (.xlsx, .xls) or CSV (.csv) file.']);
            return;
        }

        $updateExisting = !empty($_POST['update_existing']) && ($_POST['update_existing'] === '1' || $_POST['update_existing'] === 'true');

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (count($rows) <= 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'The uploaded file is empty or missing data rows.']);
                return;
            }

            // Extract and normalize header row
            $headerRow = array_shift($rows);
            $columnIndexes = $this->mapStyleHeaders($headerRow);

            if (!isset($columnIndexes['style_code']) || !isset($columnIndexes['style_name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required columns in file header. Must contain "Style Code" and "Style Name".']);
                return;
            }

            // Pre-fetch buyers for quick matching by name or ID
            $buyers = $this->db()->select('buyers', ['buyer_id', 'buyer_name', 'buyer_code']);
            $buyerMap = [];
            foreach ($buyers as $b) {
                $buyerMap[(string)$b['buyer_id']] = (int)$b['buyer_id'];
                $cleanName = strtolower(trim((string)$b['buyer_name']));
                if ($cleanName !== '') {
                    $buyerMap[$cleanName] = (int)$b['buyer_id'];
                }
                if (!empty($b['buyer_code'])) {
                    $cleanCode = strtolower(trim((string)$b['buyer_code']));
                    $buyerMap[$cleanCode] = (int)$b['buyer_id'];
                }
            }

            // Default buyer fallback if only one active buyer exists
            $defaultBuyerId = count($buyers) === 1 ? (int)$buyers[0]['buyer_id'] : null;

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            $currentUserId = $this->auth->user()['user_id'] ?? 0;

            foreach ($rows as $index => $row) {
                $lineNum = $index + 2;

                // Skip empty row
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $styleCode = trim((string)($row[$columnIndexes['style_code']] ?? ''));
                $styleName = trim((string)($row[$columnIndexes['style_name']] ?? ''));

                if ($styleCode === '') {
                    $errors[] = "Row {$lineNum}: Style Code is required.";
                    continue;
                }

                if ($styleName === '') {
                    $errors[] = "Row {$lineNum}: Style Name is required for Style Code '{$styleCode}'.";
                    continue;
                }

                // Resolve Buyer
                $buyerVal = isset($columnIndexes['buyer']) ? trim((string)($row[$columnIndexes['buyer']] ?? '')) : '';
                $buyerId = null;

                if ($buyerVal !== '') {
                    $buyerKey = strtolower($buyerVal);
                    if (isset($buyerMap[$buyerKey])) {
                        $buyerId = $buyerMap[$buyerKey];
                    }
                }

                if (!$buyerId && $defaultBuyerId) {
                    $buyerId = $defaultBuyerId;
                }

                if (!$buyerId) {
                    $errors[] = "Row {$lineNum} ({$styleCode}): Buyer '{$buyerVal}' not found. Please provide a valid buyer name or ID.";
                    continue;
                }

                // Read optional fields
                $articleNumber = isset($columnIndexes['article_number']) ? trim((string)($row[$columnIndexes['article_number']] ?? '')) : '';
                $season = isset($columnIndexes['season']) ? trim((string)($row[$columnIndexes['season']] ?? '')) : '';
                $category = isset($columnIndexes['category']) ? trim((string)($row[$columnIndexes['category']] ?? '')) : '';
                $garmentType = isset($columnIndexes['garment_type']) ? trim((string)($row[$columnIndexes['garment_type']] ?? '')) : '';
                $priceRaw = isset($columnIndexes['price']) ? trim((string)($row[$columnIndexes['price']] ?? '0')) : '0';
                $price = is_numeric(preg_replace('/[^0-9.]/', '', $priceRaw)) ? (float)preg_replace('/[^0-9.]/', '', $priceRaw) : 0.0;
                
                $statusRaw = isset($columnIndexes['status']) ? strtolower(trim((string)($row[$columnIndexes['status']] ?? 'design'))) : 'design';
                $validStatuses = ['draft', 'design', 'active', 'approved_for_production', 'completed', 'cancelled'];
                $status = in_array($statusRaw, $validStatuses, true) ? $statusRaw : 'design';

                $styleDesc = isset($columnIndexes['style_description']) ? trim((string)($row[$columnIndexes['style_description']] ?? '')) : '';
                $internalDesc = isset($columnIndexes['description']) ? trim((string)($row[$columnIndexes['description']] ?? '')) : '';

                // Check existing style by code
                $existing = $this->db()->get('styles', '*', ['style_code' => $styleCode]);

                $data = [
                    'style_code' => $styleCode,
                    'style_name' => $styleName,
                    'article_number' => $articleNumber,
                    'buyer_id' => $buyerId,
                    'season' => $season,
                    'category' => $category,
                    'garment_type' => $garmentType,
                    'price' => $price,
                    'status' => $status,
                    'style_description' => $styleDesc,
                    'description' => $internalDesc,
                ];

                if ($existing) {
                    if ($updateExisting) {
                        $data['updated_by'] = $currentUserId;
                        $data['updated_at'] = date('Y-m-d H:i:s');
                        $this->db()->update('styles', $data, ['style_id' => $existing['style_id']]);
                        $updated++;
                    } else {
                        $skipped++;
                        $errors[] = "Row {$lineNum}: Style Code '{$styleCode}' already exists (skipped).";
                    }
                } else {
                    $data['created_by'] = $currentUserId;
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $this->db()->insert('styles', $data);
                    $imported++;
                }
            }

            $msg = "Bulk import completed. Created: {$imported}, Updated: {$updated}";
            if ($skipped > 0) $msg .= ", Skipped: {$skipped}";

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Processing error: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk Store Styles from Manual Batch Grid (JSON)
     */
    public function bulkStore(): void
    {
        $this->requireAuth();

        header('Content-Type: application/json');
        
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput, true);

        $styles = $json['styles'] ?? ($_POST['styles'] ?? null);

        if (is_string($styles)) {
            $styles = json_decode($styles, true);
        }

        if (!is_array($styles) || empty($styles)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No style records provided for bulk store.']);
            return;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $currentUserId = $this->auth->user()['user_id'] ?? 0;
        $updateExisting = !empty($json['update_existing']);

        foreach ($styles as $index => $row) {
            $rowNum = $index + 1;

            $styleCode = trim((string)($row['style_code'] ?? ''));
            $styleName = trim((string)($row['style_name'] ?? ''));
            $buyerId = !empty($row['buyer_id']) ? (int)$row['buyer_id'] : null;

            if ($styleCode === '' || $styleName === '') {
                $errors[] = "Row {$rowNum}: Style Code and Style Name are required.";
                continue;
            }

            if (!$buyerId) {
                $errors[] = "Row {$rowNum} ({$styleCode}): Buyer selection is required.";
                continue;
            }

            $data = [
                'style_code' => $styleCode,
                'style_name' => $styleName,
                'article_number' => trim((string)($row['article_number'] ?? '')),
                'buyer_id' => $buyerId,
                'season' => trim((string)($row['season'] ?? '')),
                'category' => trim((string)($row['category'] ?? '')),
                'garment_type' => trim((string)($row['garment_type'] ?? '')),
                'price' => isset($row['price']) && is_numeric($row['price']) ? (float)$row['price'] : 0.0,
                'status' => !empty($row['status']) ? (string)$row['status'] : 'design',
                'style_description' => trim((string)($row['style_description'] ?? '')),
                'description' => trim((string)($row['description'] ?? ''))
            ];

            $existing = $this->db()->get('styles', '*', ['style_code' => $styleCode]);

            if ($existing) {
                if ($updateExisting) {
                    $data['updated_by'] = $currentUserId;
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $this->db()->update('styles', $data, ['style_id' => $existing['style_id']]);
                    $updated++;
                } else {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: Style Code '{$styleCode}' already exists.";
                }
            } else {
                $data['created_by'] = $currentUserId;
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db()->insert('styles', $data);
                $imported++;
            }
        }

        $msg = "Batch store completed. Created: {$imported}, Updated: {$updated}";
        if ($skipped > 0) $msg .= ", Skipped: {$skipped}";

        echo json_encode([
            'success' => true,
            'message' => $msg,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    }

    /**
     * Map Excel/CSV header row to standard field keys
     */
    private function mapStyleHeaders(array $headerRow): array
    {
        $indexes = [];
        foreach ($headerRow as $colIdx => $colName) {
            if ($colName === null) continue;
            $normalized = strtolower(trim((string)$colName));

            if (preg_match('/code|style_code|style code|style_no|style #/', $normalized)) {
                if (!isset($indexes['style_code'])) $indexes['style_code'] = $colIdx;
            } elseif (preg_match('/name|style_name|style name|title/', $normalized)) {
                if (!isset($indexes['style_name'])) $indexes['style_name'] = $colIdx;
            } elseif (preg_match('/article|art_no|art #|ref|reference/', $normalized)) {
                if (!isset($indexes['article_number'])) $indexes['article_number'] = $colIdx;
            } elseif (preg_match('/buyer|partner|customer/', $normalized)) {
                if (!isset($indexes['buyer'])) $indexes['buyer'] = $colIdx;
            } elseif (preg_match('/season|timeline|collection/', $normalized)) {
                if (!isset($indexes['season'])) $indexes['season'] = $colIdx;
            } elseif (preg_match('/category|dept|department/', $normalized)) {
                if (!isset($indexes['category'])) $indexes['category'] = $colIdx;
            } elseif (preg_match('/garment|type|item_type/', $normalized)) {
                if (!isset($indexes['garment_type'])) $indexes['garment_type'] = $colIdx;
            } elseif (preg_match('/price|cost|val|target_price/', $normalized)) {
                if (!isset($indexes['price'])) $indexes['price'] = $colIdx;
            } elseif (preg_match('/status|stage|state/', $normalized)) {
                if (!isset($indexes['status'])) $indexes['status'] = $colIdx;
            } elseif (preg_match('/style_description|style description|summary/', $normalized)) {
                if (!isset($indexes['style_description'])) $indexes['style_description'] = $colIdx;
            } elseif (preg_match('/note|remark|description|internal/', $normalized)) {
                if (!isset($indexes['description'])) $indexes['description'] = $colIdx;
            }
        }

        return $indexes;
    }

    /**
     * Check if a row array is completely empty
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Display Technical Specification Tech Pack for a specific style
     */
    public function techPack(int $id): void
    {
        $this->requireAuth();

        $techPackData = $this->styleModel->getTechPackData($id);
        if (!$techPackData) {
            $_SESSION['error_message'] = "Style not found for Tech Pack generation.";
            $this->redirect('merchandising/styles');
            return;
        }

        $this->view('merchandising/styles/tech_pack', $techPackData);
    }

    /**
     * Display Tech Packs index library view
     */
    public function techPacksIndex(): void
    {
        $this->requireAuth();

        $filters = [
            'search'   => $_GET['search'] ?? '',
            'buyer_id' => $_GET['buyer_id'] ?? '',
            'status'   => $_GET['status'] ?? '',
            'season'   => $_GET['season'] ?? ''
        ];

        $this->view('merchandising/tech_packs/index', [
            'styles'    => $this->styleModel->getAllWithBuyers($filters),
            'dropdowns' => $this->styleModel->getDropdownOptions(),
            'filters'   => $filters
        ]);
    }

    /**
     * Database instance helper
     */
    private function db()
    {
        return Container::get(\Core\Database::class);
    }
}

