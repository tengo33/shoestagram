<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('SHOESTAGRAM_HISTORICAL_IMPORT_LIBRARY', true);
require_once __DIR__ . '/validate_historical_dataset.php';

function historical_import_execute($connection, $sql, $types = '', $params = array())
{
    if (!database_execute($connection, $sql, $types, $params)) {
        throw new RuntimeException('A database write failed: ' . mysqli_error($connection));
    }
}

function historical_import_payment_method($sourceMethod)
{
    return strcasecmp(trim((string) $sourceMethod), 'GCash') === 0
        ? 'Online Payment'
        : 'In-Store Payment';
}

function historical_import_clear_forecast_cache($productIds)
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'forecast-cache';
    $cleared = 0;
    foreach (array_unique(array_map('intval', (array) $productIds)) as $productId) {
        if ($productId <= 0) {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'product-' . $productId . '.json';
        if (is_file($path) && @unlink($path)) {
            $cleared++;
        }
    }
    $backoffPath = $directory . DIRECTORY_SEPARATOR . 'service-backoff.json';
    if (is_file($backoffPath)) {
        @unlink($backoffPath);
    }
    return $cleared;
}

function historical_import_existing_result($connection, $batch)
{
    $batchId = (int) ($batch['id'] ?? 0);
    $sales = database_fetch_one(
        $connection,
        "SELECT COUNT(*) AS records, COALESCE(SUM(quantity), 0) AS units, COALESCE(SUM(total_amount), 0) AS revenue
         FROM historical_sales
         WHERE import_batch_id = ? AND provenance = 'historical_import'",
        'i',
        array($batchId)
    );
    $restocks = database_fetch_one(
        $connection,
        'SELECT COUNT(*) AS records FROM inventory_logs WHERE import_batch_id = ?',
        'i',
        array($batchId)
    );

    return array(
        'ok' => true,
        'mode' => 'commit',
        'status' => 'already_imported',
        'batch_id' => $batchId,
        'batch_key' => (string) ($batch['batch_key'] ?? ''),
        'inserted' => 0,
        'skipped_existing' => (int) ($sales['records'] ?? 0),
        'invalid_transactions' => 0,
        'failed' => 0,
        'stored_records' => (int) ($sales['records'] ?? 0),
        'stored_units' => (int) ($sales['units'] ?? 0),
        'stored_revenue' => round((float) ($sales['revenue'] ?? 0), 2),
        'restock_records' => (int) ($restocks['records'] ?? 0),
        'message' => 'This exact workbook was already imported; no duplicate records were created.',
    );
}

function historical_import_run($workbookPath, $connection, $commit)
{
    $validation = historical_dataset_validate($workbookPath, $connection);
    $blockingErrors = array_values(array_filter($validation['errors'], static function ($error) {
        return ($error['code'] ?? '') !== 'inventory_reconciliation';
    }));
    $invalidInventoryIds = array_map('intval', $validation['inventory_validation']['invalid_product_ids'] ?? array());

    if ($blockingErrors) {
        return array(
            'ok' => false,
            'mode' => $commit ? 'commit' : 'preview',
            'status' => 'validation_failed',
            'blocking_errors' => $blockingErrors,
            'inventory_rows_skipped' => count($invalidInventoryIds),
        );
    }

    $preview = array(
        'ok' => true,
        'mode' => $commit ? 'commit' : 'preview',
        'status' => $commit ? 'ready' : 'validated_with_partial_inventory_skip',
        'workbook_sha256' => $validation['workbook_sha256'],
        'coverage' => $validation['dataset_coverage'],
        'transaction_date_range' => $validation['date_range'],
        'transactions' => count($validation['transactions']),
        'units' => (int) ($validation['totals']['units'] ?? 0),
        'revenue' => round((float) ($validation['totals']['revenue'] ?? 0), 2),
        'walk_in' => array(
            'transactions' => (int) ($validation['totals']['walk_in_transactions'] ?? 0),
            'units' => (int) ($validation['totals']['walk_in_units'] ?? 0),
            'revenue' => round((float) ($validation['totals']['walk_in_revenue'] ?? 0), 2),
        ),
        'online' => array(
            'transactions' => (int) ($validation['totals']['online_transactions'] ?? 0),
            'units' => (int) ($validation['totals']['online_units'] ?? 0),
            'revenue' => round((float) ($validation['totals']['online_revenue'] ?? 0), 2),
        ),
        'inventory_rows_valid' => (int) ($validation['inventory_validation']['valid_rows'] ?? 0),
        'inventory_rows_skipped' => count($invalidInventoryIds),
        'invalid_inventory_product_ids' => $invalidInventoryIds,
        'restock_records' => count($validation['restocks']),
        'verification_status' => 'unverified_generated',
    );

    if (!$commit) {
        return $preview;
    }

    $existingBatch = database_fetch_one(
        $connection,
        'SELECT * FROM historical_import_batches WHERE workbook_sha256 = ? LIMIT 1',
        's',
        array($validation['workbook_sha256'])
    );
    if ($existingBatch) {
        return historical_import_existing_result($connection, $existingBatch);
    }

    $batchKey = 'HIST-2026-' . strtoupper(substr((string) $validation['workbook_sha256'], 0, 12));
    $coverageStart = (string) ($validation['dataset_coverage']['from'] ?? $validation['date_range']['from']);
    $coverageEnd = (string) ($validation['dataset_coverage']['to'] ?? $validation['date_range']['to']);
    $actor = array('name' => 'Shoestagram Dataset Import', 'role' => 'system', 'email' => '');
    $batchId = 0;
    $inserted = 0;
    $restocksInserted = 0;
    $inventoryApplied = 0;

    mysqli_begin_transaction($connection);
    try {
        historical_import_execute(
            $connection,
            "INSERT INTO historical_import_batches
             (batch_key, workbook_name, workbook_sha256, coverage_start, coverage_end, inventory_as_of,
              verification_status, import_status, transaction_records, transaction_units, transaction_revenue,
              inventory_rows_applied, inventory_rows_skipped, restock_records, source_note, created_by, created_by_name)
             VALUES (?, ?, ?, ?, ?, ?, 'unverified_generated', 'completed', ?, ?, ?, ?, ?, ?, ?, NULL, ?)",
            'ssssssiidiiiss',
            array(
                $batchKey,
                basename((string) $workbookPath),
                $validation['workbook_sha256'],
                $coverageStart,
                $coverageEnd,
                $coverageEnd,
                count($validation['transactions']),
                (int) $validation['totals']['units'],
                (float) $validation['totals']['revenue'],
                (int) $validation['inventory_validation']['valid_rows'],
                count($invalidInventoryIds),
                count($validation['restocks']),
                'Workbook Data Notes identify the transactions and restocks as generated from the catalog and not independently verified against client receipts.',
                'Shoestagram Dataset Import',
            )
        );
        $batchId = (int) mysqli_insert_id($connection);
        if ($batchId <= 0) {
            throw new RuntimeException('The import batch could not be initialized.');
        }

        $productTotals = array();
        foreach ($validation['transactions'] as $transaction) {
            $productId = (int) $transaction['product_id'];
            if (!isset($productTotals[$productId])) {
                $productTotals[$productId] = array('records' => 0, 'units' => 0, 'revenue' => 0.0);
            }
            $productTotals[$productId]['records']++;
            $productTotals[$productId]['units'] += (int) $transaction['quantity'];
            $productTotals[$productId]['revenue'] += (float) $transaction['total_amount'];

            $mappedPaymentMethod = historical_import_payment_method($transaction['payment_method']);
            historical_import_execute(
                $connection,
                "INSERT INTO historical_sales
                 (external_reference, product_id, sale_date, quantity, unit_price, total_amount, transaction_type,
                  payment_method, original_payment_method, size_standard, size, source, provenance, import_batch_id,
                  is_client_verified, notes, created_by, created_by_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'US', ?, 'manual', 'historical_import', ?, 0, ?, NULL, ?)",
                'sisiddssssiss',
                array(
                    $transaction['sale_id'],
                    $productId,
                    $transaction['sale_date'],
                    (int) $transaction['quantity'],
                    (float) $transaction['unit_price'],
                    (float) $transaction['total_amount'],
                    $transaction['transaction_type'],
                    $mappedPaymentMethod,
                    $transaction['payment_method'],
                    $transaction['size'],
                    $batchId,
                    'Initialized historical dataset; pending verification against client receipts/accounting records.',
                    'Shoestagram Dataset Import',
                )
            );
            $inserted++;
        }

        $inventoryByProduct = array();
        foreach ($validation['inventory'] as $inventoryRow) {
            $inventoryByProduct[(int) $inventoryRow['product_id']] = $inventoryRow;
        }
        $invalidInventoryMap = array_fill_keys($invalidInventoryIds, true);

        foreach ($validation['forecast_coverage'] as $productId => $historyCoverage) {
            $productId = (int) $productId;
            $inventoryRow = $inventoryByProduct[$productId] ?? array('ending_stock' => 0);
            $inventoryReconciled = !isset($invalidInventoryMap[$productId]);
            $totals = $productTotals[$productId] ?? array('records' => 0, 'units' => 0, 'revenue' => 0.0);
            historical_import_execute(
                $connection,
                "INSERT INTO historical_import_products
                 (import_batch_id, product_id, history_start, history_end, transaction_records, units_sold,
                  revenue, ending_stock, inventory_reconciled, inventory_applied)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'iissiidiii',
                array(
                    $batchId,
                    $productId,
                    $historyCoverage['from'],
                    $historyCoverage['to'],
                    (int) $totals['records'],
                    (int) $totals['units'],
                    (float) $totals['revenue'],
                    (int) ($inventoryRow['ending_stock'] ?? 0),
                    $inventoryReconciled ? 1 : 0,
                    $inventoryReconciled ? 1 : 0,
                )
            );

            if ($inventoryReconciled) {
                historical_import_execute(
                    $connection,
                    'UPDATE products SET base_stock = ? WHERE id = ?',
                    'ii',
                    array((int) $inventoryRow['ending_stock'], $productId)
                );
                $inventoryApplied++;
            }
        }

        foreach ($validation['restocks'] as $restock) {
            $endingStock = (int) ($inventoryByProduct[(int) $restock['product_id']]['ending_stock'] ?? 0);
            historical_import_execute(
                $connection,
                "INSERT INTO inventory_logs
                 (product_id, actor_name, actor_email, actor_role, action_type, external_reference, import_batch_id,
                  event_date, affects_live_stock, stock_before, stock_after, quantity_change, note)
                 VALUES (?, 'Shoestagram Dataset Import', '', 'system', 'historical_restock_import', ?, ?, ?, 0, ?, ?, ?, ?)",
                'isisiiis',
                array(
                    (int) $restock['product_id'],
                    $restock['restock_id'],
                    $batchId,
                    $restock['event_date'],
                    $endingStock,
                    $endingStock,
                    (int) $restock['quantity'],
                    'Historical reference only; does not change current stock. Source type: ' . $restock['type'],
                )
            );
            $restocksInserted++;
        }

        $activityDescription = sprintf(
            'Initialized historical dataset batch %s: %d sales, %d units, PHP %.2f revenue, %d restock references, %d inventory rows applied, %d inventory rows skipped after reconciliation failure.',
            $batchKey,
            $inserted,
            (int) $validation['totals']['units'],
            (float) $validation['totals']['revenue'],
            $restocksInserted,
            $inventoryApplied,
            count($invalidInventoryIds)
        );
        if (!database_log_activity($connection, $actor, 'historical_dataset_import', $activityDescription, 'Success')) {
            throw new RuntimeException('The import activity log could not be written.');
        }

        mysqli_commit($connection);
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        return array(
            'ok' => false,
            'mode' => 'commit',
            'status' => 'rolled_back',
            'inserted' => 0,
            'skipped_existing' => 0,
            'invalid_transactions' => 0,
            'failed' => count($validation['transactions']),
            'safe_error' => $exception->getMessage(),
        );
    }

    $cacheCleared = historical_import_clear_forecast_cache(array_keys($validation['forecast_coverage']));
    return array_merge($preview, array(
        'status' => 'imported',
        'batch_id' => $batchId,
        'batch_key' => $batchKey,
        'inserted' => $inserted,
        'skipped_existing' => 0,
        'invalid_transactions' => 0,
        'failed' => 0,
        'inventory_rows_applied' => $inventoryApplied,
        'restock_records_inserted' => $restocksInserted,
        'forecast_cache_files_cleared' => $cacheCleared,
    ));
}

$workbookPath = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);
if ($workbookPath === '' || strpos($workbookPath, '--') === 0) {
    fwrite(STDERR, "Usage: php scripts/import_historical_dataset.php <workbook.xlsx> [--commit]\n");
    exit(2);
}

try {
    $result = historical_import_run($workbookPath, $conn, $commit);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Historical import failed safely: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
