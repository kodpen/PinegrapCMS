<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: reminds the staff of refunds of returns falling due. Each
 * return whose goods are on their way back or have arrived rings the bell
 * once when its refund is due within three days and once more when it is
 * late (pg_return_remind_refunds(), includes/fn/returns.php).
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by somebody who manages the orders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in user
// who may manage the orders, the gate of the Returns screens.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_ecommerce_access($user);
}

$result = array('due' => 0, 'overdue' => 0);

// E-commerce switched off is not a failure: the run is recorded so the
// scheduled-task health indicator stays green, and nothing else happens.
if (defined('ECOMMERCE') && (ECOMMERCE === true)) {
    $result = pg_return_remind_refunds();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('return_reminder_job');
}

if (php_sapi_name() === 'cli') {
    print 'return refund reminders: ' . (int) $result['due'] . ' due, ' . (int) $result['overdue'] . ' overdue' . "\n";
} elseif (!pg_cron_is_background_run()) {
    // Started by hand from the panel: back to the returns waiting for money.
    go(PATH . SOFTWARE_DIRECTORY . '/view_returns.php?status=received');
}
