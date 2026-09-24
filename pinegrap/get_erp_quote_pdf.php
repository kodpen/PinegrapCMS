<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the quote as a PDF, in the store's invoice template.
 *
 * ?id=N streams it inline, &download=1 sends it as an attachment and &html=1
 * returns the rendered HTML. A quote changes while it is open, so it is drawn
 * fresh every time rather than kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

$quote = erp_quote((int) ($_GET['id'] ?? 0));

if ($quote === null) {
    output_error(lang('The quote could not be found.'));
    exit();
}

$html = erp_quote_html((int) $quote['id']);

if ($html === false) {
    output_error(lang('The quote could not be found.'));
    exit();
}

if (!empty($_GET['html'])) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo $html;
    exit();
}

$pdf = erp_invoice_pdf($html);

if ($pdf === false) {
    output_error(lang('The PDF library is not installed.'));
    exit();
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . erp_quote_file_name($quote) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo $pdf;
exit();
