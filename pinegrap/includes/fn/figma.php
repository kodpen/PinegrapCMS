<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Figma: the personal access token pasted on Connections
 * (connections.php?service=figma), sealed as a service entry of
 * connect_accounts and never sent to the browser, and the reads the New
 * design window's Figma start makes with it: whose token it is, a file's
 * pages and top frames (GET file, depth 2) and the frames' small pictures
 * (GET images). Every call is the server's. A file read is kept in the
 * session for the length of one import, so "Another file", "Back" and a
 * retry spend no call of Figma's monthly allowance.
 *
 * The pure parts (the token's form, a file link, an answer's error, the
 * frame list) are tested in tests/figma_test.php.
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
 * The most frames one import takes, as the frame list says it.
 */
function pg_figma_max_frames()
{
    return 12;
}

/**
 * Whether a pasted token has the form of a Figma personal access token
 * (figd_ and letters, digits, dashes, underscores; older tokens have no
 * prefix). Only the form: Figma decides whether it works. Pure.
 *
 * @param mixed $token
 * @return bool
 */
function pg_figma_token_valid($token)
{
    return is_string($token) && (bool) preg_match('/^[A-Za-z0-9_\-]{20,250}$/', $token);
}

/**
 * A Figma file's key and the frame its link points at, from a link copied
 * with Share › "Copy link" (/design/, /file/ or /proto/, on figma.com). The
 * node-id of a link is written with "-" where the API writes ":". Pure.
 *
 * @param mixed $link
 * @return array|null array('key', 'node'), null for anything else
 */
function pg_figma_file_link($link)
{
    if (!is_string($link)) {
        return null;
    }

    $link = trim($link);

    if (!preg_match('~^https://(?:www\.)?figma\.com/(?:design|file|proto)/([A-Za-z0-9]{10,64})(?:[/?#]|$)~', $link, $m)) {
        return null;
    }

    $node = '';
    $query = (string) parse_url($link, PHP_URL_QUERY);

    if ($query !== '') {
        parse_str($query, $params);

        if (isset($params['node-id']) && is_string($params['node-id']) && preg_match('/^\d+[-:]\d+$/', $params['node-id'])) {
            $node = str_replace('-', ':', $params['node-id']);
        }
    }

    return array('key' => $m[1], 'node' => $node);
}

/**
 * What a Figma answer that is not a success means for the person, as one
 * kind. Pure.
 *
 * - token: the token is refused (expired, removed, mistyped)
 * - scope: the token works but may not read files (no File content scope)
 * - access: no such file, or the token's account cannot open it
 * - limit: Figma's rate limit; 'retry' is the wait in seconds
 * - network: Figma did not answer
 * - other: anything else
 *
 * @param int    $status  the HTTP status, 0 when nothing came back
 * @param string $body    the answer's body
 * @param array  $headers lower-case name => value
 * @return array array('kind', 'retry')
 */
function pg_figma_answer_error($status, $body, $headers = array())
{
    $status = (int) $status;
    $said = '';
    $decoded = json_decode((string) $body, true);

    if (is_array($decoded)) {
        foreach (array('err', 'message') as $field) {
            if (isset($decoded[$field]) && is_string($decoded[$field])) {
                $said = strtolower($decoded[$field]);
                break;
            }
        }
    }

    if ($status === 0) {
        return array('kind' => 'network', 'retry' => 0);
    }

    if ($status === 429) {
        $retry = isset($headers['retry-after']) ? (int) $headers['retry-after'] : 0;

        return array('kind' => 'limit', 'retry' => max(0, $retry));
    }

    if ($status === 403) {
        if (strpos($said, 'scope') !== false) {
            return array('kind' => 'scope', 'retry' => 0);
        }

        if (($said === '') || (strpos($said, 'token') !== false) || (strpos($said, 'expired') !== false)) {
            return array('kind' => 'token', 'retry' => 0);
        }

        return array('kind' => 'access', 'retry' => 0);
    }

    if (($status === 401)) {
        return array('kind' => 'token', 'retry' => 0);
    }

    if ($status === 404) {
        return array('kind' => 'access', 'retry' => 0);
    }

    if ($status >= 500) {
        return array('kind' => 'network', 'retry' => 0);
    }

    return array('kind' => 'other', 'retry' => 0);
}

/**
 * The frame list of a file, from Figma's answer to GET file with depth 2:
 * the file's pages, each with its top frames (frames and components) and
 * their sizes. Ticked when the list opens: the frames of the first page
 * that has frames which are as wide as a desktop screen (1024 px or more),
 * at most the most of one import. Pure.
 *
 * @param array $file the decoded answer
 * @param int   $max  the most frames one import takes
 * @return array|null array('name', 'pages' => list of array('id', 'name',
 *                    'frames' => list of array('id', 'name', 'width',
 *                    'height', 'ticked'))), 'page_count', 'frame_count');
 *                    null when the answer is no file
 */
function pg_figma_frame_list($file, $max = 12)
{
    if (!is_array($file) || !isset($file['document']['children']) || !is_array($file['document']['children'])) {
        return null;
    }

    $list = array(
        'name'        => isset($file['name']) && is_string($file['name']) ? $file['name'] : '',
        'pages'       => array(),
        'page_count'  => 0,
        'frame_count' => 0,
    );

    $ticking = true;
    $ticked = 0;

    foreach ($file['document']['children'] as $canvas) {
        if (!is_array($canvas) || !isset($canvas['type']) || ($canvas['type'] !== 'CANVAS')) {
            continue;
        }

        $page = array(
            'id'     => isset($canvas['id']) ? (string) $canvas['id'] : '',
            'name'   => isset($canvas['name']) ? (string) $canvas['name'] : '',
            'frames' => array(),
        );

        foreach ((isset($canvas['children']) && is_array($canvas['children'])) ? $canvas['children'] : array() as $node) {
            if (!is_array($node) || !isset($node['type']) || !in_array($node['type'], array('FRAME', 'COMPONENT'), true)) {
                continue;
            }

            if (isset($node['visible']) && ($node['visible'] === false)) {
                continue;
            }

            $box = (isset($node['absoluteBoundingBox']) && is_array($node['absoluteBoundingBox'])) ? $node['absoluteBoundingBox'] : array();
            $width = isset($box['width']) ? (int) round((float) $box['width']) : 0;
            $height = isset($box['height']) ? (int) round((float) $box['height']) : 0;

            $page['frames'][] = array(
                'id'     => isset($node['id']) ? (string) $node['id'] : '',
                'name'   => isset($node['name']) ? (string) $node['name'] : '',
                'width'  => $width,
                'height' => $height,
                'ticked' => false,
            );
        }

        // The first page with frames gives the frames ticked at the start.
        if ($ticking && $page['frames']) {
            foreach ($page['frames'] as $i => $frame) {
                if (($frame['width'] >= 1024) && ($ticked < $max)) {
                    $page['frames'][$i]['ticked'] = true;
                    $ticked++;
                }
            }

            $ticking = false;
        }

        $list['pages'][] = $page;
        $list['page_count']++;
        $list['frame_count'] += count($page['frames']);
    }

    return $list;
}

/**
 * The frame ids of a frame list, in its order. Pure.
 *
 * @param array $list pg_figma_frame_list()
 * @return array
 */
function pg_figma_frame_ids($list)
{
    $ids = array();

    foreach ((array) $list['pages'] as $page) {
        foreach ($page['frames'] as $frame) {
            if ($frame['id'] !== '') {
                $ids[] = $frame['id'];
            }
        }
    }

    return $ids;
}

/**
 * The site's Figma connection as stored: the token, the account's name (only
 * the name: never its e-mail address or picture), whether it was tried and
 * when. Empty when no token is stored.
 *
 * @return array token, name, state (untried | ok | refused), tried
 */
function pg_figma_connection()
{
    if (!function_exists('pg_connect_service_get')) {
        return array();
    }

    $stored = pg_connect_service_get('figma');

    if (!is_array($stored) || !isset($stored['token']) || ((string) $stored['token'] === '')) {
        return array();
    }

    return array(
        'token' => (string) $stored['token'],
        'name'  => isset($stored['name']) ? (string) $stored['name'] : '',
        'state' => (isset($stored['state']) && in_array($stored['state'], array('untried', 'ok', 'refused'), true)) ? $stored['state'] : 'untried',
        'tried' => isset($stored['tried']) ? (int) $stored['tried'] : 0,
    );
}

/**
 * Whether a token is stored. The registry's state reader.
 *
 * @return bool
 */
function pg_figma_connected()
{
    return pg_figma_connection() !== array();
}

/**
 * Stores the connection sealed (pg_connect_service_put()).
 *
 * @param array $connection token, name, state, tried
 * @return bool false when nothing could be sealed
 */
function pg_figma_connection_store($connection)
{
    return pg_connect_service_put('figma', ($connection['name'] !== '') ? $connection['name'] : 'Figma', array(
        'token' => (string) $connection['token'],
        'name'  => (string) $connection['name'],
        'state' => (string) $connection['state'],
        'tried' => (int) $connection['tried'],
    ));
}

/**
 * Removes the stored token. The token stays valid in Figma until it is
 * removed there.
 */
function pg_figma_connection_remove()
{
    pg_connect_service_delete('figma');
}

/**
 * Marks the stored token as no longer accepted, once Figma refused a read.
 */
function pg_figma_connection_refused()
{
    $connection = pg_figma_connection();

    if ($connection && ($connection['state'] !== 'refused')) {
        $connection['state'] = 'refused';
        $connection['tried'] = time();
        pg_figma_connection_store($connection);
    }
}

/**
 * One GET of Figma's REST API with the token in X-Figma-Token. The token is
 * in no log and no answer.
 *
 * @param string $path    after https://api.figma.com
 * @param string $token
 * @param int    $timeout seconds
 * @return array array('ok', 'data', 'error' => array('kind', 'retry'))
 */
function pg_figma_get($path, $token, $timeout = 20)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $answer = api_http_request('GET', 'https://api.figma.com' . $path, array(
        'headers'          => array('X-Figma-Token: ' . $token, 'Accept: application/json'),
        'timeout'          => $timeout,
        'max_body'         => 0,
        'agent'            => 'figma',
        'decode'           => true,
        'response_headers' => true,
    ));

    $status = (int) $answer['status'];

    if ($answer['ok'] && ($status >= 200) && ($status < 300)) {
        $data = json_decode((string) $answer['body'], true);

        if (is_array($data)) {
            return array('ok' => true, 'data' => $data, 'error' => null);
        }

        return array('ok' => false, 'data' => null, 'error' => array('kind' => 'other', 'retry' => 0));
    }

    return array('ok' => false, 'data' => null, 'error' => pg_figma_answer_error($status, (string) $answer['body'], isset($answer['headers']) ? $answer['headers'] : array()));
}

/**
 * Asks Figma whose token it is (GET /v1/me). A token without the scope
 * "Current user" is refused there for its scope: it is kept, with no name.
 *
 * @param string $token
 * @return array array('ok', 'name', 'error')
 */
function pg_figma_try_token($token)
{
    $me = pg_figma_get('/v1/me', $token, 10);

    if ($me['ok']) {
        $name = isset($me['data']['handle']) && is_string($me['data']['handle']) ? $me['data']['handle'] : '';

        return array('ok' => true, 'name' => function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120), 'error' => null);
    }

    if ($me['error']['kind'] === 'scope') {
        return array('ok' => true, 'name' => '', 'error' => null);
    }

    return array('ok' => false, 'name' => '', 'error' => $me['error']);
}

/**
 * A file's frame list, read once per import: kept in the session under the
 * file's key, so asking again spends no call.
 *
 * @param string $key a file key (pg_figma_file_link())
 * @return array array('ok', 'list', 'error')
 */
function pg_figma_read_file($key)
{
    $connection = pg_figma_connection();

    if (!$connection) {
        return array('ok' => false, 'list' => null, 'error' => array('kind' => 'not_connected', 'retry' => 0));
    }

    if (isset($_SESSION['pg_figma_files'][$key]['list'])) {
        return array('ok' => true, 'list' => $_SESSION['pg_figma_files'][$key]['list'], 'error' => null);
    }

    $read = pg_figma_get('/v1/files/' . rawurlencode($key) . '?depth=2', $connection['token'], 30);

    if (!$read['ok']) {
        if ($read['error']['kind'] === 'token') {
            pg_figma_connection_refused();
        }

        return array('ok' => false, 'list' => null, 'error' => $read['error']);
    }

    $list = pg_figma_frame_list($read['data'], pg_figma_max_frames());

    if ($list === null) {
        return array('ok' => false, 'list' => null, 'error' => array('kind' => 'other', 'retry' => 0));
    }

    if ($connection['state'] !== 'ok') {
        $connection['state'] = 'ok';
        $connection['tried'] = time();
        pg_figma_connection_store($connection);
    }

    // One file is kept at a time: the window works on one import.
    $_SESSION['pg_figma_files'] = array($key => array('list' => $list));

    return array('ok' => true, 'list' => $list, 'error' => null);
}

/**
 * The small pictures of a file's frames, in one GET images call, kept in
 * the session beside the file's frame list. Figma's links last 30 days.
 *
 * @param string $key a file key read by pg_figma_read_file()
 * @return array array('ok', 'pictures' => frame id => address, 'error')
 */
function pg_figma_read_pictures($key)
{
    $connection = pg_figma_connection();

    if (!$connection || !isset($_SESSION['pg_figma_files'][$key]['list'])) {
        return array('ok' => false, 'pictures' => array(), 'error' => array('kind' => 'not_connected', 'retry' => 0));
    }

    if (isset($_SESSION['pg_figma_files'][$key]['pictures'])) {
        return array('ok' => true, 'pictures' => $_SESSION['pg_figma_files'][$key]['pictures'], 'error' => null);
    }

    // The first 100 frames: a longer address is refused, and nobody ticks
    // more than a screenful.
    $ids = array_slice(pg_figma_frame_ids($_SESSION['pg_figma_files'][$key]['list']), 0, 100);
    $pictures = array();

    if ($ids) {
        $read = pg_figma_get('/v1/images/' . rawurlencode($key) . '?format=png&scale=0.2&ids=' . rawurlencode(implode(',', $ids)), $connection['token'], 45);

        if (!$read['ok']) {
            return array('ok' => false, 'pictures' => array(), 'error' => $read['error']);
        }

        foreach ((isset($read['data']['images']) && is_array($read['data']['images'])) ? $read['data']['images'] : array() as $id => $url) {
            if (is_string($url) && (strncmp($url, 'https://', 8) === 0)) {
                $pictures[(string) $id] = $url;
            }
        }
    }

    $_SESSION['pg_figma_files'][$key]['pictures'] = $pictures;

    return array('ok' => true, 'pictures' => $pictures, 'error' => null);
}
