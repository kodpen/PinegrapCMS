<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the key code form shared by add_key_code.php and
 * edit_key_code.php (includes/forms/key_code_form.php): the checks of a
 * posted key code (an offer's own code included), the values a save
 * writes, the generated code and both screens on the form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/key_code_form.php');

/**
 * Runs the shared checks on posted fields with stubbed lookups and returns
 * the error message of each field that got one.
 *
 * @param array $fields   posted field values
 * @param mixed $id       null on create, the record id on edit
 * @param array $taken    'key_codes' and 'offers' => codes already there
 * @param array $calls    receives each lookup as table, code, except id
 */
function pg_test_key_code_form_errors($fields, $id, $taken, &$calls = array())
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$liveform = new liveform('key_code_form_test');

	foreach ($fields as $name => $value) {
		$liveform->set($name, $value);
	}

	$calls = array();

	pg_key_code_form_validate($liveform, $id, function ($table, $code, $except_id) use ($taken, &$calls) {
		$calls[] = array($table, $code, $except_id);

		return in_array($code, isset($taken[$table]) ? $taken[$table] : array(), true);
	});

	$errors = array();

	foreach (array('code', 'offer_code', 'expiration_date') as $name) {
		if ($liveform->check_field_error($name)) {
			$errors[$name] = $liveform->get($name, 'error_message');
		}
	}

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	return $errors;
}

function test_key_code_form_refuses_an_offers_own_code()
{
	$errors = pg_test_key_code_form_errors(
		array('code' => 'SUMMER', 'offer_code' => 'SUMMER10'),
		null,
		array('offers' => array('SUMMER', 'SUMMER10')));

	pg_assert_same(array('code'), array_keys($errors), 'only the key code is refused');
	pg_assert_contains('offer', $errors['code'], 'the message names the offer clash');

	$errors = pg_test_key_code_form_errors(
		array('code' => 'SUMMER', 'offer_code' => 'SUMMER10'),
		'7',
		array('offers' => array('SUMMER', 'SUMMER10')));

	pg_assert_same(array('code'), array_keys($errors), 'edit refuses it too');
}

function test_key_code_form_checks_key_code_in_use_and_offer()
{
	$calls = array();
	$errors = pg_test_key_code_form_errors(
		array('code' => 'ABC', 'offer_code' => 'NONE'),
		'7',
		array('key_codes' => array('ABC')),
		$calls);

	pg_assert_same(array('code', 'offer_code'), array_keys($errors), 'code in use, no such offer');
	pg_assert_same(array('key_codes', 'ABC', '7'), $calls[0], 'edit leaves its own record out');

	$errors = pg_test_key_code_form_errors(
		array('code' => 'ABC', 'offer_code' => 'SUMMER10'),
		null,
		array('offers' => array('SUMMER10')),
		$calls);

	pg_assert_same(array(), $errors, 'a free code for an existing offer');
	pg_assert_same(array('key_codes', 'ABC', null), $calls[0], 'create leaves no record out');
}

function test_key_code_form_empty_fields()
{
	$calls = array();
	$errors = pg_test_key_code_form_errors(array('code' => '', 'offer_code' => ''), null, array(), $calls);

	pg_assert_same(array('offer_code'), array_keys($errors), 'create: the code may be left for the generator');
	pg_assert_same(array(), $calls, 'nothing to look up');
}

function test_key_code_form_values_keep_the_insert_and_update_order()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$liveform = new liveform('key_code_form_test');
	$liveform->set('code', 'TYPED');
	$liveform->set('offer_code', 'SUMMER10');
	$liveform->set('enabled', 1);
	$liveform->set('expiration_date', '');
	$liveform->set('notes', 'For the fair');
	$liveform->set('report', 'key_code');

	pg_assert_same(
		array('ABC123', 'SUMMER10', '1', '', 'For the fair', '', 'key_code'),
		pg_key_code_form_values($liveform, 'ABC123'),
		'code, offer code, enabled, expiration date, notes, single use, report');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// Enabled is written only when the box was changed on the screen: a
// completed order disables a used single-use code while the screen is open.
function test_key_code_form_enabled_kept_unless_changed()
{
	$saved = isset($_SESSION) ? $_SESSION : null;

	$cases = array(
		// posted box, opened value, stored now, expected write
		array('1', '1', '0', '0', 'untouched box keeps a code disabled by an order'),
		array('1', '1', '1', '1', 'untouched box keeps the stored value'),
		array('', '0', '1', '1', 'untouched cleared box keeps the stored value'),
		array('', '1', '0', '', 'cleared box is written'),
		array('1', '0', '0', '1', 'ticked box is written'),
		array('1', null, '0', '1', 'a form without the opened value writes as before'),
	);

	foreach ($cases as $case) {
		$_SESSION = array();
		$liveform = new liveform('key_code_form_test');
		$liveform->set('enabled', $case[0]);

		if ($case[1] !== null) {
			$liveform->set('enabled_was', $case[1]);
		}

		$values = pg_key_code_form_values($liveform, 'ABC123', $case[2]);
		pg_assert_same($case[3], $values[2], $case[4]);
	}

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_key_code.php');
	pg_assert_contains("assign_field_value('enabled_was'", $source, 'edit screen keeps the opened Enabled value');
	pg_assert_contains("pg_key_code_form_values(\$liveform, \$liveform->get_field_value('code'), \$key_code['enabled'])", $source, 'edit screen passes the stored Enabled value');
	pg_assert_contains("'name' => 'enabled_was'", file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_key_code.php'), 'template prints the opened Enabled value');
}

function test_key_code_form_generate_skips_codes_in_use()
{
	$seen = array();
	$code = pg_key_code_form_generate(function ($table, $code, $except_id) use (&$seen) {
		$seen[] = $table;

		// The first code is a key code's, the second an offer's own code.
		return (count($seen) == 1) || (count($seen) == 3);
	});

	pg_assert_same(1, preg_match('/^[0-9A-Z]{10}$/', $code), 'ten characters');
	pg_assert_same(array('key_codes', 'key_codes', 'offers', 'key_codes', 'offers'), $seen, 'both tables checked for every candidate');
	pg_assert_same('', pg_key_code_form_generate(function () { return true; }), 'gives up when every code is taken');
}

function test_key_code_form_screens_use_shared_form()
{
	foreach (array('add_key_code.php' => 'null', 'edit_key_code.php' => '$id') as $file => $id) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/key_code_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_key_code_form_validate($liveform, ' . $id . ')', $source, $file . ' checks the shared way');
		pg_assert_contains('pg_key_code_form_values($liveform, ', $source, $file . ' saves the shared values');
		pg_assert_contains("require('includes/templates/edit_key_code.php')", $source, $file . ' draws the shared template');
		pg_assert_false(strpos($source, 'FROM offers') !== false, $file . ' keeps no copy of the offer check');
	}
}

function test_key_code_form_expiration_date_reads_the_whole_value()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$dotted = (DATE_FORMAT == 'month_day') ? '06.15.2027' : '15.06.2027';
	$taken = array('offers' => array('SUMMER10'));

	foreach (array('1/6/20271', '1/6/2027x', 'x1/6/2027', '31/02/2027') as $typed) {
		$errors = pg_test_key_code_form_errors(array('code' => 'ABC', 'offer_code' => 'SUMMER10', 'expiration_date' => $typed), null, $taken);
		pg_assert_same(array('expiration_date'), array_keys($errors), $typed . ' is refused, not stored as never expiring');
	}

	foreach (array($dotted, '2027-06-15', '') as $typed) {
		$errors = pg_test_key_code_form_errors(array('code' => 'ABC', 'offer_code' => 'SUMMER10', 'expiration_date' => $typed), null, $taken);
		pg_assert_same(array(), $errors, '"' . $typed . '" is accepted');
	}

	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$liveform = new liveform('key_code_form_test');
	$liveform->set('offer_code', 'SUMMER10');
	$liveform->set('expiration_date', $dotted);
	$values = pg_key_code_form_values($liveform, 'ABC');
	pg_assert_same('2027-06-15', $values[3], 'a dotted date is stored as the day it names');

	$liveform->set('expiration_date', '');
	$values = pg_key_code_form_values($liveform, 'ABC');
	pg_assert_same('', $values[3], 'empty still means it never expires');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_key_code_form_refuses_codes_longer_than_the_column()
{
	$long = 'pg-test-' . str_repeat('ğ', 43);
	$taken = array('offers' => array('SUMMER10'), 'key_codes' => array(mb_substr($long, 0, 50)));

	$errors = pg_test_key_code_form_errors(array('code' => $long, 'offer_code' => 'SUMMER10'), 7, $taken, $calls);
	pg_assert_same(array('code'), array_keys($errors), 'a 51 letter code is refused');
	pg_assert_contains('longer than 50 characters', $errors['code'], 'with the import\'s words');
	pg_assert_same(array(array('offers', 'SUMMER10', null)), $calls, 'a code too long is not looked up');

	$errors = pg_test_key_code_form_errors(array('code' => str_repeat('ğ', 50), 'offer_code' => 'SUMMER10'), 7, $taken);
	pg_assert_same(array(), $errors, '50 letters fit, whatever their bytes');

	$errors = pg_test_key_code_form_errors(array('code' => 'ABC', 'offer_code' => str_repeat('O', 51)), 7, $taken, $calls);
	pg_assert_same(array('offer_code'), array_keys($errors), 'an offer code longer than the column');
	pg_assert_contains('longer than 50 characters', $errors['offer_code'], 'with the same words');
}

function test_key_code_form_quantity_is_a_whole_number()
{
	$cases = array(
		array('1', 1, 'one'),
		array(' 25 ', 25, 'spaces around'),
		array('1,000', 1000, 'thousands comma'),
		array('0', 0, 'zero'),
		array('', 0, 'empty'),
		array('0.5', 0, 'a half created nothing yet said created'),
		array('2.5', 0, 'the number mask posts "2,5" as 2.5: two codes, not 2.5'),
		array('1e2', 0, 'exponent'),
		array('-3', 0, 'negative'),
		array('abc', 0, 'text'),
		array(array('5'), 0, 'array'),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[1], pg_key_code_form_quantity($case[0]), $case[2]);
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_key_code.php');
	pg_assert_contains('pg_key_code_form_quantity(', $source, 'create screen reads the quantity through it');
}

function test_key_code_template_wide_layout()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_key_code.php');
	$form_column = strpos($src, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status_column = strpos($src, 'col-12 lg:col-4 2xl:col-3 order-2');
	$save_bar = strpos($src, 'col-12 order-3 position-sticky');

	pg_assert_true($form_column !== false, 'the form column');
	pg_assert_true(($status_column !== false) && ($status_column > $form_column), 'the status column follows the form column in the source');
	pg_assert_true(($save_bar !== false) && ($save_bar > $status_column), 'the sticky save bar comes last');
	pg_assert_contains('style="top:3.3rem;"', $src, 'the status box sticks below the top bar');

	$status = substr($src, $status_column, $save_bar - $status_column);
	pg_assert_contains("'id' => 'enabled'", $status, 'Enabled sits in the status column');
	pg_assert_contains("'id' => 'expiration_date'", $status, 'the expiration date sits in the status column');
	pg_assert_contains("'id' => 'single_use'", $status, 'Single-Use sits in the status column');
	pg_assert_contains("'id' => 'report_key_code'", $status, 'the report choice sits in the status column');
	pg_assert_false(strpos($status, "'id' => 'notes'") !== false, 'the notes stay in the form column');
}
