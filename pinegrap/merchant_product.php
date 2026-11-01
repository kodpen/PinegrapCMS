<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sends one product's price, sale price and stock to Google Merchant Center
 * now, from the product screen's "Send to Merchant Center" button, and shows
 * what Merchant Center reports for the product
 * (pg_merchant_push_product() in includes/fn/merchant_center.php).
 *
 * POST only; answers by going back to the product screen with a notice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

$product_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$back = PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id;

if (pg_post_body_discarded() || ($_SERVER['REQUEST_METHOD'] !== 'POST') || ($product_id <= 0)) {
    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}

validate_token_field();

$liveform = new liveform('edit_product');

if (!function_exists('pg_merchant_push_product') || pg_catalog_product_binned($product_id)) {
    go($back);
}

$result = pg_merchant_push_product($product_id);

if (!$result['ok']) {
    $liveform->mark_error('', lang(array('string' => 'The product could not be sent to Merchant Center: {var:1}', 'vars' => array(h($result['error'])))));
    go($back);
}

log_activity(lang(array('string' => 'product (ID {var:1}) was sent to Google Merchant Center', 'vars' => array($product_id))), $_SESSION['sessionusername']);

$liveform->add_notice(lang('The price and stock of this product were sent to Merchant Center. The daily feed takes over again once Google has fetched it.'));

if (!$result['found']) {
    $liveform->add_warning(lang('Merchant Center does not list this product yet. It appears after Google next fetches the feed.'));
} elseif ($result['issues']) {
    $lines = array();

    foreach ($result['issues'] as $issue) {
        $text = h($issue['description']) . (($issue['attribute'] !== '') ? ' (' . h($issue['attribute']) . ')' : '');

        if ($issue['help'] !== '') {
            $text = '<a href="' . h($issue['help']) . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
        }

        $lines[] = $text;
    }

    $liveform->add_warning(lang('Merchant Center reports these issues for this product:') . '<br>' . implode('<br>', $lines));
}

go($back);
