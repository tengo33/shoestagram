<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
$filters = array(
    'product_id' => (int) ($_GET['product_id'] ?? 0),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'brand' => trim((string) ($_GET['brand'] ?? '')),
);
$analytics = store_get_price_analytics($filters);
$stats = backoffice_get_stats($conn);
$pricing_rows = $analytics['rows'];
$pricing_pagination = backoffice_paginate_rows($pricing_rows);
$pricing_chart_rows = !empty($analytics['opportunities'])
    ? array_slice($analytics['opportunities'], 0, 8)
    : array_slice($analytics['rows'], 0, 8);
$pricing_line_chart = array_map(function ($row) {
    return array(
        'label' => $row['name'],
        'current_price' => (float) ($row['price'] ?? 0),
        'optimal_price' => (float) ($row['optimal_price'] ?? 0),
    );
}, $pricing_chart_rows);
$pricing_bar_chart = array_map(function ($row) {
    return array(
        'label' => $row['name'],
        'value' => abs((float) ($row['price_gap'] ?? 0)),
        'meta' => (string) ($row['price_action'] ?? 'Hold') . ' to ' . (string) ($row['optimal_price_display'] ?? ''),
    );
}, $pricing_chart_rows);

backoffice_render_head('Shoestagram | Admin Pricing');
backoffice_render_shell_start(
    'admin',
    'pricing',
    'Pricing analytics',
    'Current price, cost, profit, suggested price, and predicted sales impact in one view.',
    'This pricing page differentiates customer-facing selling price from admin-only cost and profit data. It uses the seeded catalog, demand history model, and reservation pressure to suggest what to improve next.',
    array(
        array('href' => 'forecasting.php', 'label' => 'Open forecasting', 'class' => 'button-light'),
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-dark'),
    )
);
?>
                <article class="admin-card surface-card">
                    <span class="eyebrow">Filters</span>
                    <h2 class="display-font">Analyze pricing by product, brand, or category.</h2>
                    <form method="GET" class="contact-form">
                        <div class="form-grid">
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
                                <label for="brand">Brand</label>
                                <select id="brand" name="brand">
                                    <option value="">All brands</option>
                                    <?php foreach (get_store_brand_names() as $brand): ?>
                                        <option value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['brand'] === $brand ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="">All categories</option>
                                    <?php foreach (get_store_categories() as $key => $label): ?>
                                        <?php if ($key === 'all') { continue; } ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['category'] === $key ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Apply filters</button>
                            <a href="pricing.php" class="button button-light">Reset</a>
                        </div>
                    </form>
                </article>

                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Current 30-day revenue</span>
                        <strong><?php echo htmlspecialchars(store_format_money($analytics['forecast_revenue_current']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Revenue projection if current prices stay in place.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Suggested pricing revenue</span>
                        <strong><?php echo htmlspecialchars(store_format_money($analytics['forecast_revenue_optimal']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Revenue projection if suggested prices are adopted.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Average margin</span>
                        <strong><?php echo number_format((float) $analytics['average_margin_percent'], 1); ?>%</strong>
                        <span>Average modeled margin across the filtered product set.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Pricing opportunities</span>
                        <strong><?php echo count($analytics['opportunities']); ?></strong>
                        <span>Products with a meaningful suggested price change.</span>
                    </article>
                </div>

                <details class="admin-secondary-panel">
                    <summary>View pricing comparison charts</summary>
                    <div class="admin-secondary-content">
                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Price position</span>
                        <h2 class="display-font">Current price versus suggested price.</h2>
                        <p class="chart-description">A product-level view of the current customer price against the model’s recommendation.</p>
                        <?php backoffice_render_line_chart($pricing_line_chart, array(
                            array('key' => 'current_price', 'label' => 'Current price', 'color' => '#6f9cf6'),
                            array('key' => 'optimal_price', 'label' => 'Suggested price', 'color' => '#eb7b4e'),
                        ), array('value_mode' => 'money')); ?>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Pricing movement</span>
                        <h2 class="display-font">How far each price wants to move.</h2>
                        <p class="chart-description">The absolute adjustment recommended for each product in the filtered set.</p>
                        <?php backoffice_render_bar_chart($pricing_bar_chart, array(
                            'value_key' => 'value',
                            'label_key' => 'label',
                            'meta_key' => 'meta',
                            'value_mode' => 'money',
                        )); ?>
                    </article>
                </div>
                    </div>
                </details>

                <article class="table-card surface-card" id="pricingRecords">
                    <span class="eyebrow">Price analytics table</span>
                    <h2 class="display-font">Selling price versus admin-side cost and profit.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current price (PHP)</th>
                                    <th>Cost (PHP)</th>
                                    <th>Profit (PHP)</th>
                                    <th>Margin</th>
                                    <th>Suggested price (PHP)</th>
                                    <th>Predicted sales</th>
                                    <th>What to improve</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pricing_rows)): ?><tr><td colspan="8">No products match these filters.</td></tr><?php endif; ?>
                                <?php foreach ($pricing_rows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($row['brand'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo number_format((float) $row['price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars(str_replace('PHP ', '', $row['cost_display']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(str_replace('PHP ', '', $row['profit_display']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $row['margin_percent'], 1); ?>%</td>
                                        <td><?php echo number_format((float) $row['optimal_price'], 2); ?></td>
                                        <td><?php echo (int) $row['forecast_units_optimal']; ?> units / 30d</td>
                                        <td><details class="table-details"><summary><?php echo htmlspecialchars(function_exists('mb_strimwidth') ? mb_strimwidth((string) $row['improvement'], 0, 48, '…', 'UTF-8') : substr((string) $row['improvement'], 0, 48), ENT_QUOTES, 'UTF-8'); ?> <span>View</span></summary><div><?php echo htmlspecialchars((string) $row['improvement'], ENT_QUOTES, 'UTF-8'); ?></div></details></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($pricing_pagination, 'pricingRecords'); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
