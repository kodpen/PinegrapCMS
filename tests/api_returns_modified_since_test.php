<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /returns?modified_since= (includes/api/resources/returns.php)
 * and the stamp it walks, order_returns.updated_at: the listing each call
 * asks for, the cursor that keeps to its listing, the page SQL of each
 * listing, modified_at on the OrderReturn, the migration step and that every
 * writer of includes/fn/returns.php moves the stamp.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/returns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The presenter's time helper and the cursor helpers live in
// includes/api/response.php, which loads only behind the API's own entry.
// Stand-ins of the same shape, so the listing can be read without it.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

if (!function_exists('api_cursor_encode')) {
	function api_cursor_encode($sort_value, $id)
	{
		return rtrim(strtr(base64_encode(json_encode(array('v' => (int) $sort_value, 'i' => (int) $id))), '+/', '-_'), '=');
	}
}

if (!function_exists('api_cursor_decode')) {
	function api_cursor_decode($cursor)
	{
		$decoded = json_decode((string) base64_decode(strtr((string) $cursor, '-_', '+/')), true);

		return (is_array($decoded) && isset($decoded['v'], $decoded['i'])) ? array('v' => (int) $decoded['v'], 'i' => (int) $decoded['i']) : null;
	}
}

function api_returns_modified_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_api_returns_list_order_follows_modified_since()
{
	pg_assert_same('newest', api_returns_list_order(array()), 'newest first by default');
	pg_assert_same('modified', api_returns_list_order(array('modified_since' => 1793088000)), 'modified_since walks the last change');
}

function test_api_returns_cursor_keeps_to_its_listing()
{
	pg_assert_same('eyJ2IjoxLCJpIjoyfQ', api_returns_cursor_parts('eyJ2IjoxLCJpIjoyfQ', 'newest'), 'an unmarked cursor walks newest first');
	pg_assert_same('eyJ2IjoxLCJpIjoyfQ', api_returns_cursor_parts('m.eyJ2IjoxLCJpIjoyfQ', 'modified'), 'a marked cursor walks modified_since');
	pg_assert_same(null, api_returns_cursor_parts('eyJ2IjoxLCJpIjoyfQ', 'modified'), 'a newest-first cursor under modified_since');
	pg_assert_same(null, api_returns_cursor_parts('m.eyJ2IjoxLCJpIjoyfQ', 'newest'), 'a modified_since cursor without the filter');

	$next = api_returns_cursor_after(array('id' => '7', 'updated_at' => '100'), 'modified');
	pg_assert_same(0, strpos($next, 'm.'), 'the modified cursor carries its mark');
	pg_assert_same(array('v' => 100, 'i' => 7), api_cursor_decode(api_returns_cursor_parts($next, 'modified')), 'and the stamp and the id');

	$next = api_returns_cursor_after(array('id' => '7', 'updated_at' => '100'), 'newest');
	pg_assert_same(array('v' => 0, 'i' => 7), api_cursor_decode(api_returns_cursor_parts($next, 'newest')), 'the newest-first cursor is the id, as before');
}

function test_api_returns_list_sql_of_each_listing()
{
	$sql = api_returns_list_sql(array("updated_at >= '50'"), 'modified', array('v' => 100, 'i' => 7), 25);
	pg_assert_true(strpos($sql, "WHERE updated_at >= '50' AND (updated_at > '100' OR (updated_at = '100' AND id > '7'))") !== false, 'the cursor walks forward on (updated_at, id)');
	pg_assert_true(strpos($sql, 'ORDER BY updated_at ASC, id ASC') !== false, 'oldest change first');
	pg_assert_true(strpos($sql, 'LIMIT 26') !== false, 'one more row tells a next page');

	$sql = api_returns_list_sql(array(), 'modified', null, 25);
	pg_assert_true(strpos($sql, 'WHERE') === false, 'no cursor, no filter: no WHERE');

	$sql = api_returns_list_sql(array("order_id = '9'"), 'newest', array('v' => 0, 'i' => 7), 50);
	pg_assert_true(strpos($sql, "WHERE order_id = '9' AND id < '7'") !== false, 'newest first walks back on the id');
	pg_assert_true(strpos($sql, 'ORDER BY id DESC') !== false, 'newest first');
}

function test_api_returns_where_reads_modified_since()
{
	pg_assert_same(array("updated_at >= '1793088000'"), api_returns_where(array('modified_since' => 1793088000)), 'modified_since filters on the stamp');
	pg_assert_same(array(), api_returns_where(array()), 'nothing without it');
}

function test_api_returns_route_declares_modified_since()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'returns.list') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'modified_since') {
					$found = $param;
				}
			}
		}
	}

	pg_assert_true(is_array($found), 'GET /returns takes modified_since');
	pg_assert_same('datetime', $found['type'], 'as a moment');
	pg_assert_same('string?', api_order_return_schema()['modified_at'], 'the OrderReturn declares modified_at');
}

function test_api_order_return_present_carries_modified_at()
{
	$row = array(
		'id' => '31', 'order_id' => '9007', 'ship_to_id' => '9008', 'status' => 'requested', 'reason' => '',
		'customer_note' => '', 'decision_note' => '', 'source' => 'api', 'carrier' => '', 'carrier_code' => '',
		'tracking_number' => '', 'requested_at' => '1793088000', 'decided_at' => '0', 'shipped_at' => '0',
		'received_at' => '0', 'refunded_at' => '0', 'closed_at' => '0'
	);

	$before = api_order_return_present($row, array(), array());
	pg_assert_same(null, $before['modified_at'], 'null before the step adds the column');

	$row['updated_at'] = '1793088000';
	$after = api_order_return_present($row, array(), array());
	pg_assert_same(api_time(1793088000), $after['modified_at'], 'the stamp as a moment');
}

function test_returns_writers_move_the_stamp()
{
	$source = api_returns_modified_test_source('includes/fn/returns.php');

	preg_match_all('/db\("UPDATE order_returns(.*?)"\);/s', $source, $matches);

	pg_assert_true(count($matches[0]) >= 7, 'the writers are found');

	$untouched = 0;

	foreach ($matches[0] as $statement) {
		if (strpos($statement, 'pg_return_touch_sql()') !== false) {
			continue;
		}

		// The refund reminder stamps are not part of what GET /returns shows.
		pg_assert_true(strpos($statement, '" . $column . "') !== false, 'an UPDATE without pg_return_touch_sql(): ' . substr($statement, 0, 80));
		$untouched++;
	}

	pg_assert_same(2, $untouched, 'only the two reminder statements leave the stamp alone');

	pg_assert_true(preg_match('/INSERT INTO order_returns \([^)]*requested_at" \. \(\$stamped \? ", updated_at"/', $source) === 1, 'a new return is stamped with its request');
}

function test_return_updated_at_migration_step()
{
	$source = api_returns_modified_test_source('includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($source, "upgrade_2026_11_0_return_updated_at();       // 11.0.168") !== false, 'the step is called');
	pg_assert_true(strpos($source, "install_add_column('order_returns', 'updated_at', \"INT UNSIGNED NOT NULL DEFAULT 0\")") !== false, 'the column');
	pg_assert_true(strpos($source, "install_add_index('order_returns', 'idx_updated', \"INDEX idx_updated (updated_at, id)\")") !== false, 'the index the listing pages on');
	pg_assert_true(preg_match('/SET updated_at = GREATEST\(requested_at, decided_at, shipped_at, received_at, refunded_at, closed_at\)\s+WHERE updated_at = 0/', $source) === 1, 'the fill touches only rows still at 0');
}
