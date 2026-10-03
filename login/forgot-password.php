<?php
require_once '../database/config.php';

function forgot_send_password_reset_code($email, $fullname, $code)
{
    $subject = 'Shoestagram password reset code';
    $message = "Hi " . $fullname . ",\n\n"
        . "Use this code to confirm your Shoestagram password reset:\n\n"
        . $code . "\n\n"
        . "The code expires in 15 minutes. If you did not request this reset, ignore this email.\n";

    return shoestagram_send_email($email, $subject, $message);
}

function forgot_mail_failure_message()
{
    return shoestagram_mail_delivery_error('Gmail SMTP did not send the password reset code. Please check the Gmail sender settings and try again.');
}

$error = '';
$success = '';
$warning = '';
$email_value = '';
$password_rules = database_password_requirements_text();
$pending_reset = isset($_SESSION['shoestagram_pending_password_reset']) && is_array($_SESSION['shoestagram_pending_password_reset'])
    ? $_SESSION['shoestagram_pending_password_reset']
    : array();

if (($pending_reset['delivery_mode'] ?? '') === 'local') {
    unset($_SESSION['shoestagram_pending_password_reset']);
    $pending_reset = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reset_action = trim((string) ($_POST['reset_action'] ?? 'request_code'));

    if ($reset_action === 'cancel_reset') {
        unset($_SESSION['shoestagram_pending_password_reset']);
        header('Location: forgot-password.php');
        exit();
    }

    if ($reset_action === 'request_code' || $reset_action === 'resend_code') {
        $email_value = $reset_action === 'resend_code'
            ? (string) ($pending_reset['email'] ?? '')
            : strtolower(trim((string) ($_POST['email'] ?? '')));

        if ($email_value === '' || !filter_var($email_value, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = database_get_user_by_email($conn, $email_value);

            if (!$user) {
                $error = 'No account is registered under that email address.';
            } elseif (database_user_is_archived($user) || (int) ($user['is_active'] ?? 0) !== 1) {
                $error = 'This account cannot reset its password right now. Please contact the store team.';
            } else {
                $code = shoestagram_generate_verification_code();
                $delivery_ok = forgot_send_password_reset_code($user['email'], $user['fullname'], $code);

                if ($delivery_ok) {
                    $pending_reset = array(
                        'user_id' => (int) $user['id'],
                        'fullname' => (string) $user['fullname'],
                        'email' => (string) $user['email'],
                    );
                    $pending_reset = shoestagram_add_pending_verification_code($pending_reset, $code);
                    $_SESSION['shoestagram_pending_password_reset'] = $pending_reset;
                    $success = ($reset_action === 'resend_code' ? 'A fresh confirmation code was sent to ' : 'We sent a 6-digit confirmation code to ') . $user['email'] . '. Enter it below before choosing a new password.';
                } else {
                    $error = forgot_mail_failure_message();
                }
            }
        }
    } elseif ($reset_action === 'confirm_reset') {
        $verification_code = shoestagram_normalize_verification_code($_POST['verification_code'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $password_error = database_validate_password_strength($new_password);

        if (empty($pending_reset)) {
            $error = 'Request a password reset code first.';
        } elseif ($verification_code === '') {
            $error = 'Enter the 6-digit confirmation code first.';
        } elseif (!shoestagram_pending_has_active_verification_code($pending_reset)) {
            $error = 'That confirmation code already expired. Request a new one below.';
        } elseif (!shoestagram_pending_verification_code_matches($pending_reset, $verification_code)) {
            $error = 'That confirmation code did not match.';
        } elseif ($new_password === '' || $confirm_password === '') {
            $error = 'Please fill in the new password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif ($password_error !== '') {
            $error = $password_error;
        } else {
            $result = database_update_user_password($conn, (int) ($pending_reset['user_id'] ?? 0), '', $new_password, false);

            if (!empty($result['ok'])) {
                $reset_user = database_get_user_by_id($conn, (int) ($pending_reset['user_id'] ?? 0));
                database_log_activity(
                    $conn,
                    $reset_user ?: array('email' => (string) ($pending_reset['email'] ?? '')),
                    'Account',
                    'Password reset completed through email verification.',
                    'Success'
                );
                unset($_SESSION['shoestagram_pending_password_reset']);
                $pending_reset = array();
                $email_value = '';
                $success = 'Password updated successfully. You can sign in with the new password now.';
            } else {
                $error = $result['message'] ?? 'Password reset failed. Please try again.';
            }
        }
    }
}

$pending_reset = isset($_SESSION['shoestagram_pending_password_reset']) && is_array($_SESSION['shoestagram_pending_password_reset'])
    ? $_SESSION['shoestagram_pending_password_reset']
    : $pending_reset;
if (($pending_reset['delivery_mode'] ?? '') === 'local') {
    unset($_SESSION['shoestagram_pending_password_reset']);
    $pending_reset = array();
}
$email_value = $email_value !== '' ? $email_value : (string) ($pending_reset['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1018">
    <title>Shoestagram | Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../store-theme.css">
</head>
<body class="auth-body">
    <div class="bg-mesh bg-mesh-left"></div>
    <div class="bg-mesh bg-mesh-right"></div>
    <div class="noise-layer"></div>

    <main class="auth-shell">
        <div class="auth-grid">
            <section class="auth-showcase surface-card reveal is-visible">
                <a href="../customer/index.php" class="brand">
                    <span class="brand-mark">
                        <img src="../customer/shoelogo.jpg" alt="Shoestagram logo">
                    </span>
                    <span class="brand-copy">
                        <strong>Shoestagram</strong>
                        <span>Password reset</span>
                    </span>
                </a>

                <span class="eyebrow" style="margin-top: 26px;">Access recovery</span>
                <h1 class="display-font">Reset a password without leaving the premium experience behind.</h1>
                <p>Password recovery now confirms account ownership first by sending a 6-digit code to the account email before any password can change.</p>

                <div class="auth-moodboard">
                    <div class="auth-tile">
                        <strong>Email confirmation</strong>
                        <span>A reset code is required before the password fields become useful.</span>
                    </div>
                    <div class="auth-tile">
                        <strong>Secure storage</strong>
                        <span>New passwords are hashed before being written back to the user table.</span>
                    </div>
                </div>
            </section>

            <section class="auth-card surface-card reveal is-visible">
                <span class="eyebrow">Forgot password</span>
                <h2 class="display-font">Set a new password.</h2>
                <p>Enter your email first. We will send a confirmation code before saving the new password.</p>

                <?php if ($error !== ''): ?>
                    <div class="flash flash-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="flash flash-success">
                        <i class="fas fa-circle-check"></i>
                        <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($warning !== ''): ?>
                    <div class="flash flash-warning">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (empty($pending_reset)): ?>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="reset_action" value="request_code">
                        <div class="field">
                            <label for="email">Email address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="hello@shoestagram.com" required>
                        </div>

                        <div class="button-row" style="margin-top: 8px;">
                            <button type="submit" class="button button-dark">Send confirmation code</button>
                            <a href="login.php" class="button button-light">Back to login</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="verification-box">
                        <strong>Confirm your email first</strong>
                        <small>Enter the 6-digit code sent to <?php echo htmlspecialchars((string) ($pending_reset['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, then choose your new password.</small>
                    </div>

                    <form method="POST" class="auth-form">
                        <input type="hidden" name="reset_action" value="confirm_reset">

                        <div class="field">
                            <label for="verification_code">Email confirmation code</label>
                            <input type="text" id="verification_code" name="verification_code" inputmode="numeric" maxlength="6" placeholder="Enter the 6-digit code" required>
                        </div>

                        <div class="field">
                            <label for="new_password">New password</label>
                            <div class="password-wrap">
                                <input type="password" id="new_password" name="new_password" placeholder="Create a stronger password" data-password-strength required>
                                <button type="button" class="password-toggle" data-password-toggle="new_password" aria-label="Show password" aria-pressed="false">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                            <small class="password-note" data-password-feedback><?php echo htmlspecialchars($password_rules, ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>

                        <div class="field">
                            <label for="confirm_password">Confirm new password</label>
                            <div class="password-wrap">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat the new password" required>
                                <button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Show password" aria-pressed="false">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>

                        <div class="button-row" style="margin-top: 8px;">
                            <button type="submit" class="button button-dark">Confirm and reset</button>
                            <button type="submit" name="reset_action" value="resend_code" class="button button-light">Resend code</button>
                            <button type="submit" name="reset_action" value="cancel_reset" class="button button-light">Start over</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="auth-ui.js"></script>
</body>
</html>
