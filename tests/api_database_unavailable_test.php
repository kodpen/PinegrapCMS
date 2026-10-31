<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's answer to a lost database connection:
 * 503 database_unavailable, told apart from the 503 service_unavailable a
 * feature answers while the database waits for its update.
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

function test_api_database_unavailable_is_a_503_of_its_own()
{
	$catalogue = api_error_catalogue();

	pg_assert_true(isset($catalogue['database_unavailable']), 'catalogued');
	pg_assert_same(503, $catalogue['database_unavailable'][0], 'database_unavailable is 503');
	pg_assert_same(503, $catalogue['service_unavailable'][0], 'service_unavailable stays for features');
}

// One answer for a lost connection, whatever the entry: the status, the code
// and the Retry-After header come from api_database_unavailable_answer().
function test_api_database_unavailable_answer_carries_retry_after()
{
	$answer = api_database_unavailable_answer();

	pg_assert_same(503, $answer['status'], 'status');
	pg_assert_same('database_unavailable', $answer['code'], 'code');
	pg_assert_same(array('Retry-After' => '30'), $answer['headers'], 'Retry-After');
	pg_assert_same(503, api_error_catalogue()[$answer['code']][0], 'catalogued with the same status');
}

// integration.php (api_run()) and mcp.php (pg_mcp_gate()) both answer a
// missing connection through the shared helper, never with their own code.
function test_api_and_mcp_entries_share_the_database_unavailable_answer()
{
	$pattern = "/if \(!defined\('DB_CONNECTED'\)\) \{\s*api_fail_database_unavailable\(\);/";

	$entries = array(
		'includes/api/bootstrap.php' => 'function api_run(',
		'includes/mcp/protocol.php'  => 'function pg_mcp_gate(',
	);

	foreach ($entries as $file => $function) {

		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		$start = strpos($source, $function);

		pg_assert_true($start !== false, $file . ' defines ' . $function);

		$body = substr($source, $start, 1500);

		pg_assert_true((bool)preg_match($pattern, $body), $file . ' answers through api_fail_database_unavailable()');
		pg_assert_true(strpos($body, "'service_unavailable'") === false, $file . ' never answers service_unavailable for it');
	}

	$bootstrap = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/bootstrap.php');

	pg_assert_true((bool)preg_match('/function api_fail_database_unavailable\(\) \{.*?api_database_unavailable_answer\(\).*?api_extra_headers\(\$name, \$value\).*?api_fail\(\$answer\[\'status\'\], \$answer\[\'code\'\]/s', $bootstrap), 'the helper sends the shared answer');
}
