<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for copying, deleting and switching page styles: the pages of a
 * design a copy takes (duplicate_style.php), which pages keep a style from
 * being deleted (delete_styles.php, edit_custom_style.php) and what a theme
 * file's activation writes and applies (includes/forms/theme_file_form.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/style_usage.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/theme_file_form.php');

// The bin folders are left out by id; no bin, no condition.
function test_style_switch_not_binned_sql_for()
{
	pg_assert_same(" AND page.page_folder NOT IN (7,9)", pg_designer_not_binned_sql_for(array('7', 9), 'page.page_folder'), 'two bin folders');
	pg_assert_same('', pg_designer_not_binned_sql_for(array(), 'page_folder'), 'no bin');
	pg_assert_same(" AND page_folder NOT IN (3)", pg_designer_not_binned_sql_for(array(3, 0, 3), 'page_folder'), 'zero and repeats dropped');
}

// A copy of a design takes the pages on the site, never the ones in the bin.
function test_style_switch_copy_leaves_binned_pages()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_style.php');

	pg_assert_contains("pg_designer_not_binned_sql('page_folder')", $source);
}

// A page in the bin does not hold its style back from the bin once the bin
// keeps styles; a page's mobile style still does, and so does every page
// while styles are deleted for good.
function test_style_switch_pages_holding_style_sql()
{
	pg_assert_same(
		"((page_style = '12') AND (page_folder NOT IN (4,5))) OR (mobile_style_id = '12')",
		pg_style_pages_holding_sql(12, array(4, 5)),
		'bin keeps styles');

	pg_assert_same(
		"(page_style = '12') OR (mobile_style_id = '12')",
		pg_style_pages_holding_sql('12', array()),
		'no bin folders');
}

function test_style_switch_delete_screens_share_the_rule()
{
	foreach (array('delete_styles.php', 'edit_custom_style.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_style_pages_holding_sql(', $source, $file);
	}
}

// Turning a theme off keeps the styles the pages wear in its own rows, so
// turning it on again later gives them back rather than older ones.
function test_style_switch_theme_activation_changes()
{
	$on = array('desktop' => 1, 'mobile' => 1);
	$off = array('desktop' => 0, 'mobile' => 0);

	pg_assert_same(array('keep' => array(), 'apply' => array('desktop', 'mobile')), pg_theme_activation_changes($off, $on), 'both on');
	pg_assert_same(array('keep' => array('desktop', 'mobile'), 'apply' => array()), pg_theme_activation_changes($on, $off), 'both off');
	pg_assert_same(
		array('keep' => array('desktop'), 'apply' => array('mobile')),
		pg_theme_activation_changes(array('desktop' => '1', 'mobile' => '0'), array('desktop' => '0', 'mobile' => '1')),
		'desktop off, mobile on');
	pg_assert_same(array('keep' => array(), 'apply' => array()), pg_theme_activation_changes($on, $on), 'nothing changed');
	pg_assert_same(array('keep' => array(), 'apply' => array('desktop')), pg_theme_activation_changes(array('desktop' => 0, 'mobile' => 1), $on), 'desktop added');
}
