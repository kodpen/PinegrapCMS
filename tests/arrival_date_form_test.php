<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the arrival date form shared by the create and the edit
 * screens (includes/forms/arrival_date_form.php): the status a posted
 * arrival date is saved with, the values that fill the columns, and that
 * both screens use the shared form and the token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');

function arrival_date_form_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_arrival_date_form_status()
{
	pg_assert_same('enabled', pg_arrival_date_form_status(array('status' => '1')), 'ticked');
	pg_assert_same('disabled', pg_arrival_date_form_status(array()), 'not ticked');
	pg_assert_same('disabled', pg_arrival_date_form_status(array('status' => '0')), 'zero');
	pg_assert_same('disabled', pg_arrival_date_form_status(array('status' => array('1'))), 'a list counts as not ticked');
}

function test_arrival_date_form_values()
{
	$values = pg_arrival_date_form_values(array(
		'name' => 'Anneler Günü',
		'description' => 'Pazar teslim',
		'code' => 'AG',
		'status' => '1',
		'default_selected' => '1',
		'sort_order' => '3',
		'custom' => '',
		'code_list' => array('x'),
	), 7);

	pg_assert_same(12, count($values), 'one value per column');
	pg_assert_same(array('Anneler Günü', 'Pazar teslim', 'AG'), array_slice($values, 0, 3), 'name, description, code');
	pg_assert_same('enabled', $values[4], 'status');
	pg_assert_same(array('1', '3', ''), array_slice($values, 7, 3), 'default selected, sort order, custom');
	pg_assert_same('7', $values[11], 'user');

	foreach ($values as $value) {
		pg_assert_true(is_string($value), 'every value is a string for db_q()');
	}

	pg_assert_same('', pg_arrival_date_form_values(array('name' => array('x')), 1)[0], 'a list posted as the name saves empty');
}

function test_arrival_date_form_screens_use_the_shared_form()
{
	foreach (array('add_arrival_date.php', 'edit_arrival_date.php') as $file) {
		$source = arrival_date_form_test_source($file);

		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');", $source, $file . ': the form');
		pg_assert_contains('pg_arrival_date_form_cards(', $source, $file . ': the cards');
		pg_assert_contains('pg_arrival_date_form_values($_POST, $user[\'id\'])', $source, $file . ': the column values');
		pg_assert_contains('pg_arrival_date_form_save_cutoffs(', $source, $file . ': the cut-offs');
		pg_assert_contains('get_token_field()', $source, $file . ': the token field');
		pg_assert_contains('validate_token_field();', $source, $file . ': the token check');
	}

	// The create screen binds the twelve values to its twelve placeholders.
	$add = arrival_date_form_test_source('add_arrival_date.php');
	$insert = substr($add, strpos($add, 'INSERT INTO arrival_dates'));
	$insert = substr($insert, 0, strpos($insert, 'UNIX_TIMESTAMP())'));

	pg_assert_same(12, substr_count($insert, '?'), 'create: one placeholder per value');

	// The cards close every element they open.
	$form = arrival_date_form_test_source('includes/forms/arrival_date_form.php');

	pg_assert_same(substr_count($form, '<div'), substr_count($form, '</div>'), 'balanced markup');
}
