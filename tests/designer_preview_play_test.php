<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's Preview as a prototype (assets/js/style_designer.js):
 * _sdPreviewPlayMap() names the open tabs a Preview link plays, and
 * _sdPreviewPlayHarness() writes a script that parses and keeps page names
 * from closing its own tag.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One function of the visual editor's source, or ''.
function _test_preview_play_js_function($name)
{
	$js = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.js');
	$start = strpos($js, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($js, "\n    function ", $start + 1);
	return substr($js, $start, ($end === false ? strlen($js) : $end) - $start);
}

// Runs `$steps` after the two functions and the stubs in Node; the JSON it
// printed, or null without Node.
function _test_preview_play_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$map = _test_preview_play_js_function('_sdPreviewPlayMap');
	$harness = _test_preview_play_js_function('_sdPreviewPlayHarness');
	pg_assert_true(($map !== '') && ($harness !== ''), 'the Preview play helpers are found');

	$script = 'var _pages = [], _activeKey = "", typed = null;'
		. 'var document = { getElementById: function (id) { return (id === "sd-page-name" && typed !== null) ? { value: typed } : null; } };'
		. 'function _sdT(s) { return s; }'
		. $map . "\n" . $harness . "\n" . $steps;
	$file = tempnam(sys_get_temp_dir(), 'pgpreviewplay');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(json_last_error() === JSON_ERROR_NONE, 'Node printed JSON: ' . $printed);
	return $answer;
}

// Typed names win over saved ones, the active tab reads the name box, the
// home tab answers the site address, a locked tab plays nothing, and names
// compare without case.
function test_preview_play_map_names_the_open_tabs()
{
	$answer = _test_preview_play_run(
		'_pages = ['
		. '{ key: "a", page_name: "About-Us", savedName: "about", page_home: 1 },'
		. '{ key: "b", page_name: "about", savedName: "" },'
		. '{ key: "c", page_name: "", savedName: "" },'
		. '{ key: "d", page_name: "secret", savedName: "secret", access: "locked" },'
		. '{ key: "e", page_name: "old", savedName: "old" }];'
		. '_activeKey = "e"; typed = " Contact ";'
		. 'console.log(JSON.stringify(_sdPreviewPlayMap()));'
	);
	if ($answer === null) {
		return;
	}
	pg_assert_same(array('about-us' => 'a', 'about' => 'b', 'contact' => 'e', 'old' => 'e', '' => 'a'), $answer);
}

// The script parses as JavaScript and no page name can end its <script>.
function test_preview_play_harness_is_one_script()
{
	$answer = _test_preview_play_run(
		'_pages = [{ key: "a", page_name: "x</script><b>", savedName: "" }]; _activeKey = "a";'
		. 'var h = _sdPreviewPlayHarness("https://example.test/");'
		. 'var body = h.replace(/^\s*<script id="pg-preview-play">/, "").replace(/<\/script>\s*$/, "");'
		. 'var ok = true; try { new Function(body); } catch (e) { ok = String(e); }'
		. 'console.log(JSON.stringify({ tags: (h.match(/<\/script/gi) || []).length, parses: ok }));'
	);
	if ($answer === null) {
		return;
	}
	pg_assert_same(array('tags' => 1, 'parses' => true), $answer);
}
