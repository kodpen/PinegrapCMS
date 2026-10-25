<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer summary of a Workspace channel
 * (includes/workspace/customer.php) and its route in the external API
 * (GET /workspace/channels/{id}/customer, includes/workspace/api_customer.php)
 * that need no database: who the customer is and which ties the reader sees,
 * which section each right opens, how the sections are laid out from their
 * rows (money in integer minor units, written out with the money helpers),
 * that a section without its right or without rows is left out, that cart
 * quotes are a seam that shows nothing yet, and how the route is declared,
 * reads its channel as the channel context does and narrows the sections to
 * the key's scopes.
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

function workspace_customer_summary_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// A ws_refs_resolve() entry as the reader sees it.
function workspace_customer_summary_test_ref($type, $id, $label, $known, $url = '', $meta = '')
{
	return array('type' => $type, 'id' => $id, 'label' => $label, 'url' => $url, 'meta' => $meta, 'status' => '', 'known' => $known, 'icon' => '');
}

// Every right a section can ask for.
function workspace_customer_summary_test_viewer($rights = array())
{
	return array_merge(array(
		'id' => 7, 'role' => 3, 'member' => true,
		'ecommerce' => true, 'contacts' => true, 'erp' => true, 'erp_cash' => true, 'forms' => true, 'calendars' => true, 'users' => false,
	), $rights);
}

// The money helper as a stub: it writes down what it was handed.
function workspace_customer_summary_test_money($minor, $currency)
{
	return '[' . var_export($minor, true) . ' ' . $currency . ']';
}

// Rows of every section as the readers hand them over.
function workspace_customer_summary_test_data()
{
	return array(
		'orders' => array('count' => 7, 'rows' => array(
			array('id' => '41', 'order_number' => '1045', 'status' => 'complete', 'total' => '125000', 'order_date' => '1792580000'),
			array('id' => '38', 'order_number' => '1031', 'status' => 'cancelled', 'total' => '9990', 'order_date' => '1792400000'),
		)),
		'erp_accounts' => array(
			array('id' => '9', 'title' => 'Acme Ltd', 'status' => 'active', 'balance' => '-45050'),
		),
		'sales_quotes' => array('count' => 3, 'rows' => array(
			array('id' => '12', 'full_number' => 'PGFT2026000012', 'account_id' => '9', 'account_title' => 'Acme Ltd', 'issue_date' => '2026-10-20', 'valid_until' => '2026-11-19', 'currency' => 'EUR', 'grand_total' => '300000'),
			array('id' => '11', 'full_number' => 'PGFT2026000011', 'account_id' => '9', 'account_title' => 'Acme Ltd', 'issue_date' => '2026-10-01', 'valid_until' => '0000-00-00', 'currency' => 'TRY', 'grand_total' => '150050'),
		)),
		'forms' => array(
			array('id' => '730', 'reference_code' => 'AB12CD34EF', 'submitted_timestamp' => '1792500000', 'complete' => '1', 'form_name' => 'Contact us', 'open' => true),
			array('id' => '729', 'reference_code' => 'ZZ99YY88XX', 'submitted_timestamp' => '1792400000', 'complete' => '0', 'form_name' => 'Staff only', 'open' => false),
			array('id' => '728', 'reference_code' => 'QQ11WW22EE', 'submitted_timestamp' => '1792300000', 'complete' => '0', 'form_name' => null, 'open' => true),
		),
	);
}

/* ---------------------------------------------------------------------------
   Who the customer is
   --------------------------------------------------------------------------- */

// The customer chosen, with the ties the reader may see; the ids the summary
// reads by hold every tie, seen or not.
function test_ws_customer_identity_build_names_the_customer_and_its_ties()
{
	$links = array(
		array('sigil' => '#', 'type' => 'contact', 'id' => 5),
		array('sigil' => '#', 'type' => 'user_account', 'id' => 3),
		array('sigil' => '#', 'type' => 'erp_account', 'id' => 9),
	);

	$refs = array(
		'erp_account:4'  => workspace_customer_summary_test_ref('erp_account', 4, 'Acme Holding', true, '/pg/edit_erp_account.php?id=4', 'Customer'),
		'contact:5'      => workspace_customer_summary_test_ref('contact', 5, 'Ayşe Demir', true, '/pg/edit_contact.php?id=5', 'ayse@example.com'),
		'user_account:3' => workspace_customer_summary_test_ref('user_account', 3, lang('User'), false),
		'erp_account:9'  => workspace_customer_summary_test_ref('erp_account', 9, 'Acme Ltd', true, '/pg/edit_erp_account.php?id=9'),
	);

	$identity = ws_customer_identity_build('erp_account', 4, 5, $links, $refs);

	pg_assert_same(array(
		'type'   => 'erp_account',
		'id'     => 4,
		'kind'   => lang('Current account'),
		'label'  => 'Acme Holding',
		'detail' => 'Customer',
		'known'  => true,
		'url'    => '/pg/edit_erp_account.php?id=4',
	), $identity['customer'], 'the customer as the reader sees it');

	pg_assert_same(array('contact:5', 'erp_account:9'), array_map(function ($item) {
		return $item['type'] . ':' . $item['id'];
	}, $identity['links']), 'only the ties the reader may see are listed');

	pg_assert_same(5, $identity['contact_id'], 'the contact it stands for');
	pg_assert_same(array(3), $identity['user_ids'], 'a user the reader may not see is still read by');
	pg_assert_same(array(4, 9), $identity['erp_account_ids'], 'the customer itself and the account tied to it');
}

// A customer the reader may not see goes by its kind and number, with no
// address and nothing that would tell more; one that is gone is nobody.
function test_ws_customer_identity_build_unknown_and_gone()
{
	$refs = array('contact:12' => workspace_customer_summary_test_ref('contact', 12, lang('Contact'), false));
	$identity = ws_customer_identity_build('contact', 12, 12, array(), $refs);

	pg_assert_same(lang('Contact') . ' #12', $identity['customer']['label'], 'named by kind and number');
	pg_assert_false($identity['customer']['known']);
	pg_assert_same('', $identity['customer']['url'], 'no address to open');
	pg_assert_same('', $identity['customer']['detail'], 'no detail');
	pg_assert_same(array(), $identity['links']);
	pg_assert_same(array(), $identity['user_ids']);

	$nameless = array('contact:12' => workspace_customer_summary_test_ref('contact', 12, '  ', true, '/pg/edit_contact.php?id=12'));

	pg_assert_same(lang('Contact') . ' #12', ws_customer_identity_build('contact', 12, 12, array(), $nameless)['customer']['label'], 'a contact with nothing to call it by');

	pg_assert_same(null, ws_customer_identity_build('contact', 12, 12, array(), array()), 'deleted since it was chosen');
	pg_assert_same(null, ws_customer_identity_build('', 0, 0, array(), array()), 'a channel about nobody');
}

/* ---------------------------------------------------------------------------
   Rights
   --------------------------------------------------------------------------- */

// Each section is opened by the right its own screens ask for.
function test_ws_customer_summary_rights_follow_the_panel()
{
	pg_assert_same(array(
		'orders'       => true,
		'erp_accounts' => true,
		'sales_quotes' => true,
		'cart_quotes'  => true,
		'forms'        => true,
	), ws_customer_summary_rights(workspace_customer_summary_test_viewer()), 'every right');

	pg_assert_same(array(
		'orders'       => false,
		'erp_accounts' => true,
		'sales_quotes' => true,
		'cart_quotes'  => false,
		'forms'        => false,
	), ws_customer_summary_rights(workspace_customer_summary_test_viewer(array('ecommerce' => false, 'forms' => false))), 'an accountant');

	pg_assert_same(array(
		'orders'       => true,
		'erp_accounts' => false,
		'sales_quotes' => false,
		'cart_quotes'  => true,
		'forms'        => true,
	), ws_customer_summary_rights(workspace_customer_summary_test_viewer(array('erp' => false))), 'no ERP right: no balance and no sales quotes');

	pg_assert_same(array_fill_keys(array('orders', 'erp_accounts', 'sales_quotes', 'cart_quotes', 'forms'), false), ws_customer_summary_rights(array('id' => 7, 'role' => 3)), 'nothing given, nothing opened');
}

/* ---------------------------------------------------------------------------
   Sections from rows
   --------------------------------------------------------------------------- */

// Each section laid out from its rows: amounts stay integer minor units and
// are written out by the money helper, orders and balances in the site's own
// currency, sales quotes in their own.
function test_ws_customer_summary_build_lays_out_the_sections()
{
	$rights = ws_customer_summary_rights(workspace_customer_summary_test_viewer());
	$summary = ws_customer_summary_build($rights, workspace_customer_summary_test_data(), 'workspace_customer_summary_test_money', 5);

	pg_assert_same(array('orders', 'erp_accounts', 'sales_quotes', 'forms'), array_keys($summary), 'every section with rows, in order');

	pg_assert_same(7, $summary['orders']['count'], 'how many orders the customer has');
	pg_assert_same(array(
		'id'           => 41,
		'number'       => '1045',
		'status'       => 'complete',
		'status_label' => lang('Complete'),
		'total'        => 125000,
		'total_text'   => '[125000 ]',
		'placed_at'    => 1792580000,
	), $summary['orders']['latest'][0], 'an order');
	pg_assert_same(lang('Cancelled'), $summary['orders']['latest'][1]['status_label']);

	pg_assert_same(array(array(
		'id'           => 9,
		'title'        => 'Acme Ltd',
		'status'       => 'active',
		'balance'      => -45050,
		'balance_text' => '[-45050 ]',
	)), $summary['erp_accounts'], 'a balance in the site\'s own currency, the sign kept');

	pg_assert_same(3, $summary['sales_quotes']['count']);
	pg_assert_same(array(
		'id'            => 12,
		'number'        => 'PGFT2026000012',
		'account_id'    => 9,
		'account_title' => 'Acme Ltd',
		'issue_date'    => '2026-10-20',
		'valid_until'   => '2026-11-19',
		'currency'      => 'EUR',
		'total'         => 300000,
		'total_text'    => '[300000 EUR]',
	), $summary['sales_quotes']['latest'][0], 'a sales quote in its own currency');
	pg_assert_same('', $summary['sales_quotes']['latest'][1]['valid_until'], 'no date it runs out on');

	pg_assert_same(array(
		array('id' => 730, 'name' => 'Contact us', 'reference_code' => 'AB12CD34EF', 'complete' => true, 'submitted_at' => 1792500000),
		array('id' => 728, 'name' => lang('Form'), 'reference_code' => 'QQ11WW22EE', 'complete' => false, 'submitted_at' => 1792300000),
	), $summary['forms'], 'a form in a folder the reader may not edit is left out');
}

// A section the reader holds no right to is not shown, whatever its rows;
// nor is one with nothing in it.
function test_ws_customer_summary_build_leaves_out_what_is_not_allowed_or_empty()
{
	$data = workspace_customer_summary_test_data();
	$none = array_fill_keys(array('orders', 'erp_accounts', 'sales_quotes', 'cart_quotes', 'forms'), false);

	pg_assert_same(array(), ws_customer_summary_build($none, $data, 'workspace_customer_summary_test_money'), 'no right, no section');

	$rights = ws_customer_summary_rights(workspace_customer_summary_test_viewer(array('erp' => false)));

	pg_assert_same(array('orders', 'forms'), array_keys(ws_customer_summary_build($rights, $data, 'workspace_customer_summary_test_money')), 'the ERP sections need the ERP right');

	$all = ws_customer_summary_rights(workspace_customer_summary_test_viewer());
	$empty = array(
		'orders'       => array('count' => 0, 'rows' => array()),
		'erp_accounts' => array(),
		'sales_quotes' => array('count' => 0, 'rows' => array()),
		'cart_quotes'  => array('count' => 0, 'rows' => array()),
		'forms'        => array(array('id' => '1', 'reference_code' => 'A', 'submitted_timestamp' => '1', 'complete' => '1', 'form_name' => 'x', 'open' => false)),
	);

	pg_assert_same(array(), ws_customer_summary_build($all, $empty, 'workspace_customer_summary_test_money'), 'empty sections are left out, and so are forms the reader may not open');
	pg_assert_same(array(), ws_customer_summary_build($all, array(), 'workspace_customer_summary_test_money'), 'nothing read');
}

// Only the latest few rows are shown; the count still says how many there are.
function test_ws_customer_summary_build_keeps_the_latest_few()
{
	$data = workspace_customer_summary_test_data();
	$data['orders']['rows'][] = array('id' => '30', 'order_number' => '1020', 'status' => 'exported', 'total' => '100', 'order_date' => '1792300000');
	$summary = ws_customer_summary_build(ws_customer_summary_rights(workspace_customer_summary_test_viewer()), $data, 'workspace_customer_summary_test_money', 2);

	pg_assert_same(2, count($summary['orders']['latest']));
	pg_assert_same(7, $summary['orders']['count']);
	pg_assert_same(2, count($summary['sales_quotes']['latest']));
	pg_assert_same(array(730, 728), array_column($summary['forms'], 'id'), 'the latest two the reader may open');

	$data['forms'][] = array('id' => '700', 'reference_code' => 'OLD', 'submitted_timestamp' => '1', 'complete' => '1', 'form_name' => 'x', 'open' => true);

	pg_assert_same(2, count(ws_customer_summary_build(ws_customer_summary_rights(workspace_customer_summary_test_viewer()), $data, 'workspace_customer_summary_test_money', 2)['forms']), 'no more than the limit');

	$data['orders']['count'] = 0;

	pg_assert_same(2, ws_customer_summary_build(ws_customer_summary_rights(workspace_customer_summary_test_viewer()), $data, 'workspace_customer_summary_test_money', 2)['orders']['count'], 'never fewer than the rows shown');
}

// Cart quotes have no table yet: their reader reads nothing and the summary
// shows no such section, never sales quotes in their place.
function test_ws_customer_summary_cart_quotes_are_a_seam()
{
	pg_assert_same(array('count' => 0, 'rows' => array()), ws_customer_cart_quote_rows(array('contact_id' => 5, 'user_ids' => array(3), 'erp_account_ids' => array(9)), 5), 'nothing to read');

	$data = workspace_customer_summary_test_data();
	$data['cart_quotes'] = array('count' => 2, 'rows' => array(array('id' => 1)));
	$summary = ws_customer_summary_build(ws_customer_summary_rights(workspace_customer_summary_test_viewer()), $data, 'workspace_customer_summary_test_money');

	pg_assert_false(isset($summary['cart_quotes']), 'not laid out before their tables exist');
	pg_assert_contains("if (\$rights['cart_quotes'])", workspace_customer_summary_test_source('ws_customer_summary'), 'the seam asks the ecommerce right');
}

// Amounts from the database come as strings; they are whole minor units,
// rounded and never cut.
function test_ws_customer_minor_rounds()
{
	pg_assert_same(1999, ws_customer_minor('1999'));
	pg_assert_same(-250, ws_customer_minor('-250'));
	pg_assert_same(2000, ws_customer_minor(1999.5), 'rounded, not cut');
	pg_assert_same(12, ws_customer_minor('12.4'));
	pg_assert_same(0, ws_customer_minor(null));
	pg_assert_same(0, ws_customer_minor(''));
}

// The site's own currency is written as the order screens write a total
// (prepare_amount()), its symbol as text; any other currency goes to the
// ERP's helper, as the quote screens write it.
function test_ws_customer_money_text()
{
	if (!defined('BASE_CURRENCY_SYMBOL')) {
		define('BASE_CURRENCY_SYMBOL', '$');
	}

	$symbol = html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	pg_assert_same($symbol . pg_format_number(1234.56, 2), ws_customer_money_text(123456), 'minor units written as the amount');
	pg_assert_same('-' . $symbol . pg_format_number(2.5, 2), ws_customer_money_text('-250', ''), 'a negative amount');
	pg_assert_same($symbol . pg_format_number(0, 2), ws_customer_money_text(0), 'nothing');

	$source = workspace_customer_summary_test_source('ws_customer_money_text');

	pg_assert_contains('prepare_amount(', $source, 'the order screens\' helper');
	pg_assert_contains('erp_money_out_currency(', $source, 'the ERP\'s helper for another currency');
	pg_assert_contains('html_entity_decode(', $source, 'the symbol as text');
}

// The orders of a customer: by its contact, the users tied to it and, where
// the column is there, its current accounts.
function test_ws_customer_order_where()
{
	$identity = array('contact_id' => 5, 'user_ids' => array(3, 4), 'erp_account_ids' => array(9));

	pg_assert_same("contact_id = '5' OR user_id IN ('3','4') OR erp_account_id IN ('9')", ws_customer_order_where($identity, true));
	pg_assert_same("contact_id = '5' OR user_id IN ('3','4')", ws_customer_order_where($identity, false), 'no erp_account_id column yet');
	pg_assert_same("erp_account_id IN ('9')", ws_customer_order_where(array('contact_id' => 0, 'user_ids' => array(), 'erp_account_ids' => array('9')), true), 'an account alone');
	pg_assert_same('', ws_customer_order_where(array('contact_id' => 0, 'user_ids' => array(0), 'erp_account_ids' => array()), true), 'nobody to read by: no query');
}

// Nothing is read for a section the reader may not see, and the readers stay
// read-only: the accountant's read-only ERP right reads the ERP sections.
function test_ws_customer_summary_reads_only_what_the_rights_open()
{
	$source = workspace_customer_summary_test_source('ws_customer_summary');

	foreach (array(
		'orders'       => 'ws_customer_order_rows(',
		'erp_accounts' => 'ws_customer_erp_account_rows(',
		'sales_quotes' => 'ws_customer_sales_quote_rows(',
		'cart_quotes'  => 'ws_customer_cart_quote_rows(',
		'forms'        => 'ws_customer_form_rows(',
	) as $section => $reader) {
		$gate = strpos($source, "if (\$rights['" . $section . "'])");

		pg_assert_true($gate !== false, $section . ' asks its right');
		pg_assert_true(strpos($source, $reader) > $gate, $reader . ' is called behind it');
	}

	pg_assert_contains('ws_customer_summary_build($rights, $data', $source, 'the same rights lay it out');
	pg_assert_contains("erp_quote_rows(array('state' => 'open'", workspace_customer_summary_test_source('ws_customer_sales_quote_rows'), 'open sales quotes, from the ERP\'s reader');
	pg_assert_contains('ws_viewer_folder_open($viewer', workspace_customer_summary_test_source('ws_customer_form_rows'), 'a form is opened as the panel opens it');

	foreach (array('ws_customer_identity', 'ws_customer_summary', 'ws_customer_order_rows', 'ws_customer_erp_account_rows', 'ws_customer_sales_quote_rows', 'ws_customer_form_rows') as $function) {
		$body = workspace_customer_summary_test_source($function);

		pg_assert_false((bool) preg_match('/\b(UPDATE|INSERT|DELETE|REPLACE)\s/', $body), $function . ' writes nothing');
		pg_assert_true(strpos($body, 'manage_erp_readonly') === false, $function . ' does not shut out the read-only right');
	}
}

/* ---------------------------------------------------------------------------
   The route
   --------------------------------------------------------------------------- */

// GET /workspace/channels/{id}/customer: a read with the workspace scope,
// declared with its object, offered to MCP clients as the context is.
function test_ws_api_channel_customer_route()
{
	$route = null;

	foreach (ws_api_routes() as $row) {
		if ($row['id'] === 'workspace.channels.customer') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'declared');

	if (!is_array($route)) {
		return;
	}

	pg_assert_same('GET', $route['method']);
	pg_assert_same('/workspace/channels/{id}/customer', $route['path']);
	pg_assert_same('workspace:read', $route['scope']);
	pg_assert_same('ws_api_channel_customer', $route['handler']);
	pg_assert_same('WorkspaceChannelCustomer', $route['returns']);
	pg_assert_false(isset($route['mcp']), 'a read: no MCP exception');
	pg_assert_true(pg_mcp_route_is_offered($route), 'a tool, as the channel context');
	pg_assert_false(!empty($route['dry_run']), 'nothing to rehearse');
	pg_assert_same(array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)), $route['params']);
}

// Every object the answer is built of is registered, and each presenter
// answers with exactly the fields its declaration names.
function test_ws_api_channel_customer_objects()
{
	$objects = ws_openapi_objects();
	$pairs = array(
		'WorkspaceChannelCustomer' => array('ws_api_channel_customer_schema', 'ws_api_channel_customer_present'),
		'WorkspaceCustomerRecord'  => array('ws_api_customer_record_schema', 'ws_api_customer_record_present'),
		'WorkspaceCustomerOrders'  => array('ws_api_customer_orders_schema', 'ws_api_customer_orders_present'),
		'WorkspaceCustomerOrder'   => array('ws_api_customer_order_schema', 'ws_api_customer_order_present'),
		'WorkspaceCustomerAccount' => array('ws_api_customer_account_schema', 'ws_api_customer_account_present'),
		'WorkspaceCustomerQuotes'  => array('ws_api_customer_quotes_schema', 'ws_api_customer_quotes_present'),
		'WorkspaceCustomerQuote'   => array('ws_api_customer_quote_schema', 'ws_api_customer_quote_present'),
		'WorkspaceCustomerForm'    => array('ws_api_customer_form_schema', 'ws_api_customer_form_present'),
	);

	foreach ($pairs as $name => $functions) {
		pg_assert_same($functions[0], $objects[$name] ?? '', $name . ' is registered');
		pg_assert_true(function_exists($functions[0]) && function_exists($functions[1]), $name . ' has its declaration and presenter');
	}

	$rights = ws_customer_summary_rights(workspace_customer_summary_test_viewer());
	$summary = ws_customer_summary_build($rights, workspace_customer_summary_test_data(), 'workspace_customer_summary_test_money');
	$identity = ws_customer_identity_build('contact', 5, 5, array(array('sigil' => '#', 'type' => 'user_account', 'id' => 3)), array(
		'contact:5'      => workspace_customer_summary_test_ref('contact', 5, 'Ayşe Demir', true, '/pg/edit_contact.php?id=5'),
		'user_account:3' => workspace_customer_summary_test_ref('user_account', 3, 'ayse', true, '/pg/edit_user.php?id=3'),
	));

	$answer = ws_api_channel_customer_present(array('id' => 30), $identity, $summary, 'https://example.com');

	pg_assert_same(array_keys(ws_api_channel_customer_schema()), array_keys($answer), 'the answer');
	pg_assert_same(array_keys(ws_api_customer_record_schema()), array_keys($answer['customer']), 'the customer');
	pg_assert_same(array_keys(ws_api_customer_orders_schema()), array_keys($answer['orders']), 'the orders');
	pg_assert_same(array_keys(ws_api_customer_order_schema()), array_keys($answer['orders']['latest'][0]), 'an order');
	pg_assert_same(array_keys(ws_api_customer_account_schema()), array_keys($answer['erp_accounts'][0]), 'an account');
	pg_assert_same(array_keys(ws_api_customer_quotes_schema()), array_keys($answer['sales_quotes']), 'the sales quotes');
	pg_assert_same(array_keys(ws_api_customer_quote_schema()), array_keys($answer['sales_quotes']['latest'][0]), 'a sales quote');
	pg_assert_same(array_keys(ws_api_customer_form_schema()), array_keys($answer['forms'][0]), 'a form');

	pg_assert_same(30, $answer['channel_id']);
	pg_assert_same('https://example.com/pg/edit_contact.php?id=5', $answer['customer']['url'], 'a panel address made whole');
	pg_assert_same('2026-10-21T10:53:20Z', $answer['orders']['latest'][0]['placed_at'], 'times as ISO-8601');
	pg_assert_same(125000, $answer['orders']['latest'][0]['total'], 'money in minor units');
	pg_assert_same(null, $answer['sales_quotes']['latest'][1]['valid_until'], 'no date it runs out on');
	pg_assert_same(1, count($answer['links']));
}

// A channel about nobody, and sections left out, answer null; the links are
// an empty list.
function test_ws_api_channel_customer_present_nulls()
{
	$answer = ws_api_channel_customer_present(array('id' => 30), null, array(), 'https://example.com');

	pg_assert_same(array(
		'channel_id'   => 30,
		'customer'     => null,
		'links'        => array(),
		'orders'       => null,
		'erp_accounts' => null,
		'sales_quotes' => null,
		'forms'        => null,
	), $answer);

	$unknown = ws_api_customer_record_present(array('type' => 'contact', 'id' => 12, 'kind' => 'Contact', 'label' => 'Contact #12', 'detail' => '', 'known' => false, 'url' => ''), 'https://example.com');

	pg_assert_same(null, $unknown['url'], 'nothing to open');
}

// The route reads its channel as the channel context does: Claude's
// application through the person who asked where its owner is not in,
// everybody else by the owner with the 404 of every channel route.
function test_ws_api_channel_customer_reads_the_channel_as_the_context()
{
	$customer = workspace_customer_summary_test_source('ws_api_channel_customer');
	$context = workspace_customer_summary_test_source('ws_api_channel_context');

	pg_assert_contains("ws_api_assistant_channel_reader(\$params['id'])", $customer, 'the customer route');
	pg_assert_contains("ws_api_assistant_channel_reader(\$params['id'])", $context, 'the channel context');

	$reader = workspace_customer_summary_test_source('ws_api_assistant_channel_reader');

	pg_assert_contains('ws_api_is_claude() && ws_claude_channel_allowed($channel) && !ws_app_reads_channel($owner, $channel)', $reader);
	pg_assert_true(strpos($reader, 'ws_api_channel_or_404($viewer, $id)') > strpos($reader, 'ws_claude_channel_asker($channel)'), 'otherwise the ordinary check');

	pg_assert_contains('ws_api_customer_viewer($viewer, api_current_scopes())', $customer, 'narrowed to the key\'s scopes');
	pg_assert_true(strpos($customer, 'ws_customer_identity(') > strpos($customer, 'ws_api_customer_viewer('), 'the customer is named with the narrowed reader');
	pg_assert_true(strpos($customer, 'ws_customer_summary(') > strpos($customer, 'ws_api_customer_viewer('), 'the sections are read with it');
}

// The key reads a section only with the scope the API reads that kind of
// record with elsewhere, as the assistants do (ws_ai_type_scope()).
function test_ws_api_customer_viewer_narrows_to_the_scopes()
{
	$scopes = ws_api_customer_scopes();
	$search = ws_api_search_record_scopes();

	pg_assert_same($search['order'], $scopes['ecommerce'], 'orders');
	pg_assert_same($search['contact'], $scopes['contacts'], 'contacts');
	pg_assert_same($search['erp_account'], $scopes['erp'], 'current accounts');
	pg_assert_same($search['erp_quote'], $scopes['erp'], 'sales quotes');
	pg_assert_same($search['receipt'], $scopes['erp_cash'], 'cash');
	pg_assert_same($search['form'], $scopes['forms'], 'forms');

	$viewer = workspace_customer_summary_test_viewer();
	$narrow = ws_api_customer_viewer($viewer, array('workspace:read', 'orders:read'));

	pg_assert_true($narrow['ecommerce']);
	pg_assert_false($narrow['contacts']);
	pg_assert_false($narrow['erp']);
	pg_assert_false($narrow['erp_cash']);
	pg_assert_false($narrow['forms']);
	pg_assert_same($viewer['id'], $narrow['id'], 'the same person');
	pg_assert_same(true, $narrow['calendars'], 'rights the summary does not read are left alone');

	$wide = ws_api_customer_viewer(workspace_customer_summary_test_viewer(array('erp' => false)), array('workspace:read', 'erp:read', 'forms:read'));

	pg_assert_false($wide['erp'], 'a scope never opens what the person may not see');
	pg_assert_true($wide['forms']);
	pg_assert_same(array('orders', 'cart_quotes', 'forms'), array_keys(array_filter(ws_customer_summary_rights(ws_api_customer_viewer($viewer, array('orders:read', 'forms:read'))))), 'the sections follow the narrowed rights');
}
