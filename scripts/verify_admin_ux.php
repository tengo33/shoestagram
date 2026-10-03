<?php
/** Read-only CLI checks for Admin record paging and display helpers. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

session_save_path(sys_get_temp_dir());
session_start();
require dirname(__DIR__) . '/backoffice-layout.php';

$checks = array();
$_SERVER['PHP_SELF'] = '/shoestagram/admin/sales.php';
$all_seen = array();
foreach (range(1, 15) as $requested_page) {
    $_GET = array('page' => $requested_page, 'status' => 'completed', 'channel' => 'online');
    $rows = range(1, 148);
    $pagination = backoffice_paginate_rows($rows);
    $all_seen = array_merge($all_seen, $rows);
    if (in_array($requested_page, array(1, 8, 15), true)) {
        ob_start();
        backoffice_render_pagination($pagination, 'testRecords');
        $html = ob_get_clean();
        $first = ($requested_page - 1) * 10 + 1;
        $last = min(148, $requested_page * 10);
        $checks['page_' . $requested_page] = count($rows) === $last - $first + 1
            && $rows[0] === $first
            && end($rows) === $last
            && strpos($html, 'Showing ' . $first . '&ndash;' . $last . ' of 148 records') !== false
            && strpos($html, 'Page ' . $requested_page . ' of 15') !== false
            && strpos($html, 'aria-current="page"') !== false
            && strpos($html, 'status=completed') !== false
            && strpos($html, 'channel=online') !== false
            && strpos($html, '#testRecords') !== false;
        if ($requested_page === 8) {
            $checks['compact_numbers'] = strpos($html, '&hellip;') !== false
                && substr_count($html, 'class="pagination-page"') <= 6;
        }
        if ($requested_page === 1 || $requested_page === 15) {
            $checks['edge_' . $requested_page] = strpos($html, 'aria-disabled="true"') !== false;
        }
    }
}
$checks['no_missing_or_duplicate_rows'] = $all_seen === range(1, 148);

$transaction_rows = array();
foreach (range(1, 23) as $number) {
    $transaction_rows[] = array('transaction_key' => 'order:' . $number, 'item' => 1);
    $transaction_rows[] = array('transaction_key' => 'order:' . $number, 'item' => 2);
}
$_GET = array('page' => 2);
$transaction_page = backoffice_paginate_transaction_lines($transaction_rows);
$checks['transaction_groups'] = $transaction_page['total'] === 23
    && count($transaction_rows) === 20
    && $transaction_rows[0]['transaction_key'] === 'order:11'
    && $transaction_rows[19]['transaction_key'] === 'order:20';

$manila_time = backoffice_manila_datetime('2026-10-03T05:42:00+00:00');
$checks['manila_time'] = $manila_time && $manila_time->format('M j, Y g:i A') === 'Oct 3, 2026 1:42 PM'
    && backoffice_manila_datetime('') === null
    && backoffice_manila_datetime('0000-00-00 00:00:00') === null
    && backoffice_manila_datetime('2026-10-03', true) === null
    && backoffice_manila_datetime('2026-02-30 13:42:00', true) === null;

$admin_keys = array_column(backoffice_nav_items('admin'), 'key');
$staff_keys = array_column(backoffice_nav_items('staff'), 'key');
$checks['navigation_roles'] = in_array('manual-sales', $admin_keys, true)
    && !in_array('manual-sales', $staff_keys, true);

require dirname(__DIR__) . '/database/config.php';
require dirname(__DIR__) . '/store-data.php';
$reservations = store_group_checkout_orders(store_state_get_orders());
$transactions = get_admin_dynamic_transactions(0);
$manual_sales = store_get_manual_sales();
$activity_page = database_get_activity_log_page($conn, array(), 1, 10);
$counts = array(
    'reservations' => count($reservations),
    'sales_lines' => count($transactions),
    'manual_sales' => count($manual_sales),
    'activity_events' => (int) $activity_page['total'],
    'reviews' => count(store_state_get_reviews()),
    'messages' => count(store_state_get_messages()),
);
$newest_first = function ($rows, $date_key) {
    for ($index = 1; $index < count($rows); $index++) {
        if (strcmp((string) ($rows[$index - 1][$date_key] ?? ''), (string) ($rows[$index][$date_key] ?? '')) < 0) {
            return false;
        }
    }
    return true;
};
$checks['reservation_newest_first'] = $newest_first($reservations, 'created_at');
$checks['sales_newest_first'] = $newest_first($transactions, 'created_at');
$checks['manual_newest_first'] = $newest_first($manual_sales, 'sale_date');
$checks['activity_newest_first'] = $newest_first($activity_page['rows'], 'created_at');

$seen_reservation_ids = array();
foreach (range(1, max(1, (int) ceil(count($reservations) / 10))) as $number) {
    $_GET = array('page' => $number);
    $visible = $reservations;
    backoffice_paginate_rows($visible, 'page', 10);
    $seen_reservation_ids = array_merge($seen_reservation_ids, array_column($visible, 'checkout_id'));
}
$checks['live_reservation_pages'] = $seen_reservation_ids === array_column($reservations, 'checkout_id');

$seen_transaction_lines = array();
$transaction_page_count = max(1, (int) ceil(count(array_unique(array_column($transactions, 'transaction_key'))) / 10));
foreach (range(1, $transaction_page_count) as $number) {
    $_GET = array('page' => $number);
    $visible = $transactions;
    backoffice_paginate_transaction_lines($visible, 'page', 10);
    foreach ($visible as $row) {
        $seen_transaction_lines[] = (string) $row['transaction_key'] . ':' . (int) ($row['checkout_item_number'] ?? 1);
    }
}
$all_transaction_lines = array_map(function ($row) {
    return (string) $row['transaction_key'] . ':' . (int) ($row['checkout_item_number'] ?? 1);
}, $transactions);
sort($seen_transaction_lines);
sort($all_transaction_lines);
$checks['live_sales_pages'] = $seen_transaction_lines === $all_transaction_lines;

$manual_page_count = max(1, (int) ceil(count($manual_sales) / 10));
$manual_page_numbers = array_values(array_unique(array(1, (int) ceil($manual_page_count / 2), $manual_page_count)));
$manual_page_ids = array();
foreach ($manual_page_numbers as $number) {
    foreach (store_get_manual_sales(array(), 10, ($number - 1) * 10) as $row) {
        $manual_page_ids[] = (int) $row['id'];
    }
}
$checks['live_manual_pages'] = count($manual_page_ids) === count(array_unique($manual_page_ids))
    && !empty($manual_page_ids);

$activity_page_count = max(1, (int) ceil((int) $activity_page['total'] / 10));
$activity_page_numbers = array_values(array_unique(array(1, (int) ceil($activity_page_count / 2), $activity_page_count)));
$activity_page_ids = array();
foreach ($activity_page_numbers as $number) {
    foreach (database_get_activity_log_page($conn, array(), $number, 10)['rows'] as $row) {
        $activity_page_ids[] = (int) $row['id'];
    }
}
$checks['live_activity_pages'] = count($activity_page_ids) === count(array_unique($activity_page_ids))
    && !empty($activity_page_ids);

$reviews = store_state_get_reviews();
usort($reviews, function ($left, $right) {
    return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
});
$review_ids = array();
foreach (range(1, max(1, (int) ceil(count($reviews) / 10))) as $number) {
    $_GET = array('review_page' => $number);
    $visible = $reviews;
    backoffice_paginate_rows($visible, 'review_page', 10);
    $review_ids = array_merge($review_ids, array_column($visible, 'id'));
}
$checks['live_feedback_pages'] = $review_ids === array_column($reviews, 'id');

foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
}
echo 'Live record counts: ' . implode(', ', array_map(function ($key, $value) {
    return $key . '=' . $value;
}, array_keys($counts), array_values($counts))) . PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
