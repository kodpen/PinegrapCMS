<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an account an order's auto-registration opens is handed to the
 * customer with a set password link and never with its password, and that
 * the store's BCC addresses get a copy of the receipt without the link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No account: one e-mail to the customer with the BCC, as before.
function test_order_receipt_copies_without_new_account()
{
	$copies = pg_order_receipt_copies('c@example.com', array('shop@example.com', 'x@example.com'), false);

	pg_assert_same(array(array('to' => 'c@example.com', 'bcc' => array('shop@example.com', 'x@example.com'), 'audience' => 'customer')), $copies);
}

// A new account: the customer's copy alone, then one store copy per address.
function test_order_receipt_copies_split_for_new_account()
{
	$copies = pg_order_receipt_copies('c@example.com', array('shop@example.com', '', 'x@example.com'), true);

	pg_assert_same(3, count($copies));
	pg_assert_same(array('to' => 'c@example.com', 'bcc' => array(), 'audience' => 'customer'), $copies[0]);
	pg_assert_same(array('to' => 'shop@example.com', 'bcc' => array(), 'audience' => 'store'), $copies[1]);
	pg_assert_same(array('to' => 'x@example.com', 'bcc' => array(), 'audience' => 'store'), $copies[2]);
}

// A new account and no BCC: just the customer's copy.
function test_order_receipt_copies_new_account_without_bcc()
{
	pg_assert_same(array(array('to' => 'c@example.com', 'bcc' => array(), 'audience' => 'customer')), pg_order_receipt_copies('c@example.com', array(), true));
}

// The customer's e-mail always; the page only for the customer's own
// session; the store's copy never.
function test_order_new_account_for_customer()
{
	pg_assert_true(pg_order_new_account_for_customer('customer', false), 'customer e-mail');
	pg_assert_true(pg_order_new_account_for_customer('customer', true), 'customer e-mail of a ghosted order');
	pg_assert_true(pg_order_new_account_for_customer('screen', false), 'own receipt page');
	pg_assert_false(pg_order_new_account_for_customer('screen', true), 'staff member ordering for the customer');
	pg_assert_false(pg_order_new_account_for_customer('store', false), 'store copy');
}

// The audience flag keeps only known values and defaults to the page.
function test_order_receipt_audience_flag()
{
	pg_assert_same('screen', pg_order_receipt_audience());
	pg_assert_same('store', pg_order_receipt_audience('store'));
	pg_assert_same('store', pg_order_receipt_audience('nonsense'));
	pg_assert_same('screen', pg_order_receipt_audience('screen'));
}

// The customer's block: address and link, no password; others: the account only.
function test_order_new_account_block()
{
	$text = pg_order_new_account_block('c@example.com', 'https://example.com/s?k=abc', true, false);

	pg_assert_contains('Email: c@example.com', $text);
	pg_assert_contains('Set your password: https://example.com/s?k=abc', $text);
	pg_assert_false(strpos($text, 'Password:') !== false, 'no password line');

	$html = pg_order_new_account_block('c<d@example.com', 'https://example.com/s?x=1&k=abc', true, true);

	pg_assert_contains('c&lt;d@example.com', $html);
	pg_assert_contains('<a href="https://example.com/s?x=1&amp;k=abc">Set your password</a>', $html);

	$store = pg_order_new_account_block('c@example.com', 'https://example.com/s?k=abc', false, false);

	pg_assert_contains('A new account was created with this order: c@example.com', $store);
	pg_assert_false(strpos($store, 'k=abc') !== false, 'no link for the store');
	pg_assert_contains('Forgot password', pg_order_new_account_block('c@example.com', '', true, true));
	pg_assert_same('', pg_order_new_account_block('', 'https://example.com/s?k=abc', true, false));
}

// No order screen reads a password from the session any more.
function test_order_no_password_in_session_or_output()
{
	$files = array(
		'submit_order.php',
		'get_order_receipt.php',
		'get_order_receipt_in_plain_text.php',
		'get_view_order_screen_content.php',
		'includes/templates/order_receipt.php',
	);

	foreach ($files as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match('/\[\'auto_registration\'\]\[\'password\'\]/', $source), $file . ': session password');
		pg_assert_same(0, preg_match('/\$random_password/', $source), $file . ': random password');
	}
}

// The account is created without a password (algo 3), like the form's, so
// the signed-in customer's change password screen asks for no current
// password; the customer's copy, which may carry the link, is sent as secret
// so a failed delivery never writes it to the activity log or the notice.
function test_order_account_has_no_password_and_customer_copy_is_secret()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');

	pg_assert_same(1, preg_match('/create_member_user\(array\(/', $source), 'account is created');
	pg_assert_same(0, preg_match('/create_member_user\(array\((?:(?!\)\);).)*\'password\'/s', $source), 'no password passed');
	// The receipt is e-mailed by pg_order_complete_receipt(), which
	// pg_order_complete() runs; submit_order() hands it the account the
	// order opened.
	pg_assert_contains("'new_account' => \$registration['account'],", (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php'), 'the receipt is told about the new account');
	pg_assert_contains("\$context['receipt'], \$billing_email_address, is_array(\$context['new_account']) ? \$context['new_account'] : array());", $source, 'the completion passes it on');
	pg_assert_contains("'secret' => ((\$receipt_copy['audience'] === 'customer') && \$has_account)", $source, 'customer copy is secret');
}
