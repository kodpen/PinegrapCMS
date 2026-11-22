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

	// The shared save binds the twelve values to its twelve placeholders.
	$save = arrival_date_form_test_source('includes/fn/store_lookups.php');
	$insert = substr($save, strpos($save, 'INSERT INTO arrival_dates'));
	$insert = substr($insert, 0, strpos($insert, 'UNIX_TIMESTAMP())'));

	pg_assert_same(12, substr_count($insert, '?'), 'create: one placeholder per value');

	foreach (array('add_arrival_date.php', 'edit_arrival_date.php') as $file) {
		$source = arrival_date_form_test_source($file);
		pg_assert_contains('pg_store_arrival_date_save(', $source, $file . ': the shared save');
		pg_assert_false(strpos($source, 'arrival_dates SET') !== false || strpos($source, 'INSERT INTO arrival_dates') !== false, $file . ': no SQL of its own for the row');
	}

	// The cards close every element they open.
	$form = arrival_date_form_test_source('includes/forms/arrival_date_form.php');

	pg_assert_same(substr_count($form, '<div'), substr_count($form, '</div>'), 'balanced markup');
}

// A date typed in the site's order.
function arrival_date_form_test_date($day, $month, $year)
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	return (DATE_FORMAT == 'month_day') ? $month . '/' . $day . '/' . $year : $day . '/' . $month . '/' . $year;
}

function test_arrival_date_form_errors()
{
	$valid = array(
		'name' => 'Anneler Günü',
		'start_date' => arrival_date_form_test_date(2, 11, 2026),
		'end_date' => arrival_date_form_test_date(31, 12, 2026),
		'arrival_date' => arrival_date_form_test_date(24, 12, 2026),
		'sort_order' => '3',
	);

	pg_assert_same(array(), pg_arrival_date_form_errors($valid), 'a valid arrival date');

	$errors = pg_arrival_date_form_errors(array('name' => ' ', 'start_date' => arrival_date_form_test_date(31, 2, 2027), 'arrival_date' => 'abc', 'sort_order' => '12,5') + $valid);
	pg_assert_same(array('name', 'start_date', 'arrival_date', 'sort_order'), array_keys($errors), 'the blank name, the impossible start, the text arrival date and the decimal sort order');

	$errors = pg_arrival_date_form_errors(array('end_date' => arrival_date_form_test_date(1, 11, 2026)) + $valid);
	pg_assert_same(array('end_date'), array_keys($errors), 'an end before the start');

	$custom = array('custom' => '1', 'arrival_date' => '', 'custom_maximum_arrival_date' => '') + $valid;
	pg_assert_same(array(), pg_arrival_date_form_errors($custom), 'a customer-entered date needs no arrival date');
	pg_assert_same(array('custom_maximum_arrival_date'), array_keys(pg_arrival_date_form_errors(array('custom_maximum_arrival_date' => 'abc') + $custom)), 'but its latest date is a real one when given');
	// A blank arrival date is the starter sites' AT-ONCE choice, which the
	// shipping methods read as no date (as soon as possible).
	pg_assert_same(array(), pg_arrival_date_form_errors(array('arrival_date' => '') + $valid), 'a blank arrival date is as soon as possible');
	pg_assert_same(array(), pg_arrival_date_form_errors(array('code' => 'AT-ONCE', 'arrival_date' => '0000-00-00') + $valid), 'and so is the stored unset date posted back');
	pg_assert_same(array('arrival_date'), array_keys(pg_arrival_date_form_errors(array('arrival_date' => 'soon') + $valid)), 'one given is a real date');

	foreach (array('add_arrival_date.php', 'edit_arrival_date.php') as $file) {
		$source = arrival_date_form_test_source($file);
		pg_assert_true(strpos($source, 'pg_arrival_date_form_errors($_POST)') < strpos($source, 'pg_arrival_date_form_values($_POST'), $file . ': checked before the write');
	}

	$edit = arrival_date_form_test_source('edit_arrival_date.php');
	pg_assert_true(strpos($edit, 'pg_arrival_date_form_errors($_POST)') < strpos($edit, 'pg_store_arrival_date_delete('), 'the cut-offs stay while a field is wrong');
	pg_assert_true(strpos($edit, 'pg_arrival_date_form_errors($_POST)') < strpos($edit, 'pg_arrival_date_form_save_cutoffs('), 'and are replaced only after the checks');
}

function test_arrival_date_form_sort_order_range()
{
	$valid = array(
		'name' => 'Anneler Günü',
		'start_date' => arrival_date_form_test_date(2, 11, 2026),
		'end_date' => arrival_date_form_test_date(31, 12, 2026),
		'arrival_date' => arrival_date_form_test_date(24, 12, 2026),
	);

	foreach (array('1000', '32767', '-32768', '-100') as $sort_order) {
		pg_assert_same(array(), pg_arrival_date_form_errors(array('sort_order' => $sort_order) + $valid), $sort_order . ' fits the smallint column');
	}

	foreach (array('32768', '-32769') as $sort_order) {
		pg_assert_same(array('sort_order'), array_keys(pg_arrival_date_form_errors(array('sort_order' => $sort_order) + $valid)), $sort_order . ' does not');
	}

	// The box holds every value the server takes, so a stored 1000 is not
	// shown, and saved back, as 100.
	$form = arrival_date_form_test_source('includes/forms/arrival_date_form.php');
	pg_assert_contains('name="sort_order" id="sort_order" maxlength="6" inputmode="numeric" data-inputmask-alias="integer" data-inputmask-min="-32768" data-inputmask-max="32767"', $form, 'the sort order box');
}

function test_arrival_date_form_required_dates()
{
	$form = arrival_date_form_test_source('includes/forms/arrival_date_form.php');

	pg_assert_contains('id="start_date" class="form-control" maxlength="10" autocomplete="off" required data-pg-date', $form, 'the start date is required');
	pg_assert_contains('id="end_date" class="form-control" maxlength="10" autocomplete="off" required data-pg-date', $form, 'the end date is required');
	// Blank is as soon as possible: the box is never required, and says so.
	pg_assert_contains('id="arrival_date" class="form-control" maxlength="10" autocomplete="off" aria-describedby="arrival_date_help" data-pg-date', $form, 'the arrival date may be left blank');
	pg_assert_contains('lang(\'Leave blank to deliver as soon as possible.\')', $form, 'and its help says what blank means');
	pg_assert_same(false, strpos($form, 'arrival_date_required'), 'no switch moves a required attribute');
}

function test_arrival_date_form_blank_arrival_date_saves_the_unset_date()
{
	$values = pg_arrival_date_form_values(array('name' => 'En kısa sürede', 'code' => 'AT-ONCE', 'arrival_date' => ''), 1);

	pg_assert_same('0000-00-00', $values[3], 'a blank arrival date is stored as the unset date the shipping methods read as at once');
}

function test_arrival_date_form_error_text()
{
	pg_assert_same(
		'Please enter a valid date for Start Date. Please enter a valid Name.',
		pg_arrival_date_form_error_text(array('start_date' => 'Please enter a valid date for Start Date', 'name' => 'Please enter a valid Name.')),
		'every message ends in one full stop'
	);
	pg_assert_same('A &lt;b&gt;.', pg_arrival_date_form_error_text(array('x' => 'A <b>')), 'escaped');
	pg_assert_same('', pg_arrival_date_form_error_text(array()), 'none');

	foreach (array('add_arrival_date.php', 'edit_arrival_date.php') as $file) {
		pg_assert_contains('output_error(pg_arrival_date_form_error_text($errors)', arrival_date_form_test_source($file), $file . ': the error screen');
	}
}

function test_arrival_date_form_date_text()
{
	pg_assert_same('', pg_arrival_date_form_date_text('0000-00-00'), 'an unset date is empty, not 0/0/0000');
	pg_assert_same('', pg_arrival_date_form_date_text(''), 'blank');
	pg_assert_same('', pg_arrival_date_form_date_text(null), 'null');
	pg_assert_true(pg_arrival_date_form_date_text('2026-12-24') !== '', 'a real date is written');
	pg_assert_contains('2026', pg_arrival_date_form_date_text('2026-12-24'), 'with its year');
	pg_assert_contains('pg_arrival_date_form_date_text($row[\'arrival_date\'])', arrival_date_form_test_source('includes/fn/arrival_date_list_edit.php'), 'the list uses it');
}

// The cut-off rows backend.src.js adds carry their names: the select and
// the date box had none, and the delete button read its icon name.
function test_arrival_date_cutoff_row_names()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($source, 'function create_shipping_cutoff(');
	$body = substr($source, $start, 4000);

	pg_assert_contains('aria-label="\' + prepare_content_for_html(lang(\'Shipping Method\'))', $body, 'the method select');
	pg_assert_contains('aria-label="\' + prepare_content_for_html(lang(\'Cut-off Date & Time\'))', $body, 'the date and time box');
	pg_assert_contains('title="\' + prepare_content_for_html(lang(\'Delete\'))', $body, 'the delete button');
	pg_assert_contains('<span class="material-icons" aria-hidden="true">delete</span>', $body, 'its icon is hidden from screen readers');
}

// A cut-off row deleted from the table posts no field: it is not a row
// left out, so the save lands on the list without the notice. The rows
// are read by their field names, so the posted last row number, a list
// or a huge number included, never decides how many are read.
function test_arrival_date_form_cutoffs_read_the_posted_rows()
{
	$post = array(
		'start_date' => '2026-12-01',
		'end_date' => '2026-12-20',
		'last_shipping_cutoff_number' => '3',
		'shipping_cutoff_1_shipping_method_id' => '2',
		'shipping_cutoff_1_date_and_time' => '2026-12-10T17:30',
		'shipping_cutoff_3_shipping_method_id' => '5',
		'shipping_cutoff_3_date_and_time' => '2026-12-11T09:00',
	);
	$kept = array(array(2, '2026-12-10 17:30'), array(5, '2026-12-11 09:00'));

	pg_assert_same(array(1, 3), pg_arrival_date_form_cutoff_numbers($post), 'the rows the table posted, row 2 deleted');
	pg_assert_same(array($kept, false), pg_arrival_date_form_cutoffs($post), 'the deleted row is not left out');
	pg_assert_same(array(array(), false), pg_arrival_date_form_cutoffs(array('start_date' => '2026-12-01', 'end_date' => '2026-12-20', 'last_shipping_cutoff_number' => '1')), 'a row added and deleted again');

	// A row the table posted blank, or with a method used twice, is still
	// left out with the notice.
	$blank = $post + array('shipping_cutoff_4_shipping_method_id' => '', 'shipping_cutoff_4_date_and_time' => '');
	pg_assert_same(array($kept, true), pg_arrival_date_form_cutoffs(array('last_shipping_cutoff_number' => '4') + $blank), 'a blank row');
	$twice = $post + array('shipping_cutoff_4_shipping_method_id' => '2', 'shipping_cutoff_4_date_and_time' => '2026-12-12T09:00');
	pg_assert_same(array($kept, true), pg_arrival_date_form_cutoffs(array('last_shipping_cutoff_number' => '4') + $twice), 'a method used twice');

	// The rows go in table order whatever order the fields arrive in.
	pg_assert_same(array(2, 10), pg_arrival_date_form_cutoff_numbers(array('shipping_cutoff_10_date_and_time' => '', 'shipping_cutoff_2_shipping_method_id' => '', 'shipping_cutoff_10_shipping_method_id' => '')), 'by number');

	// Only the names the table writes count as rows.
	pg_assert_same(array(), pg_arrival_date_form_cutoff_numbers(array('shipping_cutoff_0_shipping_method_id' => '2', 'shipping_cutoff_01_date_and_time' => '', 'shipping_cutoff_x_shipping_method_id' => '', 'shipping_cutoff_1_note' => '', 0 => 'x')), 'not a row name');

	// The last row number posted as a list or as a huge number read the
	// endless loop $i <= array() and a billion empty rows before.
	foreach (array(array('1'), '999999999', 'x') as $last) {
		pg_assert_same(array($kept, false), pg_arrival_date_form_cutoffs(array('last_shipping_cutoff_number' => $last) + $post), 'last row number ' . json_encode($last));
	}

	$form = arrival_date_form_test_source('includes/forms/arrival_date_form.php');
	$start = strpos($form, 'function pg_arrival_date_form_save_cutoffs(');
	$save = substr($form, $start, 600);
	pg_assert_contains('pg_arrival_date_form_cutoffs($post)', $save, 'the screens save the rows read here');
	pg_assert_same(false, strpos($save, 'last_shipping_cutoff_number'), 'and never loop to the posted last number');
}

// The posted select of a combobox stays 1px wide whatever its inline
// style: width:100% pushed a 375px page 2px sideways.
function test_combobox_native_select_size()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$rule = substr($css, strpos($css, '.pg-combobox-native {'), 200);

	pg_assert_contains('width: 1px !important;', $rule, 'width');
	pg_assert_contains('height: 1px !important;', $rule, 'height');
}

// The screens use the wide layout: a form column, a sticky status column
// with the availability switches and a Summary, a sticky save bar.
function test_arrival_date_form_wide_layout()
{
	$form = arrival_date_form_test_source('includes/forms/arrival_date_form.php');
	$start = strpos($form, 'function pg_arrival_date_form_cards(');
	$cards = substr($form, $start, strpos($form, 'function pg_arrival_date_form_status_row(') - $start);

	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $cards, 'the form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $cards, 'the status column');
	pg_assert_contains('position-sticky" style="top:3.3rem;"', $cards, 'its sticky box below the top bar');
	pg_assert_true(strpos($cards, 'order-1') < strpos($cards, 'order-2'), 'the status column follows the form column in the source');
	pg_assert_true(strpos($cards, 'id="status"') > strpos($cards, 'order-2'), 'the enabled switch sits in the status column');
	pg_assert_true(strpos($cards, 'id="default_selected"') > strpos($cards, 'order-2'), 'and the default switch');

	foreach (array('custom_maximum_arrival_date_row', 'shipping_cutoff_heading_row', 'shipping_cutoff_row', 'shipping_cutoff_table', 'last_shipping_cutoff_number') as $id) {
		pg_assert_contains($id, $cards . arrival_date_form_test_source('add_arrival_date.php'), 'the element ' . $id . ' the cut-off script reads');
	}

	pg_assert_contains("lang('Chosen by the customer')", $cards, 'a custom arrival date reads as chosen by the customer');
	pg_assert_contains("lang('As soon as possible')", $cards, 'a blank one reads as as soon as possible');
	pg_assert_contains('MutationObserver', $cards, 'the cut-off count follows the rows');

	$row = pg_arrival_date_form_status_row('A & B', '<b>1</b>');
	pg_assert_contains('<dt class="fw-normal">A &amp; B</dt>', $row, 'the label is escaped');
	pg_assert_contains('<b>1</b></dd>', $row, 'the value is HTML');
	pg_assert_false(strpos($row, 'border-bottom') !== false, 'the last row draws no line');
	pg_assert_contains('border-bottom', pg_arrival_date_form_status_row('A', '1', false), 'another row does');

	foreach (array('add_arrival_date.php', 'edit_arrival_date.php') as $file) {
		$source = arrival_date_form_test_source($file);
		pg_assert_contains('pg_arrival_date_form_layout(pg_arrival_date_form_cards(', $source, $file . ': the layout');
	}

	$edit = arrival_date_form_test_source('edit_arrival_date.php');
	pg_assert_contains("lang('Last Modified')", $edit, 'edit: the last change in the Summary');
	pg_assert_contains('value="Delete" formnovalidate', $edit, 'edit: an emptied name does not block Delete');
}
