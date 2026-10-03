<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['home_action'] ?? '') === 'reset_recommendations') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try resetting the recommendation mix again.');
        storefront_redirect('index.php');
    }

    storefront_reset_recommendation_state();
    store_state_record_recommendation_reset();
    storefront_set_flash('success', 'Recommendation signals reset. The homepage is back to a fresh default mix.');
    storefront_redirect('index.php#recommendations');
}

$all_products = array_values(get_store_products());
$featured_products = array_slice(get_store_featured_products(), 0, 5);
$trending_products = get_store_trending_products(4);
$new_arrivals = get_store_new_arrivals(4);
$best_sellers = get_store_best_sellers(4);
$limited_products = get_store_limited_products(4);
$stories = get_store_story_highlights();
$brands = get_store_brands();
$promos = get_store_promos();
$reviews = get_store_recent_reviews(4);
$reviews_have_demo = count(array_filter($reviews, function ($review) {
    return !empty($review['is_demo']);
})) > 0;
$personalization_seed = storefront_get_personalization_seed($context);
$profile = store_state_get_user_profile($context['user_email']);
$profile_complete = store_state_profile_is_complete($profile);
$recommended = get_store_recommended_products(array(
    'brand' => $personalization_seed['brand'],
    'style' => $personalization_seed['style'],
    'max_price' => $personalization_seed['max_price'],
    'limit' => 4,
));
$fit_for_you = $profile_complete ? get_store_personalized_products($profile, 4, 'shoes') : array();
$style_for_you = $profile_complete ? get_store_personalized_products($profile, 4) : array();
$orders = store_state_get_orders();
$completed_orders = count(array_filter($orders, function ($order) {
    return ($order['status'] ?? '') === 'Completed';
}));

storefront_render_head(
    'Shoestagram | Premium Sneaker and Streetwear Recommendations',
    'Shoestagram pairs premium sneaker culture, responsive shopping, reservation flow, and recommendation-first discovery in one polished storefront.'
);
?>
<body class="store-body storefront-home">
<?php storefront_render_header('home', $context, array(
    'search_placeholder' => 'Search New Balance, Nike, tees, limited drops',
    'brand_tagline' => 'Streetwear Discovery Platform',
    'announcement' => 'Responsive storefront, reservation tracking, wishlist, and admin insights now share one premium design system.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <?php
        $hero_candidates = array_values(array_filter($featured_products, function ($item) { return $item['category'] === 'shoes'; }));
        if (empty($hero_candidates)) {
            $hero_candidates = array_values(array_filter($all_products, function ($item) { return $item['category'] === 'shoes'; }));
        }
        $hero_product = $hero_candidates[0] ?? ($featured_products[0] ?? ($all_products[0] ?? null));
        ?>
        <section class="editorial-hero" aria-labelledby="heroTitle">
            <div class="editorial-copy">
                <span class="eyebrow">The everyday rotation / Shoestagram</span>
                <h1 id="heroTitle">Good style.<br><em>On repeat.</em></h1>
                <p>Fresh sneakers. Everyday essentials. Find the pieces that feel like you.</p>
                <div class="hero-actions">
                    <a href="shop.php" class="button button-dark">Shop the collection <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="shop.php?sort=newest" class="text-link">New arrivals</a>
                </div>
                <span class="editorial-footnote">Find it. Style it. Make it yours.</span>
            </div>
            <?php if ($hero_product): ?>
                <a class="editorial-image" href="product.php?slug=<?php echo urlencode($hero_product['slug']); ?>">
                    <img src="<?php echo htmlspecialchars($hero_product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($hero_product['name'], ENT_QUOTES, 'UTF-8'); ?>" width="640" height="640" fetchpriority="high">
                    <div class="editorial-image-caption">
                        <div><span>In the spotlight / <?php echo htmlspecialchars($hero_product['brand'], ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo htmlspecialchars($hero_product['name'], ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo htmlspecialchars(store_format_money($hero_product['price']), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </div>
                </a>
            <?php endif; ?>
        </section>
        <div class="store-benefits" aria-label="Ways to shop">
            <span><i class="fas fa-shoe-prints" aria-hidden="true"></i>Sneakers &amp; streetwear</span>
            <span><i class="fas fa-sliders" aria-hidden="true"></i>Discover your fit</span>
            <span><i class="fas fa-store" aria-hidden="true"></i>Reserve for in-store pickup</span>
        </div>

        <section class="section reveal">
            <div class="promo-grid">
                <?php foreach ($promos as $promo): ?>
                    <a href="<?php echo htmlspecialchars($promo['href'], ENT_QUOTES, 'UTF-8'); ?>" class="promo-card surface-card hover-lift">
                        <span class="eyebrow"><?php echo htmlspecialchars($promo['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <h3 class="display-font"><?php echo htmlspecialchars($promo['copy'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <?php if (($promo['type'] ?? '') === 'product_promotion'): ?>
                            <div class="promo-price-row">
                                <span><?php echo htmlspecialchars(store_format_money((float) $promo['original_price']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo htmlspecialchars(store_format_money((float) $promo['sale_price']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ((float) ($promo['discount_percent'] ?? 0) > 0): ?>
                                    <small><?php echo number_format((float) $promo['discount_percent'], 0); ?>% off</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-link"><?php echo htmlspecialchars($promo['cta'], ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($context['is_logged_in'] && !$profile_complete): ?>
            <section class="section reveal">
                <div class="surface-card onboarding-banner">
                    <div>
                        <span class="eyebrow">Unlock better recommendations</span>
                        <h2 class="display-font section-title">A better fit starts with you.</h2>
                        <p class="section-copy">Save your sizes, favorite brands, and budget for a more personal selection.</p>
                    </div>
                    <div class="button-row">
                        <a href="preferences.php?welcome=1" class="button button-dark">Build my fit profile</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($context['is_logged_in'] && $profile_complete): ?>
            <section class="section reveal">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Personalized for you</span>
                        <h2 class="display-font section-title"><?php echo htmlspecialchars(get_store_profile_heading($profile), ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>
                    <a href="preferences.php" class="text-link">Edit fit profile <i class="fas fa-arrow-right"></i></a>
                </div>
                <p class="section-copy">These picks are prioritized using your sizes, favorite categories, brand preferences, style direction, and budget range.</p>
                <div class="product-grid">
                    <?php foreach ($fit_for_you as $product): ?>
                        <?php storefront_render_product_card($product, array(
                            'reason' => store_profile_matches_size($product, $profile) ? 'Available in your saved size' : get_store_recommendation_reason($product, $personalization_seed),
                        )); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section reveal">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Built around your style</span>
                        <h2 class="display-font section-title">Your style, more ways.</h2>
                    </div>
                </div>
                <div class="product-grid">
                    <?php foreach ($style_for_you as $product): ?>
                        <?php storefront_render_product_card($product, array(
                            'reason' => get_store_recommendation_reason($product, array(
                                'brand' => $profile['brands'][0] ?? '',
                                'style' => $profile['styles'][0] ?? '',
                                'max_price' => $profile['budget_max'] ?? '',
                            )),
                        )); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Story highlights</span>
                    <h2 class="display-font section-title">Find your lane.</h2>
                </div>
                <p class="section-copy">A fresh pair or an everyday essential. Start with what you love.</p>
            </div>
            <div class="story-strip">
                <?php foreach ($stories as $story): ?>
                    <a href="<?php echo htmlspecialchars($story['href'], ENT_QUOTES, 'UTF-8'); ?>" class="story-card hover-lift">
                        <span class="story-icon"><i class="fas <?php echo htmlspecialchars($story['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                        <strong><?php echo htmlspecialchars($story['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($story['meta'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Smart recommendations</span>
                    <h2 class="display-font section-title">Recommended for you.</h2>
                </div>
                <div class="button-row">
                    <p class="section-copy" style="margin: 0;">Inspired by your favorite brands, style, and budget.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="home_action" value="reset_recommendations">
                        <button type="submit" class="button button-light">Reset recommendations</button>
                    </form>
                </div>
            </div>
            <div class="product-grid" id="recommendations">
                <?php foreach ($recommended as $product): ?>
                    <?php storefront_render_product_card($product, array(
                        'reason' => get_store_recommendation_reason($product, $personalization_seed),
                    )); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Trending now</span>
                    <h2 class="display-font section-title">In heavy rotation.</h2>
                </div>
                <a href="shop.php?sort=trending" class="text-link">Shop trending <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="product-grid">
                <?php foreach ($trending_products as $product): ?>
                    <?php storefront_render_product_card($product, array(
                        'reason' => 'Trending strongly with shoppers right now',
                    )); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="split-showcase">
                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">New arrivals</span>
                            <h2 class="display-font section-title">Just landed.</h2>
                        </div>
                    </div>
                    <div class="product-grid compact-grid">
                        <?php foreach ($new_arrivals as $product): ?>
                            <?php storefront_render_product_card($product, array(
                                'reason' => 'Newly added with strong momentum',
                            )); ?>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Limited and premium</span>
                            <h2 class="display-font section-title">A little less ordinary.</h2>
                        </div>
                    </div>
                    <div class="product-grid compact-grid">
                        <?php foreach ($limited_products as $product): ?>
                            <?php storefront_render_product_card($product, array(
                                'reason' => 'Low stock and high reservation pressure',
                            )); ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Best sellers</span>
                    <h2 class="display-font section-title">The crowd favorites.</h2>
                </div>
                <a href="shop.php?sort=best" class="text-link">View all best sellers <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="product-grid">
                <?php foreach ($best_sellers as $product): ?>
                    <?php storefront_render_product_card($product, array(
                        'reason' => 'Consistent demand and strong sales performance',
                    )); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Brand showcase</span>
                    <h2 class="display-font section-title">Brands in the rotation.</h2>
                </div>
                <p class="section-copy">Explore the collection from your favorite labels.</p>
            </div>
            <div class="brand-grid">
                <?php foreach ($brands as $brand): ?>
                    <article class="brand-card surface-card hover-lift">
                        <strong><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p><?php echo htmlspecialchars($brand['copy'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="shop.php?brand=<?php echo urlencode($brand['name']); ?>" class="text-link">Browse <?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Reservation flow</span>
                    <h2 class="display-font section-title">Find it. Reserve it. Wear it.</h2>
                </div>
                <p class="section-copy">Choose your piece, reserve your size, and track it through to pickup.</p>
            </div>
            <div class="flow-grid">
                <article class="flow-card surface-card">
                    <span>01</span>
                    <strong>Browse and shortlist</strong>
                    <p>Explore the shop, wishlist favorites, and compare premium product cards with live stock and review signals.</p>
                </article>
                <article class="flow-card surface-card">
                    <span>02</span>
                    <strong>Reserve with confidence</strong>
                    <p>Choose your size and payment method. We will confirm your reservation before pickup.</p>
                </article>
                <article class="flow-card surface-card">
                    <span>03</span>
                    <strong>Complete and review</strong>
                    <p>Pick up your order, make it your own, and share a review of your purchase.</p>
                </article>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Customer voice</span>
                    <h2 class="display-font section-title">From the community.</h2>
                    <?php if ($reviews_have_demo): ?><p class="section-copy">Development-preview reviews are clearly labeled until more verified customer reviews are available.</p><?php endif; ?>
                </div>
                <a href="account.php" class="text-link">Review your completed orders <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="review-grid">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-card surface-card hover-lift">
                        <div class="rating-chip">
                            <span class="star-row"><?php echo storefront_render_rating_markup($review['rating']); ?></span>
                            <small><?php echo number_format((float) $review['rating'], 1); ?></small>
                        </div>
                        <h3><?php echo htmlspecialchars($review['product_name'] ?? 'Shoestagram experience', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <span>Reviewed by <?php echo htmlspecialchars($review['customer_name'] ?? 'Shoestagram Member', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($review['is_demo'])): ?><span class="review-origin-badge is-demo"><i class="fas fa-flask" aria-hidden="true"></i> Development preview</span><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php storefront_render_footer('Premium Discovery', 'A premium PWA-ready sneaker and clothing recommendation storefront with reservation tracking, account history, and admin visibility.'); ?>
    </main>

<?php storefront_render_mobile_dock('home'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
