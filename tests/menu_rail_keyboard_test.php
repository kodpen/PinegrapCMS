<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the main menu read without a mouse: a row on the icon rail keeps
 * its name for a screen reader (its label is hidden from sight only), and
 * the phone drawer opened from its button takes the keyboard's focus.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function menu_rail_keyboard_test_css()
{
	return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css'));
}

function menu_rail_keyboard_test_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
}

// The declarations of every rule whose selector list is exactly $selector.
function menu_rail_keyboard_test_rules($css, $selector)
{
	$found = array();

	if (preg_match_all('/(?:^|[}{;])\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $matches)) {
		$found = $matches[1];
	}

	return $found;
}

// The body of a named function in backend.src.js, up to the next closing
// brace at its own indent.
function menu_rail_keyboard_test_function($name)
{
	$found = preg_match('/^( *)function ' . preg_quote($name, '/') . '\(.*?\n\1\}\n/ms', menu_rail_keyboard_test_js(), $match);
	pg_assert_true((bool) $found, $name . '() not found');

	return $found ? $match[0] : '';
}

// On the rail the label is hidden, and a row with a count (Communication's
// unread e-mail, the Workspace's news, the flows running) was named by its
// count alone: visibility: hidden takes the label out of the name a screen
// reader reads, and the count is then the row's only text, which wins over
// its title. The label is hidden from sight only.
function test_menu_rail_label_stays_in_the_row_name()
{
	$css = menu_rail_keyboard_test_css();
	$rail = menu_rail_keyboard_test_rules($css, ':where(.software-container:not(.menu-drawer-only)) #menu:not(.expanded) .list-group .list-group-item .list-group-text');

	pg_assert_true(count($rail) > 0, 'the rail rule for the row labels is there');

	foreach ($rail as $body) {
		pg_assert_false((bool) preg_match('/visibility\s*:\s*hidden|display\s*:\s*none/', $body), 'the rail label must stay in the accessibility tree');
		pg_assert_true((bool) preg_match('/opacity\s*:\s*0\s*;/', $body), 'the rail label is hidden from sight');
	}

	// The phone drawer shows the labels again.
	$drawer = menu_rail_keyboard_test_rules($css, '#menu:not(.expanded) .list-group .list-group-item .list-group-text');
	pg_assert_true(count($drawer) > 0, 'the drawer rule for the row labels is there');
	pg_assert_true((bool) preg_match('/opacity\s*:\s*1\s*;/', implode('', $drawer)), 'the drawer shows the labels');
}
