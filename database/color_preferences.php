<?php

function store_product_shoe_colors($product)
{
    if (!in_array((string) ($product['category'] ?? ''), array('shoes', 'slippers', 'sandals'), true)) {
        return array();
    }

    // These names are candidates only; options are collected from live catalog products.
    $known = array('Black', 'White', 'Gray', 'Blue', 'Red', 'Brown', 'Green', 'Yellow', 'Pink', 'Beige', 'Cream', 'Teal', 'Mint', 'Orange', 'Purple', 'Tan', 'Gold', 'Silver', 'Khaki', 'Mustard', 'Navy');
    $colorway = trim((string) ($product['colorway'] ?? ''));
    $found = array();

    $source = $colorway !== '' && strcasecmp($colorway, 'Store Colorway') !== 0
        ? $colorway : (string) ($product['name'] ?? '');
    foreach ($known as $color) {
        $spelling = $color === 'Gray' ? '(?:gray|grey)' : preg_quote($color, '/');
        if (preg_match('/(?<![a-z])' . $spelling . '(?![a-z])/i', $source)) {
            $found[$color] = $color;
        }
    }

    // Keep an administrator-entered colorway even when it is outside the common list.
    if ($colorway !== '' && strcasecmp($colorway, 'Store Colorway') !== 0) {
        foreach (preg_split('~\s*(?:/|,|\+|&|\band\b)\s*~i', $colorway) as $part) {
            $part = trim($part);
            if ($part === '' || strlen($part) > 40) {
                continue;
            }
            $has_known_color = false;
            foreach ($known as $color) {
                $spelling = $color === 'Gray' ? '(?:gray|grey)' : preg_quote($color, '/');
                if (preg_match('/(?<![a-z])' . $spelling . '(?![a-z])/i', $part)) {
                    $has_known_color = true;
                    break;
                }
            }
            if (!$has_known_color) {
                $found[ucwords(strtolower($part))] = ucwords(strtolower($part));
            }
        }
    }

    return array_values($found);
}

function get_store_shoe_color_options()
{
    $colors = array();
    foreach (get_store_products() as $product) {
        foreach (store_product_shoe_colors($product) as $color) {
            $colors[$color] = $color;
        }
    }
    natcasesort($colors);
    return array_values($colors);
}

function store_validate_preferred_colors($input)
{
    $allowed = get_store_shoe_color_options();
    return is_array($input) ? array_values(array_unique(array_intersect($allowed, array_filter($input, 'is_string')))) : array();
}

function store_product_matches_preferred_colors($product, $colors)
{
    return is_array($colors) && count(array_intersect(store_product_shoe_colors($product), $colors)) > 0;
}

function store_color_preference_bonus($product, $colors)
{
    return store_product_matches_preferred_colors($product, $colors) ? 18 : 0;
}
