<?php

/*
 * Load local development secrets before any application configuration is read.
 * Existing process/server variables take precedence over the local .env file.
 */
function shoestagram_load_env_file($path)
{
    static $loaded_paths = array();
    $path = (string) $path;

    if (isset($loaded_paths[$path])) {
        return $loaded_paths[$path];
    }

    if (!is_file($path) || !is_readable($path)) {
        $loaded_paths[$path] = false;
        return false;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);

    if (!is_array($lines)) {
        $loaded_paths[$path] = false;
        return false;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');

        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match('/\A[A-Z_][A-Z0-9_]*\z/', $name)) {
            continue;
        }

        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        /* Do not replace environment variables supplied by the web server. */
        if (getenv($name) !== false || array_key_exists($name, $_ENV)) {
            continue;
        }

        $_ENV[$name] = $value;
        @putenv($name . '=' . $value);
    }

    $loaded_paths[$path] = true;
    return true;
}

function shoestagram_env($name, $default = '')
{
    $name = (string) $name;
    $value = getenv($name);

    if ($value !== false) {
        return (string) $value;
    }

    return array_key_exists($name, $_ENV) ? (string) $_ENV[$name] : $default;
}

shoestagram_load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

require_once __DIR__ . '/catalog_seed.php';

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'shoestagram_db';

mysqli_report(MYSQLI_REPORT_OFF);

function database_connect_without_schema($host, $user, $password)
{
    $connection = @mysqli_connect($host, $user, $password);

    if ($connection) {
        @mysqli_set_charset($connection, 'utf8mb4');
    }

    return $connection;
}

function database_column_exists($connection, $table, $column)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);

    if ($table === '' || $column === '') {
        return false;
    }

    $result = @mysqli_query($connection, "SHOW COLUMNS FROM `" . $table . "` LIKE '" . mysqli_real_escape_string($connection, $column) . "'");
    return $result && mysqli_fetch_assoc($result);
}

function database_index_exists($connection, $table, $index)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $index = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $index);

    if ($table === '' || $index === '') {
        return false;
    }

    $result = @mysqli_query(
        $connection,
        "SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . mysqli_real_escape_string($connection, $index) . "'"
    );

    return $result && mysqli_fetch_assoc($result);
}

function database_foreign_key_exists($connection, $table, $constraint)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $constraint = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $constraint);
    if ($table === '' || $constraint === '') {
        return false;
    }

    $result = @mysqli_query(
        $connection,
        "SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = '" . mysqli_real_escape_string($connection, $table) . "'
           AND CONSTRAINT_NAME = '" . mysqli_real_escape_string($connection, $constraint) . "'
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'
         LIMIT 1"
    );
    return $result && mysqli_fetch_assoc($result);
}

function database_value_or_empty($value)
{
    return $value === null ? '' : (string) $value;
}

function database_encode_json($value)
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $encoded !== false ? $encoded : '[]';
}

function database_password_requirements_text()
{
    return 'Use at least 8 characters with 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.';
}

function database_validate_password_strength($password)
{
    $password = (string) $password;

    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must include at least one special character.';
    }

    return '';
}

function shoestagram_configured_account_email($environment_name, $fallback)
{
    $email = strtolower(trim(shoestagram_env($environment_name, $fallback)));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $fallback;
}

function shoestagram_admin_email()
{
    return shoestagram_configured_account_email('SHOESTAGRAM_ADMIN_EMAIL', 'shoestagram00@gmail.com');
}

function shoestagram_staff_email()
{
    return shoestagram_configured_account_email('SHOESTAGRAM_STAFF_EMAIL', 'shoestagramstaff@gmail.com');
}

function shoestagram_support_email()
{
    return shoestagram_admin_email();
}

function shoestagram_clean_mail_header($value)
{
    return trim(str_replace(array("\r", "\n"), '', (string) $value));
}

function shoestagram_mail_config()
{
    $path = __DIR__ . '/mail_config.php';
    $config = is_file($path) ? require $path : array();

    if (!is_array($config)) {
        $config = array();
    }

    return array_merge(array(
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => shoestagram_support_email(),
        'from_name' => 'Shoestagram',
        'verify_peer' => true,
    ), $config);
}

function shoestagram_smtp_read_response($socket)
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return array(
        'code' => (int) substr($response, 0, 3),
        'message' => $response,
    );
}

function shoestagram_mail_set_last_error($message)
{
    $GLOBALS['shoestagram_mail_last_error'] = (string) $message;
}

function shoestagram_mail_last_error()
{
    return isset($GLOBALS['shoestagram_mail_last_error'])
        ? (string) $GLOBALS['shoestagram_mail_last_error']
        : '';
}

function shoestagram_smtp_command($socket, $command, $expected_codes)
{
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }

    $response = shoestagram_smtp_read_response($socket);
    $ok = in_array((int) $response['code'], array_map('intval', (array) $expected_codes), true);

    if (!$ok) {
        shoestagram_mail_set_last_error(trim((string) $response['message']));
    }

    return $ok;
}

function shoestagram_smtp_escape_message($message)
{
    $message = preg_replace("/\r\n|\r|\n/", "\r\n", (string) $message);
    return preg_replace('/^\./m', '..', $message);
}

function shoestagram_send_smtp_email($to, $subject, $message, $reply_to = '')
{
    shoestagram_mail_set_last_error('');

    $config = shoestagram_mail_config();
    $host = trim((string) $config['host']);
    $port = (int) $config['port'];
    $username = trim((string) $config['username']);
    $password = preg_replace('/\s+/', '', (string) $config['password']);
    $from_email = filter_var(trim((string) $config['from_email']), FILTER_VALIDATE_EMAIL);
    $from_name = shoestagram_clean_mail_header($config['from_name']);
    $encryption = strtolower(trim((string) $config['encryption']));
    $verify_peer = !array_key_exists('verify_peer', $config) || (bool) $config['verify_peer'];

    if ($host === '' || $username === '' || $password === '' || !$from_email || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        shoestagram_mail_set_last_error('SMTP config is incomplete.');
        return false;
    }

    if (!in_array($encryption, array('tls', 'ssl', 'none'), true)) {
        shoestagram_mail_set_last_error('SMTP encryption setting is invalid.');
        return false;
    }

    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create(array(
        'ssl' => array(
            'peer_name' => $host,
            'SNI_enabled' => true,
            'verify_peer' => $verify_peer,
            'verify_peer_name' => $verify_peer,
            'allow_self_signed' => false,
        ),
    ));
    $socket = @stream_socket_client($target, $error_number, $error_message, 20, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        shoestagram_mail_set_last_error('SMTP connection failed: ' . $error_message);
        return false;
    }

    stream_set_timeout($socket, 20);
    $ok = shoestagram_smtp_command($socket, null, 220)
        && shoestagram_smtp_command($socket, 'EHLO localhost', 250);

    if ($ok && $encryption === 'tls') {
        $crypto_method = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
            ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            : STREAM_CRYPTO_METHOD_TLS_CLIENT;
        $ok = shoestagram_smtp_command($socket, 'STARTTLS', 220)
            && @stream_socket_enable_crypto($socket, true, $crypto_method)
            && shoestagram_smtp_command($socket, 'EHLO localhost', 250);

        if (!$ok && shoestagram_mail_last_error() === '') {
            shoestagram_mail_set_last_error('SMTP TLS negotiation failed.');
        }
    }

    $headers = array(
        'Date: ' . date('r'),
        'From: ' . $from_name . ' <' . $from_email . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    );

    if ($reply_to) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    $body = implode("\r\n", $headers) . "\r\n\r\n" . shoestagram_smtp_escape_message(wordwrap((string) $message, 70));

    $ok = $ok
        && shoestagram_smtp_command($socket, 'AUTH LOGIN', 334)
        && shoestagram_smtp_command($socket, base64_encode($username), 334)
        && shoestagram_smtp_command($socket, base64_encode($password), 235)
        && shoestagram_smtp_command($socket, 'MAIL FROM:<' . $from_email . '>', 250)
        && shoestagram_smtp_command($socket, 'RCPT TO:<' . $to . '>', array(250, 251))
        && shoestagram_smtp_command($socket, 'DATA', 354)
        && shoestagram_smtp_command($socket, $body . "\r\n.", 250);

    shoestagram_smtp_command($socket, 'QUIT', array(221, 250));
    fclose($socket);

    return $ok;
}

function shoestagram_send_email($to, $subject, $message, $reply_to = '')
{
    $to = filter_var(trim((string) $to), FILTER_VALIDATE_EMAIL);
    $subject = shoestagram_clean_mail_header($subject);
    $reply_to = filter_var(trim((string) $reply_to), FILTER_VALIDATE_EMAIL);

    if (!$to || $subject === '' || trim((string) $message) === '') {
        return false;
    }

    return shoestagram_send_smtp_email($to, $subject, $message, $reply_to);
}

function shoestagram_generate_verification_code()
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function shoestagram_normalize_verification_code($code)
{
    return preg_replace('/\D+/', '', (string) $code);
}

function shoestagram_mail_delivery_error($fallback)
{
    return (string) $fallback;
}

function shoestagram_pending_verification_codes($pending)
{
    $codes = array();

    if (isset($pending['codes']) && is_array($pending['codes'])) {
        foreach ($pending['codes'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $code = shoestagram_normalize_verification_code($entry['code'] ?? '');
            $expires_at = (int) ($entry['expires_at'] ?? 0);

            if ($code !== '' && $expires_at > 0) {
                $codes[] = array(
                    'code' => $code,
                    'expires_at' => $expires_at,
                );
            }
        }
    }

    $legacy_code = shoestagram_normalize_verification_code($pending['code'] ?? '');
    $legacy_expires_at = (int) ($pending['expires_at'] ?? 0);

    if ($legacy_code !== '' && $legacy_expires_at > 0) {
        $codes[] = array(
            'code' => $legacy_code,
            'expires_at' => $legacy_expires_at,
        );
    }

    return $codes;
}

function shoestagram_add_pending_verification_code($pending, $code, $ttl_seconds = 900)
{
    $code = shoestagram_normalize_verification_code($code);
    $expires_at = time() + max(60, (int) $ttl_seconds);
    $codes = shoestagram_pending_verification_codes(is_array($pending) ? $pending : array());
    $codes[] = array(
        'code' => $code,
        'expires_at' => $expires_at,
    );

    $codes = array_values(array_filter($codes, function ($entry) {
        return (int) ($entry['expires_at'] ?? 0) >= time();
    }));
    $codes = array_slice($codes, -5);

    $pending['code'] = $code;
    $pending['expires_at'] = $expires_at;
    $pending['codes'] = $codes;

    return $pending;
}

function shoestagram_pending_has_active_verification_code($pending)
{
    foreach (shoestagram_pending_verification_codes(is_array($pending) ? $pending : array()) as $entry) {
        if ((int) $entry['expires_at'] >= time()) {
            return true;
        }
    }

    return false;
}

function shoestagram_pending_verification_code_matches($pending, $input_code)
{
    $input_code = shoestagram_normalize_verification_code($input_code);

    if ($input_code === '') {
        return false;
    }

    foreach (shoestagram_pending_verification_codes(is_array($pending) ? $pending : array()) as $entry) {
        if ((int) $entry['expires_at'] >= time() && hash_equals((string) $entry['code'], $input_code)) {
            return true;
        }
    }

    return false;
}

function database_ensure_schema($host, $user, $password, $database)
{
    $bootstrap = database_connect_without_schema($host, $user, $password);

    if (!$bootstrap) {
        die('Database connection failed. Start MySQL in XAMPP and verify your root credentials.');
    }

    @mysqli_query(
        $bootstrap,
        "CREATE DATABASE IF NOT EXISTS `" . preg_replace('/[^a-zA-Z0-9_]/', '', (string) $database) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    mysqli_close($bootstrap);

    $connection = @mysqli_connect($host, $user, $password, $database);

    if (!$connection) {
        die('Database connection failed for schema "' . htmlspecialchars($database, ENT_QUOTES, 'UTF-8') . '".');
    }

    @mysqli_set_charset($connection, 'utf8mb4');
    return $connection;
}

function database_ensure_tables($connection)
{
    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','customer','staff') NOT NULL DEFAULT 'customer',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        address TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        archived_at DATETIME NULL DEFAULT NULL,
        failed_login_attempts INT NOT NULL DEFAULT 0,
        last_failed_login DATETIME NULL DEFAULT NULL,
        locked_until DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!database_column_exists($connection, 'users', 'phone')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT '' AFTER role");
    }

    if (!database_column_exists($connection, 'users', 'address')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN address TEXT NULL AFTER phone");
    }

    if (!database_column_exists($connection, 'users', 'is_active')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER address");
    }

    if (!database_column_exists($connection, 'users', 'archived_at')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER is_active");
    }

    if (!database_column_exists($connection, 'users', 'failed_login_attempts')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0 AFTER archived_at");
    }

    if (!database_column_exists($connection, 'users', 'last_failed_login')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN last_failed_login DATETIME NULL DEFAULT NULL AFTER failed_login_attempts");
    }

    if (!database_column_exists($connection, 'users', 'locked_until')) {
        @mysqli_query($connection, "ALTER TABLE users ADD COLUMN locked_until DATETIME NULL DEFAULT NULL AFTER last_failed_login");
    }

    @mysqli_query($connection, "ALTER TABLE users MODIFY role ENUM('admin','customer','staff') NOT NULL DEFAULT 'customer'");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY,
        slug VARCHAR(190) NOT NULL UNIQUE,
        name VARCHAR(190) NOT NULL,
        brand VARCHAR(120) NOT NULL,
        category VARCHAR(80) NOT NULL,
        style VARCHAR(120) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        old_price DECIMAL(10,2) NULL DEFAULT NULL,
        cost_min DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        cost_max DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        badge VARCHAR(80) NOT NULL DEFAULT 'Core Pick',
        tag_label VARCHAR(80) NOT NULL DEFAULT 'core',
        featured TINYINT(1) NOT NULL DEFAULT 0,
        trending TINYINT(1) NOT NULL DEFAULT 0,
        best_seller TINYINT(1) NOT NULL DEFAULT 0,
        limited TINYINT(1) NOT NULL DEFAULT 0,
        new_arrival TINYINT(1) NOT NULL DEFAULT 0,
        condition_label VARCHAR(60) NOT NULL DEFAULT 'Brand New',
        base_stock INT NOT NULL DEFAULT 0,
        rating DECIMAL(3,1) NOT NULL DEFAULT 4.5,
        reviews_count INT NOT NULL DEFAULT 0,
        likes_count INT NOT NULL DEFAULT 0,
        reservations_seed INT NOT NULL DEFAULT 0,
        sales_count_seed INT NOT NULL DEFAULT 0,
        trend_score INT NOT NULL DEFAULT 60,
        tone VARCHAR(120) NOT NULL DEFAULT '',
        colorway VARCHAR(120) NOT NULL DEFAULT '',
        blurb TEXT NULL,
        description TEXT NULL,
        image_url VARCHAR(255) NOT NULL DEFAULT '',
        preference_tags LONGTEXT NULL,
        sizes LONGTEXT NULL,
        size_standards LONGTEXT NULL,
        gallery LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!database_column_exists($connection, 'products', 'size_standards')) {
        @mysqli_query($connection, "ALTER TABLE products ADD COLUMN size_standards LONGTEXT NULL AFTER sizes");
    }

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS historical_import_batches (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_key VARCHAR(80) NOT NULL,
        workbook_name VARCHAR(255) NOT NULL DEFAULT '',
        workbook_sha256 CHAR(64) NOT NULL,
        coverage_start DATE NOT NULL,
        coverage_end DATE NOT NULL,
        inventory_as_of DATE NOT NULL,
        verification_status VARCHAR(60) NOT NULL DEFAULT 'unverified_generated',
        import_status VARCHAR(30) NOT NULL DEFAULT 'completed',
        transaction_records INT NOT NULL DEFAULT 0,
        transaction_units INT NOT NULL DEFAULT 0,
        transaction_revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        inventory_rows_applied INT NOT NULL DEFAULT 0,
        inventory_rows_skipped INT NOT NULL DEFAULT 0,
        restock_records INT NOT NULL DEFAULT 0,
        source_note VARCHAR(500) NOT NULL DEFAULT '',
        created_by INT NULL DEFAULT NULL,
        created_by_name VARCHAR(150) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_historical_import_batch_key (batch_key),
        UNIQUE KEY uq_historical_import_workbook_sha256 (workbook_sha256),
        INDEX idx_historical_import_coverage (coverage_start, coverage_end),
        INDEX idx_historical_import_inventory_as_of (inventory_as_of),
        CONSTRAINT fk_historical_import_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS historical_import_products (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        import_batch_id BIGINT UNSIGNED NOT NULL,
        product_id INT NOT NULL,
        history_start DATE NOT NULL,
        history_end DATE NOT NULL,
        transaction_records INT NOT NULL DEFAULT 0,
        units_sold INT NOT NULL DEFAULT 0,
        revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        ending_stock INT NOT NULL DEFAULT 0,
        inventory_reconciled TINYINT(1) NOT NULL DEFAULT 0,
        inventory_applied TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_historical_import_product (import_batch_id, product_id),
        INDEX idx_historical_import_products_product (product_id, import_batch_id),
        CONSTRAINT fk_historical_import_products_batch FOREIGN KEY (import_batch_id) REFERENCES historical_import_batches(id) ON DELETE CASCADE,
        CONSTRAINT fk_historical_import_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS inventory_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        actor_name VARCHAR(150) NOT NULL DEFAULT '',
        actor_email VARCHAR(190) NOT NULL DEFAULT '',
        actor_role VARCHAR(40) NOT NULL DEFAULT '',
        action_type VARCHAR(80) NOT NULL DEFAULT '',
        stock_before INT NOT NULL DEFAULT 0,
        stock_after INT NOT NULL DEFAULT 0,
        quantity_change INT NOT NULL DEFAULT 0,
        note TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inventory_logs_product_id (product_id),
        CONSTRAINT fk_inventory_logs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!database_column_exists($connection, 'inventory_logs', 'external_reference')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD COLUMN external_reference VARCHAR(80) NULL DEFAULT NULL AFTER action_type");
    }
    if (!database_column_exists($connection, 'inventory_logs', 'import_batch_id')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD COLUMN import_batch_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER external_reference");
    }
    if (!database_column_exists($connection, 'inventory_logs', 'event_date')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD COLUMN event_date DATE NULL DEFAULT NULL AFTER import_batch_id");
    }
    if (!database_column_exists($connection, 'inventory_logs', 'affects_live_stock')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD COLUMN affects_live_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER event_date");
    }
    if (!database_index_exists($connection, 'inventory_logs', 'uq_inventory_logs_external_reference')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD UNIQUE KEY uq_inventory_logs_external_reference (external_reference)");
    }
    if (!database_index_exists($connection, 'inventory_logs', 'idx_inventory_logs_import_batch')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD INDEX idx_inventory_logs_import_batch (import_batch_id)");
    }
    if (!database_foreign_key_exists($connection, 'inventory_logs', 'fk_inventory_logs_import_batch')) {
        @mysqli_query($connection, "ALTER TABLE inventory_logs ADD CONSTRAINT fk_inventory_logs_import_batch FOREIGN KEY (import_batch_id) REFERENCES historical_import_batches(id) ON DELETE RESTRICT");
    }

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS activity_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL DEFAULT NULL,
        user_name VARCHAR(150) NOT NULL DEFAULT '',
        user_email VARCHAR(190) NOT NULL DEFAULT '',
        user_role VARCHAR(40) NOT NULL DEFAULT '',
        activity_type VARCHAR(80) NOT NULL,
        description VARCHAR(1000) NOT NULL DEFAULT '',
        status VARCHAR(30) NOT NULL DEFAULT 'Success',
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_logs_created (created_at, id),
        INDEX idx_activity_logs_user (user_id),
        INDEX idx_activity_logs_email (user_email),
        INDEX idx_activity_logs_role (user_role),
        INDEX idx_activity_logs_type (activity_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        size_standard VARCHAR(40) NOT NULL DEFAULT 'US',
        size VARCHAR(60) NOT NULL DEFAULT '',
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cart_user_product_size (user_id, product_id, size),
        INDEX idx_cart_user_id (user_id),
        INDEX idx_cart_product_id (product_id),
        CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!database_column_exists($connection, 'cart', 'size')) {
        @mysqli_query($connection, "ALTER TABLE cart ADD COLUMN size VARCHAR(60) NOT NULL DEFAULT '' AFTER product_id");
    }

    if (!database_column_exists($connection, 'cart', 'size_standard')) {
        @mysqli_query($connection, "ALTER TABLE cart ADD COLUMN size_standard VARCHAR(40) NOT NULL DEFAULT 'US' AFTER product_id");
    }

    @mysqli_query($connection, "ALTER TABLE cart DROP INDEX uq_cart_user_product_size");
    @mysqli_query($connection, "ALTER TABLE cart ADD UNIQUE KEY uq_cart_user_product_size_standard (user_id, product_id, size_standard, size)");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
        INDEX idx_wishlist_user_id (user_id),
        INDEX idx_wishlist_product_id (product_id),
        CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS product_promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        title VARCHAR(160) NOT NULL,
        original_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        status ENUM('Active','Paused','Expired') NOT NULL DEFAULT 'Active',
        starts_at DATE NULL DEFAULT NULL,
        ends_at DATE NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_product_promotions_product_id (product_id),
        INDEX idx_product_promotions_status_dates (status, starts_at, ends_at),
        CONSTRAINT fk_product_promotions_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connection, "CREATE TABLE IF NOT EXISTS historical_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        sale_date DATE NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        transaction_type ENUM('walk_in','online') NOT NULL,
        payment_method VARCHAR(80) NOT NULL DEFAULT '',
        size_standard VARCHAR(40) NOT NULL DEFAULT '',
        size VARCHAR(60) NOT NULL DEFAULT '',
        source ENUM('manual') NOT NULL DEFAULT 'manual',
        notes VARCHAR(500) NOT NULL DEFAULT '',
        created_by INT NULL DEFAULT NULL,
        created_by_name VARCHAR(150) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_historical_sales_date (sale_date),
        INDEX idx_historical_sales_product_date (product_id, sale_date),
        INDEX idx_historical_sales_channel_date (transaction_type, sale_date),
        CONSTRAINT fk_historical_sales_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
        CONSTRAINT fk_historical_sales_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!database_column_exists($connection, 'historical_sales', 'external_reference')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD COLUMN external_reference VARCHAR(80) NULL DEFAULT NULL AFTER id");
    }
    if (!database_column_exists($connection, 'historical_sales', 'provenance')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD COLUMN provenance ENUM('manual_entry','historical_import') NOT NULL DEFAULT 'manual_entry' AFTER source");
    }
    if (!database_column_exists($connection, 'historical_sales', 'import_batch_id')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD COLUMN import_batch_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER provenance");
    }
    if (!database_column_exists($connection, 'historical_sales', 'is_client_verified')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD COLUMN is_client_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER import_batch_id");
    }
    if (!database_column_exists($connection, 'historical_sales', 'original_payment_method')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD COLUMN original_payment_method VARCHAR(80) NOT NULL DEFAULT '' AFTER payment_method");
    }
    if (!database_index_exists($connection, 'historical_sales', 'uq_historical_sales_external_reference')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD UNIQUE KEY uq_historical_sales_external_reference (external_reference)");
    }
    if (!database_index_exists($connection, 'historical_sales', 'idx_historical_sales_import_batch')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD INDEX idx_historical_sales_import_batch (import_batch_id)");
    }
    if (!database_foreign_key_exists($connection, 'historical_sales', 'fk_historical_sales_import_batch')) {
        @mysqli_query($connection, "ALTER TABLE historical_sales ADD CONSTRAINT fk_historical_sales_import_batch FOREIGN KEY (import_batch_id) REFERENCES historical_import_batches(id) ON DELETE RESTRICT");
    }
}

function database_seed_products($connection)
{
    $count_result = @mysqli_query($connection, "SELECT COUNT(*) AS count FROM products");
    $row = $count_result ? mysqli_fetch_assoc($count_result) : array('count' => 0);

    if ((int) ($row['count'] ?? 0) > 0) {
        return;
    }

    $statement = @mysqli_prepare(
        $connection,
        "INSERT INTO products (
            id, slug, name, brand, category, style, price, old_price, cost_min, cost_max, badge, tag_label,
            featured, trending, best_seller, limited, new_arrival, condition_label, base_stock, rating,
            reviews_count, likes_count, reservations_seed, sales_count_seed, trend_score, tone, colorway,
            blurb, description, image_url, preference_tags, sizes, gallery
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )"
    );

    if (!$statement) {
        return;
    }

    foreach (shoestagram_catalog_seed_products() as $product) {
        $old_price = $product['old_price'] !== null ? (float) $product['old_price'] : null;
        $featured = !empty($product['featured']) ? 1 : 0;
        $trending = !empty($product['trending']) ? 1 : 0;
        $best_seller = !empty($product['best_seller']) ? 1 : 0;
        $limited = !empty($product['limited']) ? 1 : 0;
        $new_arrival = !empty($product['new_arrival']) ? 1 : 0;
        $preference_tags = database_encode_json($product['preference_tags']);
        $sizes = database_encode_json($product['sizes']);
        $gallery = database_encode_json($product['gallery']);

        mysqli_stmt_bind_param(
            $statement,
            'isssssddddssiiiiisidiiiiissssssss',
            $product['id'],
            $product['slug'],
            $product['name'],
            $product['brand'],
            $product['category'],
            $product['style'],
            $product['price'],
            $old_price,
            $product['cost_min'],
            $product['cost_max'],
            $product['badge'],
            $product['tag'],
            $featured,
            $trending,
            $best_seller,
            $limited,
            $new_arrival,
            $product['condition'],
            $product['stock'],
            $product['rating'],
            $product['reviews_count'],
            $product['likes'],
            $product['reservations'],
            $product['sales_count'],
            $product['trend_score'],
            $product['tone'],
            $product['colorway'],
            $product['blurb'],
            $product['description'],
            $product['image'],
            $preference_tags,
            $sizes,
            $gallery
        );
        @mysqli_stmt_execute($statement);
    }

    mysqli_stmt_close($statement);
}

function database_seed_user($connection, $fullname, $email, $password, $role)
{
    $check_statement = @mysqli_prepare($connection, "SELECT id FROM users WHERE email = ? LIMIT 1");

    if (!$check_statement) {
        return;
    }

    mysqli_stmt_bind_param($check_statement, 's', $email);
    mysqli_stmt_execute($check_statement);
    $result = mysqli_stmt_get_result($check_statement);
    $existing_user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($check_statement);

    if ($existing_user) {
        return;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insert_statement = @mysqli_prepare(
        $connection,
        "INSERT INTO users (fullname, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)"
    );

    if (!$insert_statement) {
        return;
    }

    mysqli_stmt_bind_param($insert_statement, 'ssss', $fullname, $email, $hashed_password, $role);
    @mysqli_stmt_execute($insert_statement);
    mysqli_stmt_close($insert_statement);
}

function database_get_first_user_by_role($connection, $role)
{
    $role = trim((string) $role);

    if (!in_array($role, array('admin', 'staff', 'customer'), true)) {
        return null;
    }

    return database_fetch_one(
        $connection,
        "SELECT id, fullname, email, role, phone, address, is_active, archived_at, created_at, updated_at
         FROM users
         WHERE role = ?
         ORDER BY id ASC
         LIMIT 1",
        's',
        array($role)
    );
}

function database_migrate_role_account_email($connection, $role, $target_email, $legacy_email)
{
    $role = trim((string) $role);
    $target_email = strtolower(trim((string) $target_email));
    $legacy_email = strtolower(trim((string) $legacy_email));

    if (!in_array($role, array('admin', 'staff'), true) || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $target_owner = database_get_user_by_email($connection, $target_email);
    if ($target_owner) {
        return (string) $target_owner['role'] === $role;
    }

    $candidate = $legacy_email !== '' ? database_get_user_by_email($connection, $legacy_email) : null;
    if (!$candidate || (string) $candidate['role'] !== $role) {
        $candidate = database_get_first_user_by_role($connection, $role);
    }

    if (!$candidate) {
        return false;
    }

    return database_execute(
        $connection,
        "UPDATE users SET email = ? WHERE id = ? AND role = ?",
        'sis',
        array($target_email, (int) $candidate['id'], $role)
    );
}

function database_configure_role_accounts($connection)
{
    return array(
        'admin' => database_migrate_role_account_email(
            $connection,
            'admin',
            shoestagram_admin_email(),
            'admin@shoestagram.com'
        ),
        'staff' => database_migrate_role_account_email(
            $connection,
            'staff',
            shoestagram_staff_email(),
            'staff@shoestagram.com'
        ),
    );
}

function database_seed_privileged_user_from_environment($connection, $fullname, $email, $password_environment_name, $role)
{
    if (database_get_first_user_by_role($connection, $role)) {
        return;
    }

    $password = shoestagram_env($password_environment_name, '');
    if ($password === '' || database_validate_password_strength($password) !== '') {
        return;
    }

    database_seed_user($connection, $fullname, $email, $password, $role);
}

function database_bootstrap_defaults($connection)
{
    database_seed_privileged_user_from_environment(
        $connection,
        'Shoestagram Admin',
        shoestagram_admin_email(),
        'SHOESTAGRAM_ADMIN_INITIAL_PASSWORD',
        'admin'
    );
    database_seed_privileged_user_from_environment(
        $connection,
        'Store Staff',
        shoestagram_staff_email(),
        'SHOESTAGRAM_STAFF_INITIAL_PASSWORD',
        'staff'
    );
    database_seed_user($connection, 'Demo Customer', 'demo@shoestagram.com', 'demo123', 'customer');
}

function database_fetch_one($connection, $query, $types = '', $params = array())
{
    $statement = @mysqli_prepare($connection, $query);

    if (!$statement) {
        return null;
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    return $row ?: null;
}

function database_fetch_all($connection, $query, $types = '', $params = array())
{
    $statement = @mysqli_prepare($connection, $query);

    if (!$statement) {
        return array();
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $rows = array();

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($statement);
    return $rows;
}

function database_execute($connection, $query, $types = '', $params = array())
{
    $statement = @mysqli_prepare($connection, $query);

    if (!$statement) {
        return false;
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    $ok = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    return $ok;
}

function database_get_user_by_id($connection, $user_id)
{
    return database_fetch_one(
        $connection,
        "SELECT id, fullname, email, role, phone, address, is_active, archived_at, created_at, updated_at FROM users WHERE id = ? LIMIT 1",
        'i',
        array((int) $user_id)
    );
}

function database_get_user_with_password($connection, $user_id)
{
    return database_fetch_one(
        $connection,
        "SELECT id, fullname, email, password, role, phone, address, is_active, archived_at, created_at, updated_at FROM users WHERE id = ? LIMIT 1",
        'i',
        array((int) $user_id)
    );
}

function database_get_user_by_email($connection, $email)
{
    return database_fetch_one(
        $connection,
        "SELECT id, fullname, email, role, phone, address, is_active, archived_at, created_at, updated_at FROM users WHERE email = ? LIMIT 1",
        's',
        array(trim((string) $email))
    );
}

function database_get_login_user($connection, $email)
{
    return database_fetch_one(
        $connection,
        "SELECT id, fullname, email, password, role, phone, address, is_active, archived_at,
                failed_login_attempts, last_failed_login, locked_until, created_at, updated_at
         FROM users
         WHERE email = ?
         LIMIT 1",
        's',
        array(strtolower(trim((string) $email)))
    );
}

function database_login_max_attempts()
{
    return 5;
}

function database_login_lock_seconds()
{
    return 5 * 60;
}

function database_login_lock_remaining($user)
{
    $locked_until = strtotime((string) ($user['locked_until'] ?? ''));
    return $locked_until ? max(0, $locked_until - time()) : 0;
}

function database_reset_login_attempts($connection, $user_id)
{
    return database_execute(
        $connection,
        "UPDATE users
         SET failed_login_attempts = 0, last_failed_login = NULL, locked_until = NULL
         WHERE id = ?",
        'i',
        array((int) $user_id)
    );
}

function database_record_failed_login($connection, $user)
{
    $user_id = (int) ($user['id'] ?? 0);
    if ($user_id <= 0) {
        return array('attempts' => 0, 'locked' => false, 'locked_until' => '');
    }

    $attempts = max(0, (int) ($user['failed_login_attempts'] ?? 0));
    $last_failed = strtotime((string) ($user['last_failed_login'] ?? ''));

    if ($last_failed && (time() - $last_failed) >= database_login_lock_seconds()) {
        $attempts = 0;
    }

    $attempts++;
    $locked = $attempts >= database_login_max_attempts();
    $locked_until = $locked ? date('Y-m-d H:i:s', time() + database_login_lock_seconds()) : '';

    $ok = database_execute(
        $connection,
        "UPDATE users
         SET failed_login_attempts = ?, last_failed_login = NOW(), locked_until = ?
         WHERE id = ?",
        'isi',
        array($attempts, $locked ? $locked_until : null, $user_id)
    );

    return array(
        'attempts' => $attempts,
        'locked' => $ok && $locked,
        'locked_until' => $locked_until,
    );
}

function database_activity_types()
{
    return array(
        'Login',
        'Failed Login',
        'Login Lockout',
        'Logout',
        'Account',
        'Order',
        'Payment',
        'Inventory',
        'Historical Sales',
        'Review',
    );
}

function database_activity_actor_from_session()
{
    return array(
        'id' => (int) ($_SESSION['user_id'] ?? 0),
        'name' => (string) ($_SESSION['user_name'] ?? ''),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'role' => (string) ($_SESSION['user_role'] ?? ''),
    );
}

function database_log_activity($connection, $actor, $activity_type, $description, $status = 'Success', $attempted_email = '')
{
    $actor = is_array($actor) ? $actor : array();
    $user_id = (int) ($actor['id'] ?? $actor['user_id'] ?? 0);
    $user_id = $user_id > 0 ? $user_id : null;
    $user_name = trim((string) ($actor['fullname'] ?? $actor['name'] ?? $actor['user_name'] ?? ''));
    $user_email = strtolower(trim((string) ($actor['email'] ?? $actor['user_email'] ?? $attempted_email)));
    $user_role = strtolower(trim((string) ($actor['role'] ?? $actor['user_role'] ?? '')));
    $activity_type = trim((string) $activity_type);
    $description = trim((string) $description);
    $status = ucfirst(strtolower(trim((string) $status)));
    $ip_address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($activity_type === '' || !in_array($status, array('Success', 'Failed', 'Locked', 'Info'), true)) {
        return false;
    }

    if (strlen($description) > 1000) {
        $description = substr($description, 0, 1000);
    }
    if (strlen($ip_address) > 45) {
        $ip_address = substr($ip_address, 0, 45);
    }

    return database_execute(
        $connection,
        "INSERT INTO activity_logs
         (user_id, user_name, user_email, user_role, activity_type, description, status, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'isssssss',
        array($user_id, $user_name, $user_email, $user_role, $activity_type, $description, $status, $ip_address)
    );
}

function database_get_activity_log_page($connection, $filters = array(), $page = 1, $per_page = 30)
{
    $filters = is_array($filters) ? $filters : array();
    $where = array('1 = 1');
    $types = '';
    $params = array();
    $date_from = trim((string) ($filters['date_from'] ?? ''));
    $date_to = trim((string) ($filters['date_to'] ?? ''));
    $search = trim((string) ($filters['search'] ?? ''));
    $role = strtolower(trim((string) ($filters['role'] ?? '')));
    $activity_type = trim((string) ($filters['activity_type'] ?? ''));
    $status = ucfirst(strtolower(trim((string) ($filters['status'] ?? ''))));

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $where[] = 'created_at >= ?';
        $types .= 's';
        $params[] = $date_from . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $where[] = 'created_at <= ?';
        $types .= 's';
        $params[] = $date_to . ' 23:59:59';
    }
    if ($search !== '') {
        $where[] = '(user_name LIKE ? OR user_email LIKE ? OR description LIKE ?)';
        $needle = '%' . $search . '%';
        $types .= 'sss';
        array_push($params, $needle, $needle, $needle);
    }
    if (in_array($role, array('admin', 'staff', 'customer'), true)) {
        $where[] = 'user_role = ?';
        $types .= 's';
        $params[] = $role;
    }
    if (in_array($activity_type, database_activity_types(), true)) {
        $where[] = 'activity_type = ?';
        $types .= 's';
        $params[] = $activity_type;
    }
    if (in_array($status, array('Success', 'Failed', 'Locked', 'Info'), true)) {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $where_sql = implode(' AND ', $where);
    $count_row = database_fetch_one(
        $connection,
        'SELECT COUNT(*) AS total FROM activity_logs WHERE ' . $where_sql,
        $types,
        $params
    );
    $total = (int) ($count_row['total'] ?? 0);
    $per_page = min(100, max(10, (int) $per_page));
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($pages, max(1, (int) $page));
    $offset = ($page - 1) * $per_page;
    $rows = database_fetch_all(
        $connection,
        'SELECT id, user_id, user_name, user_email, user_role, activity_type, description, status, ip_address, created_at
         FROM activity_logs
         WHERE ' . $where_sql . '
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $per_page . ' OFFSET ' . $offset,
        $types,
        $params
    );

    return array(
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $per_page,
    );
}

function database_get_role_notification_emails($connection, $roles)
{
    $roles = array_values(array_unique(array_filter(array_map('strval', (array) $roles), function ($role) {
        return in_array($role, array('admin', 'staff'), true);
    })));

    if (empty($roles)) {
        return array();
    }

    $placeholders = implode(', ', array_fill(0, count($roles), '?'));
    $rows = database_fetch_all(
        $connection,
        "SELECT email
         FROM users
         WHERE role IN (" . $placeholders . ")
           AND is_active = 1
           AND archived_at IS NULL
         ORDER BY id ASC",
        str_repeat('s', count($roles)),
        $roles
    );
    $emails = array();

    foreach ($rows as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }

    return array_values(array_unique($emails));
}

function shoestagram_send_role_email($connection, $roles, $subject, $message, $reply_to = '')
{
    $recipients = database_get_role_notification_emails($connection, $roles);
    if (empty($recipients)) {
        shoestagram_mail_set_last_error('No active recipients are configured for this role notification.');
        return false;
    }

    $sent_all = true;
    foreach ($recipients as $recipient) {
        if (!shoestagram_send_email($recipient, $subject, $message, $reply_to)) {
            $sent_all = false;
        }
    }

    return $sent_all;
}

function database_user_is_archived($user)
{
    return trim((string) ($user['archived_at'] ?? '')) !== '';
}

function database_user_archive_is_eligible($user, $months = 3)
{
    if (!$user || database_user_is_archived($user)) {
        return false;
    }

    $created_at = strtotime((string) ($user['created_at'] ?? ''));

    if (!$created_at) {
        return false;
    }

    return $created_at <= strtotime('-' . max(1, (int) $months) . ' months');
}

function database_user_account_age_days($user)
{
    $created_at = strtotime((string) ($user['created_at'] ?? ''));

    if (!$created_at) {
        return 0;
    }

    return max(0, (int) floor((time() - $created_at) / 86400));
}

function database_sync_session_user($connection, $user_id)
{
    $user = database_get_user_by_id($connection, $user_id);

    if (!$user) {
        return false;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) $user['fullname'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role'] = (string) $user['role'];

    return true;
}

function database_create_user_record($connection, $payload)
{
    $fullname = trim((string) ($payload['fullname'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');
    $role = trim((string) ($payload['role'] ?? 'customer'));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $address = trim((string) ($payload['address'] ?? ''));

    if ($fullname === '' || $email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('ok' => false, 'message' => 'Complete name, email, and password are required.');
    }

    if (!in_array($role, array('admin', 'staff', 'customer'), true)) {
        $role = 'customer';
    }

    if (database_get_user_by_email($connection, $email)) {
        return array('ok' => false, 'message' => 'That email already exists.');
    }

    $password_error = database_validate_password_strength($password);
    if ($password_error !== '') {
        return array('ok' => false, 'message' => $password_error);
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $ok = database_execute(
        $connection,
        "INSERT INTO users (fullname, email, password, role, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
        'ssssss',
        array($fullname, $email, $hashed_password, $role, $phone, $address)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'User account created.' : 'User account could not be created.',
        'user_id' => $ok ? (int) mysqli_insert_id($connection) : 0,
    );
}

function database_update_user_record($connection, $user_id, $payload)
{
    $user = database_get_user_by_id($connection, $user_id);

    if (!$user) {
        return array('ok' => false, 'message' => 'User not found.');
    }

    $fullname = trim((string) ($payload['fullname'] ?? $user['fullname']));
    $email = strtolower(trim((string) ($payload['email'] ?? $user['email'])));
    $role = trim((string) ($payload['role'] ?? $user['role']));
    $phone = trim((string) ($payload['phone'] ?? $user['phone']));
    $address = trim((string) ($payload['address'] ?? $user['address']));
    $is_active = database_user_is_archived($user)
        ? 0
        : (isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : (int) $user['is_active']);

    if ($fullname === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('ok' => false, 'message' => 'A valid name and email are required.');
    }

    if (!in_array($role, array('admin', 'staff', 'customer'), true)) {
        $role = 'customer';
    }

    $email_owner = database_get_user_by_email($connection, $email);
    if ($email_owner && (int) $email_owner['id'] !== (int) $user_id) {
        return array('ok' => false, 'message' => 'That email is already assigned to another user.');
    }

    $ok = database_execute(
        $connection,
        "UPDATE users SET fullname = ?, email = ?, role = ?, phone = ?, address = ?, is_active = ? WHERE id = ?",
        'sssssii',
        array($fullname, $email, $role, $phone, $address, $is_active, (int) $user_id)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'User account updated.' : 'User account could not be updated.',
        'old_email' => (string) $user['email'],
        'new_email' => $email,
    );
}

function database_update_user_password($connection, $user_id, $current_password, $new_password, $require_current = true)
{
    $user = database_get_user_with_password($connection, $user_id);

    if (!$user) {
        return array('ok' => false, 'message' => 'User account not found.');
    }

    if (database_user_is_archived($user)) {
        return array('ok' => false, 'message' => 'Archived accounts cannot reset passwords.');
    }

    $current_password = (string) $current_password;
    $new_password = (string) $new_password;

    if ($require_current && !password_verify($current_password, (string) $user['password'])) {
        return array('ok' => false, 'message' => 'The current password did not match.');
    }

    $password_error = database_validate_password_strength($new_password);
    if ($password_error !== '') {
        return array('ok' => false, 'message' => $password_error);
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $ok = database_execute(
        $connection,
        "UPDATE users
         SET password = ?, failed_login_attempts = 0, last_failed_login = NULL, locked_until = NULL
         WHERE id = ?",
        'si',
        array($hashed, (int) $user_id)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Password updated successfully.' : 'Password could not be updated.',
    );
}

function database_delete_user_record($connection, $user_id)
{
    return database_execute($connection, "DELETE FROM users WHERE id = ?", 'i', array((int) $user_id));
}

function database_archive_user_record($connection, $user_id)
{
    $user = database_get_user_by_id($connection, $user_id);

    if (!$user) {
        return array('ok' => false, 'message' => 'User not found.');
    }

    if (database_user_is_archived($user)) {
        return array('ok' => false, 'message' => 'This account is already archived.');
    }

    if (!database_user_archive_is_eligible($user, 3)) {
        return array('ok' => false, 'message' => 'Accounts can only be archived once they are at least 3 months old.');
    }

    $ok = database_execute(
        $connection,
        "UPDATE users SET is_active = 0, archived_at = NOW() WHERE id = ?",
        'i',
        array((int) $user_id)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'User archived successfully.' : 'User could not be archived.',
    );
}

function database_log_inventory_action($connection, $product_id, $actor, $action_type, $stock_before, $stock_after, $note = '')
{
    $product_id = (int) $product_id;

    if ($product_id <= 0) {
        return false;
    }

    $actor_name = trim((string) ($actor['name'] ?? ''));
    $actor_email = trim((string) ($actor['email'] ?? ''));
    $actor_role = trim((string) ($actor['role'] ?? ''));
    $actor_id = (int) ($actor['id'] ?? 0);
    $stock_before = (int) $stock_before;
    $stock_after = (int) $stock_after;
    $quantity_change = $stock_after - $stock_before;
    $action_type = trim((string) $action_type);
    $note = trim((string) $note);

    $ok = database_execute(
        $connection,
        "INSERT INTO inventory_logs (product_id, actor_name, actor_email, actor_role, action_type, stock_before, stock_after, quantity_change, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'issssiiis',
        array($product_id, $actor_name, $actor_email, $actor_role, $action_type, $stock_before, $stock_after, $quantity_change, $note)
    );

    if ($ok) {
        $product = database_fetch_one($connection, 'SELECT name FROM products WHERE id = ? LIMIT 1', 'i', array($product_id));
        $product_name = (string) ($product['name'] ?? ('Product #' . $product_id));
        database_log_activity(
            $connection,
            array('id' => $actor_id, 'name' => $actor_name, 'email' => $actor_email, 'role' => $actor_role),
            'Inventory',
            $product_name . ': stock changed from ' . $stock_before . ' to ' . $stock_after . ' (' . ($quantity_change >= 0 ? '+' : '') . $quantity_change . ').',
            'Success'
        );
    }

    return $ok;
}

function database_get_inventory_logs($connection, $limit = 40)
{
    $limit = max(1, (int) $limit);
    return database_fetch_all(
        $connection,
        "SELECT l.*, p.name AS product_name
         FROM inventory_logs l
         LEFT JOIN products p ON p.id = l.product_id
         ORDER BY COALESCE(l.event_date, DATE(l.created_at)) DESC, l.created_at DESC, l.id DESC
         LIMIT " . $limit
    );
}

function database_get_inventory_logs_page($connection, $requested_page = 1, $per_page = 20)
{
    $per_page = max(1, min(100, (int) $per_page));
    $count = database_fetch_one($connection, 'SELECT COUNT(*) AS total FROM inventory_logs');
    $total = (int) ($count['total'] ?? 0);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($pages, max(1, (int) $requested_page));
    $offset = ($page - 1) * $per_page;
    $rows = database_fetch_all($connection,
        "SELECT l.*, p.name AS product_name
         FROM inventory_logs l
         LEFT JOIN products p ON p.id = l.product_id
         ORDER BY COALESCE(l.event_date, DATE(l.created_at)) DESC, l.created_at DESC, l.id DESC
         LIMIT " . $per_page . " OFFSET " . $offset);
    return array('rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $per_page, 'key' => 'log_page');
}

$conn = database_ensure_schema($host, $user, $password, $database);
database_ensure_tables($conn);
database_seed_products($conn);
database_configure_role_accounts($conn);
database_bootstrap_defaults($conn);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}
