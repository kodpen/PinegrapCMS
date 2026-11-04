<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * functions.php does not load includes/fn/contact_list.php, so every screen
 * that calls one of its functions must require it itself: the contact
 * groups list called pg_contact_group_list_order_map() without it and
 * answered HTTP 500.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_contact_list_module_is_not_loaded_by_functions_php()
{
	$functions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');

	pg_assert_false(strpos($functions, "/includes/fn/contact_list.php'") !== false, 'the module is loaded by the screens that need it');
}

function test_contact_list_module_required_by_every_screen_calling_it()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');

	preg_match_all('/^function\s+([a-z0-9_]+)\s*\(/mi', $module, $match);
	$names = $match[1];

	pg_assert_true(in_array('pg_contact_group_list_order_map', $names, true), 'the groups list sort map lives in the module');

	$checked = 0;

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		$source = (string) file_get_contents($file);

		foreach ($names as $name) {
			if (!preg_match('/(?<![a-z0-9_>$])' . preg_quote($name, '/') . '\s*\(/i', $source)) {
				continue;
			}

			$checked++;
			pg_assert_contains("/includes/fn/contact_list.php'", $source, basename($file) . ' calls ' . $name . '() and requires the module');

			break;
		}
	}

	pg_assert_true($checked >= 3, 'the contacts list, the contact editor and the groups list are among the screens checked');
}
