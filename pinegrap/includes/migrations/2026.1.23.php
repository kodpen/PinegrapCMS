<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.23. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_23() {
    // Performance monitor — per-request runtime metrics for admin diagnostics.
    // Written via register_shutdown_function() (after fastcgi_finish_request when available)
    // so the request-path overhead is one row insert + a probabilistic retention sweep.
    //
    // duration_ms / cpu_*_ms are integers (sub-ms noise is below sampling resolution anyway).
    // peak_memory_kb stores memory_get_peak_usage(true) / 1024 so 32-bit hosts stay safe.
    // request_url uses VARCHAR(512) instead of 2083 to keep the index size sane on utf8mb4.
    db("CREATE TABLE IF NOT EXISTS perf_log (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        request_url     VARCHAR(512) NOT NULL DEFAULT '',
        script_name     VARCHAR(255) NOT NULL DEFAULT '',
        area            VARCHAR(16) NOT NULL DEFAULT 'frontend',
        method          VARCHAR(8) NOT NULL DEFAULT 'GET',
        http_status     SMALLINT UNSIGNED NOT NULL DEFAULT 200,
        duration_ms     INT UNSIGNED NOT NULL DEFAULT 0,
        peak_memory_kb  INT UNSIGNED NOT NULL DEFAULT 0,
        cpu_user_ms     INT UNSIGNED NOT NULL DEFAULT 0,
        cpu_system_ms   INT UNSIGNED NOT NULL DEFAULT 0,
        user_id         INT UNSIGNED NOT NULL DEFAULT 0,
        is_ajax         TINYINT(1) NOT NULL DEFAULT 0,
        log_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_timestamp (log_timestamp),
        INDEX idx_duration (duration_ms),
        INDEX idx_peak_memory (peak_memory_kb),
        INDEX idx_script (script_name),
        INDEX idx_area_timestamp (area, log_timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
