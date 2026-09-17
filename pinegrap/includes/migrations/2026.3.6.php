<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.6. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_6() {
    // ── visitors: MyISAM to InnoDB ───────────────────────────────────────
    //
    // MyISAM locks whole tables. update_visitor_page_data() runs an UPDATE on
    // this table for every page view, and an UPDATE takes an exclusive table
    // lock, so at this traffic level page views already serialise against one
    // another. Add a reporting query holding a read lock and every visitor on
    // the site queues behind it — which is why the dashboard being open made
    // the site slow.
    //
    // MyISAM's concurrent-insert optimisation does not rescue this: it only
    // applies while the table has no gaps, and a table under constant UPDATE
    // always has gaps.
    //
    // The other reason is recovery. An unclean shutdown leaves a MyISAM table
    // of this size needing REPAIR TABLE, which can run for hours with the
    // table unwritable throughout. InnoDB recovers from its log on startup.
    //
    // Safe to re-run: if the engine is already InnoDB this does nothing, so a
    // request killed part-way through simply repeats the ALTER next time.
    // Checked rather than assumed because ALTER TABLE ... ENGINE cannot be
    // resumed and is expensive to start over.
    //
    // Only `visitors` is converted. search_items carries the FULLTEXT indexes
    // that get_search_results.php queries with MATCH ... AGAINST, and older
    // MySQL supports FULLTEXT on MyISAM only.
    $status = db_item("SHOW TABLE STATUS LIKE 'visitors'");

    if (is_array($status) && isset($status['Engine']) && strtolower($status['Engine']) !== 'innodb') {
        db("ALTER TABLE visitors ENGINE=InnoDB");
    }
}
