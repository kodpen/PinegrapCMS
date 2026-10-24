<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's legacy Paraşüt E-Shipment
 * (includes/fn/parasut.php): the stock movement line both shipment
 * documents carry, the product's tracking check, and, read from the source,
 * the two shipment_documents requests and the single retry after a 429.
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
 * The source of one function of includes/fn/parasut.php, from its
 * declaration to the next one.
 *
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_shipment_test_source($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

// A shipment document's line is a stock movement naming the product and the
// warehouse: Paraşüt answers a shipment_document_details line with 422
// "Stok kalemleri doldurulmalı".
function test_parasut_shipment_stock_movement_names_product_warehouse_and_quantity()
{
	$line = parasut_shipment_stock_movement(9001, 77, 3);

	pg_assert_same(array(
		'type' => 'stock_movements',
		'attributes' => array('quantity' => 3),
		'relationships' => array(
			'product' => array('data' => array('type' => 'products', 'id' => '9001')),
			'warehouse' => array('data' => array('type' => 'warehouses', 'id' => '77')),
		),
	), $line);

	$json = (string) json_encode($line);
	pg_assert_false(strpos($json, 'details') !== false, 'no shipment_document_details in the line');
	pg_assert_false(strpos($json, 'unit') !== false, 'no detail unit in the line');
}

// The opening-stock inflow and the outgoing document both send their lines
// as relationships.stock_movements, built by the one helper.
function test_parasut_shipment_documents_send_stock_movements()
{
	foreach (array('parasut_ensure_product_stock_tracking', 'parasut_create_shipment') as $function) {
		$body = parasut_shipment_test_source($function);

		pg_assert_true($body !== '', $function . '() is found');
		pg_assert_contains("_parasut_prefix() . '/shipment_documents'", $body);
		pg_assert_contains('parasut_shipment_stock_movement(', $body);
		pg_assert_contains("'stock_movements' => ", $body);
		pg_assert_false(strpos($body, 'shipment_document_details') !== false, $function . '() builds no detail line');
		pg_assert_false(strpos($body, "'details' =>") !== false, $function . '() sends no relationships.details');
	}
}

// A stock movement names its product, so an outgoing line whose product
// Paraşüt does not have is refused before the document is posted, not sent
// without one.
function test_parasut_create_shipment_refuses_a_line_without_a_product()
{
	$body = parasut_shipment_test_source('parasut_create_shipment');
	$refusal = strpos($body, "if (\$item_parasut_product_id === '') {");
	$post = strpos($body, "_parasut_request('POST'");

	pg_assert_true(($refusal !== false) && ($post !== false) && ($refusal < $post), 'a line without a product is refused before the POST');
	pg_assert_false(strpos($body, 'if ($item_parasut_product_id) {') !== false, 'no line goes on without a product');
}

// Paraşüt v4 products carry inventory_tracking and no product_type (a GET
// answers null), so the tracking check reads inventory_tracking alone.
function test_parasut_product_stock_tracked_reads_inventory_tracking_alone()
{
	pg_assert_true(parasut_product_stock_tracked(array('inventory_tracking' => true, 'product_type' => null)));
	pg_assert_true(parasut_product_stock_tracked(array('inventory_tracking' => true)));
	pg_assert_false(parasut_product_stock_tracked(array('inventory_tracking' => false, 'product_type' => 'physical')));
	pg_assert_false(parasut_product_stock_tracked(array('inventory_tracking' => null)));
	pg_assert_false(parasut_product_stock_tracked(array()));
	pg_assert_false(parasut_product_stock_tracked(null));
}

// A tracked product is not written again on every press; one that is not
// gets inventory_tracking alone, the attribute v4 has.
function test_parasut_stock_tracking_put_sends_inventory_tracking_only()
{
	$body = parasut_shipment_test_source('parasut_ensure_product_stock_tracking');

	pg_assert_contains('$is_tracked = parasut_product_stock_tracked($attrs);', $body);
	pg_assert_contains("'attributes' => ['inventory_tracking' => true],", $body);
	pg_assert_false(strpos($body, 'product_type') !== false, 'no product_type is read or sent');
}

// A 429 is a request Paraşüt did not process, so it is sent once more after
// a full rate window, as the e-document driver does; a second 429 is
// returned.
function test_parasut_request_retries_once_after_429()
{
	$body = parasut_shipment_test_source('_parasut_request');

	pg_assert_contains('function _parasut_request($method, $url, $body = null, $token = null, $retry_429 = true)', $body);
	pg_assert_contains("if ((\$http_code === 429) && \$retry_429) {", $body);
	pg_assert_contains('sleep((int) ceil(PARASUT_RATE_WINDOW));', $body);
	pg_assert_contains('return _parasut_request($method, $url, $body, $token, false);', $body);
}
