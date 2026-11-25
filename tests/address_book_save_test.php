<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_address_book_writable() (includes/fn/shipping.php): the
 * shipping steps copy a recipient's address into the signed-in customer's
 * address book only when it is one the address book screen would accept,
 * so a refused or half-typed step never overwrites a saved address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _address_book_save_test_values()
{
	return array(
		'first_name' => 'Ayşe',
		'last_name'  => 'Yılmaz',
		'address_1'  => 'Atatürk Cad. 5',
		'city'       => 'Kadıköy',
		'state'      => 'IST',
		'zip_code'   => '34710',
		'country'    => 'TR',
	);
}

function test_address_book_writable_takes_a_full_address()
{
	pg_assert_true(pg_address_book_writable(_address_book_save_test_values(), true, true), 'every field given');
}

function test_address_book_writable_refuses_a_missing_field()
{
	foreach (array('first_name', 'last_name', 'address_1', 'city', 'country') as $field) {
		$values = _address_book_save_test_values();
		$values[$field] = '  ';
		pg_assert_false(pg_address_book_writable($values, false, false), $field . ' empty');

		unset($values[$field]);
		pg_assert_false(pg_address_book_writable($values, false, false), $field . ' not posted');
	}
}

function test_address_book_writable_state_and_zip_only_when_required()
{
	$values = _address_book_save_test_values();
	$values['state'] = '';
	$values['zip_code'] = '';

	pg_assert_true(pg_address_book_writable($values, false, false), 'neither required');
	pg_assert_false(pg_address_book_writable($values, true, false), 'state required');
	pg_assert_false(pg_address_book_writable($values, false, true), 'zip code required');
}

function test_address_book_writable_reads_a_list_as_empty()
{
	$values = _address_book_save_test_values();
	$values['address_1'] = array('x');

	pg_assert_false(pg_address_book_writable($values, false, false), 'a list is no address');
}

// Both shipping steps ask the rule before the address book write; the
// classic step also waits for a form without errors.
function test_address_book_save_is_guarded_on_both_steps()
{
	$classic = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shipping_address_and_arrival.php');
	pg_assert_contains("if (USER_LOGGED_IN and !\$ghost and (\$liveform->check_form_errors() == false) and pg_address_book_writable(", $classic, 'classic step');

	$express = (string) file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php');
	pg_assert_contains("if (USER_LOGGED_IN and !\$ghost and pg_address_book_writable(", $express, 'express order');
}
