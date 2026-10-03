<?php
session_start();
require_once '../store-data.php';
require_once 'storefront-layout.php';

$context = storefront_get_context();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please submit the contact form again.');
        storefront_redirect('contact.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        storefront_set_flash('error', 'Please complete every field before sending your message.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        storefront_set_flash('error', 'Please use a valid email address.');
    } else {
        $saved = store_state_save_contact_message($name, $email, $subject, $message);
        $mail_body = "New Shoestagram contact message\n\n"
            . "Name: " . $name . "\n"
            . "Email: " . $email . "\n"
            . "Subject: " . $subject . "\n\n"
            . $message . "\n";
        $sent = shoestagram_send_email(
            shoestagram_support_email(),
            'Shoestagram contact: ' . $subject,
            $mail_body,
            $email
        );

        if ($saved && $sent) {
            storefront_set_flash('success', 'Your message has been sent to the Shoestagram team.');
        } elseif ($saved) {
            storefront_set_flash('warning', 'Your message was saved for our team to review. Email delivery is currently unavailable.');
        } else {
            storefront_set_flash('error', 'The message could not be saved right now. Please try again.');
        }
    }

    storefront_redirect('contact.php');
}

storefront_render_head(
    'Shoestagram | Contact',
    'Reach the Shoestagram team for sizing help, reservation support, product questions, or account guidance.'
);
?>
<body class="store-body">
<?php storefront_render_header('contact', $context, array(
    'search_placeholder' => 'Search before you ask',
    'brand_tagline' => 'Support and Store Contact',
    'announcement' => 'Customer inquiries can now flow into the same Shoestagram system that tracks reservations, reviews, and admin activity.',
)); ?>
<?php storefront_render_flash_stack(); ?>

    <main class="page-shell main-flow">
        <section class="hero-section reveal">
            <div class="hero-grid hero-home-grid">
                <article class="surface-card hero-copy hero-primary-card">
                    <div class="hero-kicker">
                        <span class="eyebrow">Contact support</span>
                        <span class="hero-chip">Fast help</span>
                    </div>
                    <h1 class="display-font hero-title">Let’s find your answer.</h1>
                    <p class="hero-description">Questions about sizing, availability, payment, or pickup? Send a note to the Shoestagram team.</p>
                    <div class="hero-notes">
                        <div class="hero-note">
                            <strong>Reservation support</strong>
                            <span>Ask about pending confirmations, ready-for-pickup timing, or order progress.</span>
                        </div>
                        <div class="hero-note">
                            <strong>Style guidance</strong>
                            <span>Get help narrowing footwear, apparel, or colorway decisions before you reserve.</span>
                        </div>
                    </div>
                </article>

                <aside class="surface-card showcase-panel">
                    <div class="showcase-heading">
                        <span class="eyebrow">Response topics</span>
                        <p>Our team can help with products, reservations, payments, and pickup.</p>
                    </div>
                    <div class="flow-grid mini-flow-grid">
                        <article class="flow-card inner-card">
                            <span>01</span>
                            <strong>Sizing and fit</strong>
                            <p>Find the right size before you lock stock through reservation.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>02</span>
                            <strong>Order progress</strong>
                            <p>Follow up on confirmation, pickup, and completion status.</p>
                        </article>
                        <article class="flow-card inner-card">
                            <span>03</span>
                            <strong>Recommendations</strong>
                            <p>Ask for help refining brands, styles, and price direction.</p>
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
                            <span class="eyebrow">Send a message</span>
                            <h2 class="display-font section-title">How can we help?</h2>
                        </div>
                    </div>
                    <form method="POST" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-grid">
                            <div class="field">
                                <label for="name">Full name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($context['is_logged_in'] ? $context['user_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="email">Email address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($context['is_logged_in'] ? $context['user_email'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        <div class="field">
                            <label for="subject">Subject</label>
                            <select id="subject" name="subject" required>
                                <option value="">Choose a topic</option>
                                <option value="Sizing help">Sizing help</option>
                                <option value="Reservation support">Reservation support</option>
                                <option value="Recommendation request">Recommendation request</option>
                                <option value="Stock availability">Stock availability</option>
                                <option value="General inquiry">General inquiry</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="6" placeholder="Tell us what you need help with..." required></textarea>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Send message</button>
                            <a href="shop.php" class="button button-light">Back to shop</a>
                        </div>
                    </form>
                </article>

                <aside class="support-stack">
                    <article class="surface-card contact-card">
                        <span class="eyebrow">Store support</span>
                        <h3 class="display-font">Here to help you shop.</h3>
                        <ul class="feature-list">
                            <li>Fast answers for reservation timing, product availability, and pickup readiness.</li>
                            <li>Clear recommendation guidance when you are stuck between brands, styles, or budgets.</li>
                            <li>Your message reaches the Shoestagram team for review and follow-up.</li>
                        </ul>
                    </article>

                    <article class="surface-card contact-card">
                        <span class="eyebrow">Direct channels</span>
                        <div class="support-list">
                            <div>
                                <strong>Email</strong>
                                <span><?php echo htmlspecialchars(shoestagram_support_email(), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div>
                                <strong>Pickup hours</strong>
                                <span>Monday to Saturday, 10:00 AM to 8:00 PM</span>
                            </div>
                            <div>
                                <strong>Reservation updates</strong>
                                <span>Track status from your account after you sign in.</span>
                            </div>
                        </div>
                    </article>
                </aside>
            </div>
        </section>

        <?php storefront_render_footer('Contact', 'Support is now connected to the same premium system as reservations, account history, admin visibility, and recommendation-aware customer service.'); ?>
    </main>

<?php storefront_render_mobile_dock('contact'); ?>

    <script src="storefront-ui.js?v=<?php echo filemtime(__DIR__ . '/storefront-ui.js'); ?>"></script>
</body>
</html>
