<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Where the bytes of a files row are: on the web server's disk under
 * data/files (storage 'local', every row until the switch is on) or in the
 * S3-compatible object store (storage 's3', the object named by
 * storage_key). An upload is always written locally; the general job moves
 * it to the store afterwards (pg_file_move_run()), so an upload never waits
 * on the network. A reader that needs a path on the disk asks
 * pg_file_local_copy(), which answers the local file or a copy fetched into
 * data/cache/objects; the general job keeps that cache under its size cap
 * (pg_file_cache_sweep()).
 *
 * Nothing moves while the switch (config_text object_storage_files) is off,
 * which is how every site starts.
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
 * Where a row's bytes are. A row read before the migration, or with a value
 * this code does not know, is local.
 *
 * @param array $row a files row (storage may be missing)
 * @return string 'local' or 's3'
 */
function pg_file_storage(array $row)
{
    return (isset($row['storage']) && ($row['storage'] === 's3') && !empty($row['storage_key'])) ? 's3' : 'local';
}


/**
 * Whether files.storage and files.storage_key are there (11.0.152).
 *
 * @return bool
 */
function pg_file_storage_ready()
{
    return pg_schema_has('files', 'storage') && pg_schema_has('files', 'storage_key');
}


/**
 * Whether uploaded files go to the object store: the switch, the columns
 * and a store that can take requests.
 *
 * @param array|null $config pg_os_settings(); read when null
 * @return bool
 */
function pg_file_storage_on($config = null)
{
    if ((pg_config_text('object_storage_files') !== '1') || !pg_file_storage_ready()) {
        return false;
    }

    return pg_os_ready(is_array($config) ? $config : pg_os_settings());
}


/**
 * The object key of a file, below the store's prefix. The id keeps two
 * files apart whatever their names; the name keeps the bucket readable. The
 * key is stored in the row, so a later rename does not move the object.
 *
 * @param int $id
 * @param string $name
 * @return string
 */
function pg_file_storage_key($id, $name)
{
    $name = str_replace(array('/', '\\'), '_', trim((string) $name));

    return 'files/' . (int) $id . '/' . (($name !== '') ? $name : 'file');
}


/**
 * Whether the move job may take a row. Design and theme files stay on the
 * disk: the designer, the code editors and the theme screens read and write
 * them by path all the time. The DKIM key stays (pg_file_withheld()).
 *
 * @param array $row id, name, design, theme
 * @return bool
 */
function pg_file_storage_movable(array $row)
{
    $name = isset($row['name']) ? trim((string) $row['name']) : '';

    if (($name === '') || ($name !== pg_basename($name)) || pg_file_withheld($name)) {
        return false;
    }

    return empty($row['design']) && empty($row['theme']);
}


/**
 * How many times a file is tried before the job stops trying and names it
 * in the Site Log.
 *
 * @return int
 */
function pg_file_move_attempts()
{
    return 3;
}


/**
 * The move job's state, read from config_text object_storage_move and made
 * whole: cursor (the id the walk continues below; 0 starts at the newest
 * file), failures (id => failed attempts) and rested_at (when the last pass
 * finished).
 *
 * @param mixed $value the stored JSON, decoded
 * @return array
 */
function pg_file_move_state_normalize($value)
{
    $value = is_array($value) ? $value : array();
    $failures = array();

    if (isset($value['failures']) && is_array($value['failures'])) {
        foreach ($value['failures'] as $id => $count) {
            if (((int) $id > 0) && ((int) $count > 0)) {
                $failures[(int) $id] = (int) $count;
            }
        }
    }

    // A long list of files that never move would grow the setting without
    // end; the oldest entries go, and those files are tried afresh.
    if (count($failures) > 500) {
        $failures = array_slice($failures, -500, null, true);
    }

    return array(
        'cursor'    => isset($value['cursor']) ? max(0, (int) $value['cursor']) : 0,
        'failures'  => $failures,
        'rested_at' => isset($value['rested_at']) ? max(0, (int) $value['rested_at']) : 0,
    );
}


/**
 * Whether a tick has work: a pass is under way, or the last one finished
 * long enough ago that new uploads may wait.
 *
 * @param array $state pg_file_move_state_normalize()
 * @param int $now
 * @return bool
 */
function pg_file_move_due(array $state, $now)
{
    return ($state['cursor'] > 0) || (((int) $now - $state['rested_at']) >= 600);
}


/**
 * The state after a tick. The cursor moves below every row the tick read,
 * moved or not, so a failing file never holds the walk up; a file that
 * failed is tried again on the next pass until it reaches the attempt
 * limit. A tick that found no more rows below the ones it handled ends the
 * pass.
 *
 * @param array $state pg_file_move_state_normalize()
 * @param array $outcomes id => true (moved), false (failed) or null (left alone)
 * @param bool $finished nothing is left below the handled rows
 * @param int $now
 * @return array state, given_up (ids that reached the limit with this tick)
 */
function pg_file_move_next_state(array $state, array $outcomes, $finished, $now)
{
    $given_up = array();

    foreach ($outcomes as $id => $outcome) {
        $id = (int) $id;

        if ($outcome === true) {
            unset($state['failures'][$id]);
        } elseif ($outcome === false) {
            $count = (isset($state['failures'][$id]) ? $state['failures'][$id] : 0) + 1;
            $state['failures'][$id] = $count;

            if ($count === pg_file_move_attempts()) {
                $given_up[] = $id;
            }
        }
    }

    if ($finished || (count($outcomes) === 0)) {
        $state['cursor'] = 0;
        $state['rested_at'] = (int) $now;
    } else {
        $state['cursor'] = min(array_map('intval', array_keys($outcomes)));
    }

    return array('state' => $state, 'given_up' => $given_up);
}


/**
 * Whether a file reached the attempt limit and is left on the disk.
 *
 * @param array $state
 * @param int $id
 * @return bool
 */
function pg_file_move_given_up(array $state, $id)
{
    return isset($state['failures'][(int) $id]) && ($state['failures'][(int) $id] >= pg_file_move_attempts());
}


/**
 * Moves one local file to the store: send, check by HEAD that the object
 * has the file's size, mark the row, then delete the local bytes. A file
 * that changed or was renamed while it was sent stays local and the object
 * is deleted again.
 *
 * @param array $config pg_os_settings()
 * @param array $row id, name, type
 * @return array ok, bytes; or pg_os_fail()
 */
function pg_file_move_one(array $config, array $row)
{
    $id = (int) $row['id'];
    $name = (string) $row['name'];
    $path = FILE_DIRECTORY_PATH . '/' . $name;

    clearstatcache(true, $path);

    $size = @filesize($path);
    $mtime = @filemtime($path);

    if (($size === false) || !is_file($path)) {
        return pg_os_fail('missing', lang(array('string' => 'The file {var:1} is not on the disk.', 'vars' => array($name))));
    }

    $key = pg_file_storage_key($id, $name);
    $type = isset($row['type']) ? strtolower((string) $row['type']) : '';

    $put = pg_os_put($config, $key, $path, array('content_type' => get_mime_type($type)));

    if (empty($put['ok'])) {
        return $put;
    }

    $head = pg_os_head($config, $key);

    if (empty($head['ok'])) {
        return $head;
    }

    if ((int) $head['size'] !== (int) $size) {
        pg_os_delete($config, $key);

        return pg_os_fail('size_mismatch', lang(array('string' => 'The stored copy of {var:1} does not have the size of the file.', 'vars' => array($name))));
    }

    clearstatcache(true, $path);

    if ((@filesize($path) !== $size) || (@filemtime($path) !== $mtime)) {
        pg_os_delete($config, $key);

        return pg_os_fail('changed', lang(array('string' => 'The file {var:1} changed while it was sent.', 'vars' => array($name))));
    }

    $marked = db_q(
        "UPDATE files SET storage = 's3', storage_key = ? WHERE id = ? AND name = ? AND storage = 'local'",
        array($key, $id, $name)
    );

    if (!$marked || ((int) mysqli_affected_rows(db::$con) !== 1)) {
        pg_os_delete($config, $key);

        return pg_os_fail('changed', lang(array('string' => 'The file {var:1} changed while it was sent.', 'vars' => array($name))));
    }

    // A copy left in the cache by an earlier stored life of this id is stale.
    pg_file_cache_forget($id);

    if (!@unlink($path)) {
        // The row already points at the store, which has the same bytes; the
        // local file is only room that a later clean-up can give back.
        log_activity(lang(array('string' => 'The file {var:1} was moved to the object store, but its copy on the disk could not be deleted.', 'vars' => array($name))), 'UNKNOWN');
    }

    return array('ok' => true, 'bytes' => (int) $size);
}


/**
 * One tick of the move job: the newest local files first, at most $limit of
 * them and $seconds of work, the place kept in config_text between ticks.
 *
 * @param int $limit
 * @param int $seconds
 * @return array moved, bytes, failed (this tick), given_up (names)
 */
function pg_file_move_run($limit = 20, $seconds = 20)
{
    $outcome = array('moved' => 0, 'bytes' => 0, 'failed' => 0, 'given_up' => array());
    $config = pg_os_settings();

    if (!pg_file_storage_on($config) || !defined('FILE_DIRECTORY_PATH')) {
        return $outcome;
    }

    $state = pg_file_move_state_normalize(json_decode(pg_config_text('object_storage_move'), true));

    if (!pg_file_move_due($state, time())) {
        return $outcome;
    }

    $limit = max(1, (int) $limit);
    $sql = "SELECT id, name, type, design, theme FROM files WHERE storage = 'local'"
        . (($state['cursor'] > 0) ? " AND id < " . (int) $state['cursor'] : '')
        . " ORDER BY id DESC LIMIT " . $limit;

    $rows = db_items($sql);

    if (!is_array($rows)) {
        return $outcome;
    }

    $started = microtime(true);
    $outcomes = array();
    $errors = array();
    $names = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $names[$id] = (string) $row['name'];

        if (!pg_file_storage_movable($row) || pg_file_move_given_up($state, $id)) {
            $outcomes[$id] = null;
            continue;
        }

        // Out of time: the rest of this page is read again on the next tick.
        if ((microtime(true) - $started) >= $seconds) {
            break;
        }

        $result = pg_file_move_one($config, $row);

        if (!empty($result['ok'])) {
            $outcomes[$id] = true;
            $outcome['moved']++;
            $outcome['bytes'] += $result['bytes'];
        } else {
            $outcomes[$id] = false;
            $outcome['failed']++;
            $errors[$id] = isset($result['message']) ? (string) $result['message'] : '';
        }
    }

    // The pass is over when the page was the last one and the clock let
    // every row of it through; stopped early, the walk continues below the
    // last row handled.
    $finished = (count($rows) < $limit) && (count($outcomes) === count($rows));

    if ((count($outcomes) === 0) && !$finished) {
        return $outcome;
    }

    $next = pg_file_move_next_state($state, $outcomes, $finished, time());

    pg_config_text_save('object_storage_move', json_encode($next['state']));

    foreach ($next['given_up'] as $id) {
        $outcome['given_up'][] = $names[$id];

        log_activity(lang(array(
            'string' => 'The file {var:1} could not be moved to the object store after {var:2} attempts and stays on the disk: {var:3}',
            'vars'   => array($names[$id], pg_file_move_attempts(), $errors[$id]),
        )), 'UNKNOWN');
    }

    return $outcome;
}


/**
 * The folder of the copies fetched for readers that need a path.
 *
 * @return string
 */
function pg_file_cache_directory()
{
    return defined('FILE_DIRECTORY_PATH') ? dirname(FILE_DIRECTORY_PATH) . '/cache/objects' : '';
}


/**
 * The cache path of a stored file: its id and its type, so a reader that
 * looks at the extension (office, PDF) still sees it.
 *
 * @param int $id
 * @param string $type files.type
 * @return string
 */
function pg_file_cache_name($id, $type)
{
    $type = preg_replace('/[^a-z0-9]/', '', strtolower((string) $type));

    return (int) $id . (($type !== '') ? '.' . $type : '');
}


/**
 * Deletes the cached copy of a file, whatever its type.
 *
 * @param int $id
 * @return void
 */
function pg_file_cache_forget($id)
{
    $directory = pg_file_cache_directory();

    if (($directory === '') || !is_dir($directory)) {
        return;
    }

    // No GLOB_BRACE: musl-based systems (Alpine images) do not have it.
    $paths = array_merge(array($directory . '/' . (int) $id), (array) @glob($directory . '/' . (int) $id . '.*'));

    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}


/**
 * A path on the disk holding a file's bytes, for a reader that cannot work
 * from an address: the local file, or for a stored file a copy fetched into
 * data/cache/objects (kept and reused while its size matches the row).
 *
 * @param int $file_id
 * @return string '' when the row is missing or the bytes cannot be had
 */
function pg_file_local_copy($file_id)
{
    if (!defined('FILE_DIRECTORY_PATH')) {
        return '';
    }

    $columns = pg_file_storage_ready() ? 'id, name, type, size, storage, storage_key' : 'id, name, type, size';
    $row = db_item("SELECT " . $columns . " FROM files WHERE id = '" . (int) $file_id . "'");

    if (!is_array($row) || !isset($row['name'])) {
        return '';
    }

    if (pg_file_storage($row) === 'local') {
        $path = FILE_DIRECTORY_PATH . '/' . $row['name'];

        return is_file($path) ? $path : '';
    }

    $directory = pg_file_cache_directory();
    $path = $directory . '/' . pg_file_cache_name($row['id'], $row['type']);

    clearstatcache(true, $path);

    if (is_file($path) && ((int) @filesize($path) === (int) $row['size'])) {
        // The modification time is the last use the sweep goes by.
        @touch($path);

        return $path;
    }

    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return '';
    }

    $config = pg_os_settings();

    if (!pg_os_ready($config)) {
        return '';
    }

    // Fetched beside the copy and renamed into place, so a reader never
    // opens a half-written file.
    $partial = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.part';
    $got = pg_os_get($config, $row['storage_key'], $partial);

    if (empty($got['ok'])) {
        return '';
    }

    if (!@rename($partial, $path)) {
        @unlink($partial);

        return '';
    }

    return $path;
}


/**
 * How many bytes the copies may take together before the oldest used go.
 *
 * @return int
 */
function pg_file_cache_cap()
{
    return 1073741824;
}


/**
 * Which copies to delete: those not used for a week, then the least
 * recently used until the rest fit under the cap. A '.part' file older
 * than a day is a fetch that died. Pure.
 *
 * @param array $entries path => array(size, mtime)
 * @param int $cap bytes
 * @param int $now
 * @return array paths to delete
 */
function pg_file_cache_sweep_pick(array $entries, $cap, $now)
{
    $delete = array();
    $kept = array();
    $total = 0;

    foreach ($entries as $path => $entry) {
        $age = (int) $now - (int) $entry[1];

        if (substr($path, -5) === '.part') {
            if ($age > 86400) {
                $delete[] = $path;
            }

            continue;
        }

        if ($age > 604800) {
            $delete[] = $path;
            continue;
        }

        $kept[$path] = (int) $entry[1];
        $total += (int) $entry[0];
    }

    asort($kept);

    foreach ($kept as $path => $mtime) {
        if ($total <= $cap) {
            break;
        }

        $delete[] = $path;
        $total -= (int) $entries[$path][0];
    }

    return $delete;
}


/**
 * The general job's sweep of the copies: once an hour, a stamp file keeps
 * the time.
 *
 * @return int bytes freed
 */
function pg_file_cache_sweep()
{
    $directory = pg_file_cache_directory();

    if (($directory === '') || !is_dir($directory)) {
        return 0;
    }

    $stamp = $directory . '/.swept';
    $now = time();

    if (is_file($stamp) && (($now - (int) @filemtime($stamp)) < 3600)) {
        return 0;
    }

    @touch($stamp);

    $entries = array();

    foreach ((array) @scandir($directory) as $entry) {
        $path = $directory . '/' . $entry;

        if (($entry === '') || ($entry[0] === '.') || !is_file($path)) {
            continue;
        }

        $entries[$path] = array((int) @filesize($path), (int) @filemtime($path));
    }

    $freed = 0;

    foreach (pg_file_cache_sweep_pick($entries, pg_file_cache_cap(), $now) as $path) {
        if (@unlink($path)) {
            $freed += $entries[$path][0];
        }
    }

    return $freed;
}
