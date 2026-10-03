<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();
storefront_require_login('preferences.php');

$profile = store_state_get_user_profile($context['user_email']);
$profile = array_merge(array(
    'brands' => array(),
    'categories' => array(),
    'styles' => array(),
    'shoe_size' => '',
    'apparel_size' => '',
    'budget_max' => '',
), $profile);

$brands = get_store_brand_names();
$categories = get_store_categories();
$style_options = get_store_style_names();
$budget_options = get_store_budget_options();
$shoe_sizes = array('6', '7', '8', '8.5', '9', '9.5', '10', '11', '12');
$apparel_sizes = array('S', 'M', 'L', 'XL', 'XXL');
$is_welcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please save your fit profile again.');
        storefront_redirect('preferences.php');
    }

    $new_profile = array(
        'brands' => isset($_POST['brands']) && is_array($_POST['brands']) ? array_values(array_filter(array_map('trim', $_POST['brands']))) : array(),
        'categories' => isset($_POST['categories']) && is_array($_POST['categories']) ? array_values(array_filter(array_map('trim', $_POST['categories']))) : array(),
        'styles' => isset($_POST['styles']) && is_array($_POST['styles']) ? array_values(array_filter(array_map('trim', $_POST['styles']))) : array(),
        'shoe_size' => trim((string) ($_POST['shoe_size'] ?? '')),
        'apparel_size' => trim((string) ($_POST['apparel_size'] ?? '')),
        'budget_max' => trim((string) ($_POST['budget_max'] ?? '')),
    );

    if (empty($new_profile['categories'])) {
        storefront_set_flash('error', 'Choose at least one category so Shoestagram knows what to recommend.');
        storefront_redirect('preferences.php');
    }

    if ($new_profile['shoe_size'] === '' && $new_profile['apparel_size'] === '') {
        storefront_set_flash('error', 'Choose at least one size so we can show products that fit you.');
        storefront_redirect('preferences.php');
    }

    if (store_state_save_user_profile($context['user_email'], $new_profile)) {
        storefront_set_preferences(
            $new_profile['brands'][0] ?? '',
            $new_profile['styles'][0] ?? '',
            $new_profile['budget_max']
        );
        storefront_set_flash('success', 'Your fit profile is saved. The homepage will now show recommendations shaped around your style, sizes, and budget.');
        storefront_redirect('index.php');
    }

    storefront_set_flash('error', 'We could not save your fit profile right now. Please try again.');
    storefront_redirect('preferences.php');
}

storefront_render_head(
    'Shoestagram | Your Fit Profile',
    'Set your sizes and style preferences so Shoestagram can recommend products that truly fit your shopping profile.'
);
?>
<body class="store-body">
<?php storefront_render_header('account', $context, array(
    'search_placeholder' => 'Search while building your fit profile',
    'brand_tagline' => 'Fit Profile and Recommendation Setup',
    'announcement' => 'Choose your brands, categories, sizes, and budget so the homepage can surface products that actually fit your taste.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow"><?php echo $is_welcome ? 'Welcome to Shoestagram' : 'Update your fit profile'; ?></span>
                        <span class="hero-chip">Personalized recommendations</span>
                    </div>
                    <h1 class="display-font hero-title">Your style. Your fit.</h1>
                    <p class="hero-description">Save your sizes, favorite brands, and budget. We will use them to help you discover pieces that feel more like you.</p>
                    <div class="hero-notes">
                        <div class="hero-note">
                            <strong>Size-aware</strong>
                            <span>Recommendations prefer products that already carry your selected sizes.</span>
                        </div>
                        <div class="hero-note">
                            <strong>Style-aware</strong>
                            <span>Preferred silhouettes and categories feed directly into the recommendation score.</span>
                        </div>
                        <div class="hero-note">
                            <strong>Budget-aware</strong>
                            <span>The storefront can keep suggestions closer to your spending range.</span>
                        </div>
                    </div>
                </article>

                <aside class="surface-card showcase-panel">
                    <div class="showcase-heading">
                        <span class="eyebrow">What changes after this?</span>
                        <p>Your customer homepage and account hub will get more useful immediately.</p>
                    </div>
                    <div class="flow-grid mini-flow-grid">
                        <article class="flow-card inner-card">
                            <span>01</span>
                            <strong>Shoes fit for you</strong>
                            <p>Footwear recommendations start respecting your preferred shoe size and style direction.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>02</span>
                            <strong>Clothing that matches</strong>
                            <p>Apparel picks lean toward the categories and sizes you actually want to wear.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>03</span>
                            <strong>Faster decisions</strong>
                            <p>The homepage becomes more helpful because it starts with products that make sense for you.</p>
                        </article>
                    </div>
                </aside>
            </div>
        </section>

        <section class="section reveal">
            <div class="contact-grid">
                <article class="surface-card contact-card">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Your preferences</span>
                            <h2 class="display-font section-title">A few details. Better finds.</h2>
                        </div>
                    </div>
                    <form method="POST" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="field">
                            <label>Preferred brands</label>
                            <div class="choice-grid">
                                <?php foreach ($brands as $brand): ?>
                                    <label class="choice-card">
                                        <input type="checkbox" name="brands[]" value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($brand, $profile['brands'], true) ? ' checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="field">
                            <label>What do you usually buy?</label>
                            <div class="choice-grid">
                                <?php foreach ($categories as $key => $label): ?>
                                    <?php if ($key === 'all') { continue; } ?>
                                    <label class="choice-card">
                                        <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($key, $profile['categories'], true) ? ' checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="field">
                            <label>Preferred styles</label>
                            <div class="choice-grid">
                                <?php foreach ($style_options as $style): ?>
                                    <label class="choice-card">
                                        <input type="checkbox" name="styles[]" value="<?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($style, $profile['styles'], true) ? ' checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label for="shoe_size">Shoe size</label>
                                <select id="shoe_size" name="shoe_size">
                                    <option value="">Select shoe size</option>
                                    <?php foreach ($shoe_sizes as $size): ?>
                                        <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $profile['shoe_size'] === $size ? ' selected' : ''; ?>><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="apparel_size">Apparel size</label>
                                <select id="apparel_size" name="apparel_size">
                                    <option value="">Select apparel size</option>
                                    <?php foreach ($apparel_sizes as $size): ?>
                                        <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $profile['apparel_size'] === $size ? ' selected' : ''; ?>><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="budget_max">Budget ceiling</label>
                            <select id="budget_max" name="budget_max">
                                <option value="">Any price</option>
                                <?php foreach ($budget_options as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $profile['budget_max'] === $value ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="button-row">
                            <button type="submit" class="button button-dark">Save my fit profile</button>
                            <a href="account.php" class="button button-light">Back to account</a>
                        </div>
                    </form>
                </article>

                <aside class="support-stack">
                    <article class="surface-card contact-card">
                        <span class="eyebrow">Current profile</span>
                        <div class="support-list">
                            <div>
                                <strong>Shoe size</strong>
                                <span><?php echo htmlspecialchars($profile['shoe_size'] !== '' ? $profile['shoe_size'] : 'Not selected', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div>
                                <strong>Apparel size</strong>
                                <span><?php echo htmlspecialchars($profile['apparel_size'] !== '' ? $profile['apparel_size'] : 'Not selected', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div>
                                <strong>Budget</strong>
                                <span><?php echo htmlspecialchars($profile['budget_max'] !== '' ? (get_store_budget_options()[$profile['budget_max']] ?? ('PHP ' . $profile['budget_max'] . ' and below')) : 'Any price', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </article>
                </aside>
            </div>
        </section>

        <?php storefront_render_footer('Fit Profile', 'Your saved fit profile lets Shoestagram recommend products that match your sizes, categories, style direction, and spending range.'); ?>
    </main>

<?php storefront_render_mobile_dock('account'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
