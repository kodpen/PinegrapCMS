<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The reorder link a customer.reorder_due event carries
 * (includes/fn/customer_rhythm_link.php): the visitor sees the products and
 * quantities due and puts them into their cart.
 *
 *   GET  reorder.php?p=<id-quantity.…>&e=<end>&s=<signature>
 *   POST reorder.php   p, e, s, token (the form token)
 *
 * Opening the page changes nothing, so a mail scanner or a link preview
 * spends nothing; the POST fills the visitor's cart at the catalogue's
 * price and stock of that moment and sends the browser to the cart. The
 * page names no order, person or address and signs no one in; an ended
 * link points to the order history behind the sign-in. Counted on the
 * firewall's sensitive allowance (waf_sensitive_scripts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// Nothing of the link is kept by a proxy, indexed, framed by another site
// or passed on as the referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$ro_title = lang('Order again');

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    http_response_code(404);
    print output_header_secure(array('title' => $ro_title))
        . pg_secure_card('bi-cart-x', lang('This link is not valid.'), '<p>' . h(lang('Check that the whole address from the message was opened, or ask for a new link.')) . '</p>')
        . output_footer_secure();
    exit();
}

$ro_self = PATH . SOFTWARE_DIRECTORY . '/reorder.php';
$ro_post = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
$ro_link = pg_customer_rhythm_link_parse($ro_post ? $_POST : $_GET);
$ro_state = pg_customer_rhythm_link_state($ro_link, pg_fs_key(), time());
$ro_error = '';

if ($ro_post && ($ro_state === 'open')) {
    validate_token_field();

    $ro_result = pg_customer_rhythm_link_apply($ro_link);

    if ($ro_result['ok']) {
        $ro_cart = pg_sw_cart_url();
        $ro_query = 'p=' . $ro_link['p'] . '&e=' . $ro_link['e'] . '&s=' . $ro_link['s'];

        header('Location: ' . (($ro_cart !== '') ? $ro_cart : $ro_self . '?' . $ro_query . '&added=1'), true, 303);
        exit();
    }

    $ro_error = (string) $ro_result['error'];
}

if ($ro_state === 'invalid') {
    http_response_code(404);
    print output_header_secure(array('title' => $ro_title))
        . pg_secure_card('bi-cart-x', lang('This link is not valid.'), '<p>' . h(lang('Check that the whole address from the message was opened, or ask for a new link.')) . '</p>')
        . output_footer_secure();
    exit();
}

if ($ro_state === 'ended') {
    $ro_account = get_page_type_url('my account');
    $ro_body = '<p>' . h(lang('This link has ended. Sign in to order these products again from your order history.')) . '</p>';

    if ($ro_account) {
        $ro_body .= '<a class="pg-button pg-button-fill w-100" href="' . h($ro_account) . '"><i class="bi bi-person me-1" aria-hidden="true"></i>' . h(lang('Go to my account')) . '</a>';
    }

    http_response_code(410);
    print output_header_secure(array('title' => $ro_title))
        . pg_secure_card('bi-cart', $ro_title, $ro_body)
        . output_footer_secure();
    exit();
}

$ro_plan = pg_customer_rhythm_link_plan($ro_link);
$ro_body = '';

if ($ro_error !== '') {
    $ro_body .= '<div class="alert theme-warning py-3" role="alert">' . h($ro_error) . '</div>';
}

if (!empty($_GET['added'])) {
    $ro_body .= '<p>' . h(lang('The products are in your cart.')) . '</p>';
}

if ($ro_plan['add']) {
    $ro_body .= '<table class="table"><thead><tr><th scope="col">' . h(lang('Product')) . '</th><th scope="col" class="text-end">' . h(lang('Quantity')) . '</th></tr></thead><tbody>';

    foreach ($ro_plan['add'] as $ro_line) {
        $ro_product = $ro_plan['products'][$ro_line['product_id']];
        $ro_name = trim((string) $ro_product['short_description']);

        $ro_body .= '<tr><td>' . h(($ro_name !== '') ? $ro_name : (string) $ro_product['name']) . '</td><td class="text-end">' . h(pg_format_number($ro_line['quantity'], 0)) . '</td></tr>';
    }

    $ro_body .= '</tbody></table>';

    if ($ro_plan['skipped']) {
        $ro_body .= '<p>' . h(lang(array('string' => '{var:1} of these products cannot be ordered now and are left out.', 'vars' => count($ro_plan['skipped'])))) . '</p>';
    }

    $ro_body .= '<form method="post" action="' . h($ro_self) . '">'
        . get_token_field()
        . '<input type="hidden" name="p" value="' . h($ro_link['p']) . '">'
        . '<input type="hidden" name="e" value="' . (int) $ro_link['e'] . '">'
        . '<input type="hidden" name="s" value="' . h($ro_link['s']) . '">'
        . '<button type="submit" class="pg-button pg-button-fill w-100"><i class="bi bi-cart-plus me-1" aria-hidden="true"></i>' . h(lang('Add to Cart')) . '</button>'
        . '<p class="small fg-secondary mt-4 mb-0">' . h(lang('Prices are the store\'s current prices, shown in the cart before you order.'))
        . ' ' . h(lang(array('string' => 'This link works until {var:1}.', 'vars' => date('d.m.Y H:i', (int) $ro_link['e'])))) . '</p>'
        . '</form>';
} elseif ($ro_error === '') {
    $ro_body .= '<p>' . h(lang('None of these products can be ordered now.')) . '</p>';
}

print output_header_secure(array('title' => $ro_title))
    . pg_secure_card('bi-cart-plus', $ro_title, $ro_body)
    . output_footer_secure();
