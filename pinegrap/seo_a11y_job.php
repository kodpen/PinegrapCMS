<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Accessibility report: the nightly pass that renders every published page
 * and stores its WCAG findings in seo_issue (codes a11y_*). The checks and the
 * walk are in includes/fn/a11y.php.
 *
 * Run by the general job's dispatcher (heavy lane) or from the command line.
 * A run stops after the time budget and the next one carries on where it
 * stopped. The run is recorded only once the pass has reached the last page,
 * so the dispatcher hands an unfinished pass out again on the next tick
 * instead of a day later.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require('init.php');

// A web request must come from a signed-in manager, as for the SEO structure
// job: anybody else could make the server render pages for the whole budget.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

// Read by the render path; a command line run has no request to define it
// (platform/scheduled-jobs-command-line-render.md).
if (!defined('REQUEST_URL')) {
    define('REQUEST_URL', defined('PATH') ? PATH : '/');
}

// Pages are rendered as for the SEO structure pass, so the same budget.
$a11y_run = pg_a11y_run(defined('SEO_ANALYZE_TIME_BUDGET') ? (int) SEO_ANALYZE_TIME_BUDGET : 120);

if ($a11y_run['failed']) {
    log_activity(lang(array(
        'string' => 'accessibility check could not render {var:1} page{suffix:1}',
        'vars' => $a11y_run['failed'],
        'suffix' => (($a11y_run['failed'] > 1) ? 's' : ''))), '');
}

if ($a11y_run['done']) {
    pg_cron_ran('seo_a11y_job');
}
