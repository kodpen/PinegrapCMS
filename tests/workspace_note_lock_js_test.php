<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the notes lock on the My Notes screen
 * (assets/js/workspace_note_lock.js, window.PGWsNoteLock.pure): which notes
 * are behind the lock, the PIN's shape, when an open lock runs out and the
 * minutes of a pause; and that the script, its texts and its hooks are in
 * place. The Node parts are skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $body against the script in Node and answers what it printed as JSON;
// null when Node cannot run.
function workspace_note_lock_js_test_node($body)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_note_lock.js') . "\n"
		. "var P = window.PGWsNoteLock.pure;\n"
		. 'console.log(JSON.stringify((function () {' . $body . '}())));';

	$file = @tempnam(sys_get_temp_dir(), 'pglock');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_workspace_note_lock_js_behind()
{
	$result = workspace_note_lock_js_test_node('
		return [
			P.behind({ locked: true }),
			P.behind({ lock_group: true }),
			P.behind({ locked: false, lock_group: false }),
			P.behind({}),
			P.behind(null)
		];
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, true, false, false, false), $result, 'an own locked note or one in a locked group');
}

function test_workspace_note_lock_js_pin_shape()
{
	$result = workspace_note_lock_js_test_node('
		return ["1234", "12345678", "123", "123456789", "12a4", "", null, 1234, " 1234"].map(P.pinOk);
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, true, false, false, false, false, false, true, false), $result, '4 to 8 digits, as the server takes them');
}

function test_workspace_note_lock_js_expired()
{
	$result = workspace_note_lock_js_test_node('
		var minute = 60000;
		return {
			five_in: P.expired(5, 0, 4 * minute),
			five_out: P.expired(5, 0, 5 * minute),
			every_time: P.expired(P.EVERY_TIME, 0, 999 * minute),
			sign_out: P.expired(P.UNTIL_SIGN_OUT, 0, 99999 * minute),
			text: P.expired("1", 0, minute)
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(false, $result['five_in'], 'used within the time: still open');
	pg_assert_same(true, $result['five_out'], 'the time passed with nothing done: shut');
	pg_assert_same(false, $result['every_time'], 'every time: the server asks for any other note');
	pg_assert_same(false, $result['sign_out'], 'until sign-out never runs out here');
	pg_assert_same(true, $result['text'], 'minutes as text count too');
}

function test_workspace_note_lock_js_wait_minutes()
{
	$result = workspace_note_lock_js_test_node('return [P.waitMinutes(0), P.waitMinutes(59), P.waitMinutes(60), P.waitMinutes(61), P.waitMinutes(900)];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(1, 1, 1, 2, 15), $result, 'whole minutes, rounded up, at least one');
}

function test_workspace_note_lock_js_bundled_with_texts()
{
	$bundles = pg_js_bundles();
	$list = $bundles['assets/js/workspace.min.js'];
	$lock = array_search('assets/js/workspace_note_lock.js', $list, true);

	pg_assert_true($lock !== false, 'the lock script is bundled');
	pg_assert_true($lock < array_search('assets/js/workspace.js', $list, true), 'before workspace.js');

	$strings = ws_note_lock_js_strings();
	preg_match_all("/kit\\.t\\('(nlock_[a-z_]+)'/", file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_note_lock.js'), $used);
	preg_match_all("/t\\('(nlock_[a-z_]+)'/", file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), $used_screen);

	foreach (array_unique(array_merge($used[1], $used_screen[1])) as $key) {
		pg_assert_true(isset($strings[$key]), 'the text ' . $key . ' is sent to the screen');
	}
}
