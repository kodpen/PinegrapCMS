<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: repeating tasks. Hands out the copies of the workspace's
 * repeating tasks that have come due, to the same people, and reads again
 * the public holiday calendars not read for a day. The rules and the writing
 * live in includes/workspace/recurrence.php and workdays.php; this script
 * only decides who may start it.
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by a member of the team.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in member
// of the team.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    $viewer = ws_viewer($user);

    if (!$viewer['member']) {
        exit();
    }
}

$result = array('made' => 0, 'ended' => 0, 'failed' => 0);

// The module switched off is not a failure: the run is recorded so the
// scheduled-task health indicator stays green, and nothing else happens.
if (ws_enabled() && function_exists('ws_recurrence_run')) {
    $result = ws_recurrence_run();
}

// The public holiday calendars, one a run (includes/workspace/workdays.php).
if (ws_enabled() && function_exists('ws_holiday_feeds_tick')) {
    ws_holiday_feeds_tick();
}

// Requests to Claude that waited through a daily limit or a failed call, and
// runs that stopped answering.
if (ws_enabled() && function_exists('ws_claude_tick')) {
    ws_claude_tick();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('workspace_recurring_job');
}

if (php_sapi_name() === 'cli') {
    print 'repeating tasks: ' . (int) $result['made'] . ' handed out, ' . (int) $result['ended'] . ' ended, ' . (int) $result['failed'] . ' failed' . "\n";
} elseif (!pg_cron_is_background_run()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
}
