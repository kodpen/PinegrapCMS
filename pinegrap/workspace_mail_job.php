<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: incoming e-mail. Reads every Workspace mailbox that has an
 * IMAP server and was not read in the last minute, and brings its new mail
 * into the box's channel. The reading lives in
 * includes/workspace/mail_fetch.php; this script only decides who may start
 * it.
 *
 * Runs from the job dispatcher, or from a crontab line of its own every
 * minute for mail within a minute.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in
// manager, the same gate the scheduled jobs settings sit behind.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');

$result = array('boxes' => 0, 'stored' => 0, 'failed' => 0);

// The module switched off, or no mailbox, is not a failure: the run is
// recorded so the scheduled-task health indicator stays green.
if (ws_enabled()) {
    $result = ws_mail_fetch_run(40);
}

pg_cron_ran('workspace_mail_job');

if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'boxes: ' . $result['boxes'] . ', stored: ' . $result['stored'] . ', failed: ' . $result['failed'] . "\n";
}
