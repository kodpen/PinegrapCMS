<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panels of the main menu (includes/fn/output.php): which rows
 * open their entries inside the menu (pg_menu_row_opens_panel()), the panel
 * they open (pg_menu_panel()) and the module that colours its icons
 * (pg_menu_tone()), the System tools row (pg_menu_tools_markup()), and the
 * retired Site Settings row (slot 20).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

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

// A row opens its own panel, another row's once that one is drawn (the pinned
// My Notes row opens the Workspace list), or none.
function test_menu_row_panel_id_own_shared_or_none()
{
	$entries = '<a href=\'/pinegrap/view_pages.php\'>Pages</a><a href=\'/pinegrap/add_page.php\'>Create Page</a>';

	pg_assert_same('menu_panel_2', pg_menu_row_panel_id(array('id' => 2, 'href' => 'view_pages.php'), $entries, array()), 'its own panel');
	pg_assert_same('', pg_menu_row_panel_id(array('id' => 0, 'href' => 'welcome.php'), '', array()), 'a plain link');
	pg_assert_same('menu_panel_23', pg_menu_row_panel_id(array('id' => 24, 'href' => 'workspace_notes.php', 'panel_of' => 23), '', array(23 => true)), 'the drawn panel of the row it names');
	pg_assert_same('', pg_menu_row_panel_id(array('id' => 24, 'href' => 'workspace_notes.php', 'panel_of' => 23), '', array()), 'nothing while that panel is not drawn');
	pg_assert_same('', pg_menu_row_panel_id(array('id' => 24, 'href' => 'workspace_notes.php', 'panel_of' => 23), $entries, array()), 'its own entries do not make it one');
	pg_assert_same('menu_panel_1', pg_menu_row_panel_id(array('id' => 1, 'href' => 'view_folders.php', 'panel' => true), '<div id="explorer-side-files" data-pg-menu-auto></div>', array()), 'a row asking for its panel with a box and no link');
}

// The pinned My Notes row has no panel of its own: the server's row and the
// one workspace.js draws when it is pinned both open the Workspace list.
function test_menu_notes_row_opens_the_workspace_list()
{
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$start = strpos($output, "\$menu_items[24]['id'] = 24;");
	$row = substr($output, $start, strpos($output, '}', $start) - $start);

	pg_assert_contains("\$menu_items[24]['panel_of'] = 23;", $row, 'the row opens the Workspace list');
	pg_assert_false(strpos($row, 'data-bs-content') !== false, 'no entries of its own');
	pg_assert_false(strpos($output, 'menu_panels_unattached') !== false, 'no panel drawn without its row');
	pg_assert_contains("if (\$menu_panel_id === 'menu_panel_' . (int) \$options['id']) {", $output, 'a row opening another row\'s panel draws none');

	$workspace = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_false(strpos($workspace, 'menu_panel_24') !== false, 'the pinned row hooks no panel of its own');
	pg_assert_contains("var panel = document.getElementById('menu_panel_23');", $workspace, 'a row pinned on the notes screen opens the Workspace list');

	$backend = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	pg_assert_contains('openMenuPanel(panel, { animate: true, focus: true, opener: row });', $backend, 'the click names the row it came from');
	pg_assert_contains("var row = document.getElementById(open.getAttribute('data-pg-menu-opener') || open.getAttribute('data-pg-menu-row'));", $backend, 'the way back returns the focus to that row');
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
	pg_assert_false(strpos($html, 'data-pg-menu-tone') !== false, 'no module, plain icons');
}

function test_menu_panel_names_its_module_for_two_colour_icons()
{
	pg_assert_contains('data-pg-menu-row="menu_item_2" data-pg-menu-tone="pages" role="group"', pg_menu_panel('menu_panel_2', 'menu_item_2', 'Pages', '', 'pages'), 'the module');
	pg_assert_false(strpos(pg_menu_panel('menu_panel_2', 'menu_item_2', 'Pages', '', 'pages" onclick="x'), 'data-pg-menu-tone') !== false, 'anything but a plain name is left out');
}

function test_menu_tone_is_the_module_of_a_colour_class()
{
	pg_assert_same('pages', pg_menu_tone('pages-color'), 'a module colour');
	pg_assert_same('settings', pg_menu_tone('settings-color'), 'the System tools row');
	pg_assert_same('', pg_menu_tone(''), 'no class');
	pg_assert_same('', pg_menu_tone('pg-neon-icon'), 'not a module colour');
	pg_assert_same('', pg_menu_tone('pages-color extra'), 'one class only');
}

function test_menu_every_row_colour_has_its_tone_in_the_stylesheet()
{
	// Every colour class a row is given must have its two shades for the
	// panel's icons, or that panel's icons are drawn without colour.
	$menu = pg_test_function_source('output_menu');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	preg_match_all("/\['color_class'\] = '([a-z]+)-color';/", $menu, $match);
	pg_assert_true(count($match[1]) > 10, 'the rows are read');

	foreach (array_unique($match[1]) as $tone) {
		pg_assert_contains('#menu .pg-menu-panel[data-pg-menu-tone="' . $tone . '"] { --pg-menu-tone: var(--' . $tone . '-color); --pg-menu-tone-2: var(--' . $tone . '-color-highlight); }', $css, $tone);
	}
}

function test_menu_site_settings_row_is_retired()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_false(strpos($menu, '$menu_items[20]') !== false, 'slot 20 is not built');
	pg_assert_false(strpos($menu, '$active_menu = 20;') !== false, 'nothing marks it');
	pg_assert_false(function_exists('pg_menu_settings_markup'), 'its panels are gone');
	pg_assert_same(array(8, 25), pg_menu_categories()['system']['slots'], 'System: Users and System tools');
	pg_assert_same('', pg_menu_icon('settings', 'settings-color'), 'its gear is not drawn');
}

function test_menu_translations_has_a_way_for_managers()
{
	// translations.php admits managers; row 21 is built for role < 2 only.
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("if ((int) \$user['role'] === 2) {\n            \$menu_items[2]['data-bs-content'] .= '<a href=\\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/translations.php\\''", $menu, 'in the Pages panel for a manager');
	pg_assert_contains("case 'translations.php':\n            // A manager reaches it from the Pages panel; row 21 is built for\n            // role < 2 only.\n            \$active_menu = (\$user['role'] < 2) ? 21 : 2;", $menu, 'the row that lists it is marked');
}

function test_menu_tools_icon_is_two_coloured_and_moves()
{
	$svg = pg_menu_icon('tools', 'settings-color');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains('class="pg-mi pg-mi-tools me-3 settings-color"', $svg, 'the module colour');
	pg_assert_contains('<g class="pg-mi-a pg-mi-latch">', $svg, 'the latch in the accent colour');
	pg_assert_contains('<g class="pg-mi-a pg-mi-kit">', $svg, 'the tools inside in the accent colour');
	pg_assert_contains('<g class="pg-mi-lid">', $svg, 'the lid that opens');
	pg_assert_contains('#menu .pg-mi-tools { --pg-mi-a: var(--pg-mi-amber); }', $css, 'its accent hue');
	pg_assert_contains('#menu .list-group-item:is(:hover, :focus-visible) .pg-mi-tools .pg-mi-lid {', $css, 'the lid opens on hover');
}

function test_menu_tools_markup_opens_its_groups()
{
	$groups = array(
		array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
			array('label' => 'Site Log', 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
			array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'))),
		array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array()),
		array('label' => 'Jobs', 'icon' => 'bi-tools', 'items' => array(
			array('label' => 'Purge Cache', 'icon' => 'bi-trash3', 'url' => 'purge_cache.php?token=a&b', 'confirm' => 'Sure?'))));

	$menu = pg_menu_tools_markup($groups, '/pinegrap/', '', 'settings');

	// First level: the screen itself, then every group with screens.
	pg_assert_true(strpos($menu['entries'], 'href=\'/pinegrap/system_tools.php\'') < strpos($menu['entries'], 'menu_entry_25_tools_0'), 'the screen comes first');
	pg_assert_contains('id=\'menu_entry_25_tools_0\'', $menu['entries'], 'a group entry');
	pg_assert_contains('data-pg-menu-panel=\'menu_panel_25_tools_0\'', $menu['entries'], 'a group opens its panel');
	pg_assert_contains('data-pg-menu-panel=\'menu_panel_25_tools_2\'', $menu['entries'], 'groups keep their numbers');
	pg_assert_false(strpos($menu['entries'] . $menu['panels'], 'Empty') !== false, 'a group without screens is left out');
	pg_assert_contains('href=\'/pinegrap/view_log.php\'', $menu['entries'], 'a group entry leads to its first screen');
	pg_assert_false(strpos($menu['entries'] . $menu['panels'], 'data-pg-settings-page') !== false, 'no settings page here');

	// Second level.
	pg_assert_contains('id="menu_panel_25_tools_0"', $menu['panels'], 'the group panel');
	pg_assert_same(2, substr_count($menu['panels'], 'data-pg-menu-tone="settings"'), 'each group panel colours its icons as the row');
	pg_assert_contains('data-pg-menu-row="menu_entry_25_tools_0"', $menu['panels'], 'belongs to its entry');
	pg_assert_contains('target=\'_blank\' rel=\'noopener\'', $menu['panels'], 'a screen that opens apart keeps its target');
	pg_assert_contains('data-confirm-content=\'Sure?\'', $menu['panels'], 'a job keeps its question');
	pg_assert_contains('purge_cache.php?token=a&amp;b', $menu['panels'], 'addresses are escaped');

	pg_assert_same(array('entries' => '', 'panels' => ''), pg_menu_tools_markup(array(), '/pinegrap/', ''), 'no group, nothing');
}

function test_menu_tools_markup_in_the_toolbar()
{
	$groups = array(array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
		array('label' => 'Site Log', 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
		array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'))));

	$menu = pg_menu_tools_markup($groups, '/pinegrap/', ' target=\'_parent\'');
	$all = $menu['entries'] . $menu['panels'];

	pg_assert_same(3, substr_count($all, 'target=\'_parent\''), 'every link but the one with its own target leaves the toolbar frame');
	pg_assert_same(1, substr_count($all, 'target=\'_blank\''), 'its own target wins');
}

