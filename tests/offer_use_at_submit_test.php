<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the check the checkout's submit makes on the offers an order uses:
 * an offer that has ended, was disabled, reached its usage limit or lost
 * the code it needs since the cart worked its discount out no longer holds,
 * and offers with a usage limit and a single-use key code are locked while
 * the order is paid and completed. Also a gift line whose product is gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function offer_use_at_submit_row($overrides = array())
{
	return array_merge(array(
		'id' => 7,
		'status' => 'enabled',
		'start_date' => '2026-11-01',
		'end_date' => '2026-11-30',
		'require_code' => 1,
		'code' => 'AUTUMN',
		'conditions_met' => true,
		'usage_limit' => false,
	), $overrides);
}

function test_offer_use_at_submit_running_offer_holds()
{
	pg_assert_same(array(), pg_offer_use_refused(array(offer_use_at_submit_row()), '2026-11-24', 'AUTUMN'));
	pg_assert_same(array(), pg_offer_use_refused(array(offer_use_at_submit_row()), '2026-11-24', 'autumn'), 'the code in any case');
	pg_assert_same(array(), pg_offer_use_refused(array(offer_use_at_submit_row(array('require_code' => 0, 'code' => ''))), '2026-11-24', ''), 'an offer without a code');
	pg_assert_same(array(), pg_offer_use_refused(array(), '2026-11-24', ''), 'no offer');
}

function test_offer_use_at_submit_ended_or_disabled_offer_is_refused()
{
	pg_assert_same(array(7), pg_offer_use_refused(array(offer_use_at_submit_row()), '2026-12-01', 'AUTUMN'), 'ended at midnight');
	pg_assert_same(array(7), pg_offer_use_refused(array(offer_use_at_submit_row(array('status' => 'disabled'))), '2026-11-24', 'AUTUMN'), 'disabled');
	pg_assert_same(array(7), pg_offer_use_refused(array(offer_use_at_submit_row(array('status' => null, 'start_date' => null, 'end_date' => null))), '2026-11-24', 'AUTUMN'), 'deleted');
}

function test_offer_use_at_submit_limit_reached_is_refused()
{
	pg_assert_same(array(7), pg_offer_use_refused(array(offer_use_at_submit_row(array('conditions_met' => false))), '2026-11-24', 'AUTUMN'));
}

// A single-use key code another order used meanwhile no longer names the
// offer code, so the coded offer it stood for goes.
function test_offer_use_at_submit_code_gone_is_refused()
{
	$offers = array(offer_use_at_submit_row(), offer_use_at_submit_row(array('id' => 8, 'require_code' => 0, 'code' => '')));
	pg_assert_same(array(7), pg_offer_use_refused($offers, '2026-11-24', ''));
}

function test_offer_use_at_submit_lock_names()
{
	$offers = array(
		offer_use_at_submit_row(array('id' => 9, 'usage_limit' => true)),
		offer_use_at_submit_row(array('id' => 7)),
		offer_use_at_submit_row(array('id' => 3, 'usage_limit' => true)),
	);
	pg_assert_same(array('pg_key_code_use_12', 'pg_offer_use_3', 'pg_offer_use_9'), pg_offer_use_lock_names($offers, array('id' => 12, 'single_use' => 1)));
	pg_assert_same(array('pg_offer_use_3', 'pg_offer_use_9'), pg_offer_use_lock_names($offers, array('id' => 12, 'single_use' => 0)), 'a key code for many uses');
	pg_assert_same(array(), pg_offer_use_lock_names(array(offer_use_at_submit_row()), null), 'nothing limited');
}

function test_offer_use_at_submit_gift_product_gone()
{
	pg_assert_true(pg_offer_gift_line_product_gone(array('product_id' => 5, 'product_price' => null)), 'the product row is gone');
	pg_assert_false(pg_offer_gift_line_product_gone(array('product_id' => 5, 'product_price' => '0')), 'a free product');
	pg_assert_false(pg_offer_gift_line_product_gone(array('product_id' => 5, 'product_price' => '1500')));
}

// The submit asks before the payment, and not on a payment's return: the
// payment may already be taken then.
function test_offer_use_at_submit_called_before_payment()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$check = strpos($source, 'pg_order_offer_use_check(');
	$errors = strpos($source, '// if an error or notice exists, then send user back to previous screen');
	pg_assert_true($check !== false, 'submit_order() checks the offers');
	pg_assert_true($check < $errors, 'before the errors send the shopper back');
	pg_assert_contains("if (!pg_payment_is_return_mode(\$_GET['mode'] ?? '')) {\n        \$offer_use = pg_order_offer_use_check(", $source, 'not on a payment return');
}

function test_offer_use_at_submit_gift_loop_drops_gone_product()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	pg_assert_contains("if (!pg_offer_gift_line_product_gone(\$order_item) && ", $source);
}
