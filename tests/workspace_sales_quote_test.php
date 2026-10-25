<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "New sales quote" in a Workspace channel's menu
 * (includes/workspace/customer.php): which current account the quote is
 * written to (the channel's customer, else an account tied to it through
 * its contact; only an active account that is not a supplier alone), who is
 * offered it (the ERP right without the accountant's read-only one), and
 * that nobody else's request reads an account, let alone opens one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// erp_accounts rows as the lookup reads them, by id.
function workspace_sales_quote_test_accounts()
{
	return array(
		7  => array('id' => 7, 'kind' => 'customer', 'status' => 'active'),
		8  => array('id' => 8, 'kind' => 'both', 'status' => 'active'),
		9  => array('id' => 9, 'kind' => 'supplier', 'status' => 'active'),
		10 => array('id' => 10, 'kind' => 'customer', 'status' => 'passive'),
	);
}

// The ties ws_customer_link_tokens() gives, as tags.
function workspace_sales_quote_test_links(array $pairs)
{
	$out = array();

	foreach ($pairs as $pair) {
		$out[] = array('sigil' => '#', 'type' => $pair[0], 'id' => $pair[1]);
	}

	return $out;
}

// A Workspace member, with or without the ERP right.
function workspace_sales_quote_test_viewer($erp)
{
	return array('id' => 4, 'role' => 3, 'member' => true, 'erp' => $erp, 'erp_cash' => false);
}

// A channel whose customer is a current account is about that account.
function test_workspace_sales_quote_account_is_the_customer()
{
	pg_assert_same(7, ws_sales_quote_account_pick('erp_account', 7, array(), workspace_sales_quote_test_accounts()));
	pg_assert_same(8, ws_sales_quote_account_pick('erp_account', 8, array(), workspace_sales_quote_test_accounts()), 'an account that buys and sells');
}

// A contact or a user goes to the account tied to it through the contact.
function test_workspace_sales_quote_account_tied_to_the_customer()
{
	$links = workspace_sales_quote_test_links(array(array('user_account', 3), array('erp_account', 7)));

	pg_assert_same(7, ws_sales_quote_account_pick('contact', 21, $links, workspace_sales_quote_test_accounts()), 'contact');
	pg_assert_same(7, ws_sales_quote_account_pick('user_account', 3,
		workspace_sales_quote_test_links(array(array('contact', 21), array('erp_account', 7))), workspace_sales_quote_test_accounts()), 'user');
}

// No quote is written to a supplier alone or to a passive account (the
// account screen offers none; the editor lists active accounts only): the
// next account tied to the customer is taken.
function test_workspace_sales_quote_account_skips_suppliers_and_passive_accounts()
{
	$accounts = workspace_sales_quote_test_accounts();

	pg_assert_same(0, ws_sales_quote_account_pick('erp_account', 9, array(), $accounts), 'supplier');
	pg_assert_same(0, ws_sales_quote_account_pick('erp_account', 10, array(), $accounts), 'passive');
	pg_assert_same(8, ws_sales_quote_account_pick('erp_account', 9, workspace_sales_quote_test_links(array(array('contact', 21), array('erp_account', 8))), $accounts), 'supplier with a tied customer account');
	pg_assert_same(7, ws_sales_quote_account_pick('contact', 21, workspace_sales_quote_test_links(array(array('erp_account', 10), array('erp_account', 9), array('erp_account', 7))), $accounts), 'first usable tie');
}

// An account that is gone, ties of other kinds and a channel about nobody
// give none.
function test_workspace_sales_quote_account_none()
{
	$accounts = workspace_sales_quote_test_accounts();

	pg_assert_same(0, ws_sales_quote_account_pick('erp_account', 99, array(), $accounts), 'deleted account');
	pg_assert_same(0, ws_sales_quote_account_pick('contact', 21, workspace_sales_quote_test_links(array(array('user_account', 3), array('erp_account', 99))), $accounts), 'only a deleted tie');
	pg_assert_same(0, ws_sales_quote_account_pick('contact', 7, workspace_sales_quote_test_links(array(array('user_account', 7))), $accounts), 'a contact or user id is never an account id');
	pg_assert_same(0, ws_sales_quote_account_pick('', 0, array(), $accounts), 'no customer');
	pg_assert_same(0, ws_sales_quote_account_pick('erp_account', 0, array(), $accounts), 'no id');
}

// Offered to whoever may write ERP documents: the ERP right, not the
// accountant's read-only one.
function test_workspace_sales_quote_offered_to_erp_writers_only()
{
	pg_assert_true(ws_sales_quote_may_start(workspace_sales_quote_test_viewer(true), false), 'ERP right');
	pg_assert_false(ws_sales_quote_may_start(workspace_sales_quote_test_viewer(false), false), 'no ERP right');
	pg_assert_false(ws_sales_quote_may_start(workspace_sales_quote_test_viewer(true), true), 'read-only accountant');
	pg_assert_false(ws_sales_quote_may_start(array('id' => 4, 'role' => 3, 'member' => true), false), 'a reader built without module rights');
}

// Without the right nothing is looked up: the channel's account is never
// read for that reader, so the menu cannot tell whether one exists. Errors
// throw here, so a query (there is no database) fails the test.
function test_workspace_sales_quote_lookup_reads_nothing_without_the_right()
{
	$channel = array('id' => 5, 'contact_id' => 21, 'customer_type' => 'erp_account', 'customer_id' => 7);
	$before = pg_error_throws();

	pg_error_throws(true);

	try {
		pg_assert_same(0, ws_channel_sales_quote_account(workspace_sales_quote_test_viewer(false), $channel, false), 'no ERP right');
		pg_assert_same(0, ws_channel_sales_quote_account(workspace_sales_quote_test_viewer(true), $channel, true), 'read-only accountant');
	} finally {
		pg_error_throws($before);
	}

	// The right is asked before the first query, and no account is ever
	// opened from the Workspace (erp_account_for_contact() creates one).
	$source = pg_test_function_source('ws_channel_sales_quote_account');
	$gate = strpos($source, 'ws_sales_quote_may_start(');

	pg_assert_true($gate !== false, 'the lookup asks ws_sales_quote_may_start()');

	foreach (array('db_', 'ws_customer_link_tokens(', 'ws_erp_quotes_readable(') as $reader) {
		$at = strpos($source, $reader);
		pg_assert_true(($at === false) || ($gate < $at), $reader . ' comes after the right is asked');
	}

	pg_assert_false(strpos($source, 'erp_account_for_contact') !== false, 'never opens an account');
	pg_assert_false(stripos($source, 'INSERT') !== false, 'writes nothing');
}
