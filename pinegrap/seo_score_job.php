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

// Nightly SEO score recalculation. It should be run by a cron job, like
// job.php.
//
// Everything is invalidated first, then recomputed. The full pass is not
// redundant work: the uniqueness checks (duplicated titles and descriptions)
// depend on every other row, so a row whose own fields never changed can
// still be stale because a different row started or stopped sharing its
// title. Editing screens only mark the row they save; this pass is what
// keeps everyone else honest.
//
// The stale flag itself is the cursor. A run that hits the time budget leaves
// the remaining rows marked stale, and the next run - or the list screens,
// or the refresh button - continues from exactly there. No bookkeeping
// column, nothing to reset after a crash.

require('init.php');
require_once(dirname(__FILE__) . '/seo.php');

// Nothing to do before the upgrade that added the columns. Without this the
// three invalidating UPDATEs below would run every night for a pass that
// pg_seo_recalculate() then declines to perform.
if (!pg_seo_schema_ready()) {
    pg_cron_ran('seo_score_job');
    exit;
}

$total_processed = 0;
$total_remaining = 0;

// Days after which a record is rescored even if nothing edited it. Shares the
// structure job's setting: both exist for the same reason, that a record can
// stop being correct because something else changed.
$full_refresh_days = defined('SEO_ANALYZE_FULL_REFRESH_DAYS') ? (int) SEO_ANALYZE_FULL_REFRESH_DAYS : 7;

// Products before pages on purpose: a catalog page's score is derived from
// the scores of the products it lists, so the sources have to be fresh
// before the pages that read them are computed.
$types = array();

// Product and group scores only matter when the store is on. The columns
// exist either way, but scoring records the operator cannot see would only
// produce numbers nobody acts on.
if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
    $types[] = 'product';
    $types[] = 'product_group';
}

$types[] = 'page';

foreach ($types as $type) {
    if ($type == 'page') {
        $table = 'page';
    } elseif ($type == 'product') {
        $table = 'products';
    } else {
        $table = 'product_groups';
    }

    // Only start a fresh full pass when the previous one finished; otherwise
    // continue the leftover stale rows so repeated runs converge instead of
    // starting over every night on a site too large for one budget.
    //
    // The refresh is driven off seo_checked_at rather than by blanking the
    // whole column: the pass exists because uniqueness is a cross-row
    // property and a record can go stale without being touched, but marking
    // every row stale at once used to be indistinguishable from "never
    // scored" on screen, so the morning after a big site showed dashes
    // instead of the perfectly good scores it already had.
    if (!db_value("SELECT COUNT(*) FROM `" . $table . "` WHERE seo_analysis_current = 0")) {

        $refresh_before = time() - ($full_refresh_days * 86400);

        db(
            "UPDATE `" . $table . "`
            SET seo_analysis_current = 0
            WHERE seo_checked_at < '" . (int) $refresh_before . "'");
    }

    // Queue records whose measured speed moved, before the pass that reads it.
    // Speed changes without anyone editing anything, so nothing else would
    // mark these stale until the periodic full refresh came round.
    pg_seo_refresh_speed($type);

    $run = pg_seo_recalculate($type, null, 60);

    $total_processed += $run['processed'];
    $total_remaining += $run['remaining'];
}

// If at least one record was processed, then log activity.
if ($total_processed) {

    $plural_suffix = '';

    if ($total_processed > 1) {
        $plural_suffix = 's';
    }

    log_activity(lang(array(
        'string' => 'nightly job recalculated the SEO score of {var:1} record{suffix:1}',
        'vars' => $total_processed,
        'suffix' => $plural_suffix)), '');
}

pg_cron_ran('seo_score_job');
