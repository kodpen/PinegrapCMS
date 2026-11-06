<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the designer's Ctrl+G exit goes
 * (assets/js/style_designer.src.js, window._sdExitDesigner): only to the
 * exit address the server checked (_design.exitUrl, pg_safe_back_url()),
 * never to a send_to read from the address bar. Runs the handler under Node
 * on a fake window; skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs window._sdExitDesigner() with $search as the address bar's query
// string and $exit_url as the server's exit address; prints the address
// _pgExit() was handed, or "back" when it went back in history. Null
// without Node.
function designer_exit_target_node($search, $exit_url)
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

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = '        if (!window._sdExitDesignerBound) {';
	$to = '            document.addEventListener(\'keydown\', function (e) {' . "\n" . '                if (!(e.ctrlKey || e.metaKey) || e.shiftKey) return;';
	$a = strpos($js, $from);
	$b = ($a !== false) ? strpos($js, $to, $a) : false;

	if ($b === false) {
		return array('error' => 'the exit handler of style_designer.js was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgexit');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var result = null;' . "\n"
		. 'var window = { location: { search: ' . json_encode($search) . ', href: "" }, history: { length: 2, back: function () { result = "back"; } } };' . "\n"
		. 'var _design = ' . json_encode(($exit_url === null) ? null : array('exitUrl' => $exit_url)) . ';' . "\n"
		. 'var _suppressUnloadWarn = false;' . "\n"
		. 'function _pgExit(url) { result = url; }' . "\n"
		. 'function isDirty() { return false; }' . "\n"
		. 'function _sdT(s) { return s; }' . "\n"
		. '(function () {' . "\n" . substr($js, $a, $b - $a) . "\n" . '}})();' . "\n"
		. 'window._sdExitDesigner();' . "\n"
		. 'console.log(JSON.stringify({ target: result }));');

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A send_to in the address bar that the server refused (another host, a
// backslash taken as a slash, a script address behind a space or a tab) is
// not followed: the exit goes to the address the server checked.
function test_designer_exit_ignores_address_bar_send_to()
{
	$hostile = array(
		'https://evil.example/x',
		'/\\evil.example/x',
		'\\\\evil.example/x',
		' javascript:alert(1)',
		"java\tscript:alert(1)",
	);

	foreach ($hostile as $send_to) {
		$result = designer_exit_target_node('?id=4&send_to=' . rawurlencode($send_to), 'view_pages.php');

		if ($result === null) {
			return;
		}

		pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
		pg_assert_same('view_pages.php', $result['target'], 'exit for send_to ' . json_encode($send_to));
	}
}

// A same-site send_to reaches the exit through the server's exit address,
// unchanged; with none at all the exit goes back in history.
function test_designer_exit_uses_server_exit_address()
{
	$result = designer_exit_target_node('?id=4&send_to=%2Furun%2Fcay', '/urun/cay');

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same('/urun/cay', $result['target'], 'checked send_to');

	$result = designer_exit_target_node('?id=4', null);
	pg_assert_same('back', $result['target'], 'no exit address');
}
