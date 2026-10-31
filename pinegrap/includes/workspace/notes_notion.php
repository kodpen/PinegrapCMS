<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Notion pages brought in as notes through the site's notion MCP
 * server (the ready-made connector of mcp_catalogue.php). No Notion code of
 * its own: the person's own connection to that server searches and fetches
 * the pages with the server's tools, as tools/list names them, and each page
 * picked is laid out in a stage as a Markdown file with its title, dates,
 * tags and page id in the front matter. From there the import is the file
 * import's (notes_import.php): the same preview, duplicate rules
 * (ws_note_sources, provider notion), limits, batches and links.
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
 * How many pages one pick may bring in: every page is a request to the
 * server while the person waits.
 */
define('WS_NOTE_NOTION_PICK_MAX', 25);

/**
 * How many search results the picker lists.
 */
define('WS_NOTE_NOTION_LIST_MAX', 100);

/* ---------------------------------------------------------------------------
   The server and its tools
   --------------------------------------------------------------------------- */

/**
 * The tool of a server that does $kind (search or fetch): Notion's own name
 * first, then the plain one, then any name ending in it.
 *
 * @param array[] $tools  by name, as ws_mcp_tools_on() gives them
 * @param string  $kind   search or fetch
 * @return array|null
 */
function ws_note_notion_tool_pick($tools, $kind)
{
    $tools = (array) $tools;

    foreach (array('notion-' . $kind, $kind, 'notion_' . $kind) as $name) {
        if (isset($tools[$name]) && is_array($tools[$name])) {
            return $tools[$name];
        }
    }

    foreach ($tools as $name => $tool) {
        if (is_array($tool) && preg_match('/[-_.\/]' . preg_quote($kind, '/') . '$/i', (string) $name)) {
            return $tool;
        }
    }

    return null;
}

/**
 * The argument a tool takes its one value in, read from its input schema:
 * the first of $wanted it has, else its first required string.
 *
 * @param array    $tool
 * @param string[] $wanted
 * @return string '' when the schema offers none
 */
function ws_note_notion_argument_name($tool, $wanted)
{
    $schema = json_decode((string) ($tool['schema'] ?? ''), true);
    $properties = (is_array($schema) && isset($schema['properties']) && is_array($schema['properties'])) ? $schema['properties'] : array();

    foreach ((array) $wanted as $name) {
        if (isset($properties[$name])) {
            return (string) $name;
        }
    }

    foreach ((array) ($schema['required'] ?? array()) as $name) {
        if (is_string($name) && isset($properties[$name]) && (($properties[$name]['type'] ?? 'string') === 'string')) {
            return $name;
        }
    }

    return '';
}

/**
 * Where the person stands with Notion: the server missing or off, their own
 * account not connected, a tool the import needs missing or turned off, or
 * ready with the two tools.
 *
 * @param array|null $server    the notion server's row
 * @param bool       $connected the person's own account to it is active
 * @param array[]    $tools     the tools turned on, by name
 * @return array state (missing, not_connected, no_tool, ready), message, search, fetch
 */
function ws_note_notion_state($server, $connected, $tools)
{
    $out = array('state' => 'missing', 'message' => '', 'search' => null, 'fetch' => null);

    if (!$server || (($server['status'] ?? '') !== 'active') || (($server['auth_kind'] ?? '') !== 'oauth')) {
        $out['message'] = lang('Notion is not set up on this site. An administrator adds the Notion connector under Workspace settings › MCP servers.');

        return $out;
    }

    if (!$connected) {
        $out['state'] = 'not_connected';
        $out['message'] = lang(array('string' => 'Connect your own Notion account first: Workspace settings › MCP servers, @{var:1}, Connect my account.', 'vars' => $server['alias']));

        return $out;
    }

    foreach (array('search', 'fetch') as $kind) {
        $out[$kind] = ws_note_notion_tool_pick($tools, $kind);

        if (!$out[$kind]) {
            $out['state'] = 'no_tool';
            $out['message'] = lang(array('string' => '@{var:1} offers no {var:2} tool, or it is turned off. An administrator reads its tools again under Workspace settings › MCP servers.', 'vars' => array($server['alias'], $kind)));

            return $out;
        }
    }

    $out['state'] = 'ready';

    return $out;
}

/**
 * The site's Notion server: the ready-made connector, whatever alias it was
 * added under, else a server named notion.
 *
 * @return array|null
 */
function ws_note_notion_server()
{
    if (!function_exists('ws_mcp_ready') || !ws_mcp_ready()) {
        return null;
    }

    $servers = ws_mcp_servers();
    $added = ws_mcp_catalogue_added($servers);

    if (isset($added['notion'])) {
        return $added['notion'];
    }

    foreach ($servers as $server) {
        if ((string) $server['alias'] === 'notion') {
            return $server;
        }
    }

    return null;
}

/**
 * ws_note_notion_state() for a person, read from the database. Tools whose
 * calls wait for approval are left out: they write.
 *
 * @param array $viewer
 * @return array as ws_note_notion_state(), with server
 */
function ws_note_notion_status($viewer)
{
    $server = ws_note_notion_server();
    $mine = $server ? ws_mcp_person_account($server, (int) $viewer['id']) : null;
    $tools = array();

    foreach ($server ? ws_mcp_tools_on($server) : array() as $name => $tool) {
        if (empty($tool['approve'])) {
            $tools[$name] = $tool;
        }
    }

    return ws_note_notion_state($server, $mine && ($mine['status'] === 'active'), $tools) + array('server' => $server);
}

/**
 * Calls one of the server's tools with the person's own account.
 *
 * @param array  $server
 * @param int    $user_id
 * @param array  $tool
 * @param array  $arguments
 * @return array ok, error, result
 */
function ws_note_notion_call($server, $user_id, $tool, $arguments)
{
    $conn = ws_mcp_connection($server, WS_MCP_SCREEN_TIMEOUT, (int) $user_id, true);

    if (empty($conn['headers'])) {
        return array('ok' => false, 'error' => ws_mcp_error_text($server, 401, '', true), 'result' => null);
    }

    $out = pg_mcp_client_call_tool($conn, $tool['name'], (object) $arguments);

    // A token the service no longer takes is renewed once.
    if (!$out['ok'] && ws_mcp_reauthorize($conn, $server, $out['status'] ?? 0)) {
        $out = pg_mcp_client_call_tool($conn, $tool['name'], (object) $arguments);
    }

    if (!$out['ok']) {
        return array('ok' => false, 'error' => ws_mcp_error_text($server, $out['status'] ?? 0, $out['error'], $conn['person']), 'result' => null);
    }

    if (!empty($out['task'])) {
        return array('ok' => false, 'error' => lang('The service would answer later; an import cannot wait for it. Try again.'), 'result' => null);
    }

    if ($out['is_error']) {
        $text = pg_mcp_client_result_text($out['result'], 300);

        return array('ok' => false, 'error' => trim(lang('The tool answered with an error.') . ' ' . $text), 'result' => null);
    }

    db("UPDATE ws_mcp_servers SET last_used = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");

    return array('ok' => true, 'error' => '', 'result' => $out['result']);
}

/**
 * The person's Notion pages that match $query, for the picker.
 *
 * @param array  $viewer
 * @param string $query
 * @return array ok, error, pages (ws_note_notion_search_pages())
 */
function ws_note_notion_search($viewer, $query)
{
    $status = ws_note_notion_status($viewer);

    if ($status['state'] !== 'ready') {
        return array('ok' => false, 'error' => $status['message'], 'pages' => array());
    }

    $name = ws_note_notion_argument_name($status['search'], array('query', 'q', 'text'));

    if ($name === '') {
        return array('ok' => false, 'error' => lang(array('string' => 'The {var:1} tool of @{var:2} takes no search words.', 'vars' => array($status['search']['name'], $status['server']['alias']))), 'pages' => array());
    }

    $call = ws_note_notion_call($status['server'], (int) $viewer['id'], $status['search'], array($name => mb_substr(trim((string) $query), 0, 200)));

    if (!$call['ok']) {
        return array('ok' => false, 'error' => $call['error'], 'pages' => array());
    }

    return array('ok' => true, 'error' => '', 'pages' => ws_note_notion_search_pages($call['result']));
}

/* ---------------------------------------------------------------------------
   Reading what the tools answer
   --------------------------------------------------------------------------- */

/**
 * A Notion page id (32 hex characters, lower case) out of an id with or
 * without dashes, or out of a page's address.
 *
 * @param mixed $value
 * @return string '' when it holds none
 */
function ws_note_notion_page_id($value)
{
    $value = is_string($value) ? $value : '';
    $path = preg_replace('/[?#].*$/s', '', $value);

    if (preg_match('/([0-9a-f]{8})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{12})(?![0-9a-f])/i', $path, $match)) {
        return strtolower($match[1] . $match[2] . $match[3] . $match[4] . $match[5]);
    }

    return '';
}

/**
 * A page title as a note's: one line, without the 32-hex page id Notion
 * puts after it in exports and addresses.
 *
 * @param mixed $title
 * @return string
 */
function ws_note_notion_title($title)
{
    $title = trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', is_string($title) ? $title : ''));
    // An address writes the words with dashes, the id joined by one.
    $address = (bool) preg_match('/^[\p{L}\p{N}-]+-[0-9a-f]{32}$/iu', $title);
    $title = trim(preg_replace('/[\s-]+[0-9a-f]{32}$/i', '', $title));

    if ($address) {
        $title = str_replace('-', ' ', $title);
    }

    return mb_substr($title, 0, 200);
}

/**
 * The JSON objects a tool result holds: each text item that decodes to one,
 * and its structured content.
 *
 * @param array|null $result
 * @return array[]
 */
function ws_note_notion_objects($result)
{
    $out = array();

    foreach ((array) ($result['content'] ?? array()) as $item) {
        if (is_array($item) && (($item['type'] ?? '') === 'text') && isset($item['text'])) {
            $decoded = json_decode((string) $item['text'], true);

            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }
    }

    if (isset($result['structuredContent']) && is_array($result['structuredContent'])) {
        $out[] = $result['structuredContent'];
    }

    return $out;
}

/**
 * The pages a search answered, each once: id, title (without its page id,
 * '' when untitled), url, when (last changed, 0 when not given). Databases
 * and other results are left out.
 *
 * @param array|null $result
 * @return array[]
 */
function ws_note_notion_search_pages($result)
{
    $pages = array();

    foreach (ws_note_notion_objects($result) as $object) {
        foreach ((array) ($object['results'] ?? array()) as $item) {
            if (!is_array($item) || (count($pages) >= WS_NOTE_NOTION_LIST_MAX)) {
                continue;
            }

            $type = (string) ($item['type'] ?? ($item['object'] ?? 'page'));
            $url = (is_string($item['url'] ?? null) && preg_match('#^https://#i', $item['url'])) ? $item['url'] : '';
            $id = ws_note_notion_page_id($item['id'] ?? '');
            $id = ($id !== '') ? $id : ws_note_notion_page_id($url);

            if (($type !== 'page') || ($id === '') || isset($pages[$id])) {
                continue;
            }

            $when = $item['timestamp'] ?? ($item['last_edited_time'] ?? '');

            $pages[$id] = array(
                'id'    => $id,
                'title' => ws_note_notion_title($item['title'] ?? ''),
                'url'   => $url,
                'when'  => ws_note_import_time(is_string($when) ? $when : ''),
            );
        }
    }

    return array_values($pages);
}

/**
 * Notion's Markdown as the workspace's: links for page and database
 * mentions, a person's name for a person, the date for a date, an empty
 * block as an empty line, and the blocks Notion wraps in its own tags
 * (callout, toggle, columns) as their text, one indent less.
 *
 * @param string $text
 * @return string
 */
function ws_note_notion_markdown($text)
{
    $text = str_replace("\r\n", "\n", (string) $text);

    if (preg_match('/<content>\n?(.*?)\n?<\/content>/s', $text, $match)) {
        $text = $match[1];
    }

    $text = preg_replace_callback('/<(page|database|mention-page|mention-database|data-source)\s[^>]*?url="([^"]*)"[^>]*?(?:\/>|>(.*?)<\/\1>)/s', function ($match) {
        $words = trim(strip_tags($match[3] ?? ''));

        return '[' . (($words !== '') ? $words : $match[2]) . '](' . $match[2] . ')';
    }, $text);
    $text = preg_replace('/<mention-user\b[^>]*>(.*?)<\/mention-user>/s', '$1', $text);
    $text = preg_replace_callback('/<mention-date\s[^>]*?start="([^"]*)"(?:[^>]*?end="([^"]*)")?[^>]*\/>/', function ($match) {
        return $match[1] . (!empty($match[2]) ? ' – ' . $match[2] : '');
    }, $text);
    $text = preg_replace('/<empty-block\s*\/>/', '', $text);

    // A wrapping block's lines are indented one tab under it.
    $blocks = 'callout|details|columns|column|synced_block|toggle';

    for ($depth = 0; $depth < 5; $depth++) {
        $before = $text;
        $text = preg_replace_callback('/<(' . $blocks . ')\b[^>]*>(.*?)<\/\1>/s', function ($match) {
            return trim(preg_replace('/^\t/m', '', $match[2]), "\n");
        }, $text);

        if ($text === $before) {
            break;
        }
    }

    $text = preg_replace('/<\/?(?:' . $blocks . '|summary|table_of_contents|synced_block_reference|span|unknown|mention-[a-z-]+)\b[^>]*>/', '', $text);

    return trim(preg_replace('/\n{3,}/', "\n\n", $text));
}

/**
 * The page properties a fetch gives (a JSON object between <properties>
 * tags), by lower-case name, a date property's "date:Name:start" read as
 * "name".
 *
 * @param string $text
 * @return array
 */
function ws_note_notion_properties($text)
{
    $out = array();

    if (preg_match('/<properties>\s*(\{.*?\})\s*<\/properties>/s', (string) $text, $match)) {
        foreach ((array) json_decode($match[1], true) as $name => $value) {
            $name = strtolower(trim(preg_replace('/^date:(.*):start$/i', '$1', (string) $name)));
            $out[$name] = $value;
        }
    }

    return $out;
}

/**
 * The tags among a page's properties: Tags, Labels or Etiketler, as a list,
 * a JSON list in a string or words between commas.
 *
 * @param array $properties ws_note_notion_properties()
 * @return string[]
 */
function ws_note_notion_tags($properties)
{
    foreach (array('tags', 'tag', 'labels', 'label', 'etiketler', 'etiket') as $name) {
        if (!isset($properties[$name])) {
            continue;
        }

        $value = $properties[$name];

        if (is_string($value) && is_array(json_decode($value, true))) {
            $value = json_decode($value, true);
        }

        $tags = is_array($value) ? $value : explode(',', (string) $value);
        $out = array();

        foreach ($tags as $tag) {
            $tag = is_scalar($tag) ? trim((string) $tag) : '';

            if ($tag !== '') {
                $out[] = $tag;
            }
        }

        return array_values(array_unique($out));
    }

    return array();
}

/**
 * A fetched page as a note: id, title, body (Markdown), created, updated,
 * tags, url. Not a note (ok false) when it is a database or holds nothing.
 *
 * @param array|null $result the fetch tool's result
 * @param string     $asked  the page id it was fetched by
 * @return array ok, error, id, title, body, created, updated, tags, url
 */
function ws_note_notion_page($result, $asked)
{
    $out = array('ok' => false, 'error' => lang('The page could not be read.'), 'id' => ws_note_notion_page_id($asked), 'title' => '', 'body' => '', 'created' => 0, 'updated' => 0, 'tags' => array(), 'url' => '');
    $objects = ws_note_notion_objects($result);
    $object = array();

    foreach ($objects as $candidate) {
        if (isset($candidate['text']) || isset($candidate['metadata'])) {
            $object = $candidate;
            break;
        }
    }

    if ($object) {
        $text = is_string($object['text'] ?? null) ? $object['text'] : '';
        $type = (string) ($object['metadata']['type'] ?? 'page');

        if ($type !== 'page') {
            $out['error'] = lang('It is not a page: only pages become notes.');

            return $out;
        }
    } else {
        $text = pg_mcp_client_result_text($result, 4 * WS_NOTE_IMPORT_TEXT_MAX);
    }

    $url = is_string($object['url'] ?? null) ? $object['url'] : '';

    if (($url === '') && preg_match('/<page\s[^>]*url="([^"]*)"/', $text, $match)) {
        $url = $match[1];
    }

    $properties = ws_note_notion_properties($text);
    $pick = function ($names) use ($properties, $object) {
        foreach ($names as $name) {
            foreach (array($object[$name] ?? null, $properties[$name] ?? null) as $value) {
                $time = ws_note_import_time(is_string($value) ? $value : '');

                if ($time > 0) {
                    return $time;
                }
            }
        }

        return 0;
    };

    $id = ws_note_notion_page_id($url);
    $out['id'] = ($id !== '') ? $id : $out['id'];
    $out['url'] = preg_match('#^https://#i', $url) ? $url : '';
    $out['title'] = ws_note_notion_title($object['title'] ?? '');
    $out['body'] = ws_note_notion_markdown($text);
    $out['created'] = $pick(array('created_time', 'created time', 'created', 'created at'));
    $out['updated'] = $pick(array('last_edited_time', 'last edited time', 'last edited', 'updated', 'modified'));
    $out['tags'] = ws_note_notion_tags($properties);

    // No title given: the first heading is the title, as a file's is.
    if (($out['title'] === '') && preg_match('/\A[ \t]{0,3}#[ \t]+(.+?)[ \t]*(?:\n|\z)/u', $out['body'], $heading)) {
        $out['title'] = ws_note_notion_title($heading[1]);
        $out['body'] = ltrim((string) substr($out['body'], strlen($heading[0])), "\n");
    }

    if (($out['id'] === '') || (($out['title'] === '') && ($out['body'] === ''))) {
        return $out;
    }

    $out['ok'] = true;
    $out['error'] = '';

    return $out;
}

/**
 * The Markdown file a page is staged as: the front matter the import reads
 * (title, created, updated, the page id it is known by, tags) and its text.
 *
 * @param array $page ws_note_notion_page()
 * @return string
 */
function ws_note_notion_file($page)
{
    return ws_note_enex_file(array(
        'title'   => $page['title'],
        'created' => $page['created'],
        'updated' => $page['updated'],
        'tags'    => $page['tags'],
        'id'      => ws_note_source_id('markdown', array('id' => $page['id'])),
    ), $page['body']);
}

/* ---------------------------------------------------------------------------
   The stage
   --------------------------------------------------------------------------- */

/**
 * Fetches the pages picked and lays them out in a stage as Markdown files,
 * as notes_keep.php lays out a Keep export: from there the file import's
 * plan, batches and finish take them. A page that cannot be fetched or is
 * no page is left out and named in the warnings; nothing is written as a
 * note yet.
 *
 * @param array    $viewer
 * @param string[] $ids    page ids, as the picker listed them
 * @param string   $token  the stage's name
 * @return array ok, error, token, warnings
 */
function ws_note_notion_stage($viewer, $ids, $token)
{
    $fail = function ($error, $dir = '') {
        ws_note_import_remove($dir);

        return array('ok' => false, 'error' => $error, 'token' => '', 'warnings' => array());
    };

    $picked = array();

    foreach ((array) $ids as $id) {
        $id = ws_note_notion_page_id(is_string($id) ? $id : '');

        if ($id !== '') {
            $picked[$id] = true;
        }
    }

    if (empty($picked)) {
        return $fail(lang('Pick the pages to import.'));
    }

    if (count($picked) > WS_NOTE_NOTION_PICK_MAX) {
        return $fail(lang(array('string' => 'Pick at most {var:1} pages at a time.', 'vars' => WS_NOTE_NOTION_PICK_MAX)));
    }

    $status = ws_note_notion_status($viewer);

    if ($status['state'] !== 'ready') {
        return $fail($status['message']);
    }

    $argument = ws_note_notion_argument_name($status['fetch'], array('id', 'url', 'page_id'));

    if ($argument === '') {
        return $fail(lang(array('string' => 'The {var:1} tool of @{var:2} does not say which page to fetch.', 'vars' => array($status['fetch']['name'], $status['server']['alias']))));
    }

    $dir = ws_note_import_stage_dir($token);

    if (($dir === '') || !@mkdir($dir, 0755) || !@mkdir($dir . '/files', 0755)) {
        ws_note_import_log_failure('creating the stage folder of a Notion import');

        return $fail(lang('The file could not be saved.'), $dir);
    }

    $entries = array();
    $warnings = array();
    $taken = array();
    $last = '';

    foreach (array_keys($picked) as $id) {
        $call = ws_note_notion_call($status['server'], (int) $viewer['id'], $status['fetch'], array($argument => $id));
        $page = $call['ok'] ? ws_note_notion_page($call['result'], $id) : array('ok' => false, 'error' => $call['error']);

        if (!$page['ok']) {
            $last = $page['error'];
            $warnings[] = lang(array('string' => 'Page {var:1}: {var:2}', 'vars' => array($id, $page['error'])));
            continue;
        }

        $number = count($entries);
        $text = ws_note_notion_file($page);

        if (@file_put_contents($dir . '/files/' . $number . '.txt', $text) === false) {
            ws_note_import_log_failure('writing a page of a Notion import');

            return $fail(lang('The file could not be saved.'), $dir);
        }

        $title = ($page['title'] !== '') ? $page['title'] : lang('Untitled');
        $entries[] = array(ws_note_export_name($title, $number + 1, $taken) . '.md', strlen($text), max($page['created'], $page['updated']), $number);
    }

    if (empty($entries)) {
        return $fail(($last !== '') ? $last : lang('The page could not be read.'), $dir);
    }

    $stage = array(
        'dir'      => $dir,
        'user'     => (int) $viewer['id'],
        'created'  => time(),
        'zip'      => false,
        'provider' => 'notion',
        'name'     => 'Notion',
        'entries'  => $entries,
    );

    if (!ws_note_import_save($stage)) {
        return $fail(lang('The file could not be saved.'), $dir);
    }

    return array('ok' => true, 'error' => '', 'token' => $token, 'warnings' => $warnings);
}

