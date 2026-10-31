<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the links a calendar view, calendar event view or form view
 * directory page keeps outside its own table (includes/forms/page_form.php):
 * a save changes only the links its screen offered, so a calendar or form
 * list view the editor cannot see stays linked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

function test_page_form_link_changes_keeps_what_was_not_offered()
{
	$changes = pg_page_form_link_changes(array(1, 2, 3), array(2, 3, 4), array(3, 4));

	pg_assert_same(array('2'), $changes['remove'], 'only an offered, unticked link goes');
	pg_assert_same(array('4'), $changes['add']);
	pg_assert_same(array('3'), $changes['update']);
}

function test_page_form_link_changes_ignores_a_tick_not_offered()
{
	$changes = pg_page_form_link_changes(array('5'), array(), array('5', '6'));

	pg_assert_same(array(), $changes['remove'], 'nothing offered, nothing removed');
	pg_assert_same(array(), $changes['add'], 'a posted box the screen did not draw adds nothing');
	pg_assert_same(array(), $changes['update']);
}

function test_page_form_link_changes_new_page()
{
	$changes = pg_page_form_link_changes(array(), array('7', '8'), array('8'));

	pg_assert_same(array(), $changes['remove']);
	pg_assert_same(array('8'), $changes['add']);
	pg_assert_same(array(), $changes['update']);
}

// The save no longer empties the link tables of the page, and the drawing and
// the save read the offered form list views from the same helper.
function test_page_form_save_type_links_has_no_blanket_delete()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

	pg_assert_false((bool) preg_match('/DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = \S+"\)/', $source), 'form view directory links are not deleted wholesale');
	pg_assert_false((bool) preg_match('/DELETE FROM \$table WHERE page_id = \S+"\)/', $source), 'calendar links are not deleted wholesale');
	pg_assert_same(2, substr_count($source, '= pg_page_form_offered_form_list_views($user)') + substr_count($source, 'foreach (pg_page_form_offered_form_list_views($user)'), 'drawing and save share the offered list views');
}
