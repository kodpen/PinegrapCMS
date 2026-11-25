<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a menu's "Mark the section in view" and "After scrolling" rows:
 * the panel's writer of data-pg-scrolled-class (cut out of
 * assets/js/style_designer.src.js and run under Node), the site script's
 * class swap (cut out of assets/js/pg_page_scroll.js), the markers bringing
 * that script to the page, and the renderer printing them as written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source between $from and $to, or '' with a failed assertion.
function _test_nav_scroll_cut($file, $from, $to)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
	$start = strpos($js, $from);
	$end = ($start === false) ? false : strpos($js, $to, $start);
	pg_assert_true($start !== false && $end !== false, 'found in ' . $file . ': ' . $from);
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

// Runs $body under Node with the cut functions; null when Node is missing.
function _test_nav_scroll_node($body)
{
	$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
	if ($node === '') {
		return null;
	}
	$script = _test_nav_scroll_cut('style_designer.src.js', 'function _sdNavScrolledKind(', 'function _sdNavScrollRows(')
		. _test_nav_scroll_cut('pg_page_scroll.js', 'function kind(', 'function setScrolled(')
		. 'process.stdout.write(JSON.stringify((function () {' . $body . '})()));';
	$file = tempnam(sys_get_temp_dir(), 'pgns') . '.js';
	file_put_contents($file, $script);
	$out = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($file) . ' 2>&1');
	@unlink($file);
	$res = json_decode($out, true);
	pg_assert_true(is_array($res), 'the functions run: ' . substr($out, 0, 200));
	return $res;
}

function test_nav_scroll_marker()
{
	$p = pg_page_scroll_pattern();
	pg_assert_true((bool) preg_match($p, '<nav class="navbar" data-pg-scrolled="80" data-pg-scrolled-class="shadow">'), 'After scrolling');
	pg_assert_true((bool) preg_match($p, '<nav class="navbar" data-pg-spy="">'), 'Mark the section in view');
	pg_assert_false((bool) preg_match($p, '<nav data-pg-scrolled-class="shadow">'), 'the class list alone is no marker');
	pg_assert_false((bool) preg_match($p, '<nav data-pg-spying="1">'), 'a longer attribute name');
}

function test_nav_scroll_attrs_print_and_bring_the_script()
{
	$nav = array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'navbar bg-body', '_attrs' => array(
		array('name' => 'data-pg-spy', 'value' => ''),
		array('name' => 'data-pg-scrolled', 'value' => '120'),
		array('name' => 'data-pg-scrolled-class', 'value' => 'bg-dark navbar-dark shadow py-1'),
	)), 'children' => array());
	$html = _render_tree_node($nav);
	pg_assert_contains('data-pg-spy=""', $html);
	pg_assert_contains('data-pg-scrolled="120"', $html);
	pg_assert_contains('data-pg-scrolled-class="bg-dark navbar-dark shadow py-1"', $html);
	pg_assert_contains('pg_page_scroll.js', pg_page_scroll_inject('<html><body>' . $html . '</body></html>', '/pg_page_scroll.js'));

	$plain = array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'navbar bg-body'), 'children' => array());
	$page = '<html><body>' . _render_tree_node($plain) . '</body></html>';
	pg_assert_same($page, pg_page_scroll_inject($page, '/pg_page_scroll.js'), 'a menu with both rows off gets nothing');
}

function test_nav_scroll_panel_writer()
{
	$r = _test_nav_scroll_node(
		'var a = "shadow py-1";'
		. 'return {'
		. ' bg: _sdNavScrolledClass(a, "bg", "bg-dark navbar-dark"),'
		. ' swap: _sdNavScrolledClass("bg-dark navbar-dark shadow py-1", "bg", "bg-body"),'
		. ' clear: _sdNavScrolledClass("shadow py-1", "shadow", ""),'
		. ' all: _sdNavScrolledClass("shadow", "height", ""),'
		. ' odd: _sdNavScrolledClass("shadow evil", "height", "py-0"),'
		. ' parts: _sdNavScrolledParts("py-1 bg-primary navbar-dark shadow-lg")'
		. '};'
	);
	if ($r === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('bg-dark navbar-dark shadow py-1', $r['bg'], 'a background joins the defaults');
	pg_assert_same('bg-body shadow py-1', $r['swap'], 'a background replaces the one before');
	pg_assert_same('py-1', $r['clear'], 'Unchanged takes its class out');
	pg_assert_same('shadow', $r['all']);
	pg_assert_same('shadow py-0', $r['odd'], 'a class of no list is dropped');
	pg_assert_same(array('bg' => 'bg-primary navbar-dark', 'shadow' => 'shadow-lg', 'height' => 'py-1'), $r['parts']);
}

function test_nav_scroll_site_swap()
{
	$r = _test_nav_scroll_node(
		'var drawn = "navbar navbar-expand-lg navbar-light bg-body shadow-sm py-3 sticky-top";'
		. 'return {'
		. ' on: scrolledClasses(drawn, "bg-dark navbar-dark shadow py-1", true),'
		. ' off: scrolledClasses(drawn, "bg-dark navbar-dark shadow py-1", false),'
		. ' keep: scrolledClasses("navbar bg-body bg-opacity-75 bg-gradient", "bg-dark", true),'
		. ' empty: scrolledClasses("navbar shadow-sm", "", true)'
		. '};'
	);
	if ($r === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('navbar navbar-expand-lg sticky-top bg-dark navbar-dark shadow py-1', $r['on'], 'the drawn classes of the same kinds step aside');
	pg_assert_same('navbar navbar-expand-lg navbar-light bg-body shadow-sm py-3 sticky-top', $r['off'], 'back above the distance: as drawn');
	pg_assert_same('navbar bg-opacity-75 bg-gradient bg-dark', $r['keep'], 'opacity and gradient are no background colour');
	pg_assert_same('navbar shadow-sm', $r['empty']);
}
