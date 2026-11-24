<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel's "New" line in workspace.js: newLineAt() places it
 * over the first message after the read mark the screen opened with and
 * counts the messages under it, from what was loaded at the opening only.
 * The Node part is skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent; '' when it is not there.
function workspace_new_line_test_function($name)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));

	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', $source, $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_new_line_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgnewline');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_new_line_at()
{
	$at = workspace_new_line_test_function('newLineAt');

	pg_assert_true($at !== '', 'newLineAt() is in workspace.js');

	$result = workspace_new_line_test_node($at . '
function m(id, extra) {
	var message = { id: id, kind: "message", mine: false, deleted: false };
	Object.keys(extra || {}).forEach(function (key) { message[key] = extra[key]; });
	return message;
}
var list = [m(10), m(11), m(12, { mine: true }), m(13, { kind: "system" }), m(14), m(15, { deleted: true }), m(16)];
console.log(JSON.stringify({
	plain: newLineAt(list, 11, 16, true),
	ownFirst: newLineAt(list, 11, 13, true),
	upto: newLineAt(list, 11, 14, true),
	later: newLineAt(list, 13, 20, true),
	allRead: newLineAt(list, 16, 16, true),
	ownOnly: newLineAt([m(10), m(11, { mine: true })], 10, 11, true),
	noMark: newLineAt(list, 0, 16, true),
	era: newLineAt(list, -1, 16, true),
	cut: newLineAt(list, 5, 16, false),
	reaches: newLineAt(list, 10, 16, false),
	empty: newLineAt([], 10, 16, true)
}));
');

	if ($result === null) {
		pg_assert_true(true, 'Node is not here; the drawing is checked where it runs');
		return;
	}

	pg_assert_same(array('id' => 14, 'count' => 2), $result['plain'], 'over the first message of someone else after the mark, counting those under it');
	pg_assert_same(null, $result['ownFirst'], 'the reader\'s own message and a system line are passed over');
	pg_assert_same(array('id' => 14, 'count' => 1), $result['upto'], 'a message that came after the opening is not counted');
	pg_assert_same(array('id' => 14, 'count' => 2), $result['later'], 'a later mark moves the line down');
	pg_assert_same(null, $result['allRead'], 'nothing after the mark, no line');
	pg_assert_same(null, $result['ownOnly'], 'only the reader\'s own messages after the mark, no line');
	pg_assert_same(null, $result['noMark'], 'no read mark (not a member), no line');
	pg_assert_same(null, $result['era'], 'no read mark (an earlier version), no line');
	pg_assert_same(null, $result['cut'], 'the loaded messages begin after the mark: the line\'s place is not loaded');
	pg_assert_same(array('id' => 11, 'count' => 3), $result['reaches'], 'older messages not loaded but the mark itself is: the line stands');
	pg_assert_same(null, $result['empty'], 'no messages, no line');
}
