<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for content kept from visitors outside its folder's access: the
 * folders whose files every visitor opens (includes/fn/files.php), read by
 * the External API's files list and the Workspace canvas pictures, and the
 * Workspace link card of the site's own page (includes/workspace/link_preview.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/link_preview.php');

// Root 1; 2 public, 3 inherits from it; 4 private, 5 inherits from it; bin 9
// with a public folder 10 and an inheriting folder 11 moved into it.
function private_content_access_rows()
{
	$rows = array();
	foreach (array(
		array(1, 0, ''),
		array(2, 1, 'public'),
		array(3, 2, ''),
		array(4, 1, 'private'),
		array(5, 4, ''),
		array(6, 4, 'public'),
		array(9, 1, 'private'),
		array(10, 9, 'public'),
		array(11, 10, ''),
	) as $r) {
		$rows[$r[0]] = array('folder_id' => $r[0], 'folder_parent' => $r[1], 'folder_access_control_type' => $r[2]);
	}
	return $rows;
}

// A folder that inherits public access is public; a public folder in the
// Recycle Bin is not, nor is anything that inherits a private type.
function test_folder_public_ids_follow_the_inherited_type_and_the_bin()
{
	pg_assert_same(array(1, 2, 3, 6), pg_folder_public_ids(private_content_access_rows(), 9), 'public by own or inherited type, outside the bin');
	pg_assert_same(array(1, 2, 3, 6, 10, 11), pg_folder_public_ids(private_content_access_rows(), 0), 'no bin');
	pg_assert_same(array(), pg_folder_public_ids(array(), 0), 'no folders');
}

// The condition names the folders as integers and keeps attachments out; no
// public folder matches no file.
function test_files_public_sql()
{
	pg_assert_same("(files.folder IN (1, 3)) AND (files.attachment = '0')", pg_files_public_sql_for(array('1', 3)), 'listed folders');
	pg_assert_same("(1 = 0) AND (files.attachment = '0')", pg_files_public_sql_for(array()), 'none');
}

// The API's list and read, and the canvas pictures, go through the shared
// condition instead of the folder's own column.
function test_public_file_readers_use_the_resolved_folders()
{
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/files.php');
	$canvas = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split_parts.php');

	pg_assert_contains('pg_files_public_sql()', $api, 'API files');
	pg_assert_contains('pg_files_public_sql()', $canvas, 'canvas pictures');
	pg_assert_false(strpos($canvas, "folder.folder_access_control_type = 'public'") !== false, 'canvas no longer reads the own column');
}

// The card of the site's own page is shared with every reader of the
// channel, guests included: only a page every visitor opens gets one.
function test_link_preview_own_card_only_for_open_pages()
{
	$row = array('page_name' => 'members', 'page_title' => 'Members area', 'page_meta_description' => 'Board minutes', 'folder_archived' => '0');

	pg_assert_same(array('title' => 'Members area', 'description' => 'Board minutes', 'site_name' => 'Site'), ws_link_preview_own_card($row, 'public', 'Site'), 'public page');
	pg_assert_same('members', ws_link_preview_own_card(array('page_title' => '') + $row, 'public', 'Site')['title'], 'name when no title');

	foreach (array('private', 'membership', 'registration', 'guest') as $access) {
		pg_assert_same(null, ws_link_preview_own_card($row, $access, 'Site'), $access . ' page');
	}

	pg_assert_same(null, ws_link_preview_own_card(array('folder_archived' => '1') + $row, 'public', 'Site'), 'archived folder');
	pg_assert_same(null, ws_link_preview_own_card(null, 'public', 'Site'), 'no page');
}
