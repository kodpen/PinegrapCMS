<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a classic photo gallery page (get_photo_gallery.php) with a
 * Thumbnail Max Size of 0 shows each picture at full size on a mobile
 * device too: the cells there were sized for a 0-pixel thumbnail (25 px
 * photos, 10 px wide albums) while the picture showed at full size.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_photo_gallery_classic_full_size_mobile_cells()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_photo_gallery.php');

	pg_assert_same(0, substr_count($source, "if (\$device_type == 'mobile') {\n"), 'a mobile cell is sized whatever the thumbnail size');
	pg_assert_same(2, substr_count($source, "if ((\$device_type == 'mobile') && (intval(\$thumbnail_max_size) > 0)) {"), 'album and photo cells are sized only for a thumbnail size');
	pg_assert_same(2, substr_count($source, 'get_thumbnail_dimensions($image_width, $image_height, $thumbnail_max_size)'), 'the pictures still use the stored size');
}
