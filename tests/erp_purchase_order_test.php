<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the purchase order line keys: erp_source_line_key()
 * (includes/erp/invoice_manual.php), which cleans the key a line arrives
 * with, and erp_purchase_order_line_keys() (includes/erp/purchase_orders.php),
 * which gives every order line one of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');

// A plain key passes through as it is.
function test_erp_purchase_order_source_line_key_keeps_letters_and_digits()
{
	pg_assert_same('a1b2c3d4', erp_source_line_key('a1b2c3d4'));
	pg_assert_same('7', erp_source_line_key(7));
}

// Anything but letters and digits is dropped, and the key is cut at 16.
function test_erp_purchase_order_source_line_key_cleans_and_cuts()
{
	pg_assert_same('ab12', erp_source_line_key(' a-b"1<2> '));
	pg_assert_same('0123456789abcdef', erp_source_line_key('0123456789abcdefXYZ'));
}

// Empty, and the zero the line editor leaves in an emptied row, are no key.
function test_erp_purchase_order_source_line_key_empty_and_zero()
{
	pg_assert_same('', erp_source_line_key(''));
	pg_assert_same('', erp_source_line_key(null));
	pg_assert_same('', erp_source_line_key('0'));
	pg_assert_same('', erp_source_line_key('000'));
	pg_assert_same('10', erp_source_line_key('10'));
}

// Lines that come with their keys keep them, in order.
function test_erp_purchase_order_line_keys_keep_existing()
{
	$keyed = erp_purchase_order_line_keys(array(
		array('description' => 'A', 'source_line' => 'aaaa1111'),
		array('description' => 'B', 'source_line' => 'bbbb2222'),
	));

	pg_assert_same(array('aaaa1111', 'bbbb2222'), array_column($keyed, 'source_line'));
	pg_assert_same(array('A', 'B'), array_column($keyed, 'description'));
}

// A new line, or one whose key was emptied, is given a fresh key.
function test_erp_purchase_order_line_keys_give_new_lines_a_key()
{
	$next = 0;
	$keyed = erp_purchase_order_line_keys(array(
		array('description' => 'A', 'source_line' => 'aaaa1111'),
		array('description' => 'B'),
		array('description' => 'C', 'source_line' => '0'),
	), function () use (&$next) {
		return 'new' . (++$next);
	});

	pg_assert_same(array('aaaa1111', 'new1', 'new2'), array_column($keyed, 'source_line'));
}

// A key already taken by an earlier line (a copied row, a key typed twice,
// one differing only in case) is not handed to a second line.
function test_erp_purchase_order_line_keys_no_duplicates()
{
	$next = 0;
	$keyed = erp_purchase_order_line_keys(array(
		array('source_line' => 'abcd'),
		array('source_line' => 'abcd'),
		array('source_line' => 'ABCD'),
	), function () use (&$next) {
		return 'k' . (++$next);
	});

	pg_assert_same(array('abcd', 'k1', 'k2'), array_column($keyed, 'source_line'));
}

// A fresh key that clashes with one already used is drawn again.
function test_erp_purchase_order_line_keys_redraw_on_clash()
{
	$draws = array('aaaa', 'aaaa', 'bbbb');
	$keyed = erp_purchase_order_line_keys(array(
		array('source_line' => 'aaaa'),
		array('source_line' => ''),
	), function () use (&$draws) {
		return array_shift($draws);
	});

	pg_assert_same(array('aaaa', 'bbbb'), array_column($keyed, 'source_line'));
}

// The default keys are eight hex digits, and differ from line to line.
function test_erp_purchase_order_line_keys_default_random()
{
	$keyed = erp_purchase_order_line_keys(array(array(), array(), array()));
	$keys = array_column($keyed, 'source_line');

	pg_assert_same(3, count(array_unique($keys)));
	foreach ($keys as $key) {
		pg_assert_true(preg_match('/^[0-9a-f]{8}$/', $key) === 1);
	}
}
