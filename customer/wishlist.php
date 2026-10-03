<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

storefront_require_login('wishlist.php');

$context = storefront_get_context();
$wishlist_products = store_get_wishlist_products($context['user_id']);
$wishlist_ids = array_map(function ($product) {
    return (int) $product['id'];
}, $wishlist_products);
$recommended = array_values(array_filter(get_store_recommended_products(array(
    'brand' => $context['preferences']['brand'],
    'style' => $context['preferences']['style'],
    'max_price' => $context['preferences']['max_price'],
    'limit' => 8,
)), function ($product) use ($wishlist_ids) {
    return !in_array((int) $product['id'], $wishlist_ids, true);
}));

storefront_render_head(
    'Shoestagram | Wishlist',
    'Manage your Shoestagram wishlist, remove saved products, and move items into your cart.'
);
?>
<body class="store-body">
<?php storefront_render_header('wishlist', $context, array(
    'search_placeholder' => 'Search the catalog from saved items',
    'brand_tagline' => 'Saved Products and Comparison',
    'announcement' => 'Your wishlist is private to your account and can move products directly into cart.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow">Wishlist</span>
                        <span class="hero-chip"><?php echo count($wishlist_products); ?> saved</span>
                    </div>
                    <h1 class="display-font hero-title">The ones on your mind.</h1>
                    <p class="hero-description">Your personal shortlist. Save it now, come back for your perfect fit.</p>
                    <div class="hero-actions">
                        <a href="shop.php" class="button button-dark">Continue shopping</a>
                        <?php if (!empty($wishlist_products)): ?>
                            <form method="POST" action="wishlist-action.php" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="clear">
                                <input type="hidden" name="redirect" value="wishlist.php">
                                <button type="submit" class="button button-light">Clear wishlist</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="hero-notes">
                        <div class="hero-note">
                            <strong><?php echo count($wishlist_products); ?></strong>
                            <span>products currently saved into your shortlist</span>
                        </div>
                        <div class="hero-note">
                            <strong><?php echo (int) $context['cart_count']; ?></strong>
                            <span>items already waiting in your cart</span>
                        </div>
                    </div>
                </article>

                <aside class="surface-card showcase-panel">
                    <div class="showcase-heading">
                        <span class="eyebrow">Wishlist flow</span>
                        <p>Save from the shop, compare here, then move the product into cart without sharing state across accounts.</p>
                    </div>
                    <div class="flow-grid mini-flow-grid">
                        <article class="flow-card inner-card">
                            <span>01</span>
                            <strong>Save</strong>
                            <p>Tap the heart on products you want to revisit.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>02</span>
                            <strong>Move</strong>
                            <p>Send a saved product straight to your private cart.</p>
                        </article>
                    </div>
                </aside>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Saved now</span>
                    <h2 class="display-font section-title">Your current shortlist.</h2>
                </div>
                <p class="section-copy"><?php echo count($wishlist_products) ? count($wishlist_products) . ' saved product(s) ready to compare or move to cart.' : 'No products saved yet. Build your shortlist from the main shop.'; ?></p>
            </div>

            <?php if (empty($wishlist_products)): ?>
                <div class="surface-card empty-state">
                    <span class="eyebrow">Wishlist empty</span>
                    <h3 class="display-font">Start saving the products you want to revisit.</h3>
                    <p>Your shortlist will appear here as soon as you tap a heart in the shop.</p>
                    <div class="button-row">
                        <a href="shop.php" class="button button-dark">Browse the catalog</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($wishlist_products as $product): ?>
                        <?php storefront_render_product_card($product, array('wishlist_actions' => true)); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">You may also like</span>
                    <h2 class="display-font section-title">More to fall for.</h2>
                </div>
                <p class="section-copy">These skip anything already saved so the rail stays useful.</p>
            </div>
            <div class="product-grid">
                <?php foreach (array_slice($recommended, 0, 4) as $product): ?>
                    <?php storefront_render_product_card($product, array(
                        'reason' => get_store_recommendation_reason($product, $context['preferences']),
                    )); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <?php storefront_render_footer('Wishlist', 'Wishlist records are stored per user with duplicate prevention and direct cart movement.'); ?>
    </main>

<?php storefront_render_mobile_dock('wishlist'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
