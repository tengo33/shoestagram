<?php
require_once '../database/config.php';
require_once '../database/store_state.php';

function auth_safe_redirect($value, $fallback)
{
    $value = trim((string) $value);

    if ($value === '' || strpos($value, '://') !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
        return $fallback;
    }

    if (strpos($value, '../') !== 0) {
        $value = '../customer/' . ltrim($value, '/');
    }

    return $value;
}

$error = '';
$redirect_target = auth_safe_redirect($_POST['redirect'] ?? ($_GET['redirect'] ?? '../customer/index.php'), '../customer/index.php');
$email_value = isset($_COOKIE['shoestagram_remember_email']) ? trim((string) $_COOKIE['shoestagram_remember_email']) : '';

if ($email_value === '' && isset($_COOKIE['shoestagram_admin_email'])) {
    $email_value = trim((string) $_COOKIE['shoestagram_admin_email']);
}

if ($email_value === '' && isset($_COOKIE['shoestagram_staff_email'])) {
    $email_value = trim((string) $_COOKIE['shoestagram_staff_email']);
}
$flash_messages = isset($_SESSION['shoestagram_flash']) && is_array($_SESSION['shoestagram_flash']) ? $_SESSION['shoestagram_flash'] : array();
unset($_SESSION['shoestagram_flash']);

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif (($_SESSION['user_role'] ?? '') === 'staff') {
        header('Location: ../staff/dashboard.php');
    } else {
        $existing_profile = isset($_SESSION['user_email']) ? store_state_get_user_profile($_SESSION['user_email']) : array();
        if (!store_state_profile_is_complete($existing_profile)) {
            header('Location: ../customer/preferences.php?welcome=1');
            exit();
        }
        header('Location: ' . $redirect_target);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_value = trim((string) ($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember_me = !empty($_POST['remember_me']);

    if ($email_value === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $user_data = database_get_login_user($conn, $email_value);

        if ($user_data) {
            $lock_remaining = database_login_lock_remaining($user_data);
            $last_failed_at = strtotime((string) ($user_data['last_failed_login'] ?? ''));

            if ($lock_remaining <= 0 && (
                trim((string) ($user_data['locked_until'] ?? '')) !== ''
                || ($last_failed_at && (time() - $last_failed_at) >= database_login_lock_seconds())
            )) {
                database_reset_login_attempts($conn, (int) $user_data['id']);
                $user_data['failed_login_attempts'] = 0;
                $user_data['last_failed_login'] = null;
                $user_data['locked_until'] = null;
            }

            if ($lock_remaining > 0) {
                $minutes_remaining = max(1, (int) ceil($lock_remaining / 60));
                $error = 'Too many failed login attempts. Please try again in ' . $minutes_remaining . ' minute' . ($minutes_remaining === 1 ? '' : 's') . '.';
            } elseif (
                trim((string) ($user_data['archived_at'] ?? '')) === ''
                && (int) $user_data['is_active'] === 1
                && password_verify($password, (string) $user_data['password'])
            ) {
                database_reset_login_attempts($conn, (int) $user_data['id']);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_data['id'];
                $_SESSION['user_name'] = $user_data['fullname'];
                $_SESSION['user_email'] = $user_data['email'];
                $_SESSION['user_role'] = $user_data['role'];

                database_log_activity(
                    $conn,
                    $user_data,
                    'Login',
                    'Successful login.',
                    'Success'
                );

                if ($remember_me) {
                    setcookie('shoestagram_remember_email', $email_value, time() + (60 * 60 * 24 * 30), '/');
                } else {
                    setcookie('shoestagram_remember_email', '', time() - 3600, '/');
                }

                if ($user_data['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                } elseif ($user_data['role'] === 'staff') {
                    header('Location: ../staff/dashboard.php');
                } else {
                    if (strpos($redirect_target, '../admin/') === 0 || strpos($redirect_target, '../staff/') === 0) {
                        $redirect_target = '../customer/index.php';
                    }

                    $profile = store_state_get_user_profile($user_data['email']);

                    if (!store_state_profile_is_complete($profile)) {
                        $redirect_target = '../customer/preferences.php?welcome=1';
                    }

                    header('Location: ' . $redirect_target);
                }
                exit();
            } else {
                $attempt = database_record_failed_login($conn, $user_data);
                database_log_activity(
                    $conn,
                    $user_data,
                    'Failed Login',
                    'Failed login attempt.',
                    'Failed'
                );

                if (!empty($attempt['locked'])) {
                    database_log_activity(
                        $conn,
                        $user_data,
                        'Login Lockout',
                        'Temporary login lockout started after 5 consecutive failed attempts.',
                        'Locked'
                    );
                    $error = 'Too many failed login attempts. Please try again in 5 minutes.';
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } else {
            /* Keep unknown-account failures closer to the cost of a real password check. */
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
            database_log_activity(
                $conn,
                array(),
                'Failed Login',
                'Failed login attempt.',
                'Failed',
                $email_value
            );
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1018">
    <title>Shoestagram | Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../store-theme.css?v=auth-20260908">
</head>
<body class="auth-body auth-login-page">
    <div class="bg-mesh bg-mesh-left"></div>
    <div class="bg-mesh bg-mesh-right"></div>
    <div class="noise-layer"></div>

    <main class="auth-shell" data-auth-page>
        <div class="auth-grid">
            <section class="auth-showcase" aria-labelledby="auth-brand-heading">
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
                    <span class="auth-kicker"><i class="fas fa-bolt" aria-hidden="true"></i> Your next rotation starts here</span>
                    <h1 id="auth-brand-heading" class="display-font">Step into<br><em>your style.</em></h1>
                    <p>Discover standout pairs, everyday essentials, and fresh streetwear selected for the way you move.</p>
                </div>

                <figure class="auth-visual">
                    <img src="../products/newbalance530.jpeg" alt="Curated sneakers on the Shoestagram display" class="auth-visual-image">
                    <figcaption class="auth-visual-caption">
                        <span>Fresh selection</span>
                        <strong>Made for your rotation.</strong>
                    </figcaption>
                    <div class="auth-visual-badge" aria-hidden="true">
                        <i class="fas fa-arrow-trend-up"></i>
                        <span>Curated<br>daily</span>
                    </div>
                </figure>

                <div class="auth-showcase-footer">
                    <span>Footwear</span><span>Apparel</span><span>Personal picks</span>
                </div>
            </section>

            <section class="auth-card" aria-labelledby="login-heading">
                <a href="../customer/index.php" class="auth-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to store</a>

                <header class="auth-card-head">
                    <span class="eyebrow">Member access</span>
                    <h2 id="login-heading" class="display-font">Welcome back.</h2>
                    <p>Sign in with your Shoestagram account to continue.</p>
                </header>

                <?php if ($error !== ''): ?>
                    <div class="flash flash-error auth-alert" role="alert" aria-live="assertive">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php foreach ($flash_messages as $message): ?>
                    <div class="flash flash-<?php echo htmlspecialchars($message['type'], ENT_QUOTES, 'UTF-8'); ?> auth-alert" role="status" aria-live="polite">
                        <i class="fas <?php echo $message['type'] === 'success' ? 'fa-circle-check' : ($message['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info'); ?>"></i>
                        <span><?php echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>

                <form method="POST" class="auth-form">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="field">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com" autocomplete="email" inputmode="email" required>
                    </div>

                    <div class="field">
                        <div class="auth-field-label-row">
                            <label for="password">Password</label>
                            <a href="forgot-password.php">Forgot password?</a>
                        </div>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <label class="auth-check">
                        <input type="checkbox" name="remember_me">
                        <span class="auth-check-control" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <span>Remember my email on this device</span>
                    </label>

                    <button type="submit" class="button button-dark auth-submit">
                        <span>Sign in</span><i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="auth-trust-line">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <span>One secure sign-in. Your account opens the right Shoestagram experience automatically.</span>
                </div>

                <p class="auth-account-switch">New to Shoestagram? <a href="register.php" data-auth-transition>Create an account</a></p>
            </section>
        </div>
    </main>

    <script src="auth-ui.js?v=auth-20260908"></script>
</body>
</html>
