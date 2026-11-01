<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quantity a cart form posts (pg_cart_posted_quantity()). The
 * designer cart widget's handler (cart_action.php) read it with (int), so an
 * emptied box, a minus sign or a typo became 0 and took the line out of the
 * cart, also on the way to checkout. The classic cart (shopping_cart.php)
 * only removes a line for 0 and only saves whole numbers; anything else
 * keeps the line's quantity.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A whole number is the new quantity; 0 removes the line.
function test_cart_posted_quantity_whole_numbers()
{
	pg_assert_same(3, pg_cart_posted_quantity('3'), 'plain number');
	pg_assert_same(0, pg_cart_posted_quantity('0'), 'zero removes');
	pg_assert_same(0, pg_cart_posted_quantity('00'), 'zeros remove');
	pg_assert_same(1250, pg_cart_posted_quantity('1,250'), 'thousands separator');
	pg_assert_same(4, pg_cart_posted_quantity(' 4 '), 'spaces around');
}

// Anything else keeps the line as it is.
function test_cart_posted_quantity_not_a_number_keeps_the_line()
{
	foreach (array('', '   ', '-1', '-0', 'abc', '2a', '1.5', '1e3', '+2') as $raw) {
		pg_assert_same(null, pg_cart_posted_quantity($raw), 'kept: "' . $raw . '"');
	}

	pg_assert_same(null, pg_cart_posted_quantity(array('2')), 'kept: a list');
	pg_assert_same(null, pg_cart_posted_quantity(null), 'kept: nothing');
}

// The designer cart reads every posted quantity through the helper and
// skips a line it keeps.
function test_cart_action_reads_quantity_through_the_helper()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/cart_action.php');

	pg_assert_contains('$q = pg_cart_posted_quantity($q);', $source, 'helper used');
	pg_assert_contains('if ($q === null) continue;', $source, 'kept line skipped');
	pg_assert_true(strpos($source, '$q   = (int)$q;') === false, 'no (int) read left');
}
