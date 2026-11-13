<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quantity a cart form posts (pg_cart_posted_quantity()). The
 * designer cart widget's handler (cart_action.php) read it with (int), so an
 * emptied box, a minus sign or a typo became 0 and took the line out of the
 * cart, also on the way to checkout. The classic cart (shopping_cart.php)
 * only removes a line for 0 and only saves whole numbers; anything else
 * keeps the line's quantity. Every handler dropped commas as thousands
 * separators, so "2,5" on a site that writes 4.999,00 put 25 units in the
 * cart: the figure is now read with the site's own separators.
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

// A site that writes 4.999,00: the dot groups thousands, the comma is the
// decimal mark, so "2,5" is two and a half and no quantity.
function test_cart_posted_quantity_turkish_separators()
{
	$tr = array('decimal' => ',', 'thousands' => '.');

	pg_assert_same(1250, pg_cart_posted_quantity('1.250', $tr), 'dot groups thousands');
	pg_assert_same(1000000, pg_cart_posted_quantity('1.000.000', $tr), 'two groups');
	pg_assert_same(7, pg_cart_posted_quantity('7', $tr), 'plain number');
	pg_assert_same(0, pg_cart_posted_quantity('0', $tr), 'zero removes');

	foreach (array('2,5', '1,250', '2.5', '12.34', '1.2345', '.250', '1.', '1..250', '-1.000', '1,000.00') as $raw) {
		pg_assert_same(null, pg_cart_posted_quantity($raw, $tr), 'refused: "' . $raw . '"');
	}
}

// A site that writes 4,999.00 groups with commas; a mark out of place is
// no grouping and no quantity ("2,5" was read as 25).
function test_cart_posted_quantity_english_separators()
{
	$en = array('decimal' => '.', 'thousands' => ',');

	pg_assert_same(1250, pg_cart_posted_quantity('1,250', $en), 'comma groups thousands');
	pg_assert_same(12, pg_cart_posted_quantity('12', $en), 'plain number');

	foreach (array('2,5', '1.250', '1,25', '12,3456', ',250') as $raw) {
		pg_assert_same(null, pg_cart_posted_quantity($raw, $en), 'refused: "' . $raw . '"');
	}
}

// A site that groups with a space takes a plain or a no-break space; one
// that writes no grouping takes only digits.
function test_cart_posted_quantity_space_and_no_grouping()
{
	$space = array('decimal' => ',', 'thousands' => "\u{00A0}");

	pg_assert_same(1250, pg_cart_posted_quantity('1 250', $space), 'plain space');
	pg_assert_same(1250, pg_cart_posted_quantity("1\u{00A0}250", $space), 'no-break space');
	pg_assert_same(null, pg_cart_posted_quantity('2,5', $space), 'decimal refused');

	$none = array('decimal' => ',', 'thousands' => '');

	pg_assert_same(1250, pg_cart_posted_quantity('1250', $none), 'digits');
	pg_assert_same(null, pg_cart_posted_quantity('1.250', $none), 'no grouping mark');
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

// The cart widget's quick add reads its quantity the same way: '1.5', '-3'
// or '1e3' were stripped to their digits (15, 3, 13) and added, with no
// upper bound.
function test_cart_action_quick_add_reads_quantity_through_the_helper()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/cart_action.php');

	pg_assert_contains("\$qa_qty = pg_cart_posted_quantity(isset(\$_POST['quick_add_quantity']) ? \$_POST['quick_add_quantity'] : '');", $source, 'quick add uses the helper');
	pg_assert_true(strpos($source, "preg_replace('/[^\\d]/', '', (string)(isset(\$_POST['quick_add_quantity'])") === false, 'no digit stripping left');
}

// The classic cart, checkout, order form and product page read their
// quantity boxes through the helper; none strips commas any more, and a
// cart or checkout box that holds no whole number says so.
function test_storefront_handlers_read_quantity_through_the_helper()
{
	foreach (array('shopping_cart.php', 'express_order.php', 'order_form.php', 'catalog_detail.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, 'pg_cart_posted_quantity(') !== false, $file . ': helper used');
		pg_assert_same(0, preg_match("/str_replace\\(',', '', [^;]*quantity/", $source), $file . ': no comma stripping left');
	}

	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("\$posted_quantity = pg_cart_posted_quantity(\$_POST['quantity'][\$order_item['id']]);", $source, $file . ': line quantity read');
		pg_assert_contains("if (\$posted_quantity === null) {\n" . ($file === 'express_order.php' ? '                ' : '            ') . "\$liveform->mark_error('quantity[' . \$order_item['id'] . ']', lang('Please enter a valid quantity.'));", $source, $file . ': refused box marked');
		pg_assert_contains("\$quick_add_quantity = pg_cart_posted_quantity(isset(\$_POST['quick_add_quantity']) ? \$_POST['quick_add_quantity'] : '');", $source, $file . ': quick add read');
	}
}
