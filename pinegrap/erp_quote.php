<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page a sales quote's guest link leads to (includes/erp/quote_links.php):
 * the customer reads the quote and accepts it with a drawn signature, without
 * an account. The token in the address opens this one quote.
 *
 *   GET  erp_quote.php?t=...   the quote, or where it stands
 *   POST erp_quote.php         t, signer_name, signature, token (the form token)
 *
 * Opening the page changes nothing, so a mail scanner or a link preview
 * accepts nothing. A wrong, unknown or revoked token gets the same neutral
 * answer. There is no payment or collection here: the quote becomes an
 * invoice on the panel. Counted on the firewall's sensitive allowance
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

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
}

if (!function_exists('erp_quote_links_ready') || !erp_quote_links_ready()) {
    http_response_code(404);
    output_error(lang('This link is not valid.'));
}

$self = PATH . SOFTWARE_DIRECTORY . '/erp_quote.php';
$error = '';
$signer_name = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_token_field();

    // A field posted as an array counts as not given.
    pg_request_single_values(array('t', 'signer_name', 'signature'));

    $token = (isset($_POST['t']) && is_string($_POST['t'])) ? $_POST['t'] : '';
    $signer_name = (isset($_POST['signer_name']) && is_string($_POST['signer_name'])) ? mb_substr(trim($_POST['signer_name']), 0, 255) : '';
    $png = pg_signature_png_from_data_url((isset($_POST['signature']) && is_string($_POST['signature'])) ? $_POST['signature'] : '');

    $result = ($png === false)
        ? array('success' => false, 'error' => lang('The signature could not be read. Sign again.'))
        : erp_quote_link_accept($token, $png, $signer_name);

    if ($result['success']) {
        header('Location: ' . $self . '?t=' . rawurlencode($token), true, 303);
        exit;
    }

    $error = (string) $result['error'];
} else {
    $token = (isset($_GET['t']) && is_string($_GET['t'])) ? $_GET['t'] : '';
}

$link = erp_quote_link_for_token($token);
$quote = is_array($link) ? erp_quote((int) $link['quote_id']) : null;
$state = ($quote !== null) ? erp_quote_link_state($link, $quote, time()) : 'revoked';

if ($state === 'revoked') {
    http_response_code(404);
}

$software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
$version = function ($path) {
    return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
};
$site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
$card = '';
$scripts = '';

if ($error !== '') {
    $card .= '<div class="alert theme-warning py-3" role="alert">' . h($error) . '</div>';
}

if ($state === 'open') {
    $money = function ($minor) use ($quote) {
        return h(erp_money_out_currency((int) $minor, (string) $quote['currency']));
    };
    $rows = '';

    foreach (erp_quote_lines($quote) as $line) {
        // The same figures the panel's quote prints for the line.
        $amounts = erp_quote_line_amounts($line);
        $rows .= '<tr><td>' . h((string) $line['description']) . '</td>'
            . '<td class="text-end">' . h(erp_quantity_text($line['quantity'])) . '</td>'
            . '<td class="text-end">' . $money($line['unit_price']) . '</td>'
            . '<td class="text-end">' . (($amounts['discount'] > 0) ? $money($amounts['discount']) : '') . '</td>'
            . '<td class="text-end">' . h(erp_percent_text($amounts['tax_rate'])) . '</td>'
            . '<td class="text-end">' . $money($amounts['total']) . '</td></tr>';
    }

    $total = function ($label, $value) {
        return '<div class="d-flex justify-content-between py-1"><span>' . h($label) . '</span><b>' . $value . '</b></div>';
    };

    $card .= '<h1 class="pg-cart-quote-title">' . h(lang(array('string' => 'Quote {var:1}', 'vars' => (string) $quote['full_number']))) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h((string) $quote['account_title']) . ' · '
        . h(lang(array('string' => 'Valid until {var:1}', 'vars' => prepare_form_data_for_output((string) $quote['valid_until'], 'date', false)))) . '</p>'
        . '<div class="table-responsive"><table class="table pg-cart-quote-lines"><thead><tr><th scope="col">' . h(lang('Description')) . '</th><th scope="col" class="text-end">' . h(lang('Quantity'))
        . '</th><th scope="col" class="text-end">' . h(lang('Unit price')) . '</th><th scope="col" class="text-end">' . h(lang('Discount'))
        . '</th><th scope="col" class="text-end">' . h(erp_tax_label('percent')) . '</th><th scope="col" class="text-end">' . h(lang('Line total')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<p class="pg-cart-quote-fine mt-0 mb-3">' . h(lang('Line totals are after the discount and include VAT.')) . '</p>'
        . '<div class="mb-5">'
        . $total(lang('Subtotal'), $money($quote['subtotal']))
        . (((int) $quote['discount_total'] !== 0) ? $total(lang('Discount'), '&minus;' . $money($quote['discount_total'])) : '')
        . $total(erp_tax_label('tax'), $money($quote['tax_total']))
        . (((int) $quote['withholding_total'] !== 0) ? $total(lang('VAT withholding'), '&minus;' . $money($quote['withholding_total'])) : '')
        . '<div class="d-flex justify-content-between py-3 border-top h5 mb-0"><span>' . h(lang('Total')) . '</span><b>' . $money($quote['grand_total']) . '</b></div>'
        . '</div>'
        . ((trim((string) $quote['notes']) !== '') ? '<p class="pg-cart-quote-meta">' . nl2br(h((string) $quote['notes'])) . '</p>' : '');

    if (erp_signatures_ready()) {
        $card .= '<form method="post" action="' . h($self) . '" autocomplete="off" class="mt-5">'
            . get_token_field()
            . '<input type="hidden" name="t" value="' . h($token) . '">'
            . '<label class="form-label" for="signer_name">' . h(lang('Name of the person signing')) . '</label>'
            . '<input type="text" class="form-control mb-3" id="signer_name" name="signer_name" value="' . h($signer_name) . '" maxlength="255" required>'
            . erp_signature_pad_markup('signature', array('id' => 'erp_quote_signature'))
            . '<button type="submit" class="btn btn-solid theme-primary mt-5"><i class="bi bi-pen me-1" aria-hidden="true"></i>' . h(lang('Sign and accept')) . '</button>'
            . '<p class="pg-cart-quote-fine">' . h(lang('By signing you accept this quote as shown. An ordinary electronic signature drawn on the screen; it is not a qualified electronic signature.')) . '</p>'
            . '</form>';
        $scripts = pg_signature_includes();
    }
} elseif (($state === 'used') || ($state === 'accepted')) {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang(array('string' => 'Quote {var:1}', 'vars' => (string) $quote['full_number']))) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h(lang('This quote has been accepted. Thank you.')) . '</p>';
} elseif (($state === 'expired') || ($state === 'closed')) {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang(array('string' => 'Quote {var:1}', 'vars' => (string) $quote['full_number']))) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h(lang('This quote can no longer be accepted through this link.')) . '</p>';
} else {
    $card .= '<h1 class="pg-cart-quote-title">' . h(lang('This link is not valid.')) . '</h1>'
        . '<p class="pg-cart-quote-meta">' . h(lang('Check that the whole address from the message was opened, or ask for a new link.')) . '</p>';
}

$title = h(lang('Sales quote') . (($site !== '') ? ' · ' . $site : ''));

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
    <div class="pg-cart-quote-site"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>' . h(lang('Sales quote')) . (($site !== '') ? ' · ' . h($site) : '') . '</div>
    ' . $card . '
</main>' . $scripts . '
</body>
</html>';
