<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();
$categories = get_store_categories();
$category_cards = array(
    'shoes' => array(
        'icon' => 'fa-shoe-prints',
        'title' => 'Footwear foundations',
        'copy' => 'Retro runners, court lows, and performance-led pairs that anchor the full outfit.',
    ),
    'tops' => array(
        'icon' => 'fa-shirt',
        'title' => 'Premium tops',
        'copy' => 'Heavyweight tees and textured layers that keep the silhouette clean without feeling basic.',
    ),
    'bottoms' => array(
        'icon' => 'fa-person-walking',
        'title' => 'Tailored shorts',
        'copy' => 'Relaxed but deliberate summer layers that work with both lifestyle and performance sneakers.',
    ),
    'accessories' => array(
        'icon' => 'fa-hat-cowboy-side',
        'title' => 'Accessories',
        'copy' => 'Quiet finishing pieces that sharpen the overall look without overwhelming it.',
    ),
);
$featured = array_slice(get_store_featured_products(), 0, 4);

storefront_render_head(
    'Shoestagram | Collection',
    'Explore the Shoestagram collection lanes and category direction before entering the full premium catalog.'
);
?>
<body class="store-body storefront-collection">
<?php storefront_render_header('collection', $context, array(
    'search_placeholder' => 'Search the collection',
    'brand_tagline' => 'Curated Category Direction',
    'announcement' => 'Use the collection page to understand how footwear, apparel, and accessories work together inside the Shoestagram assortment.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid collection-hero-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow">Collection</span>
                        <span class="hero-chip"><?php echo count($featured); ?> highlighted products</span>
                    </div>
                    <h1 class="display-font hero-title">Build your everyday rotation.</h1>
                    <p class="hero-description">From the pair you wear on repeat to the layers that pull it all together. Explore the collection, your way.</p>
                    <div class="hero-actions">
                        <a href="shop.php" class="button button-dark">Open the shop</a>
                        <a href="about.php" class="button button-light">Read the brand story</a>
                    </div>
                </article>

                <aside class="surface-card showcase-panel collection-featured-panel" aria-labelledby="featured-edit-title">
                    <div class="showcase-heading">
                        <span class="eyebrow">Featured edit</span>
                        <h2 id="featured-edit-title" class="display-font">The current shortlist.</h2>
                        <p>A few favorites from the current collection.</p>
                    </div>
                    <div class="collection-featured-grid">
                        <?php foreach ($featured as $product): ?>
                            <a href="product.php?slug=<?php echo urlencode($product['slug']); ?>" class="collection-featured-card" aria-label="View <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="collection-featured-media">
                                    <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="320" height="220" loading="lazy" decoding="async">
                                    <span class="collection-featured-rating"><i class="fas fa-star" aria-hidden="true"></i> <?php echo number_format((float) $product['rating'], 1); ?></span>
                                </span>
                                <span class="collection-featured-copy">
                                    <small><?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span><?php echo htmlspecialchars(store_format_money($product['price']), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Category lanes</span>
                    <h2 class="display-font section-title">Start with the essentials.</h2>
                </div>
            </div>
            <div class="category-grid">
                <?php foreach ($category_cards as $key => $card): ?>
                    <?php $category_products = filter_store_products(array('category' => $key)); $category_product = reset($category_products); ?>
                    <a href="shop.php?category=<?php echo urlencode($key); ?>" class="category-card surface-card hover-lift">
                        <?php if ($category_product): ?><img class="category-photo" src="<?php echo htmlspecialchars($category_product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="400" height="400" loading="lazy" decoding="async"><?php endif; ?>
                        <div class="category-top">
                            <span class="category-icon"><i class="fas <?php echo htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                            <span class="pill"><?php echo htmlspecialchars($categories[$key], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <strong><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p><?php echo htmlspecialchars($card['copy'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <span class="category-link">Browse <?php echo htmlspecialchars(strtolower($categories[$key]), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php storefront_render_footer('Collection', 'The collection page helps customers understand the assortment before they filter the full catalog, reserve products, or move into reviews and order history.'); ?>
    </main>

<?php storefront_render_mobile_dock('collection'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
