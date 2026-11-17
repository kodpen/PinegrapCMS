<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fields and samples of the webhook events
 * (api_webhook_event_payloads()): each field set is the set of keys the
 * announcing code writes, read from the code itself (a literal array by the
 * tokenizer, a payload function by calling it), each sample has exactly those
 * keys with values of the declared types; GET /webhooks/events is routed as
 * its own route, not as /webhooks/{id}; the event list it answers; and the
 * checks of a sample sent by POST /webhooks/{id}/test.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');

function test_webhook_payloads_test_event_refusal()
{
	$payloads = array('order.created' => array('fields' => array(), 'sample' => array()));

	pg_assert_same('', api_webhook_sample_refusal('order.created', array('order.created', 'order.shipped'), $payloads), 'an event of the subscription with a sample');
	pg_assert_same('not_listened', api_webhook_sample_refusal('order.shipped', array('order.created'), $payloads), 'an event the subscription does not listen to');
	pg_assert_same('no_sample', api_webhook_sample_refusal('order.shipped', array('order.shipped'), $payloads), 'an event without a sample yet');
	pg_assert_same('not_listened', api_webhook_sample_refusal('', array('order.created'), $payloads), 'no name');
}

function test_webhook_payloads_sample_body_and_header()
{
	$body = api_webhook_sample_body('order.created', array('id' => 4), '2026-11-17T10:00:00Z');

	pg_assert_same(array('event' => 'order.created', 'created_at' => '2026-11-17T10:00:00Z', 'data' => array('id' => 4), 'test' => true), json_decode($body, true), 'the envelope of a real event, marked test');
	pg_assert_true(api_webhook_body_is_test($body), 'a sample is sent with X-Pinegrap-Test');

	pg_assert_false(api_webhook_body_is_test(api_webhook_body('order.created', array('id' => 4, 'test' => true), '2026-11-17T10:00:00Z')), 'a real event whose data says test is not');
	pg_assert_false(api_webhook_body_is_test('{"event":"webhook.test","created_at":"","data":{"webhook_id":1}}'), 'the plain test event is as it was');
	pg_assert_false(api_webhook_body_is_test(''), 'an empty body');

	pg_assert_same(
		array('Content-Type: application/json', 'X-Pinegrap-Event: order.created', 'X-Pinegrap-Delivery: 9', 'X-Pinegrap-Signature: t=100,v1=' . api_webhook_signature('s', '100', $body), 'X-Pinegrap-Test: 1'),
		api_webhook_delivery_headers('order.created', 9, 's', '100', $body),
		'the headers of a sample'
	);
}
