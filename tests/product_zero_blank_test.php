<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the boxes whose hint says "leave blank for no limit" or "leave
 * blank for no expiration": the save stores the blank as 0 and the readers
 * take 0 as unlimited, so the edit screen shows a stored 0 as an empty box,
 * as a new product shows it (product_builder.php), and the Mailchimp card
 * does the same for its two sync boxes (includes/settings/contact.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PATH')) {
	define('PATH', '/');
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

// Every stored form of zero for the five "no limit" columns becomes blank.
function test_product_zero_blank_maps_stored_zeros_to_blank()
{
	$row = pg_pb_blank_unlimited_zeros(array(
		'number_of_payments'    => '0',
		'private_days'          => 0,
		'minimum_quantity'      => null,
		'maximum_quantity'      => '0',
		'commission_rate_limit' => '0.00',
	));

	pg_assert_same('', $row['number_of_payments']);
	pg_assert_same('', $row['private_days']);
	pg_assert_same('', $row['minimum_quantity']);
	pg_assert_same('', $row['maximum_quantity']);
	pg_assert_same('', $row['commission_rate_limit']);
}

// A real limit is kept as stored, and other columns are left alone.
function test_product_zero_blank_keeps_real_values_and_other_columns()
{
	$row = pg_pb_blank_unlimited_zeros(array(
		'number_of_payments'    => '12',
		'private_days'          => '30',
		'minimum_quantity'      => '1',
		'maximum_quantity'      => '5',
		'commission_rate_limit' => '7.50',
		'default_quantity'      => '0',
		'inventory_quantity'    => '0',
	));

	pg_assert_same('12', $row['number_of_payments']);
	pg_assert_same('30', $row['private_days']);
	pg_assert_same('1', $row['minimum_quantity']);
	pg_assert_same('5', $row['maximum_quantity']);
	pg_assert_same('7.50', $row['commission_rate_limit']);
	pg_assert_same('0', $row['default_quantity']);
	pg_assert_same('0', $row['inventory_quantity']);
}

// A row without one of the columns (an older site) gains no key.
function test_product_zero_blank_adds_no_missing_column()
{
	$row = pg_pb_blank_unlimited_zeros(array('name' => 'SKU-1'));

	pg_assert_same(array('name' => 'SKU-1'), $row);
}

// The loader runs the mapping, so every edited product gets it.
function test_product_zero_blank_runs_in_the_loader()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');

	if (!preg_match('/function pg_pb_load_product\(.*?\n}\n/s', $source, $match)) {
		pg_assert_true(false, 'pg_pb_load_product() not found');
		return;
	}

	pg_assert_true(strpos($match[0], 'pg_pb_blank_unlimited_zeros($row)') !== false);
}

// The Mailchimp boxes print a stored 0 (all orders, no limit) as blank.
function test_product_zero_blank_mailchimp_boxes_print_zero_blank()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/contact.php');

	foreach (array('mailchimp_sync_days', 'mailchimp_sync_limit') as $id) {
		pg_assert_true(preg_match('/<input\b[^>]*\bid="' . $id . '"[^>]*>/', $source, $match) === 1, $id . ' box');
		pg_assert_true(strpos($match[0], 'value="\' . h($' . $id . ' ? $' . $id . ' : \'\') . \'"') !== false, $id . ' prints 0 blank');
	}
}
