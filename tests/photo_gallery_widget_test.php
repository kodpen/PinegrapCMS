<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photo Gallery system widget
 * (includes/fn/widgets_photo_gallery.php): which folder it shows, its
 * album and photo rows, its flags and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_photo_gallery_widget_folder_shown()
{
	// 1 the page's folder; 2 below it, 3 below 2; 4 beside it; 5 and 6 a loop.
	$parents = array(1 => 0, 2 => 1, 3 => 2, 4 => 0, 5 => 6, 6 => 5);
	$parent_of = function ($id) use ($parents) {
		return isset($parents[$id]) ? $parents[$id] : 0;
	};
	pg_assert_same(1, pg_gal_folder_shown(1, null, $parent_of), 'no album asked for');
	pg_assert_same(2, pg_gal_folder_shown(1, '2', $parent_of), 'an album of the gallery');
	pg_assert_same(3, pg_gal_folder_shown(1, '3', $parent_of), 'an album deeper down');
	pg_assert_same(1, pg_gal_folder_shown(1, '4', $parent_of), 'a folder outside the gallery is never opened');
	pg_assert_same(1, pg_gal_folder_shown(1, '5', $parent_of), 'a loop in the parents ends the walk');
	pg_assert_same(1, pg_gal_folder_shown(1, '2 OR 1', $parent_of), 'only a number');
	pg_assert_same(1, pg_gal_folder_shown(1, array('2'), $parent_of), 'not an array');
}

function test_photo_gallery_widget_rows()
{
	$rows = pg_gal_rows(
		array(
			array('id' => 7, 'name' => 'Summer', 'photo' => 'beach day.jpg', 'photos' => 3),
			array('id' => 8, 'name' => 'Empty', 'photo' => '', 'photos' => 0),
			array('id' => 9, 'name' => '', 'photo' => '', 'photos' => 0),
		),
		array(array('id' => 20, 'name' => 'cover.png', 'description' => 'Our team')),
		'/gallery', '/');
	pg_assert_same(3, count($rows), 'albums with a name, then the photos');
	pg_assert_same('/gallery?folder_id=7', $rows[0]['__gallery_item_url'], 'an album opens on the page');
	pg_assert_same('/beach%20day.jpg', $rows[0]['__gallery_item_image_url'], 'its first photo');
	pg_assert_same('3', $rows[0]['__gallery_item_photo_count'], 'its number of photos');
	pg_assert_same('1', $rows[0]['__gallery_item_is_album'], 'an album');
	pg_assert_same('', $rows[1]['__gallery_item_image_url'], 'an empty album has no picture');
	pg_assert_same('/cover.png', $rows[2]['__gallery_item_url'], 'a photo opens the image');
	pg_assert_same('Our team', $rows[2]['__gallery_item_description'], 'its description');
	pg_assert_same('1', $rows[2]['__gallery_item_is_photo'], 'a photo');
	pg_assert_same('', $rows[2]['__gallery_item_count_label'], 'a photo has no count');
}

function test_photo_gallery_widget_flags()
{
	$album = array('__gallery_item_is_album' => '1');
	$photo = array('__gallery_item_is_album' => '');
	pg_assert_same(array('has_gallery_items' => true, 'no_gallery_items' => false, 'has_albums' => true, 'has_photos' => true, 'in_album' => false),
		pg_gal_flags(array($album, $photo), false), 'albums and photos');
	pg_assert_same(array('has_gallery_items' => false, 'no_gallery_items' => true, 'has_albums' => false, 'has_photos' => false, 'in_album' => true),
		pg_gal_flags(array(), true), 'an empty album');
}

function test_photo_gallery_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['photo_gallery']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('photo_gallery', $map['photo gallery'], 'stands in for the classic type');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_photo_gallery(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'photo_gallery'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'photo_gallery': {", $js, 'has a starter tree');
	pg_assert_contains("photo_gallery:     SW_PHOTO_GALLERY_TOKEN_GROUPS", $js, 'has a token palette');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_photo_gallery.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'photo_gallery')", $source, 'Messages named after the classic liveform');
}
