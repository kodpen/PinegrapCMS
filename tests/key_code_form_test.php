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
