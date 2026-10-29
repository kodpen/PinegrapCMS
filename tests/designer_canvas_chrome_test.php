<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's chrome around the canvas: the context bar
 * keeps its row whether or not it shows anything, so selecting an element on
 * press does not move the canvas under the pointer before the release; an
 * element's toolbar goes above it, below it when there is no room above (the
 * top of the canvas) and inside it only when there is no room below either;
 * the page root's name tag sits at the bottom-left corner of the canvas.
 * _sdTbSpot() is cut out of assets/js/style_designer.src.js and run under Node
 * (skipped on a machine without Node); the rest is read from the sources.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_canvas_chrome_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function in style_designer.js, up to the next
// function declared at the same indentation.
function designer_canvas_chrome_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs each [top, bottom, viewport height] through _sdTbSpot(); null without Node.
function designer_canvas_chrome_test_spots($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = designer_canvas_chrome_test_function(designer_canvas_chrome_test_source(), '_sdTbSpot')
		. 'console.log(JSON.stringify(' . json_encode($cases) . '.map(function (c) { return _sdTbSpot(c[0], c[1], c[2]); })));';
	$file = tempnam(sys_get_temp_dir(), 'pgtbspot');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Room above: above, as before. At the top of the canvas: under the element,
// unless the element reaches the bottom of the viewport too, then inside it.
// An element scrolled half out at the top gets it under its visible part.
function test_designer_canvas_toolbar_spot()
{
	$answer = designer_canvas_chrome_test_spots(array(
		array(22, 62, 600),
		array(400, 440, 600),
		array(11, 51, 600),
		array(0, 40, 600),
		array(18, 42, 600),
		array(0, 578, 600),
		array(0, 579, 600),
		array(0, 601, 600),
		array(-300, 40, 600),
		array(-300, 900, 600),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('above', 'above', 'below', 'below', 'below', 'below', 'inside', 'inside', 'below', 'inside'), $answer);
}

// Every place that writes the toolbar's spot uses _sdTbSpot(): the hover, the
// scroll pass, the full pass after a render and the script placement of
// see-through wrappers' chrome. No path decides "inside" from the top alone.
function test_designer_canvas_toolbar_spot_is_used_everywhere()
{
	$source = designer_canvas_chrome_test_source();

	pg_assert_false(strpos($source, "'sd-tb-inside', tops[") !== false, 'no toolbar flip decided from the top alone');
	pg_assert_false(strpos($source, "'sd-tb-inside', _sdBoxRect(") !== false, 'no hover flip decided from the top alone');
	pg_assert_contains('_sdTbSpot(r.top, r.bottom, _sdCanvasViewH())', designer_canvas_chrome_test_function($source, '_sdTbFlip'));
	pg_assert_contains('_sdTbFlipAll(ws);', designer_canvas_chrome_test_function($source, '_sdOnCanvasScroll'));
	pg_assert_contains("_sdTbFlipAll(Array.prototype.slice.call(canvasDoc.querySelectorAll('div.sd-wrap')));", designer_canvas_chrome_test_function($source, 'adjustCanvasToolbars'));

	$place = designer_canvas_chrome_test_function($source, '_sdPlaceChromeOf');
	pg_assert_contains('_sdTbSpot(er.top, er.bottom, win.innerHeight)', $place);
	pg_assert_contains("spot === 'below' ? er.bottom + 2", $place);

	pg_assert_contains("'.sd-wrap.sd-tb-below > .sd-tb { top:calc(100% + 2px) !important;", $source);
	pg_assert_contains("'.sd-wrap.sd-tb-inside > .sd-tb { top:2px !important;", $source);
	// The root's tag at the canvas's bottom-left beats both spot rules.
	pg_assert_contains("'body > .sd-wrap[data-sd-type=\"root\"] > .sd-tb { position:fixed; top:auto !important; bottom:0; left:0;", $source);

	// The size badge goes after a toolbar drawn under the element.
	pg_assert_contains("wrap.classList.contains('sd-tb-below')", designer_canvas_chrome_test_function($source, '_positionSizeBadge'));
}

// The context bar is never shown or hidden with display: only its contents
// are hidden (sd-ctx-idle) and the row stays, at one fixed height, never
// wrapping onto a second line.
function test_designer_context_bar_keeps_its_row()
{
	$source = designer_canvas_chrome_test_source();

	pg_assert_contains('\'<div class="sd-ctx-bar sd-ctx-idle" id="sd-ctx-bar">\'', $source);
	pg_assert_false(strpos($source, 'id="sd-ctx-bar" style="display:none"') !== false, 'the bar is not hidden with display');

	$update = designer_canvas_chrome_test_function($source, 'updateCtxBar');
	pg_assert_false(strpos($update, 'bar.style.display') !== false, 'updateCtxBar() never changes the bar\'s display');
	pg_assert_contains('_sdCtxBarIdle(bar, true);', $update);
	pg_assert_contains('_sdCtxBarIdle(bar, false);', $update);

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	$from = strpos($css, "\n.sd-ctx-bar {");
	pg_assert_true($from !== false, 'style_designer.css has the .sd-ctx-bar rule');
	$rule = substr($css, $from, strpos($css, '}', $from) - $from);
	pg_assert_contains('height: 35px;', $rule);
	pg_assert_contains('flex-wrap: nowrap;', $rule);
	pg_assert_contains('overflow-x: auto;', $rule);
	pg_assert_contains('.sd-ctx-bar.sd-ctx-idle > * { visibility: hidden; }', $css);
	pg_assert_contains('.sd-ctx-options  { display: flex; align-items: center; flex-wrap: nowrap;', $css);
}
