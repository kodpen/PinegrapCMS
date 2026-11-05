<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page experiments screen layout (page_experiments.php): the
 * toolbar, the New experiment form and the experiment cards in a form
 * column, a Summary in a sticky status column written after it, and the
 * form's submit button in a sticky save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_experiments_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/page_experiments.php');
}

function test_page_experiments_has_form_and_status_columns()
{
	$source = page_experiments_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar is there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in that order');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	foreach (array('id="button_bar"', '$output_form', '$output_experiments') as $part) {
		$at = strpos($source, $part, $form);
		pg_assert_true($at !== false && $at < $status, $part . ' sits in the form column');
	}
}

function test_page_experiments_form_fields_are_form_field_boxes()
{
	$source = page_experiments_layout_source();

	foreach (array('name', 'draft_page_id', 'goal_kind', 'goal_target', 'weight_b') as $field) {
		pg_assert_true(
			(bool) preg_match('/<div class="form-field[^"]*"[^>]*>\s*\'\s*\.\s*\$liveform->output_field\(array\(\'type\' => \'[a-z]+\', \'name\' => \'' . $field . '\'/', $source),
			$field . ' sits in a form-field box'
		);
	}

	pg_assert_contains('form="page_experiment_new"', $source, 'the save bar button submits the New experiment form');
	pg_assert_contains('id="page_experiment_new"', $source, 'the New experiment form has its id');
}

function test_page_experiments_status_column_reads_the_summary()
{
	$source = page_experiments_layout_source();
	$status = substr($source, (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">'));

	pg_assert_contains("lang('Summary')", $status, 'the summary card has its title');
	foreach (array('$summary_total', '$summary_running', '$summary_ended') as $count) {
		pg_assert_contains($count, $status, $count . ' is shown');
	}
}
