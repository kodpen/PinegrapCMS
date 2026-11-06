<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the watchdog of the voice transcript queue (ws_voice_watchdog()
 * in includes/workspace/voice.php): it runs on every kick, before the run
 * lock, and every screen showing a waiting recording kicks every 15 seconds,
 * so two watchdogs often meet over the same rows. A recording it gives up
 * on asks the assistants its words ask (ws_voice_ask()) once: only the
 * watchdog whose own UPDATE moved the row asks; one that finds the row
 * already moved by another asks nothing.
 *
 * The function's source runs in a PHP process of its own with a fake
 * database: the rows both watchdogs read, and whether this one's UPDATE
 * changed a row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// Runs the watchdog's source with the fake database. $changed says whether
// this watchdog's UPDATEs move their row (false: another one already did).
// Null when PHP cannot be started again.
function workspace_voice_watchdog_test_run($changed)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgvoicewd');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('WS_VOICE_ATTEMPTS', 3);
define('WS_VOICE_STALE', 900);
define('WS_VOICE_QUEUE_TTL', 86400);

$GLOBALS['asked'] = array();
$GLOBALS['writes'] = array();

// Read by both watchdogs at the same moment: a recording queued over a
// day, one cut off on its third attempt, one cut off on its first.
$GLOBALS['rows'] = array(
	array('id' => '1', 'message_id' => '11', 'status' => 'queued', 'attempts' => '0'),
	array('id' => '2', 'message_id' => '12', 'status' => 'running', 'attempts' => '3'),
	array('id' => '3', 'message_id' => '13', 'status' => 'running', 'attempts' => '1'),
);

function e($value) { return addslashes((string) $value); }
function lang($text) { return is_array($text) ? $text['string'] : $text; }
function ws_voice_ask($message_id) { $GLOBALS['asked'][] = (int) $message_id; }
function ws_voice_changed() { return $GLOBALS['changed']; }

function db_items($sql)
{
	return $GLOBALS['rows'];
}

function db_values($sql)
{
	// The message ids of the rows that fail: over a day, or out of attempts.
	$out = array();

	foreach ($GLOBALS['rows'] as $row) {
		if (($row['status'] === 'queued') || ((int) $row['attempts'] >= 3)) {
			$out[] = $row['message_id'];
		}
	}

	return $out;
}

function db($sql)
{
	$GLOBALS['writes'][] = $sql;

	return true;
}

eval($source);

ws_voice_watchdog();

echo json_encode(array('asked' => $GLOBALS['asked'], 'writes' => $GLOBALS['writes']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$source = ' . var_export(pg_test_function_source('ws_voice_watchdog'), true) . ";\n"
		. '$GLOBALS[\'changed\'] = ' . var_export((bool) $changed, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['asked'], $result['writes'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// Another watchdog already gave the rows up: this one asks nobody again,
// or the person's question reaches Claude or Pinegrap AI twice.
function test_ws_voice_watchdog_asks_nobody_for_rows_another_moved()
{
	$result = workspace_voice_watchdog_test_run(false);

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the watchdog ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(array(), $result['asked'], 'nobody asked twice');
}

// This watchdog moved the rows: the two given up ask what they ask, once
// each; the one put back in the queue asks nothing yet.
function test_ws_voice_watchdog_asks_once_for_rows_it_gave_up()
{
	$result = workspace_voice_watchdog_test_run(true);

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the watchdog ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(array(11, 12), $result['asked'], 'the day-old and the out-of-attempts recordings');

	// Each row is moved on its own, under the condition it was read with,
	// so the UPDATE tells whether this watchdog took it.
	$joined = implode("\n", $result['writes']);

	foreach (array('1', '2', '3') as $id) {
		pg_assert_contains("id = '" . $id . "'", $joined, 'row ' . $id . ' moved on its own');
	}
}
