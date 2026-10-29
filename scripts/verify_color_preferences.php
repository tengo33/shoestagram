<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/color_preferences.php';

function get_store_products(): array
{
    return array(
        array('category' => 'shoes', 'name' => 'Runner', 'colorway' => 'Black/White'),
        array('category' => 'shoes', 'name' => 'Copper Runner', 'colorway' => 'Copper'),
        array('category' => 'tops', 'name' => 'Red Shirt', 'colorway' => 'Red'),
    );
}

function check_color(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$base = array('category' => 'shoes', 'name' => 'Runner', 'colorway' => 'Black/White', 'trend_score' => 60, 'rating' => 4.5);
check_color(get_store_shoe_color_options() === array('Black', 'Copper', 'White'), 'Options did not follow the footwear catalog.');
check_color(store_validate_preferred_colors(array('White', 'Copper', 'Red', 'White')) === array('Copper', 'White'), 'Submitted colors were not restricted to available shoe colors.');
check_color(store_product_shoe_colors($base) === array('Black', 'White'), 'Combined colorway was not split.');
check_color(store_product_matches_preferred_colors($base, array('White')), 'White did not match Black/White.');
check_color(store_product_matches_preferred_colors($base, array('Black', 'Red')), 'Black did not match a multiple-color preference.');
check_color(!store_product_matches_preferred_colors($base, array('Red')), 'Unrelated color matched.');
check_color(!store_product_matches_preferred_colors(array_merge($base, array('category' => 'tops')), array('Black')), 'Apparel received a shoe-color match.');
check_color(store_color_preference_bonus($base, array()) === 0, 'Empty preference changed the previous score.');
check_color(store_color_preference_bonus($base, array('White')) === 18, 'Matching color did not add its score.');
check_color(store_color_preference_bonus($base, array('Red')) === 0, 'Nonmatching color changed the score.');
check_color(store_product_shoe_colors(array_merge($base, array('colorway' => 'Store Colorway', 'name' => 'Red/Black Sneakers'))) === array('Black', 'Red'), 'Name fallback did not match a multi-color shoe.');
check_color(store_product_shoe_colors(array_merge($base, array('colorway' => 'Grey'))) === array('Gray'), 'Grey spelling did not normalize.');

// Load a copy so its __DIR__/runtime points to a fresh temporary directory.
$temp = sys_get_temp_dir() . '/shoestagram-color-test-' . bin2hex(random_bytes(6));
check_color(mkdir($temp, 0700), 'Could not create isolated profile test directory.');
$source = $temp . '/store_state.php';
check_color(copy(__DIR__ . '/../database/store_state.php', $source), 'Could not copy profile store.');
$snippet = 'require ' . var_export($source, true) . '; '
    . '$initial = array("brands"=>array("Nike"),"categories"=>array("shoes"),"styles"=>array("Sport"),"colors"=>array("Black","White"),"shoe_size"=>"9","apparel_size"=>"","budget_max"=>"2000"); '
    . 'if (!store_state_save_user_profile("COLOR@EXAMPLE.COM", $initial)) exit(2); '
    . '$saved = store_state_get_user_profile("color@example.com"); '
    . 'if ($saved["colors"] !== array("Black","White") || $saved["brands"] !== array("Nike") || $saved["shoe_size"] !== "9") exit(3); '
    . 'if (store_state_profile_is_complete(array_diff_key($saved, array("colors"=>1))) !== true) exit(4); '
    . 'exit(0);';
$process = proc_open(array(PHP_BINARY, '-r', $snippet), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
check_color(is_resource($process), 'Could not run isolated profile test.');
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($process);
@unlink($temp . '/runtime/user_profiles.json');
@rmdir($temp . '/runtime');
@unlink($source);
@rmdir($temp);
check_color($code === 0, 'Profile roundtrip failed: ' . trim($stdout . $stderr));

echo "Color matching, unchanged empty-preference scoring, and isolated profile roundtrip passed.\n";
