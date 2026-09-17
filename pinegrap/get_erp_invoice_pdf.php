<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the invoice as a PDF.
 *
 * ?id=N streams the document inline; &download=1 sends it as an attachment
 * and &html=1 returns the rendered HTML for a look in the browser. A POST that
 * carries a template renders that template instead of the saved one, so the
 * settings screen can show a draft before it is kept. No panel chrome is
 * printed: the output is the document and nothing else.
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

$invoice_id = (int) ($_REQUEST['id'] ?? 0);
$preview_template = null;

// A draft template is a settings matter, and it is only ever posted from the
// settings form, so it takes the settings gate and the form token.
if ($_POST && isset($_POST['template'])) {
    validate_token_field();

    if (!validate_erp_access($user, 'settings')) {
        exit();
    }

    $preview_template = str_replace("\r\n", "\n", (string) $_POST['template']);

    if ($invoice_id <= 0) {
        $invoice_id = (int) db_value("SELECT id FROM erp_invoices ORDER BY id DESC LIMIT 1");
    }
}

$invoice = ($invoice_id > 0)
    ? db_item("SELECT id, full_number FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1")
    : null;

if (!is_array($invoice)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="' . h(defined('SOFTWARE_LANGUAGE') ? SOFTWARE_LANGUAGE : 'tr') . '"><head><meta charset="utf-8"><title>' . lang('Invoice') . '</title></head>'
        . '<body style="font-family:sans-serif;padding:2rem;">'
        . (($preview_template !== null) ? lang('There is no invoice to preview yet.') : lang('The invoice could not be found.'))
        . '</body></html>';
    exit();
}

$html = erp_invoice_html($invoice_id, $preview_template);

if ($html === false) {
    output_error(lang('The invoice could not be found.'));
    exit();
}

if (($preview_template !== null) || !empty($_GET['html'])) {
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

// The document number is the file name; anything that is not safe in a header
// or on a disk is replaced rather than escaped.
$file_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $invoice['full_number']);
if (trim($file_name, '_') === '') {
    $file_name = 'invoice_' . $invoice_id;
}

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $file_name . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo $pdf;
exit();
