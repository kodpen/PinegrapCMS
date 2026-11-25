<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pure rules of Compare's Look tab (assets/js/pg_compare_look.js,
 * window.PgCompareLook): the stored tree out of a page's baseline, the
 * remembered form and width, what the window shows for each reader state,
 * the handle's keys, the scale, the sides and their chooser values, the
 * foot's sentence and the base of a server-drawn picture. Runs in Node;
 * skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PgCompareLook loaded as L; its `out`
// comes back decoded. Null when Node cannot run.
function compare_look_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = PG_FUNCTIONS_DIR . '/assets/js/pg_compare_look.js';
	pg_assert_true(is_file($file), 'pg_compare_look.js exists');

	if (!is_file($file)) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents($file) . "\n"
		. "var L = window.PgCompareLook, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$tmp = @tempnam(sys_get_temp_dir(), 'pgcl');

	if ($tmp === false) {
		return null;
	}

	file_put_contents($tmp, $script);
	$out = array();
	@exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $status);
	@unlink($tmp);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The baseline is the tree's JSON, then the settings: a "|" inside a text
// of the tree does not cut it.
function test_compare_look_live_tree()
{
	$res = compare_look_test_node('var t = { _id: "r", type: "root", props: { text: "a|b" }, children: [] };'
		. ' out = [L.liveTree(JSON.stringify(t) + "|{\"a\":1}|{}"), L.liveTree("null|{}|{}"), L.liveTree(""), L.liveTree(undefined), L.liveTree("{broken|x")];');

	if ($res === null) {
		return;
	}

	pg_assert_same('a|b', $res[0]['props']['text'], 'a | in a text stays in the tree');
	pg_assert_same('r', $res[0]['_id'], 'the tree is the part before the settings');
	pg_assert_same(null, $res[1], 'no stored tree');
	pg_assert_same(null, $res[2], 'an empty baseline');
	pg_assert_same(null, $res[3], 'no baseline');
	pg_assert_same(null, $res[4], 'a broken baseline');
}

// The remembered form and width; anything else falls back to the defaults.
function test_compare_look_prefs()
{
	$res = compare_look_test_node('out = [L.prefs(null), L.prefs("{\"form\":\"side\",\"width\":\"phone\"}"), L.prefs("{\"form\":\"x\",\"width\":7}"), L.prefs("nope"),'
		. ' L.prefsText({ form: "side", width: "phone", pos: 30 })];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('form' => 'slider', 'width' => 'desktop'), $res[0], 'nothing stored: the slider at desktop width');
	pg_assert_same(array('form' => 'side', 'width' => 'phone'), $res[1], 'what was chosen last');
	pg_assert_same(array('form' => 'slider', 'width' => 'desktop'), $res[2], 'unknown values');
	pg_assert_same(array('form' => 'slider', 'width' => 'desktop'), $res[3], 'not JSON');
	pg_assert_same('{"form":"side","width":"phone"}', $res[4], 'the handle\'s place is not kept');
}

// What the window shows for each state of the reader, and in a narrow window.
function test_compare_look_layout()
{
	$res = compare_look_test_node('var p = { form: "side", width: "desktop" };'
		. ' out = { changed: L.layout("changed", p, false), slider: L.layout("changed", { form: "slider", width: "phone" }, false),'
		. ' fresh: L.layout("new", p, false), same: L.layout("same", p, false), unreadable: L.layout("unreadable", p, false),'
		. ' narrow: L.layout("changed", p, true), narrowNew: L.layout("new", p, true) };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('form' => 'side', 'width' => 'desktop', 'only' => '', 'handle' => false, 'forms' => true, 'widths' => true, 'note' => ''), $res['changed'], 'side by side: two pictures, no handle');
	pg_assert_same(array('form' => 'slider', 'width' => 'phone', 'only' => '', 'handle' => true, 'forms' => true, 'widths' => true, 'note' => ''), $res['slider'], 'the slider at a phone width');
	pg_assert_same(array('form' => 'slider', 'width' => 'desktop', 'only' => 'after', 'handle' => false, 'forms' => false, 'widths' => true, 'note' => 'new'), $res['fresh'], 'a page never on the site: the draft alone, the width chooser stays');
	pg_assert_same(array('form' => 'slider', 'width' => 'desktop', 'only' => 'before', 'handle' => false, 'forms' => false, 'widths' => true, 'note' => 'same'), $res['same'], 'nothing changed: one picture');
	pg_assert_same(array('form' => 'side', 'width' => 'desktop', 'only' => '', 'handle' => false, 'forms' => true, 'widths' => true, 'note' => ''), $res['unreadable'], 'an unreadable list costs the list only');
	pg_assert_same(array('form' => 'slider', 'width' => 'phone', 'only' => '', 'handle' => true, 'forms' => false, 'widths' => false, 'note' => ''), $res['narrow'], 'narrow: the slider alone at a phone width');
	pg_assert_same('after', $res['narrowNew']['only'], 'narrow and new: the draft alone');
	pg_assert_same(false, $res['narrowNew']['handle'], 'narrow and new: no handle');
}

// The handle's keys, its clamp and the scale of a picture in its room.
function test_compare_look_handle_and_scale()
{
	$res = compare_look_test_node('out = { keys: [L.key(50, "ArrowLeft", false), L.key(50, "ArrowRight", true), L.key(1, "ArrowLeft", false), L.key(99, "ArrowRight", true),'
		. ' L.key(40, "Home", false), L.key(40, "End", false), L.key(40, "a", false)],'
		. ' clamp: [L.clamp(-5), L.clamp(105), L.clamp("x"), L.clamp(33.3)],'
		. ' widths: [L.width("desktop"), L.width("phone"), L.width("x")],'
		. ' scale: [L.scale(640, 1280), L.scale(2000, 1280), L.scale(0, 390), L.scale(300, 390)] };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(48, 60, 0, 100, 0, 100, null), $res['keys'], 'arrows move by 2, with Shift by 10; Home and End; any other key is not the handle\'s');
	pg_assert_same(array(0, 100, 50, 33.3), $res['clamp'], 'the place stays between the two ends');
	pg_assert_same(array(1280, 390, 1280), $res['widths'], 'desktop 1280 px, phone 390 px');
	pg_assert_same(array(0.5, 1, 1, 300 / 390), $res['scale'], 'scaled down to the room, never up');
}

// A side and its chooser value; the chooser's values from the revisions.
function test_compare_look_sides()
{
	$res = compare_look_test_node('out = { parse: [L.side("now"), L.side("live"), L.side("rev:14"), L.side("rev:0"), L.side("x"), L.side({ kind: "rev", no: 3 })],'
		. ' value: [L.sideValue({ kind: "rev", no: 14 }), L.sideValue({ kind: "live" }), L.sideValue(null)],'
		. ' values: [L.sideValues([{ revision_no: 14 }, { revision_no: 13 }], true, true), L.sideValues([], false, false)],'
		. ' same: [L.sameSide({ kind: "rev", no: 2 }, { kind: "rev", no: 2 }), L.sameSide({ kind: "live" }, { kind: "now" })] };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('kind' => 'now', 'no' => 0), $res['parse'][0], 'the page now');
	pg_assert_same(array('kind' => 'live', 'no' => 0), $res['parse'][1], 'the page on the site');
	pg_assert_same(array('kind' => 'rev', 'no' => 14), $res['parse'][2], 'a revision');
	pg_assert_same(null, $res['parse'][3], 'no revision 0');
	pg_assert_same(null, $res['parse'][4], 'an unknown side');
	pg_assert_same(array('kind' => 'rev', 'no' => 3), $res['parse'][5], 'a side object');
	pg_assert_same(array('rev:14', 'live', ''), $res['value'], 'chooser values');
	pg_assert_same(array('now', 'live', 'rev:14', 'rev:13'), $res['values'][0], 'the page now, on the site, then the revisions newest first');
	pg_assert_same(array(), $res['values'][1], 'a page not open and never on the site, no revisions');
	pg_assert_same(array(true, false), $res['same'], 'the same side twice');
}

// The foot's sentence for each state and count.
function test_compare_look_foot()
{
	$res = compare_look_test_node('out = [L.foot("changed", 7, true), L.foot("changed", 1, true), L.foot("changed", 3, false), L.foot("changed", 1, false),'
		. ' L.foot("same", 0, true), L.foot("new", 0, true), L.foot("unreadable", 0, true)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('live_many', 'live_one', 'many', 'one', 'same', 'new', 'unreadable'), $res, 'a sentence code per state and count');
}

// A picture drawn by the server is shown in a frame of the panel's origin:
// its own addresses must resolve against the site, so a base is put first in
// its head, and only when it has none.
function test_compare_look_with_base()
{
	$res = compare_look_test_node('var b = "https://ex.test/site/";'
		. ' out = [L.withBase("<!DOCTYPE html><html><head><title>x</title></head><body></body></html>", b),'
		. ' L.withBase("<html><HEAD lang=\"en\"><base href=\"/a/\"></HEAD></html>", b),'
		. ' L.withBase("<p>no head</p>", b), L.withBase("<head></head>", "\"><script>")];');

	if ($res === null) {
		return;
	}

	pg_assert_same('<!DOCTYPE html><html><head><base href="https://ex.test/site/"><title>x</title></head><body></body></html>', $res[0], 'the base first in the head');
	pg_assert_same('<html><HEAD lang="en"><base href="/a/"></HEAD></html>', $res[1], 'a page with its own base keeps it');
	pg_assert_same('<base href="https://ex.test/site/"><p>no head</p>', $res[2], 'no head: the base first');
	pg_assert_same('<head><base href="&quot;&gt;&lt;script&gt;"></head>', $res[3], 'the base is escaped');
}
