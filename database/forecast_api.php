<?php

require_once __DIR__ . '/config.php';

/*
 * Server-side ForecastAPI client. Responses stay in PHP memory; callers must
 * choose explicitly which non-sensitive forecast fields to display.
 */
function shoestagram_forecast_api_request($identifier, $data, $periods, $frequency = 'D', $data_type = 'sales')
{
    $api_key = trim(shoestagram_env('FORECAST_API_KEY', ''));
    $report = array(
        'environment_loaded' => $api_key !== '',
        'authentication' => 'Failed',
        'http_status_code' => 0,
        'forecast_response_received' => false,
        'forecasts' => array(),
        'model_info' => array(),
        'generated_at' => date('c'),
        'safe_error_message' => '',
    );

    if ($api_key === '') {
        $report['safe_error_message'] = 'Forecast API credential is not configured.';
        return $report;
    }

    if (!function_exists('curl_init')) {
        $report['safe_error_message'] = 'The PHP cURL extension is unavailable.';
        return $report;
    }

    $payload = array(
        'identifier' => trim((string) $identifier),
        'data' => array_values((array) $data),
        'periods' => max(1, (int) $periods),
        'frequency' => strtoupper(trim((string) $frequency)),
        'data_type' => trim((string) $data_type),
    );
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if ($payload['identifier'] === '' || empty($payload['data']) || $body === false) {
        $report['safe_error_message'] = 'The forecast request data is incomplete.';
        return $report;
    }

    $request = curl_init('https://forecastapi.com/v2/forecast');
    curl_setopt_array($request, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));

    $response = curl_exec($request);
    $transport_ok = $response !== false;
    $report['http_status_code'] = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);

    if (!$transport_ok) {
        $report['safe_error_message'] = 'The Forecast API request could not connect.';
        return $report;
    }

    if ($report['http_status_code'] === 401 || $report['http_status_code'] === 403) {
        $report['safe_error_message'] = 'Forecast API authentication was rejected.';
        return $report;
    }

    if ($report['http_status_code'] < 200 || $report['http_status_code'] >= 300) {
        $report['safe_error_message'] = 'Forecast API returned an unsuccessful response.';
        return $report;
    }

    $decoded = json_decode((string) $response, true);
    $result = is_array($decoded) && isset($decoded['result']) && is_array($decoded['result'])
        ? $decoded['result']
        : (is_array($decoded) ? $decoded : array());
    $forecasts = isset($result['forecasts']) && is_array($result['forecasts'])
        ? $result['forecasts']
        : array();

    $report['authentication'] = 'Success';
    $report['forecasts'] = $forecasts;
    $report['model_info'] = isset($result['model_info']) && is_array($result['model_info'])
        ? $result['model_info']
        : array();
    $report['forecast_response_received'] = !empty($forecasts);
    $report['safe_error_message'] = $report['forecast_response_received']
        ? ''
        : 'Forecast API returned no forecast periods.';

    return $report;
}

function shoestagram_forecast_api_smoke_test()
{
    return shoestagram_forecast_api_request(
        'shoestagram-connectivity-test',
        array(
            array('date' => '2025-01-01', 'value' => 120),
            array('date' => '2025-02-01', 'value' => 128),
            array('date' => '2025-03-01', 'value' => 141),
            array('date' => '2025-04-01', 'value' => 136),
            array('date' => '2025-05-01', 'value' => 152),
            array('date' => '2025-06-01', 'value' => 165),
            array('date' => '2025-07-01', 'value' => 171),
            array('date' => '2025-08-01', 'value' => 168),
            array('date' => '2025-09-01', 'value' => 181),
            array('date' => '2025-10-01', 'value' => 193),
            array('date' => '2025-11-01', 'value' => 205),
            array('date' => '2025-12-01', 'value' => 214),
        ),
        3,
        'M',
        'sales'
    );
}
