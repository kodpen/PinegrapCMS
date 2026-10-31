<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins: a list screen's title and heading, the breadcrumbs of its
 * child screens and its menu entry name the list the same way, and the
 * referral source screens spell their title right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_names_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Each list: its one name, the files that must say it and the old names
// that must be gone from them.
function list_names_test_cases()
{
	return array(
		'Short Links' => array(
			'files' => array('view_short_links.php', 'add_short_link.php', 'edit_short_link.php', 'import_short_links.php'),
			'old' => array("lang('My Short Links')"),
		),
		'Forms' => array(
			'files' => array('view_submitted_forms.php', 'add_submitted_form.php', 'edit_submitted_form.php', 'import_submitted_forms.php'),
			'old' => array("lang('My Submitted Forms')"),
		),
		'All Design Files' => array(
			'files' => array('view_design_files.php', 'add_design_file.php', 'edit_design_file.php', 'edit_javascript.php', 'editor_edit_file.php', 'edit_theme_css.php'),
			'old' => array("lang('Design Files')"),
		),
		'All Commission Profiles' => array(
			'files' => array('view_recurring_commission_profiles.php', 'edit_recurring_commission_profile.php'),
			'old' => array("lang('All Recurring Commission Profiles')"),
		),
		'All Currencies' => array(
			'files' => array('view_currencies.php', 'add_currency.php', 'edit_currency.php'),
			'old' => array("lang('Currencies')"),
		),
	);
}

function test_list_names_child_screens_use_the_list_name()
{
	foreach (list_names_test_cases() as $name => $case) {
		foreach ($case['files'] as $file) {
			$source = list_names_test_source($file);
			pg_assert_contains("lang('" . $name . "')", $source, $file . " names the list '" . $name . "'");
			foreach ($case['old'] as $old) {
				pg_assert_false(strpos($source, $old) !== false, $file . ' no longer says ' . $old);
			}
		}
	}
}

function test_list_names_list_heading_matches_its_name()
{
	$headings = array(
		'view_short_links.php' => 'Short Links',
		'view_submitted_forms.php' => 'Forms',
		'view_design_files.php' => 'All Design Files',
		'view_recurring_commission_profiles.php' => 'All Commission Profiles',
		'view_currencies.php' => 'All Currencies',
	);
	foreach ($headings as $file => $name) {
		$source = list_names_test_source($file);
		pg_assert_true((bool) preg_match("/'title'\s*=>\s*lang\('" . preg_quote($name, '/') . "'\)/", $source), $file . ' title');
		pg_assert_true((bool) preg_match("/'heading'\s*=>\s*lang\('" . preg_quote($name, '/') . "'\)/", $source), $file . ' heading');
	}
}

function test_list_names_referral_source_screens_spelled_right()
{
	$screens = array('add_referral_source.php' => 'Create Referral Source', 'edit_referral_source.php' => 'Edit Referral Source');
	foreach ($screens as $file => $title) {
		$source = list_names_test_source($file);
		pg_assert_false(strpos($source, 'Refferal') !== false, $file . ' has no misspelled Refferal');
		pg_assert_same(3, substr_count($source, "lang('" . $title . "')"), $file . ' title, heading and breadcrumb');
	}
}
