<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the provider's copy of an e-document: the PDF İşbaşı (or another
 * provider) rendered, or the UBL XML that went to GİB. Pinegrap's own PDF
 * is get_erp_invoice_pdf.php; this one is the legally signed version, so it
 * is fetched from the provider every time and never cached on disk.
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

$invoice_id = (int) ($_GET['id'] ?? 0);
$format = ((string) ($_GET['format'] ?? 'pdf') === 'xml') ? 'xml' : 'pdf';
$download = !empty($_GET['download']) || ($format === 'xml');

$result = erp_edoc_invoice_document($invoice_id, $format);

if (empty($result['success'])) {
    output_error(h((string) $result['error']) . ' <a href="edit_erp_invoice.php?id=' . $invoice_id . '">' . lang('Invoice') . '</a>');
    exit();
}

$filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $result['filename']);

if ($filename === '') {
    $filename = 'edoc_' . $invoice_id . '.' . $format;
}

header('Content-Type: ' . (string) $result['mime']);
header('Content-Length: ' . strlen((string) $result['content']));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo $result['content'];
