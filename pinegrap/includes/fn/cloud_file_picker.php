<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The cloud file picker of the Upload File screen (add_file.php): a file
 * from the person's Google Drive or Dropbox goes into the file library.
 *
 * The browser opens the provider's own picker (the Google Picker, the
 * Dropbox Chooser) and hands back only the chosen file's id or link. The
 * server downloads the bytes - Google with the person's own connection
 * (purpose file_picker, includes/connect/purposes/file_picker.php), Dropbox
 * from the Chooser's direct link, which needs no token - into a temporary
 * file, never past the biggest single upload the server accepts, and passes
 * that file to pg_file_store_upload() (includes/fn/files.php), the function
 * every browser upload goes through. Every download goes through
 * api_http_request(), which refuses an address inside the network.
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
// Rules that need no connection (tests/cloud_file_picker_test.php)
// ----------------------------------------------------------------------------

/**
 * The providers the picker offers, in the order the screen shows them.
 * account: whether the person must have connected an account for it.
 *
 * @return array key => label, account
 */
function pg_cloud_picker_providers()
{
    return array(
        'google'  => array('label' => 'Google Drive', 'account' => true),
        'dropbox' => array('label' => 'Dropbox', 'account' => false),
    );
}

/**
 * The Google Cloud project number the Picker asks for as its app id: the
 * digits a web client ID starts with ("123456789012-abc.apps.googleusercontent.com").
 *
 * @param string $client_id
 * @return string '' when the client ID does not carry one
 */
function pg_cloud_picker_google_app_id($client_id)
{
    return (preg_match('/^([0-9]{5,20})-[a-z0-9]+\.apps\.googleusercontent\.com$/', trim((string) $client_id), $match) === 1)
        ? $match[1]
        : '';
}

/**
 * Where the chosen file is read from, from what the browser sent.
 *
 * Google: a Drive file id; its metadata (name, type, size) is read first,
 * then its bytes, both with the person's token. Dropbox: the Chooser's
 * direct link, only on Dropbox's own download host, read without a token;
 * its name and size come from the Chooser and the download is held to the
 * size limit whatever they say.
 *
 * @param string $provider
 * @param array  $choice   id (Google) or link, name, bytes (Dropbox)
 * @return array ok, error, url, meta_url, token (bool), name, size
 */
function pg_cloud_picker_source($provider, $choice)
{
    $source = array('ok' => false, 'error' => '', 'url' => '', 'meta_url' => '', 'token' => false, 'name' => '', 'size' => 0);

    $choice = is_array($choice) ? $choice : array();

    if ($provider === 'google') {
        $id = isset($choice['id']) ? (string) $choice['id'] : '';

        if (preg_match('/^[A-Za-z0-9_-]{10,200}$/', $id) !== 1) {
            $source['error'] = lang('The chosen file could not be found.');

            return $source;
        }

        $base = 'https://www.googleapis.com/drive/v3/files/' . $id;

        $source['ok'] = true;
        $source['token'] = true;
        $source['meta_url'] = $base . '?fields=name%2CmimeType%2Csize&supportsAllDrives=true';
        $source['url'] = $base . '?alt=media&supportsAllDrives=true';

        return $source;
    }

    if ($provider === 'dropbox') {
        $link = isset($choice['link']) ? trim((string) $choice['link']) : '';
        $parts = @parse_url($link);
        $host = (is_array($parts) && isset($parts['host'])) ? strtolower($parts['host']) : '';

        // Only Dropbox's download host: anything else would let the browser
        // name an address for the server to fetch.
        if (!is_array($parts) || (strtolower($parts['scheme'] ?? '') !== 'https')
            || isset($parts['user']) || isset($parts['port'])
            || (($host !== 'dl.dropboxusercontent.com') && (substr($host, -strlen('.dl.dropboxusercontent.com')) !== '.dl.dropboxusercontent.com'))) {
            $source['error'] = lang('The chosen file could not be found.');

            return $source;
        }

        $source['ok'] = true;
        $source['url'] = $link;
        $source['name'] = pg_basename(isset($choice['name']) ? (string) $choice['name'] : '');
        $source['size'] = isset($choice['bytes']) ? max(0, (int) $choice['bytes']) : 0;

        return $source;
    }

    $source['error'] = lang('This kind of connection is not available.');

    return $source;
}

/**
 * The name and size of a Drive file from its metadata, or the reason it
 * cannot be taken: a Google Docs, Sheets or Slides file has no bytes of its
 * own to download.
 *
 * @param array|null $data the decoded answer of the metadata request
 * @return array ok, error, name, size
 */
function pg_cloud_picker_google_meta($data)
{
    $data = is_array($data) ? $data : array();
    $name = pg_basename(isset($data['name']) ? (string) $data['name'] : '');
    $type = isset($data['mimeType']) ? (string) $data['mimeType'] : '';

    if ($name === '') {
        return array('ok' => false, 'error' => lang('The chosen file could not be found.'), 'name' => '', 'size' => 0);
    }

    if (strpos($type, 'application/vnd.google-apps.') === 0) {
        return array('ok' => false, 'name' => $name, 'size' => 0, 'error' => lang(array(
            'string' => '{var:1} is a Google Docs, Sheets or Slides file. Download it from Google Drive in another format and upload that.',
            'vars'   => array($name))));
    }

    return array('ok' => true, 'error' => '', 'name' => $name, 'size' => isset($data['size']) ? max(0, (int) $data['size']) : 0);
}

/**
 * The sentence refusing a file over the limit, or '' when it fits.
 *
 * @param string $name
 * @param int    $size bytes
 * @param int    $max  bytes; 0 is no limit
 * @return string
 */
function pg_cloud_picker_size_problem($name, $size, $max)
{
    if (((int) $max <= 0) || ((int) $size <= (int) $max)) {
        return '';
    }

    return lang(array(
        'string' => '{var:1} is larger than {var:2}, the biggest single file this server accepts.',
        'vars'   => array($name, convert_bytes_to_string((int) $max, 1))));
}

/**
 * Writes one piece of a download to its file, unless it would take the file
 * past $max. Answers false to end the transfer.
 *
 * @param resource $handle
 * @param string   $chunk
 * @param int      $written bytes written so far, updated
 * @param bool     $over    set when the limit stopped it
 * @param int      $max     0 is no limit
 * @return bool
 */
function pg_cloud_picker_write($handle, $chunk, &$written, &$over, $max)
{
    $length = strlen($chunk);

    if (((int) $max > 0) && (($written + $length) > (int) $max)) {
        $over = true;

        return false;
    }

    if (($length > 0) && (fwrite($handle, $chunk) !== $length)) {
        return false;
    }

    $written += $length;

    return true;
}


// ----------------------------------------------------------------------------
// Downloading
// ----------------------------------------------------------------------------

/**
 * Streams one address into a file, never past $max bytes.
 *
 * @param string $url
 * @param array  $headers "Name: value" strings
 * @param int    $max     bytes; 0 is no limit
 * @param string $path    the file to write; emptied first
 * @return array ok, status, size, over (bool), error
 */
function pg_cloud_picker_download($url, $headers, $max, $path)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $outcome = array('ok' => false, 'status' => 0, 'size' => 0, 'over' => false, 'error' => '');

    $handle = @fopen($path, 'wb');

    if ($handle === false) {
        $outcome['error'] = lang('The server could not write a temporary file.');

        return $outcome;
    }

    $written = 0;
    $over = false;

    $result = api_http_request('GET', $url, array(
        'headers'  => $headers,
        'timeout'  => 600,
        'max_body' => 2000,
        'agent'    => 'connect',
        'write'    => function ($chunk) use ($handle, &$written, &$over, $max) {
            return pg_cloud_picker_write($handle, $chunk, $written, $over, $max);
        },
    ));

    fclose($handle);

    $outcome['status'] = (int) $result['status'];
    $outcome['size'] = $written;
    $outcome['over'] = $over;
    $outcome['ok'] = !$over && $result['ok'];
    $outcome['error'] = $over ? '' : (string) $result['error'];

    return $outcome;
}

/**
 * Downloads the chosen file into a temporary file within the upload limit.
 * The caller passes the file to pg_file_store_upload() and deletes it.
 *
 * @param string $provider
 * @param array  $choice        what the browser sent
 * @param int    $connection_id the person's connection (Google), else 0
 * @param int    $max           bytes; 0 is no limit
 * @return array ok, message, path, name, size
 */
function pg_cloud_picker_fetch($provider, $choice, $connection_id, $max)
{
    $fail = function ($message, $path = '') {
        if (($path !== '') && is_file($path)) {
            @unlink($path);
        }

        return array('ok' => false, 'message' => $message, 'path' => '', 'name' => '', 'size' => 0);
    };

    $labels = pg_cloud_picker_providers();
    $label = isset($labels[$provider]) ? $labels[$provider]['label'] : $provider;

    $source = pg_cloud_picker_source($provider, $choice);

    if (!$source['ok']) {
        return $fail($source['error']);
    }

    $name = $source['name'];
    $size = $source['size'];

    if ($source['meta_url'] !== '') {
        $meta = pg_connect_api($connection_id, 'GET', $source['meta_url'], array('max_body' => 65536));

        if (!$meta['ok']) {
            return $fail(pg_cloud_picker_failed_message($label, $meta));
        }

        $taken = pg_cloud_picker_google_meta($meta['data']);

        if (!$taken['ok']) {
            return $fail($taken['error']);
        }

        $name = $taken['name'];
        $size = $taken['size'];
    }

    if ($name === '') {
        return $fail(lang('The chosen file could not be found.'));
    }

    // Refused before a byte is fetched when the size is known up front.
    $problem = pg_cloud_picker_size_problem($name, $size, $max);

    if ($problem !== '') {
        return $fail($problem);
    }

    $path = @tempnam(sys_get_temp_dir(), 'pgpick');

    if (!is_string($path) || ($path === '')) {
        return $fail(lang('The server could not write a temporary file.'));
    }

    @set_time_limit(660);

    $download = array();

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $headers = array();

        if ($source['token']) {
            $token = pg_connect_access_token($connection_id, array(), $attempt > 0);

            if (!$token['ok']) {
                return $fail($token['error'], $path);
            }

            $headers[] = 'Authorization: Bearer ' . $token['token'];
        }

        $download = pg_cloud_picker_download($source['url'], $headers, $max, $path);

        // A token that ran out between the two requests is renewed once.
        if (!$source['token'] || ($download['status'] !== 401)) {
            break;
        }
    }

    if ($download['over']) {
        return $fail(pg_cloud_picker_size_problem($name, $max + 1, $max), $path);
    }

    if (!$download['ok']) {
        return $fail(pg_cloud_picker_failed_message($label, $download), $path);
    }

    return array('ok' => true, 'message' => '', 'path' => $path, 'name' => $name, 'size' => (int) $download['size']);
}

/**
 * The sentence for a request to the provider that failed.
 *
 * @param string $label
 * @param array  $result status, error
 * @return string
 */
function pg_cloud_picker_failed_message($label, $result)
{
    $reason = ((string) ($result['error'] ?? '') !== '') ? (string) $result['error'] : ('HTTP ' . (int) ($result['status'] ?? 0));

    return lang(array(
        'string' => 'The file could not be downloaded from {var:1}: {var:2}',
        'vars'   => array($label, $reason)));
}


// ----------------------------------------------------------------------------
// The screen
// ----------------------------------------------------------------------------

/**
 * The person's connection for a provider, or 0.
 *
 * @param string $provider
 * @param int    $user_id
 * @return int
 */
function pg_cloud_picker_connection($provider, $user_id)
{
    foreach (pg_connect_accounts(array('provider' => $provider, 'user_id' => (int) $user_id, 'purpose' => 'file_picker')) as $account) {
        if ($account['status'] === 'active') {
            return (int) $account['id'];
        }
    }

    return 0;
}

/**
 * What the Upload File screen offers this person: the providers they can
 * pick from now (with what the browser's picker needs), and those whose
 * application is set up but whose account they have not connected yet.
 * Nothing set up: both are empty and the screen is as it was.
 *
 * @param int $user_id
 * @return array pick => key => label, client (the browser's settings);
 *               connect => key => label
 */
function pg_cloud_picker_choices($user_id)
{
    $choices = array('pick' => array(), 'connect' => array());

    if (!pg_connect_schema_ready()) {
        return $choices;
    }

    foreach (pg_cloud_picker_providers() as $key => $provider) {
        $app = pg_connect_app($key);

        if (!pg_connect_app_ready($app)) {
            continue;
        }

        if ($key === 'dropbox') {
            $choices['pick'][$key] = array('label' => $provider['label'], 'client' => array('app_key' => $app['client_id']));

            continue;
        }

        // The Google Picker needs the browser API key and the project number
        // besides the person's token.
        $api_key = isset($app['options']['picker_api_key']) ? trim((string) $app['options']['picker_api_key']) : '';
        $app_id = pg_cloud_picker_google_app_id($app['client_id']);

        if (($api_key === '') || ($app_id === '') || !pg_connect_can_seal()) {
            continue;
        }

        if (pg_cloud_picker_connection($key, $user_id) > 0) {
            $choices['pick'][$key] = array('label' => $provider['label'], 'client' => array('api_key' => $api_key, 'app_id' => $app_id));
        } else {
            $choices['connect'][$key] = $provider['label'];
        }
    }

    return $choices;
}

/**
 * Whether the request carries the session's form token. The picker's
 * requests answer in JSON, so a refusal is said in JSON too
 * (validate_token_field() prints a page).
 *
 * @return bool
 */
function pg_cloud_picker_token_valid()
{
    $session = (string) ($_SESSION['software']['token'] ?? '');
    $posted = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

    return ($session !== '') && hash_equals($session, $posted);
}

/**
 * Prints a JSON answer and ends the request.
 *
 * @param int   $status
 * @param array $data
 */
function pg_cloud_picker_json($status, $data)
{
    http_response_code((int) $status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo encode_json($data);
    exit();
}

/**
 * The Google Picker's access token for this person (cloud_action=token).
 * It is the person's own drive.file token, which the Picker needs in the
 * browser to show their Drive.
 *
 * @param array $user
 */
function pg_cloud_picker_token_answer($user)
{
    if (!pg_cloud_picker_token_valid()) {
        pg_cloud_picker_json(403, array('status' => 'error', 'message' => lang('Your session expired. Reload the page and try again.')));
    }

    $connection_id = pg_cloud_picker_connection('google', (int) $user['id']);

    if ($connection_id === 0) {
        pg_cloud_picker_json(404, array('status' => 'error', 'message' => lang('Connect your Google Drive account first.')));
    }

    $token = pg_connect_access_token($connection_id);

    if (!$token['ok']) {
        pg_cloud_picker_json(502, array('status' => 'error', 'message' => $token['error']));
    }

    pg_cloud_picker_json(200, array('status' => 'success', 'token' => $token['token']));
}
