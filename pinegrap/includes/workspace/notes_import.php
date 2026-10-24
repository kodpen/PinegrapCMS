<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - notes brought in from other note apps, in three steps:
 *
 *   1. The upload (one .zip, Markdown and text files, or Evernote exports,
 *      which notes_enex.php lays out as such files) is staged under
 *      data/temp and read through: what every file would become is shown to
 *      the person before anything is written (ws_note_import_plan()).
 *   2. The files they keep ticked are written a batch at a time: a new note
 *      keeps the dates its file carries, and the files its text links to go
 *      to the note's own folder (ws_note_import_batch()).
 *   3. Links between the notes ([[Name]], a link to another .md) are pointed
 *      at the notes they became, and the stage is removed
 *      (ws_note_import_finish()).
 *
 * A file exported from here (pinegrap_note in its front matter, see
 * notes_io.php) knows the note it came from. Unchanged, it is left alone;
 * changed, it brings that note up to date when the person may edit it, and
 * every line it did not change stays as the note had it, tags included. The
 * text conversions are in notes_io.php; the screen's endpoint is
 * workspace_notes_import.php.
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
 * The largest upload in bytes, before the server's own limit.
 */
define('WS_NOTE_IMPORT_UPLOAD_MAX', 104857600);

/**
 * How many note files one upload is read for, and how many entries a zip may
 * hold at all.
 */
define('WS_NOTE_IMPORT_NOTES_MAX', 5000);
define('WS_NOTE_IMPORT_ENTRIES_MAX', 50000);

/**
 * How many bytes of linked files one import may put in the file manager.
 */
define('WS_NOTE_IMPORT_FILES_BYTES', 536870912);

/**
 * How long a stage nobody came back for is kept, and how long one batch
 * writes before it answers (seconds).
 */
define('WS_NOTE_IMPORT_STAGE_AGE', 86400);
define('WS_NOTE_IMPORT_SECONDS', 8);

/**
 * The largest upload: WS_NOTE_IMPORT_UPLOAD_MAX, or what the server takes
 * when that is less.
 *
 * @return int bytes
 */
function ws_note_import_upload_max()
{
    $max = WS_NOTE_IMPORT_UPLOAD_MAX;

    if (function_exists('pg_upload_limits')) {
        $limits = pg_upload_limits();
        $server = (int) ($limits['file_max'] ?? 0);

        if (($server > 0) && ($server < $max)) {
            $max = $server;
        }
    }

    return $max;
}

/**
 * How many loose files one upload may carry: what the server takes in one
 * request (max_file_uploads drops the rest without a word), at most 500.
 *
 * @return int
 */
function ws_note_import_files_max()
{
    $limits = function_exists('pg_upload_limits') ? pg_upload_limits() : array();
    $max = (int) ($limits['max_files'] ?? 0);

    return ($max > 0) ? min($max, 500) : 500;
}

/* ---------------------------------------------------------------------------
   The stage: data/temp/ws_notes_import_<token>/ holds source.zip (or files/,
   the Markdown and text files uploaded on their own) and stage.json, which
   lists what the upload holds, what each note file would become, and how far
   the import has got.
   --------------------------------------------------------------------------- */

/**
 * @return string data/temp, made when missing; '' when it cannot be written
 */
function ws_note_import_temp_dir()
{
    $directory = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    return (is_dir($directory) && is_writable($directory)) ? $directory : '';
}

/**
 * The folder of a stage. The token is checked against its shape before it
 * goes anywhere near a path.
 *
 * @param string $token
 * @return string '' for a token that is not one of ours
 */
function ws_note_import_stage_dir($token)
{
    if (preg_match('/^[a-f0-9]{32}$/', (string) $token) !== 1) {
        return '';
    }

    $directory = ws_note_import_temp_dir();

    return ($directory !== '') ? $directory . '/ws_notes_import_' . $token : '';
}

/**
 * The zip of a stage, opened once a request; closed (to be removed) with
 * $close.
 *
 * @param string $path
 * @param bool   $close
 * @return ZipArchive|false
 */
function ws_note_import_zip($path, $close = false)
{
    static $open = array();

    if ($close) {
        if (!empty($open[$path])) {
            $open[$path]->close();
        }

        unset($open[$path]);

        return false;
    }

    if (!isset($open[$path])) {
        $archive = class_exists('ZipArchive') ? new ZipArchive() : null;
        $open[$path] = ($archive && ($archive->open($path) === true)) ? $archive : false;
    }

    return $open[$path];
}

/**
 * Removes a stage and everything in it.
 *
 * @param string $dir
 */
function ws_note_import_remove($dir)
{
    if (($dir === '') || !is_dir($dir)) {
        return;
    }

    ws_note_import_zip($dir . '/source.zip', true);

    foreach ((array) glob($dir . '/files/*') as $file) {
        @unlink($file);
    }

    @rmdir($dir . '/files');

    foreach ((array) glob($dir . '/*') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    @rmdir($dir);
}

/**
 * Removes the stages nobody came back for.
 */
function ws_note_import_sweep()
{
    $directory = ws_note_import_temp_dir();

    if ($directory === '') {
        return;
    }

    foreach ((array) glob($directory . '/ws_notes_import_*', GLOB_ONLYDIR) as $dir) {
        $time = @filemtime($dir . '/stage.json');

        if ($time === false) {
            $time = @filemtime($dir);
        }

        if ((time() - (int) $time) > WS_NOTE_IMPORT_STAGE_AGE) {
            ws_note_import_remove($dir);
        }
    }
}

/**
 * Puts a file step of an import that failed in the error log, with what PHP
 * said about it: the screen only says that the file could not be saved.
 *
 * @param string      $step
 * @param string|null $detail what PHP said, when the step gathered it itself;
 *                            null reads error_get_last()
 * @return void
 */
function ws_note_import_log_failure($step, $detail = null)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    if ($detail === null) {
        $last = error_get_last();
        $detail = is_array($last) ? (string) $last['message'] : '';
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        'Notes import: ' . $step . ' failed' . (($detail !== '') ? ': ' . $detail : '.'),
        __FILE__,
        __LINE__,
        array()));
}

/**
 * Writes stage.json whole or not at all.
 *
 * @param array $stage
 * @return bool
 */
function ws_note_import_save($stage)
{
    $dir = (string) ($stage['dir'] ?? '');
    unset($stage['dir']);

    $json = json_encode($stage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (($dir === '') || !is_string($json)) {
        return false;
    }

    $temp = $dir . '/stage.json.' . bin2hex(random_bytes(4));

    if (@file_put_contents($temp, $json) === false) {
        ws_note_import_log_failure('writing stage.json');

        return false;
    }

    $why = '';

    if (!ws_note_import_replace($temp, $dir . '/stage.json', $json, $why)) {
        ws_note_import_log_failure('putting stage.json in place', $why);
        @unlink($temp);

        return false;
    }

    return true;
}

/**
 * Puts $temp, written beside $path with $bytes in it, in the place of $path.
 *
 * rename() comes first: a reader then never finds half a file. On Windows a
 * file only just written can be held for a moment by another program - a
 * sync client such as OneDrive, a virus scanner - and renaming or replacing
 * it is refused while it is held. The upload writes stage.json twice within
 * a second, so the second write meets the first one still held. The bytes
 * are then written into $path itself, the way data/config.php is written
 * when rename() fails (update.php), and on Windows both ways are tried again
 * for about three seconds.
 *
 * @param string     $temp
 * @param string     $path
 * @param string     $bytes what $temp holds
 * @param string     $why   set to what PHP said about the last try that failed
 * @param int[]|null $waits milliseconds to wait before each try after the
 *                          first; null: about three seconds of them on
 *                          Windows, none elsewhere
 * @return bool whether $path holds $bytes
 */
function ws_note_import_replace($temp, $path, $bytes, &$why = '', $waits = null)
{
    if ($waits === null) {
        $waits = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? array(100, 250, 500, 1000, 1500) : array();
    }

    $bytes = (string) $bytes;
    $said = function ($way) {
        $last = error_get_last();

        return $way . ': ' . (is_array($last) ? (string) $last['message'] : 'no message');
    };

    foreach (array_merge(array(0), array_map('intval', (array) $waits)) as $wait) {
        if ($wait > 0) {
            usleep($wait * 1000);
        }

        error_clear_last();

        if (@rename($temp, $path)) {
            $why = '';

            return true;
        }

        $why = $said('rename');
        error_clear_last();

        if (@file_put_contents($path, $bytes) === strlen($bytes)) {
            @unlink($temp);
            $why = '';

            return true;
        }

        $why .= '; ' . $said('writing it in place');
    }

    return false;
}

/**
 * A stage of the person's own.
 *
 * @param array  $viewer
 * @param string $token
 * @return array|null
 */
function ws_note_import_load($viewer, $token)
{
    $dir = ws_note_import_stage_dir($token);

    if (($dir === '') || !is_file($dir . '/stage.json')) {
        return null;
    }

    $stage = json_decode((string) @file_get_contents($dir . '/stage.json'), true);

    if (!is_array($stage) || ((int) ($stage['user'] ?? 0) !== (int) $viewer['id']) || !is_array($stage['entries'] ?? null)) {
        return null;
    }

    $stage['dir'] = $dir;

    return $stage;
}

/**
 * Stages an upload: one .zip, Markdown and text files, or Evernote exports,
 * moved under data/temp with the list of what they hold. An Evernote export
 * is laid out as note and attachment files right away (notes_enex.php);
 * nothing is written as a note yet.
 *
 * @param array $viewer
 * @param array $files the upload, one entry a file: name, tmp_name, size, error
 * @return array ok, error, token
 */
function ws_note_import_stage($viewer, $files)
{
    // A failed step below is logged with error_get_last(); nothing from
    // before the upload may stand in for it.
    error_clear_last();

    $fail = function ($error, $dir = '') {
        ws_note_import_remove($dir);

        return array('ok' => false, 'error' => $error, 'token' => '');
    };

    $max = ws_note_import_upload_max();
    $too_large = lang(array('string' => 'The upload is larger than {var:1} MB.', 'vars' => max(1, (int) floor($max / 1048576))));
    $choose = lang('Choose Markdown (.md) or text (.txt) files, one .zip of them, or Evernote exports (.enex).');
    $total = 0;
    $zips = 0;
    $exports = 0;

    if (empty($files)) {
        return $fail($choose);
    }

    foreach ($files as $file) {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if (in_array($error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
            return $fail($too_large);
        }

        if (($error !== UPLOAD_ERR_OK) || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return $fail(lang('The file could not be read.'));
        }

        $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, array('md', 'markdown', 'txt', 'zip', 'enex'), true)) {
            return $fail($choose);
        }

        $zips += ($extension === 'zip') ? 1 : 0;
        $exports += ($extension === 'enex') ? 1 : 0;
        $total += (int) $file['size'];
    }

    if ($total > $max) {
        return $fail($too_large);
    }

    if (($zips > 0) && (count($files) > 1)) {
        return $fail(lang('Upload a .zip on its own, without other files.'));
    }

    if (($exports > 0) && ($exports < count($files))) {
        return $fail(lang('Upload Evernote exports (.enex) on their own, without other files.'));
    }

    if (($exports > 0) && !function_exists('xml_parser_create')) {
        return $fail(lang('This server cannot read Evernote exports (the PHP xml extension is missing).'));
    }

    if (($zips > 0) && !class_exists('ZipArchive')) {
        return $fail(lang('This server cannot open zip files (the PHP zip extension is missing). Upload the Markdown files themselves instead.'));
    }

    $token = bin2hex(random_bytes(16));
    $dir = ws_note_import_stage_dir($token);

    if (($dir === '') || !@mkdir($dir, 0755)) {
        ws_note_import_log_failure('creating the stage folder');

        return $fail(lang('The file could not be saved.'));
    }

    $entries = array();
    $files = array_values($files);

    if ($exports > 0) {
        $sources = array();

        foreach ($files as $number => $file) {
            $source = $dir . '/source-' . (int) $number . '.enex';

            if (!@move_uploaded_file($file['tmp_name'], $source)) {
                ws_note_import_log_failure('moving an uploaded Evernote export into the stage');

                return $fail(lang('The file could not be saved.'), $dir);
            }

            $sources[] = $source;
        }

        $read = ws_note_enex_stage($dir, $sources);

        if (!$read['ok']) {
            return $fail($read['error'], $dir);
        }

        $entries = $read['entries'];
    } elseif ($zips > 0) {
        $path = $dir . '/source.zip';

        if (!@move_uploaded_file($files[0]['tmp_name'], $path)) {
            ws_note_import_log_failure('moving the uploaded zip into the stage');

            return $fail(lang('The file could not be saved.'), $dir);
        }

        $archive = new ZipArchive();

        if ($archive->open($path) !== true) {
            return $fail(lang('The zip file could not be opened.'), $dir);
        }

        if ($archive->numFiles > WS_NOTE_IMPORT_ENTRIES_MAX) {
            $archive->close();

            return $fail(lang(array('string' => 'The zip holds more than {var:1} files.', 'vars' => WS_NOTE_IMPORT_ENTRIES_MAX)), $dir);
        }

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);

            if (!is_array($stat) || (substr((string) $stat['name'], -1) === '/')) {
                continue;
            }

            // The name as the zip holds it, not libzip's guess: a zip from
            // a Turkish Windows names its entries in CP857 without the
            // UTF-8 flag, and the guess (CP437) garbles ı, ş and İ.
            $name = ws_note_import_entry_name(ws_note_import_zip_name($archive->getNameIndex($index, ZipArchive::FL_ENC_RAW), $stat['name']));

            if ($name !== '') {
                $entries[] = array($name, (int) $stat['size'], (int) $stat['mtime'], $index);
            }
        }

        $archive->close();
    } else {
        if (!@mkdir($dir . '/files', 0755)) {
            ws_note_import_log_failure('creating the files folder of the stage');

            return $fail(lang('The file could not be saved.'), $dir);
        }

        foreach ($files as $number => $file) {
            if (!@move_uploaded_file($file['tmp_name'], $dir . '/files/' . (int) $number . '.txt')) {
                ws_note_import_log_failure('moving an uploaded file into the stage');

                return $fail(lang('The file could not be saved.'), $dir);
            }

            $entries[] = array(ws_note_import_entry_name(pg_basename(str_replace('\\', '/', (string) $file['name']))), (int) $file['size'], 0, (int) $number);
        }
    }

    $stage = array(
        'dir'     => $dir,
        'user'    => (int) $viewer['id'],
        'created' => time(),
        'zip'     => ($zips > 0),
        'name'    => mb_substr(ws_note_import_entry_name(pg_basename(str_replace('\\', '/', (string) $files[0]['name']))), 0, 120),
        'entries' => $entries,
    );

    if (!ws_note_import_save($stage)) {
        return $fail(lang('The file could not be saved.'), $dir);
    }

    return array('ok' => true, 'error' => '', 'token' => $token);
}

/**
 * The bytes of one entry of the upload, or false when it cannot be read or
 * is larger than $max. A zip says how big an entry is, but what it unpacks
 * to is read no further than $max either way.
 *
 * @param array $stage
 * @param int   $entry index in $stage['entries']
 * @param int   $max   bytes
 * @return string|false
 */
function ws_note_import_read($stage, $entry, $max)
{
    $row = $stage['entries'][$entry] ?? null;

    if (!is_array($row) || ((int) $row[1] > $max)) {
        return false;
    }

    if (!empty($stage['zip'])) {
        $archive = ws_note_import_zip($stage['dir'] . '/source.zip');
        $bytes = $archive ? $archive->getFromIndex((int) $row[3], $max + 1) : false;
    } else {
        $bytes = @file_get_contents($stage['dir'] . '/files/' . (int) $row[3] . '.txt', false, null, 0, $max + 1);
    }

    return (is_string($bytes) && (strlen($bytes) <= $max)) ? $bytes : false;
}

/**
 * What the upload holds, looked up the ways a link names a file.
 *
 * @param array $stage
 * @return array notes (entries read as notes), path (path => entry), lower
 *               (lower-cased path => entry), name (lower-cased file name =>
 *               entries)
 */
function ws_note_import_index($stage)
{
    $index = array('notes' => array(), 'path' => array(), 'lower' => array(), 'name' => array());

    foreach ($stage['entries'] as $entry => $row) {
        $path = (string) $row[0];

        if (!ws_note_import_visible($path)) {
            continue;
        }

        if (!isset($index['path'][$path])) {
            $index['path'][$path] = $entry;
        }

        $lower = mb_strtolower($path);

        if (!isset($index['lower'][$lower])) {
            $index['lower'][$lower] = $entry;
        }

        $index['name'][mb_strtolower(pg_basename($path))][] = $entry;

        // An attachment of an Evernote note (a fifth value) is a file, even
        // one named like a note.
        if (ws_note_import_is_note($path) && empty($row[4])) {
            $index['notes'][] = $entry;
        }
    }

    return $index;
}

/**
 * @param string $path
 * @return string the folder a path sits in, '' at the top
 */
function ws_note_import_dir_of($path)
{
    $dir = dirname((string) $path);

    return (($dir === '.') || ($dir === '/')) ? '' : $dir;
}

/**
 * The entry a path or a name stands for: the path as written, then in any
 * case, then a file of that name anywhere (the one beside the file that
 * links to it first, as Obsidian finds a file).
 *
 * @param array  $stage
 * @param array  $index ws_note_import_index()
 * @param int    $from  the entry the link is written in
 * @param string $path
 * @return int -1 when the upload has none
 */
function ws_note_import_lookup($stage, $index, $from, $path)
{
    $path = ws_note_import_path('', $path);

    if ($path === '') {
        return -1;
    }

    if (isset($index['path'][$path])) {
        return $index['path'][$path];
    }

    if (isset($index['lower'][mb_strtolower($path)])) {
        return $index['lower'][mb_strtolower($path)];
    }

    $candidates = $index['name'][mb_strtolower(pg_basename($path))] ?? array();

    if (empty($candidates)) {
        return -1;
    }

    $dir = ws_note_import_dir_of($stage['entries'][$from][0] ?? '');

    foreach ($candidates as $candidate) {
        if (ws_note_import_dir_of($stage['entries'][$candidate][0]) === $dir) {
            return $candidate;
        }
    }

    return $candidates[0];
}

/**
 * What a link of a note file points at: another note, or a file to keep with
 * the note. [[Name]] is a file when the upload has a file of that very name,
 * a note otherwise; [words](path) is a note when the path ends in .md.
 *
 * @param array $stage
 * @param array $index
 * @param int   $from
 * @param array $link ws_note_import_relink() link
 * @return array kind (note | file), entry (-1 when not in the upload)
 */
function ws_note_import_target($stage, $index, $from, $link)
{
    if ($link['kind'] === 'link') {
        $found = ws_note_import_lookup($stage, $index, $from, $link['path']);

        if (($found >= 0) && !empty($stage['entries'][$found][4])) {
            return array('kind' => 'file', 'entry' => $found);
        }

        $note = (bool) preg_match('/\.(md|markdown|txt)$/i', $link['path']);

        return array('kind' => $note ? 'note' : 'file', 'entry' => ($found >= 0 && ($note === ws_note_import_is_note($stage['entries'][$found][0]))) ? $found : -1);
    }

    $found = ws_note_import_lookup($stage, $index, $from, $link['name']);

    if ($found >= 0) {
        return array('kind' => (ws_note_import_is_note($stage['entries'][$found][0]) && empty($stage['entries'][$found][4])) ? 'note' : 'file', 'entry' => $found);
    }

    foreach (array('.md', '.markdown', '.txt') as $extension) {
        $found = ws_note_import_lookup($stage, $index, $from, $link['name'] . $extension);

        if ($found >= 0) {
            return array('kind' => 'note', 'entry' => $found);
        }
    }

    return array('kind' => 'note', 'entry' => -1);
}

/* ---------------------------------------------------------------------------
   One note file, as it would be written.
   --------------------------------------------------------------------------- */

/**
 * @return string scheme and host, as the export puts before a site link
 */
function ws_note_import_base()
{
    return (defined('URL_SCHEME') && defined('HOSTNAME_SETTING')) ? URL_SCHEME . HOSTNAME_SETTING : '';
}

/**
 * The address of a note, as a link in another note's text.
 *
 * @param int $note_id
 * @return string
 */
function ws_note_import_note_url($note_id)
{
    return ws_note_import_base() . PATH . SOFTWARE_DIRECTORY . '/workspace_notes.php?note=' . (int) $note_id;
}

/**
 * The note a file was exported from, when the person may still read it: the
 * id in its front matter, and the moment that note was made, which a note of
 * the same number on another site does not share.
 *
 * @param array $viewer
 * @param array $parsed ws_note_import_parse()
 * @return array|null the row, with access
 */
function ws_note_import_match($viewer, $parsed)
{
    if (((int) $parsed['origin'] <= 0) || ((int) $parsed['origin_created'] <= 0)) {
        return null;
    }

    $note = ws_note_for($viewer, (int) $parsed['origin'], 'view');

    return ($note && ((int) $note['created_at'] === (int) $parsed['origin_created'])) ? $note : null;
}

/**
 * Where an export put a note's files, from the folder the note's file sits
 * in: beside it (a note of one's own) or a folder up (one under Shared/).
 *
 * @param array  $note
 * @param string $dir
 * @return array[] absolute url => path in the upload, one map a place
 */
function ws_note_import_note_files($note, $dir)
{
    $maps = array();

    foreach (array_unique(array($dir, ws_note_import_dir_of($dir))) as $root) {
        $map = array();

        foreach (ws_note_export_attachments($note) as $file) {
            $map[$file['url']] = ws_note_import_path($root, $file['zip']);
        }

        $maps[] = $map;
    }

    return $maps;
}

/**
 * One note file of the upload as it would be written for this person: its
 * title, its text with links pointed from the top of the upload, its dates,
 * and, for a file exported from a note they may read, that note, the file
 * laid over it, and whether anything changed.
 *
 * @param array $viewer
 * @param array $stage
 * @param int   $entry
 * @return array ok, error, title, body, merged, created, updated, note, same
 */
function ws_note_import_entry($viewer, $stage, $entry)
{
    $path = (string) $stage['entries'][$entry][0];
    $out = array('ok' => false, 'error' => '', 'title' => ws_note_import_name_title($path), 'body' => '', 'merged' => '', 'created' => 0, 'updated' => 0, 'note' => null, 'same' => false);

    if ((int) $stage['entries'][$entry][1] > WS_NOTE_IMPORT_TEXT_MAX) {
        $out['error'] = lang('The file is too long to be a note.');

        return $out;
    }

    $bytes = ws_note_import_read($stage, $entry, WS_NOTE_IMPORT_TEXT_MAX);

    if ($bytes === false) {
        $out['error'] = lang('The file could not be read.');

        return $out;
    }

    $parsed = ws_note_import_parse(ws_note_import_text($bytes), $path, (int) $stage['entries'][$entry][2]);
    $dir = ws_note_import_dir_of($path);

    $out['title'] = $parsed['title'];
    $out['body'] = ws_note_import_root_links($parsed['body'], $dir);
    $out['created'] = $parsed['created'];
    $out['updated'] = $parsed['updated'];

    $note = ws_note_import_match($viewer, $parsed);

    if ($note) {
        $refs = ws_refs_resolve($viewer, ws_tokens((string) $note['body']));
        $merged = ws_note_import_merge($out['body'], (string) $note['body'], $refs, ws_note_import_base(), ws_note_import_note_files($note, $dir));

        $out['note'] = $note;
        $out['merged'] = $merged;
        $out['same'] = ($merged === rtrim(str_replace("\r\n", "\n", (string) $note['body'])))
            && ($parsed['title'] === ws_note_title($viewer, $note));
    }

    // A file with nothing in it but its name (Obsidian makes one for every
    // link clicked before its note was written) is not a note.
    if ((trim($out['body']) === '') && ($parsed['named'] || ($out['title'] === ''))) {
        $out['error'] = lang('The file is empty.');

        return $out;
    }

    $out['ok'] = true;

    return $out;
}

/**
 * How many files a text links to that the upload holds, and how many it
 * links to that it does not (or that a note cannot keep).
 *
 * @param array  $stage
 * @param array  $index
 * @param int    $entry
 * @param string $body
 * @return array found, missing
 */
function ws_note_import_count_files($stage, $index, $entry, $body)
{
    $found = array();
    $missing = 0;

    ws_note_import_relink($body, function ($link) use ($stage, $index, $entry, &$found, &$missing) {
        $target = ws_note_import_target($stage, $index, $entry, $link);

        if ($target['kind'] !== 'file') {
            return null;
        }

        if (($target['entry'] < 0) || ws_upload_refused($stage['entries'][$target['entry']][0])) {
            $missing++;
        } else {
            $found[$target['entry']] = true;
        }

        return null;
    });

    return array('found' => count($found), 'missing' => $missing);
}

/**
 * What every note file of a staged upload would become, for the screen to
 * show before anything is written. The rows stay in the stage for the
 * batches, which work them out again from the files: a row says what was
 * shown, not what is written.
 *
 * @param array $viewer
 * @param array $stage
 * @return array the stage with rows, capacity (notes the person may still
 *               add), more (note files past WS_NOTE_IMPORT_NOTES_MAX)
 */
function ws_note_import_plan($viewer, $stage)
{
    $index = ws_note_import_index($stage);
    $rows = array();

    foreach (array_slice($index['notes'], 0, WS_NOTE_IMPORT_NOTES_MAX) as $entry) {
        $item = ws_note_import_entry($viewer, $stage, $entry);
        $row = array('i' => $entry, 'path' => (string) $stage['entries'][$entry][0], 'title' => $item['title'], 'kind' => 'error', 'note' => 0, 'name' => '', 'error' => $item['error'], 'warnings' => array(), 'files' => 0);

        if (!$item['ok']) {
            $rows[] = $row;
            continue;
        }

        $access = $item['note'] ? (string) $item['note']['access'] : '';
        $body = $item['body'];

        if ($item['note']) {
            $row['note'] = (int) $item['note']['id'];
            $row['name'] = ws_note_title($viewer, $item['note']);
        }

        if ($item['same']) {
            $row['kind'] = 'same';
        } elseif (in_array($access, array('owner', 'edit'), true)) {
            $row['kind'] = 'update';
            $body = $item['merged'];
        } else {
            $row['kind'] = 'new';

            if ($access === 'view') {
                $row['warnings'][] = lang('A note shared with you: it is added as a note of your own.');
            }
        }

        if (mb_strlen($body) > WS_NOTE_MAX) {
            $row['kind'] = 'error';
            $row['error'] = lang(array('string' => 'A note can be at most {var:1} characters long.', 'vars' => WS_NOTE_MAX));
            $rows[] = $row;
            continue;
        }

        $files = ws_note_import_count_files($stage, $index, $entry, $body);
        $row['files'] = $files['found'];

        if ($files['missing'] > 0) {
            $row['warnings'][] = lang(array('string' => '{var:1} linked file(s) are not in the upload or cannot be kept here.', 'vars' => $files['missing']));
        }

        $rows[] = $row;
    }

    $stage['rows'] = $rows;
    $stage['capacity'] = max(0, WS_NOTE_LIMIT - (int) db_value("SELECT COUNT(*) FROM ws_notes WHERE user_id = '" . (int) $viewer['id'] . "'"));
    $stage['more'] = max(0, count($index['notes']) - WS_NOTE_IMPORT_NOTES_MAX);

    return $stage;
}

/* ---------------------------------------------------------------------------
   Writing.
   --------------------------------------------------------------------------- */

/**
 * The files a note's text links to, kept in the note's folder, and the links
 * pointed at them. For the note a file was exported from, a file its folder
 * holds already is linked again rather than kept twice.
 *
 * @param array  $viewer
 * @param array  $stage  bytes (written so far) grows
 * @param array  $index
 * @param int    $entry
 * @param array  $note   one the person may change, with id
 * @param string $body
 * @param bool   $known  the note was there before the import
 * @return string
 */
function ws_note_import_attach($viewer, &$stage, $index, $entry, $note, $body, $known)
{
    $have = array();
    $kept = array();

    if ($known) {
        foreach (ws_note_export_attachments($note) as $file) {
            $have[mb_strtolower(pg_basename($file['path']))] = $file['url'];
        }
    }

    return ws_note_import_relink($body, function ($link) use ($viewer, &$stage, $index, $entry, $note, $have, &$kept) {
        $target = ws_note_import_target($stage, $index, $entry, $link);

        if ($target['kind'] !== 'file') {
            return null;
        }

        $found = $target['entry'];
        $name = ($found >= 0) ? pg_basename((string) $stage['entries'][$found][0]) : pg_basename(($link['kind'] === 'wiki') ? $link['name'] : $link['path']);
        $label = (trim($link['label']) !== '') ? $link['label'] : $name;

        // A file that is not in the upload, or that a note cannot keep, is
        // named; a path to nowhere would only show as written.
        if ($found < 0) {
            return $label;
        }

        if (!isset($kept[$found]) && isset($have[mb_strtolower($name)])) {
            $kept[$found] = $have[mb_strtolower($name)];
        }

        if (!isset($kept[$found])) {
            if (((int) ($stage['bytes'] ?? 0) + (int) $stage['entries'][$found][1]) > WS_NOTE_IMPORT_FILES_BYTES) {
                return $label;
            }

            $bytes = ws_note_import_read($stage, $found, ws_upload_max_bytes());

            if ($bytes === false) {
                return $label;
            }

            $stored = ws_note_file_store_bytes($viewer, $note, $name, $bytes);

            if (!$stored['ok']) {
                return $label;
            }

            $stage['bytes'] = (int) ($stage['bytes'] ?? 0) + strlen($bytes);
            $kept[$found] = $stored['url'];
        }

        return '[' . $label . '](' . $kept[$found] . ')';
    });
}

/**
 * Does a text link to other notes ([[Name]], a link to an .md)?
 *
 * @param array  $stage
 * @param array  $index
 * @param int    $entry
 * @param string $body
 * @return bool
 */
function ws_note_import_links_notes($stage, $index, $entry, $body)
{
    $any = false;

    ws_note_import_relink($body, function ($link) use ($stage, $index, $entry, &$any) {
        if (ws_note_import_target($stage, $index, $entry, $link)['kind'] === 'note') {
            $any = true;
        }

        return null;
    });

    return $any;
}

/**
 * Writes one row of the plan: a new note of the person's own with the dates
 * its file carries, or the note the file was exported from brought up to
 * date (a version of it kept, as with any change). The files the text links
 * to go to the note's folder.
 *
 * @param array  $viewer
 * @param array  $stage
 * @param array  $index
 * @param array  $row
 * @param string $mode update | new (a file exported from here makes a new note)
 * @return array state (added | updated | same | failed), note_id, written
 *               (the note's updated_at after the write), links, error
 */
function ws_note_import_write($viewer, &$stage, $index, $row, $mode)
{
    $entry = (int) $row['i'];
    $item = ws_note_import_entry($viewer, $stage, $entry);
    $failed = function ($error) {
        return array('state' => 'failed', 'note_id' => 0, 'written' => 0, 'links' => false, 'error' => $error);
    };

    if (!$item['ok']) {
        return $failed($item['error']);
    }

    $access = $item['note'] ? (string) $item['note']['access'] : '';

    if ($item['note'] && $item['same'] && ($mode !== 'new')) {
        return array('state' => 'same', 'note_id' => (int) $item['note']['id'], 'written' => 0, 'links' => false, 'error' => '');
    }

    // The note it was exported from, brought up to date.
    if (($mode !== 'new') && in_array($access, array('owner', 'edit'), true)) {
        $note = $item['note'];
        $body = ws_note_import_attach($viewer, $stage, $index, $entry, $note, $item['merged'], true);
        $data = array('note_id' => (int) $note['id'], 'body' => $body);

        if ($item['title'] !== ws_note_title($viewer, $note)) {
            $data['title'] = $item['title'];
        }

        $result = ws_note_save($viewer, $data);

        if (!$result['ok']) {
            return $failed($result['error']);
        }

        return array(
            'state'   => 'updated',
            'note_id' => (int) $note['id'],
            'written' => (int) db_value("SELECT updated_at FROM ws_notes WHERE id = '" . (int) $note['id'] . "'"),
            'links'   => ws_note_import_links_notes($stage, $index, $entry, $body),
            'error'   => '',
        );
    }

    // A new note of the person's own: the checks of any new note, the limit
    // among them.
    $check = ws_note_save_check($viewer, array('note_id' => 0, 'title' => $item['title'], 'body' => $item['body']));

    if (!$check['ok']) {
        return $failed($check['error']);
    }

    $created = ($item['created'] > 0) ? (int) $item['created'] : time();
    $updated = max($created, (int) $item['updated']);
    $title = (string) $check['sets']['title'];
    $body = (string) $check['sets']['body'];

    db("INSERT INTO ws_notes (user_id, title, body, pinned, updated_by, created_at, updated_at)
        VALUES ('" . (int) $viewer['id'] . "', '" . e($title) . "', '" . e($body) . "',
            '0', '" . (int) $viewer['id'] . "', '" . $created . "', '" . $updated . "')");

    $note_id = (int) mysqli_insert_id(db::$con);

    if ($note_id <= 0) {
        return $failed(lang('The note could not be saved.'));
    }

    $note = ws_note($note_id);
    $linked = ws_note_import_attach($viewer, $stage, $index, $entry, $note, $body, false);

    // Addresses are longer than the names they replace: a note at its limit
    // keeps the names.
    if (($linked !== $body) && (mb_strlen($linked) <= WS_NOTE_MAX)) {
        db("UPDATE ws_notes SET body = '" . e($linked) . "' WHERE id = '" . $note_id . "'");
        $body = $linked;
    }

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('note', $note_id, $body, (int) $viewer['id']);
    }

    return array('state' => 'added', 'note_id' => $note_id, 'written' => $updated, 'links' => ws_note_import_links_notes($stage, $index, $entry, $body), 'error' => '');
}

/**
 * Writes the next rows of the plan, for as long as one request should take.
 * The first batch fixes what the person chose (mode, the files left out);
 * a row already written is never written twice.
 *
 * @param array  $viewer
 * @param array  $stage
 * @param string $mode
 * @param int[]  $skip entries left out
 * @return array done, next, total, counts, failed (this batch's: title, path, error)
 */
function ws_note_import_batch($viewer, &$stage, $mode, $skip)
{
    if (!isset($stage['mode'])) {
        $stage['mode'] = ($mode === 'new') ? 'new' : 'update';
        $stage['skip'] = array_values(array_unique(array_map('intval', (array) $skip)));
        $stage['next'] = 0;
        $stage['results'] = array();
        $stage['counts'] = array('added' => 0, 'updated' => 0, 'same' => 0, 'skipped' => 0, 'failed' => 0);
        $stage['bytes'] = 0;
    }

    $index = ws_note_import_index($stage);
    $skipped = array_flip($stage['skip']);
    $rows = (array) ($stage['rows'] ?? array());
    $total = count($rows);
    $next = (int) $stage['next'];
    $started = microtime(true);
    $failed = array();

    while ($next < $total) {
        if (($next > (int) $stage['next']) && ((microtime(true) - $started) > WS_NOTE_IMPORT_SECONDS)) {
            break;
        }

        $row = $rows[$next];
        $next++;

        if (isset($stage['results'][$row['i']])) {
            continue;
        }

        if (($row['kind'] === 'error') || isset($skipped[(int) $row['i']])) {
            $stage['results'][$row['i']] = array('state' => 'skipped', 'note_id' => 0, 'written' => 0, 'links' => false);
            $stage['counts']['skipped']++;
            continue;
        }

        $result = ws_note_import_write($viewer, $stage, $index, $row, $stage['mode']);

        $stage['results'][$row['i']] = array('state' => $result['state'], 'note_id' => $result['note_id'], 'written' => $result['written'], 'links' => $result['links']);
        $stage['counts'][$result['state']]++;

        if ($result['state'] === 'failed') {
            $failed[] = array('title' => $row['title'], 'path' => $row['path'], 'error' => $result['error']);
        }
    }

    $stage['next'] = $next;

    return array('done' => ($next >= $total), 'next' => $next, 'total' => $total, 'counts' => $stage['counts'], 'failed' => $failed);
}

/**
 * Points the links between the notes just written at the notes they became:
 * [[Name]] and [words](Other.md) to a note of this import, or else to a note
 * of the person's own with that title; one that finds no note reads as its
 * words. A note changed by somebody since the import wrote it is left alone.
 *
 * @param array $viewer
 * @param array $stage
 * @return array done, counts, first (the first note written, to open)
 */
function ws_note_import_finish($viewer, &$stage)
{
    $index = ws_note_import_index($stage);
    $results = (array) ($stage['results'] ?? array());
    $titles = array();
    $first = 0;

    foreach ((array) ($stage['rows'] ?? array()) as $row) {
        $note_id = (int) ($results[$row['i']]['note_id'] ?? 0);

        if ($note_id <= 0) {
            continue;
        }

        if (($first === 0) && in_array($results[$row['i']]['state'], array('added', 'updated'), true)) {
            $first = $note_id;
        }

        foreach (array($row['title'], ws_note_import_name_title($row['path'])) as $name) {
            $key = mb_strtolower(trim((string) $name));

            if (($key !== '') && !isset($titles[$key])) {
                $titles[$key] = $note_id;
            }
        }
    }

    foreach ((array) db_items("SELECT id, title FROM ws_notes WHERE user_id = '" . (int) $viewer['id'] . "' AND title <> '' ORDER BY id") as $own) {
        $key = mb_strtolower(trim((string) $own['title']));

        if (!isset($titles[$key])) {
            $titles[$key] = (int) $own['id'];
        }
    }

    $started = microtime(true);
    $stage['linked'] = (array) ($stage['linked'] ?? array());

    foreach ($results as $entry => $result) {
        if (empty($result['links']) || ((int) $result['note_id'] <= 0) || isset($stage['linked'][$entry])) {
            continue;
        }

        if ((microtime(true) - $started) > WS_NOTE_IMPORT_SECONDS) {
            return array('done' => false, 'counts' => $stage['counts'] ?? array(), 'first' => $first);
        }

        $stage['linked'][$entry] = true;
        $note = db_item("SELECT id, body, updated_at FROM ws_notes WHERE id = '" . (int) $result['note_id'] . "'");

        if (!is_array($note) || ((int) $note['updated_at'] !== (int) $result['written'])) {
            continue;
        }

        $body = ws_note_import_relink((string) $note['body'], function ($link) use ($stage, $index, $entry, $results, $titles) {
            $target = ws_note_import_target($stage, $index, (int) $entry, $link);

            if ($target['kind'] !== 'note') {
                return null;
            }

            $name = ($link['kind'] === 'wiki') ? $link['name'] : $link['path'];
            $note_id = ($target['entry'] >= 0) ? (int) ($results[$target['entry']]['note_id'] ?? 0) : 0;

            if ($note_id <= 0) {
                $note_id = (int) ($titles[mb_strtolower(ws_note_import_name_title($name))] ?? 0);
            }

            $label = (trim($link['label']) !== '') ? $link['label'] : ws_note_import_name_title($name);

            return ($note_id > 0) ? '[' . $label . '](' . ws_note_import_note_url($note_id) . ')' : $label;
        });

        if (($body !== (string) $note['body']) && (mb_strlen($body) <= WS_NOTE_MAX)) {
            db("UPDATE ws_notes SET body = '" . e($body) . "'
                WHERE id = '" . (int) $note['id'] . "' AND updated_at = '" . (int) $note['updated_at'] . "'");
        }
    }

    return array('done' => true, 'counts' => $stage['counts'] ?? array(), 'first' => $first);
}
