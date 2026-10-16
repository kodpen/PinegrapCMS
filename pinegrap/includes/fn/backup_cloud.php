<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The remote copy of the automatic backup in the site's own cloud storage:
 * Google Drive, OneDrive or Dropbox, through an account connected on
 * Connected accounts for the "backup" purpose (includes/fn/connect.php).
 *
 * An archive is sent in 10 MiB parts (Drive's resumable upload, a Graph
 * upload session, a Dropbox upload session), so neither the site nor the
 * service has to hold a large backup in one request, and a token that ends
 * half-way through is renewed between two parts. The part size is a multiple
 * of what Drive (256 KiB) and Graph (320 KiB) require.
 *
 * Every name sent is a single file in one folder the site owns there. The
 * retention of the local backups is applied to that folder too, and only to
 * automatic backup names (pg_backup_is_auto_name()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// ----------------------------------------------------------------------------
// Rules that need no connection (tests/backup_cloud_test.php)
// ----------------------------------------------------------------------------

/**
 * The cloud destinations: remote type => the provider that serves it.
 *
 * @return array type => array('provider' => string, 'label' => string)
 */
function pg_backup_cloud_types()
{
    return array(
        'gdrive'   => array('provider' => 'google', 'label' => 'Google Drive'),
        'onedrive' => array('provider' => 'microsoft', 'label' => 'OneDrive'),
        'dropbox'  => array('provider' => 'dropbox', 'label' => 'Dropbox'),
    );
}


/**
 * Whether a remote type is one of the cloud destinations.
 *
 * @param string $type
 * @return bool
 */
function pg_backup_cloud_is_type($type)
{
    return is_string($type) && array_key_exists($type, pg_backup_cloud_types());
}


/**
 * The bytes one request carries.
 *
 * @return int
 */
function pg_backup_cloud_chunk_size()
{
    return 10485760;
}


/**
 * A folder as it is kept: one to three names separated by "/", each of
 * letters, digits, spaces, dots, dashes and underscores, not starting with a
 * dot and not ending with a dot or a space (Windows and OneDrive refuse
 * those). '' is the default folder.
 *
 * @param mixed $value
 * @return string|null null when the value cannot be a folder
 */
function pg_backup_cloud_folder($value)
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim(str_replace('\\', '/', (string) $value));
    $value = trim($value, '/');

    if ($value === '') {
        return '';
    }

    $segments = array();

    foreach (explode('/', $value) as $segment) {
        $segment = trim($segment);

        if ($segment === '') {
            continue;
        }

        if (!preg_match('/^[A-Za-z0-9_\-][A-Za-z0-9 ._\-]{0,59}$/', $segment) || preg_match('/[. ]$/', $segment)) {
            return null;
        }

        $segments[] = $segment;
    }

    if (count($segments) > 3) {
        return null;
    }

    return implode('/', $segments);
}


/**
 * The folder used when none is set: the site's host name, made a valid
 * folder name.
 *
 * @param string $hostname
 * @return string
 */
function pg_backup_cloud_default_folder($hostname)
{
    $name = preg_replace('/[^A-Za-z0-9._\-]+/', '-', strtolower((string) $hostname));
    $name = trim(substr($name, 0, 60), '.-');

    return ($name === '') ? 'pinegrap' : $name;
}


/**
 * The parts a file of $size bytes is sent in.
 *
 * @param int $size
 * @param int $chunk
 * @return array list of array(start, length); one empty part for an empty file
 */
function pg_backup_cloud_chunks($size, $chunk)
{
    $size = max(0, (int) $size);
    $chunk = max(1, (int) $chunk);

    if ($size === 0) {
        return array(array(0, 0));
    }

    $parts = array();

    for ($start = 0; $start < $size; $start += $chunk) {
        $parts[] = array($start, min($chunk, $size - $start));
    }

    return $parts;
}


/**
 * The Content-Range of a part.
 *
 * @param int $start
 * @param int $length
 * @param int $total
 * @return string
 */
function pg_backup_cloud_content_range($start, $length, $total)
{
    if ((int) $length <= 0) {
        return 'bytes */' . (int) $total;
    }

    return 'bytes ' . (int) $start . '-' . ((int) $start + (int) $length - 1) . '/' . (int) $total;
}


/**
 * Where an unfinished upload goes on, from the service's own account of what
 * it has: Drive's Range header ("bytes=0-1234") or Graph's
 * nextExpectedRanges ("1235-").
 *
 * @param string $range
 * @return int|null the next byte to send, or null when it cannot be read
 */
function pg_backup_cloud_next_offset($range)
{
    $range = trim((string) $range);

    if ($range === '') {
        return 0;
    }

    if (preg_match('/^bytes=0-(\d+)$/', $range, $match)) {
        return (int) $match[1] + 1;
    }

    if (preg_match('/^(\d+)-(\d*)$/', $range, $match)) {
        return (int) $match[1];
    }

    return null;
}


/**
 * A value inside a Drive search query, quoted.
 *
 * @param string $value
 * @return string
 */
function pg_backup_cloud_drive_quote($value)
{
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
}


/**
 * The Dropbox-API-Arg header value: JSON with everything outside ASCII
 * escaped, as the header must be.
 *
 * @param array $arguments
 * @return string
 */
function pg_backup_cloud_dropbox_arg(array $arguments)
{
    return json_encode($arguments, JSON_UNESCAPED_SLASHES);
}


/**
 * What a service said when it refused a request, in one line.
 *
 * Google and Graph answer {"error": {"message"}}, Dropbox {"error_summary"}
 * or plain text.
 *
 * @param array $result what pg_connect_api() or api_http_request() returned
 * @return string
 */
function pg_backup_cloud_error($result)
{
    $status = isset($result['status']) ? (int) $result['status'] : 0;

    if ($status === 0) {
        $error = isset($result['error']) ? trim((string) $result['error']) : '';

        return ($error !== '') ? $error : lang('The storage service did not answer.');
    }

    $body = isset($result['body']) ? (string) $result['body'] : '';
    $data = json_decode($body, true);
    $reason = '';

    if (is_array($data)) {
        if (isset($data['error']['message']) && is_string($data['error']['message'])) {
            $reason = $data['error']['message'];

            if (isset($data['error']['code']) && is_string($data['error']['code']) && ($data['error']['code'] !== '')) {
                $reason = $data['error']['code'] . ': ' . $reason;
            }
        } elseif (isset($data['error_summary']) && is_string($data['error_summary'])) {
            $reason = $data['error_summary'];
        } elseif (isset($data['error_description']) && is_string($data['error_description'])) {
            $reason = $data['error_description'];
        } elseif (isset($data['error']) && is_string($data['error'])) {
            $reason = $data['error'];
        }
    } elseif ((strpos($body, '<') === false) && (trim($body) !== '')) {
        $reason = $body;
    }

    $reason = trim(preg_replace('/\s+/', ' ', $reason));

    if (strlen($reason) > 300) {
        $reason = substr($reason, 0, 300) . '...';
    }

    return lang(array('string' => 'The storage service answered HTTP {var:1}. {var:2}', 'vars' => array($status, $reason)));
}


// ----------------------------------------------------------------------------
// Sending
// ----------------------------------------------------------------------------

/**
 * The connected account a destination's settings name, when it can serve it.
 *
 * @param string $type
 * @param array $config pg_backup_remote_normalize($type, ...)
 * @return array array('ok' => bool, 'account' => array|null, 'error' => string)
 */
function pg_backup_cloud_account($type, array $config)
{
    $types = pg_backup_cloud_types();

    if (!isset($types[$type]) || !function_exists('pg_connect_account')) {
        return array('ok' => false, 'account' => null, 'error' => lang('No remote backup destination is set.'));
    }

    $account = ((int) $config['connection_id'] > 0) ? pg_connect_account((int) $config['connection_id']) : null;

    if (!is_array($account) || ($account['provider'] !== $types[$type]['provider']) || ((int) $account['user_id'] !== 0)
        || !in_array('backup', $account['purposes'], true)
    ) {
        return array('ok' => false, 'account' => null, 'error' => lang(array(
            'string' => 'No {var:1} account is connected for backups. Connect one in the backup settings.',
            'vars'   => $types[$type]['label'],
        )));
    }

    return array('ok' => true, 'account' => $account, 'error' => '');
}


/**
 * The folder a destination writes to, as segments.
 *
 * @param array $config
 * @return array
 */
function pg_backup_cloud_folder_segments(array $config)
{
    $folder = isset($config['folder']) ? pg_backup_cloud_folder($config['folder']) : '';

    if (($folder === null) || ($folder === '')) {
        $folder = pg_backup_cloud_default_folder(defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '');
    }

    return explode('/', $folder);
}


/**
 * One part of a file.
 *
 * @param resource $handle
 * @param int $start
 * @param int $length
 * @return string|null null when the file could not be read
 */
function pg_backup_cloud_read($handle, $start, $length)
{
    if ($length <= 0) {
        return '';
    }

    if (fseek($handle, (int) $start) !== 0) {
        return null;
    }

    $data = '';

    while ((strlen($data) < $length) && !feof($handle)) {
        $piece = fread($handle, $length - strlen($data));

        if (($piece === false) || ($piece === '')) {
            break;
        }

        $data .= $piece;
    }

    return (strlen($data) === (int) $length) ? $data : null;
}


/**
 * Send one file to a cloud destination, replacing a file of the same name.
 *
 * @param string $file
 * @param string $type 'gdrive', 'onedrive' or 'dropbox'
 * @param array $config pg_backup_remote_normalize($type, ...)
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_cloud_put($file, $type, array $config)
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => lang('The cURL extension of PHP is not available on this server.'));
    }

    $account = pg_backup_cloud_account($type, $config);

    if (!$account['ok']) {
        return array('ok' => false, 'error' => $account['error']);
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $size = @filesize($file);
    $handle = @fopen($file, 'rb');

    if (($size === false) || !$handle) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(basename($file)))));
    }

    $connection_id = (int) $account['account']['id'];
    $segments = pg_backup_cloud_folder_segments($config);
    $name = basename($file);

    if ($type === 'gdrive') {
        $result = pg_backup_gdrive_put($connection_id, $segments, $name, $handle, (int) $size);
    } elseif ($type === 'onedrive') {
        $result = pg_backup_onedrive_put($connection_id, $segments, $name, $handle, (int) $size);
    } else {
        $result = pg_backup_dropbox_put($connection_id, $segments, $name, $handle, (int) $size);
    }

    fclose($handle);

    return $result;
}


/**
 * Delete the automatic backups beyond the retention from a cloud
 * destination's folder.
 *
 * @param string $type
 * @param array $config
 * @param int $keep weeks to keep; below 1 keeps everything
 * @return array array('ok' => bool, 'deleted' => array, 'error' => string)
 */
function pg_backup_cloud_prune($type, array $config, $keep)
{
    $outcome = array('ok' => true, 'deleted' => array(), 'error' => '');

    if ((int) $keep < 1) {
        return $outcome;
    }

    $account = pg_backup_cloud_account($type, $config);

    if (!$account['ok']) {
        return array('ok' => false, 'deleted' => array(), 'error' => $account['error']);
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $connection_id = (int) $account['account']['id'];
    $segments = pg_backup_cloud_folder_segments($config);

    if ($type === 'gdrive') {
        $listed = pg_backup_gdrive_list($connection_id, $segments);
    } elseif ($type === 'onedrive') {
        $listed = pg_backup_onedrive_list($connection_id, $segments);
    } else {
        $listed = pg_backup_dropbox_list($connection_id, $segments);
    }

    if (!$listed['ok']) {
        return array('ok' => false, 'deleted' => array(), 'error' => $listed['error']);
    }

    $names = array();

    foreach ($listed['files'] as $entry) {
        $names[] = $entry['name'];
    }

    $prune = pg_backup_prune_list(array_values(array_unique($names)), (int) $keep);

    foreach ($listed['files'] as $entry) {

        // Asked again at the point of deletion, as for the local copies.
        if (!in_array($entry['name'], $prune, true) || !pg_backup_is_auto_name($entry['name'])) {
            continue;
        }

        if ($type === 'gdrive') {
            $gone = pg_connect_api($connection_id, 'DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($entry['id']), array('timeout' => 30));
        } elseif ($type === 'onedrive') {
            $gone = pg_connect_api($connection_id, 'DELETE', 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode($entry['id']), array('timeout' => 30), pg_backup_onedrive_scopes());
        } else {
            $gone = pg_connect_api($connection_id, 'POST', 'https://api.dropboxapi.com/2/files/delete_v2', array(
                'body'    => json_encode(array('path' => $entry['id']), JSON_UNESCAPED_SLASHES),
                'headers' => array('Content-Type: application/json'),
                'timeout' => 30,
            ));
        }

        if ($gone['ok']) {
            $outcome['deleted'][] = $entry['name'];
        } else {
            $outcome['ok'] = false;
            $outcome['error'] = pg_backup_cloud_error($gone);
        }
    }

    return $outcome;
}


// ----------------------------------------------------------------------------
// Google Drive
// ----------------------------------------------------------------------------

/**
 * The id of the folder the backups go in, made when $create allows.
 *
 * drive.file shows the application only what it created, so the folder is
 * looked up among those; a folder of the same name made by hand is not seen
 * and a second one is created next to it.
 *
 * @param int $connection_id
 * @param array $segments
 * @param bool $create
 * @return array array('ok' => bool, 'id' => string, 'error' => string)
 */
function pg_backup_gdrive_folder($connection_id, array $segments, $create)
{
    $parent = 'root';

    foreach ($segments as $segment) {

        $found = pg_connect_api($connection_id, 'GET', 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q'        => "mimeType = 'application/vnd.google-apps.folder' and name = " . pg_backup_cloud_drive_quote($segment)
                . ' and ' . pg_backup_cloud_drive_quote($parent) . ' in parents and trashed = false',
            'fields'   => 'files(id)',
            'spaces'   => 'drive',
            'pageSize' => 1,
        ), '', '&', PHP_QUERY_RFC3986), array('timeout' => 30));

        if (!$found['ok']) {
            return array('ok' => false, 'id' => '', 'error' => pg_backup_cloud_error($found));
        }

        if (isset($found['data']['files'][0]['id']) && is_string($found['data']['files'][0]['id'])) {
            $parent = $found['data']['files'][0]['id'];
            continue;
        }

        if (!$create) {
            return array('ok' => true, 'id' => '', 'error' => '');
        }

        $made = pg_connect_api($connection_id, 'POST', 'https://www.googleapis.com/drive/v3/files?fields=id', array(
            'body'    => json_encode(array('name' => $segment, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => array($parent))),
            'headers' => array('Content-Type: application/json; charset=UTF-8'),
            'timeout' => 30,
        ));

        if (!$made['ok'] || !isset($made['data']['id']) || !is_string($made['data']['id'])) {
            return array('ok' => false, 'id' => '', 'error' => pg_backup_cloud_error($made));
        }

        $parent = $made['data']['id'];
    }

    return array('ok' => true, 'id' => $parent, 'error' => '');
}


/**
 * The files in the backup folder.
 *
 * @param int $connection_id
 * @param array $segments
 * @param string|null $folder_id the folder when it is known already
 * @return array array('ok' => bool, 'files' => list of array(id, name), 'error' => string)
 */
function pg_backup_gdrive_list($connection_id, array $segments, $folder_id = null)
{
    if ($folder_id === null) {
        $folder = pg_backup_gdrive_folder($connection_id, $segments, false);

        if (!$folder['ok']) {
            return array('ok' => false, 'files' => array(), 'error' => $folder['error']);
        }

        $folder_id = $folder['id'];
    }

    $files = array();

    if ($folder_id === '') {
        return array('ok' => true, 'files' => $files, 'error' => '');
    }

    $page = '';

    for ($round = 0; $round < 10; $round++) {

        $query = array(
            'q'        => pg_backup_cloud_drive_quote($folder_id) . " in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'",
            'fields'   => 'nextPageToken,files(id,name)',
            'spaces'   => 'drive',
            'pageSize' => 1000,
        );

        if ($page !== '') {
            $query['pageToken'] = $page;
        }

        $listed = pg_connect_api($connection_id, 'GET', 'https://www.googleapis.com/drive/v3/files?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), array(
            'timeout'  => 30,
            'max_body' => 4194304,
        ));

        if (!$listed['ok']) {
            return array('ok' => false, 'files' => array(), 'error' => pg_backup_cloud_error($listed));
        }

        foreach ((isset($listed['data']['files']) && is_array($listed['data']['files'])) ? $listed['data']['files'] : array() as $entry) {
            if (isset($entry['id'], $entry['name']) && is_string($entry['id']) && is_string($entry['name'])) {
                $files[] = array('id' => $entry['id'], 'name' => $entry['name']);
            }
        }

        $page = (isset($listed['data']['nextPageToken']) && is_string($listed['data']['nextPageToken'])) ? $listed['data']['nextPageToken'] : '';

        if ($page === '') {
            break;
        }
    }

    return array('ok' => true, 'files' => $files, 'error' => '');
}


/**
 * Upload a file to Drive with a resumable upload, then remove the earlier
 * copies of the same name: Drive keeps files of equal names side by side.
 *
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_gdrive_put($connection_id, array $segments, $name, $handle, $size)
{
    $folder = pg_backup_gdrive_folder($connection_id, $segments, true);

    if (!$folder['ok']) {
        return array('ok' => false, 'error' => $folder['error']);
    }

    $content_type = (substr($name, -4) === '.zip') ? 'application/zip' : 'text/plain';

    $session = pg_connect_api($connection_id, 'POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id', array(
        'body'             => json_encode(array('name' => $name, 'parents' => array($folder['id']))),
        'headers'          => array(
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . $content_type,
            'X-Upload-Content-Length: ' . (int) $size,
        ),
        'timeout'          => 30,
        'response_headers' => true,
    ));

    $location = isset($session['headers']['location']) ? (string) $session['headers']['location'] : '';

    if (!$session['ok'] || (strpos($location, 'https://www.googleapis.com/upload/drive/') !== 0)) {
        return array('ok' => false, 'error' => pg_backup_cloud_error($session));
    }

    $offset = 0;
    $new_id = '';

    // Each round sends what the service is still missing, so a part it
    // received only in part is sent again from where it stopped.
    for ($round = 0; $round < (count(pg_backup_cloud_chunks($size, pg_backup_cloud_chunk_size())) * 2) + 2; $round++) {

        $length = min(pg_backup_cloud_chunk_size(), $size - $offset);
        $data = pg_backup_cloud_read($handle, $offset, $length);

        if ($data === null) {
            return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array($name))));
        }

        $sent = pg_connect_api($connection_id, 'PUT', $location, array(
            'body'             => $data,
            'headers'          => array(
                'Content-Type: ' . $content_type,
                'Content-Range: ' . pg_backup_cloud_content_range($offset, $length, $size),
                'Expect:',
            ),
            'timeout'          => 300,
            'response_headers' => true,
        ));

        unset($data);

        if ($sent['ok']) {
            $new_id = (isset($sent['data']['id']) && is_string($sent['data']['id'])) ? $sent['data']['id'] : '';
            break;
        }

        if ((int) $sent['status'] !== 308) {
            return array('ok' => false, 'error' => pg_backup_cloud_error($sent));
        }

        $next = pg_backup_cloud_next_offset(isset($sent['headers']['range']) ? $sent['headers']['range'] : '');

        if (($next === null) || ($next > $size)) {
            return array('ok' => false, 'error' => pg_backup_cloud_error($sent));
        }

        $offset = $next;
    }

    if ($new_id === '') {
        return array('ok' => false, 'error' => lang('The upload did not finish.'));
    }

    // The new copy is in place; the older ones of the same name go.
    $listed = pg_backup_gdrive_list($connection_id, $segments, $folder['id']);

    if ($listed['ok']) {
        foreach ($listed['files'] as $entry) {
            if (($entry['name'] === $name) && ($entry['id'] !== $new_id)) {
                pg_connect_api($connection_id, 'DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($entry['id']), array('timeout' => 30));
            }
        }
    }

    return array('ok' => true, 'error' => '');
}


// ----------------------------------------------------------------------------
// OneDrive (Microsoft Graph)
// ----------------------------------------------------------------------------

/**
 * The scopes a Graph token for the app folder is fetched for.
 *
 * @return array
 */
function pg_backup_onedrive_scopes()
{
    return array('https://graph.microsoft.com/Files.ReadWrite.AppFolder');
}


/**
 * The address of a path inside the app folder.
 *
 * @param array $segments
 * @param string $suffix what follows the path (":/children", ...)
 * @return string
 */
function pg_backup_onedrive_url(array $segments, $suffix = '')
{
    $base = 'https://graph.microsoft.com/v1.0/me/drive/special/approot';

    if (!$segments) {
        return $base . $suffix;
    }

    return $base . ':/' . implode('/', array_map('rawurlencode', $segments)) . ($suffix === '' ? ':' : ':' . $suffix);
}


/**
 * Make sure the app folder and the backup folder in it exist.
 *
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_onedrive_folder($connection_id, array $segments)
{
    // The app folder itself is created the first time it is asked for.
    $root = pg_connect_api($connection_id, 'GET', pg_backup_onedrive_url(array()) . '?$select=id', array('timeout' => 30), pg_backup_onedrive_scopes());

    if (!$root['ok']) {
        return array('ok' => false, 'error' => pg_backup_cloud_error($root));
    }

    $parent = array();

    foreach ($segments as $segment) {

        $made = pg_connect_api($connection_id, 'POST', $parent ? pg_backup_onedrive_url($parent, '/children') : pg_backup_onedrive_url(array(), '/children'), array(
            'body'    => json_encode(array('name' => $segment, 'folder' => new stdClass(), '@microsoft.graph.conflictBehavior' => 'fail')),
            'headers' => array('Content-Type: application/json'),
            'timeout' => 30,
        ), pg_backup_onedrive_scopes());

        // 409: it is there already.
        if (!$made['ok'] && ((int) $made['status'] !== 409)) {
            return array('ok' => false, 'error' => pg_backup_cloud_error($made));
        }

        $parent[] = $segment;
    }

    return array('ok' => true, 'error' => '');
}


/**
 * The files in the backup folder.
 *
 * @return array array('ok' => bool, 'files' => list of array(id, name), 'error' => string)
 */
function pg_backup_onedrive_list($connection_id, array $segments)
{
    $files = array();
    $url = pg_backup_onedrive_url($segments, '/children') . '?$select=id,name,file&$top=999';

    for ($round = 0; ($round < 10) && ($url !== ''); $round++) {

        $listed = pg_connect_api($connection_id, 'GET', $url, array('timeout' => 30, 'max_body' => 4194304), pg_backup_onedrive_scopes());

        // No folder yet: nothing in it.
        if ((int) $listed['status'] === 404) {
            break;
        }

        if (!$listed['ok']) {
            return array('ok' => false, 'files' => array(), 'error' => pg_backup_cloud_error($listed));
        }

        foreach ((isset($listed['data']['value']) && is_array($listed['data']['value'])) ? $listed['data']['value'] : array() as $entry) {
            if (isset($entry['id'], $entry['name'], $entry['file']) && is_string($entry['id']) && is_string($entry['name'])) {
                $files[] = array('id' => $entry['id'], 'name' => $entry['name']);
            }
        }

        $next = isset($listed['data']['@odata.nextLink']) ? (string) $listed['data']['@odata.nextLink'] : '';
        $url = (strpos($next, 'https://graph.microsoft.com/') === 0) ? $next : '';
    }

    return array('ok' => true, 'files' => $files, 'error' => '');
}


/**
 * Upload a file to OneDrive through an upload session, replacing a file of
 * the same name.
 *
 * The parts go to the session's own address without the access token, as
 * Graph requires: the address is the permission.
 *
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_onedrive_put($connection_id, array $segments, $name, $handle, $size)
{
    $folder = pg_backup_onedrive_folder($connection_id, $segments);

    if (!$folder['ok']) {
        return $folder;
    }

    $session = pg_connect_api($connection_id, 'POST', pg_backup_onedrive_url(array_merge($segments, array($name)), '/createUploadSession'), array(
        'body'    => json_encode(array('item' => array('@microsoft.graph.conflictBehavior' => 'replace'))),
        'headers' => array('Content-Type: application/json'),
        'timeout' => 30,
    ), pg_backup_onedrive_scopes());

    $upload_url = isset($session['data']['uploadUrl']) ? (string) $session['data']['uploadUrl'] : '';

    if (!$session['ok'] || (strpos($upload_url, 'https://') !== 0)) {
        return array('ok' => false, 'error' => pg_backup_cloud_error($session));
    }

    $offset = 0;

    for ($round = 0; $round < (count(pg_backup_cloud_chunks($size, pg_backup_cloud_chunk_size())) * 2) + 2; $round++) {

        $length = min(pg_backup_cloud_chunk_size(), $size - $offset);
        $data = pg_backup_cloud_read($handle, $offset, $length);

        if ($data === null) {
            return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array($name))));
        }

        $sent = api_http_request('PUT', $upload_url, array(
            'body'     => $data,
            'headers'  => array(
                'Content-Range: ' . pg_backup_cloud_content_range($offset, $length, $size),
                'Expect:',
            ),
            'timeout'  => 300,
            'max_body' => 65536,
            'agent'    => 'connect',
        ));

        unset($data);

        // 200 or 201: the file is complete.
        if (in_array((int) $sent['status'], array(200, 201), true)) {
            return array('ok' => true, 'error' => '');
        }

        if ((int) $sent['status'] !== 202) {
            // The session is dropped so the part already sent does not linger.
            api_http_request('DELETE', $upload_url, array('timeout' => 15, 'agent' => 'connect'));

            return array('ok' => false, 'error' => pg_backup_cloud_error($sent));
        }

        $data = json_decode((string) $sent['body'], true);
        $next = isset($data['nextExpectedRanges'][0]) ? pg_backup_cloud_next_offset((string) $data['nextExpectedRanges'][0]) : null;

        if (($next === null) || ($next >= $size)) {
            return array('ok' => false, 'error' => pg_backup_cloud_error($sent));
        }

        $offset = $next;
    }

    return array('ok' => false, 'error' => lang('The upload did not finish.'));
}


// ----------------------------------------------------------------------------
// Dropbox
// ----------------------------------------------------------------------------

/**
 * A path inside the app folder.
 *
 * @param array $segments
 * @param string $name '' for the folder itself
 * @return string
 */
function pg_backup_dropbox_path(array $segments, $name = '')
{
    return '/' . implode('/', array_merge($segments, ($name === '') ? array() : array($name)));
}


/**
 * One call to Dropbox's content endpoint, with its arguments in the header.
 *
 * @return array what pg_connect_api() returned
 */
function pg_backup_dropbox_content($connection_id, $endpoint, array $arguments, $data)
{
    return pg_connect_api($connection_id, 'POST', 'https://content.dropboxapi.com/2/files/' . $endpoint, array(
        'body'    => $data,
        'headers' => array(
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: ' . pg_backup_cloud_dropbox_arg($arguments),
            'Expect:',
        ),
        'timeout' => 300,
    ));
}


/**
 * The files in the backup folder.
 *
 * @return array array('ok' => bool, 'files' => list of array(id, name), 'error' => string)
 */
function pg_backup_dropbox_list($connection_id, array $segments)
{
    $files = array();
    $url = 'https://api.dropboxapi.com/2/files/list_folder';
    $arguments = array('path' => pg_backup_dropbox_path($segments), 'limit' => 2000);

    for ($round = 0; $round < 10; $round++) {

        $listed = pg_connect_api($connection_id, 'POST', $url, array(
            'body'     => json_encode($arguments, JSON_UNESCAPED_SLASHES),
            'headers'  => array('Content-Type: application/json'),
            'timeout'  => 30,
            'max_body' => 4194304,
        ));

        // No folder yet: nothing in it.
        if (((int) $listed['status'] === 409) && (strpos((string) $listed['body'], 'not_found') !== false)) {
            break;
        }

        if (!$listed['ok']) {
            return array('ok' => false, 'files' => array(), 'error' => pg_backup_cloud_error($listed));
        }

        foreach ((isset($listed['data']['entries']) && is_array($listed['data']['entries'])) ? $listed['data']['entries'] : array() as $entry) {
            if (isset($entry['.tag'], $entry['name'], $entry['path_lower']) && ($entry['.tag'] === 'file') && is_string($entry['name'])) {
                $files[] = array('id' => (string) $entry['path_lower'], 'name' => $entry['name']);
            }
        }

        if (empty($listed['data']['has_more']) || !isset($listed['data']['cursor'])) {
            break;
        }

        $url = 'https://api.dropboxapi.com/2/files/list_folder/continue';
        $arguments = array('cursor' => (string) $listed['data']['cursor']);
    }

    return array('ok' => true, 'files' => $files, 'error' => '');
}


/**
 * Upload a file to Dropbox, replacing a file of the same name: one request
 * for a small file, an upload session for a larger one.
 *
 * @return array array('ok' => bool, 'error' => string)
 */
function pg_backup_dropbox_put($connection_id, array $segments, $name, $handle, $size)
{
    $commit = array(
        'path'       => pg_backup_dropbox_path($segments, $name),
        'mode'       => 'overwrite',
        'autorename' => false,
        'mute'       => true,
    );

    $parts = pg_backup_cloud_chunks($size, pg_backup_cloud_chunk_size());
    $last = count($parts) - 1;
    $session_id = '';

    foreach ($parts as $index => $part) {

        $data = pg_backup_cloud_read($handle, $part[0], $part[1]);

        if ($data === null) {
            return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array($name))));
        }

        if ($last === 0) {
            $sent = pg_backup_dropbox_content($connection_id, 'upload', $commit, $data);
        } elseif ($index === 0) {
            $sent = pg_backup_dropbox_content($connection_id, 'upload_session/start', array('close' => false), $data);
            $session_id = isset($sent['data']['session_id']) ? (string) $sent['data']['session_id'] : '';

            if ($sent['ok'] && ($session_id === '')) {
                $sent['ok'] = false;
            }
        } elseif ($index < $last) {
            $sent = pg_backup_dropbox_content($connection_id, 'upload_session/append_v2', array(
                'cursor' => array('session_id' => $session_id, 'offset' => $part[0]),
                'close'  => false,
            ), $data);
        } else {
            $sent = pg_backup_dropbox_content($connection_id, 'upload_session/finish', array(
                'cursor' => array('session_id' => $session_id, 'offset' => $part[0]),
                'commit' => $commit,
            ), $data);
        }

        unset($data);

        if (!$sent['ok']) {
            return array('ok' => false, 'error' => pg_backup_cloud_error($sent));
        }
    }

    return array('ok' => true, 'error' => '');
}
