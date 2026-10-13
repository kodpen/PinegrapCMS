<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP module's route table in the external API
 * (includes/erp/api.php) that need no database: every route reaches a
 * handler, no two routes answer the same method on the same path, the writes
 * on invoices and quotes can be rehearsed with X-Dry-Run, and the helpers
 * that read a typed document's lines (includes/erp/api_documents.php).
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

// Issuing, cancelling, returning and e-mailing cannot be taken back, so every
// write on an invoice or a quote can be tried first.
function test_erp_api_document_writes_can_be_rehearsed()
{
	foreach (erp_api_routes() as $route) {
		if (($route['method'] === 'GET') || !preg_match('/^erp\.(invoices|invoice_recurrences|quotes|orders)\./', $route['id'])) {
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
