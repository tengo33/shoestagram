<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
backoffice_handle_order_update('admin');
$stats = backoffice_get_stats($conn);
$reservation_orders = store_group_checkout_orders($stats['orders']);
$reservation_filters = array(
    'search' => trim((string) ($_GET['search'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'payment_status' => trim((string) ($_GET['payment_status'] ?? '')),
    'reserved_date' => trim((string) ($_GET['reserved_date'] ?? '')),
);
$reservation_orders = array_values(array_filter($reservation_orders, function ($order) use ($reservation_filters) {
    if ($reservation_filters['status'] !== '' && (string) ($order['status'] ?? '') !== $reservation_filters['status']) {
        return false;
    }
    if ($reservation_filters['payment_status'] !== '' && (string) ($order['payment_status'] ?? '') !== $reservation_filters['payment_status']) {
        return false;
    }
    $reserved_at = backoffice_manila_datetime($order['created_at'] ?? '', true);
    if ($reservation_filters['reserved_date'] !== '' && (!$reserved_at || $reserved_at->format('Y-m-d') !== $reservation_filters['reserved_date'])) {
        return false;
    }
    if ($reservation_filters['search'] !== '') {
        $haystack = (string) ($order['checkout_id'] ?? '') . ' ' . (string) ($order['customer_name'] ?? '') . ' ' . (string) ($order['customer_email'] ?? '');
        foreach ($order['items'] ?? array() as $item) {
            $haystack .= ' ' . (string) ($item['product_name'] ?? '');
        }
        if (stripos($haystack, $reservation_filters['search']) === false) {
            return false;
        }
    }
    return true;
}));
$reservation_pagination = backoffice_paginate_rows($reservation_orders, 'page', 10);

backoffice_render_head('Shoestagram | Admin Reservations');
backoffice_render_shell_start(
    'admin',
    'reservations',
    'Reservation management',
    'Review reservations, payments, and pickup progress in one queue.',
    'Reservations are reflected in real-time stock calculations, so this page affects both customer availability and the forecasting model immediately after each status change.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Pending</span>
                        <strong><?php echo (int) $stats['status_breakdown']['Pending']; ?></strong>
                        <span>Orders waiting for first review.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Confirmed</span>
                        <strong><?php echo (int) $stats['status_breakdown']['Confirmed']; ?></strong>
                        <span>Reservations already approved.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Ready for pickup</span>
                        <strong><?php echo (int) $stats['status_breakdown']['Ready for Pickup']; ?></strong>
                        <span>Orders waiting for customer collection.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Completed</span>
                        <strong><?php echo (int) $stats['status_breakdown']['Completed']; ?></strong>
                        <span>Orders already closed in the runtime flow.</span>
                    </article>
                </div>

                <article class="admin-card surface-card">
                    <span class="eyebrow">Find reservations</span>
                    <form method="GET" class="contact-form">
                        <div class="reservation-filter-grid">
                            <div class="field"><label for="reservation_search">Search</label><input type="search" id="reservation_search" name="search" value="<?php echo htmlspecialchars($reservation_filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Order, customer, or product"></div>
                            <div class="field"><label for="reservation_status">Reservation status</label><select id="reservation_status" name="status"><option value="">All statuses</option><?php foreach (store_state_statuses() as $status): ?><option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $reservation_filters['status'] === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                            <div class="field"><label for="reservation_payment">Payment status</label><select id="reservation_payment" name="payment_status"><option value="">All payments</option><?php foreach (store_state_payment_statuses() as $status): ?><option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $reservation_filters['payment_status'] === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                            <div class="field"><label for="reservation_date">Reserved date</label><input type="date" id="reservation_date" name="reserved_date" value="<?php echo htmlspecialchars($reservation_filters['reserved_date'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                        </div>
                        <div class="button-row"><button type="submit" class="button button-dark">Apply filters</button><a href="reservations.php" class="button button-light">Clear</a></div>
                    </form>
                </article>

                <article class="table-card surface-card" id="reservationRecords">
                    <span class="eyebrow">Reservation table</span>
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
                                <?php if (empty($reservation_orders)): ?><tr><td colspan="8">No reservations match these filters.</td></tr><?php endif; ?>
                                <?php foreach ($reservation_orders as $order): ?>
                                    <?php $reserved_at = backoffice_manila_datetime($order['created_at'] ?? '', true); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['checkout_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo count($order['items']); ?> item<?php echo count($order['items']) === 1 ? '' : 's'; ?><br><small><?php echo array_sum(array_map(function ($item) { return (int) $item['quantity']; }, $order['items'])); ?> units</small></td>
                                        <td><?php echo number_format((float) $order['subtotal'], 2); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($order['payment_status'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>"><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php if ($reserved_at): ?><?php echo htmlspecialchars($reserved_at->format('M j, Y'), ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($reserved_at->format('g:i A'), ENT_QUOTES, 'UTF-8'); ?></small><?php else: ?>Date unavailable<?php endif; ?></td>
                                        <td>
                                            <details class="table-details reservation-details">
                                                <summary>View / Update</summary>
                                                <div>
                                                    <p><strong>Reservation ID:</strong> <?php echo htmlspecialchars($order['checkout_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p><strong>Products:</strong></p>
                                                    <?php foreach ($order['items'] as $item): ?><p><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(store_order_size_display($item), ENT_QUOTES, 'UTF-8'); ?> × <?php echo (int) $item['quantity']; ?></p><?php endforeach; ?>
                                                    <p><strong>Amount:</strong> <?php echo htmlspecialchars(store_format_money((float) $order['subtotal']), ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p><strong>Payment method:</strong> <?php echo htmlspecialchars((string) ($order['payment_method'] ?? 'In-Store Payment'), ENT_QUOTES, 'UTF-8'); ?><br><strong>Payment status:</strong> <?php echo htmlspecialchars((string) ($order['payment_status'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <?php if (!empty($order['payment_reference'])): ?><p><strong>Payment reference:</strong> <?php echo htmlspecialchars((string) $order['payment_reference'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                                    <?php if (!empty($order['payment_proof'])): ?><p><a class="proof-link" href="<?php echo htmlspecialchars('../customer/uploads/payment-proofs/' . rawurlencode(basename((string) $order['payment_proof'])), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View payment proof</a></p><?php endif; ?>
                                                    <p><strong>Reservation status:</strong> <?php echo htmlspecialchars((string) $order['status'], ENT_QUOTES, 'UTF-8'); ?><br><strong>Reserved:</strong> <?php echo $reserved_at ? htmlspecialchars($reserved_at->format('F j, Y · g:i A'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></p>
                                                    <?php if (!empty($order['pickup_window'])): ?><p><strong>Pickup / completion:</strong> <?php echo htmlspecialchars((string) $order['pickup_window'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                                </div>
                                            </details>
                                            <form method="POST" class="reservation-update-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="admin_action" value="update_order">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <label>Reservation status
                                                <select name="status">
                                                    <?php foreach (backoffice_allowed_statuses('admin', (string) $order['status']) as $status_option): ?>
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
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($reservation_pagination, 'reservationRecords'); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
