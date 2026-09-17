<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Sends whatever marketplace changes are queued, and collects the answers to
// what was sent last time. Run by cron, like the other jobs.
//
// Its own entry point rather than a step inside job.php, for the same reason
// the webhook job is: it waits on somebody else's server, and a marketplace
// having a slow morning must not hold up the abandoned-cart mail.

require('init.php');

require_once(dirname(__FILE__) . '/includes/api/outbound/sync.php');

$result = mp_sync_run(25);

// Scheduled-task health, the same as the other jobs record.
if (function_exists('pg_cron_ran')) {

	pg_cron_ran('api_sync_job');

}

if (php_sapi_name() === 'cli') {

	print 'pushed: ' . $result['pushed']
		. ' in ' . $result['batches'] . ' batch(es)'
		. ', finished: ' . $result['done']
		. ', failed: ' . $result['failed']
		. ', still waiting: ' . $result['waiting']
		. ', orders in: ' . $result['orders']
		. ', orders refused: ' . $result['order_errors'] . "\n";

}
