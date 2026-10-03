<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = get_store_product_by_slug($slug);

if (!$product) {
    http_response_code(404);
    storefront_render_head('Shoestagram | Product Not Found', 'The requested product could not be found in Shoestagram.');
    ?>
    <body class="store-body">
        <main class="page-shell main-flow">
            <section class="section" style="padding-top: 120px;">
                <div class="surface-card empty-state">
                    <span class="eyebrow">404</span>
                    <h1 class="display-font">This product is no longer in the current storefront edit.</h1>
                    <p>Head back to the catalog and explore the latest recommendations, limited drops, and best sellers.</p>
                    <div class="button-row">
                        <a href="shop.php" class="button button-dark">Back to shop</a>
                        <a href="index.php" class="button button-light">Go home</a>
                    </div>
                </div>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit();
}

$context = storefront_get_context();
storefront_track_recent_view($product['id']);
store_state_record_product_view($product['id']);
$is_wishlisted = $context['is_logged_in'] ? store_product_is_wishlisted($context['user_id'], (int) $product['id']) : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try again.');
        storefront_redirect('product.php?slug=' . urlencode($product['slug']));
    }

    storefront_require_login('product.php?slug=' . urlencode($product['slug']));

    $product_action = trim((string) ($_POST['product_action'] ?? 'reserve'));

    if ($product_action === 'review') {
        $result = store_state_submit_review(
            trim((string) ($_POST['order_id'] ?? '')),
            $context,
            (int) ($_POST['rating'] ?? 5),
            $_POST['comment'] ?? '',
            (int) $product['id'],
            $_POST['title'] ?? ''
        );

        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        storefront_redirect('product.php?slug=' . urlencode($product['slug']) . '#reviews');
    }

    $size_standard = trim((string) ($_POST['size_standard'] ?? 'US'));
    $size = trim((string) ($_POST['size'] ?? ''));
    $quantity = $_POST['quantity'] ?? '';
    $payment_method = trim((string) ($_POST['payment_method'] ?? 'In-Store Payment'));
    $payment_reference = trim((string) ($_POST['payment_reference'] ?? ''));
    $normalized_payment_method = store_state_normalize_payment_method($payment_method);

    if ($normalized_payment_method === 'Online Payment' && $payment_reference === '') {
        storefront_set_flash('error', 'Enter the GCash reference number for online payment verification.');
        storefront_redirect('product.php?slug=' . urlencode($product['slug']) . '#reserve');
    }

    $proof_upload = array('ok' => true, 'path' => '', 'original_name' => '');

    if ($normalized_payment_method === 'Online Payment') {
        $proof_upload = store_handle_payment_proof_upload('payment_proof', true);

        if (empty($proof_upload['ok'])) {
            storefront_set_flash('error', $proof_upload['message']);
            storefront_redirect('product.php?slug=' . urlencode($product['slug']) . '#reserve');
        }
    }

    $result = store_state_create_reservation($context, $product, $size, $quantity, array(
        'size_standard' => $size_standard,
        'payment_method' => $payment_method,
        'payment_status' => $normalized_payment_method === 'Online Payment' ? 'Pending' : 'Unpaid',
        'payment_reference' => $payment_reference,
        'payment_proof' => $normalized_payment_method === 'Online Payment' ? ($proof_upload['path'] ?? '') : '',
        'payment_proof_name' => $normalized_payment_method === 'Online Payment' ? ($proof_upload['original_name'] ?? '') : '',
        'payment_note' => $normalized_payment_method === 'Online Payment'
            ? 'Customer submitted a GCash reference and proof image for manual verification.'
            : 'Customer chose to pay in store.',
        'transaction_type' => 'ONLINE',
        'channel' => 'Website reservation',
    ));

    if (empty($result['ok']) && !empty($proof_upload['path'])) {
        $uploaded_path = store_payment_proof_absolute_path($proof_upload['path']);

        if ($uploaded_path !== '' && is_file($uploaded_path)) {
            unlink($uploaded_path);
        }
    }

    if (!empty($result['ok'])) {
        store_send_order_email(
            $result['order'],
            'Shoestagram reservation ' . $result['order']['id'],
            'Your reservation has been received.'
        );
        storefront_set_flash('success', $result['message'] . ' You can follow its status from your account hub.');
    } else {
        storefront_set_flash('error', $result['message']);
    }

    storefront_redirect('product.php?slug=' . urlencode($product['slug']) . '#reserve');
}

$similar_products = get_store_similar_products($product, 4);
$personalization_seed = storefront_get_personalization_seed($context, array(
    'brand' => $product['brand'],
    'style' => $product['style'],
));
$recommended_products = get_store_recommended_products(array(
    'exclude_id' => $product['id'],
    'brand' => $personalization_seed['brand'],
    'style' => $personalization_seed['style'],
    'max_price' => $personalization_seed['max_price'],
    'limit' => 4,
));
$reviews = get_store_product_reviews((int) $product['id']);
$reviews_are_demo = !empty($reviews) && !empty($reviews[0]['is_demo']);
$review_count = count($reviews);
$review_rating_sum = array_sum(array_map(function ($review) {
    return max(1, min(5, (int) ($review['rating'] ?? 5)));
}, $reviews));
$review_average = $review_count > 0 ? round($review_rating_sum / $review_count, 1) : 0;
$review_distribution = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
foreach ($reviews as $review) {
    $rating_value = max(1, min(5, (int) ($review['rating'] ?? 5)));
    $review_distribution[$rating_value]++;
}
$reviewable_product_orders = $context['is_logged_in']
    ? store_state_customer_reviewable_product_orders($context['user_email'], (int) $product['id'])
    : array();
$completed_product_orders = $context['is_logged_in']
    ? store_state_customer_product_orders($context['user_email'], (int) $product['id'], true)
    : array();
$size_standard_labels = store_size_standard_labels();
$product_size_standards = $product['size_standards'] ?? array('US' => $product['sizes']);
$gcash_qr_path = store_gcash_qr_public_path('../');

storefront_render_head(
    'Shoestagram | ' . $product['name'],
    'View premium product details, stock, sizes, reservation options, and similar recommendations for ' . $product['name'] . '.'
);
?>
<body class="store-body storefront-product">
<?php storefront_render_header('shop', $context, array(
    'search_placeholder' => 'Search your next rotation piece',
    'brand_tagline' => 'Product Detail and Reservation',
    'announcement' => 'Reserve by size, review live stock, and move from discovery to pickup without leaving the premium storefront flow.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span aria-hidden="true">/</span><a href="shop.php">Shop</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span></nav>
        <section class="section reveal">
            <div class="product-page-grid">
                <div class="gallery-panel surface-card">
                    <div class="gallery-main">
                        <img src="<?php echo htmlspecialchars($product['gallery'][0], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" data-gallery-main>
                    </div>
                    <div class="gallery-thumbs">
                        <?php foreach ($product['gallery'] as $index => $image): ?>
                            <button type="button" class="gallery-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" aria-label="View product image <?php echo $index + 1; ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>" data-gallery-thumb="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> thumbnail">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="product-detail-stack">
                    <article class="surface-card product-detail-panel">
                        <div class="product-detail-top">
                            <div>
                                <span class="eyebrow"><?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <h1 class="display-font"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                                <p><?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="rating-chip large-rating">
                                <span class="star-row"><?php echo storefront_render_rating_markup($review_average); ?></span>
                                <small><?php echo number_format((float) $review_average, 1); ?> from <?php echo $review_count; ?> <?php echo $reviews_are_demo ? 'demo previews' : 'approved reviews'; ?></small>
                            </div>
                        </div>

                        <div class="price-hero">
                            <div>
                                <strong><?php echo htmlspecialchars(store_format_money($product['price']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($product['old_price'])): ?>
                                    <span class="old-price"><?php echo htmlspecialchars(store_format_money($product['old_price']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($product['promotion'])): ?>
                                    <span class="sale-note"><?php echo htmlspecialchars($product['promotion']['title'] . ' · ' . number_format((float) $product['promotion']['discount_percent'], 0) . '% off', ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="status-pill status-<?php echo strtolower(str_replace(' ', '-', $product['stock_status'])); ?>"><?php echo htmlspecialchars($product['stock_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="fact-grid">
                            <div class="fact-card">
                                <span>Condition</span>
                                <strong><?php echo htmlspecialchars($product['condition'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="fact-card">
                                <span>Colorway</span>
                                <strong><?php echo htmlspecialchars($product['colorway'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="fact-card">
                                <span>Available stock</span>
                                <strong><?php echo (int) $product['stock']; ?> units</strong>
                            </div>
                            <div class="fact-card">
                                <span>Recommendation signal</span>
                                <strong><?php echo htmlspecialchars(get_store_recommendation_reason($product, $personalization_seed), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </article>

                    <article class="surface-card reservation-panel" id="reserve">
                        <div class="section-head compact-head">
                            <div>
                                <span class="eyebrow">Reserve this product</span>
                                <h2 class="display-font section-title">Make it yours.</h2>
                            </div>
                        </div>
                        <form method="POST" class="order-form"<?php echo $context['is_logged_in'] ? '' : ' data-requires-account'; ?> enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="reservation-fields-grid">
                                <div class="field">
                                    <label for="size_standard">Sizing standard</label>
                                    <select id="size_standard" name="size_standard" required>
                                        <?php foreach ($size_standard_labels as $standard_key => $standard_label): ?>
                                            <?php if (empty($product_size_standards[$standard_key])) { continue; } ?>
                                            <option value="<?php echo htmlspecialchars($standard_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($standard_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="size">Choose size</label>
                                    <select id="size" name="size" required data-size-choices<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?>>
                                        <option value="">Select a size</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="quantity">Quantity</label>
                                    <div class="quantity-control" data-quantity-control>
                                        <button type="button" data-quantity-decrease aria-label="Decrease quantity"<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?>>&minus;</button>
                                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo max(1, (int) $product['stock']); ?>" step="1" inputmode="numeric" aria-label="Quantity" required<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?>>
                                        <button type="button" data-quantity-increase aria-label="Increase quantity"<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?>>+</button>
                                    </div>
                                    <small class="field-note"><?php echo (int) $product['stock'] > 0 ? (int) $product['stock'] . ' currently available.' : 'Out of stock.'; ?></small>
                                </div>
                                <div class="field">
                                    <label for="payment_method">Payment method</label>
                                    <select id="payment_method" name="payment_method" required>
                                        <option value="Online Payment">Online Payment</option>
                                        <option value="In-Store Payment">In-Store Payment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="payment-reference-panel" data-payment-reference-panel>
                                <div class="payment-panel-head">
                                    <div>
                                        <span class="eyebrow">GCash manual verification</span>
                                        <h3 class="display-font">Scan, upload, and reserve.</h3>
                                    </div>
                                    <span class="payment-badge">Online Payment</span>
                                </div>
                                <div class="payment-online-grid">
                                    <div class="gcash-qr-card">
                                        <?php if ($gcash_qr_path !== ''): ?>
                                            <div class="gcash-qr-image-wrap">
                                                <img class="gcash-qr-image" src="<?php echo htmlspecialchars($gcash_qr_path, ENT_QUOTES, 'UTF-8'); ?>" alt="Shoestagram GCash QR code">
                                            </div>
                                        <?php else: ?>
                                            <div class="gcash-qr-missing">
                                                <strong>GCash QR unavailable</strong>
                                                <span>Please choose in-store payment or contact support for assistance.</span>
                                            </div>
                                        <?php endif; ?>
                                        <p>Scan the QR code to complete your payment, then upload your payment screenshot for staff verification.</p>
                                    </div>
                                    <div class="payment-proof-stack">
                                        <div class="field">
                                            <label for="payment_proof">Upload proof of payment</label>
                                            <input type="file" id="payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                            <span class="field-note">JPG, PNG, or WEBP only. Maximum file size: 5 MB.</span>
                                        </div>
                                        <div class="field">
                                            <label for="payment_reference">GCash reference number</label>
                                            <input type="text" id="payment_reference" name="payment_reference" placeholder="Example: GCASH-123456">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="payment-store-panel" data-instore-payment-panel>
                                <span class="eyebrow">In-store payment</span>
                                <p>Pay at the Shoestagram counter when your reservation is confirmed and ready for pickup.</p>
                            </div>
                            <div class="live-order-total"><span>Reservation total</span><strong data-unit-price="<?php echo htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(store_format_money($product['price']), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <div class="reservation-summary">
                                <div>
                                    <span>Reservation process</span>
                                    <strong>Pending -> Confirmed -> Ready for Pickup -> Completed</strong>
                                </div>
                                <div>
                                    <span>Payment status</span>
                                    <strong>Online payments start Pending; in-store payments start Unpaid</strong>
                                </div>
                            </div>
                            <div class="button-row">
                                <button type="submit" class="button button-dark"<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?><?php echo $context['is_logged_in'] ? '' : ' data-requires-account'; ?>>Reserve now</button>
                                <button type="submit" class="button button-light" form="productCartForm"<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?><?php echo $context['is_logged_in'] ? '' : ' data-requires-account'; ?>>Add to cart</button>
                                <button type="submit" class="button button-light" form="productWishlistForm"<?php echo $context['is_logged_in'] ? '' : ' data-requires-account'; ?>>
                                    <?php echo $is_wishlisted ? 'Saved' : 'Save'; ?>
                                </button>
                            </div>
                        </form>
                        <form method="POST" action="cart-action.php" id="productCartForm" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $context['is_logged_in'] ? '' : ' data-requires-account'; ?>>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                            <input type="hidden" name="quantity" id="productCartQuantity" value="1">
                            <input type="hidden" name="size_standard" id="productCartSizeStandard" value="US">
                            <input type="hidden" name="size" id="productCartSize" value="">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars('product.php?slug=' . urlencode($product['slug']) . '#reserve', ENT_QUOTES, 'UTF-8'); ?>">
                        </form>
                        <form method="POST" action="wishlist-action.php" id="productWishlistForm"<?php echo $context['is_logged_in'] ? '' : ' data-requires-account'; ?>>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars('product.php?slug=' . urlencode($product['slug']) . '#reserve', ENT_QUOTES, 'UTF-8'); ?>">
                        </form>
                    </article>
                </div>

                <article class="surface-card product-review-panel" id="reviews">
                    <div class="product-review-heading">
                        <div>
                            <span class="eyebrow">Customer reviews</span>
                            <h2 class="display-font section-title">Worn, styled, reviewed.</h2>
                        </div>
                        <div class="review-score" aria-label="Average rating <?php echo number_format($review_average, 1); ?> out of 5">
                            <strong><?php echo number_format($review_average, 1); ?></strong>
                            <span><span class="star-row"><?php echo storefront_render_rating_markup($review_average); ?></span><small><?php echo $review_count; ?> review<?php echo $review_count === 1 ? '' : 's'; ?></small></span>
                        </div>
                    </div>

                    <?php if ($reviews_are_demo): ?>
                        <div class="review-demo-notice" role="note">
                            <i class="fas fa-flask" aria-hidden="true"></i>
                            <span><strong>Development preview</strong> — sample reviews are shown until this product receives an approved customer review.</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($review_count > 0): ?>
                        <div class="review-breakdown" aria-label="Rating distribution">
                            <?php foreach ($review_distribution as $stars => $count): ?>
                                <div class="review-breakdown-row">
                                    <span><?php echo $stars; ?> <i class="fas fa-star" aria-hidden="true"></i></span>
                                    <span class="review-breakdown-track"><span style="width: <?php echo $review_count > 0 ? round(($count / $review_count) * 100, 2) : 0; ?>%;"></span></span>
                                    <small><?php echo $count; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($reviewable_product_orders)): ?>
                        <details class="review-compose">
                            <summary>Write a verified-purchase review <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                            <form method="POST" class="review-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="product_action" value="review">
                                <div class="field">
                                    <label for="review-order">Completed order</label>
                                    <select id="review-order" name="order_id" required>
                                        <?php foreach ($reviewable_product_orders as $order): ?>
                                            <option value="<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($order['id'] . ' — ' . store_order_size_display($order), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <fieldset class="review-star-field">
                                    <legend>Rating</legend>
                                    <div class="review-star-options">
                                        <?php for ($star = 5; $star >= 1; $star--): ?>
                                            <label><input type="radio" name="rating" value="<?php echo $star; ?>"<?php echo $star === 5 ? ' checked' : ''; ?> required><span><?php echo str_repeat('★', $star); ?><small><?php echo $star; ?> star<?php echo $star === 1 ? '' : 's'; ?></small></span></label>
                                        <?php endfor; ?>
                                    </div>
                                </fieldset>
                                <div class="field">
                                    <label for="review-title">Review title <small>(optional)</small></label>
                                    <input type="text" id="review-title" name="title" maxlength="120" placeholder="Sum up your experience">
                                </div>
                                <div class="field">
                                    <label for="review-comment">Review</label>
                                    <textarea id="review-comment" name="comment" rows="4" maxlength="1000" placeholder="Share your thoughts on fit, comfort, quality, or pickup." required></textarea>
                                </div>
                                <button type="submit" class="button button-dark">Submit review</button>
                            </form>
                        </details>
                    <?php elseif ($context['is_logged_in'] && !empty($completed_product_orders)): ?>
                        <p class="review-eligibility-note"><i class="fas fa-circle-check" aria-hidden="true"></i> Your completed order for this product has already been reviewed.</p>
                    <?php elseif ($context['is_logged_in']): ?>
                        <p class="review-eligibility-note"><i class="fas fa-lock" aria-hidden="true"></i> Complete an order for this product to leave a Verified Purchase review.</p>
                    <?php else: ?>
                        <p class="review-eligibility-note"><i class="fas fa-lock" aria-hidden="true"></i> Sign in after completing an order to leave a Verified Purchase review.</p>
                    <?php endif; ?>

                    <div class="product-review-list">
                        <?php foreach ($reviews as $review): ?>
                            <article class="storefront-review-card">
                                <div class="storefront-review-meta">
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['customer_name'] ?? 'Shoestagram Member', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="star-row" aria-label="<?php echo (int) $review['rating']; ?> out of 5 stars"><?php echo storefront_render_rating_markup($review['rating']); ?></span>
                                    </div>
                                    <span class="review-origin-badge <?php echo !empty($review['verified_purchase']) ? 'is-verified' : 'is-demo'; ?>">
                                        <i class="fas <?php echo !empty($review['verified_purchase']) ? 'fa-circle-check' : 'fa-flask'; ?>" aria-hidden="true"></i>
                                        <?php echo !empty($review['verified_purchase']) ? 'Verified Purchase' : 'Demo review'; ?>
                                    </span>
                                </div>
                                <?php if (trim((string) ($review['title'] ?? '')) !== ''): ?><h3><?php echo htmlspecialchars($review['title'], ENT_QUOTES, 'UTF-8'); ?></h3><?php endif; ?>
                                <p>“<?php echo htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8'); ?>”</p>
                                <div class="storefront-review-foot">
                                    <?php if (trim((string) ($review['size'] ?? '')) !== ''): ?><span><?php echo !empty($review['is_demo']) ? 'Sample size' : 'Purchased size'; ?>: <?php echo htmlspecialchars(store_size_display($review['size'], $review['size_standard'] ?? 'US'), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                    <time datetime="<?php echo htmlspecialchars((string) ($review['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(date('F j, Y', strtotime((string) ($review['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></time>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Similar products</span>
                    <h2 class="display-font section-title">Keep the same energy.</h2>
                </div>
                <a href="shop.php?brand=<?php echo urlencode($product['brand']); ?>" class="text-link">Browse <?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="product-grid">
                <?php foreach ($similar_products as $similar): ?>
                    <?php storefront_render_product_card($similar, array(
                        'reason' => 'Similar silhouette, category, or brand direction',
                    )); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="split-showcase recommendation-showcase">
                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">You may also like</span>
                            <h2 class="display-font section-title">Your next favorites.</h2>
                        </div>
                    </div>
                    <div class="product-grid compact-grid">
                        <?php foreach ($recommended_products as $recommended_product): ?>
                            <?php storefront_render_product_card($recommended_product, array(
                                'reason' => get_store_recommendation_reason($recommended_product, $personalization_seed),
                            )); ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <?php storefront_render_footer('Product Detail', 'Every product page now carries brand context, reservation controls, live stock data, similar products, and feedback that loops back into recommendation quality.'); ?>
    </main>

<?php storefront_render_mobile_dock('shop'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const sizeStandards = <?php echo json_encode($product_size_standards, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const standardLabels = <?php echo json_encode($size_standard_labels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const standardSelect = document.getElementById('size_standard');
        const sizeSelect = document.getElementById('size');
        const paymentSelect = document.getElementById('payment_method');
        const paymentPanel = document.querySelector('[data-payment-reference-panel]');
        const inStorePaymentPanel = document.querySelector('[data-instore-payment-panel]');
        const paymentReference = document.getElementById('payment_reference');
        const paymentProof = document.getElementById('payment_proof');
        const cartStandard = document.getElementById('productCartSizeStandard');
        const cartSize = document.getElementById('productCartSize');

        function syncSizes() {
            const standard = standardSelect ? standardSelect.value : 'US';
            const sizes = sizeStandards[standard] || [];

            if (!sizeSelect) {
                return;
            }

            sizeSelect.innerHTML = '<option value="">Select a size</option>';
            sizes.forEach(function (size) {
                const option = document.createElement('option');
                option.value = size;
                option.textContent = (standardLabels[standard] || standard) + ' ' + size;
                sizeSelect.appendChild(option);
            });

            if (cartStandard) {
                cartStandard.value = standard;
            }

            if (cartSize) {
                cartSize.value = '';
            }
        }

        function syncPayment() {
            const online = paymentSelect && paymentSelect.value === 'Online Payment';

            if (paymentPanel) {
                paymentPanel.style.display = online ? 'block' : 'none';
            }

            if (inStorePaymentPanel) {
                inStorePaymentPanel.style.display = online ? 'none' : 'block';
            }

            if (paymentReference) {
                paymentReference.required = online;
            }

            if (paymentProof) {
                paymentProof.required = online;

                if (!online) {
                    paymentProof.value = '';
                }
            }
        }

        if (standardSelect) {
            standardSelect.addEventListener('change', syncSizes);
            syncSizes();
        }

        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                if (cartSize) {
                    cartSize.value = sizeSelect.value;
                }
            });
        }

        if (paymentSelect) {
            paymentSelect.addEventListener('change', syncPayment);
            syncPayment();
        }
    });
    </script>
    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
