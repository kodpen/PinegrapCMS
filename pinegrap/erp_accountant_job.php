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

    // A pack that could not be built, or was built but not sent, leaves one
    // line so a failed run shows in the Site Log (and the ERP audit trail).
    // A background run has no user; a hand run is logged under its user.
    if (!in_array($result['reason'], array('off', 'done', 'sent'), true)) {
        $period = erp_accountant_last_month();

        log_activity(lang(array(
            'string' => $result['ran']
                ? 'The accountant pack for {var:1} - {var:2} was built but not sent: {var:3}'
                : 'The accountant pack for {var:1} - {var:2} could not be built: {var:3}',
            'vars' => array($period['from'], $period['to'], $result['reason']))),
            pg_cron_is_background_run() ? 'UNKNOWN' : '');
    }
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_accountant_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'accountant pack: ' . ($result['ran'] ? ('built #' . (int) $result['id'] . ', ' . $result['reason']) : ('skipped (' . $result['reason'] . ')')) . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_accountant.php');
}
