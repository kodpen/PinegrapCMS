<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The object store's health: calls that could not reach it, counted over the
 * last hour for the operations alert storage_unreachable; the last result of
 * the Storage card's test; the move job's failures for System Status.
 *
 * A failed call is noted in data/temp/object_storage_failures.json (time and
 * message, the last hour only), written under flock() and only when a call
 * fails, so a working store costs nothing here.
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
 * How many failed calls in an hour make the store count as unreachable.
 *
 * @return int
 */
function pg_os_health_threshold()
{
    return 5;
}


/**
 * Whether a pg_curl_run() answer says the store could not be used: no
 * answer at all (a cURL error other than a write stopped on our side), a
 * server error, or refused credentials. A missing object, a bad request or a
 * call that was never sent (pg_os_fail() before cURL) is not. Pure.
 *
 * @param array $result
 * @return bool
 */
function pg_os_health_unreachable(array $result)
{
    if (!empty($result['ok'])) {
        return false;
    }

    $errno = isset($result['errno']) ? (int) $result['errno'] : 0;

    // 23: the sink or the cap stopped the transfer, a local matter.
    if ($errno > 0) {
        return ($errno !== 23);
    }

    $status = isset($result['status']) ? (int) $result['status'] : 0;

    return ($status >= 500) || ($status === 401) || ($status === 403);
}


/**
 * The failures kept: those of the last hour, the newest 50, with $add
 * appended. Pure.
 *
 * @param mixed $entries the stored list of array(time, message)
 * @param int $now
 * @param array|null $add array(time, message)
 * @return array
 */
function pg_os_health_window($entries, $now, $add = null)
{
    $kept = array();

    foreach ((is_array($entries) ? $entries : array()) as $entry) {
        if (is_array($entry) && isset($entry[0]) && (((int) $now - (int) $entry[0]) < 3600) && ((int) $entry[0] <= (int) $now)) {
            $kept[] = array((int) $entry[0], isset($entry[1]) ? (string) $entry[1] : '');
        }
    }

    if (is_array($add)) {
        $kept[] = array((int) $add[0], (string) $add[1]);
    }

    return array_slice($kept, -50);
}


/**
 * The file the failures are kept in.
 *
 * @return string
 */
function pg_os_health_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/object_storage_failures.json';
}


/**
 * Notes a call that could not reach the store.
 *
 * @param array $result pg_curl_run()
 * @return void
 */
function pg_os_health_record(array $result)
{
    $path = pg_os_health_path();

    if (!is_dir(dirname($path))) {
        return;
    }

    $handle = @fopen($path, 'c+');

    if (!$handle) {
        return;
    }

    if (@flock($handle, LOCK_EX)) {
        $stored = json_decode((string) stream_get_contents($handle), true);
        $error = isset($result['error']) ? trim((string) $result['error']) : '';
        $message = ($error !== '') ? $error : ('HTTP ' . (isset($result['status']) ? (int) $result['status'] : 0));
        $now = time();

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(pg_os_health_window($stored, $now, array($now, substr($message, 0, 300)))));
        fflush($handle);
        flock($handle, LOCK_UN);
    }

    fclose($handle);
}


/**
 * The failed calls of the last hour.
 *
 * @param int $now
 * @return array count, message (of the newest)
 */
function pg_os_health_recent($now)
{
    $stored = is_file(pg_os_health_path()) ? json_decode((string) @file_get_contents(pg_os_health_path()), true) : null;
    $entries = pg_os_health_window($stored, $now);
    $last = $entries ? $entries[count($entries) - 1] : null;

    return array('count' => count($entries), 'message' => $last ? $last[1] : '');
}


/**
 * Keeps the Storage card's last test result (config_text
 * object_storage_last_test).
 *
 * @param array $test pg_os_test()
 * @param int $now
 * @return void
 */
function pg_os_health_test_save(array $test, $now)
{
    pg_config_text_save('object_storage_last_test', json_encode(array(
        'at'      => (int) $now,
        'ok'      => !empty($test['ok']),
        'message' => empty($test['ok']) ? substr((string) (isset($test['message']) ? $test['message'] : ''), 0, 500) : '',
    )));
}


/**
 * The last test result as stored, made whole. Pure.
 *
 * @param mixed $value the stored JSON, decoded
 * @return array|null at, ok, message; null when no test was run
 */
function pg_os_health_test_normalize($value)
{
    if (!is_array($value) || empty($value['at'])) {
        return null;
    }

    return array(
        'at'      => (int) $value['at'],
        'ok'      => !empty($value['ok']),
        'message' => isset($value['message']) ? (string) $value['message'] : '',
    );
}


/**
 * The last test result.
 *
 * @return array|null pg_os_health_test_normalize()
 */
function pg_os_health_test_last()
{
    return pg_os_health_test_normalize(json_decode((string) pg_config_text('object_storage_last_test'), true));
}


/**
 * The move job's failures: files still being tried and files left on the
 * disk after the attempt limit. Pure.
 *
 * @param array $state pg_file_move_state_normalize()
 * @param int $attempts pg_file_move_attempts()
 * @return array failing, given_up (counts), given_up_ids (the first 5)
 */
function pg_os_health_move_failures(array $state, $attempts)
{
    $failing = 0;
    $given_up = array();

    foreach ((isset($state['failures']) ? $state['failures'] : array()) as $id => $count) {
        if ((int) $count >= (int) $attempts) {
            $given_up[] = (int) $id;
        } else {
            $failing++;
        }
    }

    return array('failing' => $failing, 'given_up' => count($given_up), 'given_up_ids' => array_slice($given_up, 0, 5));
}
