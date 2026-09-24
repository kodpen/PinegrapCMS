<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: the accountant's monthly pack. Once a month builds the pack
 * for the month before and e-mails the accountant a link to it, when the
 * accountant card says so. The decision and the pack live in
 * includes/erp/accountant.php; this script only decides who may start it.
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by somebody who may change things in the ERP.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// A web request must come from a signed-in user who may change things in the
// ERP: the run sends mail.
if (!pg_cron_is_background_run()) {
    $user = validate_user();

    if (!validate_erp_access($user, 'write')) {
        exit();
    }
}

$result = array('ran' => false, 'reason' => 'off', 'id' => 0);

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    if (erp_accountant_ready()) {
        $result = erp_accountant_monthly_run();
    }
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_accountant_job');
}

if (php_sapi_name() === 'cli') {
    print 'accountant pack: ' . ($result['ran'] ? ('built #' . (int) $result['id'] . ', ' . $result['reason']) : ('skipped (' . $result['reason'] . ')')) . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_accountant.php');
}
