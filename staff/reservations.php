<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
backoffice_handle_order_update('staff');
$stats = backoffice_get_stats($conn);
$reservation_orders = store_group_checkout_orders($stats['orders']);
$reservation_pagination = backoffice_paginate_rows($reservation_orders);

backoffice_render_head('Shoestagram | Staff Reservations');
backoffice_render_shell_start(
    'staff',
    'reservations',
    'Reservation operations',
    'Staff can review the same live reservation queue and update statuses as needed.',
    'This module mirrors the admin reservation table so staff can move orders forward without switching panels.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to dashboard', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <article class="table-card surface-card">
                    <span class="eyebrow">Reservation queue</span>
                    <h2 class="display-font">Live reservation workflow.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Size / Qty</th>
                                    <th>Amount (PHP)</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservation_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['checkout_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><details class="table-details"><summary><?php echo count($order['items']); ?> item<?php echo count($order['items']) === 1 ? '' : 's'; ?> — View</summary><div><?php foreach ($order['items'] as $item): ?><p><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(store_order_size_display($item), ENT_QUOTES, 'UTF-8'); ?> × <?php echo (int) $item['quantity']; ?></p><?php endforeach; ?></div></details></td>
                                        <td><?php echo array_sum(array_map(function ($item) { return (int) $item['quantity']; }, $order['items'])); ?> units</td>
                                        <td><?php echo number_format((float) $order['subtotal'], 2); ?></td>
                                        <td>
                                            <div class="payment-detail-list">
                                                <strong><?php echo htmlspecialchars((string) ($order['payment_method'] ?? 'In-Store Payment'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span><?php echo htmlspecialchars((string) ($order['payment_status'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if (!empty($order['payment_reference'])): ?>
                                                    <span>Ref: <?php echo htmlspecialchars((string) $order['payment_reference'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($order['payment_proof'])): ?>
                                                    <a class="proof-link" href="<?php echo htmlspecialchars('../customer/uploads/payment-proofs/' . rawurlencode(basename((string) $order['payment_proof'])), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View proof</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>"><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="admin_action" value="update_order">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <select name="status">
                                                    <?php foreach (backoffice_allowed_statuses('staff', (string) $order['status']) as $status_option): ?>
                                                        <option value="<?php echo htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $status_option === $order['status'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="payment_status" aria-label="Payment status for <?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php foreach (store_state_payment_statuses() as $payment_option): ?>
                                                        <option value="<?php echo htmlspecialchars($payment_option, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $payment_option === ($order['payment_status'] ?? '') ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($payment_option, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="button button-dark">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($reservation_pagination); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
