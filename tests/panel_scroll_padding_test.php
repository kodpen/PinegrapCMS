<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins the panel's window scroll padding: focus and in-page jumps land below
 * the fixed top bar (and the floating DataTables heading) and above a sticky
 * save bar, and the status boxes of the edit screens stick below the bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_scroll_padding_css()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

/** The declarations of every rule whose selector list names $selector, joined. */
function pg_scroll_padding_rule($css, $selector)
{
	$css = preg_replace('~/\*.*?\*/~s', '', $css);
	if (!preg_match_all('~(?<=^|[;{}])()\s*([^{};]+)\{([^{}]*)\}~', $css, $m, PREG_SET_ORDER)) {
		return null;
	}
	$found = array();
	foreach ($m as $rule) {
		$selectors = array_map(function ($s) {
			return preg_replace('~\s+~', ' ', trim($s));
		}, explode(',', $rule[2]));
		if (in_array($selector, $selectors, true)) {
			$found[] = preg_replace('~\s+~', ' ', trim($rule[3]));
		}
	}
	return $found ? implode(' ', $found) : null;
}

function test_html_scroll_padding_clears_the_top_bar()
{
	$rule = pg_scroll_padding_rule(pg_scroll_padding_css(), 'html');
	pg_assert_true($rule !== null && strpos($rule, 'scroll-padding-top: calc(var(--pg-navbar-h) + 0.5rem)') !== false, 'html clears the fixed top bar: ' . var_export($rule, true));
}

function test_html_scroll_padding_clears_the_floating_list_heading()
{
	$rule = pg_scroll_padding_rule(pg_scroll_padding_css(), 'html:has(.dtfh-floatingparent-head)');
	pg_assert_true($rule !== null && strpos($rule, 'scroll-padding-top: calc(var(--pg-navbar-h) +') !== false, 'the floating heading adds to the top padding');
}

function test_html_scroll_padding_clears_sticky_bottom_bars()
{
	$css = pg_scroll_padding_css();
	foreach (array(
		'html:has(nav.buttons.navigation.position-sticky)',
		'html:has(.position-sticky > nav.buttons.navigation)',
		'html:has(.mpp-bulk:not(.d-none))',
	) as $selector) {
		$rule = pg_scroll_padding_rule($css, $selector);
		pg_assert_true($rule !== null && strpos($rule, 'scroll-padding-bottom:') !== false, $selector . ' sets a bottom padding');
	}
}

function test_no_nested_has_in_scroll_padding_rules()
{
	// A :has() inside a :has() is invalid and drops the whole rule; chained
	// html:has(a):has(b) is fine.
	pg_assert_true(!preg_match('~html(?::has\([^()]*\))*:has\([^()]*:has\(~', pg_scroll_padding_css()), 'no nested :has() on html');
}

function test_window_anchors_do_not_stack_a_large_scroll_margin()
{
	$css = pg_scroll_padding_css();
	$rule = pg_scroll_padding_rule($css, '[id^="pg_pb_sec_"]');
	pg_assert_same('scroll-margin-top: 1.5rem;', $rule);
	$repair = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/software_repair.php');
	pg_assert_true(strpos($repair, 'scroll-margin-top: 5rem') === false, 'software_repair.php anchors drop the 5rem margin');
}

function test_status_boxes_stick_below_the_top_bar()
{
	foreach (array('add_return.php', 'view_return.php', 'privacy_erase.php', 'privacy_export.php') as $file) {
		$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
		pg_assert_true(!preg_match('~class="position-sticky" style="top:1rem;?"~', $src), $file . ' status box sticks at top:3.3rem');
		pg_assert_true(strpos($src, 'class="position-sticky" style="top:3.3rem;"') !== false, $file . ' has the 3.3rem status box');
	}
}
