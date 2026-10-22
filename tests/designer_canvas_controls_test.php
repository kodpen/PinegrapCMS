<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the controls of a page that keep working on the visual editor's
 * canvas: a selected element inside a see-through container is lifted for
 * its outline without moving an element its own classes place (an alert's
 * close button stays in its corner and dismisses the alert).
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

function _test_canvas_controls_js()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	}
	return $js;
}

// Runs $script under Node and returns its JSON output decoded; null without
// Node.
function _test_canvas_controls_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgcc');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	return json_decode(implode("\n", $out), true);
}

// The canvas stylesheet rules, as the editor builds them (with the marker of
// the boxed-wrapper rules still in front).
function _test_canvas_controls_iframe_css()
{
	static $css = false;
	if ($css !== false) {
		return $css;
	}
	$js = _test_canvas_controls_js();
	$from = strpos($js, '    var _SD_BX = ');
	$to = strpos($js, '    // The canvas stylesheet for this design', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has its canvas stylesheet');
	$css = _test_canvas_controls_node('function _sdT(s) { return s; } function esc(s) { return s; }'
		. substr($js, $from, $to - $from)
		. 'process.stdout.write(JSON.stringify(IFRAME_CSS));');
	return $css;
}

// The selectors of a rule's selector list, split at the commas outside
// parentheses (a :not() list keeps its own).
function _test_canvas_controls_selectors($rule)
{
	$list = ltrim(substr($rule, 0, (int) strpos($rule, '{')), "\x01");
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
	$out[] = trim($current);
	return $out;
}

// A selected child of an alert, card body, navbar … is lifted
// (position:relative, z-index) above its neighbours, but an element placed
// by its own classes keeps its place: the close button of a dismissible
// alert jumped out of its corner on mousedown and the click hit the alert.
function test_designer_canvas_controls_selected_lift_skips_placed_elements()
{
	$css = _test_canvas_controls_iframe_css();
	if ($css === null) {
		return;
	}
	$lifts = array();
	foreach ($css as $rule) {
		if (strpos($rule, '.sd-selected >') !== false && preg_match('/\{[^}]*position:\s*relative/', $rule)) {
			$lifts[] = $rule;
		}
	}
	pg_assert_true(count($lifts) >= 2, 'the canvas lifts a selected child in the see-through containers');
	$containers = array();
	foreach ($lifts as $rule) {
		foreach (_test_canvas_controls_selectors($rule) as $selector) {
			if (!preg_match('/^\.([a-z-]+) > \.sd-wrap\.sd-selected > (.*)$/', $selector, $m)) {
				continue;
			}
			$containers[] = $m[1];
			foreach (array('.btn-close', '.position-absolute', '.position-fixed', '.position-sticky', '.sticky-top', '.fixed-top', '.stretched-link', '.sd-tb', '.sd-issue-badge') as $placed) {
				pg_assert_true(preg_match('/:not\([^)]*' . preg_quote($placed, '/') . '\b/', $m[2]) === 1, $m[1] . ': a selected ' . $placed . ' is not lifted');
			}
		}
	}
	foreach (array('alert', 'card-body', 'navbar', 'toast-header', 'btn-group', 'dropdown-menu') as $container) {
		pg_assert_true(in_array($container, $containers, true), 'a selected child of .' . $container . ' is still lifted');
	}
	// The outline itself is drawn on every selected child, a close button
	// included.
	$outlined = false;
	foreach ($css as $rule) {
		if (in_array('.alert > .sd-wrap.sd-selected > *', _test_canvas_controls_selectors($rule), true) && strpos($rule, 'outline:1px solid') !== false && strpos($rule, 'position:') === false) {
			$outlined = true;
		}
	}
	pg_assert_true($outlined, 'a selected child of an alert keeps its outline, without being moved');
}
