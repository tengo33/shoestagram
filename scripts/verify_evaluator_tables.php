<?php
/** Read-only pagination, sorting, and grouped analytics checks. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
session_save_path(sys_get_temp_dir());
putenv('FORECAST_API_KEY=');
$_ENV['FORECAST_API_KEY'] = '';
require dirname(__DIR__) . '/store-data.php';
require dirname(__DIR__) . '/backoffice-layout.php';

$failed = false;
$check = function ($label, $condition) use (&$failed) {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed = $failed || !$condition;
};

$records = array();
for ($number = 45; $number >= 1; $number--) {
    $records[] = array('id' => $number);
}
$_SERVER['PHP_SELF'] = '/admin/sales.php';
$_GET = array('page' => 1, 'channel' => 'ONLINE', 'date_from' => '2026-01-01');
$first = $records;
$first_page = backoffice_paginate_rows($first);
$_GET['page'] = 2;
$second = $records;
$second_page = backoffice_paginate_rows($second);
$check('20-row pagination and latest-first page 1', count($first) === 20 && $first[0]['id'] === 45 && $first[19]['id'] === 26);
$check('page 2 has no duplicate or missing records', count($second) === 20 && $second[0]['id'] === 25 && $second[19]['id'] === 6
    && count(array_unique(array_merge(array_column($first, 'id'), array_column($second, 'id')))) === 40);
ob_start();
backoffice_render_pagination($second_page);
$navigation = ob_get_clean();
$check('page links preserve date and channel filters', strpos($navigation, 'channel=ONLINE') !== false
    && strpos($navigation, 'date_from=2026-01-01') !== false && strpos($navigation, 'page=1') !== false
    && strpos($navigation, 'page=3') !== false);
$line_records = array();
for ($number = 1; $number <= 21; $number++) {
    $line_count = $number === 20 ? 3 : 1;
    for ($item = 1; $item <= $line_count; $item++) {
        $line_records[] = array('transaction_key' => 'system:TEST-' . $number, 'item' => $item);
    }
}
$_GET['page'] = 1;
$line_first = $line_records;
backoffice_paginate_transaction_lines($line_first);
$_GET['page'] = 2;
$line_second = $line_records;
backoffice_paginate_transaction_lines($line_second);
$check('transaction pagination keeps all checkout lines together', count($line_first) === 22
    && count($line_second) === 1 && count(array_filter($line_first, function ($row) {
        return $row['transaction_key'] === 'system:TEST-20';
    })) === 3);

$transactions = array();
foreach (array(7500, 6500, 500) as $index => $amount) {
    $transactions[] = array('status' => 'Completed', 'source' => 'system', 'reference' => 'TEST-ONE',
        'transaction_key' => 'system:TEST-ONE', 'transaction_type' => 'ONLINE', 'amount' => $amount,
        'quantity' => 1, 'product_id' => $index + 1, 'created_at' => '2026-10-01T14:45:00+08:00');
}
$summary = backoffice_summarize_transaction_channels($transactions);
$check('three product rows count as one transaction', $summary['total_transactions'] === 1
    && abs($summary['total_revenue'] - 14500.0) < 0.001);
$grouped_orders = store_group_checkout_orders(array(
    array('id' => 'TEST-ONE-1', 'checkout_id' => 'TEST-ONE', 'subtotal' => 7500, 'created_at' => '2026-10-01T14:45:00+08:00'),
    array('id' => 'TEST-ONE-2', 'checkout_id' => 'TEST-ONE', 'subtotal' => 6500, 'created_at' => '2026-10-01T14:45:00+08:00'),
    array('id' => 'TEST-ONE-3', 'checkout_id' => 'TEST-ONE', 'subtotal' => 500, 'created_at' => '2026-10-01T14:45:00+08:00'),
));
$check('customer/admin order grouping contains every line and total', count($grouped_orders) === 1
    && count($grouped_orders[0]['items']) === 3 && (float) $grouped_orders[0]['subtotal'] === 14500.0);
$filtered = backoffice_filter_transactions($transactions, array('product_id' => 2, 'channel' => 'ONLINE'));
$check('product/channel filters preserve line-level analytics', count($filtered) === 1
    && (int) $filtered[0]['product_id'] === 2 && (float) $filtered[0]['amount'] === 6500.0);
$midnight = array(array('status' => 'Completed', 'source' => 'system', 'transaction_type' => 'ONLINE',
    'created_at' => '2026-10-01T00:30:00+08:00', 'product_id' => 1, 'amount' => 100.0));
$check('date filter uses Philippine transaction date', count(backoffice_filter_transactions($midnight,
    array('date_from' => '2026-10-01', 'date_to' => '2026-10-01'))) === 1);

$manual_first = store_get_manual_sales(array(), 20, 0);
$manual_second = store_get_manual_sales(array(), 20, 20);
$manual_total = (int) store_manual_sales_summary()['records'];
$check('historical-sales SQL page size', count($manual_first) <= 20 && count($manual_second) <= 20);
$manual_ids = array_merge(array_column($manual_first, 'id'), array_column($manual_second, 'id'));
$check('historical-sales pages do not overlap', count($manual_ids) === count(array_unique($manual_ids)));
if ($manual_first) {
    $selected_product = (int) $manual_first[0]['product_id'];
    $filtered_manual = store_get_manual_sales(array('product_id' => $selected_product), 20, 0);
    $check('historical-sales product filter remains applied on paged SQL',
        !empty($filtered_manual) && count(array_filter($filtered_manual, function ($sale) use ($selected_product) {
            return (int) $sale['product_id'] !== $selected_product;
        })) === 0);
}
if ($manual_total > 20) {
    $check('historical-sales second page exists', count($manual_second) > 0);
}

$log_page = database_get_inventory_logs_page($conn, 1, 20);
$check('inventory-log SQL pagination', count($log_page['rows']) <= 20 && $log_page['per_page'] === 20);
$roles = database_fetch_all($conn, 'SELECT role, COUNT(*) AS total FROM users GROUP BY role');
$role_counts = array_column($roles, 'total', 'role');
$check('account roles remain present', array_key_exists('admin', $role_counts)
    && array_key_exists('staff', $role_counts) && array_key_exists('customer', $role_counts));

$history_product_id = (int) ($manual_first[0]['product_id'] ?? 0);
if ($history_product_id <= 0) {
    foreach (store_state_get_orders() as $order) {
        if (store_forecast_order_counts_as_completed_sale($order)) {
            $history_product_id = (int) ($order['product_id'] ?? 0);
            break;
        }
    }
}
if ($history_product_id > 0) {
    $expected_days = array();
    foreach (store_state_get_orders() as $order) {
        if ((int) ($order['product_id'] ?? 0) === $history_product_id && store_forecast_order_counts_as_completed_sale($order)) {
            $date = substr((string) ($order['created_at'] ?? ''), 0, 10);
            $expected_days[$date] = ($expected_days[$date] ?? 0) + (int) ($order['quantity'] ?? 0);
        }
    }
    foreach (store_get_manual_sales(array('product_id' => $history_product_id)) as $sale) {
        $date = (string) ($sale['sale_date'] ?? '');
        $expected_days[$date] = ($expected_days[$date] ?? 0) + (int) ($sale['quantity'] ?? 0);
    }
    $history = store_get_product_completed_sales_history($history_product_id);
    $series_match = !empty($history['series']) && count($history['series']) <= 90;
    foreach ($history['series'] as $point) {
        $series_match = $series_match && (int) $point['value'] === (int) ($expected_days[$point['date']] ?? 0);
    }
    $check('forecast history remains product-specific completed-item units', $series_match);
} else {
    echo "NOT FULLY VERIFIED forecast history: no eligible completed sale found.\n";
}

$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
$expected_periods = array('daily' => 0.0, 'weekly' => 0.0, 'monthly' => 0.0);
foreach (get_admin_dynamic_transactions(0) as $transaction) {
    if (($transaction['status'] ?? '') !== 'Completed' || !store_transaction_counts_for_analytics($transaction)) {
        continue;
    }
    $date = (new DateTimeImmutable((string) $transaction['created_at'], new DateTimeZone('Asia/Manila')))
        ->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
    if ($date > $today->format('Y-m-d')) {
        continue;
    }
    $amount = (float) $transaction['amount'];
    if ($date === $today->format('Y-m-d')) $expected_periods['daily'] += $amount;
    if ($date >= $today->modify('monday this week')->format('Y-m-d')) $expected_periods['weekly'] += $amount;
    if ($date >= $today->format('Y-m-01')) $expected_periods['monthly'] += $amount;
}
$overview = store_get_sales_overview();
$check('dashboard day/week/month cards use completed period sales',
    abs((float) $overview['daily_sales'] - round($expected_periods['daily'], 2)) < 0.001
    && abs((float) $overview['weekly_sales'] - round($expected_periods['weekly'], 2)) < 0.001
    && abs((float) $overview['monthly_sales'] - round($expected_periods['monthly'], 2)) < 0.001);

exit($failed ? 1 : 0);
