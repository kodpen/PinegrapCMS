<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * functions.php does not load the list table driver
 * (includes/fn/list_table.php), so every screen that calls one of its
 * functions must load it: itself, or through a module it requires that
 * loads it. My Campaign Profiles and Bookings called pg_list_table_printed()
 * and its cell helpers without it and answered HTTP 500.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A file's PHP without its comments, so a function named in a comment is
 * not taken for a call.
 *
 * @param string $source
 * @return string
 */
function pg_test_list_table_code_without_comments($source)
{
	$code = '';

	foreach (token_get_all($source) as $token) {
		if (is_array($token)) {
			if (($token[0] === T_COMMENT) || ($token[0] === T_DOC_COMMENT)) {
				continue;
			}

			$code .= $token[1];
		} else {
			$code .= $token;
		}
	}

	return $code;
}

function test_list_table_module_is_not_loaded_by_functions_php()
{
	$functions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');

	pg_assert_false(strpos($functions, "/includes/fn/list_table.php'") !== false, 'the driver is loaded by the screens that need it');
}

function test_list_table_module_loaded_by_every_screen_calling_it()
{
	$require = "/includes/fn/list_table.php'";

	preg_match_all('/^function\s+([a-z0-9_]+)\s*\(/mi', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php'), $match);
	$names = $match[1];

	pg_assert_true(in_array('pg_list_table_printed', $names, true), 'the printed list lives in the driver');

	$checked = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		$source = (string) file_get_contents($file);
		$code = pg_test_list_table_code_without_comments($source);
		$called = '';

		foreach ($names as $name) {
			if (preg_match('/(?<![a-z0-9_>$:])' . preg_quote($name, '/') . '\s*\(/i', $code)) {
				$called = $name;
				break;
			}
		}

		if ($called === '') {
			continue;
		}

		$checked[] = basename($file);

		if (strpos($source, $require) !== false) {
			continue;
		}

		// A module the screen requires (from includes/) may load the driver
		// for it, at its top or inside the function that builds the list.
		$loaded = false;

		preg_match_all("~(?:require|include)(?:_once)?\s*\(\s*(?:PG_FUNCTIONS_DIR|dirname\(__FILE__\)|__DIR__)\s*\.\s*'(/includes/[^']+\.php)'~", $source, $modules);

		foreach ($modules[1] as $module) {
			if (strpos((string) @file_get_contents(PG_FUNCTIONS_DIR . $module), $require) !== false) {
				$loaded = true;
				break;
			}
		}

		pg_assert_true($loaded, basename($file) . ' calls ' . $called . '() and loads includes/fn/list_table.php');
	}

	pg_assert_true(in_array('view_email_campaign_profiles.php', $checked, true) && in_array('view_bookings.php', $checked, true), 'My Campaign Profiles and Bookings are among the screens checked');
}
