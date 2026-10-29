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
                                    <th>Items</th>
                                    <th>Amount (PHP)</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Reserved On</th>
                                    <th>Details / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reservation_orders)): ?><tr><td colspan="8">No matching records found.</td></tr><?php endif; ?>
                                <?php foreach ($reservation_orders as $order): ?>
                                    <?php $reserved_at = backoffice_manila_datetime($order['created_at'] ?? '', true); ?>
                                    <?php $updated_at = backoffice_manila_datetime($order['updated_at'] ?? '', true); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['checkout_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo count($order['items']); ?> item<?php echo count($order['items']) === 1 ? '' : 's'; ?><br><small><?php echo array_sum(array_map(function ($item) { return (int) $item['quantity']; }, $order['items'])); ?> units</small></td>
                                        <td><?php echo number_format((float) $order['subtotal'], 2); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($order['payment_status'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>"><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php if ($reserved_at): ?><?php echo htmlspecialchars($reserved_at->format('M j, Y'), ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($reserved_at->format('g:i A'), ENT_QUOTES, 'UTF-8'); ?></small><?php else: ?>Date unavailable<?php endif; ?></td>
                                        <td>
                                            <details class="table-details reservation-details" data-detail-title="Reservation and payment details">
                                                <summary>View / Update</summary>
                                                <div>
                                                    <p><strong>Reservation ID:</strong> <?php echo htmlspecialchars($order['checkout_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p><strong>Products:</strong></p>
                                                    <ul class="backoffice-detail-item-list">
                                                        <?php foreach ($order['items'] as $item): ?><li><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(store_order_size_display($item), ENT_QUOTES, 'UTF-8'); ?> × <?php echo (int) $item['quantity']; ?></li><?php endforeach; ?>
                                                    </ul>
                                                    <p><strong>Amount:</strong> <?php echo htmlspecialchars(store_format_money((float) $order['subtotal']), ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p><strong>Payment method:</strong> <?php echo htmlspecialchars((string) ($order['payment_method'] ?? 'In-Store Payment'), ENT_QUOTES, 'UTF-8'); ?><br><strong>Payment status:</strong> <?php echo htmlspecialchars((string) ($order['payment_status'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <?php if (!empty($order['payment_reference'])): ?><p><strong>Payment reference:</strong> <?php echo htmlspecialchars((string) $order['payment_reference'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                                    <?php if (!empty($order['payment_proof'])): ?><p><a class="proof-link" href="<?php echo htmlspecialchars('../customer/uploads/payment-proofs/' . rawurlencode(basename((string) $order['payment_proof'])), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View payment proof</a></p><?php endif; ?>
                                                    <?php if (!empty($order['payment_note'])): ?><p><strong>Payment note:</strong> <?php echo nl2br(htmlspecialchars((string) $order['payment_note'], ENT_QUOTES, 'UTF-8')); ?></p><?php endif; ?>
                                                    <p><strong>Reservation status:</strong> <?php echo htmlspecialchars((string) $order['status'], ENT_QUOTES, 'UTF-8'); ?><br><strong>Reserved:</strong> <?php echo $reserved_at ? htmlspecialchars($reserved_at->format('F j, Y · g:i A'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></p>
                                                    <?php if ($updated_at): ?><p><strong>Last updated:</strong> <?php echo htmlspecialchars($updated_at->format('F j, Y · g:i A'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                                    <?php if (!empty($order['pickup_window'])): ?><p><strong>Pickup or completion:</strong> <?php echo htmlspecialchars((string) $order['pickup_window'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                                    <h3>Update status</h3>
                                            <form method="POST" class="reservation-update-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="admin_action" value="update_order">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <label>Reservation status
                                                <select name="status">
                                                    <?php foreach (backoffice_allowed_statuses('staff', (string) $order['status']) as $status_option): ?>
                                                        <option value="<?php echo htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $status_option === $order['status'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                <label>Payment status
                                                <select name="payment_status" aria-label="Payment status for <?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php foreach (store_state_payment_statuses() as $payment_option): ?>
                                                        <option value="<?php echo htmlspecialchars($payment_option, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $payment_option === ($order['payment_status'] ?? '') ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($payment_option, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                <button type="submit" class="button button-dark">Save</button>
                                            </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($reservation_pagination); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
