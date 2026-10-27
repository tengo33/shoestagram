<?php

/* Size quantities are an optional allocation of the existing live product total.
 * The total remains governed by products.base_stock and the order lifecycle. */

function store_size_stock_primary_standard($product)
{
    foreach (array('US', 'EU', 'CN') as $standard) {
        if (!empty($product['size_standards'][$standard])) {
            return $standard;
        }
    }
    return '';
}

function store_size_stock_signature($product)
{
    return hash('sha256', json_encode($product['size_standards'] ?? array(), JSON_UNESCAPED_UNICODE));
}

function store_size_stock_variant_index($product, $standard, $size)
{
    $standard = store_normalize_size_standard($standard);
    $primary = store_size_stock_primary_standard($product);
    $primary_sizes = (array) ($product['size_standards'][$primary] ?? array());
    $sizes = (array) ($product['size_standards'][$standard] ?? array());
    if (!$primary_sizes || count($sizes) !== count($primary_sizes)) {
        return null;
    }
    $index = array_search((string) $size, array_map('strval', $sizes), true);
    return $index === false ? null : (int) $index;
}

function store_size_stock_state($product, $orders = null)
{
    $product_id = (int) ($product['id'] ?? 0);
    $primary = store_size_stock_primary_standard($product);
    $empty = array('configured' => false, 'allocation_exists' => false, 'reason' => 'Size allocation is not configured.', 'standard' => $primary, 'rows' => array());
    if ($product_id <= 0 || $primary === '') {
        $empty['reason'] = 'Sizes are not applicable to this product.';
        return $empty;
    }

    $connection = store_db_connection();
    $header = database_fetch_one($connection, 'SELECT * FROM product_size_allocations WHERE product_id = ?', 'i', array($product_id));
    if (!$header) {
        return $empty;
    }

    $empty['allocation_exists'] = true;
    $empty['reason'] = 'Size allocation needs reconciliation. Orders for this product are paused until an administrator configures its current size stock.';
    if ((int) $header['base_stock_snapshot'] !== (int) ($product['base_stock'] ?? 0)
        || (string) $header['size_signature'] !== store_size_stock_signature($product)
        || (string) $header['primary_standard'] !== $primary) {
        return $empty;
    }

    $primary_sizes = array_values((array) $product['size_standards'][$primary]);
    foreach ((array) ($product['size_standards'] ?? array()) as $sizes) {
        if ($sizes && count((array) $sizes) !== count($primary_sizes)) {
            return $empty;
        }
    }

    $saved_rows = database_fetch_all($connection, 'SELECT size_index, size_label, quantity FROM product_size_stock WHERE product_id = ? ORDER BY size_index', 'i', array($product_id));
    if (count($saved_rows) !== count($primary_sizes)) {
        return $empty;
    }
    $remaining = array();
    foreach ($saved_rows as $index => $row) {
        if ((int) $row['size_index'] !== $index || (string) $row['size_label'] !== (string) $primary_sizes[$index] || (int) $row['quantity'] < 0) {
            return $empty;
        }
        $remaining[$index] = (int) $row['quantity'];
    }
    if (array_sum($remaining) !== (int) $header['total_stock_snapshot']) {
        return $empty;
    }

    try {
        $configured_at = new DateTimeImmutable((string) $header['configured_at']);
    } catch (Exception $error) {
        return $empty;
    }
    foreach ($orders === null ? store_state_get_orders() : $orders as $order) {
        if ((int) ($order['product_id'] ?? 0) !== $product_id || !store_state_order_affects_live_inventory($order)) {
            continue;
        }
        $status = (string) ($order['status'] ?? 'Pending');
        if ($status !== 'Completed' && !store_state_is_active_reservation($status)) {
            continue;
        }
        $created_at = trim((string) ($order['created_at'] ?? ''));
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $created_at)) {
            return $empty;
        }
        try {
            $order_created_at = new DateTimeImmutable($created_at);
        } catch (Exception $error) {
            return $empty;
        }
        if ($order_created_at <= $configured_at) {
            continue;
        }
        $index = store_size_stock_variant_index($product, $order['size_standard'] ?? 'US', $order['size'] ?? '');
        if ($index === null) {
            return $empty;
        }
        $remaining[$index] -= max(1, (int) ($order['quantity'] ?? 1));
    }

    if (min($remaining) < 0 || array_sum($remaining) !== (int) ($product['stock'] ?? 0)) {
        return $empty;
    }

    $rows = array();
    foreach ($primary_sizes as $index => $size) {
        $rows[] = array('size' => (string) $size, 'quantity' => $remaining[$index], 'index' => $index);
    }
    return array('configured' => true, 'allocation_exists' => true, 'reason' => '', 'standard' => $primary, 'rows' => $rows);
}

function store_size_stock_available($product, $standard, $size)
{
    $state = store_size_stock_state($product);
    if (!$state['configured']) {
        return !empty($state['allocation_exists']) ? 0 : null;
    }
    $index = store_size_stock_variant_index($product, $standard, $size);
    return $index === null ? 0 : (int) ($state['rows'][$index]['quantity'] ?? 0);
}

function store_size_stock_availability_map($product)
{
    $state = store_size_stock_state($product);
    if (!$state['configured'] && empty($state['allocation_exists'])) {
        return null;
    }
    $map = array();
    foreach ((array) $product['size_standards'] as $standard => $sizes) {
        foreach ((array) $sizes as $index => $size) {
            $map[$standard][(string) $size] = $state['configured'] ? (int) ($state['rows'][$index]['quantity'] ?? 0) : 0;
        }
    }
    return $map;
}

function store_save_size_stock_allocation($product_id, $input)
{
    $product = get_store_product_by_id((int) $product_id);
    if (!$product) {
        return array('ok' => false, 'message' => 'Product not found.');
    }
    $primary = store_size_stock_primary_standard($product);
    $sizes = array_values((array) ($product['size_standards'][$primary] ?? array()));
    if (!$sizes) {
        return array('ok' => false, 'message' => 'This product has no configurable sizes.');
    }
    foreach ((array) $product['size_standards'] as $standard_sizes) {
        if ($standard_sizes && count((array) $standard_sizes) !== count($sizes)) {
            return array('ok' => false, 'message' => 'Size standards have different numbers of sizes. Align their options before allocating stock.');
        }
    }
    $submitted = $input['quantities'] ?? null;
    if (!is_array($submitted) || count($submitted) !== count($sizes)) {
        return array('ok' => false, 'message' => 'Enter a whole-number quantity for every available size.');
    }
    $quantities = array();
    foreach ($sizes as $index => $size) {
        $value = $submitted[$index] ?? null;
        if (!is_scalar($value) || !preg_match('/^\d+$/', trim((string) $value))) {
            return array('ok' => false, 'message' => 'Size quantities must be non-negative whole numbers.');
        }
        $quantity = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
        if ($quantity === false) {
            return array('ok' => false, 'message' => 'A size quantity is too large.');
        }
        $quantities[$index] = (int) $quantity;
    }
    if (array_sum($quantities) !== (int) $product['stock']) {
        return array('ok' => false, 'message' => 'Allocated sizes must add up to the current available stock of ' . (int) $product['stock'] . '. No stock was changed.');
    }

    $before = store_size_stock_state($product);
    $changes = array();
    foreach ($sizes as $index => $size) {
        $old = $before['configured'] ? (string) ($before['rows'][$index]['quantity'] ?? 0) : 'unallocated';
        $changes[] = $primary . ' ' . $size . ': ' . $old . ' → ' . $quantities[$index];
    }
    $connection = store_db_connection();
    if (!mysqli_begin_transaction($connection)) {
        return array('ok' => false, 'message' => 'Size stock could not be saved. Please try again.');
    }
    $locked = database_fetch_one($connection, 'SELECT base_stock, size_standards FROM products WHERE id = ? FOR UPDATE', 'i', array((int) $product_id));
    $locked_standards = $locked ? store_normalize_size_standards($locked['size_standards'] ?? '', $product['sizes'], $product['category']) : array();
    if (!$locked || (int) $locked['base_stock'] !== (int) $product['base_stock']
        || store_size_stock_signature(array('size_standards' => $locked_standards)) !== store_size_stock_signature($product)) {
        mysqli_rollback($connection);
        return array('ok' => false, 'message' => 'Product stock or sizes changed. Reload and review the allocation.');
    }
    $configured_at = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d\TH:i:s.uP');
    $ok = database_execute($connection,
        'INSERT INTO product_size_allocations (product_id, primary_standard, size_signature, base_stock_snapshot, total_stock_snapshot, configured_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE primary_standard = VALUES(primary_standard), size_signature = VALUES(size_signature), base_stock_snapshot = VALUES(base_stock_snapshot), total_stock_snapshot = VALUES(total_stock_snapshot), configured_at = VALUES(configured_at)',
        'issiis', array((int) $product_id, $primary, store_size_stock_signature($product), (int) $product['base_stock'], (int) $product['stock'], $configured_at));
    $ok = $ok && database_execute($connection, 'DELETE FROM product_size_stock WHERE product_id = ?', 'i', array((int) $product_id));
    foreach ($sizes as $index => $size) {
        if (!$ok) {
            break;
        }
        $ok = database_execute($connection, 'INSERT INTO product_size_stock (product_id, size_index, size_label, quantity) VALUES (?, ?, ?, ?)',
            'iisi', array((int) $product_id, (int) $index, (string) $size, $quantities[$index]));
    }
    if ($ok) {
        $ok = database_log_activity($connection, store_inventory_actor(), 'Inventory',
            'Updated size stock for ' . $product['name'] . '. ' . implode('; ', $changes) . '.', 'Success');
    }
    if (!$ok || !mysqli_commit($connection)) {
        mysqli_rollback($connection);
        return array('ok' => false, 'message' => 'Size stock could not be saved. No allocation was changed.');
    }

    return array('ok' => true, 'message' => 'Size stock saved. The product total remains ' . (int) $product['stock'] . '.');
}
