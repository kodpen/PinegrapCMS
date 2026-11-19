<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Order Report's filters in includes/fn/order_report.php: the
 * column each filter field compares, and that the screen leaves out and
 * refuses a filter with no known field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_report_filters_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order_report.php');
}

// An empty or unknown field has no column: such a filter is left out of the
// SQL instead of building "IFNULL(, '')", which failed the whole report.
function test_order_report_filter_column_unknown_field()
{
	pg_assert_same('', pg_order_report_filter_column(''), 'empty field');
	pg_assert_same('', pg_order_report_filter_column('no_such_field'), 'unknown field');
	pg_assert_same('', pg_order_report_filter_column(array('billing_city')), 'an array is no field');
	pg_assert_same('orders.billing_city', pg_order_report_filter_column('billing_city'), 'billing city');
	pg_assert_same('orders.status', pg_order_report_filter_column('order_status'), 'order status');
	pg_assert_same('orders.opt_in', pg_order_report_filter_column('opt_in_status'), 'opt-in status');
	pg_assert_same('orders.tax_exempt', pg_order_report_filter_column('tax_status'), 'tax status');
	pg_assert_same('order_items.product_name', pg_order_report_filter_column('product_name'), 'product name');
	pg_assert_same('ship_tos.state', pg_order_report_filter_column('shipping_state'), 'shipping state');
	pg_assert_same('ship_tos.ship_to_name', pg_order_report_filter_column('ship_to_name'), 'ship to name');
	pg_assert_contains('orders.order_date', pg_order_report_filter_column('order_date', 0, 0), 'order date');
}

// Every field the filter row offers has a column.
function test_order_report_filter_column_covers_every_offered_field()
{
	preg_match_all("/\\\$field_options\\[\\] = array\\('name' => [^;]*?'value' => '([a-z0-9_]+)'/", order_report_filters_source(), $matches);

	pg_assert_true(count($matches[1]) > 40, 'the offered fields are read');

	foreach ($matches[1] as $field) {
		pg_assert_true(pg_order_report_filter_column($field, 0, 0) !== '', 'a column for ' . $field);
	}
}

// The report leaves a stored filter with no field out, so it still opens;
// the save refuses one with the visitor report's message.
function test_order_report_screen_skips_and_refuses_a_filter_with_no_field()
{
	$source = order_report_filters_source();

	pg_assert_contains("\$operand_1 = pg_order_report_filter_column(\$filter['field'], \$site_time_from, \$site_time_to);", $source, 'the WHERE loop reads the column');
	pg_assert_contains("if (\$operand_1 === '') {\n            continue;\n        }", $source, 'a filter with no column adds nothing');
	pg_assert_contains("if (pg_order_report_filter_column(\$liveform->get_field_value('filter_' . \$i . '_field')) === '') {", $source, 'the save checks the field');
	pg_assert_contains("lang('Please select a field for each filter.')", $source, 'with the shared message');
}

// "Does not contain" and "is not equal to" on a field of the order's lines
// or recipients keep an order only when none of them matches: compared row
// by row through the join, the order's other product passed and an order
// holding the excluded product was listed.
function test_order_report_negative_filter_on_lines_asks_that_none_matches()
{
	pg_assert_same(
		"NOT EXISTS (SELECT 1 FROM orders AS filter_order LEFT JOIN order_items ON order_items.order_id = filter_order.id WHERE (filter_order.id = orders.id) AND (IFNULL(order_items.product_name, '') LIKE '%PGS-1003%'))",
		pg_order_report_filter_sql('order_items.product_name', 'does not contain', 'PGS-1003'),
		'no line holds the product'
	);
	pg_assert_same(
		"NOT EXISTS (SELECT 1 FROM orders AS filter_order LEFT JOIN ship_tos ON ship_tos.order_id = filter_order.id WHERE (filter_order.id = orders.id) AND (IFNULL(ship_tos.state, '') = 'CA'))",
		pg_order_report_filter_sql('ship_tos.state', 'is not equal to', 'CA'),
		'no recipient in the state'
	);
}

// The other comparisons, and every comparison of the order's own columns,
// stay the row-by-row condition.
function test_order_report_other_filters_keep_their_condition()
{
	foreach (array('contains', 'is equal to', 'is less than', 'is greater than or equal to') as $operator) {
		pg_assert_same(
			prepare_sql_operation($operator, 'order_items.product_name', 'Mug'),
			pg_order_report_filter_sql('order_items.product_name', $operator, 'Mug'),
			'product name ' . $operator
		);
	}

	foreach (array('does not contain', 'is not equal to', 'contains') as $operator) {
		pg_assert_same(
			prepare_sql_operation($operator, 'orders.billing_city', 'Bursa'),
			pg_order_report_filter_sql('orders.billing_city', $operator, 'Bursa'),
			'billing city ' . $operator
		);
	}

	pg_assert_contains("pg_order_report_filter_sql(\$operand_1, \$filter['operator'], \$operand_2)", order_report_filters_source(), 'the screen builds its conditions with it');
}
