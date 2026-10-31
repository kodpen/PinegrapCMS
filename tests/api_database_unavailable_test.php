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

// The bootstrap answers a missing connection with the new code, never with
// the code features use for "update the database first".
function test_api_bootstrap_answers_a_lost_connection_with_database_unavailable()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/bootstrap.php');

	pg_assert_true((bool)preg_match("/if \(!defined\('DB_CONNECTED'\)\) \{\s*(?:[^}]*?)api_fail\(503, 'database_unavailable'/", $source), 'database_unavailable');
}
