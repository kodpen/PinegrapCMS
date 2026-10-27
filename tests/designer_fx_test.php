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

// A component carries its interaction on the element that takes its id,
// as the editor's HTML does (_sdFxIntoFirstTag()); a copy typed into the
// Attributes panel is left out; the language switcher's placeholder has none.
function test_designer_fx_rendered_on_components()
{
	$fx = array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 900);
	$attrs = pg_fx_attrs(array('_fx' => $fx));
	$panel = array(array('name' => 'data-pg-fx', 'value' => 'typed'), array('name' => 'data-k', 'value' => 'v'));
	$c = function ($type, $props = array()) use ($fx, $panel) {
		return _render_tree_node(array('type' => 'component', 'props' => array('componentType' => $type, 'id' => 'n1', '_fx' => $fx, '_attrs' => $panel) + $props, 'children' => array()), 0, 0);
	};

	foreach (array(
		'btn'       => '<a href="#" class="btn btn-primary" id="n1"' . $attrs . ' data-k="v">',
		'badge'     => '<span class="badge bg-primary" id="n1"' . $attrs . ' data-k="v">',
		'card'      => '<div class="card" id="n1"' . $attrs . ' data-k="v">',
		'alert_box' => $attrs,
		'hero'      => $attrs,
		'navbar'    => $attrs,
		'accordion' => $attrs,
		'tabs'      => $attrs,
		'carousel'  => $attrs,
	) as $type => $expected) {
		$html = $c($type);
		pg_assert_contains($expected, $html, $type);
		pg_assert_same(1, substr_count($html, 'data-pg-fx="'), $type . ': written once');
		pg_assert_false(strpos($html, 'typed') !== false, $type . ': the typed copy is left out');
	}
	pg_assert_contains('<button type="button" class="btn btn-primary" id="n1"' . $attrs, $c('btn', array('btnElement' => 'button')));
	pg_assert_contains('<input type="button" value="Button" class="btn btn-primary" id="n1"' . $attrs, $c('btn', array('btnElement' => 'input')));
	// The interaction is on the component's outer element: the first tag.
	pg_assert_true(strpos($c('card'), 'data-pg-fx') < strpos($c('card'), '</div>'), 'on the outer element');

	$html = _render_tree_node(array('type' => 'component', 'props' => array('componentType' => 'btn', '_attrs' => $panel), 'children' => array()), 0, 0);
	pg_assert_contains('data-pg-fx="typed"', $html, 'without an interaction the panel entry stays');
}

// Nodes inside a widget or a shared component are drawn by the same paths
// and keep their interaction through the widget's finish pass; the page
// looks for the attributes once widgets and shared components are in.
function test_designer_fx_inside_widgets_and_shared_components()
{
	$fx = array('effect' => 'fade', 'trigger' => 'view');
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_fx' => $fx), 'children' => array(
			array('type' => 'component', 'props' => array('componentType' => 'btn', '_fx' => array('effect' => 'scale', 'trigger' => 'hover')), 'children' => array()),
		)),
	));
	$scope = pg_sw_finish_open(array('sid' => 94, 'kind' => 'search_results', 'email' => false));
	try {
		$html = pg_sw_finish_widget_html(_render_tree_node($tree, 0, 0), $scope);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_contains('<div data-pg-fx="fade" data-pg-fx-trigger="view">', $html);
	pg_assert_contains('data-pg-fx="scale" data-pg-fx-trigger="hover"', $html, 'a component inside a widget');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$inject = strpos($page, '$content = pg_fx_inject($content);');
	pg_assert_true($inject !== false, 'the page injects the script');
	pg_assert_true(strpos($page, '$content = _expand_shared_refs($content);') < $inject, 'after the shared components');
	pg_assert_true(strpos($page, '$content = _expand_system_widgets($content, $mode, $email);') < $inject, 'after the widgets');
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

// The Interactions section: offered where the page writes the attributes,
// its rows follow the effect and the trigger, a field change keeps the
// rest and drops what no longer applies, and the panel wires it up.
function test_designer_fx_panel()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$twins = strpos($js, '    // The lists are first-is-default');
	$twins_end = strpos($js, '    // The id, the title and tooltip, the interaction');
	$panel = strpos($js, '    var _SD_FX_CONTENT');
	$panel_end = strpos($js, "    // The Interactions section's fields write");
	pg_assert_true($twins !== false && $twins_end !== false && $panel !== false && $panel_end !== false, 'the panel code is found');

	$script = 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }'
		. 'function _sdT(s) { return s; }'
		. 'function row(l, c) { return "<row " + l + ">" + c + "</row>"; }'
		. 'function sect(i, t, b) { return "<sect " + t + ">" + b + "</sect>"; }'
		. 'function swAttr(a, on) { return "<sw " + a + (on ? " on" : "") + ">"; }'
		. substr($js, $twins, $twins_end - $twins) . substr($js, $panel, $panel_end - $panel)
		. 'function rows(h) { return (h.match(/<row [^>]+>/g) || []).map(function (r) { return r.slice(5, -1); }); }'
		. 'var c = function (t, p) { return { type: t, props: p }; };'
		. 'var out = {'
		. ' takes: [c("content", { contentType: "heading" }), c("content", { contentType: "custom_html" }), c("semantic", {}), c("row", {}), c("component", { componentType: "btn" }), c("shared_ref", {}), c("component", { componentType: "language_switcher" })].map(_sdFxTakes),'
		. ' none: rows(propsInteractions(c("semantic", {}))),'
		. ' slide: rows(propsInteractions(c("semantic", { _fx: { effect: "slide-up", trigger: "view" } }))),'
		. ' hover: rows(propsInteractions(c("semantic", { _fx: { effect: "fade", trigger: "hover" } }))),'
		. ' sticky: rows(propsInteractions(c("semantic", { _fx: { effect: "sticky", offset: 8 } }))),'
		. ' parallax: rows(propsInteractions(c("semantic", { _fx: { effect: "parallax" } }))),'
		. ' other: propsInteractions(c("component", { componentType: "language_switcher", _fx: { effect: "fade" } })),'
		. ' component: rows(propsInteractions(c("component", { componentType: "card", _fx: { effect: "fade", trigger: "hover" } }))),'
		. ' into: ['
		. '  _sdFxIntoFirstTag("  <a href=\\"#\\" class=\\"btn\\">x</a>\\n", { _fx: { effect: "fade", trigger: "load" } }),'
		. '  _sdFxIntoFirstTag("<input type=\\"button\\" value=\\"a/b\\"/>", { _fx: { effect: "scale", trigger: "hover" } }),'
		. '  _sdFxIntoFirstTag("<span>x</span>", {}),'
		. '  _sdFxIntoFirstTag("text", { _fx: { effect: "fade" } })'
		. ' ],'
		. ' slideHtml: propsInteractions(c("semantic", { _fx: { effect: "slide-left", trigger: "hover" } })),'
		. ' set: ['
		. '  _sdFxSet(null, "effect", "fade"),'
		. '  _sdFxSet({ effect: "fade", duration: 900 }, "effect", "slide-up"),'
		. '  _sdFxSet({ effect: "slide-up", stagger: 80, repeat: true }, "trigger", "hover"),'
		. '  _sdFxSet({ effect: "fade", delay: 100 }, "effect", "sticky"),'
		. '  _sdFxSet({ effect: "fade" }, "effect", ""),'
		. '  _sdFxSet({ effect: "fade" }, "duration", "99999"),'
		. '  _sdFxSet({ effect: "fade" }, "repeat", true)'
		. ' ]'
		. '};'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	pg_assert_same(array(true, false, true, true, true, false, false), $got['takes'], 'where the section is offered');
	pg_assert_same(array('Effect'), $got['none'], 'no interaction: the effect alone');
	pg_assert_same(array('Effect', 'Trigger', 'Duration', 'Delay', 'Easing', 'Distance', 'Stagger children', 'Every time'), $got['slide'], 'a slide that enters on view');
	pg_assert_same(array('Effect', 'Trigger', 'Duration', 'Delay', 'Easing'), $got['hover'], 'a hover fade');
	pg_assert_same(array('Effect', 'Top offset'), $got['sticky']);
	pg_assert_same(array('Effect', 'Speed'), $got['parallax']);
	pg_assert_same('', $got['other'], 'not on the language switcher');
	pg_assert_same(array('Effect', 'Trigger', 'Duration', 'Delay', 'Easing'), $got['component'], 'on a component');
	// The component's first tag takes the attributes the page writes.
	pg_assert_same('  <a href="#" class="btn"' . pg_fx_attrs(array('_fx' => array('effect' => 'fade', 'trigger' => 'load'))) . ">x</a>\n", $got['into'][0]);
	pg_assert_same('<input type="button" value="a/b"' . pg_fx_attrs(array('_fx' => array('effect' => 'scale', 'trigger' => 'hover'))) . '/>', $got['into'][1], 'before a void tag\'s slash');
	pg_assert_same('<span>x</span>', $got['into'][2], 'no interaction, unchanged');
	pg_assert_same('text', $got['into'][3], 'no tag, unchanged');
	pg_assert_contains("_sdFxIntoFirstTag(comp.toHTML(P, pad, _pcChildren), n.props)", $js, 'the editor\'s HTML writes it on components');
	pg_assert_contains('data-fx="distance" min="0" max="400" step="1" value="" placeholder="6"', $got['slideHtml'], 'the script\'s own distance as the placeholder');
	pg_assert_contains('<option value="slide-left" selected>Slide left</option>', $got['slideHtml']);

	pg_assert_same(pg_fx_normalize(array('effect' => 'fade')), $got['set'][0], 'a new effect with the defaults');
	pg_assert_same(900, $got['set'][1]['duration'], 'switching the effect keeps the timing');
	pg_assert_same(pg_fx_normalize(array('effect' => 'slide-up', 'trigger' => 'hover')), $got['set'][2], 'hover drops stagger and repeat');
	pg_assert_same(array('effect' => 'sticky', 'offset' => 0), $got['set'][3]);
	pg_assert_same(null, $got['set'][4], 'cleared');
	pg_assert_same(5000, $got['set'][5]['duration'], 'clamped');
	pg_assert_same(true, $got['set'][6]['repeat']);

	pg_assert_contains("optHtml += propsInteractions(selectedNode);", $js, 'the panel shows the section');
	pg_assert_contains("_sdFxBind(selectedNode);", $js, 'and binds it');
	pg_assert_contains("if (_sdCodeSetFxAttr(node, name, now)) return;", $js, 'the code view hands the attributes back');
	pg_assert_contains('data-fx-play', $got['slideHtml'], 'a moving effect has a Play button');
	pg_assert_contains("        _sdScrollers();\n        _sdFxCanvas();", $js, 'the canvas binds interactions after every render');
	pg_assert_contains("var _fxAttrs = _sdFxAttrs(node.props);", $js, 'canvas elements carry the attributes');
}

// pg_fx.src.js on a small fake document: the editor's canvas (pgFxEditing)
// plays the entrances there when the file starts and shows a node drawn by
// a later render in place, a node taken out is forgotten, replay() plays
// an effect with important transitions and gives the node back, sticky
// gets its position where no head style gave it one.
function test_designer_fx_script_on_the_canvas()
{
	$fake = <<<'JS'
var timers = [], observed = [], unobserved = [];
function El(attrs) {
	var el = this;
	this.attrs = attrs; this.children = []; this.parentNode = null; this.offsetWidth = 200; this.offsetHeight = 100;
	this.listeners = {};
	var cls = {};
	this.classList = {
		add: function (c) { cls[c] = 1; }, remove: function (c) { delete cls[c]; },
		contains: function (c) { return !!cls[c]; },
		toggle: function (c) { if (cls[c]) { delete cls[c]; return false; } cls[c] = 1; return true; }
	};
	var prio = {};
	this.style = {
		opacity: '', transform: '', transition: '', top: '', position: '',
		setProperty: function (k, v, p) { this[k] = v; prio[k] = p || ''; },
		removeProperty: function (k) { this[k] = ''; prio[k] = ''; },
		getPropertyPriority: function (k) { return prio[k] || ''; }
	};
	this.getAttribute = function (n) { return Object.prototype.hasOwnProperty.call(el.attrs, n) ? el.attrs[n] : null; };
	this.addEventListener = function (t, f) { (el.listeners[t] = el.listeners[t] || []).push(f); };
}
var nodes = [];
var document = {
	readyState: 'complete',
	querySelectorAll: function () { return nodes.filter(function (n) { return n.attrs['data-pg-fx'] !== undefined; }); },
	documentElement: { contains: function (el) { return nodes.indexOf(el) !== -1; }, clientHeight: 800 },
	addEventListener: function () {}
};
var window = {
	pgFxEditing: true, innerHeight: 800,
	matchMedia: function () { return { matches: false }; },
	getComputedStyle: function (el) { return { display: 'block', position: el.style.position || 'static' }; },
	IntersectionObserver: function () { this.observe = function (e) { observed.push(e); }; this.unobserve = function (e) { unobserved.push(e); }; },
	requestAnimationFrame: function (f) { f(); },
	setTimeout: function (f) { timers.push(f); },
	addEventListener: function () {}
};
function setTimeout(f) { timers.push(f); }
function clearTimeout() {}
var first = new El({ 'data-pg-fx': 'fade', 'data-pg-fx-trigger': 'load' });
var sticky = new El({ 'data-pg-fx': 'sticky', 'data-pg-fx-offset': '12' });
var viewed = new El({ 'data-pg-fx': 'slide-up', 'data-pg-fx-trigger': 'view' });
nodes.push(first, sticky, viewed);
JS;
	$steps = <<<'JS'
var out = {};
out.first = [first.style.opacity, first.style.transition.indexOf('opacity 600ms') === 0, first.classList.contains('pg-fx-armed')];
out.sticky = [sticky.style.position, sticky.style.top];
out.viewed = [viewed.style.opacity, observed.indexOf(viewed) !== -1];
nodes = [first];
var redrawn = new El({ 'data-pg-fx': 'fade', 'data-pg-fx-trigger': 'view' });
nodes.push(redrawn);
window.pgFxBind(document);
out.redrawn = [redrawn.style.opacity, redrawn.classList.contains('pg-fx-armed'), observed.indexOf(redrawn) === -1];
out.forgotten = unobserved.indexOf(viewed) !== -1;
var hover = new El({ 'data-pg-fx': 'scale', 'data-pg-fx-trigger': 'hover' });
hover.style.transform = 'rotate(2deg)';
nodes.push(hover);
window.pgFxBind(document);
timers = [];
out.replay = window.pgFx.replay(hover);
out.during = [hover.style.transform, hover.style.getPropertyPriority('transition')];
timers.shift()();
out.back = [hover.style.transform];
timers.shift()();
out.after = [hover.style.transform, hover.style.transition, hover.style.getPropertyPriority('transition')];
timers = [];
out.replayEntrance = window.pgFx.replay(redrawn);
out.entranceDuring = [redrawn.style.opacity, redrawn.style.getPropertyPriority('transition')];
out.stickyReplay = window.pgFx.replay(sticky);
console.log(JSON.stringify(out));
JS;
	$script = $fake . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js') . $steps;
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	pg_assert_same(array('', true, true), $got['first'], 'the first bind plays the entrance');
	pg_assert_same(array('sticky', '12px'), $got['sticky'], 'sticky without a head style');
	pg_assert_same(array('0', true), $got['viewed'], 'a view entrance waits, armed');
	pg_assert_same(array('', true, true), $got['redrawn'], 'a node drawn later shows in place');
	pg_assert_true($got['forgotten'], 'a node taken out is no longer observed');
	pg_assert_true($got['replay'], 'a hover effect replays');
	pg_assert_same(array('rotate(2deg) scale(1.05)', 'important'), $got['during'], 'towards the effect, important, on top of its own transform');
	pg_assert_same(array('rotate(2deg)'), $got['back'], 'and back');
	pg_assert_same(array('rotate(2deg)', '', ''), $got['after'], 'its own state and transition again');
	pg_assert_true($got['replayEntrance']);
	pg_assert_same(array('', 'important'), $got['entranceDuring'], 'an entrance plays to its own state');
	pg_assert_false($got['stickyReplay'], 'sticky has nothing to replay');
}
