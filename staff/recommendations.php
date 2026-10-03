<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
$stats = backoffice_get_stats($conn);
$insights = $stats['recommendation_insights'];

backoffice_render_head('Shoestagram | Staff Recommendations');
backoffice_render_shell_start(
    'staff',
    'recommendations',
    'Recommendation view',
    'Staff can see what the system recommends most and what customers interact with most.',
    'This helps staff understand which products are easiest to suggest in-store based on availability, popularity, and reservation pressure.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to dashboard', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Recommended products</span>
                        <h2 class="display-font">Best products to suggest right now.</h2>
                        <div class="mini-catalog">
                            <?php foreach ($insights['most_recommended'] as $product): ?>
                                <article class="mini-card hover-lift">
                                    <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <p><?php echo htmlspecialchars(get_store_recommendation_reason($product), ENT_QUOTES, 'UTF-8'); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Best-selling and available</span>
                        <h2 class="display-font">Products staff can confidently recommend.</h2>
                        <div class="chart-list">
                            <?php foreach (array_slice($stats['top_products'], 0, 6) as $product): ?>
                                <div class="chart-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo (int) $product['sales_count']; ?> sales · <?php echo (int) $product['stock']; ?> left</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-bar" style="width: <?php echo $stats['max_sales'] > 0 ? round(((int) $product['sales_count'] / $stats['max_sales']) * 100, 2) : 0; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>
<?php backoffice_render_shell_end(); ?>
