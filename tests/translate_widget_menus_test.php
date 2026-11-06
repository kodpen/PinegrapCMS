<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menus a shared component or a system widget draws, as the
 * site translation reads them: a Menu or Menu Sequence widget names its menu
 * in its settings, a component may hold a classic menu region.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

// A Menu widget and a Menu Sequence widget name their menu; other widgets,
// an empty name and broken settings name none.
function test_translate_widget_menu_names_from_settings()
{
	pg_assert_same(array('Main'), pg_tr_shared_menu_names(array(), json_encode(array('regionType' => 'menu_region', 'menu_name' => 'Main'))));
	pg_assert_same(array('Docs'), pg_tr_shared_menu_names(array(), json_encode(array('regionType' => 'menu_sequence_region', 'menu_name' => ' Docs '))), 'trimmed as the renderer reads it');
	pg_assert_same(array('Main'), pg_tr_shared_menu_names(array(), json_encode(array('regionType' => 'menu_region', 'config' => array('menu_name' => 'Main')))), 'settings under config');
	pg_assert_same(array(), pg_tr_shared_menu_names(array(), json_encode(array('regionType' => 'menu_region', 'menu_name' => ''))), 'no menu chosen');
	pg_assert_same(array(), pg_tr_shared_menu_names(array(), json_encode(array('regionType' => 'catalog_listing', 'menu_name' => 'Main'))), 'another widget');
	pg_assert_same(array(), pg_tr_shared_menu_names(array(), null), 'a plain component');
	pg_assert_same(array(), pg_tr_shared_menu_names(array(), '{broken'), 'broken settings');
}

// A component (a header) holding classic menu regions names them too, once
// each, numeric names kept as strings.
function test_translate_widget_menu_names_from_tree()
{
	$tree = array('type' => 'section', 'props' => array(), 'children' => array(
		array('type' => 'region', 'props' => array('regionType' => 'menu', 'regionName' => 'Top')),
		array('type' => 'region', 'props' => array('regionType' => 'menu_sequence', 'regionName' => '2026')),
		array('type' => 'region', 'props' => array('regionType' => 'common', 'regionName' => 'Footer')),
	));
	pg_assert_same(array('Top', '2026'), pg_tr_shared_menu_names($tree, null));
	pg_assert_same(array('Top', '2026'), pg_tr_shared_menu_names($tree, json_encode(array('regionType' => 'menu_region', 'menu_name' => 'Top'))), 'a name met twice is listed once');
}
