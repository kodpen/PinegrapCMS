<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recycle Bin on a classic photo gallery page
 * (get_photo_gallery.php). Staff skip the folder access check, so a gallery
 * page in the site's root folder listed the Recycle Bin as an album and opened
 * it (?folder_id=<bin>), showing deleted images; the Photo Gallery widget
 * never opens the bin. The scope of the gallery now leaves the bin and every
 * folder below it out (pg_child_folders_outside_bin()), and the album list
 * leaves the bin out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Root 1 holds albums 2 and 9 (the bin); 3 is under 2, 10 under the bin.
function photo_gallery_classic_bin_folders()
{
	return array(
		array('id' => '1', 'parent_folder_id' => '0'),
		array('id' => '2', 'parent_folder_id' => '1'),
		array('id' => '3', 'parent_folder_id' => '2'),
		array('id' => '9', 'parent_folder_id' => '1'),
		array('id' => '10', 'parent_folder_id' => '9'),
	);
}

function test_photo_gallery_classic_scope_leaves_bin_out()
{
	pg_assert_same(array('2', '3'), pg_child_folders_outside_bin(1, photo_gallery_classic_bin_folders(), 9), 'bin and its folder left out');
	pg_assert_same(array('2', '3', '9', '10'), pg_child_folders_outside_bin(1, photo_gallery_classic_bin_folders(), 0), 'no bin: every folder below');
	pg_assert_same(array('3'), pg_child_folders_outside_bin(2, photo_gallery_classic_bin_folders(), 9), 'a gallery below the root');
	pg_assert_same(get_child_folders(1, photo_gallery_classic_bin_folders()), pg_child_folders_outside_bin(1, photo_gallery_classic_bin_folders(), 0), 'same as get_child_folders() without a bin');
}

function test_photo_gallery_classic_uses_bin_aware_scope()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_photo_gallery.php');

	pg_assert_same(0, substr_count($source, 'get_child_folders($folder_id, $folders)'), 'scope still reaches the bin');
	pg_assert_same(2, substr_count($source, 'pg_child_folders_outside_bin($folder_id, $folders, $gallery_bin_id)'), 'both layouts check the scope without the bin');
	pg_assert_same(2, substr_count($source, "if ((int) \$row['id'] !== \$gallery_bin_id) {"), 'both layouts leave the bin out of the albums');
}
