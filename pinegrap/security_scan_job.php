<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Security Scan: one full scan of the files, the stored content and the
 * accounts and settings, recorded as findings
 * (includes/fn/security_scan_section.php). It only reads: nothing is
 * deleted, moved or blocked.
 *
 * Run by the general job's dispatcher (heavy lane) or from the command line.
 * A run stops after its time budget, or at once while Scan now holds the
 * scan, and the next one carries on where it stopped. The run is recorded
 * only once the scan is through, so the dispatcher hands an unfinished scan
 * out again on the next tick instead of a day later.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require('init.php');

// A web request must come from a signed-in manager, the gate of the firewall
// settings the scan belongs to: anybody else could make the server read its
// files for the whole budget.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

$security_scan = pg_security_scan_run(pg_cron_time_left(defined('SEO_ANALYZE_TIME_BUDGET') ? (int) SEO_ANALYZE_TIME_BUDGET : 120));

if ($security_scan['done']) {
    pg_cron_ran('security_scan_job');
} else {
    pg_cron_due_again('security_scan_job', 60);
}
