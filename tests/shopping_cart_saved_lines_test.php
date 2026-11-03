<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the cart form handler (shopping_cart.php): every read of
 * the order's lines leaves a line saved for later out. Before, checkout from
 * the cart asked for the hidden line's gift card, schedule and product form
 * fields, added the product a saved line requires, and counted a saved line
 * in the free-item and shipping checks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_shopping_cart_reads_only_active_lines()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shopping_cart.php');

	// Each SELECT that reads order_items of the session's order.
	preg_match_all('/SELECT[^;]*?FROM order_items[^;]*?order_id = [^;]*;/s', $source, $reads);

	pg_assert_true(count($reads[0]) >= 7, 'the line reads are found');

	foreach ($reads[0] as $read) {
		pg_assert_contains("pg_order_item_active_sql('order_items')", $read, 'active lines only: ' . substr(preg_replace('/\s+/', ' ', $read), 0, 80));
	}
}
