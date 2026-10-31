<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gift card and instalment amounts on the external API's Order
 * (includes/api/resources/orders.php): the two money fields in the totals,
 * the instalment count, the select that reads them and the schema that
 * declares them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

// orders.php pulls in the outbound webhook module, whose gate asks for
// init.php; behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');

// The presenter's money and time helpers live in includes/api/response.php,
// which loads only behind the API's own entry. Stand-ins of the same shape.
if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

// An order row as api_order_select() reads it, strings as mysqli gives them.
function api_order_money_test_row()
{
	return array(
		'id' => '812', 'order_number' => '1000812', 'order_date' => '1793023380', 'status' => 'complete',
		'contact_id' => '0', 'member_id' => null,
		'billing_first_name' => 'Ada', 'billing_last_name' => 'Lovelace',
		'billing_email_address' => 'ada@example.com', 'billing_company' => '',
		'billing_address_1' => '', 'billing_address_2' => '', 'billing_city' => '',
		'billing_state' => '', 'billing_zip_code' => '', 'billing_country' => '',
		'billing_phone_number' => '',
		'subtotal' => '100000', 'discount' => '0', 'tax' => '0', 'shipping' => '0',
		'surcharge' => '0', 'gift_card_discount' => '0', 'installment_charges' => '0',
		'payment_installment' => '1', 'total' => '100000',
		'payment_method' => 'Credit/Debit Card', 'transaction_id' => '', 'special_offer_code' => ''
	);
}

function test_api_order_money_an_order_paid_partly_by_gift_card()
{
	$row = api_order_money_test_row();
	$row['gift_card_discount'] = '25000';
	$row['total'] = '75000';

	$order = api_order_present($row);

	pg_assert_same(25000, $order['totals']['gift_card_discount'], 'the gift card part in minor units');
	pg_assert_same(0, $order['totals']['installment_charges'], 'no instalment charge');
	pg_assert_same(75000, $order['totals']['total'], 'the total as stored');
	pg_assert_same(1, $order['payment_installment'], 'a single payment');
}

function test_api_order_money_an_order_paid_in_instalments()
{
	$row = api_order_money_test_row();
	$row['installment_charges'] = '4500';
	$row['payment_installment'] = '6';
	$row['total'] = '104500';

	$order = api_order_present($row);

	pg_assert_same(4500, $order['totals']['installment_charges'], 'the instalment charge in minor units');
	pg_assert_same(0, $order['totals']['gift_card_discount'], 'no gift card');
	pg_assert_same(6, $order['payment_installment'], 'six instalments');
	pg_assert_same(104500, $order['totals']['total'], 'the total raised by the charge');
}

function test_api_order_money_a_row_without_the_columns_answers_zero()
{
	$row = api_order_money_test_row();
	unset($row['gift_card_discount'], $row['installment_charges'], $row['payment_installment']);
	$row['payment_installment'] = '';

	$order = api_order_present($row);

	pg_assert_same(0, $order['totals']['gift_card_discount'], 'zero when not read');
	pg_assert_same(0, $order['totals']['installment_charges'], 'zero when not read');
	pg_assert_same(0, $order['payment_installment'], 'an empty count is zero');
}

function test_api_order_money_fields_list_the_two_amounts()
{
	$fields = api_order_money_fields();

	pg_assert_true(in_array('gift_card_discount', $fields, true), 'gift_card_discount is a money field');
	pg_assert_true(in_array('installment_charges', $fields, true), 'installment_charges is a money field');
}

function test_api_order_money_select_reads_the_columns()
{
	$select = api_order_select(array());

	foreach (array('gift_card_discount', 'installment_charges', 'payment_installment') as $column) {
		pg_assert_true(strpos($select, 'orders.' . $column) !== false, $column . ' is selected');
	}
}

function test_api_order_money_schema_declares_them()
{
	$schema = api_order_schema();

	pg_assert_same('integer', isset($schema['totals']['gift_card_discount']) ? $schema['totals']['gift_card_discount'] : null, 'gift_card_discount is an integer');
	pg_assert_same('integer', isset($schema['totals']['installment_charges']) ? $schema['totals']['installment_charges'] : null, 'installment_charges is an integer');
	pg_assert_same('integer', isset($schema['payment_installment']) ? $schema['payment_installment'] : null, 'payment_installment is an integer');

	foreach (api_order_money_fields() as $field) {
		pg_assert_true(isset($schema['totals'][$field]), $field . ' is declared in the totals');
	}
}
