<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file scan of the Security Scan (2026.11.0): a resumable walk of the
 * software folder and the upload folders that reports, as findings
 * (includes/fn/security_scan.php):
 *
 *   - file_php_unknown        a PHP file the release does not carry
 *   - file_php_retired        such a file at a place an earlier release
 *                             carried, on the Cleanup tool's list
 *                             (pg_clean_up_file_list())
 *   - file_upload_executable  a name in the upload folders the server may run
 *                             (.php, .phtml, .phar, a double extension)
 *   - file_picture_php        PHP tags inside a picture in the upload folders
 *   - file_server_config      a new or changed .htaccess / .user.ini
 *   - file_shell_mark         a known web shell mark (eval(base64_decode(,
 *                             assert($_ ...) in a PHP file outside the
 *                             release reference
 *
 * The scan only reads: it never deletes, moves or blocks a file. It runs from
 * the scheduled task inside a time budget and carries on where it stopped
 * (security_scan_job.php). Everything above the "Disk and database" line is
 * pure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The config_text row that keeps the file scan's run: its cursor, the last
// finished run and the server-config baseline.
define('PG_SECURITY_FILES_STATE', 'security_scan_files');

// A content check reads at most this much of one file, in chunks.
define('PG_SECURITY_FILES_READ_MAX', 32 * 1048576);

// One read of a content check. fread() sets aside the whole length before it
// reads, so a large chunk costs every small picture of the walk that much
// (1 MB took about 170 microseconds a file in a large, fragmented heap).
define('PG_SECURITY_FILES_READ_CHUNK', 65536);

/**
 * The rules of the file scan: kind => severity.
 *
 * @return array<string, int>
 */
function pg_security_file_kinds()
{
    return array(
        'file_php_unknown' => PG_SECURITY_SEVERITY_HIGH,
        'file_php_retired' => PG_SECURITY_SEVERITY_MEDIUM,
        'file_upload_executable' => PG_SECURITY_SEVERITY_CRITICAL,
        'file_picture_php' => PG_SECURITY_SEVERITY_HIGH,
        'file_server_config' => PG_SECURITY_SEVERITY_MEDIUM,
        'file_shell_mark' => PG_SECURITY_SEVERITY_CRITICAL,
    );
}

/**
 * The reason of a file rule in words, for the screen.
 *
 * @param string $kind
 * @return string
 */
function pg_security_file_reason($kind)
{
    switch ((string) $kind) {
        case 'file_php_unknown':
            return lang('A PHP file the release does not carry.');
        case 'file_php_retired':
            return lang('A file of an earlier version that this version no longer uses.');
        case 'file_upload_executable':
            return lang('A file in the upload folders whose name lets the server run it.');
        case 'file_picture_php':
            return lang('A picture with PHP code inside.');
        case 'file_server_config':
            return lang('A server settings file (.htaccess or .user.ini) was added or changed.');
        case 'file_shell_mark':
            return lang('A PHP file outside the release with the mark of a web shell.');
    }

    return '';
}

/**
 * What to do about a file finding, in words, for the screen.
 *
 * @param string $kind
 * @return string
 */
function pg_security_file_advice($kind)
{
    switch ((string) $kind) {
        case 'file_php_unknown':
            return lang('If nobody on your team put this file here, quarantine it and change the passwords of the staff and hosting accounts.');
        case 'file_php_retired':
            return lang('The update leaves the files of earlier versions in place. Run Clean Up in System tools to delete them, or quarantine this one.');
        case 'file_upload_executable':
            return lang('Uploads never need a name the server can run: quarantine the file, then look at who uploaded it.');
        case 'file_picture_php':
            return lang('A real picture never holds PHP code: quarantine the file.');
        case 'file_server_config':
            return lang('Compare the file with what your team or host set. If nobody changed it, put back the earlier copy; if the change is expected, ignore this finding.');
        case 'file_shell_mark':
            return lang('Quarantine the file at once, change every password of the site and the hosting account, and ask your host to look for other changed files.');
    }

    return '';
}

/**
 * The extensions PHP may run a file under, whatever handler a host mapped.
 *
 * @return string[]
 */
function pg_security_php_extensions()
{
    return array('php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'phtml', 'phtm', 'phar', 'pht', 'phpt', 'hphp', 'ctp');
}

/**
 * The extensions read as pictures.
 *
 * @return string[]
 */
function pg_security_picture_extensions()
{
    return array('jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg', 'tif', 'tiff', 'heic');
}

/**
 * The last segment of a place, lower case.
 *
 * @param string $place
 * @return string
 */
function pg_security_file_name($place)
{
    $place = (string) $place;
    $slash = strrpos($place, '/');

    return strtolower(($slash === false) ? $place : substr($place, $slash + 1));
}

/**
 * The last extension of a name, lower case, '' when it has none.
 *
 * @param string $name
 * @return string
 */
function pg_security_file_extension($name)
{
    $name = pg_security_file_name($name);
    $dot = strrpos($name, '.');

    return (($dot === false) || ($dot === 0)) ? '' : substr($name, $dot + 1);
}

/**
 * Does a name end in an extension PHP runs?
 *
 * @param string $name
 * @return bool
 */
function pg_security_name_is_php($name)
{
    return in_array(pg_security_file_extension($name), pg_security_php_extensions(), true);
}

/**
 * The PHP extension a name carries anywhere after its first segment
 * ("shell.php", "shell.php.jpg", "a.PHTML"), '' when none. Apache's
 * AddHandler runs "shell.php.jpg" as PHP, so any segment counts.
 *
 * @param string $name
 * @return string
 */
function pg_security_name_executable($name)
{
    $parts = explode('.', pg_security_file_name($name));

    array_shift($parts);

    foreach ($parts as $part) {
        if (in_array($part, pg_security_php_extensions(), true)) {
            return $part;
        }
    }

    return '';
}

/**
 * Is this a picture by its last extension?
 *
 * @param string $name
 * @return bool
 */
function pg_security_name_is_picture($name)
{
    return in_array(pg_security_file_extension($name), pg_security_picture_extensions(), true);
}

/**
 * Is this a server settings file (.htaccess, .user.ini)?
 *
 * @param string $name
 * @return bool
 */
function pg_security_name_is_server_config($name)
{
    return in_array(pg_security_file_name($name), array('.htaccess', '.user.ini'), true);
}

/**
 * The PHP files the release carries outside what the integrity reference
 * covers (the root files and includes/): they are compared by place.
 *
 * @return string[]
 */
function pg_security_php_shipped_outside_reference()
{
    return array(
        'data/config.php',
        'data/config(default).php',
        'install/index.php',
        'assets/lib/tiny_mce_3_5_10/get_file_list.php',
        'assets/lib/tiny_mce_3_5_10/get_folder_list.php',
        'assets/lib/tiny_mce_3_5_10/get_image_list.php',
        'assets/lib/tiny_mce_3_5_10/get_media_list.php',
        'assets/lib/tiny_mce_3_5_10/get_page_list.php',
        'assets/lib/tiny_mce_3_5_10/plugins/advimage/add_file.php',
        'assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php',
    );
}

/**
 * Is a PHP place outside the reference's scope one this software puts there:
 * a file the release ships (pg_security_php_shipped_outside_reference()),
 * the compiled language cache, data/temp/lang_<code>.php
 * (pg_lang_cache_path()), or the new config.php while it is written,
 * data/config.tmp<uniqid>.php (pg_write_config_file()).
 *
 * @param string $place
 * @return bool
 */
function pg_security_php_known_outside_reference($place)
{
    return in_array((string) $place, pg_security_php_shipped_outside_reference(), true)
        || (preg_match('#^data/temp/lang_[A-Za-z0-9_-]{1,32}\.php$#', (string) $place) === 1)
        || (preg_match('#^data/config\.tmp[0-9a-f]{13}\.php$#', (string) $place) === 1);
}

/**
 * Is a place in the integrity reference's scope: a file directly in the
 * software folder or under includes/? The same scope as
 * pg_integrity_in_scope(), without its ignored names.
 *
 * @param string $place
 * @return bool
 */
function pg_security_place_in_reference_scope($place)
{
    $place = (string) $place;
    $slash = strpos($place, '/');

    return ($slash === false) || (substr($place, 0, $slash) === 'includes');
}

/**
 * The upload area a place is in: 'files' (data/files/), 'layouts'
 * (data/layouts/) or '' for the software folder.
 *
 * @param string $place
 * @return string
 */
function pg_security_place_area($place)
{
    foreach (array('files', 'layouts') as $area) {
        if (strpos((string) $place, 'data/' . $area . '/') === 0) {
            return $area;
        }
    }

    return '';
}

/**
 * The web shell marks: name => pattern. Only looked for in PHP files outside
 * the release reference, where nothing of this software uses them.
 *
 * @return array<string, string>
 */
function pg_security_shell_marks()
{
    return array(
        'eval(base64_decode(' => '/\beval\s*\(\s*base64_decode\s*\(/i',
        'eval(gzinflate(' => '/\beval\s*\(\s*(gzinflate|gzuncompress|str_rot13)\s*\(/i',
        'eval($_' => '/\beval\s*\(\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)\b/i',
        'assert($_' => '/\bassert\s*\(\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)\b/i',
        'system($_' => '/\b(system|passthru|shell_exec|exec|popen|proc_open)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)\b/i',
    );
}

/**
 * The name of the first shell mark found in a text, '' when none.
 *
 * @param string $text
 * @return string
 */
function pg_security_text_shell_mark($text)
{
    foreach (pg_security_shell_marks() as $name => $pattern) {
        if (preg_match($pattern, (string) $text) === 1) {
            return $name;
        }
    }

    return '';
}

/**
 * Does a text hold an opening PHP tag? Only "<?php" followed by a space or
 * the end counts: "<?=" and "<?" are three and two bytes that turn up by
 * chance in the compressed data of a large picture.
 *
 * @param string $text
 * @return bool
 */
function pg_security_text_has_php_tag($text)
{
    return preg_match('/<\?php(\s|$)/i', (string) $text) === 1;
}

/**
 * What a server settings file is against the baseline of the last finished
 * run: 'new', 'changed' or '' (as it was). No baseline yet (the first run)
 * gives '' for every file: the first run takes them as they are.
 *
 * @param array|null $baseline place => sha1
 * @param string $place
 * @param string $digest
 * @return string
 */
function pg_security_config_verdict($baseline, $place, $digest)
{
    if (!is_array($baseline)) {
        return '';
    }

    if (!isset($baseline[$place])) {
        return 'new';
    }

    return ((string) $baseline[$place] === (string) $digest) ? '' : 'changed';
}

/**
 * Does a server settings file only shut its folder to the web? An .htaccess
 * of nothing but "deny from all", "Require all denied", their "Order" and
 * "<IfModule>" lines, "Options -Indexes", comments and blank lines can only
 * take access away. This software writes such a file into every folder it
 * makes for private data (data/, includes/, a backup or mailbox folder), so
 * one that is new or changed is no finding.
 *
 * @param string $place
 * @param string $text The file's content
 * @return bool
 */
function pg_security_config_denies_only($place, $text)
{
    if (pg_security_file_name($place) !== '.htaccess') {
        return false;
    }

    $denies = false;

    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = strtolower(preg_replace('/\s+/', ' ', trim($line)));

        if (($line === '') || ($line[0] === '#')) {
            continue;
        }

        if (in_array($line, array('deny from all', 'require all denied'), true)) {
            $denies = true;
            continue;
        }

        if (
            in_array($line, array('order deny,allow', 'order allow,deny', 'options -indexes', '</ifmodule>'), true)
            || (preg_match('/^<ifmodule !?[a-z0-9_.]+>$/', $line) === 1)
        ) {
            continue;
        }

        return false;
    }

    return $denies;
}

/**
 * The content checks a file needs: 'shell' (shell marks), 'php_tag' (a
 * picture in the upload folders), 'digest' (a server settings file).
 *
 * @param string $place
 * @param bool|null $in_reference Whether the release reference lists it; null when no reference could be read
 * @return string[]
 */
function pg_security_file_reads($place, $in_reference)
{
    $reads = array();
    $area = pg_security_place_area($place);

    if (pg_security_name_is_server_config($place)) {
        $reads[] = 'digest';
    }

    if (($area !== '') && pg_security_name_is_picture($place) && (pg_security_name_executable($place) === '')) {
        $reads[] = 'php_tag';
    }

    if (pg_security_name_is_php($place) || (($area !== '') && (pg_security_name_executable($place) !== ''))) {
        $outside = ($area !== '')
            || (pg_security_place_in_reference_scope($place) ? ($in_reference === false) : !in_array($place, pg_security_php_shipped_outside_reference(), true));

        // The language cache is written by this software but outside any
        // reference, so its content is still looked at.

        if ($outside) {
            $reads[] = 'shell';
        }
    }

    return $reads;
}

/**
 * The sightings for one file (pg_security_sighting() rows).
 *
 * @param string $place
 * @param bool|null $in_reference As for pg_security_file_reads()
 * @param array $facts What the reads found: shell (mark name), php_tag (bool), config (verdict)
 * @return array[]
 */
function pg_security_file_sightings($place, $in_reference, array $facts)
{
    $kinds = pg_security_file_kinds();
    $area = pg_security_place_area($place);
    $found = array();

    if ($area === 'files') {
        $extension = pg_security_name_executable($place);

        if ($extension !== '') {
            $found['file_upload_executable'] = '.' . $extension;
        }
    } elseif ($area === 'layouts') {
        // A custom layout is the page's id with .php: data/layouts/12.php;
        // the 2017.2.2 password pages step kept the earlier one as 12.bak.php
        // (includes/migrations/legacy.php).
        $extension = pg_security_name_executable($place);

        if (($extension !== '') && (preg_match('#^data/layouts/[1-9][0-9]*(?:\.bak)?\.php$#', $place) !== 1)) {
            $found['file_upload_executable'] = '.' . $extension;
        }
    } elseif (pg_security_name_is_php($place)) {
        $unknown = pg_security_place_in_reference_scope($place)
            ? ($in_reference === false)
            : !pg_security_php_known_outside_reference($place);

        // A place an earlier release carried is reported as retired: the
        // update writes over the files and deletes none, and Clean Up removes
        // them. It is still reported, since a file left callable is a risk
        // and a retired name can be a planted file; its content is read for
        // shell marks as any file outside the reference.
        if ($unknown) {
            $found[pg_clean_up_place_listed($place) ? 'file_php_retired' : 'file_php_unknown'] = '';
        }
    }

    if (!empty($facts['php_tag'])) {
        $found['file_picture_php'] = '<?php';
    }

    if (!empty($facts['shell'])) {
        $found['file_shell_mark'] = (string) $facts['shell'];
    }

    $sightings = array();

    foreach ($found as $kind => $detail) {
        $severity = $kinds[$kind];
        $sighting = pg_security_sighting($kind, $place, $severity, $detail);

        if ($sighting !== null) {
            $sightings[] = $sighting;
        }
    }

    if (!empty($facts['config'])) {
        // In the upload folders nothing of this software adds or changes one.
        $severity = ($area !== '') ? PG_SECURITY_SEVERITY_HIGH : $kinds['file_server_config'];
        $sighting = pg_security_sighting('file_server_config', $place, $severity, (string) $facts['config']);

        if ($sighting !== null) {
            $sightings[] = $sighting;
        }
    }

    return $sightings;
}

/**
 * Compare two places segment by segment, as the walk visits them: a folder's
 * files come in the order of their names, and everything inside "a/" comes
 * before "a.b" and "a0".
 *
 * @param string $a
 * @param string $b
 * @return int <0, 0, >0
 */
function pg_security_place_compare($a, $b)
{
    $a = explode('/', (string) $a);
    $b = explode('/', (string) $b);
    $count = min(count($a), count($b));

    for ($i = 0; $i < $count; $i++) {
        $order = strcmp($a[$i], $b[$i]);

        if ($order !== 0) {
            return $order;
        }
    }

    return count($a) - count($b);
}

/**
 * May the walk skip a folder because the run has gone past it? True when the
 * cursor (the last place done) comes after the folder and is not inside it.
 *
 * @param string $folder Place of the folder
 * @param string $cursor Last place done, '' at the start
 * @return bool
 */
function pg_security_folder_done($folder, $cursor)
{
    if ((string) $cursor === '') {
        return false;
    }

    if (strpos((string) $cursor, $folder . '/') === 0) {
        return false;
    }

    return pg_security_place_compare($folder, $cursor) < 0;
}

/**
 * Folders of the software folder the walk never enters: the upload folders
 * (walked on their own, as they may live elsewhere), the backups and a
 * restore's unpacked copy (archives and copies the site does not run), the
 * quarantine folder (files a person moved away) and the Git folder.
 *
 * @param string $place
 * @return bool
 */
function pg_security_walk_skipped($place)
{
    // data/temp/restore_<id> holds a backup being restored
    // (pg_restore_work_path()), the same content as data/backups.
    return in_array((string) $place, array('.git', 'data/backups', 'data/files', 'data/layouts', PG_SECURITY_QUARANTINE_FOLDER), true)
        || (preg_match('#^data/temp/restore_[^/]+$#', (string) $place) === 1);
}

/**
 * A fresh run state, or the stored one with its defaults filled in.
 *
 * @param mixed $stored Decoded JSON of the config_text row
 * @return array
 */
function pg_security_files_state($stored)
{
    $state = array(
        'started' => 0,
        'root' => 0,
        'cursor' => '',
        'files' => 0,
        'reference' => '',
        'baseline' => null,
        'configs' => array(),
        'unread' => array(),
        'last_finished' => 0,
        'last_files' => 0,
        'last_reference' => '',
    );

    if (is_array($stored)) {
        foreach ($state as $key => $value) {
            if (array_key_exists($key, $stored)) {
                $state[$key] = $stored[$key];
            }
        }
    }

    if (!is_array($state['configs'])) {
        $state['configs'] = array();
    }

    if (!is_array($state['unread'])) {
        $state['unread'] = array();
    }

    if (($state['baseline'] !== null) && !is_array($state['baseline'])) {
        $state['baseline'] = null;
    }

    return $state;
}

/**
 * The kinds a finished run may mark gone: the file kinds, without the three
 * that need the release reference when the run had none.
 *
 * @param string $reference 'local', 'github' or ''
 * @return string[]
 */
function pg_security_files_judged_kinds($reference)
{
    $kinds = array_keys(pg_security_file_kinds());

    if ((string) $reference === '') {
        $kinds = array_values(array_diff($kinds, array('file_php_unknown', 'file_php_retired', 'file_shell_mark')));
    }

    return $kinds;
}

// ---------------------------------------------------------------------------
// Disk and database
// ---------------------------------------------------------------------------

/**
 * The places the release reference lists, as place => true, and which
 * reference it is. Only what check_directory_file_integrity() already keeps
 * is read: the dev machine's own reference when stamped for this host and
 * version, else the cached GitHub tree of this version. Nothing is fetched.
 *
 * @return array array('source' => 'local'|'github'|'', 'places' => array)
 */
function pg_security_files_reference()
{
    $base = PG_FUNCTIONS_DIR . '/data/temp/';
    $version = defined('VERSION') ? (string) VERSION : '';
    $none = array('source' => '', 'places' => array());

    if ($version === '') {
        return $none;
    }

    $local = is_file($base . 'hash_reference.json') ? json_decode((string) @file_get_contents($base . 'hash_reference.json'), true) : null;

    if (
        is_array($local) && isset($local['_generated']['version'], $local['_generated']['host'])
        && ((string) $local['_generated']['version'] === $version)
        && (strtolower((string) $local['_generated']['host']) === pg_integrity_host())
    ) {
        $files = pg_integrity_reference_files($local);

        if (!empty($files)) {
            return array('source' => 'local', 'places' => array_fill_keys(array_keys($files), true));
        }
    }

    $github = is_file($base . 'hash_reference_github.json') ? json_decode((string) @file_get_contents($base . 'hash_reference_github.json'), true) : null;

    if (
        is_array($github) && isset($github['version'], $github['files'])
        && ((string) $github['version'] === $version)
        && is_array($github['files']) && !empty($github['files'])
    ) {
        return array('source' => 'github', 'places' => array_fill_keys(array_keys($github['files']), true));
    }

    return $none;
}

/**
 * The roots the walk goes through, in order: place prefix => folder on disk.
 *
 * @return array<string, string>
 */
function pg_security_files_roots()
{
    return array(
        '' => PG_FUNCTIONS_DIR,
        'data/files' => defined('FILE_DIRECTORY_PATH') ? FILE_DIRECTORY_PATH : PG_FUNCTIONS_DIR . '/data/files',
        'data/layouts' => defined('LAYOUT_DIRECTORY_PATH') ? LAYOUT_DIRECTORY_PATH : PG_FUNCTIONS_DIR . '/data/layouts',
    );
}

/**
 * The root folder on disk a file place lies in and the place's part inside
 * it, or null when it is not a file place.
 *
 * @param string $place
 * @return array|null array(folder, relative)
 */
function pg_security_place_disk_parts($place)
{
    $place = pg_security_place_path($place);

    if ($place === '') {
        return null;
    }

    foreach (array_reverse(pg_security_files_roots(), true) as $prefix => $folder) {
        if (($prefix !== '') && (strpos($place, $prefix . '/') === 0)) {
            return array(rtrim($folder, '/\\'), substr($place, strlen($prefix) + 1));
        }
    }

    return array(rtrim(PG_FUNCTIONS_DIR, '/\\'), $place);
}

/**
 * The file on disk a file finding's place names (for the section's
 * Quarantine), '' when it is not a file place.
 *
 * @param string $place
 * @return string
 */
function pg_security_place_disk_path($place)
{
    $parts = pg_security_place_disk_parts($place);

    return ($parts === null) ? '' : $parts[0] . '/' . $parts[1];
}

/**
 * Is the folder a file place names on disk still the folder the walk found
 * it in: no segment of it turned into a symbolic link since? A folder
 * swapped for a link between the scan and a click would let Quarantine move
 * a file from anywhere, or Restore write one there.
 *
 * @param string $place
 * @return bool
 */
function pg_security_place_folder_real($place)
{
    $parts = pg_security_place_disk_parts($place);

    if ($parts === null) {
        return false;
    }

    $root = realpath($parts[0]);
    $slash = strrpos($parts[1], '/');
    $folder = realpath(($slash === false) ? $parts[0] : $parts[0] . '/' . substr($parts[1], 0, $slash));

    if (!is_string($root) || !is_string($folder)) {
        return false;
    }

    $expected = rtrim(str_replace('\\', '/', $root), '/') . (($slash === false) ? '' : '/' . substr($parts[1], 0, $slash));

    return str_replace('\\', '/', $folder) === $expected;
}

/**
 * The files under a folder after the cursor, in walk order, as place => path.
 * Symbolic links are not followed.
 *
 * @param string $folder Folder on disk
 * @param string $prefix Its place, '' for the software folder
 * @param string $cursor Last place done
 * @param callable|null $unread Given the place of a folder that is there but cannot be listed
 * @return Generator
 */
function pg_security_files_walk($folder, $prefix, $cursor, $unread = null)
{
    $names = @scandir($folder);

    if (!is_array($names)) {
        // A folder that is there but cannot be listed was not looked at; an
        // upload folder that does not exist has nothing in it.
        if (is_callable($unread) && is_dir($folder)) {
            $unread($prefix);
        }

        return;
    }

    sort($names, SORT_STRING);

    foreach ($names as $name) {
        if (($name === '.') || ($name === '..')) {
            continue;
        }

        $place = ($prefix === '') ? $name : $prefix . '/' . $name;
        $path = $folder . '/' . $name;

        if (is_link($path)) {
            continue;
        }

        if (is_dir($path)) {
            if (!pg_security_walk_skipped($place) && !pg_security_folder_done($place, $cursor)) {
                foreach (pg_security_files_walk($path, $place, $cursor, $unread) as $inner_place => $inner_path) {
                    yield $inner_place => $inner_path;
                }
            }

            continue;
        }

        if (($cursor === '') || (pg_security_place_compare($place, $cursor) > 0)) {
            yield $place => $path;
        }
    }
}

/**
 * Read a file in chunks and run the checks on it; a chunk keeps the end of
 * the one before it, so a mark cut in two is still found.
 *
 * @param string $path
 * @param string[] $reads From pg_security_file_reads()
 * @return array Facts for pg_security_file_sightings(), plus digest
 */
function pg_security_files_read($path, array $reads)
{
    $facts = array('shell' => '', 'php_tag' => false, 'digest' => '', 'denies_only' => false);

    if (in_array('digest', $reads, true)) {
        $digest = @sha1_file($path);
        $facts['digest'] = is_string($digest) ? $digest : '';

        // A server settings file is a few lines; one over 64 KB is never
        // only a deny.
        if (($facts['digest'] !== '') && (@filesize($path) <= 65536)) {
            $facts['denies_only'] = pg_security_config_denies_only($path, (string) @file_get_contents($path));
        }
    }

    $shell = in_array('shell', $reads, true);
    $php_tag = in_array('php_tag', $reads, true);

    if (!$shell && !$php_tag) {
        return $facts;
    }

    $handle = @fopen($path, 'rb');

    if ($handle === false) {
        return $facts;
    }

    $carry = '';
    $read = 0;

    while (!feof($handle) && ($read < PG_SECURITY_FILES_READ_MAX)) {
        $chunk = fread($handle, PG_SECURITY_FILES_READ_CHUNK);

        if (!is_string($chunk) || ($chunk === '')) {
            break;
        }

        $read += strlen($chunk);
        $text = $carry . $chunk;

        if ($shell && ($facts['shell'] === '')) {
            $facts['shell'] = pg_security_text_shell_mark($text);
        }

        if ($php_tag && !$facts['php_tag']) {
            $facts['php_tag'] = pg_security_text_has_php_tag($text);
        }

        if ((!$shell || ($facts['shell'] !== '')) && (!$php_tag || $facts['php_tag'])) {
            break;
        }

        $carry = substr($text, -512);
    }

    fclose($handle);

    return $facts;
}

/**
 * Check one file: the reads its place needs, then its sightings. A server
 * settings file also gives its digest for the run's baseline.
 *
 * @param string $place
 * @param string $path
 * @param bool|null $in_reference As for pg_security_file_reads()
 * @param array|null $baseline The last finished run's server settings digests
 * @return array digest (string), sightings (array[])
 */
function pg_security_files_check($place, $path, $in_reference, $baseline)
{
    $reads = pg_security_file_reads($place, $in_reference);
    $facts = empty($reads) ? array('shell' => '', 'php_tag' => false, 'digest' => '', 'denies_only' => false) : pg_security_files_read($path, $reads);

    if ($facts['digest'] !== '') {
        $facts['config'] = $facts['denies_only'] ? '' : pg_security_config_verdict($baseline, $place, $facts['digest']);
    }

    $sightings = pg_security_file_sightings($place, $in_reference, $facts);

    // A finding carries the file's digest, so an ignored one opens again
    // when the file changes (pg_security_finding_merge()). Only a file with
    // a finding is hashed: they are few.
    if (!empty($sightings)) {
        $digest = ($facts['digest'] !== '') ? $facts['digest'] : @sha1_file($path);

        foreach ($sightings as $index => $sighting) {
            $sightings[$index]['detail'] = pg_security_detail_with_digest($sighting['detail'], is_string($digest) ? $digest : '');
        }
    }

    return array('digest' => (string) $facts['digest'], 'sightings' => $sightings);
}

/**
 * Walk on from the state's root and cursor, checking each file, until every
 * root is done or $stop answers true before a file. The state comes back
 * with the place to go on from, so the next call resumes there.
 *
 * @param array $state From pg_security_files_state()
 * @param array<string, string> $roots From pg_security_files_roots()
 * @param array|null $places The reference's places (place => true), null when the run has none
 * @param callable $stop Asked before each file
 * @param callable $found Given each sighting
 * @return array state, stopped (bool), files (checked this call)
 */
function pg_security_files_advance(array $state, array $roots, $places, callable $stop, callable $found)
{
    $folders = array_values($roots);
    $prefixes = array_keys($roots);
    $files = 0;

    $unread = function ($place) use (&$state) {
        if (!in_array((string) $place, $state['unread'], true)) {
            $state['unread'][] = (string) $place;
        }
    };

    for ($root = (int) $state['root']; $root < count($folders); $root++) {
        foreach (pg_security_files_walk($folders[$root], $prefixes[$root], (string) $state['cursor'], $unread) as $place => $path) {
            if ($stop()) {
                return array('state' => $state, 'stopped' => true, 'files' => $files);
            }

            $check = pg_security_files_check($place, $path, is_array($places) ? isset($places[$place]) : null, $state['baseline']);

            if ($check['digest'] !== '') {
                $state['configs'][$place] = $check['digest'];
            }

            foreach ($check['sightings'] as $sighting) {
                $found($sighting);
            }

            $state['cursor'] = $place;
            $state['files']++;
            $files++;
        }

        $state['root'] = $root + 1;
        $state['cursor'] = '';
    }

    return array('state' => $state, 'stopped' => false, 'files' => $files);
}

/**
 * Write a sighting: a new row, or the stored one merged.
 *
 * @param array $sighting
 * @param int $now
 * @return void
 */
function pg_security_finding_record(array $sighting, $now)
{
    $stored = db_item("SELECT * FROM security_finding WHERE fingerprint = '" . e($sighting['fingerprint']) . "'");
    $row = pg_security_finding_merge(is_array($stored) ? $stored : null, $sighting, $now);

    if (is_array($stored)) {
        db_q(
            "UPDATE security_finding SET severity = ?, state = ?, detail = ?, last_seen = ? WHERE id = ?",
            array((int) $row['severity'], (int) $row['state'], (string) $row['detail'], (int) $row['last_seen'], (int) $stored['id'])
        );

        return;
    }

    db_q(
        "INSERT INTO security_finding (kind, place, fingerprint, severity, state, detail, note, acted_by, first_seen, last_seen, acted_at)
        VALUES (?, ?, ?, ?, ?, ?, '', 0, ?, ?, 0)
        ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)",
        array($row['kind'], $row['place'], $row['fingerprint'], (int) $row['severity'], (int) $row['state'], (string) $row['detail'], (int) $row['first_seen'], (int) $row['last_seen'])
    );
}

/**
 * End a full run: what the run did not see becomes gone (a quarantined
 * finding stays quarantined, pg_security_state_unseen()), but for the places
 * under a folder it could not list, and gone rows past their 90 days are
 * removed.
 *
 * @param int $started When the run started
 * @param string $reference The run's reference source
 * @param int $now
 * @param string[] $unread Folder places the walk could not list
 * @return void
 */
function pg_security_files_close($started, $reference, $now, array $unread = array())
{
    pg_security_scan_close_kinds(pg_security_files_judged_kinds($reference), (int) $started, $unread);

    db_q(
        "DELETE FROM security_finding WHERE state = ? AND last_seen < ?",
        array(PG_SECURITY_STATE_GONE, (int) $now - PG_SECURITY_GONE_KEEP)
    );
}

/**
 * Work on the file scan for at most $budget seconds, from where the last run
 * stopped.
 *
 * @param int $budget Seconds
 * @return array done (bool), files (checked this call), findings (sightings written)
 */
function pg_security_files_run($budget)
{
    $result = array('done' => false, 'files' => 0, 'findings' => 0);

    if (!pg_security_finding_ready()) {
        $result['done'] = true;

        return $result;
    }

    $state = pg_security_files_state(json_decode(pg_config_text(PG_SECURITY_FILES_STATE), true));
    $now = time();
    $deadline = microtime(true) + max(1, (int) $budget);

    $reference = pg_security_files_reference();

    if ((int) $state['started'] === 0) {
        $state['started'] = $now;
        $state['root'] = 0;
        $state['cursor'] = '';
        $state['files'] = 0;
        $state['configs'] = array();
        $state['unread'] = array();
        $state['reference'] = $reference['source'];
    }

    // A pass that started without a reference, or lost it on the way, does
    // not judge by one: its unknown-PHP and shell findings are not marked
    // gone either (pg_security_files_judged_kinds()).
    $has_reference = ($reference['source'] !== '') && ($state['reference'] !== '');
    $walk = pg_security_files_advance(
        $state,
        pg_security_files_roots(),
        $has_reference ? $reference['places'] : null,
        function () use ($deadline) {
            return microtime(true) >= $deadline;
        },
        function (array $sighting) use ($now, &$result) {
            pg_security_finding_record($sighting, $now);
            $result['findings']++;
        }
    );
    $state = $walk['state'];
    $stopped = $walk['stopped'];
    $result['files'] = $walk['files'];

    if (!$stopped) {
        pg_security_files_close((int) $state['started'], $has_reference ? $state['reference'] : '', $now, $state['unread']);

        // The first finished run sets the baseline; later ones only drop the
        // files that are gone, so a changed file stays reported until a
        // person deals with it.
        if ($state['baseline'] === null) {
            $state['baseline'] = $state['configs'];
        } else {
            $state['baseline'] = array_intersect_key($state['baseline'], $state['configs']);
        }

        $state['last_finished'] = $now;
        $state['last_files'] = (int) $state['files'];
        $state['last_reference'] = $has_reference ? (string) $state['reference'] : '';
        $state['started'] = 0;
        $state['root'] = 0;
        $state['cursor'] = '';
        $state['files'] = 0;
        $state['configs'] = array();
        $state['unread'] = array();
        $result['done'] = true;
    }

    pg_config_text_save(PG_SECURITY_FILES_STATE, json_encode($state));

    return $result;
}
