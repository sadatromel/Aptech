<?php

use FastRoute\RouteCollector;
use Core\Container;

use App\Controllers\Commercial\CommercialDashboardController;
use App\Controllers\Commercial\QuotationController;
use App\Controllers\Commercial\SalesOrderController;
use App\Controllers\Commercial\SalesContractController;
use App\Controllers\Commercial\InvoiceController;
use App\Controllers\Commercial\ExportController;
use App\Controllers\Commercial\LetterOfCreditController;

use App\Controllers\Production\ProductionController;
use App\Controllers\Production\PlanningController;
use App\Controllers\Production\CapacityController;
use App\Controllers\Production\CuttingController;
use App\Controllers\Production\SewingController;
use App\Controllers\Production\FinishingController;
use App\Controllers\Production\QualityController;
use App\Controllers\Production\PackingController;
use App\Controllers\Production\WashingController;

return function (RouteCollector $r) {
    // ---------------------------------------------------------
    // COMMERCIAL MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/commercial', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [CommercialDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/dashboard', [CommercialDashboardController::class, 'index']);

        $r->addRoute(['GET', 'POST'], '/quotations', [QuotationController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/quotations/create', [QuotationController::class, 'create']);
        $r->addRoute(['POST'], '/quotations/store', [QuotationController::class, 'store']);
        $r->addRoute(['GET'], '/quotations/show', [QuotationController::class, 'show']);

        $r->addRoute(['GET', 'POST'], '/sales_orders', [SalesOrderController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/sales-orders', [SalesOrderController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/sales_orders/create', [SalesOrderController::class, 'create']);
        $r->addRoute(['POST'], '/sales_orders/store', [SalesOrderController::class, 'store']);
        $r->addRoute(['GET'], '/sales_orders/show', [SalesOrderController::class, 'show']);

        $r->addRoute(['GET'], '/sales-contracts', [SalesContractController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/sales-contracts/create', [SalesContractController::class, 'create']);
        $r->addRoute(['POST'], '/sales-contracts/store', [SalesContractController::class, 'store']);
        $r->addRoute(['GET'], '/sales-contracts/{id:\d+}', [SalesContractController::class, 'show']);
        $r->addRoute(['GET'], '/sales-contracts/show/{id:\d+}', [SalesContractController::class, 'show']);

        $r->addRoute(['GET', 'POST'], '/invoices', [InvoiceController::class, 'index']);
        $r->addRoute(['GET'], '/invoices/create', [InvoiceController::class, 'create']);
        $r->addRoute(['POST'], '/invoices/store', [InvoiceController::class, 'store']);
        $r->addRoute(['GET'], '/invoices/show/{id:\d+}', [InvoiceController::class, 'show']);
        $r->addRoute(['POST'], '/invoices/receive-payment/{id:\d+}', [InvoiceController::class, 'receivePayment']);
        $r->addRoute(['GET'], '/invoices/print/{id:\d+}', [InvoiceController::class, 'printInvoice']);

        $r->addRoute(['GET'], '/export-documents', [ExportController::class, 'index']);
        $r->addRoute(['POST'], '/export-documents', [ExportController::class, 'store']);
        $r->addRoute(['GET'], '/export-documents/{id:\d+}', [ExportController::class, 'show']);
        $r->addRoute(['GET'], '/export', [ExportController::class, 'index']);

        $r->addRoute(['GET'], '/letters-of-credit', [LetterOfCreditController::class, 'index']);
        $r->addRoute(['POST'], '/letters-of-credit', [LetterOfCreditController::class, 'store']);
    });

    // ---------------------------------------------------------
    // MASTER LIBRARY MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/master_library', function (RouteCollector $r) {
        // Buyer Brands
        $r->addRoute(['GET', 'POST'], '/buyer_brands', [\App\Controllers\MasterLibrary\BuyerBrandController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/buyer_brands/create', [\App\Controllers\MasterLibrary\BuyerBrandController::class, 'create']);
        $r->addRoute(['POST'], '/buyer_brands/store', [\App\Controllers\MasterLibrary\BuyerBrandController::class, 'store']);
        $r->addRoute(['GET'], '/buyer_brands/edit', [\App\Controllers\MasterLibrary\BuyerBrandController::class, 'edit']);
        $r->addRoute(['POST'], '/buyer_brands/update', [\App\Controllers\MasterLibrary\BuyerBrandController::class, 'update']);
        $r->addRoute(['POST'], '/buyer_brands/delete', [\App\Controllers\MasterLibrary\BuyerBrandController::class, 'destroy']);

        // Product Categories
        $r->addRoute(['GET', 'POST'], '/product_categories', [\App\Controllers\MasterLibrary\ProductCategoryController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/product_categories/create', [\App\Controllers\MasterLibrary\ProductCategoryController::class, 'create']);
        $r->addRoute(['POST'], '/product_categories/store', [\App\Controllers\MasterLibrary\ProductCategoryController::class, 'store']);
        $r->addRoute(['GET'], '/product_categories/edit', [\App\Controllers\MasterLibrary\ProductCategoryController::class, 'edit']);
        $r->addRoute(['POST'], '/product_categories/update', [\App\Controllers\MasterLibrary\ProductCategoryController::class, 'update']);
        $r->addRoute(['POST'], '/product_categories/delete', [\App\Controllers\MasterLibrary\ProductCategoryController::class, 'destroy']);

        // Product Departments
        $r->addRoute(['GET', 'POST'], '/product_departments', [\App\Controllers\MasterLibrary\ProductDepartmentController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/product_departments/create', [\App\Controllers\MasterLibrary\ProductDepartmentController::class, 'create']);
        $r->addRoute(['POST'], '/product_departments/store', [\App\Controllers\MasterLibrary\ProductDepartmentController::class, 'store']);
        $r->addRoute(['GET'], '/product_departments/edit', [\App\Controllers\MasterLibrary\ProductDepartmentController::class, 'edit']);
        $r->addRoute(['POST'], '/product_departments/update', [\App\Controllers\MasterLibrary\ProductDepartmentController::class, 'update']);
        $r->addRoute(['POST'], '/product_departments/delete', [\App\Controllers\MasterLibrary\ProductDepartmentController::class, 'destroy']);

        // Garment Sample Types
        $r->addRoute(['GET', 'POST'], '/garment_sample_types', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'index']);
        $r->addRoute(['GET'], '/garment_sample_types/create', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'create']);
        $r->addRoute(['POST'], '/garment_sample_types/create', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'store']);
        $r->addRoute(['POST'], '/garment_sample_types/store', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'store']);
        $r->addRoute(['GET'], '/garment_sample_types/edit/{id:\d+}', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'edit']);
        $r->addRoute(['POST'], '/garment_sample_types/edit/{id:\d+}', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'update']);
        $r->addRoute(['POST'], '/garment_sample_types/update', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'update']);
        $r->addRoute(['POST'], '/garment_sample_types/delete', [\App\Controllers\MasterLibrary\GarmentSampleTypeController::class, 'destroy']);

        // Product Natures
        $r->addRoute(['GET', 'POST'], '/product_natures', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'index']);
        $r->addRoute(['GET'], '/product_natures/create', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'create']);
        $r->addRoute(['POST'], '/product_natures/create', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'store']);
        $r->addRoute(['POST'], '/product_natures/store', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'store']);
        $r->addRoute(['GET'], '/product_natures/edit/{id:\d+}', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'edit']);
        $r->addRoute(['POST'], '/product_natures/edit/{id:\d+}', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'update']);
        $r->addRoute(['POST'], '/product_natures/update', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'update']);
        $r->addRoute(['POST'], '/product_natures/delete', [\App\Controllers\MasterLibrary\ProductNatureController::class, 'destroy']);

        // Product Types
        $r->addRoute(['GET', 'POST'], '/product_types', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'index']);
        $r->addRoute(['GET'], '/product_types/create', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'create']);
        $r->addRoute(['POST'], '/product_types/create', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'store']);
        $r->addRoute(['POST'], '/product_types/store', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'store']);
        $r->addRoute(['GET'], '/product_types/edit/{id:\d+}', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'edit']);
        $r->addRoute(['POST'], '/product_types/edit/{id:\d+}', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'update']);
        $r->addRoute(['POST'], '/product_types/update', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'update']);
        $r->addRoute(['POST'], '/product_types/delete', [\App\Controllers\MasterLibrary\ProductTypeController::class, 'destroy']);

        // Garments Items
        $r->addRoute(['GET', 'POST'], '/garments_items', [\App\Controllers\MasterLibrary\GarmentsItemController::class, 'index']);
        $r->addRoute(['GET'], '/garments_items/create', [\App\Controllers\MasterLibrary\GarmentsItemController::class, 'create']);
        $r->addRoute(['POST'], '/garments_items/create', [\App\Controllers\MasterLibrary\GarmentsItemController::class, 'store']);
        $r->addRoute(['POST'], '/garments_items/store', [\App\Controllers\MasterLibrary\GarmentsItemController::class, 'store']);
        $r->addRoute(['GET'], '/garments_items/edit/{id:\d+}', [\App\Controllers\MasterLibrary\GarmentsItemController::class, 'edit']);
        $r->addRoute(['POST'], '/garments_items/edit/{id:\d+}', [\App\Controllers\MasterLibrary\GarmentsItemController::class, 'update']);
        // Units of Measure (UOM)
        $r->addRoute(['GET', 'POST'], '/uoms', [\App\Controllers\MasterLibrary\UomController::class, 'index']);
        $r->addRoute(['GET'], '/uoms/create', [\App\Controllers\MasterLibrary\UomController::class, 'create']);
        $r->addRoute(['POST'], '/uoms/create', [\App\Controllers\MasterLibrary\UomController::class, 'store']);
        $r->addRoute(['POST'], '/uoms/store', [\App\Controllers\MasterLibrary\UomController::class, 'store']);
        $r->addRoute(['GET'], '/uoms/edit/{id:\d+}', [\App\Controllers\MasterLibrary\UomController::class, 'edit']);
        $r->addRoute(['POST'], '/uoms/edit/{id:\d+}', [\App\Controllers\MasterLibrary\UomController::class, 'update']);
        $r->addRoute(['POST'], '/uoms/update', [\App\Controllers\MasterLibrary\UomController::class, 'update']);
        $r->addRoute(['POST'], '/uoms/delete', [\App\Controllers\MasterLibrary\UomController::class, 'destroy']);
    });

    // ---------------------------------------------------------
    // MERCHANDISING MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/merchandising', function (RouteCollector $r) {
        // Executive Dashboard
        $r->addRoute(['GET'], '', [\App\Controllers\Merchandising\MerchandisingDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/dashboard', [\App\Controllers\Merchandising\MerchandisingDashboardController::class, 'index']);

        // Styles & Tech Packs
        $r->addRoute(['GET', 'POST'], '/styles', [\App\Controllers\Merchandising\StyleController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/styles/create', [\App\Controllers\Merchandising\StyleController::class, 'create']);
        $r->addRoute(['POST'], '/styles/store', [\App\Controllers\Merchandising\StyleController::class, 'store']);
        $r->addRoute(['POST'], '/styles/bulk-upload', [\App\Controllers\Merchandising\StyleController::class, 'bulkUpload']);
        $r->addRoute(['POST'], '/styles/bulk-store', [\App\Controllers\Merchandising\StyleController::class, 'bulkStore']);
        $r->addRoute(['GET'], '/styles/download-template', [\App\Controllers\Merchandising\StyleController::class, 'downloadTemplate']);
        $r->addRoute(['GET'], '/styles/show', [\App\Controllers\Merchandising\StyleController::class, 'show']);
        $r->addRoute(['GET'], '/styles/{id:\d+}', [\App\Controllers\Merchandising\StyleController::class, 'show']);
        $r->addRoute(['POST'], '/styles/{id:\d+}', [\App\Controllers\Merchandising\StyleController::class, 'update']);
        $r->addRoute(['GET'], '/styles/{id:\d+}/tech-pack', [\App\Controllers\Merchandising\StyleController::class, 'techPack']);
        $r->addRoute(['GET'], '/tech-packs', [\App\Controllers\Merchandising\StyleController::class, 'techPacksIndex']);

        // Time & Action (T&A) Calendar
        $r->addRoute(['GET'], '/tna', [\App\Controllers\Merchandising\TnaController::class, 'index']);
        $r->addRoute(['GET'], '/tna/{id:\d+}', [\App\Controllers\Merchandising\TnaController::class, 'show']);
        $r->addRoute(['POST'], '/tna/update-milestone/{id:\d+}', [\App\Controllers\Merchandising\TnaController::class, 'updateMilestone']);

        // Bill of Materials (BOM)
        $r->addRoute(['GET', 'POST'], '/bom', [\App\Controllers\Merchandising\BomController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/boms', [\App\Controllers\Merchandising\BomController::class, 'index']);

        // Sampling & Tracking
        $r->addRoute(['GET', 'POST'], '/sampling', [\App\Controllers\Merchandising\SamplingController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/sample-planning', [\App\Controllers\Merchandising\SamplePlanningController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/sample-production', [\App\Controllers\Merchandising\SampleProductionTrackingController::class, 'index']);

        // Production Orders
        $r->addRoute(['GET', 'POST'], '/production-orders', [\App\Controllers\Merchandising\ProductionOrderController::class, 'index']);
        $r->addRoute(['POST'], '/production-orders/store', [\App\Controllers\Merchandising\ProductionOrderController::class, 'store']);
        $r->addRoute(['GET'], '/production-orders/show/{id:\d+}', [\App\Controllers\Merchandising\ProductionOrderController::class, 'show']);
        $r->addRoute(['POST'], '/production-orders/update/{id:\d+}', [\App\Controllers\Merchandising\ProductionOrderController::class, 'update']);
        $r->addRoute(['POST'], '/production-orders/update-status/{id:\d+}', [\App\Controllers\Merchandising\ProductionOrderController::class, 'updateStatus']);
        $r->addRoute(['POST'], '/production-orders/delete/{id:\d+}', [\App\Controllers\Merchandising\ProductionOrderController::class, 'destroy']);
        $r->addRoute(['GET'], '/production-orders/style-details/{id:\d+}', [\App\Controllers\Merchandising\ProductionOrderController::class, 'getStyleDetails']);
        $r->addRoute(['GET'], '/production-orders/get-cities/{id:\d+}', [\App\Controllers\Merchandising\ProductionOrderController::class, 'getCities']);

        // Costing & Purchase Requests & Transfer
        $r->addRoute(['GET', 'POST'], '/costing', [\App\Controllers\Merchandising\BulkCostingController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/bulk-costing', [\App\Controllers\Merchandising\BulkCostingController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/purchase-requests', [\App\Controllers\Merchandising\PurchaseRequestController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/transfer', [\App\Controllers\Merchandising\TransferController::class, 'index']);
    });

    // ---------------------------------------------------------
    // INVENTORY MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/inventory', function (RouteCollector $r) {
        $r->addRoute(['GET'], '/dashboard', [\App\Controllers\Inventory\StoreDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/items', [\App\Controllers\Inventory\ItemMasterController::class, 'index']);
        $r->addRoute(['POST'], '/items/store', [\App\Controllers\Inventory\ItemMasterController::class, 'store']);
        $r->addRoute(['POST'], '/items/category', [\App\Controllers\Inventory\ItemMasterController::class, 'storeCategory']);
        $r->addRoute(['POST'], '/items/opening-balance', [\App\Controllers\Inventory\ItemMasterController::class, 'updateOpeningBalance']);
        
        $r->addRoute(['GET'], '/warehouses', [\App\Controllers\Inventory\WarehouseController::class, 'index']);
        $r->addRoute(['POST'], '/warehouses/store', [\App\Controllers\Inventory\WarehouseController::class, 'storeWarehouse']);
        $r->addRoute(['POST'], '/warehouses/room', [\App\Controllers\Inventory\WarehouseController::class, 'storeRoom']);
        $r->addRoute(['POST'], '/warehouses/rack', [\App\Controllers\Inventory\WarehouseController::class, 'storeRack']);
        
        $r->addRoute(['GET'], '/transactions', [\App\Controllers\Inventory\StockTransactionController::class, 'index']);
        $r->addRoute(['POST'], '/transactions/grn', [\App\Controllers\Inventory\StockTransactionController::class, 'storeGRN']);
        $r->addRoute(['POST'], '/transactions/requisition', [\App\Controllers\Inventory\StockTransactionController::class, 'storeRequisition']);
        $r->addRoute(['POST'], '/transactions/issue', [\App\Controllers\Inventory\StockTransactionController::class, 'storeIssue']);
        $r->addRoute(['POST'], '/transactions/transfer', [\App\Controllers\Inventory\StockTransactionController::class, 'storeTransfer']);
        $r->addRoute(['POST'], '/transactions/adjustment', [\App\Controllers\Inventory\StockTransactionController::class, 'storeAdjustment']);
        
        $r->addRoute(['GET'], '/production', [\App\Controllers\Inventory\ProductionStoreController::class, 'index']);
        $r->addRoute(['POST'], '/production/inspection', [\App\Controllers\Inventory\ProductionStoreController::class, 'storeInspection']);
        
        $r->addRoute(['GET'], '/control', [\App\Controllers\Inventory\InventoryControlController::class, 'index']);
        $r->addRoute(['POST'], '/control/approval', [\App\Controllers\Inventory\InventoryControlController::class, 'processApproval']);
        $r->addRoute(['GET'], '/control/barcode', [\App\Controllers\Inventory\InventoryControlController::class, 'generateBarcode']);

        // Gate Passes setup
        $r->addRoute(['GET', 'POST'], '/gate-passes', [\App\Controllers\Inventory\GatePassController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/gate-passes/create', [\App\Controllers\Inventory\GatePassController::class, 'create']);
        $r->addRoute(['POST'], '/gate-passes/store', [\App\Controllers\Inventory\GatePassController::class, 'store']);
        $r->addRoute(['GET'], '/gate-passes/show/{id:\d+}', [\App\Controllers\Inventory\GatePassController::class, 'show']);
        $r->addRoute(['POST'], '/gate-passes/delete/{id:\d+}', [\App\Controllers\Inventory\GatePassController::class, 'destroy']);
    });

    // ---------------------------------------------------------
    // PROCUREMENT MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/procurement', function (RouteCollector $r) {
        $r->addRoute(['GET'], '/vendors', [\App\Controllers\Procurement\VendorController::class, 'index']);
        $r->addRoute(['POST'], '/vendors', [\App\Controllers\Procurement\VendorController::class, 'store']);
        $r->addRoute(['GET'], '/vendors/{id:\d+}', [\App\Controllers\Procurement\VendorController::class, 'show']);
        $r->addRoute(['POST'], '/vendors/{id:\d+}', [\App\Controllers\Procurement\VendorController::class, 'update']);
        $r->addRoute(['POST'], '/vendors/{id:\d+}/delete', [\App\Controllers\Procurement\VendorController::class, 'destroy']);
        $r->addRoute(['POST'], '/vendors/import', [\App\Controllers\Procurement\VendorController::class, 'import']);
        $r->addRoute(['GET'], '/vendors/download-template', [\App\Controllers\Procurement\VendorController::class, 'downloadTemplate']);

        $r->addRoute(['GET'], '/pos', [\App\Controllers\Procurement\PurchaseOrderController::class, 'index']);
        $r->addRoute(['POST'], '/pos', [\App\Controllers\Procurement\PurchaseOrderController::class, 'store']);
        $r->addRoute(['GET'], '/pos/{id:\d+}', [\App\Controllers\Procurement\PurchaseOrderController::class, 'show']);
        $r->addRoute(['POST'], '/pos/{id:\d+}', [\App\Controllers\Procurement\PurchaseOrderController::class, 'update']);
        $r->addRoute(['POST'], '/pos/{id:\d+}/status', [\App\Controllers\Procurement\PurchaseOrderController::class, 'updateStatus']);
        $r->addRoute(['POST'], '/pos/{id:\d+}/delete', [\App\Controllers\Procurement\PurchaseOrderController::class, 'destroy']);
    });

    // ---------------------------------------------------------
    // TRANSPORT MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/transport', function (RouteCollector $r) {
        $r->addRoute(['GET', 'POST'], '/vehicles', [\App\Controllers\Transport\TransportController::class, 'vehicles']);
        $r->addRoute(['GET', 'POST'], '/vehicles/create', [\App\Controllers\Transport\TransportController::class, 'createVehicle']);
        $r->addRoute(['POST'], '/vehicles/store', [\App\Controllers\Transport\TransportController::class, 'storeVehicle']);
        $r->addRoute(['GET', 'POST'], '/routes', [\App\Controllers\Transport\TransportController::class, 'routes']);

        // Added missing routes
        $r->addRoute(['GET', 'POST'], '/vehicle-requisition', [\App\Controllers\Transport\TransportController::class, 'requisition']);
        $r->addRoute(['GET', 'POST'], '/employee-seat-allotment', [\App\Controllers\Transport\TransportController::class, 'seatAllotment']);
        $r->addRoute(['GET', 'POST'], '/maintenance', [\App\Controllers\Transport\TransportController::class, 'maintenance']);
        $r->addRoute(['GET', 'POST'], '/parts-inventory', [\App\Controllers\Transport\TransportController::class, 'partsInventory']);
    });

    // ---------------------------------------------------------
    // CUTTING MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/cutting', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [CuttingController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/create', [CuttingController::class, 'create']);
        $r->addRoute(['POST'], '/store', [CuttingController::class, 'store']);
        $r->addRoute(['GET'], '/bundles/{id:\d+}', [CuttingController::class, 'bundles']);
        $r->addRoute(['GET'], '/generate-bundles/{id:\d+}', [CuttingController::class, 'generateBundles']);
    });

    // ---------------------------------------------------------
    // SEWING MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/sewing', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [SewingController::class, 'index']);
        $r->addRoute(['GET'], '/lines', [SewingController::class, 'lines']);
        $r->addRoute(['GET'], '/create', [SewingController::class, 'create']);
        $r->addRoute(['POST'], '/store', [SewingController::class, 'store']);
        $r->addRoute(['GET'], '/edit/{id:\d+}', [SewingController::class, 'edit']);
        $r->addRoute(['GET'], '/performance', [SewingController::class, 'performance']);
    });

    // ---------------------------------------------------------
    // WASHING MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/washing', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [WashingController::class, 'batches']);
        $r->addRoute(['GET', 'POST'], '/create', [WashingController::class, 'createBatch']);
    });

    // ---------------------------------------------------------
    // PACKING MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/packing', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [PackingController::class, 'plans']);
        $r->addRoute(['GET'], '/plans', [PackingController::class, 'plans']);
        $r->addRoute(['GET', 'POST'], '/create', [PackingController::class, 'createPlan']);
        $r->addRoute(['GET'], '/cartons', [PackingController::class, 'cartons']);
    });

    // ---------------------------------------------------------
    // FINISHING MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/finishing', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [FinishingController::class, 'index']);
        $r->addRoute(['GET'], '/{tab}', [FinishingController::class, 'showTab']);
    });

    // ---------------------------------------------------------
    // QUALITY MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/quality', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [QualityController::class, 'index']);
        $r->addRoute(['GET'], '/{tab}', [QualityController::class, 'showTab']);
    });

    // ---------------------------------------------------------
    // ---------------------------------------------------------
    // FINANCE MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/finance', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [\App\Controllers\Finance\FinanceDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/', [\App\Controllers\Finance\FinanceDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/dashboard', [\App\Controllers\Finance\FinanceDashboardController::class, 'index']);

        $r->addRoute(['GET'], '/accounts', [\App\Controllers\Finance\ChartOfAccountsController::class, 'index']);
        $r->addRoute(['POST'], '/accounts/store', [\App\Controllers\Finance\ChartOfAccountsController::class, 'storeAccount']);
        $r->addRoute(['POST'], '/accounts/opening-balance', [\App\Controllers\Finance\ChartOfAccountsController::class, 'storeOpeningBalance']);
        $r->addRoute(['POST'], '/accounts/fiscal-year', [\App\Controllers\Finance\ChartOfAccountsController::class, 'storeFiscalYear']);

        $r->addRoute(['GET'], '/vouchers', [\App\Controllers\Finance\FinanceVoucherController::class, 'index']);
        $r->addRoute(['POST'], '/vouchers/store', [\App\Controllers\Finance\FinanceVoucherController::class, 'store']);
        $r->addRoute(['POST'], '/vouchers/approve', [\App\Controllers\Finance\FinanceVoucherController::class, 'approve']);

        $r->addRoute(['GET'], '/cash-bank', [\App\Controllers\Finance\CashBankController::class, 'index']);
        $r->addRoute(['POST'], '/cash-bank/transfer', [\App\Controllers\Finance\CashBankController::class, 'storeTransfer']);
        $r->addRoute(['POST'], '/cash-bank/cheque', [\App\Controllers\Finance\CashBankController::class, 'storeCheque']);
        $r->addRoute(['POST'], '/cash-bank/reconciliation', [\App\Controllers\Finance\CashBankController::class, 'storeReconciliation']);

        $r->addRoute(['GET'], '/subledgers', [\App\Controllers\Finance\FinanceSubledgerController::class, 'index']);
        $r->addRoute(['POST'], '/subledgers/receipt', [\App\Controllers\Finance\FinanceSubledgerController::class, 'storeReceipt']);
        $r->addRoute(['POST'], '/subledgers/payment', [\App\Controllers\Finance\FinanceSubledgerController::class, 'storePayment']);
        $r->addRoute(['POST'], '/subledgers/cost-center', [\App\Controllers\Finance\FinanceSubledgerController::class, 'storeCostCenter']);

        $r->addRoute(['GET'], '/general-ledger', [\App\Controllers\Finance\GeneralLedgerController::class, 'index']);
        $r->addRoute(['POST'], '/general-ledger/store', [\App\Controllers\Finance\GeneralLedgerController::class, 'store']);
        $r->addRoute(['POST'], '/general-ledger/journal', [\App\Controllers\Finance\GeneralLedgerController::class, 'storeJournal']);
        $r->addRoute(['GET'], '/general-ledger/show/{id:\d+}', [\App\Controllers\Finance\GeneralLedgerController::class, 'show']);
        $r->addRoute(['POST'], '/general-ledger/delete/{id:\d+}', [\App\Controllers\Finance\GeneralLedgerController::class, 'destroy']);
        $r->addRoute(['GET'], '/general-ledger/trial-balance', [\App\Controllers\Finance\GeneralLedgerController::class, 'trialBalance']);
        $r->addRoute(['GET'], '/general-ledger/profit-and-loss', [\App\Controllers\Finance\GeneralLedgerController::class, 'profitAndLoss']);
        $r->addRoute(['GET'], '/general-ledger/balance-sheet', [\App\Controllers\Finance\GeneralLedgerController::class, 'balanceSheet']);
        $r->addRoute(['GET'], '/general-ledger/cash-book', [\App\Controllers\Finance\GeneralLedgerController::class, 'cashBook']);

        $r->addRoute(['GET'], '/reports', [\App\Controllers\Finance\FinancialReportsController::class, 'index']);
        $r->addRoute(['GET'], '/reports/profit-and-loss', [\App\Controllers\Finance\GeneralLedgerController::class, 'profitAndLoss']);
        $r->addRoute(['GET'], '/reports/balance-sheet', [\App\Controllers\Finance\GeneralLedgerController::class, 'balanceSheet']);
        $r->addRoute(['GET'], '/reports/cash-book', [\App\Controllers\Finance\GeneralLedgerController::class, 'cashBook']);
        $r->addRoute(['POST'], '/reports/depreciation', [\App\Controllers\Finance\FinancialReportsController::class, 'postDepreciation']);
        $r->addRoute(['POST'], '/reports/budget', [\App\Controllers\Finance\FinancialReportsController::class, 'storeBudget']);
        $r->addRoute(['POST'], '/reports/vat', [\App\Controllers\Finance\FinancialReportsController::class, 'storeVAT']);
    });

    // ---------------------------------------------------------
    // ADMIN / SETUP ROUTING (Legacy-to-MVC routes)
    // ---------------------------------------------------------
    $r->addGroup('/admin', function (RouteCollector $r) {
        // User Management
        $r->addRoute(['GET', 'POST'], '/users', [\App\Controllers\Admin\UserController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/users/create', [\App\Controllers\Admin\UserController::class, 'create']);
        $r->addRoute(['POST'], '/users/store', [\App\Controllers\Admin\UserController::class, 'store']);
        $r->addRoute(['GET'], '/users/edit', [\App\Controllers\Admin\UserController::class, 'edit']);
        $r->addRoute(['POST'], '/users/update', [\App\Controllers\Admin\UserController::class, 'update']);
        $r->addRoute(['POST'], '/users/destroy', [\App\Controllers\Admin\UserController::class, 'destroy']);
        $r->addRoute(['POST'], '/users/toggle_status', [\App\Controllers\Admin\UserController::class, 'toggleStatus']);
        $r->addRoute(['POST'], '/users/reset_password', [\App\Controllers\Admin\UserController::class, 'resetPassword']);
        $r->addRoute(['GET'], '/users/show', [\App\Controllers\Admin\UserController::class, 'show']);

        // Roles
        $r->addRoute(['GET', 'POST'], '/roles', [\App\Controllers\Admin\RoleController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/roles/create', [\App\Controllers\Admin\RoleController::class, 'create']);
        $r->addRoute(['GET', 'POST'], '/roles/edit', [\App\Controllers\Admin\RoleController::class, 'edit']);
        $r->addRoute(['POST'], '/roles/store', [\App\Controllers\Admin\RoleController::class, 'store']);
        $r->addRoute(['POST'], '/roles/update', [\App\Controllers\Admin\RoleController::class, 'update']);
        $r->addRoute(['POST'], '/roles/destroy', [\App\Controllers\Admin\RoleController::class, 'destroy']);
        $r->addRoute(['POST'], '/roles/update_permissions', [\App\Controllers\Admin\RoleController::class, 'updatePermissions']);

        // Settings
        $r->addRoute(['GET', 'POST'], '/settings', [\App\Controllers\Admin\SettingsController::class, 'index']);

        // Setup
        $r->addRoute(['GET'], '/setup', [\App\Controllers\Admin\SetupInstallationController::class, 'index']);
        $r->addRoute(['GET'], '/setup/system-info', [\App\Controllers\Admin\SetupInstallationController::class, 'systemInfo']);
        $r->addRoute(['GET'], '/setup/configuration', [\App\Controllers\Admin\SetupInstallationController::class, 'configuration']);
        $r->addRoute(['GET'], '/setup/backup', [\App\Controllers\Admin\SetupInstallationController::class, 'backup']);
        $r->addRoute(['GET'], '/setup/wizard', [\App\Controllers\Admin\SetupInstallationController::class, 'wizard']);

        // Setup Actions
        $r->addRoute(['POST'], '/setup/save-config', [\App\Controllers\Admin\SetupInstallationController::class, 'saveConfiguration']);
        $r->addRoute(['POST'], '/setup/delete-config', [\App\Controllers\Admin\SetupInstallationController::class, 'deleteConfiguration']);
        $r->addRoute(['POST'], '/setup/create-backup', [\App\Controllers\Admin\SetupInstallationController::class, 'createBackup']);
        $r->addRoute(['POST'], '/setup/delete-backup', [\App\Controllers\Admin\SetupInstallationController::class, 'deleteBackup']);
        $r->addRoute(['POST'], '/setup/install', [\App\Controllers\Admin\SetupInstallationController::class, 'install']);
        $r->addRoute(['GET'], '/setup/download', [\App\Controllers\Admin\SetupInstallationController::class, 'downloadBackup']);
    });

    // ---------------------------------------------------------
    // PRODUCTION MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/production', function (RouteCollector $r) {
        $r->addRoute(['GET', 'POST'], '', [ProductionController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/dashboard', [ProductionController::class, 'index']);

        // Planning
        $r->addRoute(['GET', 'POST'], '/planning', [PlanningController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/planning/create', [PlanningController::class, 'create']);
        $r->addRoute(['GET'], '/planning/line-loading', [PlanningController::class, 'lineLoading']);
        $r->addRoute(['GET'], '/planning/capacity-report', [PlanningController::class, 'capacityReport']);

        // Capacity
        $r->addRoute(['GET'], '/capacity', [CapacityController::class, 'index']);

        // Cutting
        $r->addRoute(['GET'], '/cutting', [CuttingController::class, 'index']);
        $r->addRoute(['GET', 'POST'], '/cutting/create', [CuttingController::class, 'create']);
        $r->addRoute(['POST'], '/cutting/store', [CuttingController::class, 'store']);
        $r->addRoute(['GET'], '/cutting/bundles/{id:\d+}', [CuttingController::class, 'bundles']);
        $r->addRoute(['GET'], '/cutting/generate-bundles/{id:\d+}', [CuttingController::class, 'generateBundles']);

        // Sewing
        $r->addRoute(['GET'], '/sewing', [SewingController::class, 'index']);
        $r->addRoute(['GET'], '/sewing/lines', [SewingController::class, 'lines']);
        $r->addRoute(['GET'], '/sewing/create', [SewingController::class, 'create']);
        $r->addRoute(['POST'], '/sewing/store', [SewingController::class, 'store']);
        $r->addRoute(['GET'], '/sewing/edit/{id:\d+}', [SewingController::class, 'edit']);
        $r->addRoute(['GET'], '/sewing/performance', [SewingController::class, 'performance']);

        // Finishing
        $r->addRoute(['GET'], '/finishing', [FinishingController::class, 'index']);
        $r->addRoute(['GET'], '/finishing/{tab}', [FinishingController::class, 'showTab']);

        // Quality
        $r->addRoute(['GET'], '/quality', [QualityController::class, 'index']);
        $r->addRoute(['GET'], '/quality/{tab}', [QualityController::class, 'showTab']);

        // Packing
        $r->addRoute(['GET'], '/packing', [PackingController::class, 'plans']);
        $r->addRoute(['GET'], '/packing/plans', [PackingController::class, 'plans']);
        $r->addRoute(['GET', 'POST'], '/packing/create', [PackingController::class, 'createPlan']);
        $r->addRoute(['GET'], '/packing/cartons', [PackingController::class, 'cartons']);

        // Washing
        $r->addRoute(['GET'], '/washing', [WashingController::class, 'batches']);
        $r->addRoute(['GET', 'POST'], '/washing/create', [WashingController::class, 'createBatch']);

        // MRP & WIP
        $r->addRoute(['GET'], '/mrp', function() { (new ProductionController())->showTab('mrp'); });
        $r->addRoute(['GET'], '/wip', function() { (new ProductionController())->showTab('wip'); });
        $r->addRoute(['GET'], '/reports', function() { (new ProductionController())->showTab('reports'); });
    });

    // ---------------------------------------------------------
    // CORE ROUTES
    // ---------------------------------------------------------
    $r->addRoute(['GET'], '/dashboard', [\App\Controllers\DashboardController::class, 'index']);
    $r->addRoute(['GET'], '/home', [\App\Controllers\DashboardController::class, 'index']);
    $r->addRoute(['GET', 'POST'], '/login', [\App\Controllers\AuthController::class, 'login']);
    $r->addRoute(['GET'], '/logout', [\App\Controllers\AuthController::class, 'logout']);
    $r->addRoute(['GET'], '/profile', [\App\Controllers\ProfileController::class, 'index']);
    $r->addRoute(['POST'], '/profile', [\App\Controllers\ProfileController::class, 'update']);
    $r->addRoute(['GET'], '/users', [\App\Controllers\Admin\UserController::class, 'index']);
    $r->addRoute(['GET', 'POST'], '/register', [\App\Controllers\Admin\UserController::class, 'create']);
    $r->addRoute(['GET'], '/system_library', [\App\Controllers\Admin\SystemController::class, 'index']);

    // ---------------------------------------------------------
    // HR MODULE ROUTING
    // ---------------------------------------------------------
    $r->addGroup('/hr', function (RouteCollector $r) {
        $r->addRoute(['GET'], '', [\App\Controllers\HR\HRDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/dashboard', [\App\Controllers\HR\HRDashboardController::class, 'index']);
        $r->addRoute(['GET'], '/reports', [\App\Controllers\HR\HRReportController::class, 'index']);
        $r->addRoute(['GET'], '/reports/job-card', [\App\Controllers\HR\HRReportController::class, 'jobCardReport']);
        $r->addRoute(['GET'], '/reports/job-card-four-hours', [\App\Controllers\HR\HRReportController::class, 'jobCardFourHoursReport']);
        $r->addRoute(['GET'], '/reports/extra-ot-payment-sheet', [\App\Controllers\HR\HRReportController::class, 'extraOtPaymentSheetReport']);
        $r->addRoute(['GET'], '/reports/extra-ot-payment-sheet/export-excel', [\App\Controllers\HR\HRReportController::class, 'exportExtraOtPaymentExcel']);
        $r->addRoute(['GET'], '/reports/salary-sheet-staff', [\App\Controllers\HR\HRReportController::class, 'salarySheetStaffReport']);
        $r->addRoute(['GET'], '/reports/salary-sheet-worker', [\App\Controllers\HR\HRReportController::class, 'salarySheetWorkerReport']);
        $r->addRoute(['GET'], '/reports/ot-payment-sheet', [\App\Controllers\HR\HRReportController::class, 'otPaymentSheetReport']);
        $r->addRoute(['GET'], '/reports/ot-payment-sheet/export-excel', [\App\Controllers\HR\HRReportController::class, 'exportOtPaymentExcel']);
        $r->addRoute(['GET'], '/reports/payslip-bangla', [\App\Controllers\HR\HRReportController::class, 'payslipBanglaReport']);
        $r->addRoute(['GET'], '/reports/payslip-english', [\App\Controllers\HR\HRReportController::class, 'payslipEnglishReport']);
        $r->addRoute(['GET'], '/reports/leave-status', [\App\Controllers\HR\HRReportController::class, 'leaveStatusReport']);
        $r->addRoute(['GET'], '/reports/employee-leave-record', [\App\Controllers\HR\HRReportController::class, 'employeeLeaveRecordReport']);
        $r->addRoute(['GET'], '/reports/annual-leave-statement', [\App\Controllers\HR\HRReportController::class, 'annualLeaveStatementReport']);
        $r->addRoute(['GET'], '/reports/yearly-leave-balance', [\App\Controllers\HR\HRReportController::class, 'yearlyLeaveBalanceReport']);
        $r->addRoute(['GET'], '/reports/monthly-detailed-attendance', [\App\Controllers\HR\HRReportController::class, 'monthlyDetailedAttendance']);
        $r->addRoute(['GET'], '/reports/monthly-detailed-attendance/export-excel', [\App\Controllers\HR\HRReportController::class, 'exportMonthlyAttendanceExcel']);
    });

    // Module Reports Hub Routes
    $r->addRoute(['GET'], '/commercial/reports', function() { (new \App\Controllers\GenericReportController())->show('commercial'); });
    $r->addRoute(['GET'], '/merchandising/reports', function() { (new \App\Controllers\GenericReportController())->show('merchandising'); });
    $r->addRoute(['GET'], '/procurement/reports', function() { (new \App\Controllers\GenericReportController())->show('procurement'); });
    $r->addRoute(['GET'], '/inventory/reports', function() { (new \App\Controllers\GenericReportController())->show('inventory'); });
    $r->addRoute(['GET'], '/transport/reports', function() { (new \App\Controllers\GenericReportController())->show('transport'); });
    $r->addRoute(['GET'], '/master_library/reports', function() { (new \App\Controllers\GenericReportController())->show('master_library'); });

    // Dynamic Module Loader
    $r->addRoute(['GET'], '/dynamic', [\App\Controllers\DynamicModuleController::class, 'handle']);
};
