<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the File and Catalog Manager folder tree on the explorer's
 * own screen (moved into the main menu's panel): rows are focusable links
 * that say which one is current, branch arrows are toggles that say whether
 * they are open, Enter and Space press them, and the Grid/List pair is named.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_tree_keyboard_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_folders.php');
}

function test_explorer_tree_keyboard_static_rows_are_focusable_links()
{
	$source = explorer_tree_keyboard_source();
	foreach (array('all_files_quick', 'all_images_quick', 'all_pages_quick', 'short_links_quick', 'all_products_quick', 'all_variant_sets_quick', 'shared_quick', 'backups_quick') as $id) {
		pg_assert_true(preg_match('/<div class="tree-row[^"]*" id="' . $id . '" role="link" tabindex="0">/', $source) === 1, $id . ' is a focusable link');
	}
	pg_assert_true(strpos($source, '<div class="tree-row" id="erp_quick" role="link" tabindex="0" aria-label="<?php echo lang(\'ERP Files\'); ?>"><span class="tree-toggle bi bi-chevron-right" data-role="erp-toggle" role="button" tabindex="0" aria-expanded="false" aria-label="<?php echo lang(\'Expand\'); ?>"></span>') !== false, 'the ERP Files arrow is a named toggle with aria-expanded');
}

function test_explorer_tree_keyboard_dynamic_rows_are_focusable_links()
{
	$source = explorer_tree_keyboard_source();
	pg_assert_true(strpos($source, "(state.mode !== 'all' && node.id === state.folderId) ? ' active\" aria-current=\"page' : '') + '\" role=\"link\" tabindex=\"0\" data-folder-id=\"' + node.id + '\" aria-label=\"' + esc(node.name) + '\"") !== false, 'folder rows are focusable links named by the folder');
	pg_assert_true(strpos($source, "(node.enabled ? '' : ' catalog-off') + ((node.id === state.groupId) ? ' active\" aria-current=\"page' : '') + '\" role=\"link\" tabindex=\"0\" data-folder-id=\"' + node.id + '\" aria-label=\"' + esc(node.name) + '\"") !== false, 'catalog group rows are focusable links named by the group');
	pg_assert_true(strpos($source, "' active\" aria-current=\"page' : '') +\n                    '\" role=\"link\" tabindex=\"0\" data-shortcut-id=\"' + id + '\"") !== false, 'Pinned and Recent rows are focusable links');
	pg_assert_true(strpos($source, "' is-empty') + '\" role=\"link\" tabindex=\"0\" data-erp-folder=") !== false, 'ERP folder rows are focusable links');
	pg_assert_true(substr_count($source, "treeToggleMarkup(node.has_children, expanded)") === 2, 'folder and group rows draw their arrow through treeToggleMarkup()');
}

function test_explorer_tree_keyboard_toggle_state_kept_in_step()
{
	$source = explorer_tree_keyboard_source();
	pg_assert_true(strpos($source, "'expand' => lang('Expand'),") !== false && strpos($source, "'collapse' => lang('Collapse'),") !== false, 'the screen L map carries Expand and Collapse');
	pg_assert_true(strpos($source, "toggle.setAttribute('aria-expanded', open ? 'true' : 'false');") !== false, 'syncTreeToggle() sets aria-expanded');
	pg_assert_true(strpos($source, "toggle.setAttribute('aria-label', open ? L.collapse : L.expand);") !== false, 'syncTreeToggle() names the arrow');
	pg_assert_true(strpos($source, "syncTreeToggle(root.querySelector('.tree-toggle'), open);") !== false, 'setErpTreeOpen() keeps the ERP arrow in step');
	pg_assert_true(substr_count($source, "syncTreeToggle(node.row.querySelector('.tree-toggle'), true);") === 2, 'both create-in-tree paths open their branch through syncTreeToggle()');
	pg_assert_true(strpos($source, "syncTreeToggle(toggle, true);") !== false && strpos($source, "syncTreeToggle(toggle, false);") !== false, 'the tree click handler opens and closes through syncTreeToggle()');
	pg_assert_true(strpos($source, "toggle.classList.add('open');") === false && strpos($source, "toggle.classList.remove('open');") === false, 'no arrow flips .open alone');
}

function test_explorer_tree_keyboard_current_row_and_keys()
{
	$source = explorer_tree_keyboard_source();
	pg_assert_true(strpos($source, "function markTreeRow(row, on)") !== false, 'one helper moves .active and aria-current');
	pg_assert_true(strpos($source, "classList.toggle('active', treeLive &&") === false, 'highlightTree() does not move .active alone');
	pg_assert_true(strpos($source, "markTreeRow(root, insideErp() && (state.erpFolder === ''));") !== false, 'the ERP root row says when it is current');
	pg_assert_true(strpos($source, "if ((event.key !== 'Enter') && (event.key !== ' ')) { return; }") !== false, 'the tree takes Enter and Space');
	pg_assert_true(strpos($source, "var target = event.target.closest('.tree-row[tabindex], .tree-toggle[role=\"button\"]');") !== false, 'Enter and Space press the focused row or arrow');
	pg_assert_true(strpos($source, '#explorer_tree_pane .tree-row:focus-visible, #explorer_tree_pane .tree-toggle:focus-visible') !== false, 'rows and arrows show focus');
}

function test_explorer_tree_keyboard_view_group_named()
{
	$source = explorer_tree_keyboard_source();
	pg_assert_true(strpos($source, '<div class="btn-group btn-group-sm" id="explorer_view_group" role="group" aria-label="<?php echo lang(\'View\'); ?>">') !== false, 'the Grid/List group is named');
}
