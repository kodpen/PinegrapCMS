<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The settings-type lists (countries, states, zones, tax zones, currencies,
 * referral sources, contact groups, calendars, event locations, auto
 * dialogs) on the list table driver, printed whole in browser mode.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';

function settings_lists_td11_files()
{
	return array(
		'view_countries' => 'view_countries.php',
		'view_states' => 'view_states.php',
		'view_zones' => 'view_zones.php',
		'view_tax_zones' => 'view_tax_zones.php',
		'view_currencies' => 'view_currencies.php',
		'view_referral_sources' => 'view_referral_sources.php',
		'view_contact_groups' => 'view_contact_groups.php',
		'view_calendars' => 'view_calendars.php',
		'view_calendar_event_locations' => 'view_calendar_event_locations.php',
		'view_auto_dialogs' => 'includes/templates/view_auto_dialogs.php',
	);
}

function test_settings_lists_td11_printed()
{
	foreach (settings_lists_td11_files() as $list => $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . basename($file));

		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list . ' keeps a heading sort');
		pg_assert_false(strpos($source, 'class="chart') !== false, $file . ': no DataTables table left');
		pg_assert_false(strpos($source, 'get_column_heading(') !== false, $file . ': no heading links left');
		pg_assert_contains("'list' => '" . $list . "'", $screen, $file . ': printed under its pg_list_state() name');
		pg_assert_contains("pg_list_state('" . $list . "'", $screen, $file . ': same name as its session sort');
		pg_assert_contains("pg_list_table_search_box('" . $list . "')", $source, $file . ': toolbar search box');
		pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $screen, $file . ': driver loaded');
		// A list with quick edit draws its cells in its includes/fn module.
		$modules = array('view_zones' => 'zone_list_edit.php', 'view_tax_zones' => 'tax_zone_list_edit.php', 'view_currencies' => 'currency_list_edit.php');
		$cells = isset($modules[$list]) ? (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $modules[$list]) : $screen;
		pg_assert_contains('data-pg-open', $cells, $file . ': the name opens the record');
	}
}

function test_settings_lists_td11_columns_without_sort_code()
{
	// Membership and Members have no code in $sort_columns, so the session
	// could not keep a sort by them.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_contact_groups.php');

	pg_assert_contains("'kind' => array('heading' => lang('Membership'), 'kind' => 'badge', 'sortable' => false)", $source);
	pg_assert_contains("'members' => array('heading' => lang('Members'), 'kind' => 'number', 'sortable' => false)", $source);
}

function test_settings_lists_td11_auto_dialog_preview()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_auto_dialogs.php');

	pg_assert_contains('?preview_auto_dialog=', $source, 'the preview link stays');
	pg_assert_contains("'preview' => array('heading' => lang('Preview'), 'kind' => 'actions')", $source);
}
