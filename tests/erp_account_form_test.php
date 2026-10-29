<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pieces the account create and edit screens share
 * (includes/erp/account_form.php) and the day check the CSV import uses
 * too (includes/erp/accounts.php): a payment term and a reminder threshold
 * are whole days from 0 to 3650, and the row handed to erp_account_save()
 * carries every key both screens send, because the save writes some
 * columns only when their key is present and a dropped key would quietly
 * keep the stored value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');

// Stands in for the liveform: the builder only reads posted values.
class pg_test_account_form_post
{
	public $values = array();

	public function __construct($values)
	{
		$this->values = $values;
	}

	public function get_field_value($name)
	{
		return $this->values[$name] ?? '';
	}
}

function pg_test_account_form_post($overrides = array())
{
	return new pg_test_account_form_post(array_merge(array(
		'kind' => 'customer',
		'title' => 'Deneme Ltd',
		'is_person' => '0',
		'tax_number' => '1234567808',
		'tax_office' => 'Konak',
		'email' => 'info@example.com',
		'phone' => '555',
		'address' => 'Street 1',
		'district' => 'Konak',
		'city' => 'Izmir',
		'state' => 'Aegean',
		'postcode' => '35000',
		'status' => 'active',
		'notes' => 'Note',
		'overdue_notify_customer' => '1',
		'invoice_email' => 'bills@example.com',
		'invoice_mail' => '1',
		'credit_limit' => '1.250,50',
	), $overrides));
}

function test_erp_account_days_refused_edges()
{
	pg_assert_same(false, erp_account_days_refused(''), 'empty is no term');
	pg_assert_same(false, erp_account_days_refused('0'));
	pg_assert_same(false, erp_account_days_refused('3650'));
	pg_assert_same(true, erp_account_days_refused('3651'), 'over ten years');
	pg_assert_same(true, erp_account_days_refused('12a'));
	pg_assert_same(true, erp_account_days_refused('00001'), 'five digits');
	pg_assert_same(true, erp_account_days_refused('-1'));
	pg_assert_same(true, erp_account_days_refused('1.5'));
}

function test_erp_account_form_save_fields_keys()
{
	$fields = erp_account_form_save_fields(pg_test_account_form_post(), 'DE', 'EUR', 7, '30', '15', 'TR');

	$keys = array('contact_id', 'kind', 'title', 'is_person', 'tax_number', 'tax_office', 'email', 'phone', 'address', 'district', 'city',
		'state', 'postcode', 'country_code', 'currency', 'status', 'notes', 'payment_days', 'overdue_notify_days', 'overdue_notify_customer',
		'invoice_email', 'invoice_mail', 'credit_limit');

	pg_assert_same($keys, array_keys($fields), 'every key the screens send, and no id or created_by');
	pg_assert_same(7, $fields['contact_id']);
	pg_assert_same(false, $fields['is_person']);
	pg_assert_same('Aegean', $fields['state'], 'a foreign address keeps its state');
	pg_assert_same('DE', $fields['country_code']);
	pg_assert_same('EUR', $fields['currency']);
	pg_assert_same(30, $fields['payment_days']);
	pg_assert_same(15, $fields['overdue_notify_days']);
	pg_assert_same(true, $fields['overdue_notify_customer']);
	pg_assert_same('bills@example.com', $fields['invoice_email']);
	pg_assert_same(true, $fields['invoice_mail']);
	pg_assert_same(125050, $fields['credit_limit']);
}

function test_erp_account_form_save_fields_turkish_state()
{
	$fields = erp_account_form_save_fields(pg_test_account_form_post(), 'TR', 'TRY', 0, '', '', '');

	pg_assert_same('', $fields['state'], 'a Turkish address has no state');
	pg_assert_same(0, $fields['payment_days'], 'an empty term is 0');
	pg_assert_same(0, $fields['overdue_notify_days']);
}

function test_erp_account_form_save_fields_country_fallback()
{
	// Creating: an empty choice takes the store's country, and its rules.
	$added = erp_account_form_save_fields(pg_test_account_form_post(), '', 'TRY', 0, '', '', 'TR');

	pg_assert_same('TR', $added['country_code'], 'add falls back to the store country');
	pg_assert_same('', $added['state'], 'the fallback country decides the state rule');

	// Editing: an empty choice is passed through as empty.
	$edited = erp_account_form_save_fields(pg_test_account_form_post(array('state' => '')), '', 'TRY', 0, '', '', '');

	pg_assert_same('', $edited['country_code'], 'edit passes an empty code through');
	pg_assert_same('', $edited['state']);

	$chosen = erp_account_form_save_fields(pg_test_account_form_post(), 'DE', 'EUR', 0, '', '', 'TR');

	pg_assert_same('DE', $chosen['country_code'], 'a chosen country wins over the fallback');
}
