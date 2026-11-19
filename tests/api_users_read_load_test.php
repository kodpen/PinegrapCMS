<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a users read of the external API loads every function its
 * column probes call. GET /users and GET /users/{id} reach
 * api_user_optional_columns(), which asks pg_user_permission_ws_replies_column()
 * on a site with the Workspace columns; that function lives in
 * includes/user_permissions.php. The check runs in a separate PHP process,
 * since the runner shares one process with tests that load the write module.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Loads functions.php and the users resource alone, as a read does, and
// answers which of the probe functions exist; null when no PHP can be run.
function api_users_read_load_probe()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgusrrd');

	if ($file === false) {
		return null;
	}

	$functions = array(
		'pg_user_has_ws_columns',
		'pg_user_permission_ws_replies_column',
		'pg_user_permission_ws_available',
		'pg_user_has_erp_columns',
		'pg_user_has_erp_readonly_column',
		'pg_auth_column_exists'
	);

	$script = '<?php' . "\n"
		. 'error_reporting(E_ALL);' . "\n"
		. 'require_once(' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ');' . "\n"
		. 'define("PG_API_PANEL", true);' . "\n"
		. 'require_once(PG_FUNCTIONS_DIR . "/includes/api/resources/users.php");' . "\n"
		. '$found = array();' . "\n"
		. 'foreach (' . var_export($functions, true) . ' as $name) { $found[$name] = function_exists($name); }' . "\n"
		. 'echo json_encode($found);' . "\n";

	file_put_contents($file, $script);

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Every function the read's column probes call is there without the write
// module, so a site with the Workspace columns answers 200, not 500.
function test_api_users_read_load_has_every_probe()
{
	$found = api_users_read_load_probe();

	if ($found === null) {
		pg_assert_true(true, 'no PHP binary to run the probe');
		return;
	}

	pg_assert_false(isset($found['error']), 'the read loads without an error: ' . (isset($found['error']) ? $found['error'] : ''));

	foreach (array('pg_user_has_ws_columns', 'pg_user_permission_ws_replies_column', 'pg_user_permission_ws_available', 'pg_user_has_erp_columns', 'pg_user_has_erp_readonly_column', 'pg_auth_column_exists') as $name) {
		pg_assert_true(!empty($found[$name]), $name . ' is loaded by a users read');
	}
}
