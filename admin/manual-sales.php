<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try that historical-sales change again.');
        header('Location: manual-sales.php');
        exit();
    }

    $action = (string) ($_POST['admin_action'] ?? '');
    if ($action === 'save_manual_sale') {
        $sale_id = (int) ($_POST['sale_id'] ?? 0);
        $result = store_save_manual_sale($_POST, $sale_id, array(
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'fullname' => (string) ($_SESSION['user_name'] ?? 'Administrator'),
        ));

        if ($result['ok']) {
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Historical Sales',
                ($sale_id > 0 ? 'Edited' : 'Added') . ' manual historical sale #' . (int) $result['id'] . '.',
                'Success'
            );
            storefront_set_flash('success', $sale_id > 0 ? 'Historical sale updated.' : 'Historical sale added to actual sales history.');
            header('Location: manual-sales.php');
            exit();
        }

        storefront_set_flash('error', implode(' ', $result['errors']));
        header('Location: manual-sales.php' . ($sale_id > 0 ? '?edit=' . $sale_id : ''));
        exit();
    }

    if ($action === 'delete_manual_sale') {
        $sale_id = (int) ($_POST['sale_id'] ?? 0);
        if ($sale_id > 0 && store_get_manual_sale($sale_id) && store_delete_manual_sale($sale_id)) {
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Historical Sales',
                'Deleted manual historical sale #' . $sale_id . '.',
                'Success'
            );
            storefront_set_flash('success', 'Historical sale deleted. Analytics and forecast history have been refreshed.');
        } else {
            storefront_set_flash('error', 'The historical sale could not be deleted.');
        }
        header('Location: manual-sales.php');
        exit();
    }
}

$products = get_store_products();
usort($products, function ($left, $right) {
    return strcasecmp((string) $left['name'], (string) $right['name']);
});

$filters = array(
    'month' => trim((string) ($_GET['month'] ?? '')),
    'year' => (int) ($_GET['year'] ?? 0),
    'product_id' => (int) ($_GET['product_id'] ?? 0),
    'channel' => trim((string) ($_GET['channel'] ?? '')),
);
$summary = store_manual_sales_summary($filters);
$sales_total = (int) $summary['records'];
$sales_pages = max(1, (int) ceil($sales_total / 10));
$sales_page = min($sales_pages, max(1, (int) ($_GET['page'] ?? 1)));
$sales = store_get_manual_sales($filters, 10, ($sales_page - 1) * 10);
$sales_pagination = array('page' => $sales_page, 'pages' => $sales_pages, 'total' => $sales_total, 'per_page' => 10, 'key' => 'page');
$edit_sale = isset($_GET['edit']) ? store_get_manual_sale((int) $_GET['edit']) : null;
$default_product = !empty($products) ? $products[0] : array('id' => 0, 'price' => 0);
$form_sale = $edit_sale ?: array(
    'id' => 0,
    'sale_date' => date('Y-m-d'),
    'transaction_type' => 'walk_in',
    'product_id' => (int) ($default_product['id'] ?? 0),
    'size_standard' => 'US',
    'size' => '',
    'quantity' => 1,
    'unit_price' => (float) ($default_product['price'] ?? 0),
    'total_amount' => (float) ($default_product['price'] ?? 0),
    'payment_method' => 'In-Store Payment',
    'notes' => '',
);
$available_years = array((int) date('Y'));
foreach (database_fetch_all($conn, "SELECT DISTINCT YEAR(sale_date) AS sale_year FROM historical_sales WHERE source = 'manual' ORDER BY sale_year DESC") as $year_row) {
    $available_years[] = (int) ($year_row['sale_year'] ?? 0);
}
for ($offset = 1; $offset <= 6; $offset++) {
    $available_years[] = (int) date('Y') - $offset;
}
$available_years = array_values(array_unique(array_filter($available_years)));
rsort($available_years);

backoffice_render_head('Shoestagram | Historical Sales');
backoffice_render_shell_start(
    'admin',
    'manual-sales',
    'Historical sales',
    'Encode legitimate legacy store sales without mixing them with Shoestagram-created orders.',
    'Every record is permanently identified as a manual historical entry and contributes to actual sales analytics and product demand history.',
    array(
        array('href' => 'sales.php', 'label' => 'Transactions & analytics', 'class' => 'button-dark'),
        array('href' => 'manual-sales.php?form=add#manualSaleForm', 'label' => 'Add historical sale', 'class' => 'button-light'),
    )
);
?>
                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Total sales</span>
                        <strong><?php echo htmlspecialchars(store_format_money($summary['total_sales']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo (int) $summary['records']; ?> manual historical record<?php echo (int) $summary['records'] === 1 ? '' : 's'; ?>.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Walk-in</span>
                        <strong><?php echo htmlspecialchars(store_format_money($summary['walk_in_sales']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>External counter sales encoded by Admin.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Online</span>
                        <strong><?php echo htmlspecialchars(store_format_money($summary['online_sales']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>External online sales not created by Shoestagram.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Units sold</span>
                        <strong><?php echo number_format((int) $summary['units_sold']); ?></strong>
                        <span>Actual product demand in this filtered view.</span>
                    </article>
                </div>

                <article class="admin-card surface-card">
                    <span class="eyebrow">Filter historical entries</span>
                    <h2 class="display-font">Review a month, product, or sales channel.</h2>
                    <form method="GET" class="contact-form">
                        <div class="form-grid">
                            <div class="field">
                                <label for="filter_month">Month</label>
                                <input type="month" id="filter_month" name="month" value="<?php echo htmlspecialchars($filters['month'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="filter_year">Year</label>
                                <select id="filter_year" name="year">
                                    <option value="0">All years</option>
                                    <?php foreach ($available_years as $year): ?>
                                        <option value="<?php echo $year; ?>"<?php echo $filters['year'] === $year ? ' selected' : ''; ?>><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="filter_product">Product</label>
                                <select id="filter_product" name="product_id">
                                    <option value="0">All products</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo (int) $product['id']; ?>"<?php echo $filters['product_id'] === (int) $product['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="filter_channel">Channel</label>
                                <select id="filter_channel" name="channel">
                                    <option value="">Walk-in and Online</option>
                                    <?php foreach (store_manual_sale_channels() as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['channel'] === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Apply filters</button>
                            <a href="manual-sales.php" class="button button-light">Reset</a>
                        </div>
                    </form>
                </article>

                <details class="admin-card surface-card admin-form-panel" id="manualSaleForm"<?php echo $edit_sale || isset($_GET['form']) ? ' open' : ''; ?>>
                    <summary class="admin-form-summary"><span class="eyebrow"><?php echo $edit_sale ? 'Edit entry' : 'New entry'; ?></span><strong><?php echo $edit_sale ? 'Update this historical sale.' : 'Add a legitimate external store sale.'; ?></strong></summary>
                    <p class="chart-description">Use this only for legacy sales that do not already exist as a Shoestagram transaction. Current inventory is not changed by historical imports.</p>
                    <form method="POST" class="contact-form" data-manual-sale-form>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="admin_action" value="save_manual_sale">
                        <input type="hidden" name="sale_id" value="<?php echo (int) ($form_sale['id'] ?? 0); ?>">
                        <div class="form-grid">
                            <div class="field">
                                <label for="sale_date">Date</label>
                                <input type="date" id="sale_date" name="sale_date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars((string) $form_sale['sale_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="transaction_type">Transaction channel</label>
                                <select id="transaction_type" name="transaction_type" required data-sale-channel>
                                    <?php foreach (store_manual_sale_channels() as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($form_sale['transaction_type'] ?? '') === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="sale_product">Product</label>
                                <select id="sale_product" name="product_id" required data-sale-product>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo (int) $product['id']; ?>" data-price="<?php echo htmlspecialchars(number_format((float) $product['price'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" data-has-sizes="<?php echo !empty(store_product_all_sizes($product)) ? '1' : '0'; ?>"<?php echo (int) ($form_sale['product_id'] ?? 0) === (int) $product['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" data-sale-size-field>
                                <label for="sale_size">Size <small>(optional)</small></label>
                                <div class="form-grid compact-form-grid">
                                    <select id="sale_size_standard" name="size_standard" aria-label="Size standard">
                                        <?php foreach (store_size_standard_labels() as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo store_normalize_size_standard($form_sale['size_standard'] ?? 'US') === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" id="sale_size" name="size" maxlength="60" placeholder="e.g. 9" value="<?php echo htmlspecialchars((string) ($form_sale['size'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div class="field">
                                <label for="sale_quantity">Quantity sold</label>
                                <input type="number" id="sale_quantity" name="quantity" min="1" step="1" value="<?php echo (int) $form_sale['quantity']; ?>" required data-sale-quantity>
                            </div>
                            <div class="field">
                                <label for="sale_unit_price">Unit price</label>
                                <input type="number" id="sale_unit_price" name="unit_price" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float) $form_sale['unit_price'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required data-sale-price>
                            </div>
                            <div class="field">
                                <label for="sale_total">Total sale</label>
                                <input type="text" id="sale_total" value="<?php echo htmlspecialchars(store_format_money((float) $form_sale['total_amount']), ENT_QUOTES, 'UTF-8'); ?>" readonly data-sale-total>
                                <small class="field-note">Calculated securely from quantity × unit price.</small>
                            </div>
                            <div class="field">
                                <label for="payment_method">Payment method</label>
                                <select id="payment_method" name="payment_method" required data-sale-payment>
                                    <?php foreach (store_manual_sale_payment_methods() as $payment_method): ?>
                                        <option value="<?php echo htmlspecialchars($payment_method, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($form_sale['payment_method'] ?? '') === $payment_method ? ' selected' : ''; ?>><?php echo htmlspecialchars($payment_method, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field-wide">
                                <label for="sale_notes">Optional note</label>
                                <textarea id="sale_notes" name="notes" maxlength="500" placeholder="Imported from August store sales record"><?php echo htmlspecialchars((string) ($form_sale['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark"><?php echo $edit_sale ? 'Save changes' : 'Add historical sale'; ?></button>
                            <?php if ($edit_sale): ?><a href="manual-sales.php" class="button button-light">Cancel edit</a><?php endif; ?>
                        </div>
                    </form>
                </details>

                <article class="table-card surface-card" id="historicalRecords">
                    <span class="eyebrow">Historical records</span>
                    <h2 class="display-font">Clearly separated from Shoestagram transactions.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Date</th><th>Product</th><th>Channel</th><th>Quantity</th><th>Total (PHP)</th><th>Source</th><th>Details</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (empty($sales)): ?>
                                    <tr><td colspan="8"><div class="empty-state"><strong>No historical sales match these filters.</strong><span>Add a legitimate external sale or change the filters above.</span></div></td></tr>
                                <?php endif; ?>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string) $sale['sale_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($sale['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong><?php if (trim((string) ($sale['size'] ?? '')) !== ''): ?><br><small><?php echo htmlspecialchars(store_size_display($sale['size'], $sale['size_standard'] ?? 'US'), ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars(store_manual_sale_channels()[$sale['transaction_type']] ?? 'Online', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $sale['quantity']; ?></td>
                                        <td><strong><?php echo number_format((float) $sale['total_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-pill status-confirmed"><?php echo htmlspecialchars(store_manual_sale_source_label($sale['source'] ?? 'manual', $sale['provenance'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (($sale['provenance'] ?? '') === 'historical_import' && ($sale['import_verification_status'] ?? '') !== 'client_verified'): ?>
                                                <br><small>Pending client verification</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><details class="table-details"><summary>View details</summary><div><p><strong>Unit price:</strong> <?php echo htmlspecialchars(store_format_money((float) $sale['unit_price']), ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Payment:</strong> <?php echo htmlspecialchars((string) $sale['payment_method'], ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Note:</strong> <?php echo trim((string) ($sale['notes'] ?? '')) !== '' ? nl2br(htmlspecialchars((string) $sale['notes'], ENT_QUOTES, 'UTF-8')) : 'None'; ?></p></div></details></td>
                                        <td>
                                            <div class="button-row compact-actions">
                                                <a class="button button-light" href="manual-sales.php?edit=<?php echo (int) $sale['id']; ?>#manualSaleForm">Edit</a>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this historical sale? This will remove it from analytics and forecast history.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="admin_action" value="delete_manual_sale">
                                                    <input type="hidden" name="sale_id" value="<?php echo (int) $sale['id']; ?>">
                                                    <button type="submit" class="button button-light">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($sales_pagination, 'historicalRecords'); ?>
                </article>
                <script>
                (() => {
                    const form = document.querySelector('[data-manual-sale-form]');
                    if (!form) return;
                    const product = form.querySelector('[data-sale-product]');
                    const quantity = form.querySelector('[data-sale-quantity]');
                    const price = form.querySelector('[data-sale-price]');
                    const total = form.querySelector('[data-sale-total]');
                    const channel = form.querySelector('[data-sale-channel]');
                    const payment = form.querySelector('[data-sale-payment]');
                    const sizeField = form.querySelector('[data-sale-size-field]');
                    const isEditing = <?php echo $edit_sale ? 'true' : 'false'; ?>;

                    const syncTotal = () => {
                        const amount = Math.max(0, Number(quantity.value) || 0) * Math.max(0, Number(price.value) || 0);
                        total.value = 'PHP ' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };
                    const syncProduct = (useDefaultPrice) => {
                        const selected = product.options[product.selectedIndex];
                        sizeField.hidden = selected?.dataset.hasSizes !== '1';
                        if (useDefaultPrice && selected?.dataset.price) price.value = selected.dataset.price;
                        syncTotal();
                    };
                    product.addEventListener('change', () => syncProduct(true));
                    quantity.addEventListener('input', syncTotal);
                    price.addEventListener('input', syncTotal);
                    channel.addEventListener('change', () => {
                        payment.value = channel.value === 'walk_in' ? 'In-Store Payment' : 'Online Payment';
                    });
                    syncProduct(!isEditing && !price.value);
                    syncTotal();
                })();
                </script>
<?php backoffice_render_shell_end(); ?>
