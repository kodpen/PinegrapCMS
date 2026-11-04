<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for store credit on My Account (includes/fn/store_credit_account.php):
 * the orders grouped by card, the card list and when the section is shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_store_credit_account_uses_are_grouped_by_card()
{
	$uses = pg_credit_account_group_uses(array(
		array('gift_card_id' => '7', 'order_id' => '30', 'order_number' => '1030', 'order_date' => '1762000000', 'amount' => '1250'),
		array('gift_card_id' => '9', 'order_id' => '29', 'order_number' => '1029', 'order_date' => '1761900000', 'amount' => '500'),
		array('gift_card_id' => '7', 'order_id' => '28', 'order_number' => '1028', 'order_date' => '1761800000', 'amount' => '250'),
		array('gift_card_id' => '7', 'order_id' => '27', 'order_number' => '1027', 'order_date' => '1761700000', 'amount' => '0'),
		array('gift_card_id' => '0', 'order_id' => '26', 'order_number' => '1026', 'order_date' => '1761600000', 'amount' => '100'),
	));

	pg_assert_same(array(7, 9), array_keys($uses), 'one entry per card, no card 0');
	pg_assert_same(2, count($uses[7]), 'a row that spent nothing is left out');
	pg_assert_same('1030', $uses[7][0]['order_number'], 'newest first, as read');
	pg_assert_same(1250, $uses[7][0]['amount'], 'amount as an int');
	pg_assert_same(30, $uses[7][0]['order_id'], 'order id as an int');
}

function test_store_credit_account_cards_carry_state_last_day_and_uses()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);
	$end = mktime(0, 0, 0, 12, 1, 2026);

	$cards = pg_credit_account_cards(array(
		array('id' => '9', 'kind' => 'points', 'reason' => '200 reward points converted', 'amount' => '1000', 'balance' => '1000', 'expires_at' => '0', 'expiration_date' => '0000-00-00'),
		array('id' => '7', 'kind' => 'refund', 'reason' => 'Return 4', 'amount' => '2000', 'balance' => '0', 'expires_at' => '0', 'expiration_date' => '0000-00-00'),
		array('id' => '5', 'kind' => 'manual', 'reason' => 'Late delivery', 'amount' => '500', 'balance' => '500', 'expires_at' => (string) $end, 'expiration_date' => '2026-11-30'),
		array('id' => '3', 'kind' => 'manual', 'reason' => '', 'amount' => '500', 'balance' => '500', 'expires_at' => (string) mktime(0, 0, 0, 11, 1, 2026), 'expiration_date' => '2026-10-31'),
	), array(7 => array(array('order_id' => 30, 'order_number' => '1030', 'order_date' => 1762000000, 'amount' => 2000))), $now);

	pg_assert_same(array(9, 7, 5, 3), array_column($cards, 'id'), 'order kept');
	pg_assert_same(array('open', 'used', 'open', 'expired'), array_column($cards, 'state'), 'states');
	pg_assert_same('', $cards[0]['last_day'], 'never expires');
	pg_assert_same('2026-11-30', $cards[2]['last_day'], 'last day is the day before the end');
	pg_assert_same(1, count($cards[1]['uses']), 'the spent card lists its order');
	pg_assert_same(array(), $cards[0]['uses'], 'an unspent card lists none');
	pg_assert_same(1000, $cards[0]['balance'], 'balance as an int');
}

function test_store_credit_account_last_day_follows_a_staff_date()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	$cards = pg_credit_account_cards(array(
		array('id' => '1', 'kind' => 'manual', 'amount' => '500', 'balance' => '500', 'expires_at' => (string) mktime(0, 0, 0, 12, 1, 2026), 'expiration_date' => '2026-11-20'),
		array('id' => '2', 'kind' => 'manual', 'amount' => '500', 'balance' => '500', 'expires_at' => '0', 'expiration_date' => '2026-12-31'),
	), array(), $now);

	pg_assert_same('2026-11-20', $cards[0]['last_day'], 'an earlier gift card screen date wins');
	pg_assert_same('2026-12-31', $cards[1]['last_day'], 'a date with no expiry moment');
}

function test_store_credit_account_section_shown_with_cards_or_points()
{
	pg_assert_same(false, pg_credit_account_shown(array(), array('shown' => false)), 'nothing');
	pg_assert_same(true, pg_credit_account_shown(array(array('id' => 1)), array('shown' => false)), 'a card');
	pg_assert_same(true, pg_credit_account_shown(array(), array('shown' => true)), 'points to convert');
}
