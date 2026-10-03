<?php
require_once '../database/config.php';
require_once '../store-data.php';
require_once '../customer/storefront-layout.php';
require_once '../backoffice-layout.php';

backoffice_require_role(array('admin'));

$filters = array(
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'role' => strtolower(trim((string) ($_GET['role'] ?? ''))),
    'activity_type' => trim((string) ($_GET['activity_type'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
);
$activity_page = database_get_activity_log_page($conn, $filters, (int) ($_GET['page'] ?? 1), 10);

backoffice_render_head('Shoestagram | Activity Log');
backoffice_render_shell_start(
    'admin',
    'activity-log',
    'Activity log',
    'Review meaningful authentication, account, and operational activity across Shoestagram.',
    'This history is read-only. Passwords, verification codes, API keys, and payment secrets are never recorded.',
    array(
        array('href' => 'dashboard.php', 'label' => 'Back to overview', 'class' => 'button-light'),
    )
);
?>
                <article class="admin-card surface-card">
                    <span class="eyebrow">Filters</span>
                    <h2 class="display-font">Find security and business activity.</h2>
                    <form method="GET" class="contact-form">
                        <div class="activity-filter-grid">
                            <div class="field">
                                <label for="date_from">Date from</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="date_to">Date to</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label for="search">User, email, or detail</label>
                                <input type="search" id="search" name="search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search activity">
                            </div>
                            <div class="field">
                                <label for="role">Role</label>
                                <select id="role" name="role">
                                    <option value="">All roles</option>
                                    <?php foreach (array('admin' => 'Admin', 'staff' => 'Staff', 'customer' => 'Customer') as $role_value => $role_label): ?>
                                        <option value="<?php echo $role_value; ?>"<?php echo $filters['role'] === $role_value ? ' selected' : ''; ?>><?php echo $role_label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="activity_type">Activity</label>
                                <select id="activity_type" name="activity_type">
                                    <option value="">All activities</option>
                                    <?php foreach (database_activity_types() as $activity_type): ?>
                                        <option value="<?php echo htmlspecialchars($activity_type, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['activity_type'] === $activity_type ? ' selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity_type)), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="status">Result</label>
                                <select id="status" name="status">
                                    <option value="">All results</option>
                                    <?php foreach (array('Success', 'Failed', 'Locked', 'Info') as $status_option): ?>
                                        <option value="<?php echo $status_option; ?>"<?php echo ucfirst(strtolower($filters['status'])) === $status_option ? ' selected' : ''; ?>><?php echo $status_option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="button-row">
                            <button type="submit" class="button button-dark">Apply filters</button>
                            <a href="activity-log.php" class="button button-light">Clear</a>
                        </div>
                    </form>
                </article>

                <article class="table-card surface-card" id="activityRecords">
                    <div class="section-head compact-head">
                        <div>
                            <span class="eyebrow">Audit history</span>
                            <h2 class="display-font"><?php echo (int) $activity_page['total']; ?> meaningful event<?php echo (int) $activity_page['total'] === 1 ? '' : 's'; ?>.</h2>
                        </div>
                        <span class="status-pill status-completed">Newest first</span>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date &amp; time</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Activity</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activity_page['rows'])): ?>
                                    <tr><td colspan="6">No activity matches the selected filters.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($activity_page['rows'] as $activity): ?>
                                    <?php
                                    $activity_status = (string) ($activity['status'] ?? 'Info');
                                    $status_class = $activity_status === 'Success' ? 'completed' : ($activity_status === 'Failed' ? 'cancelled' : 'pending');
                                    $activity_time = backoffice_manila_datetime($activity['created_at'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?php echo $activity_time ? htmlspecialchars($activity_time->format('M j, Y'), ENT_QUOTES, 'UTF-8') . '<br><small>' . htmlspecialchars($activity_time->format('g:i A'), ENT_QUOTES, 'UTF-8') . '</small>' : 'Date unavailable'; ?></td>
                                        <td><?php echo htmlspecialchars((string) ($activity['user_name'] !== '' ? $activity['user_name'] : 'Unknown user'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($activity['user_role'] !== '' ? backoffice_role_label($activity['user_role']) : 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $activity['activity_type'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php $description = (string) ($activity['description'] ?? ''); ?>
                                        <td><details class="table-details"><summary>View details</summary><div><p><strong>Email:</strong> <?php echo htmlspecialchars((string) ($activity['user_email'] !== '' ? $activity['user_email'] : 'Not available'), ENT_QUOTES, 'UTF-8'); ?></p><p><?php echo nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')); ?></p></div></details></td>
                                        <td><span class="status-pill status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($activity_status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php backoffice_render_pagination($activity_page, 'activityRecords'); ?>
                </article>
<?php backoffice_render_shell_end(); ?>
