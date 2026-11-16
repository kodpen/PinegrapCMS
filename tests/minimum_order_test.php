<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a contact group's minimum order (includes/fn/minimum_order.php):
 * the setting that maps a group to an amount, the highest of a customer's
 * groups, a guest, the limit itself and an order in another currency.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/minimum_order.php');

// J8: the setting is one JSON object of group id => whole minor units;
// anything else is dropped when read.
function test_minimum_order_setting_read_and_encode()
{
	pg_assert_same(array(), pg_minimum_order_read(''));
	pg_assert_same(array(), pg_minimum_order_read('not json'));
	pg_assert_same(array(), pg_minimum_order_read(null));
	pg_assert_same(array(4 => 150000, 9 => 20000), pg_minimum_order_read('{"4":150000,"9":"20000","0":500,"x":5,"7":0,"8":-3}'));
	pg_assert_same('{"4":150000}', pg_minimum_order_encode(array(4 => 150000, 7 => 0, 0 => 900)));
	pg_assert_same('', pg_minimum_order_encode(array()), 'an empty map removes the setting');
	pg_assert_same(array(4 => 150000, 9 => 30000), pg_minimum_order_put(array(4 => 150000, 9 => 20000), 9, 30000));
	pg_assert_same(array(4 => 150000), pg_minimum_order_put(array(4 => 150000, 9 => 20000), 9, 0), 'zero removes the group');
}

// J8: a deleted group's entry is ignored when read.
function test_minimum_order_deleted_group_ignored()
{
	pg_assert_same(array(4 => 150000), pg_minimum_order_existing(array(4 => 150000, 9 => 20000), array(4)));
	pg_assert_same(array(), pg_minimum_order_existing(array(9 => 20000), array()));
}

// J8: one group.
function test_minimum_order_one_group()
{
	pg_assert_same(150000, pg_minimum_order_for_groups(array(4 => 150000), array(4)));
	pg_assert_same(0, pg_minimum_order_for_groups(array(4 => 150000), array(5)), 'another group');
}

// J8: in several such groups the highest wins.
function test_minimum_order_two_groups_highest()
{
	pg_assert_same(150000, pg_minimum_order_for_groups(array(4 => 150000, 9 => 20000), array(9, 4, 12)));
	pg_assert_same(20000, pg_minimum_order_for_groups(array(4 => 150000, 9 => 20000), array('9')));
}

// J8: a guest matches none.
function test_minimum_order_guest_matches_none()
{
	pg_assert_same(0, pg_minimum_order_for_groups(array(4 => 150000), array()));

	$judged = pg_minimum_order_judge(0, 100);
	pg_assert_true($judged['ok']);
	pg_assert_same(0, $judged['missing']);
}

// J9: the limit itself passes; a minor unit less is refused with what is missing.
function test_minimum_order_the_limit_itself()
{
	pg_assert_same(array('ok' => true, 'minimum' => 150000, 'amount' => 150000, 'missing' => 0), pg_minimum_order_judge(150000, 150000));
	pg_assert_same(array('ok' => false, 'minimum' => 150000, 'amount' => 149999, 'missing' => 1), pg_minimum_order_judge(150000, 149999));
	pg_assert_same(array('ok' => true, 'minimum' => 150000, 'amount' => 200000, 'missing' => 0), pg_minimum_order_judge(150000, 200000));
}

// J9: an order in another currency is turned into the base currency with
// round(), never cut with (int), and then compared.
function test_minimum_order_other_currency()
{
	// 3 units per base unit: 449999 / 3 = 149999.67, rounded up to 150000.
	pg_assert_same(150000, pg_minimum_order_base_amount(449999, 3.0));
	pg_assert_true(pg_minimum_order_judge(150000, pg_minimum_order_base_amount(449999, 3.0))['ok'], 'rounded, not cut');
	pg_assert_same(149999, pg_minimum_order_base_amount(449997, 3.0));
	pg_assert_false(pg_minimum_order_judge(150000, pg_minimum_order_base_amount(449997, 3.0))['ok']);
	pg_assert_same(1234, pg_minimum_order_base_amount(1234, 1.0), 'the base currency');
	pg_assert_same(1234, pg_minimum_order_base_amount(1234, 0), 'a rate of zero counts as the base');
	pg_assert_same(1235, pg_minimum_order_base_amount(1234.6, 1.0));
}

// J10: a group without a minimum works as today; no switch.
function test_minimum_order_no_minimum_as_today()
{
	pg_assert_same(0, pg_minimum_order_for_groups(array(), array(4, 9)));
	pg_assert_true(pg_minimum_order_judge(0, 0)['ok'], 'an empty cart of a customer without a minimum');
}

// The money box: an amount typed in major units becomes whole minor units;
// empty is none; a negative one is refused.
function test_minimum_order_posted_amount()
{
	pg_assert_same(150000, pg_minimum_order_posted('1.500,00'));
	pg_assert_same(150000, pg_minimum_order_posted('1500'));
	pg_assert_same(1999, pg_minimum_order_posted('19.99'));
	pg_assert_same(0, pg_minimum_order_posted(''));
	pg_assert_same(null, pg_minimum_order_posted('-5'));
}

// The wiring: the one refusal before the pre-save hook, the cart's warning
// before its messages are drawn, the edit screen's card and save.
function test_minimum_order_wiring()
{
	$submit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$cart = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact_group.php');

	pg_assert_same(1, substr_count($submit, 'pg_minimum_order_check('), 'one refusal');
	pg_assert_true(strpos($submit, 'pg_minimum_order_check(') < strpos($submit, 'eval(prepare_for_eval($pre_save_hook_code));'), 'before the pre-save hook');
	pg_assert_contains("\$liveform->mark_error('minimum_order', \$minimum_order['message']);", $submit);

	pg_assert_contains('pg_minimum_order_check(pg_minimum_order_customer(), (int) round((float) get_order_subtotal()), 1.0, \'cart\')', $cart, 'the cart asks the same function');
	pg_assert_true(strpos($cart, 'pg_minimum_order_check(') < strpos($cart, "_pg_inject_messages_node(\$tree_decoded, 'shopping_cart');"), 'before the messages are drawn');

	pg_assert_contains('pg_minimum_order_card($liveform)', $edit, 'the field on the edit screen');
	pg_assert_contains('pg_minimum_order_save($id, $minimum_order_posted)', $edit, 'kept in the one setting');
}
