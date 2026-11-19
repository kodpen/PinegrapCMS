<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Works the open "update translations" jobs of the server engines (Google
// Cloud Translation) and closes the ones nobody has touched for a day. Run by
// cron, like the other jobs: an update started from the panel gets twenty
// seconds there, what is left is finished here, a batch at a time. Then the
// translated Design Canvas pictures waiting to be drawn that this server
// draws whole (includes/fn/picture_server_draw.php).

require('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in
// manager, the same gate the Translations screen sits behind; without it
// anybody could start the job from a browser.
if (!pg_cron_is_background_run()) {
	$user = validate_user();
	validate_area_access($user, 'manager');
}

$result = array('jobs' => 0, 'done' => 0, 'failed' => 0);

// Before the upgrade there are no tables to work; the run is recorded all the
// same so the job does not read as overdue.
if (pg_tr_ready()) {

	pg_tr_load();

	$result = pg_tr_jobs_run_due(40);

	// The translated Design Canvas pictures this server draws whole, with
	// nobody in the panel. Only while a row may be waiting (the mark, read
	// without a query); the drawing code is loaded only then.
	if (pg_picture_tr_marked()) {

		require_once(PG_FUNCTIONS_DIR . '/includes/fn/picture_server_draw.php');

		$result['pictures'] = pg_picture_tr_draw_due(20)['drawn'];

	}

}

// Scheduled-task health, the same as the other jobs record.
if (function_exists('pg_cron_ran')) {

	pg_cron_ran('translation_job');

}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {

	print 'jobs: ' . $result['jobs'] . ', translated: ' . $result['done'] . ', failed: ' . $result['failed'] . ', pictures drawn: ' . (isset($result['pictures']) ? $result['pictures'] : 0) . "\n";

}
