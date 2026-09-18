<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - product lookup for the invoice line editor.
 *
 * Answers a typed fragment with up to twenty matching products, as JSON, for
 * the editor to fill a line from. Read only, and only for a logged-in user
 * with ERP access: the catalogue's own storefront endpoints know nothing of
 * kurus prices and tax rates, and this one is not for the storefront.
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

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

$query = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($query) > 100) {
    $query = mb_substr($query, 0, 100);
}

$results = array();

if ($query !== '') {
    $like = escape(escape_like($query));

    $rows = (array) db_items("SELECT id, name, price, tax_rate, short_description
        FROM products
        WHERE name LIKE '%" . $like . "%'
        ORDER BY name ASC, id ASC
        LIMIT 20");

    foreach ($rows as $row) {
        $results[] = array(
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            // Kurus, the way the editor works with money; the screen formats it.
            'price' => (int) $row['price'],
            // NULL means the product takes the zone rate, which is the operator's call here.
            'tax_rate' => ($row['tax_rate'] === null) ? null : (float) $row['tax_rate'],
            'short_description' => (string) ($row['short_description'] ?? ''),
        );
    }
}

echo json_encode(array('results' => $results), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
