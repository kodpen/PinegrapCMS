<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The System tools screen: the screens that leave Site Settings
 * (pg_settings_tool_groups()) are drawn there as cards, the dialog's list
 * keeps only the categories plus one link to that screen, and the panel's
 * search palette lists the screen and every tool from the same groups.
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

if (!defined('PG_PANEL_ACTIONS')) {
	define('PG_PANEL_ACTIONS', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/search.php');

function settings_system_tools_admin()
{
	return array('role' => 0);
}

function settings_system_tools_sample_groups()
{
	return array(
		array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
			array('label' => 'Site Log', 'icon' => 'bi-card-list', 'url' => 'view_log.php'),
		)),
		array('label' => 'Jobs', 'icon' => 'bi-tools', 'items' => array(
			array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'),
			array('label' => 'Purge Cache', 'icon' => 'bi-trash3', 'url' => 'purge_cache.php?token=abc', 'confirm' => 'All caches & more.'),
		)),
		array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array()),
	);
}

// The whole dialog reads the categories, which ask the database, so its
// source is checked here and its one tool row through the helper below.
function test_settings_system_tools_modal_has_no_tool_groups()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/modal.php');
	// The headings over the list are the category groups (pg_settings_grouped()),
	// not the tool groups, which stay on the System tools screen.
	pg_assert_same(false, strpos($source, 'pg_settings_tool_groups($user) as'), 'no tool group heading in the list');
	pg_assert_contains('pg_settings_grouped(', $source, 'headings come from the category groups');
	pg_assert_same(false, strpos($source, "\$group['items']"), 'no tool link drawn in the list');
	pg_assert_contains('pg_settings_tools_link(pg_settings_tool_groups($user))', $source, 'one System tools row, from the gated groups');
}

function test_settings_system_tools_modal_link_for_user_with_tools()
{
	$markup = pg_settings_tools_link(pg_settings_tool_groups(settings_system_tools_admin()));
	pg_assert_contains('href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/system_tools.php') . '"', $markup, 'the row links to the System tools screen');
	pg_assert_contains('class="pg-sm-link pg-sm-away"', $markup, 'drawn as a row that leaves the dialog');
	pg_assert_contains('<span>' . h(lang('System tools')) . '</span>', $markup, 'labelled System tools');
}

function test_settings_system_tools_link_only_with_tools()
{
	pg_assert_same('', pg_settings_tools_link(array()), 'no link when there is no tool');
	pg_assert_same('', pg_settings_tools_link(array(array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array()))), 'no link for empty groups');
	pg_assert_contains('system_tools.php', pg_settings_tools_link(settings_system_tools_sample_groups()), 'a link when there is a tool');
}

function test_settings_system_tools_count()
{
	pg_assert_same(0, pg_settings_tool_count(array()), 'nothing');
	pg_assert_same(3, pg_settings_tool_count(settings_system_tools_sample_groups()), 'three links over three groups');
}

function test_settings_system_tools_cards_keep_gate_target_confirm()
{
	$markup = pg_settings_tool_cards(settings_system_tools_sample_groups(), '/pinegrap/');
	pg_assert_same(2, substr_count($markup, 'class="card h-100"'), 'one card per group that has links');
	pg_assert_same(false, strpos($markup, '>Empty<'), 'a group without links is not drawn');
	pg_assert_contains('href="/pinegrap/view_log.php"', $markup, 'link under the software directory');
	pg_assert_contains('target="_blank" rel="noopener"', $markup, 'target kept');
	pg_assert_contains('href="/pinegrap/purge_cache.php?token=abc"', $markup, 'query string kept');
	pg_assert_contains('data-confirm-content="All caches &amp; more."', $markup, 'confirm kept and escaped');
}

function test_settings_system_tools_cards_list_every_admin_tool()
{
	$groups = pg_settings_tool_groups(settings_system_tools_admin());
	$markup = pg_settings_tool_cards($groups, '/pinegrap/');
	$confirms = 0;

	foreach ($groups as $group) {
		foreach ($group['items'] as $item) {
			pg_assert_contains('href="' . h('/pinegrap/' . $item['url']) . '"', $markup, $item['label'] . ' is listed');

			if (isset($item['confirm'])) {
				$confirms++;
				pg_assert_contains('data-confirm-content="' . h($item['confirm']) . '"', $markup, $item['label'] . ' keeps its confirm');
			}
		}
	}

	pg_assert_true($confirms > 0, 'at least one tool asks first (Purge Cache)');
}

function test_settings_system_tools_palette_lists_missing_tools()
{
	$groups = pg_settings_tool_groups(settings_system_tools_admin());
	$actions = pg_panel_search_tool_actions($groups, '/pinegrap', array());
	$urls = array();

	foreach ($actions as $action) {
		pg_assert_true(count($action['keys']) > 0, $action['label'] . ' has search keys');
		$urls[] = $action['url'];
	}

	pg_assert_contains('/pinegrap/system_tools.php', implode(' ', $urls), 'the screen itself is listed');

	foreach (array('api_settings.php', 'database_engine.php', 'mail_queue.php', 'clean_up.php', 'view_sessions.php', 'view_audit_log.php') as $script) {
		pg_assert_true(in_array('/pinegrap/' . $script, $urls, true), $script . ' is listed');
	}

	if (!pg_hosted()) {
		pg_assert_true(in_array('/pinegrap/software_update.php', $urls, true), 'Check for Updates is listed');
	}

	// A tool that asks first opens the screen, where the question is asked,
	// instead of running from the palette.
	foreach ($actions as $action) {
		pg_assert_same(false, strpos($action['url'], 'purge_cache.php'), 'Purge Cache is not run from the palette');
	}
}

function test_settings_system_tools_palette_follows_gates()
{
	$groups = array(
		array('label' => 'Store Lists', 'icon' => 'bi-bag', 'items' => array(
			array('label' => 'All Referral Sources', 'icon' => 'bi-signpost-2', 'url' => 'view_referral_sources.php'),
			array('label' => 'All Currencies', 'icon' => 'bi-coin', 'url' => 'view_currencies.php'),
		)),
		array('label' => 'Connections', 'icon' => 'bi-plug', 'items' => array(
			array('label' => 'Marketplaces', 'icon' => 'bi-buildings', 'url' => 'marketplace_settings.php'),
		)),
	);

	$existing = array(array('label' => 'Currencies', 'icon' => 'bi-coin', 'url' => '/pinegrap/view_currencies.php', 'keys' => array('para')));
	$urls = array_map(function ($a) { return $a['url']; }, pg_panel_search_tool_actions($groups, '/pinegrap', $existing));

	pg_assert_true(in_array('/pinegrap/view_referral_sources.php', $urls, true), 'Referral Sources listed when its group is');
	pg_assert_true(in_array('/pinegrap/marketplace_settings.php', $urls, true), 'Marketplaces listed when its group is');
	pg_assert_same(false, in_array('/pinegrap/view_currencies.php', $urls, true), 'a screen the palette already lists is not repeated');

	pg_assert_same(array(), pg_panel_search_tool_actions(array(), '/pinegrap', array()), 'no tools, not even the screen');

	$admin = pg_settings_tool_groups(array('role' => 0));
	$manager = pg_settings_tool_groups(array('role' => 2));
	$admin_urls = array_map(function ($a) { return $a['url']; }, pg_panel_search_tool_actions($admin, '/pinegrap', array()));
	$manager_urls = array_map(function ($a) { return $a['url']; }, pg_panel_search_tool_actions($manager, '/pinegrap', array()));

	if (!pg_hosted()) {
		pg_assert_true(in_array('/pinegrap/software_repair.php', $admin_urls, true), 'Repair Software for an administrator');
	}
	pg_assert_same(false, in_array('/pinegrap/software_repair.php', $manager_urls, true), 'no Repair Software for a manager');
}

function test_settings_system_tools_page_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/system_tools.php');
	pg_assert_contains("validate_area_access(\$user, 'manager');", $source, 'managers and above');
	pg_assert_contains('pg_settings_tool_count($system_tools_groups) === 0', $source, 'a user with no tool is refused');
	pg_assert_contains('pg_settings_tool_cards(', $source, 'cards drawn by the tested helper');
	pg_assert_contains("'breadcrumb'", $source, 'breadcrumb back to Site Settings');
	pg_assert_same(false, strpos($source, "'help'"), 'its help topic is a row of get_help_url.php');
}

// The main menu's System tools row: slot 25, under the screen's own gate.
function settings_system_tools_menu_case_slot($source, $script)
{
	$start = strpos($source, "case '" . $script . "':");

	if ($start === false) {
		return null;
	}

	preg_match('/\$active_menu = ([^;]+);/', $source, $match, 0, $start);

	return isset($match[1]) ? $match[1] : null;
}

function test_settings_system_tools_menu_row()
{
	$source = pg_test_function_source('output_menu');
	$start = strpos($source, "\$menu_items[25]['id'] = 25;");
	pg_assert_true($start !== false, 'slot 25 is built');

	$gate = strrpos(substr($source, 0, $start), 'if (');
	pg_assert_contains("if (\$user['role'] <= 2 && pg_settings_tool_count(\$menu_tools_groups) > 0)", substr($source, $gate, $start - $gate), 'under system_tools.php\'s own gate');
	pg_assert_contains("\$menu_tools_groups = (\$user['role'] <= 2) ? pg_settings_tool_groups(\$user) : array();", $source, 'from the gated groups');
	pg_assert_contains("\$menu_items[25]['href'] = 'system_tools.php';", $source, 'the row leads to the screen');
	pg_assert_contains("\$menu_items[25]['title'] = 'System tools';", $source, 'titled as the screen');
	pg_assert_contains('$menu_panels_nested .= $menu_tools[\'panels\'];', $source, 'its group panels join the nested ones');

	$tools = array('system_tools.php', 'view_log.php', 'view_audit_log.php', 'smtp_settings.php', 'api_settings.php', 'connections.php', 'marketplace_settings.php', 'software_update.php', 'software_repair.php', 'view_performance_log.php', 'clean_up.php', 'database_engine.php', 'mail_queue.php', 'backups.php', 'cloudflare.php');

	foreach ($tools as $script) {
		pg_assert_same('25', settings_system_tools_menu_case_slot($source, $script), $script . ' marks the System tools row');
	}

	foreach (array('settings_general.php', 'settings_accounting.php', 'settings_privacy.php') as $script) {
		pg_assert_same(null, settings_system_tools_menu_case_slot($source, $script), $script . ' marks no row: the Site Settings row is retired');
	}

	pg_assert_same("(\$user['role'] < 2) ? 21 : 2", settings_system_tools_menu_case_slot($source, 'translations.php'), 'translations.php marks the row that lists it');

	foreach (array('view_referral_sources.php', 'add_currency.php', 'edit_currency.php') as $script) {
		pg_assert_same("(\$user['role'] <= 2) ? 25 : 15", settings_system_tools_menu_case_slot($source, $script), $script . ' marks System tools, or shipping for role 3');
	}

	pg_assert_same(null, settings_system_tools_menu_case_slot($source, 'view_sessions.php'), 'view_sessions.php stays unmarked');
}
