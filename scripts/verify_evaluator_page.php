<?php
/** Read-only CLI rendering smoke test for protected back-office pages. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
$allowed = array(
    'admin' => array('dashboard.php', 'sales.php', 'manual-sales.php', 'reservations.php', 'inventory.php', 'activity-log.php', 'users.php', 'profile.php', 'pricing.php', 'forecasting.php', 'feedback.php', 'recommendations.php'),
    'staff' => array('dashboard.php', 'sales.php', 'reservations.php', 'inventory.php', 'recommendations.php', 'feedback.php', 'profile.php', 'support.php'),
    'customer' => array('account.php', 'cart.php'),
);
$role = (string) ($argv[1] ?? '');
$page = (string) ($argv[2] ?? '');
if (!isset($allowed[$role]) || !in_array($page, $allowed[$role], true)) {
    fwrite(STDERR, "Unsupported page.\n");
    exit(2);
}

session_save_path(sys_get_temp_dir());
session_start();
/* UI smoke tests use the existing safe local fallback and never make API calls. */
putenv('FORECAST_API_KEY=');
$_ENV['FORECAST_API_KEY'] = '';
require dirname(__DIR__) . '/database/config.php';
$row = database_fetch_one($conn, 'SELECT id FROM users WHERE role = ? AND is_active = 1 AND archived_at IS NULL ORDER BY id ASC LIMIT 1', 's', array($role));
if (!$row) {
    echo "NOT FULLY VERIFIED: No active account for this role.\n";
    exit(3);
}
$_SESSION['user_id'] = (int) $row['id'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/shoestagram/' . $role . '/' . $page;
parse_str((string) ($argv[3] ?? ''), $_GET);
unset($_GET['export']);
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] . ($_GET ? '?' . http_build_query($_GET) : '');
$_SERVER['HTTP_HOST'] = 'localhost';
$warnings = array();
set_error_handler(function ($severity, $message, $file, $line) use (&$warnings) {
    if (error_reporting() & $severity) {
        $warnings[] = basename((string) $file) . ':' . (int) $line . ':' . $severity;
    }
    return true;
});
ob_start();
try {
    chdir(dirname(__DIR__) . '/' . $role);
    require $page;
    $html = ob_get_clean();
    restore_error_handler();
    $ok = !$warnings && stripos($html, '<html') !== false && stripos($html, '</html>') !== false
        && stripos($html, 'Warning:') === false && stripos($html, 'Notice:') === false;
    $table_mismatches = 0;
    if (class_exists('DOMDocument')) {
        $previous_libxml_mode = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $ok = $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING) && $ok;
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//table') as $table) {
            $headers = $xpath->query('./thead/tr[1]/th', $table);
            $header_columns = 0;
            foreach ($headers as $header) {
                $header_columns += max(1, (int) $header->getAttribute('colspan'));
            }
            if ($header_columns === 0) {
                continue;
            }
            foreach ($xpath->query('./tbody/tr', $table) as $row) {
                $row_columns = 0;
                foreach ($xpath->query('./td', $row) as $cell) {
                    $row_columns += max(1, (int) $cell->getAttribute('colspan'));
                }
                if ($row_columns > 0 && $row_columns !== $header_columns) {
                    $table_mismatches++;
                }
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous_libxml_mode);
        $ok = $ok && $table_mismatches === 0;
    }
    if ($role === 'admin' && $page === 'reservations.php' && isset($xpath, $reservation_orders)) {
        $visible_rows = $xpath->query('//article[@id="reservationRecords"]//tbody/tr[td[8]]');
        $ok = $ok && $visible_rows->length === count($reservation_orders);
        foreach ($reservation_orders as $index => $order) {
            $cells = $xpath->query('./td', $visible_rows->item($index));
            if ($cells->length !== 8) {
                $ok = false;
                continue;
            }
            $reserved_at = backoffice_manila_datetime($order['created_at'] ?? '', true);
            $displayed_time = preg_replace('/\s+/', ' ', (string) $cells->item(6)->textContent);
            $ok = $ok && ($reserved_at
                ? strpos($displayed_time, $reserved_at->format('M j, Y')) !== false
                    && strpos($displayed_time, $reserved_at->format('g:i A')) !== false
                : strpos($displayed_time, 'Date unavailable') !== false);
            if (trim((string) ($_GET['status'] ?? '')) !== '') {
                $ok = $ok && (string) ($order['status'] ?? '') === (string) $_GET['status'];
            }
            if (trim((string) ($_GET['payment_status'] ?? '')) !== '') {
                $ok = $ok && (string) ($order['payment_status'] ?? '') === (string) $_GET['payment_status'];
            }
        }
    }
    if ($role === 'admin' && $page === 'users.php') {
        $admin_row = database_fetch_one($conn, "SELECT email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $customer_row = database_fetch_one($conn, "SELECT email FROM users WHERE role = 'customer' ORDER BY id ASC LIMIT 1");
        preg_match('/<table class="data-table">(.*?)<\/table>/s', $html, $table_match);
        $staff_table = $table_match[1] ?? '';
        $ok = $ok && $staff_table !== ''
            && strpos($staff_table, htmlspecialchars((string) ($admin_row['email'] ?? ''), ENT_QUOTES, 'UTF-8')) === false
            && strpos($staff_table, htmlspecialchars((string) ($customer_row['email'] ?? ''), ENT_QUOTES, 'UTF-8')) === false;
        if ((int) ($_GET['edit'] ?? 0) > 0) {
            $target = database_get_user_by_id($conn, (int) $_GET['edit']);
            if ($target && ($target['role'] ?? '') !== 'staff') {
                $ok = $ok && strpos($html, 'name="target_user_id" value="' . (int) $_GET['edit'] . '"') === false;
            }
        }
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $role . '/' . $page . ' rendered; warnings=' . count($warnings) . '; table_columns=' . ($table_mismatches === 0 ? 'valid' : 'invalid') . '; html=' . (strlen($html) > 0 ? 'yes' : 'no') . PHP_EOL;
    if (!$ok && $warnings) {
        echo 'Warning locations: ' . implode(', ', array_unique($warnings)) . PHP_EOL;
    }
    exit($ok ? 0 : 1);
} catch (Throwable $error) {
    ob_end_clean();
    restore_error_handler();
    echo 'FAIL ' . $role . '/' . $page . ' threw a server error.' . PHP_EOL;
    exit(1);
}
