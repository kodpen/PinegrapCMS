<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - products for the line editors, as JSON.
 *
 * ?q= is what was typed into a line: the name (short_description), the SKU
 * (name) and the barcode are searched, results in the shape the editor fills
 * a line from (see erp_product_for_line()). ?barcode= is one scanned code and
 * answers with that one product, or null. &account_id= names the account the
 * document is for, so a product without a rate of its own carries the rate
 * of that account's tax zone; ?zone_rate=1 answers with that rate alone, for
 * lines already on the document when the account changes.
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

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

// The account the document is for: a product without a rate of its own is
// offered at the tax zone of that account's address. One value only: an
// array (?account_id[]=x) cast to 1 and priced for the first account.
$account_id = max(0, is_scalar($_GET['account_id'] ?? null) ? (int) $_GET['account_id'] : 0);

if (isset($_GET['zone_rate'])) {
    echo json_encode(array('rate' => ($account_id > 0) ? erp_account_zone_rate($account_id) : erp_default_tax_rate()), $flags);
    exit();
}

if (isset($_GET['barcode']) && is_string($_GET['barcode'])) {
    $row = erp_product_by_barcode($_GET['barcode']);

    // A product in the Recycle Bin is not put on a document, as in the search.
    if (is_array($row) && pg_catalog_product_binned($row['id'])) {
        $row = null;
    }

    echo json_encode(array('product' => is_array($row) ? erp_product_for_line($row, $account_id) : null), $flags);
    exit();
}

echo json_encode(array('results' => erp_product_search(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 20, $account_id)), $flags);
