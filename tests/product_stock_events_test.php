<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a stock change announces (pg_product_stock_events(),
 * includes/fn/ecommerce.php): inventory.changed on every write of a tracked
 * product, stock.low once on the crossing of the low-stock line, nothing for
 * a product that does not track stock; and for the rule that decides
 * whether a save of the whole row moved the stock at all
 * (pg_product_stock_delta()). Also that every stock writer on the site calls
 * pg_product_stock_changed(), and that the API's own writer announces the
 * same keys.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_stock_events_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// -- pg_product_stock_events() ----------------------------------------------

function test_product_stock_events_tracked_write_announces_inventory_changed()
{
	$events = pg_product_stock_events(41, 'SKU-41', true, 5, 3, 0, 'checkout');

	pg_assert_same(1, count($events), 'one event without a threshold');
	pg_assert_same('inventory.changed', $events[0][0]);
	pg_assert_same(
		array('product_id' => 41, 'quantity' => 3, 'out_of_stock' => false, 'source' => 'checkout'),
		$events[0][1]);
}

function test_product_stock_events_sold_out_says_out_of_stock()
{
	$events = pg_product_stock_events(41, 'SKU-41', true, 2, 0, 0, 'local_sale');

	pg_assert_same(true, $events[0][1]['out_of_stock']);
	pg_assert_same(0, $events[0][1]['quantity']);
	pg_assert_same('local_sale', $events[0][1]['source']);
}

function test_product_stock_events_untracked_product_announces_nothing()
{
	pg_assert_same(array(), pg_product_stock_events(41, 'SKU-41', false, 5, 3, 5, 'barcode'));
}

function test_product_stock_events_missing_product_announces_nothing()
{
	pg_assert_same(array(), pg_product_stock_events(0, '', true, 5, 3, 5, 'panel'));
}

function test_product_stock_events_crossing_the_line_announces_stock_low_once()
{
	// 6 -> 4 with a line at 5: crossed.
	$events = pg_product_stock_events(41, 'SKU-41', true, 6, 4, 5, 'checkout');

	pg_assert_same(2, count($events), 'inventory.changed and stock.low');
	pg_assert_same('stock.low', $events[1][0]);
	pg_assert_same(
		array('product_id' => 41, 'name' => 'SKU-41', 'quantity' => 4, 'threshold' => 5),
		$events[1][1]);

	// 4 -> 2, already under the line: not again.
	$events = pg_product_stock_events(41, 'SKU-41', true, 4, 2, 5, 'checkout');
	pg_assert_same(1, count($events), 'no second stock.low under the line');

	// Landing on the line counts as crossing it.
	$events = pg_product_stock_events(41, 'SKU-41', true, 6, 5, 5, 'checkout');
	pg_assert_same(2, count($events), 'at the line is low');

	// Staying above it does not.
	$events = pg_product_stock_events(41, 'SKU-41', true, 9, 6, 5, 'checkout');
	pg_assert_same(1, count($events), 'above the line is not low');
}

function test_product_stock_events_restock_above_the_line_is_not_low()
{
	$events = pg_product_stock_events(41, 'SKU-41', true, 2, 20, 5, 'barcode');

	pg_assert_same(1, count($events));
	pg_assert_same('inventory.changed', $events[0][0]);
}

function test_product_stock_events_tracking_switched_on_under_the_line_is_a_crossing()
{
	// No level before (null): a count at or under the line is low, as the
	// stock API's set and the Workspace treat it.
	$events = pg_product_stock_events(41, 'SKU-41', true, null, 3, 5, 'panel');
	pg_assert_same(2, count($events), 'switched on with a low count');

	$events = pg_product_stock_events(41, 'SKU-41', true, null, 30, 5, 'panel');
	pg_assert_same(1, count($events), 'switched on with plenty');
}

function test_product_stock_events_no_threshold_never_announces_low()
{
	$events = pg_product_stock_events(41, 'SKU-41', true, 6, 0, 0, 'checkout');

	pg_assert_same(1, count($events));
}

function test_product_stock_events_casts_what_the_row_gives()
{
	// Column values arrive as strings; the payload carries ints and bools.
	$events = pg_product_stock_events('41', 'SKU-41', true, '6', '4', '5', 'bulk_edit');

	pg_assert_same(41, $events[0][1]['product_id']);
	pg_assert_same(4, $events[0][1]['quantity']);
	pg_assert_same(false, $events[0][1]['out_of_stock']);
	pg_assert_same(4, $events[1][1]['quantity']);
	pg_assert_same(5, $events[1][1]['threshold']);
}

// -- pg_product_stock_delta() -----------------------------------------------

function test_product_stock_delta_moved_count_gives_before_and_after()
{
	pg_assert_same(array(5, 3), pg_product_stock_delta(true, '5', true, '3'));
	pg_assert_same(array(0, 12), pg_product_stock_delta(true, 0, true, 12));
}

function test_product_stock_delta_same_count_is_no_change()
{
	pg_assert_same(null, pg_product_stock_delta(true, '5', true, '5'));
	pg_assert_same(null, pg_product_stock_delta(true, '5', true, 5));
}

function test_product_stock_delta_tracking_switched_on_has_no_before()
{
	pg_assert_same(array(null, 3), pg_product_stock_delta(false, '3', true, '3'));
	pg_assert_same(array(null, 0), pg_product_stock_delta(false, '0', true, ''));
}

function test_product_stock_delta_untracked_after_is_no_change()
{
	pg_assert_same(null, pg_product_stock_delta(true, '5', false, '0'));
	pg_assert_same(null, pg_product_stock_delta(false, '5', false, '5'));
}

// -- the writers ------------------------------------------------------------

// Every stock writer on the site goes through the one helper: a writer that
// does not leaves updated_since and the inventory.changed subscribers blind
// to its sale or correction.
function test_product_stock_site_writers_call_the_helper()
{
	$writers = array(
		'submit_order.php'               => 'checkout',
		'includes/local_sale.php'        => 'local_sale',
		'product_builder.php'            => 'panel',
		'barcode_decrease_inventory.php' => 'barcode',
		'barcode_increase_inventory.php' => 'barcode',
		'edit_products.php'              => 'bulk_edit',
	);

	foreach ($writers as $file => $source) {
		$code = product_stock_events_test_source($file);

		pg_assert_contains('pg_product_stock_changed(', $code, $file);
		pg_assert_contains("'" . $source . "'", $code, $file . ' names its source');
	}

	// The product screen announces both its save and the list's quick edit.
	pg_assert_same(2, substr_count(product_stock_events_test_source('product_builder.php'), 'pg_product_stock_changed('), 'product screen: save and quick edit');
}

// The helper's inventory.changed carries the keys the stock API's own writer
// sends, so a subscriber reads a sale and an API write the same way.
function test_product_stock_events_match_the_api_writer_keys()
{
	$api = product_stock_events_test_source('includes/api/resources/inventory.php');
	$events = pg_product_stock_events(41, 'SKU-41', true, 6, 4, 5, 'checkout');

	foreach (array('product_id', 'quantity', 'out_of_stock') as $key) {
		pg_assert_true(array_key_exists($key, $events[0][1]), 'inventory.changed carries ' . $key);
		pg_assert_contains("'" . $key . "'", $api, 'the API writer sends ' . $key);
	}

	foreach (array('product_id', 'name', 'quantity', 'threshold') as $key) {
		pg_assert_true(array_key_exists($key, $events[1][1]), 'stock.low carries ' . $key);
	}
}

// The event names the helper announces are in the catalogue; an unknown
// name is dropped without a word.
function test_product_stock_events_are_in_the_catalogue()
{
	$catalogue = product_stock_events_test_source('includes/api/outbound/webhooks.php');

	pg_assert_contains("'inventory.changed'", $catalogue);
	pg_assert_contains("'stock.low'", $catalogue);
}
