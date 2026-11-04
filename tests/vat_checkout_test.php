<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the EU VAT check at checkout (includes/fn/vat_checkout.php): when
 * the check runs, what each answer writes on the order (tax_exempt, vat_id,
 * vat_checked_at, vat_request_id) and the text the customer is shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * An answer shaped like pg_vat_check()'s.
 */
function _vat_checkout_answer($status, $country = 'DE', $error = '')
{
	return array(
		'id'         => $country . '123456789',
		'country'    => $country,
		'number'     => '123456789',
		'status'     => $status,
		'name'       => '',
		'address'    => '',
		'request_id' => ($status === 'valid') ? 'WAPIAAAAW1234567' : '',
		'checked_at' => 1762200000,
		'error'      => $error,
		'cached'     => false,
	);
}

function test_vat_checkout_applies_only_across_member_states()
{
	pg_assert_true(pg_vat_checkout_applies('DE123456789', 'DE', 'FR'));
	pg_assert_true(pg_vat_checkout_applies(' 123456789 ', 'de', 'fr'));
	pg_assert_true(pg_vat_checkout_applies('EL123456789', 'GR', 'FR'));

	pg_assert_false(pg_vat_checkout_applies('', 'DE', 'FR'), 'no id typed');
	pg_assert_false(pg_vat_checkout_applies('   ', 'DE', 'FR'), 'blank id');
	pg_assert_false(pg_vat_checkout_applies('DE123456789', 'FR', 'FR'), 'same country');
	pg_assert_false(pg_vat_checkout_applies('1234567890', 'TR', 'FR'), 'billing outside the EU');
	pg_assert_false(pg_vat_checkout_applies('DE123456789', 'DE', 'TR'), 'site outside the EU');
	pg_assert_false(pg_vat_checkout_applies('DE123456789', 'DE', ''), 'no site country');
	pg_assert_false(pg_vat_checkout_applies('GB123456789', 'GB', 'FR'), 'GB is no member');
}

function test_vat_checkout_outcome_valid_sets_the_flag_and_the_proof()
{
	$check = _vat_checkout_answer('valid');
	$outcome = pg_vat_checkout_outcome($check, pg_vat_exempt_decision($check, 'DE', 'FR'));

	pg_assert_same(1, $outcome['tax_exempt']);
	pg_assert_same(array('vat_id' => 'DE123456789', 'vat_checked_at' => 1762200000, 'vat_request_id' => 'WAPIAAAAW1234567'), $outcome['fields']);
	pg_assert_same('', $outcome['error']);
	pg_assert_same('VAT id verified: the order is invoiced without VAT under the reverse charge.', $outcome['notice']);
}

function test_vat_checkout_outcome_unavailable_charges_vat_with_a_note()
{
	$check = _vat_checkout_answer('unavailable', 'DE', 'MS_UNAVAILABLE');
	$outcome = pg_vat_checkout_outcome($check, pg_vat_exempt_decision($check, 'DE', 'FR'));

	pg_assert_same(null, $outcome['tax_exempt'], 'the box stays as posted');
	pg_assert_same(pg_vat_order_fields_empty(), $outcome['fields']);
	pg_assert_same('', $outcome['error'], 'the order goes on');
	pg_assert_same('The EU VAT service (VIES) could not confirm the VAT id right now, so VAT is charged on this order.', $outcome['notice']);
}

function test_vat_checkout_outcome_refusals_stop_the_form()
{
	$cases = array(
		'invalid'       => array(_vat_checkout_answer('invalid'), 'DE', 'The EU VAT service (VIES) does not know this VAT id. Correct it, or clear the box to order with VAT.'),
		'malformed'     => array(_vat_checkout_answer('malformed', 'DE', 'format'), 'DE', 'This is not a valid VAT id for the billing country. Correct it, or clear the box to order with VAT.'),
		'other_country' => array(_vat_checkout_answer('valid', 'AT'), 'DE', 'The VAT id belongs to a member state other than the billing country.'),
	);

	foreach ($cases as $label => $case) {
		$outcome = pg_vat_checkout_outcome($case[0], pg_vat_exempt_decision($case[0], $case[1], 'FR'));
		pg_assert_same(null, $outcome['tax_exempt'], $label);
		pg_assert_same(pg_vat_order_fields_empty(), $outcome['fields'], $label);
		pg_assert_same($case[2], $outcome['error'], $label);
		pg_assert_same('', $outcome['notice'], $label);
	}
}

function test_vat_checkout_outcome_without_a_check_clears_the_proof()
{
	foreach (array('no_check', 'not_eu', 'same_country') as $reason) {
		$outcome = pg_vat_checkout_outcome(array(), array('exempt' => false, 'reason' => $reason));
		pg_assert_same(null, $outcome['tax_exempt'], $reason);
		pg_assert_same(pg_vat_order_fields_empty(), $outcome['fields'], $reason);
		pg_assert_same('', $outcome['error'], $reason);
		pg_assert_same('', $outcome['notice'], $reason);
	}
}

function test_vat_order_fields_fit_the_columns()
{
	pg_assert_same(array('vat_id' => '', 'vat_checked_at' => 0, 'vat_request_id' => ''), pg_vat_order_fields_empty());

	$check = _vat_checkout_answer('valid');
	$check['id'] = str_repeat('A', 30);
	$check['request_id'] = str_repeat('B', 40);
	$outcome = pg_vat_checkout_outcome($check, array('exempt' => true, 'reason' => 'exempt'));

	pg_assert_same(20, strlen($outcome['fields']['vat_id']), 'orders.vat_id is VARCHAR(20)');
	pg_assert_same(32, strlen($outcome['fields']['vat_request_id']), 'orders.vat_request_id is VARCHAR(32)');
}

function test_vat_checkout_tax_number_width()
{
	pg_assert_same(11, pg_vat_tax_number_width('TR'));
	pg_assert_same(20, pg_vat_tax_number_width('FR'));
	pg_assert_same(20, pg_vat_tax_number_width(''));
}
