<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the api.php get_designer_regions action called without the
 * fields it reads. The LiveSite migration sends only "content", so a missing
 * "search" printed a PHP warning before the JSON and the migration could not
 * read the answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_panel_get_designer_regions() on $request in a fresh PHP with every
// warning turned into a failure and the database answering no rows. Returns
// array('response' => decoded JSON, 'queries' => SQL), array('error' =>
// output) on a warning or an answer that is not JSON, or null when PHP cannot
// be started again.
function api_designer_regions_missing_fields_run($request)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgdreg');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

define('PG_PANEL_ACTIONS', true);

$GLOBALS['queries'] = array();

function db_items($sql) { $GLOBALS['queries'][] = $sql; return array(); }
function escape($value) { return addslashes((string) $value); }
function escape_like($value) { return addcslashes((string) $value, '%_'); }
function encode_json($value) { return json_encode($value); }

require $pg_dir . '/includes/panel/designer.php';

register_shutdown_function(function () {
	echo "\n" . json_encode($GLOBALS['queries']);
});

try {
	pg_panel_get_designer_regions($request, 'get_designer_regions');
} catch (Throwable $error) {
	echo get_class($error) . ': ' . $error->getMessage() . ' at line ' . $error->getLine();
	exit(1);
}
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$request = ' . var_export($request, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	// The answer is the first line, the queries the shutdown function wrote
	// after it.
	$response = isset($lines[0]) ? json_decode($lines[0], true) : null;
	$queries = isset($lines[1]) ? json_decode($lines[1], true) : null;

	if (($code !== 0) || (count($lines) !== 2) || !is_array($response) || !is_array($queries)) {
		return array('error' => implode("\n", $lines));
	}

	return array('response' => $response, 'queries' => $queries);
}

// The migration's own request: content, no search.
function test_api_designer_regions_without_search()
{
	$run = api_designer_regions_missing_fields_run(array('action' => 'get_designer_regions', 'content' => true));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');

	if (isset($run['error'])) {
		return;
	}

	pg_assert_same('success', $run['response']['status']);
	pg_assert_contains('cregion_content AS content', $run['queries'][0]);
	pg_assert_false(strpos($run['queries'][0], 'LIKE') !== false, 'a search filter without a search');
}

// Neither field: names only, no warning.
function test_api_designer_regions_without_any_field()
{
	$run = api_designer_regions_missing_fields_run(array('action' => 'get_designer_regions'));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');

	if (isset($run['error'])) {
		return;
	}

	pg_assert_same('success', $run['response']['status']);
	pg_assert_false(strpos($run['queries'][0], 'cregion_content') !== false, 'content read without being asked');
}

// A search still filters by name.
function test_api_designer_regions_search_filters()
{
	$run = api_designer_regions_missing_fields_run(array('action' => 'get_designer_regions', 'search' => 'head'));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');

	if (isset($run['error'])) {
		return;
	}

	pg_assert_contains("cregion_name LIKE '%head%'", $run['queries'][0]);
}

// A search sent as a list is read as no search.
function test_api_designer_regions_search_list_is_ignored()
{
	$run = api_designer_regions_missing_fields_run(array('action' => 'get_designer_regions', 'search' => array('x')));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');

	if (isset($run['error'])) {
		return;
	}

	pg_assert_false(strpos($run['queries'][0], 'LIKE') !== false, 'a search filter from a list');
}
