<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's canvas against the published page of a
 * Bootstrap design: the width buttons give the canvas the width they name,
 * and the canvas stylesheet re-creates the Bootstrap rules its node wrappers
 * break (row-cols-* and row child widths, a responsive display class on a
 * column, breadcrumb dividers, list group borders, floating labels, a full
 * width inline element, inline wrappers on the text line).
 *
 * The canvas stylesheet (IFRAME_CSS) is cut out of
 * assets/js/style_designer.js and evaluated under Node; on a machine without
 * Node those tests are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_canvas_bs_js()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	}
	return $js;
}

// The canvas stylesheet entries, boxed-wrapper marker still in front; null
// without Node.
function _test_canvas_bs_iframe_css()
{
	static $css = false;
	if ($css !== false) {
		return $css;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		$css = null;
		return $css;
	}
	$js = _test_canvas_bs_js();
	$from = strpos($js, '    var _SD_BX = ');
	$to = strpos($js, '    // The canvas stylesheet for this design', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has its canvas stylesheet');
	$file = tempnam(sys_get_temp_dir(), 'pgbs');
	file_put_contents($file, 'function _sdT(s) { return s; } function esc(s) { return s; }'
		. substr($js, $from, $to - $from)
		. 'process.stdout.write(JSON.stringify(IFRAME_CSS));');
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	$css = json_decode(implode("\n", $out), true);
	return $css;
}

// The canvas rules of a Bootstrap design (boxed wrappers) as one
// stylesheet text, each entry on a line of its own, marker dropped; with
// $only_boxed the entries for both wrapper models are left out.
function _test_canvas_bs_boxed_rules($only_boxed = true)
{
	$css = _test_canvas_bs_iframe_css();
	if ($css === null) {
		return null;
	}
	$text = '';
	foreach ($css as $entry) {
		if ($entry !== '' && $entry[0] === "\x01") {
			$text .= substr($entry, 1) . "\n";
		} elseif (!$only_boxed) {
			$text .= $entry . "\n";
		}
	}
	return $text;
}

// The declarations of the first rule whose selector list holds $selector,
// read from a stylesheet text (inside an @media block when $media is given).
function _test_canvas_bs_decls($text, $selector, $media = '')
{
	if ($media !== '') {
		$head = '@media (' . $media . '){';
		$at = strpos($text, $head);
		while ($at !== false) {
			$end = strpos($text, '}}', $at);
			$block = substr($text, $at + strlen($head), $end - $at - strlen($head) + 1);
			$found = _test_canvas_bs_decls($block, $selector);
			if ($found !== null) {
				return $found;
			}
			$at = strpos($text, '@media (' . $media . '){', $at + 1);
		}
		return null;
	}
	$offset = 0;
	while (($open = strpos($text, '{', $offset)) !== false) {
		$close = strpos($text, '}', $open);
		$start = max(strrpos(substr($text, 0, $open), '}'), strrpos(substr($text, 0, $open), "\n"), strrpos(substr($text, 0, $open), '{'));
		$selectors = trim(substr($text, $start === false ? 0 : $start + 1, $open - ($start === false ? 0 : $start + 1)));
		foreach (_test_canvas_bs_split($selectors) as $s) {
			if ($s === $selector) {
				return trim(substr($text, $open + 1, $close - $open - 1));
			}
		}
		$offset = $close + 1;
	}
	return null;
}

// A selector list split at the commas outside parentheses.
function _test_canvas_bs_split($list)
{
	$out = array();
	$depth = 0;
	$current = '';
	for ($i = 0, $n = strlen($list); $i < $n; $i++) {
		$c = $list[$i];
		if ($c === '(') {
			$depth++;
		} elseif ($c === ')') {
			$depth--;
		} elseif ($c === ',' && $depth === 0) {
			$out[] = trim($current);
			$current = '';
			continue;
		}
		$current .= $c;
	}
	if (trim($current) !== '') {
		$out[] = trim($current);
	}
	return $out;
}

// The declarations Bootstrap's bundled stylesheet gives $selector (inside
// the @media block that starts at $min px when given).
function _test_canvas_bs_bootstrap_decls($selector, $min = 0)
{
	static $bs = null;
	if ($bs === null) {
		$bs = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css');
	}
	if ($min) {
		$head = '@media (min-width:' . $min . 'px){';
		$at = strpos($bs, $head);
		while ($at !== false) {
			$end = strpos($bs, '}}', $at);
			$found = _test_canvas_bs_decls(substr($bs, $at + strlen($head), $end - $at - strlen($head) + 1), $selector);
			if ($found !== null) {
				return $found;
			}
			$at = strpos($bs, '@media (min-width:' . $min . 'px){', $at + 1);
		}
		return null;
	}
	return _test_canvas_bs_decls($bs, $selector);
}

// Bootstrap's left-to-right declarations in logical terms (the canvas
// writes the sides that way, so a right-to-left design is mirrored).
function _test_canvas_bs_logical($decls)
{
	return strtr((string) $decls, array(
		'padding-left' => 'padding-inline-start',
		'padding-right' => 'padding-inline-end',
		'float:left' => 'float:inline-start',
		'border-left-width' => 'border-inline-start-width',
		'margin-left' => 'margin-inline-start',
	));
}

// The width buttons set the frame wrapper's width; its side lines took two
// of those pixels, so "768px · MD" drew the canvas at 766 px and Bootstrap
// laid it out at the SM breakpoint. The lines take no width now.
function test_designer_canvas_width_buttons_give_the_canvas_their_width()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	$blocks = array();
	if (preg_match_all('/(?:^|\n)\.sd-iframe-wrap\s*\{([^}]*)\}/', $css, $m)) {
		$blocks = $m[1];
	}
	pg_assert_true(count($blocks) >= 1, 'the editor stylesheet styles the canvas frame wrapper');
	foreach ($blocks as $body) {
		pg_assert_false((bool) preg_match('/(^|[;\s])border(-left|-right|-inline)?(-width)?\s*:/', $body), 'the frame wrapper has no border taking width: ' . trim($body));
		pg_assert_false((bool) preg_match('/(^|[;\s])padding(-left|-right|-inline)?\s*:/', $body), 'the frame wrapper has no side padding taking width');
	}
	$last = end($blocks);
	pg_assert_contains('-1px 0 0 var(--sd-panel-border)', $last, 'the left side line is a shadow');
	pg_assert_contains('1px 0 0 var(--sd-panel-border)', $last, 'the right side line is a shadow');
	pg_assert_contains('.sd-iframe-wrap iframe { width:100%;', $css, 'the canvas frame fills the wrapper');
}

// row-cols-* sizes the row's children: the canvas gives the element in the
// wrapper the width Bootstrap gives a direct child, at every breakpoint,
// and leaves an element with a col-* class of its own to that class.
function test_designer_canvas_row_cols_widths_follow_bootstrap()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	$el = ':not([class*="col-"]):not(.sd-tb):not(.sd-persistent-note):not(.sd-issue-badge)';
	$breakpoints = array('' => 0, '-sm' => 576, '-md' => 768, '-lg' => 992, '-xl' => 1200, '-xxl' => 1400);
	foreach ($breakpoints as $infix => $min) {
		foreach (array('auto', '1', '2', '3', '4', '5', '6') as $n) {
			$page = _test_canvas_bs_bootstrap_decls('.row-cols' . $infix . '-' . $n . '>*', $min);
			pg_assert_true($page !== null, 'Bootstrap has .row-cols' . $infix . '-' . $n);
			$sel = '.row-cols' . $infix . '-' . $n . ' > .sd-wrap:not([data-sd-type="col"]) > ' . $el;
			$canvas = _test_canvas_bs_decls($text, $sel, $min ? 'min-width:' . $min . 'px' : '');
			pg_assert_true($canvas !== null, 'the canvas sizes ' . $sel . ($min ? ' from ' . $min . 'px' : ''));
			pg_assert_same(str_replace(' ', '', rtrim($page, ';')), str_replace(' ', '', rtrim((string) $canvas, ';')), 'row-cols' . $infix . '-' . $n . ' gives the same size');
		}
	}
	// Smallest first, as in Bootstrap: a wider breakpoint's rule comes later.
	pg_assert_true(strpos($text, '.row-cols-6 > .sd-wrap') < strpos($text, '.row-cols-sm-1 > .sd-wrap'), 'the breakpoints are in Bootstrap order');
	pg_assert_true(strpos($text, '.row-cols-lg-6 > .sd-wrap') < strpos($text, '.row-cols-xl-1 > .sd-wrap'), 'the breakpoints are in Bootstrap order');
}

// `.row > *` gives a row's child the full width before its col-* class
// does; on the canvas that size goes on the element with zero specificity,
// so the element's own col-* class still wins.
function test_designer_canvas_row_child_full_width_with_zero_specificity()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	$decls = _test_canvas_bs_decls($text, ':where(.row > .sd-wrap:not([data-sd-type="col"]) > :not(.sd-tb):not(.sd-persistent-note):not(.sd-issue-badge))');
	pg_assert_true($decls !== null, 'the canvas sizes a row child across its wrapper');
	pg_assert_contains('flex-shrink:0', $decls);
	pg_assert_contains('width:100%', $decls);
	pg_assert_contains('max-width:100%', $decls);
	pg_assert_false(strpos((string) $decls, '!important') !== false, 'a col-* class beats it');
}

// The editor hides its own wrappers with d-none; a column's wrapper is the
// column itself and carries its classes, so a responsive "d-none d-lg-block"
// column is left to Bootstrap (it never showed on the canvas).
function test_designer_canvas_column_display_classes_left_to_bootstrap()
{
	$css = _test_canvas_bs_iframe_css();
	if ($css === null) {
		return;
	}
	$rule = null;
	foreach ($css as $entry) {
		if (strpos($entry, '.sd-wrap.d-none') !== false && strpos($entry, 'display:none') !== false) {
			$rule = $entry;
		}
	}
	pg_assert_true($rule !== null, 'the canvas hides the editor\'s own d-none wrappers');
	$selectors = _test_canvas_bs_split(substr($rule, 0, strpos($rule, '{')));
	pg_assert_true(in_array('.sd-wrap.d-none:not([data-sd-type="col"])', $selectors, true), 'a column wrapper is not hidden by the canvas: ' . $rule);
	pg_assert_false(in_array('.sd-wrap.d-none', $selectors, true), 'no plain .sd-wrap.d-none selector');
	pg_assert_contains("wrapper.className += ' ' + buildColClasses(node.props);", _test_canvas_bs_js(), 'a column wrapper carries the column classes');
}

// An item that follows another draws the divider (and its space), across
// the wrappers too.
function test_designer_canvas_breadcrumb_dividers()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	pg_assert_same(str_replace(' ', '', _test_canvas_bs_logical(_test_canvas_bs_bootstrap_decls('.breadcrumb-item+.breadcrumb-item'))),
		str_replace(' ', '', rtrim((string) _test_canvas_bs_decls($text, '.breadcrumb > .sd-wrap + .sd-wrap > .breadcrumb-item'), ';')), 'the item space');
	pg_assert_same(str_replace(' ', '', _test_canvas_bs_logical(_test_canvas_bs_bootstrap_decls('.breadcrumb-item+.breadcrumb-item::before'))),
		str_replace(' ', '', rtrim((string) _test_canvas_bs_decls($text, '.breadcrumb > .sd-wrap + .sd-wrap > .breadcrumb-item::before'), ';')), 'the divider');
}

// List group borders between items, of a horizontal list at every
// breakpoint, and of a flush list.
function test_designer_canvas_list_group_borders()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	$same = function ($page, $canvas, $label) {
		pg_assert_true($canvas !== null, $label . ' is on the canvas');
		pg_assert_same(str_replace(' ', '', rtrim((string) $page, ';')), str_replace(' ', '', rtrim((string) $canvas, ';')), $label);
	};
	$same(_test_canvas_bs_bootstrap_decls('.list-group-item+.list-group-item'), _test_canvas_bs_decls($text, '.list-group > .sd-wrap + .sd-wrap > .list-group-item'), 'the seam between items');
	$same(_test_canvas_bs_bootstrap_decls('.list-group-flush>.list-group-item'), _test_canvas_bs_decls($text, '.list-group-flush > .sd-wrap > .list-group-item'), 'a flush item');
	$same(_test_canvas_bs_bootstrap_decls('.list-group-flush>.list-group-item:last-child'), _test_canvas_bs_decls($text, '.list-group-flush > .sd-wrap:last-child > .list-group-item'), 'the last flush item');
	foreach (array('' => 0, '-sm' => 576, '-md' => 768, '-lg' => 992, '-xl' => 1200, '-xxl' => 1400) as $infix => $min) {
		$same(_test_canvas_bs_logical(_test_canvas_bs_bootstrap_decls('.list-group-horizontal' . $infix . '>.list-group-item+.list-group-item', $min)),
			_test_canvas_bs_decls($text, '.list-group-horizontal' . $infix . ' > .sd-wrap + .sd-wrap > .list-group-item', $min ? 'min-width:' . $min . 'px' : ''),
			'the seam of a horizontal' . $infix . ' list');
	}
}

// A floating label lies over its control and the control has the floating
// size, across the wrappers.
function test_designer_canvas_floating_labels()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	$control = _test_canvas_bs_decls($text, '.form-floating > .sd-wrap > .form-control');
	pg_assert_true($control !== null, 'the canvas sizes a floating control');
	pg_assert_contains('height:calc(3.5rem + calc(var(--bs-border-width) * 2))', $control);
	$label = _test_canvas_bs_decls($text, '.form-floating > .sd-wrap > label');
	pg_assert_true($label !== null, 'the canvas places a floating label');
	pg_assert_contains('position:absolute', $label);
	pg_assert_contains('height:100%', $label);
	pg_assert_false(strpos((string) $label, 'pointer-events') !== false, 'the label stays pickable on the canvas');
	$up = _test_canvas_bs_decls($text, '.form-floating > .sd-wrap:has(> .form-control:not(:placeholder-shown)) ~ .sd-wrap > label');
	pg_assert_same(str_replace(' ', '', _test_canvas_bs_bootstrap_decls('.form-floating>.form-control:not(:placeholder-shown)~label')), str_replace(' ', '', rtrim((string) $up, ';')), 'a filled control lifts its label');
}

// Inline wrappers sit on the text line like the element they hold: on the
// baseline and without the block wrappers' vertical margin (a middle-aligned
// or margined inline-block made each line taller than on the page).
function test_designer_canvas_inline_wrappers_on_the_line()
{
	$text = _test_canvas_bs_boxed_rules(false);
	if ($text === null) {
		return;
	}
	foreach (array('.sd-wrap.sd-wrap-inline', '.sd-wrap[data-content-type="icon"]', '.sd-wrap[data-content-type="link"]', '.sd-wrap[data-content-type="badge"]', '.sd-wrap[data-component-type="badge"]') as $sel) {
		$decls = _test_canvas_bs_decls($text, $sel);
		pg_assert_true($decls !== null, $sel . ' has its display rule');
		pg_assert_contains('vertical-align:baseline', $decls, $sel . ' on the baseline');
		pg_assert_contains('margin:0', $decls, $sel . ' has no vertical margin');
	}
	// A button is middle-aligned on the page too; it only loses the margin.
	foreach (array('.sd-wrap[data-component-type="btn"]', '.sd-wrap[data-content-type="button"]') as $sel) {
		$decls = _test_canvas_bs_decls($text, $sel);
		pg_assert_true($decls !== null, $sel . ' has its display rule');
		pg_assert_contains('vertical-align:middle', $decls, $sel . ' stays middle-aligned');
		pg_assert_contains('margin:0', $decls, $sel . ' has no vertical margin');
	}
}

// An inline element told to fill its line (btn.w-100) gets a wrapper as
// wide as the line, so the percentage applies to the parent.
function test_designer_canvas_full_width_inline_element()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	foreach (array('.sd-wrap.sd-wrap-inline:has(> .w-100)', '.sd-wrap[data-component-type="btn"]:has(> .w-100)', '.sd-wrap[data-content-type="button"]:has(> .w-100)', '.sd-wrap[data-content-type="link"]:has(> .w-100)') as $sel) {
		$decls = _test_canvas_bs_decls($text, $sel);
		pg_assert_true($decls !== null, $sel . ' is widened');
		pg_assert_contains('display:block', $decls, $sel);
	}
}

// The first accordion item keeps its top border and outer corners, the
// others lose the last one's corners; a flush accordion has no outer lines.
function test_designer_canvas_accordion_item_borders()
{
	$text = _test_canvas_bs_boxed_rules();
	if ($text === null) {
		return;
	}
	$first = _test_canvas_bs_decls($text, '.accordion > .sd-wrap:first-child > .accordion-item');
	pg_assert_true($first !== null, 'the canvas draws the first item\'s top');
	pg_assert_contains('border-top:var(--bs-accordion-border-width) solid var(--bs-accordion-border-color)', $first);
	pg_assert_contains('border-top-left-radius:var(--bs-accordion-border-radius)', $first);
	$middle = _test_canvas_bs_decls($text, '.accordion > .sd-wrap:not(:last-child) > .accordion-item');
	pg_assert_contains('border-bottom-right-radius:0', (string) $middle);
	pg_assert_contains('border-top:0', (string) _test_canvas_bs_decls($text, '.accordion-flush > .sd-wrap:first-child > .accordion-item'));
	pg_assert_contains('border-bottom:0', (string) _test_canvas_bs_decls($text, '.accordion-flush > .sd-wrap:last-child > .accordion-item'));
	pg_assert_contains('border-left:0', (string) _test_canvas_bs_decls($text, '.accordion-flush > .sd-wrap > .accordion-item'));
	// Bootstrap does it by type order, which the toolbar in the wrapper breaks.
	pg_assert_true(_test_canvas_bs_bootstrap_decls('.accordion-item:not(:first-of-type)') === 'border-top:0', 'Bootstrap drops the top border of a later item');
}

// _sdFxFits(): a boxed wrapper drops its empty-wrapper minimum when its
// element is shown, in the flow, has a bottom margin and is shorter than the
// minimum (Chromium drops that margin under a min-height); an empty element,
// or one too thin to pick (a divider's 1px line), keeps the minimum as its
// handle. Heights are compared in CSS pixels, the canvas zoom divided out.
function test_designer_canvas_fit_short_element_keeps_its_margin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$js = _test_canvas_bs_js();
	pg_assert_true(strpos($js, '    function _sdFxFits(') !== false, 'the designer has _sdFxFits()');
	// The function with the grab height declared above it.
	$from = strpos($js, '    var _SD_FX_FIT_MIN_GRAB = ');
	pg_assert_true($from !== false && $from < strpos($js, '    function _sdFxFits('), 'the grab height is declared before it');
	$to = strpos($js, "\n    }\n", $from);
	$fn = substr($js, $from, $to - $from + 7);
	$script = $fn . '
		function run(c) {
			var w = { _sdFitMin: c.kept || 0, currentCSSZoom: c.zoom };
			var el = { getBoundingClientRect: function () { return { height: c.h }; } };
			var cs = { display: c.display || "flex", position: c.position || "static", cssFloat: c.float || "none", marginBottom: c.mb };
			var win = { getComputedStyle: function () { return { display: c.wdisplay || "block", paddingBottom: c.pb || "0px", borderBottomWidth: "0px", minHeight: c.had ? "0px" : (c.min || "28px") }; } };
			var r = _sdFxFits(w, el, cs, win, !!c.had);
			return r + (r ? ":" + w._sdFitMin : "");
		}
		process.stdout.write(JSON.stringify([
			run({ h: 27, mb: "8px" }),
			run({ h: 40, mb: "8px" }),
			run({ h: 27, mb: "0px" }),
			run({ h: 0, mb: "8px" }),
			run({ h: 27, mb: "8px", pb: "8px" }),
			run({ h: 27, mb: "8px", position: "absolute" }),
			run({ h: 27, mb: "8px", display: "none" }),
			run({ h: 27, mb: "8px", wdisplay: "inline-block" }),
			run({ h: 27, mb: "8px", min: "auto" }),
			run({ h: 27, mb: "8px", had: true, kept: 28 }),
			run({ h: 30, mb: "8px", had: true, kept: 28 }),
			run({ h: 30, mb: "8px", min: "36px" }),
			run({ h: 1, mb: "16px" }),
			run({ h: 11, mb: "8px" }),
			run({ h: 12, mb: "8px" }),
			run({ h: 40.5, mb: "8px", zoom: 1.5 }),
			run({ h: 40.5, mb: "8px", zoom: 1.5, had: true, kept: 28 }),
			run({ h: 1.5, mb: "16px", zoom: 1.5 }),
			run({ h: 20, mb: "8px", zoom: 0.5 }),
			run({ h: 9, mb: "8px", zoom: 0.5 })
		]));';
	$file = tempnam(sys_get_temp_dir(), 'pgfit');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	pg_assert_same(array(
		'true:28', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'true:28', 'false', 'true:36',
		// A divider, and an element just under and at the grab height.
		'false', 'false', 'true:28',
		// At 150%: a 27px item still fits, a divider still keeps its box.
		'true:28', 'true:28', 'false',
		// At 50%: a 40px item is no short element, an 18px one is.
		'false', 'true:28'
	), json_decode(implode("\n", $out), true));

	$css = _test_canvas_bs_iframe_css();
	if ($css !== null) {
		pg_assert_true(in_array('.sd-wrap.sd-fx-fit { min-height:0; }', $css, true), 'the canvas drops the minimum of a fitted wrapper');
	}
	$pass = substr($js, strpos($js, '    function _sdCanvasFidelityPass('), 9000);
	pg_assert_contains('_sdFxFits(w, el, cs, win, fitHad)', $pass, 'the fidelity pass measures it');
	pg_assert_contains("addFit.forEach(function (w) { w.classList.add('sd-fx-fit'); });", $pass, 'and writes it after measuring');
}

// A selected floating label is not lifted out of its place over the control
// (the selection lift is position:relative), or the second click of a
// double-click misses it.
function test_designer_canvas_floating_label_not_lifted()
{
	$css = _test_canvas_bs_iframe_css();
	if ($css === null) {
		return;
	}
	$found = false;
	foreach ($css as $entry) {
		if (strpos($entry, '.sd-selected >') === false || strpos($entry, 'position:relative; z-index:2;') === false) {
			continue;
		}
		foreach (_test_canvas_bs_split(substr($entry, 1, strpos($entry, '{') - 1)) as $sel) {
			if (strpos($sel, '.form-floating > .sd-wrap.sd-selected > ') === 0) {
				$found = true;
				pg_assert_true(substr($sel, -strlen(':not(label)')) === ':not(label)', 'a floating label keeps its place when selected: ' . $sel);
			}
		}
	}
	pg_assert_true($found, 'a selected child of .form-floating is still lifted');
}
