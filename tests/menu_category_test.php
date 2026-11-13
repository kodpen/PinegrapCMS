<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the categories of the main menu (includes/fn/output.php): the
 * table of categories (pg_menu_categories()), the layout of the rows a person
 * gets (pg_menu_layout()), the heading (pg_menu_heading()) and the parts of
 * output_menu() that feed them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// The layout as key => slots, the easiest shape to compare.
function pg_test_menu_layout($drawn)
{
	$shape = array();

	foreach (pg_menu_layout($drawn, pg_menu_categories()) as $block) {
		$shape[] = array($block['key'], $block['label'], $block['slots']);
	}

	return $shape;
}

function test_menu_category_with_no_row_has_no_heading()
{
	$shape = pg_test_menu_layout(array(0, 8, 25));

	pg_assert_same(array(array('', '', array(0)), array('system', 'System', array(8, 25))), $shape, 'only the rows built are laid out');
}

function test_menu_categories_keep_the_table_order_whatever_the_build_order()
{
	$shape = pg_test_menu_layout(array(25, 8, 15, 10, 1, 2, 23, 0));

	pg_assert_same(array(
		array('', '', array(0, 23)),
		array('content', 'Content', array(2, 1)),
		array('ecommerce', 'Ecommerce', array(10, 15)),
		array('system', 'System', array(8, 25)),
	), $shape);
}

function test_menu_categories_for_narrow_rights()
{
	pg_assert_same(array(array('', '', array(0)), array('ecommerce', 'Ecommerce', array(15))), pg_test_menu_layout(array(0, 15)), 'order reports only: one heading over the one row');
	pg_assert_same(array(array('', '', array(0)), array('accounting', 'Accounting', array(22))), pg_test_menu_layout(array(0, 22)), 'the ERP alone is not under the store');
	pg_assert_same(array(array('', '', array(0)), array('content', 'Content', array(2, 1))), pg_test_menu_layout(array(0, 1, 2)), 'folder rights: pages and the file manager');
	pg_assert_same(array(array('', '', array(0, 23, 24))), pg_test_menu_layout(array(0, 23, 24)), 'the workspace and a pinned My Notes: no heading');
	pg_assert_same(array(array('', '', array(0))), pg_test_menu_layout(array(0)), 'nothing but the dashboard');
	pg_assert_same(array(), pg_test_menu_layout(array()), 'no rows, no blocks');
}

function test_menu_manager_gets_no_design_heading()
{
	// Row 21 is built for roles 0 and 1, and for whoever may open an entry
	// behind its Other; a manager on a site without Ads has neither.
	foreach (pg_menu_layout(array(0, 23, 1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 22, 25), pg_menu_categories()) as $block) {
		pg_assert_false($block['key'] === 'design', 'no Design block');
	}

	pg_assert_same(array(array('', '', array(0)), array('content', 'Content', array(2, 1)), array('design', 'Design', array(21))), pg_test_menu_layout(array(0, 1, 2, 21)), 'with Ads on: the heading over the one row');
}

function test_menu_row_no_category_lists_is_drawn_at_the_end()
{
	$shape = pg_test_menu_layout(array(0, 99, 8));

	pg_assert_same(array(array('', '', array(0)), array('system', 'System', array(8)), array('', '', array(99))), $shape, 'an unlisted row is kept, untitled, last');
}

function test_menu_categories_list_every_row_once()
{
	// The slots output_menu() builds, read from its source.
	preg_match_all('/\$menu_items\[(\d+)\]\[\'id\'\] = /', pg_test_function_source('output_menu'), $matches);
	$built = array_map('intval', array_unique($matches[1]));
	sort($built);

	$listed = array();
	$labels = array();

	foreach (pg_menu_categories() as $key => $category) {
		foreach ($category['slots'] as $slot) {
			pg_assert_false(in_array($slot, $listed, true), 'slot ' . $slot . ' is listed once');
			$listed[] = $slot;
		}

		if ($key !== '') {
			$labels[] = $category['label'];
		}
	}

	sort($listed);

	pg_assert_same($built, $listed, 'every row built has a place, and nothing else does');

	foreach (array(3, 13, 16, 17, 18, 19, 20) as $retired) {
		pg_assert_false(in_array($retired, $listed, true), 'retired slot ' . $retired);
		pg_assert_false(in_array($retired, $built, true), 'retired slot ' . $retired . ' is not built');
	}

	// Each title is a lang() source the Turkish shards translate.
	foreach ($labels as $label) {
		pg_assert_true(pg_test_menu_label_translated($label), $label . ' is translated');
	}
}

function pg_test_menu_label_translated($label)
{
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$shard = json_decode((string) file_get_contents($file), true);

		if (is_array($shard) && isset($shard[$label]) && ($shard[$label] !== '')) {
			return true;
		}
	}

	return false;
}

function test_menu_heading_markup_is_escaped()
{
	$html = pg_menu_heading('content', 'Pages & <b>Files</b>');

	pg_assert_contains('id="menu_heading_content"', $html, 'its id');
	pg_assert_contains('class="pg-menu-heading"', $html, 'its class');
	pg_assert_contains('Pages &amp; &lt;b&gt;Files&lt;/b&gt;', $html, 'the label is escaped');
	pg_assert_false(strpos($html, '<a') !== false, 'not a link');
	pg_assert_false(strpos($html, 'list-group-item') !== false, 'not a row');
}

function test_menu_layout_reads_only_the_rows_built()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("if (isset(\$options['id'])) {\n            \$menu_drawn[] = (int) \$key;", $menu, 'a row counts once it has its id');
	pg_assert_contains('pg_menu_layout($menu_drawn, pg_menu_categories())', $menu, 'the layout is fed the rows built');
	pg_assert_false(strpos($menu, '$reordered') !== false, 'no second ordering of the rows');
}

function test_menu_file_manager_row_holds_the_files()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("\$menu_items[1]['href'] = 'view_folders.php';", $menu, 'the row opens the explorer');
	pg_assert_contains("\$menu_items[1]['data-bs-content'] = pg_menu_explorer_side('files', \$user, \$toolbar, \$menu_tail);", $menu, 'the panel is the tree alone, with Files and Pictures in it');
	pg_assert_false(strpos($menu, "/view_folders.php?view=files&upload=1") !== false, 'uploading happens in the explorer, not from an entry');
	pg_assert_false(strpos($menu, "OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=images") !== false, 'Pictures is not an entry twice');
	pg_assert_false(strpos($menu, "OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=files\\''") !== false, 'nor Files');
	pg_assert_false(strpos($menu, "\$menu_items[3]") !== false, 'the Files row is gone');
	pg_assert_false(strpos($menu, '? 3 : 1') !== false, 'every view of the explorer marks the File Manager row');
	pg_assert_false(strpos($menu, '$active_menu = 3;') !== false, 'nothing marks the retired row');
	pg_assert_false(strpos($menu, '$active_menu = 13;') !== false, 'nor the never-built 13');
}

function test_menu_store_rows_are_named_for_their_subject()
{
	$menu = pg_test_function_source('output_menu');

	foreach (array(10 => 'Orders', 11 => 'Products', 14 => 'Offers', 15 => 'Shipping') as $slot => $title) {
		pg_assert_contains("\$menu_items[" . $slot . "]['title'] = '" . $title . "';", $menu, 'row ' . $slot);
		pg_assert_contains("data-pg-menu-label=\\'' . h(lang('" . $title . "')) . '\\'", $menu, 'its first entry gives the row name as its hint label');
	}
}

function test_menu_products_full_list_entries_name_the_row()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("/view_products.php?filter=all_products\\'' . \$output_parent_target . ' data-pg-menu-label=\\'' . h(lang('Products')) . '\\'", $menu, 'the filtered full list gives no hint either');
}

function test_menu_products_has_one_all_products_entry_on_the_full_list()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_same(1, substr_count($menu, "lang('All Products')"), 'one All Products entry in the panel');
	pg_assert_false(strpos($menu, "/view_products.php\\'' . \$output_parent_target") !== false, 'no entry on the bare address, which reopens the last filter');
}

function test_menu_explorer_views_refresh_the_menu_mark()
{
	// The explorer changes its view in place; every address it writes goes
	// through setAddress(), which asks the menu to mark the view again.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains("function setAddress(url) {\n        window.history.replaceState(null, '', url);\n\n        if (window.pgMenu && (typeof window.pgMenu.refreshCurrent === 'function')) {\n            window.pgMenu.refreshCurrent();", $source, 'the one place that writes the address');
	pg_assert_same(1, substr_count($source, 'history.replaceState('), 'no other address write');
	pg_assert_true(substr_count($source, "setAddress('view_") >= 6, 'every view switch uses it');
}

function test_menu_system_category_holds_the_system_tools_row()
{
	$categories = pg_menu_categories();
	pg_assert_same(array(8, 25), $categories['system']['slots'], 'Users, System tools');
}

// The Page Styles row (17) and the Ads row (16) are gone: their entries sit
// behind Other in the Page Design Studio row (21).
function test_menu_design_category_holds_the_page_design_studio_row_alone()
{
	$categories = pg_menu_categories();

	pg_assert_same(array(21), $categories['design']['slots'], 'Design: the Page Design Studio');
	pg_assert_same(array(6, 7, 9), $categories['marketing']['slots'], 'Marketing: Visitors, Contacts, My Campaigns');
}

function test_menu_page_styles_and_ads_rows_are_retired()
{
	$menu = pg_test_function_source('output_menu');

	foreach (array(16, 17) as $slot) {
		pg_assert_false(strpos($menu, '$menu_items[' . $slot . ']') !== false, 'slot ' . $slot . ' is not built');
		pg_assert_false(strpos($menu, '$active_menu = ' . $slot . ';') !== false, 'nothing marks slot ' . $slot);
	}

	pg_assert_same('', pg_menu_icon('styles', 'design-color'), 'the palette went with its row');
	pg_assert_same('', pg_menu_icon('ads', 'ads-color'), 'the billboard too');
}

function test_menu_row_21_is_titled_page_design_studio()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("\$menu_items[21]['title'] = 'Page Design Studio';", $menu, 'the row');
	pg_assert_false(strpos($menu, 'Visual Page Editor') !== false, 'the old name is gone from the menu');
	pg_assert_true(pg_test_menu_label_translated('Page Design Studio'), 'the row name is translated');
	pg_assert_true(pg_test_menu_label_translated('Other'), 'Other is translated');
}

// The part of output_menu() that builds Other's entries: from where the
// list starts to where the row's own lines begin.
function pg_test_menu_other_source()
{
	$menu = pg_test_function_source('output_menu');
	$start = strpos($menu, "\$design_other_content = '';");
	$end = strpos($menu, "\$menu_items[21]['id'] = 21;");

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end), 'Other is built before the row');

	return substr($menu, $start, $end - $start);
}

function test_menu_other_holds_the_page_styles_entries_in_their_order_then_ads()
{
	$other = pg_test_menu_other_source();

	// The entries as the Page Styles panel had them, then Ads.
	$order = array(
		'/view_styles.php', '/add_custom_style.php', '/import_design.php',
		'/view_themes.php', '/add_theme_file.php',
		'/view_design_files.php', '/add_design_file.php', '/import_zip.php',
		'/migration.php', '/find_and_replace.php', '/mass_edit.php',
		'/view_menus.php', '/add_menu.php',
		'/view_regions.php?filter=all_common_regions', '/add_common_region.php',
		'/view_regions.php?filter=all_designer_regions', '/add_designer_region.php',
		'/view_regions.php?filter=all_login_regions', '/add_login_region.php',
		'/view_regions.php?filter=all_ad_regions', '/add_ad_region.php',
		'/view_regions.php?filter=all_dynamic_regions', '/add_dynamic_region.php',
		"\$design_other_content .= '<hr class=\\'divider my-3\\' />' . \$design_menus_content",
		'/view_ads.php', '/add_ad.php',
	);

	$at = -1;

	foreach ($order as $needle) {
		$found = strpos($other, $needle);

		pg_assert_true($found !== false, $needle . ' is behind Other');
		pg_assert_true($found > $at, $needle . ' keeps its place');
		$at = $found;
	}

	pg_assert_same(1, substr_count($other, "lang('Page Styles')"), 'Page Styles heads the list');
	pg_assert_same(1, substr_count($other, "lang('Ads')"), 'Ads ends it');
}

function test_menu_other_keeps_each_entry_under_its_own_gate()
{
	$other = pg_test_menu_other_source();

	// The classic designer's screens: designers and administrators.
	$classic = strpos($other, "if ((\$user['role'] < 2)) {\n        // OTHER > Page Styles");
	// Ads: the gate its row had.
	$ads = strpos($other, "if ((ADS === true) && ((\$user['role'] < 3) || (count(get_items_user_can_edit('ad_regions', \$user['id'])) > 0))) {\n        // OTHER > Ads");

	pg_assert_true($classic !== false, 'the classic screens under role < 2');
	pg_assert_true(($ads !== false) && ($ads > $classic), 'Ads under the gate of its screens');
	pg_assert_true(strpos($other, '/view_ads.php') > $ads, 'the Ads entries are inside it');
	pg_assert_true(strpos($other, '/mass_edit.php') < $ads, 'the classic entries are not');
	pg_assert_contains("if ((\$user['role'] < 1) && (defined('MIG') == true) && (MIG == true)) {", $other, 'Migration stays the administrator\'s');
	pg_assert_contains("if (!pg_hosted()) {", $other, 'Import My Site stays off a hosted site');
	pg_assert_contains("if ((\$user['role'] < 1) && ((defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true))) {", $other, 'Dynamic Regions stay the administrator\'s');
}

function test_menu_row_21_is_drawn_for_whoever_may_open_one_of_its_entries()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("if ((\$user['role'] < 2) || (\$design_other_content !== '')) {\n        \$menu_items[21]['id'] = 21;", $menu, 'the studio\'s own entries, or one behind Other');
	pg_assert_contains("\$menu_items[21]['data-bs-content'] = '';\n\n        if (\$user['role'] < 2) {\n            \$menu_items[21]['data-bs-content'] .= '<a href=\\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_system_styles.php\\''", $menu, 'the studio\'s own entries keep their gate');
	pg_assert_contains("\$menu_items[21]['href'] = 'view_system_styles.php';", $menu, 'the row keeps its address');

	// Other is the last entry, drawn only when one of its own is there.
	pg_assert_contains("if (isset(\$menu_items[21]['id']) && (\$design_other_content !== '')) {\n        \$design_other = pg_menu_other_markup(21, \$design_other_content, \$output_parent_target, pg_menu_tone(\$menu_items[21]['color_class']), \$menu_other_here);", $menu, 'Other from its entries');
	pg_assert_contains("\$menu_items[21]['data-bs-content'] .= \$design_other['entry'];\n            \$menu_panels_nested .= \$design_other['panel'];", $menu, 'the entry in the row\'s panel, its panel at the second level');
	pg_assert_true(strpos($menu, "\$design_other = pg_menu_other_markup(") > strpos($menu, "/experiments.php"), 'after the studio\'s own entries');
}

// The rows a script name marks, read from the switch of output_menu():
// file => array(what $active_menu is set to, whether Other stands open).
function pg_test_menu_active_rows()
{
	$menu = pg_test_function_source('output_menu');
	$start = strpos($menu, 'switch ($file_name) {');
	$end = strpos($menu, '// PAGE DESIGN STUDIO > Other: the last entry', $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the switch is found');

	$rows = array();

	foreach (explode('break;', substr($menu, $start, $end - $start)) as $group) {
		if (!preg_match_all("/case '([a-z0-9_]+\\.php)':/", $group, $files)) {
			continue;
		}

		preg_match('/\\$active_menu = ([^;]+);/', $group, $active);

		foreach ($files[1] as $file) {
			$rows[$file] = array(isset($active[1]) ? $active[1] : '', strpos($group, '$menu_other_here = true;') !== false);
		}
	}

	return $rows;
}

function test_menu_other_screens_mark_row_21_and_stand_open()
{
	$rows = pg_test_menu_active_rows();

	// Every screen that marked the Page Styles row or the Ads row.
	$screens = array(
		'view_styles.php', 'add_style.php', 'view_system_style_source.php', 'add_custom_style.php', 'edit_custom_style.php',
		'view_themes.php', 'add_theme_file.php', 'edit_theme_file.php', 'theme_designer.php', 'edit_theme_css.php',
		'view_design_files.php', 'add_design_file.php', 'edit_design_file.php', 'edit_javascript.php',
		'import_design.php', 'import_zip.php', 'legacy_to_designer.php', 'migration.php', 'find_and_replace.php', 'mass_edit.php',
		'view_menus.php', 'add_menu.php', 'edit_menu.php', 'view_menu_items.php', 'add_menu_item.php', 'edit_menu_item.php',
		'view_regions.php', 'add_common_region.php', 'edit_common_region.php', 'edit_designer_region.php', 'add_designer_region.php',
		'add_ad_region.php', 'edit_ad_region.php', 'add_dynamic_region.php', 'edit_dynamic_region.php', 'add_login_region.php', 'edit_login_region.php',
		'view_ads.php', 'add_ad.php', 'edit_ad.php',
	);

	foreach ($screens as $file) {
		pg_assert_true(isset($rows[$file]), $file . ' marks a row');
		pg_assert_same(array('21', true), $rows[$file], $file . ' marks row 21, with Other open');
	}

	// Nothing else opens Other: the studio's own screens mark the row alone.
	foreach ($rows as $file => $row) {
		if (!in_array($file, $screens, true)) {
			pg_assert_false($row[1], $file . ' is not one of Other\'s screens');
		}
	}

	pg_assert_same(array('21', false), $rows['add_system_style.php'], 'the studio\'s own screen');
	pg_assert_same(array('21', false), $rows['experiments.php'], 'Experiments');
	pg_assert_same(array("(\$user['role'] < 2) ? 21 : 2", false), $rows['view_system_styles.php'], 'the home, in the Pages panel below the designer');
}

function test_menu_row_21_opens_other_on_its_screens()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("if (\$menu_other_here) {\n                \$menu_items[21]['opens'] = \$design_other['panel_id'];", $menu, 'the row names Other on the screens of its entries');
	pg_assert_contains("\$menu_click_panel_id = pg_menu_row_click_panel(\$options, \$menu_panel_id);\n            \$output_menu_row_panel .= ' data-pg-menu-panel=\"' . \$menu_click_panel_id . '\" aria-controls=\"' . \$menu_click_panel_id . '\" aria-expanded=\"false\"';", $menu, 'the click shows the panel the row names');
	pg_assert_contains("if (\$menu_panel_id === 'menu_panel_' . (int) \$options['id']) {", $menu, 'the row still draws its own panel');
}
