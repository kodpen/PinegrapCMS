<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.3. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_3() {
    // Performance monitor on/off, in Site Settings rather than config.php.
    //
    // Defaults to on: the monitor is how a slow page gets noticed at all, and
    // after the summary rewrite it costs a fraction of a millisecond per
    // request — on PHP-FPM, after the response has already been sent, so the
    // visitor waits for none of it.
    install_add_column('config', 'perf_monitor', "TINYINT(1) NOT NULL DEFAULT 1");

    // Discard whatever the summary already holds.
    //
    // Before the sanity gate was added, a request whose start time came back
    // as zero produced a duration of roughly 1.7 trillion milliseconds. MySQL
    // clamped it to the unsigned ceiling without complaint, and because a
    // summary row accumulates rather than replaces, that one measurement made
    // its bucket's average permanently meaningless — one install reported an
    // average of 595,400,352,033 ms.
    //
    // There is no way to tell a poisoned bucket from a healthy one after the
    // fact, and the table refills within the hour, so the honest move is to
    // start again.
    if (install_table_exists('perf_stats')) {
        db("TRUNCATE perf_stats");
    }
}
