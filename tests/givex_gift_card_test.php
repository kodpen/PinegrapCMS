<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Givex gift card path of the express order, order preview and
 * order submit. A Givex reply that was not the standard one (empty, an HTML
 * error page, a cut-off list, a balance that is not a number) was read at
 * keys it did not have, then taken as "no remaining balance", and a balance
 * that was not a number stopped the page with an error, at redemption after
 * Givex had taken the amount. A code of only dashes and spaces was sent to
 * Givex empty, a gift card code posted as an array stopped the order preview,
 * and so did an applied gift card id given as an array on removal. Both Givex
 * hosts are asked through pg_curl_run(), the secondary one only when cURL
 * failed on the primary.
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

// A Givex reply as its XML-RPC method response, one string per value.
function givex_test_reply($values)
{
	$params = '';

	foreach ($values as $value) {
		$params .= '<param><value><string>' . $value . '</string></value></param>';
	}

	return '<?xml version="1.0"?><methodResponse><params>' . $params . '</params></methodResponse>';
}

// A balance check gives the balance, or an error message, never neither.
function test_givex_balance_reply()
{
	$unreadable = 'The gift card service sent a reply that could not be read';

	pg_assert_same(array('balance' => '25.00'), pg_givex_parse_response('balance', givex_test_reply(array('0', '0', '25.00'))), 'balance');
	pg_assert_same(array('balance' => '0.00'), pg_givex_parse_response('balance', givex_test_reply(array('0', '0', '0.00'))), 'no balance left');
	pg_assert_same(array('error_message' => 'Card not active'), pg_givex_parse_response('balance', givex_test_reply(array('0', '2', 'Card not active'))), 'standard error');
	pg_assert_same(array('error_message' => 'Not authorized'), pg_givex_parse_response('balance', givex_test_reply(array('Not authorized'))), 'non-standard error');

	$unreadable_replies = array(
		'error without its text' => givex_test_reply(array('0', '2')),
		'no balance' => givex_test_reply(array('0', '0')),
		'balance not a number' => givex_test_reply(array('0', '0', 'abc')),
		'negative balance' => givex_test_reply(array('0', '0', '-5.00')),
		'empty body' => '',
		'HTML error page' => '<html><body>Internal error</body></html>',
		'empty first value' => givex_test_reply(array('')),
	);

	foreach ($unreadable_replies as $label => $reply) {
		pg_assert_same(array('error_message' => $unreadable), pg_givex_parse_response('balance', $reply), $label);
	}
}

// A redemption with result 0 went through at Givex: it stays a success, and a
// new balance that cannot be read is left out.
function test_givex_redemption_reply()
{
	pg_assert_same(array('authorization_number' => 'AUTH123', 'balance' => '5.00'), pg_givex_parse_response('redemption', givex_test_reply(array('0', '0', 'AUTH123', '5.00'))), 'redeemed');
	pg_assert_same(array('authorization_number' => 'AUTH9', 'balance' => null), pg_givex_parse_response('redemption', givex_test_reply(array('0', '0', 'AUTH9', 'abc'))), 'new balance not a number');
	pg_assert_same(array('authorization_number' => '', 'balance' => null), pg_givex_parse_response('redemption', givex_test_reply(array('0', '0'))), 'cut-off reply');
	pg_assert_same(array('error_message' => 'Redemption refused'), pg_givex_parse_response('redemption', givex_test_reply(array('0', '2', 'Redemption refused'))), 'refused');

	$submit_order = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	pg_assert_contains("\$new_balance = isset(\$result['balance']) ? \$result['balance'] * 100 : \$applied_gift_card['old_balance'] - \$amount;", $submit_order, 'new balance without one in the reply');
	pg_assert_same(0, substr_count($submit_order, "escape(\$result['balance'] * 100)"), 'reply balance multiplied unchecked');
}

// A code that is empty once dashes and spaces are gone is not sent to Givex.
function test_givex_empty_code_not_sent()
{
	foreach (array('express_order.php', 'order_preview.php') as $file) {
		pg_assert_contains("if ((\$gift_card['id'] == '') && ((ECOMMERCE_GIVEX == false) || (\$gift_card_code === ''))) {", file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}
}

// The order preview's gift card code and the removed gift card's id are one
// string each; one not given or given as an array counts as empty.
function test_givex_single_value_inputs()
{
	$order_preview = file_get_contents(PG_FUNCTIONS_DIR . '/order_preview.php');
	pg_assert_true(strpos($order_preview, "pg_request_single_values(array('gift_card_code'));") < strpos($order_preview, "\$liveform->assign_field_value('gift_card_code', \$_POST['gift_card_code'] ?? '');"), 'order preview code');

	$remove = file_get_contents(PG_FUNCTIONS_DIR . '/remove_gift_card_from_order.php');
	pg_assert_true(strpos($remove, "pg_request_single_values(array('applied_gift_card_id'));") < strpos($remove, "escape(\$_GET['applied_gift_card_id'] ?? '')"), 'removed gift card id');
}

// Both Givex hosts are asked through pg_curl_run(): the primary first, the
// secondary only when cURL failed on it, and that failure is still answered
// as curl_errno and curl_error.
function test_givex_request_through_curl_run()
{
	$source = pg_test_function_source('send_givex_request');

	pg_assert_same(0, substr_count($source, 'curl_init'), 'no hand-written cURL handle');
	pg_assert_same(0, substr_count($source, 'curl_setopt'), 'no hand-written cURL option');
	pg_assert_contains("pg_curl_run('POST', 'https://' . \$hostname . ':50042', array(", $source, 'one wrapper call');
	pg_assert_contains("'headers' => array('Content-Type: text/xml'),", $source, 'XML content type');
	pg_assert_contains("'timeout' => 15,", $source, 'timeout');
	pg_assert_contains("'forbid_reuse' => true,", $source, 'fresh connection');
	pg_assert_true(strpos($source, 'ECOMMERCE_GIVEX_PRIMARY_HOSTNAME') < strpos($source, 'ECOMMERCE_GIVEX_SECONDARY_HOSTNAME'), 'primary host before the secondary');
	pg_assert_contains("if (\$response['errno'] == 0) {", $source, 'fall-back only on a cURL error');
	pg_assert_contains("\$result['curl_errno'] = \$response['errno'];", $source, 'curl_errno kept');
	pg_assert_contains("\$result['curl_error'] = \$response['error'];", $source, 'curl_error kept');
	pg_assert_contains("pg_givex_parse_response(\$type, \$response['body'])", $source, 'reply parsed');
}
