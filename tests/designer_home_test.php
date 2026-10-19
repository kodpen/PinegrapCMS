<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visual Page Editor home (view_system_styles.php): which
 * design is shown large (pg_designer_home_featured_id()) and how a name
 * typed into the rename box is stored (pg_designer_design_name_clean()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

function _test_designer_home_row($id, $home)
{
	return array('style_id' => $id, 'home_count' => $home);
}

function test_designer_home_featured_is_the_only_design()
{
	pg_assert_same(7, pg_designer_home_featured_id(array(_test_designer_home_row(7, 0))), 'one design, home page or not');
	pg_assert_same(0, pg_designer_home_featured_id(array()), 'no designs');
}

function test_designer_home_featured_is_the_one_with_the_home_page()
{
	$designs = array(_test_designer_home_row(3, 0), _test_designer_home_row(5, 1), _test_designer_home_row(9, 0));
	pg_assert_same(5, pg_designer_home_featured_id($designs), 'the design the home page is in');
}

function test_designer_home_featured_is_none_without_a_single_holder()
{
	pg_assert_same(0, pg_designer_home_featured_id(array(_test_designer_home_row(3, 0), _test_designer_home_row(5, 0))), 'none holds the home page');
	pg_assert_same(0, pg_designer_home_featured_id(array(_test_designer_home_row(3, 1), _test_designer_home_row(5, 2))), 'two hold one');
}

function test_designer_home_name_clean_keeps_one_line()
{
	pg_assert_same('Kafe sitesi', pg_designer_design_name_clean("  Kafe \t\n  sitesi  "), 'spaces, tabs and line breaks become one space');
	pg_assert_same('Yaz İndirimi', pg_designer_design_name_clean("Yaz\x07\x00İndirimi"), 'control characters become a space');
	pg_assert_same('', pg_designer_design_name_clean('   '), 'only spaces');
}

function test_designer_home_name_clean_cuts_at_the_column_size()
{
	$long = str_repeat('ş', 120);
	pg_assert_same(100, mb_strlen(pg_designer_design_name_clean($long), 'UTF-8'), 'one hundred characters, not bytes');
	pg_assert_same('abc', pg_designer_design_name_clean(str_repeat(' ', 5) . 'abc'), 'leading spaces do not count');
}

function test_designer_home_name_clean_refuses_what_is_not_text()
{
	pg_assert_same('', pg_designer_design_name_clean(array('a')), 'an array');
	pg_assert_same('', pg_designer_design_name_clean(null), 'null');
	pg_assert_same('', pg_designer_design_name_clean("\xC3\x28"), 'broken UTF-8');
	pg_assert_same('12', pg_designer_design_name_clean(12), 'a number is its digits');
}
