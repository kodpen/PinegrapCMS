<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the history of a Workspace channel's customer
 * (includes/workspace/customer_history.php) that need no database: which
 * source each right opens, how the rows of every source become one list,
 * newest first, with the screen each entry links to; that a source without
 * its right is left out whatever rows came; how a page ends and where the
 * next one starts, never splitting the entries of one second; and how the
 * readers ask the database.
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

// The body of a function with its comment lines left out.
function workspace_customer_history_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// The money helper as a stub: it writes down what it was handed.
function workspace_customer_history_test_money($minor, $currency)
{
	return '[' . var_export($minor, true) . ' ' . $currency . ']';
}

// Every source open.
function workspace_customer_history_test_rights($rights = array())
{
	return array_merge(array('orders' => true, 'invoices' => true, 'sales_quotes' => true, 'forms' => true, 'workspace' => true), $rights);
}

// One row of every source, each a minute apart: the task newest, the order
// oldest.
function workspace_customer_history_test_data()
{
	return array(
		'orders'       => array(
			array('id' => 501, 'order_number' => '1042', 'status' => 'complete', 'total' => '12550', 'order_date' => 1000),
		),
		'invoices'     => array(
			array('id' => 70, 'direction' => 'sales', 'full_number' => 'PGF2026000012', 'status' => 'partially_paid', 'issue_date' => '2026-10-02', 'currency' => 'TRY', 'grand_total' => '50000', 'account_title' => 'Demir Ltd', 'created_at' => 1060),
		),
		'sales_quotes' => array(
			array('id' => 88, 'full_number' => 'PGFT-0012', 'state_label' => 'Open', 'currency' => 'EUR', 'grand_total' => '99000', 'account_title' => 'Demir Ltd', 'created_at' => 1120),
		),
		'forms'        => array(
			array('id' => 61, 'form_name' => 'Contact us', 'reference_code' => 'X7K2', 'complete' => '1', 'submitted_timestamp' => 1180, 'open' => true),
			array('id' => 60, 'form_name' => 'Secret form', 'reference_code' => 'Q1', 'complete' => '1', 'submitted_timestamp' => 1190, 'open' => false),
		),
		'messages'     => array(
			array('id' => 9001, 'channel_id' => 14, 'channel_name' => 'sales', 'sender_name' => 'Ali', 'excerpt' => 'Sent the quote', 'created_at' => 1240),
		),
		'tasks'        => array(
			array('id' => 300, 'title' => 'Call back', 'status_label' => 'Open', 'created_at' => 1300),
		),
	);
}

// The ERP right opens invoices and sales quotes, read-only included; the
// ecommerce right orders, the forms right forms; a member reads where the
// customer was tagged.
function test_ws_customer_history_rights()
{
	pg_assert_same(
		array('orders' => true, 'invoices' => false, 'sales_quotes' => false, 'forms' => false, 'workspace' => true),
		ws_customer_history_rights(array('member' => true, 'ecommerce' => true, 'erp' => false, 'forms' => false)),
		'the ecommerce right'
	);
	pg_assert_same(
		array('orders' => false, 'invoices' => true, 'sales_quotes' => true, 'forms' => true, 'workspace' => false),
		ws_customer_history_rights(array('member' => false, 'ecommerce' => false, 'erp' => true, 'forms' => true)),
		'the ERP and forms rights'
	);
}

// One list, newest first, each entry with its title, what is said about it,
// its amount and the screen it opens.
function test_ws_customer_history_build_one_list_newest_first()
{
	$history = ws_customer_history_build(workspace_customer_history_test_rights(), workspace_customer_history_test_data(), 'workspace_customer_history_test_money', '/p/', 20);
	$entries = $history['entries'];

	pg_assert_same(array('task', 'message', 'form', 'sales_quote', 'invoice', 'order'), array_column($entries, 'kind'), 'newest first, a form the reader may not open left out');
	pg_assert_same(false, $history['more'], 'everything fits');
	pg_assert_same(0, $history['before'], 'no next page');

	$by_kind = array_column($entries, null, 'kind');

	pg_assert_same(array(
		'kind' => 'order', 'id' => 501, 'at' => 1000, 'title' => lang('Order') . ' #1042', 'detail' => '',
		'status_label' => lang('Complete'), 'amount' => 12550, 'amount_text' => '[12550 ]', 'url' => '/p/view_order.php?id=501',
	), $by_kind['order'], 'an order, in the site\'s currency');

	pg_assert_same(array(
		'kind' => 'invoice', 'id' => 70, 'at' => 1060, 'title' => lang('Invoice') . ' PGF2026000012', 'detail' => 'Demir Ltd · 02.10.2026',
		'status_label' => lang('Partially paid'), 'amount' => 50000, 'amount_text' => '[50000 TRY]', 'url' => '/p/edit_erp_invoice.php?id=70',
	), $by_kind['invoice'], 'an invoice, in its own currency, with the day it was issued');

	pg_assert_same(array(
		'kind' => 'sales_quote', 'id' => 88, 'at' => 1120, 'title' => lang('Sales quote') . ' PGFT-0012', 'detail' => 'Demir Ltd',
		'status_label' => 'Open', 'amount' => 99000, 'amount_text' => '[99000 EUR]', 'url' => '/p/edit_erp_quote.php?id=88',
	), $by_kind['sales_quote'], 'a sales quote, as the ERP calls its state now');

	pg_assert_same(array(
		'kind' => 'form', 'id' => 61, 'at' => 1180, 'title' => 'Contact us X7K2', 'detail' => '',
		'status_label' => '', 'amount' => null, 'amount_text' => '', 'url' => '/p/edit_submitted_form.php?id=61',
	), $by_kind['form'], 'a submitted form');

	pg_assert_same(array(
		'kind' => 'message', 'id' => 9001, 'at' => 1240, 'title' => '#sales', 'detail' => 'Ali: Sent the quote',
		'status_label' => '', 'amount' => null, 'amount_text' => '', 'url' => '/p/workspace.php?channel=14&message=9001',
	), $by_kind['message'], 'a message that tags the customer');

	pg_assert_same(array(
		'kind' => 'task', 'id' => 300, 'at' => 1300, 'title' => 'Call back', 'detail' => lang('Task'),
		'status_label' => 'Open', 'amount' => null, 'amount_text' => '', 'url' => '/p/workspace_tasks.php?task=300',
	), $by_kind['task'], 'a task that tags the customer');
}

// The other kinds of record write their own words.
function test_ws_customer_history_build_labels()
{
	$data = array(
		'invoices' => array(
			array('id' => 71, 'direction' => 'purchase', 'full_number' => '', 'status' => 'draft', 'issue_date' => '0000-00-00', 'currency' => 'TRY', 'grand_total' => '100', 'account_title' => '', 'created_at' => 5),
		),
		'forms'    => array(
			array('id' => 62, 'form_name' => '', 'reference_code' => '', 'complete' => '0', 'submitted_timestamp' => 4, 'open' => true),
		),
		'orders'   => array(
			array('id' => 502, 'order_number' => '1043', 'status' => 'odd', 'total' => '12.6', 'order_date' => 3),
		),
	);

	$by_kind = array_column(ws_customer_history_build(workspace_customer_history_test_rights(), $data, 'workspace_customer_history_test_money', '/p/', 20)['entries'], null, 'kind');

	pg_assert_same(lang('Purchase invoice') . ' #71', $by_kind['invoice']['title'], 'a purchase invoice without a number goes by its id');
	pg_assert_same('', $by_kind['invoice']['detail'], 'no account, no day');
	pg_assert_same(lang('Draft'), $by_kind['invoice']['status_label'], 'a draft says so');
	pg_assert_same(lang('Form'), $by_kind['form']['title'], 'a form without a name');
	pg_assert_same(lang('Incomplete'), $by_kind['form']['status_label'], 'an incomplete one says so');
	pg_assert_same('odd', $by_kind['order']['status_label'], 'a status without a word stays as it is');
	pg_assert_same(13, $by_kind['order']['amount'], 'an amount is rounded, never cut');
}

// A source without its right is left out whatever rows came.
function test_ws_customer_history_build_rights_close_sources()
{
	$history = ws_customer_history_build(
		workspace_customer_history_test_rights(array('orders' => false, 'invoices' => false, 'workspace' => false)),
		workspace_customer_history_test_data(),
		'workspace_customer_history_test_money',
		'/p/',
		20
	);

	pg_assert_same(array('form', 'sales_quote'), array_column($history['entries'], 'kind'));
}

// A page ends at the limit; the next starts below the first entry left out,
// and the entries of the second it falls in go together to the next page.
function test_ws_customer_history_build_pages()
{
	$data = array('tasks' => array());

	foreach (array(500, 400, 300, 300, 200) as $index => $at) {
		$data['tasks'][] = array('id' => 10 + $index, 'title' => 'T' . $index, 'status_label' => '', 'created_at' => $at);
	}

	$history = ws_customer_history_build(workspace_customer_history_test_rights(), $data, 'workspace_customer_history_test_money', '/p/', 3);

	pg_assert_same(array(500, 400), array_column($history['entries'], 'at'), 'the third entry shares its second with the first one left out, so both wait');
	pg_assert_same(true, $history['more'], 'more to read');
	pg_assert_same(301, $history['before'], 'the next page reads below 301, so both entries of second 300 come');

	$history = ws_customer_history_build(workspace_customer_history_test_rights(), $data, 'workspace_customer_history_test_money', '/p/', 2);

	pg_assert_same(array(500, 400), array_column($history['entries'], 'at'), 'a clean cut');
	pg_assert_same(301, $history['before'], 'the next page starts at the first entry left out');

	$same = array('tasks' => array());

	foreach (array(11, 12, 13) as $id) {
		$same['tasks'][] = array('id' => $id, 'title' => 'T', 'status_label' => '', 'created_at' => 700);
	}

	$history = ws_customer_history_build(workspace_customer_history_test_rights(), $same, 'workspace_customer_history_test_money', '/p/', 2);

	pg_assert_same(array(13, 12), array_column($history['entries'], 'id'), 'a page all of one second is not emptied; the newest id first');
	pg_assert_same(700, $history['before'], 'and the next page reads below that second');
}

// How many entries a page holds: 20 when nothing is asked, 1 to 50.
function test_ws_customer_history_limit()
{
	pg_assert_same(20, ws_customer_history_limit(null));
	pg_assert_same(20, ws_customer_history_limit(''));
	pg_assert_same(1, ws_customer_history_limit(0));
	pg_assert_same(50, ws_customer_history_limit(500));
	pg_assert_same(7, ws_customer_history_limit('7'));
}

// The readers: every source below the page's start, newest first; the
// Workspace part only through ws_refs and only what the reader may see,
// leaving out the channel's own messages.
function test_ws_customer_history_readers()
{
	$source = workspace_customer_history_test_source('ws_customer_history');

	pg_assert_contains('ws_customer_history_rights($viewer)', $source, 'the rights decide which sources are read');
	pg_assert_contains('ws_customer_history_build(', $source, 'one list');

	$orders = workspace_customer_history_test_source('ws_customer_history_order_rows');
	pg_assert_contains('ws_customer_order_where(', $orders, 'the orders the summary counts');
	pg_assert_contains('order_date <', $orders, 'below the page\'s start');

	foreach (array('ws_customer_history_invoice_rows' => 'erp_invoices', 'ws_customer_history_quote_rows' => 'erp_quotes') as $function => $table) {
		$body = workspace_customer_history_test_source($function);
		pg_assert_contains($table, $body, $function . ' reads ' . $table);
		pg_assert_contains('account_id IN', $body, $function . ': the customer\'s current accounts');
		pg_assert_contains('created_at <', $body, $function . ': below the page\'s start');
	}

	pg_assert_contains('erp_quote_state(', workspace_customer_history_test_source('ws_customer_history_quote_rows'), 'a quote as the ERP calls it now');
	pg_assert_contains('ws_viewer_folder_open(', workspace_customer_history_test_source('ws_customer_history_form_rows'), 'a form only in a folder the reader may edit');

	$refs = workspace_customer_history_test_source('ws_customer_history_ref_rows');
	pg_assert_contains('ws_refs', $refs, 'where the customer and its ties were tagged');
	pg_assert_contains('ws_can_read_channel(', $refs, 'a message only in a channel the reader may read');
	pg_assert_contains('ws_can_see_task(', $refs, 'a task only when the reader may see it');
	pg_assert_contains("\$viewer['member']", $refs, 'never for a site member');
	pg_assert_contains('deleted_at = 0', $refs, 'no deleted message');
}

// The route: read with workspace:read, a page asked with before and limit.
function test_ws_api_customer_history_route()
{
	$route = null;

	foreach (ws_api_routes() as $row) {
		if ($row['id'] === 'workspace.channels.customer_history') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'the route is declared');

	if (!is_array($route)) {
		return;
	}

	pg_assert_same('GET', $route['method']);
	pg_assert_same('/workspace/channels/{id}/customer/history', $route['path']);
	pg_assert_same('workspace:read', $route['scope']);
	pg_assert_same('ws_api_channel_customer_history', $route['handler']);
	pg_assert_same('WorkspaceCustomerHistory', $route['returns']);
	pg_assert_same(array('id', 'before', 'limit'), array_column($route['params'], 'name'));

	$handler = workspace_customer_history_test_source('ws_api_channel_customer_history');

	pg_assert_contains('ws_api_assistant_channel_reader(', $handler, 'the channel read as the summary reads it');
	pg_assert_contains('ws_api_customer_viewer(', $handler, 'the reader narrowed to the key\'s scopes');
	pg_assert_contains('ws_customer_history_limit(', $handler, 'the page size bounded');
}

// The objects are registered and each presenter answers with exactly the
// fields its declaration names.
function test_ws_api_customer_history_present()
{
	$objects = ws_openapi_objects();

	pg_assert_same('ws_api_customer_history_schema', $objects['WorkspaceCustomerHistory'] ?? '', 'the page is registered');
	pg_assert_same('ws_api_customer_history_entry_schema', $objects['WorkspaceCustomerHistoryEntry'] ?? '', 'an entry is registered');

	$data = workspace_customer_history_test_data();
	$history = ws_customer_history_build(workspace_customer_history_test_rights(), $data, 'workspace_customer_history_test_money', '/pg/', 2);
	$answer = ws_api_customer_history_present(array('id' => 30), $history, 'https://example.com');

	pg_assert_same(array_keys(ws_api_customer_history_schema()), array_keys($answer), 'the page');
	pg_assert_same(array_keys(ws_api_customer_history_entry_schema()), array_keys($answer['entries'][0]), 'an entry');
	pg_assert_same(30, $answer['channel_id']);
	pg_assert_same(true, $answer['more'], 'more to read');
	pg_assert_same(1181, $answer['before'], 'where the next page starts: the form, the first entry left out');
	pg_assert_same('https://example.com/pg/workspace_tasks.php?task=300', $answer['entries'][0]['url'], 'a panel address made whole');
	pg_assert_same(null, $answer['entries'][0]['amount'], 'no amount');
	pg_assert_same('1970-01-01T00:21:40Z', $answer['entries'][0]['at'], 'times as ISO-8601');

	$all = ws_api_customer_history_present(array('id' => 30), ws_customer_history_build(workspace_customer_history_test_rights(), $data, 'workspace_customer_history_test_money', '/pg/', 20), 'https://example.com');

	pg_assert_same(null, $all['before'], 'no next page');
	pg_assert_same(12550, $all['entries'][5]['amount'], 'money in minor units');
}

// The panel action reads its channel as every channel action does and asks
// the history for the same reader; the card asks it on demand.
function test_ws_channel_customer_history_action()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case 'ws_channel_customer_history':");

	pg_assert_true($start !== false, 'the Workspace actions answer ws_channel_customer_history');

	if ($start !== false) {
		$case = substr($source, $start, 900);

		pg_assert_contains('ws_action_channel($viewer, $request)', $case, 'a channel the reader may read, else not found');
		pg_assert_contains('ws_customer_identity($viewer, $channel)', $case, 'the customer named for the reader');
		pg_assert_contains('ws_customer_history($viewer, $channel, $identity', $case, 'the history for that reader');
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	pg_assert_true(isset(pg_panel_actions()['ws_channel_customer_history']), 'a row of the panel action table');

	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_customer.js');

	pg_assert_contains("'ws_channel_customer_history'", $card, 'the card asks for the history');
	pg_assert_contains('before:', $card, 'page by page');

	$strings = ws_customer_js_strings();

	foreach (array('cust_history', 'cust_older', 'cust_history_empty') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key);
	}
}
