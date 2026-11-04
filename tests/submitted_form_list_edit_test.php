<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the submitted forms list's quick edit
 * (includes/fn/submitted_form_list_edit.php): every value the submitted
 * form screen changes on the list is a field, the value each field opens
 * with, the required-field check before a form is completed, and the
 * form editor's username.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list_edit.php');

foreach (array('DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $sf_edit_test_name => $sf_edit_test_value) {
	if (!defined($sf_edit_test_name)) {
		define($sf_edit_test_name, $sf_edit_test_value);
	}
}

function pg_test_submitted_form_quick_record(array $over = array())
{
	return array_merge(array(
		'id' => '12', 'complete' => '0', 'form_name' => 'Form', 'reference_code' => 'R1',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => 'sam',
		'form_editor_username' => 'ed', 'submitted_timestamp' => '1700000000',
		'last_modified_username' => 'ed', 'last_modified_timestamp' => '1700000000',
	), $over);
}

// The values the submitted form screen changes are editable columns with a
// cell field; the rest are computed or set when the form was submitted. A
// new column fails here until it is one or the other.
function test_submitted_form_quick_every_screen_value_is_a_field()
{
	$text = array('custom_form', 'reference_code', 'payment', 'contact', 'user', 'submitted', 'last_modified', 'actions');
	$fields = pg_submitted_form_quick_edit_columns();
	$record = pg_test_submitted_form_quick_record(array('order_id' => '0', 'paid_at' => '0'));
	$cells = pg_submitted_form_quick_cells(pg_submitted_form_list_row($record, false), $record, $fields)['cells'];

	pg_assert_same(array('status', 'form_editor'), array_keys($fields));

	foreach (pg_submitted_form_list_columns(true, true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is not changed on the screen');
			continue;
		}

		pg_assert_true(isset($column['editable'], $fields[$key]), $key . ' is a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell carries its field');
		pg_assert_same('12', $cells[$key]['attributes']['data-pg-edit-id'] ?? '', $key . ' cell names its form');
	}

	foreach (pg_submitted_form_list_columns(true, false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

function test_submitted_form_quick_cells_without_edit_have_no_field()
{
	$record = pg_test_submitted_form_quick_record();
	$row = pg_submitted_form_quick_cells(pg_submitted_form_list_row($record, false), $record, array());

	foreach ($row['cells'] as $key => $cell) {
		pg_assert_false(isset($cell['attributes']['data-pg-edit']), $key . ' is text');
	}
}

function test_submitted_form_quick_values()
{
	pg_assert_same('0', pg_submitted_form_quick_value('status', pg_test_submitted_form_quick_record()));
	pg_assert_same('1', pg_submitted_form_quick_value('status', pg_test_submitted_form_quick_record(array('complete' => '1'))));
	pg_assert_same('ed', pg_submitted_form_quick_value('form_editor', pg_test_submitted_form_quick_record()));
	pg_assert_same('', pg_submitted_form_quick_value('form_editor', pg_test_submitted_form_quick_record(array('form_editor_username' => null))));

	$fields = pg_submitted_form_quick_edit_columns();
	pg_assert_same(array('1', '0'), array_map('strval', array_keys($fields['status']['options'])));
	pg_assert_false(pg_list_edit_parse($fields['status'], '2')['ok']);
	pg_assert_same(100, $fields['form_editor']['max']);
}

function test_submitted_form_quick_form_editor_cell()
{
	$record = pg_test_submitted_form_quick_record(array('form_editor_username' => 'a<b'));
	$row = pg_submitted_form_list_row($record, false);

	pg_assert_same('a&lt;b', $row['cells']['form_editor']['html']);
	pg_assert_same('', pg_submitted_form_list_row(pg_test_submitted_form_quick_record(array('form_editor_username' => null)), false)['cells']['form_editor']['html']);
	pg_assert_same(array_keys(pg_submitted_form_list_columns()), array_keys($row['cells']), 'a cell for every column');
}

// Completing a form needs every required field filled, as the screen's
// Complete button asks; information fields hold no value.
function test_submitted_form_quick_missing_required()
{
	$fields = array(
		array('id' => '1', 'label' => 'Name', 'type' => 'text box', 'required' => '1'),
		array('id' => '2', 'label' => 'Note', 'type' => 'text area', 'required' => '0'),
		array('id' => '3', 'label' => 'File', 'type' => 'file upload', 'required' => '1'),
		array('id' => '4', 'label' => 'Intro', 'type' => 'information', 'required' => '1'),
	);

	pg_assert_same('', pg_submitted_form_quick_missing($fields, array('1' => 'Ann', '3' => '9')));
	pg_assert_same(lang(array('string' => '{var:1} is required.', 'vars' => 'Name')), pg_submitted_form_quick_missing($fields, array('1' => ' ', '3' => '9')));
	pg_assert_same(lang(array('string' => '{var:1} is required.', 'vars' => 'File')), pg_submitted_form_quick_missing($fields, array('1' => 'Ann')));

	// A required field without a label still stops the change.
	$unlabelled = array(array('id' => '5', 'label' => '', 'type' => 'text box', 'required' => '1'));
	pg_assert_same(lang('A required field is empty.'), pg_submitted_form_quick_missing($unlabelled, array()));
}

function test_submitted_form_quick_sorts_by_form_editor()
{
	$filter_source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

	pg_assert_contains("'form_editor'    => lang('Form Editor')", $filter_source, 'a sort code');
	pg_assert_contains("'form_editor'        => 'form_editor_user.user_username'", $filter_source, 'what it orders by');
}
