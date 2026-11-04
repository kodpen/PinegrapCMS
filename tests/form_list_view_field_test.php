<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the check of an order-by or filter field picked on the form list
 * view screen (pg_form_list_view_field_allowed(), includes/fn/forms.php):
 * only a value the view's columns can store is saved.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_list_view_field_accepts_every_standard_field()
{
	$standard_fields = get_standard_fields_for_view();

	foreach ($standard_fields as $field) {
		pg_assert_true(pg_form_list_view_field_allowed($field['value'], $standard_fields, array()), $field['value']);
	}

	pg_assert_true(pg_form_list_view_field_allowed('address_name', $standard_fields, array()));
}

function test_form_list_view_field_accepts_none_and_the_form_fields()
{
	$standard_fields = get_standard_fields_for_view();

	pg_assert_true(pg_form_list_view_field_allowed('', $standard_fields, array()));
	pg_assert_true(pg_form_list_view_field_allowed('12', $standard_fields, array(12, 15)));
	pg_assert_true(pg_form_list_view_field_allowed(15, $standard_fields, array('12', '15')));
}

function test_form_list_view_field_refuses_values_outside_the_list()
{
	$standard_fields = get_standard_fields_for_view();

	pg_assert_false(pg_form_list_view_field_allowed('no_such_field', $standard_fields, array()));
	pg_assert_false(pg_form_list_view_field_allowed('13', $standard_fields, array(12, 15)));
	pg_assert_false(pg_form_list_view_field_allowed('12.0', $standard_fields, array(12)));
	pg_assert_false(pg_form_list_view_field_allowed(array('reference_code'), $standard_fields, array()));
	pg_assert_false(pg_form_list_view_field_allowed(null, $standard_fields, array()));
}

function test_form_list_view_field_takes_random_only_where_allowed()
{
	$standard_fields = get_standard_fields_for_view();

	pg_assert_true(pg_form_list_view_field_allowed('random', $standard_fields, array(), true));
	pg_assert_false(pg_form_list_view_field_allowed('random', $standard_fields, array()));
}
