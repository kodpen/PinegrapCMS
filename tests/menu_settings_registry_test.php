<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The main menu's System tools row reads pg_settings_tool_groups(), which
 * lives in includes/settings/registry.php. That file is not part of the
 * function modules and refuses to load unless PG_SETTINGS_MENU (or
 * PG_SETTINGS_ENTRY) is defined, so output_menu() has to define the
 * constant and include it before the call. output_menu() reads the
 * database, so its source is checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function menu_settings_registry_output_menu_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$start = strpos($source, 'function output_menu(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_menu_settings_registry_loaded_before_tool_groups()
{
	$body = menu_settings_registry_output_menu_source();
	pg_assert_true($body !== '', 'output_menu() found');

	$call = strpos($body, 'pg_settings_tool_groups($user)');
	pg_assert_true($call !== false, 'output_menu() reads the tool groups');

	$define = strpos($body, "define('PG_SETTINGS_MENU', true)");
	$include = strpos($body, "include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php')");

	pg_assert_true($define !== false && $define < $call, 'PG_SETTINGS_MENU is defined before the call');
	pg_assert_true($include !== false && $include < $call, 'the registry is loaded before the call');
	pg_assert_true($define !== false && $include !== false && $define < $include, 'the constant comes before the include');
}
