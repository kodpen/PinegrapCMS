<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The signed-in customer's own ERP documents, for the "My Invoices" widget
 * (_render_system_widget_account_invoices()):
 *
 *   ?id=N         an issued sales invoice or return of the customer's
 *                 account, as the PDF kept when it was issued (made and kept
 *                 now when it never was)
 *   ?statement=1  the account statement - the reconciliation letter - as of
 *                 today from the first of the year, or as of ?as_of= from
 *                 ?from= (Y-m-d)
 *
 * &download=1 sends it as an attachment.
 *
 * The panel's get_erp_invoice_pdf.php asks for the ERP right and stays as it
 * is; this one asks only whether the document belongs to the account linked
 * to the signed-in member's contact. Another account's document, a draft, a
 * cancelled one and a member with no account all get the same 404, so a
 * guessed number says nothing about whether it exists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// Counted per address before anything is read, so numbers tried in a loop
// run into the limit rather than into the database.
if (function_exists('waf_rate_exceeded') && function_exists('waf_client_ip')
    && waf_rate_exceeded(waf_client_ip(), 'erp_doc', 30, 60)) {
    header('Retry-After: 60');
    output_error(lang('Too many requests from your address. Please wait a moment and try again.'), 429);
}

if (pg_session_signed_in() == false) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to=' . urlencode(get_request_uri()));
    exit();
}

$account = pg_erp_member_account();

if ($account === null) {
    output_error(lang('The document could not be found.'), 404);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

$download = !empty($_GET['download']);

if (!empty($_GET['statement'])) {
    $read = function ($name) {
        return (isset($_GET[$name]) && is_scalar($_GET[$name])) ? (string) $_GET[$name] : '';
    };

    $options = erp_reconciliation_options(array('as_of' => $read('as_of'), 'from' => $read('from')));
    $pdf = erp_reconciliation_pdf($account['id'], $options);

    if ($pdf === false) {
        output_error(lang('The PDF library is not installed.'));
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . erp_reconciliation_file_name($account['id'], $options['as_of']) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');
    echo $pdf;
    exit();
}

$document = erp_storefront_document($account['id'], (isset($_GET['id']) && is_scalar($_GET['id'])) ? (int) $_GET['id'] : 0);

if ($document === null) {
    output_error(lang('The document could not be found.'), 404);
}

// The copy kept at issue is the document. One from before copies were kept
// is rendered and kept now, as the panel does; where nothing can be kept
// (the files table without its ERP columns) it is rendered for this request.
$kept = erp_archive_invoice((int) $document['id']);

if ($kept !== null) {
    erp_archive_send($kept, (string) $document['full_number'] . '.pdf', $download);
}

$html = erp_invoice_html((int) $document['id']);
$pdf = ($html !== false) ? erp_invoice_pdf($html) : false;

if ($pdf === false) {
    output_error(lang('The PDF library is not installed.'));
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $document['full_number']) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
echo $pdf;
exit();
