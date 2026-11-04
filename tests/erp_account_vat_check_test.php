<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the EU VAT check of an ERP account card's tax number
 * (includes/erp/account_form.php): which cards reach VIES, that an
 * untouched number on edit makes no call, and what each VIES answer does
 * to the save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/vat_check.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/vat_checkout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');

// Stands in for the liveform: posted values in, marked errors out.
class pg_test_account_vat_form
{
	public $values = array();
	public $errors = array();

	public function __construct($values)
	{
		$this->values = $values;
	}

	public function get_field_value($name)
	{
		return $this->values[$name] ?? '';
	}

	public function mark_error($name, $message)
	{
		$this->errors[$name] = $message;
	}

	public function check_field_error($name)
	{
		return isset($this->errors[$name]);
	}
}

// A VIES stand-in that records the numbers it is asked about.
function pg_test_account_vat_checker($status, &$calls)
{
	return function ($number, $country) use ($status, &$calls) {
		$calls[] = $number . '/' . $country;
		return array('status' => $status, 'name' => ($status === 'valid') ? 'ACME GMBH' : '');
	};
}

function test_erp_account_vat_outcome_valid_and_unavailable_never_refuse()
{
	foreach (array(true, false) as $refuse) {
		$valid = erp_account_vat_outcome(array('status' => 'valid', 'name' => 'ACME GMBH'), $refuse);
		pg_assert_same('', $valid['error']);
		pg_assert_true(strpos($valid['notice'], 'ACME GMBH') !== false, 'the registered name is shown');

		$down = erp_account_vat_outcome(array('status' => 'unavailable'), $refuse);
		pg_assert_same('', $down['error'], 'VIES down never stops the save');
		pg_assert_true($down['warning'] !== '');
	}

	pg_assert_true(strpos(erp_account_vat_outcome(array('status' => 'valid', 'name' => ''), false)['notice'], '()') === false, 'no empty brackets');
}

function test_erp_account_vat_outcome_invalid_follows_the_policy()
{
	foreach (array('invalid', 'malformed') as $status) {
		$refused = erp_account_vat_outcome(array('status' => $status), true);
		pg_assert_true($refused['error'] !== '', $status . ' refused');
		pg_assert_same('', $refused['warning']);

		$warned = erp_account_vat_outcome(array('status' => $status), false);
		pg_assert_same('', $warned['error'], $status . ' only warned');
		pg_assert_true($warned['warning'] !== '');
	}

	pg_assert_same(array('error' => '', 'notice' => '', 'warning' => ''), erp_account_vat_outcome(array(), true), 'no check, nothing said');
}

function test_erp_account_form_check_vat_only_across_an_eu_border()
{
	$calls = array();
	$checker = pg_test_account_vat_checker('valid', $calls);

	// A Turkish card, a card in the store's own member state, a card outside the EU.
	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => '1234567890')), 'TR', null, false, $checker, 'DE');
	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'DE123456789')), 'DE', null, false, $checker, 'DE');
	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'GB123456789')), 'GB', null, false, $checker, 'DE');
	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'FR12345678901')), 'FR', null, false, $checker, 'TR');
	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => '')), 'FR', null, false, $checker, 'DE');
	pg_assert_same(array(), $calls, 'no call outside a cross-border EU card');

	$said = erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'FR 123 456 789 01')), 'FR', null, false, $checker, 'DE');
	pg_assert_same(array('FR 123 456 789 01/FR'), $calls);
	pg_assert_true($said['notice'] !== '');

	// A number the format check already marked is not sent.
	$form = new pg_test_account_vat_form(array('tax_number' => 'FR12345678901'));
	$form->errors['tax_number'] = 'marked';
	erp_account_form_check_vat($form, 'FR', null, false, $checker, 'DE');
	pg_assert_same(1, count($calls));
}

function test_erp_account_form_check_vat_edit_checks_only_a_changed_number()
{
	$calls = array();
	$checker = pg_test_account_vat_checker('valid', $calls);

	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'FR12345678901')), 'FR', 'FR12345678901', false, $checker, 'DE');
	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'fr 123.456.789-01')), 'FR', 'FR12345678901', false, $checker, 'DE');
	pg_assert_same(array(), $calls, 'untouched number, no call');

	erp_account_form_check_vat(new pg_test_account_vat_form(array('tax_number' => 'FR12345678902')), 'FR', 'FR12345678901', false, $checker, 'DE');
	pg_assert_same(1, count($calls));
}

function test_erp_account_form_check_vat_marks_the_box_only_when_refusing()
{
	$calls = array();
	$checker = pg_test_account_vat_checker('invalid', $calls);

	$form = new pg_test_account_vat_form(array('tax_number' => 'FR12345678901'));
	$said = erp_account_form_check_vat($form, 'FR', null, true, $checker, 'DE');
	pg_assert_true(isset($form->errors['tax_number']));
	pg_assert_same(array('notice' => '', 'warning' => ''), $said);

	$form = new pg_test_account_vat_form(array('tax_number' => 'FR12345678901'));
	$said = erp_account_form_check_vat($form, 'FR', null, false, $checker, 'DE');
	pg_assert_same(array(), $form->errors);
	pg_assert_true($said['warning'] !== '');
}
