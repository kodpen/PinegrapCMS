<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Sends whatever webhook deliveries are due. Run by cron, like the other jobs.
//
// It is its own entry point rather than a step inside job.php because it is the
// only scheduled work here that waits on somebody else's server: a receiver
// timing out should delay the next webhook, not the abandoned-cart mail.

require('init.php');

require_once(dirname(__FILE__) . '/includes/api/outbound/webhooks.php');

$result = api_webhook_dispatch(20);

// Scheduled-task health, the same as the other jobs record.
if (function_exists('pg_cron_ran')) {

	pg_cron_ran('api_webhook_job');

}

if (php_sapi_name() === 'cli') {

	print 'sent: ' . $result['sent'] . ', still failing: ' . $result['failed'] . "\n";

}
