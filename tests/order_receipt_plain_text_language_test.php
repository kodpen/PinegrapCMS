<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The plain-text order receipt (the receipt e-mail's text body,
 * get_order_receipt_in_plain_text()) speaks the language the HTML receipt
 * speaks: every label through lang(), the country through
 * pg_geo_country_label(), and the long date's "at" through a lang() key in
 * get_absolute_time(). Before, the labels, the stored country name
 * ("Turkey") and the "at" went out in English whatever the order's language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// The source with its comments blanked out.
function order_receipt_plain_text_language_code($file)
{
	$code = '';
	foreach (token_get_all((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file)) as $token) {
		if (is_array($token)) {
			$code .= in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true) ? preg_replace('/[^\n]/', ' ', $token[1]) : $token[1];
		} else {
			$code .= $token;
		}
	}
	return $code;
}

// No English label is written as a bare literal any more.
function test_order_receipt_plain_text_language_no_english_literals()
{
	// A key inside lang('...') is the translated label, not a literal.
	$code = preg_replace("/lang\\('[^']*'\\)/", 'lang()', order_receipt_plain_text_language_code('get_order_receipt_in_plain_text.php'));

	$literals = array(
		"'Order Number: '", "'Order Date: '", "'Order Totals:'", "'Subtotal: '", "'Total: '",
		"'Billing Information:'", "'Ship to'", "'Shipping: '", "'Discount: -'", "'Tax: '",
		"'Gift Card'", "'Surcharge: '", "'Recurring Charges:'", "'Payment Schedule: '",
		"'Amount: '", "'Recipient Email: '", "'Your Name: '", "'Message:'", "'Delivery Date: '",
		"'Immediate'", "'Phone: '", "'Fax: '", "'PO Number: '", "'Tax-Exempt'",
		"'Applied Gift Cards:'", "Remaining Balance: ", "'Card Type: '", "'Card Number: '",
		"'Payment Information:'", "'Payment Method: '", "' of '", "', Payments: '", "', Start: '",
		"'; Requested Arrival Date: '", "' Subtotal: '", "' Tax: '", "' Total: '",
	);

	foreach ($literals as $literal) {
		pg_assert_same(false, strpos($code, $literal), $literal . ' is written in English');
	}
}

// Each label goes through lang(), with a key the Turkish shards know.
function test_order_receipt_plain_text_language_labels_through_lang()
{
	$code = order_receipt_plain_text_language_code('get_order_receipt_in_plain_text.php');
	$map = pg_test_language_map('tr');

	$keys = array(
		'Order Number', 'Order Date', 'Order Totals', 'Subtotal', 'Total', 'Billing Information',
		'Ship to', 'Shipping', 'Discount', 'Tax', 'Gift Card', 'Gift Cards', 'Surcharge',
		'Recurring Charges', 'Payment Schedule', 'Number of Payments', 'Start Date', 'Amount',
		'Recipient Email', 'Your Name', 'Message', 'Delivery Date', 'Immediate', 'Phone', 'Fax',
		'PO Number', 'Tax-Exempt', 'Applied Gift Card', 'Applied Gift Cards', 'Remaining Balance',
		'Card Type', 'Card Number', 'Payment Information', 'Payment Method',
		'Requested Arrival Date', '{var:1} of {var:2}', '{var:1} Subtotal', '{var:1} Tax',
		'{var:1} Total',
	);

	foreach ($keys as $key) {
		pg_assert_true(strpos($code, "lang('" . $key . "')") !== false || strpos($code, "'string' => '" . $key . "'") !== false, $key . ' through lang()');
		pg_assert_true(isset($map[$key]) && ($map[$key] !== ''), $key . ' has a Turkish value');
	}
}

// The ship to and the billing country are named in the order's language;
// the stored name stays what zones match on.
function test_order_receipt_plain_text_language_country_label()
{
	$code = order_receipt_plain_text_language_code('get_order_receipt_in_plain_text.php');

	pg_assert_same(1, substr_count($code, '$country = pg_geo_country_label($country, $country_code);'), 'ship to country');
	pg_assert_same(1, substr_count($code, '$billing_country = pg_geo_country_label($billing_country, $billing_country_code);'), 'billing country');
	pg_assert_true(strpos($code, '$country = pg_geo_country_label(') < strpos($code, '$country = mb_strtoupper($country);'), 'named before a verified address is upper-cased');
}

// The long date and time joins its parts with a lang() key, not a fixed
// "at" in the date() format.
function test_order_receipt_plain_text_language_absolute_time_at()
{
	$code = order_receipt_plain_text_language_code('includes/fn/core.php');

	pg_assert_same(false, strpos($code, '\a\t'), 'no fixed "at" in a date() format');
	pg_assert_same(1, substr_count($code, "'string' => '{var:1} at {var:2}'"), 'the date and the time join through lang()');

	$map = pg_test_language_map('tr');
	pg_assert_true(isset($map['{var:1} at {var:2}']), 'Turkish value');
	pg_assert_same(false, strpos((string) ($map['{var:1} at {var:2}'] ?? ' at '), ' at '), 'Turkish value has no English "at"');

	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$text = get_absolute_time(array('timestamp' => 1760000000, 'size' => 'long', 'timezone_type' => 'site', 'format' => 'plain_text'));
	pg_assert_same(1, preg_match('/^[A-Za-z]+, .+ \d{4} at \d{1,2}:\d{2}( [AP]M)? \S+$/', $text), 'English long date and time: ' . $text);
}

// The plain-text receipt's header and footer are translated like the subject.
function test_order_receipt_plain_text_language_header_footer()
{
	$code = order_receipt_plain_text_language_code('includes/fn/order_completion.php');

	pg_assert_same(1, substr_count($code, '$header = pg_tr_text($header);'), 'header');
	pg_assert_same(1, substr_count($code, '$footer = pg_tr_text($footer);'), 'footer');
}
