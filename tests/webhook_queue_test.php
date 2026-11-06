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
