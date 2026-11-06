<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the claim the order screen's Paraşüt buttons take on an order
 * (includes/fn/parasut.php): one Create Invoice or E-Shipment of an order
 * runs at a time, so two presses at once cannot write two sales invoices,
 * two e-documents or two waybills.
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
 * @return string
 */
function parasut_press_claim_test_source($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

// Lock names belong to the whole database server: the name carries the
// database and the order, within the 64 characters MySQL takes.
function test_parasut_order_press_lock_name()
{
	pg_assert_true(function_exists('parasut_order_press_lock_name'), 'parasut_order_press_lock_name() exists');

	$one = parasut_order_press_lock_name('shop@localhost', 12);
	pg_assert_true(strlen($one) <= 64, 'within 64 characters');
	pg_assert_same($one, parasut_order_press_lock_name('shop@localhost', '12'));
	pg_assert_true($one !== parasut_order_press_lock_name('shop@localhost', 13), 'one lock per order');
	pg_assert_true($one !== parasut_order_press_lock_name('other@localhost', 12), 'one lock per database');
}

// The press runs under the claim, which is released after it.
function test_parasut_order_press_claimed_runs_and_releases()
{
	pg_assert_true(function_exists('parasut_order_press_claimed'), 'parasut_order_press_claimed() exists');

	$steps = array();
	$lock = function ($name) use (&$steps) {
		$steps[] = 'lock';

		return true;
	};
	$release = function ($name) use (&$steps) {
		$steps[] = 'release';
	};
	$run = function () use (&$steps) {
		$steps[] = 'run';

		return array('success' => true, 'parasut_invoice_id' => '7');
	};

	$result = parasut_order_press_claimed(5, $run, $lock, $release);
	pg_assert_same(array('success' => true, 'parasut_invoice_id' => '7'), $result);
	pg_assert_same(array('lock', 'run', 'release'), $steps);
}

// A press that cannot take the claim (another press of the same order is
// running) is refused before anything is read or written, and releases
// nothing it does not hold.
function test_parasut_order_press_claimed_refuses_a_second_press()
{
	$steps = array();
	$lock = function ($name) {
		return false;
	};
	$release = function ($name) use (&$steps) {
		$steps[] = 'release';
	};
	$run = function () use (&$steps) {
		$steps[] = 'run';

		return array('success' => true);
	};

	$result = parasut_order_press_claimed(5, $run, $lock, $release);
	pg_assert_false($result['success']);
	pg_assert_same(null, $result['parasut_invoice_id']);
	pg_assert_same(null, $result['parasut_shipment_id']);
	pg_assert_contains('Another Paraşüt send of this order is running', $result['error']);
	pg_assert_same(array(), $steps);
}

// A press that throws still releases the claim.
function test_parasut_order_press_claimed_releases_after_an_exception()
{
	$released = 0;
	$lock = function ($name) {
		return true;
	};
	$release = function ($name) use (&$released) {
		$released++;
	};
	$run = function () {
		throw new RuntimeException('boom');
	};

	$thrown = false;

	try {
		parasut_order_press_claimed(5, $run, $lock, $release);
	} catch (RuntimeException $e) {
		$thrown = true;
	}

	pg_assert_true($thrown, 'the exception goes on');
	pg_assert_same(1, $released);
}

// Both buttons take the claim before the order is read: the press that
// waited reads the order the first one left.
function test_parasut_create_functions_take_the_claim()
{
	foreach (array('invoice', 'shipment') as $what) {
		$body = parasut_press_claim_test_source('parasut_create_' . $what);
		pg_assert_contains('function parasut_create_' . $what . '($order_id, &$notices = array())', $body);
		pg_assert_contains('return parasut_order_press_claimed($order_id, function () use ($order_id, &$notices) {', $body);
		pg_assert_contains('return _parasut_create_' . $what . '_claimed($order_id, $notices);', $body);

		$claimed = parasut_press_claim_test_source('_parasut_create_' . $what . '_claimed');
		pg_assert_contains('function _parasut_create_' . $what . '_claimed($order_id, &$notices)', $claimed);
	}
}
