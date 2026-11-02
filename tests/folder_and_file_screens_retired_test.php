<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the folder screens (add_folder.php, edit_folder.php and their
 * shared form) and the classic files list (view_files.php) are gone and that
 * the File Manager does their work: nothing links to them, Cleanup deletes
 * them on an updated site, the File Manager's menu panel holds only its
 * tree, the root folder is renamed and given the site's default page styles
 * in the explorer, and the folder duplicate with find and replace opens from
 * the explorer and comes back to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/get_help_url.php');
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_folders.php');

function folder_and_file_screens_retired_files()
{
	return array('add_folder.php', 'edit_folder.php', 'includes/forms/folder_form.php', 'view_files.php');
}

function folder_and_file_screens_retired_source($path)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
}

function test_folder_and_file_screens_retired_are_gone_and_cleaned_up()
{
	$clean_up = folder_and_file_screens_retired_source('clean_up.php');

	foreach (folder_and_file_screens_retired_files() as $path) {
		pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/' . $path), $path . ' is not shipped');
		pg_assert_contains("    '" . $path . "',\n", $clean_up, 'Cleanup deletes ' . $path . ' on an updated site');
	}
}

// Every PHP and JavaScript file of the product, the minified bundles and the
// migrations (history) left out: none names a retired screen.
function test_folder_and_file_screens_retired_nothing_links_to_them()
{
	$root = PG_FUNCTIONS_DIR;
	$names = array('add_folder.php', 'edit_folder.php', 'folder_form.php', 'view_files.php');
	$found = array();

	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		if (!preg_match('/\.(php|js)$/', $path) || preg_match('/\.min\.js$/', $path)) {
			continue;
		}

		if (($path === 'clean_up.php') || (strpos($path, 'includes/migrations/') === 0) || (strpos($path, 'assets/lib/') === 0) || (strpos($path, 'includes/dompdf/') === 0)) {
			continue;
		}

		$source = (string) file_get_contents($file->getPathname());

		foreach ($names as $name) {
			if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($name, '/') . '/', $source)) {
				$found[] = $path . ' names ' . $name;
			}
		}
	}

	pg_assert_same(array(), $found, 'no file names a retired screen');
}

function test_folder_and_file_screens_retired_menu_panel_is_the_tree()
{
	$output = folder_and_file_screens_retired_source('includes/fn/output.php');
	$start = strpos($output, "\$menu_items[1]['id'] = 1;");
	$row = substr($output, $start, strpos($output, '// PAGES', $start) - $start);

	pg_assert_contains("\$menu_items[1]['panel'] = true;", $row, 'the row asks for its panel by the flag');
	pg_assert_contains("\$menu_items[1]['data-bs-content'] = pg_menu_explorer_side('files', \$user, \$toolbar, \$menu_tail);", $row, 'the panel holds the tree alone');
	pg_assert_false(strpos($row, "lang('Upload Files or Folders')") !== false, 'no upload link');
	pg_assert_false(strpos($row, "lang('Create File')") !== false, 'no create file link');
	pg_assert_false(strpos($row, "lang('Folder')") !== false, 'no create folder link');
	pg_assert_false(strpos($row, 'data-pg-menu-skip') !== false, 'no File Manager link above the tree');

	pg_assert_same('https://pinegrap.com/introduction', get_help_url(array('screen' => 'add_folder')), 'no help row for the create screen');
	pg_assert_same('https://pinegrap.com/introduction', get_help_url(array('screen' => 'edit_folder')), 'no help row for the edit screen');
	pg_assert_same('https://pinegrap.com/introduction', get_help_url(array('screen' => 'view_files')), 'no help row for the files list');
	pg_assert_same('https://pinegrap.com/folders', get_help_url(array('screen' => 'view_folders')), 'the File Manager keeps its row');
}

// The ways in that named the screens now open the File Manager.
function test_folder_and_file_screens_retired_ways_in_open_the_file_manager()
{
	pg_assert_contains("\$href = 'view_folders.php?folder=' . \$id;", folder_and_file_screens_retired_source('includes/dashboard/widgets/widget_7.php'), 'a recent folder opens in the File Manager');
	pg_assert_contains("'url' => 'view_folders.php?view=files'", folder_and_file_screens_retired_source('includes/dashboard/v2/figures.php'), 'the files figure opens the Files view');
	pg_assert_contains('<a href="view_folders.php?view=files"', folder_and_file_screens_retired_source('includes/dashboard/widgets/widget_18.php'), 'the heavy images card opens the Files view');

	$search = folder_and_file_screens_retired_source('includes/panel/search.php');
	pg_assert_contains("'url' => \$base_url . '/view_folders.php?view=files'", $search, 'Files in the panel search opens the Files view');
	pg_assert_false(strpos($search, "lang('Add Folder')") !== false, 'no Add Folder action');

	pg_assert_contains("go(PATH . SOFTWARE_DIRECTORY . '/view_folders.php?view=files');", folder_and_file_screens_retired_source('optimize.php'), 'optimize goes back to the Files view');
	pg_assert_contains("header('Location: view_folders.php?view=files');", folder_and_file_screens_retired_source('create_file.php'), 'a created file lands in the Files view');
	pg_assert_contains("'view_folders.php?view=files'", folder_and_file_screens_retired_source('assets/js/cloud_file_picker.js'), 'the cloud picker lands in the Files view');

	foreach (array('add_file.php', 'create_file.php', 'edit_file.php', 'optimize.php') as $path) {
		pg_assert_contains("new liveform('view_folders');", folder_and_file_screens_retired_source($path), $path . ' leaves its notice for the File Manager');
	}
}

function test_folder_and_file_screens_retired_explorer_edits_folders_itself()
{
	$explorer = folder_and_file_screens_retired_source('view_folders.php');

	pg_assert_false(strpos($explorer, "menuItem('bi-pencil', L.edit, 'edit', multi || !item.can_edit);\n                html += menuItem('bi-sliders', L.folder_settings") !== false, 'no Edit entry for a folder');
	pg_assert_contains("if (item.kind === 'folder') { openFolderSettings(item.id); }", $explorer, 'an edit of a folder opens its settings');
	pg_assert_contains("html += menuItem('bi-input-cursor-text', L.rename, 'tree_rename', !node.canEdit);", $explorer, 'the root folder is renamed in the tree');
	pg_assert_contains("html += menuItem('bi-sliders', L.folder_settings, 'tree_settings', !node.canEdit || (node.isRoot && !(state.caps.role < 3)));", $explorer, 'the root folder has its settings for managers and above');
	pg_assert_contains('var isRoot = !!folder.is_root;', $explorer, 'the drawer knows the root');
	pg_assert_contains("var orderArchiveHtml = isRoot ? '' :", $explorer, 'the root has no order or archive in the drawer');
	pg_assert_false(strpos($explorer, "href=\"view_files.php\"") !== false, 'no classic Files view in the More menu');
}

// The root keeps its stored order and archive, whatever is sent.
function test_folder_and_file_screens_retired_root_settings_keep_order_and_archive()
{
	$root = array('folder_parent' => '0', 'folder_order' => '4', 'folder_archived' => '');
	$child = array('folder_parent' => '1', 'folder_order' => '4', 'folder_archived' => '');

	pg_assert_same(array(4, ''), pg_explorer_folder_settings_order_archive($root, array('order' => '9', 'archived' => '1')), 'the root keeps its own');
	pg_assert_same(array(4, '1'), pg_explorer_folder_settings_order_archive(array('folder_parent' => 0, 'folder_order' => 4, 'folder_archived' => '1'), array()), 'an archived root stays so');
	pg_assert_same(array(9, '1'), pg_explorer_folder_settings_order_archive($child, array('order' => '9', 'archived' => '1')), 'another folder takes the request');
	pg_assert_same(array(0, ''), pg_explorer_folder_settings_order_archive($child, array('archived' => 'yes')), 'only an exact 1 archives');

	$handler = folder_and_file_screens_retired_source('includes/explorer/handle_folders.php');
	pg_assert_contains("'is_root' => ((int) \$folder['folder_parent'] == 0),", $handler, 'the drawer is told the root');
	pg_assert_contains('list($order, $archived) = pg_explorer_folder_settings_order_archive($folder, $request);', $handler, 'the save uses the rule');
}

// The folder duplicate with find and replace (duplicate_folder.php) opens
// from the explorer's folder menus and comes back to the File Manager.
function test_folder_and_file_screens_retired_duplicate_with_find_and_replace()
{
	$explorer = folder_and_file_screens_retired_source('view_folders.php');
	pg_assert_same(2, substr_count($explorer, "'duplicate_folder.php?id=' + "), 'opened from the listing and the tree');
	pg_assert_contains("'duplicate_find_replace' => lang('Duplicate with Find & Replace'),", $explorer, 'its entry is translated');

	$screen = folder_and_file_screens_retired_source('duplicate_folder.php');
	pg_assert_contains("\$back_url = pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?folder=' . (int) \$folder['parent']);", $screen, 'Cancel goes back to the File Manager');
	pg_assert_contains("go(pg_safe_redirect_path(pg_send_to_url(''), PATH . SOFTWARE_DIRECTORY . '/view_folders.php?folder=' . (int) \$new_folder['id']));", $screen, 'the duplicate lands in the File Manager');
	pg_assert_contains("new liveform('view_folders');", $screen, 'its notice is left for the File Manager');

	pg_assert_contains('href="<?=h($back_url)?>"', folder_and_file_screens_retired_source('includes/templates/duplicate_folder.php'), 'the crumb goes back to the File Manager');
}
