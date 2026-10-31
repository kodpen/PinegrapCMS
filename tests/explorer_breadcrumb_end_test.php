<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the file manager's location bar (view_folders.php):
 * the breadcrumb is a horizontal scroll container with a hidden scrollbar,
 * so after every draw and on every resize it is scrolled to its end, where
 * the open folder's name is, and a fade on its left edge shows that it
 * scrolls. A unit test cannot measure the layout, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_breadcrumb_end_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// The body of one function in the screen's script, from its declaration to
// the next function declared at the same indent.
function explorer_breadcrumb_end_function($name)
{
	$source = explorer_breadcrumb_end_source();
	$start = strpos($source, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_explorer_breadcrumb_render_draws_then_shows_the_end()
{
	$render = explorer_breadcrumb_end_function('renderBreadcrumb');

	pg_assert_true($render !== '', 'renderBreadcrumb() is found in view_folders.php');
	pg_assert_true(explorer_breadcrumb_end_function('drawBreadcrumb') !== '', 'drawBreadcrumb() is found in view_folders.php');

	$draw = strpos($render, 'drawBreadcrumb();');
	$end = strpos($render, 'showBreadcrumbEnd();');

	pg_assert_true($draw !== false, 'renderBreadcrumb() calls drawBreadcrumb()');
	pg_assert_true(($end !== false) && ($draw < $end), 'renderBreadcrumb() calls showBreadcrumbEnd() after the draw');
	pg_assert_true(strpos($render, 'innerHTML') === false, 'renderBreadcrumb() itself writes no markup');
}

function test_explorer_breadcrumb_end_scrolls_and_marks_the_bar()
{
	$end = explorer_breadcrumb_end_function('showBreadcrumbEnd');

	pg_assert_true($end !== '', 'showBreadcrumbEnd() is found in view_folders.php');
	pg_assert_contains("'#explorer_breadcrumb .breadcrumb'", $end, 'showBreadcrumbEnd() finds the scrolling list');
	pg_assert_contains('.scrollLeft = ', $end, 'showBreadcrumbEnd() sets scrollLeft');
	pg_assert_contains('.scrollWidth', $end, 'showBreadcrumbEnd() scrolls to the full width');
	pg_assert_contains("'is-scrolled'", $end, 'showBreadcrumbEnd() toggles is-scrolled');
}

function test_explorer_breadcrumb_end_follows_resize_and_scroll()
{
	$source = explorer_breadcrumb_end_source();

	pg_assert_contains('if (window.ResizeObserver) {', $source, 'a ResizeObserver is used where the browser has one');
	pg_assert_true(
		(bool) preg_match('/new ResizeObserver\(\s*showBreadcrumbEnd\s*\)\.observe\(\s*document\.getElementById\(\'explorer_breadcrumb\'\)\s*\)/', $source),
		'the ResizeObserver on #explorer_breadcrumb calls showBreadcrumbEnd()'
	);
	pg_assert_contains("window.addEventListener('resize', showBreadcrumbEnd);", $source, 'a window resize falls back to showBreadcrumbEnd()');
	pg_assert_true(
		(bool) preg_match("/getElementById\('explorer_breadcrumb'\)\.addEventListener\('scroll', function[^}]*is-scrolled[^}]*\}, true\);/s", $source),
		'one capture-phase scroll listener on #explorer_breadcrumb toggles is-scrolled'
	);
}

function test_explorer_breadcrumb_markup_is_written_only_by_the_draw()
{
	$source = explorer_breadcrumb_end_source();
	$draw = explorer_breadcrumb_end_function('drawBreadcrumb');
	$outside = str_replace($draw, '', $source);

	pg_assert_true(
		!preg_match("/getElementById\('explorer_breadcrumb'\)\.innerHTML\s*=/", $outside),
		'no innerHTML write to #explorer_breadcrumb outside drawBreadcrumb()'
	);
}

function test_explorer_breadcrumb_fade_rule_exists()
{
	$source = explorer_breadcrumb_end_source();

	pg_assert_contains('#explorer_breadcrumb { position: relative; }', $source, 'the bar holds its fade');
	pg_assert_contains('#explorer_breadcrumb.is-scrolled::before {', $source, 'a scrolled bar fades on its left edge');
	pg_assert_contains('linear-gradient(to right, var(--pg-bg-body), transparent)', $source, 'the fade reads the panel background token');
}
