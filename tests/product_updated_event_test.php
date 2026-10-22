<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel's product writers announce product.updated the way
 * the API (api_products_update()) and the Workspace do: the product screen's
 * save (pg_pb_update_product()) and the product list's quick edit
 * (pg_pb_quick_update(), only when the cell really changed), both with
 * {id, name}. Both functions query the database, so their source is read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_updated_event_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function product_updated_event_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_product_updated_event_product_screen_save_announces_it()
{
	$body = product_updated_event_test_body(product_updated_event_test_source('product_builder.php'), 'pg_pb_update_product');

	pg_assert_true($body !== '', 'pg_pb_update_product() found');
	pg_assert_contains(
		"pg_announce('product.updated', array('id' => (int) \$product_id, 'name' => \$product['name']))",
		$body,
		'the save announces {id, name}');

	// After the row and everything hanging off it is written, so a receiver
	// that reads the product back sees the saved version.
	$announce = strpos($body, "pg_announce('product.updated'");
	$write = strpos($body, "pg_pb_update_row('products'");

	pg_assert_true(($write !== false) && ($announce > $write), 'announced after the row is written');
}

function test_product_updated_event_quick_edit_announces_only_a_change()
{
	$body = product_updated_event_test_body(product_updated_event_test_source('product_builder.php'), 'pg_pb_quick_update');

	pg_assert_true($body !== '', 'pg_pb_quick_update() found');
	pg_assert_contains(
		"pg_announce('product.updated', array('id' => (int) \$product_id, 'name' => \$current['name']))",
		$body,
		'the quick edit announces {id, name}');

	// Inside "if ($changed)": the same value again writes and announces nothing.
	$changed = strpos($body, 'if ($changed) {');
	$announce = strpos($body, "pg_announce('product.updated'");
	$write = strpos($body, "pg_pb_update_row('products'");

	pg_assert_true(($changed !== false) && ($write > $changed) && ($announce > $write), 'announced inside the change branch, after the write');
	pg_assert_same(1, substr_count($body, "pg_announce('product.updated'"), 'announced once');
}

// The payload carries the keys the API's own update sends, so a subscriber
// reads a panel save and an API write the same way; and the event is one the
// catalogue knows and the Workspace listens to.
function test_product_updated_event_matches_the_api_and_the_catalogue()
{
	$api = product_updated_event_test_source('includes/api/resources/products.php');

	pg_assert_contains("api_webhook_enqueue('product.updated', array('id' => ", $api, 'the API announces it');
	pg_assert_contains("'name' => ", $api);

	pg_assert_contains("'product.updated'", product_updated_event_test_source('includes/api/outbound/webhooks.php'), 'in the catalogue');
	pg_assert_true(in_array('product.updated', pg_event_workspace_events(), true), 'the Workspace listens to it');
}
