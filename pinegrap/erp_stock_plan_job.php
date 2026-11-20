<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: the daily stock record behind stock planning. Writes
 * yesterday's units sold and stock per tracked product, counts the last 14
 * days' units again, and on its first runs fills the sold units of the 420
 * days before the record began; then rebuilds the forecast (erp_stock_plan)
 * per tracked product. The work lives in includes/erp/stock_plan.php; this
 * script only decides who may start it and when a tick stops.
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

// One run at a time: a crontab line or a request from the panel takes the
// job's lock as the dispatcher does for a dispatched run.
if (!defined('PG_CRON_DISPATCH')) {
    if (!pg_cron_claim('erp_stock_plan_job')) {
        if (!pg_cron_is_background_run()) {
            go(PATH . SOFTWARE_DIRECTORY . '/erp_dashboard.php');
        }

        exit();
    }

    register_shutdown_function('pg_cron_release', 'erp_stock_plan_job');
}

$result = array('ran' => false, 'reason' => 'off', 'rows_day' => '');

// With the Accounting module off nothing is written; the run is still
// recorded so the scheduled-task health stays green.
if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');

    $result = erp_stock_plan_run(true, function () {
        return pg_cron_time_left(60);
    });
}

if ($result['reason'] === 'more') {
    // The first fill or the forecast has work left: come back in a minute,
    // not an hour.
    pg_cron_due_again('erp_stock_plan_job', 60);
} elseif ($result['reason'] === 'error') {
    log_activity(lang('The daily stock record could not be written; the next run tries again.'), 'UNKNOWN');
} elseif (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_stock_plan_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'daily stock record: ' . $result['reason'] . ($result['rows_day'] !== '' ? ' (' . $result['rows_day'] . ')' : '') . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_dashboard.php');
}
