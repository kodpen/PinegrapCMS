<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what weakens cart recovery (includes/fn/cart_recovery_weak.php):
 * each rule fires above its share and stays quiet under its minimum.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cart_recovery_figures.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/cart_recovery_weak.php');

// A list of the same facts n times.
function cart_recovery_weak_test_many($n, array $facts)
{
	return ($n > 0) ? array_fill(0, $n, $facts) : array();
}

function test_cart_recovery_weak_unreachable()
{
	$untouched = array_merge(
		cart_recovery_weak_test_many(3, array('address' => false, 'consent' => false)),
		cart_recovery_weak_test_many(2, array('address' => true, 'consent' => false)),
		cart_recovery_weak_test_many(4, array('address' => true, 'consent' => true))
	);

	$finding = pg_cart_recovery_weak_unreachable(12, $untouched);

	pg_assert_same('unreachable', $finding['rule'], 'fires');
	pg_assert_same(3, $finding['no_address'], 'no address');
	pg_assert_same(2, $finding['no_consent'], 'an address but no consent');
	pg_assert_same(42, $finding['percent'], '5 of 12');

	pg_assert_same(null, pg_cart_recovery_weak_unreachable(9, $untouched), 'under the minimum left carts');
	pg_assert_same(null, pg_cart_recovery_weak_unreachable(20, $untouched), '5 of 20 is not above 30 %');
}

function test_cart_recovery_weak_unreachable_leaves_out_a_deleted_order()
{
	// The no-touch row of a completed order that was deleted since: nothing
	// is known of its address, so it is not "no e-mail address".
	$gone = pg_cart_recovery_weak_untouched_fact(null, true);
	pg_assert_same(null, $gone['address'], 'a deleted order: address unknown');
	pg_assert_same(false, $gone['stock_out'], 'and no line out of stock');

	$kept = pg_cart_recovery_weak_untouched_fact(array('open' => true, 'address' => true, 'opt_in' => false, 'stock_out' => true), true);
	pg_assert_same(true, $kept['address'], 'an order still there keeps its address');
	pg_assert_same(false, $kept['consent'], 'no opt-in where the mail needs it');
	pg_assert_same(true, $kept['stock_out'], 'an open cart out of stock');
	pg_assert_same(true, pg_cart_recovery_weak_untouched_fact(array('open' => true, 'address' => true, 'opt_in' => false, 'stock_out' => false), false)['consent'], 'no opt-in needed');

	$untouched = array_merge(
		cart_recovery_weak_test_many(3, array('address' => false, 'consent' => false)),
		cart_recovery_weak_test_many(4, array('address' => null, 'consent' => false)),
		cart_recovery_weak_test_many(5, array('address' => true, 'consent' => true))
	);

	pg_assert_same(null, pg_cart_recovery_weak_unreachable(12, $untouched), '3 of 12 is not above 30 %: the deleted orders are not counted');
}

function test_cart_recovery_weak_messages()
{
	$messages = array(
		'campaign:1' => array('label' => 'Weak', 'channel' => 'email', 'sent' => 40, 'reached' => 40, 'returned' => 1),
		'campaign:2' => array('label' => 'Fine', 'channel' => 'email', 'sent' => 40, 'reached' => 40, 'returned' => 6),
		'campaign:3' => array('label' => 'Rare', 'channel' => 'email', 'sent' => 10, 'reached' => 10, 'returned' => 0),
		'campaign:4' => array('label' => 'Weaker', 'channel' => 'whatsapp', 'sent' => 60, 'reached' => 50, 'returned' => 0),
	);

	$findings = pg_cart_recovery_weak_messages($messages);

	pg_assert_same(array('Weaker', 'Weak'), array_column($findings, 'label'), 'the most sent first; a well clicked or rarely sent one is left out');
	pg_assert_same(3, $findings[1]['percent'], '1 of 40 came back');
	pg_assert_same('message', $findings[0]['rule'], 'the message rule');

	// Just under the minimum sends, and exactly at the 5 % click share: quiet.
	pg_assert_same(array(), pg_cart_recovery_weak_messages(array(
		array('label' => 'Under', 'channel' => 'email', 'sent' => 19, 'reached' => 19, 'returned' => 0),
		array('label' => 'At', 'channel' => 'email', 'sent' => 40, 'reached' => 40, 'returned' => 2),
	)), 'under its thresholds the rule stays quiet');
}

function test_cart_recovery_weak_stalled()
{
	$touched = array_merge(
		cart_recovery_weak_test_many(2, array('returned' => true, 'recovered' => true)),
		cart_recovery_weak_test_many(3, array('returned' => true, 'recovered' => false, 'open' => true, 'step' => 'payment')),
		cart_recovery_weak_test_many(1, array('returned' => true, 'recovered' => false, 'open' => true, 'step' => 'cart')),
		cart_recovery_weak_test_many(1, array('returned' => true, 'recovered' => false, 'open' => false, 'completed' => true)),
		cart_recovery_weak_test_many(5, array('returned' => false, 'recovered' => false, 'open' => true, 'step' => 'cart'))
	);

	$finding = pg_cart_recovery_weak_stalled($touched);

	pg_assert_same(7, $finding['returned'], 'only carts that came back count');
	pg_assert_same(4, $finding['open'], 'still open by the order\'s own status');
	pg_assert_same(1, $finding['late'], 'completed after the 7 days');
	pg_assert_same('payment', $finding['step'], 'the step most open carts stopped at');
	pg_assert_same(3, $finding['steps']['payment'], 'counted by step');

	pg_assert_same(null, pg_cart_recovery_weak_stalled(array_slice($touched, 0, 4)), 'under the minimum came back');
}

function test_cart_recovery_weak_late_and_stock()
{
	$touched = array_merge(
		cart_recovery_weak_test_many(6, array('delay' => 2 * 86400)),
		cart_recovery_weak_test_many(4, array('delay' => 7 * 3600)),
		cart_recovery_weak_test_many(5, array('delay' => null))
	);

	$late = pg_cart_recovery_weak_late($touched);
	pg_assert_same(6, $late['carts'], 'more than a day');
	pg_assert_same(10, $late['timed'], 'carts with no known start are left out');
	pg_assert_same(null, pg_cart_recovery_weak_late(array_slice($touched, 0, 9)), 'under the minimum timed carts');

	$carts = array_merge(
		cart_recovery_weak_test_many(3, array('recovered' => false, 'stock_out' => true)),
		cart_recovery_weak_test_many(1, array('recovered' => true, 'stock_out' => true))
	);

	pg_assert_same(3, pg_cart_recovery_weak_stock(10, $carts)['carts'], 'a recovered cart is not counted');
	pg_assert_same(null, pg_cart_recovery_weak_stock(15, $carts), '3 of 15 is not above 20 %');
}

function test_cart_recovery_weak_step_and_carts()
{
	pg_assert_same('payment', pg_cart_recovery_weak_step(array('checkout_page_id' => 4, 'billing' => true)), 'sent to the payment service');
	pg_assert_same('billing', pg_cart_recovery_weak_step(array('checkout_page_id' => 0, 'billing' => true, 'shipping' => true)), 'billing details');
	pg_assert_same('shipping', pg_cart_recovery_weak_step(array('shipping' => true)), 'a shipping address');
	pg_assert_same('cart', pg_cart_recovery_weak_step(array()), 'nothing more than the cart');

	$from = 1800000000;
	$row = array('channel' => 'email', 'clicked_at' => 0, 'restored_at' => 0, 'recovered' => 0, 'completed_at' => 0);

	$carts = pg_cart_recovery_weak_carts(array(
		array('order_id' => 1, 'sent_at' => $from + 10) + $row,
		array('order_id' => 1, 'sent_at' => $from + 20, 'clicked_at' => $from + 30) + $row,
		array('order_id' => 2, 'sent_at' => $from - 10) + $row,
		array('order_id' => 2, 'sent_at' => $from + 10) + $row,
		array('order_id' => 3, 'channel' => '', 'sent_at' => 0) + $row,
	), $from, $from + 86400);

	pg_assert_same(array(1), array_keys($carts), 'a cart of the period of its first touch');
	pg_assert_same($from + 10, $carts[1]['first_sent'], 'its first send');
	pg_assert_true($carts[1]['returned'], 'a later touch clicked');
}

function test_cart_recovery_weak_carts_completed_late()
{
	$from = 1800000000;
	$row = array('channel' => 'email', 'clicked_at' => 0, 'restored_at' => 0, 'recovered' => 0, 'completed_at' => 0);

	$carts = pg_cart_recovery_weak_carts(array(
		// Completed 8 days after its only touch: late.
		array('order_id' => 1, 'sent_at' => $from + 10, 'completed_at' => $from + 10 + 8 * 86400) + $row,
		// Completed an hour after a pop-up on a fresh cart: not recovered, not late either.
		array('order_id' => 2, 'channel' => 'page', 'sent_at' => $from + 10, 'completed_at' => $from + 3610) + $row,
		// Recovered.
		array('order_id' => 3, 'sent_at' => $from + 10, 'completed_at' => $from + 100, 'recovered' => 1) + $row,
		// Still open.
		array('order_id' => 4, 'sent_at' => $from + 10) + $row,
	), $from, $from + 86400);

	pg_assert_true($carts[1]['late'], 'completed more than 7 days after its last touch');
	pg_assert_same(false, $carts[2]['late'], 'completed within the 7 days without a recovery is not late');
	pg_assert_same(false, $carts[3]['late'], 'a recovered cart is not late');
	pg_assert_same(false, $carts[4]['late'], 'an open cart is not late');
}

function test_cart_recovery_weak_runs_every_rule()
{
	$figures = array('left' => 12, 'messages' => array());
	$untouched = cart_recovery_weak_test_many(6, array('address' => false, 'consent' => false, 'recovered' => false, 'stock_out' => true));

	$findings = pg_cart_recovery_weak($figures, array(), $untouched);

	pg_assert_same(array('unreachable', 'stock'), array_column($findings, 'rule'), 'the unreachable carts first');
	pg_assert_same(array(), pg_cart_recovery_weak(array('left' => 2, 'messages' => array()), array(), array()), 'nothing on a quiet shop');
}

function test_cart_recovery_weak_section_lines()
{
	if (!defined('PG_VISITORS_SCREEN')) {
		define('PG_VISITORS_SCREEN', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/visitors/cart_recovery.php');

	$findings = array(
		array('rule' => 'unreachable', 'carts' => 5, 'left' => 12, 'no_address' => 3, 'no_consent' => 2, 'percent' => 42),
		array('rule' => 'stalled', 'carts' => 5, 'returned' => 7, 'open' => 4, 'late' => 1, 'steps' => array(), 'step' => 'payment', 'percent' => 71),
		array('rule' => 'late', 'carts' => 6, 'timed' => 10, 'percent' => 60),
		array('rule' => 'stock', 'carts' => 3, 'left' => 10, 'percent' => 30),
		array('rule' => 'message', 'label' => 'Your cart <b>', 'channel' => 'email', 'sent' => 40, 'reached' => 40, 'returned' => 1, 'percent' => 3),
	);

	foreach ($findings as $finding) {
		pg_assert_true(pg_visitors_cart_recovery_weak_text($finding) !== '', 'a line for ' . $finding['rule']);
	}

	pg_assert_contains(lang('the payment'), pg_visitors_cart_recovery_weak_text($findings[1]), 'names the step');

	$html = pg_visitors_cart_recovery_weak_list($findings);
	pg_assert_same(5, substr_count($html, '<li '), 'one line each');
	pg_assert_contains('Your cart &lt;b&gt;', $html, 'the message name escaped');
}

function test_cart_recovery_weak_unreachable_line_leaves_out_a_zero()
{
	if (!defined('PG_VISITORS_SCREEN')) {
		define('PG_VISITORS_SCREEN', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/visitors/cart_recovery.php');

	$text = pg_visitors_cart_recovery_weak_text(array('rule' => 'unreachable', 'carts' => 5, 'left' => 12, 'no_address' => 5, 'no_consent' => 0, 'percent' => 42));
	pg_assert_same(lang(array('string' => '{var:1} of {var:2} left carts ({var:3} %) could not be reached: they have no e-mail address.', 'vars' => array('5', '12', 42))), $text, 'the no-address line alone');
}
