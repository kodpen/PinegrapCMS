<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the layout of the Mass Edit screen (mass_edit.php): form
 * column, sticky status column with the Summary and Find & Replace, sticky
 * save bar whose Apply Changes names the edit form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_mass_edit_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/mass_edit.php');
}

function test_mass_edit_screen_uses_the_wide_layout()
{
	$source = _test_mass_edit_layout_source();
	$form = strpos($source, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status = strpos($source, 'col-12 lg:col-4 2xl:col-3 order-2');
	$bar = strpos($source, 'col-12 order-3 position-sticky');

	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar');
	pg_assert_true($form < $status && $status < $bar, 'the status column follows the form column, so a phone shows the fields first');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');
	pg_assert_true(strpos($source, "' . \$summary_card . '\n                        ' . \$output_find_replace . '") !== false, 'the status column holds the Summary and Find & Replace');
}

function test_mass_edit_save_bar_submits_the_edit_form()
{
	$source = _test_mass_edit_layout_source();

	pg_assert_contains('<form name="form" id="mass_edit_form"', $source, 'the edit form keeps its name for the submit script');
	pg_assert_contains('<button type="submit" form="mass_edit_form" value="Save Changes"', $source, 'Apply Changes sits outside the form and names it');
	pg_assert_same(1, substr_count($source, 'class="buttons navigation'), 'one save bar for both steps');
	pg_assert_false(strpos($source, 'col-12 lg:col-6') !== false, 'page cards no longer pair at lg inside the narrower form column');
}

function test_mass_edit_find_replace_form_is_outside_the_edit_form()
{
	$source = _test_mass_edit_layout_source();
	$edit_open = strpos($source, '<form name="form" id="mass_edit_form"');
	$edit_close = strpos($source, '</form>', (int) $edit_open);
	$summary = strpos($source, "' . \$summary_card . '");

	pg_assert_true($edit_close < $summary, 'the edit form closes before the status column, so Find & Replace is not a nested form');
}
