<?php

if (PHP_SAPI !== 'cli' && !defined('SHOESTAGRAM_HISTORICAL_IMPORT_LIBRARY')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/xlsx_reader.php';
require_once dirname(__DIR__) . '/database/config.php';
require_once dirname(__DIR__) . '/database/store_state.php';

function historical_dataset_excel_date($value)
{
    $value = trim((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : '';
    }
    if (!is_numeric($value)) {
        return '';
    }

    $days = (int) floor((float) $value);
    if ($days < 1 || $days > 2958465) {
        return '';
    }

    return (new DateTimeImmutable('1899-12-30'))->modify('+' . $days . ' days')->format('Y-m-d');
}

function historical_dataset_records($sheets, $name)
{
    return shoestagram_xlsx_rows_as_records($sheets[$name] ?? array());
}

function historical_dataset_text($value)
{
    return preg_replace('/\s+/', ' ', trim((string) $value));
}

function historical_dataset_sizes($value)
{
    $sizes = is_array($value) ? $value : explode(',', (string) $value);
    return array_values(array_filter(array_map(static function ($size) {
        return historical_dataset_text($size);
    }, $sizes), static function ($size) {
        return $size !== '';
    }));
}

function historical_dataset_close($left, $right, $tolerance = 0.005)
{
    return abs((float) $left - (float) $right) <= $tolerance;
}

function historical_dataset_add_error(&$report, $code, $message, $context = array())
{
    $report['errors'][] = array(
        'code' => (string) $code,
        'message' => (string) $message,
        'context' => is_array($context) ? $context : array(),
    );
}

function historical_dataset_validate($workbookPath, $connection)
{
    $sheets = shoestagram_xlsx_read($workbookPath);
    $expectedSheets = array(
        'Data Notes', 'Product Catalog', 'Transactions', 'Daily Sales', 'Weekly Sales',
        'Monthly Sales', 'Product Summary', 'Walk-in vs Online', 'Inventory', 'Restocks',
        'Forecasting History',
    );
    $report = array(
        'ok' => true,
        'workbook' => basename((string) $workbookPath),
        'workbook_sha256' => hash_file('sha256', $workbookPath),
        'sheet_names' => array_keys($sheets),
        'missing_sheets' => array_values(array_diff($expectedSheets, array_keys($sheets))),
        'errors' => array(),
        'warnings' => array(),
        'counts' => array(),
        'totals' => array(),
        'date_range' => array('from' => '', 'to' => ''),
        'dataset_coverage' => array('from' => '', 'to' => ''),
        'distinct_values' => array(),
        'database_state' => array(),
        'transactions' => array(),
        'inventory' => array(),
        'restocks' => array(),
    );

    if ($report['missing_sheets']) {
        historical_dataset_add_error($report, 'missing_sheets', 'One or more required sheets are missing.', array('sheets' => $report['missing_sheets']));
    }

    $catalogRows = historical_dataset_records($sheets, 'Product Catalog');
    $transactionRows = historical_dataset_records($sheets, 'Transactions');
    $inventoryRows = historical_dataset_records($sheets, 'Inventory');
    $restockRows = historical_dataset_records($sheets, 'Restocks');
    $dailyRows = historical_dataset_records($sheets, 'Daily Sales');
    $weeklyRows = historical_dataset_records($sheets, 'Weekly Sales');
    $monthlyRows = historical_dataset_records($sheets, 'Monthly Sales');
    $productSummaryRows = historical_dataset_records($sheets, 'Product Summary');
    $channelRows = historical_dataset_records($sheets, 'Walk-in vs Online');
    $forecastRows = historical_dataset_records($sheets, 'Forecasting History');

    $report['counts'] = array(
        'catalog_products' => count($catalogRows),
        'transactions' => count($transactionRows),
        'inventory_products' => count($inventoryRows),
        'restocks' => count($restockRows),
        'daily_summary_rows' => count($dailyRows),
        'weekly_summary_rows' => count($weeklyRows),
        'monthly_summary_rows' => count($monthlyRows),
        'product_summary_rows' => count($productSummaryRows),
        'channel_summary_rows' => count($channelRows),
        'forecast_history_rows' => count($forecastRows),
    );

    $databaseRows = database_fetch_all($connection, 'SELECT id, name, brand, category, price, base_stock, sizes FROM products ORDER BY id');
    $databaseProducts = array();
    foreach ($databaseRows as $row) {
        $databaseProducts[(int) $row['id']] = $row;
    }
    $report['database_state']['product_count'] = count($databaseProducts);
    $report['database_state']['historical_sales'] = database_fetch_one(
        $connection,
        'SELECT COUNT(*) AS records, COALESCE(SUM(quantity), 0) AS units, COALESCE(SUM(total_amount), 0) AS revenue, MIN(sale_date) AS earliest_date, MAX(sale_date) AS latest_date FROM historical_sales'
    );

    $catalog = array();
    foreach ($catalogRows as $rowNumber => $row) {
        $productId = filter_var($row['Product ID'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || (int) $productId <= 0 || isset($catalog[(int) $productId])) {
            historical_dataset_add_error($report, 'catalog_product_id', 'Catalog Product ID is invalid or duplicated.', array('row' => $rowNumber + 2));
            continue;
        }
        $productId = (int) $productId;
        $catalog[$productId] = $row;
        $databaseProduct = $databaseProducts[$productId] ?? null;
        if (!$databaseProduct) {
            historical_dataset_add_error($report, 'product_not_found', 'Workbook product does not exist in the database.', array('product_id' => $productId));
            continue;
        }

        $comparisons = array(
            'name' => array($row['Product Name'] ?? '', $databaseProduct['name'] ?? ''),
            'brand' => array($row['Brand'] ?? '', $databaseProduct['brand'] ?? ''),
            'category' => array($row['Category'] ?? '', $databaseProduct['category'] ?? ''),
        );
        foreach ($comparisons as $field => $values) {
            if (strcasecmp(historical_dataset_text($values[0]), historical_dataset_text($values[1])) !== 0) {
                historical_dataset_add_error($report, 'product_' . $field . '_mismatch', 'Workbook and database product metadata do not match.', array('product_id' => $productId, 'field' => $field));
            }
        }
        if (!historical_dataset_close($row['Price'] ?? null, $databaseProduct['price'] ?? null)) {
            historical_dataset_add_error($report, 'product_price_mismatch', 'Workbook and database product prices do not match.', array('product_id' => $productId));
        }
        $databaseSizes = json_decode((string) ($databaseProduct['sizes'] ?? '[]'), true);
        if (historical_dataset_sizes($row['Sizes'] ?? '') !== historical_dataset_sizes(is_array($databaseSizes) ? $databaseSizes : array())) {
            historical_dataset_add_error($report, 'product_sizes_mismatch', 'Workbook and database product sizes do not match.', array('product_id' => $productId));
        }
    }
    foreach ($databaseProducts as $productId => $databaseProduct) {
        if (!isset($catalog[$productId])) {
            historical_dataset_add_error($report, 'database_product_missing_from_catalog', 'Database product is missing from workbook catalog.', array('product_id' => $productId));
        }
    }

    $saleIds = array();
    $transactions = array();
    $daily = array();
    $monthly = array();
    $byProduct = array();
    $dailyByProduct = array();
    $byChannel = array();
    $totals = array('transactions' => 0, 'units' => 0, 'revenue' => 0.0);
    $channels = array();
    $paymentMethods = array();
    $orderStatuses = array();
    $paymentStatuses = array();
    $dates = array();

    foreach ($transactionRows as $rowNumber => $row) {
        $rowLabel = $rowNumber + 2;
        $saleId = historical_dataset_text($row['Sale ID'] ?? '');
        $date = historical_dataset_excel_date($row['Date'] ?? '');
        $productId = filter_var($row['Product ID'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($row['Quantity'] ?? null, FILTER_VALIDATE_INT);
        $unitPrice = filter_var($row['Unit Price'] ?? null, FILTER_VALIDATE_FLOAT);
        $totalSale = filter_var($row['Total Sale'] ?? null, FILTER_VALIDATE_FLOAT);
        $channel = historical_dataset_text($row['Channel'] ?? '');
        $paymentMethod = historical_dataset_text($row['Payment Method'] ?? '');
        $orderStatus = historical_dataset_text($row['Order Status'] ?? '');
        $paymentStatus = historical_dataset_text($row['Payment Status'] ?? '');

        if ($saleId === '' || isset($saleIds[$saleId])) {
            historical_dataset_add_error($report, 'sale_id', 'Sale ID is blank or duplicated.', array('row' => $rowLabel, 'sale_id' => $saleId));
        } else {
            $saleIds[$saleId] = true;
        }
        if ($date === '') {
            historical_dataset_add_error($report, 'sale_date', 'Transaction date is invalid.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }
        if ($productId === false || !isset($catalog[(int) $productId]) || !isset($databaseProducts[(int) $productId])) {
            historical_dataset_add_error($report, 'sale_product', 'Transaction Product ID is not safely matched.', array('row' => $rowLabel, 'sale_id' => $saleId));
            continue;
        }
        $productId = (int) $productId;
        $catalogProduct = $catalog[$productId];
        foreach (array('Product Name', 'Brand', 'Category') as $field) {
            if (strcasecmp(historical_dataset_text($row[$field] ?? ''), historical_dataset_text($catalogProduct[$field] ?? '')) !== 0) {
                historical_dataset_add_error($report, 'sale_product_metadata', 'Transaction product metadata does not match the Product ID.', array('row' => $rowLabel, 'sale_id' => $saleId, 'field' => $field));
            }
        }
        if ($quantity === false || (int) $quantity <= 0) {
            historical_dataset_add_error($report, 'sale_quantity', 'Transaction quantity must be a positive integer.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }
        if ($unitPrice === false || (float) $unitPrice < 0 || !historical_dataset_close($unitPrice, $catalogProduct['Price'] ?? null)) {
            historical_dataset_add_error($report, 'sale_unit_price', 'Transaction unit price is invalid or does not match the catalog.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }
        if ($totalSale === false || $quantity === false || $unitPrice === false || !historical_dataset_close($totalSale, (int) $quantity * (float) $unitPrice)) {
            historical_dataset_add_error($report, 'sale_total', 'Transaction total does not equal quantity times unit price.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }
        if (!in_array($channel, array('Walk-in', 'Online'), true)) {
            historical_dataset_add_error($report, 'sale_channel', 'Transaction channel is unsupported.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }
        if ($orderStatus !== 'Completed' || $paymentStatus !== 'Paid') {
            historical_dataset_add_error($report, 'sale_status', 'Historical transactions must be Completed and Paid.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }
        $allowedSizes = historical_dataset_sizes($catalogProduct['Sizes'] ?? '');
        $size = historical_dataset_text($row['Size'] ?? '');
        if ($size === '' || !in_array($size, $allowedSizes, true)) {
            historical_dataset_add_error($report, 'sale_size', 'Transaction size is not allowed for its product.', array('row' => $rowLabel, 'sale_id' => $saleId));
        }

        $normalized = array(
            'sale_id' => $saleId,
            'sale_date' => $date,
            'product_id' => $productId,
            'product_name' => historical_dataset_text($row['Product Name'] ?? ''),
            'brand' => historical_dataset_text($row['Brand'] ?? ''),
            'category' => historical_dataset_text($row['Category'] ?? ''),
            'size' => $size,
            'quantity' => (int) $quantity,
            'unit_price' => round((float) $unitPrice, 2),
            'total_amount' => round((float) $totalSale, 2),
            'transaction_type' => $channel === 'Walk-in' ? 'walk_in' : 'online',
            'channel' => $channel,
            'payment_method' => $paymentMethod,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
        );
        $transactions[] = $normalized;
        $dates[] = $date;
        $channels[$channel] = true;
        $paymentMethods[$paymentMethod] = true;
        $orderStatuses[$orderStatus] = true;
        $paymentStatuses[$paymentStatus] = true;
        $totals['transactions']++;
        $totals['units'] += (int) $quantity;
        $totals['revenue'] += (float) $totalSale;

        if (!isset($daily[$date])) {
            $daily[$date] = array('transactions' => 0, 'units' => 0, 'revenue' => 0.0, 'walk_in_revenue' => 0.0, 'online_revenue' => 0.0);
        }
        $daily[$date]['transactions']++;
        $daily[$date]['units'] += (int) $quantity;
        $daily[$date]['revenue'] += (float) $totalSale;
        $daily[$date][$channel === 'Walk-in' ? 'walk_in_revenue' : 'online_revenue'] += (float) $totalSale;

        if (!isset($byProduct[$productId])) {
            $byProduct[$productId] = array('transactions' => 0, 'units' => 0, 'revenue' => 0.0, 'walk_in_revenue' => 0.0, 'online_revenue' => 0.0);
        }
        $byProduct[$productId]['transactions']++;
        $byProduct[$productId]['units'] += (int) $quantity;
        $byProduct[$productId]['revenue'] += (float) $totalSale;
        $byProduct[$productId][$channel === 'Walk-in' ? 'walk_in_revenue' : 'online_revenue'] += (float) $totalSale;
        $dailyByProduct[$date . ':' . $productId] = ($dailyByProduct[$date . ':' . $productId] ?? 0) + (int) $quantity;

        if (!isset($byChannel[$channel])) {
            $byChannel[$channel] = array('transactions' => 0, 'units' => 0, 'revenue' => 0.0);
        }
        $byChannel[$channel]['transactions']++;
        $byChannel[$channel]['units'] += (int) $quantity;
        $byChannel[$channel]['revenue'] += (float) $totalSale;
    }

    /* Rebuild monthly totals directly to keep the reconciliation logic obvious. */
    $monthly = array();
    foreach ($transactions as $transaction) {
        $month = substr($transaction['sale_date'], 0, 7);
        if (!isset($monthly[$month])) {
            $monthly[$month] = array('transactions' => 0, 'units' => 0, 'revenue' => 0.0, 'walk_in_revenue' => 0.0, 'online_revenue' => 0.0);
        }
        $monthly[$month]['transactions']++;
        $monthly[$month]['units'] += $transaction['quantity'];
        $monthly[$month]['revenue'] += $transaction['total_amount'];
        $monthly[$month][$transaction['transaction_type'] === 'walk_in' ? 'walk_in_revenue' : 'online_revenue'] += $transaction['total_amount'];
    }

    sort($dates);
    $report['date_range'] = array('from' => $dates ? reset($dates) : '', 'to' => $dates ? end($dates) : '');
    $report['totals'] = array(
        'transactions' => $totals['transactions'],
        'units' => $totals['units'],
        'revenue' => round($totals['revenue'], 2),
        'walk_in_transactions' => (int) ($byChannel['Walk-in']['transactions'] ?? 0),
        'walk_in_units' => (int) ($byChannel['Walk-in']['units'] ?? 0),
        'walk_in_revenue' => round((float) ($byChannel['Walk-in']['revenue'] ?? 0), 2),
        'online_transactions' => (int) ($byChannel['Online']['transactions'] ?? 0),
        'online_units' => (int) ($byChannel['Online']['units'] ?? 0),
        'online_revenue' => round((float) ($byChannel['Online']['revenue'] ?? 0), 2),
    );
    $report['distinct_values'] = array(
        'channels' => array_keys($channels),
        'payment_methods' => array_keys($paymentMethods),
        'order_statuses' => array_keys($orderStatuses),
        'payment_statuses' => array_keys($paymentStatuses),
    );

    $compareSummary = static function (&$report, $label, $actual, $expected, $context = array()) {
        foreach ($expected as $field => $expectedValue) {
            $actualValue = $actual[$field] ?? null;
            $matches = in_array($field, array('revenue', 'walk_in_revenue', 'online_revenue'), true)
                ? historical_dataset_close($actualValue, $expectedValue)
                : (int) $actualValue === (int) $expectedValue;
            if (!$matches) {
                historical_dataset_add_error($report, $label . '_mismatch', 'Validation summary does not match Transactions.', array_merge($context, array('field' => $field, 'expected' => $expectedValue, 'actual' => $actualValue)));
            }
        }
    };

    foreach ($dailyRows as $row) {
        $date = historical_dataset_excel_date($row['Date'] ?? '');
        $compareSummary($report, 'daily_summary', $daily[$date] ?? array(), array(
            'transactions' => (int) ($row['Transactions'] ?? 0),
            'units' => (int) ($row['Units Sold'] ?? 0),
            'revenue' => (float) ($row['Revenue'] ?? 0),
            'walk_in_revenue' => (float) ($row['Walk-in Revenue'] ?? 0),
            'online_revenue' => (float) ($row['Online Revenue'] ?? 0),
        ), array('date' => $date));
    }
    $dailyCoverageDates = array();
    foreach ($dailyRows as $row) {
        $dailyCoverageDates[] = historical_dataset_excel_date($row['Date'] ?? '');
    }
    $dailyCoverageDates = array_values(array_filter($dailyCoverageDates));
    sort($dailyCoverageDates);
    if ($dailyCoverageDates) {
        $coverageFrom = reset($dailyCoverageDates);
        $coverageTo = end($dailyCoverageDates);
        $expectedDailyRows = (int) (new DateTimeImmutable($coverageFrom))->diff(new DateTimeImmutable($coverageTo))->format('%a') + 1;
        if (count($dailyCoverageDates) !== $expectedDailyRows || count(array_unique($dailyCoverageDates)) !== count($dailyCoverageDates)) {
            historical_dataset_add_error($report, 'daily_coverage', 'Daily Sales is not a unique continuous daily series.');
        }
        $report['dataset_coverage'] = array('from' => $coverageFrom, 'to' => $coverageTo);
    }

    foreach ($monthlyRows as $row) {
        $month = substr(historical_dataset_excel_date($row['Month'] ?? ''), 0, 7);
        $compareSummary($report, 'monthly_summary', $monthly[$month] ?? array(), array(
            'transactions' => (int) ($row['Transactions'] ?? 0),
            'units' => (int) ($row['Units Sold'] ?? 0),
            'revenue' => (float) ($row['Revenue'] ?? 0),
            'walk_in_revenue' => (float) ($row['Walk-in Revenue'] ?? 0),
            'online_revenue' => (float) ($row['Online Revenue'] ?? 0),
        ), array('month' => $month));
    }
    if (count($monthlyRows) !== count($monthly)) {
        historical_dataset_add_error($report, 'monthly_row_count', 'Monthly Sales row count does not match transaction months.');
    }

    foreach ($weeklyRows as $row) {
        $from = historical_dataset_excel_date($row['Week Start'] ?? '');
        $to = historical_dataset_excel_date($row['Week End'] ?? '');
        $actual = array('transactions' => 0, 'units' => 0, 'revenue' => 0.0, 'walk_in_revenue' => 0.0, 'online_revenue' => 0.0);
        foreach ($daily as $date => $values) {
            if ($date < $from || $date > $to) {
                continue;
            }
            foreach ($actual as $field => $value) {
                $actual[$field] += $values[$field];
            }
        }
        $compareSummary($report, 'weekly_summary', $actual, array(
            'transactions' => (int) ($row['Transactions'] ?? 0),
            'units' => (int) ($row['Units Sold'] ?? 0),
            'revenue' => (float) ($row['Revenue'] ?? 0),
            'walk_in_revenue' => (float) ($row['Walk-in Revenue'] ?? 0),
            'online_revenue' => (float) ($row['Online Revenue'] ?? 0),
        ), array('week_start' => $from));
    }

    foreach ($productSummaryRows as $row) {
        $productId = (int) ($row['Product ID'] ?? 0);
        $compareSummary($report, 'product_summary', $byProduct[$productId] ?? array(), array(
            'transactions' => (int) ($row['Transactions'] ?? 0),
            'units' => (int) ($row['Units Sold'] ?? 0),
            'revenue' => (float) ($row['Revenue'] ?? 0),
            'walk_in_revenue' => (float) ($row['Walk-in Revenue'] ?? 0),
            'online_revenue' => (float) ($row['Online Revenue'] ?? 0),
        ), array('product_id' => $productId));
    }

    foreach ($channelRows as $row) {
        $channel = historical_dataset_text($row['Channel'] ?? '');
        $compareSummary($report, 'channel_summary', $byChannel[$channel] ?? array(), array(
            'transactions' => (int) ($row['Transactions'] ?? 0),
            'units' => (int) ($row['Units Sold'] ?? 0),
            'revenue' => (float) ($row['Revenue'] ?? 0),
        ), array('channel' => $channel));
    }

    $restockIds = array();
    $restocksByProduct = array();
    $restocks = array();
    foreach ($restockRows as $rowNumber => $row) {
        $restockId = historical_dataset_text($row['Restock ID'] ?? '');
        $productId = (int) ($row['Product ID'] ?? 0);
        $date = historical_dataset_excel_date($row['Date'] ?? '');
        $quantity = filter_var($row['Quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($restockId === '' || isset($restockIds[$restockId])) {
            historical_dataset_add_error($report, 'restock_id', 'Restock ID is blank or duplicated.', array('row' => $rowNumber + 2));
        } else {
            $restockIds[$restockId] = true;
        }
        if (!isset($catalog[$productId]) || strcasecmp(historical_dataset_text($row['Product Name'] ?? ''), historical_dataset_text($catalog[$productId]['Product Name'] ?? '')) !== 0) {
            historical_dataset_add_error($report, 'restock_product', 'Restock product does not match the catalog.', array('row' => $rowNumber + 2, 'restock_id' => $restockId));
        }
        if ($date === '' || $quantity === false || (int) $quantity <= 0) {
            historical_dataset_add_error($report, 'restock_values', 'Restock date or quantity is invalid.', array('row' => $rowNumber + 2, 'restock_id' => $restockId));
        }
        $restocksByProduct[$productId] = ($restocksByProduct[$productId] ?? 0) + (int) $quantity;
        $restocks[] = array('restock_id' => $restockId, 'event_date' => $date, 'product_id' => $productId, 'quantity' => (int) $quantity, 'type' => historical_dataset_text($row['Type'] ?? ''));
    }

    $inventory = array();
    $invalidInventoryProductIds = array();
    foreach ($inventoryRows as $rowNumber => $row) {
        $productId = (int) ($row['Product ID'] ?? 0);
        $beginning = (int) ($row['Beginning Stock'] ?? 0);
        $restocked = (int) ($row['Restocked'] ?? 0);
        $units = (int) ($row['Units Sold'] ?? 0);
        $ending = (int) ($row['Ending/Current Stock'] ?? 0);
        $reconciliation = (int) ($row['Reconciliation'] ?? 0);
        if (!isset($catalog[$productId]) || !isset($databaseProducts[$productId])) {
            historical_dataset_add_error($report, 'inventory_product', 'Inventory product is not safely matched.', array('row' => $rowNumber + 2, 'product_id' => $productId));
            continue;
        }
        if ($beginning + $restocked - $units !== $ending || $reconciliation !== 0) {
            historical_dataset_add_error($report, 'inventory_reconciliation', 'Inventory arithmetic does not reconcile.', array('product_id' => $productId));
            $invalidInventoryProductIds[$productId] = true;
        }
        if ((int) ($byProduct[$productId]['units'] ?? 0) !== $units) {
            historical_dataset_add_error($report, 'inventory_sales_units', 'Inventory Units Sold does not match Transactions.', array('product_id' => $productId));
        }
        if ((int) ($restocksByProduct[$productId] ?? 0) !== $restocked) {
            historical_dataset_add_error($report, 'inventory_restock_units', 'Inventory Restocked does not match Restocks.', array('product_id' => $productId));
        }
        if ((int) ($catalog[$productId]['Current Stock'] ?? -1) !== $ending) {
            historical_dataset_add_error($report, 'inventory_catalog_stock', 'Inventory ending stock does not match Product Catalog current stock.', array('product_id' => $productId));
        }
        $inventory[$productId] = array('product_id' => $productId, 'beginning_stock' => $beginning, 'restocked' => $restocked, 'units_sold' => $units, 'ending_stock' => $ending);
    }

    $forecastKeys = array();
    $forecastByProduct = array();
    foreach ($forecastRows as $rowNumber => $row) {
        $date = historical_dataset_excel_date($row['Date'] ?? '');
        $productId = (int) ($row['Product ID'] ?? 0);
        $units = filter_var($row['Units Sold'] ?? null, FILTER_VALIDATE_INT);
        $key = $date . ':' . $productId;
        if ($date === '' || !isset($catalog[$productId]) || $units === false || (int) $units < 0 || isset($forecastKeys[$key])) {
            historical_dataset_add_error($report, 'forecast_history_row', 'Forecasting History has an invalid or duplicated date/product row.', array('row' => $rowNumber + 2));
            continue;
        }
        $forecastKeys[$key] = true;
        $forecastByProduct[$productId][] = $date;
        $actualUnits = (int) ($dailyByProduct[$key] ?? 0);
        if ($actualUnits !== (int) $units) {
            historical_dataset_add_error($report, 'forecast_history_units', 'Forecasting History units do not match Transactions.', array('date' => $date, 'product_id' => $productId));
        }
    }
    foreach ($forecastByProduct as $productId => $productDates) {
        sort($productDates);
        $first = reset($productDates);
        $last = end($productDates);
        $expectedCount = (int) (new DateTimeImmutable($first))->diff(new DateTimeImmutable($last))->format('%a') + 1;
        if (count($productDates) !== $expectedCount) {
            historical_dataset_add_error($report, 'forecast_history_gap', 'Forecasting History is not a continuous daily series.', array('product_id' => $productId));
        }
    }
    $report['forecast_coverage'] = array();
    foreach ($forecastByProduct as $productId => $productDates) {
        sort($productDates);
        $report['forecast_coverage'][(int) $productId] = array(
            'from' => (string) reset($productDates),
            'to' => (string) end($productDates),
            'days' => count($productDates),
        );
    }

    $orders = store_state_get_orders();
    $orderSummary = array('total' => count($orders), 'completed' => 0, 'active' => 0, 'completed_on_or_before_import_end' => 0, 'invalid_product_ids' => 0);
    foreach ($orders as $order) {
        $status = (string) ($order['status'] ?? '');
        $date = substr((string) ($order['created_at'] ?? ''), 0, 10);
        if ($status === 'Completed') {
            $orderSummary['completed']++;
            if ($date !== '' && $report['date_range']['to'] !== '' && $date <= $report['date_range']['to']) {
                $orderSummary['completed_on_or_before_import_end']++;
            }
        }
        if (in_array($status, array('Pending', 'Confirmed', 'Ready for Pickup'), true)) {
            $orderSummary['active']++;
        }
        if (!isset($databaseProducts[(int) ($order['product_id'] ?? 0)])) {
            $orderSummary['invalid_product_ids']++;
        }
    }
    $report['database_state']['orders'] = $orderSummary;

    $report['inventory_validation'] = array(
        'valid_product_ids' => array_values(array_diff(array_keys($inventory), array_keys($invalidInventoryProductIds))),
        'invalid_product_ids' => array_map('intval', array_keys($invalidInventoryProductIds)),
        'valid_rows' => count($inventory) - count($invalidInventoryProductIds),
        'invalid_rows' => count($invalidInventoryProductIds),
    );

    $report['transactions'] = $transactions;
    $report['inventory'] = array_values($inventory);
    $report['restocks'] = $restocks;
    $report['ok'] = empty($report['errors']);

    return $report;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $path = $argv[1] ?? '';
    if ($path === '') {
        fwrite(STDERR, "Usage: php scripts/validate_historical_dataset.php <workbook.xlsx>\n");
        exit(2);
    }

    try {
        $report = historical_dataset_validate($path, $conn);
        $output = $report;
        unset($output['transactions'], $output['inventory'], $output['restocks']);
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit($report['ok'] ? 0 : 1);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Dataset validation failed safely: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
