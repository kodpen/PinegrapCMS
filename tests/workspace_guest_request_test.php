<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the guest page (workspace_guest.php) reads the strings of a
 * request it did not make itself: a value sent as a list or an object, in
 * the JSON body or as the guest cookie, reads as nothing rather than as the
 * word "Array" with a PHP warning.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in this file runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/guests.php');

function test_ws_guest_request_text_keeps_a_string()
{
	pg_assert_same('send', ws_guest_request_text('send'));
	pg_assert_same('', ws_guest_request_text(''));
}

function test_ws_guest_request_text_reads_a_list_or_object_as_nothing()
{
	pg_assert_same('', ws_guest_request_text(array('send')));
	pg_assert_same('', ws_guest_request_text(array('a' => 'b')));
	pg_assert_same('', ws_guest_request_text(null));
}

function test_ws_guest_request_text_reads_a_number_as_its_digits()
{
	pg_assert_same('5', ws_guest_request_text(5));
	pg_assert_same('', ws_guest_request_text(true));
}
