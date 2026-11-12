<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - live pages: an HTML document an agent writes, kept as an
 * .html file of the channel with its versions, shown in the guarded frame
 * (includes/fn/guarded_frame.php, live_page.php). The routes are in
 * api_page.php.
 *
 * What marks a page, with no column of its own: channels refuse an .html
 * upload (ws_upload_refused_extensions()), so an .html file reaches a
 * channel only through these routes, and each of its versions is a
 * file_versions row of format html that the routes wrote. A file with no
 * such row is no page and is never shown with its script.
 *
 * A version row keeps the page's bytes under data/file_versions/ and, as
 * its document, who made it: {"page": 1, "by": {id, kind, handle}}.
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
 * How many versions of a page are kept: the first and the newest after it.
 *
 * @return int
 */
function ws_page_versions_keep()
{
    return 20;
}

/**
 * The name a page's file is kept under: the name given without an
 * extension or the letters a file name cannot carry, else "Page", always
 * .html. Pure but for lang().
 *
 * @param mixed $name
 * @return string
 */
function ws_page_file_name($name)
{
    $base = trim(preg_replace('/\.[a-z0-9]{2,5}$/i', '', is_string($name) ? $name : ''));
    $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', ' ', $base));

    if ($base === '') {
        $base = lang('Page');
    }

    return mb_substr($base, 0, 120) . '.html';
}

/**
 * Why a page's HTML is refused, or ''. Pure.
 *
 * @param mixed $html
 * @return string '' when it may be kept; empty, too_large or not_utf8
 */
function ws_page_html_error($html)
{
    if (!is_string($html) || (trim($html) === '')) {
        return 'empty';
    }

    if (strlen($html) > PG_GUARDED_FRAME_MAX_BYTES) {
        return 'too_large';
    }

    if (!preg_match('//u', $html)) {
        return 'not_utf8';
    }

    return '';
}

/**
 * The refusal's words for ws_page_html_error().
 *
 * @param string $error
 * @return string
 */
function ws_page_html_error_text($error)
{
    if ($error === 'too_large') {
        return lang(array('string' => 'A page is at most {var:1} KB.', 'vars' => (int) (PG_GUARDED_FRAME_MAX_BYTES / 1024)));
    }

    if ($error === 'not_utf8') {
        return lang('A page must be written in UTF-8.');
    }

    return lang('A page needs its HTML.');
}

/**
 * The document a version row keeps: who made it. Pure.
 *
 * @param array    $agent     id, kind, handle
 * @param string[] $libraries the site's libraries it names
 * @return string JSON
 */
function ws_page_version_doc($agent, $libraries = array())
{
    return json_encode(array('page' => 1, 'by' => array(
        'id'     => (int) ($agent['id'] ?? 0),
        'kind'   => (string) ($agent['kind'] ?? ''),
        'handle' => (string) ($agent['handle'] ?? ''),
    ), 'libraries' => array_values($libraries)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * The agent a version's document names, or null. Pure.
 *
 * @param mixed $doc_json
 * @return array|null id, kind, handle
 */
function ws_page_version_by($doc_json)
{
    $doc = json_decode((string) $doc_json, true);
    $by = (is_array($doc) && isset($doc['by']) && is_array($doc['by'])) ? $doc['by'] : null;

    if (!$by || !isset($by['handle']) || !is_string($by['handle']) || ($by['handle'] === '')) {
        return null;
    }

    return array('id' => (int) ($by['id'] ?? 0), 'kind' => (string) ($by['kind'] ?? ''), 'handle' => $by['handle']);
}

/**
 * The version ids the retention removes: all but version 1 and the newest
 * ws_page_versions_keep() - 1, oldest first. Pure.
 *
 * @param array[] $rows version_id, version_no
 * @return int[]
 */
function ws_page_versions_pruned($rows)
{
    $later = array();

    foreach ($rows as $row) {
        if ((int) $row['version_no'] > 1) {
            $later[(int) $row['version_no']] = (int) $row['version_id'];
        }
    }

    krsort($later);
    $removed = array_slice($later, ws_page_versions_keep() - 1, null, true);
    ksort($removed);

    return array_values($removed);
}

/**
 * The versions of a page, oldest first, without their bytes; empty for a
 * file that is no page.
 *
 * @param int $file_id
 * @return array[]
 */
function ws_page_versions($file_id)
{
    if (((int) $file_id <= 0) || !pg_file_versions_ready()) {
        return array();
    }

    $rows = db_items("SELECT version_id, version_no, doc_json, size, user_id, source, created_at, stored_name
        FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND format = 'html' ORDER BY version_no");

    return is_array($rows) ? $rows : array();
}

/**
 * Is this file a page: an .html file with a version the routes wrote?
 *
 * @param int $file_id
 * @return bool
 */
function ws_page_is_page($file_id)
{
    return ((int) $file_id > 0) && pg_file_versions_ready()
        && ((int) db_value("SELECT COUNT(*) FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND format = 'html'") > 0);
}

/**
 * Keeps the page's bytes as its next version.
 *
 * @param int      $file_id
 * @param string   $html
 * @param array    $agent     id, kind, handle
 * @param int      $user_id   the application's owner
 * @param string[] $libraries the site's libraries it names
 * @return array|null the row, or null when it could not be written
 */
function ws_page_version_add($file_id, $html, $agent, $user_id, $libraries = array())
{
    $dir = pg_file_versions_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $version_no = (int) db_value("SELECT MAX(version_no) FROM file_versions WHERE file_id = '" . (int) $file_id . "'") + 1;
    $name = pg_file_versions_stored_name($file_id, $version_no, 'html', sha1(uniqid((string) mt_rand(), true) . $html));
    $path = $dir . '/' . $name;

    if (($name === '') || !pg_file_write_whole($path, $html)) {
        return null;
    }

    $doc = ws_page_version_doc($agent, $libraries);
    $saved = db_q("INSERT INTO file_versions (file_id, version_no, doc_json, doc_hash, stored_name, format, width, height, size, redacted, user_id, source, created_at)
        VALUES (?, ?, ?, ?, ?, 'html', 0, 0, ?, 0, ?, 'agent', UNIX_TIMESTAMP())", array(
        (int) $file_id, $version_no, $doc, sha1($html), $name, strlen($html), (int) $user_id));

    if (!$saved) {
        @unlink($path);

        return null;
    }

    return pg_file_versions_row($file_id, mysqli_insert_id(db::$con));
}

/**
 * Keeps the first version and the newest after it.
 *
 * @param int $file_id
 */
function ws_page_versions_prune($file_id)
{
    $rows = ws_page_versions($file_id);
    $ids = ws_page_versions_pruned($rows);

    foreach ($rows as $row) {
        if (in_array((int) $row['version_id'], $ids, true)) {
            pg_file_versions_delete_row($row);
        }
    }
}

/**
 * Writes a new version of a page into its file and keeps it.
 *
 * @param array    $message   ws_messages row of the page
 * @param string   $html      checked by ws_page_html_error()
 * @param array    $agent
 * @param int      $user_id
 * @param string[] $libraries the site's libraries it names
 * @return bool
 */
function ws_page_write($message, $html, $agent, $user_id, $libraries = array())
{
    $file_id = (int) $message['file_id'];
    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'");

    // Every write goes to the file directory, which the general job takes
    // as the file's bytes (pg_file_storage_reconcile_run()).
    $name = basename($stored);
    $path = FILE_DIRECTORY_PATH . '/' . $name;

    if (($stored === '') || !pg_file_write_whole($path, $html)) {
        return false;
    }

    db_q("UPDATE files SET size = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array(strlen($html), $file_id));

    if (function_exists('pg_file_cache_forget')) {
        pg_file_cache_forget($file_id);
    }

    if (!ws_page_version_add($file_id, $html, $agent, $user_id, $libraries)) {
        return false;
    }

    ws_page_versions_prune($file_id);

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $message['id']);
    }

    return true;
}

/**
 * A page's versions as the API gives them, oldest first. Pure but for
 * api_time() when given.
 *
 * @param array[]       $rows ws_page_versions()
 * @param callable|null $time formats created_at
 * @return array[] version, source, user_id, agent_id, agent, size, created_at
 */
function ws_page_versions_read($rows, $time = null)
{
    $read = array();

    foreach ($rows as $row) {
        $by = ws_page_version_by($row['doc_json'] ?? '');

        $read[] = array(
            'version'    => (int) $row['version_no'],
            'source'     => (string) $row['source'],
            'user_id'    => ((int) $row['user_id'] > 0) ? (int) $row['user_id'] : null,
            'agent_id'   => ($by && ($by['id'] > 0)) ? $by['id'] : null,
            'agent'      => $by ? $by['handle'] : null,
            'size'       => (int) $row['size'],
            'created_at' => $time ? $time((int) $row['created_at']) : (int) $row['created_at'],
        );
    }

    return $read;
}

/**
 * The libraries a page may name, by the name a call gives: each a copy the
 * site ships, put into the page by the site (ws_page_with_libraries()); a
 * page never loads one from an address. Pure.
 *
 * @return array[] name => path (under the software directory), title
 */
function ws_page_libraries()
{
    return array(
        'chartjs' => array('path' => 'assets/lib/chartjs/chart.umd.min.js', 'title' => 'Chart.js 4.4.7'),
    );
}

/**
 * The libraries a call names, checked: known names only, each once, in the
 * order given. Pure.
 *
 * @param mixed $names
 * @return array names, error ('' or the unknown name)
 */
function ws_page_libraries_check($names)
{
    if (($names === null) || ($names === '')) {
        return array('names' => array(), 'error' => '');
    }

    if (!is_array($names)) {
        return array('names' => array(), 'error' => '?');
    }

    $known = ws_page_libraries();
    $picked = array();

    foreach ($names as $name) {
        $name = is_string($name) ? strtolower(trim($name)) : '';

        if (!isset($known[$name])) {
            return array('names' => array(), 'error' => ($name !== '') ? $name : '?');
        }

        if (!in_array($name, $picked, true)) {
            $picked[] = $name;
        }
    }

    return array('names' => $picked, 'error' => '');
}

/**
 * The libraries a version's document names. Pure.
 *
 * @param mixed $doc_json
 * @return string[]
 */
function ws_page_version_libraries($doc_json)
{
    $doc = json_decode((string) $doc_json, true);
    $checked = ws_page_libraries_check((is_array($doc) && isset($doc['libraries'])) ? $doc['libraries'] : null);

    return $checked['names'];
}

/**
 * The page with the libraries' scripts put in ahead of it: right after its
 * doctype when it has one (a script before <html> lands in the head and the
 * page keeps its standards mode), else first. A source holding "</script"
 * or "<!--" would end or swallow its element, so it is left out. Pure.
 *
 * @param string   $html
 * @param string[] $sources the libraries' code
 * @return string
 */
function ws_page_inline_scripts($html, $sources)
{
    $scripts = '';

    foreach ($sources as $source) {
        if (!is_string($source) || ($source === '') || (stripos($source, '</script') !== false) || (strpos($source, '<!--') !== false)) {
            continue;
        }

        $scripts .= '<script>' . $source . "</script>\n";
    }

    if ($scripts === '') {
        return (string) $html;
    }

    if (preg_match('/^\s*<!DOCTYPE[^>]*>/i', (string) $html, $match)) {
        return $match[0] . "\n" . $scripts . substr((string) $html, strlen($match[0]));
    }

    return $scripts . $html;
}

/**
 * The page as the frame shows it: with the site's own copy of each library
 * it names.
 *
 * @param string   $html
 * @param string[] $names checked by ws_page_libraries_check()
 * @return string
 */
function ws_page_with_libraries($html, $names)
{
    $known = ws_page_libraries();
    $sources = array();

    foreach ($names as $name) {
        if (isset($known[$name])) {
            $code = @file_get_contents(PG_FUNCTIONS_DIR . '/' . $known[$name]['path']);

            if (is_string($code)) {
                $sources[] = $code;
            }
        }
    }

    return ws_page_inline_scripts($html, $sources);
}

/**
 * The libraries the newest version of a page names.
 *
 * @param int $file_id
 * @return string[]
 */
function ws_page_libraries_of($file_id)
{
    $rows = ws_page_versions($file_id);

    return $rows ? ws_page_version_libraries(end($rows)['doc_json']) : array();
}
