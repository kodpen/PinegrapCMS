<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pre-information form or the distance sales contract filled from the
 * visitor's current cart, for the checkout's acceptance checkboxes to link
 * to. Shows the visitor's own cart only (the order id in the session) and
 * changes nothing; the copy kept with the order is written when the order is
 * placed (pg_legal_documents_store()).
 *
 * Request: GET type=preinfo|contract
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !pg_legal_documents_enabled()) {
    output_error(lang('This page is not available.'));
}

$types = pg_legal_document_types();
$type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';

if (!isset($types[$type])) {
    output_error(lang('This page is not available.'));
}

$documents = pg_legal_documents_for_cart();
$document = isset($documents[$type]) ? $documents[$type] : '';

// The document carries the visitor's own details: never kept by a cache.
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => true))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . h($types[$type]) . '</title>
<style>
body { margin: 0; padding: 1.5rem 1rem 3rem; background: #fff; color: #212529; font: 15px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
main { max-width: 52rem; margin: 0 auto; }
h2 { font-size: 1.5rem; margin: 0 0 1rem; }
h3 { font-size: 1.05rem; margin: 1.5rem 0 .5rem; }
table { width: 100%; border-collapse: collapse; margin: .5rem 0 1rem; }
th, td { padding: .35rem .5rem; border-bottom: 1px solid #dee2e6; vertical-align: top; }
th { font-weight: 600; }
.pg-legal-empty { padding: 1rem; border: 1px solid #dee2e6; border-radius: .375rem; }
@media print { body { padding: 0; } }
</style>
</head>
<body>
<main>'
    . (($document !== '')
        ? $document
        : '<h2>' . h($types[$type]) . '</h2><p class="pg-legal-empty">' . h(lang('There is nothing in your cart, so there is no document to show.')) . '</p>')
    . '</main>
</body>
</html>';
