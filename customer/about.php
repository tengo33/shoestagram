<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();
$brands = array_slice(get_store_brands(), 0, 6);
$stories = get_store_story_highlights();

storefront_render_head(
    'Shoestagram | About',
    'Learn how Shoestagram blends premium sneaker culture, curated fashion, reservation flow, and recommendation-driven shopping.'
);
?>
<body class="store-body">
<?php storefront_render_header('about', $context, array(
    'search_placeholder' => 'Search the collection',
    'brand_tagline' => 'Brand Story and Recommendation Logic',
    'announcement' => 'Shoestagram is designed to feel like a premium sneaker store and a modern social discovery space at the same time.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow">About Shoestagram</span>
                        <span class="hero-chip">Instagram x sneaker retail</span>
                    </div>
                    <h1 class="display-font hero-title">Good style is personal.</h1>
                    <p class="hero-description">Sneakers set the tone. Streetwear makes it yours. Shoestagram brings your next favorites together, with a little inspiration to help you find your own rotation.</p>
                    <div class="hero-actions">
                        <a href="shop.php" class="button button-dark">Explore the catalog</a>
                        <a href="contact.php" class="button button-light">Talk to support</a>
                    </div>
                </article>

                <aside class="surface-card showcase-panel">
                    <div class="showcase-heading">
                        <span class="eyebrow">A good shopping day</span>
                        <p>From your first favorite to your next pickup, keep it simple.</p>
                    </div>
                    <div class="flow-grid mini-flow-grid">
                        <article class="flow-card inner-card">
                            <span>01</span>
                            <strong>Premium discovery</strong>
                            <p>Feed-like highlights, hover-rich product cards, and polished storefront storytelling.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>02</span>
                            <strong>Reservation-first shopping</strong>
                            <p>Choose a size and follow your reservation from confirmation to pickup.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>03</span>
                            <strong>Feedback loop</strong>
                            <p>Tell the community what you think after completing your purchase.</p>
                        </article>
                    </div>
                </aside>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Core values</span>
                    <h2 class="display-font section-title">Made for your everyday.</h2>
                </div>
                <p class="section-copy">Find pieces you love, choose your fit, and reserve them with clear stock and pickup information.</p>
            </div>
            <div class="brand-grid">
                <article class="brand-card surface-card hover-lift">
                    <strong>Editorial curation</strong>
                    <p>The catalog is built to feel selected, not dumped onto one endless page without hierarchy.</p>
                </article>
                <article class="brand-card surface-card hover-lift">
                    <strong>Recommendation-first shopping</strong>
                    <p>Preferred brand, style, price sensitivity, live stock, and trend signals shape what customers see first.</p>
                </article>
                <article class="brand-card surface-card hover-lift">
                    <strong>Premium UI language</strong>
                    <p>Rounded cards, layered gradients, subtle glass surfaces, hover lift, and social-inspired discovery cues keep browsing memorable.</p>
                </article>
                <article class="brand-card surface-card hover-lift">
                    <strong>Operational visibility</strong>
                    <p>Track your reservation and payment status from your account, and reach our team when you need help.</p>
                </article>
            </div>
        </section>

        <section class="section reveal">
            <div class="split-showcase">
                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Recommendation engine</span>
                            <h2 class="display-font section-title">A more personal selection.</h2>
                        </div>
                    </div>
                    <ul class="feature-list">
                        <li>Preferred brand and recent browsing behavior help determine the strongest matches.</li>
                        <li>Style tags and product silhouettes raise relevance when customers lean technical, retro, tailored, or minimal.</li>
                        <li>Budget ceilings shape which products feel realistic instead of aspirational-only.</li>
                        <li>Low stock, strong ratings, and trend score keep urgency and quality visible.</li>
                    </ul>
                </article>

                <article class="surface-card split-panel">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Story-style lanes</span>
                            <h2 class="display-font section-title">Follow your own style.</h2>
                        </div>
                    </div>
                    <div class="story-strip tight-story-strip">
                        <?php foreach ($stories as $story): ?>
                            <a href="<?php echo htmlspecialchars($story['href'], ENT_QUOTES, 'UTF-8'); ?>" class="story-card hover-lift">
                                <span class="story-icon"><i class="fas <?php echo htmlspecialchars($story['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                <strong><?php echo htmlspecialchars($story['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($story['meta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Brands in our collection</span>
                    <h2 class="display-font section-title">Labels you know. Pieces you love.</h2>
                </div>
            </div>
            <div class="brand-grid">
                <?php foreach ($brands as $brand): ?>
                    <article class="brand-card surface-card hover-lift">
                        <strong><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p><?php echo htmlspecialchars($brand['copy'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="shop.php?brand=<?php echo urlencode($brand['name']); ?>" class="text-link">Shop this brand <i class="fas fa-arrow-right"></i></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php storefront_render_footer('About', 'Shoestagram is designed to feel like a premium commercial system: modern, memorable, responsive, recommendation-led, and grounded in a reservation-to-feedback lifecycle.'); ?>
    </main>

<?php storefront_render_mobile_dock('about'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
