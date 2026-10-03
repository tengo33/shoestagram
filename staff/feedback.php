<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
backoffice_handle_review_update('staff');
$stats = backoffice_get_stats($conn);

backoffice_render_head('Shoestagram | Staff Feedback');
backoffice_render_shell_start(
    'staff',
    'feedback',
    'Feedback and support',
    'Customer messages and review notes for day-to-day assistance.',
    'This mirrors the admin feedback view so staff can respond better on the floor or during reservation follow-up.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to dashboard', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="admin-card-grid">
                    <?php backoffice_render_review_management($stats['reviews']); ?>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Customer messages</span>
                        <h2 class="display-font">Latest support requests.</h2>
                        <div class="admin-alerts">
                            <?php foreach ($stats['recent_messages'] as $message): ?>
                                <article class="alert-card info">
                                    <i class="fas fa-envelope"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo htmlspecialchars($message['name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($message['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p style="margin: 8px 0 0; color: var(--muted);"><?php echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card surface-card">
                        <span class="eyebrow">Recent review notes</span>
                        <h2 class="display-font">Customer submissions and development records.</h2>
                        <div class="admin-alerts">
                            <?php foreach ($stats['recent_reviews'] as $review): ?>
                                <article class="alert-card success">
                                    <i class="fas fa-star"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['product_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $review['rating']; ?>/5</strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo htmlspecialchars($review['customer_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p style="margin: 6px 0 0; color: var(--parchment-dark); font-weight: 800;"><?php echo store_state_review_is_demo($review) ? 'Development seed' : 'Customer submission'; ?></p>
                                        <p style="margin: 8px 0 0; color: var(--muted);"><?php echo htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>
<?php backoffice_render_shell_end(); ?>
