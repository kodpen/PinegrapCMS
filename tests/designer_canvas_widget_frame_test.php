<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the frame a system widget's page renderer draws around its tree
 * and its mirror on the visual editor's canvas: the express order widget is
 * rendered inside `.container.py-4.pg-eo-wrap` on the page, whose side
 * padding takes in the negative margins of the starter's top-level `.row`;
 * the canvas pads the widget's inner area by the same amount, or the grid
 * sticks out of the canvas and the canvas scrolls sideways.
 *
 * The canvas stylesheet (IFRAME_CSS) is cut out of
 * assets/js/style_designer.src.js and evaluated under Node; on a machine without
 * Node that test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_widget_frame_js()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	return $js;
}

// The body of the named function in style_designer.js, up to the next
// function declared at the same indentation.
function _test_widget_frame_js_function($name)
{
	$js = _test_widget_frame_js();
	$from = strpos($js, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($js, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');
	return substr($js, $from, $to - $from);
}

// The canvas stylesheet rules with the boxed-wrapper marker still in front;
// null without Node.
function _test_widget_frame_iframe_css()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$js = _test_widget_frame_js();
	$from = strpos($js, '    var _SD_BX = ');
	$to = strpos($js, '    // The canvas stylesheet for this design', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has its canvas stylesheet');
	$file = tempnam(sys_get_temp_dir(), 'pgwf');
	file_put_contents($file, 'function _sdT(s) { return s; } function esc(s) { return s; }'
		. substr($js, $from, $to - $from)
		. 'process.stdout.write(JSON.stringify(IFRAME_CSS));');
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	return json_decode(implode("\n", $out), true);
}

// The source of a PHP function, read from its file.
function _test_widget_frame_php_source($name)
{
	$f = new ReflectionFunction($name);
	$lines = file($f->getFileName());
	return implode('', array_slice($lines, $f->getStartLine() - 1, $f->getEndLine() - $f->getStartLine() + 1));
}

// The page draws the designed express order inside a Bootstrap container
// (the empty cart, the designed tree and the built-in layout alike), and the
// starter tree puts its gutter row straight under the widget's root, so the
// container's padding is what keeps the row's negative margins in the page.
function test_designer_canvas_widget_frame_express_order_page_container()
{
	$php = _test_widget_frame_php_source('_render_system_widget_express_order');
	pg_assert_same(2, substr_count($php, "'<div class=\"container py-4 pg-eo-wrap\">'"), 'both express order layouts are drawn inside .container.py-4');
	pg_assert_contains("'<div class=\"container py-4\">'", $php);

	$tree = _test_widget_frame_php_source('_eo_default_designer_tree');
	pg_assert_contains("\$sem('div', 'row g-3 pg-eo-grid', array(", $tree);
	pg_assert_contains("sem('div', 'row g-3 pg-eo-grid', [", _test_widget_frame_js_function('_buildExpressOrderStarterTree'));
}

// The canvas tags a system widget's wrapper with its kind and pads the
// express order's inner area by the container's side padding, for the
// Bootstrap (boxed wrapper) model only; its max-width and py-4 stay page-only.
function test_designer_canvas_widget_frame_express_order_canvas_padding()
{
	pg_assert_contains("wrapper.setAttribute('data-sd-sys-type', String(_sysCfg.regionType));", _test_widget_frame_js_function('buildSharedRefEl'));

	$css = _test_widget_frame_iframe_css();
	if ($css === null) {
		return;
	}
	$found = array();
	foreach ($css as $rule) {
		if (strpos($rule, '[data-sd-sys-type="express_order"]') !== false) {
			$found[] = $rule;
		}
	}
	pg_assert_same(1, count($found), 'one canvas rule frames the express order widget');
	$rule = $found[0];
	pg_assert_same("\x01", $rule[0], 'the rule is a boxed-wrapper (Bootstrap) rule');
	pg_assert_contains('.sd-system-wrap[data-sd-sys-type="express_order"] > .sd-shared-inner {', $rule);
	pg_assert_contains('padding-left:.75rem;', $rule);
	pg_assert_contains('padding-right:.75rem;', $rule);
	pg_assert_false(strpos($rule, 'max-width') !== false, 'the canvas keeps its own width');
}
