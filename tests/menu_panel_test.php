<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panels of the main menu (includes/fn/output.php): which rows
 * open their entries inside the menu (pg_menu_row_opens_panel()), the panel
 * they open (pg_menu_panel()) and the Site Settings row's two levels
 * (pg_menu_settings_markup()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_menu_row_without_entries_is_a_link()
{
	pg_assert_false(pg_menu_row_opens_panel('', 'view_pages.php'), 'no entries');
	pg_assert_false(pg_menu_row_opens_panel("  \n ", 'view_pages.php'), 'only white space');
	pg_assert_false(pg_menu_row_opens_panel('<hr class=\'divider my-2\' />', 'view_pages.php'), 'a divider and no link');
}

function test_menu_row_with_several_entries_opens_a_panel()
{
	$entries = '<a href=\'/pinegrap/view_pages.php\' class=\'btn\'>Pages</a>'
		. '<a href=\'/pinegrap/add_page.php\' class=\'btn\'>Create Page</a>';

	pg_assert_true(pg_menu_row_opens_panel($entries, 'view_pages.php'), 'two links');
}

function test_menu_row_whose_one_entry_is_its_own_screen_is_a_link()
{
	pg_assert_false(pg_menu_row_opens_panel('<a href=\'/pinegrap/view_submitted_forms.php\' class=\'btn\'>Forms</a>', 'view_submitted_forms.php'), 'the same screen under the panel folder');
	pg_assert_false(pg_menu_row_opens_panel('<a href=\'view_submitted_forms.php\'>Forms</a>', 'view_submitted_forms.php'), 'the same relative address');
	pg_assert_false(pg_menu_row_opens_panel('<a href="/pinegrap/view_folders.php?view=files&amp;upload=1">Upload</a>', 'view_folders.php?view=files&upload=1'), 'an encoded query string');
	pg_assert_false(pg_menu_row_opens_panel('<a href=\'/pinegrap/view_ads.php\' target=\'_parent\' class=\'btn\'>Ads</a><hr class=\'divider my-2\' />', 'view_ads.php'), 'a divider does not count');
}

function test_menu_row_whose_one_entry_goes_elsewhere_opens_a_panel()
{
	pg_assert_true(pg_menu_row_opens_panel('<a href=\'/pinegrap/view_log.php\'>Site Log</a>', 'welcome.php'), 'another screen');
	pg_assert_true(pg_menu_row_opens_panel('<a href=\'/pinegrap/xview_pages.php\'>Pages</a>', 'view_pages.php'), 'a name that only ends the same');
	pg_assert_true(pg_menu_row_opens_panel('<a onclick=\'open_search_index_window();\' class=\'btn\'>Update Search Index</a>', 'view_pages.php'), 'a link with no address');
	pg_assert_true(pg_menu_row_opens_panel('<a href=\'/pinegrap/view_pages.php\'>Pages</a>', ''), 'a row with no address of its own');
}

function test_menu_row_with_a_control_opens_a_panel()
{
	$entries = '<a href=\'/pinegrap/welcome.php\'>Dashboard</a>'
		. '<form action=\'/pinegrap/welcome.php\' method=\'get\'><input type=\'number\' name=\'refresh\'></form>';

	pg_assert_true(pg_menu_row_opens_panel($entries, 'welcome.php'), 'a form');
	pg_assert_true(pg_menu_row_opens_panel('<a href=\'/pinegrap/welcome.php\'>Dashboard</a><select id=\'widget_theme\'></select>', 'welcome.php'), 'a select');
}

function test_menu_panel_carries_its_entries_as_they_are()
{
	$entries = '<a href=\'/pinegrap/view_pages.php\' class=\'btn btn-link bi bi-window bi-me-2\'>Pages</a><hr class=\'divider my-2\' />';
	$html = pg_menu_panel('menu_panel_2', 'menu_item_2', 'Pages & <b>Posts</b>', $entries);

	pg_assert_contains('id="menu_panel_2"', $html, 'the panel id the row points at');
	pg_assert_contains('data-pg-menu-row="menu_item_2"', $html, 'the row it belongs to');
	pg_assert_contains('aria-labelledby="menu_panel_2_title"', $html, 'named by its title');
	pg_assert_contains('id="menu_panel_2_title"', $html, 'the title id');
	pg_assert_contains('Pages &amp; &lt;b&gt;Posts&lt;/b&gt;', $html, 'the title is escaped');
	pg_assert_contains('<div class="pg-menu-panel-body">' . $entries . '</div>', $html, 'the entries unchanged');
	pg_assert_contains('data-pg-menu-back', $html, 'the way back');
	pg_assert_contains('aria-label="' . h(lang('Back')) . '"', $html, 'the way back is named');
	pg_assert_contains('no-popover', $html, 'the way back keeps a plain title hint');
}

function test_menu_settings_markup_opens_categories_and_groups()
{
	$categories = array(
		'general' => array('label' => 'General', 'icon' => 'bi-sliders', 'url' => 'settings_general.php', 'sections' => array('pgset-server' => 'Server & Domain', 'pgset-cron' => 'Cron Jobs')),
		'appearance' => array('label' => 'Appearance', 'icon' => 'bi-palette', 'url' => 'settings_appearance.php', 'sections' => array('pgset-theme' => 'Theme')));
	$groups = array(
		array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
			array('label' => 'Site Log', 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
			array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'))),
		array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array()),
		array('label' => 'Jobs', 'icon' => 'bi-tools', 'items' => array(
			array('label' => 'Purge Cache', 'icon' => 'bi-trash3', 'url' => 'purge_cache.php?token=a&b', 'confirm' => 'Sure?'))));

	$menu = pg_menu_settings_markup($categories, $groups, '/pinegrap/', '');

	pg_assert_same('settings_general.php', $menu['href'], 'the row leads to the first category');

	// First level: every category and every group with screens, each opening its own panel.
	pg_assert_contains('id=\'menu_entry_20_general\' href=\'/pinegrap/settings_general.php\'', $menu['entries'], 'a category entry');
	pg_assert_contains('data-pg-menu-panel=\'menu_panel_20_appearance\'', $menu['entries'], 'opens its panel');
	pg_assert_contains('data-pg-menu-panel=\'menu_panel_20_tools_0\'', $menu['entries'], 'a group opens its panel');
	pg_assert_contains('data-pg-menu-panel=\'menu_panel_20_tools_2\'', $menu['entries'], 'groups keep their numbers');
	pg_assert_false(strpos($menu['entries'] . $menu['panels'], 'Empty') !== false, 'a group without screens is left out');
	pg_assert_same(1, substr_count($menu['entries'], '<hr'), 'one divider between categories and groups');
	pg_assert_contains('href=\'/pinegrap/view_log.php\'', $menu['entries'], 'a group entry leads to its first screen');

	// Second level.
	pg_assert_contains('id="menu_panel_20_general"', $menu['panels'], 'the category panel');
	pg_assert_contains('data-pg-menu-row="menu_entry_20_general"', $menu['panels'], 'belongs to its entry');
	pg_assert_contains('href=\'/pinegrap/settings_general.php#pgset-cron\'', $menu['panels'], 'a section link');
	pg_assert_contains('Server &amp; Domain', $menu['panels'], 'section names are escaped');
	pg_assert_contains('target=\'_blank\' rel=\'noopener\'', $menu['panels'], 'a screen that opens apart keeps its target');
	pg_assert_contains('data-confirm-content=\'Sure?\'', $menu['panels'], 'a job keeps its question');
	pg_assert_contains('purge_cache.php?token=a&amp;b', $menu['panels'], 'addresses are escaped');

	// Every link to a settings page skips the modal; the neighbouring screens do not need to.
	pg_assert_same(2 + 3 + 2, substr_count($menu['entries'] . $menu['panels'], 'data-pg-settings-page'), 'category entries, category pages and sections');
}

function test_menu_settings_markup_in_the_toolbar()
{
	$categories = array('general' => array('label' => 'General', 'icon' => 'bi-sliders', 'url' => 'settings_general.php', 'sections' => array('pgset-server' => 'Server')));
	$groups = array(array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
		array('label' => 'Site Log', 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
		array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'))));

	$menu = pg_menu_settings_markup($categories, $groups, '/pinegrap/', ' target=\'_parent\'');
	$all = $menu['entries'] . $menu['panels'];

	pg_assert_same(5, substr_count($all, 'target=\'_parent\''), 'every link but the one with its own target leaves the toolbar frame');
	pg_assert_same(1, substr_count($all, 'target=\'_blank\''), 'its own target wins');
}
