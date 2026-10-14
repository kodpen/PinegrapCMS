<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for request values that arrive as arrays in the order flow (express
 * order, shipping address and arrival, billing information, order preview):
 * pg_cf_posted_value() (includes/fn/custom_form.php), which
 * submit_custom_form() applies to each custom shipping and billing form field,
 * and the parts of pg_liveform_single_values() (includes/fn/forms.php) the
 * order scripts rely on: a card number read as stored, and a total emptied to
 * null.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Stands in for a liveform whose card number is stored encrypted: reading it
// through get_field_value() decrypts it, which an array cannot go through.
function _test_order_form_array_liveform($values)
{
	return new class($values) {
		public $values;

		public function __construct($values)
		{
			$this->values = $values;
		}

		public function get_field($field)
		{
			return array_key_exists($field, $this->values) ? array('value' => $this->values[$field]) : null;
		}

		public function get_field_value($field)
		{
			if (($field === 'card_number') && is_array($this->values[$field] ?? null)) {
				throw new TypeError('decrypt_credit_card_number(): array given');
			}

			return isset($this->values[$field]) ? $this->values[$field] : null;
		}

		public function assign_field_value($field, $value)
		{
			$this->values[$field] = $value;
		}
	};
}

// A field that holds one value keeps a string and loses an array.
function test_order_form_array_posted_value_single_fields()
{
	foreach (array('text box', 'text area', 'date', 'date and time', 'time', 'email address', 'radio button') as $type) {
		$field = array('type' => $type, 'multiple' => '0');

		pg_assert_same('', pg_cf_posted_value(array('2026-10-14'), $field), $type . ': array');
		pg_assert_same('2026-10-14', pg_cf_posted_value('2026-10-14', $field), $type . ': string');
		pg_assert_same(null, pg_cf_posted_value(null, $field), $type . ': not posted');
	}

	pg_assert_same('', pg_cf_posted_value(array('A'), array('type' => 'pick list', 'multiple' => '0')), 'single pick list');
}

// A check box group and a multi-select keep their list as it came, so a normal
// post is stored exactly as before.
function test_order_form_array_posted_value_lists_kept()
{
	$list = array('A', 'C');

	pg_assert_same($list, pg_cf_posted_value($list, array('type' => 'check box', 'multiple' => '0')), 'check box group');
	pg_assert_same($list, pg_cf_posted_value($list, array('type' => 'pick list', 'multiple' => '1')), 'multi-select');
	pg_assert_same('A', pg_cf_posted_value('A', array('type' => 'check box', 'multiple' => '0')), 'one check box');
}

// An entry of a list that is itself a list (name[0][]=x) is dropped; when none
// is left the field counts as not given, which a required field reports.
function test_order_form_array_posted_value_nested_entries_dropped()
{
	$field = array('type' => 'check box', 'multiple' => '0');

	pg_assert_same(array(1 => 'B'), pg_cf_posted_value(array(array('A'), 'B'), $field), 'mixed');
	pg_assert_same('', pg_cf_posted_value(array(array('A')), array('type' => 'pick list', 'multiple' => '1')), 'only nested');
}

// A card number posted as an array is emptied without being read through
// get_field_value(), and a string card number is left alone.
function test_order_form_array_single_values_card_number()
{
	$liveform = _test_order_form_array_liveform(array('card_number' => array('4111111111111111')));

	pg_liveform_single_values($liveform, array('card_number'));

	pg_assert_same('', $liveform->values['card_number'], 'array emptied');

	$liveform = _test_order_form_array_liveform(array('card_number' => 'pgc1:cipher'));

	pg_liveform_single_values($liveform, array('card_number'));

	pg_assert_same('pgc1:cipher', $liveform->values['card_number'], 'string kept');
}

// A total, which the order compares as a number, is emptied to null: '' would
// stop that arithmetic as the array did.
function test_order_form_array_single_values_total_to_null()
{
	$liveform = _test_order_form_array_liveform(array('total' => array('299.95'), 'total_with_surcharge' => '305.00'));

	pg_liveform_single_values($liveform, array('total', 'total_with_surcharge'), null);

	pg_assert_same(null, $liveform->values['total'], 'array becomes null');
	pg_assert_same('305.00', $liveform->values['total_with_surcharge'], 'string kept');
}
