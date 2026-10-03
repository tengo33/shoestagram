<?php

/* CLI-only: no credential or forecast diagnostics are exposed to HTTP clients. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/store-data.php';
require_once dirname(__DIR__) . '/database/forecast_api.php';

$selection = store_find_product_with_completed_sales_history();

if ($selection === null) {
    echo json_encode(array(
        'safe_error_message' => 'No product has completed-sale history available for the test.',
    ), JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$product = $selection['product'];
$history = $selection['history'];
$api = shoestagram_forecast_api_request(
    'shoestagram-product-' . (int) $product['id'],
    $history['series'],
    90,
    'D',
    'sales'
);
$projection = $api['forecast_response_received']
    ? store_build_forecast_api_projection($product, $api)
    : array();
$first_period = !empty($projection['forecast_periods']) ? $projection['forecast_periods'][0] : array();

$report = array(
    'Product tested' => (string) $product['name'],
    'Historical sales days available' => (int) $history['historical_sales_days'],
    'Continuous historical days submitted' => count($history['series']),
    'Earliest historical date' => $history['earliest_date'],
    'Latest historical date' => $history['latest_date'],
    'Total actual units sold in historical period' => (int) $history['total_units_sold'],
    'API HTTP status' => (int) $api['http_status_code'],
    '30-Day Forecasted Demand' => $projection['forecast_30_day_demand'] ?? null,
    '90-Day Forecasted Units' => $projection['forecast_90_day_units'] ?? null,
    'Current Price' => $projection['current_price'] ?? null,
    '30-Day Forecasted Revenue' => $projection['forecast_30_day_revenue'] ?? null,
    'Current Stock' => $projection['current_stock'] ?? null,
    'Suggested Stock' => $projection['suggested_stock'] ?? null,
    'Reorder Gap' => $projection['reorder_gap'] ?? null,
    'Forecast model/method' => $projection['model_method'] ?? '',
    'First-period prediction interval' => isset($first_period['lower'], $first_period['upper'])
        ? array('lower' => $first_period['lower'], 'upper' => $first_period['upper'])
        : null,
    'Safe error message' => $api['safe_error_message'] !== '' ? $api['safe_error_message'] : 'None',
);

echo json_encode($report, JSON_UNESCAPED_SLASHES) . PHP_EOL;
