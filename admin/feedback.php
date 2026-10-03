<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
backoffice_handle_review_update('admin');
$stats = backoffice_get_stats($conn);
$feedback_reviews = $stats['reviews'];
usort($feedback_reviews, function ($left, $right) {
    $date = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    return $date !== 0 ? $date : strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
});
$review_pagination = backoffice_paginate_rows($feedback_reviews, 'review_page', 10);
$feedback_messages = store_state_get_messages();
usort($feedback_messages, function ($left, $right) {
    $date = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    return $date !== 0 ? $date : strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
});
$message_pagination = backoffice_paginate_rows($feedback_messages, 'message_page', 10);

backoffice_render_head('Shoestagram | Admin Feedback');
backoffice_render_shell_start(
    'admin',
    'feedback',
    'Feedback and support',
    'Reviews and customer messages in their own dedicated panel.',
    'This page keeps post-purchase sentiment and support concerns visible without mixing them into sales or inventory screens.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="feedback-sections">
                    <?php backoffice_render_review_management($feedback_reviews, $review_pagination); ?>
                    <details class="admin-secondary-panel"<?php echo isset($_GET['message_page']) ? ' open' : ''; ?>>
                        <summary>View customer messages</summary>
                        <div class="admin-secondary-content">
                    <article class="admin-card surface-card" id="messages">
                        <span class="eyebrow">Support inbox</span>
                        <h2 class="display-font">Customer messages, newest first.</h2>
                        <div class="admin-alerts">
                            <?php if (empty($feedback_messages)): ?><p>No customer messages yet.</p><?php endif; ?>
                            <?php foreach ($feedback_messages as $message): ?>
                                <article class="alert-card info">
                                    <i class="fas fa-envelope"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo htmlspecialchars($message['name'], ENT_QUOTES, 'UTF-8'); ?><?php $message_time = backoffice_manila_datetime($message['created_at'] ?? ''); ?> · <?php echo $message_time ? htmlspecialchars($message_time->format('M j, Y · g:i A'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></p>
                                        <p class="record-comment-preview"><?php echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <details class="table-details"><summary>View message</summary><div><p><strong>From:</strong> <?php echo htmlspecialchars($message['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($message['email'], ENT_QUOTES, 'UTF-8'); ?>)</p><p><?php echo nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8')); ?></p></div></details>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php backoffice_render_pagination($message_pagination, 'messages'); ?>
                    </article>
                        </div>
                    </details>
                </div>
<?php backoffice_render_shell_end(); ?>
