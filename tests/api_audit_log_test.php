<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /audit-log (includes/api/resources/audit_log.php): the line
 * as the API answers it, both sides decoded as stored, and its declaration.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/audit_log.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The time helper lives in includes/api/response.php, which loads only behind
// the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// An audit_log row as the listing reads it.
function api_audit_log_test_row($changes = array())
{
	return array_merge(array(
		'id' => '41', 'created_at' => '1792000000', 'request_id' => 'req1', 'source' => 'panel', 'user_id' => '3',
		'username' => 'ayse', 'ip' => '10.0.0.1', 'action' => 'update', 'entity_type' => 'product', 'entity_id' => '7',
		'label' => 'Kalem', 'before_json' => '{"price":"1250","api_key":"***"}', 'after_json' => '{"price":"1300","api_key":"***"}',
	), $changes);
}

// Types, nulls for what is not there, both sides decoded as written.
function test_api_audit_log_present()
{
	$line = api_audit_log_present(api_audit_log_test_row());

	pg_assert_same(41, $line['id'], 'id');
	pg_assert_same(gmdate('Y-m-d\\TH:i:s\\Z', 1792000000), $line['created_at'], 'time');
	pg_assert_same(3, $line['user_id'], 'user');
	pg_assert_same(7, $line['entity_id'], 'entity id');
	pg_assert_same(array('price' => '1250', 'api_key' => '***'), $line['before'], 'before');
	pg_assert_same(array('price' => '1300', 'api_key' => '***'), $line['after'], 'after');
	pg_assert_same(array_keys(api_audit_log_schema()), array_keys($line), 'fields as declared');

	$job = api_audit_log_present(api_audit_log_test_row(array('source' => 'job', 'user_id' => '0', 'username' => '', 'ip' => '', 'request_id' => '', 'entity_id' => '0', 'before_json' => null, 'after_json' => '')));

	pg_assert_same(null, $job['user_id'], 'no user');
	pg_assert_same(null, $job['username'], 'no name');
	pg_assert_same(null, $job['ip'], 'no address');
	pg_assert_same(null, $job['request_id'], 'no request');
	pg_assert_same(null, $job['entity_id'], 'no record id');
	pg_assert_same(null, $job['before'], 'nothing before');
	pg_assert_same(null, $job['after'], 'nothing after');

	$cut = api_audit_log_present(api_audit_log_test_row(array('before_json' => '{"truncated":true,"fields":["page_tree_json"]}')));
	pg_assert_same(array('truncated' => true, 'fields' => array('page_tree_json')), $cut['before'], 'side cut to its names');
}

// One read route under system:read, with the screen's filters.
function test_api_audit_log_route()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'audit_log.list') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'declared');
	pg_assert_same('GET', $found['method'], 'read');
	pg_assert_same('/audit-log', $found['path'], 'path');
	pg_assert_same('system:read', $found['scope'], 'scope');
	pg_assert_same(array('from', 'to', 'source', 'user', 'action', 'entity_type', 'entity_id', 'q', 'request_id', 'limit', 'cursor'), array_column($found['params'], 'name'), 'parameters');
}
