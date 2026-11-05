<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a classic photo gallery page (get_photo_gallery.php) lists a
 * membership album by the edit rights to that album. The album list asked
 * for edit rights to the folder shown instead, so a user with edit rights to
 * a membership album only (and no membership) did not see the album on the
 * gallery, although opening it with ?folder_id= showed it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_photo_gallery_classic_album_membership_reads_album_edit_rights()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_photo_gallery.php');

	pg_assert_same(0, substr_count($source, '} elseif (check_edit_access($folder_id) == false) {'), 'album list asks the folder shown');
	pg_assert_same(2, substr_count($source, '} elseif (check_edit_access($child_folder[\'id\']) == false) {'), 'both layouts ask the album');
	// The album that is opened still asks for edit rights to itself.
	pg_assert_same(2, substr_count($source, 'if (check_edit_access($folder_id) == false) {'), 'opened album asks itself');
}
