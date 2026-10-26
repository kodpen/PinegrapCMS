<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the interactions of visual editor nodes
 * (includes/fn/designer_fx.php): pg_fx_normalize() keeps the known fields
 * in range and drops what does not apply, pg_fx_attrs() writes the effect,
 * the trigger and the non-default values, the page writes them on content,
 * semantic and grid nodes, pg_fx_inject() adds the head style and the
 * script once. The editor's twins (_sdFxNormalize(), _sdFxAttrs()) and the
 * helpers of assets/js/pg_fx.src.js are run under Node; on a machine
 * without Node those halves are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The interactions both sides are compared on.
function _test_fx_cases()
{
	return array(
		'none' => null,
		'not an array' => 'fade',
		'unknown effect' => array('effect' => 'spin'),
		'bare fade' => array('effect' => 'fade'),
		'upper case' => array('effect' => 'FADE', 'trigger' => 'Load', 'easing' => 'LINEAR'),
		'slide with timing' => array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => '900', 'delay' => 150, 'easing' => 'ease-in-out', 'stagger' => 80, 'distance' => 40, 'repeat' => true),
		'clamped' => array('effect' => 'slide-left', 'duration' => 1, 'delay' => 99999, 'stagger' => -5, 'distance' => 1000),
		'rounded' => array('effect' => 'scale', 'duration' => 650.5, 'delay' => '2.4'),
		'not numbers' => array('effect' => 'scale', 'duration' => 'fast', 'delay' => true, 'distance' => array(5), 'stagger' => ''),
		'unknown trigger and easing' => array('effect' => 'fade', 'trigger' => 'scroll', 'easing' => 'bounce'),
		'hover drops stagger and repeat' => array('effect' => 'slide-up', 'trigger' => 'hover', 'stagger' => 100, 'repeat' => true, 'offset' => 5),
		'click' => array('effect' => 'scale', 'trigger' => 'click', 'duration' => 300),
		'load drops repeat' => array('effect' => 'fade', 'trigger' => 'load', 'repeat' => 1, 'distance' => 50),
		'sticky' => array('effect' => 'sticky', 'offset' => 72, 'trigger' => 'view', 'duration' => 900),
		'sticky default' => array('effect' => 'sticky'),
		'parallax' => array('effect' => 'parallax', 'speed' => 35, 'trigger' => 'hover'),
		'parallax clamped' => array('effect' => 'parallax', 'speed' => 1),
	);
}

function test_designer_fx_normalize()
{
	pg_assert_same(null, pg_fx_normalize(null), 'no interaction');
	pg_assert_same(null, pg_fx_normalize(array('effect' => 'spin')), 'an unknown effect');
	pg_assert_same(array('effect' => 'fade', 'trigger' => 'view', 'duration' => 600, 'delay' => 0, 'easing' => 'ease-out', 'stagger' => 0, 'repeat' => false), pg_fx_normalize(array('effect' => 'fade')), 'the defaults');
	pg_assert_same(array('effect' => 'slide-left', 'trigger' => 'view', 'duration' => 100, 'delay' => 5000, 'easing' => 'ease-out', 'distance' => 400, 'stagger' => 0, 'repeat' => false), pg_fx_normalize(array('effect' => 'slide-left', 'duration' => 1, 'delay' => 99999, 'stagger' => -5, 'distance' => 1000)), 'clamped');
	pg_assert_same(array('effect' => 'slide-up', 'trigger' => 'hover', 'duration' => 600, 'delay' => 0, 'easing' => 'ease-out', 'distance' => 0), pg_fx_normalize(array('effect' => 'slide-up', 'trigger' => 'hover', 'stagger' => 100, 'repeat' => true, 'offset' => 5)), 'hover keeps no stagger, repeat or offset');
	pg_assert_same(array('effect' => 'sticky', 'offset' => 72), pg_fx_normalize(array('effect' => 'sticky', 'offset' => 72, 'trigger' => 'view')), 'sticky has no trigger');
	pg_assert_same(array('effect' => 'parallax', 'speed' => 5), pg_fx_normalize(array('effect' => 'parallax', 'speed' => 1)), 'parallax speed clamped');
	pg_assert_same(651, pg_fx_normalize(array('effect' => 'scale', 'duration' => 650.5))['duration'], 'a half rounds up');
}

function test_designer_fx_attrs()
{
	pg_assert_same('', pg_fx_attrs(array()), 'no interaction');
	pg_assert_same(' data-pg-fx="fade" data-pg-fx-trigger="view"', pg_fx_attrs(array('_fx' => array('effect' => 'fade'))), 'the defaults are not written');
	pg_assert_same(' data-pg-fx="slide-up" data-pg-fx-trigger="view" data-pg-fx-duration="900" data-pg-fx-delay="150" data-pg-fx-easing="ease-in-out" data-pg-fx-stagger="80" data-pg-fx-distance="40" data-pg-fx-repeat',
		pg_fx_attrs(array('_fx' => _test_fx_cases()['slide with timing'])), 'every value in order');
	pg_assert_same(' data-pg-fx="sticky" data-pg-fx-offset="72"', pg_fx_attrs(array('_fx' => array('effect' => 'sticky', 'offset' => 72))), 'sticky');
	pg_assert_same(' data-pg-fx="parallax"', pg_fx_attrs(array('_fx' => array('effect' => 'parallax', 'speed' => 20))), 'parallax at its default speed');
	pg_assert_true((bool) preg_match(pg_fx_pattern(), pg_fx_attrs(array('_fx' => array('effect' => 'fade')))), 'the marker is found');
	pg_assert_false((bool) preg_match(pg_fx_pattern(), '<div data-pg-fx-trigger="view">'), 'a longer name is no marker');
	pg_assert_false((bool) preg_match(pg_fx_pattern(), '<p>data-pg-fx</p>'), 'the word as text');
}

// Content, semantic and grid nodes carry the interaction after the title;
// a copy of its names typed into the Attributes panel is left out.
function test_designer_fx_rendered_on_nodes()
{
	$fx = array('effect' => 'fade', 'trigger' => 'load');
	$panel = array(array('name' => 'data-pg-fx', 'value' => 'typed'), array('name' => 'data-k', 'value' => 'v'));
	$html = _render_tree_node(array('type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h2', 'text' => 'H', 'title' => 'T', '_fx' => $fx, '_attrs' => $panel), 'children' => array()), 0, 0);
	pg_assert_contains('<h2 title="T" data-pg-fx="fade" data-pg-fx-trigger="load" data-k="v"', $html);
	pg_assert_same(1, substr_count($html, 'data-pg-fx="'), 'written once');

	$html = _render_tree_node(array('type' => 'semantic', 'props' => array('tag' => 'section', '_fx' => $fx), 'children' => array()), 0, 0);
	pg_assert_contains('<section data-pg-fx="fade" data-pg-fx-trigger="load"', $html);

	$html = _render_tree_node(array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'P', '_attrs' => $panel), 'children' => array()), 0, 0);
	pg_assert_contains('data-pg-fx="typed"', $html, 'without an interaction the panel entry stays');

	pg_assert_contains('data-pg-fx="fade"', _pg_grid_open_tag('row', array('_fx' => $fx), array('class' => '', 'style' => '')), 'a grid node');
}

function test_designer_fx_inject()
{
	$src = '/pinegrap/assets/js/pg_fx.min.js?v=1';
	$tag = '<script src="' . $src . '" defer></script>' . "\n";

	$plain = '<html><head></head><body><p>x</p></body></html>';
	pg_assert_same($plain, pg_fx_inject($plain, $src), 'a page without the marker is left as it is');

	$page = '<html><head><title>t</title></head><body><p data-pg-fx="fade">x</p></body></html>';
	$once = pg_fx_inject($page, $src);
	pg_assert_same('<html><head><title>t</title>' . pg_fx_head_style() . '</head><body><p data-pg-fx="fade">x</p>' . $tag . '</body></html>', $once);
	pg_assert_same($once, pg_fx_inject($once, $src), 'a second call adds nothing');

	pg_assert_same(pg_fx_head_style() . '<p data-pg-fx="fade">x</p>' . $tag, pg_fx_inject('<p data-pg-fx="fade">x</p>', $src), 'a fragment');
	pg_assert_contains('src="/x.js?a=1&amp;b=2"', pg_fx_inject('<p data-pg-fx="fade">', '/x.js?a=1&b=2'), 'the address is escaped');

	$style = pg_fx_head_style();
	pg_assert_contains('prefers-reduced-motion:no-preference', $style, 'hidden only where motion is allowed');
	pg_assert_contains('[data-pg-fx-trigger="view"]:not(.pg-fx-armed)', $style);
	pg_assert_contains('pg-fx-failsafe 0s 3s forwards', $style, 'a script that never comes lets them show');
	pg_assert_contains('pg_fx_inject($content)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'the published page asks for it');
}

function test_designer_fx_preview_pattern()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$found = preg_match("~\\{ test: (/[^\\n]*?/[a-z]*), file: 'assets/js/pg_fx\\.min\\.js' \\}~", $js, $m);
	pg_assert_true((bool) $found, 'the preview has a row for pg_fx.min.js');
	pg_assert_same(pg_fx_pattern(), $found ? $m[1] : '', 'the preview tests the same expression as the server');
}

// Runs $script under Node and returns its JSON output, null without Node.
function _test_fx_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgfx') . '.js';
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$json = json_decode(implode("\n", $out), true);
	pg_assert_true($code === 0 && $json !== null, 'node ran: ' . implode("\n", $out));

	return $json;
}

// The editor's twins give the same interaction and the same attributes.
function test_designer_fx_editor_twins()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, '    // The lists are first-is-default');
	$end = strpos($js, '    // The id, the title and tooltip, the interaction');
	pg_assert_true($start !== false && $end !== false, 'the twins are found');

	$cases = _test_fx_cases();
	$script = substr($js, $start, $end - $start)
		. 'var cases = ' . json_encode($cases) . ';'
		. 'var out = {}; Object.keys(cases).forEach(function (k) { out[k] = [_sdFxNormalize(cases[k]), _sdFxAttrs({ _fx: cases[k] })]; });'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	foreach ($cases as $name => $fx) {
		pg_assert_same(array(pg_fx_normalize($fx), pg_fx_attrs(array('_fx' => $fx))), $got[$name], $name);
	}
}

// What the page writes, pg_fx.src.js reads back as the same values; the
// helpers give the start states, transitions and parallax offsets.
function test_designer_fx_script_reads_the_page()
{
	$cases = array();
	foreach (_test_fx_cases() as $name => $fx) {
		$norm = pg_fx_normalize($fx);
		if ($norm === null) continue;
		$attrs = array();
		preg_match_all('~ (data-pg-fx[a-z-]*)(?:="([^"]*)")?~', pg_fx_attrs(array('_fx' => $fx)), $all, PREG_SET_ORDER);
		foreach ($all as $a) $attrs[$a[1]] = isset($a[2]) ? $a[2] : '';
		$cases[$name] = array('norm' => $norm, 'attrs' => $attrs);
	}

	$script = 'var window = {};' . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js')
		. 'var fx = window.pgFx, cases = ' . json_encode($cases) . ', out = {};'
		. 'Object.keys(cases).forEach(function (k) { var a = cases[k].attrs;'
		. ' out[k] = fx.read(function (n) { return Object.prototype.hasOwnProperty.call(a, n) ? a[n] : null; }); });'
		. 'out._shift = ["fade|view", "slide-up|view", "slide-left|load", "slide-up|hover", "slide-right|click", "scale|view", "scale|hover", "fade|hover"].map(function (s) {'
		. ' var p = s.split("|"); return fx.shift(fx.read(function (n) { return n === "data-pg-fx" ? p[0] : n === "data-pg-fx-trigger" ? p[1] : null; })); });'
		. 'out._transition = fx.transition({ duration: 600, easing: "ease-out" }, 150);'
		. 'out._parallax = [fx.parallax(400, 200, 1000, 20), fx.parallax(0, 100, 800, 50), fx.parallax(100, 100, 1000, 20)];'
		. 'out._unknown = fx.read(function (n) { return n === "data-pg-fx" ? "spin" : null; });'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	foreach ($cases as $name => $c) {
		$norm = $c['norm'];
		$read = $got[$name];
		foreach ($norm as $field => $value) {
			if ($field === 'distance' && $value === 0) {
				pg_assert_same($norm['trigger'] === 'view' || $norm['trigger'] === 'load' ? 24 : 6, $read['distance'], $name . ': the script\'s own distance');
				continue;
			}
			pg_assert_same($value, $read[$field], $name . ': ' . $field);
		}
	}

	pg_assert_same(array(
		array('opacity' => '0', 'transform' => ''),
		array('opacity' => '0', 'transform' => 'translate3d(0px,24px,0)'),
		array('opacity' => '0', 'transform' => 'translate3d(24px,0px,0)'),
		array('opacity' => '', 'transform' => 'translate3d(0px,-6px,0)'),
		array('opacity' => '', 'transform' => 'translate3d(6px,0px,0)'),
		array('opacity' => '0', 'transform' => 'scale(0.9)'),
		array('opacity' => '', 'transform' => 'scale(1.05)'),
		array('opacity' => '0.6', 'transform' => ''),
	), $got['_shift'], 'start and hover states');
	pg_assert_same('opacity 600ms ease-out 150ms,transform 600ms ease-out 150ms', $got['_transition']);
	pg_assert_same(array(0, 175, 70), $got["_parallax"], "parallax offsets");
	pg_assert_same(null, $got['_unknown'], 'an unknown effect is not read');
}
