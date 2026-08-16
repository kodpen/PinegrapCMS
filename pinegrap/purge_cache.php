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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');
validate_token_field();

$cleared = array();

// ── 0. LiteSpeed server-level cache purge ────────────────────────────────────
// Only claim this when the server really is LiteSpeed. Every other web server
// ignores the header without complaint, so reporting it unconditionally told the
// operator a purge had happened on Apache, nginx and IIS, where none had.
$server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
$is_litespeed    = (stristr($server_software, 'litespeed') !== false) || isset($_SERVER['LSWS_EDITION']);

if ($is_litespeed && !headers_sent()) {
    header('X-LiteSpeed-Purge: *');
    $cleared[] = 'LiteSpeed Cache';

    // Touching .htaccess makes LiteSpeed recycle its LSPHP workers and reload config.
    $htaccess_file = dirname(__FILE__) . '/.htaccess';
    if (file_exists($htaccess_file) && @touch($htaccess_file)) {
        $cleared[] = 'LiteSpeed Worker Reload (.htaccess)';
    }
}

// ── 1. PHP OPcache (bytecode) ────────────────────────────────────────────────
$opcache_reset_ok = false;
if (function_exists('opcache_reset')) {
    if (@opcache_reset()) {
        $opcache_reset_ok = true;
        $cleared[] = 'OPcache (Reset)';
    }
}

// Per-file invalidation is a fallback for hosts that refuse the full reset
// (opcache.restrict_api, or a control panel that disables opcache_reset).
//
// It used to run unconditionally, right after the reset had already emptied the
// cache. opcache_invalidate() returns TRUE when "there was nothing to
// invalidate", so every call succeeded and the counter reported how many .php
// files exist on disk -- the identical number on every single purge.
if (!$opcache_reset_ok && function_exists('opcache_invalidate')) {
    $root_dir = dirname(__FILE__) . '/';
    $invalidated_count = 0;

    try {
        $directory = new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator  = new RecursiveIteratorIterator($directory);

        foreach ($iterator as $file_info) {
            if ($file_info->isFile() && $file_info->getExtension() === 'php') {
                if (@opcache_invalidate($file_info->getPathname(), true)) {
                    $invalidated_count++;
                }
            }
        }
    } catch (Exception $e) {
        $php_files = glob($root_dir . '*.php');
        if (is_array($php_files)) {
            foreach ($php_files as $f) {
                if (@opcache_invalidate($f, true)) {
                    $invalidated_count++;
                }
            }
        }
    }

    if ($invalidated_count > 0) {
        $cleared[] = 'OPcache (' . $invalidated_count . ')';
    }
}

// ── 2. APC / APCu (application cache) ───────────────────────────────────────
// Report only when the extension actually confirms the flush.
if (function_exists('apc_clear_cache')) {
    $apc_user   = @apc_clear_cache('user');
    $apc_opcode = @apc_clear_cache('opcode');
    if ($apc_user || $apc_opcode) {
        $cleared[] = 'APC';
    }
}
if (function_exists('apcu_clear_cache')) {
    if (@apcu_clear_cache()) {
        $cleared[] = 'APCu';
    }
}

// The PHP stat cache was cleared here. It lives only for the duration of the
// current request and this script redirects a few lines below, so the call was
// discarded either way.

// ── 3. Touch backend CSS / JS ────────────────────────────────────────────────
// The backend header includes these as "?v=filemtime(...)" query strings, so a
// new timestamp does reach the browser. Two files, bounded cost.
$backend_dir   = dirname(__FILE__) . '/assets/';
$backend_files = array(
    $backend_dir . 'backend.min.css',
    $backend_dir . 'backend.min.js',
);
$backend_touched = 0;
foreach ($backend_files as $f) {
    if (file_exists($f) && @touch($f)) {
        $backend_touched++;
    }
}
if ($backend_touched > 0) {
    $cleared[] = lang('Backend assets') . ' (' . $backend_touched . ')';
}

// ── 4. Touch frontend system CSS / JS ───────────────────────────────────────
// pinegrap.min.css, frontend.min.css, responsive.min.css and friends carry a
// ?v=filemtime(...) suffix, so a new timestamp does reach the browser.
//
// These are kept because they ship inside the update package, and
// ZipArchive::extractTo() preserves the archive's timestamps -- a freshly
// updated file can land on disk with an mtime older than the copy the visitor
// already holds. Nine files at most.
$root             = dirname(__FILE__) . '/';
$frontend_touched = 0;
$min_css = glob($root . '*.min.css');
if (is_array($min_css)) {
    foreach ($min_css as $f) {
        if (@touch($f)) {
            $frontend_touched++;
        }
    }
}
$min_js = glob($root . '*.min.js');
if (is_array($min_js)) {
    foreach ($min_js as $f) {
        if (@touch($f)) {
            $frontend_touched++;
        }
    }
}
if ($frontend_touched > 0) {
    $cleared[] = lang('Frontend assets') . ' (' . $frontend_touched . ')';
}

// ── Removed: bulk touch of theme files and uploaded images ───────────────────
//
// Both directories are served by get_file.php, which sends
// "Cache-Control: public, max-age=604800". While that max-age is fresh the
// browser makes no request at all, so it never sees the new Last-Modified and
// the 304 handshake further up in get_file.php never runs. Touching 63 theme
// files and 134 images changed nothing for a week, and the counts printed on
// screen were simply how many files the directory held -- identical on every
// purge, whether or not anything had been edited.
//
// Since output-time asset versioning landed, mtime reaches the browser inside
// the URL, so editing a file invalidates it on its own. That also inverts the
// old cost: touching every image would now hand each visitor a full re-download
// of the whole media library for no reason.

// ── 5. Application file caches in data/temp ─────────────────────────────────
// The system status bar on settings.php and welcome.php is served from a 10
// minute file cache, and the database health check behind it from a separate
// 1 hour one. Without dropping these, a purge did nothing to the one widget the
// operator is usually staring at while purging.
//
// data/temp/hash_reference.json is deliberately NOT touched: it is the file
// integrity baseline, not a cache. Deleting it would make the integrity check
// report a tampered installation.
$temp_directory = dirname(__FILE__) . '/data/temp/';
$temp_cache_files = array(
    'system_status_cache.json',
    'db_health_cache.json',
);
$temp_cleared = 0;
foreach ($temp_cache_files as $temp_cache_file) {
    $temp_cache_path = $temp_directory . $temp_cache_file;
    if (file_exists($temp_cache_path) && @unlink($temp_cache_path)) {
        $temp_cleared++;
    }
}
if ($temp_cleared > 0) {
    $cleared[] = lang('System status') . ' (' . $temp_cleared . ')';
}

$message = implode(', ', $cleared);
if ($message === '') {
    $message = lang('nothing to clear');
}

log_activity(lang(array('string' => 'Cache purged ({var:1}).', 'vars' => array($message))), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform = new liveform('settings');
$liveform->add_notice(lang(array('string' => 'Cache cleared: {var:1}', 'vars' => array($message))));
header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/settings.php');
exit();