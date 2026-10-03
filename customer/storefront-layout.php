<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function storefront_nav_items()
{
    return array(
        array('key' => 'home', 'href' => 'index.php', 'label' => 'Home'),
        array('key' => 'collection', 'href' => 'collection.php', 'label' => 'Collection'),
        array('key' => 'shop', 'href' => 'shop.php', 'label' => 'Shop'),
        array('key' => 'about', 'href' => 'about.php', 'label' => 'About'),
        array('key' => 'contact', 'href' => 'contact.php', 'label' => 'Contact'),
    );
}

function storefront_get_preferences()
{
    $stored = isset($_SESSION['shoestagram_preferences']) && is_array($_SESSION['shoestagram_preferences'])
        ? $_SESSION['shoestagram_preferences']
        : array();
    $profile = array();

    if (empty($stored) && isset($_SESSION['user_email'])) {
        $profile = store_state_get_user_profile($_SESSION['user_email']);
    }

    return array(
        'brand' => isset($stored['brand']) && trim((string) $stored['brand']) !== '' ? trim((string) $stored['brand']) : (isset($profile['brands'][0]) ? trim((string) $profile['brands'][0]) : ''),
        'style' => isset($stored['style']) && trim((string) $stored['style']) !== '' ? trim((string) $stored['style']) : (isset($profile['styles'][0]) ? trim((string) $profile['styles'][0]) : ''),
        'max_price' => isset($stored['max_price']) && trim((string) $stored['max_price']) !== '' ? trim((string) $stored['max_price']) : (isset($profile['budget_max']) ? trim((string) $profile['budget_max']) : ''),
    );
}

function storefront_set_preferences($brand, $style, $max_price)
{
    $_SESSION['shoestagram_preferences'] = array(
        'brand' => trim((string) $brand),
        'style' => trim((string) $style),
        'max_price' => trim((string) $max_price),
    );
}

function storefront_reset_recommendation_state()
{
    unset($_SESSION['shoestagram_preferences'], $_SESSION['shoestagram_recent_views']);
}

function storefront_track_recent_view($product_id)
{
    $product_id = (int) $product_id;

    if ($product_id <= 0) {
        return;
    }

    $recent = isset($_SESSION['shoestagram_recent_views']) && is_array($_SESSION['shoestagram_recent_views'])
        ? $_SESSION['shoestagram_recent_views']
        : array();

    $recent = array_values(array_filter($recent, function ($existing_id) use ($product_id) {
        return (int) $existing_id !== $product_id;
    }));

    array_unshift($recent, $product_id);
    $_SESSION['shoestagram_recent_views'] = array_slice($recent, 0, 6);
}

function storefront_get_recent_views()
{
    return isset($_SESSION['shoestagram_recent_views']) && is_array($_SESSION['shoestagram_recent_views'])
        ? array_values($_SESSION['shoestagram_recent_views'])
        : array();
}

function storefront_get_personalization_seed($context, $overrides = array())
{
    $preferences = isset($context['preferences']) ? $context['preferences'] : storefront_get_preferences();
    $seed = array(
        'brand' => trim((string) ($overrides['brand'] ?? $preferences['brand'])),
        'style' => trim((string) ($overrides['style'] ?? $preferences['style'])),
        'max_price' => trim((string) ($overrides['max_price'] ?? $preferences['max_price'])),
    );

    $recent_views = storefront_get_recent_views();

    if ((!isset($seed['brand']) || $seed['brand'] === '') && !empty($recent_views)) {
        $brand_count = array();

        foreach ($recent_views as $product_id) {
            $product = get_store_product_by_id($product_id);

            if (!$product) {
                continue;
            }

            $brand = (string) $product['brand'];

            if (!isset($brand_count[$brand])) {
                $brand_count[$brand] = 0;
            }

            $brand_count[$brand]++;
        }

        arsort($brand_count);
        $seed['brand'] = (string) key($brand_count);
    }

    if ((!isset($seed['style']) || $seed['style'] === '') && !empty($recent_views)) {
        foreach ($recent_views as $product_id) {
            $product = get_store_product_by_id($product_id);

            if ($product) {
                $seed['style'] = (string) $product['style'];
                break;
            }
        }
    }

    return $seed;
}

function storefront_get_context()
{
    $email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
    $is_logged_in = isset($_SESSION['user_id']);
    $user_id = $is_logged_in ? (int) $_SESSION['user_id'] : 0;
    $cart_count = $is_logged_in ? store_get_cart_count((int) $_SESSION['user_id']) : 0;
    $wishlist_count = $is_logged_in ? store_get_wishlist_count((int) $_SESSION['user_id']) : 0;

    return array(
        'is_logged_in' => $is_logged_in,
        'user_id' => $user_id,
        'user_name' => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
        'user_email' => $email,
        'user_role' => isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '',
        'preferences' => storefront_get_preferences(),
        'recent_views' => storefront_get_recent_views(),
        'profile' => $email !== '' ? store_state_get_user_profile($email) : array(),
        'cart_count' => $cart_count,
        'wishlist_count' => $wishlist_count,
    );
}

function storefront_role_label($role)
{
    if ($role === 'admin') {
        return 'Administrator';
    }

    if ($role === 'staff') {
        return 'Store Staff';
    }

    return 'Member';
}

function storefront_csrf_token()
{
    if (empty($_SESSION['shoestagram_csrf_token'])) {
        try {
            $_SESSION['shoestagram_csrf_token'] = bin2hex(random_bytes(16));
        } catch (Exception $exception) {
            $_SESSION['shoestagram_csrf_token'] = md5(uniqid('', true));
        }
    }

    return $_SESSION['shoestagram_csrf_token'];
}

function storefront_verify_csrf($token)
{
    $stored = isset($_SESSION['shoestagram_csrf_token']) ? (string) $_SESSION['shoestagram_csrf_token'] : '';
    $token = (string) $token;

    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

function storefront_set_flash($type, $message)
{
    if (!isset($_SESSION['shoestagram_flash']) || !is_array($_SESSION['shoestagram_flash'])) {
        $_SESSION['shoestagram_flash'] = array();
    }

    $_SESSION['shoestagram_flash'][] = array(
        'type' => (string) $type,
        'message' => (string) $message,
    );
}

function storefront_pull_flash_messages()
{
    $messages = isset($_SESSION['shoestagram_flash']) && is_array($_SESSION['shoestagram_flash'])
        ? $_SESSION['shoestagram_flash']
        : array();

    unset($_SESSION['shoestagram_flash']);

    return $messages;
}

function storefront_safe_redirect_target($value, $fallback)
{
    $value = trim((string) $value);

    if ($value === '' || strpos($value, '://') !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
        return $fallback;
    }

    return $value;
}

function storefront_redirect($target)
{
    if (!headers_sent()) {
        header('Location: ' . $target);
        exit();
    } else {
        // If headers are already sent, use JavaScript fallback
        echo '<script>window.location.href = "' . addslashes($target) . '";</script>';
        exit();
    }
}

function storefront_require_login($redirect_target)
{
    if (!isset($_SESSION['user_id'])) {
        $redirect_target = trim((string) $redirect_target);

        if ($redirect_target !== '' && strpos($redirect_target, '../') !== 0) {
            $redirect_target = '../customer/' . ltrim($redirect_target, '/');
        }

        storefront_set_flash('error', 'Create an account or sign in first before adding products, reserving items, or using account tools.');
        storefront_redirect('../login/login.php?redirect=' . urlencode($redirect_target));
    }
}

function storefront_render_head($title, $description)
{
    $manifest_relative_path = '../manifest.json';
    $manifest_file_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'manifest.json';
    $has_manifest = is_file($manifest_file_path);
    $csrf_token = storefront_csrf_token();
    ?>
<!DOCTYPE html>
<html lang="en" class="storefront-document">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#292c26">
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="icon" type="image/jpeg" href="shoelogo.jpg">
<link rel="apple-touch-icon" href="shoelogo.jpg">    
    <link rel="stylesheet" href="../store-theme.css?v=<?php echo filemtime(dirname(__DIR__) . '/store-theme.css'); ?>">
    <link rel="stylesheet" href="storefront-theme.css?v=<?php echo filemtime(__DIR__ . '/storefront-theme.css'); ?>">
    <script src="storefront-experience.js?v=<?php echo filemtime(__DIR__ . '/storefront-experience.js'); ?>" defer></script>
    <script src="../flash-notifications.js?v=<?php echo filemtime(dirname(__DIR__) . '/flash-notifications.js'); ?>" defer></script>
    <meta name="shoestagram-csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="shoestagram-authenticated" content="<?php echo isset($_SESSION['user_id']) ? '1' : '0'; ?>">
    <?php if ($has_manifest): ?>
        <link rel="manifest" href="<?php echo htmlspecialchars($manifest_relative_path, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<?php
}

function storefront_render_flash_stack()
{
    $messages = storefront_pull_flash_messages();

    if (empty($messages)) {
        return;
    }
    ?>
    <div class="flash-stack page-shell">
        <?php foreach ($messages as $message): ?>
            <?php $flash_type = in_array(($message['type'] ?? ''), array('success', 'error', 'warning', 'info'), true) ? (string) $message['type'] : 'info'; ?>
            <div role="<?php echo $flash_type === 'error' ? 'alert' : 'status'; ?>" aria-live="polite" class="flash flash-<?php echo htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8'); ?>" data-flash-notification data-flash-type="<?php echo htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas <?php echo $flash_type === 'success' ? 'fa-circle-check' : ($flash_type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info'); ?>" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                <button type="button" class="flash-close" data-flash-close aria-label="Dismiss notification"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function storefront_render_header($active_nav, $context, $options = array())
{
    $search_placeholder = isset($options['search_placeholder']) ? $options['search_placeholder'] : 'Search sneakers, apparel, brands';
    $brand_tagline = isset($options['brand_tagline']) ? $options['brand_tagline'] : 'Premium Streetwear Discovery';
    $announcement = isset($options['announcement']) ? $options['announcement'] : 'Premium sneaker and streetwear recommendations with reservation tracking built in.';
    $wishlist_count = isset($context['wishlist_count']) ? (int) $context['wishlist_count'] : 0;
    $cart_count = isset($context['cart_count']) ? (int) $context['cart_count'] : 0;
    ?>
    <a class="skip-link" href="#storefrontMain">Skip to content</a>

    <header class="site-header">
        <div class="announcement-bar">
            <div class="page-shell announcement-inner">
                <p>Your style. Your rotation. <a href="shop.php?sort=newest">Explore the latest <i class="fas fa-arrow-right" aria-hidden="true"></i></a></p>
            </div>
        </div>

        <div class="page-shell site-header-inner">
            <button type="button" class="tool-button mobile-menu-toggle" data-open-menu aria-label="Open navigation" aria-haspopup="dialog" aria-controls="storefrontMenu"><i class="fas fa-bars" aria-hidden="true"></i></button>
            <a href="index.php" class="brand">
                <span class="brand-mark">
                    <img src="shoelogo.jpg" alt="" width="44" height="44">
                </span>
                <span class="brand-copy">
                    <strong>Shoestagram</strong>
                    <span>Sneakers &amp; streetwear</span>
                </span>
            </a>

            <nav class="desktop-nav" aria-label="Main navigation">
                <?php foreach (storefront_nav_items() as $item): ?>
                    <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $active_nav === $item['key'] ? ' aria-current="page"' : ''; ?> class="nav-pill<?php echo $active_nav === $item['key'] ? ' is-active' : ''; ?>">
                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="header-tools">
                <form action="shop.php" method="GET" class="search-shell desktop-only">
                    <i class="fas fa-search"></i>
                    <label class="sr-only" for="headerSearch">Search products</label>
                    <input type="search" id="headerSearch" name="search" placeholder="Search the collection">
                </form>

                <a href="wishlist.php" class="tool-button<?php echo $active_nav === 'wishlist' ? ' is-active-tool' : ''; ?>" aria-label="<?php echo htmlspecialchars('Open wishlist' . ($wishlist_count > 0 ? ' (' . $wishlist_count . ' saved)' : ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="<?php echo $active_nav === 'wishlist' ? 'fas' : 'far'; ?> fa-heart"></i>
                    <span id="wishlistCount" class="tool-count" data-server-count="<?php echo $wishlist_count; ?>"><?php echo $wishlist_count > 0 ? (string) $wishlist_count : ''; ?></span>
                </a>

                <a href="cart.php" id="cartButton" class="tool-button" aria-label="<?php echo htmlspecialchars('Open cart' . ($cart_count > 0 ? ' (' . $cart_count . ' item' . ($cart_count === 1 ? '' : 's') . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-bag-shopping"></i>
                    <span id="cartCount" class="tool-count" data-server-count="<?php echo $cart_count; ?>"><?php echo $cart_count > 0 ? (string) $cart_count : ''; ?></span>
                </a>

                <?php if ($context['is_logged_in']): ?>
                    <details class="account-menu">
                        <summary class="account-summary" aria-label="Open account menu">
                            <span class="account-avatar"><?php echo htmlspecialchars(strtoupper(substr($context['user_name'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="account-meta">
                                <strong><?php echo htmlspecialchars($context['user_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small><?php echo htmlspecialchars(storefront_role_label($context['user_role']), ENT_QUOTES, 'UTF-8'); ?></small>
                            </span>
                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </summary>
                        <div class="account-panel">
                            <div class="account-box">
                                <strong><?php echo htmlspecialchars($context['user_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($context['user_email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="account-links">
                                <a href="account.php" class="account-link">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <span>My account</span>
                                </a>
                                <a href="account.php#orders" class="account-link"><i class="fas fa-bag-shopping" aria-hidden="true"></i><span>Orders &amp; reservations</span></a>
                                <a href="preferences.php" class="account-link">
                                    <i class="fas fa-sliders" aria-hidden="true"></i>
                                    <span>Edit fit profile</span>
                                </a>
                                <?php if ($context['user_role'] === 'admin'): ?>
                                    <a href="../admin/dashboard.php" class="account-link">
                                        <i class="fas fa-shield-alt" aria-hidden="true"></i>
                                        <span>Open admin dashboard</span>
                                    </a>
                                <?php elseif ($context['user_role'] === 'staff'): ?>
                                    <a href="../staff/dashboard.php" class="account-link">
                                        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                                        <span>Open staff workspace</span>
                                    </a>
                                <?php endif; ?>
                                <a href="contact.php" class="account-link">
                                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                    <span>Contact support</span>
                                </a>
                                <a href="../login/logout.php" class="account-link logout-link">
                                    <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </details>
                <?php else: ?>
                    <div class="button-row compact-row">
                        <a href="../login/login.php" class="button button-dark">Login</a>
                        <a href="../login/register.php" class="button button-light desktop-only">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <dialog id="storefrontMenu" class="store-dialog menu-dialog" aria-labelledby="menuTitle">
        <div class="dialog-heading"><h2 id="menuTitle">Explore Shoestagram</h2><button type="button" class="tool-button" data-close-dialog aria-label="Close navigation"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
        <form action="shop.php" method="GET" class="menu-search">
            <label for="mobileSearch">Find your next favorite</label>
            <div class="search-shell"><input type="search" id="mobileSearch" name="search" placeholder="Product, brand, or style"><button type="submit" class="tool-button" aria-label="Search"><i class="fas fa-search" aria-hidden="true"></i></button></div>
        </form>
        <nav aria-label="Mobile navigation" class="drawer-links">
            <?php foreach (storefront_nav_items() as $item): ?>
                <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $active_nav === $item['key'] ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?><i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            <?php endforeach; ?>
        </nav>
        <div class="drawer-secondary">
            <a href="shop.php?sort=newest">New arrivals</a>
            <a href="index.php#recommendations">Recommended for you</a>
            <a href="account.php#orders">My reservations &amp; orders</a>
            <a href="preferences.php">My fit profile</a>
            <?php if ($context['is_logged_in']): ?><a href="../login/logout.php">Logout</a><?php else: ?><a href="../login/login.php">Login / Register</a><?php endif; ?>
        </div>
        <p class="drawer-note">Good finds. Great rotation.</p>
    </dialog>
    <?php
}

function storefront_render_rating_markup($rating)
{
    $rounded = (int) round((float) $rating);
    $markup = '';

    for ($index = 1; $index <= 5; $index++) {
        $markup .= '<i class="' . ($index <= $rounded ? 'fas star-filled' : 'far star-empty') . ' fa-star"></i>';
    }

    return $markup;
}

function storefront_render_product_card($product, $options = array())
{
    $reason = $options['reason'] ?? '';
    $wishlist_actions = !empty($options['wishlist_actions']);
    $link = 'product.php?slug=' . urlencode($product['slug']);
    $in_stock = (int) $product['stock'] > 0;
    $stock_label = $in_stock ? ($product['stock_status'] ?? 'In stock') : 'Out of stock';
    $badge = $product['badge'] ?? '';
    if (strtolower($badge) === 'high margin') { $badge = 'Style pick'; }
    ?>
    <article class="product-card hover-lift">
        <div class="product-media">
            <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="product-cover-link" aria-label="View <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" class="product-image" loading="lazy" decoding="async" width="600" height="600">
            </a>
            <div class="product-topline">
                <?php if ($badge !== ''): ?><span class="product-badge"><?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                <?php if ($wishlist_actions): ?>
                    <form method="POST" action="wishlist-action.php" class="inline-action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>"><input type="hidden" name="redirect" value="wishlist.php">
                        <button type="submit" class="ghost-icon is-active" aria-label="Remove <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> from wishlist"><i class="fas fa-heart" aria-hidden="true"></i></button>
                    </form>
                <?php else: ?>
                    <button type="button" class="ghost-icon js-wishlist-toggle" data-wishlist-id="<?php echo (int) $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Save <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> to wishlist"><i class="far fa-heart" aria-hidden="true"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <div class="product-content">
            <div class="product-meta-line">
                <span class="product-subtitle"><?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="card-rating" aria-label="<?php echo number_format((float) $product['rating'], 1); ?> out of 5 stars"><i class="fas fa-star" aria-hidden="true"></i> <?php echo number_format((float) $product['rating'], 1); ?></span>
            </div>
            <h3 class="product-name"><a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
            <div class="card-price-line">
                <div class="price-block"><strong><?php echo htmlspecialchars(store_format_money($product['price']), ENT_QUOTES, 'UTF-8'); ?></strong><?php if (!empty($product['old_price'])): ?><span class="old-price"><?php echo htmlspecialchars(store_format_money($product['old_price']), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></div>
                <span class="card-stock<?php echo !$in_stock ? ' is-sold-out' : ((int) $product['stock'] <= 8 ? ' is-low' : ''); ?>"><?php echo htmlspecialchars($stock_label, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if ($reason !== ''): ?><p class="recommendation-tag"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i><span><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></span></p><?php endif; ?>
            <div class="product-actions">
                <?php if ($wishlist_actions): ?>
                    <form method="POST" action="wishlist-action.php" class="inline-action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="move_to_cart"><input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>"><input type="hidden" name="redirect" value="wishlist.php">
                        <button type="submit" class="button button-dark"<?php echo !$in_stock ? ' disabled' : ''; ?>>Move to cart</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="button button-dark" onclick="openAddToCartModal(<?php echo (int) $product['id']; ?>)"<?php echo !$in_stock ? ' disabled' : ''; ?>><?php echo $in_stock ? 'Choose size' : 'Sold out'; ?><i class="fas fa-plus" aria-hidden="true"></i></button>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="button button-light" aria-label="View <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">View</a>
            </div>
        </div>
    </article>
    <?php
}

function storefront_render_footer($eyebrow, $copy)
{
    ?>
    <footer class="site-footer reveal">
        <div class="page-shell footer-shell surface-card">
            <div class="footer-brand-block">
                <span class="eyebrow">The Shoestagram edit</span>
                <h2 class="display-font">Good finds.<br>Great rotation.</h2>
                <p>Sneakers, streetwear, and the pieces you make your own. Find your next favorite.</p>
            </div>
            <div class="footer-columns">
                <div>
                    <h3>Explore</h3>
                    <a href="index.php">Home</a>
                    <a href="collection.php">Collection</a>
                    <a href="shop.php">Shop</a>
                    <a href="wishlist.php">Wishlist</a>
                </div>
                <div>
                    <h3>Customer</h3>
                    <a href="account.php">Account hub</a>
                    <a href="contact.php">Support</a>
                    <a href="../login/login.php">Login</a>
                    <a href="../login/register.php">Register</a>
                </div>
                <div>
                    <h3>Your rotation</h3>
                    <a href="shop.php?sort=newest">New arrivals</a>
                    <a href="account.php#orders">Orders &amp; reservations</a>
                    <a href="preferences.php">Fit preferences</a>
                    <a href="about.php">Our story</a>
                </div>
            </div>
        </div>
        <p class="footer-note">&copy; <?php echo date('Y'); ?> Shoestagram. Your style. Your rotation.</p>
    </footer>
    <?php
}

function storefront_render_mobile_dock($active_nav)
{
    $items = array(
        array('key' => 'home', 'href' => 'index.php', 'icon' => 'fa-house', 'label' => 'Home'),
        array('key' => 'shop', 'href' => 'shop.php', 'icon' => 'fa-bag-shopping', 'label' => 'Shop'),
        array('key' => 'wishlist', 'href' => 'wishlist.php', 'icon' => 'fa-heart', 'label' => 'Saved'),
        array('key' => 'account', 'href' => 'account.php', 'icon' => 'fa-user', 'label' => 'Account'),
    );
    ?>
    <nav class="mobile-dock" aria-label="Quick navigation">
        <?php foreach ($items as $item): ?>
            <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="mobile-dock-link<?php echo $active_nav === $item['key'] ? ' is-active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
    storefront_render_cart_modal();
}


// One shared, keyboard-accessible quick-add dialog for all customer pages.
function storefront_render_cart_modal()
{
    ?>
    <dialog id="addToCartModal" class="store-dialog cart-dialog" aria-labelledby="cartDialogTitle" aria-describedby="modalStatus">
        <div class="dialog-heading">
            <div><span class="eyebrow">Make it part of your rotation</span><h2 id="cartDialogTitle">Choose your fit</h2></div>
            <button type="button" class="tool-button" data-close-dialog aria-label="Close add to cart"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <p id="modalStatus" class="field-note" role="status" aria-live="polite"></p>
        <div id="modalProductSummary" class="quick-product" hidden>
            <img id="modalProductImage" alt="" width="140" height="140">
            <div><h3 id="modalProductName"></h3><strong id="modalProductPrice"></strong><p id="modalStockInfo" class="field-note"></p></div>
        </div>
        <form method="POST" action="cart-action.php" id="modalCartForm" hidden>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" id="modalProductId">
            <input type="hidden" name="redirect" value="cart.php">
            <div class="form-grid">
                <div class="field"><label for="modalSizeStandard">Sizing standard</label><select id="modalSizeStandard" name="size_standard" required></select></div>
                <div class="field">
                    <label for="modalQuantity">Quantity</label>
                    <div class="quantity-control" data-quantity-control>
                        <button type="button" data-quantity-decrease aria-label="Decrease quantity" disabled>&minus;</button>
                        <input type="number" id="modalQuantity" name="quantity" value="1" min="1" max="1" step="1" inputmode="numeric" aria-label="Quantity" required disabled>
                        <button type="button" data-quantity-increase aria-label="Increase quantity" disabled>+</button>
                    </div>
                </div>
            </div>
            <div class="field"><label for="modalSize">Choose size</label><select id="modalSize" name="size" required data-size-choices><option value="">Select a size</option></select></div>
            <div class="button-row"><button type="submit" class="button button-dark" id="modalConfirmBtn">Add to cart <i class="fas fa-arrow-right" aria-hidden="true"></i></button><button type="button" class="button button-light" data-close-dialog>Keep browsing</button></div>
        </form>
    </dialog>
    <?php
}
