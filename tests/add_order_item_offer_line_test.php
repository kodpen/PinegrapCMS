<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * add_order_item() adds to the shopper's own line of a product it finds
 * (added_by_offer = 0) by that line's id, so a gift line an offer put in the
 * same recipient for the same product keeps its quantity and price.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// add_order_item()'s source without its comment lines.
function add_order_item_offer_line_test_source()
{
	$reflection = new ReflectionFunction('add_order_item');
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

function test_add_order_item_updates_the_found_line_only()
{
	$source = add_order_item_offer_line_test_source();

	preg_match_all('~UPDATE\s+order_items\s+SET(.*?)\$result~s', $source, $matches);

	pg_assert_same(2, count($matches[1]), 'the donation and the quantity update');

	foreach ($matches[1] as $update) {
		pg_assert_true((bool) preg_match('~WHERE\s+id\s*=\s*\?~', $update), 'an update is matched by the found line id');
		pg_assert_false(strpos($update, 'product_id') !== false, 'an update does not match every line of the product');
	}
}
