<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /changes/deleted (includes/api/resources/changes.php): the
 * type map, which types a caller is answered for, the since rule (410
 * since_too_old) and the route's declaration.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// Only products and offers are recorded on every delete path; the rest are
// named as not covered.
function test_api_changes_deleted_coverage()
{
	$coverage = api_changes_deleted_coverage();

	pg_assert_same(array('products', 'offers'), $coverage['covered'], 'covered');
	pg_assert_same(array('orders', 'customers', 'pages', 'files', 'users'), $coverage['not_covered'], 'not covered');

	$types = api_changes_deleted_types();
	$audit = pg_audit_tables();
	$entities = array();

	foreach ($audit as $table) {
		$entities[] = $table['type'];
	}

	foreach ($types as $name => $type) {
		pg_assert_true(in_array($type['entity'], $entities, true), $name . ' maps to an audit type');
	}
}

function test_api_changes_deleted_parse_types()
{
	pg_assert_same(array('types' => array('products', 'offers')), api_changes_deleted_parse_types(''), 'none: every covered');
	pg_assert_same(array('types' => array('offers')), api_changes_deleted_parse_types(' Offers , '), 'trimmed, lower case');
	pg_assert_same(array('types' => array('products', 'offers')), api_changes_deleted_parse_types('products,offers,products'), 'once each');
	pg_assert_true(isset(api_changes_deleted_parse_types('widgets')['error']), 'unknown refused');
	pg_assert_true(isset(api_changes_deleted_parse_types('orders')['error']), 'not covered refused');
	pg_assert_true(isset(api_changes_deleted_parse_types(',,')['error']), 'no name refused');
}

function test_api_changes_deleted_readable()
{
	$all = array('products', 'offers');

	pg_assert_same(array('products', 'offers'), api_changes_deleted_readable($all, array('products:read', 'offers:read'), 0), 'both scopes');
	pg_assert_same(array('offers'), api_changes_deleted_readable($all, array('offers:read', 'products:write'), 1), 'write is not read');
	pg_assert_same(array(), api_changes_deleted_readable($all, array(), 0), 'no scope, nothing');
	pg_assert_same(array('products'), api_changes_deleted_readable($all, array('products:read'), 3), 'user role, no row rule');
	pg_assert_same(array(), api_changes_deleted_readable(array('orders'), array('orders:read'), 0), 'not covered never answered');
}

// A since before the trail's reach is 410; the floor is the later of the
// retention cutoff and the oldest line, now when the trail is empty.
function test_api_changes_deleted_since_rule()
{
	$now = 1800000000;

	pg_assert_same(1700000000, api_changes_deleted_floor(0, 1700000000, $now), 'kept forever: oldest line');
	pg_assert_same(1750000000, api_changes_deleted_floor(1750000000, 1700000000, $now), 'cutoff after the oldest line');
	pg_assert_same(1760000000, api_changes_deleted_floor(1750000000, 1760000000, $now), 'oldest line after the cutoff');
	pg_assert_same($now, api_changes_deleted_floor(0, 0, $now), 'empty trail');

	pg_assert_true(api_changes_deleted_since_ok(1750000000, 1750000000), 'at the floor');
	pg_assert_false(api_changes_deleted_since_ok(1749999999, 1750000000), 'before the floor');

	$catalogue = api_error_catalogue();

	pg_assert_same(410, $catalogue['since_too_old'][0], '410 explained');
}

function test_api_changes_deleted_present()
{
	$row = api_changes_deleted_present(array('id' => '9', 'created_at' => '1792000000', 'entity_type' => 'offer', 'entity_id' => '12'));

	pg_assert_same(array('type' => 'offers', 'id' => 12, 'deleted_at' => gmdate('Y-m-d\\TH:i:s\\Z', 1792000000)), $row, 'type, id, time only');
	pg_assert_same(array_keys(api_changes_deleted_schema()), array_keys($row), 'fields as declared');
}

function test_api_changes_deleted_route()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'changes.deleted') {
			$found = $route;
		}
	}

	pg_assert_true($found !== null, 'declared');
	pg_assert_same('GET', $found['method'], 'read');
	pg_assert_same('', $found['scope'], 'no scope of its own');

	$since = null;

	foreach ($found['params'] as $param) {
		if ($param['name'] === 'since') {
			$since = $param;
		}
	}

	pg_assert_true(!empty($since['required']), 'since required');
}
