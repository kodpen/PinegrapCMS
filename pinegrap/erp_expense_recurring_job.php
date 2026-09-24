<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: repeating expenses. Writes every expense a recurrence has
 * come due for - the month's rent, the internet line - left to pay or paid
 * from the till the recurrence names. The work lives in
 * includes/erp/expense_recurring.php; this script only decides who may start
 * it.
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
// ERP: the run writes expenses and till movements.
if (!pg_cron_is_background_run()) {
    $user = validate_user();

    if (!validate_erp_access($user, 'write')) {
        exit();
    }
}

$result = array('written' => 0, 'skipped' => 0, 'errors' => array());

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    $result = erp_expense_recurring_run();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('erp_expense_recurring_job');
}

if (php_sapi_name() === 'cli') {
    print 'repeating expenses: ' . (int) $result['written'] . ' written, ' . (int) $result['skipped'] . ' skipped'
        . (!empty($result['errors']) ? (', ' . count($result['errors']) . ' error(s): ' . implode('; ', $result['errors'])) : '') . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_expenses.php');
}
