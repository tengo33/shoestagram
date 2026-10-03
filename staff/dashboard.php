<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
$stats = backoffice_get_stats($conn);
$overview = $stats['sales_overview'];
$sales_history = $stats['sales_history']['history'];
$forecast = $stats['sales_projection'];
$pricing = $stats['price_analytics'];
$staff_pricing_chart = array_map(function ($row) {
    return array(
        'label' => $row['name'],
        'value' => abs((float) ($row['price_gap'] ?? 0)),
        'meta' => (string) ($row['price_action'] ?? 'Hold') . ' to ' . (string) ($row['optimal_price_display'] ?? ''),
    );
}, array_slice($pricing['opportunities'], 0, 6));
$staff_forecast_line = array_map(function ($row) {
    return array(
        'label' => $row['name'],
        'next_30d_units' => (int) ($row['next_30d_units'] ?? 0),
        'suggested_stock' => (int) ($row['suggested_stock'] ?? 0),
    );
}, array_slice($forecast['restock_priority'], 0, 6));

backoffice_render_head('Shoestagram | Staff Dashboard');
backoffice_render_shell_start(
    'staff',
    'dashboard',
    'Staff dashboard',
    'Sales summary, low-stock alerts, overstock signals, and reservation flow in one place.',
    'Staff gets the same live store data as admin, but focused on the actions needed for daily store operations.',
    array(
        array('href' => 'sales.php', 'label' => 'Open sales', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
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
                        <span>Forecasted revenue across the next 30-day window.</span>
                    </article>
                </div>

                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Alerts</span>
                        <h2 class="display-font">Where staff should focus first.</h2>
                        <div class="admin-alerts">
                            <?php foreach (array_slice($stats['alerts'], 0, 5) as $alert): ?>
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
                        <span class="eyebrow">Reservation flow</span>
                        <h2 class="display-font">Queue counts that affect floor operations.</h2>
                        <ul class="feature-list">
                            <li><strong><?php echo (int) $stats['status_breakdown']['Pending']; ?></strong> pending reservations waiting in the system.</li>
                            <li><strong><?php echo (int) $stats['status_breakdown']['Ready for Pickup']; ?></strong> orders are already marked ready for customer pickup.</li>
                            <li><strong><?php echo (int) $stats['status_breakdown']['Completed']; ?></strong> reservations have already been completed.</li>
                        </ul>
                    </article>
                </div>

                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Forecast watch</span>
                        <h2 class="display-font">Restock demand line chart.</h2>
                        <?php backoffice_render_line_chart($staff_forecast_line, array(
                            array('key' => 'next_30d_units', 'label' => '30-day units', 'color' => '#eb7b4e'),
                            array('key' => 'suggested_stock', 'label' => 'Suggested stock', 'color' => '#6f9cf6'),
                        )); ?>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Pricing watch</span>
                        <h2 class="display-font">Top pricing moves as a bar graph.</h2>
                        <?php backoffice_render_bar_chart($staff_pricing_chart, array(
                            'value_key' => 'value',
                            'label_key' => 'label',
                            'meta_key' => 'meta',
                            'value_mode' => 'money',
                        )); ?>
                    </article>
                </div>

                <article class="table-card surface-card">
                    <span class="eyebrow">Sales pulse</span>
                    <h2 class="display-font">Six-month sales line for the staff view.</h2>
                    <?php backoffice_render_line_chart($sales_history, array(
                        array('key' => 'revenue', 'label' => 'Revenue', 'color' => '#eb7b4e'),
                    )); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
