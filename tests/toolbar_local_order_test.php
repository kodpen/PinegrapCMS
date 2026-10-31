<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the image picker and prototype viewer toolbars wrap on narrow
 * screens at the position their controls take in the markup, so Tab and
 * screen readers follow the order the controls are drawn in: their phone
 * rules keep a full-width line but no `order`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function toolbar_local_order_test_block($file, $media)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, '@media (max-width: ' . $media . ') {');

	pg_assert_true($start !== false, $file . ' phone block exists');

	if ($start === false) {
		return '';
	}

	return (string) substr($source, $start, 1200);
}

function test_toolbar_local_order_image_picker_filters_keep_markup_order()
{
	$block = toolbar_local_order_test_block('editor_select_image.php', '767.98px');

	pg_assert_true(preg_match('/#files_nav_controls\s*\{([^}]*)\}/', $block, $rule) === 1, 'filter rule found');
	pg_assert_true(strpos($rule[1], 'order:') === false, 'filters are not reordered');
	pg_assert_true(strpos($rule[1], 'flex-basis: 100%') !== false, 'filters keep a full-width line');
	pg_assert_true(preg_match('/#files_nav_controls \+ \.navbar-nav\s*\{[^}]*margin-left: auto/', $block) === 1, 'Refresh and Close stay at the right end');
}

function test_toolbar_local_order_prototype_nav_keeps_markup_order()
{
	$block = toolbar_local_order_test_block('includes/fn/prototype.php', '575.98px');

	pg_assert_true(preg_match('/\.pg-proto-nav\s*\{([^}]*)\}/', $block, $rule) === 1, 'nav rule found');
	pg_assert_true(strpos($rule[1], 'order:') === false, 'page navigation is not reordered');
	pg_assert_true(strpos($rule[1], 'flex: 1 1 100%') !== false, 'page navigation keeps a full-width line');
}
