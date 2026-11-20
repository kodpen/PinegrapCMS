<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's canvas bar in three zones (assets/js/
 * style_designer.src.js, createCanvasIframe()): the View menu's badge counts
 * the switches that are on (_sdCanvasBarBadge()), the bar folds by its own
 * width in a fixed order (_sdCanvasBarFold()), every id the handlers and
 * shortcuts reach stays, and Prototype mode puts the Prototype view's
 * controls in the same bar. The pure functions run under Node; skipped where
 * there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_canvas_bar_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_canvas_bar_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs `$expr` for each case with `c` bound to it, after the named
// functions, and returns the decoded results. Null without Node.
function designer_canvas_bar_node($names, $cases, $expr)
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

	$js = designer_canvas_bar_source();
	$code_js = '';

	foreach ($names as $name) {
		$fn = designer_canvas_bar_fn($js, $name);

		if (!pg_assert_true($fn !== '', 'style_designer.js has ' . $name . '()')) {
			return null;
		}

		$code_js .= $fn . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pgcbar');
	file_put_contents($file, $code_js
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) { return ' . $expr . '; })));' . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function test_designer_canvas_bar_badge_counts_switches_on()
{
	$res = designer_canvas_bar_node(array('_sdCanvasBarBadge'), array(
		array(),
		array(array('on' => false, 'shown' => true), array('on' => false, 'shown' => true)),
		array(array('on' => true, 'shown' => true), array('on' => false, 'shown' => true), array('on' => true, 'shown' => true)),
		// A switch an e-mail design does not offer (animations) is not
		// counted, even when it was left on.
		array(array('on' => true, 'shown' => true), array('on' => true, 'shown' => false)),
		array(array('on' => true, 'shown' => true), array('on' => true, 'shown' => true), array('on' => true, 'shown' => true), array('on' => true, 'shown' => true)),
	), '_sdCanvasBarBadge(c)');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(0, 0, 2, 1, 4), $res, 'the badge is the number of shown switches that are on');
}

function test_designer_canvas_bar_fold_order_by_width()
{
	// needs[level]: the bar's width at each fold level. 0 everything,
	// 1 without the width text, 2 the sizes folded into one list,
	// 3 the labels gone too.
	$needs = array(1015, 940, 830, 610);
	$res = designer_canvas_bar_node(array('_sdCanvasBarFold'), array(
		array('w' => 1200, 'n' => $needs),
		array('w' => 1015, 'n' => $needs),
		array('w' => 1014, 'n' => $needs),
		array('w' => 930, 'n' => $needs),
		array('w' => 830, 'n' => $needs),
		array('w' => 700, 'n' => $needs),
		array('w' => 390, 'n' => $needs),
		// Not measured yet (a hidden bar): nothing folds.
		array('w' => 0, 'n' => $needs),
		array('w' => 500, 'n' => array()),
	), '_sdCanvasBarFold(c.w, c.n)');

	if ($res === null) {
		return;
	}

	pg_assert_same(0, $res[0], 'a wide bar folds nothing');
	pg_assert_same(0, $res[1], 'a bar exactly as wide as it needs folds nothing');
	pg_assert_same(1, $res[2], 'the width text goes first');
	pg_assert_same(2, $res[3], 'then the sizes fold into one list (930 px: the drawing\'s width)');
	pg_assert_same(2, $res[4], 'the sizes stay folded while that is enough');
	pg_assert_same(3, $res[5], 'then the labels go');
	pg_assert_same(3, $res[6], 'narrower still, the last level stays and the bar wraps');
	pg_assert_same(0, $res[7], 'an unmeasured bar folds nothing');
	pg_assert_same(0, $res[8], 'no measures, no fold');
}

function test_designer_canvas_bar_keeps_ids_and_names_switches()
{
	$js = designer_canvas_bar_source();
	$bar = designer_canvas_bar_fn($js, 'createCanvasIframe');

	pg_assert_true($bar !== '', 'style_designer.js has createCanvasIframe()');

	foreach (array('sd-vb-zoom-out', 'sd-vb-zoom-label', 'sd-vb-zoom-in', 'sd-vb-outline', 'sd-vb-theme', 'sd-vb-grid', 'sd-vb-motion', 'sd-vb-find', 'sd-vb-snapshots', 'sd-vb-proto', 'sd-bp-label', 'sd-vb-view', 'sd-vb-design', 'sd-vb-fit') as $id) {
		pg_assert_contains('id="' . $id . '"', $bar, 'the bar has #' . $id);
	}

	foreach (array('sd-vb-outline', 'sd-vb-theme', 'sd-vb-grid', 'sd-vb-motion') as $id) {
		pg_assert_true((bool) preg_match('/id="' . $id . '" role="menuitemcheckbox"/', $bar), '#' . $id . ' is a switch row of the View menu');
	}

	$theme = designer_canvas_bar_fn($js, 'applyCanvasTheme');
	pg_assert_true(strpos($theme, "_sdT('Light')") === false, 'the Dark canvas switch never names the state as Light');
	$motion = designer_canvas_bar_fn($js, '_sdSyncMotionButton');
	pg_assert_true(strpos($motion, "_sdT('Pause animations')") === false, 'the Play animations switch keeps its name');
}

// Fit scales the frame (the canvas keeps its width, so Bootstrap keeps the
// breakpoint); what the editor draws in its own document from the frame's
// position takes the scale or is hidden while fitted.
function test_designer_canvas_bar_fit_scales_the_frame()
{
	$js = designer_canvas_bar_source();
	$apply = designer_canvas_bar_fn($js, '_sdFitApply');
	pg_assert_contains("wrap.style.transform = 'scale(' + s + ')';", $apply, 'Fit scales the frame, it does not zoom the canvas');
	pg_assert_contains('_sdFitLeave()', designer_canvas_bar_fn($js, 'setCanvasZoom'), '− and + leave Fit');
	pg_assert_contains('_sdFitLeave();', designer_canvas_bar_fn($js, 'initResize'), 'a resize handle leaves Fit');
	pg_assert_contains('rect.left + e.clientX * fs', $js, 'the canvas context menu takes the scale');
	pg_assert_contains('* _sdFitScale();', designer_canvas_bar_fn($js, '_positionSizeBadge'), 'the size badge takes the scale');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	foreach (array('.sd-canvas-fitted .sd-resize-handle,', '.sd-canvas-fitted .sd-icon-fs-handle') as $sel) {
		pg_assert_contains($sel, $css, 'hidden while fitted: ' . $sel);
	}
}

// The width text names a Bootstrap breakpoint only where there is one: an
// e-mail design has none, so it reads the width alone.
function test_designer_canvas_bar_width_text()
{
	$res = designer_canvas_bar_node(array('_sdBpLabelText'), array(
		array(375, false), array(576, false), array(768, false), array(992, false),
		array(1200, false), array(1400, false), array(1919, false),
		array(600, true), array(375, true),
	), '_sdBpLabelText(c[0], c[1])');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('375 px · XS', '576 px · SM', '768 px · MD', '992 px · LG', '1200 px · XL', '1400 px · XXL', '1919 px · XXL', '600 px', '375 px'), $res, 'a page names its breakpoint, an e-mail its width alone');
}

// Prototype mode in the same bar (D59, D60): the Prototype view has no bar
// of its own; its controls sit in two zones of the canvas bar after the mode
// switch, shown while the mode is on, the canvas's zones hidden. No Close
// button: the switch's Design side leads back. Each mode keeps its zoom.
function test_designer_canvas_bar_prototype_mode()
{
	$js = str_replace("\r\n", "\n", designer_canvas_bar_source());
	$build = designer_canvas_bar_fn($js, '_sdProtoBuild');

	pg_assert_true($build !== '', 'style_designer.js has _sdProtoBuild()');
	pg_assert_true(strpos($build, 'sd-proto-bar') === false, 'the view has no bar of its own');
	pg_assert_true(strpos($build, "_sdT('Close the prototype view')") === false, 'no Close button: the switch leads back');
	pg_assert_contains("var cbar = document.getElementById('sd-bp-bar');", $build, 'the controls go into the canvas bar');
	pg_assert_contains("var map = _sdProtoEl('div', 'sd-bp-zone sd-bp-pv sd-bp-pmap');", $build, 'the map\'s zone');
	pg_assert_contains("var tools = _sdProtoEl('div', 'sd-bp-zone sd-bp-pv sd-bp-ptools');", $build, 'the tools\' zone');

	// D59: the count, − zoom + and Fit for the map; All arrows, Tidy up,
	// Read again. D60: "N broken", "N to check", Suggest targets, Links and
	// Play, which the view's own bar held, have their place too.
	foreach (array('ui.status', 'ui.broken', 'ui.check', 'zoomOut', 'zoomBtn', 'zoomIn', 'fit') as $part) {
		pg_assert_contains('map.appendChild(' . $part . ');', $build, 'in the map\'s zone: ' . $part);
	}

	foreach (array('ui.allEdges', 'tidy', 'ui.suggest', 'ui.listToggle', 'ui.play', 'reload') as $part) {
		pg_assert_contains('tools.appendChild(' . $part . ');', $build, 'in the tools\' zone: ' . $part);
	}

	foreach (array("_sdT('Zoom Out')", "_sdT('Zoom In')", "_sdT('Reset zoom to 100%')", "_sdT('Fit to screen')", "_sdT('All arrows')", "_sdT('Tidy up')", "_sdT('Read the links again')", "_sdT('Suggest targets')", "_sdT('Links')", "_sdT('Play')") as $text) {
		pg_assert_contains($text, $build, 'the bar names ' . $text);
	}

	pg_assert_contains("ui.allEdges.setAttribute('role', 'switch');", $build, '"All arrows" is a switch, not an action');
	pg_assert_contains("ui.allEdges.setAttribute('aria-checked', on ? 'true' : 'false');", designer_canvas_bar_fn($js, '_sdProtoAllEdges'), 'the switch says its state');
	pg_assert_contains("set textContent(text) { zoomBtn.textContent = _sdT('{var:1}%', parseInt(text, 10) || 0); }", $build, 'the map\'s zoom is written as the canvas\'s');
	pg_assert_contains('_sdProto.canvas.view.zoomBy(1 / _sdProto.view.k);', $build, 'a click on it goes back to 100%, as on the canvas');
	pg_assert_true(strpos($build, "_sdProtoEl('span', 'sd-proto-btn-label'") === false, 'a label folds with the bar (sd-vb-txt), not by the window');
	pg_assert_contains("cbar.appendChild(map);", $build, 'the zones join the bar');

	pg_assert_contains('_sdCanvasBarMode(true);', designer_canvas_bar_fn($js, '_sdProtoOpen'), 'opening the view turns the bar to Prototype mode');
	$close = designer_canvas_bar_fn($js, '_sdProtoClose');
	pg_assert_contains('_sdCanvasBarMode(false);', $close, 'closing it turns the bar back');
	pg_assert_contains("var btn = document.getElementById('sd-vb-design');", $close, 'the focus goes to the switch\'s Design side');
	$mode = designer_canvas_bar_fn($js, '_sdCanvasBarMode');
	pg_assert_contains("bar.classList.toggle('sd-bp-proto', !!proto);", $mode, 'the mode is a class, so the bar is measured again');
	pg_assert_contains('_sdViewMenuOpen(false);', $mode, 'the View menu closes with its mode');

	$draw = designer_canvas_bar_fn($js, '_sdProtoDraw');
	pg_assert_contains("_sdProtoStatus(_sdT('{var:1} pages', pages) + ' · ' + _sdT('{var:1} links', count));", $draw, 'the count says the pages and the links');
	pg_assert_contains('ui.status.parentNode.title = ui.status.textContent;', designer_canvas_bar_fn($js, '_sdProtoStatusFit'), 'a count folded away keeps its words in its zone\'s title');
	pg_assert_contains("el.style.top = Math.max(4, r.bottom - o.top + 2) + 'px';",designer_canvas_bar_fn($js, '_sdProtoProblemsOpen'), 'the problems list opens on the map, under the bar');
	pg_assert_contains("if (e.target.closest && e.target.closest('#sd-proto, .sd-bp-bar.sd-bp-proto')) return;", designer_canvas_bar_fn($js, 'initKeyboard'), 'the editor\'s shortcuts keep off the bar\'s Prototype controls');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	foreach (array(
		'.sd-bp-bar .sd-bp-pv,',
		'.sd-bp-bar.sd-bp-proto .sd-bp-canvas,',
		'.sd-bp-bar.sd-bp-proto .sd-bp-tools,',
		'.sd-bp-bar.sd-bp-proto .sd-bp-zsep-tools { display:none; }',
		'.sd-bp-bar.sd-bp-proto .sd-bp-pv { display:flex; }',
		'.sd-bp-bar[data-fold="1"] .sd-bp-pcount,',
		'.sd-bp-bar .sd-bp-switch.active .sd-vb-switch',
	) as $rule) {
		pg_assert_contains($rule, $css, 'styled: ' . $rule);
	}

	pg_assert_true(strpos($css, '.sd-proto-bar') === false, 'the view\'s own bar is not styled any more');
	pg_assert_contains('<span class="sd-bp-zsep sd-bp-zsep-tools" aria-hidden="true"></span>', designer_canvas_bar_fn($js, 'createCanvasIframe'), 'the line before the canvas\'s tools hides with them');
}

// While fitted the percentage is the frame's real scale, rounded.
function test_designer_canvas_bar_zoom_shown()
{
	$res = designer_canvas_bar_node(array('_sdCanvasZoomPct'), array(
		array(1, 1), array(1.1, 1), array(0.7, 1), array(1, 594 / 1400), array(1, 930 / 1200), array(1, 0),
	), '_sdCanvasZoomPct(c[0], c[1])');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(100, 110, 70, 42, 78, 100), $res, 'the zoom times the fit scale, rounded; no scale reads as none');
}
