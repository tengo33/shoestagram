<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
backoffice_handle_review_update('admin');
$stats = backoffice_get_stats($conn);
$feedback_search = backoffice_query_text('search');
$review_status_filter = backoffice_query_text('review_status', 30);
$feedback_reviews = backoffice_filter_feedback_reviews($stats['reviews'], $feedback_search, $review_status_filter);
usort($feedback_reviews, function ($left, $right) {
    $date = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    return $date !== 0 ? $date : strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
});
$review_pagination = backoffice_paginate_rows($feedback_reviews, 'review_page', 10);
$feedback_messages = store_state_get_messages();
$feedback_messages = backoffice_filter_feedback_messages($feedback_messages, $feedback_search);
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
                <article class="admin-card surface-card"><span class="eyebrow">Find feedback</span><form method="GET" class="contact-form backoffice-filter-form" role="search"><div class="backoffice-filter-grid"><div class="field"><label for="feedback_search">Search reviews and messages</label><input type="search" id="feedback_search" name="search" value="<?php echo htmlspecialchars($feedback_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search customer, product, or comment..."></div><div class="field"><label for="review_status">Review status</label><select id="review_status" name="review_status"><option value="">All reviews</option><?php foreach (store_state_review_statuses() as $status): ?><option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $review_status_filter === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div></div><p class="field-note">Review status applies only to reviews; text search also checks customer messages.</p><div class="button-row"><button type="submit" class="button button-dark">Search / filter</button><a href="feedback.php" class="button button-light">Clear filters</a></div></form></article>
                <div class="feedback-sections">
                    <?php backoffice_render_review_management($feedback_reviews, $review_pagination); ?>
                    <details class="admin-secondary-panel"<?php echo isset($_GET['message_page']) || $feedback_search !== '' ? ' open' : ''; ?>>
                        <summary>View customer messages</summary>
                        <div class="admin-secondary-content">
                    <article class="admin-card surface-card" id="messages">
                        <span class="eyebrow">Support inbox</span>
                        <h2 class="display-font">Customer messages, newest first.</h2>
                        <div class="admin-alerts">
                            <?php if (empty($feedback_messages)): ?><p>No matching records found.</p><?php endif; ?>
                            <?php foreach ($feedback_messages as $message): ?>
                                <article class="alert-card info">
                                    <i class="fas fa-envelope"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <p style="margin: 6px 0 0; color: var(--muted);"><?php echo htmlspecialchars($message['name'], ENT_QUOTES, 'UTF-8'); ?><?php $message_time = backoffice_manila_datetime($message['created_at'] ?? ''); ?> · <?php echo $message_time ? htmlspecialchars($message_time->format('M j, Y · g:i A'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></p>
                                        <p class="record-comment-preview"><?php echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <details class="table-details" data-detail-title="Customer message"><summary>View message</summary><div><p><strong>Subject:</strong> <?php echo htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8'); ?></p><p><strong>From:</strong> <?php echo htmlspecialchars($message['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($message['email'], ENT_QUOTES, 'UTF-8'); ?>)</p><?php if ($message_time): ?><p><strong>Received:</strong> <?php echo htmlspecialchars($message_time->format('M j, Y · g:i A'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?><p><strong>Message:</strong><br><?php echo nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8')); ?></p></div></details>
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
