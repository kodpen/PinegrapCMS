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
    return pg_file_storage_running('to', $config);
}


/**
 * Whether the job walks the files in a direction now: 'to' sends local
 * files to the store, 'back' fetches stored files home. Needs the mode, the
 * columns and a store that can take requests.
 *
 * @param string $direction 'to' or 'back'
 * @param array|null $config pg_os_settings(); read when null
 * @return bool
 */
function pg_file_storage_running($direction, $config = null)
{
    $mode = pg_file_storage_mode(pg_config_text('object_storage_files'));

    if (($mode['direction'] !== $direction) || $mode['paused'] || !pg_file_storage_ready()) {
        return false;
    }

    return pg_os_ready(is_array($config) ? $config : pg_os_settings());
}


/**
 * The switch config_text object_storage_files read: '1' moves files to the
 * store, 'back' brings them home, either with '_paused' is held where it
 * is; anything else is off, which is how every site starts. Pure.
 *
 * @param mixed $value
 * @return array direction ('to', 'back' or ''), paused (bool)
 */
function pg_file_storage_mode($value)
{
    $modes = array(
        '1'           => array('direction' => 'to', 'paused' => false),
        'paused'      => array('direction' => 'to', 'paused' => true),
        'back'        => array('direction' => 'back', 'paused' => false),
        'back_paused' => array('direction' => 'back', 'paused' => true),
    );

    return isset($modes[(string) $value]) ? $modes[(string) $value] : array('direction' => '', 'paused' => false);
}


/**
 * The switch's value after a button of the Storage card. Pause and resume
 * keep the direction; a pause with nothing running, or a resume of nothing
 * paused, changes nothing. Pure.
 *
 * @param mixed $value the current value
 * @param string $action 'move', 'back', 'pause' or 'resume'
 * @return string|null the new value; null when the action does not apply
 */
function pg_file_storage_mode_after($value, $action)
{
    $mode = pg_file_storage_mode($value);

    if ($action === 'move') {
        return '1';
    }

    if ($action === 'back') {
        return 'back';
    }

    if (!in_array($action, array('pause', 'resume'), true) || ($mode['direction'] === '') || (($action === 'pause') === $mode['paused'])) {
        return null;
    }

    $value = ($mode['direction'] === 'to') ? '1' : 'back';

    return ($action === 'pause') ? (($value === '1') ? 'paused' : 'back_paused') : $value;
}


/**
 * The Storage card's buttons for the switch's value, in their order: start
 * a move, pause or resume the running walk, turn it round. Pure.
 *
 * @param mixed $value config_text object_storage_files
 * @return array actions ('move', 'back', 'pause', 'resume')
 */
function pg_file_storage_actions($value)
{
    $mode = pg_file_storage_mode($value);

    if ($mode['direction'] === '') {
        return array('move');
    }

    return array($mode['paused'] ? 'resume' : 'pause', ($mode['direction'] === 'to') ? 'back' : 'move');
}


/**
 * The config_text key holding the job's state for a direction.
 *
 * @param string $direction 'to' or 'back'
 * @return string
 */
function pg_file_move_state_key($direction)
{
    return ($direction === 'back') ? 'object_storage_back' : 'object_storage_move';
}


/**
 * Files and bytes by where they are, from the rows of a GROUP BY storage
 * query. A row with a value this code does not know counts as local. Pure.
 *
 * @param array $rows storage, files, bytes
 * @return array local_files, local_bytes, stored_files, stored_bytes
 */
function pg_file_storage_usage_sum(array $rows)
{
    $usage = array('local_files' => 0, 'local_bytes' => 0.0, 'stored_files' => 0, 'stored_bytes' => 0.0);

    foreach ($rows as $row) {
        $where = (isset($row['storage']) && ($row['storage'] === 's3')) ? 'stored' : 'local';
        $usage[$where . '_files'] += isset($row['files']) ? (int) $row['files'] : 0;
        $usage[$where . '_bytes'] += isset($row['bytes']) ? (float) $row['bytes'] : 0.0;
    }

    return $usage;
}


/**
 * Files and bytes on the disk and in the store, by the rows. Before the
 * storage columns every file is local.
 *
 * @return array pg_file_storage_usage_sum()
 */
function pg_file_storage_usage()
{
    $rows = pg_file_storage_ready()
        ? db_items("SELECT storage, COUNT(*) AS files, SUM(size) AS bytes FROM files GROUP BY storage")
        : db_items("SELECT 'local' AS storage, COUNT(*) AS files, SUM(size) AS bytes FROM files");

    return pg_file_storage_usage_sum(is_array($rows) ? $rows : array());
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
 * them by path all the time. So do style sheets and scripts, and the DKIM
 * key (pg_file_withheld()).
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

    // get_file.php rewrites {path} in a style sheet or script as it sends
    // it, which a redirect to the store would skip.
    if (in_array(strtolower((string) substr(strrchr($name, '.'), 1)), array('css', 'js'), true)) {
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

    // The size just verified is written too: pg_file_local_copy() reuses a
    // cached copy only while its size matches the row's.
    $marked = db_q(
        "UPDATE files SET storage = 's3', storage_key = ?, size = ? WHERE id = ? AND name = ? AND storage = 'local'",
        array($key, (int) $size, $id, $name)
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
 * Brings one stored file home: its bytes fetched beside the name and renamed
 * into the file directory, then the row set back to local. The object is
 * left to pg_file_storage_orphan_sweep(), which deletes it a day later, so
 * an address the store signed a moment ago still answers. A local file
 * already under the name (a replace door wrote it) is kept as the bytes.
 *
 * @param array $config pg_os_settings()
 * @param array $row id, name, storage_key
 * @return array ok, bytes; or pg_os_fail()
 */
function pg_file_back_one(array $config, array $row)
{
    $id = (int) $row['id'];
    $name = (string) $row['name'];
    $path = FILE_DIRECTORY_PATH . '/' . $name;

    if (!is_file($path)) {
        $partial = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.part';
        $got = pg_os_get($config, (string) $row['storage_key'], $partial);

        if (empty($got['ok'])) {
            @unlink($partial);

            return $got;
        }

        if (!@rename($partial, $path)) {
            @unlink($partial);

            return pg_os_fail('write', lang(array('string' => 'The file {var:1} could not be written to the disk.', 'vars' => array($name))));
        }
    }

    clearstatcache(true, $path);
    $size = (int) @filesize($path);

    if (!db_q("UPDATE files SET storage = 'local', storage_key = '', size = ? WHERE id = ? AND storage = 's3'", array($size, $id))) {
        return pg_os_fail('changed', lang(array('string' => 'The file {var:1} changed while it was fetched.', 'vars' => array($name))));
    }

    pg_file_cache_forget($id);

    return array('ok' => true, 'bytes' => $size);
}


/**
 * One tick of the move job in the direction the switch names: to the store
 * the newest local files first, back home the newest stored files first;
 * at most $limit of them and $seconds of work, the place kept in config_text
 * between ticks.
 *
 * @param int $limit
 * @param int $seconds
 * @return array direction, moved, bytes, failed (this tick), given_up (names)
 */
function pg_file_move_run($limit = 20, $seconds = 20)
{
    $outcome = array('direction' => '', 'moved' => 0, 'bytes' => 0, 'failed' => 0, 'given_up' => array());
    $config = pg_os_settings();
    $direction = pg_file_storage_running('back', $config) ? 'back' : 'to';

    if (!pg_file_storage_running($direction, $config) || !defined('FILE_DIRECTORY_PATH')) {
        return $outcome;
    }

    $outcome['direction'] = $direction;
    $state_key = pg_file_move_state_key($direction);
    $state = pg_file_move_state_normalize(json_decode(pg_config_text($state_key), true));

    if (!pg_file_move_due($state, time())) {
        return $outcome;
    }

    $limit = max(1, (int) $limit);
    $sql = (($direction === 'back')
            ? "SELECT id, name, type, design, theme, storage_key FROM files WHERE storage = 's3' AND storage_key <> ''"
            : "SELECT id, name, type, design, theme FROM files WHERE storage = 'local'")
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

        if ((($direction === 'to') && !pg_file_storage_movable($row)) || pg_file_move_given_up($state, $id)) {
            $outcomes[$id] = null;
            continue;
        }

        // Out of time: the rest of this page is read again on the next tick.
        if ((microtime(true) - $started) >= $seconds) {
            break;
        }

        $result = ($direction === 'back') ? pg_file_back_one($config, $row) : pg_file_move_one($config, $row);

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

    pg_config_text_save($state_key, json_encode($next['state']));

    foreach ($next['given_up'] as $id) {
        $outcome['given_up'][] = $names[$id];

        $vars = array($names[$id], pg_file_move_attempts(), $errors[$id]);

        log_activity(($direction === 'back')
            ? lang(array('string' => 'The file {var:1} could not be brought back from the object store after {var:2} attempts and stays there: {var:3}', 'vars' => $vars))
            : lang(array('string' => 'The file {var:1} could not be moved to the object store after {var:2} attempts and stays on the disk: {var:3}', 'vars' => $vars)), 'UNKNOWN');
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
 * Whether a file's bytes are kept anywhere, by its stored name: on the disk,
 * or in the object store for a moved row. Fetches nothing, for a screen
 * that only says whether a file is there and links to it.
 *
 * @param string $name files.name
 * @return bool
 */
function pg_file_kept($name)
{
    $name = (string) $name;

    if (!defined('FILE_DIRECTORY_PATH') || ($name === '') || (pg_basename($name) !== $name)) {
        return false;
    }

    if (is_file(FILE_DIRECTORY_PATH . '/' . $name)) {
        return true;
    }

    return pg_file_storage_ready() && ((int) db_value("SELECT id FROM files WHERE name = '" . e($name) . "' AND storage = 's3' AND storage_key <> '' LIMIT 1") > 0);
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

    // A local file under the name wins for a stored row too: a replace door
    // or an editor save wrote new bytes there, and the general job sets the
    // row back to local (pg_file_storage_reconcile_run()).
    $local = FILE_DIRECTORY_PATH . '/' . $row['name'];

    if (is_file($local) || (pg_file_storage($row) === 'local')) {
        return is_file($local) ? $local : '';
    }

    $directory = pg_file_cache_directory();
    $path = $directory . '/' . pg_file_cache_name($row['id'], $row['type']);

    clearstatcache(true, $path);

    if (is_file($path) && ((int) @filesize($path) === (int) $row['size'])) {
        pg_file_cache_used($path);

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
 * A path a reader opens for a file it knows by its stored name (files.name):
 * the file in the file directory when it is there, which is every file that
 * never moved (design and theme files, .css, .js) at the cost of one stat;
 * otherwise pg_file_local_copy() of the row with that name.
 *
 * @param string $name files.name
 * @return string '' when there is no such file or its bytes cannot be had
 */
function pg_file_reader_path($name)
{
    $name = (string) $name;

    if (!defined('FILE_DIRECTORY_PATH') || ($name === '') || (pg_basename($name) !== $name)) {
        return '';
    }

    $local = FILE_DIRECTORY_PATH . '/' . $name;

    if (is_file($local)) {
        return $local;
    }

    if (!pg_file_storage_ready()) {
        return '';
    }

    $id = (int) db_value("SELECT id FROM files WHERE name = '" . e($name) . "' AND storage = 's3' LIMIT 1");

    return ($id > 0) ? pg_file_local_copy($id) : '';
}


/**
 * The file in the file directory for a door that changes the bytes in place
 * (rotate, optimize): a stored file's cached copy is copied there first.
 * The general job then sets the row back to local and sends the new bytes
 * (pg_file_storage_reconcile_run()).
 *
 * @param string $name files.name
 * @return string the local path, or '' when the bytes cannot be had
 */
function pg_file_make_local($name)
{
    $source = pg_file_reader_path($name);

    if ($source === '') {
        return '';
    }

    $local = FILE_DIRECTORY_PATH . '/' . $name;

    if ($source === $local) {
        return $local;
    }

    // Copied beside the name and renamed, so get_file.php never sends half.
    $partial = $local . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.tmp';

    if (!@copy($source, $partial) || !@rename($partial, $local)) {
        @unlink($partial);

        return '';
    }

    return $local;
}


/**
 * The path a file's bytes are at on this disk now, without fetching: the
 * file in the file directory, else the cached copy of a stored file. For a
 * pass that walks many files (a sweep, a list) and must not download them.
 *
 * @param array $row files row with id, name, type
 * @return string '' when neither is there
 */
function pg_file_present_path(array $row)
{
    if (!defined('FILE_DIRECTORY_PATH') || !isset($row['name']) || ((string) $row['name'] === '')) {
        return '';
    }

    $local = FILE_DIRECTORY_PATH . '/' . $row['name'];

    if (is_file($local)) {
        return $local;
    }

    if (!isset($row['id']) || ((int) $row['id'] < 1)) {
        return '';
    }

    $copy = pg_file_cache_directory() . '/' . pg_file_cache_name($row['id'], isset($row['type']) ? $row['type'] : '');

    return is_file($copy) ? $copy : '';
}


/**
 * Marks a cached copy as used now. Only the access time moves: the
 * modification time stays the time it was fetched, which a derivative's
 * signature (pg_image_derivative_target()) reads, so a copy that is used
 * keeps the derivatives made from it. pg_file_cache_sweep() takes the later
 * of the two as the last use.
 *
 * @param string $path
 * @return void
 */
function pg_file_cache_used($path)
{
    $mtime = @filemtime($path);

    if ($mtime !== false) {
        @touch($path, (int) $mtime, time());
    }
}


/**
 * A cached copy's last use: the later of its fetch (modification time) and
 * its last read (access time, set by pg_file_cache_used()). Pure.
 *
 * @param int|false $mtime
 * @param int|false $atime
 * @return int
 */
function pg_file_cache_last_use($mtime, $atime)
{
    return max((int) $mtime, (int) $atime);
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
 * @param array $entries path => array(size, last use: pg_file_cache_last_use())
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

        $entries[$path] = array((int) @filesize($path), pg_file_cache_last_use(@filemtime($path), @fileatime($path)));
    }

    $freed = 0;

    foreach (pg_file_cache_sweep_pick($entries, pg_file_cache_cap(), $now) as $path) {
        if (@unlink($path)) {
            $freed += $entries[$path][0];
        }
    }

    return $freed;
}


/**
 * Stored rows whose name has a file on the disk again: a door that replaces
 * a file's bytes writes them locally, and get_file.php already sends that
 * file. The row goes back to local, its cached copy goes, and the move job
 * sends the new bytes on a later pass; the old object is left to
 * pg_file_storage_orphan_sweep(). Walks the stored rows by id, $limit a
 * tick, the place kept in config_text object_storage_reconcile.
 *
 * @param int $limit
 * @return int rows set back to local
 */
function pg_file_storage_reconcile_run($limit = 500)
{
    if (!pg_file_storage_ready() || !defined('FILE_DIRECTORY_PATH')) {
        return 0;
    }

    $cursor = (int) pg_config_text('object_storage_reconcile');
    $rows = db_items("SELECT id, name FROM files WHERE storage = 's3' AND id > " . $cursor . " ORDER BY id LIMIT " . max(1, (int) $limit));

    if (!is_array($rows)) {
        return 0;
    }

    $reset = 0;

    foreach ($rows as $row) {
        if (is_file(FILE_DIRECTORY_PATH . '/' . $row['name'])
            && db_q("UPDATE files SET storage = 'local', storage_key = '' WHERE id = ? AND storage = 's3'", array((int) $row['id']))) {
            pg_file_cache_forget((int) $row['id']);
            $reset++;
        }
    }

    // A short page is the end of the walk; the next tick starts again.
    $last = $rows ? (int) $rows[count($rows) - 1]['id'] : 0;
    pg_config_text_save('object_storage_reconcile', (count($rows) < (int) $limit) ? '' : (string) $last);

    return $reset;
}


/**
 * Which listed objects no row names any more. Only keys the move job makes
 * (files/<id>/...) are looked at, and only those older than a day, so an
 * object just sent whose row is about to be marked is never taken. Pure.
 *
 * @param array $items pg_os_list() items: key, modified
 * @param array|null $referenced storage_key => true for every stored row;
 *                               null when the rows could not be read
 * @param int $now
 * @return array keys to delete; none without a confirmed list of rows
 */
function pg_file_storage_orphans(array $items, $referenced, $now)
{
    $orphans = array();

    // A failed read would make every object look unnamed and delete live files.
    if (!is_array($referenced)) {
        return $orphans;
    }

    foreach ($items as $item) {
        $key = isset($item['key']) ? (string) $item['key'] : '';

        if ((preg_match('#^files/[0-9]+/#', $key) === 1) && !isset($referenced[$key])
            && !empty($item['modified']) && (((int) $now - (int) $item['modified']) > 86400)) {
            $orphans[] = $key;
        }
    }

    return $orphans;
}


/**
 * Where the next sweep's listing starts: after the last orphan this pass
 * handled when more orphans than $max were found (the rest are read again
 * next time), after the window's last key when the store holds more keys,
 * else from the top ('').
 *
 * @param array $items the window listed: key each, in listing order
 * @param bool $truncated the store holds keys after the window
 * @param array $orphans the window's orphan keys, in listing order
 * @param int $max deletions a pass
 * @return string a key below the configured prefix, or ''
 */
function pg_file_storage_sweep_cursor(array $items, $truncated, array $orphans, $max)
{
    $max = max(1, (int) $max);

    if (count($orphans) > $max) {
        return (string) $orphans[$max - 1];
    }

    if ($truncated && !empty($items)) {
        $last = end($items);

        return isset($last['key']) ? (string) $last['key'] : '';
    }

    return '';
}


/**
 * Deletes the objects of deleted and replaced files: once a day, a window of
 * 5000 keys of files/ compared with the keys the rows hold, at most 200
 * deletions a pass. The next pass lists on from where this one stopped
 * (config_text object_storage_orphan_sweep_after), so a store of more keys
 * is covered window by window instead of its first keys every day. Every delete door of the product removes only the row and the local
 * bytes, so this one pass covers them all. Runs while a store is set up,
 * whether or not the switch is on, so files moved earlier are still cleaned.
 *
 * @return int objects deleted
 */
function pg_file_storage_orphan_sweep()
{
    $config = pg_os_settings();

    if (!pg_file_storage_ready() || !pg_os_ready($config)) {
        return 0;
    }

    $last = (int) pg_config_text('object_storage_orphan_sweep');

    if ((time() - $last) < 86400) {
        return 0;
    }

    pg_config_text_save('object_storage_orphan_sweep', (string) time());

    $after = pg_config_text('object_storage_orphan_sweep_after');
    $listing = pg_os_list($config, 'files/', array('limit' => 5000, 'start_after' => $after));

    if (empty($listing['ok'])) {
        return 0;
    }

    $deleted = 0;
    $referenced = pg_file_storage_referenced_keys();

    // Rows that cannot be read leave the window to be read again.
    if (!is_array($referenced)) {
        return 0;
    }

    $orphans = pg_file_storage_orphans($listing['items'], $referenced, time());
    pg_config_text_save('object_storage_orphan_sweep_after',
        pg_file_storage_sweep_cursor($listing['items'], !empty($listing['truncated']), $orphans, 200));

    foreach (array_slice($orphans, 0, 200) as $key) {
        $done = pg_os_delete($config, $key);

        if (!empty($done['ok'])) {
            $deleted++;
        }
    }

    return $deleted;
}


/**
 * The object keys the stored rows hold. Read with mysqli_query() itself: a
 * failed read must stop only the sweep, where db_values() answers it with an
 * empty list (during an upgrade) or ends the whole job (pg_db_run()). An
 * empty list is trusted only when a count of stored rows confirms it.
 *
 * @return array|null storage_key => true; null when the rows cannot be read
 */
function pg_file_storage_referenced_keys()
{
    if (!class_exists('db') || empty(db::$con)) {
        return null;
    }

    $result = @mysqli_query(db::$con, "SELECT storage_key FROM files WHERE storage = 's3'");

    if (!$result) {
        return null;
    }

    $referenced = array();

    while ($row = mysqli_fetch_row($result)) {
        $referenced[(string) $row[0]] = true;
    }

    if ($referenced) {
        return $referenced;
    }

    $count = @mysqli_query(db::$con, "SELECT COUNT(*) FROM files WHERE storage = 's3'");
    $row = $count ? mysqli_fetch_row($count) : null;

    return (is_array($row) && ((int) $row[0] === 0)) ? $referenced : null;
}
