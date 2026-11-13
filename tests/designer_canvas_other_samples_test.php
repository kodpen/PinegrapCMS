<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer canvas's samples of the comments, calendar, Google
 * reviews, booking, accessibility, ad, menu and language widgets and for a
 * loop row's place (style_designer.js, run under Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the part of the editor from _SD_REGION_SAMPLES to _sdLoopPosition()
// with $script after it; null when Node is not there.
function _canvas_other_samples_run($script)
{
	if (!function_exists('exec')) return null;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return null;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$a = strpos($js, "\n    var _SD_REGION_SAMPLES = {");
	pg_assert_true($a !== false, '_SD_REGION_SAMPLES is found');
	$b = strpos($js, "\n    function _sdLoopPosition(", $a);
	pg_assert_true($b !== false, '_sdLoopPosition is found');
	$b = strpos($js, "\n    }\n", $b) + 6;
	$stubs = "function _sdT(s, v) { v = v || []; return String(s).replace(/\\{var(?::(\\d+))?\\}/g, function (m, n) { return v[(n || 1) - 1]; }); }\n"
		. "function _sdMoney(n) { return String(n); }\nfunction _sdDateSample() { return 'DAY'; }\nfunction _sdSiteWord() { return ''; }\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgother');
	if ($file === false) return null;
	file_put_contents($file, $stubs . substr($js, $a, $b - $a) . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	return json_decode($printed, true);
}

// The card is row 1, the last sample row the last; odd and even alternate.
function test_canvas_loop_position()
{
	$got = _canvas_other_samples_run("console.log(JSON.stringify([_sdLoopPosition(1, 3), _sdLoopPosition(3, 3), _sdLoopPosition(1, 1)]));\n");
	if ($got === null) return;
	pg_assert_same(array('__loop_index' => '1', '__loop_count' => '3', '__loop_is_first' => '1', '__loop_is_last' => '',
		'__loop_is_odd' => '1', '__loop_is_even' => ''), $got[0]);
	pg_assert_same(array('__loop_index' => '3', '__loop_count' => '3', '__loop_is_first' => '', '__loop_is_last' => '1',
		'__loop_is_odd' => '1', '__loop_is_even' => ''), $got[1]);
	pg_assert_same('1', $got[2]['__loop_is_last'], 'a card alone is the last row too');
}

// Every kind's sample answers, written as the page writes it: the stars
// match the rating, the counts the rows, the current language is active
// on the card alone, and only the all-day flag is empty.
function test_canvas_region_samples()
{
	$got = _canvas_other_samples_run("var c = _sdRegionSample('comments'), e = _sdRegionSample('calendar_view'), l = _sdRegionSample('language_switcher');\n"
		. "var rows = _SD_REGION_LOOP_ROWS.language_switcher(), empty = [];\n"
		. "Object.keys(_SD_REGION_SAMPLES).forEach(function (k) { var m = _sdRegionSample(k); Object.keys(m).forEach(function (t) { if (m[t] === '') empty.push(t); }); });\n"
		. "console.log(JSON.stringify([c.__comment_stars_text.length === +c.__comment_rating, c.__comment_anchor === 'c-' + c.__comment_id,\n"
		. "  JSON.stringify(_sdRegionSample('comments_recent')) === JSON.stringify(c), e === _sdRegionSample('calendar_view') && e.__event_month === undefined,\n"
		. "  +l.__language_count === rows.length + 1, l.__language_active_class === 'active' && rows.every(function (r) { return r.__language_active_class === ''; }),\n"
		. "  _sdRegionSample('nothing'), empty]));\n");
	if ($got === null) return;
	pg_assert_same(array(true, true, true, true, true, true, null, array('__event_is_all_day', '__event_is_all_day')), $got);
}
