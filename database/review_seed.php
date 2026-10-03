<?php

/*
 * Development-only product review previews.
 * These records never enter runtime reviews.json, never affect product ratings,
 * and never receive a Verified Purchase label. Remove this file and the single
 * include in store-data.php when production review coverage is sufficient.
 */
function shoestagram_demo_reviews_for_product($product)
{
    $product_id = max(0, (int) ($product['id'] ?? 0));
    $product_name = (string) ($product['name'] ?? 'this product');
    $sizes = function_exists('store_product_all_sizes') ? store_product_all_sizes($product) : array();
    $review_sizes = !empty($sizes) ? array_values($sizes) : array('', '', '');
    $templates = array(
        array(
            'customer_name' => 'Daniel M.',
            'rating' => 5,
            'title' => 'Comfortable from day one',
            'comment' => 'Very comfortable and the sizing felt accurate. The quality looks even better in person.',
            'created_at' => '2026-08-24T14:20:00+08:00',
        ),
        array(
            'customer_name' => 'Mia R.',
            'rating' => 5,
            'title' => 'Clean everyday pair',
            'comment' => 'Clean design, easy to style, and comfortable enough for everyday wear.',
            'created_at' => '2026-08-18T10:05:00+08:00',
        ),
        array(
            'customer_name' => 'Noah C.',
            'rating' => 4,
            'title' => 'Looks true to the photos',
            'comment' => 'The finish is neat and the product looks true to the gallery photos.',
            'created_at' => '2026-08-11T17:40:00+08:00',
        ),
    );

    return array_map(function ($template, $index) use ($product_id, $product_name, $review_sizes) {
        return array_merge($template, array(
            'id' => 'DEMO-REV-' . $product_id . '-' . ($index + 1),
            'order_id' => 'DEMO-ORDER-' . $product_id . '-' . ($index + 1),
            'product_id' => $product_id,
            'product_name' => $product_name,
            'size' => (string) ($review_sizes[$index % max(1, count($review_sizes))] ?? ''),
            'size_standard' => 'US',
            'status' => 'Published',
            'source' => 'development_seed',
            'is_demo' => true,
            'verified_purchase' => false,
        ));
    }, $templates, array_keys($templates));
}
