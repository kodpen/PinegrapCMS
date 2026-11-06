<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address lock of label creation
 * (pg_shipment_label_create() in includes/fn/shipment_labels.php): the
 * active-label check and the service call run under one lock per address,
 * so a double press, a bulk run and an API call at once buy one label; a
 * create that finds the lock taken is refused as busy, and the API answers
 * it as 409 label_busy. No database is reached: the locked function is read
 * as source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_labels.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');

function test_shipment_label_create_takes_the_address_lock_before_the_check()
{
	$source = pg_test_function_source('pg_shipment_label_create');
	$lock = strpos($source, 'GET_LOCK');
	$release = strpos($source, 'RELEASE_LOCK');

	pg_assert_true($lock !== false, 'create takes a lock');
	pg_assert_true($release !== false, 'create releases it');
	pg_assert_true(strpos($source, 'pg_shipment_label_lock_name(') !== false, 'the lock is the address\'s');
	pg_assert_true(strpos($source, "GET_LOCK('\" . e(\$lock) . \"', 0)") !== false, 'a second create does not wait for the service');

	$work = strpos($source, 'pg_shipment_label_create_locked(');
	pg_assert_true($work !== false && $lock < $work && $work < $release, 'the check and the service call run inside the lock');
	pg_assert_true(strpos($source, 'pg_shipment_label_create_check(') === false, 'no check outside the lock');

	$locked = pg_test_function_source('pg_shipment_label_create_locked');
	$check = strpos($locked, 'pg_shipment_label_create_check(');
	$open = strpos($locked, 'pg_shipment_label_open(');
	pg_assert_true($check !== false && $open !== false && $check < $open, 'the locked part checks, then opens');
}

function test_shipment_label_busy_refusal_and_api_answer()
{
	$refusal = pg_shipment_label_busy_refusal();

	pg_assert_same('busy', $refusal['code']);
	pg_assert_same('A label is being created for this address. Wait a moment, then open the order again.', $refusal['error']);
	pg_assert_same(false, $refusal['ok']);
	pg_assert_same(array('ok', 'error', 'code', 'unclear', 'id', 'number', 'row'), array_keys($refusal), 'the full shape of a create result');

	// The locked-out create returns the refusal as it is: a "+" with an
	// empty result on its left would keep the empty code and error.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/shipment_labels.php');
	pg_assert_same(1, preg_match('/GET_LOCK[^\n]*\n\s*return pg_shipment_label_busy_refusal\(\);/', $source), 'the busy create returns the refusal unmixed');
	pg_assert_same(array(409, 'label_busy', 'x'), api_order_label_refusal(array('code' => 'busy', 'error' => 'x')));
}
