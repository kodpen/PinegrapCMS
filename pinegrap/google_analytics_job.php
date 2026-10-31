<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: reads the Google Analytics report again once the kept one
 * is older than its cache window (includes/fn/google_analytics.php). A site
 * with no GA4 property chosen records the run and calls nothing.
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

// A background run has no user; a web request must come from a signed-in
// user who may see the visitor figures, the same gate as the card.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_visitors_access($user);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');

$result = pg_ga_refresh();

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('google_analytics_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    print 'google analytics: ' . ($result['ran'] ? ($result['ok'] ? 'read' : $result['error']) : 'nothing due') . "\n";
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/welcome.php');
}
