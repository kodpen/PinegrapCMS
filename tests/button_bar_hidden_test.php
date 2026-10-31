<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rule in backend.src.css that hides an empty #button_bar hides only a
 * bar with nothing in it: a bar that carries a filter select, tabs, a link,
 * a field or a fact in its grow slot stays visible, even with no .btn in it
 * (pg_filter_select() keeps its Apply button inside <noscript>).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The selectors inside the :has() of the rule that hides #button_bar.
 *
 * @return array|null null when no such rule is found
 */
function button_bar_hidden_test_keep_list()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	if (!preg_match('/#button_bar:not\(:has\((.+?)\)\)\s*\{\s*display:\s*none;?\s*\}/s', $css, $m)) {
		return null;
	}
	return array_map('trim', explode(',', $m[1]));
}

function test_button_bar_hidden_rule_exists_once()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	pg_assert_same(1, preg_match_all('/#button_bar:not\(:has\(/', $css), 'one hiding rule');
	pg_assert_true(button_bar_hidden_test_keep_list() !== null, 'the rule hides with display: none');
}

function test_button_bar_with_a_control_or_a_fact_stays_visible()
{
	$keep = (array) button_bar_hidden_test_keep_list();
	foreach (array(
		'.btn'                        => 'a button',
		'a'                           => 'a link or a nav-pills tab (Cash forecast)',
		'button'                      => 'a plain button',
		'select'                      => 'a pg_filter_select() filter (Repeating invoices, e-invoice inbox)',
		'input:not([type="hidden"])'  => 'a search field',
		'textarea'                    => 'a text field',
		'.pg-toolbar-grow > *'        => 'a grow slot with markup (Backup Manager disk usage)',
		'.pg-toolbar-grow:not(:empty)' => 'a grow slot with plain text',
	) as $selector => $what) {
		pg_assert_true(in_array($selector, $keep, true), 'keeps a bar with ' . $what);
	}
}

function test_button_bar_empty_parts_do_not_keep_it()
{
	// An empty grow spacer, a hidden input or a bare group still count as nothing.
	$keep = (array) button_bar_hidden_test_keep_list();
	foreach (array('.pg-toolbar-grow', 'input', '.btn-group', '*', 'span', 'div') as $selector) {
		pg_assert_false(in_array($selector, $keep, true), $selector . ' alone does not keep the bar');
	}
}
