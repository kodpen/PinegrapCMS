<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_migration_response(), pg_migration_failure_detail() and
 * pg_migration_url_valid() in includes/fn/update.php: how migration.php reads
 * the source site's answers and checks the API URL it is given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');

function pg_migration_test_result($overrides)
{
	return array_merge(array('ok' => true, 'status' => 200, 'body' => '', 'errno' => 0, 'error' => ''), $overrides);
}

// A JSON answer with a status comes back as decoded; a missing message is ''.
function test_migration_response_success_and_error()
{
	$ok = pg_migration_response(pg_migration_test_result(array('body' => '{"status":"success","styles":[{"id":1}]}')));
	pg_assert_same('success', $ok['status']);
	pg_assert_same('', $ok['message']);
	pg_assert_same(array(array('id' => 1)), $ok['styles']);
	pg_assert_same(0, $ok['errno']);

	$error = pg_migration_response(pg_migration_test_result(array('body' => '{"status":"error"}')));
	pg_assert_same('error', $error['status']);
	pg_assert_same('', $error['message']);

	$message = pg_migration_response(pg_migration_test_result(array('body' => '{"status":"error","message":"Invalid login."}')));
	pg_assert_same('Invalid login.', pg_migration_failure_detail($message));
}

// No answer, an HTTP error page or a body that is not JSON is a
// communication error with the reason, never a null to read keys from.
function test_migration_response_communication_errors()
{
	$curl = pg_migration_response(pg_migration_test_result(array('ok' => false, 'status' => 0, 'errno' => 60, 'error' => 'SSL certificate problem')));
	pg_assert_same('', $curl['status']);
	pg_assert_same('', $curl['message']);
	pg_assert_same(60, $curl['errno']);
	pg_assert_same('cURL error 60: SSL certificate problem', pg_migration_failure_detail($curl));

	$refused = pg_migration_response(pg_migration_test_result(array('ok' => false, 'status' => 0, 'error' => 'Address refused')));
	pg_assert_same('Address refused', pg_migration_failure_detail($refused));

	$http = pg_migration_response(pg_migration_test_result(array('ok' => false, 'status' => 404, 'body' => '<html>Not Found</html>')));
	pg_assert_same('', $http['status']);
	pg_assert_same('HTTP 404', pg_migration_failure_detail($http));

	$not_json = pg_migration_response(pg_migration_test_result(array('body' => 'hello')));
	pg_assert_same('', $not_json['status']);
	pg_assert_same('', pg_migration_failure_detail($not_json));

	$no_status = pg_migration_response(pg_migration_test_result(array('body' => '{"styles":[]}')));
	pg_assert_same('', $no_status['status']);

	$empty = pg_migration_response(array());
	pg_assert_same('', $empty['status']);
	pg_assert_same(0, $empty['errno']);
}

function test_migration_url_valid()
{
	pg_assert_same(true, pg_migration_url_valid('https://www.example.com/pinegrap/api.php'));
	pg_assert_same(true, pg_migration_url_valid('http://192.168.1.10/pinegrap/api.php'));
	pg_assert_same(true, pg_migration_url_valid(' HTTPS://example.com/api.php '));
	pg_assert_same(false, pg_migration_url_valid('https://'));
	pg_assert_same(false, pg_migration_url_valid(''));
	pg_assert_same(false, pg_migration_url_valid('bir adres değil'));
	pg_assert_same(false, pg_migration_url_valid('example.com/api.php'));
	pg_assert_same(false, pg_migration_url_valid('ftp://example.com/api.php'));
	pg_assert_same(false, pg_migration_url_valid('javascript:alert(1)'));
}
