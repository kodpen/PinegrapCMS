<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for request values that arrive as arrays where a form expects one
 * string: pg_liveform_single_values() (includes/fn/forms.php), which the form
 * processors call after liveform::add_fields_to_session(), and the ?value_<id>
 * and ?connect_to_contact links a designed custom form reads
 * (pg_cf_field_initial_value() and pg_cf_connect_to_contact_off() in
 * includes/fn/custom_form.php). An array counts as not given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Stands in for a liveform: the helper only reads and assigns field values.
function _test_form_array_liveform($values)
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
			return isset($this->values[$field]) ? $this->values[$field] : null;
		}

		public function assign_field_value($field, $value)
		{
			$this->values[$field] = $value;
		}
	};
}

// Runs $check with $_GET set to $get, and puts the real $_GET back afterwards.
function _test_form_array_with_get($get, $check)
{
	$saved = $_GET;
	$_GET = $get;

	try {
		return $check();
	} finally {
		$_GET = $saved;
	}
}

// A listed field posted as an array is emptied; strings are left as they are.
function test_form_array_single_values_empties_arrays()
{
	$liveform = _test_form_array_liveform(array(
		'first_name' => array('x'),
		'last_name' => 'Doe',
		'5' => array('a', 'b'),
	));

	pg_liveform_single_values($liveform, array('first_name', 'last_name', '5'));

	pg_assert_same('', $liveform->values['first_name'], 'array emptied');
	pg_assert_same('Doe', $liveform->values['last_name'], 'string kept');
	pg_assert_same('', $liveform->values['5'], 'numeric field name');
}

// A field that is not listed, such as a check box group, keeps its array, and
// a listed field that was not posted is not created.
function test_form_array_single_values_leaves_other_fields()
{
	$liveform = _test_form_array_liveform(array('118' => array('Design', 'Code')));

	pg_liveform_single_values($liveform, array('first_name'));

	pg_assert_same(array('Design', 'Code'), $liveform->values['118'], 'unlisted array kept');
	pg_assert_false(array_key_exists('first_name', $liveform->values), 'missing field not created');
}

// ?value_<id>[]= on a designed form falls through to the field's default.
function test_form_array_initial_value_ignores_array_query()
{
	$field = array('id' => 5, 'default_value' => 'Default');

	$value = _test_form_array_with_get(array('value_5' => array('x')), function () use ($field) {
		return pg_cf_field_initial_value($field, null);
	});

	pg_assert_same('Default', $value);
}

// A string in ?value_<id> still pre-fills the control, trimmed.
function test_form_array_initial_value_uses_string_query()
{
	$field = array('id' => 5, 'default_value' => 'Default');

	$value = _test_form_array_with_get(array('value_5' => '  Hello '), function () use ($field) {
		return pg_cf_field_initial_value($field, null);
	});

	pg_assert_same('Hello', $value);
}

// ?connect_to_contact[]=false does not switch the contact link off.
function test_form_array_connect_to_contact_array_is_not_off()
{
	pg_assert_false(_test_form_array_with_get(array('connect_to_contact' => array('false')), function () {
		return pg_cf_connect_to_contact_off();
	}), 'array');

	pg_assert_true(_test_form_array_with_get(array('connect_to_contact' => ' FALSE '), function () {
		return pg_cf_connect_to_contact_off();
	}), 'string');
}
