<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
$edit_id = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the inventory action again.');
        header('Location: inventory.php');
        exit();
    }

    $inventory_action = trim((string) ($_POST['inventory_action'] ?? ''));

    if ($inventory_action === 'save_product') {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $result = store_save_product($_POST, $product_id);
        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: inventory.php');
        exit();
    }

    if ($inventory_action === 'delete_product') {
        $result = store_delete_product((int) ($_POST['product_id'] ?? 0));
        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: inventory.php');
        exit();
    }

    if ($inventory_action === 'save_size_stock') {
        $result = store_save_size_stock_allocation((int) ($_POST['product_id'] ?? 0), $_POST);
        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: inventory.php#productRecords');
        exit();
    }
}

$stats = backoffice_get_stats($conn);
$inventory_products = $stats['products'];
$inventory_filters = array(
    'search' => backoffice_query_text('search'),
    'stock' => backoffice_query_text('stock', 20),
    'category' => backoffice_query_text('category', 80),
    'brand' => backoffice_query_text('brand'),
    'sort' => backoffice_query_text('sort', 30),
);
$inventory_brands = array_values(array_unique(array_map(function ($product) { return (string) $product['brand']; }, $inventory_products)));
natcasesort($inventory_brands);
$inventory_products = backoffice_filter_inventory_products($inventory_products, $inventory_filters);
$inventory_per_page = (int) ($_GET['per_page'] ?? 10);
if (!in_array($inventory_per_page, array(10, 20, 50), true)) $inventory_per_page = 10;
$product_pagination = backoffice_paginate_rows($inventory_products, 'product_page', $inventory_per_page);
$inventory_log_search = backoffice_query_text('log_search');
$inventory_log_page = database_get_inventory_logs_page($conn, (int) ($_GET['log_page'] ?? 1), 10, array('search' => $inventory_log_search));
$product_to_edit = $edit_id > 0 ? get_store_product_by_id($edit_id) : null;
$product_size_standards_to_edit = store_normalize_size_standards(
    $product_to_edit['size_standards'] ?? array(),
    $product_to_edit['sizes'] ?? array(),
    $product_to_edit['category'] ?? 'shoes'
);
$budget_options = get_store_budget_options();

backoffice_render_head('Shoestagram | Admin Inventory');
backoffice_render_shell_start(
    'admin',
    'inventory',
    'Inventory management',
    'Create, update, and track products with real-time stock pressure and logs.',
    'This inventory module now uses the seeded product database, runtime reservation deductions, and inventory logs so stock movement stays visible by product and by user action.',
    array(
        array('href' => 'inventory.php?form=add#productForm', 'label' => 'Add product', 'class' => 'button-light'),
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-dark'),
    )
);
?>
                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Low stock watch</span>
                        <h2 class="display-font">Products closest to selling through.</h2>
                        <div class="admin-alerts">
                            <?php foreach (array_slice($stats['low_stock_products'], 0, 6) as $product): ?>
                                <article class="alert-card warning">
                                    <i class="fas fa-box-open"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo (int) $product['stock']; ?> left · suggested <?php echo (int) $product['suggested_stock']; ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Overstock watch</span>
                        <h2 class="display-font">Products sitting above forecast need.</h2>
                        <div class="admin-alerts">
                            <?php foreach (array_slice($stats['overstock_products'], 0, 6) as $product): ?>
                                <article class="alert-card info">
                                    <i class="fas fa-layer-group"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo (int) $product['stock']; ?> left · suggested <?php echo (int) $product['suggested_stock']; ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>

                <details class="admin-card surface-card admin-form-panel" id="productForm"<?php echo $product_to_edit || isset($_GET['form']) ? ' open' : ''; ?>>
                    <summary class="admin-form-summary"><span class="eyebrow"><?php echo $product_to_edit ? 'Edit product' : 'Add product'; ?></span><strong><?php echo $product_to_edit ? 'Update catalog item' : 'Create a new inventory item'; ?></strong></summary>
                    <form method="POST" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="inventory_action" value="save_product">
                        <input type="hidden" name="product_id" value="<?php echo (int) ($product_to_edit['id'] ?? 0); ?>">
                        <div class="form-grid">
                            <div class="field">
                                <label for="name">Product name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars((string) ($product_to_edit['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="brand">Brand</label>
                                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars((string) ($product_to_edit['brand'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <?php foreach (get_store_categories() as $key => $label): ?>
                                        <?php if ($key === 'all') { continue; } ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($product_to_edit['category'] ?? 'shoes') === $key ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="style">Style label</label>
                                <input type="text" id="style" name="style" value="<?php echo htmlspecialchars((string) ($product_to_edit['style'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="preference_tags">Tags</label>
                                <input type="text" id="preference_tags" name="preference_tags" value="<?php echo htmlspecialchars(implode(', ', $product_to_edit['preference_tags'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>" placeholder="running, lifestyle, bestseller">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="sizes_us">US sizes</label>
                                <input type="text" id="sizes_us" name="sizes_us" value="<?php echo htmlspecialchars(implode(', ', $product_size_standards_to_edit['US'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>" placeholder="7, 8, 9, 10">
                            </div>
                            <div class="field">
                                <label for="sizes_eu">EU sizes</label>
                                <input type="text" id="sizes_eu" name="sizes_eu" value="<?php echo htmlspecialchars(implode(', ', $product_size_standards_to_edit['EU'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>" placeholder="39, 40, 41, 42">
                            </div>
                            <div class="field">
                                <label for="sizes_cn">China/Asian sizes</label>
                                <input type="text" id="sizes_cn" name="sizes_cn" value="<?php echo htmlspecialchars(implode(', ', $product_size_standards_to_edit['CN'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>" placeholder="40, 41, 42, 43">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="price">Selling price</label>
                                <input type="number" step="0.01" id="price" name="price" value="<?php echo htmlspecialchars((string) ($product_to_edit['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="cost_min">Cost min</label>
                                <input type="number" step="0.01" id="cost_min" name="cost_min" value="<?php echo htmlspecialchars((string) ($product_to_edit['cost_min'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="cost_max">Cost max</label>
                                <input type="number" step="0.01" id="cost_max" name="cost_max" value="<?php echo htmlspecialchars((string) ($product_to_edit['cost_max'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="stock">Base stock</label>
                                <input type="number" id="stock" name="stock" value="<?php echo htmlspecialchars((string) ($product_to_edit['base_stock'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="badge">Badge</label>
                                <input type="text" id="badge" name="badge" value="<?php echo htmlspecialchars((string) ($product_to_edit['badge'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="image">Image URL</label>
                                <input type="text" id="image" name="image" value="<?php echo htmlspecialchars((string) ($product_to_edit['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional custom image URL">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="tone">Tone</label>
                                <input type="text" id="tone" name="tone" value="<?php echo htmlspecialchars((string) ($product_to_edit['tone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="colorway">Colorway</label>
                                <input type="text" id="colorway" name="colorway" value="<?php echo htmlspecialchars((string) ($product_to_edit['colorway'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="old_price">Old price</label>
                                <input type="number" step="0.01" id="old_price" name="old_price" value="<?php echo htmlspecialchars((string) ($product_to_edit['old_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="blurb">Short blurb</label>
                            <textarea id="blurb" name="blurb" rows="3"><?php echo htmlspecialchars((string) ($product_to_edit['blurb'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="field">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars((string) ($product_to_edit['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="choice-grid">
                            <?php foreach (array('featured' => 'Featured', 'trending' => 'Trending', 'best_seller' => 'Best seller', 'limited' => 'Limited', 'new_arrival' => 'New arrival') as $flag => $label): ?>
                                <label class="choice-card">
                                    <input type="checkbox" name="<?php echo htmlspecialchars($flag, ENT_QUOTES, 'UTF-8'); ?>" value="1"<?php echo !empty($product_to_edit[$flag]) ? ' checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark"><?php echo $product_to_edit ? 'Update product' : 'Create product'; ?></button>
                            <?php if ($product_to_edit): ?>
                                <a href="inventory.php" class="button button-light">Cancel edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </details>

                <article class="table-card surface-card" id="productRecords">
                    <span class="eyebrow">Live product table</span>
                    <h2 class="display-font">Every product and its current stock position.</h2>
                    <form method="GET" class="contact-form backoffice-filter-form" role="search">
                        <div class="backoffice-filter-grid">
                            <div class="field"><label for="inventory_search">Search products</label><input type="search" id="inventory_search" name="search" value="<?php echo htmlspecialchars($inventory_filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search product, brand, category, or ID..."></div>
                            <div class="field"><label for="inventory_stock">Stock status</label><select id="inventory_stock" name="stock"><option value="">All stock</option><option value="in"<?php echo $inventory_filters['stock'] === 'in' ? ' selected' : ''; ?>>In stock</option><option value="low"<?php echo $inventory_filters['stock'] === 'low' ? ' selected' : ''; ?>>Low stock</option><option value="out"<?php echo $inventory_filters['stock'] === 'out' ? ' selected' : ''; ?>>Out of stock</option></select></div>
                            <div class="field"><label for="inventory_category">Category</label><select id="inventory_category" name="category"><option value="">All categories</option><?php foreach (get_store_categories() as $key => $label): if ($key === 'all') continue; ?><option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $inventory_filters['category'] === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                            <div class="field"><label for="inventory_sort">Sort</label><select id="inventory_sort" name="sort"><option value="">Product name</option><option value="stock_high"<?php echo $inventory_filters['sort'] === 'stock_high' ? ' selected' : ''; ?>>Highest stock</option><option value="stock_low"<?php echo $inventory_filters['sort'] === 'stock_low' ? ' selected' : ''; ?>>Lowest stock</option></select></div>
                        </div>
                        <details class="backoffice-more-filters"<?php echo $inventory_filters['brand'] !== '' || $inventory_per_page !== 10 ? ' open' : ''; ?>><summary>More filters</summary><div class="backoffice-filter-grid"><div class="field"><label for="inventory_brand">Brand</label><select id="inventory_brand" name="brand"><option value="">All brands</option><?php foreach ($inventory_brands as $brand): ?><option value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $inventory_filters['brand'] === $brand ? ' selected' : ''; ?>><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><div class="field"><label for="inventory_per_page">Rows per page</label><select id="inventory_per_page" name="per_page"><?php foreach (array(10, 20, 50) as $count): ?><option value="<?php echo $count; ?>"<?php echo $inventory_per_page === $count ? ' selected' : ''; ?>><?php echo $count; ?></option><?php endforeach; ?></select></div></div></details>
                        <div class="button-row"><button type="submit" class="button button-dark">Search / filter</button><a href="inventory.php#productRecords" class="button button-light">Clear filters</a></div>
                    </form>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price (PHP)</th>
                                    <th>Total stock</th>
                                    <th>Stock status</th>
                                    <th>Stock by size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory_products)): ?><tr><td colspan="7">No matching records found. <a href="inventory.php#productRecords">Clear filters</a></td></tr><?php endif; ?>
                                <?php foreach ($inventory_products as $product): ?>
                                    <?php $size_stock = store_size_stock_state($product); $size_dialog_id = 'sizeStock-' . (int) $product['id']; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $product['price'], 2); ?></td>
                                        <td><strong><?php echo (int) $product['stock']; ?></strong></td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $product['stock_status'])); ?>"><?php echo htmlspecialchars((string) $product['stock_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>
                                            <?php if ($size_stock['standard'] === ''): ?>
                                                <span class="field-note">Not applicable</span>
                                            <?php elseif ($size_stock['configured']): ?>
                                                <div class="backoffice-size-summary" aria-label="Remaining stock by size">
                                                    <?php foreach ($size_stock['rows'] as $size_row): ?>
                                                        <span class="backoffice-size-chip<?php echo (int) $size_row['quantity'] === 0 ? ' is-out' : ''; ?>"><?php echo htmlspecialchars($size_stock['standard'] . ' ' . $size_row['size'], ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo (int) $size_row['quantity']; ?></strong><?php echo (int) $size_row['quantity'] === 0 ? ' · Out of stock' : ''; ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="field-note"><?php echo $size_stock['allocation_exists'] ? 'Reconciliation needed' : 'Allocation needed'; ?></span>
                                            <?php endif; ?>
                                                <dialog id="<?php echo $size_dialog_id; ?>" class="backoffice-size-dialog" aria-labelledby="<?php echo $size_dialog_id; ?>Title">
                                                    <div class="backoffice-size-dialog-inner">
                                                        <div class="section-head compact-head"><div><span class="eyebrow">Inventory details</span><h2 id="<?php echo $size_dialog_id; ?>Title" class="display-font"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2></div><button type="button" class="button button-light" data-size-dialog-close>Close</button></div>
                                                        <p><strong>Category:</strong> <?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?><br><strong>Brand:</strong> <?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?><br><strong>Price:</strong> <?php echo htmlspecialchars(store_format_money((float) $product['price']), ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <p><strong>Stock status:</strong> <?php echo htmlspecialchars((string) $product['stock_status'], ENT_QUOTES, 'UTF-8'); ?><br><strong>Suggested stock:</strong> <?php echo (int) $product['suggested_stock']; ?><br><strong>Estimated cost:</strong> <?php echo htmlspecialchars((string) $product['cost_display'], ENT_QUOTES, 'UTF-8'); ?><br><strong>Estimated profit:</strong> <?php echo htmlspecialchars((string) $product['profit_display'], ENT_QUOTES, 'UTF-8'); ?><br><strong>Reservations:</strong> <?php echo (int) $product['reservations']; ?></p>
                                                        <p><strong>Current total stock:</strong> <?php echo (int) $product['stock']; ?> units<br><strong>Size standard:</strong> <?php echo $size_stock['standard'] === '' ? 'Not applicable' : htmlspecialchars(store_size_standard_label($size_stock['standard']), ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <?php if ($size_stock['standard'] !== ''): ?>
                                                        <p><strong>Available sizes:</strong> <?php echo htmlspecialchars(implode(', ', array_map('strval', (array) ($product['size_standards'][$size_stock['standard']] ?? array()))), ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <?php if ($size_stock['configured']): ?>
                                                            <div class="table-wrap"><table class="data-table"><thead><tr><th><?php echo htmlspecialchars($size_stock['standard'], ENT_QUOTES, 'UTF-8'); ?> size</th><th>Remaining stock</th></tr></thead><tbody><?php foreach ($size_stock['rows'] as $size_row): ?><tr><td><?php echo htmlspecialchars((string) $size_row['size'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $size_row['quantity']; ?> units<?php echo (int) $size_row['quantity'] === 0 ? ' · Out of stock' : ''; ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th>Total remaining stock</th><th><?php echo array_sum(array_column($size_stock['rows'], 'quantity')); ?> units</th></tr></tfoot></table></div>
                                                        <?php else: ?><p class="backoffice-size-notice"><?php echo htmlspecialchars($size_stock['reason'], ENT_QUOTES, 'UTF-8'); ?> Current total stock has been preserved; no size quantities have been guessed.</p><?php endif; ?>
                                                        <details class="backoffice-more-filters"><summary><?php echo $size_stock['configured'] ? 'Edit size stock' : 'Configure size stock'; ?></summary>
                                                            <p class="field-note">Allocate the current available total across the listed sizes. Other size standards represent these same variants in the same order.</p>
                                                            <form method="POST" class="contact-form" data-size-allocation-form data-target-stock="<?php echo (int) $product['stock']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="hidden" name="inventory_action" value="save_size_stock">
                                                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                                                <div class="backoffice-size-inputs">
                                                                    <?php foreach ((array) ($product['size_standards'][$size_stock['standard']] ?? array()) as $index => $size): ?>
                                                                        <div class="field"><label for="size-<?php echo (int) $product['id']; ?>-<?php echo (int) $index; ?>"><?php echo htmlspecialchars(store_size_standard_label($size_stock['standard']) . ' ' . $size, ENT_QUOTES, 'UTF-8'); ?></label><input type="number" id="size-<?php echo (int) $product['id']; ?>-<?php echo (int) $index; ?>" name="quantities[<?php echo (int) $index; ?>]" min="0" step="1" value="<?php echo $size_stock['configured'] ? (int) $size_stock['rows'][$index]['quantity'] : ''; ?>" required></div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <p class="field-note" role="status">Allocated: <strong data-allocation-total>0</strong> / <?php echo (int) $product['stock']; ?> current units</p>
                                                                <button type="submit" class="button button-dark">Save size stock</button>
                                                            </form>
                                                        </details>
                                                        <?php endif; ?>
                                                    </div>
                                                </dialog>
                                        </td>
                                        <td>
                                            <div class="button-row">
                                                <a href="inventory.php?edit=<?php echo (int) $product['id']; ?>#productForm" class="button button-light">Edit</a>
                                                <button type="button" class="button button-light" data-size-dialog-open="<?php echo $size_dialog_id; ?>">View Details</button>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="inventory_action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                                    <button type="submit" class="button button-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($product_pagination, 'productRecords'); ?>
                </article>

                <script>
                document.querySelectorAll('[data-size-dialog-open]').forEach(function (button) {
                    const dialog = document.getElementById(button.dataset.sizeDialogOpen);
                    button.addEventListener('click', function () { if (dialog && !dialog.open) dialog.showModal(); });
                    dialog?.addEventListener('close', function () { button.focus(); });
                });
                document.querySelectorAll('[data-size-dialog-close]').forEach(function (button) {
                    button.addEventListener('click', function () { button.closest('dialog')?.close(); });
                });
                document.querySelectorAll('[data-size-allocation-form]').forEach(function (form) {
                    const inputs = Array.from(form.querySelectorAll('input[name^="quantities["]'));
                    const total = form.querySelector('[data-allocation-total]');
                    const update = function () {
                        const allocated = inputs.reduce(function (sum, input) { return sum + (Number(input.value) || 0); }, 0);
                        total.textContent = String(allocated);
                        total.parentElement.classList.toggle('is-invalid', allocated !== Number(form.dataset.targetStock));
                    };
                    inputs.forEach(function (input) { input.addEventListener('input', update); });
                    update();
                });
                </script>

                <article class="table-card surface-card" id="stockHistory">
                    <span class="eyebrow">Inventory history logs</span>
                    <h2 class="display-font">Who updated stock and when.</h2>
                    <form method="GET" class="contact-form backoffice-filter-form" role="search"><div class="backoffice-filter-grid"><div class="field"><label for="inventory_log_search">Search stock history</label><input type="search" id="inventory_log_search" name="log_search" value="<?php echo htmlspecialchars($inventory_log_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search product, action, or staff..."></div></div><div class="button-row"><button type="submit" class="button button-dark">Search logs</button><a href="inventory.php#stockHistory" class="button button-light">Clear</a></div></form>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Product</th>
                                    <th>Action</th>
                                    <th>User</th>
                                    <th>Before</th>
                                    <th>After</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory_log_page['rows'])): ?><tr><td colspan="7">No matching records found.</td></tr><?php endif; ?>
                                <?php foreach ($inventory_log_page['rows'] as $log): ?>
                                    <?php $log_time = backoffice_manila_datetime($log['created_at'] ?? ''); ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($log['event_date'])): ?>
                                                <?php echo htmlspecialchars(date('M j, Y', strtotime((string) $log['event_date'])), ENT_QUOTES, 'UTF-8'); ?><br><small>Historical event</small>
                                            <?php else: ?>
                                                <?php echo $log_time ? htmlspecialchars($log_time->format('M j, Y'), ENT_QUOTES, 'UTF-8') . '<br><small>' . htmlspecialchars($log_time->format('g:i A'), ENT_QUOTES, 'UTF-8') . '</small>' : 'Date unavailable'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((string) ($log['product_name'] ?? 'Deleted product'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(($log['action_type'] ?? '') === 'historical_restock_import' ? 'Historical import' : ucwords(str_replace('_', ' ', (string) $log['action_type'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $log['actor_name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $log['actor_role'])), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo empty($log['affects_live_stock']) ? 'Reference only' : (int) $log['stock_before']; ?></td>
                                        <td><?php echo empty($log['affects_live_stock']) ? 'No live change' : (int) $log['stock_after']; ?></td>
                                        <td><details class="table-details" data-detail-title="Inventory history details"><summary>View Details</summary><div>
                                            <p><strong>Log ID:</strong> <?php echo (int) ($log['id'] ?? 0); ?></p>
                                            <p><strong>Product:</strong> <?php echo htmlspecialchars((string) ($log['product_name'] ?? 'Deleted product'), ENT_QUOTES, 'UTF-8'); ?> (ID <?php echo (int) ($log['product_id'] ?? 0); ?>)</p>
                                            <p><strong>Action:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($log['action_type'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p><strong>Actor:</strong> <?php echo htmlspecialchars((string) ($log['actor_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string) ($log['actor_role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</p>
                                            <p><strong>Actor email:</strong> <?php echo htmlspecialchars((string) ($log['actor_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p><strong>Recorded at:</strong> <?php echo $log_time ? htmlspecialchars($log_time->format('F j, Y, g:i A'), ENT_QUOTES, 'UTF-8') . ' PHT' : 'Date unavailable'; ?></p>
                                            <p><strong>Historical event date:</strong> <?php echo !empty($log['event_date']) ? htmlspecialchars((string) $log['event_date'], ENT_QUOTES, 'UTF-8') : 'None'; ?></p>
                                            <p><strong>Live stock:</strong> <?php echo empty($log['affects_live_stock']) ? 'Reference only — no live stock change' : 'Changed live stock'; ?></p>
                                            <p><strong>Stock before / after:</strong> <?php echo (int) ($log['stock_before'] ?? 0); ?> / <?php echo (int) ($log['stock_after'] ?? 0); ?></p>
                                            <p><strong>Quantity change:</strong> <?php echo (int) ($log['quantity_change'] ?? 0); ?></p>
                                            <p><strong>External reference:</strong> <?php echo htmlspecialchars((string) (($log['external_reference'] ?? '') !== '' ? $log['external_reference'] : 'None'), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p><strong>Import batch ID:</strong> <?php echo !empty($log['import_batch_id']) ? (int) $log['import_batch_id'] : 'None'; ?></p>
                                            <p><strong>Note:</strong> <?php echo trim((string) ($log['note'] ?? '')) !== '' ? nl2br(htmlspecialchars((string) $log['note'], ENT_QUOTES, 'UTF-8')) : 'No note provided.'; ?></p>
                                        </div></details></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($inventory_log_page, 'stockHistory'); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
