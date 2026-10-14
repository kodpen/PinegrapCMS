<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theme Designer settings gate. The designer edits the rows a
 * theme has in system_theme_css_rules and rewrites the theme's CSS file from
 * them. A file without such rows (a hand-written CSS theme, or not a theme)
 * opened the designer with dozens of warnings, and Save deleted the file and
 * wrote generated CSS in its place. Both the screen and its module endpoint
 * now stop before anything is read or written for such a file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The designer checks for designer settings before its GET and POST branches,
// so neither the screen nor Save, Duplicate or Preview runs without them.
function test_theme_designer_rules_gate_before_both_branches()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/theme_designer.php');

	$gate = strpos($source, "SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '\" . \$id . \"'");
	$get_branch = strpos($source, 'if (!$_POST) {');
	$first_unlink = strpos($source, 'unlink(');

	pg_assert_true($gate !== false, 'theme_designer.php counts the theme\'s designer settings');
	pg_assert_true(($get_branch !== false) && ($gate < $get_branch), 'the gate runs before the GET branch');
	pg_assert_true(($first_unlink !== false) && ($gate < $first_unlink), 'the gate runs before the CSS file is deleted');
}

// A CSS file without designer settings opens in the CSS editor, every other
// file is "not found".
function test_theme_designer_rules_gate_sends_css_to_the_css_editor()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/theme_designer.php');
	$gate = strpos($source, "SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '\" . \$id . \"'");
	$block = substr($source, $gate, 1500);

	pg_assert_contains("/edit_theme_css.php?id=' . \$id", $block, 'CSS editor redirect');
	pg_assert_contains("lang('This theme has no Theme Designer settings, so it opens in the CSS editor.')", $block, 'notice');
	pg_assert_contains("output_error(lang('Record not found.')", $block, 'not found for other files');
}

// The module endpoint stops on "Record not found." before it builds a module
// for a file without designer settings.
function test_theme_designer_rules_gate_on_module_endpoint()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_theme_designer_module.php');

	$gate = strpos($source, "SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '\" . (int) \$file_id . \"'");
	$switch = strpos($source, 'switch($module_id)');

	pg_assert_true($gate !== false, 'get_theme_designer_module.php counts the theme\'s designer settings');
	pg_assert_true(($switch !== false) && ($gate < $switch), 'the gate runs before a module is built');
}
