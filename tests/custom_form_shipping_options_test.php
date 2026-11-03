<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the option check of the custom shipping and billing forms
 * (submit_custom_form()): a pick list, radio button or check box value
 * outside the field's option list is refused, as on a custom form page
 * (pg_cf_option_value_error()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A value of the list, several values of the list and no value pass.
function test_custom_form_shipping_options_in_list()
{
	$field = array('type' => 'pick list', 'label' => 'Gift wrap');
	$options = array('Yes', 'No');
	pg_assert_same(null, pg_cf_option_value_error($field, 'Yes', $options));
	pg_assert_same(null, pg_cf_option_value_error($field, ' No ', $options), 'spaces around are ignored');
	pg_assert_same(null, pg_cf_option_value_error($field, '', $options), 'an empty value is the required check\'s business');
	pg_assert_same(null, pg_cf_option_value_error(array('type' => 'check box', 'label' => 'Days'), array('Yes', 'No'), $options));
}

// A made-up value is refused with the field's label, escaped.
function test_custom_form_shipping_options_outside_list()
{
	$options = array('Yes', 'No');
	$message = pg_cf_option_value_error(array('type' => 'radio button', 'label' => 'Wrap <b>&</b>'), 'Maybe', $options);
	pg_assert_true(is_string($message) && $message !== '', 'a value outside the list is refused');
	pg_assert_true(strpos($message, 'Wrap &lt;b&gt;&amp;&lt;/b&gt;') !== false, 'the label is escaped');
	pg_assert_true(is_string(pg_cf_option_value_error(array('type' => 'check box', 'label' => 'Days'), array('Yes', 'Other'), $options)), 'one value of a group outside the list');
	pg_assert_true(is_string(pg_cf_option_value_error(array('type' => 'pick list', 'label' => 'A'), array(array('Yes')), $options)), 'a nested array');
}

// Fields without an option list are not held to one.
function test_custom_form_shipping_options_not_checked()
{
	pg_assert_same(null, pg_cf_option_value_error(array('type' => 'text box', 'label' => 'Note'), 'Anything', array('Yes')));
	pg_assert_same(null, pg_cf_option_value_error(array('type' => 'check box', 'label' => 'Agree'), 'on', array()), 'a box with no option rows');
}

// submit_custom_form() runs the check.
function test_custom_form_shipping_options_wired()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
	$start = strpos($source, 'function submit_custom_form(');
	$end = strpos($source, "\nfunction ", $start + 10);
	pg_assert_true($start !== false && strpos(substr($source, $start, $end - $start), 'pg_cf_option_value_error(') !== false, 'submit_custom_form() checks posted options');
}
