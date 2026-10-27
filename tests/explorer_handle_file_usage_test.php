<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's "Where used" sub-action handler
 * (includes/explorer/handle_file_usage.php): the handler lives there, behind
 * the gate, pg_explorer_handle() calls it, and the settings that name the
 * file outright are matched whole.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// The handler is loaded by the explorer's functions and called from
// pg_explorer_handle() with the folders the person may see.
function test_explorer_file_usage_handler_lives_in_its_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');

	pg_assert_contains('function pg_explorer_handle_file_usage(', $handler, 'handler');
	pg_assert_true(function_exists('pg_explorer_handle_file_usage'), 'handler loaded');
	pg_assert_contains("case 'explorer_file_usage':\n            pg_explorer_handle_file_usage(\$type, \$request, \$user, \$folders_that_user_has_access_to);\n            break;", $explorer, 'called by its case');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, 'gate');
	pg_assert_false(strpos($handler, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');", $explorer, 'loaded by the explorer');
	pg_assert_contains('pg_explorer_folder_visible($usage_file[\'folder\'], $folders_that_user_has_access_to) == false', $handler, 'the folder check is kept');
}

// A setting counts only when it holds the file's name whole, and only for
// the columns the database has.
function test_explorer_file_usage_setting_hits()
{
	$labels = array('og_default_image' => 'Share', 'organization_logo' => 'Logo', 'app_icon' => 'Icon');
	$config = array('og_default_image' => 'logo.png', 'organization_logo' => 'big-logo.png', 'app_icon' => 'logo.png');

	pg_assert_same(
		array(
			array('name' => 'Share', 'note' => '', 'url' => ''),
			array('name' => 'Icon', 'note' => '', 'url' => '')),
		pg_explorer_file_usage_setting_hits($config, array('og_default_image', 'organization_logo', 'app_icon'), $labels, 'logo.png'),
		'whole matches only');

	pg_assert_same(
		array(array('name' => 'Icon', 'note' => '', 'url' => '')),
		pg_explorer_file_usage_setting_hits($config, array('app_icon'), $labels, 'logo.png'),
		'only the columns asked');

	pg_assert_same(array(), pg_explorer_file_usage_setting_hits($config, $labels ? array_keys($labels) : array(), $labels, 'other.png'), 'no match');
}
