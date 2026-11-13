<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Components drawings of the visual editor's palette hover
 * card (_sdPalInfo() and _sdPalDrawing() in style_designer.src.js): the two
 * functions are cut out of the core and run under Node (skipped on a machine
 * without Node); every drawing key named in the info map draws a whole SVG
 * with marks in it, and every row of the Components tab (grid, Bootstrap
 * components, HTML elements, Content, Form fields, Lists in a record and
 * Regions) has a drawing key and a description.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A top-level function of the core, up to its four-space closing brace,
// asserted found.
function designer_pal_drawing_test_fn($head)
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	$start = strpos($js, '    ' . $head);
	$end = $start === false ? false : strpos($js, "\n    }\n", $start);
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . $head);
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start) . "\n    }\n";
}

// The list sources of the palette's Lists in a record rows.
function designer_pal_drawing_test_list_sources()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, "{ title: _sdT('Lists in a record')");
	$end = $start === false ? false : strpos($js, ']},', $start);
	pg_assert_true($start !== false && $end !== false, 'the palette holds the Lists in a record group');
	if ($start === false || $end === false) {
		return array();
	}
	preg_match_all("/listSource: '([a-z_.]+)'/", substr($js, $start, $end - $start), $m);
	return $m[1];
}

// Runs the info map and the drawings under Node; null without Node.
function designer_pal_drawing_test_run()
{
	static $got = false;
	if ($got !== false) {
		return $got;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		$got = null;
		return $got;
	}

	$script = "'use strict';\n"
		. "function _sdT(key) { return key; }\n"
		. "var _sdPalInfoMap = null;\n"
		. designer_pal_drawing_test_fn('function _sdPalInfo()')
		. designer_pal_drawing_test_fn('function _sdPalDrawing(key)')
		. designer_pal_drawing_test_fn('function _sdPalKey(it)')
		. 'var LIST_SOURCES = ' . json_encode(designer_pal_drawing_test_list_sources()) . ";\n"
		. <<<'JS'
var map = _sdPalInfo(), keys = Object.keys(map);
var noDrawing = [], broken = [], noDesc = [];
keys.forEach(function (k) {
    if (!map[k].d) noDesc.push(k);
    if (!map[k].v) { noDrawing.push(k); return; }
    var svg = _sdPalDrawing(map[k].v);
    var marks = (svg.match(/<(rect|circle|path|text)\b/g) || []).length;
    if (svg.indexOf('<svg ') !== 0 || svg.slice(-6) !== '</svg>' || marks < 3) broken.push(k + ' (' + map[k].v + ')');
});
console.log(JSON.stringify({
    total: keys.length,
    noDrawing: noDrawing,
    noDesc: noDesc,
    broken: broken,
    lists: LIST_SOURCES.length,
    listsMissing: LIST_SOURCES.filter(function (src) {
        var info = map[_sdPalKey({ type: 'loop_area', extra: { listSource: src } })];
        return !info || !info.v || !info.d;
    }),
    html: ['s:section', 's:div', 's:input:radio', 's:select'].every(function (k) { return map[k] && !!map[k].v; }),
    form: ['f:text', 'f:radiogroup', 'f:captcha', 'f:form', 'r:page', 'r:menu', 'r:comments_block'].every(function (k) { return map[k] && !!map[k].v; }),
    content: ['t:heading', 't:image', 't:custom_php', 't:icon'].every(function (k) { return map[k] && !!map[k].v; }),
    unknown: _sdPalDrawing('no-such-drawing')
}));
JS;

	$file = tempnam(sys_get_temp_dir(), 'pgpaldrawing');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", array_slice($out, 0, 20)));
	$read = $code === 0 ? json_decode((string) end($out), true) : null;
	$got = (is_array($read) ? $read : array()) + array(
		'total' => 0, 'noDrawing' => null, 'noDesc' => null, 'broken' => null, 'lists' => 0, 'listsMissing' => null,
		'html' => false, 'form' => false, 'content' => false, 'unknown' => null,
	);
	return $got;
}

function test_designer_pal_drawing_every_component_item_is_drawn()
{
	$got = designer_pal_drawing_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_true($got['total'] > 100, 'the info map is read');
	pg_assert_same(array(), $got['noDrawing'], 'Components rows without a drawing');
	pg_assert_same(array(), $got['noDesc'], 'Components rows without a description');
	pg_assert_same(array(), $got['broken'], 'items whose drawing is missing, not a whole SVG or nearly empty');
	pg_assert_true($got['html'], 'HTML elements have a drawing');
	pg_assert_true($got['content'], 'Content items have a drawing');
	pg_assert_true($got['form'], 'Form fields and Regions have a drawing');
	pg_assert_true($got['lists'] >= 20, 'the Lists in a record rows are read from the palette');
	pg_assert_same(array(), $got['listsMissing'], 'Lists in a record rows without a drawing or a description');
	pg_assert_same('', $got['unknown'], 'an unknown drawing key draws nothing');
}
