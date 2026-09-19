<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Wakes the devices that are waiting to hear about something. Run by cron, like
// the other jobs.
//
// Its own entry point as well as a step inside job.php, for the same reason the
// webhook job has one: it waits on somebody else's server, and an operator who
// wants a banner within a minute can point a dedicated cron entry here instead
// of waiting for the general job's turn.

require('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in
// manager, the same gate the scheduled jobs settings sit behind; without it
// anybody could start the job from a browser.
if (!pg_cron_is_background_run()) {
	$user = validate_user();
	validate_area_access($user, 'manager');
}

require_once(dirname(__FILE__) . '/includes/push.php');

$woken = pg_push_queue_run(20);

// Scheduled-task health, the same as the other jobs record.
if (function_exists('pg_cron_ran')) {

	pg_cron_ran('push_job');

}

if (php_sapi_name() === 'cli') {

	print 'woken: ' . $woken . "\n";

}
