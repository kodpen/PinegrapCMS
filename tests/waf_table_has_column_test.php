<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for waf_table_has_column(): with no db class loaded (a unit test
 * run alone, a script that loads waf.php first) it answers false instead
 * of stopping on "Class db not found".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the probe in a fresh PHP, where no other test has declared a db
// stub. Null when PHP cannot be started again.
function waf_table_has_column_test_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwafcol');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. 'require_once ' . var_export(PG_FUNCTIONS_DIR . '/waf.php', true) . ";\n"
		. "\$first = waf_table_has_column('page', 'page_name');\n"
		. "\$second = waf_table_has_column('page', 'page_name');\n"
		. "echo json_encode(array('db' => class_exists('db', false), 'first' => \$first, 'second' => \$second));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result)) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

// No db class: false on the first call and on the cached second one.
function test_waf_table_has_column_without_db_class_is_false()
{
	$result = waf_table_has_column_test_run();

	if ($result === null) {
		return;
	}

	pg_assert_same(null, isset($result['error']) ? $result['error'] : null, 'no error');
	pg_assert_same(false, $result['db'], 'db class not loaded');
	pg_assert_same(false, $result['first'], 'first call');
	pg_assert_same(false, $result['second'], 'cached call');
}
