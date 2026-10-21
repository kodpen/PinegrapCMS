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
