<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The datetime attribute of the <time> element get_absolute_time() writes
 * for a time ('type' => 'time') names the moment in UTC: a Z suffix on the
 * site's local clock put an Istanbul 22:11 three hours late for every
 * reader of the attribute. The zone abbreviation of the short form is kept
 * as the zone gives it, never read by the day and month name table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs get_absolute_time() in a fresh PHP (it reads constants a test run
// cannot define twice) with the site in Istanbul; null when PHP cannot be
// started again.
function absolute_time_datetime_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgabstime');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('SOFTWARE_LANGUAGE', 'en');
define('DATE_FORMAT', 'day_month');
define('TIME_FORMAT', 'twenty_four_hours');
date_default_timezone_set('Europe/Istanbul');
require $pg_dir . '/functions.php';

// Thursday 15 October 2026, 22:11:41 in Istanbul, 19:11:41 UTC.
$timestamp = 1792091501;
$out = array();

foreach (array('time', 'date_and_time') as $type) {
	$out[$type] = get_absolute_time(array('timestamp' => $timestamp, 'type' => $type));
}

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['time'], $result['date_and_time'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

function test_absolute_time_datetime_of_a_time_is_utc()
{
	$result = absolute_time_datetime_run();

	if ($result === null) {
		pg_assert_true(true, 'PHP cannot be started again');
		return;
	}

	pg_assert_false(isset($result['error']), $result['error'] ?? '');

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same('<time datetime="2026-10-15T19:11:41Z">22:11</time>', $result['time']);
	pg_assert_contains('<time datetime="2026-10-15T19:11:41Z">', $result['date_and_time']);
}

// Draws the short date and time in Turkish in a fresh PHP, the site in
// Lisbon (WEST in summer) and the signed-in user in Johannesburg (SAST), so
// the result does not depend on the machine's zone. Null when PHP cannot be
// started again.
function absolute_time_datetime_zone_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgabszone');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('SOFTWARE_LANGUAGE', 'tr');
define('DATE_FORMAT', 'day_month');
define('TIME_FORMAT', 'twenty_four_hours');
define('USER_LOGGED_IN', true);
define('USER_TIMEZONE', 'Africa/Johannesburg');
date_default_timezone_set('Europe/Lisbon');
require $pg_dir . '/functions.php';

// Thursday 15 October 2026, 19:11:41 UTC.
$timestamp = 1792091501;

echo json_encode(array(
	'site' => get_absolute_time(array('timestamp' => $timestamp, 'format' => 'plain_text', 'timezone_type' => 'site')),
	'user' => get_absolute_time(array('timestamp' => $timestamp, 'format' => 'plain_text')),
));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['site'], $result['user'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The zone abbreviation of the short form stays as the zone gives it: the
// day name table's "We" and "Sa" are not read inside "WEST" or "SAST".
function test_absolute_time_datetime_short_zone_is_not_translated()
{
	$result = absolute_time_datetime_zone_run();

	if ($result === null) {
		pg_assert_true(true, 'PHP cannot be started again');
		return;
	}

	pg_assert_false(isset($result['error']), $result['error'] ?? '');

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same('Per 15 Eki 2026 20:11 WEST', $result['site'], 'site zone');
	pg_assert_same('Per 15 Eki 2026 21:11 SAST', $result['user'], 'user zone');
}
