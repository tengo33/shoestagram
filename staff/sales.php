<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
backoffice_handle_promotion_update('staff');
$filters = array(
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'product_id' => (int) ($_GET['product_id'] ?? 0),
    'source' => trim((string) ($_GET['source'] ?? '')),
    'channel' => trim((string) ($_GET['channel'] ?? '')),
);
$stats = backoffice_get_stats($conn);
$filtered_transactions = backoffice_filter_transactions(get_admin_dynamic_transactions(0), $filters);
$channel_summary = backoffice_summarize_transaction_channels($filtered_transactions);
$history = store_get_sales_history($filters);
$promo_product_id = (int) ($_GET['promo_product_id'] ?? 0);
$product_lookup = array();
foreach ($stats['products'] as $product) {
    $product_lookup[(int) $product['id']] = $product;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $completed_units = 0;
    foreach ($filtered_transactions as $transaction) {
        if (($transaction['status'] ?? '') === 'Completed') {
            $completed_units += max(0, (int) ($transaction['quantity'] ?? 0));
        }
    }

    $date_from_label = $filters['date_from'] !== '' ? shoestagram_pdf_date_label($filters['date_from']) : 'First available sale';
    $date_to_label = $filters['date_to'] !== '' ? shoestagram_pdf_date_label($filters['date_to']) : 'Latest available sale';
    $source_label = $filters['source'] === 'manual' ? 'Historical entries' : ($filters['source'] === 'system' ? 'System transactions' : 'All sales');
    $channel_label = strtoupper($filters['channel']) === 'WALK-IN' ? 'Walk-in' : (strtoupper($filters['channel']) === 'ONLINE' ? 'Online' : 'Walk-in and Online');
    $product_label = $filters['product_id'] > 0 && isset($product_lookup[$filters['product_id']])
        ? $product_lookup[$filters['product_id']]['name']
        : 'All products';

    $pdf = shoestagram_build_sales_report_pdf(array(
        'title' => 'Staff Sales Report',
        'scope_label' => 'Read-only completed-sales summary with filtered transaction detail',
        'generated_at' => 'now',
        'filters' => array(
            array('label' => 'Report period', 'value' => $date_from_label . ' to ' . $date_to_label),
            array('label' => 'Product', 'value' => $product_label),
            array('label' => 'Data source', 'value' => $source_label),
            array('label' => 'Channel', 'value' => $channel_label),
        ),
        'summary' => array(
            array('label' => 'Total revenue', 'value' => store_format_money($channel_summary['total_revenue'])),
            array('label' => 'Transactions', 'value' => number_format((int) $channel_summary['total_transactions'])),
            array('label' => 'Units sold', 'value' => number_format($completed_units)),
            array('label' => 'Walk-in sales', 'value' => store_format_money($channel_summary['channels']['WALK-IN']['revenue'])),
            array('label' => 'Online sales', 'value' => store_format_money($channel_summary['channels']['ONLINE']['revenue'])),
        ),
        'secondary_metrics' => array(
            array('label' => 'Channel difference', 'value' => store_format_money($channel_summary['difference'])),
            array('label' => 'Current month revenue', 'value' => store_format_money($stats['sales_overview']['monthly_sales'])),
            array('label' => '30-day forecast', 'value' => store_format_money($stats['sales_overview']['forecast_next_month_revenue'])),
        ),
        'transactions' => $filtered_transactions,
        'history' => $history['history'],
    ));
    shoestagram_pdf_output('shoestagram-staff-sales-report.pdf', $pdf);
}

$transaction_pagination = backoffice_paginate_transaction_lines($filtered_transactions);

backoffice_render_head('Shoestagram | Staff Sales');
backoffice_render_shell_start(
    'staff',
    'sales',
    'Sales summary',
    'The staff sales view combines completed system transactions and historical entries.',
    'Staff can filter actual sales and review source labels without receiving permission to alter Admin historical records.',
    array(
        array('href' => 'sales.php?export=pdf&date_from=' . urlencode($filters['date_from']) . '&date_to=' . urlencode($filters['date_to']) . '&product_id=' . urlencode((string) $filters['product_id']) . '&source=' . urlencode($filters['source']) . '&channel=' . urlencode($filters['channel']), 'label' => 'Export PDF', 'class' => 'button-dark'),
        array('href' => 'dashboard.php', 'label' => 'Back to dashboard', 'class' => 'button-light'),
    )
);
?>
                <article class="admin-card surface-card">
                    <span class="eyebrow">Filters</span>
                    <h2 class="display-font">Limit the sales summary by date or product.</h2>
                    <form method="GET" class="contact-form">
                        <div class="form-grid">
                            <div class="field">
                                <label for="date_from">Date from</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="date_to">Date to</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="product_id">Product</label>
                                <select id="product_id" name="product_id">
                                    <option value="0">All products</option>
                                    <?php foreach ($stats['products'] as $product): ?>
                                        <option value="<?php echo (int) $product['id']; ?>"<?php echo (int) $filters['product_id'] === (int) $product['id'] ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="source">Data source</label>
                                <select id="source" name="source">
                                    <option value="">All sales</option>
                                    <option value="system"<?php echo $filters['source'] === 'system' ? ' selected' : ''; ?>>System transactions</option>
                                    <option value="manual"<?php echo $filters['source'] === 'manual' ? ' selected' : ''; ?>>Historical entries</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="channel">Channel</label>
                                <select id="channel" name="channel">
                                    <option value="">Walk-in and Online</option>
                                    <option value="WALK-IN"<?php echo $filters['channel'] === 'WALK-IN' ? ' selected' : ''; ?>>Walk-in</option>
                                    <option value="ONLINE"<?php echo $filters['channel'] === 'ONLINE' ? ' selected' : ''; ?>>Online</option>
                                </select>
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Apply filters</button>
                            <a href="sales.php" class="button button-light">Reset</a>
                        </div>
                    </form>
                </article>

                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Sales summary</span>
                        <h2 class="display-font"><?php echo htmlspecialchars(store_format_money($stats['sales_overview']['monthly_sales']), ENT_QUOTES, 'UTF-8'); ?> actual monthly sales</h2>
                        <ul class="feature-list">
                            <li><strong><?php echo (int) $channel_summary['total_transactions']; ?></strong> completed sale<?php echo (int) $channel_summary['total_transactions'] === 1 ? '' : 's'; ?> matched the current filter.</li>
                            <li><strong><?php echo htmlspecialchars(store_format_money($stats['sales_overview']['weekly_sales']), ENT_QUOTES, 'UTF-8'); ?></strong> estimated weekly output.</li>
                            <li><strong><?php echo htmlspecialchars(store_format_money($stats['sales_overview']['forecast_next_month_revenue']), ENT_QUOTES, 'UTF-8'); ?></strong> forecast revenue next month.</li>
                        </ul>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Sales graph</span>
                        <h2 class="display-font">Actual completed-sales curve.</h2>
                        <div class="chart-list">
                            <?php foreach ($history['history'] as $point): ?>
                                <div class="chart-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($point['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo htmlspecialchars(store_format_money($point['revenue']), ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $point['units']; ?> units</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-bar" style="width: <?php echo $history['max_revenue'] > 0 ? round(((float) $point['revenue'] / (float) $history['max_revenue']) * 100, 2) : 0; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>

                <?php backoffice_render_promotion_management($stats, $promo_product_id); ?>

                <article class="table-card surface-card">
                    <span class="eyebrow">Transaction history</span>
                    <h2 class="display-font">Filtered system and historical records.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Amount (PHP)</th>
                                    <th>Status</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered_transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['reference'], ENT_QUOTES, 'UTF-8'); ?><?php if (($transaction['checkout_item_count'] ?? 1) > 1): ?><br><small>Item <?php echo (int) $transaction['checkout_item_number']; ?> of <?php echo (int) $transaction['checkout_item_count']; ?></small><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($transaction['customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['product_name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($transaction['size_display'] ?? store_size_display($transaction['size'] ?? '', $transaction['size_standard'] ?? 'US'), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo number_format((float) $transaction['amount'], 2); ?></td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $transaction['status'])); ?>"><?php echo htmlspecialchars($transaction['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo htmlspecialchars($transaction['source_label'] ?? store_manual_sale_source_label($transaction['source'] ?? 'system'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($transaction_pagination); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
