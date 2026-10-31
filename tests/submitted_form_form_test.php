<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the submitted form fields shared by the create and the edit
 * screens (includes/forms/submitted_form_form.php): the type checks of a
 * posted value, and that both screens use the shared functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

/**
 * The liveform calls the field checks make, over a plain array of values.
 */
class pg_test_submitted_form_liveform
{
	public $values = array();
	public $errors = array();

	public function __construct($values)
	{
		$this->values = $values;
	}

	public function get_field_value($name)
	{
		return isset($this->values[$name]) ? $this->values[$name] : '';
	}

	public function check_field_error($name)
	{
		return isset($this->errors[$name]);
	}

	public function mark_error($name, $message)
	{
		$this->errors[$name] = $message;
	}

	public function assign_field_value($name, $value)
	{
		$this->values[$name] = $value;
	}
}

function pg_test_submitted_form_field($id, $type)
{
	return array('id' => $id, 'label' => 'Field ' . $id, 'type' => $type, 'rss_field' => '');
}

function test_submitted_form_check_field_marks_invalid_values()
{
	$liveform = new pg_test_submitted_form_liveform(array(
		'1' => 'not a date',
		'2' => 'not an address',
		'3' => '99:99 XM',
		'4' => 'not a date and time',
	));

	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('1', 'date'), 5, false);
	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('2', 'email address'), 5, false);
	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('3', 'time'), 5, false);
	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('4', 'date and time'), 5, false);

	pg_assert_same(array('1', '2', '3', '4'), array_map('strval', array_keys($liveform->errors)), 'every invalid value is marked');
}

function test_submitted_form_check_field_keeps_valid_and_empty_values()
{
	$liveform = new pg_test_submitted_form_liveform(array(
		'2' => 'someone@example.com',
		'5' => 'any text',
	));

	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('1', 'date'), 5, false);
	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('2', 'email address'), 5, false);
	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('5', 'text box'), 5, false);

	pg_assert_same(array(), $liveform->errors, 'an empty or valid value is no error');
}

function test_submitted_form_check_field_keeps_an_earlier_error()
{
	$liveform = new pg_test_submitted_form_liveform(array('1' => 'not a date'));
	$liveform->errors['1'] = 'Field 1 is required.';

	pg_submitted_form_check_field($liveform, pg_test_submitted_form_field('1', 'date'), 5, false);

	pg_assert_same('Field 1 is required.', $liveform->errors['1'], 'the required error stays');
}

function test_submitted_form_screens_use_shared_fields()
{
	foreach (array('add_submitted_form.php', 'edit_submitted_form.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/submitted_form_form.php', $source, $file . ' loads the shared fields');
		pg_assert_contains('pg_submitted_form_fields_markup(', $source, $file . ' prints the shared fields');
		pg_assert_contains('pg_submitted_form_check_field(', $source, $file . ' checks with the shared rules');
		pg_assert_contains('pg_submitted_form_store_upload(', $source, $file . ' stores uploads the shared way');
		pg_assert_contains('pg_submitted_form_save_value(', $source, $file . ' saves values the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
	}
}

// A submission without a form_data row for a file field: db_item() returns
// null there, so the stored file is read only when a row came back, on the
// edit screen's markup, its replace and its delete path.
function test_submitted_form_file_field_without_a_row()
{
	$shared = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');
	pg_assert_contains("if (\$file && \$file['name'] != '')", $shared);
	pg_assert_false(strpos($shared, "if (\$file['name'] != '')") !== false);

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_submitted_form.php');
	pg_assert_same(2, substr_count($edit, "if (\$file && \$file['id'] != '')"), 'replace and delete');
	pg_assert_false(strpos($edit, "if (\$file['id'] != '')") !== false);
}

function test_submitted_form_marks_files_to_choose_again()
{
	$liveform = new pg_test_submitted_form_liveform(array());
	$liveform->errors['3'] = 'blocked name';

	$fields = array(
		pg_test_submitted_form_field('1', 'file upload'),
		pg_test_submitted_form_field('2', 'file upload'),
		pg_test_submitted_form_field('3', 'file upload'),
		pg_test_submitted_form_field('4', 'text box'),
	);
	$files = array(
		'1' => array('name' => 'contract.pdf'),
		'2' => array('name' => ''),
		'3' => array('name' => 'run.php'),
		'4' => array('name' => 'other.pdf'),
	);

	pg_submitted_form_mark_chosen_files($liveform, $fields, $files);

	pg_assert_same(array('1'), $liveform->get_field_value(PG_SUBMITTED_FORM_CHOOSE_AGAIN), 'only a chosen file without its own error is marked');

	pg_submitted_form_mark_chosen_files($liveform, $fields, array());
	pg_assert_same(array(), $liveform->get_field_value(PG_SUBMITTED_FORM_CHOOSE_AGAIN), 'no file chosen, no mark');
}

function test_submitted_form_screens_mark_chosen_files()
{
	foreach (array('add_submitted_form.php', 'edit_submitted_form.php') as $screen) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		pg_assert_contains('pg_submitted_form_mark_chosen_files(', $source, $screen . ' marks the files to choose again before sending the form back');
	}
}
