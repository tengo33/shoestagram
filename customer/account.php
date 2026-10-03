<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/config.php';
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();
storefront_require_login('account.php');
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$password_rules = database_password_requirements_text();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try that again.');
        storefront_redirect('account.php');
    }

    $account_action = trim((string) ($_POST['account_action'] ?? ''));

    if ($account_action === 'preferences') {
        storefront_set_preferences(
            $_POST['preferred_brand'] ?? '',
            $_POST['preferred_style'] ?? '',
            $_POST['max_price'] ?? ''
        );
        storefront_set_flash('success', 'Recommendation preferences updated.');
        storefront_redirect('account.php#preferences');
    }

    if ($account_action === 'review') {
        $result = store_state_submit_review(
            trim((string) ($_POST['order_id'] ?? '')),
            $context,
            (int) ($_POST['rating'] ?? 5),
            $_POST['comment'] ?? '',
            (int) ($_POST['product_id'] ?? 0),
            $_POST['title'] ?? ''
        );

        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        storefront_redirect('account.php#reviews');
    }

    if ($account_action === 'cancel_order') {
        $result = store_state_cancel_customer_order(
            trim((string) ($_POST['order_id'] ?? '')),
            (string) ($_SESSION['user_email'] ?? '')
        );

        if (!empty($result['ok']) && !empty($result['order'])) {
            store_send_order_email(
                $result['order'],
                'Shoestagram reservation cancelled ' . $result['order']['id'],
                'Your reservation has been cancelled.'
            );
        }

        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        storefront_redirect('account.php#orders');
    }

    if ($account_action === 'profile') {
        $result = database_update_user_record($conn, $user_id, array(
            'fullname' => $_POST['fullname'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'customer',
            'is_active' => 1,
        ));

        if (!empty($result['ok'])) {
            store_state_move_user_profile($result['old_email'] ?? '', $result['new_email'] ?? '');
            database_sync_session_user($conn, $user_id);
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Account',
                'Customer profile details updated.',
                'Success'
            );
            storefront_set_flash('success', 'Profile details updated successfully.');
        } else {
            storefront_set_flash('error', $result['message']);
        }

        storefront_redirect('account.php#profile');
    }

    if ($account_action === 'password') {
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        if ($new_password !== $confirm_password) {
            storefront_set_flash('error', 'The new passwords did not match.');
            storefront_redirect('account.php#security');
        }

        $result = database_update_user_password(
            $conn,
            $user_id,
            $_POST['current_password'] ?? '',
            $new_password,
            true
        );

        if (!empty($result['ok'])) {
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Account',
                'Password changed from the customer account.',
                'Success'
            );
        }

        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        storefront_redirect('account.php#security');
    }
}

$context = storefront_get_context();
$preferences = storefront_get_preferences();
$profile = store_state_get_user_profile($context['user_email']);
$user = database_get_user_by_id($conn, $user_id) ?: array(
    'fullname' => $context['user_name'],
    'email' => $context['user_email'],
    'phone' => '',
    'address' => '',
);
$size_summary = trim(
    (($profile['shoe_size'] ?? '') !== '' ? 'Shoes ' . $profile['shoe_size'] : '')
    . ((($profile['shoe_size'] ?? '') !== '' && ($profile['apparel_size'] ?? '') !== '') ? ' · ' : '')
    . (($profile['apparel_size'] ?? '') !== '' ? 'Apparel ' . $profile['apparel_size'] : '')
);
$orders = store_state_get_customer_orders($context['user_email']);
$checkout_groups = store_group_checkout_orders($orders);
$active_orders = array_values(array_filter($checkout_groups, function ($order) {
    return store_state_is_active_reservation((string) ($order['status'] ?? ''));
}));
$completed_orders = array_values(array_filter($checkout_groups, function ($order) {
    return ($order['status'] ?? '') === 'Completed'
        && ($order['payment_status'] ?? '') !== 'Failed/Rejected';
}));
$reviewed_order_ids = array();
foreach (store_state_get_reviews() as $review) {
    if (!store_state_review_is_demo($review) && trim((string) ($review['order_id'] ?? '')) !== '') {
        $reviewed_order_ids[(string) $review['order_id']] = true;
    }
}
$reviewable_orders = array_values(array_filter($orders, function ($order) use ($reviewed_order_ids) {
    if (($order['status'] ?? '') !== 'Completed' || ($order['payment_status'] ?? '') === 'Failed/Rejected') {
        return false;
    }
    return empty($order['review_submitted']) && empty($reviewed_order_ids[(string) ($order['id'] ?? '')]);
}));
$order_pages = max(1, (int) ceil(count($checkout_groups) / 20));
$order_page = min($order_pages, max(1, (int) ($_GET['order_page'] ?? 1)));
$visible_checkout_groups = array_slice($checkout_groups, ($order_page - 1) * 20, 20);
$recent_products = array();

foreach (storefront_get_recent_views() as $product_id) {
    $product = get_store_product_by_id($product_id);

    if ($product) {
        $recent_products[] = $product;
    }
}

$personalization_seed = storefront_get_personalization_seed($context);
$recommended = get_store_recommended_products(array(
    'brand' => $personalization_seed['brand'],
    'style' => $personalization_seed['style'],
    'max_price' => $personalization_seed['max_price'],
    'limit' => 4,
));
$brands = get_store_brand_names();
$style_options = get_store_style_names();
$budget_options = get_store_budget_options();

storefront_render_head(
    'Shoestagram | Account',
    'Manage your Shoestagram profile, recommendation preferences, reservations, reviews, and account security.'
);
?>
<body class="store-body">
<?php storefront_render_header('account', $context, array(
    'search_placeholder' => 'Search before you reserve again',
    'brand_tagline' => 'Profile, Orders, and Reviews',
    'announcement' => 'Your account now covers profile details, password control, reservation history, and recommendation tuning.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow">My account</span>
                        <span class="hero-chip">Your Shoestagram</span>
                    </div>
                    <h1 class="display-font hero-title">Welcome back, <?php echo htmlspecialchars($context['user_name'], ENT_QUOTES, 'UTF-8'); ?>.</h1>
                    <p class="hero-description">Your details, favorite styles, and reservations. All in one place.</p>
                    <div class="hero-notes">
                        <div class="hero-note">
                            <strong data-countup="<?php echo count($active_orders); ?>">0</strong>
                            <span>active reservation<?php echo count($active_orders) === 1 ? '' : 's'; ?> still moving</span>
                        </div>
                        <div class="hero-note">
                            <strong data-countup="<?php echo count($completed_orders); ?>">0</strong>
                            <span>completed order<?php echo count($completed_orders) === 1 ? '' : 's'; ?> in your history</span>
                        </div>
                        <div class="hero-note">
                            <strong><?php echo htmlspecialchars($size_summary !== '' ? $size_summary : 'Profile pending', ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>saved sizing used by the recommendation engine</span>
                        </div>
                    </div>
                </article>

                <aside class="surface-card showcase-panel">
                    <div class="showcase-heading">
                        <span class="eyebrow">Profile snapshot</span>
                        <p>These details affect storefront recommendations, future account access, and order contact accuracy.</p>
                    </div>
                    <div class="support-list">
                        <div>
                            <strong>Email</strong>
                            <span><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div>
                            <strong>Phone</strong>
                            <span><?php echo htmlspecialchars($user['phone'] !== '' ? $user['phone'] : 'Not set yet', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div>
                            <strong>Preferred brand</strong>
                            <span><?php echo htmlspecialchars($preferences['brand'] !== '' ? $preferences['brand'] : 'Not set yet', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <nav class="account-shortcuts" aria-label="Account sections"><a href="#profile">My details</a><a href="#security">Security</a><a href="#orders">Orders &amp; reservations</a><a href="preferences.php">My fit profile</a><a href="wishlist.php">Saved pieces</a></nav>
        <section class="section reveal" id="profile">
            <div class="split-showcase">
                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Profile</span>
                            <h2 class="display-font section-title">Update your personal details.</h2>
                        </div>
                    </div>
                    <form method="POST" action="account.php#profile" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="account_action" value="profile">
                        <div class="form-grid">
                            <div class="field">
                                <label for="fullname">Full name</label>
                                <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0917 000 0000">
                            </div>
                            <div class="field">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars((string) ($user['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="City or pickup note">
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Save details</button>
                        </div>
                    </form>
                </article>

                <article class="surface-card split-panel" id="security">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Security</span>
                            <h2 class="display-font section-title">Change your password.</h2>
                        </div>
                    </div>
                    <form method="POST" action="account.php#security" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="account_action" value="password">
                        <div class="field">
                            <label for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                            <div class="form-grid password-pair-grid">
                                <div class="field">
                                    <label for="new_password">New password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                    <small class="password-note"><?php echo htmlspecialchars($password_rules, ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                                <div class="field">
                                    <label for="confirm_password">Confirm new password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Update password</button>
                        </div>
                    </form>
                </article>
            </div>
        </section>

        <section class="section reveal" id="preferences">
            <div class="split-showcase">
                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Recommendation profile</span>
                            <h2 class="display-font section-title">Tune your recommendation mix.</h2>
                        </div>
                    </div>
                    <form method="POST" action="account.php#preferences" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="account_action" value="preferences">
                        <div class="form-grid">
                            <div class="field">
                                <label for="preferred_brand">Preferred brand</label>
                                <select id="preferred_brand" name="preferred_brand">
                                    <option value="">No preference</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $preferences['brand'] === $brand ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="preferred_style">Preferred style</label>
                                <select id="preferred_style" name="preferred_style">
                                    <option value="">No preference</option>
                                    <?php foreach ($style_options as $style): ?>
                                        <option value="<?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $preferences['style'] === $style ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="max_price">Budget ceiling</label>
                            <select id="max_price" name="max_price">
                                <option value="">Any price</option>
                                <?php foreach ($budget_options as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $preferences['max_price'] === $value ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Save preferences</button>
                            <a href="preferences.php" class="button button-light">Edit full fit profile</a>
                        </div>
                    </form>
                </article>

                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Recommended for you</span>
                            <h2 class="display-font section-title">Fresh suggestions from your latest profile.</h2>
                        </div>
                    </div>
                    <div class="product-grid compact-grid">
                        <?php foreach ($recommended as $product): ?>
                            <?php storefront_render_product_card($product, array(
                                'reason' => get_store_recommendation_reason($product, $personalization_seed),
                            )); ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="section reveal" id="orders">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Reservation history</span>
                    <h2 class="display-font section-title">Your orders &amp; reservations.</h2>
                </div>
                <p class="section-copy">Pending and confirmed reservations can still be cancelled from here. Ready-for-pickup and completed orders stay in the timeline for reference.</p>
            </div>
            <?php if (empty($checkout_groups)): ?>
                <div class="surface-card empty-state">
                    <span class="eyebrow">No orders yet</span>
                    <h3 class="display-font">Your reservation history will appear here.</h3>
                    <p>Reserve a product from any detail page and it will start showing up in this account timeline immediately.</p>
                    <div class="button-row">
                        <a href="shop.php" class="button button-dark">Browse products</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="order-grid">
                    <?php foreach ($visible_checkout_groups as $order): ?>
                        <article class="surface-card order-card">
                            <div class="order-card-top">
                                <div>
                                    <span class="eyebrow"><?php echo htmlspecialchars($order['checkout_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <h3><?php echo count($order['items']); ?> item<?php echo count($order['items']) === 1 ? '' : 's'; ?> in this reservation</h3>
                                </div>
                                <span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>"><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="support-list">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div><strong><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars(store_order_size_display($item), ENT_QUOTES, 'UTF-8'); ?> × <?php echo (int) $item['quantity']; ?> · <?php echo htmlspecialchars(store_format_money((float) $item['subtotal']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                <?php endforeach; ?>
                                <div>
                                    <strong>Pickup status</strong>
                                    <span><?php echo htmlspecialchars($order['pickup_window'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div>
                                    <strong>Order total</strong>
                                    <span><?php echo htmlspecialchars(store_format_money((float) $order['subtotal']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="button-row order-card-actions">
                                <a href="reservation-receipt.php?order_id=<?php echo urlencode((string) ($order['id'] ?? '')); ?>" class="button button-dark">Download receipt</a>
                                <?php if ((string) ($order['status'] ?? '') === 'Completed'): ?><a href="account.php#reviews" class="button button-light">Review purchased products</a><?php endif; ?>
                                <?php if (in_array((string) ($order['status'] ?? ''), array('Pending', 'Confirmed'), true)): ?>
                                    <form method="POST" action="account.php#orders" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="account_action" value="cancel_order">
                                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="button button-light">Cancel reservation</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($order_pages > 1): ?><nav class="activity-pagination" aria-label="Order history pages"><?php if ($order_page > 1): ?><a class="button button-light" href="account.php?order_page=<?php echo $order_page - 1; ?>#orders">Previous</a><?php endif; ?><span>Page <?php echo $order_page; ?> of <?php echo $order_pages; ?></span><?php if ($order_page < $order_pages): ?><a class="button button-light" href="account.php?order_page=<?php echo $order_page + 1; ?>#orders">Next</a><?php endif; ?></nav><?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="section reveal" id="reviews">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Feedback module</span>
                    <h2 class="display-font section-title">Only completed orders can be reviewed.</h2>
                </div>
                <p class="section-copy">This keeps ratings tied to real order completion and strengthens the recommendation loop for future customers.</p>
            </div>
            <?php if (empty($reviewable_orders)): ?>
                <div class="surface-card empty-state">
                    <span class="eyebrow">No review prompts</span>
                    <h3 class="display-font">Reviews unlock after order completion.</h3>
                    <p>Once an order reaches the completed stage, a review form will appear here automatically.</p>
                </div>
            <?php else: ?>
                <div class="order-grid">
                    <?php foreach ($reviewable_orders as $order): ?>
                        <article class="surface-card order-card">
                            <div class="order-card-top">
                                <div>
                                    <span class="eyebrow"><?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <h3>Leave a review for <?php echo htmlspecialchars($order['product_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                                <span class="status-pill status-completed">Completed</span>
                            </div>
                            <form method="POST" class="review-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="account_action" value="review">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo (int) ($order['product_id'] ?? 0); ?>">
                                <div class="field">
                                    <label for="rating-<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">Rating</label>
                                    <select id="rating-<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>" name="rating" required>
                                        <option value="5">5 stars</option>
                                        <option value="4">4 stars</option>
                                        <option value="3">3 stars</option>
                                        <option value="2">2 stars</option>
                                        <option value="1">1 star</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="title-<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">Review title <small>(optional)</small></label>
                                    <input type="text" id="title-<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>" name="title" maxlength="120" placeholder="Sum up your experience">
                                </div>
                                <div class="field">
                                    <label for="comment-<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>">Comment</label>
                                    <textarea id="comment-<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>" name="comment" rows="4" maxlength="1000" placeholder="Share your experience with fit, comfort, quality, or pickup flow." required></textarea>
                                </div>
                                <div class="button-row">
                                    <button type="submit" class="button button-dark">Submit review</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Recently viewed</span>
                    <h2 class="display-font section-title">Keep momentum on what already caught your eye.</h2>
                </div>
            </div>
            <?php if (empty($recent_products)): ?>
                <div class="surface-card empty-state">
                    <span class="eyebrow">No recent views</span>
                    <h3 class="display-font">Open a few product pages and they will appear here.</h3>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($recent_products as $product): ?>
                        <?php storefront_render_product_card($product, array(
                            'reason' => 'Based on your recent browsing history',
                        )); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php storefront_render_footer('Account Hub', 'Your Shoestagram account now controls profile details, password security, recommendations, reservation history, and completed-order reviews.'); ?>
    </main>

<?php storefront_render_mobile_dock('account'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
