<?php

/**
 * Dry-run and apply the customer product-gallery POV mapping.
 *
 * Usage:
 *   php scripts/map_product_pov_gallery.php
 *   php scripts/map_product_pov_gallery.php --apply
 *   php scripts/map_product_pov_gallery.php --restore=database/backups/product-gallery-pov-...json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/store-data.php';

function pov_gallery_normalize_base($value)
{
    $value = rawurldecode(trim((string) $value));
    $value = pathinfo($value, PATHINFO_FILENAME);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return preg_replace('/[^a-z0-9]+/i', '', $value);
}

function pov_gallery_strict_base($value)
{
    $value = rawurldecode(trim((string) $value));
    $value = pathinfo($value, PATHINFO_FILENAME);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function pov_gallery_is_absolute_path($path)
{
    return preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/])~', (string) $path) === 1;
}

function pov_gallery_filename_from_path($path)
{
    $urlPath = parse_url(trim((string) $path), PHP_URL_PATH);
    return rawurldecode(basename((string) $urlPath));
}

function pov_gallery_suffix_parts($filename)
{
    $stem = pathinfo((string) $filename, PATHINFO_FILENAME);

    if (preg_match('/^(.*)\(([12])\)$/u', $stem, $matches) === 1) {
        return array('base' => rtrim($matches[1]), 'position' => (int) $matches[2]);
    }

    if (preg_match('/^(.*?)[\s_-]+([12])$/u', $stem, $matches) === 1) {
        return array('base' => rtrim($matches[1], " \t\n\r\0\x0B_-"), 'position' => (int) $matches[2]);
    }

    return null;
}

function pov_gallery_public_path($filename)
{
    return '../products%20meow/otherpov/' . rawurlencode((string) $filename);
}

function pov_gallery_decode($value)
{
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? array_values($decoded) : array();
}

function pov_gallery_build_scan($connection, $povDirectory)
{
    $allowedExtensions = array('jpg', 'jpeg', 'png', 'webp');
    $files = array_values(array_filter(scandir($povDirectory) ?: array(), static function ($filename) use ($povDirectory, $allowedExtensions) {
        if ($filename === '.' || $filename === '..' || !is_file($povDirectory . DIRECTORY_SEPARATOR . $filename)) {
            return false;
        }
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $allowedExtensions, true);
    }));
    natcasesort($files);
    $files = array_values($files);

    $candidates = array('strict' => array(), 'loose' => array());
    foreach ($files as $filename) {
        $parts = pov_gallery_suffix_parts($filename);
        if ($parts === null) {
            continue;
        }

        $strictKey = pov_gallery_strict_base($parts['base']);
        $looseKey = pov_gallery_normalize_base($parts['base']);
        if ($strictKey === '' || $looseKey === '') {
            continue;
        }

        if (!isset($candidates['strict'][$strictKey])) {
            $candidates['strict'][$strictKey] = array(1 => array(), 2 => array());
        }
        if (!isset($candidates['loose'][$looseKey])) {
            $candidates['loose'][$looseKey] = array(1 => array(), 2 => array());
        }
        $candidates['strict'][$strictKey][$parts['position']][] = $filename;
        $candidates['loose'][$looseKey][$parts['position']][] = $filename;
    }

    $rows = database_fetch_all($connection, 'SELECT id, slug, name, image_url, gallery FROM products ORDER BY id ASC');
    $products = array();
    $usedFiles = array();

    foreach ($rows as $row) {
        $rawGallery = pov_gallery_decode($row['gallery'] ?? '[]');
        $resolved = store_resolve_product_media(
            (string) ($row['name'] ?? ''),
            (string) ($row['image_url'] ?? ''),
            $rawGallery,
            max(1, (int) ($row['id'] ?? 0) - 100)
        );
        $main = trim((string) ($resolved['gallery'][0] ?? $resolved['image'] ?? $row['image_url'] ?? ''));
        $mainFilename = pov_gallery_filename_from_path($main);
        $strictKey = pov_gallery_strict_base($mainFilename);
        $looseKey = pov_gallery_normalize_base($mainFilename);
        $strictPov1 = $candidates['strict'][$strictKey][1] ?? array();
        $strictPov2 = $candidates['strict'][$strictKey][2] ?? array();
        $useStrictMatch = count($strictPov1) > 0 || count($strictPov2) > 0;
        $pov1Candidates = $useStrictMatch ? $strictPov1 : ($candidates['loose'][$looseKey][1] ?? array());
        $pov2Candidates = $useStrictMatch ? $strictPov2 : ($candidates['loose'][$looseKey][2] ?? array());

        $status = 'NO MATCH';
        if (count($pov1Candidates) > 1 || count($pov2Candidates) > 1) {
            $status = 'AMBIGUOUS';
        } elseif (count($pov1Candidates) === 1 && count($pov2Candidates) === 1) {
            $status = 'READY';
        } elseif (count($pov1Candidates) === 1 || count($pov2Candidates) === 1) {
            $status = 'PARTIAL';
        }

        $pov1 = count($pov1Candidates) === 1 ? pov_gallery_public_path($pov1Candidates[0]) : '';
        $pov2 = count($pov2Candidates) === 1 ? pov_gallery_public_path($pov2Candidates[0]) : '';
        if ($status === 'READY') {
            $usedFiles[$pov1Candidates[0]] = true;
            $usedFiles[$pov2Candidates[0]] = true;
        }

        $desiredGallery = $status === 'READY' ? array($main, $pov1, $pov2) : $rawGallery;
        $products[] = array(
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'image_url' => (string) ($row['image_url'] ?? ''),
            'existing_gallery' => $rawGallery,
            'main' => $main,
            'main_filename' => $mainFilename,
            'pov1' => $pov1,
            'pov2' => $pov2,
            'pov1_candidates' => array_values($pov1Candidates),
            'pov2_candidates' => array_values($pov2Candidates),
            'match_method' => $useStrictMatch ? 'exact-basename' : 'normalized-basename',
            'desired_gallery' => $desiredGallery,
            'already_current' => $status === 'READY' && $rawGallery === $desiredGallery,
            'status' => $status,
        );
    }

    $unmatchedFiles = array_values(array_filter($files, static function ($filename) use ($usedFiles) {
        return !isset($usedFiles[$filename]);
    }));

    return array(
        'generated_at' => date(DATE_ATOM),
        'products' => $products,
        'otherpov_files' => $files,
        'unmatched_files' => $unmatchedFiles,
    );
}

function pov_gallery_counts($scan)
{
    $counts = array(
        'checked' => count($scan['products']),
        'otherpov_files' => count($scan['otherpov_files']),
        'ready' => 0,
        'partial' => 0,
        'no_match' => 0,
        'ambiguous' => 0,
        'already_current' => 0,
        'unmatched_files' => count($scan['unmatched_files']),
    );

    foreach ($scan['products'] as $product) {
        if ($product['status'] === 'READY') {
            $counts['ready']++;
        } elseif ($product['status'] === 'PARTIAL') {
            $counts['partial']++;
        } elseif ($product['status'] === 'AMBIGUOUS') {
            $counts['ambiguous']++;
        } else {
            $counts['no_match']++;
        }

        if (!empty($product['already_current'])) {
            $counts['already_current']++;
        }
    }

    return $counts;
}

function pov_gallery_write_json($path, $data)
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create backup directory.');
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write JSON file: ' . $path);
    }
}

function pov_gallery_apply($connection, $scan, $backupDirectory)
{
    $ready = array_values(array_filter($scan['products'], static function ($product) {
        return $product['status'] === 'READY';
    }));
    $timestamp = date('Ymd-His');
    $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . 'product-gallery-pov-' . $timestamp . '.json';
    $backup = array(
        'created_at' => date(DATE_ATOM),
        'purpose' => 'Backup before mapping real POV 1 and POV 2 product gallery images.',
        'fields' => array('image_url', 'gallery'),
        'products' => array_map(static function ($product) {
            return array(
                'id' => $product['id'],
                'name' => $product['name'],
                'image_url' => $product['image_url'],
                'gallery' => $product['existing_gallery'],
            );
        }, $ready),
    );
    pov_gallery_write_json($backupPath, $backup);

    mysqli_begin_transaction($connection);
    $changed = 0;
    try {
        foreach ($ready as $product) {
            if (!empty($product['already_current'])) {
                continue;
            }

            $ok = database_execute(
                $connection,
                'UPDATE products SET gallery = ? WHERE id = ?',
                'si',
                array(database_encode_json($product['desired_gallery']), (int) $product['id'])
            );
            if (!$ok) {
                throw new RuntimeException('Database update failed for product ID ' . $product['id'] . '.');
            }
            $changed++;
        }
        mysqli_commit($connection);
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        throw $exception;
    }

    return array('backup_path' => $backupPath, 'changed' => $changed, 'ready' => count($ready));
}

function pov_gallery_restore($connection, $backupPath)
{
    $contents = file_get_contents($backupPath);
    $backup = $contents !== false ? json_decode($contents, true) : null;
    if (!is_array($backup) || !isset($backup['products']) || !is_array($backup['products'])) {
        throw new RuntimeException('The selected gallery backup is invalid.');
    }

    mysqli_begin_transaction($connection);
    $restored = 0;
    try {
        foreach ($backup['products'] as $product) {
            $id = (int) ($product['id'] ?? 0);
            if ($id <= 0 || !array_key_exists('image_url', $product) || !isset($product['gallery']) || !is_array($product['gallery'])) {
                throw new RuntimeException('The backup contains an invalid product row.');
            }
            $ok = database_execute(
                $connection,
                'UPDATE products SET image_url = ?, gallery = ? WHERE id = ?',
                'ssi',
                array((string) $product['image_url'], database_encode_json($product['gallery']), $id)
            );
            if (!$ok) {
                throw new RuntimeException('Restore failed for product ID ' . $id . '.');
            }
            $restored++;
        }
        mysqli_commit($connection);
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        throw $exception;
    }

    return $restored;
}

function pov_gallery_print_report($scan)
{
    $counts = pov_gallery_counts($scan);
    echo "Product ID\tProduct Name\tExisting Main Image\tMatched POV 1\tMatched POV 2\tStatus", PHP_EOL;
    foreach ($scan['products'] as $product) {
        echo implode("\t", array(
            $product['id'],
            $product['name'],
            $product['main'],
            $product['pov1'] !== '' ? $product['pov1'] : '-',
            $product['pov2'] !== '' ? $product['pov2'] : '-',
            $product['status'],
        )), PHP_EOL;
    }
    echo PHP_EOL;
    foreach ($counts as $label => $count) {
        echo $label, ': ', $count, PHP_EOL;
    }
}

$root = dirname(__DIR__);
$povDirectory = $root . DIRECTORY_SEPARATOR . 'products meow' . DIRECTORY_SEPARATOR . 'otherpov';
$backupDirectory = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'backups';
$options = getopt('', array('apply', 'restore:', 'report:'));

try {
    if (isset($options['restore'])) {
        $restorePath = (string) $options['restore'];
        if (!pov_gallery_is_absolute_path($restorePath)) {
            $restorePath = $root . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $restorePath);
        }
        $restored = pov_gallery_restore($conn, $restorePath);
        echo 'Restored products: ', $restored, PHP_EOL;
        exit(0);
    }

    if (!is_dir($povDirectory)) {
        throw new RuntimeException('POV directory was not found: ' . $povDirectory);
    }

    $scan = pov_gallery_build_scan($conn, $povDirectory);
    pov_gallery_print_report($scan);

    if (isset($options['report'])) {
        $reportPath = (string) $options['report'];
        if (!pov_gallery_is_absolute_path($reportPath)) {
            $reportPath = $root . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $reportPath);
        }
        pov_gallery_write_json($reportPath, $scan);
        echo 'Report: ', $reportPath, PHP_EOL;
    }

    if (isset($options['apply'])) {
        $result = pov_gallery_apply($conn, $scan, $backupDirectory);
        echo 'Backup: ', $result['backup_path'], PHP_EOL;
        echo 'READY products: ', $result['ready'], PHP_EOL;
        echo 'Gallery records changed: ', $result['changed'], PHP_EOL;
    } else {
        echo 'Mode: DRY RUN (no database changes)', PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Gallery mapping failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
