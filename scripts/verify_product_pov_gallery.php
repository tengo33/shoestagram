<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database/config.php';

function gallery_verify_read_json($path)
{
    $contents = @file_get_contents($path);
    $decoded = $contents !== false ? json_decode($contents, true) : null;
    if (!is_array($decoded)) {
        throw new RuntimeException('Could not read JSON: ' . $path);
    }
    return $decoded;
}

function gallery_verify_decode($value)
{
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? array_values($decoded) : array();
}

function gallery_verify_absolute_image_path($root, $webPath)
{
    $path = rawurldecode((string) parse_url((string) $webPath, PHP_URL_PATH));
    $path = str_replace('\\', '/', $path);
    while (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
}

function gallery_verify_fail(&$errors, $message)
{
    $errors[] = (string) $message;
}

$root = dirname(__DIR__);
$options = getopt('', array('backup:', 'dry-run:', 'post-scan:'));
$backupPath = (string) ($options['backup'] ?? '');
$dryRunPath = (string) ($options['dry-run'] ?? ($root . '/database/backups/product-gallery-pov-dry-run.json'));
$postScanPath = (string) ($options['post-scan'] ?? ($root . '/database/backups/product-gallery-pov-post-apply.json'));

try {
    if ($backupPath === '') {
        $matches = glob($root . '/database/backups/product-gallery-pov-????????-??????.json') ?: array();
        rsort($matches, SORT_STRING);
        $backupPath = (string) ($matches[0] ?? '');
    }
    if ($backupPath === '') {
        throw new RuntimeException('No product-gallery backup was found.');
    }

    $backup = gallery_verify_read_json($backupPath);
    $dryRun = gallery_verify_read_json($dryRunPath);
    $postScan = gallery_verify_read_json($postScanPath);
    $rows = database_fetch_all($conn, 'SELECT id, name, image_url, gallery FROM products ORDER BY id ASC');
    $rowsById = array();
    foreach ($rows as $row) {
        $rowsById[(int) $row['id']] = $row;
    }

    $postById = array();
    foreach ($postScan['products'] ?? array() as $product) {
        $postById[(int) ($product['id'] ?? 0)] = $product;
    }

    $errors = array();
    $checked = 0;
    $changed = 0;
    $imageFiles = array();
    $imageUrlsPreserved = 0;
    foreach ($backup['products'] ?? array() as $before) {
        $id = (int) ($before['id'] ?? 0);
        $current = $rowsById[$id] ?? null;
        $expected = $postById[$id] ?? null;
        if (!$current || !$expected) {
            gallery_verify_fail($errors, 'Missing current or expected record for product ID ' . $id . '.');
            continue;
        }
        $checked++;
        if ((string) $current['image_url'] !== (string) ($before['image_url'] ?? '')) {
            gallery_verify_fail($errors, 'image_url changed for product ID ' . $id . '.');
        } else {
            $imageUrlsPreserved++;
        }

        $currentGallery = gallery_verify_decode($current['gallery'] ?? '[]');
        $beforeGallery = is_array($before['gallery'] ?? null) ? array_values($before['gallery']) : array();
        $expectedGallery = is_array($expected['desired_gallery'] ?? null) ? array_values($expected['desired_gallery']) : array();
        if ($currentGallery !== $beforeGallery) {
            $changed++;
        }
        if ($currentGallery !== $expectedGallery) {
            gallery_verify_fail($errors, 'Gallery does not match the dry-run mapping for product ID ' . $id . '.');
        }
        if (count($currentGallery) !== 3 || count(array_unique($currentGallery)) !== 3) {
            gallery_verify_fail($errors, 'Gallery is not exactly three distinct images for product ID ' . $id . '.');
        }
        if (($currentGallery[0] ?? '') !== (string) ($expected['main'] ?? '')) {
            gallery_verify_fail($errors, 'Thumbnail 1 is not the preserved main image for product ID ' . $id . '.');
        }
        if (($currentGallery[1] ?? '') !== (string) ($expected['pov1'] ?? '')) {
            gallery_verify_fail($errors, 'Thumbnail 2 is not POV 1 for product ID ' . $id . '.');
        }
        if (($currentGallery[2] ?? '') !== (string) ($expected['pov2'] ?? '')) {
            gallery_verify_fail($errors, 'Thumbnail 3 is not POV 2 for product ID ' . $id . '.');
        }

        foreach ($currentGallery as $image) {
            $absolute = gallery_verify_absolute_image_path($root, $image);
            $imageFiles[$image] = $absolute;
            if (!is_file($absolute) || @getimagesize($absolute) === false) {
                gallery_verify_fail($errors, 'Missing or invalid image for product ID ' . $id . ': ' . $image);
            }
        }
    }

    $skippedPreserved = 0;
    $allProductImageUrlsPreserved = 0;
    foreach ($dryRun['products'] ?? array() as $product) {
        $id = (int) ($product['id'] ?? 0);
        $current = $rowsById[$id] ?? null;
        if (!$current) {
            gallery_verify_fail($errors, 'Product is missing: ' . $id . '.');
            continue;
        }
        if ((string) $current['image_url'] !== (string) ($product['image_url'] ?? '')) {
            gallery_verify_fail($errors, 'Dry-run image_url changed for product ID ' . $id . '.');
        } else {
            $allProductImageUrlsPreserved++;
        }
        if (($product['status'] ?? '') === 'READY') {
            continue;
        }
        if (gallery_verify_decode($current['gallery'] ?? '[]') !== array_values($product['existing_gallery'] ?? array())) {
            gallery_verify_fail($errors, 'Skipped gallery changed for product ID ' . $id . '.');
        } else {
            $skippedPreserved++;
        }
    }

    $report = array(
        'pass' => empty($errors),
        'backup' => $backupPath,
        'products_checked' => $checked,
        'gallery_records_changed' => $changed,
        'image_url_values_preserved' => $imageUrlsPreserved,
        'all_product_image_url_values_preserved' => $allProductImageUrlsPreserved,
        'skipped_products_preserved' => $skippedPreserved,
        'unique_image_paths_checked' => count($imageFiles),
        'all_images_valid' => empty(array_filter($errors, static function ($error) {
            return strpos($error, 'Missing or invalid image') === 0;
        })),
        'errors' => $errors,
    );
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(empty($errors) ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Gallery verification failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
