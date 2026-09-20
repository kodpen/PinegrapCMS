<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The System Status widget and its checks, cache purge, file integrity, write permissions, changelog reading, table repair.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The host this installation answers on, the way the integrity reference records it:
// lowercase, without a port. A reference generated here carries it, and that is what
// makes the reference authoritative here and nowhere else.
function pg_integrity_host()
{
    $host = defined('HOSTNAME') ? (string) HOSTNAME : (isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');

    $host = strtolower(trim($host));

    if (($colon = strrpos($host, ':')) !== false && strpos($host, ']') === false) {
        $host = substr($host, 0, $colon);
    }

    return $host;
}

/**
 * The file entries of a reference: the keys whose value is a SHA-256 digest.
 *
 * A reference can carry things that are not files, and used to carry them by
 * accident - the generator's stamp, and the bookkeeping older versions wrote
 * into the reference itself before it moved to hash_reference_state.json. They
 * were dropped by name, which can only cover the names known when that list was
 * written: a copy still carrying last_local_check was compared against the disk
 * and reported it as a missing file on every installation reading that copy.
 * Deciding by shape answers the question for anything that is not a hash,
 * whatever it is called and wherever the reference came from.
 */
function pg_integrity_reference_files($reference)
{
    if (!is_array($reference)) {
        return [];
    }

    $files = [];

    foreach ($reference as $path => $hash) {
        if (is_string($hash) && preg_match('/^[0-9a-f]{64}$/', $hash)) {
            $files[$path] = $hash;
        }
    }

    return $files;
}

/**
 * Verifies SHA-256 hashes of the shipped source files - those directly in the
 * software directory and everything under the subdirectories the reference lists,
 * includes/ among them - against data/temp/hash_reference.json to detect tampering
 * or missing files. Ignores server-generated files like error_log, .htaccess, etc.
 *
 * The reference is one of two things, and the two are treated differently:
 *
 *   - Generated on THIS installation for THIS version by _software_create_hash.php,
 *     which stamps it ("_generated": host, version, time). That is the ground truth
 *     here: the files are compared against it hourly and it is never replaced from
 *     the network. Before this rule the development machine could not be silenced -
 *     every regenerated reference was fetched over by the copy on kodpen.com within
 *     the same request, and the new files it listed came back as "extra".
 *   - Fetched from kodpen.com for the running version (a customer site, or a copy of
 *     the generated file that travelled in the package - the stamp names another
 *     host). Compared hourly; every 12 hours compared with kodpen.com again and
 *     replaced by that copy, so a reference an intruder rewrote to match their files
 *     is caught by the copy they cannot rewrite.
 *
 * The check's own bookkeeping - when it last ran, what it found, when it last asked
 * kodpen.com - lives in data/temp/hash_reference_state.json, NOT in the reference.
 * Earlier versions wrote it into the reference itself; a reference then uploaded to
 * kodpen.com carried "last_local_check" and "last_local_result" as if they were
 * files, and every site reported them missing. What counts as a file is decided
 * by shape now (pg_integrity_reference_files()), so a reference carrying anything
 * else is read for its files and the rest is ignored.
 *
 * Returns 'success', 'missing_or_tampered_files', 'unable_to_fetch_reference',
 * 'unable_to_resolve_directory' or 'version_not_defined'.
 */
function check_directory_file_integrity()
{
    // Path to the local reference file, and to the bookkeeping beside it
    $reference_file = 'data/temp/hash_reference.json';
    $state_file = 'data/temp/hash_reference_state.json';

    // Files that should be ignored during the integrity check, by name, wherever
    // they appear in the tree
    $ignored_files = ['error_log', '.htaccess', '.user.ini', '.DS_Store', 'Thumbs.db'];

    // Get current software version (must be defined as a constant)
    $current_version = defined('VERSION') ? VERSION : null;
    if ($current_version === null) {
        log_activity(lang('VERSION constant not defined.'), 'SYSTEM');
        return 'version_not_defined';
    }

    // The copy on kodpen.com for this version, decoded and stripped of anything
    // that is not a file; false when it cannot be fetched or is not JSON.
    $fetch_remote = function () use ($current_version) {
        $remote_url = 'https://kodpen.com/pinegrap_hash_referance[' . $current_version . '].json';
        $remote_context = stream_context_create(['http' => ['timeout' => 3]]);
        $remote_data = @file_get_contents($remote_url, false, $remote_context);

        if ($remote_data === false) {
            return false;
        }

        $remote_hashes = json_decode($remote_data, true);

        if (!is_array($remote_hashes)) {
            return false;
        }

        return pg_integrity_reference_files($remote_hashes);
    };

    // The bookkeeping
    $state = ['last_hash_check' => 0, 'last_local_check' => 0, 'last_local_result' => null, 'reference_stamp' => ''];

    if (is_file($state_file)) {
        $saved_state = json_decode((string) @file_get_contents($state_file), true);

        if (is_array($saved_state)) {
            $state = array_merge($state, $saved_state);
        }
    }

    // If reference file does not exist, fetch it from remote server
    if (!file_exists($reference_file)) {
        $remote_hashes = $fetch_remote();

        if ($remote_hashes === false) {
            log_activity(lang("Unable to fetch remote hash reference."), 'SYSTEM');
            return 'unable_to_fetch_reference';
        }

        if (!is_dir(dirname($reference_file))) {
            mkdir(dirname($reference_file), 0755, true);
        }

        file_put_contents($reference_file, json_encode($remote_hashes, JSON_PRETTY_PRINT));

        $state['last_hash_check'] = time();
    }

    // Get the current working directory
    $base_path = getcwd();
    if ($base_path === false) {
        log_activity(lang('Unable to resolve current working directory.'), 'SYSTEM');
        return 'unable_to_resolve_directory';
    }

    // Read and decode the reference file
    $stored_hashes = json_decode(file_get_contents($reference_file), true);
    if (!is_array($stored_hashes) || empty($stored_hashes)) {
        log_activity(lang('Hash reference file is corrupted, unreadable, or empty.'), 'SYSTEM');
        return 'unable_to_resolve_directory';
    }

    // Bookkeeping an older version wrote into the reference: taken over once, when
    // there is no state file yet, and ignored as files from here on.
    if (!is_file($state_file)) {
        foreach (['last_hash_check', 'last_local_check'] as $legacy_key) {
            if (isset($stored_hashes[$legacy_key])) {
                $state[$legacy_key] = (int) $stored_hashes[$legacy_key];
            }
        }
    }

    $generated = (isset($stored_hashes['_generated']) && is_array($stored_hashes['_generated'])) ? $stored_hashes['_generated'] : null;

    $stored_hashes = pg_integrity_reference_files($stored_hashes);

    // A reference with no file in it cannot answer the question it exists for.
    if (empty($stored_hashes)) {
        log_activity(lang('Hash reference file is corrupted, unreadable, or empty.'), 'SYSTEM');
        return 'unable_to_resolve_directory';
    }

    // A reference generated on this host for this version is the truth here.
    $authoritative = ($generated !== null)
        && isset($generated['version'], $generated['host'])
        && ((string) $generated['version'] === (string) $current_version)
        && (strtolower((string) $generated['host']) === pg_integrity_host());

    // A regenerated or replaced reference must not be answered from the cached result
    // of the previous one.
    $reference_stamp = md5(@filemtime($reference_file) . '|' . @filesize($reference_file) . '|' . json_encode($generated));

    if ($state['reference_stamp'] !== $reference_stamp) {
        $state['last_local_check'] = 0;
        $state['last_local_result'] = null;
        $state['reference_stamp'] = $reference_stamp;
    }

    // Short-circuit: return cached result if the local file hash run is less than 1 hour old.
    if (($state['last_local_result'] !== null) && ((time() - (int) $state['last_local_check']) < 3600)) {
        return $state['last_local_result'];
    }

    // Array to collect tampered or missing files
    $tampered_files = [];

    // Compare each stored hash with the current file hash.
    //
    // Entries in subdirectories used to be skipped outright, which meant the
    // whole of includes/ went unchecked - the authentication code, the external
    // API's credential check and the payment libraries among it. They are
    // checked now; the reference file lists them with forward slashes.
    //
    // The reference can arrive over the network, so a path out of it is treated
    // as untrusted input: anything absolute, anything with a drive letter and
    // anything containing a .. segment is refused rather than resolved. Only
    // files inside the software directory are ever hashed.
    foreach ($stored_hashes as $relative_path => $stored_hash) {
        $relative_path = str_replace('\\', '/', (string) $relative_path);

        if (
            $relative_path === '' ||
            $relative_path[0] === '/' ||
            strpos($relative_path, '..') !== false ||
            preg_match('#^[a-zA-Z]:#', $relative_path) === 1 ||
            in_array(basename($relative_path), $ignored_files)
        ) {
            continue;
        }

        $full_path = $base_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);

        if (file_exists($full_path)) {
            $current_hash = hash_file('sha256', $full_path);
            if ($current_hash !== $stored_hash) {
                $tampered_files[] = $relative_path;
            }
        } else {
            $tampered_files[] = $relative_path . ' (missing)';
        }
    }

    // --- Remote vs Local comparison every 12 hours ---
    //
    // Only for a reference that did not come from here. The generated one is
    // compared with nothing and replaced by nothing: it IS the reference, and the
    // copy on kodpen.com is uploaded from it, not the other way round.
    $now = time();

    if (!$authoritative && (($now - (int) $state['last_hash_check']) >= 43200)) { // 12 hours
        $remote_hashes = $fetch_remote();

        if ($remote_hashes !== false) {
            // Find differences
            $missing_in_local = array_diff_key($remote_hashes, $stored_hashes);
            $extra_in_local = array_diff_key($stored_hashes, $remote_hashes);

            foreach ($missing_in_local as $file => $hash) {
                $tampered_files[] = $file . ' (missing in local reference)';
            }
            foreach ($extra_in_local as $file => $hash) {
                $tampered_files[] = $file . ' (extra in local reference)';
            }
            foreach ($remote_hashes as $file => $hash) {
                if (isset($stored_hashes[$file]) && $stored_hashes[$file] !== $hash) {
                    $tampered_files[] = $file . ' (hash mismatch with remote)';
                }
            }

            // The copy on kodpen.com replaces the local reference
            file_put_contents($reference_file, json_encode($remote_hashes, JSON_PRETTY_PRINT));
            $stored_hashes = $remote_hashes;
        }

        // Reached or not, the next attempt is 12 hours away; an unreachable server
        // must not be asked on every request.
        $state['last_hash_check'] = $now;
    }
    // --- END Remote vs Local comparison ---

    $local_result = empty($tampered_files) ? 'success' : 'missing_or_tampered_files';

    // Persist the result beside the reference so the next request within 1 hour can
    // skip the per-file hashing entirely. The stamp is taken again because the
    // reference may just have been replaced.
    clearstatcache(true, $reference_file);

    $state['last_local_check'] = time();
    $state['last_local_result'] = $local_result;
    $state['reference_stamp'] = md5(@filemtime($reference_file) . '|' . @filesize($reference_file) . '|' . json_encode($generated));

    @file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    // If tampered or missing files are found, log them
    if ($local_result === 'missing_or_tampered_files') {
        $output_names = '';
        foreach ($tampered_files as $tampered_file) {
            $output_names .= " " . $tampered_file . ", ";
        }

        log_activity(
            lang([
                'string' => 'Tampered or missing files detected: {var:1}',
                'vars' => [$output_names]
            ])
            ,
            'SYSTEM'
        );
    }

    return $local_result;
}

/**
 * Checks whether the current request is securely served over HTTPS
 * and returns 'success' or 'warning' for UI feedback.
 */
function check_ssl_status()
{
    // Common server variables that indicate HTTPS usage
    $https_enabled = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
    );

    if ($https_enabled) {
        return 'success';
    } else {
        return 'warning';
    }
}

/**
 * Function: pg_storage_usage
 * ------------------------------------------
 * What this installation is holding, in bytes: the database, the backup
 * folder and the file library. Reported on the System Status card and scored
 * nowhere -- there is no size at which any of the three is wrong, and any
 * threshold set here would be a number invented in this file and charged to
 * sites that grew.
 *
 * Cached for six hours in a file of its own, NOT in the status cache. The
 * status cache turns over every ten minutes and two of these are cheap, but
 * the backup folder is a recursive walk over what can be gigabytes -- the same
 * walk backups.php does on its own screen, where the operator asked for it.
 * The dashboard did not ask for it, and a card that is drawn dozens of times a
 * day must not pay for it dozens of times a day. Sizes move slowly enough that
 * six hours is indistinguishable from live.
 *
 * Every figure is optional. information_schema is readable for one's own
 * schema on ordinary hosting but not everywhere, the backup folder may not
 * exist, and files.size is a column an old installation may predate. A null
 * simply leaves that line out rather than printing a confident zero.
 *
 * Compatibility: PHP 7.0 - 8.5
 * Returns: array('database' => int|null, 'backups' => int|null, 'files' => int|null, 'total' => int)
 */

function pg_storage_usage($force = false)
{
    $cache_file = PG_FUNCTIONS_DIR . '/data/temp/storage_usage.json';

    if (!$force && file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached) && isset($cached['ts']) && ((time() - $cached['ts']) < 21600)) {
            return $cached;
        }
    }

    // The storage engine's own estimate, which is what every other tool
    // reports for a MySQL database.
    $database = db_value(
        "SELECT SUM(data_length + index_length)
         FROM information_schema.TABLES
         WHERE table_schema = DATABASE()");

    $database = (($database === null) || ($database === '')) ? null : (int) $database;

    // From the table, not from a walk of the directory. files.size is written
    // when a file is stored and is the same number the file manager prints, so
    // reading it costs one scan of a small table instead of a stat per file --
    // and the two screens cannot come to disagree.
    $files = null;

    if (function_exists('waf_table_has_column') ? waf_table_has_column('files', 'size') : true) {
        $files_total = db_value("SELECT SUM(size) FROM files");
        $files = (($files_total === null) || ($files_total === '')) ? null : (int) $files_total;
    }

    // The expensive one, and the reason this function has a cache of its own.
    $backup_path = PG_FUNCTIONS_DIR . '/data/backups';
    $backups = is_dir($backup_path) ? (int) folderSize($backup_path) : null;

    $result = array(
        'ts'       => time(),
        'database' => $database,
        'backups'  => $backups,
        'files'    => $files,
        'total'    => (int) $database + (int) $backups + (int) $files,
    );

    $cache_dir = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    file_put_contents($cache_file, json_encode($result));

    return $result;
}

/**
 * Function: pg_purge_caches
 * ------------------------------------------
 * Clears every cache this software controls and reports what was actually
 * cleared. Two callers: purge_cache.php, which is a screen and redirects, and
 * api.php, which answers the System Status widget in JSON without leaving the
 * dashboard. The work is written once here because a purge that clears
 * different things depending on which door it was opened from is a purge the
 * operator cannot reason about.
 *
 * Everything is reported only on confirmation. An unconditional list told the
 * operator a LiteSpeed purge had happened on IIS, and printed a file count that
 * was simply how many files the directory held.
 *
 * Compatibility: PHP 7.0 - 8.5
 * Returns: array('cleared' => array of string, 'message' => string)
 */

function pg_purge_caches()
{
    $cleared = array();

    // LiteSpeed server-level cache purge. Only claim this when the server
    // really is LiteSpeed; every other web server ignores the header without
    // complaint, so reporting it unconditionally told the operator a purge had
    // happened on Apache, nginx and IIS, where none had.
    $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
    $is_litespeed    = (stristr($server_software, 'litespeed') !== false) || isset($_SERVER['LSWS_EDITION']);

    if ($is_litespeed && !headers_sent()) {
        header('X-LiteSpeed-Purge: *');
        $cleared[] = 'LiteSpeed Cache';

        // Touching .htaccess makes LiteSpeed recycle its LSPHP workers and
        // reload config.
        $htaccess_file = PG_FUNCTIONS_DIR . '/.htaccess';
        if (file_exists($htaccess_file) && @touch($htaccess_file)) {
            $cleared[] = 'LiteSpeed Worker Reload (.htaccess)';
        }
    }

    // PHP OPcache (bytecode). function_exists() does not answer the question:
    // the extension can be compiled in while opcache.enable is off, and then
    // every function exists, returns false and emits a warning. Ask the cache
    // whether it is running, and treat "cannot tell" (opcache.restrict_api
    // hides the status) as "try anyway" -- a reset may still be permitted.
    $opcache_status  = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
    $opcache_running = is_array($opcache_status) ? !empty($opcache_status['opcache_enabled']) : null;

    $opcache_reset_ok = false;
    if (($opcache_running !== false) && function_exists('opcache_reset')) {
        if (@opcache_reset()) {
            $opcache_reset_ok = true;
            $cleared[] = 'OPcache (Reset)';
        }
    }

    // Per-file invalidation is a fallback for hosts that refuse the full reset
    // (opcache.restrict_api, or a control panel that disables opcache_reset).
    //
    // It used to run unconditionally, right after the reset had already emptied
    // the cache. opcache_invalidate() returns TRUE when "there was nothing to
    // invalidate", so every call succeeded and the counter reported how many
    // .php files exist on disk -- the identical number on every single purge.
    if (!$opcache_reset_ok && ($opcache_running !== false) && function_exists('opcache_invalidate')) {
        $root_dir = PG_FUNCTIONS_DIR . '/';
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

    // APC / APCu (application cache). Report only when the extension actually
    // confirms the flush.
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

    // The PHP stat cache was cleared here. It lives only for the duration of
    // the current request, so the call was discarded either way.

    // Touch backend CSS / JS. The backend header includes these as
    // "?v=filemtime(...)" query strings, so a new timestamp does reach the
    // browser. Two files, bounded cost.
    $backend_dir   = PG_FUNCTIONS_DIR . '/assets/';
    $backend_files = array(
        $backend_dir . 'css/backend.src.css',
        $backend_dir . 'js/backend.src.js',
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

    // Touch frontend system CSS / JS. Of the root *.min.css / *.min.js files
    // only frontend.<suffix>.js is linked with a ?v=filemtime(...) suffix
    // (get_page_content.php), so that is the file whose new timestamp reaches
    // the browser; the other files matched by the globs are touched harmlessly.
    //
    // These are kept because they ship inside the update package, and
    // ZipArchive::extractTo() preserves the archive's timestamps -- a freshly
    // updated file can land on disk with an mtime older than the copy the
    // visitor already holds. Nine files at most.
    $root             = PG_FUNCTIONS_DIR . '/';
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

    // Removed: bulk touch of theme files and uploaded images.
    //
    // Both directories are served by get_file.php, which sends
    // "Cache-Control: public, max-age=604800". While that max-age is fresh the
    // browser makes no request at all, so it never sees the new Last-Modified
    // and the 304 handshake further up in get_file.php never runs. Touching 63
    // theme files and 134 images changed nothing for a week, and the counts
    // printed on screen were simply how many files the directory held.
    //
    // Since output-time asset versioning landed, mtime reaches the browser
    // inside the URL, so editing a file invalidates it on its own. That also
    // inverts the old cost: touching every image would now hand each visitor a
    // full re-download of the whole media library for no reason.

    // Application file caches in data/temp. The System Status widget is served
    // from a 10 minute file cache, and the database health check behind it from
    // a separate 1 hour one. Without dropping these, a purge did nothing to the
    // one widget the operator is usually staring at while purging.
    //
    // data/temp/hash_reference.json is deliberately NOT touched: it is the file
    // integrity baseline, not a cache. Deleting it would make the integrity
    // check report a tampered installation.
    $temp_directory = PG_FUNCTIONS_DIR . '/data/temp/';
    $temp_cache_files = array(
        'system_status_cache.json',
        'db_health_cache.json',
        'storage_usage.json',
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

    return array(
        'cleared' => $cleared,
        'message' => $message,
    );
}

/**
 * Function: pg_write_permission_scan
 * ------------------------------------------
 * Every folder and file of the software directory the web server cannot
 * write to.
 *
 * An update is applied by two different users on many servers: the panel's
 * own updater runs as the web server, a package extracted through the file
 * manager or FTP runs as the hosting account. A folder either of them cannot
 * write into keeps its old files through every extraction - a new migration
 * file is never created there, an old one is never replaced - and the
 * software then runs new code on an old schema. dev.kodpen.com had
 * includes/migrations at 0555 with its files at 0666: the files could be
 * overwritten, nothing could be added or renamed into place, and the old
 * 2026.4.4.php survived five extractions. This scan is what would have said
 * so beforehand.
 *
 * Folders are the question that matters (a file that cannot be replaced can
 * still be deleted and rewritten when its folder allows it, which is what
 * pg_extract_archive() does), so every folder is checked. Files are checked
 * everywhere except under data/, whose contents are the site's own (backups,
 * caches, uploads) and are never shipped.
 *
 * Memoized per request: the System Status check and the repair's report both
 * read it. The status cache (ten minutes) holds the result between requests.
 *
 * Compatibility: PHP 7.0 - 8.5
 * Returns: array(
 *     'directories'         => array relative path => octal mode, the folders that refuse (at most 200)
 *     'files'               => array relative path => octal mode, the files that refuse (at most 200)
 *     'directories_count'   => int   every folder that refuses, listed or not
 *     'files_count'         => int
 *     'checked_directories' => int
 *     'checked_files'       => int
 *     'truncated'           => bool  the walk stopped at its ceiling
 * )
 */
function pg_write_permission_scan($fresh = false)
{
    static $result = null;

    if (($result !== null) && !$fresh) {
        return $result;
    }

    $result = array(
        'directories' => array(),
        'files' => array(),
        'directories_count' => 0,
        'files_count' => 0,
        'checked_directories' => 0,
        'checked_files' => 0,
        'truncated' => false,
    );

    $root = rtrim(str_replace('\\', '/', PG_FUNCTIONS_DIR), '/');

    // is_writable() answers from the stat cache, and the repair runs in the
    // same request as the scan it reports on.
    clearstatcache();

    $mode = function ($path) {
        $perms = @fileperms($path);
        return ($perms === false) ? '?' : substr(sprintf('%o', $perms), -4);
    };

    // Whether a folder takes a new file. is_writable() answers that from the
    // permission bits, which is the truth on Linux and a guess on Windows: there
    // the read-only attribute on a folder means "customised view" (OneDrive and
    // Explorer set it freely) and stops nothing, yet is_writable() reads it as
    // closed. So a folder that looks closed on Windows is asked the only way that
    // settles it - by creating a file in it - and the answer replaces the guess.
    // Linux keeps the cheap test; a folder that refuses there really refuses.
    $folder_writable = function ($directory) {
        if (is_writable($directory)) {
            return true;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }

        $probe = @tempnam($directory, 'pgw');

        if (($probe === false) || (strpos(str_replace('\\', '/', $probe), rtrim(str_replace('\\', '/', $directory), '/') . '/') !== 0)) {
            // tempnam() falls back to the system temp directory when the folder
            // refuses; a file that landed elsewhere is not an answer
            if ($probe !== false) {
                @unlink($probe);
            }

            return false;
        }

        @unlink($probe);

        return true;
    };

    $result['checked_directories'] = 1;

    if (!$folder_writable($root)) {
        $result['directories']['.'] = $mode($root);
        $result['directories_count']++;
    }

    try {
        // No FOLLOW_SYMLINKS, so a link out of the tree is not walked, and
        // CATCH_GET_CHILD so a folder that cannot be listed is reported as
        // itself rather than ending the walk.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $seen = 0;

        foreach ($iterator as $path => $entry) {
            $seen++;

            // The software directory holds a few thousand entries; a site whose
            // data/ folder has grown past this is answered from what was seen.
            if ($seen > 40000) {
                $result['truncated'] = true;
                break;
            }

            $relative = substr(str_replace('\\', '/', $path), strlen($root) + 1);

            if ($entry->isDir()) {
                $result['checked_directories']++;

                if (!$folder_writable($path)) {
                    $result['directories_count']++;

                    if (count($result['directories']) < 200) {
                        $result['directories'][$relative] = $mode($path);
                    }
                }

                continue;
            }

            if (!$entry->isFile()) {
                continue;
            }

            // The site's own files are not the update's business.
            if ((strpos($relative, 'data/') === 0)) {
                continue;
            }

            $result['checked_files']++;

            if (!is_writable($path)) {
                $result['files_count']++;

                if (count($result['files']) < 200) {
                    $result['files'][$relative] = $mode($path);
                }
            }
        }
    } catch (\Throwable $e) {
        $result['truncated'] = true;
    }

    ksort($result['directories']);
    ksort($result['files']);

    return $result;
}

/**
 * Function: pg_write_permission_repair
 * ------------------------------------------
 * Opens every folder and file pg_write_permission_scan() reported: 0777 on
 * folders, 0666 on files. Not 0755/0644, on purpose - the web server owns
 * some of these entries and the hosting account owns others, and both have
 * to be able to replace them, which only "everyone" covers without knowing
 * which user is which. On a server with one tenant that costs nothing; on a
 * shared server the operator is told what the button does before it runs.
 *
 * chmod() only works for the owner of an entry (or root). An entry that
 * belongs to another user stays as it is and is reported by name: the fix
 * for that one is chown or chmod from the hosting panel, and the report says
 * so rather than leaving the operator to guess why a button did nothing.
 *
 * Compatibility: PHP 7.0 - 8.5
 * Returns: array('status' => 'success'|'partial'|'error', 'message' => string,
 *                'fixed' => int, 'failed' => array relative path => mode)
 */
function pg_write_permission_repair()
{
    $scan = pg_write_permission_scan(true);

    $root = rtrim(str_replace('\\', '/', PG_FUNCTIONS_DIR), '/');

    $todo = array();

    foreach ($scan['directories'] as $relative => $mode) {
        $todo[] = array('path' => ($relative === '.') ? $root : $root . '/' . $relative, 'relative' => $relative, 'mode' => 0777);
    }

    foreach ($scan['files'] as $relative => $mode) {
        $todo[] = array('path' => $root . '/' . $relative, 'relative' => $relative, 'mode' => 0666);
    }

    if (count($todo) === 0) {
        return array(
            'status' => 'success',
            'message' => lang('Every folder and file of the software is already writable by the web server.'),
            'fixed' => 0,
            'failed' => array(),
        );
    }

    $fixed = 0;
    $failed = array();

    foreach ($todo as $item) {
        @chmod($item['path'], $item['mode']);

        clearstatcache(true, $item['path']);

        if (is_writable($item['path'])) {
            $fixed++;
        } else {
            $failed[$item['relative']] = substr(sprintf('%o', @fileperms($item['path'])), -4);
        }
    }

    // What the widget reads next has to be what the folders look like now.
    pg_write_permission_scan(true);

    $more = ($scan['directories_count'] + $scan['files_count']) - count($todo);

    if (count($failed) === 0) {
        $message = lang(array(
            'string' => '{var:1} folder(s) and file(s) are writable now.',
            'vars' => number_format($fixed),
        ));

        // The listing is capped; a tree with more than two hundred refusing
        // entries is repaired in rounds, and the report says a round is left.
        if ($more > 0) {
            $message .= ' ' . lang(array(
                'string' => '{var:1} more were not listed this time; press Fix again.',
                'vars' => number_format($more),
            ));
        }

        $status = 'success';
    } else {
        $message = lang(array(
            'string' => '{var:1} made writable, {var:2} could not be changed because they belong to another system user: {var:3}. Give them to the web server user, or set the permission from the hosting panel or over FTP.',
            'vars' => array(number_format($fixed), number_format(count($failed)), implode(', ', array_slice(array_keys($failed), 0, 8)) . ((count($failed) > 8) ? ', …' : '')),
        ));

        $status = ($fixed > 0) ? 'partial' : 'error';
    }

    return array(
        'status' => $status,
        'message' => $message,
        'fixed' => $fixed,
        'failed' => $failed,
    );
}

/**
 * Function: get_system_status_checks
 * ------------------------------------------
 * This function evaluates the overall health and security status of the system.
 * It performs multiple checks including:
 *   - File integrity validation
 *   - SSL/TLS connection status
 *   - Application-level security settings (password hint, CAPTCHA, strong password enforcement)
 *   - IndexNow key configuration
 *   - PHP version compatibility
 *   - Database table health (with repair attempts)
 *   - Required PHP extensions availability
 *   - Critical php.ini directives
 *
 * Each check contributes to a cumulative health score (0–100).
 *
 * It returns the checks as data, not as markup. The bar this used to print was
 * the only thing that could be done with a string of <span>s; the dashboard now
 * draws a gauge and a card per check from the same results, and settings.php
 * draws the same widget. Rendering lives in api.php, the checks live here.
 *
 * Returned shape:
 *   array(
 *     'score'  => int 0-100,
 *     'checks' => array of array(
 *         'group'   => 'security' | 'seo' | 'server',
 *         'icon'    => Bootstrap Icons class,
 *         'color'   => the text-* class the bar used,
 *         'state'   => 'ok' | 'warn' | 'fail' | 'info',
 *         'title'   => full check name, translated,
 *         'label'   => short name for a card, translated,
 *         'message' => explanation for the popover, translated,
 *         'value'   => short state text for the card, translated,
 *         'href'    => optional screen that acts on this check,
 *     ),
 *   )
 *
 * Translations are baked in before caching, which is safe because
 * SOFTWARE_LANGUAGE comes from the site config row and is the same for every
 * user of an installation.
 *
 * Compatibility: PHP 7.0 - 8.5
 * Returns: array
 */

function get_system_status_checks()
{
    // Cache result for 10 minutes to avoid running all checks on every call
    $cache_file = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';
    if (file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        // A cache written by the version that stored rendered HTML has no
        // 'checks' key, so it simply misses and the checks run once more. No
        // migration, and no risk of handing a string to a caller expecting an
        // array.
        if (is_array($cached) && isset($cached['ts']) && isset($cached['checks'])
            // Shape version. A cache written before a key was added to every
            // check is not wrong, it is incomplete, and a reader that tests
            // each key separately grows one test per release. Bumping this
            // costs one recompute on the first load after an update.
            && (isset($cached['v']) && ((int) $cached['v'] === 6))
            && ((time() - $cached['ts']) < 600)
            // The web server rules row is the one the operator acts on from
            // this screen, and the button that acts on it reads the file live.
            // A row saying "2 missing" beside a button that has nothing to do
            // reads as a repair that refuses to run, so a file that no longer
            // matches what the cache was written from costs a full recompute
            // instead of being served stale. One extra read of a file a few
            // kilobytes long, and only when it has actually changed.
            && (pg_server_rules_signature() === (isset($cached['server_rules']) ? $cached['server_rules'] : null))
        ) {
            return $cached;
        }
    }

    $output = '';
    $score = 100;

    // Filled by $makeIcon below. $group moves as the checks pass each divider
    // and the closure reads it by reference, so a check lands in whichever
    // group it is written under without having to name it.
    $checks = array();
    $group = 'security';

    // What each check is worth, RELATIVE TO THE OTHERS.
    //
    // These are shares, not points off. The score is worked out at the bottom
    // of this function as the fraction of the total weight that came through
    // clean, so the numbers only have to be right against each other and the
    // total can be anything.
    //
    // It used to be points off a hundred, and the sixteen checks added up to
    // 198. A site with five ordinary faults was at zero with eleven checks
    // still green, and every check added over the years made the cliff
    // steeper -- the later ones were not given a share of the hundred, they
    // were simply subtracted from it. Zero now means what it says: every
    // check on the list is failing.
    //
    // The ranking is the operator's:
    //
    //   Serious    the site is open, or the way in is
    //              -- tampered files, php.ini, passwords, the rules file,
    //                 a broken database, a firewall that is off
    //   Moderate   worth fixing this week
    //              -- PHP version, SSL, CAPTCHA, bot filtering, IndexNow,
    //                 CA bundle age
    //   Minor      housekeeping
    //              -- extensions, scheduled tasks, backup age, update status
    //
    // A missing backup is deliberately near the bottom. It is worth noticing
    // and it is not a hole in the site; scoring it like one taught the
    // operator to stop reading the number.
    $weights = array(
        // Serious
        'file_integrity'  => 20,
        'password'        => 18,
        'strong_password' => 18,
        'server_rules'    => 18,
        'directives'      => 18,
        'database'        => 16,
        'waf'             => 16,
        // Moderate
        'php_version'     => 12,
        'ssl'             => 12,
        // A folder the web server cannot write into keeps its old files
        // through every update. Not a hole today; the next update fails
        // there, or worse, half-lands.
        'permissions'     => 12,
        'captcha'         => 10,
        'bot_filter'      => 10,
        'indexnow'        => 8,
        // A stale trust list breaks outbound verification quietly --
        // update downloads, payment and API calls -- rather than opening
        // the site, so it sits at the bottom of this class beside
        // IndexNow, not among the housekeeping below.
        'ca_bundle'       => 8,
        // Minor
        'extensions'      => 6,
        'cron'            => 6,
        'webhooks'        => 6,
        'backup'          => 5,
        'update'          => 5,
    );

    // The same weights by check title, so that a check which never runs -- no
    // CAPTCHA constant, no cron table, nginx in front -- leaves the total
    // instead of being scored as a pass. $makeIcon adds a title's weight the
    // first time it records it, which is also what keeps this map honest: a
    // check whose title is not here is reported and never scored, and
    // Database Size is the only one of those on purpose.
    $check_weights = array(
        'Directory File Integrity' => $weights['file_integrity'],
        'SSL Status'               => $weights['ssl'],
        'CA Certificate Bundle'    => $weights['ca_bundle'],
        'Password Hint'            => $weights['password'],
        'CAPTCHA Protection'       => $weights['captcha'],
        'Strong Password'          => $weights['strong_password'],
        'Web Application Firewall' => $weights['waf'],
        'Unknown Bot Filtering'    => $weights['bot_filter'],
        'IndexNow'                 => $weights['indexnow'],
        'PHP Version'              => $weights['php_version'],
        'Database Health'          => $weights['database'],
        'PHP Extensions'           => $weights['extensions'],
        'PHP Directives'           => $weights['directives'],
        'Web Server Rules'         => $weights['server_rules'],
        'Write Permissions'        => $weights['permissions'],
        'Last Backup'              => $weights['backup'],
        'Software Update'          => $weights['update'],
        'Scheduled Tasks'          => $weights['cron'],
        'Webhooks'                 => $weights['webhooks'],
    );

    // Colour and cost have to agree. Red takes the whole weight, yellow takes
    // half of it, grey takes nothing; a finer fraction (0.3) is for the cases
    // where the answer really is "barely worth mentioning" -- a certificate
    // that exists while the site is served over http, a PHP version that is
    // old but still supported, a rules file missing only its recommended
    // blocks. Three checks used to print a warning and charge the full weight,
    // which is a yellow chip costing as much as a red one, and the fastest way
    // to teach an operator that the number is not worth reading.
    //
    // The weight of the checks that actually ran, filled in by $makeIcon.
    $possible = 0;
    $weighed  = array();

    // Icon rule for this bar — keep it when adding a check.
    //
    // The glyph identifies WHICH check it is, the colour identifies its STATE.
    // One glyph family per check, never shared: the bar is a row of ~12 icons at
    // 1.25rem, and two checks drawn from the same family (a shield for CAPTCHA and
    // a shield for the firewall, a padlock for SSL and a padlock for the password
    // hint) are indistinguishable at that size — the operator has to hover every
    // one of them to find out what they are looking at.
    //
    //   file      → file integrity        person   → CAPTCHA
    //   padlock   → SSL / TLS             key      → strong password
    //   eye       → password hint         shield   → web application firewall
    //   robot     → unknown bot filter    broadcast→ IndexNow
    //   terminal  → PHP version           database → database health
    //   puzzle    → PHP extensions        sliders  → php.ini directives
    //   patch     → CA certificate bundle
    //
    // Where the family offers a natural negative form (unlock, shield-slash,
    // database-x) use it for the failing state: it reinforces the colour instead
    // of competing with it. Otherwise the same glyph in a different colour is fine.
    //
    // The icons are printed in three groups — security, SEO, server — divided by
    // this rule. Same markup as the divider in front of the score.

    // Short names for the cards. The popover carries the full title and the
    // explanation; a card in a quarter-width dashboard column carries about ten
    // characters. Keyed by title deliberately, and kept here rather than in
    // api.php, so renaming a check and forgetting its short name is one edit
    // away from being noticed instead of a screen away.
    $short_labels = array(
        'Directory File Integrity' => 'Files',
        'SSL Status'               => 'SSL',
        'CA Certificate Bundle'    => 'CA bundle',
        'Password Hint'            => 'Password hint',
        'Strong Password'          => 'Strong password',
        'CAPTCHA Protection'       => 'CAPTCHA',
        'Web Application Firewall' => 'WAF',
        'Unknown Bot Filtering'    => 'Bots',
        'IndexNow'                 => 'IndexNow',
        'PHP Version'              => 'PHP',
        'Database Health'          => 'Database',
        'PHP Extensions'           => 'Extensions',
        'PHP Directives'           => 'php.ini',
        // The file's own name would be the clearest label, but it is not the
        // same name on every server and the tile is built before the answer is
        // known. The value line carries it instead.
        'Web Server Rules'         => 'Server rules',
        'Write Permissions'        => 'Permissions',
        // 'Backup' and 'Update' are already translated elsewhere as the verbs
        // on buttons -- "Yedekle", "Güncelle". A tile wants the noun, so these
        // point at keys of their own rather than borrowing a label that reads
        // as an instruction.
        // "Son yedek" is one character too wide once the tile gives up room
        // for its arrow, and the value beside it is already a time.
        'Last Backup'              => 'Backup age',
        'Software Update'          => 'Update status',
        'Scheduled Tasks'          => 'Tasks',
        'Webhooks'                 => 'Webhook',
    );

    // The three checks that answer "can someone get in": files that have been
    // tampered with, a connection in the clear, a firewall filtering nothing.
    // They lead the grid instead of sitting in run order among the rest, so the
    // first thing read is the thing that matters first. Titles again, for the
    // same reason as $short_labels -- renaming a check and forgetting this list
    // is one edit away from being noticed.
    $priority_titles = array(
        'Directory File Integrity',
        'SSL Status',
        'Web Application Firewall',
    );

    // The checks that are a job rather than a reading. Each of these has a
    // screen to open or a button to press, so the widget draws them as lines
    // in its actions column instead of as tiles among the readings. Titles
    // again, for the same reason as $short_labels and $priority_titles --
    // renaming a check and forgetting this list is one edit away from being
    // noticed rather than a screen away.
    $job_titles = array(
        'Web Server Rules',
        'Write Permissions',
        'Last Backup',
        'Software Update',
        'Scheduled Tasks',
        'Webhooks',
    );

    // Records one check. Named $makeIcon still, and still called as
    // `$output .= $makeIcon(...)`, because every one of the thirty-odd call
    // sites below keeps its exact condition, weight, glyph and wording that
    // way -- the change is where the result goes, not what is checked.
    //
    // 'value' defaults to a word derived from the colour. Checks with something
    // more specific to say -- a backup age, a version -- pass their own.
    $makeIcon = function ($icon, $color, $title, $content, $value = '', $href = '', $detail = array()) use (&$checks, &$group, &$possible, &$weighed, $short_labels, $priority_titles, $job_titles, $check_weights) {

        // Counted once per title, because the branches of a check are
        // exclusive and a title that reached this point is a check that ran.
        if (isset($check_weights[$title]) && !isset($weighed[$title])) {
            $weighed[$title] = true;
            $possible += $check_weights[$title];
        }

        $state = 'info';
        if (strpos($color, 'success') !== false) {
            $state = 'ok';
        } elseif (strpos($color, 'warning') !== false) {
            $state = 'warn';
        } elseif (strpos($color, 'danger') !== false) {
            $state = 'fail';
        }

        // Whether the check said anything of its own, or whether the word
        // below was put in its mouth. The two read identically once they are
        // in the array and they are not the same thing: "3 eksik" is the
        // check talking, "Sorun" is the colour spelled out. A chip that is
        // already red does not need to be told it has a problem, so the
        // renderer drops the generic ones and keeps the rest.
        $generic = ($value === '');

        if ($generic) {
            $defaults = array(
                'ok'   => 'OK',
                'warn' => 'Warning',
                'fail' => 'Problem',
                'info' => 'Not applicable',
            );
            $value = lang($defaults[$state]);
        }

        $checks[] = array(
            'group'   => $group,
            // The untranslated title, as a stable identifier. 'title' is
            // translated before it is cached, so a renderer that wants to
            // treat one particular check differently cannot match on it
            // without matching on Turkish.
            'key'     => $title,
            'icon'    => $icon,
            'color'   => $color,
            'state'   => $state,
            'title'   => lang($title),
            'label'   => lang(isset($short_labels[$title]) ? $short_labels[$title] : $title),
            'message' => lang($content),
            'value'   => $value,
            'generic' => $generic,
            'href'    => $href,
            'priority' => in_array($title, $priority_titles),
            // Reading or job: which column of the System Status widget this
            // belongs in. See $job_titles above.
            'job' => in_array($title, $job_titles),
            // Rows the tile can expand to. Filled by the checks whose count is
            // useless on its own: per-job run times exist nowhere else in the
            // panel, and "3 active" event subscriptions is a number the
            // operator has to be able to turn into three addresses.
            'detail'  => $detail,
        );

        return '';
    };

    // 🔍 File integrity check
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $output .= $makeIcon(
            'bi-file-earmark-minus-fill',
            'text-secondary',
            'Directory File Integrity',
            'This feature cannot be used in development mode. For security, disable development mode if it is not required.'
        );
        $score -= $weights['file_integrity'] * 0.5;
    } else {
        $file_integrity = function_exists('check_directory_file_integrity') ? check_directory_file_integrity() : 'unable_to_resolve_directory';
        switch ($file_integrity) {
            case 'unable_to_resolve_directory':
                $output .= $makeIcon(
                    'bi-file-earmark-minus-fill',
                    'text-warning',
                    'Directory File Integrity',
                    'Unable to resolve directory for file integrity check.'
                );
                $score -= $weights['file_integrity'] * 0.5;
                break;
            case 'unable_to_fetch_reference':
            case 'missing_or_tampered_files':
                $output .= $makeIcon(
                    'bi-file-earmark-x-fill',
                    'text-danger',
                    'Directory File Integrity',
                    'Warning: Missing or tampered files detected!'
                );
                $score -= $weights['file_integrity'];
                break;
            case 'success':
                $output .= $makeIcon(
                    'bi-file-earmark-check-fill',
                    'text-success',
                    'Directory File Integrity',
                    'All files are intact.'
                );
                break;
        }
    }

    // 🔒 SSL status check
    $ssl_status = function_exists('check_ssl_status') ? check_ssl_status() : 'unknown';
    $url_scheme = defined('URL_SCHEME') ? URL_SCHEME : '';
    if ($ssl_status === 'success' && $url_scheme !== 'https://') {
        $output .= $makeIcon(
            'bi-lock-fill',
            'text-warning',
            'SSL Status',
            'SSL is active but URL scheme is not HTTPS. Please enable Security Mode from Site Settings.'
        );
        $score -= $weights['ssl'] * 0.3;
    } elseif ($ssl_status === 'success') {
        $output .= $makeIcon(
            'bi-lock-fill',
            'text-success',
            'SSL Status',
            'Secure connection: SSL is active.'
        );
    } else {
        $output .= $makeIcon(
            'bi-unlock-fill',
            'text-danger',
            'SSL Status',
            'SSL is not active. Your connection may not be secure.'
        );
        $score -= $weights['ssl'];
    }

    // 🩹 CA certificate bundle age -- a patch, because the file is a trust list
    // kept current by replacing it whole, and the padlock is SSL's.
    //
    // Only measured when the configuration points outbound TLS at its own
    // cacert.pem. With CURL_CA_BUNDLE empty the system store is in use and the
    // package manager keeps that current; there is nothing here to read, so no
    // row at all rather than a grey "not applicable". The bundle carries its
    // own date in the header curl's mk-ca-bundle writes:
    //   ## Certificate data from Mozilla as of: Tue Jan 10 04:12:06 2023 GMT
    // A file without that line, or with one that does not parse, is skipped
    // the same way: a guess about its age is worse than no row. Only the first
    // lines are read; the file is a few hundred kilobytes and the header is at
    // the top.
    if (defined('CURL_CA_BUNDLE') && (CURL_CA_BUNDLE !== '') && is_file(CURL_CA_BUNDLE) && is_readable(CURL_CA_BUNDLE)) {
        $ca_bundle_stamp = 0;
        $ca_bundle_handle = @fopen(CURL_CA_BUNDLE, 'r');
        if ($ca_bundle_handle) {
            for ($ca_bundle_line = 0; $ca_bundle_line < 10; $ca_bundle_line++) {
                $ca_bundle_text = fgets($ca_bundle_handle);
                if ($ca_bundle_text === false) {
                    break;
                }
                if (preg_match('/^##\s*Certificate data from Mozilla as of:\s*(.+?)\s*$/', $ca_bundle_text, $ca_bundle_match)) {
                    $ca_bundle_stamp = (int) strtotime($ca_bundle_match[1]);
                    break;
                }
            }
            fclose($ca_bundle_handle);
        }

        // A date in the future is a clock or a parse gone wrong, not a fresh
        // bundle; it is skipped with the unreadable ones.
        if (($ca_bundle_stamp > 0) && ($ca_bundle_stamp <= time())) {
            // Whole months from the header date to today, the unit the
            // thresholds below are written in.
            $ca_bundle_diff = date_diff(date_create('@' . $ca_bundle_stamp), date_create('@' . time()));
            $ca_bundle_months = ($ca_bundle_diff->y * 12) + $ca_bundle_diff->m;
            $ca_bundle_date = defined('DATE_FORMAT')
                ? date(get_date_format_code() . '/Y', $ca_bundle_stamp)
                : date('Y-m-d', $ca_bundle_stamp);

            // Mozilla refreshes the list several times a year. A bundle that
            // has missed a year of them is behind; one that has missed two no
            // longer describes the roots current certificates chain to, and
            // outbound verification starts failing for no visible reason.
            if ($ca_bundle_months > 24) {
                $ca_bundle_icon = 'bi-patch-exclamation-fill';
                $ca_bundle_color = 'text-danger';
                $ca_bundle_message = array(
                    'string' => 'The bundled CA certificate file is more than two years old: Mozilla data as of {var:1}, {var:2} months old. Outbound TLS verification may fail against current certificates; update cacert.pem.',
                    'vars'   => array($ca_bundle_date, $ca_bundle_months),
                );
                $score -= $weights['ca_bundle'];
            } elseif ($ca_bundle_months > 12) {
                $ca_bundle_icon = 'bi-patch-exclamation-fill';
                $ca_bundle_color = 'text-warning';
                $ca_bundle_message = array(
                    'string' => 'The bundled CA certificate file is more than a year old: Mozilla data as of {var:1}, {var:2} months old. Update cacert.pem.',
                    'vars'   => array($ca_bundle_date, $ca_bundle_months),
                );
                $score -= $weights['ca_bundle'] * 0.5;
            } else {
                $ca_bundle_icon = 'bi-patch-check-fill';
                $ca_bundle_color = 'text-success';
                $ca_bundle_message = array(
                    'string' => 'The bundled CA certificate file is current: Mozilla data as of {var:1}, {var:2} months old.',
                    'vars'   => array($ca_bundle_date, $ca_bundle_months),
                );
            }

            $output .= $makeIcon(
                $ca_bundle_icon,
                $ca_bundle_color,
                'CA Certificate Bundle',
                $ca_bundle_message,
                lang(array('string' => '{var:1} months', 'vars' => array($ca_bundle_months)))
            );
        }
    }

    // 👁 Password hint — an eye, because the risk is that the hint reveals something.
    //
    // Two things were wrong here. It shared the title 'Password Management'
    // with the strong-password check below, so a site failing both showed two
    // identical chips and neither said which was which. And it had no passing
    // branch: with the hint switched off -- the good state -- the check drew
    // nothing at all, so the panel was silent about it exactly when it had
    // good news, and the weight had nothing to attach to.
    if (defined('PASSWORD_HINT')) {
        if (PASSWORD_HINT) {
            $output .= $makeIcon(
                'bi-eye-fill',
                'text-danger',
                'Password Hint',
                'Password hint is enabled. This may reduce security.'
            );
            $score -= $weights['password'];
        } else {
            $output .= $makeIcon(
                'bi-eye-slash-fill',
                'text-success',
                'Password Hint',
                'Password hint is disabled.'
            );
        }
    }

    // 🧍 CAPTCHA — a person, because the question it answers is "is this a human".
    if (defined('CAPTCHA')) {
        if (CAPTCHA) {
            $output .= $makeIcon(
                'bi-person-check-fill',
                'text-success',
                'CAPTCHA Protection',
                'CAPTCHA protection is enabled.'
            );
        } else {
            $output .= $makeIcon(
                'bi-person-x-fill',
                'text-danger',
                'CAPTCHA Protection',
                'CAPTCHA protection is disabled.'
            );
            $score -= $weights['captcha'];
        }
    }

    // 🔑 Strong password — the key family belongs to passwords, filled when enforced.
    if (defined('STRONG_PASSWORD')) {
        if (STRONG_PASSWORD) {
            $output .= $makeIcon(
                'bi-key-fill',
                'text-success',
                'Strong Password',
                'Strong password enforcement is enabled.'
            );
        } else {
            $output .= $makeIcon(
                'bi-key',
                'text-danger',
                'Strong Password',
                'Strong password enforcement is disabled.'
            );
            $score -= $weights['strong_password'];
        }
    }

    // 🛡️ Web application firewall
    // waf_mode() is the single source of truth: it already folds in the Enable
    // Firewall checkbox, the schema check and the monitor/block setting.
    $waf_current_mode = function_exists('waf_mode') ? waf_mode() : 'off';
    if ($waf_current_mode === 'block') {
        $output .= $makeIcon(
            'bi-shield-fill-check',
            'text-success',
            'Web Application Firewall',
            'The firewall is enabled and blocking malicious requests.'
        );
    } elseif ($waf_current_mode === 'monitor') {
        // Monitor mode records what would have been blocked but stops nothing,
        // so it must not be scored as full protection.
        $output .= $makeIcon(
            'bi-shield-fill-exclamation',
            'text-warning',
            'Web Application Firewall',
            'The firewall is in monitor mode: it records attacks but does not block them.'
        );
        $score -= $weights['waf'] * 0.5;
    } else {
        $output .= $makeIcon(
            'bi-shield-slash-fill',
            'text-danger',
            'Web Application Firewall',
            'The firewall is disabled. No requests are being filtered.'
        );
        $score -= $weights['waf'];
    }

    // 🤖 Unknown bot filtering
    // The Enable Firewall checkbox is an absolute off switch (waf_run()), so this
    // option does nothing while the firewall is off. Reporting it as enabled in
    // that state would be a false all clear.
    if ($waf_current_mode === 'off') {
        $output .= $makeIcon(
            'bi-robot',
            'text-danger',
            'Unknown Bot Filtering',
            'Unknown bots are not being blocked, because the firewall is disabled.'
        );
        $score -= $weights['bot_filter'];
    } elseif (defined('BLOCK_UNKNOWN_BOTS') && BLOCK_UNKNOWN_BOTS) {
        $output .= $makeIcon(
            'bi-robot',
            'text-success',
            'Unknown Bot Filtering',
            'Unknown bots are being blocked.'
        );
    } else {
        $output .= $makeIcon(
            'bi-robot',
            'text-warning',
            'Unknown Bot Filtering',
            'Unknown bots are not being blocked.'
        );
        $score -= $weights['bot_filter'] * 0.5;
    }

    // Security checks end here. The groups still exist -- they are what lets
    // the cards be read as "is my site secure" then "is my server healthy"
    // rather than as one undifferentiated grid.
    $group = 'seo';

    // 📡 IndexNow — a broadcast, because the feature announces changes to search
    // engines. It used to borrow the key glyph, which collided with the password
    // check next to it.
    if (defined('INDEXNOW_KEY') && INDEXNOW_KEY !== '') {
        $key_file_path = FILE_DIRECTORY_PATH . '/' . INDEXNOW_KEY . '.txt';
        if (file_exists($key_file_path)) {
            $output .= $makeIcon(
                'bi-broadcast-pin',
                'text-success',
                'IndexNow',
                'IndexNow configuration is successful.'
            );
        } else {
            $output .= $makeIcon(
                'bi-broadcast',
                'text-warning',
                'IndexNow',
                'Warning: IndexNow key is set but the verification file is missing.'
            );
            $score -= $weights['indexnow'] * 0.5;
        }
    } else {
        $output .= $makeIcon(
            'bi-broadcast',
            'text-warning',
            'IndexNow',
            'Warning: IndexNow has not been configured yet.'
        );
        $score -= $weights['indexnow'] * 0.5;
    }

    $group = 'server';

    // 🐘 PHP version
    $php_version = PHP_VERSION;
    if (version_compare($php_version, '7.0.0', '<')) {
        $output .= $makeIcon(
            'bi-terminal-x',
            'text-danger',
            'PHP Version',
            'Your PHP version is outdated and insecure. Upgrade to PHP 8.3 or higher immediately.'
        );
        $score -= $weights['php_version'];
    } elseif (version_compare($php_version, '8.3.0', '<')) {
        $output .= $makeIcon(
            'bi-terminal-dash',
            'text-warning',
            'PHP Version',
            'Your PHP version is supported but may lack full compatibility. Please upgrade to PHP 8.3 or higher.'
        );
        $score -= $weights['php_version'] * 0.3;
    } else {
        $output .= $makeIcon(
            'bi-terminal-fill',
            'text-success',
            'PHP Version',
            'Your PHP version is fully compatible.'
        );
    }

    // 🗄️ Database tables check
    $db_report = check_and_repair_database_tables();
    $issues = 0;
    $repairs = 0;
    foreach ($db_report as $table => $messages) {
        foreach ($messages as $msg) {
            if ($msg === "error")
                $issues++;
            if ($msg === "repaired")
                $repairs++;
        }
    }
    if ($issues > 0) {
        $output .= $makeIcon(
            'bi-database-x',
            'text-danger',
            'Database Health',
            lang(array('string' => 'Database issues detected in {var:1} table(s).', 'vars' => array($issues)))
        );
        $score -= $weights['database'];
    } elseif ($repairs > 0) {
        $output .= $makeIcon(
            'bi-database-exclamation',
            'text-warning',
            'Database Health',
            lang(array('string' => '{var:1} table(s) had issues but were repaired.', 'vars' => array($repairs)))
        );
        $score -= $weights['database'] * 0.5;
    } else {
        $output .= $makeIcon(
            'bi-database-check',
            'text-success',
            'Database Health',
            'All database tables are healthy.'
        );
    }

    // 📦 PHP extensions check with alias support for IIS and Apache
    // Some extensions report a different name depending on the build
    // (e.g. 'Zend OPcache' vs 'opcache'), hence the alias lists.
    //
    // Only extensions the software actually calls are listed here, in either
    // tier. 'memcache' used to sit in the scoring list although Pinegrap never
    // calls it anywhere — practically nobody has it installed, so it deducted the
    // full extension weight on every install forever, with nothing the operator
    // could do about it. A penalty that can never be cleared teaches the operator
    // to ignore the score.
    //
    // Moving it to the optional tier only made the trap quieter. The line still
    // read as something missing, and an operator chasing it spent an afternoon
    // building a Windows extension the software would never have called. An
    // unused extension has no place on a status screen in any colour, so
    // memcache and memcached are not checked at all any more.
    $required_extensions = array(
        'mysqli'   => array('mysqli'),                    // every database query
        'curl'     => array('curl'),                      // licence, updates, gateways, IndexNow
        'openssl'  => array('openssl'),                   // encrypt_string_with_iv(), API secret keys
        'mbstring' => array('mbstring'),                  // lang() case conversion, Turkish İ/ı
        'zip'      => array('zip'),                       // update packages, backups, import / export
        'fileinfo' => array('fileinfo'),                  // upload MIME detection
        'opcache'  => array('opcache', 'Zend OPcache')    // bytecode cache
    );

    // Reported but never deducted: the software works without it. imagick falls
    // back to GD in optimize_image() and edit_file.php, so a missing imagick
    // costs image quality and format support, not function.
    $optional_extensions = array(
        'imagick' => array('imagick')
    );

    $extension_is_loaded = function ($aliases) {
        foreach ($aliases as $ext_name) {
            if (extension_loaded($ext_name)) {
                return true;
            }
        }
        return false;
    };

    $missing_ext = array();
    foreach ($required_extensions as $label => $aliases) {
        if (!$extension_is_loaded($aliases)) {
            $missing_ext[] = $label;
        }
    }

    $missing_optional_ext = array();
    foreach ($optional_extensions as $label => $aliases) {
        if (!$extension_is_loaded($aliases)) {
            $missing_optional_ext[] = $label;
        }
    }

    // The prefix is translated on its own. Composing the sentence first and handing
    // the whole thing to lang() never matched a key, so the Turkish translation
    // already sitting in tr.json was never actually used.
    if (!empty($missing_ext)) {
        $output .= $makeIcon(
            'bi-puzzle-fill',
            'text-danger',
            'PHP Extensions',
            rtrim(lang('Missing extension(s): ')) . ' ' . implode(', ', $missing_ext)
        );
        $score -= $weights['extensions'];
    } elseif (!empty($missing_optional_ext)) {
        // Grey, not yellow: nothing is broken here. imagick is genuinely optional
        // and GD covers the same work, so a warning colour would sit on this icon
        // for as long as the operator chooses not to install it — the same trap
        // as the old memcache penalty, in a softer shade.
        $output .= $makeIcon(
            'bi-puzzle-fill',
            'text-secondary',
            'PHP Extensions',
            rtrim(lang('All required PHP extensions are loaded.')) . ' '
                . rtrim(lang('Optional extension(s) not installed:')) . ' '
                . implode(', ', $missing_optional_ext)
        );
    } else {
        $output .= $makeIcon(
            'bi-puzzle-fill',
            'text-success',
            'PHP Extensions',
            'All required PHP extensions are loaded.'
        );
    }


    // ⚙️ php.ini directives check
    $directives = array(
        'allow_url_fopen' => ini_get('allow_url_fopen'),
        'short_open_tag' => ini_get('short_open_tag')
    );
    $disabled = array();
    foreach ($directives as $dir => $val) {
        if (!$val || strtolower($val) === 'off' || $val == 0) {
            $disabled[] = $dir;
        }
    }
    if (empty($disabled)) {
        $output .= $makeIcon(
            'bi-sliders',
            'text-success',
            'PHP Directives',
            'All critical php.ini directives are enabled.'
        );
    } else {
        $output .= $makeIcon(
            'bi-sliders',
            'text-danger',
            'PHP Directives',
            'Disabled directive(s): ' . implode(', ', $disabled)
        );
        $score -= $weights['directives'];
    }

    // 🧾 Web server rules — the web.config / .htaccess in the web root
    //
    // That file belongs to the site, not to the software: it is outside the
    // software folder and no update package contains it. A rule added to the
    // installer therefore reaches new installs only, and every site created
    // before it keeps the file it was given on its first day. This check is
    // what closes that gap — it reads the file the site actually has and names
    // the blocks that are not in it; the tile beside it writes them.
    //
    // Scored like the firewall, because two of the blocks are the firewall's
    // equivalent for static files: without them data/ (config.php with the
    // database password, the backups, every upload) is downloadable.
    $server_rules = pg_server_config_scan();

    $server_rules_detail = array();
    $server_rules_required = 0;
    $server_rules_recommended = 0;
    $server_rules_core = 0;

    foreach ($server_rules['missing'] as $server_rules_block) {
        $server_rules_detail[] = array(
            'label' => lang($server_rules_block['label']),
            'state' => ($server_rules_block['level'] == 'recommended') ? 'info' : 'fail',
            'when'  => lang('Missing'),
        );
        if ($server_rules_block['level'] == 'required') {
            $server_rules_required++;
        } elseif ($server_rules_block['level'] == 'core') {
            $server_rules_core++;
        } else {
            $server_rules_recommended++;
        }
    }

    // A rule that names a folder the software is not in. The router rule makes
    // that obvious -- the site 404s -- but the two deny rules fail silently and
    // this is the only place that says so.
    if ($server_rules['stale_path']) {
        $server_rules_detail[] = array(
            'label' => lang(array(
                'string' => 'Rules name another folder, not {var:1}',
                'vars'   => pg_server_config_software_dir(),
            )),
            'state' => 'fail',
            'when'  => lang('Wrong folder'),
        );
    }

    if ($server_rules['server'] == 'nginx') {
        // nginx reads nothing from the web root, so there is no file to be
        // missing anything. Saying "problem" here would be asking the operator
        // to fix something this software cannot see or reach.
        $output .= $makeIcon(
            'bi-file-earmark-code',
            'text-secondary',
            'Web Server Rules',
            'This server is nginx, which reads no configuration file from the web root. The rules have to be pasted into the server block by hand; the tile writes a sample to copy.',
            lang('nginx'),
            '',
            array()
        );

    } elseif (!$server_rules['exists']) {
        $output .= $makeIcon(
            'bi-file-earmark-x',
            'text-danger',
            'Web Server Rules',
            'The web server configuration file is missing from the web root, so no address other than a real file can be reached and nothing is blocked.',
            lang('Missing'),
            '',
            $server_rules_detail
        );
        $score -= $weights['server_rules'];

    } elseif (!$server_rules['valid']) {
        // A web.config IIS cannot parse answers 500.19 to every request. The
        // repair refuses to touch it, and so does this: the number says so.
        $output .= $makeIcon(
            'bi-file-earmark-x',
            'text-danger',
            'Web Server Rules',
            'The web server configuration file is not valid XML. It has to be repaired by hand — nothing here will write to a file it cannot read.',
            lang('Invalid'),
            '',
            array()
        );
        $score -= $weights['server_rules'];

    } elseif ($server_rules_required > 0 || $server_rules_core > 0 || $server_rules['stale_path']) {
        $output .= $makeIcon(
            'bi-file-earmark-excel',
            'text-danger',
            'Web Server Rules',
            'The web server configuration file is missing rules that keep private folders from being downloaded. Open the panel to see which, and use the tile below to add them.',
            lang(array(
                'string' => '{var:1} missing',
                'vars'   => number_format(count($server_rules_detail)),
            )),
            '',
            $server_rules_detail
        );
        $score -= $weights['server_rules'];

    } elseif ($server_rules_recommended > 0) {
        // Nothing is exposed; a header is absent. Worth a nudge, not a red tile.
        $output .= $makeIcon(
            'bi-file-earmark-text',
            'text-warning',
            'Web Server Rules',
            'The private folders are protected. Some recommended rules are not in the file yet — open the panel to see which.',
            lang(array(
                'string' => '{var:1} missing',
                'vars'   => number_format(count($server_rules_detail)),
            )),
            '',
            $server_rules_detail
        );
        $score -= $weights['server_rules'] * 0.3;

    } else {
        $output .= $makeIcon(
            'bi-file-earmark-check',
            'text-success',
            'Web Server Rules',
            'The web server configuration file carries every rule this version expects.',
            $server_rules['name'],
            '',
            array()
        );
    }

    // 🔏 Write permissions
    //
    // Can an update land here? The panel's updater writes as the web server;
    // a package extracted by hand writes as the hosting account. A folder
    // that refuses keeps its old files through every extraction, and the
    // software then runs new code against the old schema - which is the
    // stale-migration incident in one sentence (see pg_write_permission_scan()).
    // Folders are what count: a file that cannot be replaced is deleted and
    // rewritten by the updater as long as its folder allows it.
    $permissions = pg_write_permission_scan();

    $permissions_detail = array();

    foreach ($permissions['directories'] as $permissions_path => $permissions_mode) {
        if (count($permissions_detail) >= 12) {
            break;
        }
        $permissions_detail[] = array(
            'label' => $permissions_path . '/',
            'state' => 'fail',
            'when'  => $permissions_mode,
        );
    }

    foreach ($permissions['files'] as $permissions_path => $permissions_mode) {
        if (count($permissions_detail) >= 12) {
            break;
        }
        $permissions_detail[] = array(
            'label' => $permissions_path,
            'state' => 'info',
            'when'  => $permissions_mode,
        );
    }

    if ($permissions['directories_count'] > 0) {
        $output .= $makeIcon(
            'bi-folder-x',
            'text-danger',
            'Write Permissions',
            'The web server cannot write into some folders of the software, so an update cannot add or replace files there and the site keeps old code next to new. Open the panel to see which; Fix sets them writable (0777 folders, 0666 files).',
            lang(array(
                'string' => '{var:1} folder(s), {var:2} file(s)',
                'vars'   => array(number_format($permissions['directories_count']), number_format($permissions['files_count'])),
            )),
            '',
            $permissions_detail
        );
        $score -= $weights['permissions'];

    } elseif ($permissions['files_count'] > 0) {
        // Every folder takes new files, so the updater can delete and rewrite
        // these itself. Worth knowing, not worth red.
        $output .= $makeIcon(
            'bi-folder-symlink',
            'text-warning',
            'Write Permissions',
            'Every folder is writable, but some files are not. The updater replaces such a file by deleting it first, so an update still lands; Fix makes them writable outright.',
            lang(array(
                'string' => '{var:1} file(s)',
                'vars'   => number_format($permissions['files_count']),
            )),
            '',
            $permissions_detail
        );
        $score -= $weights['permissions'] * 0.3;

    } else {
        $output .= $makeIcon(
            'bi-folder-check',
            'text-success',
            'Write Permissions',
            'The web server can write to every folder and file of the software, so an update can replace all of it.',
            lang(array(
                'string' => '{var:1} folders',
                'vars'   => number_format($permissions['checked_directories']),
            )),
            '',
            array()
        );
    }

    $group = 'maintenance';

    // 💾 Last backup
    //
    // Read from the directory, NOT from config.last_software_auto_backup. That
    // column is the scheduler's cursor, not a record of what happened: opening
    // backups.php sets it to 0 to force the next run, so a check trusting it
    // would announce "never backed up" to anyone who had just visited the
    // backup screen. The folders are the only thing that knows.
    $backup_path = PG_FUNCTIONS_DIR . '/data/backups';
    $backup_count = 0;
    $backup_newest = 0;

    // Shipped with the product as install fixtures, not produced by any backup
    // run. Counting them would let a site that has never been backed up show
    // two backups dated the day it was installed.
    $backup_seed = array('english_default', 'turkish_default');

    if (is_dir($backup_path)) {
        $backup_entries = @scandir($backup_path);
        if ($backup_entries !== false) {
            foreach ($backup_entries as $backup_entry) {
                if (($backup_entry == '.') || ($backup_entry == '..')) {
                    continue;
                }
                if (in_array($backup_entry, $backup_seed)) {
                    continue;
                }
                if (!is_dir($backup_path . '/' . $backup_entry)) {
                    continue;
                }
                $backup_count++;
                // stat per folder, never a recursive walk. folderSize() on this
                // directory would read every file of every backup — gigabytes —
                // for one number on a card.
                $backup_stamp = @filemtime($backup_path . '/' . $backup_entry);
                if ($backup_stamp && ($backup_stamp > $backup_newest)) {
                    $backup_newest = (int) $backup_stamp;
                }
            }
        }
    }

    $auto_backup_off = (defined('SOFTWARE_AUTO_BACKUP') && (SOFTWARE_AUTO_BACKUP == false));

    if ($backup_newest < 1) {
        $backup_icon = 'bi-hdd-network-fill';
        $backup_color = 'text-danger';
        $backup_value = lang('Never');
        $backup_message = 'No backup has ever been taken of this site.';
        $score -= $weights['backup'];
    } else {
        $backup_age = time() - $backup_newest;
        $backup_value = get_relative_time(array('timestamp' => $backup_newest, 'format' => 'plain_text'));
        $backup_icon = 'bi-hdd-network-fill';

        // Two days covers a daily schedule that missed one run; a week means
        // the schedule itself has stopped.
        if ($backup_age > 604800) {
            $backup_color = 'text-danger';
            $backup_message = 'The most recent backup is more than a week old.';
            $score -= $weights['backup'];
        } elseif ($backup_age > 172800) {
            $backup_color = 'text-warning';
            $backup_message = 'The most recent backup is more than two days old.';
            $score -= $weights['backup'] * 0.5;
        } else {
            $backup_color = 'text-success';
            $backup_message = 'A recent backup exists.';
        }
    }

    // A switched-off schedule is not a stale backup. Scoring it as one would
    // train the operator who turned it off deliberately to ignore the number,
    // so it costs half and reads grey rather than red.
    if ($auto_backup_off && ($backup_color == 'text-danger')) {
        $score += $weights['backup'] * 0.5;
        $backup_color = 'text-secondary';
        $backup_message = 'Automatic backup is turned off.';
    }

    $output .= $makeIcon(
        $backup_icon,
        $backup_color,
        'Last Backup',
        $backup_message,
        $backup_value,
        'backups.php'
    );

    // ⬆️ Software update
    //
    // SOFTWARE_UPDATE_AVAILABLE is set in init.php from the periodic check; the
    // timestamp says how much that answer is worth. A check from two months ago
    // saying "up to date" is not information.
    $update_available = (defined('SOFTWARE_UPDATE_AVAILABLE') && SOFTWARE_UPDATE_AVAILABLE);
    $update_checked = defined('LAST_SOFTWARE_UPDATE_CHECK_TIMESTAMP')
        ? (int) LAST_SOFTWARE_UPDATE_CHECK_TIMESTAMP
        : 0;

    if ($update_available) {
        $output .= $makeIcon(
            'bi-arrow-up-circle-fill',
            'text-warning',
            'Software Update',
            'A software update is available.',
            lang('New version'),
            'software_update.php'
        );
        $score -= $weights['update'] * 0.5;
    } elseif ($update_checked < 1) {
        $output .= $makeIcon(
            'bi-arrow-up-circle',
            'text-warning',
            'Software Update',
            'The software has never been checked for updates, so it is not known whether one is waiting.',
            lang('Not checked'),
            'software_update.php'
        );
        $score -= $weights['update'] * 0.5;
    } else {
        $output .= $makeIcon(
            'bi-check-circle-fill',
            'text-success',
            'Software Update',
            'The software is up to date.',
            defined('VERSION') ? VERSION : lang('OK'),
            'software_update.php'
        );
    }

    // ⏱ Scheduled tasks
    //
    // The cron entries live outside the software, so nothing here can ask
    // whether a job is scheduled. What the panel has is the run each job
    // records when it finishes; the useful signal is a job that used to finish
    // and no longer does.
    //
    // Never run is therefore NOT a failure and costs nothing — it is a site
    // that does not use that job, or one whose cron was never set up. The
    // penalty is reserved for a job with a recorded run that has since gone
    // quiet, which is unambiguous: it worked, now it doesn't.
    $cron_runs = function_exists('pg_cron_last_runs') ? pg_cron_last_runs() : null;

    // null means the table is not there yet. Silence beats reporting every job
    // as broken on an installation that has simply not been upgraded.
    if ($cron_runs !== null) {

        $cron_jobs = pg_cron_jobs();
        $cron_ok = 0;
        $cron_never = 0;
        $cron_stalled = array();
        $cron_detail = array();

        foreach ($cron_jobs as $cron_key => $cron_job) {

            $cron_seen = isset($cron_runs[$cron_key]) ? (int) $cron_runs[$cron_key] : 0;

            if ($cron_seen < 1) {
                $cron_never++;
                $cron_state = 'info';
                $cron_when = lang('Never');
            } elseif ((time() - $cron_seen) > (int) $cron_job['stale_after']) {
                $cron_stalled[] = $cron_job['label'];
                $cron_state = 'fail';
                // plain_text: a <time> element would be baked into the cache
                // and carry a tooltip built from a timestamp up to ten minutes
                // out of date. The text is relative either way.
                $cron_when = get_relative_time(array('timestamp' => $cron_seen, 'format' => 'plain_text'));
            } else {
                $cron_ok++;
                $cron_state = 'ok';
                $cron_when = get_relative_time(array('timestamp' => $cron_seen, 'format' => 'plain_text'));
            }

            $cron_detail[] = array(
                'label' => $cron_job['label'],
                'state' => $cron_state,
                'when'  => $cron_when,
            );
        }

        if ($cron_stalled) {
            $output .= $makeIcon(
                'bi-clock-fill',
                'text-danger',
                'Scheduled Tasks',
                rtrim(lang('Scheduled task(s) that have stopped running:')) . ' ' . implode(', ', $cron_stalled),
                lang(array(
                    'string' => '{var:1} stopped',
                    'vars' => number_format(count($cron_stalled)),
                )),
                '',
                $cron_detail
            );
            $score -= $weights['cron'];
        } elseif ($cron_ok > 0) {
            $output .= $makeIcon(
                'bi-clock-fill',
                'text-success',
                'Scheduled Tasks',
                'Scheduled tasks are running on time.',
                lang(array(
                    'string' => '{var:1} running',
                    'vars' => number_format($cron_ok),
                )),
                '',
                $cron_detail
            );
        } else {
            $output .= $makeIcon(
                'bi-clock',
                'text-secondary',
                'Scheduled Tasks',
                'No scheduled task has run yet. Set up cron jobs if you want them.',
                // Shorter than 'Never' on purpose: this tile carries a chevron
                // as well, and "Hiçbir zaman" does not fit beside it. The
                // per-job panel underneath spells it out anyway.
                lang('None yet'),
                '',
                $cron_detail
            );
        }
    }

    // 📡 Webhook deliveries
    //
    // The signal worth having is not "is the dispatcher running" - the
    // scheduled-task check above already covers that - but "has a subscription
    // stopped being delivered to". A webhook that fails six times in a row is
    // marked failing and stops being tried, and from the outside that looks
    // exactly like a quiet week: the marketplace stops hearing about orders and
    // nothing on the site says so.
    //
    // A site with no subscriptions gets no tile at all. It is not a check that
    // passed, it is a feature that is not in use, and scoring it as a pass
    // would dilute the checks that did run.
    //
    // The table is asked for before it is read. Files land before the schema
    // does, and since PHP 8.1 a query on a table that is not there is an
    // exception that @ does not silence: this one line took the whole
    // dashboard down on a site whose 2026.4.4 step had not created the API
    // tables. The rule for anything that touches a table a migration adds is
    // to probe first (see the upgrade bridge near pg_code_version()).
    $webhook_list = false;

    if (defined('DB_CONNECTED') && DB_CONNECTED) {
        try {
            $webhook_table = @mysqli_query(db::$con, "SHOW TABLES LIKE 'api\\_webhooks'");

            if ($webhook_table && mysqli_num_rows($webhook_table) > 0) {

                // The rows themselves rather than a GROUP BY, because the tile
                // opens into a list and the question after "3 active" is which
                // three. The table holds one row per subscription and a busy
                // shop has a handful; twenty-five is a ceiling for the panel,
                // not a limit anyone is expected to meet.
                //
                // The application is joined in because it decides whether the
                // subscription is delivered to at all: the dispatcher skips one
                // whose application was deleted or switched off, and something
                // nothing is sent to must not be counted as active.
                $webhook_list = @mysqli_query(db::$con,
                    "SELECT api_webhooks.url, api_webhooks.status, api_apps.status AS app_status
                        FROM api_webhooks
                        LEFT JOIN api_apps ON api_apps.id = api_webhooks.app_id
                        ORDER BY api_webhooks.id ASC
                        LIMIT 25");
            }
        } catch (\Throwable $e) {
            $webhook_list = false;
        }
    }

    if ($webhook_list !== false) {

        $webhook_total = 0;
        $webhook_active = 0;
        $webhook_failing = 0;
        $webhook_detail = array();

        while ($webhook_row = mysqli_fetch_assoc($webhook_list)) {

            $webhook_total++;

            if ($webhook_row['status'] === 'failing') {

                $webhook_failing++;
                $webhook_state = 'fail';
                $webhook_when = lang('Failing');

            } elseif ($webhook_row['status'] === 'disabled') {

                $webhook_state = 'info';
                $webhook_when = lang('Stopped');

            } elseif ($webhook_row['app_status'] !== 'active') {

                $webhook_state = 'fail';
                $webhook_when = lang('No application');

            } else {

                $webhook_active++;
                $webhook_state = 'ok';
                $webhook_when = lang('Active');

            }

            $webhook_detail[] = array(
                'label' => $webhook_row['url'],
                'state' => $webhook_state,
                'when'  => $webhook_when,
            );
        }

        if ($webhook_total > 0) {

            // Deliveries that ran out of attempts. next_attempt_at is set to
            // zero when a row is given up on, which is what separates them from
            // the ones still waiting their turn.
            $webhook_stuck = 0;

            $webhook_queue = @mysqli_query(db::$con,
                "SELECT COUNT(*) AS total FROM api_webhook_queue WHERE next_attempt_at = 0");

            if ($webhook_queue !== false) {
                $webhook_queue_row = mysqli_fetch_assoc($webhook_queue);
                $webhook_stuck = is_array($webhook_queue_row) ? (int) $webhook_queue_row['total'] : 0;
            }

            // The tile opens the list rather than the top of the application
            // screen: the subscriptions are at the foot of it, and one of them
            // may belong to no application at all.
            $webhook_href = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api_settings.php#events';

            if ($webhook_failing > 0) {

                $output .= $makeIcon(
                    'bi-send-fill',
                    'text-danger',
                    'Webhooks',
                    'An event notification address stopped answering and is no longer being tried. The system it feeds is not hearing about orders.',
                    lang(array(
                        'string' => '{var:1} not delivering',
                        'vars' => number_format($webhook_failing),
                    )),
                    $webhook_href,
                    $webhook_detail
                );

                $score -= $weights['webhooks'];

            } elseif ($webhook_stuck > 0) {

                $output .= $makeIcon(
                    'bi-send-fill',
                    'text-warning',
                    'Webhooks',
                    'Some event notifications could not be delivered and were given up on. The address is answering again, so the ones that follow will arrive.',
                    lang(array(
                        'string' => '{var:1} undelivered',
                        'vars' => number_format($webhook_stuck),
                    )),
                    $webhook_href,
                    $webhook_detail
                );

                $score -= ($weights['webhooks'] / 2);

            } elseif ($webhook_active > 0) {

                $output .= $makeIcon(
                    'bi-send-fill',
                    'text-success',
                    'Webhooks',
                    'Event notifications are being delivered.',
                    lang(array(
                        'string' => '{var:1} active',
                        'vars' => number_format($webhook_active),
                    )),
                    $webhook_href,
                    $webhook_detail
                );

            } else {

                // Registered and not being sent to: every subscription is
                // switched off, or the application behind it is gone. Nothing
                // is wrong with the site, so nothing comes off the score - but
                // the green tile used to count these as active, and an
                // integrator reading "2 active" while their endpoint hears
                // nothing looks in the wrong place for the rest of the day.
                $output .= $makeIcon(
                    'bi-send',
                    'text-secondary',
                    'Webhooks',
                    'Every event subscription is switched off, or the application that registered it is gone. Nothing is being delivered.',
                    lang(array(
                        'string' => '{var:1} stopped',
                        'vars' => number_format($webhook_total),
                    )),
                    $webhook_href,
                    $webhook_detail
                );

            }

        }

    }

    // The fallback carrier, when the site names one.
    //
    // ECOMMERCE_DEFAULT_TRACKING_PROVIDER is what a shop sets when its shipping
    // method codes say nothing about the courier: every parcel without one is
    // then linked to that carrier. It is a config define with no settings
    // screen, its accepted values are the keys of pg_shipping_carriers(), and a
    // value outside that list does nothing at all - the customer simply sees a
    // number with no link, which looks like a shop that has not entered the
    // tracking number rather than a typo in a file.
    //
    // Reported and not scored, like Database Size: a site that names no
    // carrier gets no tile, because there is nothing to be right or wrong
    // about, and a site that names a real one is not healthier for it.
    if (defined('ECOMMERCE_DEFAULT_TRACKING_PROVIDER')
        && (trim((string) ECOMMERCE_DEFAULT_TRACKING_PROVIDER) !== '')
        && function_exists('pg_shipping_carriers')) {

        $default_carrier = trim((string) ECOMMERCE_DEFAULT_TRACKING_PROVIDER);

        $known_carriers = pg_shipping_carriers();

        if (isset($known_carriers[$default_carrier])) {

            $output .= $makeIcon(
                'bi-truck',
                'text-success',
                'Default Shipping Carrier',
                'Parcels whose shipping method does not name a courier are linked to this one.',
                $known_carriers[$default_carrier]['name']
            );

        } else {

            $output .= $makeIcon(
                'bi-truck',
                'text-warning',
                'Default Shipping Carrier',
                lang(array(
                    'string' => 'ECOMMERCE_DEFAULT_TRACKING_PROVIDER is set to {var:1}, which is not a carrier this software knows, so no parcel is linked through it. Accepted values: {var:2}.',
                    'vars'   => array($default_carrier, implode(', ', array_keys($known_carriers))),
                )),
                $default_carrier
            );

        }

    }

    // Database size was a check here. It is not one: it was reported and never
    // scored, so it sat among the status chips as the one entry that could not
    // be right or wrong -- and it was alone, while the two figures it belongs
    // with (the backup folder and the file library) were nowhere. All three now
    // travel together as storage, on the maintenance side of the card, out of a
    // list whose whole job is to say what passed and what did not.

    // From points off a hundred to a share of what ran.
    //
    // Everything above subtracted weights from 100, and the weights total
    // nearly 200, so five ordinary faults put a site at zero with eleven
    // checks still green. What those branches actually produced is a penalty
    // in weight units; dividing it by the weight of the checks that ran turns
    // it into the fraction of the installation that is in trouble.
    //
    // The denominator is what RAN, not the whole table: a site behind nginx,
    // or one with no CAPTCHA constant, must not be marked down for a check
    // nobody could have passed -- nor credited for passing it.
    //
    // Zero therefore means every scored check is failing. In practice it is
    // out of reach: the database check repairs what it finds and goes green on
    // the next pass by itself.
    $penalty = 100 - $score;

    if ($penalty < 0) {
        $penalty = 0;
    }

    $score = ($possible > 0)
        ? (100 - ((100 * $penalty) / $possible))
        : 100;

    // Clamp score
    if ($score < 0)
        $score = 0;
    if ($score > 100)
        $score = 100;

    $result = array(
        'ts' => time(),
        'v' => 6,
        'score' => (int) round($score),
        'checks' => $checks,
        // Cached on its own six-hour clock inside pg_storage_usage(); carried
        // here so the widget makes one call rather than two.
        'storage' => pg_storage_usage(),
        // What the web server rules row was built from. Compared on the way
        // back in; see the cache read at the top of this function.
        'server_rules' => pg_server_rules_signature(),
    );

    // Write cache
    $cache_dir = PG_FUNCTIONS_DIR . '/data/temp';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    file_put_contents($cache_file, json_encode($result));

    return $result;
}



/**
 * Function: pg_changelog_sections
 * ------------------------------------------
 * Reads changelog.txt and returns it as versions, newest first, each with its
 * entries: array('version' => '2026.4.4', 'entries' => array(array('tags' =>
 * array('YENİ','ŞEMA'), 'text' => '…'), …)).
 *
 * The install screen parses the same file for its "what is new" panel, but that
 * copy has to keep working with the software folder gone -- install/index.php
 * includes nothing from here on purpose -- so the two parsers stay separate.
 * This one is the better of the two: it keeps every tag of a double-tagged
 * entry ([YENİ] [ŞEMA]) instead of only the last, and it keeps an untagged
 * paragraph (NOT …) as an entry of its own rather than gluing it onto whatever
 * came before it.
 *
 * The banner and the tag legend at the top of the file are skipped: they sit
 * before the first version heading, and the badges say the same thing.
 *
 * @return array
 */
function pg_changelog_sections($file_path = '')
{
    if ($file_path == '') {
        $file_path = PG_FUNCTIONS_DIR . '/changelog.txt';
    }
    if (!is_file($file_path)) {
        return array();
    }
    $contents = @file_get_contents($file_path);
    if ($contents === false || $contents === '') {
        return array();
    }
    $contents = str_replace("\r\n", "\n", $contents);

    // A version heading is a version number alone on its line over a rule of
    // equal signs. Same shape install/index.php looks for, so a file that reads
    // correctly there reads correctly here.
    if (!preg_match_all('/^[ \t]*([0-9][0-9.]*)[ \t]*\n[ \t]*={5,}[ \t]*$/m',
            $contents, $matches, PREG_OFFSET_CAPTURE)) {
        return array();
    }

    $sections = array();

    foreach ($matches[0] as $index => $heading) {

        $section_start = $heading[1] + strlen($heading[0]);
        $section_end = isset($matches[0][$index + 1])
            ? $matches[0][$index + 1][1]
            : strlen($contents);

        $section_text = substr($contents, $section_start, $section_end - $section_start);
        $entries = array();
        $current = null;
        $group = '';

        // A version with many entries is written under topic headings: a line
        // of capitals at the margin over a rule of hyphens. They are read here
        // rather than skipped, so the screen can show the same grouping the
        // file has -- and so the heading never lands inside the text of the
        // entry above it, which is what happened while this parser knew only
        // about tags.
        $section_lines = explode("\n", $section_text);

        foreach ($section_lines as $line_index => $line) {

            $next_line = isset($section_lines[$line_index + 1]) ? $section_lines[$line_index + 1] : '';

            if ((preg_match('/^[ \t]{0,4}([A-ZÇĞİÖŞÜ0-9][A-ZÇĞİÖŞÜ0-9 ,:\/&()-]*[A-ZÇĞİÖŞÜ0-9)])[ \t]*$/u', $line, $found))
                && (preg_match('/^[ \t]{0,4}-{5,}[ \t]*$/', $next_line))) {
                if ($current !== null) {
                    $entries[] = $current;
                    $current = null;
                }
                $group = trim($found[1]);
                continue;
            }

            // the rule under a heading, and any other divider line
            if (preg_match('/^[ \t]{0,4}-{5,}[ \t]*$/', $line)) {
                continue;
            }

            // An entry opens at the left margin, either with its tags in
            // brackets or with a bare word such as NOT. Everything indented
            // deeper belongs to the entry above it.
            if (preg_match('/^[ \t]{0,4}((?:\[[^\]\n]+\][ \t]*)+)(.*)$/', $line, $found)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                preg_match_all('/\[([^\]\n]+)\]/', $found[1], $tag_matches);
                $current = array(
                    'tags' => array_map('trim', $tag_matches[1]),
                    'text' => trim($found[2]),
                    'group' => $group);
                continue;
            }

            if (preg_match('/^[ \t]{0,4}([A-ZÇĞİÖŞÜ]{2,10})[ \t]{2,}(.*)$/u', $line, $found)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = array(
                    'tags' => array(trim($found[1])),
                    'text' => trim($found[2]),
                    'group' => $group);
                continue;
            }

            if ($current !== null && trim($line) != '') {
                $current['text'] .= ' ' . trim($line);
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        $entries = array_values(array_filter($entries, function ($entry) {
            return trim($entry['text']) != '';
        }));

        if (count($entries) == 0) {
            continue;
        }

        $sections[] = array('version' => $matches[1][$index][0], 'entries' => $entries);
    }

    return $sections;
}

/**
 * Function: pg_changelog_preamble
 * ------------------------------------------
 * Everything above the first version heading of changelog.txt: the wordmark
 * drawn in ASCII, the title and the two sentences under it, and the legend
 * that says what each tag means.
 *
 * Returned split in two, because the two halves want different treatment. The
 * banner is a drawing -- it only survives in a monospace block that is not
 * allowed to wrap. The legend is data: five tags with a description each, and
 * drawing it with the same badges the entries carry turns it into a real key
 * for the list below instead of a paragraph describing one.
 *
 * @return array array('banner' => string, 'legend' => array(array('tag','text')))
 */
function pg_changelog_preamble($file_path = '')
{
    if ($file_path == '') {
        $file_path = PG_FUNCTIONS_DIR . '/changelog.txt';
    }
    if (!is_file($file_path)) {
        return array('banner' => '', 'legend' => array());
    }
    $contents = @file_get_contents($file_path);
    if ($contents === false || $contents === '') {
        return array('banner' => '', 'legend' => array());
    }
    $contents = str_replace("\r\n", "\n", $contents);

    // Stop at the first version heading; the sections belong to
    // pg_changelog_sections().
    if (preg_match('/^[ \t]*[0-9][0-9.]*[ \t]*\n[ \t]*={5,}[ \t]*$/m',
            $contents, $found, PREG_OFFSET_CAPTURE)) {
        $contents = substr($contents, 0, $found[0][1]);
    }

    $banner_lines = array();
    $legend = array();

    foreach (explode("\n", $contents) as $line) {

        // A legend row is a tag and its description on one line, nothing else.
        if (preg_match('/^[ \t]*\[([^\]\n]+)\][ \t]+(\S.*?)[ \t]*$/', $line, $found)) {
            $legend[] = array('tag' => trim($found[1]), 'text' => trim($found[2]));
            continue;
        }

        $banner_lines[] = rtrim($line);
    }

    // Leading and trailing blank lines are file spacing, not part of the
    // drawing, and inside a <pre> they read as a gap nobody asked for.
    while (count($banner_lines) > 0 && trim($banner_lines[0]) == '') {
        array_shift($banner_lines);
    }
    while (count($banner_lines) > 0 && trim($banner_lines[count($banner_lines) - 1]) == '') {
        array_pop($banner_lines);
    }

    return array('banner' => implode("\n", $banner_lines), 'legend' => $legend);
}

/**
 * Function: pg_changelog_tag_class
 * ------------------------------------------
 * The badge colour for a changelog tag. Matches install/index.php so the same
 * entry is the same colour on both screens; both languages of every tag are
 * listed because the file is written in whichever one the site uses.
 *
 * @param string $tag
 * @return string
 */
function pg_changelog_tag_class($tag)
{
    $tag = mb_strtoupper($tag, 'UTF-8');

    if ($tag == 'GÜVENLIK' || $tag == 'GÜVENLİK' || $tag == 'SECURITY') {
        return 'text-bg-danger';
    }
    if ($tag == 'ŞEMA' || $tag == 'SEMA' || $tag == 'SCHEMA') {
        return 'text-bg-warning';
    }
    if ($tag == 'DÜZELTME' || $tag == 'FIX') {
        return 'text-bg-primary';
    }
    if ($tag == 'HIZ' || $tag == 'SPEED' || $tag == 'PERFORMANCE') {
        return 'text-bg-info';
    }
    if ($tag == 'NOT' || $tag == 'NOTE') {
        return 'text-bg-secondary';
    }

    return 'text-bg-success';
}

/**
 * Function: check_database_tables
 * ------------------------------------------
 * This function checks the health of database tables without repairing them.
 * It uses the db() function to execute SQL queries (CHECK TABLE).
 * Results are collected in a report array and log entries are created for each
 * detected issue (corrupt or error).
 *
 * Compatibility: PHP 7.0 - 8.5
 * Returns: array containing check messages for each table
 */

function check_and_repair_database_tables($deep = false)
{
    // Cached for an hour. The routine sweep is cheap now, but it is still a
    // query per table and the dashboard asks for it on every load.
    $cache_file = PG_FUNCTIONS_DIR . '/data/temp/db_health_cache.json';

    if (!$deep && file_exists($cache_file)) {
        $cache = json_decode(file_get_contents($cache_file), true);
        if (is_array($cache) && isset($cache['last_check']) && (time() - (int) $cache['last_check']) < 3600) {
            return $cache['report'] ?? [];
        }
    }

    $cache_dir = dirname($cache_file);

    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    // One sweep at a time.
    //
    // CHECK TABLE holds the table for its duration, so two sweeps started
    // together do not take twice as long -- they take an order of magnitude
    // longer, each waiting on the other's locks. Two dashboards opened at the
    // same moment were enough to trigger it. A request that cannot take the
    // lock answers with what the last sweep found instead of queueing.
    $lock_handle = @fopen($cache_dir . '/db_health.lock', 'c');

    if ($lock_handle !== false && !flock($lock_handle, LOCK_EX | LOCK_NB)) {

        fclose($lock_handle);

        $cache = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : null;

        return (is_array($cache) && isset($cache['report']) && is_array($cache['report']))
            ? $cache['report']
            : array();
    }

    // Stamp before the sweep, not after.
    //
    // The cache used to be written on the last line only, so a sweep that ran
    // into max_execution_time wrote nothing -- and the next request started the
    // same sweep from the top and died in the same place. A database large
    // enough to exceed the limit could therefore never produce a cached result
    // and every single dashboard load paid the full cost. Stamping first turns
    // that into one slow load an hour at worst.
    $previous = array();

    if (file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached) && isset($cached['report']) && is_array($cached['report'])) {
            $previous = $cached['report'];
        }
    }

    file_put_contents($cache_file, json_encode(array('last_check' => time(), 'report' => $previous)));

    // The list comes from the schema rather than from a copy of it kept here.
    // A hand-written list goes stale in both directions: a table added by an
    // upgrade is never checked, and one that has been dropped answers every
    // sweep with 'check_failed'.
    //
    // ENGINE arrives with it and decides how each table is checked. A NULL
    // engine is itself an answer -- the server could not read the table's
    // definition -- and it is the one corruption case that costs nothing to
    // find. information_schema is read here for the same reason and with the
    // same caveat as the database size check above.
    $catalog = db(
        "SELECT TABLE_NAME AS table_name, ENGINE AS table_engine
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );

    $tables = array();

    if (is_array($catalog)) {

        // db() collapses a single row into one associative array.
        if (isset($catalog['table_name'])) {
            $catalog = array($catalog);
        }

        foreach ($catalog as $catalog_row) {

            if (!is_array($catalog_row) || !isset($catalog_row['table_name'])) {
                continue;
            }

            $tables[(string) $catalog_row['table_name']] =
                (isset($catalog_row['table_engine']) && $catalog_row['table_engine'] !== null)
                    ? strtoupper((string) $catalog_row['table_engine'])
                    : '';
        }
    }

    // Engines whose CHECK TABLE understands the cheap flags and whose REPAIR
    // TABLE actually repairs. Everything else is left to the deep sweep:
    // InnoDB ignores the flags and reads every row and every index, which on a
    // busy site's waf_log or perf_log is minutes on its own, and REPAIR TABLE
    // cannot fix an InnoDB table in any case. A broken tablespace still shows
    // up above, as a table whose engine came back NULL.
    $repairable_engines = array('MYISAM', 'ARIA', 'MRG_MYISAM', 'ISAM');

    $report = [];

    foreach ($tables as $table => $engine) {

        if ($engine === '') {

            log_activity(lang([
                'string' => 'Database table issue detected: {var:1} ({var:2})',
                'vars' => [$table, 'engine unavailable']
            ]), $_SESSION['sessionusername'] ?? 'SYSTEM');

            $report[$table][] = "error";
            continue;
        }

        $supports_repair = in_array($engine, $repairable_engines, true);

        if (!$deep && !$supports_repair) {
            continue;
        }

        // FAST skips a table the server closed cleanly; QUICK checks the index
        // without reading the data file behind it. Together they turn a
        // multi-gigabyte MyISAM table from a full read into little more than a
        // header check, and they still catch the crash-and-corrupt case that
        // REPAIR TABLE exists for. The full MEDIUM scan is what $deep asks for.
        $check = db("CHECK TABLE `$table`" . ($deep ? '' : ' FAST QUICK'));

        if ($check && is_array($check)) {

            // A check that has only one thing to say comes back as a single
            // associative row rather than as a list of them.
            $check_rows = isset($check['Msg_text']) ? array($check) : $check;

            foreach ($check_rows as $row) {

                $status = (is_array($row) && isset($row['Msg_text'])) ? $row['Msg_text'] : '';

                if (stripos($status, 'error') !== false || stripos($status, 'corrupt') !== false) {
                    // log issue
                    log_activity(lang([
                        'string' => 'Database table issue detected: {var:1} ({var:2})',
                        'vars' => [$table, $status]
                    ]), $_SESSION['sessionusername'] ?? 'SYSTEM');

                    // try repair, where the engine has one
                    $fixed = false;

                    if ($supports_repair) {

                        $repair = db("REPAIR TABLE `$table`");

                        if ($repair && is_array($repair)) {

                            $repair_rows = isset($repair['Msg_text']) ? array($repair) : $repair;

                            foreach ($repair_rows as $r) {
                                $repair_status = (is_array($r) && isset($r['Msg_text'])) ? $r['Msg_text'] : '';
                                if (stripos($repair_status, 'OK') !== false) {
                                    $fixed = true;
                                }
                                log_activity(lang([
                                    'string' => 'Repair attempted on table {var:1}: {var:2}',
                                    'vars' => [$table, $repair_status]
                                ]), $_SESSION['sessionusername'] ?? 'SYSTEM');
                            }
                        }
                    }

                    $report[$table][] = $fixed ? "repaired" : "error";
                } else {
                    $report[$table][] = "healthy";
                }
            }
        } else {
            $report[$table][] = "check_failed";
        }
    }

    // Cache the result so subsequent loads within the hour skip the sweep.
    file_put_contents($cache_file, json_encode(['last_check' => time(), 'report' => $report]));

    if ($lock_handle !== false) {
        flock($lock_handle, LOCK_UN);
        fclose($lock_handle);
    }

    return $report;
}
