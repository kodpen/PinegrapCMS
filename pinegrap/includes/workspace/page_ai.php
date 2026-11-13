<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - live pages made, read and edited by the built-in assistant
 * and the model assistants, as tools of ws_ai_step() (ai.php). The same
 * storage, version and library rules as the API routes of api_page.php:
 * the page is kept as an .html file of the channel posted as the
 * assistant's application under the request, each version a
 * file_versions row the guarded frame recognises (page.php).
 *
 * An edit is a whole new document, as on the API; the model may write it
 * whole or as replacements of exact passages of the newest version, which
 * the site applies before keeping the result as that whole document.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');

/**
 * The names of the live page tools. None starts with page_ or is
 * read_page: those are the Visual Page Editor's tools.
 *
 * @return string[]
 */
function ws_page_ai_tool_names()
{
    return array('make_live_page', 'read_live_page', 'edit_live_page');
}

/**
 * The most characters of a page's HTML one read gives the model: the tool
 * answer is cut at 12000 characters as a whole, so a longer page is read
 * in parts (from).
 *
 * @return int
 */
function ws_page_ai_read_max()
{
    return 10000;
}

/**
 * The most replacements one edit takes.
 *
 * @return int
 */
function ws_page_ai_replacements_max()
{
    return 20;
}

/**
 * The libraries the model names, written as a JSON list or as names
 * separated by commas (its arguments are flat), checked against the
 * site's list. Pure.
 *
 * @param mixed $value
 * @return array names, error ('' or the unknown name), given (false when left
 *               out or empty; none gives no library)
 */
function ws_page_ai_libraries($value)
{
    if (($value === null) || (is_string($value) && (trim($value) === ''))) {
        return array('names' => array(), 'error' => '', 'given' => false);
    }

    if (is_string($value) && (strtolower(trim($value)) === 'none')) {
        return array('names' => array(), 'error' => '', 'given' => true);
    }

    if (is_string($value)) {
        $decoded = json_decode(trim($value), true);
        $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    }

    return ws_page_libraries_check(is_array($value) ? array_values($value) : $value) + array('given' => true);
}

/**
 * The replacements of edit_live_page, written by the model as a JSON list
 * of {find, replace} (one object alone is a list of one). Pure.
 *
 * @param mixed $value
 * @return array[]|null null when it is no list of such objects
 */
function ws_page_ai_replacements($value)
{
    if (is_string($value)) {
        $value = json_decode(trim($value), true);
    }

    if (is_array($value) && isset($value['find'])) {
        $value = array($value);
    }

    if (!is_array($value) || ($value === array()) || (array_values($value) !== $value) || (count($value) > ws_page_ai_replacements_max())) {
        return null;
    }

    foreach ($value as $i => $item) {
        if (!is_array($item) || !isset($item['find']) || !is_string($item['find']) || ($item['find'] === '')
            || (isset($item['replace']) && !is_string($item['replace']))) {
            return null;
        }

        $value[$i] = array('find' => $item['find'], 'replace' => (string) ($item['replace'] ?? ''));
    }

    return $value;
}

/**
 * The page with the replacements applied in order, each to the text the
 * ones before it left: its find must stand there exactly once, else
 * nothing is changed. Pure.
 *
 * @param string  $html
 * @param array[] $replacements ws_page_ai_replacements()
 * @return array html, error ('' / missing / ambiguous), index (of the refused one, or -1)
 */
function ws_page_ai_apply($html, $replacements)
{
    foreach ($replacements as $i => $item) {
        $count = substr_count($html, $item['find']);

        if ($count !== 1) {
            return array('html' => '', 'error' => ($count === 0) ? 'missing' : 'ambiguous', 'index' => $i);
        }

        $at = strpos($html, $item['find']);
        $html = substr($html, 0, $at) . $item['replace'] . substr($html, $at + strlen($item['find']));
    }

    return array('html' => $html, 'error' => '', 'index' => -1);
}

/**
 * A page as the model reads it: its versions with their makers, the
 * libraries, and its HTML from a place on, at most ws_page_ai_read_max()
 * characters, with where the next part starts. Pure.
 *
 * @param string  $html
 * @param array[] $rows       ws_page_versions(), oldest first
 * @param int     $message_id
 * @param int     $from       the character the part starts at
 * @return array
 */
function ws_page_ai_present($html, $rows, $message_id, $from = 0)
{
    $length = mb_strlen($html);
    $from = max(0, min((int) $from, $length));
    $part = mb_substr($html, $from, ws_page_ai_read_max());
    $end = $from + mb_strlen($part);
    $last = $rows ? end($rows) : array('version_no' => 0, 'doc_json' => '');
    $versions = array();

    foreach (ws_page_versions_read($rows) as $version) {
        $version['created_at'] = gmdate('Y-m-d H:i', (int) $version['created_at']) . ' UTC';

        // The model reads only what a version has.
        $versions[] = array_filter($version, function ($value) {
            return $value !== null;
        });
    }

    return array(
        'message_id' => (int) $message_id,
        'version'    => (int) $last['version_no'],
        'libraries'  => ws_page_version_libraries($last['doc_json']),
        'versions'   => $versions,
        'length'     => $length,
        'from'       => $from,
        'next'       => ($end < $length) ? $end : null,
        'html'       => $part,
    );
}

/**
 * Are the live page tools offered for this request? Where the canvas
 * tools are: a request asked in a channel the assistant may write in, on
 * a site with the versions table, which marks a page.
 *
 * @param array      $row
 * @param array|null $asker
 * @return bool
 */
function ws_page_ai_offered($row, $asker)
{
    return ws_canvas_ai_offered($row, $asker);
}

/**
 * The live page tools, in the chat API's function format.
 *
 * @param callable $tool ws_ai_tools()'s maker
 * @return array[]
 */
function ws_page_ai_tools($tool)
{
    $guard = 'It runs in a sealed frame: no request out of it (no fetch, no script, picture or font from an address; pictures and fonts only as data: inside the document), no cookie or storage, no window or download.';
    $libraries = 'The site\'s libraries put into the page ahead of its own script, as names separated by commas: ' . implode(', ', array_map(function ($name, $library) {
        return $name . ' (' . $library['title'] . ')';
    }, array_keys(ws_page_libraries()), ws_page_libraries())) . '.';

    return array(
        $tool('make_live_page', 'Post a live page into the channel, only when the request asks for something people use rather than look at: a chart that shows a value on hover, a filter, a calculator, a small tool or a presentation. It is one whole HTML document, its styles and script inside it, posted under the request; people open it in the channel. ' . $guard . ' A still picture is draw_canvas. Still call answer at the end.', array(
            'html'      => array('type' => 'string', 'description' => 'One whole HTML document (<!DOCTYPE html>...), inline <style> and <script> only, UTF-8, at most ' . (int) (PG_GUARDED_FRAME_MAX_BYTES / 1024) . ' KB.'),
            'name'      => array('type' => 'string', 'description' => 'The page\'s name, optional.'),
            'text'      => array('type' => 'string', 'description' => 'Words beside the page, optional.'),
            'libraries' => array('type' => 'string', 'description' => $libraries . ' Optional.'),
        ), array('html')),
        $tool('read_live_page', 'Read a live page of this channel: its newest version, who made each version, its libraries and its HTML, ' . ws_page_ai_read_max() . ' characters at a time (next is where the following part starts, or null at the end). Read it before edit_live_page.', array(
            'message_id' => array('type' => 'integer', 'description' => 'The message the page is in.'),
            'from'       => array('type' => 'integer', 'description' => 'The character to start at, as next gave it; 0 when left out.'),
        ), array('message_id')),
        $tool('edit_live_page', 'Change a live page of this channel, only when the request asks to change it. It is kept as a new version made by you; the earlier ones stay. Give either html (the whole document anew) or replacements (exact passages of the newest version changed, all or none). Still call answer at the end.', array(
            'message_id'   => array('type' => 'integer', 'description' => 'The message the page is in.'),
            'version'      => array('type' => 'integer', 'description' => 'The newest version, as read_live_page gave it.'),
            'html'         => array('type' => 'string', 'description' => 'The whole document anew.'),
            'replacements' => array('type' => 'string', 'description' => 'A JSON list of at most ' . ws_page_ai_replacements_max() . ' objects such as [{"find": "<h1>Sales</h1>", "replace": "<h1>Sales 2026</h1>"}], applied in order; each find appears exactly once in the page as the ones before it leave it.'),
            'libraries'    => array('type' => 'string', 'description' => $libraries . ' Those of the version before when left out; none for no library.'),
        ), array('message_id', 'version')),
    );
}

/**
 * A live page message of the request's channel, or the refusal.
 *
 * @param array $channel
 * @param int   $id
 * @return array message, rows, html; or error
 */
function ws_page_ai_target($channel, $id)
{
    $message = ((int) $id > 0) ? ws_message((int) $id) : null;

    if (!$message || ((int) $message['deleted_at'] > 0) || ((int) $message['channel_id'] !== (int) $channel['id']) || (function_exists('ws_message_in_past') && ws_message_in_past($message))) {
        return array('error' => 'There is no such message in this channel.');
    }

    $rows = ws_page_versions((int) $message['file_id']);
    $stored = $rows ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'") : '';
    $path = ($stored !== '') ? pg_file_reader_path($stored) : '';
    $html = ($path !== '') ? @file_get_contents($path) : false;

    if (!$rows || !is_string($html)) {
        return array('error' => 'That message carries no live page.');
    }

    return array('message' => $message, 'rows' => $rows, 'html' => $html);
}

/**
 * The refusal's words for the model.
 *
 * @param string $error ws_page_html_error() or ws_page_ai_apply()
 * @return string
 */
function ws_page_ai_error_text($error)
{
    if ($error === 'missing') {
        return 'Its find is not in the page: read it again with read_live_page and copy the passage exactly.';
    }

    if ($error === 'ambiguous') {
        return 'Its find appears more than once in the page: take a longer passage that appears once.';
    }

    if ($error === 'too_large') {
        return 'A page is at most ' . (int) (PG_GUARDED_FRAME_MAX_BYTES / 1024) . ' KB.';
    }

    if ($error === 'not_utf8') {
        return 'A page must be written in UTF-8.';
    }

    return 'A page needs its HTML: one whole document.';
}

/**
 * Runs a live page tool.
 *
 * @param array  $row
 * @param array  $state
 * @param string $name
 * @param array  $args
 * @param array  $asker
 * @return array what the model is told
 */
function ws_page_ai_tool_run($row, &$state, $name, $args, $asker)
{
    if (!ws_page_ai_offered($row, $asker)) {
        return array('error' => 'A live page cannot be made or changed for this request.');
    }

    $channel = ws_ai_request_channel($row, $asker);
    $app = ws_ai_app();
    $writer = ws_assistant_writer(ws_ai_owner(), $asker, $channel);
    $by = ws_canvas_ai_by(ws_model_running());
    $known = implode(', ', array_keys(ws_page_libraries()));

    if ($name === 'make_live_page') {
        $html = $args['html'] ?? null;
        $error = ws_page_html_error($html);

        if ($error !== '') {
            return array('error' => ws_page_ai_error_text($error));
        }

        $libraries = ws_page_ai_libraries($args['libraries'] ?? null);

        if ($libraries['error'] !== '') {
            return array('error' => 'The site has no library named ' . $libraries['error'] . '. It has: ' . $known . '.');
        }

        $text = ws_tokens_normalise(trim(mb_substr((string) ($args['text'] ?? ''), 0, 4000)));
        $options = array('app_id' => (int) $app['id'], 'parent_id' => (int) $row['message_id']);
        // The checks of the message, made before anything is kept; the
        // refusal of an .html upload is not among them (api_page.php).
        $checked = ws_message_send_check($writer, $channel, $text, $options + array('file_id' => PHP_INT_MAX));

        if (!$checked['ok']) {
            return array('error' => (string) $checked['error']);
        }

        $stored = ws_store_file_bytes($channel, ws_page_file_name($args['name'] ?? ''), 'html', $html, (int) $writer['id']);

        if (!$stored['ok']) {
            return array('error' => (string) $stored['error']);
        }

        if (!ws_page_version_add($stored['file_id'], $html, $by, (int) $writer['id'], $libraries['names'])) {
            ws_upload_discard($stored['file_id']);

            return array('error' => 'The page could not be saved.');
        }

        $sent = ws_message_send($writer, $channel, $text, $options + array('file_id' => $stored['file_id'], 'file_name' => $stored['name']));

        if (empty($sent['ok'])) {
            pg_file_versions_delete_file($stored['file_id']);
            ws_upload_discard($stored['file_id']);

            return array('error' => (string) $sent['error']);
        }

        $state['live_page_done'] = 1;

        return array('ok' => true, 'message_id' => (int) $sent['message_id'], 'version' => 1,
            'note' => 'The live page is posted under the request; people open it there. Do not post it again.');
    }

    $target = ws_page_ai_target($channel, (int) ($args['message_id'] ?? 0));

    if (isset($target['error'])) {
        return $target;
    }

    if ($name === 'read_live_page') {
        return ws_page_ai_present($target['html'], $target['rows'], (int) $target['message']['id'], (int) ($args['from'] ?? 0));
    }

    $last = end($target['rows']);
    $newest = (int) $last['version_no'];

    if ((int) ($args['version'] ?? 0) !== $newest) {
        return array('error' => 'The page changed since: its newest version is ' . $newest . '. Read it again with read_live_page.');
    }

    $whole = isset($args['html']) && is_string($args['html']) && (trim($args['html']) !== '');
    $listed = isset($args['replacements']) && !(is_string($args['replacements']) && (trim($args['replacements']) === ''));

    if ($whole === $listed) {
        return array('error' => 'Give either html, the whole document anew, or replacements, not both and not neither.');
    }

    if ($whole) {
        $html = $args['html'];
    } else {
        $replacements = ws_page_ai_replacements($args['replacements']);

        if ($replacements === null) {
            return array('error' => 'Write replacements as a JSON list of at most ' . ws_page_ai_replacements_max() . ' objects, each with find (not empty) and replace.');
        }

        $applied = ws_page_ai_apply($target['html'], $replacements);

        if ($applied['error'] !== '') {
            return array('error' => 'Replacement ' . $applied['index'] . ': ' . ws_page_ai_error_text($applied['error']));
        }

        $html = $applied['html'];
    }

    $error = ws_page_html_error($html);

    if ($error !== '') {
        return array('error' => ws_page_ai_error_text($error));
    }

    // Left out, the libraries of the version before stay.
    $libraries = ws_page_ai_libraries($args['libraries'] ?? null);

    if ($libraries['error'] !== '') {
        return array('error' => 'The site has no library named ' . $libraries['error'] . '. It has: ' . $known . '.');
    }

    $names = $libraries['given'] ? $libraries['names'] : ws_page_version_libraries($last['doc_json']);

    if (!ws_page_write($target['message'], $html, $by, (int) $writer['id'], $names)) {
        return array('error' => 'The page could not be saved.');
    }

    $state['live_page_done'] = 1;

    return array('ok' => true, 'message_id' => (int) $target['message']['id'], 'version' => $newest + 1);
}
