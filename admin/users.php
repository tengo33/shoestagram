<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));
$edit_id = (int) ($_GET['edit'] ?? 0);
$password_rules = database_password_requirements_text();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the user action again.');
        header('Location: users.php');
        exit();
    }

    $user_action = trim((string) ($_POST['user_action'] ?? ''));
    $requested_user_id = (int) ($_POST['target_user_id'] ?? 0);
    if ($requested_user_id > 0) {
        $requested_user = database_get_user_by_id($conn, $requested_user_id);
        if (!$requested_user || (string) ($requested_user['role'] ?? '') !== 'staff') {
            storefront_set_flash('error', 'Only Staff accounts can be managed here.');
            header('Location: users.php');
            exit();
        }
    }

    if ($user_action === 'save_user') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $target_before = $target_user_id > 0 ? database_get_user_by_id($conn, $target_user_id) : null;

        if ($target_user_id > 0) {
            $result = database_update_user_record($conn, $target_user_id, array(
                'fullname' => $_POST['fullname'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => 'staff',
                'phone' => $_POST['phone'] ?? '',
                'address' => $_POST['address'] ?? '',
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            ));

            if (!empty($result['ok'])) {
                store_state_move_user_profile($result['old_email'] ?? '', $result['new_email'] ?? '');
                $target_after = database_get_user_by_id($conn, $target_user_id);
                $status_change = $target_before && $target_after && (int) $target_before['is_active'] !== (int) $target_after['is_active']
                    ? ' Access changed from ' . ((int) $target_before['is_active'] === 1 ? 'active' : 'inactive') . ' to ' . ((int) $target_after['is_active'] === 1 ? 'active' : 'inactive') . '.'
                    : '';
                database_log_activity(
                    $conn,
                    database_activity_actor_from_session(),
                    'Account',
                    'Updated user account ' . (string) ($target_after['email'] ?? $result['new_email'] ?? ('#' . $target_user_id)) . '.' . $status_change,
                    'Success'
                );
            }
        } else {
            $result = database_create_user_record($conn, array(
                'fullname' => $_POST['fullname'] ?? '',
                'email' => $_POST['email'] ?? '',
                'password' => $_POST['password'] ?? '',
                'role' => 'staff',
                'phone' => $_POST['phone'] ?? '',
                'address' => $_POST['address'] ?? '',
            ));

            if (!empty($result['ok'])) {
                database_log_activity(
                    $conn,
                    database_activity_actor_from_session(),
                    'Account',
                    'Created staff account ' . strtolower(trim((string) ($_POST['email'] ?? ''))) . '.',
                    'Success'
                );
            }
        }

        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: users.php');
        exit();
    }

    if ($user_action === 'delete_user') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $target_user = database_get_user_by_id($conn, $target_user_id);

        if ($target_user_id === (int) ($_SESSION['user_id'] ?? 0)) {
            storefront_set_flash('error', 'You cannot delete the currently signed-in admin account.');
        } elseif (database_delete_user_record($conn, $target_user_id)) {
            database_log_activity(
                $conn,
                database_activity_actor_from_session(),
                'Account',
                'Deleted user account ' . (string) ($target_user['email'] ?? ('#' . $target_user_id)) . '.',
                'Success'
            );
            storefront_set_flash('success', 'Staff account deleted successfully.');
        } else {
            storefront_set_flash('error', 'Staff account could not be deleted.');
        }

        header('Location: users.php');
        exit();
    }

    if ($user_action === 'archive_user') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $target_user = database_get_user_by_id($conn, $target_user_id);

        if ($target_user_id === (int) ($_SESSION['user_id'] ?? 0)) {
            storefront_set_flash('error', 'You cannot archive the currently signed-in admin account.');
        } else {
            $result = database_archive_user_record($conn, $target_user_id);
            if (!empty($result['ok'])) {
                database_log_activity(
                    $conn,
                    database_activity_actor_from_session(),
                    'Account',
                    'Archived user account ' . (string) ($target_user['email'] ?? ('#' . $target_user_id)) . '.',
                    'Success'
                );
            }
            storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        }

        header('Location: users.php');
        exit();
    }
}

$stats = backoffice_get_stats($conn);
$user_to_edit = $edit_id > 0 ? database_get_user_by_id($conn, $edit_id) : null;
if ($user_to_edit && (string) ($user_to_edit['role'] ?? '') !== 'staff') {
    $user_to_edit = null;
}
$staff_users = array_values(array_filter($stats['users'], function ($user) {
    return (string) ($user['role'] ?? '') === 'staff';
}));
$staff_total = count($staff_users);
$active_staff = count(array_filter($staff_users, function ($user) {
    return (int) ($user['is_active'] ?? 0) === 1 && !database_user_is_archived($user);
}));
$staff_pagination = backoffice_paginate_rows($staff_users);

backoffice_render_head('Shoestagram | Admin Users');
backoffice_render_shell_start(
    'admin',
    'users',
    'Staff management',
    'Create and manage staff accounts without changing customer or admin access.',
    'Admin profile settings remain separate from staff account management.',
    array(
        array('href' => 'users.php?form=add#staffForm', 'label' => 'Add staff', 'class' => 'button-light'),
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-dark'),
    )
);
?>
                <div class="metrics-grid">
                    <article class="metric-card surface-card">
                        <span class="meta-label">Staff accounts</span>
                        <strong><?php echo $staff_total; ?></strong>
                        <span>Operations accounts in this management area.</span>
                    </article>
                    <article class="metric-card surface-card">
                        <span class="meta-label">Active staff</span>
                        <strong><?php echo $active_staff; ?></strong>
                        <span>Staff currently allowed to sign in.</span>
                    </article>
                </div>

                <details class="admin-card surface-card admin-form-panel" id="staffForm"<?php echo $user_to_edit || isset($_GET['form']) ? ' open' : ''; ?>>
                    <summary class="admin-form-summary"><span class="eyebrow"><?php echo $user_to_edit ? 'Edit staff' : 'Add staff'; ?></span><strong><?php echo $user_to_edit ? 'Update staff access and details' : 'Create a new staff account'; ?></strong></summary>
                    <form method="POST" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="user_action" value="save_user">
                        <input type="hidden" name="target_user_id" value="<?php echo (int) ($user_to_edit['id'] ?? 0); ?>">
                        <div class="form-grid">
                            <div class="field">
                                <label for="fullname">Full name</label>
                                <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars((string) ($user_to_edit['fullname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars((string) ($user_to_edit['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="field">
                                <label>Role</label>
                                <span>Staff</span>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars((string) ($user_to_edit['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars((string) ($user_to_edit['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <?php if (!$user_to_edit): ?>
                                <div class="field">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" required>
                                    <small class="password-note"><?php echo htmlspecialchars($password_rules, ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="field">
                                    <label for="is_active">Status</label>
                                    <select id="is_active" name="is_active">
                                        <option value="1"<?php echo (int) ($user_to_edit['is_active'] ?? 1) === 1 && !database_user_is_archived($user_to_edit) ? ' selected' : ''; ?>>Active</option>
                                        <option value="0"<?php echo ((int) ($user_to_edit['is_active'] ?? 1) === 0 || database_user_is_archived($user_to_edit)) ? ' selected' : ''; ?>><?php echo database_user_is_archived($user_to_edit) ? 'Archived / inactive' : 'Inactive'; ?></option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark"><?php echo $user_to_edit ? 'Update staff' : 'Create staff'; ?></button>
                            <?php if ($user_to_edit): ?>
                                <a href="users.php" class="button button-light">Cancel edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </details>

                <article class="table-card surface-card" id="staffRecords">
                    <span class="eyebrow">Staff list</span>
                    <h2 class="display-font">Current staff accounts and access.</h2>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last updated</th>
                                    <th>Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staff_users)): ?><tr><td colspan="7">No staff accounts have been created yet.</td></tr><?php endif; ?>
                                <?php foreach ($staff_users as $user): ?>
                                    <?php
                                    $is_archived = database_user_is_archived($user);
                                    $can_archive = database_user_archive_is_eligible($user, 3);
                                    $status_label = $is_archived ? 'Archived' : ((int) $user['is_active'] === 1 ? 'Active' : 'Inactive');
                                    $status_class = $is_archived ? 'cancelled' : ((int) $user['is_active'] === 1 ? 'completed' : 'pending');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(backoffice_role_label($user['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="status-pill status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <?php $updated_at = backoffice_manila_datetime($user['updated_at'] ?? ''); $joined_at = backoffice_manila_datetime($user['created_at'] ?? ''); ?>
                                        <td><?php echo $updated_at ? htmlspecialchars($updated_at->format('M j, Y'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></td>
                                        <td><details class="table-details"><summary>View details</summary><div><p><strong>Phone:</strong> <?php echo htmlspecialchars((string) (($user['phone'] ?? '') !== '' ? $user['phone'] : 'Not recorded'), ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Joined:</strong> <?php echo $joined_at ? htmlspecialchars($joined_at->format('M j, Y'), ENT_QUOTES, 'UTF-8') : 'Date unavailable'; ?></p></div></details></td>
                                        <td>
                                            <div class="button-row">
                                                <a href="users.php?edit=<?php echo (int) $user['id']; ?>#staffForm" class="button button-light">Edit</a>
                                                <?php if ($can_archive): ?>
                                                    <form method="POST" class="inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="user_action" value="archive_user">
                                                        <input type="hidden" name="target_user_id" value="<?php echo (int) $user['id']; ?>">
                                                        <button type="submit" class="button button-light">Archive</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="user_action" value="delete_user">
                                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $user['id']; ?>">
                                                    <button type="submit" class="button button-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php backoffice_render_pagination($staff_pagination, 'staffRecords'); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
