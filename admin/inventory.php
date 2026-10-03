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
}

$stats = backoffice_get_stats($conn);
$inventory_products = $stats['products'];
$product_pagination = backoffice_paginate_rows($inventory_products, 'product_page');
$inventory_log_page = database_get_inventory_logs_page($conn, (int) ($_GET['log_page'] ?? 1), 10);
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
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price (PHP)</th>
                                    <th>Stock status</th>
                                    <th>Suggested stock</th>
                                    <th>Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory_products)): ?><tr><td colspan="7">No products are available yet.</td></tr><?php endif; ?>
                                <?php foreach ($inventory_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $product['price'], 2); ?></td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $product['stock_status'])); ?>"><?php echo htmlspecialchars((string) $product['stock_status'], ENT_QUOTES, 'UTF-8'); ?></span><br><small><?php echo (int) $product['stock']; ?> left</small></td>
                                        <td><?php echo (int) $product['suggested_stock']; ?></td>
                                        <td><details class="table-details"><summary>View details</summary><div><p><strong>Estimated cost:</strong> <?php echo htmlspecialchars((string) $product['cost_display'], ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Estimated profit:</strong> <?php echo htmlspecialchars((string) $product['profit_display'], ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Reservations:</strong> <?php echo (int) $product['reservations']; ?></p></div></details></td>
                                        <td>
                                            <div class="button-row">
                                                <a href="inventory.php?edit=<?php echo (int) $product['id']; ?>#productForm" class="button button-light">Edit</a>
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

                <article class="table-card surface-card" id="stockHistory">
                    <span class="eyebrow">Inventory history logs</span>
                    <h2 class="display-font">Who updated stock and when.</h2>
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
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory_log_page['rows'])): ?><tr><td colspan="7">No stock changes have been recorded yet.</td></tr><?php endif; ?>
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
                                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $log['action_type'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $log['actor_name'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $log['actor_role'])), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo empty($log['affects_live_stock']) ? 'Reference only' : (int) $log['stock_before']; ?></td>
                                        <td><?php echo empty($log['affects_live_stock']) ? 'No live change' : (int) $log['stock_after']; ?></td>
                                        <td><?php if (trim((string) ($log['note'] ?? '')) !== ''): ?><details class="table-details"><summary><?php echo htmlspecialchars(function_exists('mb_strimwidth') ? mb_strimwidth((string) $log['note'], 0, 48, '…', 'UTF-8') : substr((string) $log['note'], 0, 48), ENT_QUOTES, 'UTF-8'); ?> <span>View</span></summary><div><?php echo nl2br(htmlspecialchars((string) $log['note'], ENT_QUOTES, 'UTF-8')); ?></div></details><?php else: ?>—<?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($inventory_log_page, 'stockHistory'); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
