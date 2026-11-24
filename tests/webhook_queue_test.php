<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how an event is queued for a subscription in each status
 * (api_webhook_queue_hold(), includes/api/outbound/webhooks.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

// A failing subscription still has its events queued, on hold until somebody
// retries (a successful delivery does not send them); they used to be left
// out of the queue and lost. An active one queues for sending. A disabled
// one is not read when queueing at all.
function test_webhook_queue_failing_subscription_holds_its_events()
{
	pg_assert_true(api_webhook_queue_hold('failing'), 'failing: held');
	pg_assert_false(api_webhook_queue_hold('active'), 'active: sent');
	pg_assert_false(api_webhook_queue_hold(''), 'no status');
}

// The activity log line for a given-up delivery names the address the way
// the ops alert does: scheme and host only. The log is readable by every
// manager and by any API app with site_log:read, while Slack, Discord and
// Zapier addresses carry their secret in the path.
function test_webhook_given_up_note_masks_the_address()
{
	$note = api_webhook_given_up_note('https://hooks.slack.com/services/T000/B000/SECRETTOKEN?x=1', 8, 'HTTP 404');

	pg_assert_true(strpos($note, 'https://hooks.slack.com/…') !== false, 'scheme and host are kept');
	pg_assert_false(strpos($note, 'SECRETTOKEN'), 'the secret path is cut');
	pg_assert_false(strpos($note, 'x=1'), 'the query is cut');
	pg_assert_true(strpos($note, '8') !== false, 'attempts are named');
	pg_assert_true(strpos($note, 'HTTP 404') !== false, 'the error is named');
}

// The queued body is the envelope as JSON. A payload with bytes that are not
// UTF-8 (a name cut inside a character, a legacy row) used to make
// json_encode() answer false, and the row was queued and signed with an empty
// body that no receiver could read. Broken bytes become U+FFFD instead, as in
// the API's own answers; a payload that cannot be written at all (INF, NAN)
// gives null, so nothing empty is queued.
function test_webhook_body_survives_broken_utf8()
{
	$body = api_webhook_body('product.updated', array('id' => 7, 'name' => "Kaz\xC4ak \xFF"), '2026-11-05T10:00:00Z');

	pg_assert_true(is_string($body) && $body !== '', 'a body is written');

	$decoded = json_decode((string)$body, true);

	pg_assert_same('product.updated', $decoded['event'], 'event');
	pg_assert_same('2026-11-05T10:00:00Z', $decoded['created_at'], 'created_at');
	pg_assert_same(7, $decoded['data']['id'], 'id kept');
	pg_assert_same("Kaz\u{FFFD}ak \u{FFFD}", $decoded['data']['name'], 'broken bytes replaced');

	$plain = api_webhook_body('order.created', array('name' => 'Şükrü'), '2026-11-05T10:00:00Z');

	pg_assert_same('{"event":"order.created","created_at":"2026-11-05T10:00:00Z","data":{"name":"Şükrü"}}', $plain, 'unicode unescaped');

	pg_assert_same(null, api_webhook_body('order.created', array('amount' => NAN), '2026-11-05T10:00:00Z'), 'NAN: no body');
}

// The status a delivery writes back (active after a success, failing after
// the last attempt) never overwrites a subscription switched off while the
// delivery was on its way: Stop on the Events tab, or a channel group taken
// away, used to be undone by the answer of the delivery already in flight.
function test_webhook_status_after_delivery_leaves_a_disabled_subscription()
{
	$active = api_webhook_status_after_delivery_sql(12, 'active');

	pg_assert_true(strpos($active, "status = 'active'") !== false, 'active is written');
	pg_assert_true(strpos($active, 'last_success = UNIX_TIMESTAMP()') !== false, 'the success time with it');
	pg_assert_true(strpos($active, "id = '12'") !== false, 'this subscription');
	pg_assert_true(strpos($active, "status <> 'disabled'") !== false, 'never a disabled one');

	$failing = api_webhook_status_after_delivery_sql('7x', 'failing');

	pg_assert_true(strpos($failing, "status = 'failing'") !== false, 'failing is written');
	pg_assert_false(strpos($failing, 'last_success'), 'no success time');
	pg_assert_true(strpos($failing, "id = '7'") !== false, 'the id as a number');
	pg_assert_true(strpos($failing, "status <> 'disabled'") !== false, 'never a disabled one');
}

// One run of the dispatcher picks due rows one at a time. A receiver that
// times out used to be tried row after row until the run's time was gone,
// so every other subscription waited behind it; a subscription whose
// delivery failed in this run is left out of the next picks of the run.
function test_webhook_due_row_leaves_out_subscriptions_failed_in_this_run()
{
	$plain = api_webhook_due_row_sql(array());

	pg_assert_true(strpos($plain, 'next_attempt_at > 0') !== false, 'held and given-up rows are not picked');
	pg_assert_true(strpos($plain, "attempts < '" . api_webhook_max_attempts() . "'") !== false, 'within the attempts');
	pg_assert_true(strpos($plain, 'LIMIT 1') !== false, 'one row');
	pg_assert_false(strpos($plain, 'NOT IN'), 'nothing left out');

	$skipping = api_webhook_due_row_sql(array(4, '9', 4, 0, -2));

	pg_assert_true(strpos($skipping, "webhook_id NOT IN ('4', '9')") !== false, 'the failed subscriptions, once each');
}
