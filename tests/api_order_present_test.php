<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order's type, cancellation and refund state on the external
 * API's Order (includes/api/resources/orders.php): the presenter with and
 * without the optional columns, the select that reads only the columns the
 * site has, and the schema that declares them nullable.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

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

// An order row as api_order_select() reads it on a site without the optional
// columns.
function api_order_present_test_row()
{
	return array(
		'id' => '812', 'order_number' => '1000812', 'order_date' => '1793023380', 'status' => 'cancelled',
		'contact_id' => '0', 'member_id' => null,
		'billing_first_name' => 'Ada', 'billing_last_name' => 'Lovelace',
		'billing_email_address' => 'ada@example.com', 'billing_company' => '',
		'billing_address_1' => '', 'billing_address_2' => '', 'billing_city' => '',
		'billing_state' => '', 'billing_zip_code' => '', 'billing_country' => '',
		'billing_phone_number' => '',
		'subtotal' => '1000', 'discount' => '0', 'tax' => '180', 'shipping' => '0',
		'surcharge' => '0', 'total' => '1180',
		'payment_method' => '', 'transaction_id' => 'TY-55', 'special_offer_code' => ''
	);
}

function test_api_order_present_with_the_state_columns()
{
	$row = api_order_present_test_row();
	$row['type'] = 'marketplace';
	$row['cancelled_at'] = '1793023380';
	$row['cancellation_reason'] = 'Out of stock';
	$row['refund_status'] = 'manual_required';

	$order = api_order_present($row);

	pg_assert_same('marketplace', $order['type'], 'type as stored');
	pg_assert_same(gmdate('Y-m-d\TH:i:s\Z', 1793023380), $order['cancelled_at'], 'cancelled_at as an ISO moment');
	pg_assert_same('Out of stock', $order['cancellation_reason'], 'the reason as written');
	pg_assert_same('manual_required', $order['refund_status'], 'refund_status as stored');
}

function test_api_order_present_an_order_never_cancelled()
{
	$row = api_order_present_test_row();
	$row['status'] = 'complete';
	$row['type'] = 'local';
	$row['cancelled_at'] = null;
	$row['cancellation_reason'] = '';
	$row['refund_status'] = '';

	$order = api_order_present($row);

	pg_assert_same('local', $order['type'], 'a counter sale');
	pg_assert_same(null, $order['cancelled_at'], 'null unless cancelled');
	pg_assert_same('', $order['cancellation_reason'], 'empty when there is no reason');
	pg_assert_same('', $order['refund_status'], 'empty when no refund was attempted');
}

function test_api_order_present_without_the_state_columns()
{
	$order = api_order_present(api_order_present_test_row());

	foreach (array('type', 'cancelled_at', 'cancellation_reason', 'refund_status') as $field) {
		pg_assert_true(array_key_exists($field, $order), $field . ' is always in the answer');
		pg_assert_same(null, $order[$field], $field . ' is null on a site without its column');
	}
}

function test_api_order_select_reads_only_the_columns_the_site_has()
{
	$all = api_order_select(array('type', 'cancelled_at', 'cancellation_reason', 'refund_status'));

	foreach (array('type', 'cancelled_at', 'cancellation_reason', 'refund_status') as $column) {
		pg_assert_true(strpos($all, 'orders.' . $column . ',') !== false, $column . ' is selected when the site has it');
	}

	$none = api_order_select(array());

	foreach (array('orders.type', 'orders.cancelled_at', 'orders.cancellation_reason', 'orders.refund_status') as $column) {
		pg_assert_true(strpos($none, $column) === false, $column . ' is not selected when the site lacks it');
	}

	$some = api_order_select(array('type', 'no_such_column'));

	pg_assert_true(strpos($some, 'orders.type,') !== false, 'a present column is selected');
	pg_assert_true(strpos($some, 'no_such_column') === false, 'only the four known columns can be selected');
}

function test_api_order_schema_declares_the_state_nullable()
{
	$schema = api_order_schema();

	foreach (array('type', 'cancelled_at', 'cancellation_reason', 'refund_status') as $field) {
		pg_assert_same('string?', isset($schema[$field]) ? $schema[$field] : null, $field . ' is a nullable string');
	}
}
