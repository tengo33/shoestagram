<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/print-document.php';

function backoffice_role_label($role)
{
    if ($role === 'admin') {
        return 'Administrator';
    }

    if ($role === 'staff') {
        return 'Store Staff';
    }

    return 'Team Member';
}

function backoffice_logout_href($role)
{
    return '../login/logout.php?redirect=login.php';
}

function backoffice_require_role($roles)
{
    global $conn;

    if (isset($_SESSION['user_id']) && isset($conn) && function_exists('database_get_user_by_id')) {
        $current_user = database_get_user_by_id($conn, (int) $_SESSION['user_id']);

        if (
            $current_user
            && (int) ($current_user['is_active'] ?? 0) === 1
            && !database_user_is_archived($current_user)
        ) {
            database_sync_session_user($conn, (int) $current_user['id']);
        } else {
            unset(
                $_SESSION['user_id'],
                $_SESSION['user_name'],
                $_SESSION['user_email'],
                $_SESSION['user_role']
            );
        }
    }

    $current_role = isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : '';

    if (!isset($_SESSION['user_id']) || !in_array($current_role, $roles, true)) {
        $redirect = in_array('admin', $roles, true) && !in_array('staff', $roles, true)
            ? '../admin/dashboard.php'
            : (in_array('staff', $roles, true) && !in_array('admin', $roles, true) ? '../staff/dashboard.php' : '../customer/index.php');
        $target = '../login/login.php?redirect=' . urlencode($redirect);

        header('Location: ' . $target);
        exit();
    }
}

function backoffice_nav_items($role)
{
    if ($role === 'staff') {
        return array(
            array('key' => 'dashboard', 'group' => 'Overview', 'href' => 'dashboard.php', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard'),
            array('key' => 'sales', 'group' => 'Operations', 'href' => 'sales.php', 'icon' => 'fa-chart-column', 'label' => 'Sales'),
            array('key' => 'inventory', 'group' => 'Operations', 'href' => 'inventory.php', 'icon' => 'fa-boxes-stacked', 'label' => 'Inventory'),
            array('key' => 'reservations', 'group' => 'Operations', 'href' => 'reservations.php', 'icon' => 'fa-bag-shopping', 'label' => 'Reservations & Payments'),
            array('key' => 'recommendations', 'group' => 'Insights', 'href' => 'recommendations.php', 'icon' => 'fa-lightbulb', 'label' => 'Recommendations'),
            array('key' => 'feedback', 'group' => 'Customer care', 'href' => 'feedback.php', 'icon' => 'fa-message', 'label' => 'Feedback'),
            array('key' => 'profile', 'group' => 'Account', 'href' => 'profile.php', 'icon' => 'fa-user-gear', 'label' => 'My Profile'),
        );
    }

    return array(
        array('key' => 'dashboard', 'group' => 'Overview', 'href' => 'dashboard.php', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard'),
        array('key' => 'sales', 'group' => 'Sales', 'href' => 'sales.php', 'icon' => 'fa-chart-column', 'label' => 'Sales'),
        array('key' => 'manual-sales', 'group' => 'Sales', 'href' => 'manual-sales.php', 'icon' => 'fa-receipt', 'label' => 'Historical Sales'),
        array('key' => 'forecasting', 'group' => 'Sales', 'href' => 'forecasting.php', 'icon' => 'fa-chart-line', 'label' => 'Sales Forecasting'),
        array('key' => 'pricing', 'group' => 'Sales', 'href' => 'pricing.php', 'icon' => 'fa-tags', 'label' => 'Pricing Analytics'),
        array('key' => 'inventory', 'group' => 'Operations', 'href' => 'inventory.php', 'icon' => 'fa-boxes-stacked', 'label' => 'Inventory'),
        array('key' => 'reservations', 'group' => 'Operations', 'href' => 'reservations.php', 'icon' => 'fa-bag-shopping', 'label' => 'Reservations & Payments'),
        array('key' => 'recommendations', 'group' => 'Insights', 'href' => 'recommendations.php', 'icon' => 'fa-wand-magic-sparkles', 'label' => 'Recommendations'),
        array('key' => 'users', 'group' => 'Management', 'href' => 'users.php', 'icon' => 'fa-users', 'label' => 'Staff Management'),
        array('key' => 'activity-log', 'group' => 'Management', 'href' => 'activity-log.php', 'icon' => 'fa-clock-rotate-left', 'label' => 'Activity Log'),
        array('key' => 'feedback', 'group' => 'Management', 'href' => 'feedback.php', 'icon' => 'fa-message', 'label' => 'Feedback'),
        array('key' => 'profile', 'group' => 'Account', 'href' => 'profile.php', 'icon' => 'fa-user-gear', 'label' => 'My Profile'),
    );
}

function backoffice_user_initials($name)
{
    $words = preg_split('/\s+/', trim((string) $name));
    $initials = '';

    foreach (array_slice(array_filter($words), 0, 2) as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }

    return $initials !== '' ? $initials : 'ST';
}

function backoffice_action_icon($label)
{
    $label = strtolower((string) $label);

    if (strpos($label, 'report') !== false || strpos($label, 'export') !== false) {
        return 'fa-file-arrow-down';
    }

    if (strpos($label, 'new') !== false || strpos($label, 'add') !== false || strpos($label, 'create') !== false) {
        return 'fa-plus';
    }

    if (strpos($label, 'back') !== false) {
        return 'fa-arrow-left';
    }

    return 'fa-arrow-right';
}

function backoffice_render_head($title)
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1018">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../store-theme.css?v=<?php echo filemtime(dirname(__FILE__) . '/store-theme.css'); ?>">
    <script src="../flash-notifications.js?v=<?php echo filemtime(dirname(__FILE__) . '/flash-notifications.js'); ?>" defer></script>
</head>
    <?php
}

function backoffice_render_sidebar($role, $active_key)
{
    $user_name = (string) ($_SESSION['user_name'] ?? 'Shoestagram Team');
    $user_email = (string) ($_SESSION['user_email'] ?? '');
    $current_group = null;
    ?>
    <aside class="admin-sidebar" id="backofficeSidebar" aria-label="<?php echo htmlspecialchars(backoffice_role_label($role), ENT_QUOTES, 'UTF-8'); ?> navigation">
        <div class="admin-sidebar-head">
        <a href="dashboard.php" class="brand" aria-label="Shoestagram dashboard">
            <span class="brand-mark">
                <img src="../customer/shoelogo.jpg" alt="Shoestagram logo">
            </span>
            <span class="brand-copy">
                <strong>Shoestagram</strong>
                <span><?php echo htmlspecialchars($role === 'admin' ? 'Admin Studio' : 'Staff Studio', ENT_QUOTES, 'UTF-8'); ?></span>
            </span>
        </a>
            <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close navigation">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="admin-nav" aria-label="Primary">
            <?php foreach (backoffice_nav_items($role) as $item): ?>
                <?php if (($item['group'] ?? '') !== $current_group): ?>
                    <?php $current_group = (string) ($item['group'] ?? 'Workspace'); ?>
                    <span class="admin-nav-section"><?php echo htmlspecialchars($current_group, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $item['key'] === $active_key ? 'is-active' : ''; ?>"<?php echo $item['key'] === $active_key ? ' aria-current="page"' : ''; ?> title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="admin-nav-icon"><i class="fas <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
                    <span class="admin-nav-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="sidebar-user" title="<?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars(backoffice_user_initials($user_name), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="sidebar-user-copy">
                    <strong><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small><?php echo htmlspecialchars($user_email !== '' ? $user_email : backoffice_role_label($role), ENT_QUOTES, 'UTF-8'); ?></small>
                </span>
            </div>
            <a href="<?php echo htmlspecialchars(backoffice_logout_href($role), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-logout" title="Log out">
                <span class="admin-nav-icon"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i></span>
                <span>Log out</span>
            </a>
            <button type="button" class="sidebar-collapse" data-sidebar-collapse aria-label="Collapse navigation" aria-expanded="true" title="Collapse navigation">
                <i class="fas fa-angles-left" aria-hidden="true"></i>
                <span>Collapse sidebar</span>
            </button>
        </div>
    </aside>
    <?php
}

function backoffice_render_shell_start($role, $active_key, $eyebrow, $title, $copy, $actions = array())
{
    $user_name = (string) ($_SESSION['user_name'] ?? 'Shoestagram Team');
    $role_label = backoffice_role_label($role);
    ?>
<body class="admin-body backoffice-page admin-panel-page backoffice-role-<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?> admin-page-<?php echo htmlspecialchars($active_key, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="bg-mesh bg-mesh-left"></div>
    <div class="bg-mesh bg-mesh-right"></div>
    <div class="noise-layer"></div>

    <div class="admin-shell">
        <?php storefront_render_flash_stack(); ?>
        <div class="admin-layout">
            <?php backoffice_render_sidebar($role, $active_key); ?>
            <button type="button" class="sidebar-overlay" data-sidebar-close aria-label="Close navigation"></button>
            <main class="admin-main" id="mainContent">
                <header class="admin-topbar surface-card">
                    <div class="admin-topbar-heading">
                        <button type="button" class="backoffice-menu-toggle" data-sidebar-open aria-controls="backofficeSidebar" aria-expanded="false" aria-label="Open navigation">
                            <i class="fas fa-bars" aria-hidden="true"></i>
                        </button>
                        <div>
                            <div class="admin-breadcrumb"><span><?php echo htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8'); ?></span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong><?php echo htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <h1 class="display-font"><?php echo htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8'); ?></h1>
                            <p title="<?php echo htmlspecialchars($copy, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <div class="admin-topbar-tools">
                    <div class="button-row admin-topbar-actions">
                        <?php foreach ($actions as $action): ?>
                            <?php if (stripos((string) ($action['href'] ?? ''), 'logout.php') !== false) { continue; } ?>
                            <a href="<?php echo htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8'); ?>" class="button <?php echo htmlspecialchars($action['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span><?php echo htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <i class="fas <?php echo htmlspecialchars(backoffice_action_icon($action['label']), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="topbar-user" aria-label="Signed in user">
                        <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars(backoffice_user_initials($user_name), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><strong><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8'); ?></small></span>
                    </div>
                    </div>
                </header>
                <div class="admin-content">
    <?php
}

function backoffice_render_shell_end()
{
    ?>
                </div>
            </main>
        </div>
    </div>
    <script src="../customer/storefront-ui.js"></script>
    <script src="../backoffice-ui.js?v=<?php echo filemtime(dirname(__FILE__) . '/backoffice-ui.js'); ?>"></script>
</body>
</html>
    <?php
}

function backoffice_chart_label_excerpt($label, $limit = 16)
{
    $label = trim((string) $label);

    if ($label === '' || strlen($label) <= $limit) {
        return $label;
    }

    return rtrim(substr($label, 0, $limit - 1)) . '...';
}

function backoffice_chart_compact_value($value, $mode = 'number')
{
    $value = (float) $value;

    if ($mode === 'money') {
        return store_currency_code() . ' ' . number_format($value, 0);
    }

    if (abs($value) >= 1000000) {
        return number_format($value / 1000000, 1) . 'M';
    }

    if (abs($value) >= 1000) {
        return number_format($value / 1000, 1) . 'K';
    }

    return number_format($value, $mode === 'decimal' ? 1 : 0);
}

function backoffice_build_smooth_line_path($points)
{
    $points = array_values($points);

    if (count($points) < 2) {
        return empty($points) ? '' : 'M' . round($points[0]['x'], 2) . ' ' . round($points[0]['y'], 2);
    }

    $path = 'M' . round($points[0]['x'], 2) . ' ' . round($points[0]['y'], 2);

    for ($index = 0; $index < count($points) - 1; $index++) {
        $current = $points[$index];
        $next = $points[$index + 1];
        $previous = $points[max(0, $index - 1)];
        $after_next = $points[min(count($points) - 1, $index + 2)];
        $control_one_x = $current['x'] + (($next['x'] - $previous['x']) / 6);
        $control_one_y = $current['y'] + (($next['y'] - $previous['y']) / 6);
        $control_two_x = $next['x'] - (($after_next['x'] - $current['x']) / 6);
        $control_two_y = $next['y'] - (($after_next['y'] - $current['y']) / 6);
        $path .= ' C' . round($control_one_x, 2) . ' ' . round($control_one_y, 2)
            . ', ' . round($control_two_x, 2) . ' ' . round($control_two_y, 2)
            . ', ' . round($next['x'], 2) . ' ' . round($next['y'], 2);
    }

    return $path;
}

function backoffice_render_line_chart($points, $series, $options = array())
{
    $points = is_array($points) ? array_values($points) : array();
    $series = is_array($series) ? array_values($series) : array();

    if (empty($points) || empty($series)) {
        echo '<p class="chart-empty">No chart data available yet.</p>';
        return;
    }

    $view_width = 720;
    $view_height = 280;
    $padding_left = 22;
    $padding_right = 18;
    $padding_top = 20;
    $padding_bottom = 38;
    $plot_width = $view_width - $padding_left - $padding_right;
    $plot_height = $view_height - $padding_top - $padding_bottom;
    $max_value = 0;
    $label_key = isset($options['label_key']) ? (string) $options['label_key'] : 'label';

    foreach ($points as $point) {
        foreach ($series as $config) {
            $key = isset($config['key']) ? (string) $config['key'] : '';
            $max_value = max($max_value, (float) ($point[$key] ?? 0));
        }
    }

    $max_value = max(1, $max_value);
    $step_x = count($points) > 1 ? ($plot_width / (count($points) - 1)) : 0;
    $value_mode = isset($options['value_mode']) ? (string) $options['value_mode'] : 'number';
    $chart_id = 'line-chart-' . uniqid();
    ?>
    <div class="line-chart-shell">
        <svg class="line-chart-svg" viewBox="0 0 <?php echo $view_width; ?> <?php echo $view_height; ?>" preserveAspectRatio="none" role="img" aria-label="Trend chart">
            <defs>
                <?php foreach ($series as $series_index => $config): ?>
                    <?php $gradient_color = isset($config['color']) ? (string) $config['color'] : ($series_index === 0 ? '#eb7b4e' : '#6f9cf6'); ?>
                    <linearGradient id="<?php echo $chart_id; ?>-fill-<?php echo $series_index; ?>" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="<?php echo htmlspecialchars($gradient_color, ENT_QUOTES, 'UTF-8'); ?>" stop-opacity="0.24"></stop>
                        <stop offset="100%" stop-color="<?php echo htmlspecialchars($gradient_color, ENT_QUOTES, 'UTF-8'); ?>" stop-opacity="0"></stop>
                    </linearGradient>
                <?php endforeach; ?>
            </defs>
            <?php for ($grid_index = 0; $grid_index <= 4; $grid_index++): ?>
                <?php $grid_y = $padding_top + (($plot_height / 4) * $grid_index); ?>
                <line x1="<?php echo $padding_left; ?>" y1="<?php echo $grid_y; ?>" x2="<?php echo $view_width - $padding_right; ?>" y2="<?php echo $grid_y; ?>" class="line-chart-grid"></line>
                <text x="<?php echo $padding_left; ?>" y="<?php echo $grid_y - 6; ?>" class="line-chart-value-label"><?php echo htmlspecialchars(backoffice_chart_compact_value($max_value - (($max_value / 4) * $grid_index), $value_mode), ENT_QUOTES, 'UTF-8'); ?></text>
            <?php endfor; ?>

            <?php foreach ($series as $series_index => $config): ?>
                <?php
                $key = isset($config['key']) ? (string) $config['key'] : '';
                $color = isset($config['color']) ? (string) $config['color'] : ($series_index === 0 ? '#eb7b4e' : '#6f9cf6');
                $markers = array();

                foreach ($points as $point_index => $point) {
                    $value = (float) ($point[$key] ?? 0);
                    $x = $padding_left + ($step_x * $point_index);
                    $y = $padding_top + ($plot_height - (($value / $max_value) * $plot_height));
                    $markers[] = array('x' => $x, 'y' => $y, 'value' => $value);
                }
                $path = backoffice_build_smooth_line_path($markers);
                $area_path = $path . ' L' . round($markers[count($markers) - 1]['x'], 2) . ' ' . ($padding_top + $plot_height)
                    . ' L' . round($markers[0]['x'], 2) . ' ' . ($padding_top + $plot_height) . ' Z';
                ?>
                <path d="<?php echo htmlspecialchars($area_path, ENT_QUOTES, 'UTF-8'); ?>" class="line-chart-area" style="fill: url(#<?php echo $chart_id; ?>-fill-<?php echo $series_index; ?>);"></path>
                <path d="<?php echo htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); ?>" class="line-chart-path" style="stroke: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"></path>
                <?php foreach ($markers as $marker_index => $marker): ?>
                    <g class="line-chart-marker">
                        <title><?php echo htmlspecialchars((string) ($points[$marker_index][$label_key] ?? '') . ': ' . backoffice_chart_compact_value($marker['value'], $value_mode), ENT_QUOTES, 'UTF-8'); ?></title>
                        <circle cx="<?php echo round($marker['x'], 2); ?>" cy="<?php echo round($marker['y'], 2); ?>" r="5" class="line-chart-point" style="fill: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"></circle>
                    </g>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </svg>

        <div class="chart-axis-labels">
            <?php foreach ($points as $point): ?>
                <span><?php echo htmlspecialchars(backoffice_chart_label_excerpt($point[$label_key] ?? '', 14), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endforeach; ?>
        </div>

        <?php if (count($series) > 1): ?>
            <div class="chart-legend">
                <?php foreach ($series as $series_index => $config): ?>
                    <?php $color = isset($config['color']) ? (string) $config['color'] : ($series_index === 0 ? '#eb7b4e' : '#6f9cf6'); ?>
                    <span><i style="background: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"></i><?php echo htmlspecialchars((string) ($config['label'] ?? 'Series'), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function backoffice_render_comparison_chart($items, $options = array())
{
    $items = is_array($items) ? array_values($items) : array();

    if (empty($items)) {
        echo '<p class="chart-empty">No comparison data available yet.</p>';
        return;
    }

    $value_key = isset($options['value_key']) ? (string) $options['value_key'] : 'value';
    $label_key = isset($options['label_key']) ? (string) $options['label_key'] : 'label';
    $meta_key = isset($options['meta_key']) ? (string) $options['meta_key'] : 'meta';
    $value_mode = isset($options['value_mode']) ? (string) $options['value_mode'] : 'number';
    $max_value = max(1, max(array_map(function ($item) use ($value_key) { return (float) ($item[$value_key] ?? 0); }, $items)));
    ?>
    <div class="comparison-chart" role="list" aria-label="Sales channel comparison">
        <?php foreach ($items as $index => $item): ?>
            <?php $value = (float) ($item[$value_key] ?? 0); $width = max(3, round(($value / $max_value) * 100, 2)); $color = (string) ($item['color'] ?? ($index === 0 ? '#eb7b4e' : '#6f9cf6')); ?>
            <article class="comparison-chart-row" role="listitem">
                <div class="comparison-chart-copy">
                    <strong><?php echo htmlspecialchars((string) ($item[$label_key] ?? 'Channel'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php if ($meta_key !== '' && !empty($item[$meta_key])): ?><span><?php echo htmlspecialchars((string) $item[$meta_key], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </div>
                <div class="comparison-chart-track" title="<?php echo htmlspecialchars((string) ($item[$label_key] ?? 'Channel') . ': ' . backoffice_chart_compact_value($value, $value_mode), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="comparison-chart-fill" style="width: <?php echo $width; ?>%; background: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"></div>
                </div>
                <strong class="comparison-chart-value"><?php echo htmlspecialchars(backoffice_chart_compact_value($value, $value_mode), ENT_QUOTES, 'UTF-8'); ?></strong>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

function backoffice_render_bar_chart($items, $options = array())
{
    $items = is_array($items) ? array_values($items) : array();

    if (empty($items)) {
        echo '<p class="chart-empty">No chart data available yet.</p>';
        return;
    }

    $value_key = isset($options['value_key']) ? (string) $options['value_key'] : 'value';
    $label_key = isset($options['label_key']) ? (string) $options['label_key'] : 'label';
    $meta_key = isset($options['meta_key']) ? (string) $options['meta_key'] : 'meta';
    $value_mode = isset($options['value_mode']) ? (string) $options['value_mode'] : 'number';
    $max_value = 0;

    foreach ($items as $item) {
        $max_value = max($max_value, (float) ($item[$value_key] ?? 0));
    }

    $max_value = max(1, $max_value);
    ?>
    <div class="bar-chart-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $value = (float) ($item[$value_key] ?? 0);
            $height = max(8, round(($value / $max_value) * 100, 2));
            ?>
            <article class="bar-chart-item">
                <div class="bar-chart-meter">
                    <div class="bar-chart-fill" style="height: <?php echo $height; ?>%;"></div>
                </div>
                <strong><?php echo htmlspecialchars(backoffice_chart_label_excerpt($item[$label_key] ?? '', 16), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span><?php echo htmlspecialchars(backoffice_chart_compact_value($value, $value_mode), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($meta_key !== '' && !empty($item[$meta_key])): ?>
                    <small><?php echo htmlspecialchars((string) $item[$meta_key], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

function backoffice_render_pie_chart($items, $options = array())
{
    $items = is_array($items) ? array_values($items) : array();

    if (empty($items)) {
        echo '<p class="chart-empty">No chart data available yet.</p>';
        return;
    }

    $value_key = isset($options['value_key']) ? (string) $options['value_key'] : 'value';
    $label_key = isset($options['label_key']) ? (string) $options['label_key'] : 'label';
    $meta_key = isset($options['meta_key']) ? (string) $options['meta_key'] : 'meta';
    $value_mode = isset($options['value_mode']) ? (string) $options['value_mode'] : 'number';
    $colors = isset($options['colors']) && is_array($options['colors'])
        ? array_values($options['colors'])
        : array('#eb7b4e', '#6f9cf6', '#d7a362', '#22a06b');
    $total = 0;

    foreach ($items as $item) {
        $total += max(0, (float) ($item[$value_key] ?? 0));
    }

    $segments = array();
    $cursor = 0;

    if ($total > 0) {
        foreach ($items as $index => $item) {
            $value = max(0, (float) ($item[$value_key] ?? 0));
            $degrees = ($value / $total) * 360;
            $start = $cursor;
            $end = $index === count($items) - 1 ? 360 : $cursor + $degrees;
            $color = isset($item['color']) ? (string) $item['color'] : $colors[$index % count($colors)];
            $segments[] = htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ' ' . round($start, 2) . 'deg ' . round($end, 2) . 'deg';
            $cursor = $end;
        }
    }

    $background = $total > 0 ? 'conic-gradient(' . implode(', ', $segments) . ')' : 'rgba(15, 19, 32, 0.08)';
    ?>
    <div class="pie-chart-shell">
        <div class="pie-chart-visual" style="background: <?php echo $background; ?>;">
            <div>
                <strong><?php echo htmlspecialchars(backoffice_chart_compact_value($total, $value_mode), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Total</span>
            </div>
        </div>
        <div class="pie-chart-legend">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $value = max(0, (float) ($item[$value_key] ?? 0));
                $percent = $total > 0 ? round(($value / $total) * 100, 1) : 0;
                $color = isset($item['color']) ? (string) $item['color'] : $colors[$index % count($colors)];
                ?>
                <div class="pie-chart-row">
                    <span class="pie-chart-swatch" style="background: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                    <div>
                        <strong><?php echo htmlspecialchars((string) ($item[$label_key] ?? 'Channel'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars(backoffice_chart_compact_value($value, $value_mode), ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo number_format($percent, 1); ?>%</span>
                        <?php if ($meta_key !== '' && !empty($item[$meta_key])): ?>
                            <small><?php echo htmlspecialchars((string) $item[$meta_key], ENT_QUOTES, 'UTF-8'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function backoffice_fetch_count($connection, $query)
{
    $result = mysqli_query($connection, $query);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return isset($row['count']) ? (int) $row['count'] : 0;
}

function backoffice_get_all_users($connection)
{
    return database_fetch_all(
        $connection,
        "SELECT id, fullname, email, role, phone, address, is_active, archived_at, created_at, updated_at FROM users ORDER BY created_at DESC, id DESC"
    );
}

function backoffice_paginate_rows(&$rows, $page_key = 'page', $per_page = 10)
{
    $per_page = max(1, (int) $per_page);
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($pages, max(1, (int) ($_GET[$page_key] ?? 1)));
    $rows = array_slice($rows, ($page - 1) * $per_page, $per_page);
    return array('page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $per_page, 'key' => $page_key);
}

function backoffice_paginate_transaction_lines(&$rows, $page_key = 'page', $per_page = 10)
{
    $groups = array();
    foreach ($rows as $row) {
        $key = (string) ($row['transaction_key'] ?? ((string) ($row['source'] ?? 'system') . ':' . (string) ($row['reference'] ?? '')));
        $groups[$key][] = $row;
    }
    $keys = array_keys($groups);
    $pagination = backoffice_paginate_rows($keys, $page_key, $per_page);
    $visible = array();
    foreach ($keys as $key) {
        foreach ($groups[$key] as $row) {
            $visible[] = $row;
        }
    }
    $rows = $visible;
    return $pagination;
}

function backoffice_render_pagination($pagination, $anchor = '')
{
    $page = (int) ($pagination['page'] ?? 1);
    $pages = (int) ($pagination['pages'] ?? 1);
    $total = (int) ($pagination['total'] ?? 0);
    $per_page = (int) ($pagination['per_page'] ?? 10);
    $key = (string) ($pagination['key'] ?? 'page');
    $base = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $params = $_GET;
    unset($params['export']);
    $fragment = preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', (string) $anchor) ? '#' . $anchor : '';
    $link = function ($target) use ($params, $key, $base, $fragment) {
        $query = $params;
        $query[$key] = $target;
        return htmlspecialchars($base . '?' . http_build_query($query) . $fragment, ENT_QUOTES, 'UTF-8');
    };
    $first = $total > 0 ? ($page - 1) * $per_page + 1 : 0;
    $last = min($total, $page * $per_page);
    echo '<nav class="activity-pagination record-pagination" aria-label="Record pages">';
    echo '<div class="pagination-context"><span>Showing ' . $first . '&ndash;' . $last . ' of ' . $total . ' records</span><span>Page ' . $page . ' of ' . $pages . '</span></div>';
    echo '<div class="pagination-controls">';
    if ($page > 1) {
        echo '<a class="button button-light" href="' . $link($page - 1) . '" rel="prev" aria-label="Previous page">&lsaquo; Previous</a>';
    } else {
        echo '<span class="button button-light is-disabled" aria-disabled="true">&lsaquo; Previous</span>';
    }
    $numbers = array_values(array_unique(array_merge(array(1, $pages), range(max(1, $page - 2), min($pages, $page + 2)))));
    sort($numbers);
    $previous_number = 0;
    foreach ($numbers as $number) {
        if ($previous_number && $number > $previous_number + 1) {
            echo '<span class="pagination-ellipsis" aria-hidden="true">&hellip;</span>';
        }
        if ($number === $page) {
            echo '<span class="pagination-page is-current" aria-current="page" aria-label="Page ' . $number . ', current page">' . $number . '</span>';
        } else {
            echo '<a class="pagination-page" href="' . $link($number) . '" aria-label="Go to page ' . $number . '">' . $number . '</a>';
        }
        $previous_number = $number;
    }
    if ($page < $pages) {
        echo '<a class="button button-light" href="' . $link($page + 1) . '" rel="next" aria-label="Next page">Next &rsaquo;</a>';
    } else {
        echo '<span class="button button-light is-disabled" aria-disabled="true">Next &rsaquo;</span>';
    }
    echo '</div></nav>';
}

function backoffice_manila_datetime($value, $require_time = false)
{
    $value = trim((string) $value);
    if ($value === '' || strpos($value, '0000-00-00') === 0 || ($require_time && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))) {
        return null;
    }
    try {
        $timezone = new DateTimeZone('Asia/Manila');
        $datetime = new DateTimeImmutable($value, $timezone);
        $parse_errors = DateTimeImmutable::getLastErrors();
        if (is_array($parse_errors) && ((int) $parse_errors['warning_count'] > 0 || (int) $parse_errors['error_count'] > 0)) {
            return null;
        }
        return $datetime->setTimezone($timezone);
    } catch (Exception $error) {
        return null;
    }
}

function backoffice_get_stats($connection)
{
    $products = array_values(get_store_products());
    $orders = store_state_get_orders();
    usort($orders, function ($left, $right) {
        $date = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        return $date !== 0 ? $date : strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
    });
    $reviews = store_state_get_reviews();
    $messages = store_state_get_messages();
    $transactions = get_admin_dynamic_transactions(20);
    $active_reservations = get_admin_dynamic_reservations(20);
    $alerts = get_admin_dynamic_alerts();
    $recent_reviews = get_admin_dynamic_reviews(10);
    $recent_messages = get_admin_dynamic_messages(10);
    $users = backoffice_get_all_users($connection);
    $inventory = store_get_inventory_snapshot();
    $sales_overview = store_get_sales_overview();
    $sales_history = store_get_sales_history();
    $sales_projection = store_get_sales_projection();
    $price_analytics = store_get_price_analytics();
    $recommendation_insights = store_get_recommendation_insights();
    $promotions = store_get_promotions();
    $active_promotions = store_get_active_promotions(8);
    $low_sales_products = store_get_low_sales_products(8);
    $top_products = $products;
    $actual_product_sales = array();

    foreach (get_admin_dynamic_transactions(0) as $transaction) {
        if (($transaction['status'] ?? '') !== 'Completed' || !store_transaction_counts_for_analytics($transaction)) {
            continue;
        }

        $product_id = (int) ($transaction['product_id'] ?? 0);
        if (!isset($actual_product_sales[$product_id])) {
            $actual_product_sales[$product_id] = array('units' => 0, 'revenue' => 0.0);
        }
        $actual_product_sales[$product_id]['units'] += max(0, (int) ($transaction['quantity'] ?? 0));
        $actual_product_sales[$product_id]['revenue'] += max(0, (float) ($transaction['amount'] ?? 0));
    }

    foreach ($top_products as &$top_product) {
        $product_id = (int) ($top_product['id'] ?? 0);
        $top_product['actual_sales_count'] = (int) ($actual_product_sales[$product_id]['units'] ?? 0);
        $top_product['actual_sales_revenue'] = round((float) ($actual_product_sales[$product_id]['revenue'] ?? 0), 2);
    }
    unset($top_product);

    usort($top_products, function ($left, $right) {
        return (int) $right['actual_sales_count'] <=> (int) $left['actual_sales_count'];
    });

    $status_breakdown = array(
        'Pending' => 0,
        'Confirmed' => 0,
        'Ready for Pickup' => 0,
        'Completed' => 0,
        'Cancelled' => 0,
    );
    $ready_orders = 0;

    foreach (store_group_checkout_orders($orders) as $order) {
        $status = (string) ($order['status'] ?? 'Pending');

        if (!isset($status_breakdown[$status])) {
            $status_breakdown[$status] = 0;
        }

        $status_breakdown[$status]++;

        if ($status === 'Ready for Pickup') {
            $ready_orders++;
        }
    }

    $average_rating = count($products) > 0
        ? array_sum(array_map(function ($product) {
            return (float) $product['rating'];
        }, $products)) / count($products)
        : 0;

    $staff_users = backoffice_fetch_count($connection, "SELECT COUNT(*) AS count FROM users WHERE role = 'staff'");
    $customer_users = backoffice_fetch_count($connection, "SELECT COUNT(*) AS count FROM users WHERE role = 'customer'");
    $admin_users = backoffice_fetch_count($connection, "SELECT COUNT(*) AS count FROM users WHERE role = 'admin'");
    $active_users = backoffice_fetch_count($connection, "SELECT COUNT(*) AS count FROM users WHERE is_active = 1");
    $total_users = backoffice_fetch_count($connection, "SELECT COUNT(*) AS count FROM users");
    $max_sales = !empty($top_products) ? max(array_map(function ($product) {
        return (int) ($product['actual_sales_count'] ?? 0);
    }, $top_products)) : 1;

    return array(
        'products' => $products,
        'orders' => $orders,
        'reviews' => $reviews,
        'messages' => $messages,
        'transactions' => $transactions,
        'active_reservations' => $active_reservations,
        'alerts' => $alerts,
        'recent_reviews' => $recent_reviews,
        'recent_messages' => $recent_messages,
        'top_products' => $top_products,
        'recommended_focus' => $recommendation_insights['most_recommended'],
        'most_viewed_products' => $recommendation_insights['most_viewed'],
        'most_reserved_products' => $recommendation_insights['most_reserved'],
        'low_stock_products' => $inventory['low_stock_products'],
        'overstock_products' => $inventory['overstock_products'],
        'inventory_units' => $inventory['inventory_units'],
        'completed_revenue' => $sales_overview['actual_completed_revenue'],
        'completed_orders' => $sales_overview['actual_completed_orders'],
        'ready_orders' => $ready_orders,
        'status_breakdown' => $status_breakdown,
        'average_rating' => round($average_rating, 1),
        'staff_users' => $staff_users,
        'customer_users' => $customer_users,
        'admin_users' => $admin_users,
        'active_users' => $active_users,
        'total_users' => $total_users,
        'max_sales' => $max_sales,
        'users' => $users,
        'sales_overview' => $sales_overview,
        'sales_history' => $sales_history,
        'sales_projection' => $sales_projection,
        'price_analytics' => $price_analytics,
        'promotions' => $promotions,
        'active_promotions' => $active_promotions,
        'low_sales_products' => $low_sales_products,
        'inventory_snapshot' => $inventory,
        'inventory_logs' => database_get_inventory_logs($connection, 40),
        'recommendation_insights' => $recommendation_insights,
    );
}

function backoffice_allowed_statuses($role, $current_status)
{
    if ($role === 'admin' || $role === 'staff') {
        return store_state_statuses();
    }

    return array($current_status);
}

function backoffice_can_update_status($role, $current_status, $new_status)
{
    return in_array($new_status, backoffice_allowed_statuses($role, $current_status), true);
}

function backoffice_apply_order_update($role, $order_id, $new_status, $new_payment_status, $actor = null, $notify_customer = true)
{
    global $conn;

    $order_id = trim((string) $order_id);
    $new_status = trim((string) $new_status);
    $new_payment_status = trim((string) $new_payment_status);
    $actor = is_array($actor) ? $actor : database_activity_actor_from_session();
    $orders = store_state_get_orders();
    $current_status = '';
    $current_payment_status = '';

    foreach ($orders as $order) {
        if (($order['id'] ?? '') === $order_id) {
            $current_status = (string) ($order['status'] ?? '');
            $current_payment_status = (string) ($order['payment_status'] ?? '');
            break;
        }
    }

    if ($new_payment_status === '') {
        $new_payment_status = $current_payment_status;
    }

    if ($current_status === ''
        || !backoffice_can_update_status($role, $current_status, $new_status)
        || !in_array($new_payment_status, store_state_payment_statuses(), true)) {
        return array('ok' => false, 'message' => 'This role is not allowed to change the order or payment to that status.');
    }

    if (store_state_update_order_workflow($order_id, $new_status, $new_payment_status)) {
        $updated_order = store_get_order_by_id($order_id);
        $order_changed = $current_status !== $new_status;
        $payment_changed = $current_payment_status !== $new_payment_status;

        if ($order_changed) {
            database_log_activity(
                $conn,
                $actor,
                'Order',
                'Changed order ' . $order_id . ' status from ' . $current_status . ' to ' . $new_status . '.',
                'Success'
            );
        }

        if ($payment_changed) {
            database_log_activity(
                $conn,
                $actor,
                'Payment',
                'Changed order ' . $order_id . ' payment status from ' . $current_payment_status . ' to ' . $new_payment_status . '.',
                'Success'
            );
        }

        if ($notify_customer && $updated_order && ($order_changed || $payment_changed)) {
            store_send_order_email(
                $updated_order,
                'Shoestagram order update ' . $order_id,
                'Your reservation status has been updated.'
            );
        }

        return array(
            'ok' => true,
            'message' => 'Order ' . $order_id . ' saved. Order status: ' . $new_status . '; payment status: ' . $new_payment_status . '.',
            'order_changed' => $order_changed,
            'payment_changed' => $payment_changed,
        );
    }

    return array('ok' => false, 'message' => 'The order or payment status could not be updated.');
}

function backoffice_handle_order_update($role)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['admin_action'] ?? '') !== 'update_order') {
        return;
    }

    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the status update again.');
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']));
        exit();
    }

    $result = backoffice_apply_order_update(
        $role,
        $_POST['order_id'] ?? '',
        $_POST['status'] ?? '',
        $_POST['payment_status'] ?? ''
    );
    storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', (string) $result['message']);

    header('Location: ' . basename((string) $_SERVER['PHP_SELF']));
    exit();
}

function backoffice_handle_review_update($role)
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['admin_action'] ?? '') !== 'update_review') {
        return;
    }

    if (!in_array($role, array('admin', 'staff'), true)) {
        storefront_set_flash('error', 'This role is not allowed to moderate reviews.');
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#reviews');
        exit();
    }

    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the review action again.');
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#reviews');
        exit();
    }

    $review_id = trim((string) ($_POST['review_id'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));
    $previous_status = '';

    foreach (store_state_get_reviews() as $review) {
        if ((string) ($review['id'] ?? '') === $review_id) {
            $previous_status = (string) ($review['status'] ?? 'Published');
            break;
        }
    }

    if (!in_array($status, store_state_review_statuses(), true)) {
        storefront_set_flash('error', 'Choose a valid review moderation action.');
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#reviews');
        exit();
    }

    if (store_state_update_review_status($review_id, $status)) {
        database_log_activity(
            $conn,
            database_activity_actor_from_session(),
            'Review',
            'Changed review ' . $review_id . ' status from ' . ($previous_status !== '' ? $previous_status : 'Unknown') . ' to ' . $status . '.',
            'Success'
        );
        storefront_set_flash('success', 'Review ' . $review_id . ' updated to ' . $status . '.');
    } else {
        storefront_set_flash('error', 'The review could not be updated.');
    }

    header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#reviews');
    exit();
}

function backoffice_handle_promotion_update($role)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array(($_POST['admin_action'] ?? ''), array('save_promotion', 'toggle_promotion'), true)) {
        return;
    }

    if (!in_array($role, array('admin', 'staff'), true)) {
        storefront_set_flash('error', 'This role is not allowed to manage promotions.');
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#promotions');
        exit();
    }

    if (!storefront_verify_csrf($_POST['csrf_token'] ?? '')) {
        storefront_set_flash('error', 'Your session expired. Please try the promotion action again.');
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#promotions');
        exit();
    }

    if (($_POST['admin_action'] ?? '') === 'save_promotion') {
        $result = store_save_promotion($_POST);
        storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
        header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#promotions');
        exit();
    }

    $result = store_set_promotion_status(
        (int) ($_POST['promotion_id'] ?? 0),
        (string) ($_POST['status'] ?? 'Paused')
    );
    storefront_set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message']);
    header('Location: ' . basename((string) $_SERVER['PHP_SELF']) . '#promotions');
    exit();
}

function backoffice_review_status_class($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === 'published') {
        return 'status-completed';
    }

    if ($status === 'hidden') {
        return 'status-warning';
    }

    if ($status === 'deleted') {
        return 'status-cancelled';
    }

    return 'status-info';
}

function backoffice_promotion_status_class($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === 'active') {
        return 'status-completed';
    }

    if ($status === 'paused' || $status === 'scheduled') {
        return 'status-warning';
    }

    if ($status === 'expired') {
        return 'status-cancelled';
    }

    return 'status-info';
}

function backoffice_render_promotion_management($stats, $selected_product_id = 0)
{
    $selected_product_id = (int) $selected_product_id;
    $promotion_rows = $stats['promotions'];
    $promotion_pagination = backoffice_paginate_rows($promotion_rows, 'promo_page');
    ?>
    <article class="admin-card surface-card" id="promotions">
        <span class="eyebrow">Promotion actions</span>
        <h2 class="display-font">Create a product sale or discount.</h2>
        <form method="POST" class="contact-form" id="promotion-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="admin_action" value="save_promotion">
            <div class="form-grid">
                <div class="field">
                    <label for="promotion_product_id">Product</label>
                    <select id="promotion_product_id" name="product_id" required>
                        <option value="">Choose product</option>
                        <?php foreach ($stats['products'] as $product): ?>
                            <option value="<?php echo (int) $product['id']; ?>"<?php echo $selected_product_id === (int) $product['id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name'] . ' - ' . store_format_money($product['regular_price'] ?? $product['price']), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="promotion_title">Promotion title</label>
                    <input type="text" id="promotion_title" name="title" value="Shoestagram Sale" required>
                </div>
                <div class="field">
                    <label for="promotion_status">Status</label>
                    <select id="promotion_status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Paused">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="discount_percent">Discount percent</label>
                    <input type="number" step="0.01" min="0" max="99.99" id="discount_percent" name="discount_percent" placeholder="Example: 15">
                </div>
                <div class="field">
                    <label for="sale_price">Discounted price</label>
                    <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" placeholder="Optional fixed sale price">
                </div>
                <div class="field">
                    <label for="starts_at">Start date</label>
                    <input type="date" id="starts_at" name="starts_at" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label for="ends_at">End date</label>
                    <input type="date" id="ends_at" name="ends_at" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('+14 days')), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="button-row">
                <button type="submit" class="button button-dark">Create promotion</button>
            </div>
        </form>
    </article>

    <div class="admin-card-grid">
        <article class="admin-card surface-card">
            <span class="eyebrow">Low or no sales</span>
            <h2 class="display-font">Products that can use a promotion.</h2>
            <div class="admin-alerts">
                <?php foreach ($stats['low_sales_products'] as $product): ?>
                    <article class="alert-card info">
                        <i class="fas fa-percent"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p style="margin: 6px 0 0; color: var(--muted);">
                                <?php echo (int) $product['sales_count']; ?> sale<?php echo (int) $product['sales_count'] === 1 ? '' : 's'; ?> - <?php echo (int) $product['stock']; ?> left
                                <?php if (!empty($product['promotion'])): ?>
                                    - currently promoted
                                <?php endif; ?>
                            </p>
                            <div class="button-row compact-row" style="margin-top: 12px;">
                                <a href="sales.php?promo_product_id=<?php echo (int) $product['id']; ?>#promotions" class="button button-light">Use promotion</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="admin-card surface-card">
            <span class="eyebrow">Active offers</span>
            <h2 class="display-font">Visible customer promotions.</h2>
            <?php if (empty($stats['active_promotions'])): ?>
                <p style="margin: 18px 0 0; color: var(--muted);">No active promotion is currently visible on the homepage.</p>
            <?php else: ?>
                <div class="chart-list">
                    <?php foreach ($stats['active_promotions'] as $promotion): ?>
                        <div class="chart-row">
                            <div>
                                <strong><?php echo htmlspecialchars((string) ($promotion['product_name'] ?? 'Product'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span>
                                    <?php echo htmlspecialchars(store_format_money((float) ($promotion['regular_price'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                                    to <?php echo htmlspecialchars(store_format_money((float) ($promotion['sale_price'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ((float) ($promotion['discount_percent'] ?? 0) > 0): ?>
                                        - <?php echo number_format((float) $promotion['discount_percent'], 0); ?>% off
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="status-pill status-completed">Active</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>

    <article class="table-card surface-card" id="promotionRecords">
        <span class="eyebrow">Promotion table</span>
        <h2 class="display-font">All product promotions.</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Original (PHP)</th>
                        <th>Sale (PHP)</th>
                        <th>Discount</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promotion_rows as $promotion): ?>
                        <?php $display_status = (string) ($promotion['display_status'] ?? store_promotion_display_status($promotion)); ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($promotion['product_name'] ?? 'Product'), ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars((string) ($promotion['title'] ?? 'Promotion'), ENT_QUOTES, 'UTF-8'); ?></small></td>
                            <td><?php echo number_format((float) ($promotion['regular_price'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($promotion['sale_price'] ?? 0), 2); ?></td>
                            <td><?php echo (float) ($promotion['discount_percent'] ?? 0) > 0 ? number_format((float) $promotion['discount_percent'], 1) . '%' : 'Fixed price'; ?></td>
                            <td>
                                <?php echo htmlspecialchars((string) ($promotion['starts_at'] ?: 'No start'), ENT_QUOTES, 'UTF-8'); ?>
                                <br><small>to <?php echo htmlspecialchars((string) ($promotion['ends_at'] ?: 'No end'), ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td><span class="status-pill <?php echo htmlspecialchars(backoffice_promotion_status_class($display_status), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($display_status === 'Paused' ? 'Inactive' : $display_status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td>
                                <?php if ($display_status === 'Active'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="admin_action" value="toggle_promotion">
                                        <input type="hidden" name="promotion_id" value="<?php echo (int) $promotion['id']; ?>">
                                        <input type="hidden" name="status" value="Paused">
                                        <button type="submit" class="button button-light">Deactivate</button>
                                    </form>
                                <?php elseif ($display_status === 'Expired'): ?>
                                    <span style="color: var(--muted);">Update dates by creating a new offer.</span>
                                <?php else: ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="admin_action" value="toggle_promotion">
                                        <input type="hidden" name="promotion_id" value="<?php echo (int) $promotion['id']; ?>">
                                        <input type="hidden" name="status" value="Active">
                                        <button type="submit" class="button button-dark">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['promotions'])): ?>
                        <tr>
                            <td colspan="7">No promotions have been created yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php backoffice_render_pagination($promotion_pagination, 'promotionRecords'); ?>
    </article>
    <?php
}

function backoffice_render_review_management($reviews, $pagination = null)
{
    ?>
    <article class="admin-card surface-card" id="reviews">
        <span class="eyebrow">Review moderation</span>
        <h2 class="display-font">Manage product reviews.</h2>
        <?php if (empty($reviews)): ?>
            <p style="margin: 18px 0 0; color: var(--muted);">No customer reviews have been submitted yet.</p>
        <?php else: ?>
            <div class="admin-alerts">
                <?php foreach ($reviews as $review): ?>
                    <?php $status = (string) ($review['status'] ?? 'Published'); ?>
                    <?php $is_demo_review = store_state_review_is_demo($review); ?>
                    <article class="alert-card <?php echo $status === 'Published' ? 'success' : ($status === 'Hidden' ? 'warning' : 'info'); ?>">
                        <i class="fas fa-star"></i>
                        <div style="width: 100%;">
                            <div class="order-card-top">
                                <div>
                                    <strong><?php echo htmlspecialchars($review['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8'); ?> - <?php echo (int) $review['rating']; ?>/5</strong>
                                    <p style="margin: 6px 0 0; color: var(--muted);">
                                        <?php echo htmlspecialchars($review['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($is_demo_review): ?> - Development seed<?php else: ?> - Customer submission<?php endif; ?>
                                    </p>
                                </div>
                                <span class="status-pill <?php echo htmlspecialchars(backoffice_review_status_class($status), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p class="record-comment-preview"><?php echo htmlspecialchars($review['comment'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <details class="table-details"><summary>Read full review</summary><div><p><?php echo nl2br(htmlspecialchars((string) ($review['comment'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p><p><strong>Order:</strong> <?php echo htmlspecialchars((string) ($review['order_id'] ?? 'Not recorded'), ENT_QUOTES, 'UTF-8'); ?></p></div></details>
                            <div class="button-row compact-row" style="margin-top: 14px;">
                                <?php foreach (store_state_review_statuses() as $status_option): ?>
                                    <?php if ($status_option === $status) { continue; } ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(storefront_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="admin_action" value="update_review">
                                        <input type="hidden" name="review_id" value="<?php echo htmlspecialchars($review['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="button <?php echo $status_option === 'Deleted' ? 'button-light' : 'button-dark'; ?>">
                                            <?php echo htmlspecialchars($status_option === 'Published' ? 'Approve/show' : ($status_option === 'Hidden' ? 'Hide' : 'Delete'), ENT_QUOTES, 'UTF-8'); ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (is_array($pagination)): backoffice_render_pagination($pagination, 'reviews'); endif; ?>
    </article>
    <?php
}

function backoffice_filter_transactions($transactions, $filters = array())
{
    $date_from = trim((string) ($filters['date_from'] ?? ''));
    $date_to = trim((string) ($filters['date_to'] ?? ''));
    $product_id = (int) ($filters['product_id'] ?? 0);
    $source = strtolower(trim((string) ($filters['source'] ?? '')));
    $channel = strtoupper(trim((string) ($filters['channel'] ?? '')));

    return array_values(array_filter($transactions, function ($transaction) use ($date_from, $date_to, $product_id, $source, $channel) {
        if (($transaction['status'] ?? '') === 'Completed' && !store_transaction_counts_for_analytics($transaction)) {
            return false;
        }

        $created_at = (string) ($transaction['created_at'] ?? '');
        $date_value = '';
        if ($created_at !== '') {
            try {
                $manila = new DateTimeZone('Asia/Manila');
                $date_value = (new DateTimeImmutable($created_at, $manila))->setTimezone($manila)->format('Y-m-d');
            } catch (Exception $exception) {
                $date_value = '';
            }
        }

        if ($date_from !== '' && $date_value !== '' && $date_value < $date_from) {
            return false;
        }

        if ($date_to !== '' && $date_value !== '' && $date_value > $date_to) {
            return false;
        }

        if ($product_id > 0 && (int) ($transaction['product_id'] ?? 0) !== $product_id) {
            return false;
        }

        if (in_array($source, array('system', 'manual'), true) && (string) ($transaction['source'] ?? 'system') !== $source) {
            return false;
        }

        if (in_array($channel, array('WALK-IN', 'ONLINE'), true)
            && store_state_channel_type($transaction['transaction_type'] ?? ($transaction['channel'] ?? 'ONLINE')) !== $channel) {
            return false;
        }

        return true;
    }));
}

function backoffice_summarize_transaction_channels($transactions)
{
    $summary = array(
        'WALK-IN' => array('label' => 'Walk-in', 'transactions' => 0, 'revenue' => 0.0, 'completed_revenue' => 0.0),
        'ONLINE' => array('label' => 'Online', 'transactions' => 0, 'revenue' => 0.0, 'completed_revenue' => 0.0),
    );

    $counted_references = array();
    foreach ((array) $transactions as $transaction) {
        if (($transaction['status'] ?? '') !== 'Completed' || !store_transaction_counts_for_analytics($transaction)) {
            continue;
        }

        $channel_type = store_state_channel_type($transaction['transaction_type'] ?? ($transaction['channel'] ?? 'ONLINE'));

        if (!isset($summary[$channel_type])) {
            $channel_type = 'ONLINE';
        }

        $amount = (float) ($transaction['amount'] ?? 0);
        $reference_key = (string) ($transaction['transaction_key'] ?? ((string) ($transaction['source'] ?? 'system') . ':' . (string) ($transaction['reference'] ?? '')));
        if (!isset($counted_references[$reference_key])) {
            $summary[$channel_type]['transactions']++;
            $counted_references[$reference_key] = true;
        }
        $summary[$channel_type]['revenue'] += $amount;

        $summary[$channel_type]['completed_revenue'] += $amount;
    }

    foreach ($summary as &$row) {
        $row['revenue'] = round((float) $row['revenue'], 2);
        $row['completed_revenue'] = round((float) $row['completed_revenue'], 2);
    }
    unset($row);

    $walkin_revenue = (float) $summary['WALK-IN']['revenue'];
    $online_revenue = (float) $summary['ONLINE']['revenue'];
    $difference = round(abs($walkin_revenue - $online_revenue), 2);
    $leader_type = $walkin_revenue >= $online_revenue ? 'WALK-IN' : 'ONLINE';
    $total_revenue = round($walkin_revenue + $online_revenue, 2);
    $total_transactions = (int) $summary['WALK-IN']['transactions'] + (int) $summary['ONLINE']['transactions'];

    return array(
        'channels' => $summary,
        'difference' => $difference,
        'leader_label' => $summary[$leader_type]['label'],
        'total_revenue' => $total_revenue,
        'total_transactions' => $total_transactions,
        'walkin_share' => $total_revenue > 0 ? round(($walkin_revenue / $total_revenue) * 100, 1) : 0,
        'online_share' => $total_revenue > 0 ? round(($online_revenue / $total_revenue) * 100, 1) : 0,
    );
}

function backoffice_pdf_escape($value)
{
    return shoestagram_pdf_escape($value);
}

function backoffice_output_simple_pdf($filename, $title, $lines)
{
    shoestagram_pdf_output($filename, shoestagram_build_text_report_pdf($title, $lines));
}
