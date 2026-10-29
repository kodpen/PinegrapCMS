<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for keyboard access on the order view's product links and the
 * rich text editor's link picker: the product name and image are real links,
 * every picker row holds a button, and the type buttons' icon words stay
 * silent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_view_picker_links_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A click handler on a span or an image is out of the keyboard's reach.
function test_order_view_product_links_are_anchors()
{
	$source = order_view_picker_links_source('view_order.php');

	pg_assert_false((bool) preg_match('/<(span|img)\b[^>]*\bonclick=/', $source), 'onclick on a span or img');
	pg_assert_false(strpos($source, "document.location.href = \\'edit_product.php") !== false, 'navigation in a script');
	pg_assert_true(substr_count($source, '<a href="edit_product.php?id=\' . (int) $product_id . \'"') >= 4, 'product name and image linked in both item blocks');
	pg_assert_false((bool) preg_match('/<img(?![^>]*\balt=)[^>]*card-img/', $source), 'product image without alt');
}

// Every picker row holds a button that inserts the link, so a link can be
// chosen from the keyboard; the button stops the click reaching the row.
function test_editor_link_picker_rows_hold_buttons()
{
	$source = order_view_picker_links_source('editor_select_page_or_file.php');

	pg_assert_true(substr_count($source, '<button type="button" class="btn btn-link p-0 text-start"') >= 3, 'button in each row kind');
	pg_assert_true(substr_count($source, 'event.stopPropagation();') >= 3, 'button click kept off the row');
}

// The type buttons are named by their words, not by the ligature.
function test_editor_link_picker_type_icons_are_silent()
{
	$source = order_view_picker_links_source('editor_select_page_or_file.php');

	foreach (array('desktop_windows', 'insert_drive_file', 'link') as $ligature) {
		pg_assert_contains('<span class="material-icons me-1" aria-hidden="true">' . $ligature . '</span>', $source);
	}
}
