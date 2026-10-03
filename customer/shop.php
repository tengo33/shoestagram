<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();
$categories = get_store_categories();
$brands = get_store_brand_names();
$style_options = get_store_style_names();
$budget_options = get_store_budget_options();
$price_ranges = get_store_price_ranges();
$size_options = array();

foreach (get_store_products() as $product) {
    foreach (store_product_all_sizes($product) as $size) {
        $size = trim((string) $size);

        if ($size !== '') {
            $size_options[] = $size;
        }
    }
}

$size_options = array_values(array_unique($size_options));
usort($size_options, 'strnatcmp');

$filters = array(
    'search' => trim((string) ($_GET['search'] ?? '')),
    'anything' => trim((string) ($_GET['anything'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? 'all')),
    'brand' => trim((string) ($_GET['brand'] ?? '')),
    'tag' => trim((string) ($_GET['tag'] ?? '')),
    'style' => trim((string) ($_GET['style'] ?? '')),
    'size' => trim((string) ($_GET['size'] ?? '')),
    'availability' => trim((string) ($_GET['availability'] ?? '')),
    'price_range' => trim((string) ($_GET['price_range'] ?? '')),
    'min_price' => trim((string) ($_GET['min_price'] ?? '')),
    'max_price' => trim((string) ($_GET['max_price'] ?? '')),
    'sort' => trim((string) ($_GET['sort'] ?? 'recommended')),
);

$products = filter_store_products($filters);
$personalization_seed = storefront_get_personalization_seed($context, $filters);
$recommended = get_store_recommended_products(array(
    'brand' => $personalization_seed['brand'],
    'style' => $personalization_seed['style'],
    'max_price' => $personalization_seed['max_price'],
    'limit' => 3,
));
$active_filter_count = 0;

foreach ($filters as $key => $value) {
    if ($key === 'sort') {
        continue;
    }

    if (trim((string) $value) !== '' && trim((string) $value) !== 'all') {
        $active_filter_count++;
    }
}

$is_default_browse = $active_filter_count === 0;
$matched_product_count = count($products);

storefront_render_head(
    'Shoestagram | Shop',
    'Browse the Shoestagram catalog with search, filters, recommendations, and reservation-ready product discovery.'
);
?>
<body class="store-body storefront-shop">
<?php storefront_render_header('shop', $context, array(
    'search_placeholder' => 'Search limited drops, brands, styles, apparel',
    'brand_tagline' => 'Recommendation-Led Shopping',
    'announcement' => 'Search, filter, wishlist, reserve, and track premium products from one responsive storefront.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="catalog-intro">
            <span class="eyebrow">The Shoestagram collection</span>
            <h1>Find your next favorite.</h1>
            <p>Sneakers and streetwear for whatever your day looks like.</p>
            <nav class="filter-chip-grid" aria-label="Shop collections">
                <a href="shop.php" class="chip-link">All products</a>
                <a href="shop.php?sort=newest" class="chip-link">New arrivals</a>
                <a href="shop.php?category=shoes" class="chip-link">Sneakers</a>
                <a href="shop.php?category=tops" class="chip-link">Everyday tops</a>
                <a href="shop.php?category=slippers" class="chip-link">Slides &amp; clogs</a>
                <a href="shop.php?tag=limited" class="chip-link">Limited releases</a>
            </nav>
        </section>
        <section class="catalog-layout" aria-label="Product catalog">
            <details class="filter-sidebar" open>
                <summary>Filters &amp; sort <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <form method="GET" class="filter-form" action="shop.php">
                        <?php foreach (array('tag', 'min_price', 'max_price') as $extra_filter): ?>
                            <?php if ($filters[$extra_filter] !== ''): ?><input type="hidden" name="<?php echo $extra_filter; ?>" value="<?php echo htmlspecialchars($filters[$extra_filter], ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                        <?php endforeach; ?>
                        <div class="field">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search product, brand, or style">
                        </div>

                        <div class="field">
                            <label for="anything">Describe your find</label>
                            <input type="text" id="anything" name="anything" value="<?php echo htmlspecialchars($filters['anything'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Try: Nike size 9 red low stock">
                            <small class="field-note">Try a color, size, or style.</small>
                        </div>

                        <div class="field">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['category'] === $key ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="brand">Brand</label>
                            <select id="brand" name="brand">
                                <option value="">All brands</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['brand'] === $brand ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="style">Style</label>
                            <select id="style" name="style">
                                <option value="">All styles</option>
                                <?php foreach ($style_options as $style): ?>
                                    <option value="<?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['style'] === $style ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="price_range">Price range</label>
                            <select id="price_range" name="price_range">
                                <option value="">Any price range</option>
                                <?php foreach ($price_ranges as $range_key => $range): ?>
                                    <option value="<?php echo htmlspecialchars($range_key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['price_range'] === $range_key ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($range['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="size">Size</label>
                            <select id="size" name="size">
                                <option value="">Any size</option>
                                <?php foreach ($size_options as $size): ?>
                                    <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['size'] === $size ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="availability">Availability</label>
                            <select id="availability" name="availability">
                                <option value="">Any availability</option>
                                <option value="in-stock"<?php echo $filters['availability'] === 'in-stock' ? ' selected' : ''; ?>>In stock</option>
                                <option value="low-stock"<?php echo $filters['availability'] === 'low-stock' ? ' selected' : ''; ?>>Low stock</option>
                                <option value="sold-out"<?php echo $filters['availability'] === 'sold-out' ? ' selected' : ''; ?>>Sold out</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="sort">Sort by</label>
                            <select id="sort" name="sort">
                                <option value="recommended"<?php echo $filters['sort'] === 'recommended' ? ' selected' : ''; ?>>Recommended</option>
                                <option value="trending"<?php echo $filters['sort'] === 'trending' ? ' selected' : ''; ?>>Trending</option>
                                <option value="best"<?php echo $filters['sort'] === 'best' ? ' selected' : ''; ?>>Best sellers</option>
                                <option value="newest"<?php echo $filters['sort'] === 'newest' ? ' selected' : ''; ?>>Newest</option>
                                <option value="rating"<?php echo $filters['sort'] === 'rating' ? ' selected' : ''; ?>>Highest rated</option>
                                <option value="price-low"<?php echo $filters['sort'] === 'price-low' ? ' selected' : ''; ?>>Price: low to high</option>
                                <option value="price-high"<?php echo $filters['sort'] === 'price-high' ? ' selected' : ''; ?>>Price: high to low</option>
                            </select>
                        </div>

                        <div class="button-row">
                            <button type="submit" class="button button-dark">Apply filters</button>
                            <a href="shop.php" class="button button-light">Reset</a>
                        </div>
                    </form>
            </details>
            <div class="product-grid-wrapper">
                <div class="section-head results-head">
                    <div><span class="eyebrow"><?php echo $active_filter_count ? 'Your selection' : 'Explore the edit'; ?></span><h2 class="section-title"><?php echo $matched_product_count; ?> product<?php echo $matched_product_count === 1 ? '' : 's'; ?></h2></div>
                    <?php if ($active_filter_count): ?><a href="shop.php" class="text-link">Clear filters (<?php echo $active_filter_count; ?>)</a><?php else: ?><p class="section-copy">Find it. Make it yours.</p><?php endif; ?>
                </div>
                <?php if (empty($products)): ?>
                    <div class="surface-card empty-state">
                        <span class="eyebrow">No matches this time</span>
                        <h3>Let's try a different fit.</h3>
                        <p>Try a broader search or remove a filter to see more of the collection.</p>
                        <div class="button-row"><a href="shop.php" class="button button-dark">Clear filters</a><a href="collection.php" class="button button-light">Explore collections</a></div>
                    </div>
                <?php else: ?>
                    <div class="full-width-grid">
                        <?php foreach ($products as $product): ?><?php storefront_render_product_card($product); ?><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($recommended)): ?>
                    <section class="smart-match-wrapper">
                        <div class="section-head"><div><span class="eyebrow">A little inspiration</span><h2 class="section-title">Recommended for you.</h2></div></div>
                        <div class="smart-match-grid">
                            <?php foreach ($recommended as $product): ?><?php storefront_render_product_card($product, array('reason' => get_store_recommendation_reason($product, $personalization_seed))); ?><?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </section>

        <?php storefront_render_footer('Shop', 'Filter the full assortment, jump into product pages, reserve by size, and let the recommendation engine keep the browsing flow personal and premium.'); ?>
    </main>

<?php storefront_render_mobile_dock('shop'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body> 
</html>
