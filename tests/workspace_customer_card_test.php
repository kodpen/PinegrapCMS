<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer card on a Workspace channel's Summary tab
 * (includes/workspace/customer.php, assets/js/workspace_customer.js) that need
 * no database: the card is the customer summary the API gives, laid out for
 * the panel with an address on each row the reader may open and its dates
 * written out; the ids the summary reads by never reach the screen; the panel
 * action reads its channel as every channel action does, and the screen asks
 * for the card only on the Summary tab of a channel about a customer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

// The body of a function with its comment lines left out.
function workspace_customer_card_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// A customer as ws_customer_identity() hands it over: the record, one tie the
// reader sees, and the ids the summary reads by.
function workspace_customer_card_test_identity()
{
	return array(
		'customer'        => array('type' => 'contact', 'id' => 12, 'kind' => 'Contact', 'label' => 'Ayşe Demir', 'detail' => 'Demir Ltd', 'known' => true, 'url' => '/p/edit_contact.php?id=12'),
		'links'           => array(array('type' => 'erp_account', 'id' => 4, 'kind' => 'Current account', 'label' => 'Demir Ltd', 'detail' => '', 'known' => true, 'url' => '/p/edit_erp_account.php?id=4')),
		'contact_id'      => 12,
		'user_ids'        => array(31),
		'erp_account_ids' => array(4, 9),
	);
}

// Every section as ws_customer_summary() lays it out.
function workspace_customer_card_test_summary()
{
	return array(
		'orders'       => array('count' => 7, 'latest' => array(
			array('id' => 501, 'number' => '1042', 'status' => 'complete', 'status_label' => 'Complete', 'total' => 12550, 'total_text' => '₺125,50', 'placed_at' => mktime(10, 0, 0, 3, 9, 2026)),
		)),
		'erp_accounts' => array(
			array('id' => 4, 'title' => 'Demir Ltd', 'status' => 'active', 'balance' => 300000, 'balance_text' => '₺3.000,00'),
			array('id' => 9, 'title' => 'Demir Ltd (old)', 'status' => 'passive', 'balance' => 0, 'balance_text' => '₺0,00'),
		),
		'sales_quotes' => array('count' => 2, 'latest' => array(
			array('id' => 88, 'number' => 'TKL-0012', 'account_id' => 4, 'account_title' => 'Demir Ltd', 'issue_date' => '2026-10-01', 'valid_until' => '2026-10-31', 'currency' => 'EUR', 'total' => 99000, 'total_text' => '€990,00'),
			array('id' => 89, 'number' => '', 'account_id' => 4, 'account_title' => 'Demir Ltd', 'issue_date' => '2026-10-02', 'valid_until' => '', 'currency' => 'TRY', 'total' => 100, 'total_text' => '₺1,00'),
		)),
		'forms'        => array(
			array('id' => 61, 'name' => 'Contact us', 'reference_code' => 'X7K2', 'complete' => true, 'submitted_at' => mktime(9, 30, 0, 10, 20, 2026)),
			array('id' => 62, 'name' => 'Quote request', 'reference_code' => '', 'complete' => false, 'submitted_at' => 0),
		),
	);
}

// A day as the card writes it: d.m.Y, from a moment or a Y-m-d day; nothing
// for no day.
function test_ws_customer_date_text()
{
	pg_assert_same(date('d.m.Y', mktime(10, 0, 0, 3, 9, 2026)), ws_customer_date_text(mktime(10, 0, 0, 3, 9, 2026)), 'a moment, in the site\'s zone');
	pg_assert_same('31.10.2026', ws_customer_date_text('2026-10-31'), 'a day as it is stored, never moved by a zone');
	pg_assert_same('', ws_customer_date_text('0000-00-00'), 'the empty day of a date column');
	pg_assert_same('', ws_customer_date_text(''), 'nothing');
	pg_assert_same('', ws_customer_date_text(0), 'no moment');
	pg_assert_same('', ws_customer_date_text('next week'), 'words are no day');
}

// Who the customer is and the ties the reader sees reach the screen; the ids
// the summary reads by do not.
function test_ws_customer_card_build_keeps_the_ids_back()
{
	$card = ws_customer_card_build(workspace_customer_card_test_identity(), array(), '/p/');

	pg_assert_same(workspace_customer_card_test_identity()['customer'], $card['customer'], 'the customer as named for the reader');
	pg_assert_same(workspace_customer_card_test_identity()['links'], $card['links'], 'the ties the reader sees');
	pg_assert_same(array('customer', 'links', 'sections'), array_keys($card), 'nothing else');
	pg_assert_same(array(), $card['sections'], 'no section the summary left out');
}

// Each row gets the address its tag has, and its dates written out; amounts
// and their text pass through as the summary wrote them.
function test_ws_customer_card_build_lays_out_the_rows()
{
	$card = ws_customer_card_build(workspace_customer_card_test_identity(), workspace_customer_card_test_summary(), '/p/');
	$sections = $card['sections'];

	pg_assert_same(array('orders', 'erp_accounts', 'sales_quotes', 'forms'), array_keys($sections), 'every section, in the summary\'s order');

	$order = $sections['orders']['latest'][0];
	pg_assert_same(7, $sections['orders']['count'], 'the count of every order');
	pg_assert_same('/p/view_order.php?id=501', $order['url'], 'the order screen');
	pg_assert_same(date('d.m.Y', mktime(10, 0, 0, 3, 9, 2026)), $order['date_text'], 'the day it was placed');
	pg_assert_same(12550, $order['total'], 'minor units kept');
	pg_assert_same('₺125,50', $order['total_text'], 'its text kept');
	pg_assert_same('Complete', $order['status_label'], 'its status as the summary named it');

	$accounts = $sections['erp_accounts'];
	pg_assert_same('/p/edit_erp_account.php?id=4', $accounts[0]['url'], 'the current account screen');
	pg_assert_same('', $accounts[0]['status_label'], 'an active account carries no status');
	pg_assert_same(lang('Passive'), $accounts[1]['status_label'], 'a passive one says so');

	$quotes = $sections['sales_quotes'];
	pg_assert_same(2, $quotes['count'], 'the open count as the summary gave it');
	pg_assert_same('/p/edit_erp_quote.php?id=88', $quotes['latest'][0]['url'], 'the quote screen');
	pg_assert_same('TKL-0012', $quotes['latest'][0]['number'], 'its number');
	pg_assert_same(lang(array('string' => 'Valid until {var:1}', 'vars' => array('31.10.2026'))), $quotes['latest'][0]['valid_until_text'], 'until when it holds');
	pg_assert_same('€990,00', $quotes['latest'][0]['total_text'], 'in its own currency');
	pg_assert_same('#89', $quotes['latest'][1]['number'], 'a quote without a number goes by its id');
	pg_assert_same('', $quotes['latest'][1]['valid_until_text'], 'no end, no line');

	$forms = $sections['forms'];
	pg_assert_same('/p/edit_submitted_form.php?id=61', $forms[0]['url'], 'the submitted form screen');
	pg_assert_same(date('d.m.Y', mktime(9, 30, 0, 10, 20, 2026)), $forms[0]['date_text'], 'the day it came');
	pg_assert_same('', $forms[0]['status_label'], 'a complete one carries no status');
	pg_assert_same(lang('Incomplete'), $forms[1]['status_label'], 'an incomplete one says so');
	pg_assert_same('', $forms[1]['date_text'], 'no moment, no day');
}

// A section the summary left out stays out: the card never adds one.
function test_ws_customer_card_build_adds_no_section()
{
	$summary = workspace_customer_card_test_summary();
	unset($summary['erp_accounts'], $summary['sales_quotes']);

	pg_assert_same(array('orders', 'forms'), array_keys(ws_customer_card_build(workspace_customer_card_test_identity(), $summary, '/p/')['sections']));
}

// The card is read for the reader: who the customer is as ws_customer_identity()
// names them for that reader, the sections ws_customer_summary() opens to them.
function test_ws_customer_card_reads_for_the_reader()
{
	$source = workspace_customer_card_test_source('ws_customer_card');

	pg_assert_contains('ws_customer_identity($viewer, $channel)', $source, 'the customer named for the reader');
	pg_assert_contains('ws_customer_summary($viewer, $channel, $identity)', $source, 'the sections the reader holds the rights to');
	pg_assert_contains('ws_customer_card_build(', $source, 'laid out for the panel');
}

// The panel action reads its channel as every channel action does, and is a
// row of the panel action table.
function test_ws_channel_customer_action()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case 'ws_channel_customer':");

	pg_assert_true($start !== false, 'the Workspace actions answer ws_channel_customer');

	if ($start !== false) {
		$case = substr($source, $start, 400);

		pg_assert_contains('ws_action_channel($viewer, $request)', $case, 'a channel the reader may read, else not found');
		pg_assert_contains('ws_customer_card($viewer, $channel)', $case, 'the card for that reader');
	}

	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['ws_channel_customer']), 'a row of the panel action table');

	if (isset($actions['ws_channel_customer'])) {
		pg_assert_same('pg_panel_ws', $actions['ws_channel_customer']['handler'], 'handled by the Workspace');
	}
}

// The screen asks for the card on the Summary tab of a channel about a
// customer, and the card's file is served in the Workspace bundle before the
// screen that calls it.
function test_ws_customer_card_on_the_summary_tab()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($screen, 'drawSummary: function () {');

	pg_assert_true($start !== false, 'the Summary tab is drawn by drawSummary()');

	if ($start !== false) {
		$body = substr($screen, $start, strpos($screen, "\n        },\n", $start) - $start);

		pg_assert_contains('PGWsCustomer.card(', $body, 'the Summary tab draws the customer card');
		pg_assert_contains('channel.customer', $body, 'only for a channel about a customer');
	}

	$card = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_customer.js');

	pg_assert_contains("'ws_channel_customer'", (string) $card, 'the card asks the panel action');
	pg_assert_contains('window.PGWsCustomer', (string) $card, 'and is lent to the screen');

	$bundle = pg_js_bundles()['assets/js/workspace.min.js'] ?? array();
	$at = array_search('assets/js/workspace_customer.js', $bundle, true);

	pg_assert_true($at !== false, 'the card is in the Workspace bundle');
	pg_assert_true(($at !== false) && ($at < array_search('assets/js/workspace.js', $bundle, true)), 'before the screen');
}

// Every word the card shows is in the screen's strings.
function test_ws_customer_card_strings()
{
	$strings = ws_customer_js_strings();

	foreach (array('cust_orders', 'cust_balance', 'cust_quotes', 'cust_forms', 'cust_empty') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key);
	}
}
