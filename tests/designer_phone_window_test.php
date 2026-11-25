<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Preview on a phone" window (assets/js/style_designer_phone.js,
 * the designer part 'phone'): the time left in words, which state an answer
 * of prototype_phone draws, and that the window is a part opened from the
 * Preview menu with no button of its own in the bars. The pure functions run
 * under Node; skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_phone_window_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_phone.js');
}

// One function of the part, from its declaration to its closing brace.
function designer_phone_window_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs `$call` under Node after the part's functions named, with an _sdT
// that fills {var} and {var:n}. Null where there is no Node.
function designer_phone_window_node($names, $call)
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

	$js = designer_phone_window_source();
	$src = "function _sdT(s, v) { if (Array.isArray(v)) { v.forEach(function (x, i) { s = s.replace('{var:' + (i + 1) + '}', x); }); return s; } return v === undefined ? s : s.replace('{var}', v); }\n";

	foreach ($names as $name) {
		$fn = designer_phone_window_fn($js, $name);
		pg_assert_true($fn !== '', 'the part has ' . $name . '()');
		$src .= $fn . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pgphw');
	file_put_contents($file, $src . 'console.log(JSON.stringify(' . $call . '));' . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_designer_phone_window_says_the_time_left_in_words()
{
	$res = designer_phone_window_node(array('_phLeftWords'), '[28680, 28800, 2520, 59, 0, -5, 3600, 61].map(_phLeftWords)');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('7 h 58 min', '8 h', '42 min', 'less than a minute', '', '', '1 h', '1 min'), $res);
}

function test_designer_phone_window_draws_the_state_the_answer_asks_for()
{
	$ok = array('status' => 'success', 'link' => array('id' => 7), 'url' => 'https://x/prototype.php?link=t', 'qr' => '<svg></svg>');
	$cases = array(
		array($ok, 28000),
		array($ok, 0),
		array(array('status' => 'success', 'team_only' => true), 0),
		array(array('status' => 'error', 'message' => 'Permission denied.'), 0),
		array(array('status' => 'success', 'link' => array('id' => 7), 'url' => 'https://x', 'qr' => ''), 28000),
		array(null, 0),
	);
	$res = designer_phone_window_node(array('_phView'), json_encode($cases) . '.map(function (c) { return _phView(c[0], c[1]); })');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('ready', 'ended', 'team', 'failed', 'failed', 'failed'), $res);
}

function test_designer_phone_window_is_a_part_opened_from_the_preview_menu()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_phone.min.js', isset($parts['phone']) ? $parts['phone'] : '', 'the window is the part phone');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_same('phone', pg_designer_part_files()['phone']['when'], 'handed to those who make share links');
	pg_assert_contains("'phone'        => \$sd_phone,", $screen, 'the phone condition on the screen');
	pg_assert_false(strpos($screen, 'sd-vb-phone'), 'no button of its own in the page bar');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($core, "_sdPartCall('phone', 'open', [_sdPhoneHost()])") !== false, 'the core opens it through the part loader');
	pg_assert_same(1, substr_count($core, 'id="sd-vb-phone"'), 'one row, in the Preview menu');

	// The code is drawn by the server: the part asks no image address.
	$part = designer_phone_window_source();
	pg_assert_false(strpos($part, '<img'), 'no image address for the code');
	pg_assert_false(strpos($part, 'localStorage'), 'the address is kept nowhere');
}
