<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - OneNote pages brought in as notes through Microsoft Graph,
 * with the person's own Microsoft account (connection purpose notes,
 * includes/connect/purposes/notes.php).
 *
 * The person opens a notebook's section, picks pages, and each page picked
 * is laid out in a stage as a Markdown file, as notes_notion.php lays out a
 * Notion page: its HTML read by the web page import (notes_html.php), its
 * pictures downloaded here on the server with the account's token and kept
 * as the note's files, its title, dates and page id in the front matter.
 * From there the import is the file import's (notes_import.php): the same
 * preview, duplicate rules (ws_note_sources, provider onenote), limits,
 * batches and links.
 *
 * Every call goes through pg_connect_api() (the token renewed on a 401) and
 * only to graph.microsoft.com. Graph throttles OneNote hard: a 429 or 503
 * whose Retry-After is short is waited out once, a longer one is told to
 * the person.
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
 * How many pages one pick may bring in: every page is two requests and its
 * pictures while the person waits.
 */
define('WS_NOTE_ONENOTE_PICK_MAX', 25);

/**
 * How many sections or pages a list shows.
 */
define('WS_NOTE_ONENOTE_LIST_MAX', 500);

/**
 * How many seconds of Retry-After are waited out before a call is repeated;
 * a longer wait goes back to the person.
 */
define('WS_NOTE_ONENOTE_WAIT_MAX', 5);

/**
 * Graph's address, the start of every call.
 */
define('WS_NOTE_ONENOTE_GRAPH', 'https://graph.microsoft.com/v1.0');

/**
 * The scopes the calls need: read-only access to the person's notebooks.
 *
 * @return string[]
 */
function ws_note_onenote_scopes()
{
    return array('https://graph.microsoft.com/Notes.Read');
}

/* ---------------------------------------------------------------------------
   States, ids and addresses
   --------------------------------------------------------------------------- */

/**
 * Where the person stands with OneNote: the site's Microsoft application
 * missing, their own account not connected, or ready.
 *
 * @param bool $app_ready     the Microsoft application is set up and tokens can be sealed
 * @param int  $connection_id the person's active connection for notes, 0 if none
 * @return array state (missing, not_connected, ready), message, connect (the
 *               Connect form's fields, null when there is nothing to connect)
 */
function ws_note_onenote_state($app_ready, $connection_id)
{
    if (!$app_ready) {
        return array(
            'state'   => 'missing',
            'message' => lang('OneNote is not set up on this site. An administrator sets up the Microsoft application under Settings › Connections.'),
            'connect' => null,
        );
    }

    if ((int) $connection_id <= 0) {
        return array(
            'state'   => 'not_connected',
            'message' => lang('Connect your own Microsoft account to bring your OneNote pages in.'),
            'connect' => array(
                'provider' => 'microsoft',
                'purpose'  => 'notes',
                'owner'    => 'person',
                'return'   => 'workspace_notes.php',
            ),
        );
    }

    return array('state' => 'ready', 'message' => '', 'connect' => null);
}

/**
 * A OneNote id (notebook, section, page) as Graph gives it, e.g.
 * "0-8f2a1b!12"; '' when the value is not one.
 *
 * @param mixed $value
 * @return string
 */
function ws_note_onenote_id($value)
{
    return (is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9!_.\-]{0,199}$/', $value)) ? $value : '';
}

/**
 * Whether an address is Graph's: https, the host itself, no user or port.
 * Next links and picture addresses come from Graph's answers and are
 * followed with the account's token, so nothing else may be.
 *
 * @param mixed $url
 * @return bool
 */
function ws_note_onenote_graph_url($url)
{
    return is_string($url) && (bool) preg_match('#^https://graph\.microsoft\.com/[^\s]*$#', $url);
}

/**
 * Text on one line, at most 200 characters.
 *
 * @param mixed $text
 * @return string
 */
function ws_note_onenote_line($text)
{
    return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', is_string($text) ? $text : '')), 0, 200);
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

/**
 * How many seconds a throttled answer asks to wait: Retry-After in seconds
 * or as a date, else half a minute.
 *
 * @param array    $result pg_connect_api() with headers
 * @param int|null $now
 * @return int
 */
function ws_note_onenote_retry_after($result, $now = null)
{
    $value = trim((string) ($result['headers']['retry-after'] ?? ''));

    if (preg_match('/^\d{1,6}$/', $value)) {
        return (int) $value;
    }

    if ($value !== '') {
        $time = strtotime($value);

        if ($time !== false) {
            return max(0, $time - (($now !== null) ? (int) $now : time()));
        }
    }

    return 30;
}

/**
 * One GET to Graph with the person's connection. A throttled answer whose
 * wait is short is waited out and the call made once more.
 *
 * @param int           $connection_id
 * @param string        $url      a Graph address
 * @param int           $max_body bytes of the answer to keep
 * @param callable|null $call     pg_connect_api() or a stand-in
 * @param callable|null $sleep    sleep() or a stand-in
 * @return array ok, error, data (decoded JSON), body, wait (seconds asked
 *               when throttled), reauth (the account must be connected again)
 */
function ws_note_onenote_get($connection_id, $url, $max_body = 1048576, $call = null, $sleep = null)
{
    $out = array('ok' => false, 'error' => '', 'data' => null, 'body' => '', 'wait' => 0, 'reauth' => false);

    if (!ws_note_onenote_graph_url($url)) {
        $out['error'] = lang('The page could not be read.');

        return $out;
    }

    $call = $call ?: 'pg_connect_api';
    $sleep = $sleep ?: 'sleep';
    $options = array('timeout' => 30, 'max_body' => (int) $max_body, 'response_headers' => true);

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $result = $call((int) $connection_id, 'GET', $url, $options, ws_note_onenote_scopes());
        $status = (int) ($result['status'] ?? 0);

        if (!in_array($status, array(429, 503), true)) {
            break;
        }

        $out['wait'] = ws_note_onenote_retry_after($result);

        if (($attempt > 0) || ($out['wait'] > WS_NOTE_ONENOTE_WAIT_MAX)) {
            $out['error'] = lang(array('string' => 'OneNote is busy and asks to wait {var:1} seconds. Try again then.', 'vars' => $out['wait']));

            return $out;
        }

        $sleep($out['wait']);
    }

    if (($status >= 200) && ($status < 300)) {
        $out['ok'] = true;
        $out['wait'] = 0;
        $out['body'] = (string) ($result['body'] ?? '');
        $out['data'] = is_array($result['data'] ?? null) ? $result['data'] : null;

        return $out;
    }

    if ($status === 0) {
        $out['error'] = ((string) ($result['error'] ?? '') !== '') ? (string) $result['error'] : lang('OneNote could not be reached. Try again.');

        return $out;
    }

    if ($status === 401) {
        $out['reauth'] = true;
        $out['error'] = lang('Microsoft no longer accepts the connection: connect your account again.');

        return $out;
    }

    $said = ws_note_onenote_line($result['data']['error']['message'] ?? '');
    $out['error'] = trim(lang(array('string' => 'OneNote answered with an error ({var:1}).', 'vars' => $status)) . ' ' . $said);

    return $out;
}

/**
 * Every item of a list, following @odata.nextLink (on Graph only) until
 * $max items.
 *
 * @param callable $get function (string $url): ws_note_onenote_get()'s answer
 * @param string   $url the first page of the list
 * @param int      $max
 * @return array ok, error, items, more (the list was cut)
 */
function ws_note_onenote_list($get, $url, $max)
{
    $out = array('ok' => false, 'error' => '', 'items' => array(), 'more' => false);
    $seen = array();

    while (ws_note_onenote_graph_url($url) && !isset($seen[$url])) {
        $seen[$url] = true;
        $answer = $get($url);

        if (!$answer['ok']) {
            $out['error'] = $answer['error'];

            return $out;
        }

        foreach ((array) ($answer['data']['value'] ?? array()) as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (count($out['items']) >= $max) {
                $out['more'] = true;
                break 2;
            }

            $out['items'][] = $item;
        }

        $url = $answer['data']['@odata.nextLink'] ?? '';

        if (is_string($url) && ($url !== '') && (count($out['items']) >= $max)) {
            $out['more'] = true;
            break;
        }
    }

    $out['ok'] = true;

    return $out;
}

/* ---------------------------------------------------------------------------
   Reading the lists
   --------------------------------------------------------------------------- */

/**
 * Sections, as GET /me/onenote/sections with their parent notebook gives
 * them, grouped under their notebooks; both sorted by name. A section
 * without its notebook or a valid id is left out.
 *
 * @param array[] $sections
 * @return array[] id, name, sections (id, name)
 */
function ws_note_onenote_notebooks($sections)
{
    $notebooks = array();

    foreach ((array) $sections as $section) {
        $id = ws_note_onenote_id($section['id'] ?? '');
        $book = ws_note_onenote_id($section['parentNotebook']['id'] ?? '');

        if (($id === '') || ($book === '')) {
            continue;
        }

        if (!isset($notebooks[$book])) {
            $notebooks[$book] = array('id' => $book, 'name' => ws_note_onenote_line($section['parentNotebook']['displayName'] ?? ''), 'sections' => array());
        }

        $notebooks[$book]['sections'][$id] = array('id' => $id, 'name' => ws_note_onenote_line($section['displayName'] ?? ''));
    }

    $by_name = function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    };

    foreach ($notebooks as $book => $notebook) {
        $notebooks[$book]['sections'] = array_values($notebook['sections']);
        usort($notebooks[$book]['sections'], $by_name);
    }

    $notebooks = array_values($notebooks);
    usort($notebooks, $by_name);

    return $notebooks;
}

/**
 * Pages of a section, each once: id, title ('' when untitled), when (last
 * changed, 0 when not given).
 *
 * @param array[] $pages
 * @return array[]
 */
function ws_note_onenote_pages($pages)
{
    $out = array();

    foreach ((array) $pages as $page) {
        $id = ws_note_onenote_id($page['id'] ?? '');

        if (($id === '') || isset($out[$id])) {
            continue;
        }

        $out[$id] = array(
            'id'    => $id,
            'title' => ws_note_onenote_line($page['title'] ?? ''),
            'when'  => ws_note_import_time((string) ($page['lastModifiedDateTime'] ?? '')),
        );
    }

    return array_values($out);
}

/* ---------------------------------------------------------------------------
   A page as a note
   --------------------------------------------------------------------------- */

/**
 * The address a picture of the page is downloaded from: its full-size
 * version when given, else its src; only a OneNote resource on Graph.
 *
 * @param string $tag an <img> tag
 * @return string '' when it is not one of the page's own pictures
 */
function ws_note_onenote_image_url($tag)
{
    foreach (array('data-fullres-src', 'src') as $name) {
        if (preg_match('/\s' . $name . '\s*=\s*(["\'])(.*?)\1/is', $tag, $match)) {
            $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (ws_note_onenote_graph_url($url) && preg_match('#/onenote/resources/[^/]+/(?:\$value|content)$#', $url)) {
                return $url;
            }
        }
    }

    return '';
}

/**
 * The page's own pictures (OneNote resources), each address once.
 *
 * @param string $html
 * @return string[]
 */
function ws_note_onenote_image_urls($html)
{
    $urls = array();

    if (preg_match_all('/<img\b[^>]*>/i', (string) $html, $tags)) {
        foreach ($tags[0] as $tag) {
            $url = ws_note_onenote_image_url($tag);

            if ($url !== '') {
                $urls[$url] = true;
            }
        }
    }

    return array_keys($urls);
}

/**
 * The page's HTML with its downloaded pictures inside it as data: addresses,
 * which the web page import keeps as files; a picture of the page that was
 * not downloaded is taken out, so no Graph address is left in the note.
 *
 * @param string $html
 * @param array  $images address => bytes
 * @return string
 */
function ws_note_onenote_inline_images($html, $images)
{
    return preg_replace_callback('/<img\b[^>]*>/i', function ($match) use ($images) {
        $url = ws_note_onenote_image_url($match[0]);

        if ($url === '') {
            return $match[0];
        }

        if (!isset($images[$url]) || !is_string($images[$url]) || ($images[$url] === '')) {
            return '';
        }

        // The web page import checks the bytes' own signature, whatever
        // type is declared here.
        return '<img src="data:image/png;base64,' . base64_encode($images[$url]) . '">';
    }, (string) $html);
}

/**
 * A page as a note: its HTML read by the web page import with its pictures
 * as files, linked under attachments/<number>/.
 *
 * @param array  $meta   the page as Graph lists it (id, title, createdDateTime, lastModifiedDateTime)
 * @param string $html   the page's content
 * @param array  $images address => bytes of the pictures downloaded
 * @param int    $number the note's number in the stage, its files' folder
 * @return array ok, error, title, created, updated, body, files (name, mime, bytes)
 */
function ws_note_onenote_note($meta, $html, $images, $number)
{
    $read = ws_note_html_read(ws_note_onenote_inline_images($html, $images));

    if (!$read['ok']) {
        return $read;
    }

    $title = ws_note_onenote_line($meta['title'] ?? '');
    $read['title'] = ($title !== '') ? $title : ws_note_onenote_line($read['title']);

    foreach (array('created' => 'createdDateTime', 'updated' => 'lastModifiedDateTime') as $key => $name) {
        $time = ws_note_import_time((string) ($meta[$name] ?? ''));
        $read[$key] = ($time > 0) ? $time : $read[$key];
    }

    $from = '(' . ws_note_import_link_path('attachments/1/');
    $read['body'] = str_replace($from, '(' . ws_note_import_link_path('attachments/' . (int) $number . '/'), $read['body']);

    return $read;
}

/**
 * The Markdown file a page is staged as: title, dates and the page id it is
 * known by in the front matter, then its text.
 *
 * @param array  $note ws_note_onenote_note()
 * @param string $id   the page id
 * @return string
 */
function ws_note_onenote_file($note, $id)
{
    return ws_note_enex_file(array(
        'title'   => $note['title'],
        'created' => $note['created'],
        'updated' => $note['updated'],
        'tags'    => array(),
        'id'      => ws_note_source_id('markdown', array('id' => $id)),
    ), $note['body']);
}

/* ---------------------------------------------------------------------------
   The person's account
   --------------------------------------------------------------------------- */

/**
 * The person's active Microsoft connection for notes, 0 if none.
 *
 * @param int $user_id
 * @return int
 */
function ws_note_onenote_connection($user_id)
{
    if (!function_exists('pg_connect_accounts')) {
        return 0;
    }

    foreach (pg_connect_accounts(array('provider' => 'microsoft', 'user_id' => (int) $user_id, 'purpose' => 'notes')) as $account) {
        if ($account['status'] === 'active') {
            return (int) $account['id'];
        }
    }

    return 0;
}

/**
 * Whether the site can offer OneNote at all: the Microsoft application is
 * set up and tokens can be sealed.
 *
 * @return bool
 */
function ws_note_onenote_offered()
{
    return function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()
        && pg_connect_can_seal() && pg_connect_app_ready(pg_connect_app('microsoft'));
}

/**
 * ws_note_onenote_state() for a person, read from the database.
 *
 * @param array $viewer
 * @return array as ws_note_onenote_state(), with connection
 */
function ws_note_onenote_status($viewer)
{
    $offered = ws_note_onenote_offered();
    $connection = $offered ? ws_note_onenote_connection((int) $viewer['id']) : 0;

    return ws_note_onenote_state($offered, $connection) + array('connection' => $connection);
}

/**
 * The person's notebooks and their sections, for the picker.
 *
 * @param array $viewer
 * @return array ok, error, notebooks, reauth
 */
function ws_note_onenote_sections($viewer)
{
    $status = ws_note_onenote_status($viewer);

    if ($status['state'] !== 'ready') {
        return array('ok' => false, 'error' => $status['message'], 'notebooks' => array(), 'reauth' => false);
    }

    $reauth = false;
    $get = function ($url) use ($status, &$reauth) {
        $answer = ws_note_onenote_get($status['connection'], $url);
        $reauth = $reauth || $answer['reauth'];

        return $answer;
    };
    $list = ws_note_onenote_list($get, WS_NOTE_ONENOTE_GRAPH . '/me/onenote/sections?$select=id,displayName&$expand=parentNotebook($select=id,displayName)&$top=100', WS_NOTE_ONENOTE_LIST_MAX);

    if (!$list['ok']) {
        return array('ok' => false, 'error' => $list['error'], 'notebooks' => array(), 'reauth' => $reauth);
    }

    return array('ok' => true, 'error' => '', 'notebooks' => ws_note_onenote_notebooks($list['items']), 'reauth' => false);
}

/**
 * The pages of one section, newest first, for the picker.
 *
 * @param array  $viewer
 * @param string $section
 * @return array ok, error, pages, more, reauth
 */
function ws_note_onenote_section_pages($viewer, $section)
{
    $section = ws_note_onenote_id($section);
    $status = ws_note_onenote_status($viewer);

    if ($status['state'] !== 'ready') {
        return array('ok' => false, 'error' => $status['message'], 'pages' => array(), 'more' => false, 'reauth' => false);
    }

    if ($section === '') {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'pages' => array(), 'more' => false, 'reauth' => false);
    }

    $reauth = false;
    $get = function ($url) use ($status, &$reauth) {
        $answer = ws_note_onenote_get($status['connection'], $url);
        $reauth = $reauth || $answer['reauth'];

        return $answer;
    };
    $list = ws_note_onenote_list($get, WS_NOTE_ONENOTE_GRAPH . '/me/onenote/sections/' . rawurlencode($section) . '/pages?$select=id,title,lastModifiedDateTime&$orderby=lastModifiedDateTime%20desc&$top=100', WS_NOTE_ONENOTE_LIST_MAX);

    if (!$list['ok']) {
        return array('ok' => false, 'error' => $list['error'], 'pages' => array(), 'more' => false, 'reauth' => $reauth);
    }

    return array('ok' => true, 'error' => '', 'pages' => ws_note_onenote_pages($list['items']), 'more' => $list['more'], 'reauth' => false);
}

/* ---------------------------------------------------------------------------
   The stage
   --------------------------------------------------------------------------- */

/**
 * Downloads the page's pictures: each up to ws_upload_max_bytes(), all of
 * them within what is left of $budget. One that fails or is too large is
 * left out.
 *
 * @param int    $connection
 * @param string $html
 * @param int    $budget bytes left for the import's pictures; lowered here
 * @return array address => bytes
 */
function ws_note_onenote_download_images($connection, $html, &$budget)
{
    $file_max = function_exists('ws_upload_max_bytes') ? ws_upload_max_bytes() : 20971520;
    $images = array();

    foreach (ws_note_onenote_image_urls($html) as $url) {
        if ($budget <= 0) {
            break;
        }

        $limit = min($file_max, $budget);
        $answer = ws_note_onenote_get($connection, $url, $limit + 1);

        if ($answer['ok'] && ($answer['body'] !== '') && (strlen($answer['body']) <= $limit)) {
            $images[$url] = $answer['body'];
            $budget -= strlen($answer['body']);
        }
    }

    return $images;
}

/**
 * Fetches the pages picked and lays them out in a stage as Markdown files
 * and their pictures, as notes_notion.php does: from there the file
 * import's plan, batches and finish take them. A page that cannot be read
 * is left out and named in the warnings; nothing is written as a note yet.
 *
 * @param array    $viewer
 * @param string[] $ids   page ids, as the picker listed them
 * @param string   $token the stage's name
 * @return array ok, error, token, warnings
 */
function ws_note_onenote_stage($viewer, $ids, $token)
{
    $fail = function ($error, $dir = '') {
        ws_note_import_remove($dir);

        return array('ok' => false, 'error' => $error, 'token' => '', 'warnings' => array());
    };

    $picked = array();

    foreach ((array) $ids as $id) {
        $id = ws_note_onenote_id($id);

        if ($id !== '') {
            $picked[$id] = true;
        }
    }

    if (empty($picked)) {
        return $fail(lang('Pick the pages to import.'));
    }

    if (count($picked) > WS_NOTE_ONENOTE_PICK_MAX) {
        return $fail(lang(array('string' => 'Pick at most {var:1} pages at a time.', 'vars' => WS_NOTE_ONENOTE_PICK_MAX)));
    }

    $status = ws_note_onenote_status($viewer);

    if ($status['state'] !== 'ready') {
        return $fail($status['message']);
    }

    $dir = ws_note_import_stage_dir($token);

    if (($dir === '') || !@mkdir($dir, 0755) || !@mkdir($dir . '/files', 0755)) {
        ws_note_import_log_failure('creating the stage folder of a OneNote import');

        return $fail(lang('The file could not be saved.'), $dir);
    }

    $entries = array();
    $warnings = array();
    $taken = array();
    $last = '';
    $budget = WS_NOTE_IMPORT_FILES_BYTES;
    $files = 0;
    $notes = 0;

    foreach (array_keys($picked) as $id) {
        $base = WS_NOTE_ONENOTE_GRAPH . '/me/onenote/pages/' . rawurlencode($id);
        $meta = ws_note_onenote_get($status['connection'], $base . '?$select=id,title,createdDateTime,lastModifiedDateTime');
        $content = $meta['ok'] ? ws_note_onenote_get($status['connection'], $base . '/content', WS_NOTE_HTML_MAX + 1) : $meta;

        if ($content['ok'] && (strlen($content['body']) > WS_NOTE_HTML_MAX)) {
            $content = array('ok' => false, 'error' => lang(array('string' => 'The web page is larger than {var:1} MB.', 'vars' => (int) floor(WS_NOTE_HTML_MAX / 1048576))));
        }

        $number = $notes + 1;
        $note = $content['ok']
            ? ws_note_onenote_note((array) $meta['data'], $content['body'], ws_note_onenote_download_images($status['connection'], $content['body'], $budget), $number)
            : array('ok' => false, 'error' => $content['error']);

        if (!$note['ok']) {
            $last = $note['error'];
            $warnings[] = lang(array('string' => 'Page {var:1}: {var:2}', 'vars' => array(ws_note_onenote_line($meta['data']['title'] ?? $id), $note['error'])));

            // Throttled or signed out: the next pages would only fail too.
            if (!empty($meta['wait']) || !empty($content['wait']) || !empty($meta['reauth']) || !empty($content['reauth'])) {
                break;
            }

            continue;
        }

        $text = ws_note_onenote_file($note, $id);
        $time = max($note['created'], $note['updated']);

        if (@file_put_contents($dir . '/files/' . $files . '.txt', $text) === false) {
            ws_note_import_log_failure('writing a page of a OneNote import');

            return $fail(lang('The file could not be saved.'), $dir);
        }

        $title = ($note['title'] !== '') ? $note['title'] : lang('Untitled');
        $entries[] = array(ws_note_export_name($title, $number, $taken) . '.md', strlen($text), $time, $files);
        $files++;
        $notes++;

        foreach ($note['files'] as $file) {
            if (@file_put_contents($dir . '/files/' . $files . '.txt', $file['bytes']) === false) {
                ws_note_import_log_failure('writing a picture of a OneNote import');

                return $fail(lang('The file could not be saved.'), $dir);
            }

            $entries[] = array('attachments/' . $number . '/' . $file['name'], strlen($file['bytes']), $time, $files, 1);
            $files++;
        }
    }

    if ($notes === 0) {
        return $fail(($last !== '') ? $last : lang('The page could not be read.'), $dir);
    }

    $stage = array(
        'dir'      => $dir,
        'user'     => (int) $viewer['id'],
        'created'  => time(),
        'zip'      => false,
        'provider' => 'onenote',
        'name'     => 'OneNote',
        'entries'  => $entries,
    );

    if (!ws_note_import_save($stage)) {
        return $fail(lang('The file could not be saved.'), $dir);
    }

    return array('ok' => true, 'error' => '', 'token' => $token, 'warnings' => $warnings);
}
