<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Check all cases" of the designer's Preview (part 'previewas'):
 * the cases a run draws, what counts as a text leaving its box or a missing
 * picture, the list's head and foot, and the core hooks that draw one case
 * without keeping it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function preview_as_check_test_source($path)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path));
}

// One four-space function of the part, from its declaration to its closing
// brace.
function preview_as_check_test_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the named functions of the part and returns its
// JSON output; null without Node or when a function is missing.
function preview_as_check_test_run($names, $script)
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

	$js = preview_as_check_test_source('assets/js/style_designer_previewas.js');
	$src = 'var _sdT = function (s, a) { return String(s).replace(/\{var:(\d)\}/g, function (m, i) { return a[i - 1]; }); };' . "\n"
		. "var CASES = ['typical', 'empty', 'long', 'sold_out', 'error'];\n";

	foreach ($names as $name) {
		$fn = preview_as_check_test_fn($js, $name);

		if (!pg_assert_true($fn !== '', 'the part has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgpvacheck');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// Each case of the page for each visitor the page has a rule for; a page
// with one case runs nothing.
function test_preview_as_check_runs_each_case_for_each_visitor()
{
	$res = preview_as_check_test_run(array('checkRuns'),
		'console.log(JSON.stringify({'
		. ' plain: checkRuns(["typical", "empty", "long"], false),'
		. ' audience: checkRuns(["typical", "error"], true),'
		. ' one: checkRuns(["typical"], false)'
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('case' => 'typical', 'who' => 'signed_out', 'visit' => 'first_visit'),
		array('case' => 'empty', 'who' => 'signed_out', 'visit' => 'first_visit'),
		array('case' => 'long', 'who' => 'signed_out', 'visit' => 'first_visit'),
	), $res['plain']);
	pg_assert_same(6, count($res['audience']), 'two cases for three visitors');
	pg_assert_same(array('case' => 'error', 'who' => 'signed_in', 'visit' => 'first_visit'), $res['audience'][3]);
	pg_assert_same(array('case' => 'typical', 'who' => 'signed_out', 'visit' => 'returning'), $res['audience'][4]);
	pg_assert_same(array(), $res['one'], 'one case: nothing to check');
}

// A text leaves its box when it runs past its own box or the window; a
// scroller, a cut with an ellipsis and an inline box are not findings.
function test_preview_as_check_text_leaves_its_box()
{
	$res = preview_as_check_test_run(array('textLeaves'),
		'var m = function (o) { var b = { display: "block", overflowX: "visible", overflowY: "visible", textOverflow: "clip", sw: 200, cw: 200, sh: 40, ch: 40, left: 100, right: 300, viewW: 742 };'
		. ' for (var k in o) b[k] = o[k]; return textLeaves(b); };'
		. 'console.log(JSON.stringify({'
		. ' fits: m({}),'
		. ' wide: m({ sw: 260 }),'
		. ' subpixel: m({ sw: 201 }),'
		. ' window: m({ right: 760 }),'
		. ' cutTall: m({ overflowY: "hidden", sh: 90 }),'
		. ' tallVisible: m({ sh: 90 }),'
		. ' scroller: m({ overflowX: "auto", sw: 400 }),'
		. ' ellipsis: m({ overflowX: "hidden", textOverflow: "ellipsis", sw: 400 }),'
		. ' inline: m({ display: "inline", sw: 400, cw: 0 }),'
		. ' hidden: m({ cw: 0, ch: 0, sw: 0, right: 0 }),'
		. ' slide: m({ left: 800, right: 1100 })'
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_false($res['fits']);
	pg_assert_true($res['wide'], 'wider than its box');
	pg_assert_false($res['subpixel'], 'a pixel of rounding is no finding');
	pg_assert_true($res['window'], 'past the window\'s right edge');
	pg_assert_true($res['cutTall'], 'cut at the bottom');
	pg_assert_false($res['tallVisible'], 'a box with no height limit grows');
	pg_assert_false($res['scroller'], 'a scroller is meant to scroll');
	pg_assert_false($res['ellipsis'], 'an ellipsis is a chosen cut');
	pg_assert_false($res['inline'], 'its block is measured');
	pg_assert_false($res['hidden'], 'not drawn');
	pg_assert_false($res['slide'], 'a slide waiting beyond the window');
}

// A picture is missing when it has no address or its file could not be
// read; one still loading lazily is not judged.
function test_preview_as_check_picture_missing()
{
	$res = preview_as_check_test_run(array('pictureMissing'),
		'var m = function (o) { var b = { src: "a.jpg", complete: true, natural: 640, lazy: false, shown: true };'
		. ' for (var k in o) b[k] = o[k]; return pictureMissing(b); };'
		. 'console.log(JSON.stringify({'
		. ' ok: m({}), broken: m({ natural: 0 }), none: m({ src: "", complete: false, natural: 0 }),'
		. ' lazy: m({ lazy: true, complete: false, natural: 0 }), loading: m({ complete: false, natural: 0 }),'
		. ' notShown: m({ natural: 0, shown: false })'
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_false($res['ok']);
	pg_assert_true($res['broken'], 'the file could not be read');
	pg_assert_true($res['none'], 'no address');
	pg_assert_false($res['lazy'], 'still waiting to be scrolled to');
	pg_assert_false($res['loading'], 'not loaded yet');
	pg_assert_false($res['notShown'], 'a hidden picture is no finding');
}

// The list's head, the line under it and its foot, as board P6 says.
function test_preview_as_check_head_and_foot()
{
	$res = preview_as_check_test_run(array('checkHead'),
		'var f = function (o) { var b = { total: 6, done: 6, found: 3, busy: false, stopped: false, width: 742 }; for (var k in o) b[k] = o[k]; return checkHead(b); };'
		. 'console.log(JSON.stringify({'
		. ' busy: f({ busy: true, done: 2, found: 1 }), busyMany: f({ busy: true, done: 4, found: 2 }),'
		. ' done: f({}), stopped: f({ stopped: true, done: 3 })'
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('head' => '1 case to look at so far', 'sub' => 'Checking 3 of 6…', 'foot' => 'Checking at 742px wide.'), $res['busy']);
	pg_assert_same('2 cases to look at so far', $res['busyMany']['head']);
	pg_assert_same(array('head' => '3 of 6 cases to look at', 'sub' => 'A row opens its case.', 'foot' => 'Checked at 742px wide.'), $res['done']);
	pg_assert_same('Stopped after 3 of 6.', $res['stopped']['head']);
}

// The run is the part's: the "Case" menu's last row and the narrow sheet's
// row start it, and the part answers the core's check().
function test_preview_as_check_is_in_the_part()
{
	$part = preview_as_check_test_source('assets/js/style_designer_previewas.js');
	pg_assert_contains("_sdT('Check all cases')", $part);
	pg_assert_contains('return { answer: answer, attach: attach, check: check, failed: failed, inject: inject, note: note, redraw: render };', $part);
	pg_assert_contains('K._sdPvAsAsk(', $part, 'each case is drawn without keeping it');
	pg_assert_contains('K._sdPvAsShow(', $part, 'and shown in the Preview\'s own frame');
}

// The core: a palette row marked "Preview", one case drawn without keeping
// it, and the live preview waiting while a run draws.
function test_preview_as_check_core_hooks()
{
	$core = preview_as_check_test_source('assets/js/style_designer.src.js');
	pg_assert_contains("id: 'act_check_cases', label: _sdT('Check all cases')", $core, 'the command palette row');

	$ask = substr($core, (int) strpos($core, 'function _sdPvAsAsk(c) {'), 1600);
	pg_assert_contains('_sdPvAsApply(_sdPreviewWidgetPayload(raw), c)', $ask, 'the case\'s choice, not the kept one');

	$live = substr($core, (int) strpos($core, 'function _sdLivePreviewRefresh() {'), 600);
	pg_assert_contains('if (_sdPvAs.running) { _sdLive.again = true; return; }', $live, 'no live redraw under a run');
}

// "Sold out" chooses the product itself, as "Long" does: the "Record"
// chooser is dimmed for a widget that has the case, and stays free for one
// that draws its chosen record anyway (a form item view has no sold out).
function test_preview_as_check_sold_out_chooses_the_record()
{
	$res = preview_as_check_test_run(array('recordSids', 'recordSet'),
		'var S = { setups: { 5: { source: "product", cases: ["typical", "long", "sold_out"] }, 9: { source: "", cases: ["typical", "empty", "long", "sold_out"] } } };'
		. 'var c = function (k) { return { case: k, records: {} }; };'
		. 'var out = { product: recordSet(c("sold_out")), productSid: recordSet(c("sold_out"), "5"), long: recordSet(c("long")), typical: recordSet(c("typical")) };'
		. 'S.setups = { 7: { source: "form", cases: ["typical", "long"] }, 9: { source: "", cases: ["typical", "empty", "long", "sold_out"] } };'
		. 'out.form = recordSet(c("sold_out")); out.formSid = recordSet(c("sold_out"), "7");'
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	pg_assert_true($res['product'], 'a product page under "Sold out"');
	pg_assert_true($res['productSid'], 'per widget too');
	pg_assert_true($res['long']);
	pg_assert_false($res['typical']);
	pg_assert_false($res['form'], 'a form item view draws its chosen record under "Sold out"');
	pg_assert_false($res['formSid']);

	// The answer forgets a kept record only where the record drawn is the
	// chosen one's to draw.
	$part = preview_as_check_test_source('assets/js/style_designer_previewas.js');
	$answer = preview_as_check_test_fn($part, 'answer');
	pg_assert_contains('!recordSet(c, sid)', $answer);
	pg_assert_false(strpos($answer, "c.case !== 'sold_out'") !== false, 'one rule for the cases that choose the record');
}

// "Check all cases" asked while a draw of the bar is still on its way
// waits for it: that answer would replace a case's document in the window
// half way through the run and the wrong page would be measured.
function test_preview_as_check_waits_for_a_draw_on_its_way()
{
	$part = preview_as_check_test_source('assets/js/style_designer_previewas.js');
	$check = preview_as_check_test_fn($part, 'check');
	pg_assert_contains('if (S.drawing) { S.waiting = true; render(); return; }', $check, 'a run waits for the draw');
	pg_assert_contains('S.waiting = false;', $check);
	$answer = preview_as_check_test_fn($part, 'answer');
	pg_assert_contains('if (S.waiting) check();', $answer, 'and starts once it has come');
	pg_assert_false(strpos($answer, 'attach(') !== false, 'on every path of the answer, a new document too (bindAnswer())');
	pg_assert_contains('if (S.waiting) check();', preview_as_check_test_fn($part, 'openResult'), 'a row opened from the list draws without an answer');
	$failed = preview_as_check_test_fn($part, 'failed');
	pg_assert_contains('S.waiting = false;', $failed, 'a failed draw starts no run');
}
