<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests pg_panel_search_chip_gates() in includes/panel/search.php: each
 * chip of the panel search box is offered under the same role, right and
 * module rule as the main-menu entry that opens the same screen
 * (includes/fn/output.php), so no chip ends on "Access denied" and none is
 * missing for someone the screen lets in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The file only defines the panel actions; its gate is the dispatcher's.
if (!defined('PG_PANEL_ACTIONS')) {
	define('PG_PANEL_ACTIONS', true);
}
require_once(PG_FUNCTIONS_DIR . '/includes/panel/search.php');

// Every module on, no right granted, no database-backed right.
function panel_search_chips_rights($role, $over = array())
{
	return array_merge(array(
		'role'                    => $role,
		'create_pages'            => false,
		'manage_visitors'         => false,
		'manage_forms'            => false,
		'manage_emails'           => false,
		'manage_ecommerce'        => false,
		'manage_ecommerce_reports'=> false,
		'folder_edit'             => false,
		'contact_group'           => false,
		'calendar'                => false,
		'ad_region'               => false,
		'ecommerce_module'        => true,
		'calendars_module'        => true,
		'forms_module'            => true,
		'ads_module'              => true,
		'dynamic_regions_module'  => true,
		'not_found_log_ready'     => true,
	), $over);
}

function panel_search_chips_on($rights)
{
	return array_keys(array_filter(pg_panel_search_chip_gates($rights)));
}

function test_search_chips_admin_gets_every_chip()
{
	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(0));
	foreach ($gates as $gate => $on) {
		pg_assert_true($on, $gate);
	}
}

function test_search_chips_designer_has_no_dynamic_regions()
{
	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(1));
	pg_assert_false($gates['dynamic_regions'], 'view_regions.php lists dynamic regions for administrators only');
	pg_assert_true($gates['design'], 'design');
	pg_assert_true($gates['menus'], 'menus');
}

function test_search_chips_manager_has_no_menus_or_design()
{
	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(2));
	pg_assert_false($gates['menus'], 'view_menus.php admits designers only');
	pg_assert_false($gates['design'], 'design');
	pg_assert_true($gates['manage'], 'manage');
	pg_assert_true($gates['users'], 'users');
	pg_assert_true($gates['add_page'], 'add page');
	pg_assert_true($gates['order_reports'], 'order reports');
}

function test_search_chips_role3_without_rights_gets_only_help()
{
	pg_assert_same(array('help'), panel_search_chips_on(panel_search_chips_rights(3)));
}

function test_search_chips_role3_folder_editor()
{
	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(3, array('folder_edit' => true)));
	pg_assert_true($gates['folders'], 'pages, files, comments');
	pg_assert_false($gates['add_page'], 'add_page.php refuses without create_pages');
	pg_assert_false($gates['manage'], 'auto dialogs and short links are for managers');

	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(3, array('folder_edit' => true, 'create_pages' => true)));
	pg_assert_true($gates['add_page'], 'add page with create_pages');

	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(3, array('create_pages' => true)));
	pg_assert_false($gates['add_page'], 'create_pages without a folder right');
}

function test_search_chips_role3_rights_open_their_screens()
{
	$cases = array(
		'visitors'      => array('manage_visitors' => true),
		'order_reports' => array('manage_ecommerce_reports' => true),
		'ecommerce'     => array('manage_ecommerce' => true),
		'contacts'      => array('contact_group' => true),
		'campaigns'     => array('manage_emails' => true),
		'calendars'     => array('calendar' => true),
		'forms'         => array('manage_forms' => true),
		'ads'           => array('ad_region' => true),
	);
	foreach ($cases as $gate => $over) {
		pg_assert_same(array($gate, 'help'), panel_search_chips_on(panel_search_chips_rights(3, $over)), $gate);
	}
}

function test_search_chips_follow_module_switches()
{
	$gates = pg_panel_search_chip_gates(panel_search_chips_rights(0, array(
		'ecommerce_module'       => false,
		'calendars_module'       => false,
		'forms_module'           => false,
		'ads_module'             => false,
		'dynamic_regions_module' => false,
		'not_found_log_ready'    => false,
	)));
	foreach (array('ecommerce', 'order_reports', 'calendars', 'forms', 'ads', 'dynamic_regions', 'not_found_log') as $gate) {
		pg_assert_false($gates[$gate], $gate);
	}
	pg_assert_true($gates['manage'], 'manage');
}

function test_search_chips_use_the_gates()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_contains('$gate = pg_panel_search_chip_gates(', $php, 'chips gated by the helper');
	pg_assert_true((bool) preg_match('/if \(\$gate\[\'order_reports\'\]\) \{\s*\$actions\[\] = array\(\'label\' => lang\(\'Order Reports\'\)/', $php), 'order reports under their own right');
	pg_assert_false((bool) preg_match('/if \(\$can_(manage|design|ecommerce|contacts)\) \{\s*\$actions\[\]/', $php), 'no chip under a hand-made gate');
	pg_assert_false((bool) preg_match('/Sayfalar|Dosyalar|e-Ticaret/', $php), 'section comments in English');
}
