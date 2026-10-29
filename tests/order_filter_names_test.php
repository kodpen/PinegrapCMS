<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Advanced Filters panel of the orders list (view_orders.php):
 * every filter box is named by the label that points at its id, the date
 * type select carries a name, the person and address boxes stay out of the
 * browser's address autofill and the phone, fax and e-mail filters ask for
 * the matching keyboard.
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
 * The PHP source of the Advanced Filters block, from the affiliate field to
 * the Date Range heading.
 */
function order_filter_names_test_block()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	$start = strpos($source, '$output_affiliate =');
	$end = strpos($source, 'data-pg-date-selects', $start);

	pg_assert_true($start !== false && $end !== false, 'the Advanced Filters block is found');

	return substr($source, $start, $end - $start);
}

/**
 * Each filter field tag (input or select) of the block, keyed by its name.
 */
function order_filter_names_test_fields()
{
	preg_match_all('/<(?:input|select)\b[^>]*\bname="([a-z_0-9]+)"[^>]*>/', order_filter_names_test_block(), $matches, PREG_SET_ORDER);

	$fields = array();

	foreach ($matches as $match) {
		$fields[$match[1]] = $match[0];
	}

	return $fields;
}

function test_order_filter_names_every_label_points_at_a_field()
{
	$block = order_filter_names_test_block();

	preg_match_all('/<label\b[^>]*>/', $block, $labels);
	pg_assert_true(count($labels[0]) >= 50, 'about fifty filter labels');

	foreach ($labels[0] as $label) {
		pg_assert_true((bool) preg_match('/\bfor="(filter_[a-z_0-9]+)"/', $label, $for), 'label points at a filter_ id: ' . $label);
		pg_assert_same(1, substr_count($block, 'id="' . $for[1] . '"'), 'one field has the id ' . $for[1]);
	}
}

function test_order_filter_names_every_field_has_a_prefixed_id()
{
	foreach (order_filter_names_test_fields() as $name => $tag) {
		if ($name === 'date_type') {
			continue;
		}

		pg_assert_contains('id="filter_' . $name . '"', $tag, 'field ' . $name);
	}
}

function test_order_filter_names_date_type_select_named()
{
	$fields = order_filter_names_test_fields();

	pg_assert_contains('aria-label="\' . h(lang(\'Date Type\')) . \'"', $fields['date_type']);
}

function test_order_filter_names_person_and_address_boxes_skip_autofill()
{
	$fields = order_filter_names_test_fields();
	$names = array('ship_to_name');

	foreach (array('salutation', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'zip_code', 'country', 'phone_number') as $part) {
		$names[] = 'shipping_' . $part;
		$names[] = 'billing_' . $part;
	}

	$names[] = 'billing_fax_number';
	$names[] = 'billing_email_address';

	foreach ($names as $name) {
		pg_assert_contains('autocomplete="pg-no-autofill"', $fields[$name], 'field ' . $name);
	}
}

function test_order_filter_names_phone_and_email_keyboards()
{
	$fields = order_filter_names_test_fields();

	foreach (array('billing_phone_number' => 'tel', 'billing_fax_number' => 'tel', 'shipping_phone_number' => 'tel', 'billing_email_address' => 'email') as $name => $mode) {
		pg_assert_contains('inputmode="' . $mode . '"', $fields[$name], 'field ' . $name);
		pg_assert_contains('type="text"', $fields[$name], 'partial matches still allowed in ' . $name);
	}
}
