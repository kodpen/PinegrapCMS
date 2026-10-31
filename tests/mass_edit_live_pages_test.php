<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for mass_edit.php: the list, the page count, Find & Replace and the
 * save read only live pages (none from the Recycle Bin), and a save the
 * server cut short at max_input_vars is refused before anything is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_mass_edit_live_pages_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/mass_edit.php');
}

function test_mass_edit_reads_no_recycle_bin_page()
{
	$source = _test_mass_edit_live_pages_source();

	pg_assert_contains('$live_pages_where = \' WHERE 1 = 1\' . pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false);', $source, 'one live-pages condition');
	pg_assert_false((bool) preg_match('/FROM page["\'\s]*(\)|;|ORDER)/', $source), 'no query reads every page');
	pg_assert_same(4, substr_count($source, 'FROM page" . $live_pages_where'), 'list, count, Find & Replace and save read live pages');
}

function test_mass_edit_refuses_a_truncated_save()
{
	$source = _test_mass_edit_live_pages_source();

	pg_assert_contains('<input type="hidden" name="max_input_vars_test" value="true">' . "\n" . '                        </form>', $source, 'the marker is the last field of the edit form');
	$check = strpos($source, "if (!isset(\$_POST['max_input_vars_test']))");
	$save  = strpos($source, '$count_change = 0;');
	pg_assert_true($check !== false && $save !== false && $check < $save, 'a cut-short post is refused before the save loop');
}

function test_mass_edit_posts_only_changed_fields()
{
	$source = _test_mass_edit_live_pages_source();

	pg_assert_contains('el.value === el.defaultValue', $source, 'an unchanged text field is left out of the post');
	pg_assert_contains('box.checked === box.defaultChecked', $source, 'an unchanged switch is left out of the post');
}
