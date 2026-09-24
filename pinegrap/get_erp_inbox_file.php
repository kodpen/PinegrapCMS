<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - an incoming e-invoice as the supplier sent it: its PDF, or the UBL
 * XML taken out of the archive the provider wraps it in. Fetched from the
 * provider every time; the signed original is kept there and at GİB.
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

$id = (int) ($_GET['id'] ?? 0);
$format = ((string) ($_GET['format'] ?? 'pdf') === 'ubl') ? 'ubl' : 'pdf';
$row = erp_edoc_inbox_row($id);

if ($row === null) {
    output_error(lang('The document could not be found.') . ' <a href="erp_inbox.php">' . lang('Incoming e-invoices') . '</a>');
    exit();
}

$result = erp_edoc_inbox_file($row, $format);

if (empty($result['success'])) {
    output_error(h((string) $result['error']) . ' <a href="erp_inbox_document.php?id=' . $id . '">' . lang('Back') . '</a>');
    exit();
}

$filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $result['filename']);

if ($filename === '') {
    $filename = 'einvoice_' . $id . '.' . (($format === 'ubl') ? 'xml' : 'pdf');
}

// The XML is saved, never shown: a browser would run the stylesheet that
// comes embedded in it.
$download = !empty($_GET['download']) || ($format === 'ubl');

header('Content-Type: ' . (string) $result['mime']);
header('Content-Length: ' . strlen((string) $result['content']));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo $result['content'];
