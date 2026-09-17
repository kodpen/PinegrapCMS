<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.2.4. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_2_4() {
    // ── Web Application Firewall ─────────────────────────────────────────
    //
    // Ships DISABLED. Once an operator turns it on, it defaults to 'monitor',
    // which records what it would have blocked without blocking anything.
    // A firewall that starts rejecting traffic the moment it is installed is
    // how a storefront loses a day of orders.

    install_add_column('config', 'waf_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
    install_add_column('config', 'waf_mode', "ENUM('monitor', 'block') NOT NULL DEFAULT 'monitor'");
    install_add_column('config', 'waf_sensitivity', "ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium'");
    install_add_column('config', 'waf_signature_scan', "TINYINT(1) NOT NULL DEFAULT 1");
    install_add_column('config', 'waf_rate_limit', "TINYINT(1) NOT NULL DEFAULT 1");
    install_add_column('config', 'waf_rate_limit_requests', "SMALLINT UNSIGNED NOT NULL DEFAULT 300");
    install_add_column('config', 'waf_rate_limit_sensitive', "SMALLINT UNSIGNED NOT NULL DEFAULT 30");
    install_add_column('config', 'waf_auto_ban', "TINYINT(1) NOT NULL DEFAULT 1");
    install_add_column('config', 'waf_auto_ban_threshold', "SMALLINT UNSIGNED NOT NULL DEFAULT 5");
    install_add_column('config', 'waf_auto_ban_minutes', "SMALLINT UNSIGNED NOT NULL DEFAULT 60");
    install_add_column('config', 'waf_block_attack_tools', "TINYINT(1) NOT NULL DEFAULT 1");
    install_add_column('config', 'waf_verify_bots', "TINYINT(1) NOT NULL DEFAULT 1");
    install_add_column('config', 'waf_trusted_proxies', "TEXT DEFAULT NULL");
    install_add_column('config', 'waf_exclusions', "TEXT DEFAULT NULL");
    install_add_column('config', 'waf_blocked_agents', "TEXT DEFAULT NULL");
    install_add_column('config', 'waf_log_retention_days', "SMALLINT UNSIGNED NOT NULL DEFAULT 30");

    // Last third-party WAF/CDN seen in front of this site (Cloudflare, Sucuri,
    // Akamai...). Recorded from real request headers and surfaced in Settings
    // so the operator knows whether this firewall is their first or second
    // layer of defence.
    install_add_column('config', 'waf_external_provider', "VARCHAR(64) NOT NULL DEFAULT ''");
    install_add_column('config', 'waf_external_seen', "INT UNSIGNED NOT NULL DEFAULT 0");

    // ── Event log ────────────────────────────────────────────────────────
    // 'action' records what the firewall DID, not what it saw: in monitor
    // mode an attack is stored as 'would-block'. That distinction is the
    // whole point of monitor mode.
    install_create_table('waf_log', "CREATE TABLE IF NOT EXISTS waf_log (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
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
        INDEX idx_timestamp (log_timestamp),
        INDEX idx_ip (ip_address),
        INDEX idx_action_timestamp (action, log_timestamp),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Rate limit buckets ───────────────────────────────────────────────
    // bucket_key is the PRIMARY KEY so the counter can be a single atomic
    // INSERT ... ON DUPLICATE KEY UPDATE. Two simultaneous requests from one
    // IP therefore cannot both read the same count and both write count+1.
    install_create_table('waf_rate', "CREATE TABLE IF NOT EXISTS waf_rate (
        bucket_key   VARCHAR(64) NOT NULL PRIMARY KEY,
        hits         INT UNSIGNED NOT NULL DEFAULT 0,
        window_start INT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_window (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Bot verification cache ───────────────────────────────────────────
    // Reverse DNS is the only way to tell the real Googlebot from anyone who
    // typed "Googlebot" into their user agent, and it costs real wall-clock
    // time. Verdicts are cached for a week.
    install_create_table('waf_ip_reputation', "CREATE TABLE IF NOT EXISTS waf_ip_reputation (
        ip_address VARCHAR(45) NOT NULL,
        bot_token  VARCHAR(40) NOT NULL DEFAULT '',
        verdict    ENUM('verified', 'spoofed', 'unknown') NOT NULL DEFAULT 'unknown',
        host_name  VARCHAR(255) NOT NULL DEFAULT '',
        checked_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (ip_address, bot_token),
        INDEX idx_checked (checked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Extend the existing IP ban table ─────────────────────────────────
    //
    // banned_ip_addresses already existed but held only permanent IPv4 block
    // entries. Three additions:
    //   list_type  — the same table now also holds the ALLOW list, so an
    //                operator can exempt their own office from every rule.
    //   source     — separates operator entries from automatic bans, which
    //                matters because settings.php rewrites the manual list
    //                wholesale and must not wipe automatic bans.
    //   expires_at — automatic bans always expire. A permanent ban placed by
    //                a rule is how a customer's whole office gets locked out
    //                with nobody knowing why.
    install_add_column('banned_ip_addresses', 'list_type', "ENUM('block', 'allow') NOT NULL DEFAULT 'block'");
    install_add_column('banned_ip_addresses', 'source', "ENUM('manual', 'auto') NOT NULL DEFAULT 'manual'");
    install_add_column('banned_ip_addresses', 'note', "VARCHAR(255) NOT NULL DEFAULT ''");
    install_add_column('banned_ip_addresses', 'expires_at', "INT UNSIGNED NOT NULL DEFAULT 0");
    install_add_column('banned_ip_addresses', 'created_at', "INT UNSIGNED NOT NULL DEFAULT 0");
    install_add_column('banned_ip_addresses', 'hit_count', "INT UNSIGNED NOT NULL DEFAULT 0");
    install_add_index('banned_ip_addresses', 'idx_list_type', "INDEX idx_list_type (list_type)");
    install_add_index('banned_ip_addresses', 'idx_expires', "INDEX idx_expires (expires_at)");

    // Existing rows are operator-placed permanent blocks; label them as such.
    db("UPDATE banned_ip_addresses
        SET list_type = 'block', source = 'manual', created_at = UNIX_TIMESTAMP()
        WHERE created_at = 0");
}
