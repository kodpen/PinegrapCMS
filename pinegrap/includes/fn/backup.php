<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Automatic backups: the weekly archive auto_backup.php writes, how many of
 * them are kept, and the copy sent to a remote store (FTP or an S3-compatible
 * bucket).
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
 * Year, month and ISO week: every run in the same week writes the same name
 * and so replaces that week's backup. The archive adds ".zip".
 *
 * @param int $time
 * @return string
 */
function pg_backup_auto_name($time)
{
    return 'auto_backup_' . date('Y-m-W', (int) $time);
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
 * The name is "Y-m-W" and the week is the ISO week, which disagrees with the
 * calendar year for a few days around New Year: 1 January can be week 53 and
 * 29 December week 1. Plain string order would put "2027-01-53" (1 January)
 * after "2027-01-01" (the week after), so the week is moved to the end of its
 * month's range when it belongs to the neighbouring year.
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
 * @return string '', 'ftp' or 's3'
 */
function pg_backup_remote_type($type)
{
    $type = (string) $type;

    return in_array($type, array('ftp', 's3'), true) ? $type : '';
}


/**
 * One remote destination's settings with every key present and typed.
 *
 * @param string $type 'ftp' or 's3'
 * @param mixed $values
 * @return array
 */
function pg_backup_remote_normalize($type, $values)
{
    $values = is_array($values) ? $values : array();

    $text = function ($key) use ($values) {
        return isset($values[$key]) ? trim((string) $values[$key]) : '';
    };

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
 * Encrypt the remote settings for config.backup_remote_settings.
 *
 * @param array $values array('ftp' => array(...), 's3' => array(...))
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
 * Both destinations are kept, each under its own key, so switching between
 * them does not lose the other one's password.
 *
 * @param string $stored "<ciphertext>:<iv>"
 * @return array array('ftp' => array(...), 's3' => array(...)), normalised
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

    return array(
        'ftp' => pg_backup_remote_normalize('ftp', isset($values['ftp']) ? $values['ftp'] : array()),
        's3'  => pg_backup_remote_normalize('s3', isset($values['s3']) ? $values['s3'] : array()),
    );
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
        pg_backup_remote_record(pg_backup_remote_send($zip_path));
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
        log_activity(lang(array('string' => 'Software Auto Backup error: the folder {var:1} could not be created.', 'vars' => array('data/temp/' . basename($work)))), 'SYSTEM');
        return '';
    }

    include_once(PG_FUNCTIONS_DIR . '/mysqldump.php');

    // The database first: if the run is cut short while the files are being
    // added, the dump is what matters most.
    try {
        $dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
        $dump->start($work . '/sql.sql');
    } catch (\Exception $e) {
        pg_backup_delete_tree($work);
        log_activity(lang(array('string' => 'Software Auto Backup error: {var:1}', 'vars' => array($e->getMessage()))), 'SYSTEM');
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
        log_activity(lang(array('string' => 'Software Auto Backup error: the archive {var:1} could not be written.', 'vars' => array($name . '.zip'))), 'SYSTEM');
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

    include_once(PG_FUNCTIONS_DIR . '/mysqldump.php');

    try {
        $dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
        $dump->start($folder . '/sql.sql');
    } catch (\Exception $e) {
        // An empty folder left by a failed dump is not a backup.
        if (is_dir($folder) && (count(glob($folder . '/*')) === 0)) {
            @rmdir($folder);
        }
        log_activity(lang(array('string' => 'Software Auto Backup error: {var:1}', 'vars' => array($e->getMessage()))), 'SYSTEM');
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

        $handle = is_dir($source) ? @opendir($source) : false;

        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if (($entry !== '.') && ($entry !== '..') && is_file($source . '/' . $entry)) {
                    @copy($source . '/' . $entry, $target . '/' . $entry);
                }
            }
            closedir($handle);
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
 * @param string|null $type '' / 'ftp' / 's3'; null reads the saved settings
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
 * URI-encode a string the way Signature Version 4 expects.
 *
 * Every byte except A-Z a-z 0-9 - _ . ~ is percent-encoded with upper-case
 * hex; "/" is kept when encoding an object key path.
 *
 * @param string $value
 * @param bool $keep_slash
 * @return string
 */
function pg_backup_s3_uri_encode($value, $keep_slash)
{
    $encoded = rawurlencode((string) $value);

    // rawurlencode() leaves "~" alone since PHP 5.3, which is what SigV4 wants.
    return $keep_slash ? str_replace('%2F', '/', $encoded) : $encoded;
}


/**
 * The SigV4 signing key: HMAC chain over date, region, service.
 *
 * @param string $secret
 * @param string $date YYYYMMDD
 * @param string $region
 * @param string $service
 * @return string raw binary key
 */
function pg_backup_s3_signing_key($secret, $date, $region, $service)
{
    $key = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
    $key = hash_hmac('sha256', $region, $key, true);
    $key = hash_hmac('sha256', $service, $key, true);

    return hash_hmac('sha256', 'aws4_request', $key, true);
}


/**
 * The SigV4 canonical request.
 *
 * @param string $method
 * @param string $uri already URI-encoded path
 * @param string|array $query a canonical query string, or name => value pairs to encode and sort
 * @param array $headers name => value; names are lower-cased and sorted here
 * @param string $payload_hash hex sha256 of the body
 * @return array array('request' => string, 'signed_headers' => string)
 */
function pg_backup_s3_canonical_request($method, $uri, $query, array $headers, $payload_hash)
{
    if (is_array($query)) {
        $pairs = array();
        foreach ($query as $name => $value) {
            $pairs[pg_backup_s3_uri_encode($name, false)] = pg_backup_s3_uri_encode($value, false);
        }
        ksort($pairs, SORT_STRING);
        $parts = array();
        foreach ($pairs as $name => $value) {
            $parts[] = $name . '=' . $value;
        }
        $query = implode('&', $parts);
    }

    $canonical = array();

    foreach ($headers as $name => $value) {
        $canonical[strtolower(trim($name))] = preg_replace('/\s+/', ' ', trim((string) $value));
    }

    ksort($canonical, SORT_STRING);

    $lines = '';

    foreach ($canonical as $name => $value) {
        $lines .= $name . ':' . $value . "\n";
    }

    $signed_headers = implode(';', array_keys($canonical));

    return array(
        'request' => strtoupper($method) . "\n"
            . (($uri === '') ? '/' : $uri) . "\n"
            . (string) $query . "\n"
            . $lines . "\n"
            . $signed_headers . "\n"
            . $payload_hash,
        'signed_headers' => $signed_headers,
    );
}


/**
 * The SigV4 string to sign.
 *
 * @param string $amz_date YYYYMMDD'T'HHMMSS'Z'
 * @param string $scope date/region/service/aws4_request
 * @param string $canonical_hash hex sha256 of the canonical request
 * @return string
 */
function pg_backup_s3_string_to_sign($amz_date, $scope, $canonical_hash)
{
    return "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $scope . "\n" . $canonical_hash;
}


/**
 * The SigV4 signature of a string to sign.
 *
 * @return string lower-case hex
 */
function pg_backup_s3_signature($secret, $date, $region, $service, $string_to_sign)
{
    return hash_hmac('sha256', $string_to_sign, pg_backup_s3_signing_key($secret, $date, $region, $service));
}


/**
 * Where an object goes: URL, Host header and canonical path.
 *
 * An empty endpoint is Amazon S3 in the configured region. An endpoint with no
 * scheme is taken as https. Path style puts the bucket in the path (MinIO and
 * most self-hosted stores); virtual-hosted style puts it in the host name.
 *
 * @param array $config pg_backup_remote_normalize('s3', ...)
 * @param string $file_name
 * @return array array('url', 'host', 'uri', 'region') or array() when the endpoint cannot be read
 */
function pg_backup_s3_target(array $config, $file_name)
{
    $region = ($config['region'] !== '') ? $config['region'] : 'us-east-1';

    $endpoint = ($config['endpoint'] !== '') ? $config['endpoint'] : 'https://s3.' . $region . '.amazonaws.com';

    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $endpoint)) {
        $endpoint = 'https://' . $endpoint;
    }

    $parts = parse_url($endpoint);

    if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return array();
    }

    $scheme = strtolower($parts['scheme']);
    $host   = strtolower($parts['host']) . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    $base   = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

    $key = (($config['prefix'] !== '') ? $config['prefix'] . '/' : '') . $file_name;
    $key = pg_backup_s3_uri_encode($key, true);

    if ($config['path_style']) {
        $uri = $base . '/' . pg_backup_s3_uri_encode($config['bucket'], false) . '/' . $key;
    } else {
        $host = $config['bucket'] . '.' . $host;
        $uri  = $base . '/' . $key;
    }

    return array(
        'url'    => $scheme . '://' . $host . $uri,
        'host'   => $host,
        'uri'    => $uri,
        'region' => $region,
    );
}


/**
 * The reason an S3-compatible service gave for refusing a request.
 *
 * @param int $http
 * @param string $body the XML error document, if any
 * @return string
 */
function pg_backup_s3_error_message($http, $body)
{
    $code = '';
    $message = '';

    if (preg_match('#<Code>(.*?)</Code>#s', (string) $body, $match)) {
        $code = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
    }

    if (preg_match('#<Message>(.*?)</Message>#s', (string) $body, $match)) {
        $message = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
    }

    $reason = trim($code . (($code !== '' && $message !== '') ? ': ' : '') . $message);

    return lang(array('string' => 'The storage service answered HTTP {var:1}. {var:2}', 'vars' => array((int) $http, $reason)));
}


/**
 * Upload a file to an S3-compatible bucket with a single signed PUT.
 *
 * The body is streamed from disk; the payload hash is the file's sha256, so
 * the service verifies the bytes it received.
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

    $target = pg_backup_s3_target($config, basename($file));

    if (!$target) {
        return array('ok' => false, 'error' => lang('The storage endpoint address is not valid.'));
    }

    $size = filesize($file);
    $payload_hash = hash_file('sha256', $file);

    if (($size === false) || ($payload_hash === false)) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(basename($file)))));
    }

    $time     = time();
    $amz_date = gmdate('Ymd\THis\Z', $time);
    $date     = gmdate('Ymd', $time);
    $scope    = $date . '/' . $target['region'] . '/s3/aws4_request';

    $headers = array(
        'host'                 => $target['host'],
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date'           => $amz_date,
    );

    $canonical = pg_backup_s3_canonical_request('PUT', $target['uri'], '', $headers, $payload_hash);

    $signature = pg_backup_s3_signature(
        $config['secret_key'],
        $date,
        $target['region'],
        's3',
        pg_backup_s3_string_to_sign($amz_date, $scope, hash('sha256', $canonical['request']))
    );

    $handle = @fopen($file, 'rb');

    if (!$handle) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(basename($file)))));
    }

    $ch = curl_init($target['url']);

    curl_setopt_array($ch, array(
        CURLOPT_UPLOAD         => true,
        CURLOPT_INFILE         => $handle,
        CURLOPT_INFILESIZE     => $size,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        // Two minutes, or one second per 50 KB for a large archive.
        CURLOPT_TIMEOUT        => (int) max(120, ceil($size / 51200)),
        CURLOPT_HTTPHEADER     => array(
            'Host: ' . $target['host'],
            'x-amz-content-sha256: ' . $payload_hash,
            'x-amz-date: ' . $amz_date,
            'Content-Type: application/zip',
            'Authorization: AWS4-HMAC-SHA256 Credential=' . $config['access_key'] . '/' . $scope
                . ', SignedHeaders=' . $canonical['signed_headers']
                . ', Signature=' . $signature,
        ),
    ));

    // Sent with no User-Agent the request looks like an anonymous client to
    // the receiving side's firewall.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');

    // The archive holds the whole database; it is only sent to a verified peer.
    pg_curl_tls($ch);

    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
    fclose($handle);

    if ($errno) {
        return array('ok' => false, 'error' => trim('cURL ' . $errno . ': ' . $error . pg_curl_tls_hint($errno)));
    }

    if ($http !== 200) {
        return array('ok' => false, 'error' => pg_backup_s3_error_message($http, (string) $body));
    }

    return array('ok' => true, 'error' => '');
}
