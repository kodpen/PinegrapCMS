<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order's auto-registration (pg_order_auto_register() in
 * includes/fn/order_completion.php), shared by the checkout and a payment
 * service's message: an existing account is linked and never duplicated, a
 * new one is opened without signing anyone in or setting a cookie, and the
 * receipt e-mail names the account the completion hands it, on both paths.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a named function in a file, from its signature to the closing
// brace at the start of a line; '' when it is not there.
function order_completion_account_test_function_text($path, $name)
{
	$source = (string) file_get_contents($path);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, ($end === false ? strlen($source) : $end + 3) - $start);
}

// An account with the address is linked; only an unknown, unblocked address
// opens one.
function test_order_auto_register_action()
{
	pg_assert_same('existing', pg_order_auto_register_action('c@example.com', 17, false), 'existing account');
	pg_assert_same('existing', pg_order_auto_register_action('c@example.com', 17, true), 'existing account, address blocked since');
	pg_assert_same('new', pg_order_auto_register_action('c@example.com', 0, false), 'no account');
	pg_assert_same('blocked', pg_order_auto_register_action('c@example.com', 0, true), 'blocked address');
	pg_assert_same('', pg_order_auto_register_action('', 0, false), 'no address');
}

// The shared function opens an account only after finding none, and never
// signs anyone in or sets a cookie.
function test_order_auto_register_is_session_free()
{
	$body = order_completion_account_test_function_text(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php', 'pg_order_auto_register');

	pg_assert_true($body !== '', 'the function is there');

	foreach (array('$_SESSION', '$_COOKIE', 'setcookie(', 'pg_session_sign_in(', 'pg_login_set_device_cookie(') as $needle) {
		pg_assert_false(strpos($body, $needle) !== false, 'no ' . $needle);
	}

	pg_assert_true(strpos($body, "if (\$action === 'existing') {") < strpos($body, 'create_member_user('), 'an existing account returns before any account is created');
	pg_assert_contains('pg_set_password_link_issue($user_id);', $body, 'the set password link is issued');
}

// The checkout keeps the sign-in and device cookie around the shared call,
// for a new account and a visitor who is not ghosting.
function test_order_auto_register_checkout_signs_the_visitor_in()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$call = strpos($source, '$registration = pg_order_auto_register($billing_email_address, $contact_id);');
	$sign_in = strpos($source, "pg_session_sign_in(\$registration['user_id'], \$registration['username']);");

	pg_assert_true($call !== false, 'the checkout calls the shared function');
	pg_assert_true(($sign_in !== false) && ($sign_in > $call), 'then signs the visitor in');
	pg_assert_true(strpos($source, "pg_login_set_device_cookie((int) \$_SESSION['sessionuserid'], false);") > $sign_in, 'and sets the device cookie');
	pg_assert_same(false, strpos($source, 'create_member_user('), 'no account is created in the checkout itself');
	pg_assert_contains("\$_SESSION['software']['auto_registration'] = \$registration['account'];", $source, 'the receipt page reads the account from the session');
}

// A receipt drawn with an account handed over names that account, not the
// session's; an empty one names none; cleared, the receipt page reads the
// session again.
function test_order_receipt_new_account_from_context()
{
	$saved = $_SESSION ?? null;
	$_SESSION = array('software' => array('auto_registration' => array('email_address' => 'session@example.com', 'user_id' => 5, 'ghost' => true)));

	pg_assert_same('session@example.com', pg_order_new_account_email(), 'receipt page: the session');

	pg_order_receipt_new_account(array('email_address' => 'payment@example.com', 'user_id' => 9));
	pg_order_receipt_audience('customer');
	pg_assert_same('payment@example.com', pg_order_new_account_email(), 'e-mail: the completion\'s account');
	pg_assert_true(pg_order_new_account_drawn_for_customer(), 'the customer\'s copy carries the link');

	pg_order_receipt_audience('store');
	pg_assert_false(pg_order_new_account_drawn_for_customer(), 'the store\'s copy does not');

	pg_order_receipt_new_account(array());
	pg_assert_same('', pg_order_new_account_email(), 'an order that opened none names none');

	pg_order_receipt_new_account(false);
	pg_order_receipt_audience('screen');
	pg_assert_same('session@example.com', pg_order_new_account_email(), 'cleared: the session again');

	$_SESSION = array();
	pg_order_receipt_new_account(array('email_address' => 'payment@example.com', 'user_id' => 9));
	pg_order_receipt_audience('customer');
	pg_assert_same('payment@example.com', pg_order_new_account_email(), 'a request without a session');
	pg_order_receipt_new_account(false);
	pg_order_receipt_audience('screen');

	$_SESSION = $saved;
}

// The receipt sets the account around every copy it draws, from its
// argument, on both paths.
function test_order_receipt_draws_the_handed_account()
{
	$path = PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php';
	$body = order_completion_account_test_function_text($path, 'pg_order_complete_receipt');
	$set = strpos($body, 'pg_order_receipt_new_account($has_account ? $new_account : array());');

	pg_assert_true($set !== false, 'the account is set for the drawing');
	pg_assert_true($set < strpos($body, 'get_order_receipt_in_plain_text($order_id)'), 'before the plain-text receipt');
	pg_assert_true($set < strpos($body, 'get_page_content('), 'before the HTML receipt');
	pg_assert_true(strpos($body, 'pg_order_receipt_new_account(false);') > strpos($body, 'get_page_content('), 'cleared after it');

	pg_assert_contains("'new_account' => \$registration['account'],", order_completion_account_test_function_text($path, 'pg_order_complete_from_payment'), 'the payment message hands its account');
	pg_assert_contains("'new_account' => \$registration['account'],", (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php'), 'the checkout hands its account');
}
