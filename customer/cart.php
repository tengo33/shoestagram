<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../store-data.php';
require_once 'storefront-layout.php';

storefront_require_login('cart.php');

$context = storefront_get_context();
$cart_items = store_get_cart_items($context['user_id']);
$subtotal = store_get_cart_subtotal($context['user_id']);
$grand_total = $subtotal;
$gcash_qr_path = store_gcash_qr_public_path('../');

storefront_render_head(
    'Shoestagram | Cart',
    'Review your Shoestagram cart, adjust quantities, remove items, and prepare for checkout.'
);
?>
<body class="store-body">
<?php storefront_render_header('cart', $context, array(
    'search_placeholder' => 'Search before checkout',
    'brand_tagline' => 'Cart and Checkout Prep',
    'announcement' => 'Your cart is private to your account and ready for quantity changes, removals, and checkout prep.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow">Shopping cart</span>
                        <span class="hero-chip"><?php echo (int) $context['cart_count']; ?> item<?php echo (int) $context['cart_count'] === 1 ? '' : 's'; ?></span>
                    </div>
                    <h1 class="display-font hero-title">Your next rotation.</h1>
                    <p class="hero-description">Review your sizes and quantities, then reserve your picks for in-store pickup.</p>
                    <div class="hero-actions">
                        <a href="shop.php" class="button button-dark">Continue shopping</a>
                        <?php if (!empty($cart_items)): ?>
                            <form method="POST" action="cart-action.php" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="clear">
                                <input type="hidden" name="redirect" value="cart.php">
                                <button type="submit" class="button button-light">Clear cart</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>

                <aside class="surface-card showcase-panel cart-guide-card">
                    <span class="eyebrow">Checkout flow</span>
                    <div class="support-list">
                        <div>
                            <strong>1. Review items</strong>
                            <span>Check size, quantity, and product mix before you pay.</span>
                        </div>
                        <div>
                            <strong>2. Confirm total</strong>
                            <span>Review your total and choose how you would like to pay.</span>
                        </div>
                        <div>
                            <strong>3. Finish checkout</strong>
                            <span>Submit your reservation and track confirmation from your account.</span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="section reveal">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Cart items</span>
                    <h2 class="display-font section-title">Your selected products.</h2>
                </div>
                <p class="section-copy">Everything you picked, all in one place.</p>
            </div>

            <?php if (empty($cart_items)): ?>
                <div class="surface-card empty-state">
                    <span class="eyebrow">Cart empty</span>
                    <h3 class="display-font">Start from the shop and add products you want to compare.</h3>
                    <p>Your cart will stay attached to this account once you add something.</p>
                    <div class="button-row">
                        <a href="shop.php" class="button button-dark">Browse the catalog</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="cart-list">
                    <?php foreach ($cart_items as $item): ?>
                        <?php $product = $item['product']; ?>
                        <article class="surface-card cart-item-card">
                            <a href="product.php?slug=<?php echo urlencode($product['slug']); ?>" class="cart-item-media">
                                <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            </a>
                            <div class="cart-item-main">
                                <span class="product-subtitle"><?php echo htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo htmlspecialchars(get_store_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="product-stats">
                                    <?php if ($item['size'] !== ''): ?>
                                        <span>Size <?php echo htmlspecialchars(store_size_display($item['size'], $item['size_standard'] ?? 'US'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span>Size not selected</span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars(store_format_money($product['price']), ENT_QUOTES, 'UTF-8'); ?> each</span>
                                    <span><?php echo (int) $product['stock']; ?> in stock</span>
                                </div>
                            </div>
                            <div class="cart-item-controls">
                                <form method="POST" action="cart-action.php" class="quantity-control cart-quantity-control" data-quantity-control>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?php echo (int) $item['cart_id']; ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <button type="submit" name="quantity_step" value="decrease" data-quantity-decrease data-server-quantity-step aria-label="Decrease quantity"<?php echo (int) $item['quantity'] <= 1 ? ' disabled' : ''; ?>>&minus;</button>
                                    <input type="number" name="quantity" value="<?php echo (int) $item['quantity']; ?>" min="1" max="<?php echo max(1, (int) $product['stock']); ?>" step="1" inputmode="numeric" aria-label="Quantity for <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required<?php echo (int) $product['stock'] <= 0 ? ' disabled' : ''; ?>>
                                    <button type="submit" name="quantity_step" value="increase" data-quantity-increase data-server-quantity-step aria-label="Increase quantity"<?php echo (int) $product['stock'] <= 0 || (int) $item['quantity'] >= (int) $product['stock'] ? ' disabled' : ''; ?>>+</button>
                                </form>
                                <strong><?php echo htmlspecialchars(store_format_money($item['line_total']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <form method="POST" action="cart-action.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_id" value="<?php echo (int) $item['cart_id']; ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <button type="submit" class="button button-light">Remove</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form method="POST" action="cart-action.php" class="surface-card cart-summary-card cart-summary-lower cart-checkout-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="reserve_all">
                    <input type="hidden" name="redirect" value="account.php#orders">

                    <div class="cart-payment-section">
                        <div class="section-head compact-head">
                            <div>
                                <span class="eyebrow">Payment method</span>
                                <h3 class="display-font">Choose how you want to pay.</h3>
                            </div>
                        </div>
                        <div class="field">
                            <label for="cart_payment_method">Payment option</label>
                            <select id="cart_payment_method" name="payment_method" required>
                                <option value="In-Store Payment">Walk-In Payment</option>
                                <option value="Online Payment">Online Payment</option>
                            </select>
                        </div>
                        <div class="payment-reference-panel cart-payment-panel" data-cart-payment-reference-panel>
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
                                        <label for="cart_payment_proof">Upload proof of payment</label>
                                        <input type="file" id="cart_payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                        <span class="field-note">JPG, PNG, or WEBP only. Maximum file size: 5 MB.</span>
                                    </div>
                                    <div class="field">
                                        <label for="cart_payment_reference">GCash reference number</label>
                                        <input type="text" id="cart_payment_reference" name="payment_reference" placeholder="Example: GCASH-123456">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="payment-store-panel" data-cart-instore-payment-panel>
                            <span class="eyebrow">Walk-in payment</span>
                            <p>Pay at the Shoestagram counter when your cart reservation is confirmed and ready for pickup.</p>
                        </div>
                    </div>

                    <div class="checkout-totals"><span class="eyebrow">Order summary</span>
                    <div class="summary-line">
                        <span>Subtotal</span>
                        <strong><?php echo htmlspecialchars(store_format_money($subtotal), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="summary-line">
                        <span>Grand total</span>
                        <strong><?php echo htmlspecialchars(store_format_money($grand_total), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <p class="field-note">Online payments are verified by staff. A reservation is not a payment confirmation.</p><button type="submit" class="button button-dark">Reserve now <i class="fas fa-arrow-right" aria-hidden="true"></i></button></div>
                </form>
            <?php endif; ?>
        </section>

        <?php storefront_render_footer('Cart', 'Cart entries are stored per account with product relationships, private quantities, and checkout-ready totals.'); ?>
    </main>

<?php storefront_render_mobile_dock('cart'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const paymentSelect = document.getElementById('cart_payment_method');
        const paymentPanel = document.querySelector('[data-cart-payment-reference-panel]');
        const inStorePaymentPanel = document.querySelector('[data-cart-instore-payment-panel]');
        const paymentReference = document.getElementById('cart_payment_reference');
        const paymentProof = document.getElementById('cart_payment_proof');

        function syncCartPayment() {
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

        if (paymentSelect) {
            paymentSelect.addEventListener('change', syncCartPayment);
            syncCartPayment();
        }
    });
    </script>
    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
