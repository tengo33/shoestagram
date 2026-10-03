<?php
require_once '../store-data.php';

function register_send_verification_code($email, $fullname, $code)
{
    $subject = 'Shoestagram verification code';
    $message = "Hi " . $fullname . ",\n\n"
        . "Use this Shoestagram verification code to finish creating your account:\n\n"
        . $code . "\n\n"
        . "The code expires in 15 minutes.\n\n"
        . "If you did not request this, you can ignore this email.\n";

    return shoestagram_send_email($email, $subject, $message);
}

function register_mail_failure_message()
{
    return shoestagram_mail_delivery_error('Gmail SMTP did not send the verification code. Please check the Gmail sender settings and try again.');
}

function register_profile_from_post($source)
{
    return array(
        'brands' => isset($source['brands']) && is_array($source['brands']) ? array_values(array_filter(array_map('trim', $source['brands']))) : array(),
        'categories' => isset($source['categories']) && is_array($source['categories']) ? array_values(array_filter(array_map('trim', $source['categories']))) : array(),
        'styles' => isset($source['styles']) && is_array($source['styles']) ? array_values(array_filter(array_map('trim', $source['styles']))) : array(),
        'shoe_size' => trim((string) ($source['shoe_size'] ?? '')),
        'apparel_size' => trim((string) ($source['apparel_size'] ?? '')),
        'budget_max' => trim((string) ($source['budget_max'] ?? '')),
    );
}

$error = '';
$success = '';
$warning = '';
$fullname_value = '';
$email_value = '';
$password_rules = database_password_requirements_text();
$brands = get_store_brand_names();
$categories = get_store_categories();
$style_options = get_store_style_names();
$budget_options = get_store_budget_options();
$shoe_sizes = array('6', '7', '8', '8.5', '9', '9.5', '10', '11', '12');
$apparel_sizes = array('S', 'M', 'L', 'XL', 'XXL');
$profile_value = array(
    'brands' => array(),
    'categories' => array(),
    'styles' => array(),
    'shoe_size' => '',
    'apparel_size' => '',
    'budget_max' => '',
);
$pending_registration = isset($_SESSION['shoestagram_pending_registration']) && is_array($_SESSION['shoestagram_pending_registration'])
    ? $_SESSION['shoestagram_pending_registration']
    : array();

if (($pending_registration['delivery_mode'] ?? '') === 'local') {
    unset($_SESSION['shoestagram_pending_registration']);
    $pending_registration = array();
}

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif (($_SESSION['user_role'] ?? '') === 'staff') {
        header('Location: ../staff/dashboard.php');
    } else {
        header('Location: ../customer/index.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $register_action = trim((string) ($_POST['register_action'] ?? 'start_registration'));

    if ($register_action === 'cancel_registration') {
        unset($_SESSION['shoestagram_pending_registration']);
        header('Location: register.php');
        exit();
    }

    if ($register_action === 'resend_code') {
        if (empty($pending_registration)) {
            $error = 'Start registration again so we know where to send the code.';
        } else {
            $code = shoestagram_generate_verification_code();
            $delivery_ok = register_send_verification_code($pending_registration['email'], $pending_registration['fullname'], $code);

            if ($delivery_ok) {
                $pending_registration = shoestagram_add_pending_verification_code($pending_registration, $code);
                $_SESSION['shoestagram_pending_registration'] = $pending_registration;
                $success = 'A fresh verification code was sent to ' . $pending_registration['email'] . '.';
            } else {
                $error = register_mail_failure_message();
            }
        }
    } elseif ($register_action === 'verify_registration') {
        $verification_code = shoestagram_normalize_verification_code($_POST['verification_code'] ?? '');

        if (empty($pending_registration)) {
            $error = 'Start registration again so we can verify your email first.';
        } elseif ($verification_code === '') {
            $error = 'Enter the 6-digit verification code first.';
        } elseif (!shoestagram_pending_has_active_verification_code($pending_registration)) {
            $error = 'That verification code already expired. Request a new one below.';
        } elseif (!shoestagram_pending_verification_code_matches($pending_registration, $verification_code)) {
            $error = 'That verification code did not match.';
        } else {
            $result = database_create_user_record($conn, array(
                'fullname' => $pending_registration['fullname'] ?? '',
                'email' => $pending_registration['email'] ?? '',
                'password' => $pending_registration['password'] ?? '',
                'role' => 'customer',
            ));

            if (!empty($result['ok'])) {
                $created_user = database_get_user_by_id($conn, (int) ($result['user_id'] ?? 0));
                database_log_activity(
                    $conn,
                    $created_user ?: array(
                        'name' => (string) ($pending_registration['fullname'] ?? ''),
                        'email' => (string) ($pending_registration['email'] ?? ''),
                        'role' => 'customer',
                    ),
                    'Account',
                    'Customer account created after email verification.',
                    'Success'
                );
                $profile = isset($pending_registration['profile']) && is_array($pending_registration['profile'])
                    ? $pending_registration['profile']
                    : array();

                if (!empty($profile)) {
                    store_state_save_user_profile($pending_registration['email'] ?? '', $profile);
                }

                unset($_SESSION['shoestagram_pending_registration']);
                $pending_registration = array();
                $success = 'Account created successfully. You can sign in now and see recommendations shaped by your preferences.';
                $fullname_value = '';
                $email_value = '';
                $profile_value = register_profile_from_post(array());
            } else {
                $error = $result['message'] ?? 'Registration failed. Please try again.';
            }
        }
    } else {
        $fullname_value = trim((string) ($_POST['fullname'] ?? ''));
        $email_value = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $password_error = database_validate_password_strength($password);
        $profile_value = register_profile_from_post($_POST);

        if ($fullname_value === '' || $email_value === '' || $password === '' || $confirm_password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email_value, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif ($password_error !== '') {
            $error = $password_error;
        } elseif (database_get_user_by_email($conn, $email_value)) {
            $error = 'That email is already registered. Please sign in instead.';
        } elseif (empty($profile_value['categories'])) {
            $error = 'Choose at least one category so your account has useful recommendations.';
        } elseif ($profile_value['shoe_size'] === '' && $profile_value['apparel_size'] === '') {
            $error = 'Choose at least one size so suggested products can fit you.';
        } elseif (empty($profile_value['brands']) && empty($profile_value['styles']) && $profile_value['budget_max'] === '') {
            $error = 'Choose at least one brand, style, or budget preference.';
        } else {
            $code = shoestagram_generate_verification_code();
            $delivery_ok = register_send_verification_code($email_value, $fullname_value, $code);

            if ($delivery_ok) {
                $pending_registration = shoestagram_add_pending_verification_code(array(
                    'fullname' => $fullname_value,
                    'email' => $email_value,
                    'password' => $password,
                    'profile' => $profile_value,
                ), $code);
                $_SESSION['shoestagram_pending_registration'] = $pending_registration;
                $success = 'We sent a 6-digit verification code to ' . $email_value . '. Enter it below to finish creating your account.';
            } else {
                unset($_SESSION['shoestagram_pending_registration']);
                $pending_registration = array();
                $error = register_mail_failure_message();
            }
        }
    }
}

$pending_registration = isset($_SESSION['shoestagram_pending_registration']) && is_array($_SESSION['shoestagram_pending_registration'])
    ? $_SESSION['shoestagram_pending_registration']
    : array();
if (($pending_registration['delivery_mode'] ?? '') === 'local') {
    unset($_SESSION['shoestagram_pending_registration']);
    $pending_registration = array();
}
$fullname_value = $fullname_value !== '' ? $fullname_value : (string) ($pending_registration['fullname'] ?? '');
$email_value = $email_value !== '' ? $email_value : (string) ($pending_registration['email'] ?? '');
$profile_value = !empty($profile_value['categories']) || !empty($profile_value['brands']) || !empty($profile_value['styles'])
    ? $profile_value
    : (isset($pending_registration['profile']) && is_array($pending_registration['profile']) ? array_merge($profile_value, $pending_registration['profile']) : $profile_value);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1018">
    <title>Shoestagram | Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../store-theme.css?v=auth-20260908">
</head>
<body class="auth-body auth-register-page">
    <div class="bg-mesh bg-mesh-left"></div>
    <div class="bg-mesh bg-mesh-right"></div>
    <div class="noise-layer"></div>

    <main class="auth-shell" data-auth-page>
        <div class="auth-grid">
            <section class="auth-showcase" aria-labelledby="register-brand-heading">
                <a href="../customer/index.php" class="brand auth-brand">
                    <span class="brand-mark">
                        <img src="../customer/shoelogo.jpg" alt="Shoestagram logo">
                    </span>
                    <span class="brand-copy">
                        <strong>Shoestagram</strong>
                        <span>Curated footwear & apparel</span>
                    </span>
                </a>

                <div class="auth-showcase-copy">
                    <span class="auth-kicker"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Built around your style</span>
                    <h1 id="register-brand-heading" class="display-font">Find your fit.<br><em>Own your rotation.</em></h1>
                    <p>Create a profile once, then discover footwear and apparel shaped around your size, budget, and taste.</p>
                </div>

                <figure class="auth-visual">
                    <img src="../products/onitsukatigeryellow.jpeg" alt="Yellow sneakers from the Shoestagram collection" class="auth-visual-image">
                    <figcaption class="auth-visual-caption">
                        <span>Your profile, your picks</span>
                        <strong>Recommendations that fit.</strong>
                    </figcaption>
                    <div class="auth-visual-badge auth-visual-badge-light" aria-hidden="true">
                        <i class="fas fa-heart"></i>
                        <span>Save your<br>favorites</span>
                    </div>
                </figure>

                <div class="auth-showcase-footer">
                    <span>Personalized picks</span><span>Easy reservations</span><span>Order tracking</span>
                </div>
            </section>

            <section class="auth-card" aria-labelledby="register-heading">
                <a href="../customer/index.php" class="auth-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to store</a>

                <header class="auth-card-head">
                    <span class="eyebrow"><?php echo !empty($pending_registration) ? 'Email verification' : 'Create account'; ?></span>
                    <h2 id="register-heading" class="display-font"><?php echo !empty($pending_registration) ? 'Check your inbox.' : 'Build your profile.'; ?></h2>
                    <p><?php echo !empty($pending_registration) ? 'Enter your secure code to finish creating your Shoestagram account.' : 'Tell us the essentials, then tune your recommendations to your style.'; ?></p>
                </header>

                <ol class="auth-stepper" aria-label="Registration progress">
                    <li class="is-active"><span>1</span> Profile</li>
                    <li<?php echo !empty($pending_registration) ? ' class="is-active"' : ''; ?>><span>2</span> Verify</li>
                </ol>

                <?php if ($error !== ''): ?>
                    <div class="flash flash-error auth-alert" role="alert" aria-live="assertive">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="flash flash-success auth-alert" role="status" aria-live="polite">
                        <i class="fas fa-circle-check"></i>
                        <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($warning !== ''): ?>
                    <div class="flash flash-warning auth-alert" role="status" aria-live="polite">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pending_registration)): ?>
                    <div class="verification-box">
                        <span class="verification-icon"><i class="fas fa-envelope-open-text" aria-hidden="true"></i></span>
                        <div>
                            <strong>Verify your email</strong>
                            <small>Enter the 6-digit code sent to <?php echo htmlspecialchars((string) ($pending_registration['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>. It expires in 15 minutes.</small>
                        </div>
                    </div>

                    <form method="POST" class="auth-form">
                        <input type="hidden" name="register_action" value="verify_registration">
                        <div class="field">
                            <label for="verification_code">Email verification code</label>
                            <input type="text" id="verification_code" name="verification_code" class="auth-code-input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
                        </div>
                        <div class="auth-action-stack">
                            <button type="submit" class="button button-dark auth-submit">Verify and create account <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                            <div class="auth-secondary-actions">
                                <button type="submit" name="register_action" value="resend_code" class="auth-text-action">Resend code</button>
                                <span aria-hidden="true">&bull;</span>
                                <button type="submit" name="register_action" value="cancel_registration" class="auth-text-action">Start over</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="register_action" value="start_registration">
                        <section class="auth-form-section" aria-labelledby="account-details-heading">
                            <header class="auth-form-section-head">
                                <span>01</span>
                                <div>
                                    <h3 id="account-details-heading">Account details</h3>
                                    <p>The basics we need to create your account.</p>
                                </div>
                            </header>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="fullname">Full name</label>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($fullname_value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your full name" autocomplete="name" required>
                                </div>

                                <div class="field">
                                    <label for="email">Email address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com" autocomplete="email" inputmode="email" required>
                                </div>
                            </div>
                        </section>

                        <section class="auth-form-section" aria-labelledby="account-security-heading">
                            <header class="auth-form-section-head">
                                <span>02</span>
                                <div>
                                    <h3 id="account-security-heading">Secure your account</h3>
                                    <p>Use a unique password you do not use elsewhere.</p>
                                </div>
                            </header>
                            <div class="form-grid password-pair-grid">
                                <div class="field">
                                    <label for="password">Password</label>
                                    <div class="password-wrap">
                                        <input type="password" id="password" name="password" placeholder="Create a strong password" autocomplete="new-password" data-password-strength aria-describedby="password-feedback" required>
                                        <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <small id="password-feedback" class="password-note" data-password-feedback><?php echo htmlspecialchars($password_rules, ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>

                                <div class="field">
                                    <label for="confirm_password">Confirm password</label>
                                    <div class="password-wrap">
                                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" autocomplete="new-password" required>
                                        <button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Show password" aria-pressed="false">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="auth-form-section" aria-labelledby="account-preferences-heading">
                            <header class="auth-form-section-head">
                                <span>03</span>
                                <div>
                                    <h3 id="account-preferences-heading">Tune your recommendations</h3>
                                    <p>Choose enough detail for Shoestagram to build a useful starting feed.</p>
                                </div>
                            </header>

                            <div class="auth-preference-grid">
                                <div class="field auth-preference-field">
                                    <label>Preferred brands</label>
                                    <details class="multi-choice-select">
                                        <summary>
                                            <span>Choose brands</span>
                                            <small><?php echo count($profile_value['brands']); ?> selected</small>
                                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                        </summary>
                                        <div class="choice-grid auth-choice-grid multi-choice-panel">
                                            <?php foreach ($brands as $brand): ?>
                                                <label class="choice-card">
                                                    <input type="checkbox" name="brands[]" value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($brand, $profile_value['brands'], true) ? ' checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                    <small class="field-note">Optional if you choose a style or budget.</small>
                                </div>

                                <div class="field auth-preference-field">
                                    <label>What do you usually buy?</label>
                                    <details class="multi-choice-select">
                                        <summary>
                                            <span>Choose categories</span>
                                            <small><?php echo count($profile_value['categories']); ?> selected</small>
                                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                        </summary>
                                        <div class="choice-grid auth-choice-grid multi-choice-panel">
                                            <?php foreach ($categories as $key => $label): ?>
                                                <?php if ($key === 'all') { continue; } ?>
                                                <label class="choice-card">
                                                    <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($key, $profile_value['categories'], true) ? ' checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                    <small class="field-note auth-field-note-spacer" aria-hidden="true">&nbsp;</small>
                                </div>

                                <div class="field auth-preference-field auth-preference-span">
                                    <label>Preferred styles</label>
                                    <details class="multi-choice-select">
                                        <summary>
                                            <span>Choose styles</span>
                                            <small><?php echo count($profile_value['styles']); ?> selected</small>
                                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                        </summary>
                                        <div class="choice-grid auth-choice-grid multi-choice-panel">
                                            <?php foreach ($style_options as $style): ?>
                                                <label class="choice-card">
                                                    <input type="checkbox" name="styles[]" value="<?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($style, $profile_value['styles'], true) ? ' checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                    <small class="field-note auth-field-note-spacer" aria-hidden="true">&nbsp;</small>
                                </div>
                            </div>

                            <div class="form-grid auth-sizing-grid">
                                <div class="field">
                                    <label for="shoe_size">Shoe size</label>
                                    <select id="shoe_size" name="shoe_size">
                                        <option value="">Select shoe size</option>
                                        <?php foreach ($shoe_sizes as $size): ?>
                                            <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $profile_value['shoe_size'] === $size ? ' selected' : ''; ?>><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="apparel_size">Apparel size</label>
                                    <select id="apparel_size" name="apparel_size">
                                        <option value="">Select apparel size</option>
                                        <?php foreach ($apparel_sizes as $size): ?>
                                            <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $profile_value['apparel_size'] === $size ? ' selected' : ''; ?>><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="budget_max">Budget ceiling</label>
                                    <select id="budget_max" name="budget_max">
                                        <option value="">Any price</option>
                                        <?php foreach ($budget_options as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $profile_value['budget_max'] === $value ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <button type="submit" class="button button-dark auth-submit">Send verification code <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    </form>
                <?php endif; ?>

                <p class="auth-account-switch">
                    Already have an account? <a href="login.php" data-auth-transition>Sign in</a>
                </p>
            </section>
        </div>
    </main>

    <script src="auth-ui.js?v=auth-20260908"></script>
</body>
</html>
