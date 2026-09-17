<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.7. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_7() {
    // ── Repair: the home page counted as two pages ───────────────────────
    //
    // Data repair only, no schema change. Same class of problem as 2026.1.29.
    //
    // A site's root is one page recorded under several names — '', '/',
    // 'index.php', and a legacy 'example.com/'. 2026.3.5's backfill grouped
    // by whatever string it found, so those became separate rows from the
    // ones carrying the home page's real name. The dashboard then listed the
    // home page twice, once under its own name and once as "Homepage", with
    // its traffic divided between the two entries.
    //
    // Rows recorded live were never affected: get_page.php resolves the home
    // page before tracking runs, so it always writes the real page name.
    //
    // Merges rather than deletes, so no view is lost. Counts fold into the
    // correct bucket and only the stray rows go.
    if (!db_item("SHOW TABLES LIKE 'visitor_content_hourly'")) {
        return;
    }

    $home = db_item("SELECT page_id, page_name FROM page WHERE page_home = 'yes' ORDER BY page_id LIMIT 1");

    if (!is_array($home) || empty($home['page_id'])) {
        return;
    }

    $home_id   = (int) $home['page_id'];
    $home_name = trim($home['page_name']);
    $aliases   = "'', '/', 'index.php', 'example.com/'";

    // One row per hour at most, so this is a small set even on a busy site
    // with years of history. Done in PHP rather than as a self-referencing
    // INSERT ... SELECT with ON DUPLICATE KEY UPDATE, which behaves
    // differently across MySQL versions when the source and target are the
    // same table.
    $strays = db_items(
        "SELECT id, stat_date, stat_hour, item_type, item_id, views
         FROM visitor_content_hourly
         WHERE page_name IN ($aliases) AND page_id <> '$home_id'"
    );

    if (!is_array($strays)) {
        return;
    }

    foreach ($strays as $stray) {

        $bucket_key = sha1(
            $stray['stat_date'] . '|' . (int) $stray['stat_hour'] . '|' . $home_id
            . '|' . $stray['item_type'] . '|' . (int) $stray['item_id']
        );

        db("INSERT INTO visitor_content_hourly
                (bucket_key, stat_date, stat_hour, page_id, page_name, item_type, item_id, views)
            VALUES
                ('" . e($bucket_key) . "', '" . e($stray['stat_date']) . "', " . (int) $stray['stat_hour'] . ",
                 $home_id, '" . e($home_name) . "', '" . e($stray['item_type']) . "', " . (int) $stray['item_id'] . ",
                 " . (int) $stray['views'] . ")
            ON DUPLICATE KEY UPDATE views = views + VALUES(views)");

        db("DELETE FROM visitor_content_hourly WHERE id = '" . (int) $stray['id'] . "'");
    }
}
