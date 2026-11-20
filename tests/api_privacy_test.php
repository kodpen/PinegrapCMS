<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Privacy centre's API (includes/api/resources/privacy.php):
 * the inventory and transfers as answered, the request list's views and
 * cursor, a request with its steps, the routes and the scope.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/privacy.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function _api_privacy_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// The inventory: every catalogue line with its keys and English labels and
// counts (null when not known), the retention always a list, the transfers
// with their marks.
function test_api_privacy_inventory_present()
{
	$inventory = array(
		'contacts' => array('categories' => array('identity', 'contact'), 'subjects' => array('customer'), 'purpose' => 'Customers', 'ground' => 'legitimate_interest', 'retention' => 'passive_contact', 'person' => array('id', 'int')),
		'orders'   => array('categories' => array('order_payment'), 'subjects' => array('customer'), 'purpose' => 'Orders', 'ground' => 'contract', 'retention' => array('orders', 'incomplete_carts')),
	);
	$counts = array('contacts' => array('records' => 12, 'people' => 10), 'orders' => array('records' => null, 'people' => null));
	$transfers = array('stripe' => array('name' => 'Stripe', 'group' => 'payments', 'uses' => 'Card payments', 'country' => 'US', 'data' => array('order_payment', 'new_key'), 'personal' => true, 'abroad' => true, 'unknown' => false));

	$out = api_privacy_inventory_present($inventory, $counts, $transfers, 'tr');

	pg_assert_same('TR', $out['site_country']);
	pg_assert_same(2, count($out['lines']));
	pg_assert_same(array(
		'table'      => 'contacts',
		'categories' => array(array('key' => 'identity', 'label' => 'Identity'), array('key' => 'contact', 'label' => 'Contact')),
		'subjects'   => array(array('key' => 'customer', 'label' => 'Customers')),
		'purpose'    => 'Customers',
		'ground'     => array('key' => 'legitimate_interest', 'label' => 'Legitimate interest'),
		'retention'  => array(array('key' => 'passive_contact', 'label' => 'Rule: passive contacts')),
		'records'    => 12,
		'people'     => 10,
	), $out['lines'][0]);
	pg_assert_same(array(array('key' => 'orders', 'label' => 'Rule: orders'), array('key' => 'incomplete_carts', 'label' => 'Rule: incomplete carts')), $out['lines'][1]['retention']);
	pg_assert_same(null, $out['lines'][1]['records'], 'not known stays null');
	pg_assert_same(null, $out['lines'][1]['people']);
	pg_assert_same(array('service' => 'stripe', 'name' => 'Stripe', 'group' => 'payments', 'uses' => 'Card payments', 'country' => 'US',
		'data' => array(array('key' => 'order_payment', 'label' => 'Order and payment'), array('key' => 'new_key', 'label' => 'new_key')),
		'personal' => true, 'abroad' => true, 'state_unknown' => false), $out['transfers'][0], 'a key without a label keeps itself');

	// A line missing from the counts is not known either.
	$out = api_privacy_inventory_present($inventory, array(), array(), '');
	pg_assert_same(null, $out['lines'][0]['records']);
	pg_assert_same(null, $out['site_country']);
	pg_assert_same(array(), $out['transfers']);

	// The real catalogue: every key has its label.
	$out = api_privacy_inventory_present(pg_privacy_inventory(), array(), array(), 'TR');
	pg_assert_same(count(pg_privacy_inventory()), count($out['lines']));

	foreach ($out['lines'] as $line) {
		foreach (array_merge($line['categories'], $line['subjects'], array($line['ground']), $line['retention']) as $item) {
			pg_assert_true($item['label'] !== $item['key'], $line['table'] . ' ' . $item['key'] . ' has a label');
		}
	}
}

// The list's views are the screen's; the cursor pages by id, newest first,
// over proven rows only.
function test_api_privacy_requests_sql()
{
	$sql = api_privacy_requests_sql('open', 0, 50, 1000000);
	pg_assert_contains(pg_privacy_request_view_where('open', 1000000), $sql);
	pg_assert_contains('ORDER BY id DESC LIMIT 51', $sql);
	pg_assert_true(strpos($sql, 'id <') === false, 'no cursor, no bound');

	$sql = api_privacy_requests_sql('ending', 40, 10, 1000000);
	pg_assert_contains(pg_privacy_request_view_where('ending', 1000000), $sql);
	pg_assert_contains("(id < '40')", $sql);
	pg_assert_contains('LIMIT 11', $sql);

	foreach (array('open', 'ending', 'answered', 'all') as $view) {
		pg_assert_contains('(received_at > 0)', api_privacy_requests_sql($view, 0, 1, 1), $view . ' reads proven rows only');
	}

	pg_assert_same(array('open', 'ending', 'answered', 'all'), api_privacy_request_views());
}

// A request as listed: no message or answer; days_left only while open.
function test_api_privacy_request_present()
{
	$now = 1000000;
	$row = array('id' => '5', 'kind' => 'erasure', 'status' => 'open', 'source' => 'staff', 'result' => '', 'contact_id' => '3', 'user_id' => '0', 'assigned_user_id' => '2', 'created_by' => '2',
		'email' => 'a@example.com', 'name' => 'Ayşe', 'message' => 'Please erase me', 'answer' => '', 'verified_at' => '0', 'received_at' => '900000', 'due_at' => (string) ($now + 86400 * 3 - 10), 'closed_at' => '0', 'reminded' => '7');

	$out = api_privacy_request_present($row, $now);

	pg_assert_same(5, $out['id']);
	pg_assert_same('erasure', $out['kind']);
	pg_assert_same(null, $out['result'], 'no result yet');
	pg_assert_same(3, $out['contact_id']);
	pg_assert_same(null, $out['user_id'], '0 is nobody');
	pg_assert_same(2, $out['assigned_user_id']);
	pg_assert_same('a@example.com', $out['email']);
	pg_assert_same(api_time(900000), $out['received_at']);
	pg_assert_same(null, $out['verified_at']);
	pg_assert_same(3, $out['days_left']);
	pg_assert_true(!array_key_exists('message', $out) && !array_key_exists('answer', $out), 'the list carries no text');
	pg_assert_same(array_keys(api_privacy_request_schema()), array_keys($out), 'the schema names every field');

	$row['status'] = 'answered';
	$row['result'] = 'sent';
	$row['closed_at'] = '950000';
	$out = api_privacy_request_present($row, $now);
	pg_assert_same(null, $out['days_left'], 'a closed request has no days left');
	pg_assert_same('sent', $out['result']);
	pg_assert_same(api_time(950000), $out['closed_at']);
}

// One request: the text and its steps, each step's user and the kind,
// status and result from the audit line, with nothing else of it (no
// address, no e-mail).
function test_api_privacy_request_detail()
{
	$row = array('id' => '5', 'kind' => 'information', 'status' => 'answered', 'source' => 'form', 'result' => 'sent', 'contact_id' => '0', 'user_id' => '0', 'assigned_user_id' => '0',
		'email' => 'b@example.com', 'name' => '', 'message' => 'What do you hold?', 'answer' => 'This.', 'verified_at' => '1', 'received_at' => '2', 'due_at' => '3', 'closed_at' => '4', 'proof_hash' => 'x');
	$history = array(
		array('created_at' => '200', 'action' => 'answer', 'user_id' => '4', 'username' => 'admin', 'ip' => '10.0.0.1', 'after_json' => '{"kind":"information","status":"answered","result":"sent","email":"x@example.com"}'),
		array('created_at' => '100', 'action' => 'create', 'user_id' => '0', 'username' => '', 'after_json' => 'not json'),
	);

	$out = api_privacy_request_detail_present($row, $history, 10);

	pg_assert_same('What do you hold?', $out['message']);
	pg_assert_same('This.', $out['answer']);
	pg_assert_same(array('action' => 'answer', 'at' => api_time(200), 'user_id' => 4, 'user_name' => 'admin', 'kind' => 'information', 'status' => 'answered', 'result' => 'sent'), $out['steps'][0]);
	pg_assert_same(array('action' => 'create', 'at' => api_time(100), 'user_id' => null, 'user_name' => null, 'kind' => null, 'status' => null, 'result' => null), $out['steps'][1]);
	pg_assert_same(array_keys(api_privacy_request_detail_schema()), array_keys($out));
	pg_assert_true(strpos(json_encode($out), '10.0.0.1') === false, 'no address');
	pg_assert_true(strpos(json_encode($out), 'proof') === false, 'no proof');

	// The steps read audit_log's own columns, never its address.
	$sql = api_privacy_request_steps_sql(5);
	pg_assert_contains('SELECT created_at, action, user_id, username, after_json FROM audit_log', $sql);
	pg_assert_contains("(entity_type = 'privacy_request') AND (entity_id = '5')", $sql);
}

// Before the table exists the request routes answer 503, not an empty list.
function test_api_privacy_requests_503_before_the_table()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/privacy.php');

	foreach (array('function api_privacy_requests_list(', 'function api_privacy_request_read(') as $head) {
		$handler = substr($file, strpos($file, $head));
		$handler = substr($handler, 0, strpos($handler, "\n}\n"));
		pg_assert_contains('api_privacy_requests_require_table();', $handler, $head);
		pg_assert_true(strpos($handler, 'api_privacy_requests_require_table();') < strpos($handler, 'api_ok'), $head . ' checks first');
	}

	pg_assert_contains("api_fail(503, 'service_unavailable'", $file);
}

// Three read routes under privacy:read, offered to manager and up; no write.
function test_api_privacy_routes_and_scope()
{
	$expect = array('privacy.inventory' => '/privacy/inventory', 'privacy.requests' => '/privacy/requests', 'privacy.request' => '/privacy/requests/{id}');

	foreach ($expect as $id => $path) {
		$route = _api_privacy_test_route($id);
		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same('GET', $route['method']);
		pg_assert_same($path, $route['path']);
		pg_assert_same('privacy:read', $route['scope']);
	}

	$groups = api_scope_groups();
	pg_assert_same('privacy:read', $groups['privacy']['read']);
	pg_assert_same('', $groups['privacy']['write'], 'writes stay on the panel');

	foreach (array(0 => true, 1 => true, 2 => true, 3 => false) as $role => $offered) {
		$owner = array('role' => $role, 'manage_ecommerce' => false, 'manage_contacts' => true, 'manage_forms' => false);
		pg_assert_same($offered, in_array('privacy:read', api_owner_scopes($owner), true), 'role ' . $role);
	}

	// A role-3 owner, even with the contacts right, is answered 403: the
	// scope granted is capped away, and the route asks for it.
	$owner = array('role' => 3, 'manage_ecommerce' => true, 'manage_contacts' => true, 'manage_forms' => true);
	pg_assert_false(api_has_scope(api_effective_scopes(array('privacy:read'), $owner), 'privacy:read'), 'role 3 holds no privacy:read');
	pg_assert_true(in_array('customers:read', api_owner_scopes($owner), true), 'though it reads customers');
	$owner['role'] = 2;
	pg_assert_true(api_has_scope(api_effective_scopes(array('privacy:read'), $owner), 'privacy:read'), 'a manager holds it');

	// Not on an assistant's key, nor in an assistant plugin's reads.
	pg_assert_contains('privacy:read', api_assistant_scopes_left_out());
	pg_assert_false(in_array('privacy:read', api_assistant_scopes(api_scope_groups(), api_assistant_scopes_left_out()), true));
	pg_assert_false(in_array('privacy:read', api_plugin_scopes(api_scope_groups()), true));

	// The MCP tools say read only.
	foreach ($expect as $id => $path) {
		pg_assert_contains('Read only', _api_privacy_test_route($id)['description'], $id);
	}
}
