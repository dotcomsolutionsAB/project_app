<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CreateController;

use App\Http\Controllers\ViewController;

use App\Http\Controllers\UpdateController;

use App\Http\Controllers\DeleteController;

use App\Http\Controllers\CsvImportController;

use App\Http\Controllers\InvoiceController;

use App\Http\Controllers\ReportController;

use App\Http\Controllers\StockController;

use App\Http\Controllers\InvoiceControllerZP;

use App\Http\Middleware\GetUserRole;

use App\Http\Controllers\ZohoController;
// Route::get('/user', function (Request $request) {
    //     return $request->user();
    // })->middleware('auth:sanctum');

 Route::get('/public_view_product/{lang?}', [ViewController::class, 'lng_product_public']);
 Route::get('/public_view_category/{lang?}', [ViewController::class, 'lng_categories_public']);

 Route::get('/update_type/{platform}', [ViewController::class, 'getLatestUpdate']);

 Route::get('/zoho/estimate', [ZohoController::class, 'createEstimate']);
 Route::get('/estimates/{estimateId}', [ZohoController::class, 'getEstimate']);


    
Route::prefix('admin')->middleware(['auth:sanctum', GetUserRole::class . ':admin'])->group(function () {

    Route::get('/order_godown_stock/{orderId}', [ViewController::class, 'orderGodownStock']);

    Route::post('/zoho_quote', [ZohoController::class, 'zoho_quote']);

    Route::post('/zoho_sales', [ZohoController::class, 'zoho_sales']);
    
    Route::post('/generate-stock-report', [StockController::class, 'generateStockReport']);
    
    Route::post('/add_user', [CreateController::class, 'user']);

    Route::get('/view_user/{lang?}', [ViewController::class, 'user']);

    Route::get('/fetch_user/{search?}', [ViewController::class, 'find_user']);

    Route::post('/make_verify/{id}', [UpdateController::class, 'verify_user']);

    Route::post('/make_unverify/{id}', [UpdateController::class, 'unverify_user']);

    Route::post('/update_user', [UpdateController::class, 'user']);
    Route::post('/update_user_type', [UpdateController::class, 'updateUserType']);
    Route::post('/update_user_series', [UpdateController::class, 'updateUserSeries']);
    Route::post('/inactivate_user', [UpdateController::class, 'inactivate_user']);

    Route::post('/edit_purchase_lock', [UpdateController::class, 'updatePurchaseLock']);

    // Route::get('/logout', [CreateController::class, 'webLogout']);
    Route::post('/logout', [CreateController::class, 'logout']);

    Route::post('/add_product', [CreateController::class, 'product']);

    Route::get('/view_product/{lang?}', [ViewController::class, 'lng_product']);

    Route::get('/lng_product', [ViewController::class, 'product']);

    Route::post('/get_product/{lang?}', [ViewController::class, 'lng_get_product']);

    Route::get('/products/pending/{product_code}', [ViewController::class, 'productPendingSummary']);

    Route::post('/lng_get_product', [ViewController::class, 'get_product']);

    Route::get('/category/{lang?}', [ViewController::class, 'lng_categories']);

    Route::get('/lng_category', [ViewController::class, 'categories']);

    Route::get('/subcategory/{category?}/{lang?}', [ViewController::class, 'lng_sub_categories']);

    Route::get('/lng_subcategory/{category?}', [ViewController::class, 'sub_categories']);

    Route::post('/add_order', [CreateController::class, 'orders']);

    Route::get('/view_order', [ViewController::class, 'orders']);

    Route::post('/update_order/{id?}', [UpdateController::class, 'edit_order']);

    Route::post('/complete_order/{id?}', [UpdateController::class, 'complete_order']);

    Route::post('/complete_order_stock/{id?}', [UpdateController::class, 'complete_order_stock']);

    Route::post('/cancel_order/{id?}', [UpdateController::class, 'cancel_order']);

    // Route::post('/split_order/{id?}', [UpdateController::class, 'splitOrder']);
    
    Route::post('/view_user_order/{id?}', [ViewController::class, 'orders_user_id']);
    
    Route::post('/add_order_items', [CreateController::class, 'orders_items']);
    
    Route::get('/view_order_items', [ViewController::class, 'order_items']);
    
    Route::get('/view_items_orders/{id}', [ViewController::class, 'orders_items_order_id']);

    Route::get('/pending_orders', [ViewController::class, 'getPendingOrders']);

    Route::post('/add_cart', [CreateController::class, 'cart']);

    Route::get('/view_cart', [ViewController::class, 'cart']);

    Route::get('/view_cart_user/{id?}', [ViewController::class, 'cart_user']);
    
    Route::patch('/update_cart/{id?}', [UpdateController::class, 'cart']);

    Route::delete('/delete_cart/{id}', [DeleteController::class, 'cart']);

    Route::post('/add_counter', [CreateController::class, 'counter']);

    Route::get('/view_counter', [ViewController::class, 'counter']);

    Route::get('/dashboard', [ViewController::class, 'dashboard_details']);

    Route::get('/generate_order_invoice/{orderId}', [InvoiceController::class, 'generateorderInvoice']);

    Route::get('/download_packing_slip/{orderId}/{is_edited}/{is_download}', [InvoiceController::class, 'generatePackingSlip']);

    Route::get('/generate_invoice/{invoiceId}', [InvoiceController::class, 'generateInvoice']);

    Route::get('/return_order/{orderId}', [ViewController::class, 'return_order']);

    Route::post('/add_invoice', [CreateController::class, 'make_invoice']);

    Route::get('/spare_product/{lang?}/{code?}', [ViewController::class, 'get_spares']);

    Route::post('/spare_product/{lang?}/{code?}', [ViewController::class, 'get_spares_new']);

    Route::delete('/delete_user/{id}', [DeleteController::class, 'user']);

    Route::post('/upload_product', [CreateController::class, 'uploadProductsImage']);

    Route::post('/make_stock_cart', [CreateController::class, 'stock_cart_store']);
    Route::get('/view_stock_cart/{id?}', [ViewController::class, 'stock_cart_index']);
    Route::post('/update_stock_cart/{id}', [UpdateController::class, 'stock_cart_update']);
    Route::delete('/delete_stock_cart/{id}', [DeleteController::class, 'stock_cart_destroy']);

    // Route::get('/generate-stock-report', [StockController::class, 'generateStockReport']);

    Route::post('/make_stock_order', [CreateController::class, 'createStockOrder']);
    Route::get('/view_stock_order/{id?}', [ViewController::class, 'fetchStockOrder']);
    Route::post('/update_stock_order/{id}', [UpdateController::class, 'updateStockOrder']);
    Route::delete('/delete_stock_order/{id}', [DeleteController::class, 'deleteStockOrder']);

    Route::get('/import_godown', [CsvImportController::class, 'importCsv_godown']);

    Route::get('/godown/{productCode?}', [ViewController::class, 'get_godown']);

    Route::post('/stock_product', [ViewController::class, 'product_stock_details']);

    Route::get('/generate_stock_order_invoice/{orderId}', [InvoiceControllerZP::class, 'generatestockorderInvoice']);

    Route::post('/pricelist', [InvoiceController::class, 'price_list']);
    
    Route::prefix('special_rate')->group(function () {
        Route::post('/create', [CreateController::class, 'createSpecialRate']);
        Route::get('/{id?}', [ViewController::class, 'fetchSpecialRate']);
        Route::post('/update/{id?}', [UpdateController::class, 'updateSpecialRate']);
        Route::delete('/delete/{id?}', [DeleteController::class, 'deleteSpecialRate']);
    });

    Route::prefix('job_card')->group(function () {
        Route::post('/create', [CreateController::class, 'createJobCard']);
        Route::get('/{id?}', [ViewController::class, 'fetchJobCard']);
        Route::post('/update/{id?}', [UpdateController::class, 'updateJobCard']);
        Route::delete('/delete/{id?}', [DeleteController::class, 'deleteJobCard']);
    });

    Route::post('/reports/top_selling_products', [ReportController::class, 'topSellingProducts']);
    Route::get('/broadcast/pamplet/{user_id}', [ReportController::class, 'generate_pamplet_pdf']);
});

Route::prefix('user')->middleware(['auth:sanctum', GetUserRole::class . ':user'])->group(function () {

    Route::get('/get_details', [ViewController::class, 'user_details']);

    Route::post('/update_user', [UpdateController::class, 'user']);

    Route::get('/category/{lang?}', [ViewController::class, 'lng_categories']);

    Route::get('/subcategory/{category?}/{lang?}', [ViewController::class, 'lng_sub_categories']);

    Route::post('/get_product/{lang?}', [ViewController::class, 'lng_get_product']);

    Route::get('/spare_product/{lang?}/{code?}', [ViewController::class, 'get_spares']);

    Route::post('/spare_product/{lang?}/{code?}', [ViewController::class, 'get_spares_new']);

    Route::post('/make_stock_cart', [CreateController::class, 'stock_cart_store']);
    Route::get('/view_stock_cart/{id?}', [ViewController::class, 'stock_cart_index']);
    Route::post('/update_stock_cart/{id}', [UpdateController::class, 'stock_cart_update']);
    Route::delete('/delete_stock_cart/{id}', [DeleteController::class, 'stock_cart_destroy']);

    Route::post('/make_stock_order', [CreateController::class, 'createStockOrder']);
    Route::get('/view_stock_order/{id?}', [ViewController::class, 'fetchStockOrder']);
    Route::post('/update_stock_order/{id}', [UpdateController::class, 'updateStockOrder']);
    Route::delete('/delete_stock_order/{id}', [DeleteController::class, 'deleteStockOrder']);


    Route::get('/logout', [CreateController::class, 'logout']);

    // Route::get('/cart_user', [ViewController::class, 'cart_user']);
    Route::post('/add_cart', [CreateController::class, 'cart']);

    Route::get('/view_cart_user', [ViewController::class, 'cart_user']);

    Route::patch('/update_cart/{id}', [UpdateController::class, 'cart']);

    Route::delete('/delete_cart/{id}', [DeleteController::class, 'cart']);

    Route::post('/add_order', [CreateController::class, 'orders']);

    // Route::post('/add_new_order', [CreateController::class, 'new_orders']);

    Route::post('/view_user_order', [ViewController::class, 'orders_user_id']);

    Route::delete('/delete_user/{id?}', [DeleteController::class, 'user']);

    // Route::get('/generate_invoice/{userId}/{orderId}', [InvoiceController::class, 'generateInvoice']);
    // Route::get('/generate_invoice/{orderId}', [InvoiceController::class, 'generateInvoice']);

});

Route::get('/generate_order_invoice/{orderId}', [InvoiceController::class, 'generateorderInvoice']);
Route::get('/generate_packing_slip/{orderId}', [InvoiceController::class, 'generatePackingSlip']);
Route::get('/generate_packing_slip_all', [InvoiceController::class, 'generatePackingSlipsForAllOrders']);
Route::get('/pending_order', [ReportController::class, 'pendingOrderReport']);


Route::get('/fetch_products', [CsvImportController::class, 'importProduct']);
Route::get('/fetch_users', [CsvImportController::class, 'importUser']);
Route::get('/fetch_categories', [CsvImportController::class, 'importCategory']);

Route::post('/login/{otp?}', [CreateController::class, 'login']);
Route::post('/register_user', [CreateController::class, 'user']);
Route::post('/get_otp', [UpdateController::class, 'generate_otp']);