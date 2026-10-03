<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('staff'));
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$password_rules = database_password_requirements_text();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the profile action again.');
        header('Location: profile.php');
        exit();
    }

    $profile_action = trim((string) ($_POST['profile_action'] ?? ''));

    if ($profile_action === 'details') {
        $result = database_update_user_record($conn, $user_id, array(
            'fullname' => $_POST['fullname'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'staff',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'is_active' => 1,
        ));

        if (!empty($result['ok'])) {
            store_state_move_user_profile($result['old_email'] ?? '', $result['new_email'] ?? '');
            database_sync_session_user($conn, $user_id);
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Account',
                'Staff profile details updated.',
                'Success'
            );
        }

        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: profile.php');
        exit();
    }

    if ($profile_action === 'password') {
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        if ($new_password !== $confirm_password) {
            storefront_set_flash('error', 'The new passwords did not match.');
            header('Location: profile.php');
            exit();
        }

        $result = database_update_user_password($conn, $user_id, $_POST['current_password'] ?? '', $new_password, true);
        if (!empty($result['ok'])) {
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Account',
                'Password changed from the staff profile.',
                'Success'
            );
        }
        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: profile.php');
        exit();
    }
}

$user = database_get_user_by_id($conn, $user_id);

backoffice_render_head('Shoestagram | Staff Profile');
backoffice_render_shell_start(
    'staff',
    'profile',
    'Profile',
    'Update personal details and change your password.',
    'Staff profile tools stay inside the workspace so account maintenance does not require leaving the operations panel.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to dashboard', 'class' => 'button-light'),
        array('href' => '../login/logout.php', 'label' => 'Logout', 'class' => 'button-dark'),
    )
);
?>
                <div class="split-showcase">
                    <article class="surface-card split-panel">
                        <div class="section-head compact-head">
                            <div>
                                <span class="eyebrow">Personal details</span>
                                <h2 class="display-font section-title">Update staff account information.</h2>
                            </div>
                        </div>
                        <form method="POST" action="profile.php" class="contact-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="profile_action" value="details">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="fullname">Full name</label>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars((string) ($user['fullname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="field">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="phone">Phone</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars((string) ($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="field">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars((string) ($user['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div class="button-row">
                                <button type="submit" class="button button-dark">Save details</button>
                            </div>
                        </form>
                    </article>

                    <article class="surface-card split-panel">
                        <div class="section-head compact-head">
                            <div>
                                <span class="eyebrow">Password</span>
                                <h2 class="display-font section-title">Change your password.</h2>
                            </div>
                        </div>
                        <form method="POST" action="profile.php" class="contact-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="profile_action" value="password">
                            <div class="field">
                                <label for="current_password">Current password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-grid password-pair-grid">
                                <div class="field">
                                    <label for="new_password">New password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                    <small class="password-note"><?php echo htmlspecialchars($password_rules, ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                                <div class="field">
                                    <label for="confirm_password">Confirm new password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            <div class="button-row">
                                <button type="submit" class="button button-dark">Update password</button>
                            </div>
                        </form>
                    </article>
                </div>
<?php backoffice_render_shell_end(); ?>
