<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$runtime_session_path = dirname(__DIR__) . '/database/runtime';
if (is_dir($runtime_session_path)) {
    ini_set('session.save_path', $runtime_session_path);
}

require_once dirname(__DIR__) . '/store-data.php';
require_once dirname(__DIR__) . '/backoffice-layout.php';

function verify_result($name, $passed, $detail = '')
{
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . ($detail !== '' ? ' | ' . $detail : '') . PHP_EOL;
    return (bool) $passed;
}

$all_passed = true;

$user_columns = array_column(database_fetch_all($conn, 'SHOW COLUMNS FROM users'), 'Field');
$activity_table = database_fetch_one(
    $conn,
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' LIMIT 1"
);
$all_passed = verify_result(
    'persistent login schema',
    in_array('failed_login_attempts', $user_columns, true)
        && in_array('last_failed_login', $user_columns, true)
        && in_array('locked_until', $user_columns, true)
        && (bool) $activity_table
) && $all_passed;

$activity_columns = array_column(database_fetch_all($conn, 'SHOW COLUMNS FROM activity_logs'), 'Field');
$sensitive_activity_columns = array_intersect(
    $activity_columns,
    array('password', 'password_hash', 'verification_code', 'api_key', 'smtp_password', 'payment_secret')
);
$all_passed = verify_result('activity log schema excludes credential fields', empty($sensitive_activity_columns)) && $all_passed;

$orders_before = store_state_get_orders();
$order_count_before = count($orders_before);
$order_test = null;
foreach ($orders_before as $candidate) {
    if ((string) ($candidate['payment_status'] ?? '') === 'Unpaid'
        && (string) ($candidate['status'] ?? '') !== 'Completed') {
        $order_test = $candidate;
        break;
    }
}

if ($order_test) {
    $order_id = (string) $order_test['id'];
    $original_status = (string) $order_test['status'];
    $verification_actor = array(
        'name' => 'Workflow Verification',
        'email' => 'workflow-verification@shoestagram.invalid',
        'role' => 'admin',
    );
    $payment_result = backoffice_apply_order_update('admin', $order_id, $original_status, 'Paid', $verification_actor, false);
    $saved_order = store_get_order_by_id($order_id);
    $customer_copy = null;
    foreach (store_state_get_customer_orders((string) ($order_test['customer_email'] ?? '')) as $candidate) {
        if ((string) ($candidate['id'] ?? '') === $order_id) {
            $customer_copy = $candidate;
            break;
        }
    }
    $all_passed = verify_result(
        'payment status persists across retrieval paths',
        !empty($payment_result['ok'])
            && (string) ($saved_order['payment_status'] ?? '') === 'Paid'
            && (string) ($customer_copy['payment_status'] ?? '') === 'Paid'
    ) && $all_passed;

    $completed_result = backoffice_apply_order_update('admin', $order_id, 'Completed', 'Paid', $verification_actor, false);
    $completed_order = store_get_order_by_id($order_id);
    $all_passed = verify_result(
        'order and payment statuses remain separate',
        !empty($completed_result['ok'])
            && (string) ($completed_order['status'] ?? '') === 'Completed'
            && (string) ($completed_order['payment_status'] ?? '') === 'Paid'
    ) && $all_passed;

    $activity_rows = database_fetch_all(
        $conn,
        "SELECT activity_type FROM activity_logs WHERE user_email = ? AND activity_type IN ('Order', 'Payment')",
        's',
        array($verification_actor['email'])
    );
    $activity_types = array_column($activity_rows, 'activity_type');
    $all_passed = verify_result(
        'order and payment updates are activity logged once',
        count(array_filter($activity_types, function ($type) { return $type === 'Order'; })) === 1
            && count(array_filter($activity_types, function ($type) { return $type === 'Payment'; })) === 1
    ) && $all_passed;

    store_state_write_json('orders', $orders_before);
    database_execute($conn, 'DELETE FROM activity_logs WHERE user_email = ?', 's', array($verification_actor['email']));
    $restored_orders = store_state_get_orders();
    $restored_order = store_get_order_by_id($order_id);
    $all_passed = verify_result(
        'order test restored without duplicates',
        count($restored_orders) === $order_count_before
            && (string) ($restored_order['status'] ?? '') === (string) $order_test['status']
            && (string) ($restored_order['payment_status'] ?? '') === (string) $order_test['payment_status']
    ) && $all_passed;
} else {
    $all_passed = verify_result('payment status persists across retrieval paths', false, 'No unpaid order was available for the test.') && $all_passed;
}

$completed_unpaid = null;
foreach (store_state_get_orders() as $candidate) {
    if ((string) ($candidate['status'] ?? '') === 'Completed'
        && (string) ($candidate['payment_status'] ?? '') === 'Unpaid') {
        $completed_unpaid = $candidate;
        break;
    }
}

if ($completed_unpaid) {
    $orders_snapshot = store_state_get_orders();
    $revenue_before = store_get_completed_revenue();
    $history_before = store_get_product_completed_sales_history((int) $completed_unpaid['product_id']);
    store_state_update_order_workflow((string) $completed_unpaid['id'], 'Completed', 'Paid');
    $revenue_after = store_get_completed_revenue();
    $history_after = store_get_product_completed_sales_history((int) $completed_unpaid['product_id']);
    $all_passed = verify_result(
        'Paid update does not duplicate completed sales or forecast history',
        (float) $revenue_before['revenue'] === (float) $revenue_after['revenue']
            && (int) $revenue_before['completed_orders'] === (int) $revenue_after['completed_orders']
            && $history_before === $history_after
    ) && $all_passed;
    store_state_write_json('orders', $orders_snapshot);
} else {
    $all_passed = verify_result('Paid update does not duplicate completed sales or forecast history', false, 'No completed unpaid order was available for the test.') && $all_passed;
}

$customer = database_get_first_user_by_role($conn, 'customer');
$stock_product = null;
foreach (get_store_products() as $candidate) {
    if ((int) ($candidate['stock'] ?? 0) >= 5 && !empty($candidate['sizes'])) {
        $stock_product = $candidate;
        break;
    }
}

if ($customer && $stock_product) {
    mysqli_begin_transaction($conn);
    $size_standard = 'US';
    $sizes = $stock_product['size_standards'][$size_standard] ?? $stock_product['sizes'];
    $size = (string) reset($sizes);
    database_execute(
        $conn,
        'DELETE FROM cart WHERE user_id = ? AND product_id = ? AND size_standard = ? AND size = ?',
        'iiss',
        array((int) $customer['id'], (int) $stock_product['id'], $size_standard, $size)
    );

    $add_three = store_add_cart_item((int) $customer['id'], (int) $stock_product['id'], 3, $size, $size_standard);
    $reject_excess_add = store_add_cart_item(
        (int) $customer['id'],
        (int) $stock_product['id'],
        (int) $stock_product['stock'],
        $size,
        $size_standard
    );
    $cart_row = database_fetch_one(
        $conn,
        'SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size_standard = ? AND size = ? LIMIT 1',
        'iiss',
        array((int) $customer['id'], (int) $stock_product['id'], $size_standard, $size)
    );
    $all_passed = verify_result(
        'existing cart quantity cannot exceed stock',
        !empty($add_three['ok'])
            && empty($reject_excess_add['ok'])
            && (int) ($cart_row['quantity'] ?? 0) === 3
    ) && $all_passed;

    $set_max = store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], (int) $stock_product['stock']);
    $reject_over_max = store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], (int) $stock_product['stock'] + 1);
    $reject_zero = store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], 0);
    $reject_decimal = store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], '1.5');
    $reject_text = store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], 'abc');
    $all_passed = verify_result(
        'manual cart quantity validation',
        !empty($set_max['ok'])
            && empty($reject_over_max['ok'])
            && empty($reject_zero['ok'])
            && empty($reject_decimal['ok'])
            && empty($reject_text['ok'])
    ) && $all_passed;

    store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], 1);
    $plus_to_two = store_adjust_cart_item_quantity((int) $customer['id'], (int) $cart_row['id'], 'increase');
    $plus_to_three = store_adjust_cart_item_quantity((int) $customer['id'], (int) $cart_row['id'], 'increase');
    $minus_to_two = store_adjust_cart_item_quantity((int) $customer['id'], (int) $cart_row['id'], 'decrease');
    $stepped_row = database_fetch_one($conn, 'SELECT quantity FROM cart WHERE id = ? LIMIT 1', 'i', array((int) $cart_row['id']));
    store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], (int) $stock_product['stock']);
    $plus_at_max = store_adjust_cart_item_quantity((int) $customer['id'], (int) $cart_row['id'], 'increase');
    $max_row = database_fetch_one($conn, 'SELECT quantity FROM cart WHERE id = ? LIMIT 1', 'i', array((int) $cart_row['id']));
    $all_passed = verify_result(
        'quantity plus and minus boundaries',
        !empty($plus_to_two['ok'])
            && !empty($plus_to_three['ok'])
            && !empty($minus_to_two['ok'])
            && (int) ($stepped_row['quantity'] ?? 0) === 2
            && empty($plus_at_max['ok'])
            && (int) ($max_row['quantity'] ?? 0) === (int) $stock_product['stock']
    ) && $all_passed;

    store_update_cart_item((int) $customer['id'], (int) $cart_row['id'], 1);
    $minus_at_one = store_adjust_cart_item_quantity((int) $customer['id'], (int) $cart_row['id'], 'decrease');
    $still_one = database_fetch_one($conn, 'SELECT quantity FROM cart WHERE id = ? LIMIT 1', 'i', array((int) $cart_row['id']));
    $all_passed = verify_result(
        'minus at one does not remove cart item',
        !empty($minus_at_one['ok']) && (int) ($still_one['quantity'] ?? 0) === 1
    ) && $all_passed;

    $reservation_context = array('user_name' => 'Verification Customer', 'user_email' => 'verification.invalid@example.invalid');
    $reservation_values = array((int) $stock_product['stock'] + 1, 0, -1, '1.5', 'abc');
    $reservation_rejected = true;
    foreach ($reservation_values as $invalid_quantity) {
        $result = store_state_create_reservation(
            $reservation_context,
            $stock_product,
            $size,
            $invalid_quantity,
            array('size_standard' => $size_standard)
        );
        $reservation_rejected = $reservation_rejected && empty($result['ok']);
    }
    $all_passed = verify_result('server rejects invalid reservation quantities', $reservation_rejected) && $all_passed;

    $inventory_actor = array(
        'name' => 'Inventory Verification',
        'email' => 'inventory-verification@shoestagram.invalid',
        'role' => 'admin',
    );
    $inventory_logged = database_log_inventory_action(
        $conn,
        (int) $stock_product['id'],
        $inventory_actor,
        'verification',
        (int) $stock_product['base_stock'],
        (int) $stock_product['base_stock'],
        'Transaction-scoped verification event.'
    );
    $inventory_activity = database_fetch_one(
        $conn,
        "SELECT COUNT(*) AS total FROM activity_logs WHERE user_email = ? AND activity_type = 'Inventory'",
        's',
        array($inventory_actor['email'])
    );
    $all_passed = verify_result(
        'inventory changes create activity events',
        $inventory_logged && (int) ($inventory_activity['total'] ?? 0) === 1
    ) && $all_passed;
    mysqli_rollback($conn);
} else {
    $all_passed = verify_result('quantity backend tests', false, 'No customer or product with at least five available units was found.') && $all_passed;
}

$synthetic_out_of_stock = array(
    'id' => 999999,
    'name' => 'Out-of-stock verification product',
    'base_stock' => 0,
    'stock' => 0,
    'price' => 1,
    'sizes' => array('1'),
    'size_standards' => array('US' => array('1')),
);
$out_of_stock_result = store_state_create_reservation(
    array('user_name' => 'Verification Customer', 'user_email' => 'verification.invalid@example.invalid'),
    $synthetic_out_of_stock,
    '1',
    1,
    array('size_standard' => 'US')
);
$all_passed = verify_result('out-of-stock reservation rejected server-side', empty($out_of_stock_result['ok'])) && $all_passed;

$root = dirname(__DIR__);
$static_checks = array(
    'Admin payment field' => strpos(file_get_contents($root . '/admin/reservations.php'), 'name="payment_status"') !== false,
    'Staff payment field' => strpos(file_get_contents($root . '/staff/reservations.php'), 'name="payment_status"') !== false,
    'Product quantity input' => strpos(file_get_contents($root . '/customer/product.php'), 'id="quantity" name="quantity"') !== false,
    'Product quantity dropdown removed' => strpos(file_get_contents($root . '/customer/product.php'), '<select id="quantity"') === false,
    'Password pair alignment class' => strpos(file_get_contents($root . '/login/register.php'), 'password-pair-grid') !== false,
    'Admin activity navigation' => strpos(file_get_contents($root . '/backoffice-layout.php'), "'activity-log'") !== false,
);
foreach ($static_checks as $name => $passed) {
    $all_passed = verify_result($name, $passed) && $all_passed;
}

exit($all_passed ? 0 : 1);
