<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of the error record in includes/fn/errors.php.
 * None of them writes the log or installs a handler.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A line is the date prefix view_log.php looks for, then one JSON object.
function test_errors_format_line_prefix_and_json()
{
	$entry = array(
		'level'   => 'warning',
		'type'    => 'E_WARNING',
		'message' => "Line one\nline two / çğü",
		'file'    => '/srv/site/a.php',
		'line'    => 12,
		'trace'   => array('/srv/site/b.php:3 foo()'),
	);

	$timestamp = mktime(14, 5, 9, 3, 7, 2026);
	$line = pg_error_format_line($entry, $timestamp);
	$prefix = '[' . date('Y-m-d H:i:s', $timestamp) . '] ';

	pg_assert_same($prefix, substr($line, 0, strlen($prefix)), 'prefix');
	pg_assert_true((bool) preg_match('/^\[?([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9:]+)/', $line), 'view_log date pattern');
	pg_assert_false(strpos($line, "\n"), 'single line');

	$decoded = json_decode(substr($line, strlen($prefix)), true);

	pg_assert_same($entry, $decoded, 'round trip');
	pg_assert_contains('/srv/site/a.php', $line, 'slashes unescaped');
	pg_assert_contains('çğü', $line, 'unicode unescaped');
}

// Broken UTF-8 in a message does not lose the line.
function test_errors_format_line_survives_invalid_utf8()
{
	$line = pg_error_format_line(array('level' => 'warning', 'message' => "bad \xC3\x28 byte", 'line' => 1), 0);
	$decoded = json_decode(substr($line, strpos($line, '] ') + 2), true);

	pg_assert_true(is_array($decoded), 'decodes');
	pg_assert_same(1, $decoded['line'], 'other fields kept');
}

// At most ten frames, file:line and the call, never an argument.
function test_errors_trace_short_trims_and_drops_arguments()
{
	$frames = array();

	for ($i = 0; $i < 15; $i++) {
		$frames[] = array('file' => '/srv/f' . $i . '.php', 'line' => $i, 'function' => 'fn' . $i, 'args' => array('secret-password'));
	}

	$frames[0] = array('file' => '/srv/c.php', 'line' => 7, 'class' => 'Liveform', 'type' => '->', 'function' => 'render', 'args' => array('secret-password'));
	$frames[1] = array('function' => 'array_map', 'args' => array('secret-password'));
	$frames[2] = array('file' => '/srv/s.php', 'line' => 9, 'class' => 'db', 'type' => '::', 'function' => 'query');

	$trace = pg_error_trace_short($frames);

	pg_assert_same(10, count($trace), 'ten frames');
	pg_assert_same('/srv/c.php:7 Liveform->render()', $trace[0], 'method');
	pg_assert_same('[internal] array_map()', $trace[1], 'internal frame');
	pg_assert_same('/srv/s.php:9 db::query()', $trace[2], 'static');
	pg_assert_same('/srv/f3.php:3 fn3()', $trace[3], 'function');
	pg_assert_false(strpos(implode("\n", $trace), 'secret-password'), 'no arguments');
	pg_assert_same(3, count(pg_error_trace_short($frames, 3)), 'custom max');
}

// Error numbers map to the recorded level; an unknown one is an error.
function test_errors_level_name()
{
	pg_assert_same('warning', pg_error_level_name(E_WARNING));
	pg_assert_same('warning', pg_error_level_name(E_USER_WARNING));
	pg_assert_same('notice', pg_error_level_name(E_NOTICE));
	pg_assert_same('deprecated', pg_error_level_name(E_DEPRECATED));
	pg_assert_same('deprecated', pg_error_level_name(E_USER_DEPRECATED));
	pg_assert_same('fatal', pg_error_level_name(E_ERROR));
	pg_assert_same('fatal', pg_error_level_name(E_USER_ERROR));
	pg_assert_same('error', pg_error_level_name(E_RECOVERABLE_ERROR));
	pg_assert_same('error', pg_error_level_name(123456));
}

// Secret-bearing parameters are masked, everything else is left as it came.
function test_errors_mask_url()
{
	pg_assert_same('/a.php?a=1&password=***&k=***', pg_error_mask_url('/a.php?a=1&password=x&k=tok'));
	pg_assert_same('/g?Token=***&sig=***&key=***&keep=%2Fx', pg_error_mask_url('/g?Token=abc&sig=s1&key=v&keep=%2Fx'));
	pg_assert_same('/f?password%5B%5D=***&kind=k', pg_error_mask_url('/f?password%5B%5D=a&kind=k'));
	pg_assert_same('/plain/path', pg_error_mask_url('/plain/path'));
	pg_assert_same('/p?flag&a=', pg_error_mask_url('/p?flag&a='));
}

// Under the limit nothing moves; over it the oldest copy goes first.
function test_errors_rotate_plan()
{
	pg_assert_same(array(), pg_error_rotate_plan(100, 5242880, 3), 'under');
	pg_assert_same(array(), pg_error_rotate_plan(5242880, 5242880, 3), 'at the limit');
	pg_assert_same(array(array('.1', '.2'), array('', '.1')), pg_error_rotate_plan(5242881, 5242880, 3), 'over');
	pg_assert_same(array(array('', '.1')), pg_error_rotate_plan(10, 5, 2), 'keep two');
}

// A line of this log reads as text; any other line is left to the caller.
function test_errors_describe_line()
{
	$line = pg_error_format_line(array(
		'level'      => 'fatal',
		'type'       => 'RuntimeException',
		'message'    => 'Boom',
		'file'       => '/srv/a.php',
		'line'       => 4,
		'request_id' => 'abc123def456',
		'method'     => 'GET',
		'url'        => '/x?token=***',
		'user_id'    => 7,
		'ip'         => '203.0.113.5',
		'trace'      => array('/srv/b.php:2 run()'),
	), 0);

	pg_assert_same(
		"fatal: RuntimeException: Boom\n/srv/a.php:4\nGET /x?token=*** (request abc123def456, user 7, 203.0.113.5)\n#0 /srv/b.php:2 run()",
		pg_error_describe_line($line));

	$warning = pg_error_format_line(array('level' => 'warning', 'type' => 'E_WARNING', 'message' => 'Careful', 'file' => '/srv/c.php', 'line' => 1), 0);

	pg_assert_same("warning: Careful\n/srv/c.php:1", pg_error_describe_line($warning), 'E_* type left out');

	pg_assert_same(null, pg_error_describe_line('[08-Oct-2026 10:00:00 UTC] PHP Warning:  x in /a.php on line 1'), 'php error_log line');
	pg_assert_same(null, pg_error_describe_line('[2026-10-08 10:00:00] {not json'), 'broken json');
	pg_assert_same(null, pg_error_describe_line('plain text'), 'plain');
}
