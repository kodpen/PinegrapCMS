<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel entries that used to be out of the keyboard's
 * reach: the Pages menu's Update Search Index entry, the Page Designer's
 * region type choices and console header, and the order print view's
 * product links.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function keyboard_skipped_entries_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// An anchor without href takes no focus; the entry keeps the panel's anchor
// styles and stays out of the current-page mark.
function test_update_search_index_menu_entry_takes_focus()
{
	$source = keyboard_skipped_entries_source('includes/fn/output.php');

	pg_assert_false(strpos($source, "<a  onclick='open_search_index_window();'") !== false, 'menu entry without href');
	pg_assert_true(strpos($source, "<a href='#' role='button' data-pg-menu-skip onclick='open_search_index_window(); return false;'") !== false, 'menu entry is a focusable button-role link');
}

// The region type choices are buttons with their ids kept for the wiring.
function test_page_designer_region_choices_are_buttons()
{
	$source = keyboard_skipped_entries_source('assets/js/page_designer.js');

	pg_assert_false((bool) preg_match('/<a type="button" id="[pcd]regionButton"/', $source), 'region choice as an anchor without href');
	foreach (array('pregionButton', 'cregionButton', 'dregionButton') as $id) {
		pg_assert_true(strpos($source, '<button type="button" id="' . $id . '" class="btn text-start my-1"') !== false, $id . ' is a button');
	}
}

// The console header folds from the keyboard and says whether it is open;
// Clear stays a sibling, never nested inside the toggle.
function test_page_designer_console_header_toggles_from_keyboard()
{
	$source = keyboard_skipped_entries_source('assets/js/page_designer.js');

	pg_assert_false(strpos($source, 'onclick="pdConsoleToggle()" style="cursor:pointer') !== false, 'console header toggled by a div click');
	pg_assert_true((bool) preg_match('/<button type="button" id="pd_console_toggle"[^>]*aria-expanded="false" aria-controls="pd_console_body" onclick="pdConsoleToggle\(\)">(?:(?!<\/button>).)*pd_console_badge(?:(?!<\/button>).)*<\/button>\s*<button type="button"[^>]*pdConsoleClear\(\)/s', $source), 'toggle button then Clear button');
	pg_assert_true(strpos($source, "toggle.setAttribute('aria-expanded', open ? 'true' : 'false')") !== false, 'aria-expanded follows the state');
}

// The print view links the product as the order view does (#2960).
function test_print_order_product_links_are_anchors()
{
	$source = keyboard_skipped_entries_source('print_order.php');

	pg_assert_false((bool) preg_match('/<(span|img)\b[^>]*\bonclick=/', $source), 'onclick on a span or img');
	pg_assert_false(strpos($source, "document.location.href = \\'edit_product.php") !== false, 'navigation in a script');
	pg_assert_true(substr_count($source, '<a href="edit_product.php?id=\' . (int) $product_id . \'" class="pg-link-body-emphasis text-decoration-none">') === 2, 'product name linked in both item blocks');
	pg_assert_true(strpos($source, '<a href="edit_product.php?id=\' . (int) $product_id . \'" class="d-block pg-print-none" style="top:52px;position:sticky;" tabindex="-1" aria-hidden="true"><img class="rounded-9 card-img" src="\' .  PATH . h($image_name) . \'" alt=""/></a>') !== false, 'product image inside a silent link');
}
