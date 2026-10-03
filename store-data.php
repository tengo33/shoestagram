<?php

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/store_state.php';
require_once __DIR__ . '/database/forecast_api.php';
require_once __DIR__ . '/database/review_seed.php';

function store_db_connection()
{
    global $conn;
    return $conn;
}

function store_currency_code()
{
    return 'PHP';
}

function store_format_money($amount)
{
    return store_currency_code() . ' ' . number_format((float) $amount, 2);
}

function store_manual_sale_channels()
{
    return array(
        'walk_in' => 'Walk-in',
        'online' => 'Online',
    );
}

function store_manual_sale_payment_methods()
{
    return array('In-Store Payment', 'Online Payment');
}

function store_manual_sale_source_label($source = 'manual', $provenance = '')
{
    if (strtolower(trim((string) $source)) !== 'manual') {
        return 'Shoestagram Transaction';
    }

    return strtolower(trim((string) $provenance)) === 'historical_import'
        ? 'Initialized Historical Import'
        : 'Manual Historical Entry';
}

function store_latest_historical_import_batch()
{
    static $batch = null;
    static $loaded = false;

    if ($loaded) {
        return $batch;
    }

    $loaded = true;
    $batch = database_fetch_one(
        store_db_connection(),
        "SELECT *
         FROM historical_import_batches
         WHERE import_status = 'completed'
         ORDER BY coverage_end DESC, id DESC
         LIMIT 1"
    );

    return $batch ?: null;
}

function store_system_order_is_after_historical_import($order)
{
    $batch = store_latest_historical_import_batch();
    if (!$batch) {
        return true;
    }

    $coverageEnd = trim((string) ($batch['coverage_end'] ?? ''));
    $orderDate = substr((string) ($order['created_at'] ?? ''), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $coverageEnd) !== 1
        || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate) === 1 && $orderDate > $coverageEnd);
}

function store_transaction_counts_for_analytics($transaction)
{
    return !array_key_exists('counts_for_analytics', (array) $transaction)
        || !empty($transaction['counts_for_analytics']);
}

function store_get_product_import_coverage($productId)
{
    return database_fetch_one(
        store_db_connection(),
        "SELECT hip.*, hib.verification_status, hib.coverage_start, hib.coverage_end
         FROM historical_import_products hip
         INNER JOIN historical_import_batches hib ON hib.id = hip.import_batch_id
         WHERE hip.product_id = ? AND hib.import_status = 'completed'
         ORDER BY hib.coverage_end DESC, hib.id DESC
         LIMIT 1",
        'i',
        array((int) $productId)
    );
}

function store_get_historical_units_by_product()
{
    $rows = database_fetch_all(
        store_db_connection(),
        "SELECT product_id, COALESCE(SUM(quantity), 0) AS units
         FROM historical_sales
         WHERE source = 'manual'
         GROUP BY product_id"
    );
    $units = array();
    foreach ($rows as $row) {
        $units[(int) ($row['product_id'] ?? 0)] = max(0, (int) ($row['units'] ?? 0));
    }
    return $units;
}

function store_get_manual_sales($filters = array(), $limit = 0, $offset = 0)
{
    $filters = is_array($filters) ? $filters : array();
    $where = array("hs.source = 'manual'");
    $types = '';
    $params = array();

    $month = trim((string) ($filters['month'] ?? ''));
    $has_month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    if ($has_month) {
        $where[] = "DATE_FORMAT(hs.sale_date, '%Y-%m') = ?";
        $types .= 's';
        $params[] = $month;
    }

    $year = (int) ($filters['year'] ?? 0);
    if (!$has_month && $year >= 2000 && $year <= 2100) {
        $where[] = 'YEAR(hs.sale_date) = ?';
        $types .= 'i';
        $params[] = $year;
    }

    $product_id = (int) ($filters['product_id'] ?? 0);
    if ($product_id > 0) {
        $where[] = 'hs.product_id = ?';
        $types .= 'i';
        $params[] = $product_id;
    }

    $channel = strtolower(trim((string) ($filters['channel'] ?? '')));
    if (isset(store_manual_sale_channels()[$channel])) {
        $where[] = 'hs.transaction_type = ?';
        $types .= 's';
        $params[] = $channel;
    }

    $query = "SELECT hs.*, p.name AS product_name, p.slug AS product_slug,
                     hib.verification_status AS import_verification_status
              FROM historical_sales hs
              INNER JOIN products p ON p.id = hs.product_id
              LEFT JOIN historical_import_batches hib ON hib.id = hs.import_batch_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY hs.sale_date DESC, hs.id DESC";
    if ((int) $limit > 0) {
        $query .= ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) $offset);
    }

    return database_fetch_all(store_db_connection(), $query, $types, $params);
}

function store_get_manual_sale($id)
{
    return database_fetch_one(
        store_db_connection(),
        "SELECT hs.*, p.name AS product_name, hib.verification_status AS import_verification_status
         FROM historical_sales hs
         INNER JOIN products p ON p.id = hs.product_id
         LEFT JOIN historical_import_batches hib ON hib.id = hs.import_batch_id
         WHERE hs.id = ? AND hs.source = 'manual'
         LIMIT 1",
        'i',
        array((int) $id)
    );
}

function store_validate_manual_sale_input($input)
{
    $input = is_array($input) ? $input : array();
    $errors = array();
    $product_id = (int) ($input['product_id'] ?? 0);
    $product = get_store_product_by_id($product_id);
    $sale_date = trim((string) ($input['sale_date'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $sale_date);
    $date_errors = DateTimeImmutable::getLastErrors();
    $date_is_valid = $date instanceof DateTimeImmutable
        && ($date_errors === false || (((int) ($date_errors['warning_count'] ?? 0)) === 0 && ((int) ($date_errors['error_count'] ?? 0)) === 0))
        && $date->format('Y-m-d') === $sale_date;
    $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
    $unit_price_raw = trim((string) ($input['unit_price'] ?? ''));
    $unit_price = $unit_price_raw === '' && $product ? (float) ($product['price'] ?? 0) : filter_var($unit_price_raw, FILTER_VALIDATE_FLOAT);
    $channel = strtolower(trim((string) ($input['transaction_type'] ?? '')));
    $payment_method = trim((string) ($input['payment_method'] ?? ''));
    $size_standard = trim((string) ($input['size_standard'] ?? ''));
    $size = trim((string) ($input['size'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));

    if (!$product) {
        $errors[] = 'Select a valid product.';
    }
    if (!$date_is_valid) {
        $errors[] = 'Enter a valid sale date.';
    } elseif ($date > new DateTimeImmutable('today')) {
        $errors[] = 'Historical sale dates cannot be in the future.';
    }
    if ($quantity === false || (int) $quantity <= 0) {
        $errors[] = 'Quantity sold must be greater than zero.';
    } elseif ((int) $quantity > 1000000) {
        $errors[] = 'Quantity sold is too large for one historical record.';
    }
    if ($unit_price === false || (float) $unit_price < 0) {
        $errors[] = 'Unit price must be zero or greater.';
    } elseif ((float) $unit_price > 99999999.99) {
        $errors[] = 'Unit price is outside the supported range.';
    }
    if ($quantity !== false && $unit_price !== false && (int) $quantity > 0 && (float) $unit_price >= 0
        && ((int) $quantity * (float) $unit_price) > 9999999999.99) {
        $errors[] = 'The calculated total is outside the supported range.';
    }
    if (!isset(store_manual_sale_channels()[$channel])) {
        $errors[] = 'Select either Walk-in or Online as the transaction channel.';
    }
    if (!in_array($payment_method, store_manual_sale_payment_methods(), true)) {
        $errors[] = 'Select a supported payment method.';
    }
    if ($size_standard !== '' && !isset(store_size_standard_labels()[store_normalize_size_standard($size_standard)])) {
        $errors[] = 'Select a supported size standard.';
    }
    if (strlen($size) > 60) {
        $errors[] = 'Size must be 60 characters or fewer.';
    }
    if (strlen($notes) > 500) {
        $errors[] = 'Notes must be 500 characters or fewer.';
    }

    $quantity = $quantity === false ? 0 : (int) $quantity;
    $unit_price = $unit_price === false ? 0 : round((float) $unit_price, 2);

    return array(
        'ok' => empty($errors),
        'errors' => $errors,
        'data' => array(
            'product_id' => $product_id,
            'sale_date' => $sale_date,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_amount' => round($quantity * $unit_price, 2),
            'transaction_type' => $channel,
            'payment_method' => $payment_method,
            'size_standard' => $size === '' ? '' : store_normalize_size_standard($size_standard),
            'size' => $size,
            'notes' => $notes,
        ),
    );
}

function store_save_manual_sale($input, $id = 0, $actor = array())
{
    $validated = store_validate_manual_sale_input($input);
    if (!$validated['ok']) {
        return $validated;
    }

    $data = $validated['data'];
    $connection = store_db_connection();
    $id = (int) $id;
    $previous = $id > 0 ? store_get_manual_sale($id) : null;

    if ($id > 0) {
        if (!$previous) {
            return array('ok' => false, 'errors' => array('The historical sale no longer exists.'), 'data' => $data);
        }

        $ok = database_execute(
            $connection,
            "UPDATE historical_sales
             SET product_id = ?, sale_date = ?, quantity = ?, unit_price = ?, total_amount = ?, transaction_type = ?, payment_method = ?, size_standard = ?, size = ?, notes = ?
             WHERE id = ? AND source = 'manual'",
            'isiddsssssi',
            array($data['product_id'], $data['sale_date'], $data['quantity'], $data['unit_price'], $data['total_amount'], $data['transaction_type'], $data['payment_method'], $data['size_standard'], $data['size'], $data['notes'], $id)
        );
    } else {
        $actor_id = (int) ($actor['id'] ?? 0);
        $actor_id = $actor_id > 0 ? $actor_id : null;
        $actor_name = trim((string) ($actor['fullname'] ?? $actor['name'] ?? 'Administrator'));
        $ok = database_execute(
            $connection,
            "INSERT INTO historical_sales
             (product_id, sale_date, quantity, unit_price, total_amount, transaction_type, payment_method, size_standard, size, source, notes, created_by, created_by_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?, ?)",
            'isiddsssssis',
            array($data['product_id'], $data['sale_date'], $data['quantity'], $data['unit_price'], $data['total_amount'], $data['transaction_type'], $data['payment_method'], $data['size_standard'], $data['size'], $data['notes'], $actor_id, $actor_name)
        );
        if ($ok) {
            $id = (int) mysqli_insert_id($connection);
        }
    }

    if (!$ok) {
        return array('ok' => false, 'errors' => array('The historical sale could not be saved.'), 'data' => $data);
    }

    store_invalidate_forecast_cache((int) $data['product_id']);
    if ($previous && (int) ($previous['product_id'] ?? 0) !== (int) $data['product_id']) {
        store_invalidate_forecast_cache((int) ($previous['product_id'] ?? 0));
    }

    return array('ok' => true, 'errors' => array(), 'data' => $data, 'id' => $id);
}

function store_delete_manual_sale($id)
{
    $id = (int) $id;
    $existing = $id > 0 ? store_get_manual_sale($id) : null;
    $deleted = $existing && database_execute(
        store_db_connection(),
        "DELETE FROM historical_sales WHERE id = ? AND source = 'manual'",
        'i',
        array($id)
    );
    if ($deleted) {
        store_invalidate_forecast_cache((int) ($existing['product_id'] ?? 0));
    }
    return $deleted;
}

function store_manual_sales_summary($filters = array())
{
    $summary = array('total_sales' => 0.0, 'walk_in_sales' => 0.0, 'online_sales' => 0.0, 'units_sold' => 0, 'records' => 0);
    foreach (store_get_manual_sales($filters) as $sale) {
        $amount = (float) ($sale['total_amount'] ?? 0);
        $summary['total_sales'] += $amount;
        $summary['units_sold'] += max(0, (int) ($sale['quantity'] ?? 0));
        $summary['records']++;
        if (($sale['transaction_type'] ?? '') === 'walk_in') {
            $summary['walk_in_sales'] += $amount;
        } elseif (($sale['transaction_type'] ?? '') === 'online') {
            $summary['online_sales'] += $amount;
        }
    }

    return $summary;
}

function store_gcash_qr_public_path($prefix = '')
{
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'Products meow';
    $matches = glob($directory . DIRECTORY_SEPARATOR . 'gcashqr.*') ?: array();
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');

    foreach ($matches as $match) {
        $extension = strtolower(pathinfo($match, PATHINFO_EXTENSION));

        if (in_array($extension, $allowed_extensions, true)) {
            return (string) $prefix . 'Products%20meow/' . rawurlencode(basename($match));
        }
    }

    return '';
}

function store_payment_proof_absolute_path($relative_path)
{
    $relative_path = trim((string) $relative_path);

    if ($relative_path === '') {
        return '';
    }

    return __DIR__ . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relative_path);
}

function store_handle_payment_proof_upload($field_name, $required)
{
    if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
        return $required
            ? array('ok' => false, 'message' => 'Upload your GCash proof of payment screenshot before reserving.')
            : array('ok' => true, 'path' => '', 'original_name' => '');
    }

    $file = $_FILES[$field_name];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return $required
            ? array('ok' => false, 'message' => 'Upload your GCash proof of payment screenshot before reserving.')
            : array('ok' => true, 'path' => '', 'original_name' => '');
    }

    if ($error !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'message' => 'The proof of payment image could not be uploaded. Please try again.');
    }

    $max_size = 5 * 1024 * 1024;

    if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > $max_size) {
        return array('ok' => false, 'message' => 'Upload a JPG, PNG, or WEBP proof image under 5 MB.');
    }

    $original_name = (string) ($file['name'] ?? 'payment-proof');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');

    if (!in_array($extension, $allowed_extensions, true)) {
        return array('ok' => false, 'message' => 'Proof of payment must be a JPG, JPEG, PNG, or WEBP image.');
    }

    $image_info = @getimagesize((string) ($file['tmp_name'] ?? ''));

    if ($image_info === false) {
        return array('ok' => false, 'message' => 'The uploaded proof file is not a valid image.');
    }

    $allowed_mimes = array('image/jpeg', 'image/png', 'image/webp');
    $mime = strtolower((string) ($image_info['mime'] ?? ''));

    if (!in_array($mime, $allowed_mimes, true)) {
        return array('ok' => false, 'message' => 'Proof of payment must be a JPG, PNG, or WEBP image.');
    }

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment-proofs';

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        return array('ok' => false, 'message' => 'The proof upload folder is not available right now.');
    }

    try {
        $random = bin2hex(random_bytes(6));
    } catch (Exception $exception) {
        $random = substr(md5(uniqid('', true)), 0, 12);
    }

    $safe_filename = 'payment-proof-' . date('Ymd-His') . '-' . $random . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    $target_path = $upload_dir . DIRECTORY_SEPARATOR . $safe_filename;

    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target_path)) {
        return array('ok' => false, 'message' => 'The proof of payment image could not be saved. Please try again.');
    }

    return array(
        'ok' => true,
        'path' => 'uploads/payment-proofs/' . $safe_filename,
        'original_name' => $original_name,
    );
}

function store_get_order_by_id($order_id)
{
    $order_id = trim((string) $order_id);

    if ($order_id === '') {
        return null;
    }

    foreach (store_state_get_orders() as $order) {
        if ((string) ($order['id'] ?? '') === $order_id) {
            return $order;
        }
    }

    return null;
}

function store_group_checkout_orders($orders)
{
    $groups = array();
    foreach ((array) $orders as $order) {
        $reference = store_state_checkout_reference($order);
        if (!isset($groups[$reference])) {
            $groups[$reference] = $order;
            $groups[$reference]['checkout_id'] = $reference;
            $groups[$reference]['items'] = array();
            $groups[$reference]['subtotal'] = 0.0;
        }
        $groups[$reference]['items'][] = $order;
        $groups[$reference]['subtotal'] += (float) ($order['subtotal'] ?? 0);
    }
    $groups = array_values($groups);
    usort($groups, function ($left, $right) {
        $date = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        return $date !== 0 ? $date : strcmp((string) ($right['checkout_id'] ?? ''), (string) ($left['checkout_id'] ?? ''));
    });
    return $groups;
}

function store_order_email_body($heading, $order)
{
    $items = store_state_checkout_items((string) ($order['id'] ?? ''));
    if (!$items) {
        $items = array($order);
    }
    $body = $heading . "\n\n"
        . 'Order reference: ' . store_state_checkout_reference($order) . "\n"
        . 'Customer: ' . (string) ($order['customer_name'] ?? '') . "\n"
        . 'Email: ' . (string) ($order['customer_email'] ?? '') . "\n\nItems:\n";
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float) ($item['subtotal'] ?? 0);
        $body .= '- ' . (string) ($item['product_name'] ?? 'Product') . ' / ' . store_order_size_display($item)
            . ' x ' . (int) ($item['quantity'] ?? 1) . ' = ' . store_format_money((float) ($item['subtotal'] ?? 0)) . "\n";
    }
    return $body . 'Total: ' . store_format_money($total) . "\n"
        . 'Payment method: ' . (string) ($order['payment_method'] ?? 'Not specified') . "\n"
        . 'Payment status: ' . (string) ($order['payment_status'] ?? 'Pending') . "\n"
        . (!empty($order['payment_proof']) ? "Payment proof: Submitted for team review\n" : '')
        . 'Status: ' . (string) ($order['status'] ?? '') . "\n"
        . 'Pickup: ' . (string) ($order['pickup_window'] ?? '') . "\n\n"
        . "Thank you for shopping with Shoestagram.\n";
}

function store_send_order_email($order, $subject, $heading)
{
    $customer_email = filter_var(trim((string) ($order['customer_email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $body = store_order_email_body($heading, $order);
    $sent_customer = $customer_email ? shoestagram_send_email($customer_email, $subject, $body) : true;
    $sent_team = shoestagram_send_role_email(
        store_db_connection(),
        array('admin', 'staff'),
        $subject,
        $body,
        $customer_email ?: ''
    );

    return $sent_customer && $sent_team;
}

function store_is_placeholder_image($image)
{
    return strpos((string) $image, 'https://placehold.co/') === 0;
}

function store_catalog_media_lookup_key($value)
{
    $value = strtolower((string) $value);
    $value = preg_replace('/\([^)]*\)/', '', $value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);

    return trim((string) $value);
}

function store_catalog_media_lookup_key_full($value)
{
    $value = strtolower((string) $value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);

    return trim((string) $value);
}

function store_catalog_media_lookup_keys($value)
{
    $keys = array(
        store_catalog_media_lookup_key_full($value),
        store_catalog_media_lookup_key($value),
    );

    return array_values(array_unique(array_filter($keys)));
}

function store_catalog_media_directory()
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'products';
}

function store_catalog_media_public_path($filename)
{
    return '../products/' . str_replace('%2F', '/', rawurlencode((string) $filename));
}

function store_catalog_media_file_map()
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $map = array();
    $directory = store_catalog_media_directory();

    if (!is_dir($directory)) {
        return $map;
    }

    $files = glob($directory . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp,avif}', GLOB_BRACE);

    if (!is_array($files)) {
        return $map;
    }

    foreach ($files as $path) {
        $filename = basename($path);
        $lookup_key = store_catalog_media_lookup_key(pathinfo($filename, PATHINFO_FILENAME));

        if ($lookup_key !== '' && !isset($map[$lookup_key])) {
            $map[$lookup_key] = $filename;
        }
    }

    return $map;
}

function store_catalog_media_overrides()
{
    return array(
        'chicagobullsshorts' => 'Chicago bulls Short.png',
        'chicagobullsjersey' => 'Chicago Bulls Jersey.png',
        'khakishorts' => 'Khaki shorts.png',
        'denimskirt' => 'Denim Skirt.png',
        'adidaslongsleeveshirt' => 'Nike black Longsleeve.png',
        'adiletteslippers' => 'Adilletteslippers.png',
        'adidasblacksportsshirt' => 'Addidasblacksports.png',
        'nikeblackwhitesneakers' => 'Nike Black Shoes.png',
        'nikegreenrunningshoes' => 'Nike Green Running Shoes.png',
        'nikewhiterunningshoes' => 'Nike White Running shoes.png',
        'nikesbsneakers' => 'Nike Sb sneakers red.png',
        'onitsukatigerblackandwhitesneakers' => 'Onitsuka Black_white.png',
        'onitsukatigeryellowsneakers' => 'Onitsuka yellow.png',
        'onitsukatigerwhitebluesneakers' => 'Onitsuka white and blue.png',
        'adidaswhitesneakers' => 'White Samba Addidas.png',
        'nikecortezwhitered' => 'Nike Cortez White_blue.png',
        'leatherloafers' => 'Black Loafers leather shoes.png',
        'mustardnikesocks' => 'Nike Yellow socks.png',
        'nikeblacksocks' => 'Nike Black Socks.png',
        'nikewhitesocks' => 'Nike Socks White.png',
        'olopoloshirt' => 'olo polo Green_.png',
        'havaianasslippers' => 'Havainas slides brown.png',
        'chanelblacksandals' => 'Channel Black sandals.png',
        'hellokittypinkslides' => 'Hello kitty pink slides.png',
        'adidaswhiteblackslippers' => 'Adilletteslippers.png',
        'crocsblueclogs' => 'Crocs slide black_white.png',
        'crocsbeigeslides' => 'Crocs beige.png',
        'crocscreamclogs' => 'Crocs brown_white.png',
        'crocsoffwhiteclogs' => 'Crocs White.jpg',
        'jordangrayslides' => 'Black Jordan Slides Gray.png',
        'adidasblueslides' => 'AddidasSlidesNavyblue.png',
        'hermeswhitesandals' => 'Hermes White Sandal.jpg',
        'nikeslideslippersbluecamo' => 'Nike slides blue.png',
        'nikeslideslippers' => 'Nike slides blue.png',
        'jordanslideslippersgreen' => 'Jordan Slide black_white.png',
        'jordanslideslippers' => 'Jordan Slide black_white.png',
        'nikebenassislideslipperswhiteblack' => 'Nike Slides white.png',
        'nikebenassislideslippers' => 'Nike Slides white.png',
        'leatherflatshoes' => 'Black leather shoes girls.png',
        'nikelowcutsneakerswhitebrown' => 'Nike Brown.jpg',
        'nikelowcutsneakers' => 'Nike Brown.jpg',
        'nikeairmaxstylesneakerstealwhite' => 'Nike Airmax style.png',
        'nikeairmaxstylesneakers' => 'Nike Airmax style.png',
        'blackleatherformalshoes' => 'Black leather shoes one strap.png',
        'niketnairmaxplus' => 'Nike Airplus Red.png',
        'bapeshoes' => 'Bape shoes white.png',
        'nikeairforce1' => 'AF1NIKEWHITE.png',
        'newbalance530' => 'White_gray new balance.png',
        'whitenewbalancecasualshoes' => 'White new balance.png',
        'adidascampusskatestyleyellow' => 'AddidasYellow_black.png',
        'adidascampusskatestyle' => 'AddidasYellow_black.png',
        'pumasuedeblack' => 'Puma Suede navy blue.png',
        'pumasuedebrown' => 'Puma Suede Brown.jpg',
        'nikep6000black' => 'Nike P-600.jpg',
        'nikep6000' => 'Nike P-600.jpg',
        'nikeairjordan4blackcatstyle' => 'Jordan Black shoes.png',
        'tdrunningshoes' => 'Td running shoes.png',
        'nikeshoxshoxstylewhite' => 'Nike shox white.png',
        'nikeshoxshoxstyle' => 'Nike shox white.png',
        'pumacasualsuedebrown' => 'Puma suede brown_green.png',
        'pumacasualsuede' => 'Puma suede brown_green.png',
        'adidasresponserunner' => 'AddidasResponseRunner.jpg',
        'pumasuedeplatformpink' => 'Pink Puma Suede.jpg',
        'pumasuedeplatform' => 'Pink Puma Suede.jpg',
        'vansoldskoolblackwhite' => 'Vans Shool Black_white.png',
        'vansoldskool' => 'Vans Shool Black_white.png',
        'vansoldskoolbandanaprint' => 'Vans school Bandana.png',
        'niketnairmaxplusblack' => 'Nike airmax black.png',
        'oncloudmonsteronrunning' => 'Cloud tech shoes.png',
        'nikecourttennisshoesmintpink' => 'Nike court_tennis shoes.png',
        'nikecourttennisshoes' => 'Nike court_tennis shoes.png',
    );
}

function store_catalog_media_match_filename($product_name)
{
    $product_name = trim((string) $product_name);

    if ($product_name === '') {
        return '';
    }

    $lookup_key = store_catalog_media_lookup_key($product_name);
    $lookup_keys = store_catalog_media_lookup_keys($product_name);
    $overrides = store_catalog_media_overrides();

    foreach ($lookup_keys as $key) {
        if (isset($overrides[$key])) {
            return (string) $overrides[$key];
        }
    }

    $files = store_catalog_media_file_map();

    foreach ($lookup_keys as $key) {
        if (isset($files[$key])) {
            return (string) $files[$key];
        }
    }

    return $lookup_key !== '' && isset($files[$lookup_key]) ? (string) $files[$lookup_key] : '';
}

function store_catalog_media_match_path($product_name)
{
    $filename = store_catalog_media_match_filename($product_name);

    if ($filename === '') {
        return '';
    }

    return store_catalog_media_public_path($filename);
}

function store_catalog_gallery_needs_refresh($gallery)
{
    if (empty($gallery)) {
        return true;
    }

    foreach ($gallery as $image) {
        $image = trim((string) $image);

        if ($image !== '' && !store_is_placeholder_image($image)) {
            return false;
        }
    }

    return true;
}

function store_catalog_managed_media_path($image)
{
    $image = trim((string) $image);

    return strpos($image, '../products/') === 0 || strpos($image, 'products/') === 0;
}

function store_catalog_gallery_uses_managed_media($gallery)
{
    if (empty($gallery)) {
        return true;
    }

    foreach ($gallery as $image) {
        $image = trim((string) $image);

        if ($image !== '' && !store_is_placeholder_image($image) && !store_catalog_managed_media_path($image)) {
            return false;
        }
    }

    return true;
}

function store_catalog_gallery_from_image($name, $image, $palette)
{
    if ($image !== '' && !store_is_placeholder_image($image)) {
        return array($image, $image, $image);
    }

    return array(
        $image,
        shoestagram_catalog_placeholder($name . ' Detail', $palette[0], $palette[1]),
        shoestagram_catalog_placeholder($name . ' Angle', $palette[0], $palette[1]),
    );
}

function store_resolve_product_media($name, $image, $gallery = array(), $position = 1)
{
    $name = trim((string) $name);
    $image = trim((string) $image);
    $position = max(1, (int) $position);
    $palette = shoestagram_catalog_palette($position);
    $matched_local_image = store_catalog_media_match_path($name);

    if ($matched_local_image !== '' && ($image === '' || store_is_placeholder_image($image) || store_catalog_managed_media_path($image))) {
        $image = $matched_local_image;
    }

    if ($image === '') {
        $image = shoestagram_catalog_placeholder($name, $palette[0], $palette[1]);
    }

    if (store_catalog_gallery_needs_refresh($gallery) || ($matched_local_image !== '' && store_catalog_gallery_uses_managed_media($gallery))) {
        $gallery = store_catalog_gallery_from_image($name, $image, $palette);
    } else {
        $gallery = array_values(array_map(function ($entry) use ($image) {
            $entry = trim((string) $entry);

            if ($entry === '' || store_is_placeholder_image($entry)) {
                return $image;
            }

            return $entry;
        }, $gallery));
    }

    return array(
        'image' => $image,
        'gallery' => $gallery,
    );
}

function get_store_categories()
{
    return array(
        'all' => 'All Products',
        'shoes' => 'Shoes',
        'slippers' => 'Slippers',
        'sandals' => 'Sandals',
        'tops' => 'Tops',
        'bottoms' => 'Bottoms',
        'socks' => 'Socks',
        'accessories' => 'Accessories',
    );
}

function get_store_category_label($category)
{
    $categories = get_store_categories();
    return $categories[$category] ?? ucwords(str_replace('_', ' ', (string) $category));
}

function get_store_budget_options()
{
    return array(
        '500' => 'PHP 500 and below',
        '1000' => 'PHP 1,000 and below',
        '2500' => 'PHP 2,500 and below',
        '5000' => 'PHP 5,000 and below',
        '8000' => 'PHP 8,000 and below',
        '12000' => 'PHP 12,000 and below',
        '15000' => 'PHP 15,000 and below',
    );
}

function get_store_price_ranges()
{
    return array(
        'below-1000' => array('label' => 'Below PHP 1,000', 'min' => 0, 'max' => 999.99),
        '1000-2999' => array('label' => 'PHP 1,000 - PHP 2,999', 'min' => 1000, 'max' => 2999.99),
        '3000-4999' => array('label' => 'PHP 3,000 - PHP 4,999', 'min' => 3000, 'max' => 4999.99),
        '5000-7999' => array('label' => 'PHP 5,000 - PHP 7,999', 'min' => 5000, 'max' => 7999.99),
        '8000-up' => array('label' => 'PHP 8,000 and above', 'min' => 8000, 'max' => 0),
    );
}

function store_price_range_bounds($key)
{
    $ranges = get_store_price_ranges();
    $key = trim((string) $key);

    return $ranges[$key] ?? array('label' => '', 'min' => 0, 'max' => 0);
}

function store_price_within_bounds($price, $min_price = 0, $max_price = 0)
{
    $price = (float) $price;
    $min_price = (float) $min_price;
    $max_price = (float) $max_price;

    if ($min_price > 0 && $price < $min_price) {
        return false;
    }

    if ($max_price > 0 && $price > $max_price) {
        return false;
    }

    return true;
}

function store_size_standard_labels()
{
    return array(
        'US' => 'US',
        'EU' => 'EU',
        'CN' => 'China/Asian',
    );
}

function store_normalize_size_standard($value)
{
    $value = strtoupper(trim((string) $value));

    if ($value === 'CHINA' || $value === 'ASIAN' || $value === 'CHINA/ASIAN') {
        return 'CN';
    }

    return in_array($value, array('US', 'EU', 'CN'), true) ? $value : 'US';
}

function store_size_standard_label($value)
{
    $labels = store_size_standard_labels();
    $key = store_normalize_size_standard($value);

    return $labels[$key] ?? 'US';
}

function store_size_display($size, $size_standard = 'US')
{
    $size = trim((string) $size);

    if ($size === '') {
        return 'Size not selected';
    }

    return store_size_standard_label($size_standard) . ' ' . $size;
}

function store_order_size_display($order)
{
    return store_size_display($order['size'] ?? '', $order['size_standard'] ?? 'US');
}

function store_product_all_sizes($product)
{
    $sizes = array();
    $standards = isset($product['size_standards']) && is_array($product['size_standards'])
        ? $product['size_standards']
        : array();

    foreach ($standards as $standard_sizes) {
        foreach ((array) $standard_sizes as $size) {
            $size = trim((string) $size);

            if ($size !== '') {
                $sizes[] = $size;
            }
        }
    }

    foreach ((array) ($product['sizes'] ?? array()) as $size) {
        $size = trim((string) $size);

        if ($size !== '') {
            $sizes[] = $size;
        }
    }

    return array_values(array_unique($sizes));
}

function store_product_size_search_terms($product)
{
    $terms = store_product_all_sizes($product);
    $standards = isset($product['size_standards']) && is_array($product['size_standards'])
        ? $product['size_standards']
        : array();

    foreach ($standards as $standard => $standard_sizes) {
        foreach ((array) $standard_sizes as $size) {
            $size = trim((string) $size);

            if ($size !== '') {
                $terms[] = store_size_standard_label($standard) . ' ' . $size;
            }
        }
    }

    return array_values(array_unique($terms));
}

function store_convert_us_shoe_size_to_eu($size)
{
    $numeric = (float) $size;

    if ($numeric <= 0) {
        return (string) $size;
    }

    return (string) (int) round($numeric + 33);
}

function store_convert_us_shoe_size_to_cn($size)
{
    $numeric = (float) $size;

    if ($numeric <= 0) {
        return (string) $size;
    }

    return (string) (int) round($numeric + 34);
}

function store_default_size_standards($sizes, $category)
{
    $sizes = array_values(array_filter(array_map('strval', is_array($sizes) ? $sizes : array())));
    $category = strtolower(trim((string) $category));

    if (empty($sizes)) {
        return array('US' => array(), 'EU' => array(), 'CN' => array());
    }

    if (in_array($category, array('shoes', 'slippers', 'sandals'), true)) {
        return array(
            'US' => $sizes,
            'EU' => array_values(array_unique(array_map('store_convert_us_shoe_size_to_eu', $sizes))),
            'CN' => array_values(array_unique(array_map('store_convert_us_shoe_size_to_cn', $sizes))),
        );
    }

    return array(
        'US' => $sizes,
        'EU' => $sizes,
        'CN' => $sizes,
    );
}

function store_normalize_size_standards($value, $fallback_sizes = array(), $category = 'shoes')
{
    $decoded = is_array($value) ? $value : array();

    if (!is_array($value) && is_string($value) && trim($value) !== '') {
        $parsed = json_decode($value, true);
        $decoded = is_array($parsed) ? $parsed : array();
    }

    $standards = array();

    foreach (store_size_standard_labels() as $key => $label) {
        $raw = $decoded[$key] ?? $decoded[$label] ?? array();
        $items = is_array($raw) ? $raw : store_parse_multivalue_text($raw);
        $standards[$key] = array_values(array_unique(array_filter(array_map('strval', $items), function ($item) {
            return trim((string) $item) !== '';
        })));
    }

    if (empty($standards['US']) && empty($standards['EU']) && empty($standards['CN'])) {
        $standards = store_default_size_standards($fallback_sizes, $category);
    }

    return $standards;
}

function store_primary_sizes_from_standards($standards, $fallback_sizes = array())
{
    foreach (array('US', 'EU', 'CN') as $key) {
        if (!empty($standards[$key]) && is_array($standards[$key])) {
            return array_values($standards[$key]);
        }
    }

    return array_values(is_array($fallback_sizes) ? $fallback_sizes : array());
}

function store_decode_json_list($value)
{
    if (is_array($value)) {
        return array_values($value);
    }

    if (!is_string($value) || trim($value) === '') {
        return array();
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? array_values($decoded) : array();
}

function store_inventory_actor()
{
    return array(
        'id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0,
        'name' => isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : 'System',
        'email' => isset($_SESSION['user_email']) ? (string) $_SESSION['user_email'] : '',
        'role' => isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : 'system',
    );
}

function store_fetch_product_rows()
{
    static $rows = null;

    if ($rows !== null) {
        return $rows;
    }

    $rows = database_fetch_all(store_db_connection(), "SELECT * FROM products ORDER BY name ASC");
    return $rows;
}

function store_active_promotion_rows_by_product()
{
    static $promotion_map = null;

    if ($promotion_map !== null) {
        return $promotion_map;
    }

    $today = date('Y-m-d');
    $rows = database_fetch_all(
        store_db_connection(),
        "SELECT *
         FROM product_promotions
         WHERE status = 'Active'
           AND (starts_at IS NULL OR starts_at <= ?)
           AND (ends_at IS NULL OR ends_at >= ?)
         ORDER BY discount_percent DESC, sale_price ASC, id DESC",
        'ss',
        array($today, $today)
    );

    $promotion_map = array();

    foreach ($rows as $row) {
        $product_id = (int) ($row['product_id'] ?? 0);

        if ($product_id <= 0 || isset($promotion_map[$product_id])) {
            continue;
        }

        $promotion_map[$product_id] = $row;
    }

    return $promotion_map;
}

function store_calculate_sale_price($regular_price, $sale_price, $discount_percent)
{
    $regular_price = (float) $regular_price;
    $sale_price = (float) $sale_price;
    $discount_percent = (float) $discount_percent;

    if ($sale_price > 0 && $sale_price < $regular_price) {
        return round($sale_price, 2);
    }

    if ($discount_percent > 0 && $discount_percent < 100) {
        return round($regular_price * (1 - ($discount_percent / 100)), 2);
    }

    return $regular_price;
}

function store_promotion_payload_from_row($row, $regular_price)
{
    $sale_price = store_calculate_sale_price(
        $regular_price,
        $row['sale_price'] ?? 0,
        $row['discount_percent'] ?? 0
    );
    $discount_percent = (float) ($row['discount_percent'] ?? 0);

    if ($discount_percent <= 0 && (float) $regular_price > 0 && $sale_price < (float) $regular_price) {
        $discount_percent = round((((float) $regular_price - $sale_price) / (float) $regular_price) * 100, 1);
    }

    return array(
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? 'Shoestagram Sale'),
        'regular_price' => (float) $regular_price,
        'sale_price' => $sale_price,
        'discount_percent' => $discount_percent,
        'status' => (string) ($row['status'] ?? 'Active'),
        'starts_at' => (string) ($row['starts_at'] ?? ''),
        'ends_at' => (string) ($row['ends_at'] ?? ''),
    );
}

function store_raw_product_row_by_id($product_id)
{
    $product_id = (int) $product_id;

    foreach (store_fetch_product_rows() as $row) {
        if ((int) ($row['id'] ?? 0) === $product_id) {
            return $row;
        }
    }

    return null;
}

function store_promotion_status_options()
{
    return array('Active', 'Paused', 'Expired');
}

function store_normalize_promotion_status($status)
{
    $status = trim((string) $status);

    if (strcasecmp($status, 'Inactive') === 0) {
        return 'Paused';
    }

    foreach (store_promotion_status_options() as $option) {
        if (strcasecmp($status, $option) === 0) {
            return $option;
        }
    }

    return 'Paused';
}

function store_promotion_display_status($promotion)
{
    $status = store_normalize_promotion_status($promotion['status'] ?? 'Paused');
    $today = date('Y-m-d');
    $starts_at = trim((string) ($promotion['starts_at'] ?? ''));
    $ends_at = trim((string) ($promotion['ends_at'] ?? ''));

    if ($status === 'Active' && $starts_at !== '' && $starts_at > $today) {
        return 'Scheduled';
    }

    if (($status === 'Active' || $status === 'Expired') && $ends_at !== '' && $ends_at < $today) {
        return 'Expired';
    }

    return $status;
}

function store_validate_promotion_date($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
}

function store_get_promotions()
{
    $rows = database_fetch_all(
        store_db_connection(),
        "SELECT pp.*, p.name AS product_name, p.brand AS product_brand, p.slug AS product_slug, p.price AS product_regular_price
         FROM product_promotions pp
         INNER JOIN products p ON p.id = pp.product_id
         ORDER BY pp.created_at DESC, pp.id DESC"
    );

    foreach ($rows as &$row) {
        $regular_price = (float) ($row['product_regular_price'] ?? $row['original_price'] ?? 0);
        $payload = store_promotion_payload_from_row($row, $regular_price);
        $row['original_price'] = (float) ($row['original_price'] ?? $regular_price);
        $row['regular_price'] = $regular_price;
        $row['sale_price'] = (float) $payload['sale_price'];
        $row['discount_percent'] = (float) $payload['discount_percent'];
        $row['display_status'] = store_promotion_display_status($row);
    }
    unset($row);

    return $rows;
}

function store_get_active_promotions($limit = 4)
{
    $active = array_values(array_filter(store_get_promotions(), function ($promotion) {
        return store_promotion_display_status($promotion) === 'Active';
    }));

    usort($active, function ($left, $right) {
        return (float) ($right['discount_percent'] ?? 0) <=> (float) ($left['discount_percent'] ?? 0);
    });

    return array_slice($active, 0, max(1, (int) $limit));
}

function store_save_promotion($input)
{
    $product_id = (int) ($input['product_id'] ?? 0);
    $product_row = store_raw_product_row_by_id($product_id);

    if (!$product_row) {
        return array('ok' => false, 'message' => 'Choose a valid product for the promotion.');
    }

    $regular_price = (float) ($product_row['price'] ?? 0);
    $title = trim((string) ($input['title'] ?? ''));
    $sale_price_input = trim((string) ($input['sale_price'] ?? ''));
    $discount_input = trim((string) ($input['discount_percent'] ?? ''));
    $sale_price = $sale_price_input !== '' ? round((float) $sale_price_input, 2) : 0.00;
    $discount_percent = $discount_input !== '' ? round((float) $discount_input, 2) : 0.00;
    $status = store_normalize_promotion_status($input['status'] ?? 'Active');
    $starts_at = store_validate_promotion_date($input['starts_at'] ?? '');
    $ends_at = store_validate_promotion_date($input['ends_at'] ?? '');

    if ($title === '') {
        $title = 'Shoestagram Sale';
    }

    if ($regular_price <= 0) {
        return array('ok' => false, 'message' => 'The selected product needs a regular price before it can be promoted.');
    }

    if ($sale_price <= 0 && $discount_percent <= 0) {
        return array('ok' => false, 'message' => 'Enter a discounted price or a discount percentage.');
    }

    if ($sale_price > 0 && $sale_price >= $regular_price) {
        return array('ok' => false, 'message' => 'Discounted price must be lower than the product regular price.');
    }

    if ($discount_percent < 0 || $discount_percent >= 100) {
        return array('ok' => false, 'message' => 'Discount percentage must be between 0 and 99.99.');
    }

    if ($starts_at === false || $ends_at === false) {
        return array('ok' => false, 'message' => 'Use valid start and end dates.');
    }

    if ($starts_at !== null && $ends_at !== null && $ends_at < $starts_at) {
        return array('ok' => false, 'message' => 'Promotion end date must be on or after the start date.');
    }

    $ok = database_execute(
        store_db_connection(),
        "INSERT INTO product_promotions (product_id, title, original_price, sale_price, discount_percent, status, starts_at, ends_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'isdddsss',
        array($product_id, $title, $regular_price, $sale_price, $discount_percent, $status, $starts_at, $ends_at)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Promotion created for ' . (string) ($product_row['name'] ?? 'product') . '.' : 'Promotion could not be saved.',
    );
}

function store_set_promotion_status($promotion_id, $status)
{
    $promotion_id = (int) $promotion_id;
    $status = store_normalize_promotion_status($status);

    if ($promotion_id <= 0 || !in_array($status, array('Active', 'Paused'), true)) {
        return array('ok' => false, 'message' => 'Choose a valid promotion status.');
    }

    $ok = database_execute(
        store_db_connection(),
        "UPDATE product_promotions SET status = ? WHERE id = ?",
        'si',
        array($status, $promotion_id)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Promotion status updated to ' . $status . '.' : 'Promotion status could not be updated.',
    );
}

function store_get_low_sales_products($limit = 8)
{
    $products = array_values(get_store_products());

    if (empty($products)) {
        return array();
    }

    $average_sales = array_sum(array_map(function ($product) {
        return (int) ($product['sales_count'] ?? 0);
    }, $products)) / count($products);
    $low_sales_threshold = max(3, (int) floor($average_sales * 0.45));

    $candidates = array_values(array_filter($products, function ($product) use ($low_sales_threshold) {
        return (int) ($product['sales_count'] ?? 0) <= $low_sales_threshold
            || ((int) ($product['sales_count'] ?? 0) === 0 && (int) ($product['stock'] ?? 0) > 0);
    }));

    if (empty($candidates)) {
        $candidates = $products;
    }

    usort($candidates, function ($left, $right) {
        $sales_compare = (int) ($left['sales_count'] ?? 0) <=> (int) ($right['sales_count'] ?? 0);

        if ($sales_compare !== 0) {
            return $sales_compare;
        }

        return (int) ($right['stock'] ?? 0) <=> (int) ($left['stock'] ?? 0);
    });

    return array_slice($candidates, 0, max(1, (int) $limit));
}

function store_reset_product_cache()
{
    $reflection = new ReflectionFunction('store_fetch_product_rows');
    $static_variables = $reflection->getStaticVariables();

    if (array_key_exists('rows', $static_variables)) {
        $reflection = null;
    }
}

function store_margin_percent($price, $cost)
{
    $price = (float) $price;
    $cost = (float) $cost;

    if ($price <= 0) {
        return 0;
    }

    return round((($price - $cost) / $price) * 100, 1);
}

function store_recommendation_score($product, $options = array())
{
    $preferred_brand = strtolower(trim((string) ($options['brand'] ?? '')));
    $preferred_style = strtolower(trim((string) ($options['style'] ?? '')));
    $max_price = (float) ($options['max_price'] ?? 0);
    $score = (int) ($product['trend_score'] ?? 60) + (int) round(((float) ($product['rating'] ?? 4.5)) * 10);

    if ($preferred_brand !== '' && strtolower((string) ($product['brand'] ?? '')) === $preferred_brand) {
        $score += 20;
    }

    if ($preferred_style !== '') {
        $style = strtolower((string) ($product['style'] ?? ''));
        $tags = array_map('strtolower', $product['preference_tags'] ?? array());

        if (strpos($style, $preferred_style) !== false || in_array($preferred_style, $tags, true)) {
            $score += 16;
        }
    }

    if ($max_price > 0 && (float) ($product['price'] ?? 0) <= $max_price) {
        $score += 10;
    }

    if (!empty($product['is_low_stock'])) {
        $score += 6;
    }

    $score += (int) round(min(18, ((int) ($product['reservations'] ?? 0)) * 0.45));
    $score += (int) round(min(12, ((int) ($product['views_count'] ?? 0)) / 30));

    return $score;
}

function store_product_history($product, $months = 6)
{
    $months = max(3, (int) $months);
    $history = array();
    $base_units = max(3, (int) round(((int) $product['sales_count']) / 6));
    $seasonality = array(1.04, 0.96, 0.98, 1.03, 1.09, 1.12, 1.15, 1.13, 1.06, 0.99, 1.18, 1.26);
    $trend_factor = 1 + ((((int) $product['trend_score']) - 75) / 220);

    for ($offset = $months - 1; $offset >= 0; $offset--) {
        $timestamp = strtotime(date('Y-m-01') . ' -' . $offset . ' months');
        $month_index = (int) date('n', $timestamp) - 1;
        $variation = 1 + (((((int) $product['id']) + ($offset * 11)) % 9) - 4) * 0.04;
        $units = max(1, (int) round($base_units * $seasonality[$month_index] * $trend_factor * $variation));
        $history[] = array(
            'label' => date('M Y', $timestamp),
            'month' => date('Y-m', $timestamp),
            'units' => $units,
            'revenue' => round($units * (float) $product['price'], 2),
        );
    }

    return $history;
}

function store_product_forecast($product)
{
    $history = store_product_history($product, 6);
    $recent = array_slice($history, -3);
    $recent_units = array_map(function ($row) {
        return (int) $row['units'];
    }, $recent);
    $average_recent_units = !empty($recent_units) ? array_sum($recent_units) / count($recent_units) : 0;
    $next_month_timestamp = strtotime(date('Y-m-01') . ' +1 month');
    $month_index = (int) date('n', $next_month_timestamp) - 1;
    $seasonality = array(1.04, 0.96, 0.98, 1.03, 1.09, 1.12, 1.15, 1.13, 1.06, 0.99, 1.18, 1.26);
    $growth_factor = 1
        + ((((int) $product['trend_score']) - 75) / 250)
        + ((((float) $product['rating']) - 4.4) * 0.08)
        + min(0.14, ((int) $product['reservations']) / 220);
    $next_30d_units = max(2, (int) round($average_recent_units * $seasonality[$month_index] * $growth_factor));
    $next_90d_units = max($next_30d_units * 2, (int) round($next_30d_units * 3.1));
    $suggested_stock = max(4, (int) ceil($next_30d_units * 1.35));

    return array(
        'history' => $history,
        'next_30d_units' => $next_30d_units,
        'next_90d_units' => $next_90d_units,
        'next_30d_revenue' => round($next_30d_units * (float) $product['price'], 2),
        'suggested_stock' => $suggested_stock,
        'reorder_gap' => max(0, $suggested_stock - (int) $product['stock']),
    );
}

/*
 * Actual-sales history for ForecastAPI. The current application stores orders
 * in database/runtime/orders.json rather than in a MySQL transactions table.
 */
function store_forecast_order_counts_as_completed_sale($order)
{
    $status = trim((string) ($order['status'] ?? ''));
    $payment_status = trim((string) ($order['payment_status'] ?? ''));

    /* "Completed" is the app's fulfilled-sale status. Failed payments never count. */
    return $status === 'Completed'
        && $payment_status !== 'Failed/Rejected'
        && store_system_order_is_after_historical_import($order);
}

function store_get_product_completed_sales_history($product_id)
{
    $product_id = (int) $product_id;
    $daily_units = array();
    $system_orders = 0;
    $manual_records = 0;
    $imported_records = 0;

    foreach (store_state_get_orders() as $order) {
        if ((int) ($order['product_id'] ?? 0) !== $product_id || !store_forecast_order_counts_as_completed_sale($order)) {
            continue;
        }

        $date = substr((string) ($order['created_at'] ?? ''), 0, 10);

        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
            continue;
        }

        $daily_units[$date] = ($daily_units[$date] ?? 0) + max(0, (int) ($order['quantity'] ?? 0));
        $system_orders++;
    }

    foreach (store_get_manual_sales(array('product_id' => $product_id)) as $sale) {
        $date = (string) ($sale['sale_date'] ?? '');
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
            continue;
        }

        $daily_units[$date] = ($daily_units[$date] ?? 0) + max(0, (int) ($sale['quantity'] ?? 0));
        $manual_records++;
        if (($sale['provenance'] ?? '') === 'historical_import') {
            $imported_records++;
        }
    }

    ksort($daily_units);
    $actual_dates = array_keys($daily_units);
    $importCoverage = store_get_product_import_coverage($product_id);

    if (empty($actual_dates) && !$importCoverage) {
        return array(
            'series' => array(),
            'historical_sales_days' => 0,
            'earliest_date' => '',
            'latest_date' => '',
            'total_units_sold' => 0,
            'qualifying_orders' => 0,
            'system_orders' => 0,
            'manual_records' => 0,
            'imported_records' => 0,
            'uses_actual_history' => false,
        );
    }

    $earliest_date = $actual_dates ? reset($actual_dates) : (string) ($importCoverage['history_start'] ?? '');
    $latest_date = $actual_dates ? end($actual_dates) : (string) ($importCoverage['history_end'] ?? '');
    if ($importCoverage) {
        $coverageStart = (string) ($importCoverage['history_start'] ?? '');
        $coverageEnd = (string) ($importCoverage['history_end'] ?? '');
        if ($coverageStart !== '' && ($earliest_date === '' || $coverageStart < $earliest_date)) {
            $earliest_date = $coverageStart;
        }
        if ($coverageEnd > $latest_date) {
            $latest_date = $coverageEnd;
        }
    }
    $series = array();
    $cursor = new DateTimeImmutable($earliest_date);
    $last_date = new DateTimeImmutable($latest_date);

    while ($cursor <= $last_date) {
        $date = $cursor->format('Y-m-d');
        $series[] = array(
            'date' => $date,
            'value' => (int) ($daily_units[$date] ?? 0),
        );
        $cursor = $cursor->modify('+1 day');
    }

    /* Keep the API payload within the current development-tier datapoint limit. */
    if (count($series) > 90) {
        $series = array_slice($series, -90);
    }

    $submitted_sales_days = count(array_filter($series, function ($point) {
        return (float) ($point['value'] ?? 0) > 0;
    }));
    $submitted_total_units = array_sum(array_map(function ($point) {
        return max(0, (int) ($point['value'] ?? 0));
    }, $series));
    $series_start = !empty($series) ? (string) $series[0]['date'] : $earliest_date;
    $series_end = !empty($series) ? (string) $series[count($series) - 1]['date'] : $latest_date;

    return array(
        'series' => $series,
        'historical_sales_days' => $submitted_sales_days,
        'earliest_date' => $series_start,
        'latest_date' => $series_end,
        'total_units_sold' => $submitted_total_units,
        'qualifying_orders' => $system_orders + $manual_records,
        'system_orders' => $system_orders,
        'manual_records' => $manual_records,
        'imported_records' => $imported_records,
        'import_verification_status' => (string) ($importCoverage['verification_status'] ?? ''),
        'uses_actual_history' => true,
    );
}

function store_find_product_with_completed_sales_history()
{
    $selected = null;

    foreach (get_store_products() as $product) {
        $history = store_get_product_completed_sales_history((int) $product['id']);

        if (empty($history['series'])) {
            continue;
        }

        if ($selected === null
            || (int) $history['historical_sales_days'] > (int) $selected['history']['historical_sales_days']
            || ((int) $history['historical_sales_days'] === (int) $selected['history']['historical_sales_days']
                && (int) $history['total_units_sold'] > (int) $selected['history']['total_units_sold'])) {
            $selected = array('product' => $product, 'history' => $history);
        }
    }

    return $selected;
}

function store_build_forecast_api_projection($product, $api_response)
{
    $forecast_periods = isset($api_response['forecasts']) && is_array($api_response['forecasts'])
        ? $api_response['forecasts']
        : array();
    $raw_30_day_demand = 0.0;
    $raw_90_day_demand = 0.0;
    $period_details = array();

    foreach ($forecast_periods as $index => $period) {
        $value = max(0, (float) ($period['forecast'] ?? 0));

        if ($index < 30) {
            $raw_30_day_demand += $value;
        }

        if ($index < 90) {
            $raw_90_day_demand += $value;
        }

        $period_details[] = array(
            'date' => (string) ($period['date'] ?? ''),
            'forecast' => $value,
            'lower' => isset($period['lower']) ? (float) $period['lower'] : null,
            'upper' => isset($period['upper']) ? (float) $period['upper'] : null,
        );
    }

    /* Aggregate first, then round up once: shoes are whole physical units. */
    $forecast_30_day_demand = (int) ceil($raw_30_day_demand);
    $forecast_90_day_units = (int) ceil($raw_90_day_demand);
    $safety_stock = (int) ceil($forecast_30_day_demand * 0.35);
    $suggested_stock = max(4, $forecast_30_day_demand + $safety_stock);
    $current_stock = max(0, (int) ($product['stock'] ?? 0));

    return array(
        'forecast_periods' => $period_details,
        'raw_30_day_demand' => $raw_30_day_demand,
        'raw_90_day_demand' => $raw_90_day_demand,
        'forecast_30_day_demand' => $forecast_30_day_demand,
        'forecast_90_day_units' => $forecast_90_day_units,
        'current_price' => (float) ($product['price'] ?? 0),
        'forecast_30_day_revenue' => round($forecast_30_day_demand * (float) ($product['price'] ?? 0), 2),
        'current_stock' => $current_stock,
        'safety_stock' => $safety_stock,
        'suggested_stock' => $suggested_stock,
        'reorder_gap' => max(0, $suggested_stock - $current_stock),
        'model_method' => (string) ($api_response['model_info']['best_model'] ?? ''),
        'selection_metric' => (string) ($api_response['model_info']['selection_metric'] ?? ''),
        'generated_at' => (string) ($api_response['generated_at'] ?? ''),
    );
}

function store_forecast_history_status($historical_sales_days, $data_source)
{
    if ($data_source === 'Sample / Development Data') {
        return 'Sample / Development Data';
    }

    $historical_sales_days = (int) $historical_sales_days;

    if ($historical_sales_days < 7) {
        return 'Very Limited History';
    }

    if ($historical_sales_days < 30) {
        return 'Limited History';
    }

    if ($historical_sales_days < 90) {
        return 'Moderate History';
    }

    return 'Stronger History';
}

function store_get_product_sample_sales_history($product, $months = 6)
{
    $series = array();

    foreach (store_product_history($product, $months) as $month) {
        $start = new DateTimeImmutable((string) $month['month'] . '-01');
        $days_in_month = (int) $start->format('t');
        $daily_value = $days_in_month > 0 ? ((float) $month['units'] / $days_in_month) : 0;

        for ($day = 0; $day < $days_in_month; $day++) {
            $series[] = array(
                'date' => $start->modify('+' . $day . ' days')->format('Y-m-d'),
                'value' => round($daily_value, 6),
            );
        }
    }

    /* ForecastAPI's development tier accepts up to 100 datapoints per call. */
    $series = array_slice($series, -90);

    return array(
        'series' => $series,
        'historical_sales_days' => 0,
        'earliest_date' => !empty($series) ? $series[0]['date'] : '',
        'latest_date' => !empty($series) ? $series[count($series) - 1]['date'] : '',
        'total_units_sold' => 0,
        'qualifying_orders' => 0,
        'system_orders' => 0,
        'manual_records' => 0,
        'imported_records' => 0,
        'uses_actual_history' => false,
        'data_source' => 'Sample / Development Data',
    );
}

function store_get_product_forecast_history($product)
{
    $actual = store_get_product_completed_sales_history((int) ($product['id'] ?? 0));

    if (!empty($actual['series'])) {
        $hasSystem = (int) ($actual['system_orders'] ?? 0) > 0;
        $hasImported = (int) ($actual['imported_records'] ?? 0) > 0 || store_get_product_import_coverage((int) ($product['id'] ?? 0));
        $hasManual = (int) ($actual['manual_records'] ?? 0) > (int) ($actual['imported_records'] ?? 0);
        if ($hasImported && $hasSystem) {
            $actual['data_source'] = 'Historical Import + Completed Shoestagram Transactions';
        } elseif ($hasImported) {
            $actual['data_source'] = ($actual['import_verification_status'] ?? '') === 'client_verified'
                ? 'Verified Historical Import'
                : 'Historical Import — Pending Client Verification';
        } elseif ($hasSystem && $hasManual) {
            $actual['data_source'] = 'Actual Completed Transactions + Manual Historical Sales';
        } elseif ((int) ($actual['manual_records'] ?? 0) > 0) {
            $actual['data_source'] = 'Manual Historical Sales (Actual)';
        } else {
            $actual['data_source'] = 'Actual Completed Transactions';
        }
        return $actual;
    }

    return store_get_product_sample_sales_history($product);
}

function store_forecast_cache_directory()
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'forecast-cache';
}

function store_forecast_cache_path($product_id)
{
    return store_forecast_cache_directory() . DIRECTORY_SEPARATOR . 'product-' . max(0, (int) $product_id) . '.json';
}

function store_forecast_api_backoff_path()
{
    return store_forecast_cache_directory() . DIRECTORY_SEPARATOR . 'service-backoff.json';
}

function store_get_forecast_api_backoff_response()
{
    $path = store_forecast_api_backoff_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $payload = json_decode((string) @file_get_contents($path), true);
    if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
        @unlink($path);
        return null;
    }

    return isset($payload['api_response']) && is_array($payload['api_response'])
        ? $payload['api_response']
        : null;
}

function store_update_forecast_api_backoff($api_response)
{
    $status = (int) ($api_response['http_status_code'] ?? 0);
    $path = store_forecast_api_backoff_path();
    if ($status >= 200 && $status < 300) {
        return !is_file($path) || @unlink($path);
    }

    $ttl = $status === 429 ? 900 : ($status >= 500 ? 120 : 0);
    if ($ttl <= 0) {
        return false;
    }

    $directory = store_forecast_cache_directory();
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }

    $payload = array(
        'expires_at' => time() + $ttl,
        'api_response' => array(
            'environment_loaded' => !empty($api_response['environment_loaded']),
            'authentication' => (string) ($api_response['authentication'] ?? 'Failed'),
            'http_status_code' => $status,
            'forecast_response_received' => false,
            'forecasts' => array(),
            'model_info' => array(),
            'generated_at' => (string) ($api_response['generated_at'] ?? date('c')),
            'safe_error_message' => (string) ($api_response['safe_error_message'] ?? 'Forecast API is temporarily unavailable.'),
        ),
    );
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    return $encoded !== false && @file_put_contents($path, $encoded, LOCK_EX) !== false;
}

function store_invalidate_forecast_cache($product_id)
{
    $path = store_forecast_cache_path($product_id);
    return !is_file($path) || @unlink($path);
}

function store_get_cached_forecast_api_response($product_id, $history_hash)
{
    $path = store_forecast_cache_path($product_id);

    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $cached = json_decode((string) @file_get_contents($path), true);

    if (!is_array($cached)
        || !hash_equals((string) ($cached['history_hash'] ?? ''), (string) $history_hash)
        || (int) ($cached['expires_at'] ?? 0) < time()) {
        return null;
    }

    return isset($cached['api_response']) && is_array($cached['api_response']) ? $cached['api_response'] : null;
}

function store_cache_forecast_api_response($product_id, $history_hash, $api_response)
{
    $directory = store_forecast_cache_directory();

    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }

    $successful = !empty($api_response['forecast_response_received']);
    $payload = array(
        'history_hash' => (string) $history_hash,
        'expires_at' => time() + ($successful ? 21600 : 900),
        'api_response' => array(
            'environment_loaded' => !empty($api_response['environment_loaded']),
            'authentication' => (string) ($api_response['authentication'] ?? 'Failed'),
            'http_status_code' => (int) ($api_response['http_status_code'] ?? 0),
            'forecast_response_received' => $successful,
            'forecasts' => isset($api_response['forecasts']) && is_array($api_response['forecasts']) ? $api_response['forecasts'] : array(),
            'model_info' => isset($api_response['model_info']) && is_array($api_response['model_info']) ? $api_response['model_info'] : array(),
            'generated_at' => (string) ($api_response['generated_at'] ?? ''),
            'safe_error_message' => (string) ($api_response['safe_error_message'] ?? ''),
        ),
    );

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    return $encoded !== false && @file_put_contents(store_forecast_cache_path($product_id), $encoded, LOCK_EX) !== false;
}

function store_get_product_forecast_result($product)
{
    static $request_cache = array();

    $history = store_get_product_forecast_history($product);
    $history_hash = hash('sha256', json_encode($history['series'], JSON_UNESCAPED_SLASHES));
    $request_cache_key = (int) ($product['id'] ?? 0) . ':' . $history_hash;

    if (isset($request_cache[$request_cache_key])) {
        return $request_cache[$request_cache_key];
    }

    $api_response = store_get_cached_forecast_api_response((int) ($product['id'] ?? 0), $history_hash);
    $from_cache = $api_response !== null;

    if ($api_response !== null) {
        store_update_forecast_api_backoff($api_response);
    }

    if ($api_response === null) {
        $api_response = store_get_forecast_api_backoff_response();
        $from_cache = $api_response !== null;

        if ($api_response === null) {
            $api_response = shoestagram_forecast_api_request(
                'shoestagram-product-' . (int) ($product['id'] ?? 0),
                $history['series'],
                90,
                'D',
                'sales'
            );
            store_update_forecast_api_backoff($api_response);
        }
        store_cache_forecast_api_response((int) ($product['id'] ?? 0), $history_hash, $api_response);
    }

    $history_status = store_forecast_history_status(
        (int) ($history['historical_sales_days'] ?? 0),
        (string) ($history['data_source'] ?? '')
    );
    $common = array(
        'historical_sales_days' => (int) ($history['historical_sales_days'] ?? 0),
        'continuous_history_days' => count($history['series']),
        'history_start_date' => (string) ($history['earliest_date'] ?? ''),
        'history_end_date' => (string) ($history['latest_date'] ?? ''),
        'data_source' => (string) ($history['data_source'] ?? 'Sample / Development Data'),
        'history_status' => $history_status,
        'limited_history_warning' => !empty($history['uses_actual_history'])
            && (int) ($history['historical_sales_days'] ?? 0) < 7,
        'forecast_cached' => $from_cache,
        'api_http_status' => (int) ($api_response['http_status_code'] ?? 0),
    );

    if (!empty($api_response['forecast_response_received'])) {
        $projection = store_build_forecast_api_projection($product, $api_response);
        $result = array_merge($common, $projection, array(
            'forecast_source' => 'ForecastAPI',
            'forecast_available' => true,
            'forecast_unavailable_message' => '',
            'next_30d_units' => (int) $projection['forecast_30_day_demand'],
            'next_90d_units' => (int) $projection['forecast_90_day_units'],
            'next_30d_revenue' => (float) $projection['forecast_30_day_revenue'],
        ));
    } else {
        $legacy = store_product_forecast($product);
        $result = array_merge($common, $legacy, array(
            'forecast_source' => 'Fallback Forecast — Local Heuristic',
            'forecast_available' => false,
            'forecast_unavailable_message' => 'Forecast temporarily unavailable.',
            'forecast_30_day_demand' => (int) $legacy['next_30d_units'],
            'forecast_90_day_units' => (int) $legacy['next_90d_units'],
            'forecast_30_day_revenue' => (float) $legacy['next_30d_revenue'],
            'current_price' => (float) ($product['price'] ?? 0),
            'current_stock' => max(0, (int) ($product['stock'] ?? 0)),
            'model_method' => '',
            'selection_metric' => '',
            'generated_at' => '',
            'forecast_periods' => array(),
        ));
    }

    $request_cache[$request_cache_key] = $result;
    return $result;
}

function store_price_analytics_row($product)
{
    $forecast = store_product_forecast($product);
    $price = (float) $product['price'];
    $cost = (float) $product['cost_current'];
    $margin_value = round($price - $cost, 2);
    $margin_percent = store_margin_percent($price, $cost);
    $elasticity = $price <= 1000 ? 0.48 : ($price <= 5000 ? 0.31 : 0.19);
    $price_adjustment = 0;

    if ((int) $product['stock'] <= max(5, (int) round($forecast['suggested_stock'] * 0.45)) && $margin_percent < 35) {
        $price_adjustment = 0.06;
    } elseif (!empty($product['is_overstock'])) {
        $price_adjustment = -0.08;
    } elseif ($margin_percent < 20) {
        $price_adjustment = 0.07;
    } elseif ((int) $product['reservations'] >= 25 && (float) $product['rating'] >= 4.7) {
        $price_adjustment = 0.04;
    } elseif ((int) $product['views_count'] > 250 && (int) $product['reservations'] < 8) {
        $price_adjustment = -0.05;
    }

    $optimal_price = store_price_round(max($cost * 1.18, $price * (1 + $price_adjustment)));
    $delta_ratio = $price > 0 ? (($optimal_price - $price) / $price) : 0;
    $forecast_units = max(2, $forecast['next_30d_units']);
    $forecast_units_optimal = max(1, (int) round($forecast_units * (1 - ($elasticity * $delta_ratio))));
    $current_revenue = round($forecast_units * $price, 2);
    $optimal_revenue = round($forecast_units_optimal * $optimal_price, 2);

    $action = 'Hold';
    $improvement = 'Keep the price stable and support the product with steady availability.';

    if ($optimal_price > $price) {
        $action = 'Increase';
        $improvement = 'Demand is strong enough to test a small price lift while protecting margin.';
    } elseif ($optimal_price < $price) {
        $action = 'Decrease';
        $improvement = 'Demand is softer than stock depth. A price test or bundle should improve turn.';
    }

    if (!empty($product['is_low_stock'])) {
        $improvement .= ' Replenish sooner because current stock cover is tight.';
    } elseif (!empty($product['is_overstock'])) {
        $improvement .= ' Push bundles or limited promos to reduce overstock exposure.';
    }

    return array_merge($product, array(
        'forecast' => $forecast,
        'margin_value' => $margin_value,
        'margin_percent' => $margin_percent,
        'optimal_price' => $optimal_price,
        'optimal_price_display' => store_format_money($optimal_price),
        'forecast_units_current' => $forecast_units,
        'forecast_units_optimal' => $forecast_units_optimal,
        'forecast_revenue_current' => $current_revenue,
        'forecast_revenue_optimal' => $optimal_revenue,
        'price_action' => $action,
        'price_gap' => round($optimal_price - $price, 2),
        'improvement' => $improvement,
    ));
}

function store_price_round($value)
{
    return round((float) $value / 50) * 50;
}

function store_parse_multivalue_text($value)
{
    $parts = preg_split('/[\r\n,]+/', (string) $value);
    $parts = array_map('trim', $parts);
    $parts = array_values(array_filter($parts, function ($part) {
        return $part !== '';
    }));

    return array_values(array_unique($parts));
}

function store_unique_slug($slug, $exclude_id = 0)
{
    $slug = shoestagram_catalog_slug($slug);
    $base_slug = $slug;
    $counter = 2;

    while (true) {
        $existing = database_fetch_one(
            store_db_connection(),
            "SELECT id FROM products WHERE slug = ? LIMIT 1",
            's',
            array($slug)
        );

        if (!$existing || (int) $existing['id'] === (int) $exclude_id) {
            return $slug;
        }

        $slug = $base_slug . '-' . $counter;
        $counter++;
    }
}

function store_validate_product_payload($input, $existing = array())
{
    $categories = get_store_categories();
    unset($categories['all']);

    $name = trim((string) ($input['name'] ?? ($existing['name'] ?? '')));
    $brand = trim((string) ($input['brand'] ?? ($existing['brand'] ?? '')));
    $category = strtolower(trim((string) ($input['category'] ?? ($existing['category'] ?? 'shoes'))));
    $style = trim((string) ($input['style'] ?? ($existing['style'] ?? 'Lifestyle sneaker')));
    $price = (float) ($input['price'] ?? ($existing['price'] ?? 0));
    $old_price_input = trim((string) ($input['old_price'] ?? ''));
    $old_price = $old_price_input !== '' ? (float) $old_price_input : null;
    $cost_min = (float) ($input['cost_min'] ?? ($existing['cost_min'] ?? 0));
    $cost_max = (float) ($input['cost_max'] ?? ($existing['cost_max'] ?? $cost_min));
    $stock = max(0, (int) ($input['stock'] ?? ($existing['base_stock'] ?? 0)));
    $badge = trim((string) ($input['badge'] ?? ($existing['badge'] ?? 'Core Pick')));
    $blurb = trim((string) ($input['blurb'] ?? ($existing['blurb'] ?? '')));
    $description = trim((string) ($input['description'] ?? ($existing['description'] ?? '')));
    $tone = trim((string) ($input['tone'] ?? ($existing['tone'] ?? '')));
    $colorway = trim((string) ($input['colorway'] ?? ($existing['colorway'] ?? '')));
    $sizes = store_parse_multivalue_text($input['sizes'] ?? implode(',', $existing['sizes'] ?? array()));
    $existing_standards = isset($existing['size_standards']) && is_array($existing['size_standards']) ? $existing['size_standards'] : array();
    $size_standards = store_normalize_size_standards(array(
        'US' => store_parse_multivalue_text($input['sizes_us'] ?? implode(',', $existing_standards['US'] ?? $sizes)),
        'EU' => store_parse_multivalue_text($input['sizes_eu'] ?? implode(',', $existing_standards['EU'] ?? array())),
        'CN' => store_parse_multivalue_text($input['sizes_cn'] ?? implode(',', $existing_standards['CN'] ?? array())),
    ), $sizes, $category);
    $preference_tags = store_parse_multivalue_text($input['preference_tags'] ?? implode(',', $existing['preference_tags'] ?? array()));
    $featured = !empty($input['featured']);
    $trending = !empty($input['trending']);
    $best_seller = !empty($input['best_seller']);
    $limited = !empty($input['limited']);
    $new_arrival = !empty($input['new_arrival']);

    if (!isset($categories[$category])) {
        return array('ok' => false, 'message' => 'Choose a valid product category.');
    }

    if ($name === '' || $brand === '') {
        return array('ok' => false, 'message' => 'Product name and brand are required.');
    }

    if ($price <= 0 || $cost_min < 0 || $cost_max < 0) {
        return array('ok' => false, 'message' => 'Price and cost values must be positive.');
    }

    if ($cost_max < $cost_min) {
        $cost_max = $cost_min;
    }

    if (empty($sizes)) {
        $sizes = shoestagram_catalog_default_sizes($category, $name);
    }

    $size_standards = store_normalize_size_standards($size_standards, $sizes, $category);
    $sizes = store_primary_sizes_from_standards($size_standards, $sizes);

    if (empty($preference_tags)) {
        $preference_tags = shoestagram_catalog_default_tags($category, $brand, $style, $name);
    }

    $product_id = isset($existing['id']) ? (int) $existing['id'] : 0;
    $slug = store_unique_slug($input['slug'] ?? $name, $product_id);
    $price_for_badge = $price;
    $profit_current = $price - (($cost_min + $cost_max) / 2);

    if ($badge === '') {
        $badge = shoestagram_catalog_badge($price_for_badge, $profit_current, $stock, $featured, $trending);
    }

    if ($tone === '') {
        $tone = shoestagram_catalog_tone($name, $brand);
    }

    if ($colorway === '') {
        $colorway = shoestagram_catalog_colorway($name);
    }

    if ($blurb === '') {
        $blurb = $name . ' is positioned as a sellable store staple with reliable demand and practical margin room.';
    }

    if ($description === '') {
        $description = shoestagram_catalog_category_copy($category);
    }

    $position = max(1, $product_id > 0 ? ($product_id - 100) : (count(get_store_products()) + 1));
    $image = trim((string) ($input['image'] ?? ($existing['image'] ?? '')));
    $media = store_resolve_product_media($name, $image, array(), $position);
    $image = $media['image'];
    $gallery = $media['gallery'];

    return array(
        'ok' => true,
        'product' => array(
            'id' => $product_id,
            'slug' => $slug,
            'name' => $name,
            'brand' => $brand,
            'category' => $category,
            'style' => $style,
            'price' => $price,
            'old_price' => $old_price,
            'cost_min' => $cost_min,
            'cost_max' => $cost_max,
            'badge' => $badge,
            'tag' => $new_arrival ? 'new' : ($featured ? 'featured' : 'core'),
            'featured' => $featured,
            'trending' => $trending,
            'best_seller' => $best_seller,
            'limited' => $limited,
            'new_arrival' => $new_arrival,
            'condition' => 'Brand New',
            'stock' => $stock,
            'rating' => isset($existing['rating']) ? (float) $existing['rating'] : 4.6,
            'reviews_count' => isset($existing['reviews_count']) ? (int) $existing['reviews_count'] : 0,
            'likes' => isset($existing['likes']) ? (int) $existing['likes'] : 0,
            'reservations' => isset($existing['reservations']) ? (int) $existing['reservations'] : 0,
            'sales_count' => isset($existing['sales_count']) ? (int) $existing['sales_count'] : 0,
            'trend_score' => isset($existing['trend_score']) ? (int) $existing['trend_score'] : 72,
            'tone' => $tone,
            'colorway' => $colorway,
            'blurb' => $blurb,
            'description' => $description,
            'image' => $image,
            'gallery' => $gallery,
            'preference_tags' => $preference_tags,
            'sizes' => $sizes,
            'size_standards' => $size_standards,
        ),
    );
}

function store_save_product($input, $product_id = 0)
{
    $existing = $product_id > 0 ? get_store_product_by_id($product_id) : null;
    $validation = store_validate_product_payload($input, $existing ?: array());

    if (empty($validation['ok'])) {
        return $validation;
    }

    $product = $validation['product'];
    $connection = store_db_connection();

    if ($existing) {
        $ok = database_execute(
            $connection,
            "UPDATE products SET
                slug = ?, name = ?, brand = ?, category = ?, style = ?, price = ?, old_price = ?, cost_min = ?, cost_max = ?,
                badge = ?, tag_label = ?, featured = ?, trending = ?, best_seller = ?, limited = ?, new_arrival = ?,
                condition_label = ?, base_stock = ?, rating = ?, reviews_count = ?, likes_count = ?, reservations_seed = ?,
                sales_count_seed = ?, trend_score = ?, tone = ?, colorway = ?, blurb = ?, description = ?, image_url = ?,
                preference_tags = ?, sizes = ?, size_standards = ?, gallery = ?
             WHERE id = ?",
            'sssssddddssiiiiisidiiiiisssssssssi',
            array(
                $product['slug'],
                $product['name'],
                $product['brand'],
                $product['category'],
                $product['style'],
                $product['price'],
                $product['old_price'],
                $product['cost_min'],
                $product['cost_max'],
                $product['badge'],
                $product['tag'],
                $product['featured'] ? 1 : 0,
                $product['trending'] ? 1 : 0,
                $product['best_seller'] ? 1 : 0,
                $product['limited'] ? 1 : 0,
                $product['new_arrival'] ? 1 : 0,
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
                database_encode_json($product['preference_tags']),
                database_encode_json($product['sizes']),
                database_encode_json($product['size_standards']),
                database_encode_json($product['gallery']),
                (int) $product_id
            )
        );

        if ($ok && (int) $existing['base_stock'] !== (int) $product['stock']) {
            database_log_inventory_action(
                $connection,
                (int) $product_id,
                store_inventory_actor(),
                'update_stock',
                (int) $existing['base_stock'],
                (int) $product['stock'],
                'Product updated from inventory module.'
            );
        }

        return array(
            'ok' => $ok,
            'message' => $ok ? 'Product updated successfully.' : 'Product update failed.',
        );
    }

    $next_id_row = database_fetch_one($connection, "SELECT MAX(id) AS max_id FROM products");
    $next_id = max(101, ((int) ($next_id_row['max_id'] ?? 100)) + 1);
    $product['id'] = $next_id;
    $product['slug'] = store_unique_slug($product['slug'], $next_id);

    $ok = database_execute(
        $connection,
        "INSERT INTO products (
            id, slug, name, brand, category, style, price, old_price, cost_min, cost_max,
            badge, tag_label, featured, trending, best_seller, limited, new_arrival, condition_label, base_stock,
            rating, reviews_count, likes_count, reservations_seed, sales_count_seed, trend_score, tone, colorway,
            blurb, description, image_url, preference_tags, sizes, size_standards, gallery
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
         )",
        'isssssddddssiiiiisidiiiiisssssssss',
        array(
            $product['id'],
            $product['slug'],
            $product['name'],
            $product['brand'],
            $product['category'],
            $product['style'],
            $product['price'],
            $product['old_price'],
            $product['cost_min'],
            $product['cost_max'],
            $product['badge'],
            $product['tag'],
            $product['featured'] ? 1 : 0,
            $product['trending'] ? 1 : 0,
            $product['best_seller'] ? 1 : 0,
            $product['limited'] ? 1 : 0,
            $product['new_arrival'] ? 1 : 0,
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
            database_encode_json($product['preference_tags']),
            database_encode_json($product['sizes']),
            database_encode_json($product['size_standards']),
            database_encode_json($product['gallery'])
        )
    );

    if ($ok) {
        database_log_inventory_action(
            $connection,
            (int) $product['id'],
            store_inventory_actor(),
            'create_product',
            0,
            (int) $product['stock'],
            'Product created from inventory module.'
        );
    }

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Product added successfully.' : 'Product could not be added.',
    );
}

function store_update_product_size_standards($product_id, $input)
{
    $product = get_store_product_by_id($product_id);

    if (!$product) {
        return array('ok' => false, 'message' => 'Product not found.');
    }

    $fallback_sizes = isset($product['sizes']) && is_array($product['sizes']) ? $product['sizes'] : array();
    $existing_standards = isset($product['size_standards']) && is_array($product['size_standards'])
        ? $product['size_standards']
        : array();
    $size_standards = store_normalize_size_standards(array(
        'US' => store_parse_multivalue_text($input['sizes_us'] ?? implode(',', $existing_standards['US'] ?? $fallback_sizes)),
        'EU' => store_parse_multivalue_text($input['sizes_eu'] ?? implode(',', $existing_standards['EU'] ?? array())),
        'CN' => store_parse_multivalue_text($input['sizes_cn'] ?? implode(',', $existing_standards['CN'] ?? array())),
    ), $fallback_sizes, (string) ($product['category'] ?? 'shoes'));
    $primary_sizes = store_primary_sizes_from_standards($size_standards, $fallback_sizes);

    if (empty($primary_sizes)) {
        return array('ok' => false, 'message' => 'Add at least one available size.');
    }

    $ok = database_execute(
        store_db_connection(),
        "UPDATE products SET sizes = ?, size_standards = ? WHERE id = ?",
        'ssi',
        array(
            database_encode_json($primary_sizes),
            database_encode_json($size_standards),
            (int) $product_id
        )
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Product size standards updated successfully.' : 'Product size standards could not be updated.',
    );
}

function store_delete_product($product_id)
{
    $product = get_store_product_by_id($product_id);

    if (!$product) {
        return array('ok' => false, 'message' => 'Product not found.');
    }

    database_log_inventory_action(
        store_db_connection(),
        (int) $product_id,
        store_inventory_actor(),
        'delete_product',
        (int) $product['base_stock'],
        0,
        'Product deleted from inventory module.'
    );

    $ok = database_execute(store_db_connection(), "DELETE FROM products WHERE id = ?", 'i', array((int) $product_id));

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Product deleted.' : 'Product could not be deleted.',
    );
}

function get_store_products()
{
    static $products = null;

    if ($products !== null) {
        return $products;
    }

    $metrics = store_state_get_product_metrics();
    $historical_units_map = store_get_historical_units_by_product();
    $analytics = store_state_get_analytics();
    $view_map = isset($analytics['product_views']) && is_array($analytics['product_views']) ? $analytics['product_views'] : array();
    $promotion_map = store_active_promotion_rows_by_product();
    $products = array();

    foreach (store_fetch_product_rows() as $row) {
        $product_id = (int) ($row['id'] ?? 0);
        $product_metrics = $metrics[$product_id] ?? array(
            'locked_units' => 0,
            'completed_units' => 0,
            'active_reservations' => 0,
            'review_count' => 0,
            'rating_sum' => 0,
            'latest_reviews' => array(),
        );

        $base_review_count = (int) ($row['reviews_count'] ?? 0);
        $dynamic_review_count = (int) ($product_metrics['review_count'] ?? 0);
        $display_review_count = $dynamic_review_count > 0 ? $dynamic_review_count : $base_review_count;
        $display_rating = $dynamic_review_count > 0
            ? round((float) ($product_metrics['rating_sum'] ?? 0) / $dynamic_review_count, 1)
            : (float) ($row['rating'] ?? 4.5);
        $base_stock = (int) ($row['base_stock'] ?? 0);
        $stock_locked = (int) ($product_metrics['locked_units'] ?? 0);
        $units_sold = (int) ($product_metrics['completed_units'] ?? 0);
        $historical_units_sold = (int) ($historical_units_map[$product_id] ?? 0);
        $available_stock = max(0, $base_stock - $stock_locked - $units_sold);
        $views_count = isset($view_map[$product_id]['count']) ? (int) $view_map[$product_id]['count'] : 0;

        $regular_price = (float) ($row['price'] ?? 0);
        $raw_sizes = store_decode_json_list($row['sizes'] ?? '[]');
        $size_standards = store_normalize_size_standards($row['size_standards'] ?? '', $raw_sizes, (string) ($row['category'] ?? 'shoes'));
        $primary_sizes = store_primary_sizes_from_standards($size_standards, $raw_sizes);
        $promotion = isset($promotion_map[$product_id])
            ? store_promotion_payload_from_row($promotion_map[$product_id], $regular_price)
            : null;
        $effective_price = $promotion && (float) $promotion['sale_price'] > 0 && (float) $promotion['sale_price'] < $regular_price
            ? (float) $promotion['sale_price']
            : $regular_price;

        $product = array(
            'id' => $product_id,
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'category' => (string) ($row['category'] ?? 'shoes'),
            'style' => (string) ($row['style'] ?? 'Lifestyle sneaker'),
            'price' => $effective_price,
            'regular_price' => $regular_price,
            'old_price' => $promotion ? $regular_price : (isset($row['old_price']) ? (float) $row['old_price'] : null),
            'promotion' => $promotion,
            'cost_min' => (float) ($row['cost_min'] ?? 0),
            'cost_max' => (float) ($row['cost_max'] ?? 0),
            'cost_current' => round((((float) ($row['cost_min'] ?? 0)) + ((float) ($row['cost_max'] ?? 0))) / 2, 2),
            'badge' => $promotion ? 'On Sale' : (string) ($row['badge'] ?? 'Core Pick'),
            'tag' => (string) ($row['tag_label'] ?? 'core'),
            'featured' => !empty($row['featured']),
            'trending' => !empty($row['trending']),
            'best_seller' => !empty($row['best_seller']),
            'limited' => !empty($row['limited']),
            'new_arrival' => !empty($row['new_arrival']),
            'condition' => (string) ($row['condition_label'] ?? 'Brand New'),
            'base_stock' => $base_stock,
            'stock_locked' => $stock_locked,
            'units_sold' => $units_sold,
            'historical_units_sold' => $historical_units_sold,
            'stock' => $available_stock,
            'rating' => $display_rating,
            'reviews_count' => $display_review_count,
            'rating_source' => $dynamic_review_count > 0 ? 'approved_customer_reviews' : 'catalog_seed',
            'likes' => (int) ($row['likes_count'] ?? 0),
            'reservations' => (int) ($row['reservations_seed'] ?? 0) + (int) ($product_metrics['active_reservations'] ?? 0),
            'sales_count' => $historical_units_sold + $units_sold,
            'trend_score' => (int) ($row['trend_score'] ?? 60),
            'tone' => (string) ($row['tone'] ?? ''),
            'colorway' => (string) ($row['colorway'] ?? ''),
            'blurb' => (string) ($row['blurb'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'image' => (string) ($row['image_url'] ?? ''),
            'gallery' => store_decode_json_list($row['gallery'] ?? '[]'),
            'preference_tags' => store_decode_json_list($row['preference_tags'] ?? '[]'),
            'sizes' => $primary_sizes,
            'size_standards' => $size_standards,
            'live_reviews' => $product_metrics['latest_reviews'] ?? array(),
            'views_count' => $views_count,
        );

        $media = store_resolve_product_media($product['name'], $product['image'], $product['gallery'], max(1, $product_id - 100));
        $product['image'] = $media['image'];
        $product['gallery'] = $media['gallery'];

        $product['margin_percent'] = store_margin_percent($product['price'], $product['cost_current']);
        $product['cost_display'] = $product['cost_min'] === $product['cost_max']
            ? store_format_money($product['cost_min'])
            : store_format_money($product['cost_min']) . ' - ' . store_format_money($product['cost_max']);
        $profit_low = $product['price'] - $product['cost_max'];
        $profit_high = $product['price'] - $product['cost_min'];
        $product['profit_current'] = round($product['price'] - $product['cost_current'], 2);
        $product['profit_display'] = $profit_low === $profit_high
            ? store_format_money($profit_low)
            : store_format_money($profit_low) . ' - ' . store_format_money($profit_high);

        $forecast = store_product_forecast($product);
        $product['suggested_stock'] = $forecast['suggested_stock'];
        $product['days_cover'] = $forecast['next_30d_units'] > 0 ? round(($product['stock'] / $forecast['next_30d_units']) * 30, 1) : 0;
        $product['is_low_stock'] = $product['stock'] <= max(4, (int) round($forecast['suggested_stock'] * 0.45));
        $product['is_overstock'] = $product['stock'] >= max(18, (int) round($forecast['suggested_stock'] * 1.75));
        $product['stock_status'] = $product['stock'] <= 0
            ? 'Sold out'
            : ($product['is_low_stock'] ? 'Low stock' : ($product['is_overstock'] ? 'Overstock' : 'In stock'));
        $products[] = $product;
    }

    return $products;
}

function get_store_product_by_id($id)
{
    foreach (get_store_products() as $product) {
        if ((int) $product['id'] === (int) $id) {
            return $product;
        }
    }

    return null;
}

function store_cart_rows($user_id)
{
    return database_fetch_all(
        store_db_connection(),
        "SELECT c.id AS cart_id, c.user_id, c.product_id, c.size_standard, c.size, c.quantity, c.created_at, p.name AS product_name
         FROM cart c
         INNER JOIN products p ON p.id = c.product_id
         WHERE c.user_id = ?
         ORDER BY c.created_at DESC, c.id DESC",
        'i',
        array((int) $user_id)
    );
}

function store_get_cart_items($user_id)
{
    $items = array();

    foreach (store_cart_rows($user_id) as $row) {
        $product = get_store_product_by_id((int) $row['product_id']);

        if (!$product) {
            continue;
        }

        $quantity = max(1, (int) $row['quantity']);
        $items[] = array(
            'cart_id' => (int) $row['cart_id'],
            'user_id' => (int) $row['user_id'],
            'product_id' => (int) $row['product_id'],
            'size_standard' => store_normalize_size_standard($row['size_standard'] ?? 'US'),
            'size' => (string) $row['size'],
            'quantity' => $quantity,
            'product' => $product,
            'line_total' => round(((float) $product['price']) * $quantity, 2),
        );
    }

    return $items;
}

function store_get_cart_count($user_id)
{
    $row = database_fetch_one(
        store_db_connection(),
        "SELECT COALESCE(SUM(quantity), 0) AS count FROM cart WHERE user_id = ?",
        'i',
        array((int) $user_id)
    );

    return (int) ($row['count'] ?? 0);
}

function store_get_cart_subtotal($user_id)
{
    $subtotal = 0;

    foreach (store_get_cart_items($user_id) as $item) {
        $subtotal += (float) $item['line_total'];
    }

    return round($subtotal, 2);
}

function store_add_cart_item($user_id, $product_id, $quantity = 1, $size = '', $size_standard = 'US')
{
    $product = get_store_product_by_id($product_id);

    if (!$product) {
        return array('ok' => false, 'message' => 'Product not found.');
    }

    $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
    $size = trim((string) $size);
    $size_standard = store_normalize_size_standard($size_standard);
    $available_sizes = isset($product['size_standards'][$size_standard]) && is_array($product['size_standards'][$size_standard])
        ? $product['size_standards'][$size_standard]
        : $product['sizes'];

    if ($quantity === false || (int) $quantity < 1) {
        return array('ok' => false, 'message' => 'Quantity must be a positive whole number.');
    }

    $quantity = (int) $quantity;
    $available_stock = max(0, (int) ($product['stock'] ?? 0));

    if ($available_stock <= 0) {
        return array('ok' => false, 'message' => $product['name'] . ' is currently out of stock.');
    }

    if ($size === '' || !in_array($size, array_map('strval', $available_sizes), true)) {
        return array('ok' => false, 'message' => 'Choose a valid ' . store_size_standard_label($size_standard) . ' size for this product.');
    }

    $existing = database_fetch_one(
        store_db_connection(),
        "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? AND size_standard = ? AND size = ? LIMIT 1",
        'iiss',
        array((int) $user_id, (int) $product_id, $size_standard, $size)
    );
    $existing_quantity = max(0, (int) ($existing['quantity'] ?? 0));

    if (($existing_quantity + $quantity) > $available_stock) {
        return array(
            'ok' => false,
            'message' => 'Only ' . $available_stock . ' unit(s) are available. Your cart already contains ' . $existing_quantity . ' for this size.',
        );
    }

    $ok = database_execute(
        store_db_connection(),
        "INSERT INTO cart (user_id, product_id, size_standard, size, quantity)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP",
        'iissi',
        array((int) $user_id, (int) $product_id, $size_standard, $size, $quantity)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? $product['name'] . ' added to your cart.' : 'Cart could not be updated.',
    );
}

function store_update_cart_item($user_id, $cart_id, $quantity)
{
    $cart_id = (int) $cart_id;
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

    if ($quantity === false || (int) $quantity < 1) {
        return array('ok' => false, 'message' => 'Quantity must be a positive whole number. Use Remove to delete an item.');
    }

    $cart_row = database_fetch_one(
        store_db_connection(),
        "SELECT product_id FROM cart WHERE id = ? AND user_id = ? LIMIT 1",
        'ii',
        array($cart_id, (int) $user_id)
    );
    $product = $cart_row ? get_store_product_by_id((int) $cart_row['product_id']) : null;

    if (!$cart_row || !$product) {
        return array('ok' => false, 'message' => 'That cart item is no longer available.');
    }

    $quantity = (int) $quantity;
    $available_stock = max(0, (int) ($product['stock'] ?? 0));
    if ($available_stock <= 0) {
        return array('ok' => false, 'message' => $product['name'] . ' is currently out of stock.');
    }
    if ($quantity > $available_stock) {
        return array('ok' => false, 'message' => 'Only ' . $available_stock . ' unit(s) of ' . $product['name'] . ' are available.');
    }

    $ok = database_execute(
        store_db_connection(),
        "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?",
        'iii',
        array($quantity, $cart_id, (int) $user_id)
    );

    return array('ok' => $ok, 'message' => $ok ? 'Cart quantity updated.' : 'Cart could not be updated.');
}

function store_adjust_cart_item_quantity($user_id, $cart_id, $direction)
{
    $cart_row = database_fetch_one(
        store_db_connection(),
        "SELECT quantity FROM cart WHERE id = ? AND user_id = ? LIMIT 1",
        'ii',
        array((int) $cart_id, (int) $user_id)
    );

    if (!$cart_row) {
        return array('ok' => false, 'message' => 'That cart item is no longer available.');
    }

    $current = max(1, (int) $cart_row['quantity']);
    $next = $direction === 'increase' ? $current + 1 : max(1, $current - 1);
    return store_update_cart_item($user_id, $cart_id, $next);
}

function store_remove_cart_item($user_id, $cart_id)
{
    return database_execute(
        store_db_connection(),
        "DELETE FROM cart WHERE id = ? AND user_id = ?",
        'ii',
        array((int) $cart_id, (int) $user_id)
    );
}

function store_clear_cart($user_id)
{
    return database_execute(
        store_db_connection(),
        "DELETE FROM cart WHERE user_id = ?",
        'i',
        array((int) $user_id)
    );
}

function store_checkout_cart($user_context, $user_id, $payment_method, $payment_reference = '')
{
    $payment_method = store_state_normalize_payment_method($payment_method);
    $payment_reference = trim((string) $payment_reference);
    $cart_items = store_get_cart_items($user_id);

    if (empty($cart_items)) {
        return array('ok' => false, 'message' => 'Your cart is empty. Add products before checkout.', 'orders' => array());
    }

    if ($payment_method === 'Online Payment' && $payment_reference === '') {
        return array('ok' => false, 'message' => 'Enter the GCash reference number for online payment verification.', 'orders' => array());
    }

    $result = store_state_create_cart_checkout($user_context, $cart_items, array(
        'type' => 'Online Order',
        'payment_method' => $payment_method,
        'payment_status' => $payment_method === 'Online Payment' ? 'Pending' : 'Unpaid',
        'payment_reference' => $payment_reference,
        'payment_note' => $payment_method === 'Online Payment'
            ? 'Customer submitted a GCash reference for manual verification.'
            : 'Customer chose to pay in store.',
        'transaction_type' => 'ONLINE',
        'channel' => 'Online checkout',
    ));
    if (!empty($result['ok'])) {
        foreach ($cart_items as $item) {
            store_remove_cart_item($user_id, (int) $item['cart_id']);
        }
    }
    return $result;
}

function store_get_wishlist_product_ids($user_id)
{
    $rows = database_fetch_all(
        store_db_connection(),
        "SELECT product_id FROM wishlist WHERE user_id = ? ORDER BY created_at DESC, id DESC",
        'i',
        array((int) $user_id)
    );

    return array_map(function ($row) {
        return (int) $row['product_id'];
    }, $rows);
}

function store_get_wishlist_products($user_id)
{
    $products = array();

    foreach (store_get_wishlist_product_ids($user_id) as $product_id) {
        $product = get_store_product_by_id($product_id);

        if ($product) {
            $products[] = $product;
        }
    }

    return $products;
}

function store_get_wishlist_count($user_id)
{
    $row = database_fetch_one(
        store_db_connection(),
        "SELECT COUNT(*) AS count FROM wishlist WHERE user_id = ?",
        'i',
        array((int) $user_id)
    );

    return (int) ($row['count'] ?? 0);
}

function store_product_is_wishlisted($user_id, $product_id)
{
    return (bool) database_fetch_one(
        store_db_connection(),
        "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1",
        'ii',
        array((int) $user_id, (int) $product_id)
    );
}

function store_toggle_wishlist_item($user_id, $product_id)
{
    $product = get_store_product_by_id($product_id);

    if (!$product) {
        return array('ok' => false, 'message' => 'Product not found.');
    }

    if (store_product_is_wishlisted($user_id, $product_id)) {
        $ok = database_execute(
            store_db_connection(),
            "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?",
            'ii',
            array((int) $user_id, (int) $product_id)
        );

        return array(
            'ok' => $ok,
            'message' => $ok ? $product['name'] . ' removed from your wishlist.' : 'Wishlist could not be updated.',
        );
    }

    $ok = database_execute(
        store_db_connection(),
        "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)",
        'ii',
        array((int) $user_id, (int) $product_id)
    );

    return array(
        'ok' => $ok,
        'message' => $ok ? $product['name'] . ' saved to your wishlist.' : 'Wishlist could not be updated.',
    );
}

function store_remove_wishlist_item($user_id, $product_id)
{
    return database_execute(
        store_db_connection(),
        "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?",
        'ii',
        array((int) $user_id, (int) $product_id)
    );
}

function store_clear_wishlist($user_id)
{
    return database_execute(
        store_db_connection(),
        "DELETE FROM wishlist WHERE user_id = ?",
        'i',
        array((int) $user_id)
    );
}

function get_store_product_by_slug($slug)
{
    foreach (get_store_products() as $product) {
        if ((string) $product['slug'] === (string) $slug) {
            return $product;
        }
    }

    return null;
}

function get_store_brand_names()
{
    $brands = array();

    foreach (get_store_products() as $product) {
        $brands[] = $product['brand'];
    }

    $brands = array_values(array_unique($brands));
    sort($brands);
    return $brands;
}

function get_store_style_names()
{
    $styles = array();

    foreach (get_store_products() as $product) {
        $styles[] = $product['style'];
    }

    $styles = array_values(array_unique($styles));
    sort($styles);
    return $styles;
}

function get_store_brands()
{
    $copy_map = array(
        'Nike' => 'Fast turnover across running, classic, and slide silhouettes.',
        'Adidas' => 'Strong mix of everyday wearability, sportswear familiarity, and broad size demand.',
        'Jordan' => 'Higher hype value with good upside on premium pairs and slides.',
        'New Balance' => 'Comfort-first runners that work for both casual and serious shoppers.',
        'Crocs' => 'High-volume comfort products that support fast daily conversion.',
        'Puma' => 'Suede-driven casual options with solid margin potential.',
        'Onitsuka Tiger' => 'Retro styling that helps diversify the sneaker wall.',
        'Vans' => 'Skate classics that stay commercially reliable.',
    );
    $brands = array();

    foreach (get_store_brand_names() as $brand) {
        $brands[] = array(
            'name' => $brand,
            'copy' => $copy_map[$brand] ?? ($brand . ' stays relevant in the assortment through recognizable style and dependable retail pull.'),
        );
    }

    return $brands;
}

function get_store_featured_products()
{
    return array_values(array_filter(get_store_products(), function ($product) {
        return !empty($product['featured']);
    }));
}

function get_store_trending_products($limit = 4)
{
    $products = array_values(get_store_products());

    usort($products, function ($left, $right) {
        return (int) $right['trend_score'] <=> (int) $left['trend_score'];
    });

    return array_slice($products, 0, $limit);
}

function get_store_best_sellers($limit = 4)
{
    $products = array_values(get_store_products());

    usort($products, function ($left, $right) {
        return (int) $right['sales_count'] <=> (int) $left['sales_count'];
    });

    return array_slice($products, 0, $limit);
}

function get_store_new_arrivals($limit = 4)
{
    $products = array_values(array_filter(get_store_products(), function ($product) {
        return !empty($product['new_arrival']);
    }));

    usort($products, function ($left, $right) {
        return (int) $right['trend_score'] <=> (int) $left['trend_score'];
    });

    return array_slice($products, 0, $limit);
}

function get_store_limited_products($limit = 4)
{
    $products = array_values(get_store_products());

    usort($products, function ($left, $right) {
        return (int) $right['reservations'] <=> (int) $left['reservations'];
    });

    return array_slice(array_values(array_filter($products, function ($product) {
        return !empty($product['limited']) || !empty($product['is_low_stock']);
    })), 0, $limit);
}

function get_store_recommended_products($options = array())
{
    $products = array_values(get_store_products());
    $exclude_id = isset($options['exclude_id']) ? (int) $options['exclude_id'] : 0;
    $limit = isset($options['limit']) ? (int) $options['limit'] : 4;

    foreach ($products as &$product) {
        $product['_recommendation_score'] = $exclude_id > 0 && (int) $product['id'] === $exclude_id
            ? -1
            : store_recommendation_score($product, $options);
    }
    unset($product);

    usort($products, function ($left, $right) {
        return (int) $right['_recommendation_score'] <=> (int) $left['_recommendation_score'];
    });

    $products = array_values(array_filter($products, function ($product) {
        return (int) $product['_recommendation_score'] >= 0;
    }));

    foreach ($products as &$product) {
        unset($product['_recommendation_score']);
    }
    unset($product);

    return array_slice($products, 0, $limit);
}

function get_store_testimonials()
{
    return array(
        array(
            'name' => 'Mika Santos',
            'role' => 'Repeat customer',
            'rating' => 5,
            'quote' => 'The new product mix feels closer to a real local store. The recommendations finally make sense.',
        ),
        array(
            'name' => 'Andre Cruz',
            'role' => 'Sneaker buyer',
            'rating' => 5,
            'quote' => 'Reservation status, stock pressure, and better pricing signals make the whole shop feel more serious.',
        ),
        array(
            'name' => 'Paula Reyes',
            'role' => 'Lifestyle shopper',
            'rating' => 4,
            'quote' => 'The account page is easier to trust now because orders, profile details, and recommendations live together.',
        ),
    );
}

function get_store_promos()
{
    $active_promotions = store_get_active_promotions(4);

    if (!empty($active_promotions)) {
        return array_map(function ($promotion) {
            $product_name = (string) ($promotion['product_name'] ?? 'Featured product');
            $discount_percent = (float) ($promotion['discount_percent'] ?? 0);

            return array(
                'type' => 'product_promotion',
                'title' => (string) ($promotion['title'] ?? 'Shoestagram Sale'),
                'copy' => $product_name,
                'cta' => 'Reserve sale item',
                'href' => 'product.php?slug=' . urlencode((string) ($promotion['product_slug'] ?? '')),
                'product' => $product_name,
                'original_price' => (float) ($promotion['regular_price'] ?? $promotion['original_price'] ?? 0),
                'sale_price' => (float) ($promotion['sale_price'] ?? 0),
                'discount_percent' => $discount_percent,
                'ends_at' => (string) ($promotion['ends_at'] ?? ''),
            );
        }, $active_promotions);
    }

    return array(
        array(
            'title' => 'High-demand pairs',
            'copy' => 'Focus on the products the pricing engine expects to move first this month.',
            'cta' => 'Open trending footwear',
            'href' => 'shop.php?category=shoes&sort=trending',
        ),
        array(
            'title' => 'Comfort wall',
            'copy' => 'Slides, clogs, and slippers with broad size coverage and fast conversion.',
            'cta' => 'Browse comfort products',
            'href' => 'shop.php?category=slippers',
        ),
    );
}

function get_admin_transactions()
{
    return get_admin_dynamic_transactions(12);
}

function get_admin_alerts()
{
    return get_admin_dynamic_alerts();
}

function get_admin_reservations()
{
    return get_admin_dynamic_reservations(12);
}

function filter_store_products($filters = array())
{
    $products = array_values(get_store_products());
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $anything = strtolower(trim((string) ($filters['anything'] ?? '')));
    $category = strtolower(trim((string) ($filters['category'] ?? 'all')));
    $brand = strtolower(trim((string) ($filters['brand'] ?? '')));
    $tag = strtolower(trim((string) ($filters['tag'] ?? '')));
    $style = strtolower(trim((string) ($filters['style'] ?? '')));
    $size = strtolower(trim((string) ($filters['size'] ?? '')));
    $availability = strtolower(trim((string) ($filters['availability'] ?? '')));
    $price_range = trim((string) ($filters['price_range'] ?? ''));
    $min_price = (float) ($filters['min_price'] ?? 0);
    $max_price = (float) ($filters['max_price'] ?? 0);
    $sort = strtolower(trim((string) ($filters['sort'] ?? 'recommended')));

    if ($price_range !== '') {
        $bounds = store_price_range_bounds($price_range);
        $min_price = (float) ($bounds['min'] ?? 0);
        $max_price = (float) ($bounds['max'] ?? 0);
    }

    $products = array_values(array_filter($products, function ($product) use ($search, $anything, $category, $brand, $tag, $style, $size, $availability, $min_price, $max_price) {
        if ($category !== '' && $category !== 'all' && strtolower((string) $product['category']) !== $category) {
            return false;
        }

        if ($brand !== '' && strtolower((string) $product['brand']) !== $brand) {
            return false;
        }

        if ($tag !== '') {
            if ($tag === 'limited' && empty($product['limited']) && empty($product['is_low_stock'])) {
                return false;
            }

            if ($tag === 'featured' && empty($product['featured'])) {
                return false;
            }

            if ($tag === 'trending' && empty($product['trending'])) {
                return false;
            }

            if ($tag === 'new' && empty($product['new_arrival'])) {
                return false;
            }

            if ($tag === 'best' && empty($product['best_seller'])) {
                return false;
            }
        }

        if ($style !== '') {
            $style_match = strpos(strtolower((string) $product['style']), $style) !== false
                || in_array($style, array_map('strtolower', $product['preference_tags']), true);

            if (!$style_match) {
                return false;
            }
        }

        if ($size !== '' && !in_array($size, array_map('strtolower', store_product_all_sizes($product)), true)) {
            return false;
        }

        if ($availability === 'in-stock' && (int) $product['stock'] <= 0) {
            return false;
        }

        if ($availability === 'low-stock' && empty($product['is_low_stock'])) {
            return false;
        }

        if ($availability === 'sold-out' && (int) $product['stock'] > 0) {
            return false;
        }

        if (!store_price_within_bounds($product['price'], $min_price, $max_price)) {
            return false;
        }

        $free_text = trim($search . ' ' . $anything);

        if ($free_text !== '') {
            $haystack = strtolower(implode(' ', array(
                $product['name'],
                $product['brand'],
                $product['category'],
                $product['style'],
                $product['badge'],
                $product['tag'],
                $product['condition'],
                $product['stock_status'],
                $product['colorway'],
                $product['blurb'],
                $product['description'],
                implode(' ', $product['preference_tags']),
                implode(' ', store_product_size_search_terms($product)),
                'size sizes available availability price stock',
                store_format_money($product['price']),
                (string) $product['price'],
            )));

            foreach (preg_split('/\s+/', $free_text) as $term) {
                $term = trim((string) $term);

                if ($term !== '' && strpos($haystack, $term) === false) {
                    return false;
                }
            }

            if (strpos($haystack, $free_text) === false && substr_count($free_text, ' ') === 0) {
                return false;
            }
        }

        return true;
    }));

    usort($products, function ($left, $right) use ($sort) {
        if ($sort === 'price-low') {
            return (float) $left['price'] <=> (float) $right['price'];
        }

        if ($sort === 'price-high') {
            return (float) $right['price'] <=> (float) $left['price'];
        }

        if ($sort === 'rating') {
            return (float) $right['rating'] <=> (float) $left['rating'];
        }

        if ($sort === 'newest') {
            return (int) $right['new_arrival'] <=> (int) $left['new_arrival']
                ?: (int) $right['trend_score'] <=> (int) $left['trend_score'];
        }

        if ($sort === 'best') {
            return (int) $right['sales_count'] <=> (int) $left['sales_count'];
        }

        if ($sort === 'trending') {
            return (int) $right['trend_score'] <=> (int) $left['trend_score'];
        }

        return store_recommendation_score($right) <=> store_recommendation_score($left);
    });

    return $products;
}

function get_store_story_highlights()
{
    return array(
        array(
            'title' => 'Best sneakers',
            'meta' => 'Strong demand this week',
            'href' => 'shop.php?category=shoes&sort=trending',
            'icon' => 'fa-fire-flame-curved',
        ),
        array(
            'title' => 'Slide wall',
            'meta' => 'Comfort-driven pairs',
            'href' => 'shop.php?category=slippers',
            'icon' => 'fa-bolt',
        ),
        array(
            'title' => 'Fresh apparel',
            'meta' => 'Tops and bottoms',
            'href' => 'shop.php?category=tops',
            'icon' => 'fa-shirt',
        ),
        array(
            'title' => 'Low-stock watch',
            'meta' => 'Reserve before sell-out',
            'href' => 'shop.php?tag=limited',
            'icon' => 'fa-star',
        ),
        array(
            'title' => 'Daily add-ons',
            'meta' => 'Socks and extras',
            'href' => 'shop.php?category=socks',
            'icon' => 'fa-bag-shopping',
        ),
    );
}

function get_store_similar_products($reference_product, $limit = 4)
{
    $reference_id = (int) ($reference_product['id'] ?? 0);
    $category = (string) ($reference_product['category'] ?? '');
    $brand = (string) ($reference_product['brand'] ?? '');
    $products = array_values(array_filter(get_store_products(), function ($product) use ($reference_id, $category, $brand) {
        if ((int) $product['id'] === $reference_id) {
            return false;
        }

        return $product['category'] === $category || $product['brand'] === $brand;
    }));

    usort($products, function ($left, $right) {
        return (int) $right['trend_score'] <=> (int) $left['trend_score'];
    });

    return array_slice($products, 0, $limit);
}

function get_store_recent_reviews($limit = 6)
{
    $reviews = array_values(array_filter(store_state_get_reviews(), function ($review) {
        return (string) ($review['status'] ?? 'Published') === 'Published'
            && !store_state_review_is_demo($review);
    }));

    usort($reviews, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    $reviews = array_slice($reviews, 0, max(0, (int) $limit));
    if (count($reviews) < $limit && function_exists('shoestagram_demo_reviews_for_product')) {
        foreach (get_store_products() as $product) {
            foreach (shoestagram_demo_reviews_for_product($product) as $demo_review) {
                $reviews[] = $demo_review;
                if (count($reviews) >= $limit) {
                    break 2;
                }
            }
        }
    }

    return array_slice($reviews, 0, $limit);
}

function store_review_display_name($name)
{
    $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name))));
    if (empty($parts)) {
        return 'Shoestagram Member';
    }

    return count($parts) > 1
        ? $parts[0] . ' ' . strtoupper(substr($parts[count($parts) - 1], 0, 1)) . '.'
        : $parts[0];
}

function get_store_product_reviews($product_id, $include_moderated = false, $include_demo_when_empty = true)
{
    $product_id = (int) $product_id;

    if ($product_id <= 0) {
        return array();
    }

    $reviews = array_values(array_filter(store_state_get_reviews(), function ($review) use ($product_id, $include_moderated) {
        if ((int) ($review['product_id'] ?? 0) !== $product_id) {
            return false;
        }

        if (store_state_review_is_demo($review)) {
            return false;
        }

        return $include_moderated || (string) ($review['status'] ?? 'Published') === 'Published';
    }));

    $orders_by_id = array();
    foreach (store_state_get_orders() as $order) {
        $orders_by_id[(string) ($order['id'] ?? '')] = $order;
    }

    foreach ($reviews as &$review) {
        $order = $orders_by_id[(string) ($review['order_id'] ?? '')] ?? null;
        $review_email = strtolower(trim((string) ($review['customer_email'] ?? '')));
        $order_email = strtolower(trim((string) ($order['customer_email'] ?? '')));
        $review['verified_purchase'] = is_array($order)
            && (string) ($order['status'] ?? '') === 'Completed'
            && (int) ($order['product_id'] ?? 0) === $product_id
            && $review_email !== ''
            && hash_equals($order_email, $review_email);
        $review['customer_name'] = store_review_display_name($review['customer_name'] ?? 'Shoestagram Member');
        $review['size'] = $review['verified_purchase'] ? (string) ($order['size'] ?? '') : '';
        $review['size_standard'] = $review['verified_purchase'] ? (string) ($order['size_standard'] ?? 'US') : '';
        $review['is_demo'] = false;
    }
    unset($review);

    usort($reviews, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    if (empty($reviews) && !$include_moderated && $include_demo_when_empty && function_exists('shoestagram_demo_reviews_for_product')) {
        $product = get_store_product_by_id($product_id);
        return $product ? shoestagram_demo_reviews_for_product($product) : array();
    }

    return $reviews;
}

function get_store_recommendation_reason($product, $options = array())
{
    $preferred_brand = strtolower(trim((string) ($options['brand'] ?? '')));
    $preferred_style = strtolower(trim((string) ($options['style'] ?? '')));
    $max_price = (float) ($options['max_price'] ?? 0);

    if ($preferred_brand !== '' && strtolower((string) $product['brand']) === $preferred_brand) {
        return 'Matches your preferred brand';
    }

    if ($preferred_style !== '' && (strpos(strtolower((string) $product['style']), $preferred_style) !== false || in_array($preferred_style, array_map('strtolower', $product['preference_tags']), true))) {
        return 'Aligned with your style preference';
    }

    if ($max_price > 0 && (float) $product['price'] <= $max_price) {
        return 'Fits your current budget range';
    }

    if (!empty($product['is_low_stock'])) {
        return 'Low stock and high reservation pressure';
    }

    if (!empty($product['trending'])) {
        return 'Trending strongly with shoppers right now';
    }

    return 'Recommended from ratings, demand, and availability';
}

function store_profile_matches_size($product, $profile)
{
    $sizes = store_product_all_sizes($product);
    $category = (string) ($product['category'] ?? '');
    $shoe_size = trim((string) ($profile['shoe_size'] ?? ''));
    $apparel_size = trim((string) ($profile['apparel_size'] ?? ''));

    if (in_array($category, array('shoes', 'slippers', 'sandals'), true)) {
        return $shoe_size !== '' && in_array($shoe_size, $sizes, true);
    }

    return $apparel_size !== '' && in_array($apparel_size, $sizes, true);
}

function get_store_personalized_products($profile, $limit = 6, $preferred_category = '')
{
    $products = array_values(get_store_products());
    $brands = array_map('strtolower', isset($profile['brands']) && is_array($profile['brands']) ? $profile['brands'] : array());
    $categories = array_map('strtolower', isset($profile['categories']) && is_array($profile['categories']) ? $profile['categories'] : array());
    $styles = array_map('strtolower', isset($profile['styles']) && is_array($profile['styles']) ? $profile['styles'] : array());
    $budget_max = (float) ($profile['budget_max'] ?? 0);
    $preferred_category = strtolower(trim((string) $preferred_category));

    foreach ($products as &$product) {
        $score = store_recommendation_score($product);
        $product_brand = strtolower((string) $product['brand']);
        $product_category = strtolower((string) $product['category']);
        $product_style = strtolower((string) $product['style']);
        $preference_tags = array_map('strtolower', $product['preference_tags']);

        if (!empty($brands) && in_array($product_brand, $brands, true)) {
            $score += 24;
        }

        if (!empty($categories) && in_array($product_category, $categories, true)) {
            $score += 22;
        }

        if ($preferred_category !== '' && $product_category === $preferred_category) {
            $score += 18;
        }

        foreach ($styles as $style) {
            if ($style !== '' && (strpos($product_style, $style) !== false || in_array($style, $preference_tags, true))) {
                $score += 16;
                break;
            }
        }

        if ($budget_max > 0 && (float) $product['price'] <= $budget_max) {
            $score += 12;
        }

        if (store_profile_matches_size($product, $profile)) {
            $score += 28;
        }

        if ((int) $product['stock'] > 0) {
            $score += 6;
        }

        $product['_personalized_score'] = $score;
    }
    unset($product);

    usort($products, function ($left, $right) {
        return (int) $right['_personalized_score'] <=> (int) $left['_personalized_score'];
    });

    foreach ($products as &$product) {
        unset($product['_personalized_score']);
    }
    unset($product);

    return array_slice($products, 0, $limit);
}

function get_store_profile_heading($profile)
{
    $categories = isset($profile['categories']) && is_array($profile['categories']) ? $profile['categories'] : array();
    $styles = isset($profile['styles']) && is_array($profile['styles']) ? $profile['styles'] : array();

    if (in_array('shoes', $categories, true) || in_array('slippers', $categories, true) || in_array('sandals', $categories, true)) {
        if (!empty($styles)) {
            return get_store_category_label('shoes') . ' For Your ' . $styles[0] . ' Style';
        }

        return 'Footwear Picked For You';
    }

    if (!empty($styles)) {
        return $styles[0] . ' Pieces Chosen For You';
    }

    return 'Picked For Your Preferences';
}

function get_admin_dynamic_transactions($limit = 8)
{
    $orders = store_state_get_orders();
    $checkout_counts = array();
    foreach ($orders as $order) {
        $reference = store_state_checkout_reference($order);
        $checkout_counts[$reference] = ($checkout_counts[$reference] ?? 0) + 1;
    }
    $checkout_seen = array();
    $transactions = array_map(function ($order) use ($checkout_counts, &$checkout_seen) {
        $reference = store_state_checkout_reference($order);
        $checkout_seen[$reference] = ($checkout_seen[$reference] ?? 0) + 1;
        return array(
            'reference' => $reference,
            'transaction_key' => 'system:' . $reference,
            'checkout_item_count' => $checkout_counts[$reference],
            'checkout_item_number' => $checkout_seen[$reference],
            'customer' => $order['customer_name'],
            'product_id' => (int) ($order['product_id'] ?? 0),
            'product_name' => (string) ($order['product_name'] ?? ''),
            'size_standard' => store_normalize_size_standard($order['size_standard'] ?? 'US'),
            'size' => (string) ($order['size'] ?? ''),
            'size_display' => store_order_size_display($order),
            'amount' => (float) $order['subtotal'],
            'status' => $order['status'],
            'channel' => $order['channel'],
            'transaction_type' => store_state_channel_type($order['transaction_type'] ?? ($order['channel'] ?? 'ONLINE')),
            'created_at' => $order['created_at'] ?? '',
            'quantity' => max(0, (int) ($order['quantity'] ?? 0)),
            'source' => 'system',
            'source_label' => store_manual_sale_source_label('system'),
            'counts_for_analytics' => store_forecast_order_counts_as_completed_sale($order),
            'analytics_note' => store_system_order_is_after_historical_import($order) ? '' : 'Covered by the initialized historical dataset',
        );
    }, $orders);

    foreach (store_get_manual_sales() as $sale) {
        $transactions[] = array(
            'reference' => trim((string) ($sale['external_reference'] ?? '')) !== ''
                ? (string) $sale['external_reference']
                : 'HIST-' . str_pad((string) ((int) ($sale['id'] ?? 0)), 5, '0', STR_PAD_LEFT),
            'transaction_key' => 'manual:' . (int) ($sale['id'] ?? 0),
            'customer' => ($sale['provenance'] ?? '') === 'historical_import' ? 'Initialized historical record' : 'External store record',
            'product_id' => (int) ($sale['product_id'] ?? 0),
            'product_name' => (string) ($sale['product_name'] ?? ''),
            'size_standard' => (string) ($sale['size_standard'] ?? ''),
            'size' => (string) ($sale['size'] ?? ''),
            'size_display' => trim((string) ($sale['size'] ?? '')) !== ''
                ? store_size_display($sale['size'], $sale['size_standard'] ?? 'US')
                : 'Size not recorded',
            'amount' => (float) ($sale['total_amount'] ?? 0),
            'status' => 'Completed',
            'channel' => ($sale['transaction_type'] ?? '') === 'walk_in' ? 'Walk-in' : 'Online',
            'transaction_type' => ($sale['transaction_type'] ?? '') === 'walk_in' ? 'WALK-IN' : 'ONLINE',
            'created_at' => (string) ($sale['sale_date'] ?? '') . ' 12:00:00',
            'quantity' => max(0, (int) ($sale['quantity'] ?? 0)),
            'source' => 'manual',
            'provenance' => (string) ($sale['provenance'] ?? 'manual_entry'),
            'source_label' => store_manual_sale_source_label('manual', $sale['provenance'] ?? ''),
            'counts_for_analytics' => true,
            'analytics_note' => '',
        );
    }

    usort($transactions, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return $limit > 0 ? array_slice($transactions, 0, $limit) : $transactions;
}

function get_admin_dynamic_reservations($limit = 8)
{
    $orders = array_values(array_filter(store_state_get_orders(), function ($order) {
        return store_state_is_active_reservation((string) ($order['status'] ?? ''));
    }));

    usort($orders, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return array_slice($orders, 0, $limit);
}

function get_admin_dynamic_reviews($limit = 8)
{
    $reviews = store_state_get_reviews();

    usort($reviews, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return array_slice($reviews, 0, $limit);
}

function get_admin_dynamic_messages($limit = 6)
{
    $messages = store_state_get_messages();

    usort($messages, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return array_slice($messages, 0, $limit);
}

function get_admin_dynamic_alerts()
{
    $products = get_store_products();
    $alerts = array();
    $low_stock = array_values(array_filter($products, function ($product) {
        return !empty($product['is_low_stock']);
    }));
    $overstock = array_values(array_filter($products, function ($product) {
        return !empty($product['is_overstock']);
    }));

    usort($low_stock, function ($left, $right) {
        return (int) $left['stock'] <=> (int) $right['stock'];
    });

    usort($overstock, function ($left, $right) {
        return (int) $right['stock'] <=> (int) $left['stock'];
    });

    foreach (array_slice($low_stock, 0, 3) as $product) {
        $alerts[] = array(
            'level' => 'warning',
            'title' => 'Low stock',
            'copy' => $product['name'] . ' is down to ' . $product['stock'] . ' unit(s).',
        );
    }

    foreach (array_slice($overstock, 0, 2) as $product) {
        $alerts[] = array(
            'level' => 'info',
            'title' => 'Overstock',
            'copy' => $product['name'] . ' is sitting above its suggested stock level.',
        );
    }

    $best_seller = get_store_best_sellers(1);
    if (!empty($best_seller)) {
        $alerts[] = array(
            'level' => 'success',
            'title' => 'Top seller',
            'copy' => $best_seller[0]['name'] . ' is leading modeled demand this month.',
        );
    }

    return array_slice($alerts, 0, 6);
}

function store_get_completed_revenue()
{
    $completed_revenue = 0;
    $completed_orders = 0;
    $counted_checkouts = array();

    foreach (store_state_get_orders() as $order) {
        if (store_forecast_order_counts_as_completed_sale($order)) {
            $completed_revenue += (float) ($order['subtotal'] ?? 0);
            $reference = store_state_checkout_reference($order);
            if (!isset($counted_checkouts[$reference])) {
                $completed_orders++;
                $counted_checkouts[$reference] = true;
            }
        }
    }

    foreach (store_get_manual_sales() as $sale) {
        $completed_revenue += (float) ($sale['total_amount'] ?? 0);
        $completed_orders++;
    }

    return array(
        'revenue' => round($completed_revenue, 2),
        'completed_orders' => $completed_orders,
    );
}

function store_get_sales_overview()
{
    $products = get_store_products();
    $manila = new DateTimeZone('Asia/Manila');
    $today = new DateTimeImmutable('today', $manila);
    $day_start = $today->format('Y-m-d');
    $week_start = $today->modify('monday this week')->format('Y-m-d');
    $month_start = $today->format('Y-m-01');
    $period_sales = array('daily' => 0.0, 'weekly' => 0.0, 'monthly' => 0.0);
    foreach (get_admin_dynamic_transactions(0) as $transaction) {
        if (($transaction['status'] ?? '') !== 'Completed' || !store_transaction_counts_for_analytics($transaction)) {
            continue;
        }
        if (trim((string) ($transaction['created_at'] ?? '')) === '') {
            continue;
        }
        try {
            $sale_date = (new DateTimeImmutable((string) ($transaction['created_at'] ?? ''), $manila))
                ->setTimezone($manila)->format('Y-m-d');
        } catch (Exception $exception) {
            continue;
        }
        if ($sale_date > $day_start) {
            continue;
        }
        $amount = (float) ($transaction['amount'] ?? 0);
        if ($sale_date === $day_start) {
            $period_sales['daily'] += $amount;
        }
        if ($sale_date >= $week_start) {
            $period_sales['weekly'] += $amount;
        }
        if ($sale_date >= $month_start) {
            $period_sales['monthly'] += $amount;
        }
    }
    $forecast_next = store_get_sales_projection();
    $actual = store_get_completed_revenue();

    return array(
        'daily_sales' => round($period_sales['daily'], 2),
        'weekly_sales' => round($period_sales['weekly'], 2),
        'monthly_sales' => round($period_sales['monthly'], 2),
        'forecast_next_month_revenue' => $forecast_next['total_revenue_30d'],
        'forecast_next_month_units' => $forecast_next['total_units_30d'],
        'actual_completed_revenue' => $actual['revenue'],
        'actual_completed_orders' => $actual['completed_orders'],
        'inventory_cost_value' => round(array_sum(array_map(function ($product) {
            return (float) $product['cost_current'] * (int) $product['stock'];
        }, $products)), 2),
        'inventory_retail_value' => round(array_sum(array_map(function ($product) {
            return (float) $product['price'] * (int) $product['stock'];
        }, $products)), 2),
    );
}

function store_get_sales_history($filters = array())
{
    $filters = is_array($filters) ? $filters : array();
    $selected_product_id = (int) ($filters['product_id'] ?? 0);
    $date_from = trim((string) ($filters['date_from'] ?? ''));
    $date_to = trim((string) ($filters['date_to'] ?? ''));
    $source = strtolower(trim((string) ($filters['source'] ?? '')));
    $channel = strtoupper(trim((string) ($filters['channel'] ?? '')));
    $combined = array();

    foreach (get_admin_dynamic_transactions(0) as $transaction) {
        if (($transaction['status'] ?? '') !== 'Completed' || !store_transaction_counts_for_analytics($transaction)) {
            continue;
        }

        $date = substr((string) ($transaction['created_at'] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }
        if ($date_from !== '' && $date < $date_from) {
            continue;
        }
        if ($date_to !== '' && $date > $date_to) {
            continue;
        }
        if ($selected_product_id > 0 && (int) ($transaction['product_id'] ?? 0) !== $selected_product_id) {
            continue;
        }
        if (in_array($source, array('system', 'manual'), true) && ($transaction['source'] ?? '') !== $source) {
            continue;
        }
        if (in_array($channel, array('WALK-IN', 'ONLINE'), true) && ($transaction['transaction_type'] ?? '') !== $channel) {
            continue;
        }

        $month = substr($date, 0, 7);
        if (!isset($combined[$month])) {
            $combined[$month] = array(
                'label' => date('M Y', strtotime($month . '-01')),
                'month' => $month,
                'units' => 0,
                'revenue' => 0.0,
            );
        }
        $combined[$month]['units'] += max(0, (int) ($transaction['quantity'] ?? 0));
        $combined[$month]['revenue'] += max(0, (float) ($transaction['amount'] ?? 0));
    }

    if ($date_from === '' && $date_to === '') {
        $recent = array();
        for ($offset = 5; $offset >= 0; $offset--) {
            $month = date('Y-m', strtotime(date('Y-m-01') . ' -' . $offset . ' months'));
            $recent[$month] = $combined[$month] ?? array(
                'label' => date('M Y', strtotime($month . '-01')),
                'month' => $month,
                'units' => 0,
                'revenue' => 0.0,
            );
        }
        $combined = $recent;
    } elseif (empty($combined)) {
        $month = preg_match('/^\d{4}-\d{2}/', $date_from) ? substr($date_from, 0, 7) : date('Y-m');
        $combined[$month] = array(
            'label' => date('M Y', strtotime($month . '-01')),
            'month' => $month,
            'units' => 0,
            'revenue' => 0.0,
        );
    }

    ksort($combined);
    $history = array_values(array_map(function ($row) {
        $row['revenue'] = round((float) $row['revenue'], 2);
        return $row;
    }, $combined));

    return array(
        'history' => $history,
        'max_revenue' => !empty($history) ? max(array_map(function ($row) {
            return (float) $row['revenue'];
        }, $history)) : 1,
        'max_units' => !empty($history) ? max(array_map(function ($row) {
            return (int) $row['units'];
        }, $history)) : 1,
    );
}

function store_get_product_sales_summary($filters = array())
{
    $history_filters = is_array($filters) ? $filters : array();
    $summary = array();

    foreach (get_admin_dynamic_transactions(0) as $transaction) {
        if (($transaction['status'] ?? '') !== 'Completed' || !store_transaction_counts_for_analytics($transaction)) {
            continue;
        }

        $date = substr((string) ($transaction['created_at'] ?? ''), 0, 10);
        if (($history_filters['date_from'] ?? '') !== '' && $date < $history_filters['date_from']) {
            continue;
        }
        if (($history_filters['date_to'] ?? '') !== '' && $date > $history_filters['date_to']) {
            continue;
        }
        $source = strtolower(trim((string) ($history_filters['source'] ?? '')));
        if (in_array($source, array('system', 'manual'), true) && ($transaction['source'] ?? '') !== $source) {
            continue;
        }
        $channel = strtoupper(trim((string) ($history_filters['channel'] ?? '')));
        if (in_array($channel, array('WALK-IN', 'ONLINE'), true) && ($transaction['transaction_type'] ?? '') !== $channel) {
            continue;
        }

        $product_id = (int) ($transaction['product_id'] ?? 0);
        if ($product_id <= 0 || ((int) ($history_filters['product_id'] ?? 0) > 0 && (int) $history_filters['product_id'] !== $product_id)) {
            continue;
        }
        if (!isset($summary[$product_id])) {
            $summary[$product_id] = array('product_id' => $product_id, 'units' => 0, 'revenue' => 0.0, 'records' => 0);
        }
        $summary[$product_id]['units'] += max(0, (int) ($transaction['quantity'] ?? 0));
        $summary[$product_id]['revenue'] += max(0, (float) ($transaction['amount'] ?? 0));
        $summary[$product_id]['records']++;
    }

    return $summary;
}

function store_get_sales_projection()
{
    $products = get_store_products();
    $total_units_30d = 0;
    $total_units_90d = 0;
    $total_revenue_30d = 0;
    $products_needing_restock = array();
    $forecast_products = array();

    foreach ($products as $product) {
        $forecast = store_get_product_forecast_result($product);
        $total_units_30d += (int) $forecast['next_30d_units'];
        $total_units_90d += (int) $forecast['next_90d_units'];
        $total_revenue_30d += (float) $forecast['next_30d_revenue'];

        $forecast_product = array_merge($product, $forecast);
        $forecast_products[] = $forecast_product;

        if ($forecast['reorder_gap'] > 0) {
            $products_needing_restock[] = $forecast_product;
        }
    }

    usort($products_needing_restock, function ($left, $right) {
        return (int) $right['reorder_gap'] <=> (int) $left['reorder_gap'];
    });

    usort($forecast_products, function ($left, $right) {
        return (int) $right['reorder_gap'] <=> (int) $left['reorder_gap'];
    });

    return array(
        'total_units_30d' => $total_units_30d,
        'total_units_90d' => $total_units_90d,
        'total_revenue_30d' => round($total_revenue_30d, 2),
        'restock_priority' => array_slice($products_needing_restock, 0, 8),
        'products' => $forecast_products,
    );
}

function store_get_inventory_snapshot()
{
    $products = get_store_products();
    $low_stock_products = array_values(array_filter($products, function ($product) {
        return !empty($product['is_low_stock']);
    }));
    $overstock_products = array_values(array_filter($products, function ($product) {
        return !empty($product['is_overstock']);
    }));

    usort($low_stock_products, function ($left, $right) {
        return (int) $left['stock'] <=> (int) $right['stock'];
    });

    usort($overstock_products, function ($left, $right) {
        return (int) $right['stock'] <=> (int) $left['stock'];
    });

    return array(
        'low_stock_products' => $low_stock_products,
        'overstock_products' => $overstock_products,
        'inventory_units' => array_sum(array_map(function ($product) {
            return (int) $product['stock'];
        }, $products)),
        'inventory_cost_value' => round(array_sum(array_map(function ($product) {
            return (float) $product['cost_current'] * (int) $product['stock'];
        }, $products)), 2),
        'inventory_retail_value' => round(array_sum(array_map(function ($product) {
            return (float) $product['price'] * (int) $product['stock'];
        }, $products)), 2),
    );
}

function store_get_price_analytics($filters = array())
{
    $selected_product_id = isset($filters['product_id']) ? (int) $filters['product_id'] : 0;
    $category = strtolower(trim((string) ($filters['category'] ?? '')));
    $brand = strtolower(trim((string) ($filters['brand'] ?? '')));
    $products = array_values(array_filter(get_store_products(), function ($product) use ($selected_product_id, $category, $brand) {
        if ($selected_product_id > 0 && (int) $product['id'] !== $selected_product_id) {
            return false;
        }

        if ($category !== '' && strtolower((string) $product['category']) !== $category) {
            return false;
        }

        if ($brand !== '' && strtolower((string) $product['brand']) !== $brand) {
            return false;
        }

        return true;
    }));

    $rows = array_map('store_price_analytics_row', $products);

    usort($rows, function ($left, $right) {
        return (float) $right['forecast_revenue_optimal'] <=> (float) $left['forecast_revenue_optimal'];
    });

    $opportunities = array_values(array_filter($rows, function ($row) {
        return abs((float) $row['price_gap']) >= 50;
    }));

    return array(
        'rows' => $rows,
        'opportunities' => array_slice($opportunities, 0, 8),
        'forecast_revenue_current' => round(array_sum(array_map(function ($row) {
            return (float) $row['forecast_revenue_current'];
        }, $rows)), 2),
        'forecast_revenue_optimal' => round(array_sum(array_map(function ($row) {
            return (float) $row['forecast_revenue_optimal'];
        }, $rows)), 2),
        'average_margin_percent' => !empty($rows)
            ? round(array_sum(array_map(function ($row) {
                return (float) $row['margin_percent'];
            }, $rows)) / count($rows), 1)
            : 0,
    );
}

function store_get_recommendation_insights()
{
    $products = get_store_products();
    $most_viewed = $products;
    $most_reserved = $products;
    $most_recommended = $products;

    usort($most_viewed, function ($left, $right) {
        return (int) $right['views_count'] <=> (int) $left['views_count'];
    });

    usort($most_reserved, function ($left, $right) {
        return (int) $right['reservations'] <=> (int) $left['reservations'];
    });

    usort($most_recommended, function ($left, $right) {
        return store_recommendation_score($right) <=> store_recommendation_score($left);
    });

    $analytics = store_state_get_analytics();

    return array(
        'most_viewed' => array_slice($most_viewed, 0, 6),
        'most_reserved' => array_slice($most_reserved, 0, 6),
        'most_recommended' => array_slice($most_recommended, 0, 6),
        'recommendation_resets' => (int) ($analytics['recommendation_resets'] ?? 0),
        'last_reset_at' => (string) ($analytics['last_reset_at'] ?? ''),
    );
}
