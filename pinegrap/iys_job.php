<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: İYS consent sync. Pushes the e-mail marketing answers the
 * consent log gained since the last run to İYS, then pulls the changes
 * recipients made through İYS. The work lives in includes/iys/client.php;
 * this script only decides who may start it.
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by a signed-in manager.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in
// manager, the same gate the settings screens use.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

// No account, or the e-mail switch off, is not a failure: the run is
// recorded so the scheduled-task health indicator stays green.
$result = pg_iys_run();

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('iys_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    if (!$result['ran']) {
        print 'iys: skipped (' . $result['reason'] . ")\n";
    } else {
        $push = $result['push'];
        print 'iys push' . ($push['dry_run'] ? ' (dry run)' : '') . ': ' . (int) $push['pushed'] . ' pushed, ' . (int) $push['rejected'] . ' refused, '
            . (int) $push['skipped'] . ' without an address' . (($push['error'] !== '') ? ', ' . $push['error'] : '') . "\n";

        if ($result['pull'] !== null) {
            print 'iys pull: ' . (int) $result['pull']['refused'] . ' refusal(s), ' . (int) $result['pull']['approved'] . ' approval(s)'
                . (($result['pull']['error'] !== '') ? ', ' . $result['pull']['error'] : '') . "\n";
        }
    }
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/settings_contact.php');
}
