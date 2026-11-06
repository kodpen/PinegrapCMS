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

function test_vat_order_note_names_the_id_the_time_and_the_consultation_number()
{
	pg_assert_same('', pg_vat_order_note(array()), 'no id, no note');
	pg_assert_same('', pg_vat_order_note(array('vat_id' => ' ', 'vat_checked_at' => 1762200000)), 'a blank id is no id');

	$note = pg_vat_order_note(array('vat_id' => 'DE123456789', 'vat_checked_at' => 1762200000, 'vat_request_id' => 'WAPIAAAAW1234567'));

	pg_assert_true(strpos($note, 'DE123456789') !== false, 'the id');
	pg_assert_true(strpos($note, date('Y-m-d H:i', 1762200000)) !== false, 'the time of the check');
	pg_assert_true(strpos($note, 'WAPIAAAAW1234567') !== false, 'the consultation number');

	$bare = pg_vat_order_note(array('vat_id' => 'DE123456789', 'vat_checked_at' => 0, 'vat_request_id' => ''));

	pg_assert_true(strpos($bare, 'DE123456789') !== false, 'the id without a time');
	pg_assert_true(strpos($bare, date('Y-m-d', 0)) === false, 'no epoch date for a missing time');
}

function test_vat_tax_number_attrs_fit_keeps_a_turkish_box_and_widens_others()
{
	$attrs = array(
		array('name' => 'inputmode', 'value' => 'numeric'),
		array('name' => 'maxlength', 'value' => '11'),
		array('name' => 'pattern', 'value' => '[0-9]{10,11}'),
		array('name' => 'autocomplete', 'value' => 'off'),
	);

	pg_assert_same($attrs, pg_vat_tax_number_attrs_fit($attrs, 'TR'), 'a Turkish store keeps the digits box');
	pg_assert_same('', pg_vat_tax_number_hint('TR'), 'and its hint');

	pg_assert_same(array(
		array('name' => 'inputmode', 'value' => 'text'),
		array('name' => 'maxlength', 'value' => '20'),
		array('name' => 'autocomplete', 'value' => 'off'),
	), pg_vat_tax_number_attrs_fit($attrs, 'DE'), 'elsewhere: no pattern, the column width, text input');
	pg_assert_true(pg_vat_tax_number_hint('DE') !== '', 'elsewhere: the VAT id hint');
}

function test_vat_express_order_tax_number_box_fitted_in_a_designed_tree()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col-12'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(
				array('name' => 'maxlength', 'value' => '11'),
				array('name' => 'pattern', 'value' => '[0-9]{10,11}'),
			), '_bindings' => array('eo_field' => 'tax_number')), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'form-text', 'text' => '11 digits for a person, 10 for a company.'), 'children' => array()),
		)),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'text' => '11 digits for a person, 10 for a company.'), 'children' => array()),
	));

	$turkish = $tree;
	_eo_fit_tax_number_field($turkish, 'TR');
	pg_assert_same($tree, $turkish, 'a Turkish store leaves the tree as designed');

	_eo_fit_tax_number_field($tree, 'FR');
	$box = $tree['children'][0]['children'][0]['props']['_attrs'];
	pg_assert_same(array(array('name' => 'maxlength', 'value' => '20')), $box, 'the pattern goes, the box widens');
	pg_assert_same(pg_vat_tax_number_hint('FR'), $tree['children'][0]['children'][1]['props']['text'], 'the hint beside the box changes');
	pg_assert_same('11 digits for a person, 10 for a company.', $tree['children'][1]['props']['text'], 'a text away from the box stays');
}

function test_tax_exempt_box_counts_only_where_the_store_offers_it()
{
	// The box is offered with tax on and exemption on; a posted value
	// counts only then, so a hand-made post cannot drop the tax.
	pg_assert_same(1, pg_tax_exempt_box('1', true, true));
	pg_assert_same(1, pg_tax_exempt_box(1, true, true));
	pg_assert_same(0, pg_tax_exempt_box('', true, true));
	pg_assert_same(0, pg_tax_exempt_box(null, true, true));
	pg_assert_same(0, pg_tax_exempt_box('1', true, false));
	pg_assert_same(0, pg_tax_exempt_box('1', false, true));
	pg_assert_same(0, pg_tax_exempt_box('1', false, false));
}
