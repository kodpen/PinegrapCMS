<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Runs the unit tests under tests/. No Composer, no PHPUnit, no database:
 * the product's function modules are loaded the way a request loads them,
 * minus init.php and the connection, so only code that does not touch the
 * database can be tested here.
 *
 * Usage:  php tools/test.php [filter]
 *         With a filter, only test files whose name contains it, and test
 *         functions whose name contains it, are run.
 * Exit:   0 when every test passes, 1 on any failure or error (or when a
 *         filter matched nothing).
 *
 * Adding a test file: create tests/<subject>_test.php with the Pinegrap
 * header and the gate `if (!defined('PG_TEST_RUNNER')) { exit; }`, then write
 * one function per behaviour, named test_<subject>_<what_it_checks>. Every
 * function starting with test_ is picked up and run in the order it is
 * declared, files in name order. Function names share one global namespace
 * across all test files, so keep the subject prefix. Check results with
 * pg_assert_same() (strict ===), pg_assert_true(), pg_assert_false() and
 * pg_assert_contains(); a failed assertion is counted and the test carries
 * on, an exception (or a PHP warning/notice, which is turned into one) ends
 * the test as ERROR. pinegrap/functions.php and the ERP money module are
 * already loaded; anything that queries the database belongs in a sandbox
 * check (tools/setup_sandbox.sh), not here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$filter = isset($argv[1]) ? (string) $argv[1] : '';

$repository = dirname(__DIR__);
$tests_directory = $repository . '/tests';

// -- bootstrap ---------------------------------------------------------------

// The gate every test file checks.
define('PG_TEST_RUNNER', true);

// functions.php defines PG_FUNCTIONS_DIR itself and loads every module under
// includes/fn/. None of them needs a connection, a config file or a $_SERVER
// key just to be loaded, so nothing is faked here.
require_once($repository . '/pinegrap/functions.php');

// The ERP money helpers. bootstrap.php would pull in the whole module, much of
// which runs queries; money.php alone is pure arithmetic behind the same gate.
if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');

// -- assertions --------------------------------------------------------------

// Failures of the test that is running, reset before each test.
$GLOBALS['pg_test_failures'] = array();
$GLOBALS['pg_test_assertions'] = 0;

function pg_test_record($passed, $label, $detail)
{
	$GLOBALS['pg_test_assertions']++;

	if (!$passed) {
		$GLOBALS['pg_test_failures'][] = (($label !== '') ? $label . ': ' : '') . $detail;
	}

	return $passed;
}

function pg_assert_same($expected, $actual, $label = '')
{
	return pg_test_record(
		$expected === $actual,
		$label,
		'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function pg_assert_true($actual, $label = '')
{
	return pg_test_record($actual === true, $label, 'expected true, got ' . var_export($actual, true));
}

function pg_assert_false($actual, $label = '')
{
	return pg_test_record($actual === false, $label, 'expected false, got ' . var_export($actual, true));
}

function pg_assert_contains($needle, $haystack, $label = '')
{
	if (is_array($haystack)) {
		$found = in_array($needle, $haystack, true);
	} else {
		$found = (strpos((string) $haystack, (string) $needle) !== false);
	}

	return pg_test_record(
		$found,
		$label,
		var_export($haystack, true) . ' does not contain ' . var_export($needle, true));
}

// A warning or notice inside a test is a bug the test would otherwise print
// and pass; turning it into an exception makes the test an ERROR.
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		return false;
	}
	throw new ErrorException($message, 0, $severity, $file, $line);
});

// -- discovery and run -------------------------------------------------------

$files = is_dir($tests_directory) ? glob($tests_directory . '/*_test.php') : array();
sort($files);

$passed = 0;
$failed = 0;
$errors = 0;

foreach ($files as $file) {

	$file_name = basename($file);
	$file_matches = ($filter === '') || (strpos($file_name, $filter) !== false);

	$before = get_defined_functions()['user'];
	require_once($file);
	$declared = array_diff(get_defined_functions()['user'], $before);

	$tests = array();

	foreach ($declared as $function) {

		if (strpos($function, 'test_') !== 0) {
			continue;
		}

		if ($file_matches || (strpos($function, $filter) !== false)) {
			$tests[] = $function;
		}

	}

	if (count($tests) === 0) {
		continue;
	}

	echo $file_name . "\n";

	foreach ($tests as $function) {

		$GLOBALS['pg_test_failures'] = array();
		$error = null;

		try {
			$function();
		} catch (Throwable $throwable) {
			$error = $throwable;
		}

		if ($error !== null) {
			$errors++;
			echo '  ERROR ' . $function . "\n";
			echo '        ' . get_class($error) . ': ' . $error->getMessage()
				. ' (' . str_replace($repository . '/', '', $error->getFile()) . ':' . $error->getLine() . ')' . "\n";
		} elseif (count($GLOBALS['pg_test_failures']) > 0) {
			$failed++;
			echo '  FAIL  ' . $function . "\n";
		} else {
			$passed++;
			echo '  PASS  ' . $function . "\n";
		}

		// Assertions that failed before an exception are reported either way.
		foreach ($GLOBALS['pg_test_failures'] as $failure) {
			echo '        ' . str_replace("\n", "\n        ", $failure) . "\n";
		}

	}

}

echo "\n";

if (($passed + $failed + $errors) === 0) {
	echo (($filter !== '') ? 'No tests matched: ' . $filter : 'No tests found in tests/') . "\n";
	exit(1);
}

echo 'Ran ' . $GLOBALS['pg_test_assertions'] . ' assertion(s).' . "\n";
echo $passed . ' passed, ' . $failed . ' failed, ' . $errors . ' errors' . "\n";

exit((($failed + $errors) > 0) ? 1 : 0);
