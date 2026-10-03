<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
$stats = backoffice_get_stats($conn);
$insights = $stats['recommendation_insights'];

backoffice_render_head('Shoestagram | Admin Recommendations');
backoffice_render_shell_start(
    'admin',
    'recommendations',
    'Recommendation management',
    'Monitor what the system recommends most, what customers view, and what gets reserved most often.',
    'This module combines recommendation score, product view tracking, reservation pressure, and trend data so you can see what the engine is pushing and what customers actually respond to.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Recommendation resets</span>
                        <strong><?php echo (int) $insights['recommendation_resets']; ?></strong>
                        <span>How often customers reset recommendation signals on the home page.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Most viewed</span>
                        <strong><?php echo !empty($insights['most_viewed'][0]) ? (int) $insights['most_viewed'][0]['views_count'] : 0; ?></strong>
                        <span>Current top tracked product view count.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Most reserved</span>
                        <strong><?php echo !empty($insights['most_reserved'][0]) ? (int) $insights['most_reserved'][0]['reservations'] : 0; ?></strong>
                        <span>Highest reservation pressure in the catalog.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Engine focus</span>
                        <strong><?php echo count($insights['most_recommended']); ?></strong>
                        <span>High-priority products in the current recommendation set.</span>
                    </article>
                </div>

                <article class="table-card surface-card">
                    <span class="eyebrow">Most recommended products</span>
                    <h2 class="display-font">Products the system is most likely to push.</h2>
                    <div class="mini-catalog recommendation-catalog">
                        <?php foreach ($insights['most_recommended'] as $product): ?>
                            <article class="mini-card hover-lift">
                                <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p><?php echo htmlspecialchars(get_store_recommendation_reason($product), ENT_QUOTES, 'UTF-8'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>

                <div class="admin-card-grid">
                    <article class="admin-card surface-card">
                        <span class="eyebrow">Clicked / viewed items</span>
                        <h2 class="display-font">Products customers open most often.</h2>
                        <div class="chart-list">
                            <?php foreach ($insights['most_viewed'] as $product): ?>
                                <div class="chart-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo (int) $product['views_count']; ?> tracked view(s)</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-bar" style="width: <?php echo !empty($insights['most_viewed'][0]['views_count']) ? round(((int) $product['views_count'] / (int) $insights['most_viewed'][0]['views_count']) * 100, 2) : 0; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Frequently reserved items</span>
                        <h2 class="display-font">Products customers reserve most often.</h2>
                        <div class="chart-list">
                            <?php foreach ($insights['most_reserved'] as $product): ?>
                                <div class="chart-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo (int) $product['reservations']; ?> reservation signals</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-bar" style="width: <?php echo !empty($insights['most_reserved'][0]['reservations']) ? round(((int) $product['reservations'] / (int) $insights['most_reserved'][0]['reservations']) * 100, 2) : 0; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>
<?php backoffice_render_shell_end(); ?>
