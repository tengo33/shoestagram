<?php
/** Isolated checkout regression test; never writes to the live orders file. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
$source = dirname(__DIR__) . '/database/store_state.php';
$temp = sys_get_temp_dir() . '/shoestagram-checkout-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0700) || !copy($source, $temp . '/store_state.php')) {
    fwrite(STDERR, "Could not create isolated test directory.\n");
    exit(1);
}

require $temp . '/store_state.php';
require dirname(__DIR__) . '/print-document.php';

$failures = array();
$check = function ($label, $condition) use (&$failures) {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) {
        $failures[] = $label;
    }
};

try {
    $legacy = store_state_get_orders()[0];
    $legacy_pdf = shoestagram_build_reservation_receipt_pdf($legacy);
    $check('legacy single-item receipt remains supported', strpos($legacy_pdf, (string) $legacy['product_name']) !== false
        && strpos($legacy_pdf, number_format((float) $legacy['subtotal'], 2)) !== false);
    $products = array(
        array('id' => 90001, 'name' => 'Nike Air Force 1', 'brand' => 'Nike', 'price' => 7500.0, 'base_stock' => 10, 'stock' => 10, 'sizes' => array('9')),
        array('id' => 90002, 'name' => 'New Balance 530', 'brand' => 'New Balance', 'price' => 6500.0, 'base_stock' => 10, 'stock' => 10, 'sizes' => array('9')),
        array('id' => 90003, 'name' => 'Adidas Shirt', 'brand' => 'Adidas', 'price' => 500.0, 'base_stock' => 10, 'stock' => 10, 'sizes' => array('9')),
    );
    $context = array('user_name' => 'Test Customer', 'user_email' => 'test@example.invalid');
    $make_cart = function ($quantities) use ($products) {
        $cart = array();
        foreach ($quantities as $index => $quantity) {
            $cart[] = array('cart_id' => $index + 1, 'product' => $products[$index], 'quantity' => $quantity, 'size' => '9', 'size_standard' => 'US');
        }
        return $cart;
    };
    $first = store_state_create_cart_checkout($context, $make_cart(array(1, 1, 1)), array('payment_method' => 'In-Store Payment'));
    $check('three-item checkout created', !empty($first['ok']) && count($first['orders'] ?? array()) === 3);
    $reference = $first['checkout_id'] ?? '';
    $ids = array_column($first['orders'] ?? array(), 'id');
    $check('one reference and unique item IDs', $reference !== '' && count(array_unique($ids)) === 3
        && count(array_unique(array_column($first['orders'], 'checkout_id'))) === 1);
    $items = store_state_checkout_items($ids[0] ?? '');
    $check('checkout lookup returns every item', count($items) === 3);
    $check('three-item total', abs(array_sum(array_column($items, 'subtotal')) - 14500.0) < 0.001);
    $before_pdf = hash_file('sha256', store_state_file_path('orders'));
    $pdf = shoestagram_build_reservation_receipt_pdf($items[0]);
    $after_pdf = hash_file('sha256', store_state_file_path('orders'));
    $check('PDF includes all three products and total', str_starts_with($pdf, '%PDF-')
        && strpos($pdf, 'Nike Air Force 1') !== false
        && strpos($pdf, 'New Balance 530') !== false
        && strpos($pdf, 'Adidas Shirt') !== false
        && strpos($pdf, '14,500.00') !== false);
    $historical_order = $items[0];
    $historical_order['created_at'] = '2026-09-05T14:45:00+08:00';
    $historical_pdf = shoestagram_build_reservation_receipt_pdf($historical_order);
    $check('PDF preserves original transaction time separately from generation',
        strpos($historical_pdf, 'September 5, 2026 - 2:45 PM') !== false
        && strpos($historical_pdf, 'Generated ') !== false);
    $check('receipt does not mutate orders or inventory', hash_equals($before_pdf, $after_pdf));
    $check('initial stock locked per item', store_state_get_product_metrics()[90001]['locked_units'] === 1
        && store_state_get_product_metrics()[90002]['locked_units'] === 1
        && store_state_get_product_metrics()[90003]['locked_units'] === 1);
    $check('workflow update applies to entire checkout', store_state_update_order_workflow($ids[0], 'Completed', 'Paid')
        && count(array_filter(store_state_checkout_items($ids[0]), function ($item) {
            return $item['status'] === 'Completed' && $item['payment_status'] === 'Paid';
        })) === 3);
    $second = store_state_create_cart_checkout($context, $make_cart(array(3, 2)), array('payment_method' => 'In-Store Payment'));
    $second_items = store_state_checkout_items($second['orders'][0]['id'] ?? '');
    $check('multi-quantity checkout totals', !empty($second['ok']) && count($second_items) === 2
        && abs(array_sum(array_column($second_items, 'subtotal')) - 35500.0) < 0.001);
    $second_pdf = shoestagram_build_reservation_receipt_pdf($second_items[0]);
    $check('multi-quantity PDF totals', strpos($second_pdf, '22,500.00') !== false
        && strpos($second_pdf, '13,000.00') !== false && strpos($second_pdf, '35,500.00') !== false);
    store_state_update_order_workflow($second['orders'][0]['id'], 'Completed', 'Paid');
    $metrics = store_state_get_product_metrics();
    $check('inventory counts each completed item exactly once', $metrics[90001]['completed_units'] === 4
        && $metrics[90002]['completed_units'] === 3 && $metrics[90003]['completed_units'] === 1);
    store_state_update_order_workflow($second['orders'][0]['id'], 'Completed', 'Paid');
    $metrics = store_state_get_product_metrics();
    $check('repeat status update does not double-deduct', $metrics[90001]['completed_units'] === 4
        && $metrics[90002]['completed_units'] === 3);
    $orders_before_invalid = count(store_state_get_orders());
    $invalid = store_state_create_cart_checkout($context, $make_cart(array(1, 99)), array());
    $check('invalid cart is rejected atomically', empty($invalid['ok']) && count(store_state_get_orders()) === $orders_before_invalid);
    $pending = store_state_create_cart_checkout($context, $make_cart(array(1, 1)), array());
    $cancelled = store_state_cancel_customer_order($pending['orders'][0]['id'], $context['user_email']);
    $check('customer cancellation applies to all checkout items', !empty($cancelled['ok'])
        && count(array_filter(store_state_checkout_items($pending['orders'][0]['id']), function ($item) {
            return $item['status'] === 'Cancelled';
        })) === 2);
    $many_orders = store_state_get_orders();
    for ($index = 1; $index <= 24; $index++) {
        $line = $items[0];
        $line['id'] = 'TEST-MANY-' . $index;
        $line['checkout_id'] = 'TEST-MANY';
        $line['product_id'] = 91000 + $index;
        $line['product_name'] = 'Test Item ' . $index;
        $line['price_each'] = 1.0;
        $line['subtotal'] = 1.0;
        $many_orders[] = $line;
    }
    store_state_write_json('orders', $many_orders);
    $many_pdf = shoestagram_build_reservation_receipt_pdf($many_orders[count($many_orders) - 24]);
    $check('long receipt continues across pages without losing items', strpos($many_pdf, 'Test Item 24') !== false
        && strpos($many_pdf, '/Count 3') !== false && strpos($many_pdf, '24.00') !== false);
    $check('Philippine timestamp conversion', shoestagram_pdf_date_label('2026-10-01T06:45:00+00:00', true) === 'October 1, 2026 - 2:45 PM');
    $check('missing timestamp is not replaced with download time', shoestagram_pdf_date_label('', true) === '');
} finally {
    foreach (glob($temp . '/runtime/*') ?: array() as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($temp . '/runtime')) {
        rmdir($temp . '/runtime');
    }
    unlink($temp . '/store_state.php');
    rmdir($temp);
}

exit($failures ? 1 : 0);
