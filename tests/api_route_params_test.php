<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two things every external API call depends on. Every route row,
 * the core table's and each module's, carries a 'params' list: the router
 * walks it while matching any path, the OpenAPI document and the console
 * walk it for every route, so one row without it puts a warning in the log
 * on every unknown address. tools/check_api_schema.php refuses such a row.
 * And a webhook address the outgoing check refuses is answered in the site
 * language, like the call's other validation messages.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

function test_api_route_params_every_row_declares_a_list()
{
	$missing = array();

	foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes()) as $route) {
		if (!isset($route['params']) || !is_array($route['params'])) {
			$missing[] = $route['id'];
		}
	}

	pg_assert_same(array(), $missing, 'route rows without a params list');
}

// Writes a tree with one route table and runs the check over it.
function api_route_params_test_run_check($schema_source)
{
	$root = sys_get_temp_dir() . '/pg_api_params_' . getmypid() . '_' . mt_rand();

	mkdir($root . '/includes/api', 0777, true);
	file_put_contents($root . '/includes/api/schema.php', $schema_source);
	file_put_contents($root . '/includes/api/openapi.php', "<?php\n");

	$command = escapeshellarg(PHP_BINARY) . ' '
		. escapeshellarg(dirname(PG_FUNCTIONS_DIR) . '/tools/check_api_schema.php') . ' '
		. escapeshellarg($root) . ' 2>&1';

	$output = (string) shell_exec($command);

	unlink($root . '/includes/api/schema.php');
	unlink($root . '/includes/api/openapi.php');
	rmdir($root . '/includes/api');
	rmdir($root . '/includes');
	rmdir($root);

	return $output;
}

function test_api_route_params_check_refuses_a_row_without_the_list()
{
	$source = "<?php\nfunction api_schema() { return array(\n"
		. "\tarray(\n\t\t'id' => 'fixture.bare',\n\t\t'method' => 'GET',\n\t\t'path' => '/bare',\n"
		. "\t\t'handler' => 'fixture_bare',\n\t\t'returns' => array('list' => 'Fixture'),\n\t),\n"
		. "\tarray(\n\t\t'id' => 'fixture.listed',\n\t\t'method' => 'GET',\n\t\t'path' => '/listed',\n"
		. "\t\t'handler' => 'fixture_listed',\n\t\t'returns' => array('list' => 'Fixture'),\n"
		. "\t\t'params' => array(),\n\t),\n"
		. "); }\n";

	$output = api_route_params_test_run_check($source);

	pg_assert_contains("fixture.bare: no 'params'", $output, 'a row without params is refused');
	pg_assert_false(strpos($output, "fixture.listed: no 'params'") !== false, 'a row with an empty list passes');
}

function test_api_route_params_webhook_address_refusal_is_translated()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');

	pg_assert_false(strpos($source, "api_fail_validation(\$check['error']") !== false, 'no untranslated address refusal');
	pg_assert_same(2, substr_count($source, "api_fail_validation(lang(\$check['error']), 'url')"), 'create and update translate the refusal');
}
