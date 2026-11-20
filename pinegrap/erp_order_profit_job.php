<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: each finished order's costs and net profit. Reads the
 * marketplaces' real deductions, computes the orders a real cost marked, the
 * finished orders not computed yet, then rechecks the computed ones whose
 * figures changed; once a day, the products newly sold at a loss go to the
 * bell (erp_alert_profit_loss()). The work lives in
 * includes/erp/order_profit.php; this script only decides who may start it
 * and when a tick stops.
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
    if (!pg_cron_claim('erp_order_profit_job')) {
        if (!pg_cron_is_background_run()) {
            go(PATH . SOFTWARE_DIRECTORY . '/erp_dashboard.php');
        }

        exit();
    }

    register_shutdown_function('pg_cron_release', 'erp_order_profit_job');
}

$result = array('reason' => 'off', 'done' => 0);

// With the Accounting module off nothing is read or written; the run is still
// recorded so the scheduled-task health stays green.
if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit_deductions.php');

    $time_left = function () {
        return pg_cron_time_left(60);
    };

    // The marketplaces' real deductions first, so the orders they mark are
    // computed in the same run.
    $deductions = erp_order_profit_deductions_run($time_left, true);

    $result = erp_order_profit_run($time_left, true);

    if ($deductions['reason'] === 'error') {
        log_activity(lang('The marketplace deductions could not be read; the next run tries again.'), 'UNKNOWN');
    } elseif (($deductions['reason'] === 'more') && ($result['reason'] === 'done')) {
        $result['reason'] = 'more';
    }

    // Once a day, with every order worked out: the products newly sold at a
    // loss on a channel over the last 30 days go to the bell.
    if ($result['reason'] === 'done') {
        erp_alert_profit_loss();
    }
}

if ($result['reason'] === 'more') {
    // Orders are left: come back in a minute, not an hour.
    pg_cron_due_again('erp_order_profit_job', 60);
} elseif ($result['reason'] === 'error') {
    log_activity(lang('The order profit could not be written; the next run tries again.'), 'UNKNOWN');
} elseif (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_order_profit_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'order profit: ' . $result['reason'] . ' (' . (int) $result['done'] . ' order(s))' . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_dashboard.php');
}
