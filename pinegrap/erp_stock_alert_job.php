<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: low stock and quote notices. Tells the panel bell and the
 * subscribed devices about the products that have dropped to or below their
 * minimum since the last run, and about the open quotes about to run out,
 * when the ERP settings ask for it, and, with the follow-up switched on,
 * opens a Workspace follow-up for each of those quotes. The work lives in
 * includes/erp/alerts.php; this script only decides who may start it.
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
// A web request must come from a signed-in user who may use the ERP.
if (!pg_cron_is_background_run()) {
    $user = validate_user();

    if (!validate_erp_access($user, 'write')) {
        exit();
    }
}

// One run at a time, whoever starts it: a crontab line and a request from the
// panel take the job's lock in cron_runs as the general job's dispatcher does
// (it has taken it already for a dispatched run, and releases it itself).
// The products and quotes are stamped as announced only after the bell row is
// written, so two runs side by side both found them new and rang the bell,
// and woke the devices, twice. A request from the panel while a run is
// working goes to its screen.
if (!defined('PG_CRON_DISPATCH')) {
    if (!pg_cron_claim('erp_stock_alert_job')) {
        if (!pg_cron_is_background_run()) {
            go(PATH . SOFTWARE_DIRECTORY . '/erp_stock_minimums.php?show=low');
        }

        exit();
    }

    register_shutdown_function('pg_cron_release', 'erp_stock_alert_job');
}

$result = array('ran' => false, 'count' => 0, 'notification_id' => 0);
$quotes = array('ran' => false, 'count' => 0, 'notification_id' => 0);

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    $result = erp_alert_low_stock();
    $quotes = erp_alert_quote_expiry();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_stock_alert_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'low stock notices: ' . ($result['ran'] ? ((int) $result['count'] . ' product(s) announced') : 'off') . "\n";
    print 'quote notices: ' . ($quotes['ran'] ? ((int) $quotes['count'] . ' quote(s) announced') : 'off') . "\n";
    print 'quote follow-ups: ' . ((function_exists('erp_quote_expiry_followup_on') && erp_quote_expiry_followup_on()) ? ((int) ($quotes['followups'] ?? 0) . ' opened') : 'off') . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_stock_minimums.php?show=low');
}
