<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the shared admin screen toolbar wraps on narrow screens at the
 * position its controls take in the markup: the location and the search keep
 * full-width lines but no `order`, an empty spacer is hidden instead of taking
 * a line, and the File/Catalog Manager carries no phone order of its own, so
 * Tab and screen readers follow the rows as drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function toolbar_narrow_order_test_shared_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$anchor = strpos($source, '.btn-text.theme-secondary.active {');
	$start = $anchor === false ? false : strpos($source, '@media (max-width: 767.98px) {', $anchor);

	pg_assert_true($start !== false, 'shared toolbar narrow block exists');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}", $start);

	return (string) substr($source, $start, $end === false ? 1200 : $end - $start);
}

function test_toolbar_narrow_order_shared_rules_keep_markup_order()
{
	$block = toolbar_narrow_order_test_shared_block();

	pg_assert_true(strpos($block, 'order:') === false, 'location and search are not reordered');
	pg_assert_true(preg_match('/\.pg-toolbar-grow,\s*\.pg-toolbar-search\s*\{[^}]*flex: 1 1 100%/', $block) === 1, 'location and search keep a full-width line');
	pg_assert_true(preg_match('/\.pg-toolbar-grow:empty\s*\{\s*display: none;\s*\}/', $block) === 1, 'an empty spacer takes no line');
}

function test_toolbar_narrow_order_minified_rules_keep_markup_order()
{
	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.min.css');

	pg_assert_true(preg_match('/\.pg-toolbar-search\s*\{\s*order:/', $min) !== 1, 'minified search is not reordered');
	pg_assert_true(preg_match('/\.pg-toolbar-grow:empty\s*\{\s*display:\s*none;?\s*\}/', $min) === 1, 'minified empty spacer is hidden');
}

function test_toolbar_narrow_order_file_manager_has_no_phone_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	foreach (array('#new_menu_wrap', '#explorer_transfer_wrap', '#up_button', '#explorer_filter_wrap', '#file_scope_wrap', '#explorer_view_group', '#recycle_bin_button', '#explorer_more_wrap') as $id) {
		pg_assert_true(preg_match('/' . preg_quote($id, '/') . '\s*\{[^}]*order:/', $source) !== 1, $id . ' is not reordered');
	}
}
