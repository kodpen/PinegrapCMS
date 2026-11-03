<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for store credit at checkout (includes/fn/store_credit.php): which
 * applied_gift_cards rows a customer's credit adds, refreshes or removes,
 * and how the "Use my store credit" box is read from a post.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function store_credit_checkout_test_card($id, $balance)
{
	return array('id' => $id, 'code' => 'C' . $id, 'balance' => $balance);
}

function test_store_credit_checkout_plan_adds_every_open_card_in_spend_order()
{
	$cards = array(
		store_credit_checkout_test_card(3, 200),
		store_credit_checkout_test_card(2, 300),
		store_credit_checkout_test_card(1, 500),
	);

	pg_assert_same(array(
		'insert'  => array(
			array('gift_card_id' => 3, 'code' => 'C3', 'old_balance' => 200),
			array('gift_card_id' => 2, 'code' => 'C2', 'old_balance' => 300),
			array('gift_card_id' => 1, 'code' => 'C1', 'old_balance' => 500),
		),
		'delete'  => array(),
		'refresh' => array(),
	), pg_credit_checkout_plan($cards, array(), true), 'nothing applied yet: every open card, in the order they are given');
}

function test_store_credit_checkout_plan_keeps_applied_cards_and_refreshes_their_balance()
{
	$cards = array(
		store_credit_checkout_test_card(3, 150),
		store_credit_checkout_test_card(2, 300),
	);
	$applied = array(
		array('id' => 40, 'gift_card_id' => 3, 'old_balance' => 200),
		array('id' => 41, 'gift_card_id' => 2, 'old_balance' => 300),
	);

	pg_assert_same(array(
		'insert'  => array(),
		'delete'  => array(),
		'refresh' => array(40 => 150),
	), pg_credit_checkout_plan($cards, $applied, true), 'a card spent elsewhere meanwhile gets its new balance, an unchanged one is left');
}

function test_store_credit_checkout_plan_drops_a_card_that_is_no_longer_open()
{
	$cards = array(store_credit_checkout_test_card(2, 300));
	$applied = array(
		array('id' => 40, 'gift_card_id' => 3, 'old_balance' => 200),
		array('id' => 41, 'gift_card_id' => 2, 'old_balance' => 300),
	);

	pg_assert_same(array(
		'insert'  => array(),
		'delete'  => array(40),
		'refresh' => array(),
	), pg_credit_checkout_plan($cards, $applied, true), 'a voided, spent or expired card leaves the order');
}

function test_store_credit_checkout_plan_unticked_removes_every_credit_row()
{
	$cards = array(
		store_credit_checkout_test_card(3, 200),
		store_credit_checkout_test_card(2, 300),
	);
	$applied = array(
		array('id' => 40, 'gift_card_id' => 3, 'old_balance' => 200),
		array('id' => 41, 'gift_card_id' => 9, 'old_balance' => 50),
	);

	pg_assert_same(array(
		'insert'  => array(),
		'delete'  => array(40, 41),
		'refresh' => array(),
	), pg_credit_checkout_plan($cards, $applied, false), 'unticked: no card added, every applied credit row removed');
}

function test_store_credit_checkout_plan_without_credit_changes_nothing()
{
	pg_assert_same(array('insert' => array(), 'delete' => array(), 'refresh' => array()),
		pg_credit_checkout_plan(array(), array(), true), 'a customer with no credit: the order is left as it is');
	pg_assert_same(array('insert' => array(), 'delete' => array(), 'refresh' => array()),
		pg_credit_checkout_plan(array(), array(), false), 'unticked with no credit: nothing to remove');
}

function test_store_credit_checkout_posted_choice()
{
	pg_assert_same(null, pg_credit_checkout_posted(array()), 'no box on the form: no choice');
	pg_assert_same(null, pg_credit_checkout_posted(array('use_store_credit' => '1')), 'a tick without the box marker is not trusted');
	pg_assert_same(true, pg_credit_checkout_posted(array('store_credit_shown' => '1', 'use_store_credit' => '1')), 'ticked');
	pg_assert_same(false, pg_credit_checkout_posted(array('store_credit_shown' => '1')), 'unticked: the checkbox posts nothing');
	pg_assert_same(false, pg_credit_checkout_posted(array('store_credit_shown' => '1', 'use_store_credit' => array('1'))), 'an array is no tick');
}

function test_store_credit_checkout_wanted_follows_the_order()
{
	pg_assert_same(true, pg_credit_checkout_wanted(12, 0), 'no choice made: credit is used');
	pg_assert_same(false, pg_credit_checkout_wanted(12, 12), 'opted out for this order');
	pg_assert_same(true, pg_credit_checkout_wanted(13, 12), 'an opt-out of an earlier order does not carry over');
}

function test_gift_card_applied_repeats_counts_a_card_once()
{
	$rows = array(
		0 => array('id' => 40, 'gift_card_id' => 7, 'code' => 'AAA', 'givex' => '0'),
		1 => array('id' => 41, 'gift_card_id' => 7, 'code' => 'AAA', 'givex' => '0'),
		2 => array('id' => 42, 'gift_card_id' => 8, 'code' => 'BBB', 'givex' => '0'),
		3 => array('id' => 43, 'gift_card_id' => 0, 'code' => '6034', 'givex' => '1'),
		4 => array('id' => 44, 'gift_card_id' => 0, 'code' => '6034', 'givex' => '1'),
		5 => array('id' => 45, 'gift_card_id' => 0, 'code' => '6035', 'givex' => '1'),
	);

	pg_assert_same(array(1, 4), pg_gift_card_applied_repeats($rows), 'two rows of one card: the second goes; a Givex card repeats by code');
	pg_assert_same(array(), pg_gift_card_applied_repeats(array_slice($rows, 2, 2, true)), 'different cards: nothing removed');
	pg_assert_same(array(), pg_gift_card_applied_repeats(array()), 'no rows');
}
