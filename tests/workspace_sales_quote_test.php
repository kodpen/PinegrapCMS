<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "New sales quote" in a Workspace channel's menu
 * (includes/workspace/customer.php): which current account the quote is
 * written to (the channel's customer, else an account tied to it through
 * its contact; only an active account that is not a supplier alone), who is
 * offered it (the ERP right without the accountant's read-only one), that
 * nobody else's request reads an account, let alone opens one, that the
 * customer's ties are read once per channel, and that the channel's menu
 * never ends on the divider drawn below the item.
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

// The source of a loaded function with its whitespace and comments taken
// out, so a test can look for a statement however it is laid out.
function workspace_sales_quote_test_compact($function)
{
	$compact = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		if (!is_array($token) || ($token[0] !== T_WHITESPACE)) {
			$compact .= pg_test_source_text($token);
		}
	}

	return $compact;
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
// read for that reader, so the menu cannot tell whether one exists. Read
// from the source, because a call cannot show it: the runner has no
// database and no ERP, so the lookup returns 0 for every reader, allowed
// or not, before its first query. The lookup must open with the gate and
// return 0 there, so nothing reads before it and a gate whose condition
// is never true does not pass.
function test_workspace_sales_quote_lookup_reads_nothing_without_the_right()
{
	$source = pg_test_function_source('ws_channel_sales_quote_account');
	$compact = workspace_sales_quote_test_compact('ws_channel_sales_quote_account');
	$body = (string) substr($compact, (int) strpos($compact, '{'));
	$gate = '{if(!ws_sales_quote_may_start($viewer,$erp_readonly)){return0;}';

	pg_assert_same($gate, substr($body, 0, strlen($gate)), 'the lookup opens with the right and stops without it');

	// No account is ever opened from the Workspace
	// (erp_account_for_contact() creates one).
	pg_assert_false(strpos($source, 'erp_account_for_contact') !== false, 'never opens an account');
	pg_assert_false(stripos($source, 'INSERT') !== false, 'writes nothing');
}

// The records tied to the customer are read once per channel: the channel's
// details read them for the customer card and hand the same list to the
// lookup, which reads none itself, so an ERP writer's channel open does not
// run the contact, user and account queries twice. Read from the source,
// because the runner has no database to count queries on.
function test_workspace_sales_quote_channel_ties_read_once()
{
	$detail = workspace_sales_quote_test_compact('ws_channel_detail');

	pg_assert_same(1, substr_count($detail, 'ws_customer_link_tokens('), 'the channel details read the ties once');
	pg_assert_true((bool) preg_match('/\\$(\\w+)=[^;]*ws_customer_link_tokens\\(/', $detail, $read), 'into a variable');

	$links = '$' . ($read[1] ?? '');

	pg_assert_true(strpos($detail, 'ws_channel_customer_present($viewer,$channel,' . $links . ')') !== false, 'the customer card gets them');
	pg_assert_true((bool) preg_match('/ws_channel_sales_quote_account\\([^;]*,' . preg_quote($links, '/') . '\\);/', $detail), 'the lookup gets them');

	pg_assert_false(strpos(workspace_sales_quote_test_compact('ws_channel_sales_quote_account'), 'ws_customer_link_tokens(') !== false, 'the lookup reads no ties');
	pg_assert_true(strpos(workspace_sales_quote_test_compact('ws_channel_customer_present'), 'ws_customer_present($viewer,$type,$id,$links)') !== false, 'the channel card hands them on');
	pg_assert_true(strpos(workspace_sales_quote_test_compact('ws_customer_present'), 'if(!is_array($links)){$links=ws_customer_link_tokens($type,$id);}') !== false, 'the card reads them only when none are given');
}

// The item draws a divider below it, though a reader may be offered
// nothing after it (a public channel they have not joined, archived, that
// they may not manage): the menu never ends on a bare divider line.
function test_workspace_sales_quote_menu_never_ends_on_a_divider()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($script, 'channelMenu: function () {');

	pg_assert_true($start !== false, 'channelMenu() is in workspace.js');

	$end = strpos($script, 'wrap.appendChild(menu);', (int) $start);
	$menu = (string) substr($script, (int) $start, (int) $end - (int) $start);
	$last_divider = strrpos($menu, 'divider();');
	$tail = ($last_divider === false) ? '' : substr($menu, $last_divider);

	pg_assert_contains('channel.sales_quote_url', $menu, 'the item is in channelMenu()');
	pg_assert_true((bool) preg_match("/if \\(menu\\.lastElementChild && menu\\.lastElementChild\\.querySelector\\('\\.menu-divider'\\)\\) \\{\\s*menu\\.removeChild\\(menu\\.lastElementChild\\);\\s*\\}\\s*$/", $tail),
		'a divider left last is dropped before the menu is drawn');
}
