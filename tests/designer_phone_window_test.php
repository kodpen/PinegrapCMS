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

// A failed answer says why: the server's message where it gave one, and
// "Try again" only where trying again can help (no answer, a malformed one,
// or a link the server could not store, marked `retry`). A design with no
// draft pages gets its reason and no button.
function test_designer_phone_window_says_why_no_link_was_made()
{
	$cases = array(
		null,
		array('status' => 'error', 'message' => 'This design has no draft pages.'),
		array('status' => 'error', 'message' => 'Permission denied.'),
		array('status' => 'error', 'message' => 'Sorry, we could not accept your request.', 'retry' => true),
		array('status' => 'error'),
		array('status' => 'success', 'link' => array('id' => 7), 'url' => 'https://x', 'qr' => ''),
	);
	$res = designer_phone_window_node(array('_phFailure'), json_encode($cases) . '.map(_phFailure)');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('text' => 'Nothing was changed.', 'again' => true),
		array('text' => 'This design has no draft pages.', 'again' => false),
		array('text' => 'Permission denied.', 'again' => false),
		array('text' => 'Sorry, we could not accept your request.', 'again' => true),
		array('text' => 'Nothing was changed.', 'again' => false),
		array('text' => 'Nothing was changed.', 'again' => true),
	), $res);

	// Only the link the server could not store is marked to be tried again.
	$server = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/prototype_phone.php');
	pg_assert_same(1, substr_count($server, "'retry' => true"), 'one answer offers a retry');

	// With no link the foot shows no buttons that look pressable.
	$draw = designer_phone_window_fn(designer_phone_window_source(), '_phDraw');
	pg_assert_contains('foot.hidden = ', $draw, 'the foot is hidden while there is no link');
}

// A design with no draft pages gets the code of its live page: the window
// draws the live state, with "Copy link" and no countdown or "End now", as
// no share link stands behind it.
function test_designer_phone_window_draws_the_live_page_with_no_end_or_countdown()
{
	$live = array('status' => 'success', 'live' => true, 'url' => 'https://x/about', 'qr' => '<svg></svg>', 'start_page' => 'About');
	$cases = array(
		array($live, 0),
		array(array_merge($live, array('qr' => '')), 0),
		array(array_merge($live, array('url' => '')), 0),
	);
	$res = designer_phone_window_node(array('_phView'), json_encode($cases) . '.map(function (c) { return _phView(c[0], c[1]); })');

	if ($res !== null) {
		pg_assert_same(array('live', 'failed', 'failed'), $res);

		$foot = designer_phone_window_node(array('_phFoot'), '(function () { esc = function (s) { return s; }; _phNarrow = function () { return false; }; return [_phFoot(true, true), _phFoot(true)]; })()');
		pg_assert_contains('data-ph="copy"', $foot[0], 'the live page\'s address is copied');
		pg_assert_false(strpos($foot[0], 'data-ph="end"'), 'nothing to end for the live page');
		pg_assert_contains('data-ph="end"', $foot[1], 'a share link keeps End now');
	}

	// The host names the page on screen once it is saved, a page on the site
	// too: the live code opens it, and the server takes a draft link's start
	// only from a draft.
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$host = substr($core, strpos($core, 'function _sdPhoneHost()'), 600);
	pg_assert_contains('id: saved ? p.page_id : 0', $host, 'a saved page is named, draft or not');

	$draw = designer_phone_window_fn(designer_phone_window_source(), '_phDraw');
	$at = strpos($draw, "state.view === 'live'");
	pg_assert_true($at !== false, 'the window draws the live state');
	$next = strpos($draw, '} else if', $at + 1);
	$branch = ($next === false) ? substr($draw, $at) : substr($draw, $at, $next - $at);
	pg_assert_contains('opens the live page', $branch, 'the window says the code opens the live page');
	pg_assert_false(strpos($branch, '_phStartsLine'), 'no countdown');
	pg_assert_false(strpos($branch, '_phInfoLine'), 'no line about saves');
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
