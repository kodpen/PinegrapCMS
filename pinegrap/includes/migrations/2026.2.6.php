<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.2.6. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_2_6() {
    // ── Firewall log: aggregate instead of one row per request ───────────
    //
    // As shipped in 2026.2.4 the log wrote one row per event. A single burst
    // of automated traffic produced thousands of rows carrying identical
    // information, and during a sustained attack the logging became a bigger
    // load problem than the attack — the firewall DoSing its own database.
    //
    // Events are now folded into a five-minute bucket keyed on
    // (address, rule, action, category, path). The same attack repeated ten
    // thousand times is one row with hit_count = 10000, and the write is a
    // single INSERT ... ON DUPLICATE KEY UPDATE either way, so the table
    // stops growing under load instead of growing fastest exactly when it
    // can least afford to.
    //
    // The table is dropped rather than altered. It is one version old and
    // holds nothing but test noise, and adding a UNIQUE key to rows that all
    // share an empty event_key would fail outright on duplicates.
    //
    // The old shape is recognised by what it lacks: a waf_log without
    // event_key is the 2026.2.4 table and is replaced; a waf_log that has it
    // is already the new one and is left alone, log and all.
    if (install_table_exists('waf_log') && !install_column_exists('waf_log', 'event_key')) {
        install_drop_table('waf_log');
    }

    install_create_table('waf_log', "CREATE TABLE waf_log (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        event_key     CHAR(40) NOT NULL DEFAULT '',
        window_start  INT UNSIGNED NOT NULL DEFAULT 0,
        hit_count     INT UNSIGNED NOT NULL DEFAULT 1,
        ip_address    VARCHAR(45) NOT NULL DEFAULT '',
        action        VARCHAR(16) NOT NULL DEFAULT 'log',
        rule_id       VARCHAR(40) NOT NULL DEFAULT '',
        category      VARCHAR(32) NOT NULL DEFAULT '',
        score         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        method        VARCHAR(8) NOT NULL DEFAULT '',
        request_url   VARCHAR(512) NOT NULL DEFAULT '',
        target        VARCHAR(64) NOT NULL DEFAULT '',
        matched       VARCHAR(255) NOT NULL DEFAULT '',
        user_agent    VARCHAR(255) NOT NULL DEFAULT '',
        user_id       INT UNSIGNED NOT NULL DEFAULT 0,
        log_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
        last_seen     INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_event (event_key, window_start),
        INDEX idx_timestamp (log_timestamp),
        INDEX idx_last_seen (last_seen),
        INDEX idx_ip (ip_address),
        INDEX idx_action_timestamp (action, log_timestamp),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Hard ceiling on stored rows. Time-based retention alone cannot bound
    // the table: a big enough attack fills it inside the retention window.
    // The cap is what actually guarantees the log has a maximum size.
    install_add_column('config', 'waf_log_max_rows', "INT UNSIGNED NOT NULL DEFAULT 20000");

    // 30 days was too generous for a table that can take thousands of rows a
    // minute. Aggregation makes 14 days cheap, and the cap backstops it.
    install_modify_column('config', 'waf_log_retention_days', "SMALLINT UNSIGNED NOT NULL DEFAULT 14");
    db("UPDATE config SET waf_log_retention_days = 14 WHERE waf_log_retention_days = 30");
}
