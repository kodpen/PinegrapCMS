<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the name a new account takes when it is opened from an order
 * (includes/erp/accounts.php): a Turkish company's VKN is asked about at GİB
 * through the e-document provider, and the name GİB registers it under
 * replaces the one the order or the contact card gave. A person's TCKN, a
 * foreign number, a number that fails its check digit and an answer about
 * another number change nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');

function pg_test_registered_name_account($overrides = array())
{
	return array_merge(array(
		'kind' => 'customer',
		'title' => 'Deneme danismanlik',
		'is_person' => true,
		'tax_number' => '1234567808',
		'tax_office' => 'Konak',
		'country_code' => 'TR',
		'contact_id' => 12,
	), $overrides);
}

function pg_test_registered_name_lookup($overrides = array())
{
	return array_merge(array(
		'success' => true,
		'error' => '',
		'number' => '1234567808',
		'is_einvoice_user' => true,
		'aliases' => array('urn:mail:defaultpk@example.com'),
		'is_edispatch_user' => false,
		'title' => 'Deneme Danışmanlık ve Ticaret A.Ş.',
		'message' => '',
	), $overrides);
}

// Only a Turkish VKN that passes its check digit is asked about.
function test_erp_account_registered_name_wanted()
{
	pg_assert_true(erp_account_registered_name_wanted('1234567808', 'TR'));
	pg_assert_true(erp_account_registered_name_wanted(' 123 456 7808 ', 'tr'), 'spaces and a lower-case country');

	// A person's TCKN: the invoice carries the first and the last name.
	pg_assert_false(erp_account_registered_name_wanted('10000000146', 'TR'), 'TCKN');
	pg_assert_false(erp_account_registered_name_wanted('11111111111', 'TR'), 'final consumer');
	pg_assert_false(erp_account_registered_name_wanted('1234567809', 'TR'), 'wrong check digit');
	pg_assert_false(erp_account_registered_name_wanted('1234567808', 'DE'), 'foreign account');
	pg_assert_false(erp_account_registered_name_wanted('', 'TR'), 'no number');
}

// GİB's name replaces the typed one and the account is a company's.
function test_erp_account_data_with_registered_name()
{
	$data = erp_account_data_with_registered_name(pg_test_registered_name_account(), pg_test_registered_name_lookup());
	pg_assert_same('Deneme Danışmanlık ve Ticaret A.Ş.', $data['title']);
	pg_assert_false($data['is_person']);
	pg_assert_same('1234567808', $data['tax_number'], 'the number stays');
	pg_assert_same('Konak', $data['tax_office'], 'the rest stays');

	// The register's spacing is tidied.
	$data = erp_account_data_with_registered_name(pg_test_registered_name_account(), pg_test_registered_name_lookup(array('title' => "  ABC  Tekstil\tA.Ş. ")));
	pg_assert_same('ABC Tekstil A.Ş.', $data['title']);

	// Nothing is changed by a failed lookup, by a number GİB gives no name
	// for, by an answer about another number or by a TCKN.
	$account = pg_test_registered_name_account();
	pg_assert_same($account, erp_account_data_with_registered_name($account, pg_test_registered_name_lookup(array('success' => false))));
	pg_assert_same($account, erp_account_data_with_registered_name($account, pg_test_registered_name_lookup(array('title' => '', 'is_einvoice_user' => false))));
	pg_assert_same($account, erp_account_data_with_registered_name($account, pg_test_registered_name_lookup(array('number' => '9876543210'))));

	$person = pg_test_registered_name_account(array('tax_number' => '10000000146', 'title' => 'Ayşe Yılmaz'));
	pg_assert_same($person, erp_account_data_with_registered_name($person, pg_test_registered_name_lookup(array('number' => '10000000146', 'title' => 'Yılmaz Ticaret'))));
}
