<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panels of the main menu (includes/fn/output.php): which rows
 * open their entries inside the menu (pg_menu_row_opens_panel()), the panel
 * they open (pg_menu_panel()) and the module that colours its icons
 * (pg_menu_category_tone()), the System tools row (pg_menu_tools_markup()), and the
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

function test_menu_panel_entries_wrap_instead_of_ending_in_an_ellipsis()
{
	$entries = '<a href=\'/pinegrap/view_verified_shipping_addresses.php\' class=\'btn btn-link pg-link-body-emphasis text-start text-decoration-none text-truncate bi bi-geo bi-me-2\'>All Verified Shipping Addresses</a>';
	$html = pg_menu_panel('menu_panel_15', 'menu_item_15', 'Shipping', $entries);

	pg_assert_false(strpos($html, 'text-truncate') !== false, 'the utility that would beat the panel CSS is dropped');
	pg_assert_contains("class='btn btn-link pg-link-body-emphasis text-start text-decoration-none bi bi-geo bi-me-2'", $html, 'the other classes stay');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains("#menu .menu-body .pg-menu-panel-body > a.bi {\n  display: flex;\n  align-items: center;\n}", $css, 'the name wraps beside its icon');
}

function test_menu_panel_names_its_category_for_its_icons()
{
	pg_assert_contains('data-pg-menu-row="menu_item_2" data-pg-menu-tone="content" role="group"', pg_menu_panel('menu_panel_2', 'menu_item_2', 'Pages', '', 'content'), 'the category');
	pg_assert_false(strpos(pg_menu_panel('menu_panel_2', 'menu_item_2', 'Pages', '', 'content" onclick="x'), 'data-pg-menu-tone') !== false, 'anything but a plain name is left out');
}

function test_menu_category_tone_is_the_category_of_a_slot()
{
	pg_assert_same('top', pg_menu_category_tone(0), 'the untitled block at the top');
	pg_assert_same('top', pg_menu_category_tone(26), 'Automation flows');
	pg_assert_same('content', pg_menu_category_tone(2), 'Pages');
	pg_assert_same('design', pg_menu_category_tone(21), 'Page Design Studio');
	pg_assert_same('ecommerce', pg_menu_category_tone(29), 'Pick waves');
	pg_assert_same('accounting', pg_menu_category_tone(22), 'the ERP');
	pg_assert_same('marketing', pg_menu_category_tone(9), 'Communication, not its module colour');
	pg_assert_same('system', pg_menu_category_tone(25), 'System tools');
	pg_assert_same('top', pg_menu_category_tone(99), 'a row no category lists');
}

function test_menu_every_category_has_its_tone_in_the_stylesheet()
{
	// Every category colours its rows' tiles and its panels' icons; without
	// a tone line they are drawn without colour.
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$tones = array('top' => 'blue', 'content' => 'teal', 'design' => 'rose', 'ecommerce' => 'amber', 'accounting' => 'lime', 'marketing' => 'violet', 'system' => 'gray');

	foreach (array_keys(pg_menu_categories()) as $key) {
		$tone = ($key === '') ? 'top' : $key;

		pg_assert_true(isset($tones[$tone]), $tone . ' has a colour');
		pg_assert_contains(':is(#menu, .pg-look-mi) [data-pg-menu-tone="' . $tone . '"] { --pg-menu-tone: var(--pg-tone-' . $tones[$tone] . '); }', $css, $tone);
		pg_assert_contains('--pg-tone-' . $tones[$tone] . ': light-dark(', $css, $tone . ' in both themes');
	}

	pg_assert_same(7, substr_count($css, ':is(#menu, .pg-look-mi) [data-pg-menu-tone="'), 'one line a category');
	pg_assert_false(strpos($css, '#menu .pg-menu-panel[data-pg-menu-tone="') !== false, 'no tone a module');
}

function test_menu_every_row_icon_has_a_drawing()
{
	$menu = pg_test_function_source('output_menu');

	preg_match_all("/\['menu_icon'\] = '([a-z_]+)';/", $menu, $match);
	pg_assert_true(count(array_unique($match[1])) > 15, 'the rows are read');

	foreach (array_unique($match[1]) as $name) {
		$svg = pg_menu_icon($name, '', '');

		pg_assert_contains('<svg class="pg-mi pg-mi-' . $name . '" ', $svg, $name . ' is drawn, without a colour or margin of its own');
		pg_assert_contains('stroke="currentColor" stroke-width="1.7"', $svg, $name . ' in the one line family');
		pg_assert_false(strpos($svg, 'fill-opacity') !== false, $name . ' has no tint of its own');
		pg_assert_false((bool) preg_match('/\d\.\d*0(?!\d)/', $svg), $name . ' writes no trailing zeros');
	}

	pg_assert_contains("\$menu_items[21]['menu_icon'] = 'design';", $menu, 'Page Design Studio draws the selection frame');
	pg_assert_false(strpos($menu, 'pg-neon') !== false, 'the neon sign went');
}

function test_menu_row_icon_sits_on_its_category_tile()
{
	$tile = pg_menu_icon_tile(pg_menu_icon('pages', '', ''), 'content');

	pg_assert_true(strpos($tile, '<span class="pg-mi-tile me-3" data-pg-menu-tone="content"><svg class="pg-mi pg-mi-pages" ') === 0, 'the tile carries the margin and the colour');
	pg_assert_contains('data-pg-menu-tone="top"', pg_menu_icon_tile('<i class="bi bi-dot"></i>', 'x" onclick="y'), 'anything but a plain name takes the top block');

	$menu = pg_test_function_source('output_menu');
	pg_assert_contains("\$output_menu_item_icon = pg_menu_icon_tile(\$output_menu_item_icon, \$menu_row_tone);", $menu, 'every row, its fallback icons too');
	pg_assert_contains("\$menu_row_tone = pg_menu_category_tone(\$key);", $menu, 'the colour of its category');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains("  --pg-menu-tile: 2rem;\n  --pg-menu-icon: 1.2rem;", $css, 'a 32 px tile and an icon of at least 1.2rem');
	pg_assert_contains("  width: var(--pg-menu-tile);\n  height: var(--pg-menu-tile);\n  border-radius: 0.625rem;", $css, 'the tile');
	pg_assert_contains("  --pg-mi-tint: 13%;\n  --pg-mi-line: 16%;", $css, 'a tint and an inner line');
	pg_assert_contains(":is(#menu, .pg-look-mi) .pg-mi-tile > svg {\n  width: var(--pg-menu-icon);", $css, 'the icon on it');
}

function test_menu_open_row_is_told_by_a_filled_tile()
{
	// The open row and a panel's current entry: the tile filled with the
	// category's tone, the drawing on it, the row the neutral quiet fill.
	// No blue fill: it read as one more blue tile beside the top rows.
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains("  --pg-mi-on: light-dark(var(--bs-white), var(--pg-navbar-bg));", $css, 'white on the tile, the ground in dark');
	pg_assert_contains(":is(#menu .list-group-item.active, .pg-look-mi-open) .pg-mi-tile {\n  --pg-mi-tint: 100%;\n  --pg-mi-line: 100%;\n  color: var(--pg-mi-on);", $css, 'the open row\'s tile is filled');
	pg_assert_contains("  --bs-list-group-active-color: var(--pg-fg-emphasis);\n  --bs-list-group-active-bg: var(--pg-quiet);", $css, 'the row the quiet fill and dark words');
	pg_assert_false(strpos($css, '--bs-list-group-active-bg: rgba(var(--pg-primary-rgb)') !== false, 'no blue fill on the row');
	pg_assert_contains("#menu:not(.expanded) .pg-menu-main .list-group-item.list-group-item-action.active {\n    --bs-list-group-active-bg: transparent;", $css, 'the tile alone on the rail');
	pg_assert_contains("#menu .menu-body .pg-menu-panel-body > a.pg-menu-current {\n  background: var(--pg-quiet);", $css, 'the current entry the quiet fill');
	pg_assert_contains("a.bi.pg-menu-current::before {\n  color: var(--pg-mi-on);\n  background: var(--pg-menu-tone);", $css, 'the current entry\'s tile is filled');
	pg_assert_contains("  --bs-list-group-action-hover-bg: var(--pg-hover);", $css, 'hover a lighter fill');
	pg_assert_contains("#menu .menu-body .pg-menu-panel-body > a:is(:hover, :focus-visible) {\n  background: var(--pg-hover);", $css, 'hover on an entry too');

	// The drawing at 3:1 or more on every filled tile: white in the light
	// theme, the menu's ground in the dark one.
	preg_match('/--pg-navbar-bg-rgb: 248, 248, 248;.*?--pg-navbar-bg-rgb: (\d+), (\d+), (\d+);/s', $css, $ground);
	pg_assert_true(!empty($ground), 'the dark ground is found');
	$dark_ground = sprintf('%02x%02x%02x', $ground[1], $ground[2], $ground[3]);
	$lum = function ($hex) {
		$sum = 0;
		foreach (array(0 => 0.2126, 2 => 0.7152, 4 => 0.0722) as $offset => $weight) {
			$c = hexdec(substr($hex, $offset, 2)) / 255;
			$sum += $weight * (($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4));
		}
		return $sum;
	};
	$ratio = function ($a, $b) use ($lum) {
		return (max($lum($a), $lum($b)) + 0.05) / (min($lum($a), $lum($b)) + 0.05);
	};

	foreach (array('blue', 'teal', 'rose', 'amber', 'lime', 'violet', 'gray') as $tone) {
		pg_assert_true((bool) preg_match('/--pg-tone-' . $tone . ': light-dark\(#([0-9a-f]{6}), #([0-9a-f]{6})\);/', $css, $m), $tone . ' is found');
		pg_assert_true($ratio($m[1], 'ffffff') >= 3, $tone . ': white on the light tile');
		pg_assert_true($ratio($m[2], $dark_ground) >= 3, $tone . ': the ground on the dark tile');
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
	// translations.php admits managers; row 21 holds it for role < 2 only.
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("if ((int) \$user['role'] === 2) {\n            \$menu_items[2]['data-bs-content'] .= '<a href=\\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/translations.php\\''", $menu, 'in the Pages panel for a manager');
	pg_assert_contains("case 'translations.php':\n            // A manager reaches it from the Pages panel; row 21 holds it for\n            // role < 2 only.\n            \$active_menu = (\$user['role'] < 2) ? 21 : 2;", $menu, 'the row that lists it is marked');
}

function test_menu_tools_icon_is_a_toolbox_with_a_lid()
{
	$svg = pg_menu_icon('tools', '', '');

	pg_assert_contains('class="pg-mi pg-mi-tools"', $svg, 'its classes');
	pg_assert_contains('<rect x="3" y="10" width="18" height="10" rx="2"/><rect x="10.5" y="12.5" width="3" height="3" rx=".7"/>', $svg, 'the box and its latch');
	pg_assert_contains('<g data-m="lid"><path d="M3 10V9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1"/>', $svg, 'the lid that opens');
	pg_assert_same('system', pg_menu_category_tone(25), 'in the System grey');
}
function test_menu_communication_row_draws_a_bubble_with_two_arrows()
{
	$svg = pg_menu_icon('communication', '', '');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains("\$menu_items[9]['menu_icon'] = 'communication';", pg_test_function_source('output_menu'), 'row 9 names its own drawing');
	pg_assert_contains('class="pg-mi pg-mi-communication"', $svg, 'its classes');
	pg_assert_contains('<g data-m="send"><path d="M8 8.3h8M13.8 6.3l2.2 2-2.2 2"/></g>', $svg, 'the arrow going out');
	pg_assert_contains('<g data-m="recv"><path d="M16 12.8H8M10.2 10.8l-2.2 2 2.2 2"/></g>', $svg, 'the arrow coming in');
	pg_assert_same('', pg_menu_icon('campaigns', 'campaigns-color'), 'the megaphone went with it');
	pg_assert_false(strpos($css, 'pg-mi-campaigns') !== false, 'no rule of the megaphone is left');
}

function test_menu_tools_markup_is_one_list_under_group_headings()
{
	$groups = array(
		array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
			array('label' => 'Site Log', 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
			array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'))),
		array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array()),
		array('label' => 'Jobs & <b>tasks</b>', 'icon' => 'bi-tools', 'items' => array(
			array('label' => 'Purge Cache', 'icon' => 'bi-trash3', 'url' => 'purge_cache.php?token=a&b', 'confirm' => 'Sure?'))));

	$menu = pg_menu_tools_markup($groups, '/pinegrap/', '');

	pg_assert_same(2, substr_count($menu, 'class=\'pg-menu-panel-heading\''), 'a heading per group with tools');
	pg_assert_false(strpos($menu, 'Empty') !== false, 'a group without tools prints no heading');
	pg_assert_contains('Jobs &amp; &lt;b&gt;tasks&lt;/b&gt;', $menu, 'the heading is escaped');
	pg_assert_true(strpos($menu, 'Monitoring') < strpos($menu, 'view_log.php') && strpos($menu, 'si.php') < strpos($menu, 'Jobs'), 'each group\'s tools under its heading');
	pg_assert_false(strpos($menu, 'system_tools.php') !== false, 'no entry for the screen itself');
	pg_assert_false(strpos($menu, 'data-pg-menu-panel') !== false || strpos($menu, 'pg-menu-panel"') !== false, 'no second-level panel');
	pg_assert_same(3, substr_count($menu, '<a '), 'every tool is an entry');
	pg_assert_contains('target=\'_blank\' rel=\'noopener\'', $menu, 'a tool that opens apart keeps its target');
	pg_assert_contains('data-confirm-content=\'Sure?\'', $menu, 'a job keeps its question');
	pg_assert_contains('purge_cache.php?token=a&amp;b', $menu, 'addresses are escaped');
	pg_assert_contains('bi-trash3 bi-me-2\'><span class=\'pg-menu-entry-label\'>Purge Cache</span></a>', $menu, 'a tool keeps its icon, its name in a box that ends in an ellipsis');
	pg_assert_contains('title=\'Purge Cache\'', $menu, 'the whole name as the title');
	pg_assert_same(1, substr_count($menu, 'bi-box-arrow-up-right pg-menu-entry-ext'), 'only the tool that opens a new tab carries the mark');
	pg_assert_contains('System Informations</span><i class=\'bi bi-box-arrow-up-right pg-menu-entry-ext\' aria-hidden=\'true\'></i><span class=\'visually-hidden\'>', $menu, 'the mark at the end, said in words');

	pg_assert_same('', pg_menu_tools_markup(array(), '/pinegrap/', ''), 'no group, nothing');
	pg_assert_same('', pg_menu_tools_markup(array(array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array())), '/pinegrap/', ''), 'no tool, nothing');
}

function test_menu_tools_markup_in_the_toolbar()
{
	$groups = array(array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
		array('label' => 'Site Log', 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
		array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'))));

	$menu = pg_menu_tools_markup($groups, '/pinegrap/', ' target=\'_parent\'');

	pg_assert_same(1, substr_count($menu, 'target=\'_parent\''), 'every tool but the one with its own target leaves the toolbar frame');
	pg_assert_same(1, substr_count($menu, 'target=\'_blank\''), 'its own target wins');
}

function test_menu_tools_heading_is_drawn()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains('#menu .pg-menu-panel-body > .pg-menu-panel-heading {', $css, 'the group heading has its rule');
}


// Other: an entry of a row's panel that opens a second-level panel with the
// entries kept one step further in (the Page Design Studio row).
function pg_test_menu_other_entries()
{
	return '<a href=\'/pinegrap/view_styles.php\' class=\'btn btn-link pg-link-body-emphasis text-start text-decoration-none text-truncate bi bi-columns-gap bi-me-2\'>Page Styles</a>'
		. '<hr class=\'divider my-3\' />'
		. '<a href=\'/pinegrap/view_ads.php\' class=\'btn btn-link pg-link-body-emphasis text-start text-decoration-none text-truncate bi bi-badge-ad bi-me-2\'>Ads</a>';
}

function test_menu_other_markup_is_an_entry_and_its_panel()
{
	$other = pg_menu_other_markup(21, pg_test_menu_other_entries(), '', 'design');

	pg_assert_same('menu_panel_21_other', $other['panel_id'], 'the panel is named for its row');
	pg_assert_contains('id=\'menu_entry_21_other\'', $other['entry'], 'the entry');
	pg_assert_contains('href=\'/pinegrap/view_styles.php\'', $other['entry'], 'its address is the first screen, for a middle click');
	pg_assert_contains('data-pg-menu-panel=\'menu_panel_21_other\' aria-controls=\'menu_panel_21_other\' aria-expanded=\'false\'', $other['entry'], 'a plain click opens the panel, shut until then');
	pg_assert_contains(' pg-menu-parent\'', $other['entry'], 'room for its arrow');
	pg_assert_contains('>' . h(lang('Other')) . '<i class=\'bi bi-chevron-right pg-menu-chevron\' aria-hidden=\'true\'></i></a>', $other['entry'], 'its name and the arrow');
	pg_assert_contains(' data-pg-menu-skip ', $other['entry'], 'a group is never the page\'s entry');
	pg_assert_false(strpos($other['entry'], ' active') !== false, 'not marked away from its screens');
	pg_assert_false(strpos($other['entry'], 'aria-current') !== false, 'nor named current');

	pg_assert_contains('id="menu_panel_21_other"', $other['panel'], 'the second-level panel');
	pg_assert_contains('data-pg-menu-row="menu_entry_21_other"', $other['panel'], 'its way back leads to the panel holding the entry');
	pg_assert_contains('data-pg-menu-tone="design"', $other['panel'], 'icons in the row\'s colours');
	pg_assert_contains('>' . h(lang('Other')) . '</span>', $other['panel'], 'headed by its name');
	pg_assert_true(strpos($other['panel'], 'view_styles.php') < strpos($other['panel'], 'view_ads.php'), 'the entries in their order');
	pg_assert_same(1, substr_count($other['panel'], '<hr '), 'and their dividers');
}

function test_menu_other_markup_is_marked_on_its_screens()
{
	$other = pg_menu_other_markup(21, pg_test_menu_other_entries(), '', 'design', true);

	pg_assert_contains(' pg-menu-parent active\' aria-current=\'true\' data-pg-menu-panel=', $other['entry'], 'marked the way a row is on its own screens');

	// What keeps a panel open from one page to the next: an entry of it is
	// the page, or the row it belongs to is marked.
	$backend = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	pg_assert_contains("(panel.querySelector('.pg-menu-current') || row.classList.contains('active'))", $backend, 'the mark the script reads');
	pg_assert_contains("var row = panel ? document.getElementById(panel.getAttribute('data-pg-menu-row')) : null;", $backend, 'on the element the panel names');
}

function test_menu_other_markup_without_a_link_draws_nothing()
{
	$nothing = array('entry' => '', 'panel' => '', 'panel_id' => '');

	pg_assert_same($nothing, pg_menu_other_markup(21, '', '', 'design'), 'no entries');
	pg_assert_same($nothing, pg_menu_other_markup(21, '<hr class=\'divider my-3\' />', '', 'design', true), 'a divider alone');
}

function test_menu_other_markup_in_the_toolbar_and_with_a_query()
{
	$entries = '<a href=\'/pinegrap/view_regions.php?filter=all_ad_regions&amp;x=1\' target=\'_parent\' class=\'btn\'>All Ad Regions</a>';
	$other = pg_menu_other_markup(21, $entries, ' target=\'_parent\'');

	pg_assert_contains('href=\'/pinegrap/view_regions.php?filter=all_ad_regions&amp;x=1\' target=\'_parent\'', $other['entry'], 'the address stays escaped once, and leaves the toolbar frame');
	pg_assert_false(strpos($other['panel'], 'data-pg-menu-tone') !== false, 'no module, plain icons');
}

// On the screens of a second-level panel's entries the row opens that panel
// itself; anywhere else its own.
function test_menu_row_click_panel_is_its_own_or_the_one_it_names()
{
	pg_assert_same('menu_panel_21', pg_menu_row_click_panel(array('id' => 21), 'menu_panel_21'), 'its own panel');
	pg_assert_same('menu_panel_21_other', pg_menu_row_click_panel(array('id' => 21, 'opens' => 'menu_panel_21_other'), 'menu_panel_21'), 'the panel that stands open');
	pg_assert_same('', pg_menu_row_click_panel(array('id' => 21, 'opens' => 'menu_panel_21_other'), ''), 'a plain link opens nothing');
	pg_assert_same('menu_panel_21', pg_menu_row_click_panel(array('id' => 21, 'opens' => ''), 'menu_panel_21'), 'an empty name is none');
	pg_assert_same('menu_panel_21', pg_menu_row_click_panel(array('id' => 21, 'opens' => 'x" onclick="y'), 'menu_panel_21'), 'only a panel id is taken');
}
