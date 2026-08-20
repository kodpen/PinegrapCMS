<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// HTML structure analysis pass. It should be run by a cron job, after
// seo_score_job.php.
//
// This is the expensive half of the SEO score: every page is rendered in
// process through get_page_content() before its markup can be examined.
// Where seo_score_job.php reads columns and finishes in seconds, this one is
// bounded by a time budget and expected to take several runs to work through
// a large site the first time.
//
// Staleness is decided two ways. The explicit flag covers a full refresh and
// the first pass after the upgrade. The timestamp comparison covers ordinary
// editing without needing every save path in the software to know this
// feature exists: content changes bump the record's own timestamp, so a
// record analyzed before its last edit is stale by definition.
//
// What the timestamp cannot see is a change to something shared - a common
// region, a page style, a system widget's output. Those change many pages
// without touching any of their timestamps. The periodic full refresh below
// is what eventually catches them, which is why it exists at all rather than
// relying on the timestamp alone.

require('init.php');
require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(dirname(__FILE__) . '/seo_links.php');

// init.php only defines this when there is an HTTP host, and router.php when
// there is a route. Run from the command line there is neither, and two things
// in the render path read it without checking: the search results page, and
// the error screen. In PHP 8 an undefined constant is a thrown Error, so the
// first search page in the queue ends the run - and worse, any error during a
// render turns into "Undefined constant REQUEST_URL" instead of the message
// that would have said what was actually wrong.
//
// The value only reaches the canonical URL of the error page, which is not a
// page this pass scores.
if (!defined('REQUEST_URL')) {
    define('REQUEST_URL', defined('PATH') ? PATH : '/');
}

// Both bail-outs record the run before leaving. The dashboard reads that
// marker to decide whether the job is scheduled at all, so exiting without
// it would report "never ran" on a host whose cron is working perfectly and
// simply has no DOM extension - sending the operator after a scheduling
// problem that does not exist.
if (!pg_seo_structure_schema_ready()) {
    pg_cron_ran('seo_analyze_job');
    exit;
}

if (!class_exists('DOMDocument')) {

    // Logged once a day at most. A five-minute cron would otherwise write
    // the same line 288 times a day and bury everything else in the log.
    $last_dom_warning = (int) db_value("SELECT last_run_at FROM cron_runs WHERE job_name = 'seo_analyze_job_dom_warning'");

    if ($last_dom_warning < (time() - 86400)) {
        log_activity(lang('SEO structure analysis was skipped because the PHP DOM extension is not available'), '');
        pg_cron_ran('seo_analyze_job_dom_warning');
    }

    pg_cron_ran('seo_analyze_job');
    exit;
}

// Seconds of work per run. A page render is a full request's worth of
// queries, so this is a budget in pages rather than a target. The pass is
// expected to take several runs to get through a large site the first time;
// the queue it leaves behind is where the next run starts.
$time_budget = defined('SEO_ANALYZE_TIME_BUDGET') ? (int) SEO_ANALYZE_TIME_BUDGET : 120;

// The work itself lives in seo_structure.php, because the button on the Pages
// screen runs exactly the same pass with a smaller budget.
$run = pg_seo_analyze_batch($time_budget);

if ($run['analyzed']) {

    log_activity(lang(array(
        'string' => 'nightly job analyzed the HTML structure of {var:1} record{suffix:1}',
        'vars' => $run['analyzed'],
        'suffix' => (($run['analyzed'] > 1) ? 's' : ''))), '');
}


pg_cron_ran('seo_analyze_job');
