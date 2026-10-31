<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The public address of an issued invoice's PDF
 * (includes/erp/invoice_public_link.php): what a marketplace keeps with the
 * order and the buyer opens without signing in.
 *
 *   GET erp_invoice.php?i=<invoice id>&t=<token>.pdf
 *
 * Serves the PDF the invoice was issued with (erp_archive_invoice(), which
 * keeps it on the first opening when it was never kept) and nothing else. A
 * missing or wrong token, an address taken back, a cancelled, draft or
 * deleted invoice all get the same bare 404. Counted on the firewall's
 * sensitive allowance (waf_sensitive_scripts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
}

$invoice = function_exists('erp_invoice_public_for_request')
    ? erp_invoice_public_for_request($_GET['i'] ?? 0, $_GET['t'] ?? '')
    : null;

$file = is_array($invoice) ? erp_archive_invoice((int) $invoice['id']) : null;

if (!is_array($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit();
}

erp_archive_send($file, 'invoice-' . (string) $invoice['full_number'] . '.pdf');
