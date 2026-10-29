<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page a cart quote link leads to (includes/fn/cart_quotes.php): the
 * visitor sees the products and quantities of the quote and puts them into
 * their cart. The token in the address opens this one quote.
 *
 *   GET  cart_quote.php?t=...   what the quote holds, or where it stands
 *   POST cart_quote.php         t, token (the form token)
 *
 * Opening the page changes nothing, so a mail scanner or a link preview
 * spends nothing; the POST fills the visitor's cart at the catalogue's
 * prices and sends the browser to the cart. The page never shows whom the
 * quote was made for. Counted on the firewall's sensitive allowance
 * (waf_sensitive_scripts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// Nothing of the quote is kept by a proxy, indexed, framed by another site
// or passed on as the referrer.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');

if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !pg_cart_quotes_ready()) {
    http_response_code(404);
    output_error(lang('This link is not valid.'));
}

$self = PATH . SOFTWARE_DIRECTORY . '/cart_quote.php';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_token_field();

    $token = (isset($_POST['t']) && is_string($_POST['t'])) ? $_POST['t'] : '';
    $result = pg_cart_quote_apply($token);

    if ($result['ok']) {
        $cart = pg_sw_cart_url();

        header('Location: ' . (($cart !== '') ? $cart : $self . '?t=' . rawurlencode($token) . '&added=1'), true, 303);
        exit;
    }

    $error = (string) $result['error'];
} else {
    $token = (isset($_GET['t']) && is_string($_GET['t'])) ? $_GET['t'] : '';
}

$view = pg_cart_quote_view($token);

if ($view['state'] === 'invalid') {
    http_response_code(404);
}

$software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
$version = function ($path) {
    return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
};
$site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
$card = '';

if ($error !== '') {
    $card .= '<div class="alert theme-warning py-3" role="alert">' . h($error) . '</div>';
}

if ($view['state'] === 'open') {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang('Your cart quote')) . '</h1>';

    if (!empty($_GET['added'])) {
        $card .= '<p class="pg-cart-quote-meta">' . h(lang('The products are in your cart.')) . '</p>';
    }

    $card .= '<table class="table pg-cart-quote-lines"><thead><tr><th scope="col">' . h(lang('Product')) . '</th><th scope="col" class="text-end">' . h(lang('Quantity')) . '</th></tr></thead><tbody>';

    foreach ($view['lines'] as $line) {
        $card .= '<tr><td>' . h($line['name']) . '</td><td class="text-end">' . h(pg_format_number($line['quantity'], 0)) . '</td></tr>';
    }

    $card .= '</tbody></table>';

    if ($view['skipped'] > 0) {
        $card .= '<p class="pg-cart-quote-meta">' . h(lang(array('string' => '{var:1} products of this cart quote cannot be ordered now and are left out.', 'vars' => (int) $view['skipped']))) . '</p>';
    }

    if ($view['lines']) {
        $card .= '<form method="post" action="' . h($self) . '">'
            . get_token_field()
            . '<input type="hidden" name="t" value="' . h($token) . '">'
            . '<button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-cart-plus me-1" aria-hidden="true"></i>' . h(lang('Add to Cart')) . '</button>'
            . '<p class="pg-cart-quote-fine">' . h(lang('Prices are the store\'s current prices, shown in the cart before you order.'))
            . (($view['valid_until'] > 0) ? ' ' . h(lang(array('string' => 'This link works until {var:1}.', 'vars' => date('d.m.Y H:i', $view['valid_until'])))) : '')
            . '</p></form>';
    } else {
        $card .= '<p class="pg-cart-quote-meta">' . h(lang('None of the products of this cart quote can be ordered now.')) . '</p>';
    }
} elseif ($view['state'] === 'ordered') {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang('Your cart quote')) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h(lang('This cart quote has already been ordered.')) . '</p>';
} elseif (($view['state'] === 'expired') || ($view['state'] === 'cancelled')) {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang('Your cart quote')) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h(lang('This cart quote can no longer be used.')) . '</p>';
} else {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang('This link is not valid.')) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h(lang('Check that the whole address from the message was opened, or ask for a new link.')) . '</p>';
}

$title = h(lang('Cart quote') . (($site !== '') ? ' · ' . $site : ''));

echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . $title . '</title>
<link rel="stylesheet" href="' . $software . '/assets/lib/bootstrap-6/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-6/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/css/cart_quote.css?v=' . $version('/assets/css/cart_quote.css') . '">
</head>
<body class="pg-cart-quote">
<main class="pg-cart-quote-card">
    <div class="pg-cart-quote-site"><i class="bi bi-cart3 me-1" aria-hidden="true"></i>' . h(lang('Cart quote')) . (($site !== '') ? ' · ' . h($site) : '') . '</div>
    ' . $card . '
</main>
</body>
</html>';
