<?php

function shoestagram_catalog_seed_item($name, $brand, $category, $selling_price, $cost_min, $cost_max = null, $options = array())
{
    return array_merge(array(
        'name' => $name,
        'brand' => $brand,
        'category' => $category,
        'selling_price' => (float) $selling_price,
        'cost_min' => (float) $cost_min,
        'cost_max' => (float) ($cost_max !== null ? $cost_max : $cost_min),
    ), $options);
}

function shoestagram_catalog_slug($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');

    return $value !== '' ? $value : 'product';
}

function shoestagram_catalog_placeholder($label, $background, $foreground)
{
    return 'https://placehold.co/900x1100/' . rawurlencode($background) . '/' . rawurlencode($foreground) . '?text=' . rawurlencode($label);
}

function shoestagram_catalog_default_sizes($category, $name)
{
    $category = strtolower((string) $category);
    $name = strtoupper((string) $name);

    if ($category === 'tops') {
        return array('S', 'M', 'L', 'XL');
    }

    if ($category === 'bottoms') {
        return array('S', 'M', 'L', 'XL');
    }

    if ($category === 'socks') {
        return array('One Size');
    }

    if ($category === 'accessories') {
        return array('One Size');
    }

    if (strpos($name, 'KIDS') !== false) {
        return array('1', '2', '3', '4', '5');
    }

    return array('6', '7', '8', '9', '10', '11');
}

function shoestagram_catalog_default_style($category, $name)
{
    $name = strtoupper((string) $name);
    $category = strtolower((string) $category);

    if ($category === 'tops') {
        if (strpos($name, 'JERSEY') !== false) {
            return 'Basketball jersey';
        }

        if (strpos($name, 'LONG SLEEVE') !== false) {
            return 'Long sleeve casual';
        }

        if (strpos($name, 'SPORTS SHIRT') !== false) {
            return 'Sports top';
        }

        if (strpos($name, 'POLO') !== false) {
            return 'Casual polo';
        }

        return 'Streetwear top';
    }

    if ($category === 'bottoms') {
        if (strpos($name, 'SKIRT') !== false) {
            return 'Casual skirt';
        }

        return 'Streetwear bottom';
    }

    if ($category === 'socks') {
        return 'Daily essentials';
    }

    if ($category === 'slippers') {
        return 'Slide comfort';
    }

    if ($category === 'sandals') {
        return 'Open comfort';
    }

    if ($category === 'accessories') {
        return 'Store essential';
    }

    if (strpos($name, 'RUNNING') !== false || strpos($name, 'RESPONSE') !== false || strpos($name, 'CLOUDMONSTER') !== false) {
        return 'Performance runner';
    }

    if (strpos($name, 'LOAFERS') !== false || strpos($name, 'FORMAL') !== false || strpos($name, 'FLAT SHOES') !== false) {
        return 'Smart casual';
    }

    if (strpos($name, 'SKATE') !== false || strpos($name, 'SB') !== false || strpos($name, 'OLD SKOOL') !== false) {
        return 'Skate classic';
    }

    if (strpos($name, 'AIR FORCE') !== false || strpos($name, 'CORTEZ') !== false) {
        return 'Street icon';
    }

    return 'Lifestyle sneaker';
}

function shoestagram_catalog_default_tags($category, $brand, $style, $name)
{
    $tags = array(
        strtolower((string) $category),
        strtolower((string) $brand),
        strtolower((string) $style),
    );

    $name = strtoupper((string) $name);

    foreach (array('RUNNING', 'SNEAKERS', 'SLIDES', 'SLIPPERS', 'SANDALS', 'JERSEY', 'SHORTS', 'SOCKS', 'POLO') as $keyword) {
        if (strpos($name, $keyword) !== false) {
            $tags[] = strtolower($keyword);
        }
    }

    return array_values(array_unique(array_filter($tags)));
}

function shoestagram_catalog_default_stock($category, $price, $position)
{
    $category = strtolower((string) $category);

    if ($category === 'socks') {
        return 40 - ($position % 6);
    }

    if ($category === 'tops' || $category === 'bottoms') {
        return 18 + ($position % 10);
    }

    if ($category === 'slippers' || $category === 'sandals') {
        return 12 + ($position % 9);
    }

    if ($price >= 9000) {
        return 4 + ($position % 4);
    }

    if ($price >= 5000) {
        return 6 + ($position % 5);
    }

    return 9 + ($position % 8);
}

function shoestagram_catalog_palette($position)
{
    $palettes = array(
        array('E7DED2', '1C1916'),
        array('D7E1E0', '172022'),
        array('E7E4D8', '1F1D18'),
        array('DCCFD1', '21181B'),
        array('D7D6E3', '171824'),
        array('E0D7CD', '221B16'),
    );

    return $palettes[$position % count($palettes)];
}

function shoestagram_catalog_old_price($price, $featured, $trending)
{
    if (!$featured && !$trending) {
        return null;
    }

    $uplift = $price < 1000 ? 100 : ($price < 5000 ? 250 : 500);
    return $price + $uplift;
}

function shoestagram_catalog_round_price($value)
{
    return round((float) $value / 50) * 50;
}

function shoestagram_catalog_badge($price, $profit, $stock, $featured, $trending)
{
    if ($stock <= 6) {
        return 'Low Stock';
    }

    if ($price >= 9000) {
        return 'Premium';
    }

    if ($profit >= 700) {
        return 'High Margin';
    }

    if ($featured) {
        return 'Featured';
    }

    if ($trending) {
        return 'Trending';
    }

    return 'Core Pick';
}

function shoestagram_catalog_category_copy($category)
{
    $copy = array(
        'shoes' => 'Built for shoppers who want reliable pairs with clear value and current demand.',
        'slippers' => 'Comfort-driven options that move well for daily wear and quick store conversions.',
        'sandals' => 'Warm-weather pairs with strong walk-in appeal and practical margin room.',
        'tops' => 'Fast-moving apparel pieces that support outfit-building and easy add-on sales.',
        'bottoms' => 'Everyday bottoms with broad size coverage and strong matching potential.',
        'socks' => 'Entry-price add-ons that raise basket value without much resistance.',
        'accessories' => 'Small essentials that support bundling and repeat visits.',
    );

    return $copy[$category] ?? 'A catalog item designed to support premium but practical retail decisions.';
}

function shoestagram_catalog_colorway($name)
{
    $name = str_replace(array('(', ')'), '', (string) $name);
    $parts = array_map('trim', preg_split('/[-,]/', $name));
    $last = end($parts);

    if ($last && strlen((string) $last) <= 30 && strtoupper((string) $last) !== strtoupper((string) $name)) {
        return ucwords(strtolower((string) $last));
    }

    return 'Store Colorway';
}

function shoestagram_catalog_tone($name, $brand)
{
    $name = strtoupper((string) $name);

    foreach (array('BLACK', 'WHITE', 'GREEN', 'BLUE', 'YELLOW', 'PINK', 'BROWN', 'GRAY', 'GREY', 'TEAL', 'MINT', 'RED', 'CREAM', 'BEIGE', 'OFF-WHITE', 'KHAKI', 'MUSTARD') as $tone) {
        if (strpos($name, $tone) !== false) {
            return ucwords(strtolower(str_replace('-', ' ', $tone)));
        }
    }

    return $brand . ' Signature';
}

function shoestagram_catalog_seed_products()
{
    static $products = null;

    if ($products !== null) {
        return $products;
    }

    $raw_products = array(
        shoestagram_catalog_seed_item('CHICAGO BULLS SHORTS', 'Chicago Bulls', 'bottoms', 500, 350),
        shoestagram_catalog_seed_item('CHICAGO BULLS JERSEY', 'Chicago Bulls', 'tops', 850, 600),
        shoestagram_catalog_seed_item('KHAKI SHORTS', 'Shoestagram Label', 'bottoms', 450, 300),
        shoestagram_catalog_seed_item('ADIDAS LONG SLEEVE SHIRT', 'Adidas', 'tops', 750, 500),
        shoestagram_catalog_seed_item('DENIM SKIRT', 'Shoestagram Label', 'bottoms', 400, 250),
        shoestagram_catalog_seed_item('ADILETTE SLIPPERS', 'Adidas', 'slippers', 350, 200),
        shoestagram_catalog_seed_item('ADIDAS BLACK SPORTS SHIRT', 'Adidas', 'tops', 500, 300),
        shoestagram_catalog_seed_item('NIKE BLACK/WHITE SNEAKERS', 'Nike', 'shoes', 1700, 1200),
        shoestagram_catalog_seed_item('NIKE GREEN RUNNING SHOES', 'Nike', 'shoes', 2100, 1500),
        shoestagram_catalog_seed_item('NIKE WHITE RUNNING SHOES', 'Nike', 'shoes', 1900, 1300),
        shoestagram_catalog_seed_item('NIKE SB SNEAKERS', 'Nike', 'shoes', 1500, 1000),
        shoestagram_catalog_seed_item('ONITSUKA TIGER BLACK AND WHITE SNEAKERS', 'Onitsuka Tiger', 'shoes', 2000, 1400),
        shoestagram_catalog_seed_item('ONITSUKA TIGER YELLOW SNEAKERS', 'Onitsuka Tiger', 'shoes', 2000, 1400),
        shoestagram_catalog_seed_item('ONITSUKA TIGER WHITE/BLUE SNEAKERS', 'Onitsuka Tiger', 'shoes', 2100, 1500),
        shoestagram_catalog_seed_item('ADIDAS WHITE SNEAKERS', 'Adidas', 'shoes', 1900, 1300),
        shoestagram_catalog_seed_item('NIKE CORTEZ WHITE/RED', 'Nike', 'shoes', 2300, 1600),
        shoestagram_catalog_seed_item('LEATHER LOAFERS', 'Shoestagram Label', 'shoes', 1100, 700),
        shoestagram_catalog_seed_item('MUSTARD NIKE SOCKS', 'Nike', 'socks', 200, 120),
        shoestagram_catalog_seed_item('NIKE BLACK SOCKS', 'Nike', 'socks', 250, 150),
        shoestagram_catalog_seed_item('NIKE WHITE SOCKS', 'Nike', 'socks', 250, 150),
        shoestagram_catalog_seed_item('OLO POLO SHIRT', 'Olo', 'tops', 400, 350),
        shoestagram_catalog_seed_item('HAVAIANAS SLIPPERS', 'Havaianas', 'slippers', 500, 350),
        shoestagram_catalog_seed_item('CHANEL BLACK SANDALS', 'Chanel', 'sandals', 1100, 700),
        shoestagram_catalog_seed_item('HELLO KITTY PINK SLIDES', 'Hello Kitty', 'slippers', 400, 250),
        shoestagram_catalog_seed_item('ADIDAS WHITE/BLACK SLIPPERS', 'Adidas', 'slippers', 450, 300),
        shoestagram_catalog_seed_item('CROCS BLUE CLOGS', 'Crocs', 'slippers', 900, 600),
        shoestagram_catalog_seed_item('CROCS BEIGE SLIDES', 'Crocs', 'slippers', 700, 450),
        shoestagram_catalog_seed_item('CROCS CREAM CLOGS', 'Crocs', 'slippers', 850, 600),
        shoestagram_catalog_seed_item('CROCS OFF-WHITE CLOGS', 'Crocs', 'slippers', 950, 650),
        shoestagram_catalog_seed_item('JORDAN GRAY SLIDES', 'Jordan', 'slippers', 500, 300),
        shoestagram_catalog_seed_item('ADIDAS BLUE SLIDES', 'Adidas', 'slippers', 550, 350),
        shoestagram_catalog_seed_item('HERMES WHITE SANDALS', 'Hermes', 'sandals', 1200, 800),
        shoestagram_catalog_seed_item('NIKE SLIDE SLIPPERS (BLUE CAMO)', 'Nike', 'slippers', 500, 300),
        shoestagram_catalog_seed_item('JORDAN SLIDE SLIPPERS (GREEN)', 'Jordan', 'slippers', 550, 350),
        shoestagram_catalog_seed_item('NIKE BENASSI SLIDE SLIPPERS (WHITE/BLACK)', 'Nike', 'slippers', 650, 400),
        shoestagram_catalog_seed_item('LEATHER FLAT SHOES', 'Shoestagram Label', 'shoes', 1100, 700),
        shoestagram_catalog_seed_item('NIKE LOW CUT SNEAKERS (WHITE/BROWN)', 'Nike', 'shoes', 2300, 1600),
        shoestagram_catalog_seed_item('NIKE AIR MAX STYLE SNEAKERS (TEAL/WHITE)', 'Nike', 'shoes', 2600, 1800),
        shoestagram_catalog_seed_item('BLACK LEATHER FORMAL SHOES', 'Shoestagram Label', 'shoes', 1800, 800, 1500),
        shoestagram_catalog_seed_item('NIKE TN / AIR MAX PLUS', 'Nike', 'shoes', 11000, 7000, 10000),
        shoestagram_catalog_seed_item('BAPE SHOES', 'Bape', 'shoes', 1800, 1500),
        shoestagram_catalog_seed_item('NIKE AIR FORCE 1', 'Nike', 'shoes', 7500, 5900, 7000),
        shoestagram_catalog_seed_item('NEW BALANCE 530', 'New Balance', 'shoes', 6500, 4700, 5600),
        shoestagram_catalog_seed_item('WHITE NEW BALANCE CASUAL SHOES', 'New Balance', 'shoes', 6000, 3500, 5000),
        shoestagram_catalog_seed_item('ADIDAS CAMPUS / SKATE STYLE (YELLOW)', 'Adidas', 'shoes', 6500, 4000, 5500),
        shoestagram_catalog_seed_item('PUMA SUEDE BLACK', 'Puma', 'shoes', 5000, 3000, 4500),
        shoestagram_catalog_seed_item('PUMA SUEDE BROWN', 'Puma', 'shoes', 5000, 3000, 4500),
        shoestagram_catalog_seed_item('NIKE P-6000 (BLACK)', 'Nike', 'shoes', 8000, 5500, 7000),
        shoestagram_catalog_seed_item('NIKE AIR JORDAN 4 BLACK CAT STYLE', 'Jordan', 'shoes', 12000, 7000, 10000),
        shoestagram_catalog_seed_item('TD RUNNING SHOES', 'TD', 'shoes', 2200, 900, 1500),
        shoestagram_catalog_seed_item('NIKE SHOX / SHOX STYLE (WHITE)', 'Nike', 'shoes', 9500, 6000, 8000),
        shoestagram_catalog_seed_item('PUMA CASUAL SUEDE (BROWN)', 'Puma', 'shoes', 5500, 3000, 4500),
        shoestagram_catalog_seed_item('ADIDAS RESPONSE RUNNER', 'Adidas', 'shoes', 5000, 2800, 4000),
        shoestagram_catalog_seed_item('PUMA SUEDE PLATFORM (PINK)', 'Puma', 'shoes', 6500, 3500, 5000),
        shoestagram_catalog_seed_item('VANS OLD SKOOL (BLACK/WHITE)', 'Vans', 'shoes', 5500, 3000, 4500),
        shoestagram_catalog_seed_item('VANS OLD SKOOL BANDANA PRINT', 'Vans', 'shoes', 6000, 3500, 5000),
        shoestagram_catalog_seed_item('NIKE TN / AIR MAX PLUS (BLACK)', 'Nike', 'shoes', 11500, 7000, 10000),
        shoestagram_catalog_seed_item('ON CLOUDMONSTER / ON RUNNING', 'On Running', 'shoes', 13000, 8000, 11000),
        shoestagram_catalog_seed_item('NIKE COURT / TENNIS SHOES (MINT PINK)', 'Nike', 'shoes', 7000, 3500, 5500)
    );

    $products = array();

    foreach ($raw_products as $position => $raw) {
        $id = 101 + $position;
        $price = (float) $raw['selling_price'];
        $cost_min = (float) $raw['cost_min'];
        $cost_max = (float) $raw['cost_max'];
        $cost_current = round(($cost_min + $cost_max) / 2, 2);
        $profit_current = round($price - $cost_current, 2);
        $stock = isset($raw['stock']) ? (int) $raw['stock'] : shoestagram_catalog_default_stock($raw['category'], $price, $position + 1);
        $trend_score = isset($raw['trend_score'])
            ? (int) $raw['trend_score']
            : max(58, min(97, (int) round(58 + (($position * 7) % 23) + ($profit_current / max(150, $price / 4)))));
        $sales_count = isset($raw['sales_count'])
            ? (int) $raw['sales_count']
            : max(18, (int) round(($price <= 1000 ? 90 : ($price <= 3000 ? 68 : ($price <= 7000 ? 42 : 21))) + (($position * 3) % 31)));
        $reservations = isset($raw['reservations'])
            ? (int) $raw['reservations']
            : max(2, (int) round(($sales_count / 5) + (($position + 3) % 6)));
        $rating = isset($raw['rating'])
            ? (float) $raw['rating']
            : min(5.0, max(4.2, round(4.2 + (($trend_score - 58) / 70) + ((($position + 1) % 5) * 0.08), 1)));
        $reviews_count = isset($raw['reviews_count'])
            ? (int) $raw['reviews_count']
            : max(12, (int) round($sales_count * 1.7));
        $likes = isset($raw['likes'])
            ? (int) $raw['likes']
            : max(60, (int) round($sales_count * 4.8));
        $featured = array_key_exists('featured', $raw) ? (bool) $raw['featured'] : (($position + 1) % 7 === 0 || $price >= 6500);
        $trending = array_key_exists('trending', $raw) ? (bool) $raw['trending'] : (($position + 1) % 4 === 0 || $trend_score >= 88);
        $best_seller = array_key_exists('best_seller', $raw) ? (bool) $raw['best_seller'] : ($sales_count >= 85);
        $limited = array_key_exists('limited', $raw) ? (bool) $raw['limited'] : ($stock <= 7 || $price >= 9500);
        $new_arrival = array_key_exists('new_arrival', $raw) ? (bool) $raw['new_arrival'] : ($position >= 44);
        $style = isset($raw['style']) ? $raw['style'] : shoestagram_catalog_default_style($raw['category'], $raw['name']);
        $tone = isset($raw['tone']) ? $raw['tone'] : shoestagram_catalog_tone($raw['name'], $raw['brand']);
        $colorway = isset($raw['colorway']) ? $raw['colorway'] : shoestagram_catalog_colorway($raw['name']);
        $sizes = isset($raw['sizes']) ? $raw['sizes'] : shoestagram_catalog_default_sizes($raw['category'], $raw['name']);
        $preference_tags = isset($raw['preference_tags'])
            ? $raw['preference_tags']
            : shoestagram_catalog_default_tags($raw['category'], $raw['brand'], $style, $raw['name']);
        $palette = shoestagram_catalog_palette($position);
        $image = shoestagram_catalog_placeholder($raw['name'], $palette[0], $palette[1]);
        $badge = isset($raw['badge']) ? $raw['badge'] : shoestagram_catalog_badge($price, $profit_current, $stock, $featured, $trending);
        $old_price = array_key_exists('old_price', $raw) ? $raw['old_price'] : shoestagram_catalog_old_price($price, $featured, $trending);
        $slug = shoestagram_catalog_slug($raw['name']);
        $cost_display = $cost_min === $cost_max
            ? 'PHP ' . number_format($cost_min, 2)
            : 'PHP ' . number_format($cost_min, 2) . ' - PHP ' . number_format($cost_max, 2);
        $profit_low = $price - $cost_max;
        $profit_high = $price - $cost_min;
        $profit_display = $profit_low === $profit_high
            ? 'PHP ' . number_format($profit_low, 2)
            : 'PHP ' . number_format($profit_low, 2) . ' - PHP ' . number_format($profit_high, 2);

        $products[] = array(
            'id' => $id,
            'slug' => $slug,
            'name' => $raw['name'],
            'brand' => $raw['brand'],
            'category' => $raw['category'],
            'style' => $style,
            'price' => $price,
            'old_price' => $old_price,
            'cost_min' => $cost_min,
            'cost_max' => $cost_max,
            'cost_current' => $cost_current,
            'cost_display' => $cost_display,
            'profit_current' => $profit_current,
            'profit_display' => $profit_display,
            'badge' => $badge,
            'tag' => isset($raw['tag']) ? $raw['tag'] : ($new_arrival ? 'new' : ($featured ? 'featured' : 'core')),
            'featured' => $featured,
            'trending' => $trending,
            'best_seller' => $best_seller,
            'limited' => $limited,
            'new_arrival' => $new_arrival,
            'condition' => 'Brand New',
            'stock' => $stock,
            'rating' => $rating,
            'reviews_count' => $reviews_count,
            'likes' => $likes,
            'reservations' => $reservations,
            'sales_count' => $sales_count,
            'trend_score' => $trend_score,
            'tone' => $tone,
            'colorway' => $colorway,
            'blurb' => isset($raw['blurb']) ? $raw['blurb'] : $raw['name'] . ' keeps the assortment commercially useful with strong visual familiarity and clear customer value.',
            'description' => isset($raw['description']) ? $raw['description'] : shoestagram_catalog_category_copy($raw['category']),
            'image' => $image,
            'gallery' => array(
                $image,
                shoestagram_catalog_placeholder($raw['name'] . ' Detail', $palette[0], $palette[1]),
                shoestagram_catalog_placeholder($raw['name'] . ' Angle', $palette[0], $palette[1]),
            ),
            'preference_tags' => $preference_tags,
            'sizes' => $sizes,
        );
    }

    return $products;
}
