<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Import My Site settings step layout (import_design.php): the
 * settings card in a form column, what an import creates in a sticky status
 * column written after it, the Scan button in the save bar column; the scan
 * step keeps its one full-width result card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function import_design_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_design.php');
}

function test_import_design_screen_has_form_and_status_columns()
{
	$source = import_design_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, "(\$action == '' ? '<div class=\"col-12 order-3\">' : '')");

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the settings step puts its save bar in a third column');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="card my-7 position-sticky" style="top:3.3rem;">', $source, 'the status card sticks below the top bar');

	foreach (array("'name'=>'url'", "'name'=>'import_name'", 'name="max_link_level"') as $field) {
		$at = strpos($source, $field);
		pg_assert_true($at !== false && $at > $form && $at < $status, $field . ' sits in the form column');
	}
}

function test_import_design_screen_status_column_says_what_is_imported()
{
	$source = import_design_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, "\$output_buttons = '<button type=\"submit\" name=\"submit_button\" value=\"Scan\"") - $start);

	pg_assert_contains("lang('What Is Imported')", $status, 'the status card has its title');
	pg_assert_contains('Import My Site', $status, 'it names the folder the import goes to');
	pg_assert_false(strpos($status, '<input') !== false || strpos($status, 'output_field') !== false, 'nothing to fill in the status column');
}

function test_import_design_screen_row_closes_after_the_save_bar()
{
	$source = import_design_screen_layout_source();
	$bar = substr($source, (int) strpos($source, "(\$action == '' ? '<div class=\"col-12 order-3\">' : '')"));

	pg_assert_contains('<nav class="buttons navigation text-center position-sticky mb-7"', $bar, 'the save bar follows the column opening');
	pg_assert_contains("(\$action == '' ? '</div></div>' : '')", $bar, 'the bar column and the settings row close after the bar');
}
