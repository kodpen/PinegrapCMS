<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order exports of view_orders.php (multiple files, single file) read
 * each order, recipient, item and tracking number once. The product name and
 * shipping filters join order_items and ship_tos, one row per item or
 * recipient; before, an order with two matching items, or a recipient of an
 * order with several, was written to the CSV twice. Every export query that
 * carries one of those joins selects with $export_distinct now.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each double-quoted SELECT of the file that carries the filter joins.
function view_orders_export_distinct_queries()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	preg_match_all('/"SELECT(.*?)";/s', $source, $matches);

	$queries = array();
	foreach ($matches[0] as $query) {
		if ((strpos($query, '$join_order_items') !== false) || (strpos($query, '$join_ship_tos') !== false)) {
			$queries[] = $query;
		}
	}

	return $queries;
}

function test_view_orders_export_distinct_every_joined_query()
{
	$queries = view_orders_export_distinct_queries();

	pg_assert_true(count($queries) >= 8, 'the export and list queries are found');

	foreach ($queries as $query) {
		$collapsed = (strpos($query, 'SELECT {$export_distinct}') === 0)
			|| (stripos($query, 'GROUP BY') !== false)
			|| (stripos($query, 'DISTINCT') !== false);

		pg_assert_true($collapsed, 'one row per record: ' . substr(preg_replace('/\s+/', ' ', $query), 0, 60));
	}
}

// The switch is on only while a join can repeat rows.
function test_view_orders_export_distinct_switch()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');

	pg_assert_contains("\$export_distinct = ((\$join_order_items !== '') || (\$join_ship_tos !== '')) ? 'DISTINCT ' : '';", $source);
}
