<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
$stats = backoffice_get_stats($conn);
$overview = $stats['sales_overview'];
$sales_history = $stats['sales_history']['history'];
$forecast = $stats['sales_projection'];
$pricing = $stats['price_analytics'];
$channel_summary = backoffice_summarize_transaction_channels(get_admin_dynamic_transactions(0));
$channel_chart = array(
    array(
        'label' => 'Walk-in',
        'value' => $channel_summary['channels']['WALK-IN']['revenue'],
        'meta' => $channel_summary['channels']['WALK-IN']['transactions'] . ' recorded transaction' . ($channel_summary['channels']['WALK-IN']['transactions'] === 1 ? '' : 's'),
        'color' => '#eb7b4e',
    ),
    array(
        'label' => 'Online',
        'value' => $channel_summary['channels']['ONLINE']['revenue'],
        'meta' => $channel_summary['channels']['ONLINE']['transactions'] . ' recorded transaction' . ($channel_summary['channels']['ONLINE']['transactions'] === 1 ? '' : 's'),
        'color' => '#6f9cf6',
    ),
);
$monthly_total = 0;
$highest_month = null;
$lowest_month = null;

foreach ($sales_history as $point) {
    $revenue = (float) ($point['revenue'] ?? 0);
    $monthly_total += $revenue;

    if ($highest_month === null || $revenue > (float) $highest_month['revenue']) {
        $highest_month = $point;
    }

    if ($lowest_month === null || $revenue < (float) $lowest_month['revenue']) {
        $lowest_month = $point;
    }
}

$current_month = !empty($sales_history) ? end($sales_history) : null;
$previous_month = count($sales_history) > 1 ? $sales_history[count($sales_history) - 2] : null;
$monthly_change = $previous_month && (float) $previous_month['revenue'] > 0
    ? (((float) $current_month['revenue'] - (float) $previous_month['revenue']) / (float) $previous_month['revenue']) * 100
    : null;
$restock_chart = array_map(function ($product) {
    return array(
        'label' => $product['name'],
        'value' => (int) ($product['reorder_gap'] ?? 0),
        'meta' => (int) ($product['stock'] ?? 0) . ' in stock / ' . (int) ($product['suggested_stock'] ?? 0) . ' suggested',
    );
}, array_slice($forecast['restock_priority'], 0, 6));

backoffice_render_head('Shoestagram | Admin Overview');
backoffice_render_shell_start(
    'admin',
    'dashboard',
    'Admin overview',
    'Sales, forecasting, pricing, and stock now share one live command center.',
    'This dashboard combines live operations, completed system transactions, legitimate historical entries, and forecast demand signals.',
    array(
        array('href' => 'pricing.php', 'label' => 'Open pricing analytics', 'class' => 'button-dark'),
        array('href' => 'forecasting.php', 'label' => 'Open forecasting', 'class' => 'button-light'),
    )
);
?>
                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Daily sales</span>
                        <strong><?php echo htmlspecialchars(store_format_money($overview['daily_sales']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Completed sales recorded today (Philippine time).</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Weekly sales</span>
                        <strong><?php echo htmlspecialchars(store_format_money($overview['weekly_sales']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Completed sales since Monday (Philippine time).</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Monthly sales</span>
                        <strong><?php echo htmlspecialchars(store_format_money($overview['monthly_sales']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Completed sales in the current month.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Next 30 days</span>
                        <strong><?php echo htmlspecialchars(store_format_money($overview['forecast_next_month_revenue']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Predicted revenue across the next 30-day demand window.</span>
                    </article>
                </div>

                <div class="dashboard-operations-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Attention now</span>
                        <h2 class="display-font">Operational alerts that need action.</h2>
                        <div class="admin-alerts">
                            <?php foreach ($stats['alerts'] as $alert): ?>
                                <article class="alert-card <?php echo htmlspecialchars($alert['level'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas <?php echo $alert['level'] === 'warning' ? 'fa-triangle-exclamation' : ($alert['level'] === 'success' ? 'fa-circle-check' : 'fa-circle-info'); ?>"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo htmlspecialchars($alert['copy'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Pricing signal</span>
                        <h2 class="display-font"><?php echo htmlspecialchars(store_format_money($pricing['forecast_revenue_optimal']), ENT_QUOTES, 'UTF-8'); ?> potential with suggested pricing.</h2>
                        <ul class="feature-list">
                            <li><strong><?php echo number_format((float) $pricing['average_margin_percent'], 1); ?>%</strong> average modeled margin across the current catalog.</li>
                            <li><strong><?php echo count($pricing['opportunities']); ?></strong> products currently show a material pricing adjustment opportunity.</li>
                            <li>Use the dedicated pricing page to compare current price against the suggested price by product.</li>
                        </ul>
                    </article>
                </div>

                <section class="dashboard-sales-section" aria-label="Sales analytics">
                    <article class="admin-card surface-card dashboard-sales-primary">
                        <div class="chart-card-heading">
                            <div>
                                <span class="eyebrow">Sales analytics</span>
                                <h2 class="display-font">Monthly Sales Performance</h2>
                                <p>Revenue generated by month from the available sales history.</p>
                            </div>
                            <div class="chart-primary-stat">
                                <span>Total period sales</span>
                                <strong><?php echo htmlspecialchars(store_format_money($monthly_total), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($monthly_change !== null): ?><small class="<?php echo $monthly_change >= 0 ? 'is-positive' : 'is-negative'; ?>"><?php echo $monthly_change >= 0 ? '+' : ''; ?><?php echo number_format($monthly_change, 1); ?>% from the previous month</small><?php endif; ?>
                            </div>
                        </div>
                        <?php backoffice_render_line_chart($sales_history, array(
                            array('key' => 'revenue', 'label' => 'Revenue', 'color' => '#eb7b4e'),
                        ), array('value_mode' => 'money')); ?>
                        <div class="chart-insights">
                            <?php if ($highest_month): ?><span><i class="fas fa-arrow-trend-up"></i> High: <strong><?php echo htmlspecialchars((string) $highest_month['label'], ENT_QUOTES, 'UTF-8'); ?></strong> · <?php echo htmlspecialchars(store_format_money($highest_month['revenue']), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            <?php if ($lowest_month): ?><span><i class="fas fa-arrow-trend-down"></i> Low: <strong><?php echo htmlspecialchars((string) $lowest_month['label'], ENT_QUOTES, 'UTF-8'); ?></strong> · <?php echo htmlspecialchars(store_format_money($lowest_month['revenue']), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            <?php if (!empty($sales_history)): ?><span><i class="fas fa-chart-line"></i> Average: <strong><?php echo htmlspecialchars(store_format_money($monthly_total / count($sales_history)), ENT_QUOTES, 'UTF-8'); ?></strong> per month</span><?php endif; ?>
                        </div>
                    </article>
                </section>

                <div class="dashboard-analytics-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Sales channels</span>
                        <h2 class="display-font">Walk-in vs online sales.</h2>
                        <p class="chart-description">Compare recorded revenue by fulfilment channel. <?php echo htmlspecialchars($channel_summary['leader_label'], ENT_QUOTES, 'UTF-8'); ?> leads by <?php echo htmlspecialchars(store_format_money($channel_summary['difference']), ENT_QUOTES, 'UTF-8'); ?>.</p>
                        <?php backoffice_render_comparison_chart($channel_chart, array(
                            'value_key' => 'value',
                            'label_key' => 'label',
                            'meta_key' => 'meta',
                            'value_mode' => 'money',
                        )); ?>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Forecasting</span>
                        <h2 class="display-font">Restock pressure by product.</h2>
                        <p class="chart-description">The gap between available stock and the model’s suggested stock level.</p>
                        <?php backoffice_render_bar_chart($restock_chart, array(
                            'value_key' => 'value',
                            'label_key' => 'label',
                            'meta_key' => 'meta',
                            'value_mode' => 'number',
                        )); ?>
                    </article>
                </div>

                <details class="admin-secondary-panel">
                    <summary>View detailed pricing opportunities</summary>
                    <div class="admin-secondary-content">
                <article class="table-card surface-card">
                    <span class="eyebrow">What to improve</span>
                    <h2 class="display-font">Highest-value pricing actions right now.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current price (PHP)</th>
                                    <th>Suggested price (PHP)</th>
                                    <th>Action</th>
                                    <th>30-day revenue (PHP)</th>
                                    <th>Improvement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($pricing['opportunities'], 0, 8) as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $row['price'], 2); ?></td>
                                        <td><?php echo number_format((float) $row['optimal_price'], 2); ?></td>
                                        <td><span class="status-pill status-<?php echo strtolower($row['price_action']); ?>"><?php echo htmlspecialchars($row['price_action'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo number_format((float) $row['forecast_revenue_optimal'], 2); ?></td>
                                        <td><details class="table-details"><summary>View recommendation</summary><div><?php echo htmlspecialchars((string) $row['improvement'], ENT_QUOTES, 'UTF-8'); ?></div></details></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
                    </div>
                </details>
<?php backoffice_render_shell_end(); ?>
