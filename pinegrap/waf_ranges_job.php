<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Daily refresh of the AI bots' published IP range lists (waf_bot_ranges).
//
// Runs three ways, all landing on the same throttled function:
//   - its own crontab entry, calling this script directly
//   - the general job (job.php) via the job dispatch catalogue
//   - the settings screen, with ?send_to= for the redirect back
//
// The fetch itself never runs on the visitor path; waf.php only reads the
// stored rows. A run on an install that has not taken the 2026.4.4 upgrade
// is a quiet no-op.

include('init.php');

$user_id = (($_GET['send_to'] ?? '')) ? validate_user()['id'] : 0;

if (function_exists('pg_waf_refresh_ai_ranges')) {
    // Not forced: the 6-hour attempt throttle and per-row freshness checks
    // are the point on an unauthenticated entry path. The settings screen's
    // own button is the forced path, and it lives behind validate_user().
    pg_waf_refresh_ai_ranges();
}

// Scheduled-task health: record that this job finished, whoever started it.
// See pg_cron_ran().
if (function_exists('pg_cron_ran')) {
    pg_cron_ran('waf_ranges_job');
}

if (($_GET['send_to'] ?? '')) {
    include_once('liveform.class.php');
    $liveform = new liveform('settings');
    $liveform->add_notice(lang('The bot IP lists have been refreshed.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . ($_GET['send_to'] ?? ''));
    exit();
}
