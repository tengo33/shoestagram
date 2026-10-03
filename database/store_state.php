<?php

function store_state_runtime_dir()
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'runtime';
}

function store_state_file_path($name)
{
    return store_state_runtime_dir() . DIRECTORY_SEPARATOR . $name . '.json';
}

function store_state_default_orders()
{
    return array(
        array(
            'id' => 'ORD-260428-A1F9',
            'type' => 'Reservation',
            'customer_name' => 'Demo Customer',
            'customer_email' => 'demo@shoestagram.com',
            'product_id' => 8,
            'product_name' => 'Harbor Mesh Runner',
            'brand' => 'New Balance',
            'size' => '9',
            'quantity' => 1,
            'price_each' => 142.00,
            'subtotal' => 142.00,
            'status' => 'Completed',
            'pickup_window' => 'Collected',
            'channel' => 'Reservation pickup',
            'created_at' => '2026-04-28T09:45:00+08:00',
            'updated_at' => '2026-04-29T18:20:00+08:00',
            'review_submitted' => false,
        ),
        array(
            'id' => 'ORD-260429-B7D3',
            'type' => 'Reservation',
            'customer_name' => 'Demo Customer',
            'customer_email' => 'demo@shoestagram.com',
            'product_id' => 12,
            'product_name' => 'Ridge Waffle Tee',
            'brand' => 'Shoestagram Label',
            'size' => 'M',
            'quantity' => 1,
            'price_each' => 42.00,
            'subtotal' => 42.00,
            'status' => 'Ready for Pickup',
            'pickup_window' => 'Pickup within 48 hours',
            'channel' => 'Reservation hold',
            'created_at' => '2026-04-29T14:10:00+08:00',
            'updated_at' => '2026-04-30T11:45:00+08:00',
            'review_submitted' => false,
        ),
        array(
            'id' => 'ORD-260430-C2K4',
            'type' => 'Reservation',
            'customer_name' => 'Paula Reyes',
            'customer_email' => 'paula@shoestagram.com',
            'product_id' => 1,
            'product_name' => 'Monarch Runner',
            'brand' => 'Nike',
            'size' => '10',
            'quantity' => 1,
            'price_each' => 134.00,
            'subtotal' => 134.00,
            'status' => 'Confirmed',
            'pickup_window' => 'Pickup tomorrow, 2PM to 6PM',
            'channel' => 'Reservation confirmation',
            'created_at' => '2026-04-30T08:30:00+08:00',
            'updated_at' => '2026-04-30T10:00:00+08:00',
            'review_submitted' => false,
        ),
        array(
            'id' => 'ORD-260501-D8L6',
            'type' => 'Reservation',
            'customer_name' => 'Andre Cruz',
            'customer_email' => 'andre@shoestagram.com',
            'product_id' => 3,
            'product_name' => 'Studio Heavy Tee',
            'brand' => 'Essentials',
            'size' => 'L',
            'quantity' => 2,
            'price_each' => 38.00,
            'subtotal' => 76.00,
            'status' => 'Pending',
            'pickup_window' => 'Awaiting confirmation',
            'channel' => 'Walk-in reservation',
            'created_at' => '2026-05-01T09:15:00+08:00',
            'updated_at' => '2026-05-01T09:15:00+08:00',
            'review_submitted' => false,
        ),
    );
}

function store_state_default_reviews()
{
    return array(
        array(
            'id' => 'REV-260426-X1',
            'order_id' => 'ORD-260426-LEG1',
            'product_id' => 3,
            'product_name' => 'Studio Heavy Tee',
            'customer_name' => 'Mika Santos',
            'customer_email' => 'mika@shoestagram.com',
            'rating' => 5,
            'comment' => 'Premium fabric, strong shape, and it layers perfectly with neutral sneakers.',
            'created_at' => '2026-04-26T12:30:00+08:00',
            'status' => 'Published',
        ),
        array(
            'id' => 'REV-260427-Y2',
            'order_id' => 'ORD-260427-LEG2',
            'product_id' => 8,
            'product_name' => 'Harbor Mesh Runner',
            'customer_name' => 'Luis Tan',
            'customer_email' => 'luis@shoestagram.com',
            'rating' => 5,
            'comment' => 'Feels like a current performance runner but still fits into an everyday streetwear rotation.',
            'created_at' => '2026-04-27T16:50:00+08:00',
            'status' => 'Published',
        ),
        array(
            'id' => 'REV-260430-Z3',
            'order_id' => 'ORD-260430-LEG3',
            'product_id' => 9,
            'product_name' => 'Civic Pleated Short',
            'customer_name' => 'Paula Reyes',
            'customer_email' => 'paula@shoestagram.com',
            'rating' => 4,
            'comment' => 'Tailored enough for cleaner outfits, but still relaxed enough for daily wear.',
            'created_at' => '2026-04-30T19:20:00+08:00',
            'status' => 'Published',
        ),
    );
}

function store_state_review_is_demo($review)
{
    $review = is_array($review) ? $review : array();
    $source = strtolower(trim((string) ($review['source'] ?? '')));
    $order_id = strtoupper(trim((string) ($review['order_id'] ?? '')));

    return !empty($review['is_demo'])
        || in_array($source, array('demo', 'development_seed'), true)
        || strpos($order_id, '-LEG') !== false
        || strpos($order_id, '-DEMO') !== false;
}

function store_state_default_messages()
{
    return array(
        array(
            'id' => 'MSG-260430-A11',
            'name' => 'Jules Navarro',
            'email' => 'jules@example.com',
            'subject' => 'Sizing help',
            'message' => 'Can you recommend a size between 9 and 9.5 for the Harbor Mesh Runner?',
            'created_at' => '2026-04-30T17:10:00+08:00',
        ),
    );
}

function store_state_default_user_profiles()
{
    return array(
        'demo@shoestagram.com' => array(
            'brands' => array('Nike', 'New Balance'),
            'categories' => array('shoes', 'tops'),
            'styles' => array('Lifestyle sneaker', 'Streetwear top'),
            'shoe_size' => '9',
            'apparel_size' => 'L',
            'budget_max' => '2500',
            'updated_at' => '2026-05-05T09:00:00+08:00',
        ),
    );
}

function store_state_default_analytics()
{
    return array(
        'product_views' => array(),
        'recommendation_resets' => 0,
        'last_reset_at' => '',
    );
}

function store_state_normalize_payment_method($value)
{
    $value = strtolower(trim((string) $value));

    if ($value === 'online' || $value === 'gcash' || $value === 'online payment') {
        return 'Online Payment';
    }

    if ($value === 'in-store' || $value === 'instore' || $value === 'store' || $value === 'in-store payment') {
        return 'In-Store Payment';
    }

    return $value !== '' ? ucwords($value) : 'In-Store Payment';
}

function store_state_normalize_payment_status($value, $method = '')
{
    $value = trim((string) $value);
    $allowed = array('Pending', 'Paid', 'Unpaid', 'Failed/Rejected');

    if (in_array($value, $allowed, true)) {
        return $value;
    }

    return store_state_normalize_payment_method($method) === 'Online Payment' ? 'Pending' : 'Unpaid';
}

function store_state_channel_type($value)
{
    $value = strtoupper(trim((string) $value));

    if (strpos($value, 'WALK') !== false || strpos($value, 'STORE') !== false || strpos($value, 'PICKUP') !== false) {
        return 'WALK-IN';
    }

    return 'ONLINE';
}

function store_state_normalize_order($order)
{
    $order = is_array($order) ? $order : array();
    $method = store_state_normalize_payment_method($order['payment_method'] ?? '');
    $channel_type = store_state_channel_type($order['transaction_type'] ?? ($order['channel'] ?? 'ONLINE'));

    $order['type'] = (string) ($order['type'] ?? 'Reservation');
    $order['size_standard'] = function_exists('store_normalize_size_standard')
        ? store_normalize_size_standard($order['size_standard'] ?? 'US')
        : (isset($order['size_standard']) ? (string) $order['size_standard'] : 'US');
    $order['payment_method'] = $method;
    $order['payment_status'] = store_state_normalize_payment_status($order['payment_status'] ?? '', $method);
    $order['payment_reference'] = (string) ($order['payment_reference'] ?? '');
    $order['payment_proof'] = (string) ($order['payment_proof'] ?? '');
    $order['payment_proof_name'] = (string) ($order['payment_proof_name'] ?? '');
    $order['payment_note'] = (string) ($order['payment_note'] ?? '');
    $order['transaction_type'] = $channel_type;
    $order['channel'] = (string) ($order['channel'] ?? ($channel_type === 'WALK-IN' ? 'Walk-in reservation' : 'Website reservation'));

    return $order;
}

function store_state_normalize_review($review)
{
    $review = is_array($review) ? $review : array();
    $review['product_id'] = (int) ($review['product_id'] ?? 0);
    $review['status'] = (string) ($review['status'] ?? 'Published');
    $review['rating'] = max(1, min(5, (int) ($review['rating'] ?? 5)));

    return $review;
}

function store_state_default_payload($name)
{
    if ($name === 'orders') {
        return store_state_default_orders();
    }

    if ($name === 'reviews') {
        return store_state_default_reviews();
    }

    if ($name === 'messages') {
        return store_state_default_messages();
    }

    if ($name === 'user_profiles') {
        return store_state_default_user_profiles();
    }

    if ($name === 'analytics') {
        return store_state_default_analytics();
    }

    return array();
}

function store_state_read_json($name)
{
    $path = store_state_file_path($name);
    $fallback = store_state_default_payload($name);

    if (!is_file($path)) {
        store_state_write_json($name, $fallback);
        return $fallback;
    }

    $contents = @file_get_contents($path);

    if ($contents === false || trim($contents) === '') {
        return $fallback;
    }

    $decoded = json_decode($contents, true);

    if (!is_array($decoded)) {
        return $fallback;
    }

    return $decoded;
}

function store_state_write_json($name, $payload)
{
    $directory = store_state_runtime_dir();

    if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
        return false;
    }

    $path = store_state_file_path($name);
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($encoded === false) {
        return false;
    }

    return @file_put_contents($path, $encoded) !== false;
}

function store_state_get_orders()
{
    return array_values(array_map('store_state_normalize_order', store_state_read_json('orders')));
}

function store_state_get_reviews()
{
    return array_values(array_map('store_state_normalize_review', store_state_read_json('reviews')));
}

function store_state_get_messages()
{
    return store_state_read_json('messages');
}

function store_state_get_user_profiles()
{
    $profiles = store_state_read_json('user_profiles');

    return is_array($profiles) ? $profiles : array();
}

function store_state_get_analytics()
{
    $analytics = store_state_read_json('analytics');

    return is_array($analytics) ? $analytics : store_state_default_analytics();
}

function store_state_normalize_category($category)
{
    $category = strtolower(trim((string) $category));
    $map = array(
        'tshirts' => 'tops',
        'shirts' => 'tops',
        'polo' => 'tops',
        'shorts' => 'bottoms',
        'skirts' => 'bottoms',
        'caps' => 'accessories',
        'slides' => 'slippers',
        'sneakers' => 'shoes',
    );

    return $map[$category] ?? $category;
}

function store_state_get_user_profile($email)
{
    $email = strtolower(trim((string) $email));

    if ($email === '') {
        return array();
    }

    $profiles = store_state_get_user_profiles();
    $profile = isset($profiles[$email]) && is_array($profiles[$email]) ? $profiles[$email] : array();

    return array(
        'brands' => isset($profile['brands']) && is_array($profile['brands']) ? array_values($profile['brands']) : array(),
        'categories' => isset($profile['categories']) && is_array($profile['categories'])
            ? array_values(array_map('store_state_normalize_category', $profile['categories']))
            : array(),
        'styles' => isset($profile['styles']) && is_array($profile['styles']) ? array_values($profile['styles']) : array(),
        'shoe_size' => isset($profile['shoe_size']) ? trim((string) $profile['shoe_size']) : '',
        'apparel_size' => isset($profile['apparel_size']) ? trim((string) $profile['apparel_size']) : '',
        'budget_max' => isset($profile['budget_max']) ? trim((string) $profile['budget_max']) : '',
        'updated_at' => isset($profile['updated_at']) ? (string) $profile['updated_at'] : '',
    );
}

function store_state_profile_is_complete($profile)
{
    return !empty($profile['categories'])
        && (!empty($profile['shoe_size']) || !empty($profile['apparel_size']))
        && (!empty($profile['brands']) || !empty($profile['styles']) || !empty($profile['budget_max']));
}

function store_state_save_user_profile($email, $profile)
{
    $email = strtolower(trim((string) $email));

    if ($email === '') {
        return false;
    }

    $profiles = store_state_get_user_profiles();
    $profiles[$email] = array(
        'brands' => isset($profile['brands']) && is_array($profile['brands']) ? array_values(array_unique(array_map('strval', $profile['brands']))) : array(),
        'categories' => isset($profile['categories']) && is_array($profile['categories'])
            ? array_values(array_unique(array_map('store_state_normalize_category', $profile['categories'])))
            : array(),
        'styles' => isset($profile['styles']) && is_array($profile['styles']) ? array_values(array_unique(array_map('strval', $profile['styles']))) : array(),
        'shoe_size' => trim((string) ($profile['shoe_size'] ?? '')),
        'apparel_size' => trim((string) ($profile['apparel_size'] ?? '')),
        'budget_max' => trim((string) ($profile['budget_max'] ?? '')),
        'updated_at' => date('c'),
    );

    return store_state_write_json('user_profiles', $profiles);
}

function store_state_statuses()
{
    return array('Pending', 'Confirmed', 'Ready for Pickup', 'Completed', 'Cancelled');
}

function store_state_is_active_reservation($status)
{
    return in_array($status, array('Pending', 'Confirmed', 'Ready for Pickup'), true);
}

function store_state_inventory_snapshot_date()
{
    static $snapshotDate = null;

    if ($snapshotDate !== null) {
        return $snapshotDate;
    }

    $snapshotDate = '';
    $connection = $GLOBALS['conn'] ?? null;
    if (!$connection instanceof mysqli) {
        return $snapshotDate;
    }

    $result = @mysqli_query(
        $connection,
        "SELECT inventory_as_of
         FROM historical_import_batches
         WHERE import_status = 'completed'
         ORDER BY inventory_as_of DESC, id DESC
         LIMIT 1"
    );
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $candidate = trim((string) ($row['inventory_as_of'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
        $snapshotDate = $candidate;
    }

    return $snapshotDate;
}

function store_state_order_affects_live_inventory($order)
{
    $snapshotDate = store_state_inventory_snapshot_date();
    if ($snapshotDate === '') {
        return true;
    }

    $orderDate = substr((string) ($order['created_at'] ?? ''), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate) === 1 && $orderDate > $snapshotDate;
}

function store_state_get_product_metrics()
{
    $orders = store_state_get_orders();
    $reviews = store_state_get_reviews();
    $metrics = array();

    foreach ($orders as $order) {
        $product_id = (int) ($order['product_id'] ?? 0);

        if ($product_id <= 0) {
            continue;
        }

        if (!isset($metrics[$product_id])) {
            $metrics[$product_id] = array(
                'locked_units' => 0,
                'completed_units' => 0,
                'active_reservations' => 0,
                'review_count' => 0,
                'rating_sum' => 0,
                'latest_reviews' => array(),
            );
        }

        $quantity = max(1, (int) ($order['quantity'] ?? 1));
        $status = (string) ($order['status'] ?? 'Pending');

        if (store_state_order_affects_live_inventory($order) && store_state_is_active_reservation($status)) {
            $metrics[$product_id]['locked_units'] += $quantity;
            $metrics[$product_id]['active_reservations'] += $quantity;
        } elseif (store_state_order_affects_live_inventory($order) && $status === 'Completed') {
            $metrics[$product_id]['completed_units'] += $quantity;
        }
    }

    foreach ($reviews as $review) {
        $product_id = (int) ($review['product_id'] ?? 0);

        if ($product_id <= 0
            || (string) ($review['status'] ?? 'Published') !== 'Published'
            || store_state_review_is_demo($review)) {
            continue;
        }

        if (!isset($metrics[$product_id])) {
            $metrics[$product_id] = array(
                'locked_units' => 0,
                'completed_units' => 0,
                'active_reservations' => 0,
                'review_count' => 0,
                'rating_sum' => 0,
                'latest_reviews' => array(),
            );
        }

        $metrics[$product_id]['review_count'] += 1;
        $metrics[$product_id]['rating_sum'] += max(1, min(5, (int) ($review['rating'] ?? 5)));
        $metrics[$product_id]['latest_reviews'][] = $review;
    }

    foreach ($metrics as &$metric) {
        usort($metric['latest_reviews'], function ($left, $right) {
            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        });

        $metric['latest_reviews'] = array_slice($metric['latest_reviews'], 0, 3);
    }
    unset($metric);

    return $metrics;
}

function store_state_available_stock($product)
{
    $metrics = store_state_get_product_metrics();
    $product_id = (int) ($product['id'] ?? 0);
    $base_stock = isset($product['base_stock']) ? (int) $product['base_stock'] : (int) ($product['stock'] ?? 0);
    $product_metrics = $metrics[$product_id] ?? array(
        'locked_units' => 0,
        'completed_units' => 0,
    );

    return max(0, $base_stock - (int) $product_metrics['locked_units'] - (int) $product_metrics['completed_units']);
}

function store_state_generate_identifier($prefix)
{
    try {
        $random = strtoupper(bin2hex(random_bytes(4)));
    } catch (Exception $exception) {
        $random = strtoupper(substr(md5(uniqid('', true)), 0, 4));
    }

    return $prefix . '-' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('ymd-His') . '-' . $random;
}

function store_state_checkout_reference($order)
{
    return (string) (($order['checkout_id'] ?? '') !== '' ? $order['checkout_id'] : ($order['id'] ?? ''));
}

function store_state_checkout_items($order_id)
{
    $orders = store_state_get_orders();
    $reference = '';
    foreach ($orders as $order) {
        if ((string) ($order['id'] ?? '') === (string) $order_id || store_state_checkout_reference($order) === (string) $order_id) {
            $reference = store_state_checkout_reference($order);
            break;
        }
    }
    return $reference === '' ? array() : array_values(array_filter($orders, function ($order) use ($reference) {
        return store_state_checkout_reference($order) === $reference;
    }));
}

function store_state_build_reservation_order($user_context, $product, $size, $quantity, $options)
{
    $method = store_state_normalize_payment_method($options['payment_method'] ?? 'In-Store Payment');
    $type = store_state_channel_type($options['transaction_type'] ?? 'ONLINE');
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM);
    return array(
        'id' => store_state_generate_identifier('ORD'),
        'checkout_id' => trim((string) ($options['checkout_id'] ?? '')),
        'type' => trim((string) ($options['type'] ?? 'Reservation')) ?: 'Reservation',
        'customer_name' => (string) ($user_context['user_name'] ?? 'Guest Shopper'),
        'customer_email' => (string) ($user_context['user_email'] ?? ''),
        'product_id' => (int) ($product['id'] ?? 0),
        'product_name' => (string) ($product['name'] ?? 'Product'),
        'brand' => (string) ($product['brand'] ?? 'Shoestagram'),
        'size_standard' => function_exists('store_normalize_size_standard') ? store_normalize_size_standard($options['size_standard'] ?? 'US') : (string) ($options['size_standard'] ?? 'US'),
        'size' => (string) $size,
        'quantity' => (int) $quantity,
        'price_each' => (float) ($product['price'] ?? 0),
        'subtotal' => (float) ($product['price'] ?? 0) * (int) $quantity,
        'status' => 'Pending',
        'pickup_window' => 'Pickup within 48 hours after confirmation',
        'channel' => (string) ($options['channel'] ?? ($type === 'WALK-IN' ? 'Walk-in reservation' : 'Website reservation')),
        'transaction_type' => $type,
        'payment_method' => $method,
        'payment_status' => store_state_normalize_payment_status($options['payment_status'] ?? '', $method),
        'payment_reference' => trim((string) ($options['payment_reference'] ?? '')),
        'payment_proof' => trim((string) ($options['payment_proof'] ?? '')),
        'payment_proof_name' => trim((string) ($options['payment_proof_name'] ?? '')),
        'payment_note' => trim((string) ($options['payment_note'] ?? '')),
        'created_at' => $now,
        'updated_at' => $now,
        'review_submitted' => false,
    );
}

function store_state_create_cart_checkout($user_context, $cart_items, $options = array())
{
    if (!$cart_items) {
        return array('ok' => false, 'message' => 'Your cart is empty.');
    }
    $needed = array();
    $available = array();
    foreach ($cart_items as $item) {
        $product = $item['product'] ?? array();
        $id = (int) ($product['id'] ?? 0);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        $standard = function_exists('store_normalize_size_standard') ? store_normalize_size_standard($item['size_standard'] ?? 'US') : (string) ($item['size_standard'] ?? 'US');
        $sizes = $product['size_standards'][$standard] ?? ($product['sizes'] ?? array());
        if ($id <= 0 || $quantity === false || $quantity < 1 || !in_array((string) ($item['size'] ?? ''), array_map('strval', $sizes), true)) {
            return array('ok' => false, 'message' => 'A cart item has an invalid product, size, or quantity.');
        }
        $needed[$id] = ($needed[$id] ?? 0) + $quantity;
        $available[$id] = store_state_available_stock($product);
        if ($needed[$id] > $available[$id]) {
            return array('ok' => false, 'message' => 'Not enough stock for ' . (string) ($product['name'] ?? 'a product') . '.');
        }
    }
    $checkout_id = store_state_generate_identifier('ORD');
    $orders = store_state_get_orders();
    $created = array();
    foreach ($cart_items as $item) {
        $item_options = $options;
        $item_options['checkout_id'] = $checkout_id;
        $item_options['size_standard'] = $item['size_standard'] ?? 'US';
        $order = store_state_build_reservation_order($user_context, $item['product'], $item['size'], $item['quantity'], $item_options);
        $order['id'] = $checkout_id . '-' . (count($created) + 1);
        $orders[] = $order;
        $created[] = $order;
    }
    if (!store_state_write_json('orders', $orders)) {
        return array('ok' => false, 'message' => 'The checkout could not be saved right now. Please try again.');
    }
    return array('ok' => true, 'checkout_id' => $checkout_id, 'orders' => $created, 'message' => 'Reservation received. Your checkout reference is ' . $checkout_id . '.');
}

function store_state_create_reservation($user_context, $product, $size, $quantity, $options = array())
{
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
    $available = store_state_available_stock($product);
    $size_standard = function_exists('store_normalize_size_standard')
        ? store_normalize_size_standard($options['size_standard'] ?? 'US')
        : trim((string) ($options['size_standard'] ?? 'US'));
    $size_standards = isset($product['size_standards']) && is_array($product['size_standards']) ? $product['size_standards'] : array();
    $sizes = isset($size_standards[$size_standard]) && is_array($size_standards[$size_standard])
        ? $size_standards[$size_standard]
        : (isset($product['sizes']) && is_array($product['sizes']) ? $product['sizes'] : array());
    $payment_method = store_state_normalize_payment_method($options['payment_method'] ?? 'In-Store Payment');
    $payment_status = store_state_normalize_payment_status($options['payment_status'] ?? '', $payment_method);
    $transaction_type = store_state_channel_type($options['transaction_type'] ?? 'ONLINE');
    $order_type = trim((string) ($options['type'] ?? 'Reservation'));
    $channel = trim((string) ($options['channel'] ?? ($transaction_type === 'WALK-IN' ? 'Walk-in reservation' : 'Website reservation')));

    if ($quantity === false || (int) $quantity < 1) {
        return array(
            'ok' => false,
            'message' => 'Quantity must be a positive whole number.',
        );
    }

    $quantity = (int) $quantity;

    if ($available <= 0) {
        return array(
            'ok' => false,
            'message' => 'This product is currently out of stock.',
        );
    }

    if ($quantity > $available) {
        return array(
            'ok' => false,
            'message' => 'Only ' . $available . ' unit(s) are currently available for reservation.',
        );
    }

    if (!in_array((string) $size, array_map('strval', $sizes), true)) {
        return array(
            'ok' => false,
            'message' => 'Please choose a valid ' . (function_exists('store_size_standard_label') ? store_size_standard_label($size_standard) : $size_standard) . ' size before reserving this product.',
        );
    }

    $orders = store_state_get_orders();
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM);
    $order = array(
        'id' => store_state_generate_identifier('ORD'),
        'type' => $order_type !== '' ? $order_type : 'Reservation',
        'customer_name' => (string) ($user_context['user_name'] ?? 'Guest Shopper'),
        'customer_email' => (string) ($user_context['user_email'] ?? ''),
        'product_id' => (int) ($product['id'] ?? 0),
        'product_name' => (string) ($product['name'] ?? 'Product'),
        'brand' => (string) ($product['brand'] ?? 'Shoestagram'),
        'size_standard' => $size_standard,
        'size' => (string) $size,
        'quantity' => $quantity,
        'price_each' => (float) ($product['price'] ?? 0),
        'subtotal' => (float) ($product['price'] ?? 0) * $quantity,
        'status' => 'Pending',
        'pickup_window' => 'Pickup within 48 hours after confirmation',
        'channel' => $channel,
        'transaction_type' => $transaction_type,
        'payment_method' => $payment_method,
        'payment_status' => $payment_status,
        'payment_reference' => trim((string) ($options['payment_reference'] ?? '')),
        'payment_proof' => trim((string) ($options['payment_proof'] ?? '')),
        'payment_proof_name' => trim((string) ($options['payment_proof_name'] ?? '')),
        'payment_note' => trim((string) ($options['payment_note'] ?? '')),
        'created_at' => $now,
        'updated_at' => $now,
        'review_submitted' => false,
    );

    $orders[] = $order;

    if (!store_state_write_json('orders', $orders)) {
        return array(
            'ok' => false,
            'message' => 'The reservation could not be saved right now. Please try again.',
        );
    }

    return array(
        'ok' => true,
        'order' => $order,
        'message' => 'Reservation confirmed. Your order ID is ' . $order['id'] . '.',
    );
}

function store_state_update_order_status($order_id, $status)
{
    if (!in_array($status, store_state_statuses(), true)) {
        return false;
    }

    $orders = store_state_get_orders();
    $updated = false;
    $checkout_id = '';
    foreach ($orders as $candidate) {
        if ((string) ($candidate['id'] ?? '') === (string) $order_id) {
            $checkout_id = store_state_checkout_reference($candidate);
            break;
        }
    }

    foreach ($orders as &$order) {
        if ($checkout_id === '' || store_state_checkout_reference($order) !== $checkout_id) {
            continue;
        }

        $order['status'] = $status;
        $order['updated_at'] = date('c');

        if ($status === 'Completed') {
            $order['pickup_window'] = 'Collected';
        } elseif ($status === 'Cancelled') {
            $order['pickup_window'] = 'Reservation cancelled';
        } elseif ($status === 'Ready for Pickup') {
            $order['pickup_window'] = 'Ready for pickup today';
        }

        $updated = true;
    }
    unset($order);

    if (!$updated) {
        return false;
    }

    return store_state_write_json('orders', $orders);
}

function store_state_payment_statuses()
{
    return array('Pending', 'Paid', 'Unpaid', 'Failed/Rejected');
}

function store_state_update_order_payment($order_id, $payment_status, $payment_reference = '', $payment_note = '')
{
    $order_id = trim((string) $order_id);
    $payment_status = trim((string) $payment_status);

    if ($order_id === '' || !in_array($payment_status, store_state_payment_statuses(), true)) {
        return false;
    }

    $orders = store_state_get_orders();
    $updated = false;
    $checkout_id = '';
    foreach ($orders as $candidate) {
        if ((string) ($candidate['id'] ?? '') === $order_id) {
            $checkout_id = store_state_checkout_reference($candidate);
            break;
        }
    }

    foreach ($orders as &$order) {
        if ($checkout_id === '' || store_state_checkout_reference($order) !== $checkout_id) {
            continue;
        }

        $order['payment_status'] = $payment_status;
        $order['payment_reference'] = trim((string) $payment_reference) !== ''
            ? trim((string) $payment_reference)
            : (string) ($order['payment_reference'] ?? '');
        $order['payment_note'] = trim((string) $payment_note);
        $order['updated_at'] = date('c');
        $updated = true;
    }
    unset($order);

    return $updated ? store_state_write_json('orders', $orders) : false;
}

function store_state_update_order_workflow($order_id, $status, $payment_status)
{
    $order_id = trim((string) $order_id);
    $status = trim((string) $status);
    $payment_status = trim((string) $payment_status);

    if ($order_id === ''
        || !in_array($status, store_state_statuses(), true)
        || !in_array($payment_status, store_state_payment_statuses(), true)) {
        return false;
    }

    $orders = store_state_get_orders();
    $updated = false;
    $checkout_id = '';
    foreach ($orders as $candidate) {
        if ((string) ($candidate['id'] ?? '') === $order_id) {
            $checkout_id = store_state_checkout_reference($candidate);
            break;
        }
    }

    foreach ($orders as &$order) {
        if ($checkout_id === '' || store_state_checkout_reference($order) !== $checkout_id) {
            continue;
        }

        $order['status'] = $status;
        $order['payment_status'] = $payment_status;
        $order['updated_at'] = date('c');

        if ($status === 'Completed') {
            $order['pickup_window'] = 'Collected';
        } elseif ($status === 'Cancelled') {
            $order['pickup_window'] = 'Reservation cancelled';
        } elseif ($status === 'Ready for Pickup') {
            $order['pickup_window'] = 'Ready for pickup today';
        }

        $updated = true;
    }
    unset($order);

    return $updated ? store_state_write_json('orders', $orders) : false;
}

function store_state_cancel_customer_order($order_id, $email)
{
    $order_id = trim((string) $order_id);
    $email = strtolower(trim((string) $email));

    if ($order_id === '' || $email === '') {
        return array(
            'ok' => false,
            'message' => 'The reservation could not be cancelled.',
        );
    }

    $orders = store_state_get_orders();

    $checkout_id = '';
    foreach ($orders as $candidate) {
        if ((string) ($candidate['id'] ?? '') === $order_id) {
            $checkout_id = store_state_checkout_reference($candidate);
            break;
        }
    }
    foreach ($orders as &$order) {
        if ($checkout_id === '' || store_state_checkout_reference($order) !== $checkout_id) {
            continue;
        }

        if (strtolower((string) ($order['customer_email'] ?? '')) !== $email) {
            return array(
                'ok' => false,
                'message' => 'That reservation does not belong to the signed-in account.',
            );
        }

        if (!in_array((string) ($order['status'] ?? ''), array('Pending', 'Confirmed'), true)) {
            return array(
                'ok' => false,
                'message' => 'Only pending or confirmed reservations can still be cancelled.',
            );
        }

        foreach ($orders as &$item_order) {
            if (store_state_checkout_reference($item_order) === $checkout_id) {
                $item_order['status'] = 'Cancelled';
                $item_order['pickup_window'] = 'Cancelled by customer';
                $item_order['updated_at'] = date('c');
            }
        }
        unset($item_order);
        return store_state_write_json('orders', $orders)
            ? array('ok' => true, 'order' => $order, 'message' => 'Your reservation was cancelled successfully.')
            : array('ok' => false, 'message' => 'The reservation could not be cancelled right now.');
    }
    unset($order);

    return array(
        'ok' => false,
        'message' => 'We could not find that reservation.',
    );
}

function store_state_get_customer_orders($email)
{
    $email = strtolower(trim((string) $email));

    if ($email === '') {
        return array();
    }

    $orders = array_values(array_filter(store_state_get_orders(), function ($order) use ($email) {
        return strtolower((string) ($order['customer_email'] ?? '')) === $email;
    }));

    usort($orders, function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return $orders;
}

function store_state_customer_product_orders($email, $product_id, $completed_only = true)
{
    $email = strtolower(trim((string) $email));
    $product_id = (int) $product_id;

    if ($email === '' || $product_id <= 0) {
        return array();
    }

    return array_values(array_filter(store_state_get_orders(), function ($order) use ($email, $product_id, $completed_only) {
        if (strtolower((string) ($order['customer_email'] ?? '')) !== $email) {
            return false;
        }

        if ((int) ($order['product_id'] ?? 0) !== $product_id) {
            return false;
        }

        return !$completed_only || (
            (string) ($order['status'] ?? '') === 'Completed'
            && (string) ($order['payment_status'] ?? '') !== 'Failed/Rejected'
        );
    }));
}

function store_state_customer_reviewable_product_orders($email, $product_id)
{
    $reviewed_order_ids = array();
    foreach (store_state_get_reviews() as $review) {
        if (!store_state_review_is_demo($review) && trim((string) ($review['order_id'] ?? '')) !== '') {
            $reviewed_order_ids[(string) $review['order_id']] = true;
        }
    }

    return array_values(array_filter(store_state_customer_product_orders($email, $product_id, true), function ($order) use ($reviewed_order_ids) {
        $order_id = (string) ($order['id'] ?? '');
        return empty($order['review_submitted']) && empty($reviewed_order_ids[$order_id]);
    }));
}

function store_state_submit_review($order_id, $user_context, $rating, $comment, $expected_product_id = 0, $title = '')
{
    $rating = filter_var($rating, FILTER_VALIDATE_INT);
    $comment = trim((string) $comment);
    $title = trim((string) $title);
    $email = strtolower(trim((string) ($user_context['user_email'] ?? '')));
    $name = trim((string) ($user_context['user_name'] ?? 'Shoestagram Member'));
    $expected_product_id = (int) $expected_product_id;

    if ($rating === false || (int) $rating < 1 || (int) $rating > 5) {
        return array(
            'ok' => false,
            'message' => 'Choose a rating from 1 to 5 stars.',
        );
    }

    if ($comment === '') {
        return array(
            'ok' => false,
            'message' => 'Please write a short review before submitting.',
        );
    }

    if (strlen($comment) > 1000) {
        return array(
            'ok' => false,
            'message' => 'Keep your review to 1,000 characters or fewer.',
        );
    }

    if (strlen($title) > 120) {
        return array(
            'ok' => false,
            'message' => 'Keep the review title to 120 characters or fewer.',
        );
    }

    $orders = store_state_get_orders();
    $target_order = null;

    foreach ($orders as $index => $order) {
        if (($order['id'] ?? '') !== $order_id) {
            continue;
        }

        if (strtolower((string) ($order['customer_email'] ?? '')) !== $email) {
            return array(
                'ok' => false,
                'message' => 'This order does not belong to the signed-in account.',
            );
        }

        if (($order['status'] ?? '') !== 'Completed' || ($order['payment_status'] ?? '') === 'Failed/Rejected') {
            return array(
                'ok' => false,
                'message' => 'Reviews unlock only after an order is marked completed.',
            );
        }

        if (!empty($order['review_submitted'])) {
            return array(
                'ok' => false,
                'message' => 'A review has already been submitted for this order.',
            );
        }

        if ($expected_product_id > 0 && (int) ($order['product_id'] ?? 0) !== $expected_product_id) {
            return array(
                'ok' => false,
                'message' => 'That completed order belongs to a different product.',
            );
        }

        $target_order = array('index' => $index, 'order' => $order);
        break;
    }

    if ($target_order === null) {
        return array(
            'ok' => false,
            'message' => 'We could not find that completed order.',
        );
    }

    $reviews = store_state_get_reviews();
    foreach ($reviews as $existing_review) {
        if ((string) ($existing_review['order_id'] ?? '') === (string) $target_order['order']['id']) {
            return array(
                'ok' => false,
                'message' => 'A review has already been submitted for this completed order.',
            );
        }
    }

    $review = array(
        'id' => store_state_generate_identifier('REV'),
        'order_id' => $target_order['order']['id'],
        'product_id' => (int) ($target_order['order']['product_id'] ?? 0),
        'product_name' => (string) ($target_order['order']['product_name'] ?? 'Product'),
        'customer_name' => $name,
        'customer_email' => $email,
        'rating' => $rating,
        'title' => $title,
        'comment' => $comment,
        'source' => 'customer_submission',
        'is_demo' => false,
        'created_at' => date('c'),
        'status' => 'Published',
    );

    $reviews[] = $review;
    $orders[$target_order['index']]['review_submitted'] = true;
    $orders[$target_order['index']]['updated_at'] = date('c');

    if (!store_state_write_json('reviews', $reviews) || !store_state_write_json('orders', $orders)) {
        return array(
            'ok' => false,
            'message' => 'The review could not be saved right now. Please try again.',
        );
    }

    return array(
        'ok' => true,
        'message' => 'Thanks for the review. It is now shaping future recommendations.',
    );
}

function store_state_review_statuses()
{
    return array('Published', 'Hidden', 'Deleted');
}

function store_state_update_review_status($review_id, $status)
{
    $review_id = trim((string) $review_id);
    $status = trim((string) $status);

    if ($review_id === '' || !in_array($status, store_state_review_statuses(), true)) {
        return false;
    }

    $reviews = store_state_get_reviews();
    $updated = false;

    foreach ($reviews as &$review) {
        if (($review['id'] ?? '') !== $review_id) {
            continue;
        }

        $review['status'] = $status;
        $review['moderated_at'] = date('c');
        $updated = true;
        break;
    }
    unset($review);

    return $updated ? store_state_write_json('reviews', $reviews) : false;
}

function store_state_save_contact_message($name, $email, $subject, $message)
{
    $name = trim((string) $name);
    $email = trim((string) $email);
    $subject = trim((string) $subject);
    $message = trim((string) $message);

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        return false;
    }

    $messages = store_state_get_messages();
    $messages[] = array(
        'id' => store_state_generate_identifier('MSG'),
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message,
        'created_at' => date('c'),
    );

    return store_state_write_json('messages', $messages);
}

function store_state_move_user_profile($old_email, $new_email)
{
    $old_email = strtolower(trim((string) $old_email));
    $new_email = strtolower(trim((string) $new_email));

    if ($old_email === '' || $new_email === '' || $old_email === $new_email) {
        return true;
    }

    $profiles = store_state_get_user_profiles();

    if (!isset($profiles[$old_email])) {
        return true;
    }

    $profiles[$new_email] = $profiles[$old_email];
    unset($profiles[$old_email]);

    return store_state_write_json('user_profiles', $profiles);
}

function store_state_record_product_view($product_id)
{
    $product_id = (int) $product_id;

    if ($product_id <= 0) {
        return false;
    }

    $analytics = store_state_get_analytics();

    if (!isset($analytics['product_views']) || !is_array($analytics['product_views'])) {
        $analytics['product_views'] = array();
    }

    if (!isset($analytics['product_views'][$product_id]) || !is_array($analytics['product_views'][$product_id])) {
        $analytics['product_views'][$product_id] = array(
            'count' => 0,
            'last_viewed_at' => '',
        );
    }

    $analytics['product_views'][$product_id]['count'] = (int) ($analytics['product_views'][$product_id]['count'] ?? 0) + 1;
    $analytics['product_views'][$product_id]['last_viewed_at'] = date('c');

    return store_state_write_json('analytics', $analytics);
}

function store_state_get_product_view_count($product_id)
{
    $analytics = store_state_get_analytics();
    $views = $analytics['product_views'] ?? array();
    $product_id = (int) $product_id;

    return isset($views[$product_id]['count']) ? (int) $views[$product_id]['count'] : 0;
}

function store_state_record_recommendation_reset()
{
    $analytics = store_state_get_analytics();
    $analytics['recommendation_resets'] = (int) ($analytics['recommendation_resets'] ?? 0) + 1;
    $analytics['last_reset_at'] = date('c');

    return store_state_write_json('analytics', $analytics);
}
