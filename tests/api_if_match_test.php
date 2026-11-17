<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for If-Match on an update (includes/api/preconditions.php): the tag
 * comparison, which updates read the header, and the router's decision.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/preconditions.php');

// A strong or a weak tag matches the current one, W/ ignored on either side.
function test_api_if_match_strong_and_weak_tags()
{
	$current = 'W/"abc123"';

	pg_assert_true(api_if_match_holds('W/"abc123"', $current), 'weak against weak');
	pg_assert_true(api_if_match_holds('"abc123"', $current), 'strong against weak');
	pg_assert_true(api_if_match_holds('W/"abc123"', '"abc123"'), 'weak against strong');
	pg_assert_true(api_if_match_holds('  "abc123"  ', $current), 'spaces around');
	pg_assert_true(!api_if_match_holds('"abc124"', $current), 'another tag');
	pg_assert_true(!api_if_match_holds('abc123', $current), 'unquoted is another tag');
}

// * matches any record that exists.
function test_api_if_match_star()
{
	pg_assert_true(api_if_match_holds('*', 'W/"abc123"'), 'star on a record');
	pg_assert_true(api_if_match_holds(' * ', 'W/"abc123"'), 'star with spaces');
}

// One tag of a list is enough.
function test_api_if_match_several_tags()
{
	pg_assert_true(api_if_match_holds('"old1", W/"abc123", "old2"', 'W/"abc123"'), 'middle of a list');
	pg_assert_true(api_if_match_holds('"old1","abc123"', 'W/"abc123"'), 'no space after the comma');
	pg_assert_true(!api_if_match_holds('"old1", "old2"', 'W/"abc123"'), 'none of the list');
	pg_assert_true(!api_if_match_holds('"old1", , ', 'W/"abc123"'), 'empty members');
}

// No record: nothing matches, * included.
function test_api_if_match_missing_record()
{
	pg_assert_true(!api_if_match_holds('*', null), 'star without a record');
	pg_assert_true(!api_if_match_holds('W/"abc123"', null), 'tag without a record');
	pg_assert_true(!api_if_match_holds('W/"abc123"', ''), 'empty current tag');
}

// The routes of one resource: an update with a GET twin, an update without
// one, a create and a delete twin.
function api_if_match_test_routes()
{
	return array(
		array('id' => 'things.get', 'method' => 'GET', 'path' => '/things/{id}', 'params' => array()),
		array('id' => 'things.update', 'method' => 'POST', 'also_accepts' => array('PATCH'), 'path' => '/things/{id}', 'params' => array()),
		array('id' => 'things.replace', 'method' => 'PUT', 'path' => '/things/{id}', 'params' => array()),
		array('id' => 'items.update', 'method' => 'POST', 'also_accepts' => array('PATCH'), 'path' => '/things/{id}/items/{item_id}', 'params' => array()),
		array('id' => 'things.create', 'method' => 'POST', 'path' => '/things', 'params' => array()),
		array('id' => 'things.delete_post', 'method' => 'POST', 'path' => '/things/{id}/delete', 'params' => array()),
	);
}

function api_if_match_test_route($id)
{
	foreach (api_if_match_test_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// Only an update whose path a GET reads has a twin.
function test_api_if_match_get_twin()
{
	$routes = api_if_match_test_routes();

	pg_assert_true(api_route_is_update(api_if_match_test_route('things.update')), 'POST with PATCH is an update');
	pg_assert_true(api_route_is_update(api_if_match_test_route('things.replace')), 'PUT is an update');
	pg_assert_true(!api_route_is_update(api_if_match_test_route('things.create')), 'a create is not');

	$twin = api_update_get_twin(api_if_match_test_route('things.update'), $routes);
	pg_assert_same('things.get', $twin['id'], 'the GET of the same path');
	pg_assert_same(null, api_update_get_twin(api_if_match_test_route('items.update'), $routes), 'no GET of that path');
	pg_assert_same(null, api_update_get_twin(api_if_match_test_route('things.create'), $routes), 'a create');
	pg_assert_same(null, api_update_get_twin(api_if_match_test_route('things.delete_post'), $routes), 'a delete twin');
}

// The router's decision: write as today, write, or 412 with its code.
function test_api_if_match_router_decision()
{
	$routes = api_if_match_test_routes();
	$update = api_if_match_test_route('things.update');
	$reads = 0;
	$reader = function ($get_route) use (&$reads) {
		$reads++;
		return ($get_route['id'] === 'things.get') ? 'W/"current"' : null;
	};

	pg_assert_same(null, api_if_match_refusal($update, $routes, '', $reader), 'no header: write');
	pg_assert_same(0, $reads, 'no header: the record is not read');

	pg_assert_same(null, api_if_match_refusal($update, $routes, 'W/"current"', $reader), 'matching tag: write');
	pg_assert_same(null, api_if_match_refusal($update, $routes, '*', $reader), 'star on a record: write');

	$refusal = api_if_match_refusal($update, $routes, 'W/"before"', $reader);
	pg_assert_same(412, $refusal[0], 'changed: 412');
	pg_assert_same('precondition_failed', $refusal[1], 'changed: its code');
	pg_assert_true(is_string($refusal[2]) && $refusal[2] !== '', 'changed: a message');

	$gone = function ($get_route) {
		return null;
	};
	$refusal = api_if_match_refusal($update, $routes, '*', $gone);
	pg_assert_same(412, $refusal[0], 'no record: 412 even for *');

	$reads = 0;
	pg_assert_same(null, api_if_match_refusal(api_if_match_test_route('items.update'), $routes, 'W/"before"', $reader), 'no GET twin: header ignored');
	pg_assert_same(null, api_if_match_refusal(api_if_match_test_route('things.create'), $routes, 'W/"before"', $reader), 'a create: header ignored');
	pg_assert_same(0, $reads, 'ignored: the record is not read');
}

// The 412 answer is the usual envelope with a code the catalogue explains.
function test_api_if_match_code_is_catalogued()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

	$catalogue = api_error_catalogue();

	pg_assert_true(isset($catalogue['precondition_failed']), 'precondition_failed is catalogued');
	pg_assert_same(412, isset($catalogue['precondition_failed']) ? (int) $catalogue['precondition_failed'][0] : 0, 'with 412');
}

// The helper's outcomes, run once.
function api_if_match_test_run_result()
{
	static $result = null;

	if ($result !== null) {
		return $result;
	}

	$output = '';

	if (function_exists('shell_exec')) {
		$command = escapeshellarg(PHP_BINARY) . ' '
			. escapeshellarg(__DIR__ . '/helpers/api_if_match_run.php') . ' 2>&1';

		$output = (string) shell_exec($command);
	}

	$marker = strrpos($output, '@@RESULT@@');
	$decoded = ($marker === false) ? null : json_decode(substr($output, $marker + 10), true);

	$result = is_array($decoded) ? $decoded : array('error' => substr($output, 0, 2000));

	return $result;
}

// The GET handler runs inside the update and its answer gives the tag GET
// sends; the request is left as it was.
function test_api_if_match_reads_the_get_tag()
{
	$result = api_if_match_test_run_result();

	pg_assert_false(isset($result['error']), 'the helper answered: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_same(true, isset($result['tag_read']) ? $result['tag_read'] : null, 'the tag of the full GET answer');
	pg_assert_same(null, array_key_exists('tag_missing', $result) ? $result['tag_missing'] : 'unset', 'a 404 gives no tag');
	pg_assert_same('POST', isset($result['method_restored']) ? $result['method_restored'] : null, 'method restored');
	pg_assert_same(array('name' => 'Pencil'), isset($result['input_restored']) ? $result['input_restored'] : null, 'input restored');
	pg_assert_same('things.update', isset($result['route_restored']) ? $result['route_restored'] : null, 'route restored');
	pg_assert_same(false, isset($result['capture_off']) ? $result['capture_off'] : null, 'capture switched off');
}

// Through api_send(): absent, matching and * write; a changed or missing
// record is the 412 envelope.
function test_api_if_match_answers_through_api_send()
{
	$result = api_if_match_test_run_result();

	foreach (array('absent', 'matching', 'star') as $case) {
		pg_assert_same('write', isset($result[$case]) ? $result[$case] : null, $case . ': write');
	}

	$refused = array('status' => 412, 'code' => 'precondition_failed', 'message' => true, 'request_id' => true);

	pg_assert_same($refused, isset($result['changed']) ? $result['changed'] : null, 'changed: 412 envelope');
	pg_assert_same($refused, isset($result['missing']) ? $result['missing'] : null, 'missing: 412 envelope');
}
