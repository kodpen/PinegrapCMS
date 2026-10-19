<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's order documents in
 * includes/api/resources/orders.php: the shape of a kept legal document, the
 * copy that was stored without acceptance, a type the product does not label
 * yet, and the route that carries them under the orders read scope.
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

// orders.php pulls in the outbound webhook module, whose gate asks for
// init.php. Behind the gate both files only declare functions (no query, no
// configuration read at load), so the gate is answered here: nothing under
// tests/ calls the outbound HTTP client.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape, so the
// presenter can be read without it.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

// A row as the documents endpoint reads it from order_documents.
function api_order_documents_test_row($changes = array())
{
	return array_merge(array(
		'id' => '7', 'type' => 'contract', 'html' => '<h1>Mesafeli Satış Sözleşmesi</h1><p>Madde 1</p>',
		'accepted_at' => '1760000000', 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0', 'created_at' => '1760000000',
	), $changes);
}

function api_order_documents_test_types()
{
	return array('preinfo' => 'Pre-Information Form', 'contract' => 'Distance Sales Contract');
}

function test_api_order_documents_accepted_copy()
{
	$document = api_order_document_present(api_order_documents_test_row(), api_order_documents_test_types());

	pg_assert_same(7, $document['id']);
	pg_assert_same('contract', $document['type']);
	pg_assert_same('Distance Sales Contract', $document['label']);
	pg_assert_same(true, $document['accepted']);
	pg_assert_same('2025-10-09T08:53:20Z', $document['accepted_at']);
	pg_assert_same(1760000000, $document['accepted_at_unix']);
	pg_assert_same('203.0.113.9', $document['ip']);
	pg_assert_same('Mozilla/5.0', $document['user_agent']);
	pg_assert_same(1760000000, $document['created_at_unix']);
	pg_assert_same('<h1>Mesafeli Satış Sözleşmesi</h1><p>Madde 1</p>', $document['html'], 'the stored copy, untouched');

	pg_assert_same(array_keys(api_order_document_schema()), array_keys($document), 'every field declared, in order');
}

function test_api_order_documents_copy_kept_without_acceptance()
{
	$document = api_order_document_present(api_order_documents_test_row(array('accepted_at' => null)), api_order_documents_test_types());

	pg_assert_same(false, $document['accepted']);
	pg_assert_same(null, $document['accepted_at']);
	pg_assert_same(null, $document['accepted_at_unix']);
	pg_assert_same('2025-10-09T08:53:20Z', $document['created_at'], 'kept when the order was placed all the same');
}

function test_api_order_documents_unlabelled_type_and_empty_html()
{
	$document = api_order_document_present(api_order_documents_test_row(array('type' => 'kvkk', 'html' => null)), api_order_documents_test_types());

	pg_assert_same('kvkk', $document['type'], 'the type is the row\'s own');
	pg_assert_same(null, $document['label'], 'a type the product has no name for yet');
	pg_assert_same('', $document['html'], 'never null: the field is declared as a string');
}

function test_api_order_documents_route_lists_them_under_the_read_scope()
{
	$route = null;

	foreach (api_schema() as $candidate) {
		if ($candidate['id'] === 'orders.documents') {
			$route = $candidate;
		}
	}

	pg_assert_true($route !== null, 'the route exists');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/orders/{id}/documents', $route['path']);
	pg_assert_same('orders:read', $route['scope'], 'reading a kept copy is reading the order');
	pg_assert_same('api_orders_documents', $route['handler']);
	pg_assert_true(function_exists($route['handler']), 'the handler is declared');
	pg_assert_same(array('list' => 'OrderDocument'), $route['returns']);
	pg_assert_same(1, count($route['params']), 'the order id and nothing else: one page, no cursor');
	pg_assert_same('path', $route['params'][0]['in']);
}
