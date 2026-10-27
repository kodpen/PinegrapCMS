<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP module's route table in the external API
 * (includes/erp/api.php) that need no database: every route reaches a
 * handler, no two routes answer the same method on the same path, the writes
 * on invoices, quotes, delivery notes, stock, transfers and cheques can be
 * rehearsed with X-Dry-Run, money moves only with the cash scope, the
 * helpers that read a typed document's lines (includes/erp/api_documents.php,
 * api_stock.php), the cash forecast's shape (api_reports.php), and whose
 * name a collection's bell row carries (includes/erp/alerts.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/alerts.php');

function test_erp_api_routes_reach_a_handler()
{
	$ids = array();

	foreach (erp_api_routes() as $route) {
		pg_assert_false(isset($ids[$route['id']]), $route['id'] . ' is declared once');
		$ids[$route['id']] = true;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_true(in_array($route['scope'], array('erp:read', 'erp:write', 'erp_cash:read', 'erp_cash:write'), true), $route['id'] . ' asks for one of the module\'s scopes');
	}
}

// The router takes the first route that matches, so a method a route accepts
// beside its own must not be one another route on the same path answers.
function test_erp_api_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (erp_api_routes() as $route) {
		$methods = array_merge(array($route['method']), (array) ($route['also_accepts'] ?? array()));

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			$seen[$key] = $route['id'];
		}
	}
}

// Issuing, cancelling, returning, e-mailing and applying a count cannot be
// taken back, so every write on a document or on stock can be tried first.
function test_erp_api_document_writes_can_be_rehearsed()
{
	foreach (erp_api_routes() as $route) {
		if (($route['method'] === 'GET') || !preg_match('/^erp\.(invoices|invoice_recurrences|quotes|orders|waybills|stock_counts|stock)\./', $route['id'])) {
			continue;
		}

		pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		pg_assert_same('erp:write', $route['scope'], $route['id'] . ' writes with erp:write');
	}
}

function test_erp_api_line_number_reads_numbers_only()
{
	pg_assert_same(2.0, erp_api_line_number(2));
	pg_assert_same(1.5, erp_api_line_number(1.5));
	pg_assert_same(1.25, erp_api_line_number('1.25'));
	pg_assert_same(3.0, erp_api_line_number(' 3 '));
	pg_assert_same(null, erp_api_line_number('1,5'));
	pg_assert_same(null, erp_api_line_number('abc'));
	pg_assert_same(null, erp_api_line_number(true));
	pg_assert_same(null, erp_api_line_number(array(1)));
}

// A field the ERP refused is named the way the API's caller sent it.
function test_erp_api_document_field_names_the_api_field()
{
	pg_assert_same('lines[2].unit', erp_api_document_field('lines[2][unit_code]'));
	pg_assert_same('lines[0].discount_rate', erp_api_document_field('lines[0][offer_discount_rate]'));
	pg_assert_same('lines[0].quantity', erp_api_document_field('lines[0][quantity]'));
	pg_assert_same('valid_until', erp_api_document_field('due_date', array('due_date' => 'valid_until')));
	pg_assert_same('account_id', erp_api_document_field('account_id'));
	pg_assert_same(null, erp_api_document_field('_error'));
	pg_assert_same(null, erp_api_document_field(''));
}

// A refusal of the note's header is answered at the parameter it is about.
function test_erp_api_waybill_field_names_the_parameter()
{
	pg_assert_same('ship_time', erp_api_waybill_field(lang('Please enter the shipment time as HH:MM.')));
	pg_assert_same('carrier_tax_number', erp_api_waybill_field(lang('The carrier tax number has to be 10 or 11 digits.')));
	pg_assert_same('carrier_tax_number', erp_api_waybill_field(lang('The carrier tax number:') . ' x'));
	pg_assert_same('driver_id_number', erp_api_waybill_field(lang('The driver ID number has to be 11 digits.')));
	pg_assert_same('account_id', erp_api_waybill_field(lang('Choose an account.')));
	pg_assert_same(null, erp_api_waybill_field('Something else went wrong.'));
}

// Every header parameter the handler reads is one the route declares.
function test_erp_api_waybill_create_declares_its_header()
{
	$declared = array();

	foreach (erp_api_routes() as $route) {
		if ($route['id'] === 'erp.waybills.create') {
			foreach ($route['params'] as $param) {
				$declared[$param['name']] = true;
			}
		}
	}

	foreach (array_keys(erp_api_waybill_header_params()) as $name) {
		pg_assert_true(isset($declared[$name]), $name . ' is a parameter of erp.waybills.create');
	}
}

// Every write that moves a till can be tried first, and needs the cash
// scope rather than the ledger's.
function test_erp_api_cash_writes_can_be_rehearsed()
{
	$found = 0;

	foreach (erp_api_routes() as $route) {
		if (($route['method'] === 'GET') || !preg_match('/^erp\.(cash_transfers|cheques)\./', $route['id'])) {
			continue;
		}

		$found++;
		pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		pg_assert_same('erp_cash:write', $route['scope'], $route['id'] . ' writes with erp_cash:write');
	}

	pg_assert_same(3, $found, 'transfers, cheques and cheque steps are written');
}

// The steps the endpoint offers are the steps the cheque register knows.
function test_erp_api_cheque_steps_match_the_register()
{
	$offered = array();

	foreach (erp_api_routes() as $route) {
		if ($route['id'] === 'erp.cheques.step') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'step') {
					$offered = $param['values'];
				}
			}
		}
	}

	$known = array_keys(erp_cheque_step_from());
	sort($offered);
	sort($known);

	pg_assert_same($known, $offered, 'erp.cheques.step offers every step and no other');
}

// The till figures are read with the cash scope, as the panel keeps them
// behind the cash right; the ledger reports with the ledger's.
function test_erp_api_reports_read_with_their_scope()
{
	$scopes = array();

	foreach (erp_api_routes() as $route) {
		$scopes[$route['id']] = $route['scope'];
	}

	pg_assert_same('erp:read', $scopes['erp.reports.profit'] ?? '', 'the profit report reads the ledger');
	pg_assert_same('erp_cash:read', $scopes['erp.reports.cashflow'] ?? '', 'the cash flow reads the tills');
	pg_assert_same('erp_cash:read', $scopes['erp.reports.cash_forecast'] ?? '', 'the cash forecast reads the tills');
	pg_assert_same('erp:read', $scopes['erp.dashboard'] ?? '', 'the dashboard reads the ledger');
}

// The late fee is read with the ledger's scope; its invoice draft is written
// with it and can be tried first, like every other invoice write.
function test_erp_api_late_fee_routes()
{
	$routes = array();

	foreach (erp_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same('GET /erp/accounts/{id}/late-fee', ($routes['erp.accounts.late_fee']['method'] ?? '') . ' ' . ($routes['erp.accounts.late_fee']['path'] ?? ''), 'the fee is read');
	pg_assert_same('erp:read', $routes['erp.accounts.late_fee']['scope'] ?? '', 'with erp:read');

	$write = $routes['erp.accounts.late_fee_invoice'] ?? array();

	pg_assert_same('POST /erp/accounts/{id}/late-fee-invoice', ($write['method'] ?? '') . ' ' . ($write['path'] ?? ''), 'the draft is written');
	pg_assert_same('erp:write', $write['scope'] ?? '', 'with erp:write');
	pg_assert_true(!empty($write['dry_run']), 'and takes X-Dry-Run');
	pg_assert_same('ErpInvoice', $write['returns'] ?? '', 'the answer is the draft');
}

// The forecast in the API: one amount per column for each kind, in the
// columns' order, and the items cut to the limit while the count says how
// many there are.
function test_erp_api_cash_forecast_present()
{
	$forecast = erp_cash_forecast_build(array(
		array('kind' => 'sales_invoice', 'date' => '2026-10-01', 'amount' => 10000, 'title' => 'PGF2026000001', 'party' => 'Acme', 'source' => 'invoice', 'source_id' => 7, 'account_id' => 3),
		array('kind' => 'expense', 'date' => '2026-11-20', 'amount' => 3000, 'title' => 'Rent', 'party' => '', 'source' => 'expense', 'source_id' => 2, 'account_id' => 0),
		array('kind' => 'cheque_given', 'date' => '2026-10-20', 'amount' => 4000, 'title' => 'Cheque', 'party' => 'Supplier', 'source' => 'cheque', 'source_id' => 5, 'account_id' => 9),
	), '2026-10-13', 60, 20000);
	$forecast['horizon'] = 60;
	$forecast['today'] = '2026-10-13';
	$forecast['unpriced'] = 1;
	$forecast['foreign_tills'] = 0;
	$forecast['cheque_tills'] = 2;

	$out = erp_api_cash_forecast_present($forecast, 2);

	pg_assert_same(array('overdue', 'd30', 'd60'), array_column($out['columns'], 'key'), 'columns in order');
	pg_assert_same(null, $out['columns'][0]['start'], 'past due has no first day');
	pg_assert_same('2026-11-12', $out['columns'][2]['start'], '31-60 starts on its day');
	pg_assert_same(array(30000, 26000, 23000), array_column($out['columns'], 'position'), 'balance after each column');
	pg_assert_same(23000, $out['position'], 'expected balance');
	pg_assert_same(10000, $out['in'], 'money in');
	pg_assert_same(7000, $out['out'], 'money out');

	$kinds = array_column($out['kinds'], null, 'kind');

	pg_assert_same(count(erp_cash_forecast_kinds()), count($out['kinds']), 'every kind is listed');
	pg_assert_same(array(10000, 0, 0), $kinds['sales_invoice']['amounts'], 'sales invoices by column');
	pg_assert_same(array(0, 4000, 0), $kinds['cheque_given']['amounts'], 'cheques given by column');
	pg_assert_same('out', $kinds['expense']['direction'], 'an expense goes out');

	pg_assert_same(2, count($out['items']), 'items cut to the limit');
	pg_assert_same(3, $out['items_count'], 'the count says how many there are');
	pg_assert_same(array('invoice', 7, 3, 'overdue'), array($out['items'][0]['source'], $out['items'][0]['source_id'], $out['items'][0]['account_id'], $out['items'][0]['column']), 'the first item names its record');
	pg_assert_same(array(1, 2), array($out['unpriced'], $out['cheque_tills']), 'what was left out');
}

// A collection's bell row names who recorded it, and is read already for
// them: the person signed in on the panel, or, for a call through the API
// (which loads no panel user), the application's owner - the person on the
// device or behind the OAuth connection, the owner of an application key.
function test_erp_alert_actor()
{
	$app = array('id' => 3, 'name' => 'Muhasebe', 'owner' => array('id' => 7, 'username' => 'mehmet'));

	pg_assert_same(array('id' => 4, 'username' => 'ayse'), erp_alert_actor('4', 'ayse', null), 'the panel: the person signed in');
	pg_assert_same(array('id' => 7, 'username' => 'mehmet'), erp_alert_actor('', '', $app), 'the API: the application\'s owner');
	pg_assert_same(array('id' => 7, 'username' => 'mehmet'), erp_alert_actor(0, '', $app), 'no panel user at all');
	pg_assert_same(array('id' => 4, 'username' => 'ayse'), erp_alert_actor(4, 'ayse', $app), 'a panel user stands');
	pg_assert_same(array('id' => 0, 'username' => ''), erp_alert_actor('', '', array('id' => 3)), 'a call refused before its owner was loaded');
	pg_assert_same(array('id' => 0, 'username' => ''), erp_alert_actor('', '', null), 'a scheduled job');
}

// The collection notice takes both its name and its reader from
// erp_alert_actor(), not from the panel's constants, which are empty on a
// call through the API or the MCP server.
function test_erp_alert_rows_carry_the_actor()
{
	$parts = preg_split('/^function\s+(\w+)\s*\(/m', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/alerts.php'), -1, PREG_SPLIT_DELIM_CAPTURE);
	$bodies = array();

	for ($i = 1; $i < count($parts); $i += 2) {
		$bodies[$parts[$i]] = $parts[$i + 1];
	}

	foreach (array('erp_alert_create', 'erp_alert_event') as $function) {
		pg_assert_contains('erp_alert_current_actor()', $bodies[$function] ?? '', $function . '() asks erp_alert_current_actor()');
		pg_assert_false(strpos($bodies[$function] ?? '', 'USER_ID') !== false || strpos($bodies[$function] ?? '', 'USER_USERNAME') !== false, $function . '() does not read the panel\'s constants itself');
	}
}

// Opening and changing an account card can be tried first: every check runs
// (the tax number, a customer already linked, the period lock on the opening
// balance), and the rehearsal stops before the card, the link or the opening
// entry is written.
function test_erp_api_account_writes_can_be_rehearsed()
{
	$routes = array();

	foreach (erp_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('erp.accounts.create' => 'erp_api_accounts_create', 'erp.accounts.update' => 'erp_api_accounts_update') as $id => $handler) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes X-Dry-Run');

		$function = new ReflectionFunction($handler);
		$lines = file($function->getFileName());
		$source = preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
		$stop = strpos($source, 'api_dry_run_stop(');

		pg_assert_true($stop !== false, $handler . ' stops a rehearsal');
		pg_assert_true($stop < strpos($source, 'erp_account_save('), $handler . ' stops before the card is written');
		pg_assert_true($stop > strpos($source, "'tax_number')"), $handler . ' checks the tax number first');

		if (strpos($source, 'erp_account_link_contact(') !== false) {
			pg_assert_true($stop < strpos($source, 'erp_account_link_contact('), $handler . ' stops before the link');
		}
	}

	$create = (string) file_get_contents((new ReflectionFunction('erp_api_accounts_create'))->getFileName());
	$create = substr($create, strpos($create, 'function erp_api_accounts_create('));
	$create = substr($create, 0, strpos($create, 'erp_account_save('));

	pg_assert_contains('erp_lock_refusal(', $create, 'the opening date is checked against the lock before anything is written');
}

// Recording and cancelling a receipt can be tried first: the panel's receipt
// functions keep their checks in erp_post_receipt_check() and
// erp_receipt_cancel_check(), the endpoints call the same checks and stop
// the rehearsal before the till or the account is written.
function test_erp_api_receipt_writes_can_be_rehearsed()
{
	$routes = array();

	foreach (erp_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$source = function ($handler) {
		$function = new ReflectionFunction($handler);
		$lines = file($function->getFileName());

		return preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
	};

	foreach (array(
		'erp.receipts.create' => array('erp_api_receipts_create', 'erp_post_receipt_check(', 'erp_post_receipt('),
		'erp.receipts.cancel' => array('erp_api_receipts_cancel', 'erp_receipt_cancel_check(', 'erp_receipt_cancel('),
	) as $id => $expect) {
		list($handler, $check, $write) = $expect;
		$code = $source($handler);
		$stop = strpos($code, 'api_dry_run_stop(');

		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes X-Dry-Run');
		pg_assert_true($stop !== false, $handler . ' stops a rehearsal');
		pg_assert_true((strpos($code, $check) !== false) && (strpos($code, $check) < $stop), $handler . ' runs ' . $check . ') first');
		pg_assert_true($stop < strpos($code, $write), $handler . ' stops before the write');
	}

	pg_assert_contains('erp_post_receipt_check(', $source('erp_post_receipt'), 'the panel meets the same checks');
	pg_assert_contains('erp_receipt_cancel_check(', $source('erp_receipt_cancel'), 'the panel meets the same checks');
	pg_assert_contains('erp_receipt_invoice_refusal(', $source('erp_post_receipt_check'), 'the invoice is checked before the transaction');
	pg_assert_contains('erp_receipt_invoice_refusal(', $source('erp_post_receipt'), 'and again inside it');

	pg_assert_same(lang('Enter an amount greater than zero.'), erp_post_receipt_check(array('amount' => 0, 'account_id' => 1, 'cash_account_id' => 1)));
	pg_assert_same(lang('Choose an account.'), erp_post_receipt_check(array('amount' => 100, 'account_id' => 0, 'cash_account_id' => 1)));
}

// Recording, changing, paying and cancelling an expense and keeping its
// receipt file can be tried first: the panel's expense functions keep their
// checks in erp_expense_save_check(), erp_expense_pay_check(),
// erp_expense_cancel_check() and erp_expense_receipt_check(), the endpoints
// call the same checks and stop the rehearsal before the expense, the till
// or the file is written.
function test_erp_api_expense_writes_can_be_rehearsed()
{
	$routes = array();

	foreach (erp_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$source = function ($handler) {
		$function = new ReflectionFunction($handler);
		$lines = file($function->getFileName());

		return preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
	};

	foreach (array(
		'erp.expenses.create' => array('erp_api_expenses_create', 'erp_expense_save_check(', 'erp_expense_save('),
		'erp.expenses.update' => array('erp_api_expenses_update', 'erp_expense_save_check(', 'erp_expense_save('),
		'erp.expenses.pay' => array('erp_api_expenses_pay', 'erp_expense_pay_check(', 'erp_expense_pay('),
		'erp.expenses.cancel' => array('erp_api_expenses_cancel', 'erp_expense_cancel_check(', 'erp_expense_cancel('),
		'erp.expenses.receipt_put' => array('erp_api_expenses_receipt_put', 'erp_expense_receipt_check(', 'erp_expense_keep_receipt('),
	) as $id => $expect) {
		list($handler, $check, $write) = $expect;
		$code = $source($handler);
		$stop = strpos($code, 'api_dry_run_stop(');

		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes X-Dry-Run');
		pg_assert_true($stop !== false, $handler . ' stops a rehearsal');
		pg_assert_true((strpos($code, $check) !== false) && (strpos($code, $check) < $stop), $handler . ' runs ' . $check . ') first');
		pg_assert_true($stop < strpos($code, $write), $handler . ' stops before the write');
	}

	pg_assert_contains('erp_expense_save_check(', $source('erp_expense_save'), 'the panel meets the same checks');
	pg_assert_contains('erp_expense_pay_check(', $source('erp_expense_pay'), 'the panel meets the same checks');
	pg_assert_contains('erp_expense_cancel_check(', $source('erp_expense_cancel'), 'the panel meets the same checks');
	pg_assert_contains('erp_expense_receipt_check(', $source('erp_expense_keep_receipt'), 'the panel meets the same checks');
	pg_assert_contains('erp_expense_payment_refusal(', $source('erp_expense_save_check'), 'paying on the spot is checked before the expense is written');
	pg_assert_contains('erp_expense_payment_refusal(', $source('erp_expense_pay_check'), 'a payment is checked before the transaction');
	pg_assert_contains('erp_expense_payment_refusal(', $source('erp_expense_post_payment'), 'and again inside it');

	pg_assert_same(lang('Choose a payment method.'), erp_expense_payment_refusal(array('expense_date' => '2026-01-01', 'currency' => 'TRY'), array('payment_method' => 'barter', 'cash_account_id' => 1)));
}
