<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's "Workspace tasks" tab menu item: the
 * Workspace record drawer of page:<id>, printed by designer_screen.php
 * through ws_record_drawer_assets() and opened from style_designer.js with a
 * data-ws-record click. The drawer skips its fields section for a kind it
 * cannot edit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_page_tasks_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_designer_page_tasks_screen_prints_the_drawer_for_pages()
{
	$screen = designer_page_tasks_test_source('includes/designer_screen.php');

	pg_assert_contains("ws_record_drawer_assets(\$user, 'page')", $screen, 'the screen asks for the page drawer');
	pg_assert_contains("'workspaceTasks' => (\$sd_ws_tasks !== '')", $screen, 'the editor learns whether to offer the item');
	pg_assert_contains("' . \$sd_ws_tasks . '", $screen, 'the drawer loader is printed');

	$workspace = designer_page_tasks_test_source('includes/workspace/screen.php');

	pg_assert_contains('function ws_record_drawer_assets($user, $type)', $workspace, 'the helper exists');
	pg_assert_contains("isset(ws_ref_types(\$viewer)[\$type])", $workspace, 'the helper keeps the button\'s gate');
}

function test_designer_page_tasks_menu_item_opens_the_record_drawer()
{
	$js = designer_page_tasks_test_source('assets/js/style_designer.src.js');

	pg_assert_contains("_design.workspaceTasks) && saved && p.page_id > 0 && !p.pickedPending", $js, 'only saved pages of this design');
	pg_assert_contains("item('ws-tasks', 'bi-clipboard2-check', _sdT('Workspace tasks'))", $js, 'the menu item');
	pg_assert_contains("case 'ws-tasks': _pgTabsOpenWorkspaceTasks(p); break;", $js, 'the menu answers it');
	pg_assert_contains("a.setAttribute('data-ws-record', 'page:' + p.page_id);", $js, 'the drawer is opened on the page');
}

function test_designer_page_tasks_drawer_skips_fields_it_cannot_edit()
{
	$js = designer_page_tasks_test_source('assets/js/workspace.js');

	pg_assert_contains("if (!BOOT || !BOOT.quick_edit || BOOT.quick_edit.indexOf(type) !== -1) {\n            quickEditLoad(", $js, 'no fields section for a page');
}
