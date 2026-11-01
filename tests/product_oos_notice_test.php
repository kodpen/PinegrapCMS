<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_product_oos_notice() in includes/fn/ecommerce.php: the
 * "unavailable" copy the get_product action hands the variant chooser is
 * given only for a tracked product with nothing left (back-orderable or
 * not, as the catalog detail widget shows it) and as inline text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _product_oos_notice_row($inventory, $quantity, $backorder = 0)
{
	return array(
		'inventory'            => $inventory,
		'inventory_quantity'   => $quantity,
		'backorder'            => $backorder,
		'out_of_stock_message' => "<p>Sorry, this product is <b>unavailable</b>.</p>\r\n",
	);
}

// A product without stock tracking, or one in stock, shows no notice.
function test_product_oos_notice_is_empty_in_stock()
{
	pg_assert_same('', pg_product_oos_notice(_product_oos_notice_row(0, 0)), 'not tracked');
	pg_assert_same('', pg_product_oos_notice(_product_oos_notice_row('0', '0')), 'not tracked, string columns');
	pg_assert_same('', pg_product_oos_notice(_product_oos_notice_row(1, 3)), 'tracked, in stock');
}

// A tracked product with nothing left shows the copy as inline text, so it
// fits inside the paragraph it is bound to; a back-order keeps the notice.
function test_product_oos_notice_is_inline_when_out_of_stock()
{
	$inline = 'Sorry, this product is <b>unavailable</b>.';
	pg_assert_same($inline, pg_product_oos_notice(_product_oos_notice_row(1, 0)), 'tracked, none left');
	pg_assert_same($inline, pg_product_oos_notice(_product_oos_notice_row('1', '-2')), 'tracked, oversold');
	pg_assert_same($inline, pg_product_oos_notice(_product_oos_notice_row(1, 0, 1)), 'back-orderable');
}

// No copy written, no notice; missing keys read as an untracked product.
function test_product_oos_notice_without_copy()
{
	$row = _product_oos_notice_row(1, 0);
	$row['out_of_stock_message'] = '';
	pg_assert_same('', pg_product_oos_notice($row), 'empty copy');
	pg_assert_same('', pg_product_oos_notice(array()), 'no keys');
}

// The get_product action keeps its raw legacy key for old custom layouts
// and adds the gated notice; the variant chooser reads the notice.
function test_get_product_action_hands_over_the_notice()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	pg_assert_contains("\$product['out_of_stock_notice'] = pg_product_oos_notice(\$product);", $src);
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');
	pg_assert_contains("p.out_of_stock_message = ('out_of_stock_notice' in p) ? p.out_of_stock_notice : '';", $js);
}
