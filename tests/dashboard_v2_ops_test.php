<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Operations Desk card
 * (includes/dashboard/v2/cards/ops.php): the rows each lane makes from
 * widget 9's queries, their tones, the row limit and the "more" link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_DASHBOARD_V2')) {
	define('PG_DASHBOARD_V2', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/ops.php');

if (!defined('BASE_CURRENCY_SYMBOL')) {
	define('BASE_CURRENCY_SYMBOL', '$');
}

function test_dashboard_v2_ops_ship_rows_name_the_order_and_mark_late_ones()
{
	$now = mktime(12, 0, 0, 3, 10, 2026);
	$rows = pg_dashboard_v2_ops_ship_rows(array(
		array('order_id' => 7, 'order_number' => '1042', 'ship_to_name' => 'Ayşe Y.', 'order_date' => $now - 3 * 86400, 'pending_quantity' => 2),
		array('order_id' => 8, 'order_number' => '1043', 'ship_to_name' => '', 'order_date' => $now - 3600, 'pending_quantity' => 1),
	), $now);

	pg_assert_same('#1042 · Ayşe Y. · 2 packages', $rows[0]['text']);
	pg_assert_same('bad', $rows[0]['tone'], 'waited more than two days');
	pg_assert_same('view_order.php?id=7', $rows[0]['url']);
	pg_assert_same('#1043 · 1 package', $rows[1]['text'], 'no recipient name, no empty part');
	pg_assert_same('', $rows[1]['tone']);
}

function test_dashboard_v2_ops_refund_rows_show_what_is_owed()
{
	$now = mktime(12, 0, 0, 3, 10, 2026);
	$rows = pg_dashboard_v2_ops_refund_rows(array(
		array('id' => 9, 'order_number' => '', 'name' => ' ', 'owed' => 12345, 'cancelled_at' => $now - 600),
		array('id' => 10, 'order_number' => '1039', 'name' => 'Selin K.', 'owed' => 500, 'cancelled_at' => $now - 5 * 86400),
	), $now);

	pg_assert_same('#9 · Unknown', $rows[0]['text'], 'the id without a number, Unknown without a name');
	pg_assert_contains('123', $rows[0]['note']);
	pg_assert_same('warn', $rows[0]['tone']);
	pg_assert_same('#1039 · Selin K.', $rows[1]['text']);
	pg_assert_same('bad', $rows[1]['tone']);
}

function test_dashboard_v2_ops_stock_rows_prefer_the_title()
{
	$rows = pg_dashboard_v2_ops_stock_rows(array(
		array('id' => 3, 'name' => 'pen-case', 'short_description' => 'Leather Pen Case', 'timestamp' => 0),
		array('id' => 4, 'name' => 'e-book', 'short_description' => '', 'timestamp' => 0),
	));

	pg_assert_same('Leather Pen Case', $rows[0]['text']);
	pg_assert_same('', $rows[0]['note'], 'no time when the product never recorded one');
	pg_assert_same('e-book', $rows[1]['text']);
	pg_assert_same('edit_product.php?id=4', $rows[1]['url']);
}

function test_dashboard_v2_ops_lane_keeps_three_rows_and_offers_more()
{
	$rows = array();
	for ($i = 1; $i <= 5; $i++) {
		$rows[] = array('text' => 'Row ' . $i, 'note' => '', 'tone' => '', 'url' => 'x.php?id=' . $i);
	}

	$lane = pg_dashboard_v2_ops_lane('ship', 'To ship', 12, $rows, '12 packages', 'bad', '9 more waiting', 'view_orders.php', 'Nothing waiting');

	pg_assert_same(PG_DASHBOARD_V2_OPS_ROWS, count($lane['rows']));
	pg_assert_same('Row 3', $lane['rows'][2]['text']);
	pg_assert_same('9 more waiting', $lane['more']);
	pg_assert_same('bad', $lane['tone']);
	pg_assert_same('', $lane['empty']);
}

function test_dashboard_v2_ops_empty_lane_says_so_and_has_no_tone()
{
	$lane = pg_dashboard_v2_ops_lane('refund', 'To refund', 0, array(), 'Nothing owed', 'bad', '0 more waiting', '', 'No refunds are waiting.');

	pg_assert_same(0, $lane['count']);
	pg_assert_same('0', $lane['count_text']);
	pg_assert_same('', $lane['tone'], 'nothing waiting is not a warning');
	pg_assert_same('', $lane['more']);
	pg_assert_same('No refunds are waiting.', $lane['empty']);
}

function test_dashboard_v2_ops_card_is_built_on_widget_9s_queries()
{
	$cards = pg_dashboard_v2_cards();
	pg_assert_true($cards['ops']['built']);

	// Widget 9's lanes and the card read the same rows.
	$widget = file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_9.php');
	pg_assert_contains('$shipments = pg_dashboard_ops_shipments($row_limit + 1);', $widget);
	pg_assert_contains('$rows = pg_dashboard_ops_refund_list(10);', $widget);
	pg_assert_contains('$rows = pg_dashboard_ops_out_of_stock_list(8);', $widget);

	$card = file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/ops.php');
	foreach (array('pg_dashboard_ops_shipments(', 'pg_dashboard_ops_refund_list(', 'pg_dashboard_ops_out_of_stock_list(', 'pg_dashboard_ops_stock_counts()') as $call) {
		pg_assert_contains($call, $card);
	}
}
