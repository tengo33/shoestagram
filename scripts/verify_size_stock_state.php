<?php
/* Read-only, database-free checks for size allocation and order lifecycle. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$header = null;
$rows = array();
$orders = array();

function store_normalize_size_standard($value) { return strtoupper(trim((string) $value)); }
function store_db_connection() { return null; }
function database_fetch_one($connection, $query, $types = '', $params = array()) { return $GLOBALS['header']; }
function database_fetch_all($connection, $query, $types = '', $params = array()) { return $GLOBALS['rows']; }
function store_state_get_orders() { return $GLOBALS['orders']; }
function store_state_order_affects_live_inventory($order) { return true; }
function store_state_is_active_reservation($status) { return in_array($status, array('Pending', 'Confirmed', 'Ready for Pickup'), true); }
function get_store_product_by_id($id) { return $GLOBALS['product']; }

require_once dirname(__DIR__) . '/database/size_stock.php';

function check_size_stock($label, $valid)
{
    if (!$valid) {
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

$product = array(
    'id' => 42, 'base_stock' => 10, 'stock' => 10,
    'size_standards' => array('US' => array('6', '7'), 'EU' => array('39', '40'), 'CN' => array('40', '41')),
);
check_size_stock('no allocation does not invent per-size quantities',
    store_size_stock_state($product)['rows'] === array() && store_size_stock_available($product, 'US', '6') === null);

$header = array(
    'base_stock_snapshot' => 10,
    'total_stock_snapshot' => 10,
    'size_signature' => store_size_stock_signature($product),
    'primary_standard' => 'US',
    'configured_at' => '2026-10-26T10:00:00.000000+08:00',
);
$rows = array(
    array('size_index' => 0, 'size_label' => '6', 'quantity' => 4),
    array('size_index' => 1, 'size_label' => '7', 'quantity' => 6),
);
$orders = array(array(
    'product_id' => 42, 'size_standard' => 'EU', 'size' => '39', 'quantity' => 2,
    'status' => 'Pending', 'created_at' => '2026-10-26T10:00:01.000000+08:00',
));
$product['stock'] = 8;
$state = store_size_stock_state($product);
check_size_stock('reservation deducts the mapped size and reconciles to total',
    $state['configured'] && $state['rows'][0]['quantity'] === 2
    && $state['rows'][1]['quantity'] === 6 && array_sum(array_column($state['rows'], 'quantity')) === 8);
$orders[0]['status'] = 'Completed';
check_size_stock('completion does not deduct twice', store_size_stock_state($product)['rows'][0]['quantity'] === 2);
$orders[0]['status'] = 'Cancelled';
$product['stock'] = 10;
check_size_stock('cancellation restores the selected size', store_size_stock_state($product)['rows'][0]['quantity'] === 4);
$rows[0]['quantity'] = 0;
$rows[1]['quantity'] = 10;
check_size_stock('zero stock is reported for equivalent US and EU sizes',
    store_size_stock_available($product, 'US', '6') === 0
    && store_size_stock_available($product, 'EU', '39') === 0);
$product['base_stock'] = 11;
check_size_stock('an unallocated stock adjustment pauses size orders',
    !store_size_stock_state($product)['configured'] && store_size_stock_available($product, 'US', '7') === 0);
check_size_stock('size allocation rejects totals that do not match product stock',
    !store_save_size_stock_allocation(42, array('quantities' => array(4, 5)))['ok']);
check_size_stock('size allocation rejects negative quantities',
    !store_save_size_stock_allocation(42, array('quantities' => array(-1, 11)))['ok']);
