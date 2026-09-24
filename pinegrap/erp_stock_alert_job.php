<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: low stock notices. Tells the panel bell and the subscribed
 * devices about the products that have dropped to or below their minimum
 * since the last run, when the ERP settings ask for it. The work lives in
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

$result = array('ran' => false, 'count' => 0, 'notification_id' => 0);

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    $result = erp_alert_low_stock();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_stock_alert_job');
}

if (php_sapi_name() === 'cli') {
    print 'low stock notices: ' . ($result['ran'] ? ((int) $result['count'] . ' product(s) announced') : 'off') . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_stock_minimums.php?show=low');
}
