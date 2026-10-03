<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
$edit_id = (int) ($_GET['edit_sizes'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the sizing update again.');
        header('Location: inventory.php');
        exit();
    }

    if (($_POST['inventory_action'] ?? '') === 'save_product_sizes') {
        $result = store_update_product_size_standards((int) ($_POST['product_id'] ?? 0), $_POST);
        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: inventory.php');
        exit();
    }
}

$stats = backoffice_get_stats($conn);
$inventory_products = $stats['products'];
$product_pagination = backoffice_paginate_rows($inventory_products, 'product_page');
$product_to_edit = $edit_id > 0 ? get_store_product_by_id($edit_id) : null;
$product_size_standards_to_edit = store_normalize_size_standards(
    $product_to_edit['size_standards'] ?? array(),
    $product_to_edit['sizes'] ?? array(),
    $product_to_edit['category'] ?? 'shoes'
);

backoffice_render_head('Shoestagram | Staff Inventory');
backoffice_render_shell_start(
    'staff',
    'inventory',
    'Inventory watch',
    'A staff-focused stock view with low-stock and overstock alerts.',
    'Stock counts already reflect active reservations and completed deductions, so this page is safe to use as the live floor reference.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to dashboard', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Low stock alert</span>
                        <h2 class="display-font">Products that may need floor attention soon.</h2>
                        <div class="admin-alerts">
                            <?php foreach (array_slice($stats['low_stock_products'], 0, 8) as $product): ?>
                                <article class="alert-card warning">
                                    <i class="fas fa-box-open"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo (int) $product['stock']; ?> left · <?php echo (int) $product['reservations']; ?> reservation(s)</p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Overstock alert</span>
                        <h2 class="display-font">Products that should move faster.</h2>
                        <div class="admin-alerts">
                            <?php foreach (array_slice($stats['overstock_products'], 0, 8) as $product): ?>
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

                <?php if ($product_to_edit): ?>
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Edit size standards</span>
                        <h2 class="display-font"><?php echo htmlspecialchars($product_to_edit['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <form method="POST" class="contact-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="inventory_action" value="save_product_sizes">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product_to_edit['id']; ?>">
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
                            <div class="button-row">
                                <button type="submit" class="button button-dark">Update sizes</button>
                                <a href="inventory.php" class="button button-light">Cancel edit</a>
                            </div>
                        </form>
                    </article>
                <?php endif; ?>

                <article class="table-card surface-card">
                    <span class="eyebrow">Inventory table</span>
                    <h2 class="display-font">Stock positions across the full catalog.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Suggested stock</th>
                                    <th>Reservations</th>
                                    <th>Sizes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $product['stock_status'])); ?>"><?php echo (int) $product['stock']; ?> left</span></td>
                                        <td><?php echo (int) $product['suggested_stock']; ?></td>
                                        <td><?php echo (int) $product['reservations']; ?></td>
                                        <td>
                                            <?php foreach (store_size_standard_labels() as $standard_key => $standard_label): ?>
                                                <?php if (empty($product['size_standards'][$standard_key])) { continue; } ?>
                                                <small><?php echo htmlspecialchars($standard_label . ': ' . implode(', ', $product['size_standards'][$standard_key]), ENT_QUOTES, 'UTF-8'); ?></small><br>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><a href="inventory.php?edit_sizes=<?php echo (int) $product['id']; ?>" class="button button-light">Edit sizes</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($product_pagination); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
