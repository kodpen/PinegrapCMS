<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the Claude engine: a queue the routine set up in
 * the Workspace works through the external API.
 *
 * Nothing is translated here. A job for Claude is opened like any other and
 * the routine is told there is work (ws_claude_fire(), queue
 * "translations"); the routine then reads the job through
 * /translations/jobs, translates, and posts the results back
 * (includes/translate/api.php). The connection - the routine's address and
 * token, the daily allowance, the hold after a refusal - is the Workspace
 * module's and is shared with the requests people make in channels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** A run that has not posted anything for this long is no longer waited for. */
define('PG_TR_CLAUDE_STALE', 1800);

/**
 * Loads the Workspace module's files, which hold the connection to Claude.
 */
function pg_tr_claude_boot()
{
    if (!function_exists('ws_claude_ready') && is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    }
}

/**
 * Whether Claude can be given work, and why not when it cannot.
 *
 * @return array('ready' => bool, 'reason' => string)
 */
function pg_tr_claude_state()
{
    pg_tr_claude_boot();

    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !function_exists('ws_claude_ready')) {
        return array('ready' => false, 'reason' => lang('Claude is set up in the Workspace module, which this site has not switched on.'));
    }

    $missing = ws_claude_missing();

    if ($missing) {
        return array('ready' => false, 'reason' => implode(' ', $missing));
    }

    return array('ready' => true, 'reason' => '');
}

/**
 * Tells the routine there are translation jobs waiting, unless a run is
 * already under way, the daily allowance is used up, or nothing is queued.
 * Shares the lock, the hold and the allowance with the Workspace queue.
 *
 * @return array ok, status (idle | busy | held | sent | failed), error
 */
function pg_tr_claude_dispatch()
{
    $state = pg_tr_claude_state();

    if (!$state['ready']) {
        return array('ok' => false, 'status' => 'idle', 'error' => $state['reason']);
    }

    $queued = (int) db_value("SELECT COUNT(*) FROM translation_jobs WHERE engine = 'claude' AND status = 'queued'");

    if ($queued === 0) {
        return array('ok' => true, 'status' => 'idle', 'error' => '');
    }

    // A run that is under way reads the open jobs again before it stops.
    $busy = (int) db_value("SELECT COUNT(*) FROM translation_jobs
        WHERE engine = 'claude' AND status IN ('sent', 'running') AND claimed_at > '" . (time() - PG_TR_CLAUDE_STALE) . "'");

    if ($busy > 0) {
        return array('ok' => true, 'status' => 'busy', 'error' => '');
    }

    if (ws_claude_config(true)['hold_until'] > time()) {
        return array('ok' => true, 'status' => 'held', 'error' => (string) ws_claude_config()['error']);
    }

    if ((int) db_value("SELECT GET_LOCK('pg_ws_claude_fire', 0)") !== 1) {
        return array('ok' => true, 'status' => 'busy', 'error' => '');
    }

    $out = ws_claude_fire(false, 'translations');

    db_value("SELECT RELEASE_LOCK('pg_ws_claude_fire')");

    if ($out['ok']) {
        db("UPDATE translation_jobs SET status = 'sent', claimed_at = '" . time() . "' WHERE engine = 'claude' AND status = 'queued'");
    }

    return $out;
}

/**
 * Whether the application calling the external API is the one Claude works
 * through, so the job endpoints answer it alone.
 */
function pg_tr_claude_is_caller()
{
    pg_tr_claude_boot();

    return function_exists('ws_api_is_claude') && ws_api_is_claude();
}
