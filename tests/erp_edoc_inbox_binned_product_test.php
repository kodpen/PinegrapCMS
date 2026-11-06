<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product an incoming e-invoice line is linked to
 * (erp_edoc_inbox_line_product(), includes/erp/edoc/inbox.php): a product in
 * the catalogue's Recycle Bin is never linked, by the buyer's code nor by the
 * supplier's barcode, as the ERP product search and the barcode lookup of the
 * document editor leave it out. The function reads the database; its body is
 * read here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_edoc_inbox_binned_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/inbox.php');
	$start = strpos($source, 'function erp_edoc_inbox_line_product(');

	pg_assert_true($start !== false, 'the function exists');

	return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

function test_erp_edoc_inbox_buyer_code_skips_binned_product()
{
	$body = erp_edoc_inbox_binned_test_body();

	pg_assert_contains('if (is_array($row) && !pg_catalog_product_binned($row[\'id\'])) {', $body, 'a binned product found by the buyer code is not linked');
}

function test_erp_edoc_inbox_seller_barcode_skips_binned_product()
{
	$body = erp_edoc_inbox_binned_test_body();

	pg_assert_contains('return (($product_id > 0) && !pg_catalog_product_binned($product_id)) ? $product_id : 0;', $body, 'a binned product found by the supplier barcode is not linked');
}
