<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the keyboard path of the panel's drag lists: the dashboard
 * cards, the product photos (whose first tile is the cover) and the menu
 * item tree each carry a grip that is a focusable button, and the arrow keys
 * on it move the item, save it the way a drag does and say the new position.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function keyboard_reorder_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The card header is the drag handle and cannot take focus; the grip button
// sits in it beside the card's own action buttons.
function test_keyboard_reorder_dashboard_cards()
{
	$source = keyboard_reorder_source('welcome.php');

	pg_assert_contains('<button type="button" class="pg-widget-move', $source);
	pg_assert_contains("lang(array('string' => 'Move {var:1}'", $source);
	pg_assert_contains('$(document).on("keydown", ".pg-widget-move"', $source);
	pg_assert_true(
		(bool) preg_match('/\.pg-widget-move".*?pg_merge_widget_order\(.*?pg_save_widget_order\(.*?pgAnnounce\(/s', $source),
		'arrow keys save through the drag path and announce');
}

// The cover is the first tile; without a focusable grip only a mouse could
// change it.
function test_keyboard_reorder_product_photos()
{
	$js = keyboard_reorder_source('assets/js/product_builder.js');

	pg_assert_contains('pg-pb-move', $js);
	pg_assert_true(
		(bool) preg_match('/function refreshCoverBadge\(\).*?pg-pb-move/s', $js),
		'the grip is added from refreshCoverBadge(), which sees tiles the picker appends');
	pg_assert_true(
		(bool) preg_match("/on\('keydown', '\.pg-pb-move'.*?pgAnnounce\(/s", $js),
		'arrow keys move the tile and announce');

	// Both label lists the script can be handed carry the new texts.
	$php = keyboard_reorder_source('product_builder.php');
	pg_assert_true(substr_count($php, "'move_photo'") >= 2, 'move_photo label on both screens');
	pg_assert_true(substr_count($php, "'reorder_position'") >= 2, 'reorder_position label on both screens');
}

// The tree's grips were <i> elements; the panel said "Drag" and nothing else.
function test_keyboard_reorder_menu_tree()
{
	$source = keyboard_reorder_source('view_menu_items.php');

	pg_assert_false(strpos($source, '<i class="bi bi-grip-vertical me-3 fg-secondary sort-drag-handle"') !== false, 'icon grip');
	pg_assert_contains('<button type="button" class="btn btn-link p-0 me-3 fg-secondary sort-drag-handle"', $source);
	pg_assert_contains("on(\\'keydown\\', \\'.sort-drag-handle\\'", $source);
	pg_assert_true(
		(bool) preg_match("/keydown.*?updateIndentation\(.*?pgAnnounce\(/s", $source),
		'a level change re-indents and the position is announced');
}
