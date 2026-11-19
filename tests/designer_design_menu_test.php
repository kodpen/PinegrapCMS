<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The design's menu on the page strip: Settings, and Delete under a divider
 * only where the caller hands the delete item over; the strip itself keeps
 * no gear and no bin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_design_menu_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_designer_design_menu_delete_brings_its_own_divider()
{
	$screen = designer_design_menu_source('includes/designer_screen.php');
	pg_assert_true((bool) preg_match('/<div class="pg-menu-wrap sd-design-menu">(.*?)<\/ul>/s', $screen, $m), 'the design menu is on the strip');
	$menu = $m[1];
	pg_assert_true(strpos($menu, 'data-bs-target="#styleSettingsModal"') !== false, 'Settings opens the settings dialog');
	pg_assert_true(strpos($menu, "\$ctx['delete_button']") !== false, 'Delete is the item the caller hands over');
	pg_assert_true(strpos($menu, 'menu-divider') === false, 'no divider of its own: without Delete the menu holds Settings alone');

	pg_assert_true((bool) preg_match('/<div class="sd-toolbar-group sd-toolbar-pages".*?<div class="sd-tabs-scroll"/s', $screen, $g), 'the strip group is found');
	$strip = str_replace($m[0], '', $g[0]);
	pg_assert_true(strpos($strip, 'bi-gear') === false && strpos($strip, "\$ctx['delete_button']") === false, 'no gear and no bin left on the strip');

	$edit = designer_design_menu_source('edit_system_style.php');
	pg_assert_true((bool) preg_match('/\$delete_button = \'<li[^>]*>(.*?)<\/li>\';/s', $edit, $d), 'the delete item is one list item');
	pg_assert_true(strpos($d[1], '<hr class="menu-divider">') === 0, 'its divider comes first, inside the same item');
	pg_assert_true(strpos($d[1], 'id="sd-delete-style"') !== false, 'the delete handler keeps its id');
}
