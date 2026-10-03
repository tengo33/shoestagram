<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/store-data.php';

function historical_import_verify_close($left, $right, $tolerance = 0.005)
{
    return abs((float) $left - (float) $right) <= $tolerance;
}

$checks = array();
$details = array();
$batch = store_latest_historical_import_batch();
$batchId = (int) ($batch['id'] ?? 0);

$sales = database_fetch_one(
    $conn,
    "SELECT COUNT(*) AS records,
            COUNT(DISTINCT external_reference) AS distinct_references,
            COALESCE(SUM(quantity), 0) AS units,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(ABS(total_amount - (quantity * unit_price))), 0) AS formula_difference,
            MIN(sale_date) AS earliest_date,
            MAX(sale_date) AS latest_date
     FROM historical_sales
     WHERE import_batch_id = ? AND provenance = 'historical_import'",
    'i',
    array($batchId)
);
$channels = database_fetch_all(
    $conn,
    "SELECT transaction_type, COUNT(*) AS records, SUM(quantity) AS units, SUM(total_amount) AS revenue
     FROM historical_sales
     WHERE import_batch_id = ? AND provenance = 'historical_import'
     GROUP BY transaction_type",
    'i',
    array($batchId)
);
$channelMap = array();
foreach ($channels as $channel) {
    $channelMap[$channel['transaction_type']] = $channel;
}

$checks['batch_exists'] = $batchId > 0 && ($batch['import_status'] ?? '') === 'completed';
$checks['sales_count'] = (int) ($sales['records'] ?? 0) === 795;
$checks['external_ids_unique'] = (int) ($sales['distinct_references'] ?? 0) === (int) ($sales['records'] ?? 0);
$checks['units_total'] = (int) ($sales['units'] ?? 0) === 852;
$checks['revenue_total'] = historical_import_verify_close($sales['revenue'] ?? 0, 1101800);
$checks['sale_formula'] = historical_import_verify_close($sales['formula_difference'] ?? 0, 0);
$checks['channel_totals'] = (int) ($channelMap['walk_in']['records'] ?? 0) === 582
    && (int) ($channelMap['walk_in']['units'] ?? 0) === 621
    && historical_import_verify_close($channelMap['walk_in']['revenue'] ?? 0, 782450)
    && (int) ($channelMap['online']['records'] ?? 0) === 213
    && (int) ($channelMap['online']['units'] ?? 0) === 231
    && historical_import_verify_close($channelMap['online']['revenue'] ?? 0, 319350);

$productImportRows = database_fetch_all(
    $conn,
    'SELECT * FROM historical_import_products WHERE import_batch_id = ? ORDER BY product_id',
    'i',
    array($batchId)
);
$checks['product_import_rows'] = count($productImportRows) === 59;
$checks['inventory_validation_counts'] = count(array_filter($productImportRows, static function ($row) {
    return !empty($row['inventory_reconciled']);
})) === 46 && count(array_filter($productImportRows, static function ($row) {
    return empty($row['inventory_reconciled']);
})) === 13;

$products = get_store_products();
$productsById = array();
foreach ($products as $product) {
    $productsById[(int) $product['id']] = $product;
}
$stockMismatches = array();
foreach ($productImportRows as $row) {
    $productId = (int) $row['product_id'];
    if (!isset($productsById[$productId]) || (int) $productsById[$productId]['stock'] !== (int) $row['ending_stock']) {
        $stockMismatches[] = $productId;
    }
}
$checks['live_stock_matches_import_snapshot'] = !$stockMismatches;

$transactions = get_admin_dynamic_transactions(0);
$analytics = array('records' => 0, 'units' => 0, 'revenue' => 0.0, 'excluded_pre_import_system_sales' => 0);
foreach ($transactions as $transaction) {
    if (($transaction['status'] ?? '') !== 'Completed') {
        continue;
    }
    if (!store_transaction_counts_for_analytics($transaction)) {
        if (($transaction['source'] ?? '') === 'system') {
            $analytics['excluded_pre_import_system_sales']++;
        }
        continue;
    }
    $analytics['records']++;
    $analytics['units'] += (int) ($transaction['quantity'] ?? 0);
    $analytics['revenue'] += (float) ($transaction['amount'] ?? 0);
}
$checks['analytics_no_double_count'] = $analytics['records'] === 795
    && $analytics['units'] === 852
    && historical_import_verify_close($analytics['revenue'], 1101800)
    && $analytics['excluded_pre_import_system_sales'] === 14;

$forecast = array('products' => 0, 'sample_products' => 0, 'over_90_points' => array(), 'import_products' => 0, 'mixed_sample_actual' => array());
foreach ($products as $product) {
    $history = store_get_product_forecast_history($product);
    $forecast['products']++;
    $source = (string) ($history['data_source'] ?? '');
    if (strpos($source, 'Sample / Development Data') !== false) {
        $forecast['sample_products']++;
        if (!empty($history['uses_actual_history'])) {
            $forecast['mixed_sample_actual'][] = (int) $product['id'];
        }
    }
    if (strpos($source, 'Historical Import') !== false) {
        $forecast['import_products']++;
    }
    if (count((array) ($history['series'] ?? array())) > 90) {
        $forecast['over_90_points'][] = (int) $product['id'];
    }
}
$checks['forecast_history_source'] = $forecast['products'] === 59
    && $forecast['import_products'] === 59
    && $forecast['sample_products'] === 0
    && !$forecast['mixed_sample_actual']
    && !$forecast['over_90_points'];

$restocks = database_fetch_one(
    $conn,
    'SELECT COUNT(*) AS records, COUNT(DISTINCT external_reference) AS distinct_references, SUM(affects_live_stock) AS live_effects FROM inventory_logs WHERE import_batch_id = ?',
    'i',
    array($batchId)
);
$checks['restock_history_is_reference_only'] = (int) ($restocks['records'] ?? 0) === 72
    && (int) ($restocks['distinct_references'] ?? 0) === 72
    && (int) ($restocks['live_effects'] ?? 0) === 0;

$activities = database_fetch_one(
    $conn,
    "SELECT COUNT(*) AS records FROM activity_logs WHERE activity_type = 'historical_dataset_import' AND description LIKE ?",
    's',
    array('%' . ($batch['batch_key'] ?? '') . '%')
);
$checks['single_activity_log'] = (int) ($activities['records'] ?? 0) === 1;

$sampleSale = database_fetch_one(
    $conn,
    "SELECT * FROM historical_sales WHERE import_batch_id = ? AND provenance = 'historical_import' ORDER BY id LIMIT 1",
    'i',
    array($batchId)
);
$crud = array('edit' => false, 'delete' => false, 'rollback_restored' => false);
if ($sampleSale) {
    mysqli_begin_transaction($conn);
    try {
        $editResult = store_save_manual_sale(array(
            'product_id' => $sampleSale['product_id'],
            'sale_date' => $sampleSale['sale_date'],
            'quantity' => $sampleSale['quantity'],
            'unit_price' => $sampleSale['unit_price'],
            'transaction_type' => $sampleSale['transaction_type'],
            'payment_method' => $sampleSale['payment_method'],
            'size_standard' => $sampleSale['size_standard'],
            'size' => $sampleSale['size'],
            'notes' => $sampleSale['notes'],
        ), (int) $sampleSale['id'], array('name' => 'Verification'));
        $crud['edit'] = !empty($editResult['ok']);
        $crud['delete'] = store_delete_manual_sale((int) $sampleSale['id']);
        mysqli_rollback($conn);
        $crud['rollback_restored'] = (bool) store_get_manual_sale((int) $sampleSale['id']);
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
    }
}
$checks['historical_sales_edit_delete_paths'] = $crud['edit'] && $crud['delete'] && $crud['rollback_restored'];

$beforeOrder = array('created_at' => (string) ($batch['coverage_end'] ?? '') . 'T12:00:00+08:00');
$afterDate = (new DateTimeImmutable((string) ($batch['coverage_end'] ?? 'today')))->modify('+1 day')->format('Y-m-d');
$afterOrder = array('created_at' => $afterDate . 'T12:00:00+08:00');
$checks['inventory_snapshot_boundary'] = !store_state_order_affects_live_inventory($beforeOrder)
    && store_state_order_affects_live_inventory($afterOrder)
    && !store_system_order_is_after_historical_import($beforeOrder)
    && store_system_order_is_after_historical_import($afterOrder);

$manualSalesSource = (string) @file_get_contents(dirname(__DIR__) . '/admin/manual-sales.php');
$checks['admin_only_management'] = strpos($manualSalesSource, "backoffice_require_role(array('admin'))") !== false;

$adminPage = array(
    'rendered' => false,
    'has_warning' => true,
    'has_import_label' => false,
    'has_verification_label' => false,
    'forecasting_rendered' => false,
    'forecasting_has_warning' => true,
    'forecasting_has_import_source' => false,
    'forecasting_has_fallback' => false,
);
$salesVerificationSummary = $sales;
$forecastVerificationSummary = $forecast;
$adminUser = database_fetch_one($conn, "SELECT id, fullname, email, role FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
if ($adminUser) {
    $testSessionFile = '';
    if (session_status() === PHP_SESSION_NONE) {
        $sessionDirectory = dirname(__DIR__) . '/database/runtime/test-sessions';
        if (!is_dir($sessionDirectory)) {
            @mkdir($sessionDirectory, 0755, true);
        }
        session_save_path($sessionDirectory);
        session_id('historical-import-verification');
        @session_start();
        $testSessionFile = $sessionDirectory . DIRECTORY_SEPARATOR . 'sess_' . session_id();
    }
    $_SESSION['user_id'] = (int) $adminUser['id'];
    $_SESSION['user_name'] = (string) $adminUser['fullname'];
    $_SESSION['user_email'] = (string) $adminUser['email'];
    $_SESSION['user_role'] = 'admin';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = array('month' => '2026-09');
    $workingDirectory = getcwd();
    ob_start();
    chdir(dirname(__DIR__) . '/admin');
    include dirname(__DIR__) . '/admin/manual-sales.php';
    $html = (string) ob_get_clean();
    chdir($workingDirectory);
    $adminPage = array(
        'rendered' => strlen($html) > 1000,
        'has_warning' => preg_match('/Warning:|Notice:|Undefined (array key|index)|Fatal error/i', $html) === 1,
        'has_import_label' => strpos($html, 'Initialized Historical Import') !== false,
        'has_verification_label' => strpos($html, 'Pending client verification') !== false,
        'forecasting_rendered' => false,
        'forecasting_has_warning' => true,
        'forecasting_has_import_source' => false,
        'forecasting_has_fallback' => false,
    );
    $_GET = array();
    ob_start();
    chdir(dirname(__DIR__) . '/admin');
    include dirname(__DIR__) . '/admin/forecasting.php';
    $forecastingHtml = (string) ob_get_clean();
    chdir($workingDirectory);
    $adminPage['forecasting_rendered'] = strlen($forecastingHtml) > 1000;
    $adminPage['forecasting_has_warning'] = preg_match('/Warning:|Notice:|Undefined (array key|index)|Fatal error/i', $forecastingHtml) === 1;
    $adminPage['forecasting_has_import_source'] = strpos($forecastingHtml, 'Historical Import') !== false;
    $adminPage['forecasting_has_fallback'] = strpos($forecastingHtml, 'Fallback Forecast') !== false;
    $sales = $salesVerificationSummary;
    $forecast = $forecastVerificationSummary;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if ($testSessionFile !== '' && is_file($testSessionFile)) {
        @unlink($testSessionFile);
    }
}
$checks['admin_historical_sales_page'] = $adminPage['rendered']
    && !$adminPage['has_warning']
    && $adminPage['has_import_label']
    && $adminPage['has_verification_label'];
$checks['admin_forecasting_page'] = $adminPage['forecasting_rendered']
    && !$adminPage['forecasting_has_warning']
    && $adminPage['forecasting_has_import_source']
    && $adminPage['forecasting_has_fallback'];

$details['batch'] = array(
    'id' => $batchId,
    'key' => (string) ($batch['batch_key'] ?? ''),
    'coverage_start' => (string) ($batch['coverage_start'] ?? ''),
    'coverage_end' => (string) ($batch['coverage_end'] ?? ''),
    'inventory_as_of' => (string) ($batch['inventory_as_of'] ?? ''),
    'verification_status' => (string) ($batch['verification_status'] ?? ''),
);
$details['sales'] = $sales;
$details['channels'] = $channelMap;
$details['analytics'] = $analytics;
$details['forecast'] = $forecast;
$details['inventory_stock_mismatches'] = $stockMismatches;
$details['crud_transaction_rollback_test'] = $crud;
$details['admin_page'] = $adminPage;

$failed = array_keys(array_filter($checks, static function ($passed) {
    return !$passed;
}));
echo json_encode(array(
    'ok' => !$failed,
    'checks' => $checks,
    'failed_checks' => $failed,
    'details' => $details,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed ? 1 : 0);
