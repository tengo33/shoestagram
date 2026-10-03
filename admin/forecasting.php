<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
$stats = backoffice_get_stats($conn);
$forecast = $stats['sales_projection'];
$forecast_rows = $forecast['products'];
$forecast_pagination = backoffice_paginate_rows($forecast_rows);
$forecast_chart_rows = array_slice($forecast['products'], 0, 8);
$limited_history_count = count(array_filter($forecast['products'], function ($product) {
    return !empty($product['limited_history_warning']);
}));
$forecast_line_chart = array_map(function ($product) {
    return array(
        'label' => $product['name'],
        'next_30d_units' => (int) ($product['next_30d_units'] ?? 0),
        'suggested_stock' => (int) ($product['suggested_stock'] ?? 0),
    );
}, $forecast_chart_rows);
$forecast_bar_chart = array_map(function ($product) {
    return array(
        'label' => $product['name'],
        'value' => (int) ($product['reorder_gap'] ?? 0),
        'meta' => (int) ($product['stock'] ?? 0) . ' current stock',
    );
}, $forecast_chart_rows);

backoffice_render_head('Shoestagram | Admin Forecasting');
backoffice_render_shell_start(
    'admin',
    'forecasting',
    'Sales forecasting',
    'ForecastAPI demand prediction with transparent data-history and fallback status.',
    'Initialized historical imports, valid manual entries, and eligible post-import completed transactions form demand history. Development samples remain separate and are used only when no historical coverage exists.',
    array(
        array('href' => 'pricing.php', 'label' => 'Open pricing analytics', 'class' => 'button-light'),
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-dark'),
    )
);
?>
                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Next 30 days</span>
                        <strong><?php echo (int) $forecast['total_units_30d']; ?> units</strong>
                        <span>Predicted demand across the upcoming month.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Next 90 days</span>
                        <strong><?php echo (int) $forecast['total_units_90d']; ?> units</strong>
                        <span>Medium-range demand outlook for broader planning.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">30-day revenue</span>
                        <strong><?php echo htmlspecialchars(store_format_money($forecast['total_revenue_30d']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Projected revenue at current selling prices.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Restock priorities</span>
                        <strong><?php echo count($forecast['restock_priority']); ?></strong>
                        <span>Products currently below their suggested stock level. <?php echo $limited_history_count > 0 ? $limited_history_count . ' have very limited actual history.' : ''; ?></span>
                    </article>
                </div>

                <?php if ($limited_history_count > 0): ?>
                    <article class="forecast-notice surface-card">
                        <i class="fas fa-circle-exclamation"></i>
                        <div>
                            <strong>Early estimate notice</strong>
                            <p>Forecast is based on limited completed-sales history and should be treated as an early estimate. <?php echo $limited_history_count; ?> product<?php echo $limited_history_count === 1 ? '' : 's'; ?> currently use very limited completed-sales history; this is an application-level data indicator, not an API accuracy guarantee.</p>
                        </div>
                    </article>
                <?php endif; ?>

                <details class="admin-secondary-panel">
                    <summary>View demand and restock charts</summary>
                    <div class="admin-secondary-content">
                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Forecast outlook</span>
                        <h2 class="display-font">Demand against suggested stock cover.</h2>
                        <p class="chart-description">Compare each product’s next-30-day demand signal with the stock cover suggested by the current model.</p>
                        <?php backoffice_render_line_chart($forecast_line_chart, array(
                            array('key' => 'next_30d_units', 'label' => '30-day demand', 'color' => '#eb7b4e'),
                            array('key' => 'suggested_stock', 'label' => 'Suggested stock', 'color' => '#6f9cf6'),
                        ), array('value_mode' => 'number')); ?>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Restock priorities</span>
                        <h2 class="display-font">Reorder gap by product.</h2>
                        <p class="chart-description">Products with the largest gap should receive attention first.</p>
                        <?php backoffice_render_bar_chart($forecast_bar_chart, array(
                            'value_key' => 'value',
                            'label_key' => 'label',
                            'meta_key' => 'meta',
                            'value_mode' => 'number',
                        )); ?>
                    </article>
                </div>
                    </div>
                </details>

                <article class="table-card surface-card" id="forecastRecords">
                    <span class="eyebrow">Forecast detail</span>
                    <h2 class="display-font">Forecast source, history quality, and inventory action by product.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Expected demand (30 days)</th>
                                    <th>Forecasted revenue (PHP)</th>
                                    <th>Current stock</th>
                                    <th>Suggested stock</th>
                                    <th>Reorder gap</th>
                                    <th>Forecast details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($forecast_rows)): ?><tr><td colspan="7">No product forecasts are available.</td></tr><?php endif; ?>
                                <?php foreach ($forecast_rows as $product): ?>
                                    <?php $first_period = !empty($product['forecast_periods']) ? $product['forecast_periods'][0] : array(); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($product['limited_history_warning'])): ?><br><small>Early estimate: limited sales history</small><?php endif; ?><?php if (empty($product['forecast_available'])): ?><br><small>Local fallback</small><?php endif; ?></td>
                                        <td><?php echo (int) $product['next_30d_units']; ?> units</td>
                                        <td><?php echo number_format((float) $product['next_30d_revenue'], 2); ?></td>
                                        <td><?php echo (int) $product['stock']; ?></td>
                                        <td><?php echo (int) $product['suggested_stock']; ?></td>
                                        <td><?php echo (int) $product['reorder_gap']; ?></td>
                                        <td>
                                            <details class="table-details"><summary>View details</summary><div>
                                                <p><strong>90-day demand:</strong> <?php echo (int) $product['next_90d_units']; ?> units</p>
                                                <p><strong>Sales data:</strong> <?php echo htmlspecialchars((string) $product['data_source'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p><strong>Sales history available:</strong> <?php echo htmlspecialchars((string) $product['history_status'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $product['historical_sales_days']; ?> actual sales days; <?php echo (int) $product['continuous_history_days']; ?> submitted days)</p>
                                                <p><strong>Forecast source:</strong> <?php echo htmlspecialchars((string) $product['forecast_source'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php if (empty($product['forecast_available'])): ?><p><?php echo htmlspecialchars((string) $product['forecast_unavailable_message'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                                <p><strong>Model:</strong> <?php echo htmlspecialchars((string) ($product['model_method'] ?: 'Not returned'), ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p><strong>Forecast range:</strong> <?php echo isset($first_period['lower'], $first_period['upper']) ? number_format((float) $first_period['lower'], 2) . '–' . number_format((float) $first_period['upper'], 2) . ' units (day 1)' : 'Not returned'; ?></p>
                                                <?php $generated_at = backoffice_manila_datetime($product['generated_at'] ?? ''); ?><p><strong>Generated:</strong> <?php echo $generated_at ? htmlspecialchars($generated_at->format('M j, Y · g:i A'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></p>
                                            </div></details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($forecast_pagination, 'forecastRecords'); ?>
                </article>

<?php backoffice_render_shell_end(); ?>
