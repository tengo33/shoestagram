<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../store-data.php';
require_once 'storefront-layout.php';

$redirect = storefront_safe_redirect_target($_POST['redirect'] ?? 'cart.php', 'cart.php');

storefront_require_login($redirect);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
    storefront_set_flash('error', 'Your session expired. Please try again.');
    storefront_redirect($redirect);
}

$user_id = (int) $_SESSION['user_id'];
$action = trim((string) ($_POST['action'] ?? 'add'));
$ok = false;
$message = 'Cart could not be updated.';

if ($action === 'add') {
    $result = store_add_cart_item(
        $user_id,
        (int) ($_POST['product_id'] ?? 0),
        $_POST['quantity'] ?? '',
        trim((string) ($_POST['size'] ?? '')),
        trim((string) ($_POST['size_standard'] ?? 'US'))
    );
    $ok = !empty($result['ok']);
    $message = (string) ($result['message'] ?? $message);
} elseif ($action === 'update') {
    $cart_id = (int) ($_POST['cart_id'] ?? 0);
    $step = trim((string) ($_POST['quantity_step'] ?? ''));
    $result = in_array($step, array('increase', 'decrease'), true)
        ? store_adjust_cart_item_quantity($user_id, $cart_id, $step)
        : store_update_cart_item($user_id, $cart_id, $_POST['quantity'] ?? '');
    $ok = !empty($result['ok']);
    $message = (string) ($result['message'] ?? $message);
} elseif ($action === 'remove') {
    $ok = store_remove_cart_item($user_id, (int) ($_POST['cart_id'] ?? 0));
    $message = $ok ? 'Item removed from your cart.' : $message;
} elseif ($action === 'clear') {
    $ok = store_clear_cart($user_id);
    $message = $ok ? 'Cart cleared.' : $message;
} elseif ($action === 'reserve_all') {
    $context = storefront_get_context();
    $cart_items = store_get_cart_items($user_id);
    
    if (empty($cart_items)) {
        $message = 'Your cart is empty. Add items before reserving.';
    } else {
        $errors = [];
        $payment_method = store_state_normalize_payment_method($_POST['payment_method'] ?? 'In-Store Payment');
        $payment_reference = trim((string) ($_POST['payment_reference'] ?? ''));
        $proof_upload = array('ok' => true, 'path' => '', 'original_name' => '');

        if ($payment_method === 'Online Payment' && $payment_reference === '') {
            $errors[] = 'Enter the GCash reference number for online payment verification.';
        }

        if (empty($errors) && $payment_method === 'Online Payment') {
            $proof_upload = store_handle_payment_proof_upload('payment_proof', true);

            if (empty($proof_upload['ok'])) {
                $errors[] = (string) ($proof_upload['message'] ?? 'The proof of payment image could not be uploaded.');
            }
        }
        
        if (empty($errors)) {
                $result = store_state_create_cart_checkout($context, $cart_items, array(
                    'payment_method' => $payment_method,
                    'payment_status' => $payment_method === 'Online Payment' ? 'Pending' : 'Unpaid',
                    'payment_reference' => $payment_reference,
                    'payment_proof' => $payment_method === 'Online Payment' ? ($proof_upload['path'] ?? '') : '',
                    'payment_proof_name' => $payment_method === 'Online Payment' ? ($proof_upload['original_name'] ?? '') : '',
                    'payment_note' => $payment_method === 'Online Payment'
                        ? 'Customer submitted a GCash reference and proof image for manual verification.'
                        : 'Customer chose to pay in store.',
                    'transaction_type' => 'ONLINE',
                    'channel' => 'Website reservation',
                ));
                if (!empty($result['ok'])) {
                    foreach ($cart_items as $item) {
                        store_remove_cart_item($user_id, $item['cart_id']);
                    }
                    store_send_order_email($result['orders'][0], 'Shoestagram reservation ' . $result['checkout_id'], 'Your reservation has been received. Download the receipt from your account for the complete item list.');
                    $ok = true;
                    $message = 'Successfully reserved ' . count($result['orders']) . ' item(s) under ' . $result['checkout_id'] . '. Check your account.';
                } else {
                    $errors[] = $result['message'] ?? 'The checkout could not be saved.';
                }
        }

        if (!$ok && !empty($proof_upload['path'])) {
            $uploaded_path = store_payment_proof_absolute_path($proof_upload['path']);

            if ($uploaded_path !== '' && is_file($uploaded_path)) {
                unlink($uploaded_path);
            }
        }
        
        if (!empty($errors)) {
            $message = implode('; ', $errors);
        }
    }
}

storefront_set_flash($ok ? 'success' : 'error', $message);
storefront_redirect($redirect);
