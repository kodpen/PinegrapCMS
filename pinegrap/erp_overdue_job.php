<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: overdue receivable reminders. Sends the period's digest of
 * sales invoices that have newly passed the reminder threshold, over the
 * channels chosen on the ERP settings card. The decision and the wording live
 * in includes/erp/notify.php; this script only decides who may start it.
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by somebody who may use the ERP.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in user
// who may use the ERP, the same gate the ERP screens use.
if (!pg_cron_is_background_run()) {
    $user = validate_user();

    if (!validate_erp_access($user, 'write')) {
        exit();
    }
}

$result = array('ran' => false, 'reason' => 'off');

// The module switched off is not a failure: the run is recorded so the
// scheduled-task health indicator stays green, and nothing else happens.
if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    $result = erp_overdue_check(false, true);
}

// Scheduled-task health, the same as the other jobs record.
if (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_overdue_job');
}

if (php_sapi_name() === 'cli') {
    print 'overdue reminders: ' . ($result['ran']
        ? ('sent, ' . count($result['new']) . ' new, ' . count($result['second'] ?? array()) . ' second, ' . (int) ($result['customers']['sent'] ?? 0) . ' customer(s) e-mailed')
        : ('skipped (' . $result['reason'] . ')')) . "\n";
} elseif (!pg_cron_is_background_run()) {
    // Started by hand from the panel: back to the dashboard, which shows the
    // standing overdue total whether or not a digest went out.
    go(PATH . SOFTWARE_DIRECTORY . '/erp_dashboard.php');
}
