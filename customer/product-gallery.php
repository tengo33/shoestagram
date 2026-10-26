<?php

/* Additional photographed views are files, not product or inventory records. */
function storefront_product_pov_directory()
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Products meow' . DIRECTORY_SEPARATOR . 'otherpov';
}

function storefront_product_pov_groups()
{
    static $groups = null;
    if ($groups !== null) {
        return $groups;
    }

    $groups = array();
    $directory = storefront_product_pov_directory();
    if (!is_dir($directory)) {
        return $groups;
    }

    foreach (scandir($directory) ?: array() as $filename) {
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path) || !preg_match('/\.(jpe?g|png|webp|avif)\z/i', $filename)) {
            continue;
        }
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/\([0-9]+\)\z/', '', $stem);
        if ($base === '' || $base === $stem && preg_match('/^IMG_[0-9]+$/i', $base)) {
            continue;
        }
        $order = $base === $stem ? 0 : (int) substr($stem, strlen($base) + 1, -1);
        $groups[$base][$order] = $filename;
    }

    foreach ($groups as &$files) {
        ksort($files, SORT_NUMERIC);
    }
    unset($files);
    return $groups;
}

function storefront_product_pov_unique_group($stem, $groups)
{
    if ($stem !== '' && isset($groups[$stem])) {
        return $stem;
    }
    $key = store_catalog_media_lookup_key_full($stem);
    if ($key === '') {
        return '';
    }
    $matches = array();
    foreach ($groups as $group_name => $files) {
        if (store_catalog_media_lookup_key_full($group_name) === $key) {
            $matches[] = $group_name;
        }
    }
    return count($matches) === 1 ? $matches[0] : '';
}

function storefront_product_pov_group_for_product($product, $products = array())
{
    $groups = storefront_product_pov_groups();
    $name = trim((string) ($product['name'] ?? ''));
    if ($name === '' || !$groups) {
        return '';
    }

    // These two catalog items reuse another item's main image. Match the
    // clearly named product only; never assign its photos to both records.
    $named_groups = array(
        'adiletteslippers' => 'Adilletteslippers',
        'niketnairmaxplus' => 'Nike Airplus Red',
    );
    $name_key = store_catalog_media_lookup_key_full($name);
    // The existing main media for these labels shows a different colorway.
    // Keep that main media intact and avoid reinforcing the mismatch with POVs.
    if (in_array($name_key, array('crocsblueclogs', 'pumasuedeblack'), true)) {
        return '';
    }
    if (isset($named_groups[$name_key]) && isset($groups[$named_groups[$name_key]])) {
        return $named_groups[$name_key];
    }
    $by_name = storefront_product_pov_unique_group($name, $groups);
    if ($by_name !== '') {
        return $by_name;
    }

    $image = trim((string) ($product['image'] ?? ''));
    $image_path = parse_url($image, PHP_URL_PATH);
    $image_stem = pathinfo(rawurldecode(basename((string) $image_path)), PATHINFO_FILENAME);
    if ($image_stem === '') {
        return '';
    }

    $image_key = store_catalog_media_lookup_key_full($image_stem);
    foreach ($products as $other) {
        if ((int) ($other['id'] ?? 0) === (int) ($product['id'] ?? 0)) {
            continue;
        }
        $other_path = parse_url((string) ($other['image'] ?? ''), PHP_URL_PATH);
        $other_stem = pathinfo(rawurldecode(basename((string) $other_path)), PATHINFO_FILENAME);
        if ($image_key !== '' && store_catalog_media_lookup_key_full($other_stem) === $image_key) {
            return '';
        }
    }
    return storefront_product_pov_unique_group($image_stem, $groups);
}

function storefront_product_gallery_with_pov($product, $products = array())
{
    $gallery = array();
    foreach ((array) ($product['gallery'] ?? array()) as $image) {
        $image = trim((string) $image);
        if ($image !== '' && !in_array($image, $gallery, true)) {
            $gallery[] = $image;
        }
    }
    if (!$gallery && !empty($product['image'])) {
        $gallery[] = (string) $product['image'];
    }

    $group = storefront_product_pov_group_for_product($product, $products);
    $groups = storefront_product_pov_groups();
    if ($group !== '' && isset($groups[$group])) {
        foreach ($groups[$group] as $filename) {
            $path = '../Products%20meow/otherpov/' . rawurlencode($filename);
            if (!in_array($path, $gallery, true)) {
                $gallery[] = $path;
            }
        }
    }
    return $gallery;
}
