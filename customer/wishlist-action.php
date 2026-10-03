<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$redirect = storefront_safe_redirect_target($_POST['redirect'] ?? 'wishlist.php', 'wishlist.php');

storefront_require_login($redirect);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
    storefront_set_flash('error', 'Your session expired. Please try again.');
    storefront_redirect($redirect);
}

$user_id = (int) $_SESSION['user_id'];
$action = trim((string) ($_POST['action'] ?? 'toggle'));
$product_id = (int) ($_POST['product_id'] ?? 0);
$ok = false;
$message = 'Wishlist could not be updated.';

if ($action === 'clear') {
    $ok = store_clear_wishlist($user_id);
    $message = $ok ? 'Wishlist cleared.' : $message;
} elseif ($action === 'remove') {
    $ok = store_remove_wishlist_item($user_id, $product_id);
    $message = $ok ? 'Product removed from your wishlist.' : $message;
} elseif ($action === 'move_to_cart') {
    $product = get_store_product_by_id($product_id);
    $message = $product
        ? 'Open ' . $product['name'] . ' and choose a size standard before adding it to your cart.'
        : 'Choose a size standard on the product page before adding this item to your cart.';
} else {
    $result = store_toggle_wishlist_item($user_id, $product_id);
    $ok = !empty($result['ok']);
    $message = (string) ($result['message'] ?? $message);
}

storefront_set_flash($ok ? 'success' : 'error', $message);
storefront_redirect($redirect);
