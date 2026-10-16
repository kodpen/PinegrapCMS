<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Restoring a database dump (data/backups/<folder>/sql.sql) in steps.
 *
 * The installation screen used to drop the site's tables and then run the
 * whole dump inside one request. A large backup outlives the time limits of
 * the web server and of any proxy in front of it, and a request that was cut
 * short left the site with half of its tables.
 *
 * Here the dump is loaded next to the site instead: every table statement is
 * pointed at pgr_new_<table>, and each step runs statements for a few seconds,
 * stops on a statement boundary and records the byte offset it reached in
 * data/temp/restore.json. The site keeps running on its own tables meanwhile.
 * When the last statement has run, one RENAME TABLE puts every loaded table in
 * place at once and moves the tables it replaces to pgr_old_<table>, which are
 * dropped right after.
 *
 * A connection does not outlive a request, so what a step leaves open is
 * closed at its end and opened again by the next one: the session variables
 * the dump set (SET NAMES, autocommit, unique and foreign key checks) are
 * replayed in order, an open transaction is committed and a LOCK TABLES is
 * released and taken again.
 *
 * A step the server killed is noticed by the next one (the state still says
 * "running"): every pgr_new_ table that is not on the recorded list is dropped,
 * the table that was being loaded is dropped too, and the dump is read again
 * from that table's CREATE TABLE, so no row is inserted twice on either
 * engine. Later steps get half the time.
 *
 * Only statements that cannot reach the site's own tables are run: table
 * statements are renamed, session SETs, LOCK/UNLOCK TABLES, transaction
 * control and plain SELECT/PREPARE/EXECUTE run as they are, USE and
 * CREATE/DROP DATABASE are skipped, and anything else stops the restore with
 * the statement, before the site has changed.
 *
 * A backup can also be an archive (data/backups/<name>.zip, what the automatic
 * backup writes). Its sql.sql, files/ and layouts/ are extracted first, in
 * steps of their own, into data/temp/restore_<id>, and every file is checked
 * against the archive's checksum before anything is loaded, so a damaged
 * archive stops the restore while the site is still untouched. The working
 * folder goes with the restore's record.
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
 * The prefix of the tables a restore loads into.
 *
 * @return string
 */
function pg_restore_prefix_new()
{
    return 'pgr_new_';
}


/**
 * The prefix the replaced tables carry between the swap and their DROP.
 *
 * @return string
 */
function pg_restore_prefix_old()
{
    return 'pgr_old_';
}


/**
 * Seconds a step runs statements before it hands back to the browser. Far
 * below the 60–100 seconds a web server or proxy gives a request.
 *
 * @return int
 */
function pg_restore_step_seconds()
{
    return 20;
}


/**
 * Where the progress of the restore is kept.
 *
 * @return string
 */
function pg_restore_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/restore.json';
}


/**
 * The dump of a backup folder.
 *
 * @param string $folder
 * @return string
 */
function pg_restore_dump_path($folder)
{
    return PG_FUNCTIONS_DIR . '/data/backups/' . $folder . '/sql.sql';
}


/**
 * Whether a backup is an archive (data/backups/<name>.zip) rather than a
 * folder.
 *
 * @param string $folder the backup's name in data/backups
 * @return bool
 */
function pg_restore_is_archive($folder)
{
    return (is_string($folder) && (strtolower(substr($folder, -4)) === '.zip'));
}


/**
 * The file a restore starts from, which has to stay the same while it runs:
 * the archive itself, or the dump of a folder.
 *
 * @param string $folder
 * @return string
 */
function pg_restore_source_path($folder)
{
    return pg_restore_is_archive($folder) ? PG_FUNCTIONS_DIR . '/data/backups/' . $folder : pg_restore_dump_path($folder);
}


/**
 * Where an archive is extracted while it is restored.
 *
 * @param string $id the restore's id
 * @return string
 */
function pg_restore_work_path($id)
{
    return PG_FUNCTIONS_DIR . '/data/temp/restore_' . $id;
}


/**
 * The folder the restore reads sql.sql, files/ and layouts/ from: the backup
 * folder, or the folder an archive was extracted into.
 *
 * @param array $state
 * @return string
 */
function pg_restore_content_path(array $state)
{
    if (!empty($state['archive'])) {
        return pg_restore_work_path($state['id']);
    }

    return PG_FUNCTIONS_DIR . '/data/backups/' . $state['folder'];
}


/**
 * Deletes the working folders that archives were extracted into.
 *
 * @param string $id only this restore's folder; '' for every one
 */
function pg_restore_work_delete($id = '')
{
    $temp = PG_FUNCTIONS_DIR . '/data/temp';

    $entries = is_dir($temp) ? @scandir($temp) : false;

    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ((preg_match('/^restore_([a-f0-9]{16})$/', $entry, $match) === 1) && (($id === '') || ($match[1] === $id)) && is_dir($temp . '/' . $entry)) {
            pg_backup_delete_tree($temp . '/' . $entry);
        }
    }
}


/**
 * What a backup archive holds for a restore.
 *
 * sql.sql sits at the top of the archive (the automatic backup, an archive
 * the Backup Manager downloads) or in one folder (a backup folder zipped by
 * hand), the way the installation screen's upload takes it. The restore
 * extracts sql.sql and the files directly under files/ and layouts/ next to
 * it, which is what the installation copies; everything else is left in the
 * archive, and so is a name that could point outside the working folder.
 *
 * @param string $path
 * @return array ok, error, entries (each: index in the archive, name below
 *               the backup, size, crc), size (their uncompressed total) and
 *               folders (files and layouts, when the archive has them)
 */
function pg_restore_archive_read($path)
{
    $answer = array('ok' => false, 'error' => '', 'entries' => array(), 'size' => 0, 'folders' => array());

    if (!class_exists('ZipArchive')) {
        $answer['error'] = lang('Zip support is not available on this server, so the archive cannot be extracted.');
        return $answer;
    }

    $zip = new ZipArchive();

    if (!is_file($path) || (@$zip->open($path) !== true)) {
        $answer['error'] = lang('The archive could not be opened. It might be damaged.');
        return $answer;
    }

    $stats = array();
    $root = null;

    for ($index = 0; $index < $zip->numFiles; $index++) {

        $stat = $zip->statIndex($index);

        if ($stat === false) {
            continue;
        }

        $stat['name'] = str_replace('\\', '/', $stat['name']);
        $stats[$index] = $stat;

        if ($stat['name'] === 'sql.sql') {
            $root = '';
        } elseif (($root === null) && (preg_match('/^([^\/]+)\/sql\.sql$/', $stat['name'], $match) === 1)) {
            $root = $match[1] . '/';
        }
    }

    $zip->close();

    if ($root === null) {
        $answer['error'] = lang('The archive has no sql.sql, so it is not the backup of a site.');
        return $answer;
    }

    foreach ($stats as $index => $stat) {

        if (($root !== '') && (strpos($stat['name'], $root) !== 0)) {
            continue;
        }

        $name = (string) substr($stat['name'], strlen($root));

        if (preg_match('/^(files|layouts)\/(.*)$/s', $name, $match) === 1) {

            $answer['folders'][$match[1]] = true;

            // The folder itself, what sits in a folder below it, and a name
            // Windows would read as a device, a stream or another folder.
            if (($match[2] === '') || ($match[2] === '.') || ($match[2] === '..') || (preg_match('/[\x00-\x1f\/\\\\:*?"<>|]/', $match[2]) === 1)) {
                continue;
            }

        } elseif ($name !== 'sql.sql') {
            continue;
        }

        $answer['entries'][] = array('index' => $index, 'name' => $name, 'size' => (float) $stat['size'], 'crc' => (int) $stat['crc']);
        $answer['size'] += (float) $stat['size'];
    }

    $answer['folders'] = array_keys($answer['folders']);
    $answer['ok'] = true;

    return $answer;
}


/**
 * Copies one file of an open archive to disk a megabyte at a time, so a dump
 * of any size is extracted within a few megabytes of memory, and checks it
 * against its size and checksum in the archive. A file that does not come out
 * whole is deleted again.
 *
 * @param ZipArchive $zip
 * @param int        $index the file's index in the archive
 * @param string     $path  where it is written
 * @return string '' when the file is complete, otherwise what went wrong
 */
function pg_restore_archive_copy($zip, $index, $path)
{
    $stat = $zip->statIndex($index);
    $name = ($stat === false) ? '#' . $index : str_replace('\\', '/', $stat['name']);
    $damaged = lang(array('string' => 'The archive is damaged: {var:1} does not match its checksum.', 'vars' => array($name)));

    if ($stat === false) {
        return $damaged;
    }

    $stream = method_exists($zip, 'getStreamIndex') ? $zip->getStreamIndex($index) : $zip->getStream($zip->getNameIndex($index));

    if ($stream === false) {
        return $damaged;
    }

    $handle = @fopen($path, 'wb');

    if ($handle === false) {
        fclose($stream);
        return lang('The file could not be written.');
    }

    $crc = hash_init('crc32b');
    $written = 0;
    $error = '';

    while (true) {

        $chunk = @stream_get_contents($stream, 1048576);

        if (($chunk === false) || ($chunk === '')) {
            break;
        }

        if (fwrite($handle, $chunk) !== strlen($chunk)) {
            $error = lang('The file could not be written.');
            break;
        }

        hash_update($crc, $chunk);
        $written += strlen($chunk);
    }

    fclose($stream);

    if (!fclose($handle) && ($error === '')) {
        $error = lang('The file could not be written.');
    }

    if (($error === '') && (((float) $written !== (float) $stat['size']) || (hash_final($crc) !== sprintf('%08x', $stat['crc'] & 0xFFFFFFFF)))) {
        $error = $damaged;
    }

    if ($error !== '') {
        @unlink($path);
    }

    return $error;
}


/**
 * Splits off what a statement starts with: whitespace, plain comments and an
 * executable comment opener (/*!40101 or MariaDB's /*M!100100).
 *
 * @param string $sql
 * @return array position of the first keyword, and whether it sits inside an
 *               executable comment
 */
function pg_restore_statement_start($sql)
{
    $length = strlen($sql);
    $position = 0;
    $executable = false;

    while ($position < $length) {

        if (ctype_space($sql[$position])) {
            $position++;
            continue;
        }

        // A line comment ("-- " or "#") runs to the end of its line.
        if (preg_match('/\G(--(\s|$)|#)/', $sql, $match, 0, $position)) {
            $end = strpos($sql, "\n", $position);
            $position = ($end === false) ? $length : $end + 1;
            continue;
        }

        if (substr($sql, $position, 2) !== '/*') {
            break;
        }

        // An executable comment runs on every server from that version on.
        // MariaDB's dump opens with /*M!999999\- enable the sandbox mode */,
        // a version no server reaches: that one is only a comment.
        if (preg_match('/\G\/\*M?!(\d*)\s*/', $sql, $match, 0, $position) && (($match[1] === '') || ((int) $match[1] < 900000))) {
            $position += strlen($match[0]);
            $executable = true;
            break;
        }

        $end = strpos($sql, '*/', $position + 2);

        if ($end === false) {
            $position = $length;
            break;
        }

        $position = $end + 2;
    }

    return array('position' => $position, 'executable' => $executable);
}


/**
 * What a dump statement is, and for a table statement where its table name
 * sits.
 *
 * kind: 'table' (renamed into the restore's tables), 'lock' (LOCK TABLES,
 * renamed and remembered), 'unlock', 'set' (session variable, replayed by the
 * next step), 'transaction', 'plain' (run as it is), 'skip' (not run) or
 * 'other' (not allowed: it could change the site's own tables).
 *
 * @param string $sql one complete statement
 * @return array kind, verb, table, position and length of the table name as
 *               written (backticks included)
 */
function pg_restore_classify($sql)
{
    $result = array('kind' => 'other', 'verb' => '', 'table' => '', 'position' => 0, 'length' => 0);

    $start = pg_restore_statement_start($sql);
    $position = $start['position'];
    $rest = (string) substr($sql, $position);

    // Nothing but comments (and the closing of an executable one).
    if (preg_match('/^(\*\/)?\s*;?\s*$/', $rest)) {
        $result['kind'] = 'skip';
        return $result;
    }

    if (preg_match('/^SET\s/i', $rest)) {
        // Server-wide variables (GTID_PURGED in a dump of a server with GTIDs)
        // and the binary log switch need privileges a site's user does not
        // have, and none of them belongs to the site's data.
        $result['kind'] = preg_match('/@@GLOBAL\.|^SET\s+(GLOBAL|PERSIST)\s|SQL_LOG_BIN/i', $rest) ? 'skip' : 'set';
        return $result;
    }

    if (preg_match('/^UNLOCK\s+TABLES\b/i', $rest)) {
        $result['kind'] = 'unlock';
        return $result;
    }

    if (preg_match('/^(COMMIT|ROLLBACK|BEGIN|START\s+TRANSACTION)\b/i', $rest)) {
        $result['kind'] = 'transaction';
        return $result;
    }

    if (preg_match('/^(SELECT|PREPARE|EXECUTE|DEALLOCATE|DO)\b/i', $rest)) {
        $result['kind'] = 'plain';
        return $result;
    }

    // The restore always loads into the database the site is connected to.
    if (preg_match('/^(USE|CREATE\s+DATABASE|CREATE\s+SCHEMA|DROP\s+DATABASE|DROP\s+SCHEMA)\b/i', $rest)) {
        $result['kind'] = 'skip';
        return $result;
    }

    $verbs = array(
        'lock' => '/^LOCK\s+TABLES?\s+/i',
        'drop' => '/^DROP\s+TABLE(\s+IF\s+EXISTS)?\s+/i',
        'create' => '/^CREATE\s+TABLE(\s+IF\s+NOT\s+EXISTS)?\s+/i',
        'alter' => '/^ALTER\s+TABLE\s+/i',
        'insert' => '/^INSERT(\s+(LOW_PRIORITY|DELAYED|HIGH_PRIORITY))?(\s+IGNORE)?(\s+INTO)?\s+/i',
        'replace' => '/^REPLACE(\s+(LOW_PRIORITY|DELAYED))?(\s+INTO)?\s+/i',
        'truncate' => '/^TRUNCATE(\s+TABLE)?\s+/i',
    );

    foreach ($verbs as $verb => $pattern) {

        if (!preg_match($pattern, $rest, $match)) {
            continue;
        }

        $offset = strlen($match[0]);

        if (preg_match('/\G`((?:[^`]|``)+)`/', $rest, $name, 0, $offset)) {
            $table = str_replace('``', '`', $name[1]);
        } elseif (preg_match('/\G([A-Za-z0-9_$]+)/', $rest, $name, 0, $offset)) {
            $table = $name[1];
        } else {
            return $result;
        }

        $after = ltrim((string) substr($rest, $offset + strlen($name[0])));

        // A name with a database in front of it, or a second table in the
        // same statement, would reach a table this restore does not rename.
        if ((substr($after, 0, 1) === '.') || ((($verb === 'drop') || ($verb === 'truncate')) && (substr($after, 0, 1) === ','))) {
            return $result;
        }

        if (($verb === 'lock') && !preg_match('/^(READ(\s+LOCAL)?|(LOW_PRIORITY\s+)?WRITE)\s*(\*\/\s*)?;?\s*$/i', $after)) {
            return $result;
        }

        $result['kind'] = ($verb === 'lock') ? 'lock' : 'table';
        $result['verb'] = $verb;
        $result['table'] = $table;
        $result['position'] = $position + $offset;
        $result['length'] = strlen($name[0]);

        // A dump taken while another restore was running carries that
        // restore's tables; they are not part of the site.
        if ((strpos($table, pg_restore_prefix_new()) === 0) || (strpos($table, pg_restore_prefix_old()) === 0)) {
            $result['kind'] = 'skip';
        }

        return $result;
    }

    return $result;
}


/**
 * The statement with its table name replaced by the restore's table.
 *
 * @param string $sql
 * @param array  $classified pg_restore_classify() of $sql
 * @param string $prefix
 * @return string
 */
function pg_restore_rename_statement($sql, array $classified, $prefix)
{
    if (($classified['kind'] !== 'table') && ($classified['kind'] !== 'lock')) {
        return $sql;
    }

    return substr($sql, 0, $classified['position'])
        . '`' . str_replace('`', '``', $prefix . $classified['table']) . '`'
        . substr($sql, $classified['position'] + $classified['length']);
}


/**
 * Remembers what the next step has to set up again: session variables in the
 * order they were last set, and the LOCK TABLES that is still held.
 *
 * @param array  $state
 * @param string $kind pg_restore_classify() kind
 * @param string $sql  the statement as it ran
 * @return array
 */
function pg_restore_track(array $state, $kind, $sql)
{
    $sql = trim($sql);

    if ($kind === 'set') {

        $sets = array();

        foreach ((array) $state['sets'] as $set) {
            if ($set !== $sql) {
                $sets[] = $set;
            }
        }

        $sets[] = $sql;

        // A dump sets the same handful of variables over and over, so the
        // list stays short; the cap only guards against an unusual dump.
        $state['sets'] = array_slice($sets, -64);

    } elseif ($kind === 'lock') {

        $state['lock'] = $sql;

    } elseif ($kind === 'unlock') {

        $state['lock'] = '';
    }

    return $state;
}


/**
 * The progress of the restore, or null when none is recorded.
 *
 * @return array|null
 */
function pg_restore_state_read()
{
    $path = pg_restore_state_path();

    if (!is_file($path)) {
        return null;
    }

    $state = json_decode((string) @file_get_contents($path), true);

    if (!is_array($state) || !isset($state['id'], $state['folder'], $state['phase'], $state['offset'])) {
        return null;
    }

    return $state;
}


/**
 * @param array $state
 * @return bool
 */
function pg_restore_state_write(array $state)
{
    $path = pg_restore_state_path();

    if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0755, true)) {
        return false;
    }

    $state['updated'] = time();

    $temp = $path . '.' . substr(md5(uniqid('', true)), 0, 8);

    if (@file_put_contents($temp, json_encode($state), LOCK_EX) === false) {
        return false;
    }

    if (!@rename($temp, $path)) {
        // Windows will not rename over an existing file.
        @unlink($path);

        if (!@rename($temp, $path)) {
            @unlink($temp);
            return false;
        }
    }

    return true;
}


/**
 * Forgets the restore, and the folder its archive was extracted into.
 */
function pg_restore_state_clear()
{
    $state = pg_restore_state_read();

    if (($state !== null) && !empty($state['archive'])) {
        pg_restore_work_delete($state['id']);
    }

    @unlink(pg_restore_state_path());
}


/**
 * A restore that was started and has not put its tables in place yet.
 *
 * @return array|null
 */
function pg_restore_unfinished()
{
    $state = pg_restore_state_read();

    if (($state === null) || !in_array($state['phase'], array('extract', 'load', 'loaded'), true)) {
        return null;
    }

    return $state;
}


/**
 * How far the load has come, 0–100.
 *
 * @param array $state
 * @return int
 */
function pg_restore_percent(array $state)
{
    if (empty($state['size'])) {
        return 0;
    }

    return (int) min(100, floor(($state['offset'] / $state['size']) * 100));
}


/**
 * One line for the screen: "43 % · 412 MB / 950 MB".
 *
 * @param array $state
 * @return string
 */
function pg_restore_progress_text(array $state)
{
    return lang(array(
        'string' => '{var:1} % · {var:2} / {var:3}',
        'vars' => array(
            pg_restore_percent($state),
            convert_bytes_to_string((float) $state['offset'], 1),
            convert_bytes_to_string((float) $state['size'], 1),
        ),
    ));
}


/**
 * Whether a posted restore belongs to the recorded one: same id, same
 * folder, the same file as when it started (the archive, for an archive) and
 * the same database.
 *
 * @param array|null $state
 * @param string     $id
 * @param string     $folder
 * @param string     $database
 * @param string     $host
 * @return bool
 */
function pg_restore_matches($state, $id, $folder, $database, $host)
{
    if (!is_array($state) || ($id === '') || ($state['id'] !== $id) || ($state['folder'] !== $folder)) {
        return false;
    }

    if (($state['database'] !== $database) || ($state['host'] !== $host)) {
        return false;
    }

    // Once the tables are swapped the file no longer matters.
    if ($state['phase'] === 'swapped') {
        return true;
    }

    $file = pg_restore_source_path($folder);

    clearstatcache(true, $file);

    // A record written before archives could be restored names the dump's
    // size and time only.
    $size = isset($state['source_size']) ? $state['source_size'] : $state['size'];
    $mtime = isset($state['source_mtime']) ? $state['source_mtime'] : $state['mtime'];

    return (is_file($file) && ((float) filesize($file) === (float) $size) && ((int) filemtime($file) === (int) $mtime));
}


/**
 * The tables of this database whose name starts with the prefix, without it.
 *
 * @param string $prefix
 * @return array
 */
function pg_restore_tables($prefix)
{
    $tables = array();

    $like = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $prefix) . '%';

    $result = @mysqli_query(db::$con, "SHOW TABLES LIKE '" . mysqli_real_escape_string(db::$con, $like) . "'");

    if ($result) {
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = substr($row[0], strlen($prefix));
        }
    }

    return $tables;
}


/**
 * Drops the tables of an unfinished restore (and the replaced tables a swap
 * did not get to drop).
 *
 * @return int tables dropped
 */
function pg_restore_drop_tables()
{
    $dropped = 0;

    foreach (array(pg_restore_prefix_new(), pg_restore_prefix_old()) as $prefix) {
        foreach (pg_restore_tables($prefix) as $table) {
            if (@mysqli_query(db::$con, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $prefix . $table) . '`')) {
                $dropped++;
            }
        }
    }

    return $dropped;
}


/**
 * Forgets an unfinished restore and drops what it loaded. Once the tables
 * are swapped only the leftovers of the swap are dropped.
 *
 * @return int tables dropped
 */
function pg_restore_discard()
{
    $dropped = pg_restore_drop_tables();

    pg_restore_state_clear();

    return $dropped;
}


/**
 * Starts a restore of a backup folder or archive: drops what an earlier
 * restore left behind and records a new one. An archive starts with its
 * extraction; size and offset count the bytes extracted until the dump is
 * loaded.
 *
 * @param string $folder
 * @param string $database
 * @param string $host
 * @return array the new state; 'dropped' says how many leftover tables went
 */
function pg_restore_begin($folder, $database, $host)
{
    $dropped = pg_restore_drop_tables();

    pg_restore_work_delete();

    $archive = pg_restore_is_archive($folder);

    $file = pg_restore_source_path($folder);

    clearstatcache(true, $file);

    $size = (float) @filesize($file);

    if ($archive) {
        $contents = pg_restore_archive_read($file);
        $size = $contents['size'];
    }

    $state = array(
        'id' => bin2hex(random_bytes(8)),
        'folder' => $folder,
        'archive' => $archive,
        'source_size' => (float) @filesize($file),
        'source_mtime' => (int) @filemtime($file),
        'size' => $size,
        'mtime' => (int) @filemtime($file),
        'database' => $database,
        'host' => $host,
        'phase' => $archive ? 'extract' : 'load',
        'extract_index' => 0,
        'extract_written' => 0,
        'offset' => 0,
        'statements' => 0,
        'sets' => array(),
        'lock' => '',
        'table' => '',
        'table_offset' => 0,
        'tables' => array(),
        'running' => false,
        'steps' => 0,
        'recovered' => 0,
        'step_seconds' => pg_restore_step_seconds(),
        'started' => time(),
        'updated' => time(),
        'dropped' => $dropped,
    );

    pg_restore_state_write($state);

    return $state;
}


/**
 * Runs one statement of the dump against the restore's tables.
 *
 * @param array  $state            updated: tables, current table, sets, lock
 * @param string $sql
 * @param int    $statement_offset where the statement starts in the file
 * @return true|array true, or the statement and the error
 */
function pg_restore_run_statement(array &$state, $sql, $statement_offset)
{
    $classified = pg_restore_classify($sql);

    $short = preg_replace('/\s+/', ' ', trim(mb_substr($sql, 0, 160)));

    if ($classified['kind'] === 'skip') {
        return true;
    }

    if ($classified['kind'] === 'other') {
        return array('statement' => $short, 'error' => lang('A restore does not run this statement, because it could change the tables of the site before the backup is in place.'));
    }

    if (($classified['table'] !== '') && (strlen(pg_restore_prefix_new() . $classified['table']) > 64)) {
        return array('statement' => $short, 'error' => lang(array('string' => 'The table name {var:1} is too long to be loaded next to the tables of the site.', 'vars' => array($classified['table']))));
    }

    $run = trim(pg_restore_rename_statement($sql, $classified, pg_restore_prefix_new()));

    if ($classified['verb'] === 'create') {
        $state['table'] = $classified['table'];
        $state['table_offset'] = $statement_offset;
    }

    if (!@mysqli_query(db::$con, $run)) {
        return array('statement' => $short, 'error' => mysqli_error(db::$con));
    }

    if ($classified['verb'] === 'create') {
        if (!in_array($classified['table'], $state['tables'], true)) {
            $state['tables'][] = $classified['table'];
        }
    } elseif ($classified['verb'] === 'drop') {
        $state['tables'] = array_values(array_diff($state['tables'], array($classified['table'])));
    }

    $state = pg_restore_track($state, $classified['kind'], $run);

    return true;
}


/**
 * The session variables a dump changes, as the connection has them now.
 *
 * @return array name => value ('' when the server could not tell)
 */
function pg_restore_session_save()
{
    $names = array('autocommit', 'sql_mode', 'time_zone', 'character_set_client', 'character_set_results', 'collation_connection', 'unique_checks', 'foreign_key_checks');

    $result = @mysqli_query(db::$con, 'SELECT @@SESSION.' . implode(', @@SESSION.', $names));

    $row = $result ? mysqli_fetch_row($result) : null;

    return $row ? array_combine($names, $row) : array();
}


/**
 * Gives the connection back the session variables it had before the step:
 * what the rest of the request writes (the log line of a failure among it)
 * must not run inside the dump's transaction or with its character set.
 *
 * @param array $saved pg_restore_session_save()
 */
function pg_restore_session_restore(array $saved)
{
    $sets = array();

    foreach ($saved as $name => $value) {
        // The switches (autocommit, the checks) refuse a quoted '1'.
        if ($value === null) {
            $sets[] = $name . ' = NULL';
        } elseif (ctype_digit((string) $value)) {
            $sets[] = $name . ' = ' . $value;
        } else {
            $sets[] = $name . " = '" . mysqli_real_escape_string(db::$con, $value) . "'";
        }
    }

    if ($sets) {
        @mysqli_query(db::$con, 'SET SESSION ' . implode(', ', $sets));
    }
}


/**
 * Puts the database back where the last clean step left it, after a step the
 * server killed: drops the tables the recorded list does not know and the
 * table that was being loaded, and rewinds to that table's CREATE TABLE.
 *
 * @param array $state
 * @return string the table that is loaded again ('' when none)
 */
function pg_restore_recover(array &$state)
{
    foreach (pg_restore_tables(pg_restore_prefix_new()) as $table) {
        if (!in_array($table, $state['tables'], true)) {
            @mysqli_query(db::$con, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', pg_restore_prefix_new() . $table) . '`');
        }
    }

    $table = (string) $state['table'];

    if ($table !== '') {
        @mysqli_query(db::$con, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', pg_restore_prefix_new() . $table) . '`');
        $state['tables'] = array_values(array_diff($state['tables'], array($table)));
        $state['offset'] = (int) $state['table_offset'];
    }

    $state['lock'] = '';
    $state['recovered']++;
    $state['step_seconds'] = max(4, (int) floor($state['step_seconds'] / 2));

    return $table;
}


/**
 * Extracts the archive into the restore's working folder for up to the
 * state's step seconds, from where the last step stopped.
 *
 * A file a step did not finish is written on from the byte the state
 * recorded; the archive is read again up to there (a compressed file cannot
 * be entered half-way), so the checksum covers the whole file and a step the
 * server killed half-way through a write loses nothing. That reading does not
 * count against the step's writing: after it the step still writes for at
 * least half its seconds, so a very large dump keeps moving. Every file is
 * checked against its size and checksum in the archive when it is complete.
 * The site's tables are not touched here.
 *
 * @param array         $state     updated and written; once everything is
 *                                 extracted the phase is 'load', and size and
 *                                 offset count the dump
 * @param callable|null $heartbeat called with the state about once a second
 * @return array status 'more', 'done' or 'error'; error and statement on
 *               error; recovered: true when the last step was killed
 */
function pg_restore_extract_step(array &$state, $heartbeat = null)
{
    $answer = array('status' => 'error', 'error' => '', 'statement' => $state['folder'], 'recovered' => false);

    $contents = pg_restore_archive_read(pg_restore_source_path($state['folder']));

    if (!$contents['ok']) {
        $answer['error'] = $contents['error'];
        return $answer;
    }

    $work = pg_restore_work_path($state['id']);

    // The whole archive's room is asked for before the first byte, so a full
    // disk does not stop the restore half-way through a file.
    if (((int) $state['extract_index'] === 0) && ((float) $state['extract_written'] === 0.0)) {

        $free = function_exists('disk_free_space') ? @disk_free_space(PG_FUNCTIONS_DIR . '/data') : false;

        if (($free !== false) && ($free < ($contents['size'] + 67108864))) {
            $answer['error'] = lang(array('string' => 'There is not enough free disk space to extract the archive: {var:1} is needed, {var:2} is free.', 'vars' => array(convert_bytes_to_string($contents['size'] + 67108864, 1), convert_bytes_to_string((float) $free, 1))));
            return $answer;
        }
    }

    if (!empty($state['running'])) {
        $answer['recovered'] = true;
        $state['recovered']++;
        $state['step_seconds'] = max(4, (int) floor($state['step_seconds'] / 2));
    }

    $state['running'] = true;
    $state['steps']++;
    pg_restore_state_write($state);

    foreach (array_merge(array(''), $contents['folders']) as $folder) {
        if (!is_dir($work . '/' . $folder) && !@mkdir($work . '/' . $folder, 0755, true)) {
            $answer['statement'] = 'data/temp/' . basename($work) . '/' . $folder;
            $answer['error'] = lang('The folder could not be created.');
            return $answer;
        }
    }

    // The working folder is closed to the web like the backups folder is.
    @file_put_contents($work . '/.htaccess', 'deny from all');

    $zip = new ZipArchive();

    if ($zip->open(pg_restore_source_path($state['folder'])) !== true) {
        $answer['error'] = lang('The archive could not be opened. It might be damaged.');
        return $answer;
    }

    $deadline = microtime(true) + (int) $state['step_seconds'];
    $next_beat = microtime(true) + 1;
    $finished = true;
    $count = count($contents['entries']);

    while ((int) $state['extract_index'] < $count) {

        $entry = $contents['entries'][(int) $state['extract_index']];
        $written = (float) $state['extract_written'];
        $damaged = lang(array('string' => 'The archive is damaged: {var:1} does not match its checksum.', 'vars' => array($entry['name'])));

        $answer['statement'] = $state['folder'] . ' › ' . $entry['name'];

        $stream = method_exists($zip, 'getStreamIndex') ? $zip->getStreamIndex($entry['index']) : $zip->getStream($zip->getNameIndex($entry['index']));
        $handle = @fopen($work . '/' . $entry['name'], 'c+b');

        if (($stream === false) || ($handle === false)) {
            if ($stream !== false) {
                fclose($stream);
            }
            $zip->close();
            $answer['error'] = ($stream === false) ? $damaged : lang('The file could not be written.');
            return $answer;
        }

        $crc = hash_init('crc32b');

        // What an earlier step wrote is read again for the checksum.
        $skip = $written;

        while ($skip > 0) {

            $chunk = @stream_get_contents($stream, (int) min(1048576, $skip));

            if (($chunk === false) || ($chunk === '')) {
                break;
            }

            hash_update($crc, $chunk);
            $skip -= strlen($chunk);
        }

        ftruncate($handle, (int) $written);
        fseek($handle, (int) $written);

        if ($written > 0) {
            $deadline = max($deadline, microtime(true) + max(1, (int) floor($state['step_seconds'] / 2)));
        }

        $stopped = false;

        while ($skip <= 0) {

            $chunk = @stream_get_contents($stream, 1048576);

            if (($chunk === false) || ($chunk === '')) {
                break;
            }

            if (fwrite($handle, $chunk) !== strlen($chunk)) {
                fclose($stream);
                fclose($handle);
                $zip->close();
                $answer['error'] = lang('The file could not be written.');
                return $answer;
            }

            hash_update($crc, $chunk);
            $written += strlen($chunk);
            $state['offset'] += strlen($chunk);

            $now = microtime(true);

            if (($heartbeat !== null) && ($now >= $next_beat)) {
                call_user_func($heartbeat, $state);
                $next_beat = $now + 1;
            }

            if (($now >= $deadline) && ($written < $entry['size'])) {
                $stopped = true;
                break;
            }
        }

        fclose($stream);
        fclose($handle);

        if ($stopped) {
            $state['extract_written'] = $written;
            $finished = false;
            break;
        }

        if (($skip > 0) || ($written !== $entry['size']) || (hash_final($crc) !== sprintf('%08x', $entry['crc'] & 0xFFFFFFFF))) {
            $zip->close();
            $answer['error'] = $damaged;
            return $answer;
        }

        $state['extract_index']++;
        $state['extract_written'] = 0;

        if (microtime(true) >= $deadline) {
            $finished = ((int) $state['extract_index'] >= $count);
            break;
        }
    }

    $zip->close();

    if ($finished) {

        $dump = $work . '/sql.sql';

        clearstatcache(true, $dump);

        $state['phase'] = 'load';
        $state['offset'] = 0;
        $state['size'] = (float) @filesize($dump);
        $state['extracted'] = $count;
    }

    $state['running'] = false;
    pg_restore_state_write($state);

    $answer['status'] = $finished ? 'done' : 'more';
    $answer['statement'] = '';

    return $answer;
}


/**
 * Loads the dump for up to the state's step seconds, from where the last step
 * stopped.
 *
 * The caller holds the restore lock (pg_restore_lock()).
 *
 * @param array         $state     updated and written
 * @param callable|null $heartbeat called with the state about once a second
 * @return array status 'more', 'done' or 'error'; error and statement on
 *               error; recovered: the table a killed step made load again
 */
function pg_restore_step(array &$state, $heartbeat = null)
{
    $answer = array('status' => 'error', 'error' => '', 'statement' => '', 'recovered' => null);

    $handle = @fopen(pg_restore_content_path($state) . '/sql.sql', 'rb');

    if ($handle === false) {
        $answer['statement'] = $state['folder'] . '/sql.sql';
        $answer['error'] = lang('The file could not be opened.');
        return $answer;
    }

    if (!empty($state['running'])) {
        $answer['recovered'] = pg_restore_recover($state);
    }

    $state['running'] = true;
    $state['steps']++;
    pg_restore_state_write($state);

    if (fseek($handle, (int) $state['offset']) !== 0) {
        fclose($handle);
        $answer['error'] = lang('The file could not be opened.');
        return $answer;
    }

    $session = pg_restore_session_save();

    // What the dump set before this step, in the order it was set.
    foreach ((array) $state['sets'] as $set) {
        @mysqli_query(db::$con, $set);
    }

    if (($state['lock'] !== '') && !@mysqli_query(db::$con, $state['lock'])) {
        fclose($handle);
        $answer['statement'] = $state['lock'];
        $answer['error'] = mysqli_error(db::$con);
        pg_restore_session_restore($session);
        return $answer;
    }

    $deadline = microtime(true) + (int) $state['step_seconds'];
    $next_beat = microtime(true) + 1;
    $statement_offset = (int) $state['offset'];
    $query = '';
    $finished = true;

    while (($line = fgets($handle)) !== false) {

        $trimmed = trim($line);

        if (($trimmed === '') || (substr($trimmed, 0, 2) === '--') || (substr($trimmed, 0, 1) === '#')) {
            if ($query === '') {
                $statement_offset = ftell($handle);
            }
            continue;
        }

        $query .= $line;

        if (!preg_match('/;\s*$/', $line)) {
            continue;
        }

        $result = pg_restore_run_statement($state, $query, $statement_offset);

        if ($result !== true) {
            fclose($handle);
            @mysqli_query(db::$con, 'ROLLBACK');
            @mysqli_query(db::$con, 'UNLOCK TABLES');
            pg_restore_session_restore($session);
            $answer['statement'] = $result['statement'];
            $answer['error'] = $result['error'];
            return $answer;
        }

        $query = '';
        $state['statements']++;
        $state['offset'] = ftell($handle);
        $statement_offset = $state['offset'];

        $now = microtime(true);

        if (($heartbeat !== null) && ($now >= $next_beat)) {
            call_user_func($heartbeat, $state);
            $next_beat = $now + 1;
        }

        if ($now >= $deadline) {
            $finished = false;
            break;
        }
    }

    fclose($handle);

    // A transaction does not outlive the connection: what this step inserted
    // is committed now, and the next step opens its own.
    @mysqli_query(db::$con, 'COMMIT');

    if ($state['lock'] !== '') {
        @mysqli_query(db::$con, 'UNLOCK TABLES');
    }

    // Dumps written by mysqldump.php switch autocommit off for every table
    // and never back on; the rest of the request writes too.
    pg_restore_session_restore($session);

    if ($finished) {
        $state['phase'] = 'loaded';
        $state['lock'] = '';
    }

    $state['running'] = false;
    pg_restore_state_write($state);

    $answer['status'] = $finished ? 'done' : 'more';

    return $answer;
}


/**
 * Takes the restore's lock for this database, without waiting.
 *
 * @param string $database
 * @return bool
 */
function pg_restore_lock($database)
{
    $result = @mysqli_query(db::$con, "SELECT GET_LOCK('" . mysqli_real_escape_string(db::$con, 'pg_restore_' . md5($database)) . "', 0)");

    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_row($result);

    return ($row && ((string) $row[0] === '1'));
}


/**
 * Puts the loaded tables in place of the site's in one RENAME TABLE, then
 * drops the replaced tables and the software's tables the backup does not
 * have (the upgrade that follows creates those again).
 *
 * @param array $state         set to 'swapped' once the rename is through
 * @param array $system_tables every table the software creates
 * @return array ok, error, retry (the rename failed and can be tried again),
 *               tables (loaded), removed (old tables dropped)
 */
function pg_restore_swap(array &$state, array $system_tables)
{
    $answer = array('ok' => false, 'error' => '', 'retry' => false, 'tables' => 0, 'removed' => 0);

    $new = pg_restore_prefix_new();
    $old = pg_restore_prefix_old();

    if ($state['phase'] === 'loaded') {

        $loaded = pg_restore_tables($new);

        // Without its settings row the site would not start, so a dump
        // without the config table is not taken for a site backup.
        if (!in_array('config', $loaded, true)) {
            $answer['error'] = lang('The backup has no config table, so it is not the backup of a site.');
            return $answer;
        }

        $live = array();

        $result = mysqli_query(db::$con, 'SHOW TABLES');

        while ($result && ($row = mysqli_fetch_row($result))) {
            $live[$row[0]] = true;
        }

        foreach (pg_restore_tables($old) as $table) {
            @mysqli_query(db::$con, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $old . $table) . '`');
        }

        $renames = array();

        foreach ($loaded as $table) {
            $quoted = str_replace('`', '``', $table);

            if (isset($live[$table])) {
                $renames[] = '`' . $quoted . '` TO `' . str_replace('`', '``', $old . $table) . '`';
            }

            $renames[] = '`' . str_replace('`', '``', $new . $table) . '` TO `' . $quoted . '`';
        }

        // The rename waits for every query of the site that holds one of
        // these tables; a query that does not let go stops it with an error
        // instead of leaving the site waiting behind it.
        @mysqli_query(db::$con, 'SET SESSION lock_wait_timeout = 60');

        // RENAME TABLE renames all of them or none, so a failure leaves the
        // site as it was and the loaded tables ready for another try.
        if (!mysqli_query(db::$con, 'RENAME TABLE ' . implode(', ', $renames))) {
            $answer['error'] = mysqli_error(db::$con);
            $answer['retry'] = true;
            return $answer;
        }

        $state['phase'] = 'swapped';
        $state['swapped_tables'] = $loaded;
        pg_restore_state_write($state);

        foreach ($system_tables as $table) {
            if (isset($live[$table]) && !in_array($table, $loaded, true)) {
                if (@mysqli_query(db::$con, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`')) {
                    $answer['removed']++;
                }
            }
        }
    }

    foreach (pg_restore_tables($old) as $table) {
        if (@mysqli_query(db::$con, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $old . $table) . '`')) {
            $answer['removed']++;
        }
    }

    $answer['ok'] = true;
    $answer['tables'] = isset($state['swapped_tables']) ? count($state['swapped_tables']) : 0;

    return $answer;
}
