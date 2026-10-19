<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The liveness address for uptime monitors: 200 while the site is up for
 * visitors and its general job keeps running, 503 otherwise.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Anonymous on purpose: an uptime service has no account, and the answer
// says nothing an outsider could use - one word and the age of the general
// job's last run. The firewall's rate limit applies as to any request (known
// monitors such as UptimeRobot pass as good bots). A database that does not
// answer never reaches this file: init.php's guard answers 503 itself, which
// is the answer a monitor needs.
//
// No session: a monitor polling every minute would otherwise leave a session
// file behind on every request, and nothing here depends on who is asking.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the JSON; held and dropped.
ob_start();

require(dirname(__FILE__) . '/init.php');

$health_now = time();

$health_runs = pg_cron_last_runs();
$health_jobs = pg_cron_jobs();

// null: the general job never ran (no table yet, or no cron set up), which
// is not a failure - System Status says the same.
$health_cron_age = (is_array($health_runs) && !empty($health_runs['job']))
    ? max(0, $health_now - (int) $health_runs['job'])
    : null;

$health = pg_health_verdict(pg_maintenance_active(), $health_cron_age, (int) $health_jobs['job']['stale_after']);

// The general job runs the operations checks, so its own death is seen from
// here: the alert goes out at most once a day, whoever polls.
if (($health['status'] === 'cron_stalled') && pg_ops_alerts_enabled()) {
    try {
        pg_ops_alerts_check_cron($health_now);
    } catch (\Throwable $e) {
        // The 503 below says it anyway.
    }
}

ob_end_clean();

http_response_code($health['code']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

if ($health['status'] === 'maintenance') {
    $health_maintenance = pg_maintenance_settings();
    header('Retry-After: ' . pg_maintenance_retry_after($health_maintenance['until'], $health_now));
}

echo json_encode(array(
    'status'   => $health['status'],
    'cron_age' => $health_cron_age,
));
