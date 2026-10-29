<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled job: rebuilds the product feeds that are switched on and writes
 * them to data/feeds/, where feed.php serves them
 * (includes/fn/product_feeds.php). A site with no feed on records the run and
 * builds nothing.
 *
 * Runs from the job dispatcher, from a crontab line, or by hand from the
 * panel by a manager, who may change the feeds' settings.
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

$report = (defined('ECOMMERCE') && (ECOMMERCE === true))
    ? pg_product_feed_refresh()
    : array('error' => 'ecommerce_off', 'formats' => array());

// Google Merchant Center's product issues, read again once the cached
// reading is older than PG_MERCHANT_ISSUES_TTL; nothing while no Merchant
// Center account is chosen.
if (function_exists('pg_merchant_refresh_issues')) {
    pg_merchant_refresh_issues();
}

if (function_exists('pg_cron_ran')) {
    pg_cron_ran('product_feed_job');
}

// The summary is for a person running the script by hand; under the general
// job's dispatcher printed text goes to the site log (pg_cron_dispatch_output()).
if ((php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')) {
    if ($report['error'] !== '') {
        print 'product feeds: ' . $report['error'] . "\n";
    } elseif (!$report['formats']) {
        print "product feeds: none switched on\n";
    } else {
        foreach ($report['formats'] as $format => $result) {
            print 'product feeds: ' . $format . ' '
                . ($result['ok'] ? (int) $result['count'] . ' item(s)' : $result['error'])
                . ($result['skipped'] ? ', skipped ' . json_encode($result['skipped']) : '') . "\n";
        }
    }
} elseif (!pg_cron_is_background_run()) {
    go(PATH . SOFTWARE_DIRECTORY . '/settings_seo.php');
}
