<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the forms that carry a file beside typed fields (ERP expense
 * receipt, bank statement, ERP accounts import, Add and Edit Submitted
 * Form): the limit they state and enforce is the lower of their own and the
 * server's, an oversize file is refused with that limit named, a body the
 * server threw away is caught and lands on a screen that still knows the
 * record, and a submitted form's attachment that did not arrive is neither
 * copied nor recorded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/expenses.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

function test_form_upload_limits_cap_is_the_lower_limit()
{
	pg_assert_same(2097152, erp_upload_max_bytes(10485760, 2097152), 'the server stops first');
	pg_assert_same(5242880, erp_upload_max_bytes(5242880, 8388608), 'the screen stops first');
	pg_assert_same(10485760, erp_upload_max_bytes(10485760, 0), 'no server limit known');
}

function test_form_upload_limits_mb_text()
{
	pg_assert_same('2', erp_upload_mb_text(2097152), 'whole megabytes');
	pg_assert_same(pg_format_number(0.5, 1), erp_upload_mb_text(524288), 'half a megabyte');
}

function test_form_upload_limits_refusal_names_the_limit()
{
	$server = array('name' => 'receipt.jpg', 'error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0);
	$message = erp_upload_refusal($server, 10485760, 2097152);
	pg_assert_contains('receipt.jpg', $message, 'the file is named');
	pg_assert_contains(convert_bytes_to_string(2097152, 1), $message, 'the server limit is named');

	$screen = array('name' => 'big.csv', 'error' => UPLOAD_ERR_OK, 'tmp_name' => '/tmp/x', 'size' => 6000000);
	pg_assert_contains(convert_bytes_to_string(5242880, 1), erp_upload_refusal($screen, 5242880, 8388608), 'the screen limit is named');

	$fine = array('name' => 'ok.csv', 'error' => UPLOAD_ERR_OK, 'tmp_name' => '/tmp/x', 'size' => 1000);
	pg_assert_same('', erp_upload_refusal($fine, 5242880, 8388608), 'a file under both limits passes');

	$none = array('name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0);
	pg_assert_same('', erp_upload_refusal($none, 5242880, 8388608), 'no file is left to the screen');

	$partial = array('name' => 'half.pdf', 'error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => '', 'size' => 0);
	pg_assert_contains('half.pdf', erp_upload_refusal($partial, 5242880, 8388608), 'a broken upload is refused too');
}

function test_form_upload_limits_expense_attach_names_the_server_limit()
{
	$result = erp_expense_attach(1, array('name' => 'photo.jpg', 'error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0));
	pg_assert_same(false, $result['success'], 'refused');
	pg_assert_contains('photo.jpg', $result['error'], 'the file is named');
}

function test_form_upload_limits_submitted_form_marks_an_upload_error()
{
	$saved = $_FILES;
	$_FILES = array('7' => array('name' => 'scan.pdf', 'type' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0));

	$liveform = new pg_test_form_upload_liveform();
	pg_submitted_form_check_field($liveform, array('id' => '7', 'label' => 'Scan', 'type' => 'file upload', 'rss_field' => ''), 5, false);

	$_FILES = $saved;

	pg_assert_true(isset($liveform->errors['7']), 'the field is marked');
	pg_assert_contains('scan.pdf', $liveform->errors['7'], 'the file is named');
}

function test_form_upload_limits_submitted_form_store_refuses_a_missing_file()
{
	$saved = $_FILES;
	$_FILES = array('7' => array('name' => 'scan.pdf', 'type' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0));

	$file_id = pg_submitted_form_store_upload(array('id' => '7', 'upload_folder_id' => '1'), 1);

	$_FILES = $saved;

	pg_assert_same(0, $file_id, 'nothing is copied or recorded');
}

function test_form_upload_limits_screens_catch_a_discarded_body()
{
	foreach (array('add_erp_expense.php', 'edit_erp_expense.php', 'erp_bank_statements.php', 'erp_accounts_import.php', 'add_submitted_form.php', 'edit_submitted_form.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_post_body_discarded()', $source, $file . ' catches a discarded body');
	}
}

function test_form_upload_limits_record_rides_in_the_action()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_expense.php');
	pg_assert_contains('action="add_erp_expense.php\' . (($id > 0) ? (\'?id=\' . $id) : \'\') . \'"', $add, 'the expense id rides in the action');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');
	pg_assert_contains('$self_action = h(\'edit_erp_expense.php?id=\' . $id', $edit, 'the expense id rides in the action');

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_submitted_form.php');
	pg_assert_contains('action="edit_submitted_form.php?id=', $form, 'the submitted form id rides in the action');

	$new = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_submitted_form.php');
	pg_assert_contains('action="add_submitted_form.php?page_id=', $new, 'the custom form rides in the action');
}

function test_form_upload_limits_accounts_import_box_takes_csv()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_accounts_import.php');
	pg_assert_contains("'accept' => '.csv,.txt,text/csv'", $source, 'the picker offers what the server takes');
	pg_assert_contains('erp_upload_max_bytes(ERP_IMPORT_MAX_BYTES)', $source, 'the stated limit is the real one');
}

/**
 * The liveform calls the field check makes.
 */
class pg_test_form_upload_liveform
{
	public $errors = array();

	public function get_field_value($name)
	{
		return '';
	}

	public function check_field_error($name)
	{
		return isset($this->errors[$name]);
	}

	public function mark_error($name, $message)
	{
		$this->errors[$name] = $message;
	}
}
