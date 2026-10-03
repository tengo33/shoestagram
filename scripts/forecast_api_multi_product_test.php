<?php
declare(strict_types=1);

require_once __DIR__ . '/../store-data.php';

$productIds = [113, 127, 118, 149, 143];
$reports = [];
$pageFieldContractPasses = true;
$pageFieldContractMissing = [];

/* This opt-in CLI-only flag verifies a fresh request followed by disk-cache reuse. */
if (PHP_SAPI === 'cli' && in_array('--fresh-cache-test', $argv, true)) {
    $cacheTestProductId = 118;
    @unlink(store_forecast_cache_path($cacheTestProductId));
}

foreach ($productIds as $productId) {
    $product = get_store_product_by_id($productId);
    if (!$product) {
        throw new RuntimeException('Configured validation product was not found.');
    }

    $actualHistory = store_get_product_completed_sales_history($productId);
    $submittedHistory = store_get_product_forecast_history($product);
    $forecast = store_get_product_forecast_result($product);
    $predictionInterval = $forecast['forecast_periods'][0] ?? [];
    $usesActualHistory = $forecast['data_source'] !== 'Sample / Development Data';
    $pageFields = [
        'data_source', 'history_status', 'limited_history_warning', 'forecast_source',
        'forecast_available', 'forecast_unavailable_message', 'next_30d_units',
        'next_90d_units', 'next_30d_revenue', 'suggested_stock', 'reorder_gap',
        'forecast_periods', 'generated_at', 'historical_sales_days',
        'continuous_history_days', 'model_method',
    ];
    $missingFields = array_values(array_diff($pageFields, array_keys($forecast)));

    if (!empty($missingFields)) {
        $pageFieldContractPasses = false;
        $pageFieldContractMissing[$productId] = $missingFields;
    }

    $reports[] = [
        'id' => $productId,
        'name' => $product['name'],
        'data_source' => $forecast['data_source'],
        'historical_sales_days' => $forecast['historical_sales_days'],
        'history_status' => $forecast['history_status'],
        'historical_total_units_sold' => $actualHistory['total_units_sold'],
        'submitted_history_total_units' => array_sum(array_map(function ($point) {
            return (float) ($point['value'] ?? 0);
        }, $submittedHistory['series'])),
        'forecast_30d_demand' => $forecast['next_30d_units'],
        'forecast_90d_units' => $forecast['next_90d_units'],
        'current_price' => $product['price'],
        'forecast_30d_revenue' => $forecast['next_30d_revenue'],
        'current_stock' => $forecast['current_stock'],
        'suggested_stock' => $forecast['suggested_stock'],
        'reorder_gap' => $forecast['reorder_gap'],
        'forecast_model' => $forecast['model_method'] ?? 'Not returned',
        'prediction_interval' => $predictionInterval,
        'result_source' => $forecast['forecast_source'],
        'cached' => $forecast['forecast_cached'],
        'available' => $forecast['forecast_available'],
        'limited_history_warning' => $forecast['limited_history_warning'],
        'historical_days_submitted' => $forecast['continuous_history_days'],
        'revenue_formula_valid' => abs(($forecast['next_30d_units'] * $product['price']) - $forecast['next_30d_revenue']) < 0.01,
        'nonnegative_gap' => $forecast['reorder_gap'] >= 0,
        'history_separated' => $usesActualHistory
            ? !empty($actualHistory['series'])
            : empty($actualHistory['series']),
    ];
}

$catalogHistoryChecks = [
    'products_checked' => 0,
    'actual_history_only' => true,
    'sample_history_only' => true,
    'sample_history_at_most_90_points' => true,
    'mismatched_product_ids' => [],
];

foreach (get_store_products() as $catalogProduct) {
    $catalogHistoryChecks['products_checked']++;
    $actualHistory = store_get_product_completed_sales_history((int) $catalogProduct['id']);
    $selectedHistory = store_get_product_forecast_history($catalogProduct);
    $hasActualHistory = !empty($actualHistory['series']);
    $selectedActualHistory = (string) ($selectedHistory['data_source'] ?? '') !== 'Sample / Development Data';

    if ($hasActualHistory && (!$selectedActualHistory || $selectedHistory['series'] !== $actualHistory['series'])) {
        $catalogHistoryChecks['actual_history_only'] = false;
        $catalogHistoryChecks['mismatched_product_ids'][] = (int) $catalogProduct['id'];
    }

    if (!$hasActualHistory && ($selectedActualHistory || (int) ($selectedHistory['historical_sales_days'] ?? -1) !== 0 || (int) ($selectedHistory['total_units_sold'] ?? -1) !== 0)) {
        $catalogHistoryChecks['sample_history_only'] = false;
        $catalogHistoryChecks['mismatched_product_ids'][] = (int) $catalogProduct['id'];
    }

    if (!$hasActualHistory && count((array) ($selectedHistory['series'] ?? [])) > 90) {
        $catalogHistoryChecks['sample_history_at_most_90_points'] = false;
        $catalogHistoryChecks['mismatched_product_ids'][] = (int) $catalogProduct['id'];
    }
}

$catalogHistoryChecks['mismatched_product_ids'] = array_values(array_unique($catalogHistoryChecks['mismatched_product_ids']));

echo json_encode([
    'products' => $reports,
    'catalog_history_validation' => $catalogHistoryChecks,
    'forecasting_page_field_contract' => [
        'passes' => $pageFieldContractPasses,
        'missing_fields' => $pageFieldContractMissing,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
