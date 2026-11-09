<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Automatic backups: the weekly archive auto_backup.php writes, how many of
 * them are kept, and the copy sent to a remote store (FTP, an S3-compatible
 * bucket, or Google Drive, OneDrive or Dropbox through a connected account:
 * includes/fn/backup_cloud.php). Also the database dump every backup writes,
 * the hand-made and pre-upgrade ones included (pg_backup_dump_database()).
 *
 * Only names produced by pg_backup_auto_name() are ever deleted here. The
 * install dumps under data/backups (english_default, turkish_default), the
 * pre-upgrade dumps and every backup an operator named by hand fail
 * pg_backup_is_auto_name() and are left alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Seconds a newest backup may be old before it is reported as a problem: one
 * month of thirty days. pg_backup_age_state() is the only reader.
 */
if (!defined('PG_BACKUP_WARN_AGE')) {
    define('PG_BACKUP_WARN_AGE', 2592000);
}


/**
 * The directory every backup lives in.
 *
 * @return string absolute path, no trailing slash
 */
function pg_backup_directory()
{
    return PG_FUNCTIONS_DIR . '/data/backups';
}


/**
 * The name of the automatic backup taken at a given time, without extension.
 *
 * Year, month and ISO week, the year and month taken from the week's
 * Thursday: every run in the same week writes the same name and so replaces
 * that week's backup, also when the week crosses a month or a year, and the
 * year is the ISO year of the week. The calendar date of the run would split
 * such a week into two names, and the retention would count it twice. The
 * archive adds ".zip".
 *
 * @param int $time
 * @return string
 */
function pg_backup_auto_name($time)
{
    $time = (int) $time;

    // Noon of the Thursday, by calendar day, so a DST change cannot move it.
    $thursday = mktime(12, 0, 0, (int) date('n', $time), (int) date('j', $time) + 4 - (int) date('N', $time), (int) date('Y', $time));

    return 'auto_backup_' . date('Y-m', $thursday) . '-' . date('W', $time);
}


/**
 * The longest folder name a hand-made backup gets; the Backup Name field
 * carries the same maxlength. Long names made mkdir() fail on Windows paths.
 */
const PG_BACKUP_NAME_MAX = 100;


/**
 * The folder a hand-made backup is written to, from the name typed for it.
 *
 * Turkish and other Latin letters mapped to ASCII (pg_ascii_file_name()),
 * then everything outside letters, digits, "_" and "-" made "_" -- "/" and
 * "\" too, so no part of the name is dropped and no path leaves
 * data/backups, and dots, so "." and ".." cannot name a folder. Runs of "_"
 * collapse, the name is cut to PG_BACKUP_NAME_MAX and the ends are trimmed,
 * so a second pass gives the same name: every backup step sends the name
 * back and computes it again.
 *
 * @param string $name
 * @return string empty when nothing usable is left
 */
function pg_backup_folder_name($name)
{
    $name = preg_replace('/[^A-Za-z0-9_-]/', '_', pg_ascii_file_name((string) $name));
    $name = trim(preg_replace('/_{2,}/', '_', $name), '_');

    return trim(substr($name, 0, PG_BACKUP_NAME_MAX), '_');
}


/**
 * Whether a backup folder name belongs to the software, not to a hand-made
 * backup: the install dumps, the automatic backups and the pre-upgrade
 * dumps. The Backup Manager refuses to write under such a name, so a typed
 * name cannot replace a starter site or a backup the retention manages.
 *
 * Compared without case: Windows and macOS folders ignore it.
 *
 * @param string $name a name from pg_backup_folder_name()
 * @return bool
 */
function pg_backup_name_reserved($name)
{
    $name = strtolower((string) $name);

    if (in_array($name, array('english_default', 'turkish_default'), true) || pg_backup_is_auto_name($name)) {
        return true;
    }

    return ((strpos($name, 'pre_upgrade_') === 0) || (strpos($name, 'pre-update-') === 0));
}


/**
 * Whether a listed backup is one of the starter-site dumps the installer
 * offers. Deleting one removes that language from a later install made from
 * this copy, so the Backup Manager's delete confirmation says so. Only the
 * folders count: the installer reads folders, not archives.
 *
 * Compared without case: Windows and macOS folders ignore it.
 *
 * @param string $name a name from backup_list()
 * @return bool
 */
function pg_backup_is_starter_dump($name)
{
    return in_array(strtolower((string) $name), array('english_default', 'turkish_default'), true);
}


/**
 * The order the Backup Manager lists backups in: newest first, then by name.
 *
 * @param array $times backup name => the time it was taken
 * @return array the names in that order
 */
function pg_backup_list_order(array $times)
{
    $names = array_map('strval', array_keys($times));

    usort($names, function ($a, $b) use ($times) {
        if ((int) $times[$a] !== (int) $times[$b]) {
            return ((int) $times[$a] < (int) $times[$b]) ? 1 : -1;
        }
        return strcmp((string) $a, (string) $b);
    });

    return $names;
}


/**
 * When a backup in data/backups was taken. A folder's own time moves
 * whenever something is written into it (the download archive, a sync), so
 * a folder backup takes its dump's time and falls back to the folder's only
 * without one.
 *
 * @param string $path the archive or folder
 * @return int 0 when unreadable
 */
function pg_backup_taken_time($path)
{
    if (is_dir($path) && is_file($path . '/sql.sql')) {
        return (int) @filemtime($path . '/sql.sql');
    }

    return (int) @filemtime($path);
}


/**
 * Whether a directory entry is an automatic backup, as a folder or an archive.
 *
 * The one gate in front of every delete this module makes, so it is strict:
 * a hand-named backup, an install dump or a stray file never matches.
 *
 * @param string $name
 * @return bool
 */
function pg_backup_is_auto_name($name)
{
    return (is_string($name) && (preg_match('/^auto_backup_\d{4}-\d{2}-\d{2}(\.zip)?$/', $name) === 1));
}


/**
 * The order an automatic backup name sorts in, oldest first.
 *
 * New names take the year and month from the week's Thursday and sort as
 * plain strings. Names written before that used the run's calendar date,
 * which disagrees with the ISO week for a few days around New Year: 1 January
 * can be week 53 and 29 December week 1. Plain string order would put
 * "2027-01-53" (1 January) after "2027-01-01" (the week after), so the week
 * is moved to the end of its month's range when it belongs to the
 * neighbouring year.
 *
 * @param string $name an automatic backup name, with or without ".zip"
 * @return string a key that compares as a string in time order
 */
function pg_backup_auto_sort_key($name)
{
    if (!preg_match('/^auto_backup_(\d{4})-(\d{2})-(\d{2})/', (string) $name, $parts)) {
        return '';
    }

    $month = (int) $parts[2];
    $week  = (int) $parts[3];

    if (($month === 1) && ($week >= 52)) {
        $week = 0;
    } elseif (($month === 12) && ($week === 1)) {
        $week = 54;
    }

    return sprintf('%04d%02d%02d', (int) $parts[1], $month, $week);
}


/**
 * Which automatic backups a retention of $keep removes.
 *
 * A week counts once however it is stored. When the same week exists both as
 * a folder and as an archive the archive is the newer copy -- the folder was
 * written before ZipArchive became available, or before this code arrived --
 * so the folder is removed even when its week is kept. Everything that is not
 * an automatic backup name is ignored, whatever is passed in.
 *
 * @param array $names directory entries
 * @param int $keep how many weeks to keep; below 1 keeps everything
 * @return array names to delete, newest first
 */
function pg_backup_prune_list(array $names, $keep)
{
    $keep = (int) $keep;

    if ($keep < 1) {
        return array();
    }

    // week => array('zip' => name|null, 'folder' => name|null)
    $weeks = array();

    foreach ($names as $name) {

        if (!pg_backup_is_auto_name($name)) {
            continue;
        }

        $is_zip = (substr($name, -4) === '.zip');
        $base   = $is_zip ? substr($name, 0, -4) : $name;

        if (!isset($weeks[$base])) {
            $weeks[$base] = array('zip' => null, 'folder' => null);
        }

        $weeks[$base][$is_zip ? 'zip' : 'folder'] = $name;
    }

    uksort($weeks, function ($a, $b) {
        return strcmp(pg_backup_auto_sort_key($b), pg_backup_auto_sort_key($a));
    });

    $delete = array();
    $position = 0;

    foreach ($weeks as $week) {

        $position++;

        if ($position > $keep) {
            if ($week['zip'] !== null) {
                $delete[] = $week['zip'];
            }
            if ($week['folder'] !== null) {
                $delete[] = $week['folder'];
            }
            continue;
        }

        if (($week['zip'] !== null) && ($week['folder'] !== null)) {
            $delete[] = $week['folder'];
        }
    }

    return $delete;
}


/**
 * Write a zip archive from a list of files.
 *
 * pclzip is not tried: without ZipArchive the automatic backup stays a folder.
 *
 * @param string $zip_path where to write it; replaced if it exists
 * @param array $sources array(array('path' => file on disk, 'local' => name inside the archive), ...)
 * @return bool true when the archive was written and starts with a zip signature
 */
function pg_backup_zip_create($zip_path, array $sources)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    foreach ($sources as $source) {

        if (!isset($source['path'], $source['local'])) {
            continue;
        }

        // A file that cannot be read is skipped rather than failing the whole
        // archive; close() would otherwise abort on it.
        if (!is_file($source['path']) || !is_readable($source['path'])) {
            continue;
        }

        $zip->addFile($source['path'], $source['local']);
    }

    // A failed close() also raises a warning; the return value is what counts.
    $closed = @$zip->close();

    $head = '';

    if ($closed && is_file($zip_path)) {
        $handle = @fopen($zip_path, 'rb');
        if ($handle) {
            $head = (string) fread($handle, 4);
            fclose($handle);
        }
    }

    if (!$closed || !pg_looks_like_zip($head)) {
        if (is_file($zip_path)) {
            @unlink($zip_path);
        }
        return false;
    }

    return true;
}


/**
 * Whether the archive kept inside a backup folder still holds that folder.
 *
 * The Backup Manager zips a folder backup into the folder itself on its first
 * download and serves that archive afterwards. Updating the backup (the same
 * name again, or the weekly automatic folder) rewrites the dump, files and
 * layouts but leaves the archive, so it is current only while no other file
 * under the folder is as new as it is: a file from the same second may have
 * been written after it.
 *
 * @param string $zip_path the archive
 * @param string $folder   the backup folder it was made from
 * @return bool false when the archive is missing, older, or the folder cannot be read
 */
function pg_backup_folder_zip_current($zip_path, $folder)
{
    if (!is_file($zip_path) || !is_dir($folder)) {
        return false;
    }

    $zip_time = filemtime($zip_path);
    $zip_real = realpath($zip_path);

    if (($zip_time === false) || ($zip_real === false)) {
        return false;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() || ($entry->getRealPath() === $zip_real)) {
                continue;
            }

            if ($entry->getMTime() >= $zip_time) {
                return false;
            }
        }
    } catch (UnexpectedValueException $e) {
        return false;
    }

    return true;
}


/**
 * Remove a directory and everything under it.
 *
 * @param string $path
 * @return bool true when the directory is gone
 */
function pg_backup_delete_tree($path)
{
    if (!is_dir($path)) {
        return !file_exists($path);
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
    } catch (UnexpectedValueException $e) {
        return false;
    }

    return @rmdir($path);
}


/**
 * Copy the regular files of one folder into another.
 *
 * Directories, '.' and '..' are skipped. A file counts as not copied when
 * copy() fails or the copy's size differs from the source's: on a full disk
 * copy() can stop partway, and a backup holding a truncated picture or
 * layout must not be reported as complete.
 *
 * @param string $source folder to read
 * @param string $target folder to write; it must already exist
 * @return array the names that could not be copied, in reading order
 */
function pg_backup_copy_folder($source, $target)
{
    $failed = array();

    $handle = is_dir($source) ? @opendir($source) : false;

    if (!$handle) {
        return $failed;
    }

    while (false !== ($entry = readdir($handle))) {

        $from = $source . '/' . $entry;

        if (($entry === '.') || ($entry === '..') || !is_file($from)) {
            continue;
        }

        $to = $target . '/' . $entry;

        clearstatcache(true, $to);

        if (!@copy($from, $to) || !is_file($to) || (@filesize($to) !== @filesize($from))) {
            $failed[] = $entry;
        }
    }

    closedir($handle);

    return $failed;
}


/**
 * The message a backup gives when files could not be copied.
 *
 * Names the count and the first few files, so the person can see whether
 * it is one broken file or the disk running out.
 *
 * @param array $failed names returned by pg_backup_copy_folder()
 * @param int $shown how many names to list
 * @return string
 */
function pg_backup_copy_failure_message(array $failed, $shown = 3)
{
    $names = implode(', ', array_slice($failed, 0, $shown));

    if (count($failed) > $shown) {
        $names .= ', ...';
    }

    return lang(array('string' => '{var:1} files could not be copied into the backup ({var:2}), so it was removed. Free some disk space and try again.', 'vars' => array(count($failed), $names)));
}


// ----------------------------------------------------------------------------
// Database dump
// ----------------------------------------------------------------------------

/**
 * Tables every backup dumps without their rows.
 *
 * One row per visit, and the counters every page view and form item view
 * adds to. On a busy site they are most of the dump and most of the time a
 * restore takes, and nothing else in the database needs their rows: the
 * structure is dumped, so a restored site opens them empty and counts on.
 * visitor_texts is the list of referring addresses and browsers those visit
 * rows name by number; without the rows its values name nothing.
 * The readers' counter of a form item (submitted_form_info.number_of_views),
 * the daily visitor summary (visitor_stats_daily) and the hourly visitor
 * chart (visitor_stats_hourly) keep their rows.
 *
 * The same goes for the request logs the firewall, the performance monitor
 * and the API write: each is only read for its own screen and dashboard
 * figures, is pruned by its own retention setting, and fills again with the
 * next request. The hourly performance summary (perf_stats) keeps its rows,
 * and so do the logs that record what someone did (the site log, the ERP
 * audit and e-document logs) and cron_runs, whose rows the scheduled jobs
 * read back as their watermarks.
 *
 * The Search Console figures (seo_search_daily) are a copy of what Google
 * keeps for sixteen months, a row per day for every address and every search,
 * so they grow with the site's traffic. The next download after a restore
 * finds the table empty and reads the whole window again (pg_gsc_resume_day()).
 * The other SEO and search tables keep their rows: seo_issue and seo_link are
 * the findings and the link graph behind the scores stored on every page and
 * product, which would point at nothing until each one is checked again, and
 * they grow with the content rather than the traffic. So does search_items,
 * the index of the advanced site search, which only update_search_index.php
 * rebuilds; search_results_pages is the settings of the search results pages.
 *
 * @return array table name => what its rows are, as the dump's note says it
 */
function pg_backup_rowless_tables()
{
    return array(
        'visitors'                  => 'visit records',
        'visitor_texts'             => 'referring addresses and browsers of the visit records',
        'visitor_content_hourly'    => 'page views per hour',
        'submitted_form_views'      => 'form item views, one row each',
        'submitted_form_view_stats' => 'form item views per day',
        'waf_log'                   => 'firewall events',
        'perf_log'                  => 'slow requests',
        'api_request_log'           => 'API requests, one row each',
        'seo_search_daily'          => 'Search Console figures, downloaded again',
    );
}


/**
 * Tables every backup leaves out altogether.
 *
 * The two working copies the move of a MyISAM visitors table to InnoDB keeps
 * while it runs (pg_visitor_move_run()). They only mean something next to that
 * run's marker in data/temp, which no backup carries, and one of them is as
 * large as visitors.
 *
 * @return array table names
 */
function pg_backup_skipped_tables()
{
    return array(PG_VISITOR_MOVE_COPY, PG_VISITOR_MOVE_OLD);
}


/**
 * The character set the dump reads the tables in, from the site connection's.
 *
 * init_mysql_charset() opens the site's connection in utf8mb4 on every server
 * that has it, and the tables store four-byte characters (emoji, some CJK).
 * Mysqldump's own default is utf8, three bytes: the server sends each of
 * those characters as "?" and the backup keeps the "?". Only a connection
 * that is itself utf8 (a server older than 5.5.3) dumps in utf8; an unknown
 * one dumps in utf8mb4, which every supported server has.
 *
 * @param mixed $connection_charset mysqli_character_set_name() of the site connection
 * @return string 'utf8mb4' or 'utf8'
 */
function pg_backup_dump_charset($connection_charset)
{
    $charset = is_string($connection_charset) ? strtolower($connection_charset) : '';

    return (($charset === 'utf8') || ($charset === 'utf8mb3')) ? 'utf8' : 'utf8mb4';
}


/**
 * The settings Mysqldump is given for a backup.
 *
 * @param string|null $charset the dump's character set; null follows the site connection
 * @return array
 */
function pg_backup_dump_settings($charset = null)
{
    if ($charset === null) {
        $connected = class_exists('db') && !empty(db::$con);
        $charset = pg_backup_dump_charset($connected ? @mysqli_character_set_name(db::$con) : '');
    }

    return array(
        'no-data'               => array_keys(pg_backup_rowless_tables()),
        'exclude-tables'        => pg_backup_skipped_tables(),
        'default-character-set' => $charset,
    );
}


/**
 * How many bytes of the database a backup copies, from the sizes of its tables.
 *
 * The tables pg_backup_dump_settings() dumps without their rows or leaves out
 * are not counted: what they add to sql.sql is a CREATE TABLE or nothing.
 * Every other table counts at its data and index size, the figure the
 * estimate before a backup has always used.
 *
 * @param array $table_bytes table name => data and index bytes
 * @return float
 */
function pg_backup_estimate_bytes(array $table_bytes)
{
    $settings = pg_backup_dump_settings();
    $left_out = array_merge($settings['no-data'], $settings['exclude-tables']);

    $bytes = 0.0;

    foreach ($table_bytes as $table => $size) {

        if (in_array((string) $table, $left_out, true)) {
            continue;
        }

        $bytes += (float) $size;
    }

    return $bytes;
}


/**
 * The size of this database and the part of it a backup copies.
 *
 * information_schema's data and index lengths, the storage engine's own
 * estimate. A host that hides information_schema gives zeros for both.
 *
 * @return array array('database' => float, 'backup' => float) in bytes
 */
function pg_backup_database_size()
{
    $table_bytes = array();

    $rows = db_items("SELECT TABLE_NAME AS name, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");

    foreach ($rows as $row) {
        $table_bytes[(string) $row['name']] = (float) $row['bytes'];
    }

    return array(
        'database' => (float) array_sum($table_bytes),
        'backup'   => pg_backup_estimate_bytes($table_bytes),
    );
}


/**
 * The lines a backup's sql.sql starts with.
 *
 * SQL comments, so every restore path skips them. English and fixed: the file
 * may be restored on a site in another language.
 *
 * @return string
 */
function pg_backup_dump_note()
{
    $lines = array(
        '-- Pinegrap database backup',
        '--',
        '-- These tables are dumped without their rows and are empty after a restore:',
    );

    foreach (pg_backup_rowless_tables() as $table => $rows) {
        $lines[] = '--   ' . str_pad($table, 27) . $rows;
    }

    $lines[] = '-- The daily visitor summary (visitor_stats_daily) and the hourly visitor';
    $lines[] = '-- chart (visitor_stats_hourly) are backed up with their rows.';
    $lines[] = '--';

    return implode(PHP_EOL, $lines) . PHP_EOL;
}


/**
 * Dump the database into $file for a backup.
 *
 * Mysqldump opens its file with "wb" and has no way to put anything ahead of
 * its own header, so the dump is written beside $file first and copied in
 * behind the note. Without the rows of pg_backup_rowless_tables() that copy
 * is small.
 *
 * @param string $file path of the dump to write
 * @throws Exception when the dump or the copy fails; neither file is left behind
 */
function pg_backup_dump_database($file)
{
    include_once(PG_FUNCTIONS_DIR . '/mysqldump.php');

    $part = $file . '.part';

    try {
        $dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USERNAME, DB_PASSWORD, pg_backup_dump_settings());
        $dump->start($part);
    } catch (\Exception $e) {
        @unlink($part);
        throw $e;
    }

    clearstatcache(true, $part);

    $note = pg_backup_dump_note();
    $size = @filesize($part);
    $in = @fopen($part, 'rb');
    $out = ($in !== false) ? @fopen($file, 'wb') : false;

    // A short write is what a full disk gives, not false.
    $written = ($size !== false)
        && ($out !== false)
        && (@fwrite($out, $note) === strlen($note))
        && (@stream_copy_to_stream($in, $out) === $size);

    if ($in !== false) {
        fclose($in);
    }

    if ($out !== false) {
        $written = @fclose($out) && $written;
    }

    @unlink($part);

    if (!$written) {
        @unlink($file);
        throw new \Exception(lang(array('string' => '{var:1} could not be written to disk.', 'vars' => array(basename($file)))));
    }
}


// ----------------------------------------------------------------------------
// Settings
// ----------------------------------------------------------------------------

/**
 * Whether the backup columns (2026.4.8, 8.33) are in config.
 *
 * Asked once per request. The settings screen, the scheduled job and the
 * System Status card all read these columns, and db() ends the request on an
 * unknown column, so nothing reads them before this says yes.
 *
 * @return bool
 */
function pg_backup_settings_ready()
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    $ready = false;

    if (!class_exists('db') || empty(db::$con)) {
        return $ready;
    }

    $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM config WHERE Field IN ('backup_keep', 'backup_remote_type', 'backup_remote_settings', 'backup_remote_error', 'backup_remote_sent_at')");

    $ready = ($result && (mysqli_num_rows($result) === 5));

    return $ready;
}


/**
 * The backup settings from the config row, or the defaults before the upgrade.
 *
 * backup_remote_settings is returned as stored -- encrypted. It is decoded by
 * the code that is about to connect (pg_backup_remote_config()).
 *
 * @param bool $reload read the row again (after a save in the same request)
 * @return array column => value
 */
function pg_backup_settings($reload = false)
{
    static $settings = null;

    if (($settings !== null) && !$reload) {
        return $settings;
    }

    $settings = array(
        'backup_keep'            => 4,
        'backup_remote_type'     => '',
        'backup_remote_settings' => '',
        'backup_remote_error'    => '',
        'backup_remote_sent_at'  => 0,
    );

    if (!pg_backup_settings_ready()) {
        return $settings;
    }

    $row = db_item("SELECT backup_keep, backup_remote_type, backup_remote_settings, backup_remote_error, backup_remote_sent_at FROM config");

    if (is_array($row)) {
        $settings['backup_keep']            = (int) $row['backup_keep'];
        $settings['backup_remote_type']     = pg_backup_remote_type($row['backup_remote_type']);
        $settings['backup_remote_settings'] = (string) $row['backup_remote_settings'];
        $settings['backup_remote_error']    = (string) $row['backup_remote_error'];
        $settings['backup_remote_sent_at']  = (int) $row['backup_remote_sent_at'];
    }

    return $settings;
}


/**
 * A remote type from the white list, or '' for none.
 *
 * @param mixed $type
 * @return string '', 'ftp', 's3', 'gdrive', 'onedrive' or 'dropbox'
 */
function pg_backup_remote_type($type)
{
    $type = is_scalar($type) ? (string) $type : '';

    return in_array($type, pg_backup_remote_types(), true) ? $type : '';
}


/**
 * Every remote type, in the order the settings keep them.
 *
 * @return array
 */
function pg_backup_remote_types()
{
    return array('ftp', 's3', 'gdrive', 'onedrive', 'dropbox');
}


/**
 * One remote destination's settings with every key present and typed.
 *
 * A cloud destination keeps only which connected account it uses and the
 * folder; the permission itself stays with the account.
 *
 * @param string $type a remote type
 * @param mixed $values
 * @return array
 */
function pg_backup_remote_normalize($type, $values)
{
    $values = is_array($values) ? $values : array();

    $text = function ($key) use ($values) {
        return isset($values[$key]) ? trim((string) $values[$key]) : '';
    };

    if (pg_backup_cloud_is_type($type)) {

        $connection_id = isset($values['connection_id']) && is_scalar($values['connection_id']) ? (int) $values['connection_id'] : 0;
        $folder = isset($values['folder']) ? pg_backup_cloud_folder($values['folder']) : '';

        return array(
            'connection_id' => max(0, $connection_id),
            'folder'        => ($folder === null) ? '' : $folder,
        );
    }

    if ($type === 'ftp') {

        $port = isset($values['port']) ? (int) $values['port'] : 21;

        return array(
            'host'     => $text('host'),
            'port'     => (($port >= 1) && ($port <= 65535)) ? $port : 21,
            'user'     => $text('user'),
            'password' => isset($values['password']) ? (string) $values['password'] : '',
            'path'     => $text('path'),
            'tls'      => !empty($values['tls']),
        );
    }

    return array(
        'endpoint'   => $text('endpoint'),
        'region'     => $text('region'),
        'bucket'     => $text('bucket'),
        'prefix'     => trim($text('prefix'), '/'),
        'access_key' => $text('access_key'),
        'secret_key' => $text('secret_key'),
        'path_style' => !empty($values['path_style']),
    );
}


/**
 * The FTP password a save stores when the password box was left empty.
 *
 * The box is never drawn back into the form, so an empty one keeps the
 * stored password -- but only for the server it was typed for. FTP sends the
 * password as it is at sign-in, so keeping it for a new host or port, or for
 * a connection that no longer asks for TLS, would hand it to a server (or a
 * network path) the person who typed it never chose; "Save and test" sends it
 * at once. The user and the folder may change.
 *
 * @param array $posted pg_backup_remote_normalize('ftp', ...) of the form
 * @param array $stored pg_backup_remote_normalize('ftp', ...) of the stored settings
 * @return string the password to store; '' when it has to be typed again
 */
function pg_backup_ftp_password_kept(array $posted, array $stored)
{
    if ((string) $posted['password'] !== '') {
        return (string) $posted['password'];
    }

    $same_server = (strtolower((string) $posted['host']) === strtolower((string) $stored['host']))
        && ((int) $posted['port'] === (int) $stored['port'])
        && (!empty($posted['tls']) || empty($stored['tls']));

    return $same_server ? (string) $stored['password'] : '';
}


/**
 * Encrypt the remote settings for config.backup_remote_settings.
 *
 * @param array $values remote type => that destination's settings
 * @return string "<ciphertext>:<iv>"
 */
function pg_backup_remote_encode(array $values)
{
    list($cipher, $iv) = encrypt_string_with_iv(json_encode($values));

    return $cipher . ':' . $iv;
}


/**
 * Decrypt config.backup_remote_settings.
 *
 * Every destination is kept, each under its own key, so switching between
 * them does not lose the other one's password or account.
 *
 * @param string $stored "<ciphertext>:<iv>"
 * @return array remote type => that destination's settings, normalised, in
 *               the order of pg_backup_remote_types()
 */
function pg_backup_remote_decode($stored)
{
    $values = array();
    $stored = (string) $stored;

    if (($stored !== '') && (strpos($stored, ':') !== false)) {
        list($cipher, $iv) = explode(':', $stored, 2);
        $json = decode_ssl_keys($cipher, $iv);
        $decoded = ($json === '') ? null : json_decode($json, true);
        if (is_array($decoded)) {
            $values = $decoded;
        }
    }

    $remote = array();

    foreach (pg_backup_remote_types() as $type) {
        $remote[$type] = pg_backup_remote_normalize($type, isset($values[$type]) ? $values[$type] : array());
    }

    return $remote;
}


/**
 * How many backups the backup folder holds and when the newest was written.
 *
 * Read from the folder, NOT from config.last_software_auto_backup: that column
 * is the scheduler's cursor, and backups.php sets it to 0 to force the next
 * run. Every sub-folder counts (a backup taken by hand is a folder), and of
 * the files only an automatic backup archive does; the two install fixtures
 * are left out, or a site that was never backed up would show two backups
 * dated the day it was installed. One stat per entry, never a recursive walk:
 * the folder can hold gigabytes.
 *
 * The System Status check and the operations alerts both read this, so the
 * tile and the alert never disagree about what "the newest backup" is.
 *
 * @param string|null $directory the backup folder; null for pg_backup_directory()
 * @return array array('count' => int, 'newest' => int unix time, 0 for none)
 */
function pg_backup_newest($directory = null)
{
    if ($directory === null) {
        $directory = pg_backup_directory();
    }

    $found = array('count' => 0, 'newest' => 0);

    $entries = is_dir($directory) ? @scandir($directory) : false;

    if ($entries === false) {
        return $found;
    }

    foreach ($entries as $entry) {

        if (($entry === '.') || ($entry === '..') || in_array($entry, array('english_default', 'turkish_default'), true)) {
            continue;
        }

        $path = $directory . '/' . $entry;

        if (!is_dir($path) && !(pg_backup_is_auto_name($entry) && is_file($path))) {
            continue;
        }

        $found['count']++;

        $stamp = (int) @filemtime($path);

        if ($stamp > $found['newest']) {
            $found['newest'] = $stamp;
        }
    }

    return $found;
}


/**
 * How the age of the newest backup is judged: 'fail' when there is none,
 * 'warn' when it is more than a month old (PG_BACKUP_WARN_AGE), 'pass'
 * otherwise. A weekly schedule that missed a run or two is not a problem worth
 * a warning; a month without a backup is.
 *
 * The System Status tile, the operations alert and through the tile the
 * dashboard greeting and the API's system status all follow this answer, so
 * none of them warns about a backup the others call fine. A switched-off
 * schedule is the caller's business: the tile greys it, the alert skips it.
 *
 * @param int $newest unix time of the newest backup (pg_backup_newest()), 0 for none
 * @param int $now
 * @return string 'fail', 'warn' or 'pass'
 */
function pg_backup_age_state($newest, $now)
{
    if ((int) $newest < 1) {
        return 'fail';
    }

    return (((int) $now - (int) $newest) > PG_BACKUP_WARN_AGE) ? 'warn' : 'pass';
}


/**
 * The reason the last automatic backup run of this request failed.
 *
 * Every failure is logged where it happens; the run itself only answers true
 * or false. The operations alert that follows a failed run names the reason,
 * so the branches that log it also leave it here.
 *
 * @param string|null $set the reason to remember; null only reads
 * @return string '' when nothing failed
 */
function pg_backup_last_error($set = null)
{
    static $error = '';

    if ($set !== null) {
        $error = (string) $set;
    }

    return $error;
}


// ----------------------------------------------------------------------------
// The automatic backup
// ----------------------------------------------------------------------------

/**
 * Take the automatic backup, apply the retention and send the remote copy.
 *
 * With ZipArchive the week's backup is one archive, data/backups/<name>.zip,
 * holding sql.sql, files/ and layouts/. The archive is built under data/temp
 * and moved into place only once it is complete, so a failed run never
 * replaces the week's earlier archive with a broken one. Without ZipArchive
 * the backup is the folder it has always been, and no remote copy is made.
 *
 * @return bool true when a backup was written
 */
function pg_backup_run_auto()
{
    pg_backup_last_error('');

    $directory = pg_backup_directory();

    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    $name = pg_backup_auto_name(time());

    $zip_path = '';

    if (class_exists('ZipArchive')) {
        $zip_path = pg_backup_run_zip($name);
        if ($zip_path === '') {
            return false;
        }
    } elseif (!pg_backup_run_folder($name)) {
        return false;
    }

    db("UPDATE config SET last_software_auto_backup = UNIX_TIMESTAMP()");

    // Scheduled job: there is no signed-in user to attribute the entry to.
    log_activity(lang('Software Auto Backup Success'), 'SYSTEM');

    // Retention starts once the operator can see the setting: on a site that
    // has the code but not yet the 2026.4.8 columns, nothing is deleted.
    $settings = pg_backup_settings();

    if (pg_backup_settings_ready()) {
        pg_backup_prune($settings['backup_keep']);
    }

    if (($zip_path !== '') && ($settings['backup_remote_type'] !== '')) {

        $sent = pg_backup_remote_send($zip_path);

        pg_backup_remote_record($sent);

        // The bucket or cloud folder keeps as many weeks as data/backups does.
        if ($sent['ok'] && (($settings['backup_remote_type'] === 's3') || pg_backup_cloud_is_type($settings['backup_remote_type']))) {

            list($type, $config) = pg_backup_remote_config();
            $pruned = ($type === 's3')
                ? pg_backup_s3_prune($config, $settings['backup_keep'])
                : pg_backup_cloud_prune($type, $config, $settings['backup_keep']);

            foreach ($pruned['deleted'] as $name) {
                log_activity(lang(array('string' => 'Old automatic backup {var:1} was deleted from the remote copy.', 'vars' => array($name))), 'SYSTEM');
            }

            if (!$pruned['ok']) {
                log_activity(lang(array('string' => 'Old automatic backups could not be deleted from the remote copy: {var:1}', 'vars' => array($pruned['error']))), 'SYSTEM');
            }
        }
    }

    return true;
}


/**
 * Dump the database and archive it with the files and layouts.
 *
 * @param string $name the week's backup name, without extension
 * @return string the archive's path, or '' when the run failed (already logged)
 */
function pg_backup_run_zip($name)
{
    $temp = PG_FUNCTIONS_DIR . '/data/temp';

    // A run the server killed half-way (time limit, restart) leaves its
    // working folder behind with a full database dump in it. Anything of this
    // module's own naming older than a day is such a leftover.
    $leftovers = is_dir($temp) ? @scandir($temp) : false;

    if ($leftovers !== false) {
        foreach ($leftovers as $leftover) {
            if (preg_match('/^auto_backup_\d{14}_[0-9a-f]{8}$/', $leftover)
                && is_dir($temp . '/' . $leftover)
                && ((int) @filemtime($temp . '/' . $leftover) < (time() - 86400))
            ) {
                pg_backup_delete_tree($temp . '/' . $leftover);
            }
        }
    }

    $work = $temp . '/auto_backup_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);

    if (!@mkdir($work, 0755, true)) {
        log_activity(pg_backup_last_error(lang(array('string' => 'Software Auto Backup error: the folder {var:1} could not be created.', 'vars' => array('data/temp/' . basename($work))))), 'SYSTEM');
        return '';
    }

    // The database first: if the run is cut short while the files are being
    // added, the dump is what matters most.
    try {
        pg_backup_dump_database($work . '/sql.sql');
    } catch (\Exception $e) {
        pg_backup_delete_tree($work);
        log_activity(pg_backup_last_error(lang(array('string' => 'Software Auto Backup error: {var:1}', 'vars' => array($e->getMessage())))), 'SYSTEM');
        return '';
    }

    $sources = array(array('path' => $work . '/sql.sql', 'local' => 'sql.sql'));

    foreach (array('files' => FILE_DIRECTORY_PATH, 'layouts' => LAYOUT_DIRECTORY_PATH) as $folder => $path) {

        $entries = is_dir($path) ? @scandir($path) : false;

        if ($entries === false) {
            continue;
        }

        foreach ($entries as $entry) {
            if (($entry !== '.') && ($entry !== '..') && is_file($path . '/' . $entry)) {
                $sources[] = array('path' => $path . '/' . $entry, 'local' => $folder . '/' . $entry);
            }
        }
    }

    $work_zip = $work . '/' . $name . '.zip';
    $zip_path = pg_backup_directory() . '/' . $name . '.zip';

    $written = pg_backup_zip_create($work_zip, $sources) && @rename($work_zip, $zip_path);

    pg_backup_delete_tree($work);

    if (!$written) {
        log_activity(pg_backup_last_error(lang(array('string' => 'Software Auto Backup error: the archive {var:1} could not be written.', 'vars' => array($name . '.zip')))), 'SYSTEM');
        return '';
    }

    return $zip_path;
}


/**
 * The folder backup, for a server without ZipArchive.
 *
 * Writes what auto_backup.php has always written: sql.sql, files/, layouts/
 * and a deny-all .htaccess in data/backups/<name>/.
 *
 * @param string $name
 * @return bool
 */
function pg_backup_run_folder($name)
{
    $folder = pg_backup_directory() . '/' . $name;

    if (!file_exists($folder)) {
        @mkdir($folder, 0777, true);
    }

    try {
        pg_backup_dump_database($folder . '/sql.sql');
    } catch (\Exception $e) {
        // An empty folder left by a failed dump is not a backup.
        if (is_dir($folder) && (count(glob($folder . '/*')) === 0)) {
            @rmdir($folder);
        }
        log_activity(pg_backup_last_error(lang(array('string' => 'Software Auto Backup error: {var:1}', 'vars' => array($e->getMessage())))), 'SYSTEM');
        return false;
    }

    foreach (array('files' => FILE_DIRECTORY_PATH, 'layouts' => LAYOUT_DIRECTORY_PATH) as $sub => $source) {

        $target = $folder . '/' . $sub;

        if (!file_exists($target)) {
            @mkdir($target, 0777, true);
        }

        // The week's folder is rewritten on every run: clear what the last
        // run copied, so a file deleted from the site does not linger here.
        foreach (pg_glob_brace($target . '/{,.}*') as $old) {
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $failed = pg_backup_copy_folder($source, $target);

        if ($failed) {
            pg_backup_delete_tree($folder);
            log_activity(pg_backup_last_error(lang(array('string' => 'Software Auto Backup error: {var:1}', 'vars' => array(pg_backup_copy_failure_message($failed))))), 'SYSTEM');
            return false;
        }
    }

    file_put_contents($folder . '/.htaccess', 'deny from all');

    return (file_exists($folder . '/sql.sql') && is_dir($folder . '/files') && is_dir($folder . '/layouts'));
}


/**
 * Delete the automatic backups beyond the retention.
 *
 * @param int $keep weeks to keep; below 1 keeps everything
 * @return array the names deleted
 */
function pg_backup_prune($keep)
{
    $directory = pg_backup_directory();

    $entries = is_dir($directory) ? @scandir($directory) : false;

    if ($entries === false) {
        return array();
    }

    $deleted = array();

    foreach (pg_backup_prune_list($entries, $keep) as $name) {

        // Asked again at the point of deletion, so nothing but an automatic
        // backup name can ever reach unlink() or the recursive delete.
        if (!pg_backup_is_auto_name($name)) {
            continue;
        }

        $path = $directory . '/' . $name;

        if (is_dir($path)) {
            $gone = pg_backup_delete_tree($path);
        } elseif (is_file($path)) {
            $gone = @unlink($path);
        } else {
            continue;
        }

        if ($gone) {
            $deleted[] = $name;
            log_activity(lang(array('string' => 'Old automatic backup {var:1} was deleted.', 'vars' => array($name))), 'SYSTEM');
        }
    }

    return $deleted;
}


// ----------------------------------------------------------------------------
// The remote copy
// ----------------------------------------------------------------------------

/**
 * The decoded settings of the remote destination in use.
 *
 * @return array array(type, settings) -- type '' when none is set
 */
function pg_backup_remote_config()
{
    $settings = pg_backup_settings();
    $type = $settings['backup_remote_type'];

    if ($type === '') {
        return array('', array());
    }

    $remote = pg_backup_remote_decode($settings['backup_remote_settings']);

    return array($type, $remote[$type]);
}


/**
 * Send one file to the remote destination.
 *
 * @param string $file
 * @param string|null $type a remote type or ''; null reads the saved settings
 * @param array|null $config that destination's settings; null reads the saved ones
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_remote_send($file, $type = null, $config = null)
{
    if ($type === null) {
        list($type, $config) = pg_backup_remote_config();
    }

    $type = pg_backup_remote_type($type);

    if (!is_file($file) || !is_readable($file)) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(basename($file)))));
    }

    if ($type === 'ftp') {
        return pg_backup_ftp_put($file, pg_backup_remote_normalize('ftp', $config));
    }

    if ($type === 's3') {
        return pg_backup_s3_put($file, pg_backup_remote_normalize('s3', $config));
    }

    if (pg_backup_cloud_is_type($type)) {
        return pg_backup_cloud_put($file, $type, pg_backup_remote_normalize($type, $config));
    }

    return array('ok' => false, 'error' => lang('No remote backup destination is set.'));
}


/**
 * Store the outcome of a remote copy in config.
 *
 * @param array $result what pg_backup_remote_send() returned
 * @return void
 */
function pg_backup_remote_record($result)
{
    if (!pg_backup_settings_ready()) {
        return;
    }

    if (!empty($result['ok'])) {
        db("UPDATE config SET backup_remote_error = '', backup_remote_sent_at = UNIX_TIMESTAMP()");
        return;
    }

    $error = isset($result['error']) ? (string) $result['error'] : '';

    db("UPDATE config SET backup_remote_error = '" . e($error) . "'");

    log_activity(lang(array('string' => 'The remote backup copy failed: {var:1}', 'vars' => array($error))), 'SYSTEM');

    // A backup that exists only on the server it protects is lost with that
    // server, so the failed copy is worth telling someone about.
    if (function_exists('pg_ops_alert')) {
        pg_ops_alert('backup_remote_failed', $error);
    }
}


/**
 * Upload a file over FTP (or explicit FTPS).
 *
 * The file is written under a temporary name and renamed when complete, so an
 * interrupted upload never leaves a truncated archive under the real name.
 *
 * @param string $file
 * @param array $config pg_backup_remote_normalize('ftp', ...)
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_ftp_put($file, array $config)
{
    if (!extension_loaded('ftp')) {
        return array('ok' => false, 'error' => lang('The FTP extension of PHP is not available on this server.'));
    }

    // Each of these can also be disabled by the host on its own.
    foreach (array('ftp_connect', 'ftp_login', 'ftp_pasv', 'ftp_put', 'ftp_rename', 'ftp_close') as $function) {
        if (!function_exists($function)) {
            return array('ok' => false, 'error' => lang('The FTP extension of PHP is not available on this server.'));
        }
    }

    if ($config['host'] === '') {
        return array('ok' => false, 'error' => lang('The FTP server address is empty.'));
    }

    if ($config['tls']) {
        // Asked for an encrypted connection: never fall back to a clear one.
        if (!function_exists('ftp_ssl_connect')) {
            return array('ok' => false, 'error' => lang('This server cannot open an encrypted FTP connection (ftp_ssl_connect is not available).'));
        }
        $connection = @ftp_ssl_connect($config['host'], $config['port'], 30);
    } else {
        $connection = @ftp_connect($config['host'], $config['port'], 30);
    }

    if (!$connection) {
        return array('ok' => false, 'error' => lang(array('string' => 'Could not connect to the FTP server {var:1}.', 'vars' => array($config['host'] . ':' . $config['port']))));
    }

    if (function_exists('ftp_set_option')) {
        @ftp_set_option($connection, FTP_TIMEOUT_SEC, 30);
    }

    if (!@ftp_login($connection, $config['user'], $config['password'])) {
        // The server's own reply says whether it was the password or, for
        // example, a server that only accepts encrypted connections.
        $last = error_get_last();
        @ftp_close($connection);
        return array('ok' => false, 'error' => lang(array('string' => 'The FTP server refused the sign-in. {var:1}', 'vars' => array(isset($last['message']) ? $last['message'] : ''))));
    }

    @ftp_pasv($connection, true);

    $path   = rtrim($config['path'], '/');
    $target = (($path !== '') ? $path . '/' : '') . basename($file);
    $part   = $target . '.part';

    if (!@ftp_put($connection, $part, $file, FTP_BINARY)) {
        $last = error_get_last();
        @ftp_close($connection);
        return array('ok' => false, 'error' => lang(array('string' => 'The file could not be written to {var:1} on the FTP server. {var:2}', 'vars' => array($target, isset($last['message']) ? $last['message'] : ''))));
    }

    // Some servers refuse to rename over an existing file.
    if (function_exists('ftp_delete')) {
        @ftp_delete($connection, $target);
    }

    if (!@ftp_rename($connection, $part, $target)) {
        @ftp_close($connection);
        return array('ok' => false, 'error' => lang(array('string' => 'The file could not be written to {var:1} on the FTP server. {var:2}', 'vars' => array($target, ''))));
    }

    @ftp_close($connection);

    return array('ok' => true, 'error' => '');
}


/**
 * The shared object storage client's settings (includes/fn/object_storage.php)
 * for the backup destination "S3-compatible storage", which keeps its own
 * fields on the Backups card.
 *
 * An empty endpoint has always meant Amazon S3 in the region given, so a
 * site set up that way keeps sending where it did.
 *
 * @param array $config pg_backup_remote_normalize('s3', ...)
 * @return array pg_os_normalize() plus enabled
 */
function pg_backup_s3_client_config(array $config)
{
    $region = ($config['region'] !== '') ? $config['region'] : 'us-east-1';

    return pg_os_normalize(array(
        'endpoint'   => ($config['endpoint'] !== '') ? $config['endpoint'] : 'https://s3.' . $region . '.amazonaws.com',
        'region'     => $region,
        'bucket'     => $config['bucket'],
        'prefix'     => $config['prefix'],
        'path_style' => $config['path_style'],
        'key_id'     => $config['access_key'],
        'secret'     => $config['secret_key'],
    )) + array('enabled' => true);
}


/**
 * Upload a backup archive to an S3-compatible bucket, under the folder in
 * the bucket and the archive's own name. An archive above 64 MB goes up in
 * parts (pg_os_put()), so its size is not bound by one request.
 *
 * @param string $file
 * @param array $config pg_backup_remote_normalize('s3', ...)
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_s3_put($file, array $config)
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => lang('The cURL extension of PHP is not available on this server.'));
    }

    if (($config['bucket'] === '') || ($config['access_key'] === '') || ($config['secret_key'] === '')) {
        return array('ok' => false, 'error' => lang('The bucket, the access key and the secret key are all required.'));
    }

    $client = pg_backup_s3_client_config($config);

    if (!pg_os_target($client, '')) {
        return array('ok' => false, 'error' => lang('The storage endpoint address is not valid.'));
    }

    $sent = pg_os_put($client, pg_basename($file), $file, array('content_type' => 'application/zip'));

    return array('ok' => $sent['ok'], 'error' => $sent['ok'] ? '' : $sent['message']);
}


/**
 * The names an S3 listing holds directly in the backup folder: keys with no
 * further folder in them. Backups kept in a deeper folder of the same prefix
 * are not this site's and are never pruned.
 *
 * @param array $items pg_os_list() items
 * @return array names
 */
function pg_backup_s3_names(array $items)
{
    $names = array();

    foreach ($items as $item) {
        $key = isset($item['key']) ? (string) $item['key'] : '';

        if (($key !== '') && (strpos($key, '/') === false)) {
            $names[] = $key;
        }
    }

    return array_values(array_unique($names));
}


/**
 * Delete the automatic backups beyond the retention from the bucket's
 * backup folder, as pg_backup_cloud_prune() does for the cloud accounts.
 *
 * @param array $config pg_backup_remote_normalize('s3', ...)
 * @param int $keep weeks to keep; below 1 keeps everything
 * @return array array('ok' => bool, 'deleted' => array, 'error' => string)
 */
function pg_backup_s3_prune(array $config, $keep)
{
    $outcome = array('ok' => true, 'deleted' => array(), 'error' => '');

    if ((int) $keep < 1) {
        return $outcome;
    }

    $client = pg_backup_s3_client_config($config);

    // Automatic backup names start alike; listing by that start keeps a
    // bucket shared with other files from being read whole.
    $listed = pg_os_list($client, 'auto_backup_');

    if (!$listed['ok']) {
        return array('ok' => false, 'deleted' => array(), 'error' => $listed['message']);
    }

    foreach (pg_backup_prune_list(pg_backup_s3_names($listed['items']), (int) $keep) as $name) {

        // Asked again at the point of deletion, as for the local copies.
        if (!pg_backup_is_auto_name($name)) {
            continue;
        }

        $gone = pg_os_delete($client, $name);

        if ($gone['ok']) {
            $outcome['deleted'][] = $name;
        } else {
            $outcome['ok'] = false;
            $outcome['error'] = $gone['message'];
        }
    }

    return $outcome;
}


// ----------------------------------------------------------------------------
// Archives in the bucket, brought back to data/backups
// ----------------------------------------------------------------------------

/**
 * Whether a key in the bucket's backup folder names an archive that may be
 * written into data/backups under that name: a .zip directly in the folder,
 * letters, digits, dots, dashes and underscores only, not starting with a dot.
 *
 * @param string $name
 * @return bool
 */
function pg_backup_s3_archive_name_ok($name)
{
    return is_string($name) && (strlen($name) <= 200)
        && (preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*\.zip$/', $name) === 1);
}


/**
 * The archives of a bucket listing, newest first.
 *
 * @param array $items pg_os_list() items
 * @return array list of array(name, size, modified, etag)
 */
function pg_backup_s3_archive_rows(array $items)
{
    $rows = array();

    foreach ($items as $item) {
        $name = isset($item['key']) ? (string) $item['key'] : '';

        if (!pg_backup_s3_archive_name_ok($name) || isset($rows[$name])) {
            continue;
        }

        $rows[$name] = array(
            'name'     => $name,
            'size'     => (int) $item['size'],
            'modified' => (int) $item['modified'],
            'etag'     => (string) $item['etag'],
        );
    }

    uasort($rows, function ($a, $b) {
        return ($a['modified'] === $b['modified']) ? strcmp($b['name'], $a['name']) : (($a['modified'] < $b['modified']) ? 1 : -1);
    });

    return array_values($rows);
}


/**
 * The archives in the bucket's backup folder of the S3 destination in use.
 *
 * @return array ok, rows (pg_backup_s3_archive_rows()), truncated, error
 */
function pg_backup_s3_archives()
{
    list($type, $config) = pg_backup_remote_config();

    if ($type !== 's3') {
        return array('ok' => false, 'rows' => array(), 'truncated' => false, 'error' => lang('No remote backup destination is set.'));
    }

    $listed = pg_os_list(pg_backup_s3_client_config($config), '', array('limit' => 2000));

    if (!$listed['ok']) {
        return array('ok' => false, 'rows' => array(), 'truncated' => false, 'error' => $listed['message']);
    }

    return array('ok' => true, 'rows' => pg_backup_s3_archive_rows($listed['items']), 'truncated' => $listed['truncated'], 'error' => '');
}


/**
 * Brings one archive of the bucket into data/backups, where the installation
 * screen restores it like any other.
 *
 * The bytes go to data/temp first and the archive is moved into place only
 * after its size matches the listing and its directory reads as the backup
 * of a site (sql.sql in it). The CRC32 of every file is checked when the
 * restore extracts it (pg_restore_archive_copy()); an ETag is not used as a
 * checksum, since an encrypted object's is no MD5. A name that already
 * exists in data/backups is never replaced.
 *
 * @param array $row one of pg_backup_s3_archives()'s rows, as listed now
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_s3_fetch(array $row)
{
    $name = isset($row['name']) ? (string) $row['name'] : '';
    $size = isset($row['size']) ? (int) $row['size'] : 0;

    if (!pg_backup_s3_archive_name_ok($name) || ($size <= 0)) {
        return array('ok' => false, 'error' => lang('This is not a backup archive.'));
    }

    $target = pg_backup_directory() . '/' . $name;

    if (file_exists($target)) {
        return array('ok' => false, 'error' => lang(array('string' => 'A backup named {var:1} is already on this server.', 'vars' => array($name))));
    }

    $temp = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($temp) || !pg_dir_writable($temp) || !is_dir(pg_backup_directory())) {
        return array('ok' => false, 'error' => lang('The data/temp or data/backups folder cannot be written.'));
    }

    pg_backup_s3_fetch_sweep($temp);

    // The archive itself and room to restore from it.
    if (function_exists('disk_free_space')) {
        $free = @disk_free_space($temp);

        if (($free !== false) && ((float) $free < ((float) $size + 67108864))) {
            return array('ok' => false, 'error' => lang('There is not enough free disk space for this archive.'));
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    list(, $config) = pg_backup_remote_config();

    $work = $temp . '/backup_fetch_' . bin2hex(random_bytes(8)) . '.zip';

    $got = pg_os_get(pg_backup_s3_client_config($config), $name, $work, array('cap' => $size, 'timeout' => pg_os_timeout($size)));

    if (!$got['ok']) {
        return array('ok' => false, 'error' => $got['message']);
    }

    $error = '';

    if (((int) $got['size'] !== $size) || ((int) @filesize($work) !== $size)) {
        $error = lang('The archive did not arrive whole. Try again.');
    } else {
        $read = pg_restore_archive_read($work);

        if (!$read['ok']) {
            $error = $read['error'];
        }
    }

    if (($error === '') && file_exists($target)) {
        $error = lang(array('string' => 'A backup named {var:1} is already on this server.', 'vars' => array($name)));
    }

    if (($error === '') && !@rename($work, $target)) {
        $error = lang('The data/temp or data/backups folder cannot be written.');
    }

    if (is_file($work)) {
        @unlink($work);
    }

    return array('ok' => ($error === ''), 'error' => $error);
}


/**
 * Deletes downloads into data/temp that a request killed outright left
 * behind, after a day.
 *
 * @param string $temp
 * @return void
 */
function pg_backup_s3_fetch_sweep($temp)
{
    foreach ((array) @glob($temp . '/backup_fetch_*.zip') as $path) {
        if (is_string($path) && is_file($path) && (@filemtime($path) < time() - 86400)) {
            @unlink($path);
        }
    }
}
