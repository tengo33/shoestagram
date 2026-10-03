<?php
require_once '../store-data.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = get_store_product_by_id($product_id);

if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit();
}

echo json_encode([
    'id' => $product['id'],
    'name' => $product['name'],
    'price' => store_format_money($product['price']),
    'regular_price' => store_format_money($product['regular_price'] ?? $product['price']),
    'promotion' => $product['promotion'] ?? null,
    'image' => $product['image'],
    'sizes' => $product['sizes'],
    'size_standards' => $product['size_standards'] ?? array('US' => $product['sizes']),
    'size_standard_labels' => store_size_standard_labels(),
    'stock' => (int) $product['stock']
]);
