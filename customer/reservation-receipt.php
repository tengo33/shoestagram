<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../store-data.php';
require_once '../print-document.php';
require_once 'storefront-layout.php';

storefront_require_login('account.php#orders');

$context = storefront_get_context();
$order_id = trim((string) ($_GET['order_id'] ?? ''));
$order = store_get_order_by_id($order_id);

if (!$order || strcasecmp((string) ($order['customer_email'] ?? ''), (string) ($context['user_email'] ?? '')) !== 0) {
    storefront_set_flash('error', 'That reservation receipt is not available for this account.');
    storefront_redirect('account.php#orders');
}

$filename = 'shoestagram-receipt-' . preg_replace('/[^A-Za-z0-9_-]/', '-', store_state_checkout_reference($order)) . '.pdf';
$pdf = shoestagram_build_reservation_receipt_pdf($order, shoestagram_support_email());
shoestagram_pdf_output($filename, $pdf);
