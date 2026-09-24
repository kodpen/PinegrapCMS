<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the delivery note as a PDF (or, with ?html=1, the HTML it is made
 * from). Rendered from the built-in template through the same engine and the
 * same PDF library as the invoice.
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

$waybill_id = (int) ($_REQUEST['id'] ?? 0);
$preview_template = null;

// A draft template is a settings matter, and it is only ever posted from the
// settings form, so it takes the settings gate and the form token. It is
// tried on the latest note when none is named.
if ($_POST && isset($_POST['template'])) {
    validate_token_field();

    if (!validate_erp_access($user, 'settings')) {
        exit();
    }

    $preview_template = str_replace("\r\n", "\n", (string) $_POST['template']);

    if ($waybill_id <= 0) {
        $waybill_id = (int) db_value("SELECT id FROM erp_waybills WHERE status <> 'draft' ORDER BY id DESC LIMIT 1");
    }
}

$waybill = erp_waybill($waybill_id);

if (!is_array($waybill)) {
    output_error(($preview_template !== null) ? lang('There is no delivery note to preview yet.') : lang('The delivery note could not be found.'));
    exit();
}

// The copy kept at issue is the document, while the note stands; a cancelled
// note is rendered live so its cancelled mark shows.
$live = (($preview_template !== null) || !empty($_GET['html']) || ((string) $waybill['status'] !== 'issued'));
$kept = $live ? null : erp_archive_file('waybill', $waybill_id);

if ($kept !== null) {
    erp_archive_send($kept, ((string) $waybill['full_number'] !== '' ? (string) $waybill['full_number'] : 'waybill_' . $waybill_id) . '.pdf', !empty($_GET['download']));
}

$html = erp_waybill_html($waybill_id, $preview_template);

if ($html === false) {
    output_error(lang('The delivery note could not be found.'));
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

if (!$live) {
    erp_archive_store('waybill', $waybill_id, $pdf, (string) $waybill['full_number'], 'pdf', (int) $user['id']);
}

// The document number is the file name; anything that is not safe in a header
// or on a disk is replaced rather than escaped.
$file_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $waybill['full_number']);
if (trim($file_name, '_') === '') {
    $file_name = 'waybill_' . $waybill_id;
}

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $file_name . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo $pdf;
exit();
