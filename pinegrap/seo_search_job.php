<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: downloads the Google Search Console figures of the days
 * since the last run (includes/fn/search_console.php). A site that is not
 * linked to Search Console records the run and calls nothing.
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by somebody who may see the visitor figures.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in user
// who may see the visitor figures, the same gate search_console.php uses.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_visitors_access($user);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

$result = pg_gsc_linked()
    ? pg_gsc_sync(240)
    : array('ok' => false, 'skipped' => true, 'error' => '', 'from' => '', 'to' => '', 'rows' => 0);

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('seo_search_job');
}

if (php_sapi_name() === 'cli') {
    if ($result['skipped']) {
        print "search console: not linked\n";
    } elseif ($result['ok']) {
        print 'search console: ' . $result['from'] . ' - ' . $result['to'] . ', ' . (int) $result['rows'] . " row(s)\n";
    } else {
        print 'search console: ' . $result['error'] . "\n";
    }
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/search_console.php');
}
