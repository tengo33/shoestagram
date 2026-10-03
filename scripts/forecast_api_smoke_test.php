<?php

/* This script is CLI-only so credentials and diagnostic details never reach a browser. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database/forecast_api.php';

$result = shoestagram_forecast_api_smoke_test();
$report = array(
    'Environment variable loaded' => $result['environment_loaded'] ? 'Yes' : 'No',
    'API authentication' => $result['authentication'],
    'HTTP status code' => $result['http_status_code'] > 0 ? $result['http_status_code'] : 'N/A',
    'Forecast response received' => $result['forecast_response_received'] ? 'Yes' : 'No',
    'Safe error message' => $result['safe_error_message'] !== '' ? $result['safe_error_message'] : 'None',
);

echo json_encode($report, JSON_UNESCAPED_SLASHES) . PHP_EOL;
